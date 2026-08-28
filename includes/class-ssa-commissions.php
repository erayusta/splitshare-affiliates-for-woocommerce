<?php
/**
 * Komisyon kayıtları: sipariş olaylarından oluşturma, iade/iptal, bekleme→onay, toplamlar.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Commissions {

	private static function table() {
		return SSA_Install::tables()['commissions'];
	}

	public static function init() {
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'on_paid' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'on_paid' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_completed' ) );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'on_cancelled' ) );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'on_cancelled' ) );
		add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'on_cancelled' ) );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'on_refunded' ), 10, 2 );
	}

	/* ---------- Olaylar ---------- */

	public static function on_paid( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			self::record( $order );
		}
	}

	public static function on_completed( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$c = self::record( $order );
		if ( $c && 'pending' === $c->status ) {
			// Bekleme süresi tamamlanma tarihinden başlar.
			self::update( $c->id, array( 'available_at' => self::available_at( $order ) ) );
		}
	}

	public static function on_cancelled( $order_id ) {
		self::void( $order_id, 'order_' . ( wc_get_order( $order_id ) ? wc_get_order( $order_id )->get_status() : 'cancelled' ) );
	}

	public static function on_refunded( $order_id, $refund_id ) {
		self::recalculate( $order_id );
	}

	/* ---------- Hesap ---------- */

	/**
	 * Siparişten komisyon kaydı oluşturur/günceller (upsert by order_id).
	 * @return object|null commission row
	 */
	public static function record( WC_Order $order ) {
		$partner_id = (int) $order->get_meta( '_ssa_partner_id' );
		if ( ! $partner_id ) {
			return null;
		}
		$existing = self::for_order( $order->get_id() );
		if ( $existing && in_array( $existing->status, array( 'paid', 'void' ), true ) ) {
			return $existing;
		}
		$partner = SSA_Partners::get( $partner_id );
		if ( ! $partner ) {
			return null;
		}
		$calc = self::calculate_for_order( $order, $partner );
		$now  = current_time( 'mysql' );
		$data = array(
			'order_id'         => $order->get_id(),
			'partner_id'       => $partner_id,
			'attribution'      => (string) $order->get_meta( '_ssa_attribution' ),
			'code_used'        => (string) $order->get_meta( '_ssa_code' ),
			'order_total_base' => $calc['base_total'],
			'discount_pct'     => $calc['ctx']['discount_pct'],
			'commission_pct'   => $calc['ctx']['commission_pct'],
			'breakdown'        => wp_json_encode( array( 'items' => $calc['breakdown'], 'factors' => $calc['factors'], 'rules' => $calc['ctx']['rules'] ) ),
			'amount'           => $calc['amount'],
			'status'           => $calc['amount'] > 0 ? 'pending' : 'void',
			'reason'           => (string) $calc['reason'],
			'available_at'     => self::available_at( $order ),
			'is_new_customer'  => $calc['ctx']['is_new_customer'] ? 1 : 0,
			'updated_at'       => $now,
		);
		global $wpdb;
		if ( $existing ) {
			if ( 'approved' === $existing->status && $calc['amount'] > 0 ) {
				$data['status'] = 'approved';
			}
			$wpdb->update( self::table(), $data, array( 'id' => $existing->id ) ); // phpcs:ignore WordPress.DB
			$id = $existing->id;
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( self::table(), $data ); // phpcs:ignore WordPress.DB
			$id = (int) $wpdb->insert_id;
			$order->add_order_note( sprintf(
				/* translators: 1: partner, 2: amount, 3: reason */
				__( 'Affiliate commission recorded for %1$s: %2$s %3$s', 'splitshare-affiliates' ),
				$partner->display_name(),
				wp_strip_all_tags( wc_price( $calc['amount'] ) ),
				$calc['reason'] ? '(' . $calc['reason'] . ')' : ''
			) );
		}
		return self::get( $id );
	}

	/** Sipariş + ortak için hesaplayıcıyı çalıştırır. */
	public static function calculate_for_order( WC_Order $order, SSA_Partner $partner ) {
		$items = self::build_items( $order );
		$base  = 0.0;
		foreach ( $items as $it ) {
			$base += $it['paid_total'];
		}
		$ctx = array(
			'commission_pct'   => (float) $partner->commission_pct,
			'discount_pct'     => (float) $partner->discount_pct,
			'discount_applied' => 'coupon' === $order->get_meta( '_ssa_attribution' ),
			'order_base_total' => round( $base, 2 ),
			'is_new_customer'  => self::is_new_customer( $order ),
			'rules'            => SSA_Settings::rules( $order->get_date_created() ? $order->get_date_created()->getTimestamp() : null ),
		);
		$ctx = apply_filters( 'ssa_commission_context', $ctx, $order, $partner );
		$out = SSA_Calculator::calculate( $items, $ctx );
		$out['ctx'] = $ctx;
		return $out;
	}

	/** Kalemler: iade sonrası kalan tutar, KDV dahil; kategoriler + ataları. */
	public static function build_items( WC_Order $order ) {
		$items = array();
		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			$product  = $item->get_product();
			$pid      = $product ? ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() ) : (int) $item->get_product_id();
			$paid     = (float) $order->get_line_total( $item, true, false );
			$paid     = max( 0.0, $paid - self::refunded_for_item( $order, $item_id ) );
			if ( $paid <= 0 ) {
				continue;
			}
			$cats = array();
			foreach ( (array) wc_get_product_term_ids( $pid, 'product_cat' ) as $cid ) {
				$cats[] = (int) $cid;
				foreach ( get_ancestors( $cid, 'product_cat', 'taxonomy' ) as $anc ) {
					$cats[] = (int) $anc;
				}
			}
			$items[] = array( 'product_id' => $pid, 'category_ids' => array_values( array_unique( $cats ) ), 'paid_total' => round( $paid, 4 ) );
		}
		return $items;
	}

	/** Kalem için iade edilen tutar (KDV dahil). */
	public static function refunded_for_item( WC_Order $order, $item_id ) {
		$total = 0.0;
		foreach ( $order->get_refunds() as $refund ) {
			foreach ( $refund->get_items( 'line_item' ) as $ritem ) {
				if ( (int) $ritem->get_meta( '_refunded_item_id' ) === (int) $item_id ) {
					$total += abs( (float) $ritem->get_total() ) + abs( (float) $ritem->get_total_tax() );
				}
			}
		}
		return $total;
	}

	/** Müşterinin bu siparişten önce tamamlanmış siparişi yok mu? */
	public static function is_new_customer( WC_Order $order ) {
		$args = array( 'limit' => 1, 'return' => 'ids', 'status' => array( 'wc-completed', 'wc-processing' ), 'exclude' => array( $order->get_id() ), 'date_created' => '<' . ( $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time() ) );
		if ( $order->get_customer_id() ) {
			$args['customer_id'] = $order->get_customer_id();
		} elseif ( $order->get_billing_email() ) {
			$args['billing_email'] = $order->get_billing_email();
		} else {
			return true;
		}
		return count( wc_get_orders( $args ) ) === 0;
	}

	private static function available_at( WC_Order $order ) {
		$hold = (int) SSA_Settings::get( 'hold_days' );
		$from = $order->get_date_completed() ? $order->get_date_completed()->getTimestamp() : current_time( 'timestamp' );
		return gmdate( 'Y-m-d H:i:s', $from + $hold * DAY_IN_SECONDS );
	}

	/** İade/iptal sonrası yeniden hesap; ödenmişse fark için negatif düzeltme. */
	public static function recalculate( $order_id ) {
		$order = wc_get_order( $order_id );
		$c     = self::for_order( $order_id );
		if ( ! $order || ! $c ) {
			return null;
		}
		$partner = SSA_Partners::get( $c->partner_id );
		if ( ! $partner ) {
			return null;
		}
		$calc = self::calculate_for_order( $order, $partner );
		$new  = (float) $calc['amount'];
		if ( 'paid' === $c->status ) {
			$diff = round( $new - (float) $c->amount, 2 );
			if ( $diff < 0 && 'yes' === SSA_Settings::get( 'deduct_refunds_from_next_payout' ) ) {
				self::add_adjustment( $c, $diff, $order );
			}
			return $c;
		}
		self::update( $c->id, array(
			'amount'           => $new,
			'order_total_base' => $calc['base_total'],
			'breakdown'        => wp_json_encode( array( 'items' => $calc['breakdown'], 'factors' => $calc['factors'], 'rules' => $calc['ctx']['rules'] ) ),
			'status'           => $new > 0 ? ( 'approved' === $c->status ? 'approved' : 'pending' ) : 'void',
			'reason'           => $new > 0 ? (string) $calc['reason'] : 'refunded',
		) );
		$order->add_order_note( sprintf( __( 'Affiliate commission recalculated: %s', 'splitshare-affiliates' ), wp_strip_all_tags( wc_price( $new ) ) ) );
		return self::get( $c->id );
	}

	private static function add_adjustment( $c, $diff, WC_Order $order ) {
		global $wpdb;
		$exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE adjustment_of = %d', $c->id ) ); // phpcs:ignore WordPress.DB
		if ( $exists ) {
			return;
		}
		$now = current_time( 'mysql' );
		$wpdb->insert( self::table(), array( // phpcs:ignore WordPress.DB
			'order_id'      => $order->get_id(),
			'partner_id'    => $c->partner_id,
			'attribution'   => $c->attribution,
			'code_used'     => $c->code_used,
			'amount'        => $diff,
			'status'        => 'approved',
			'reason'        => 'refund_adjustment',
			'available_at'  => $now,
			'adjustment_of' => $c->id,
			'created_at'    => $now,
			'updated_at'    => $now,
		) );
		$order->add_order_note( sprintf( __( 'Affiliate: %s will be deducted from the partner\'s next payout (refund after payout).', 'splitshare-affiliates' ), wp_strip_all_tags( wc_price( abs( $diff ) ) ) ) );
	}

	public static function void( $order_id, $reason = 'void' ) {
		$c = self::for_order( $order_id );
		if ( ! $c || in_array( $c->status, array( 'paid', 'void' ), true ) ) {
			if ( $c && 'paid' === $c->status ) {
				self::recalculate( $order_id );
			}
			return;
		}
		self::update( $c->id, array( 'status' => 'void', 'reason' => $reason ) );
	}

	/** İptal/sıfır nedeninin okunur, çevrilmiş karşılığı. */
	public static function reason_label( $reason ) {
		$map = array(
			'below_min'       => __( 'below minimum basket', 'splitshare-affiliates' ),
			'zero'            => __( 'no commissionable items', 'splitshare-affiliates' ),
			'refunded'        => __( 'refunded', 'splitshare-affiliates' ),
			'order_cancelled' => __( 'order cancelled', 'splitshare-affiliates' ),
			'order_refunded'  => __( 'order refunded', 'splitshare-affiliates' ),
			'order_failed'    => __( 'payment failed', 'splitshare-affiliates' ),
			'void'            => __( 'voided by admin', 'splitshare-affiliates' ),
		);
		$reason = (string) $reason;
		return isset( $map[ $reason ] ) ? $map[ $reason ] : str_replace( '_', ' ', $reason );
	}

	/** Bekleme süresi dolanları onayla (cron). */
	public static function approve_due( $now = null ) {
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s', $now ? (int) $now : current_time( 'timestamp' ) );
		return (int) $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . " SET status = 'approved', updated_at = %s WHERE status = 'pending' AND amount > 0 AND available_at <= %s", current_time( 'mysql' ), $now ) ); // phpcs:ignore WordPress.DB
	}

	/* ---------- Okuma ---------- */

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB
	}

	public static function for_order( $order_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE order_id = %d AND adjustment_of IS NULL LIMIT 1', $order_id ) ); // phpcs:ignore WordPress.DB
	}

	public static function update( $id, array $fields ) {
		global $wpdb;
		$fields['updated_at'] = current_time( 'mysql' );
		return $wpdb->update( self::table(), $fields, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	public static function set_status( $id, $status ) {
		if ( ! in_array( $status, array( 'pending', 'approved', 'void' ), true ) ) {
			return false;
		}
		return self::update( $id, array( 'status' => $status ) );
	}

	/** Liste: $args partner_id, status, from, to, search(order id), limit, offset, unpaid_only. */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args = wp_parse_args( $args, array( 'partner_id' => 0, 'status' => '', 'from' => '', 'to' => '', 'order_id' => 0, 'limit' => 20, 'offset' => 0, 'unpaid_only' => false ) );
		$w    = self::where( $args );
		$sql  = 'SELECT * FROM ' . self::table() . " {$w} ORDER BY created_at DESC";
		if ( (int) $args['limit'] > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $args['limit'], (int) $args['offset'] );
		}
		return (array) $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB
	}

	public static function count( array $args = array() ) {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() . ' ' . self::where( $args ) ); // phpcs:ignore WordPress.DB
	}

	private static function where( array $args ) {
		global $wpdb;
		$w = array( '1=1' );
		if ( ! empty( $args['partner_id'] ) ) {
			$w[] = $wpdb->prepare( 'partner_id = %d', $args['partner_id'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$w[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}
		if ( ! empty( $args['from'] ) ) {
			$w[] = $wpdb->prepare( 'created_at >= %s', $args['from'] . ' 00:00:00' );
		}
		if ( ! empty( $args['to'] ) ) {
			$w[] = $wpdb->prepare( 'created_at <= %s', $args['to'] . ' 23:59:59' );
		}
		if ( ! empty( $args['order_id'] ) ) {
			$w[] = $wpdb->prepare( 'order_id = %d', $args['order_id'] );
		}
		if ( ! empty( $args['unpaid_only'] ) ) {
			$w[] = "status = 'approved' AND payout_id IS NULL";
		}
		return 'WHERE ' . implode( ' AND ', $w );
	}

	/** Ortak toplamları (panel + admin). */
	public static function totals_for_partner( $partner_id ) {
		global $wpdb;
		$t    = self::table();
		$out  = array( 'pending' => 0.0, 'approved' => 0.0, 'paid' => 0.0, 'void' => 0.0, 'sales_count' => 0, 'month_sales' => 0.0, 'month_commission' => 0.0, 'unpaid' => 0.0 );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT status, SUM(amount) total, SUM(CASE WHEN amount > 0 AND adjustment_of IS NULL THEN 1 ELSE 0 END) n FROM {$t} WHERE partner_id = %d GROUP BY status", $partner_id ) ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as $r ) {
			$out[ $r->status ] = (float) $r->total;
			if ( in_array( $r->status, array( 'approved', 'paid' ), true ) ) {
				$out['sales_count'] += (int) $r->n;
			}
		}
		$month = $wpdb->get_row( $wpdb->prepare( "SELECT SUM(order_total_base) sales, SUM(amount) commission FROM {$t} WHERE partner_id = %d AND status <> 'void' AND created_at >= %s", $partner_id, gmdate( 'Y-m-01 00:00:00', current_time( 'timestamp' ) ) ) ); // phpcs:ignore WordPress.DB
		$out['month_sales']      = (float) $month->sales;
		$out['month_commission'] = (float) $month->commission;
		$out['unpaid']           = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM {$t} WHERE partner_id = %d AND status = 'approved' AND payout_id IS NULL", $partner_id ) ); // phpcs:ignore WordPress.DB
		return $out;
	}
}
