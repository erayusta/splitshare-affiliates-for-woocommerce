<?php
/**
 * Ortak kuponları: ortağın kendi ürettiği kampanya kuponları (ssa_coupons tablosu).
 * Her kupon: kod, ad, indirim %, kapsam (tüm mağaza / ürünler / kategoriler), bitiş, durum.
 * WC kupon köprüsü SSA_Coupon'dadır; bu sınıf doğrulama, CRUD, istatistik ve cron'u tutar.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Partner_Coupons {

	const SCOPES = array( 'all', 'products', 'categories' );

	private static function table() {
		return SSA_Install::tables()['coupons'];
	}

	/* ---------- Saf doğrulama (WP'siz test edilir) ---------- */

	public static function normalize_code( $code ) {
		return strtoupper( trim( (string) $code ) );
	}

	/** '' | empty | length | chars */
	public static function code_error( $code, $min, $max ) {
		$code = self::normalize_code( $code );
		if ( '' === $code ) {
			return 'empty';
		}
		if ( ! preg_match( '/^[A-Z0-9]+$/', $code ) ) {
			return 'chars';
		}
		$len = strlen( $code );
		if ( $len < (int) $min || $len > (int) $max ) {
			return 'length';
		}
		return '';
	}

	/** '' | min | max */
	public static function discount_error( $discount, $min, $max ) {
		$discount = (float) $discount;
		if ( $discount < (float) $min ) {
			return 'min';
		}
		if ( $discount > (float) $max ) {
			return 'max';
		}
		return '';
	}

	/* ---------- Okuma ---------- */

	private static function hydrate( $row ) {
		if ( ! $row ) {
			return null;
		}
		$row->id           = (int) $row->id;
		$row->partner_id   = (int) $row->partner_id;
		$row->wc_coupon_id = (int) $row->wc_coupon_id;
		$row->discount_pct = (float) $row->discount_pct;
		$row->uses         = (int) $row->uses;
		$row->scope_ids    = is_string( $row->scope_ids ) ? array_map( 'intval', (array) json_decode( $row->scope_ids, true ) ) : (array) $row->scope_ids;
		return $row;
	}

	public static function get( $id ) {
		global $wpdb;
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ) ); // phpcs:ignore WordPress.DB
	}

	public static function get_by_code( $code ) {
		global $wpdb;
		$code = self::normalize_code( $code );
		if ( '' === $code ) {
			return null;
		}
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE code = %s', $code ) ) ); // phpcs:ignore WordPress.DB
	}

	/** @param array $args status ('' = hepsi), limit */
	public static function for_partner( $partner_id, array $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args( $args, array( 'status' => '', 'limit' => 200 ) );
		$where = $wpdb->prepare( 'partner_id = %d', $partner_id );
		if ( $args['status'] ) {
			$where .= $wpdb->prepare( ' AND status = %s', $args['status'] );
		}
		$rows = (array) $wpdb->get_results( 'SELECT * FROM ' . self::table() . " WHERE {$where} ORDER BY (status = 'active') DESC, created_at DESC LIMIT " . (int) $args['limit'] ); // phpcs:ignore WordPress.DB
		return array_map( array( __CLASS__, 'hydrate' ), $rows );
	}

	public static function count_active( $partner_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE partner_id = %d AND status = 'active'", $partner_id ) ); // phpcs:ignore WordPress.DB
	}

	/** Kupon başına kullanım/kazanç (komisyon kayıtlarından). */
	public static function stats( $coupon ) {
		global $wpdb;
		$t   = SSA_Install::tables()['commissions'];
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) orders, COALESCE(SUM(order_total_base),0) revenue, COALESCE(SUM(amount),0) commission, MAX(created_at) last_used FROM {$t} WHERE partner_id = %d AND code_used = %s AND attribution = 'coupon' AND adjustment_of IS NULL AND status <> 'void'", $coupon->partner_id, $coupon->code ) ); // phpcs:ignore WordPress.DB
		return array(
			'orders'     => $row ? (int) $row->orders : 0,
			'revenue'    => $row ? (float) $row->revenue : 0.0,
			'commission' => $row ? (float) $row->commission : 0.0,
			'last_used'  => $row ? $row->last_used : null,
		);
	}

	/** Ortak özeti: aktif kupon sayısı + en çok kazandıran kupon. */
	public static function partner_summary( $partner_id ) {
		$best = null;
		$max  = -1;
		foreach ( self::for_partner( $partner_id, array( 'status' => 'active' ) ) as $c ) {
			$s = self::stats( $c );
			if ( $s['commission'] > $max ) {
				$max  = $s['commission'];
				$best = $c;
			}
		}
		return array( 'active' => self::count_active( $partner_id ), 'best' => $best );
	}

	/** Ortağın kuponlu satış özeti (son N gün). */
	public static function partner_kpis( $partner_id, $days = 30 ) {
		global $wpdb;
		$t     = SSA_Install::tables()['commissions'];
		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $days * DAY_IN_SECONDS );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) orders, COALESCE(SUM(order_total_base),0) revenue, COALESCE(SUM(amount),0) commission FROM {$t} WHERE partner_id = %d AND attribution = 'coupon' AND adjustment_of IS NULL AND status <> 'void' AND created_at >= %s", $partner_id, $since ) ); // phpcs:ignore WordPress.DB
		return array(
			'active'     => self::count_active( $partner_id ),
			'orders'     => $row ? (int) $row->orders : 0,
			'revenue'    => $row ? (float) $row->revenue : 0.0,
			'commission' => $row ? (float) $row->commission : 0.0,
		);
	}

	/** Hesaplayıcı bağlamı. */
	public static function to_ctx( $row ) {
		return $row ? array( 'discount_pct' => (float) $row->discount_pct, 'scope_type' => $row->scope_type, 'scope_ids' => $row->scope_ids ) : null;
	}

	public static function covers( $row, $product_id, array $category_ids ) {
		return SSA_Calculator::covers( self::to_ctx( $row ), $product_id, $category_ids );
	}

	/** Kampanya adı (geçişte yazılan "Main code" çevrilir). */
	public static function display_name( $row ) {
		return 'Main code' === $row->name ? __( 'Main code', 'splitshare-affiliates' ) : (string) $row->name;
	}

	/** Kapsam etiketi (UI). */
	public static function scope_label( $row ) {
		$n = count( $row->scope_ids );
		if ( 'products' === $row->scope_type ) {
			/* translators: %d: number of products */
			return sprintf( _n( '%d product', '%d products', $n, 'splitshare-affiliates' ), $n );
		}
		if ( 'categories' === $row->scope_type ) {
			/* translators: %d: number of categories */
			return sprintf( _n( '%d category', '%d categories', $n, 'splitshare-affiliates' ), $n );
		}
		return __( 'Whole store', 'splitshare-affiliates' );
	}

	/** Kapsamdaki ürün/kategori adları. */
	public static function scope_names( $row, $limit = 5 ) {
		$names = array();
		foreach ( array_slice( $row->scope_ids, 0, $limit ) as $id ) {
			if ( 'products' === $row->scope_type ) {
				$p = wc_get_product( $id );
				if ( $p ) {
					$names[] = $p->get_name();
				}
			} elseif ( 'categories' === $row->scope_type ) {
				$t = get_term( $id, 'product_cat' );
				if ( $t && ! is_wp_error( $t ) ) {
					$names[] = $t->name;
				}
			}
		}
		if ( count( $row->scope_ids ) > $limit ) {
			$names[] = '+' . ( count( $row->scope_ids ) - $limit );
		}
		return $names;
	}

	/** Ortağın kuponu için paylaşım linki (ürün kapsamlıysa ilk ürün, kategori kapsamlıysa kategori). */
	public static function share_url( $row, SSA_Partner $partner ) {
		$base = home_url( '/' );
		if ( 'products' === $row->scope_type && $row->scope_ids ) {
			$p = wc_get_product( $row->scope_ids[0] );
			if ( $p ) {
				$base = $p->get_permalink();
			}
		} elseif ( 'categories' === $row->scope_type && $row->scope_ids ) {
			$link = get_term_link( (int) $row->scope_ids[0], 'product_cat' );
			if ( $link && ! is_wp_error( $link ) ) {
				$base = $link;
			}
		}
		return SSA_Tracking::link( $partner->code, $base );
	}

	/* ---------- Yazma ---------- */

	/** Sınırlar (ayarlardan). */
	public static function limits() {
		$share = (float) SSA_Settings::get( 'default_share' );
		$max   = SSA_Settings::get( 'max_coupon_discount' );
		return array(
			'min_discount' => (float) SSA_Settings::get( 'min_coupon_discount' ),
			'max_discount' => ( '' === $max || null === $max ) ? $share : min( $share, (float) $max ),
			'code_min'     => max( 2, (int) SSA_Settings::get( 'coupon_code_min_len' ) ),
			'code_max'     => min( 20, max( 4, (int) SSA_Settings::get( 'coupon_code_max_len' ) ) ),
		);
	}

	/** Kod alınmış mı? (ortak kuponları, link kodları, WC kuponları) */
	public static function code_taken( $code, $except_id = 0 ) {
		$code = self::normalize_code( $code );
		$row  = self::get_by_code( $code );
		if ( $row && $row->id !== (int) $except_id ) {
			return true;
		}
		if ( SSA_Partners::get_by_link_code( $code ) ) {
			return true;
		}
		$wc = SSA_Coupon::coupon_id( $code );
		if ( $wc ) {
			$owner = (int) get_post_meta( $wc, SSA_Coupon::META_COUPON, true );
			if ( ! $owner || $owner !== (int) $except_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Alanları doğrula ve temizle.
	 * @return array|WP_Error code, name, discount_pct, scope_type, scope_ids, expires_at
	 */
	public static function validate( array $data, $except_id = 0 ) {
		$lim  = self::limits();
		$code = self::normalize_code( isset( $data['code'] ) ? $data['code'] : '' );
		$err  = self::code_error( $code, $lim['code_min'], $lim['code_max'] );
		if ( 'empty' === $err ) {
			return new WP_Error( 'ssa_code', __( 'Enter a coupon code.', 'splitshare-affiliates' ) );
		}
		if ( 'chars' === $err ) {
			return new WP_Error( 'ssa_code', __( 'The code may only contain letters A–Z and digits.', 'splitshare-affiliates' ) );
		}
		if ( 'length' === $err ) {
			/* translators: 1: min, 2: max */
			return new WP_Error( 'ssa_code', sprintf( __( 'The code must be %1$d–%2$d characters long.', 'splitshare-affiliates' ), $lim['code_min'], $lim['code_max'] ) );
		}
		if ( self::code_taken( $code, $except_id ) ) {
			return new WP_Error( 'ssa_code', __( 'This code is already in use. Try another one.', 'splitshare-affiliates' ) );
		}
		$discount = round( (float) ( isset( $data['discount_pct'] ) ? str_replace( ',', '.', $data['discount_pct'] ) : 0 ), 2 );
		$derr     = self::discount_error( $discount, $lim['min_discount'], $lim['max_discount'] );
		if ( $derr ) {
			/* translators: 1: min, 2: max */
			return new WP_Error( 'ssa_discount', sprintf( __( 'The discount must be between %1$s%% and %2$s%%.', 'splitshare-affiliates' ), wc_format_decimal( $lim['min_discount'], 1 ), wc_format_decimal( $lim['max_discount'], 1 ) ) );
		}
		$scope = isset( $data['scope_type'] ) && in_array( $data['scope_type'], self::SCOPES, true ) ? $data['scope_type'] : 'all';
		$ids   = array_values( array_filter( array_map( 'intval', (array) ( isset( $data['scope_ids'] ) ? $data['scope_ids'] : array() ) ) ) );
		if ( 'all' === $scope ) {
			$ids = array();
		} elseif ( ! $ids ) {
			return new WP_Error( 'ssa_scope', 'products' === $scope ? __( 'Choose at least one product.', 'splitshare-affiliates' ) : __( 'Choose at least one category.', 'splitshare-affiliates' ) );
		}
		$expires = null;
		if ( ! empty( $data['expires_at'] ) ) {
			$ts = strtotime( (string) $data['expires_at'] );
			if ( ! $ts ) {
				return new WP_Error( 'ssa_expires', __( 'Invalid end date.', 'splitshare-affiliates' ) );
			}
			if ( $ts < strtotime( 'today', current_time( 'timestamp' ) ) ) {
				return new WP_Error( 'ssa_expires', __( 'The end date must be today or later.', 'splitshare-affiliates' ) );
			}
			$expires = gmdate( 'Y-m-d 23:59:59', $ts );
		}
		$name = isset( $data['name'] ) ? mb_substr( sanitize_text_field( $data['name'] ), 0, 120 ) : '';
		return compact( 'code', 'name', 'discount_pct', 'scope', 'ids', 'expires' ) + array( 'discount_pct' => $discount, 'scope_type' => $scope, 'scope_ids' => $ids, 'expires_at' => $expires );
	}

	/** @return int|WP_Error kupon satır id'si */
	public static function create( SSA_Partner $partner, array $data ) {
		if ( ! $partner->is_active() ) {
			return new WP_Error( 'ssa_inactive', __( 'Your partnership is paused; you cannot create coupons right now.', 'splitshare-affiliates' ) );
		}
		$v = self::validate( $data );
		if ( is_wp_error( $v ) ) {
			return $v;
		}
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert( self::table(), array( // phpcs:ignore WordPress.DB
			'partner_id'   => $partner->id,
			'wc_coupon_id' => 0,
			'code'         => $v['code'],
			'name'         => $v['name'],
			'discount_pct' => $v['discount_pct'],
			'scope_type'   => $v['scope_type'],
			'scope_ids'    => wp_json_encode( $v['scope_ids'] ),
			'status'       => 'active',
			'expires_at'   => $v['expires_at'],
			'uses'         => 0,
			'created_at'   => $now,
			'updated_at'   => $now,
		) );
		$id  = (int) $wpdb->insert_id;
		$row = self::get( $id );
		$wc  = SSA_Coupon::sync( $row );
		if ( is_wp_error( $wc ) ) {
			$wpdb->delete( self::table(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
			return $wc;
		}
		$wpdb->update( self::table(), array( 'wc_coupon_id' => (int) $wc ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		do_action( 'ssa_coupon_created', self::get( $id ), $partner );
		return $id;
	}

	/** Ad, indirim, kapsam, bitiş güncelle (kod sabit). @return true|WP_Error */
	public static function update( $id, array $data ) {
		$row = self::get( $id );
		if ( ! $row ) {
			return new WP_Error( 'ssa_missing', __( 'Coupon not found.', 'splitshare-affiliates' ) );
		}
		$data['code'] = $row->code;
		$v            = self::validate( $data, $row->id );
		if ( is_wp_error( $v ) ) {
			return $v;
		}
		global $wpdb;
		$wpdb->update( self::table(), array( // phpcs:ignore WordPress.DB
			'name'         => $v['name'],
			'discount_pct' => $v['discount_pct'],
			'scope_type'   => $v['scope_type'],
			'scope_ids'    => wp_json_encode( $v['scope_ids'] ),
			'expires_at'   => $v['expires_at'],
			'status'       => 'expired' === $row->status ? 'active' : $row->status,
			'updated_at'   => current_time( 'mysql' ),
		), array( 'id' => $row->id ) );
		$wc = SSA_Coupon::sync( self::get( $row->id ) );
		return is_wp_error( $wc ) ? $wc : true;
	}

	public static function set_status( $id, $status ) {
		if ( ! in_array( $status, array( 'active', 'paused', 'expired' ), true ) ) {
			return false;
		}
		global $wpdb;
		$wpdb->update( self::table(), array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
		$row = self::get( $id );
		if ( $row ) {
			SSA_Coupon::sync( $row );
		}
		return true;
	}

	public static function delete( $id ) {
		$row = self::get( $id );
		if ( ! $row ) {
			return false;
		}
		SSA_Coupon::trash( $row );
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => $row->id ) ); // phpcs:ignore WordPress.DB
		do_action( 'ssa_coupon_deleted', $row );
		return true;
	}

	/** Kullanım sayacını artır (sipariş atfında). */
	public static function bump_uses( $id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET uses = uses + 1 WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB
	}

	/** Ortağın tüm kuponlarını WC ile eşitle (ortak durdurulunca/sürdürülünce). */
	public static function resync_partner( $partner_id ) {
		foreach ( self::for_partner( $partner_id ) as $row ) {
			SSA_Coupon::sync( $row );
		}
	}

	/** Süresi dolanları kapat (cron). */
	public static function expire_due() {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::table() . " WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at < %s", current_time( 'mysql' ) ) ); // phpcs:ignore WordPress.DB
		foreach ( $ids as $id ) {
			self::set_status( (int) $id, 'expired' );
		}
		return count( $ids );
	}

	/** İndirim kovaları (donut): düşük ≤5 / orta 5–10 / yüksek >10 — aktif kuponlar. */
	public static function discount_buckets() {
		global $wpdb;
		$rows = (array) $wpdb->get_col( 'SELECT discount_pct FROM ' . self::table() . " WHERE status = 'active'" ); // phpcs:ignore WordPress.DB
		$b    = array( 'low' => 0, 'mid' => 0, 'high' => 0 );
		foreach ( $rows as $d ) {
			$d = (float) $d;
			if ( $d <= 5 ) {
				$b['low']++;
			} elseif ( $d <= 10 ) {
				$b['mid']++;
			} else {
				$b['high']++;
			}
		}
		return $b;
	}

	/** İndirim oranına göre kupon sayısı / sipariş / ciro (rapor tablosu). */
	public static function discount_distribution( $from, $to ) {
		global $wpdb;
		$t = SSA_Install::tables();
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT k.discount_pct, COUNT(DISTINCT k.id) coupons, COUNT(c.id) orders, COALESCE(SUM(c.order_total_base),0) revenue, COALESCE(SUM(c.amount),0) commission FROM {$t['coupons']} k LEFT JOIN {$t['commissions']} c ON c.code_used = k.code AND c.partner_id = k.partner_id AND c.attribution = 'coupon' AND c.status <> 'void' AND c.adjustment_of IS NULL AND c.created_at BETWEEN %s AND %s GROUP BY k.discount_pct ORDER BY k.discount_pct ASC", $from, $to ) ); // phpcs:ignore WordPress.DB
	}
}
