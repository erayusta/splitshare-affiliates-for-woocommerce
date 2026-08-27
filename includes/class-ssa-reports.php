<?php
/**
 * Raporlar: yeni müşteri oranı, dağılım kırılımı, efektif maliyet, grup ciroları, kod anomalileri.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Reports {

	private static function range( $from, $to ) {
		return array( $from ? $from . ' 00:00:00' : '1970-01-01 00:00:00', $to ? $to . ' 23:59:59' : '2100-01-01 00:00:00' );
	}

	/** Atıflı siparişlerde yeni müşteri oranı (hedef %60+). */
	public static function new_customer_rate( $from = '', $to = '' ) {
		global $wpdb;
		$t = SSA_Install::tables()['commissions'];
		list( $f, $l ) = self::range( $from, $to );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) total, SUM(is_new_customer) new_customers FROM {$t} WHERE adjustment_of IS NULL AND status <> 'void' AND created_at BETWEEN %s AND %s", $f, $l ) ); // phpcs:ignore WordPress.DB
		return array( 'total' => (int) $r->total, 'new' => (int) $r->new_customers, 'rate' => $r->total ? round( 100 * $r->new_customers / $r->total, 1 ) : 0 );
	}

	/** Ortakların dağılım tercihleri ve her grubun cirosu. */
	public static function split_distribution( $from = '', $to = '' ) {
		global $wpdb;
		$t  = SSA_Install::tables();
		list( $f, $l ) = self::range( $from, $to );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.commission_pct, p.discount_pct, COUNT(DISTINCT p.id) partners, COALESCE(SUM(c.order_total_base),0) revenue, COALESCE(SUM(c.amount),0) commission FROM {$t['partners']} p LEFT JOIN {$t['commissions']} c ON c.partner_id = p.id AND c.status <> 'void' AND c.adjustment_of IS NULL AND c.created_at BETWEEN %s AND %s WHERE p.status IN ('active','paused') GROUP BY p.commission_pct, p.discount_pct ORDER BY p.commission_pct DESC", $f, $l ) ); // phpcs:ignore WordPress.DB
		return (array) $rows;
	}

	/** (indirim + komisyon) / atıflı ciro. */
	public static function effective_cost_rate( $from = '', $to = '' ) {
		global $wpdb;
		$t = SSA_Install::tables()['commissions'];
		list( $f, $l ) = self::range( $from, $to );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT order_id, order_total_base, amount, discount_pct, attribution FROM {$t} WHERE adjustment_of IS NULL AND status <> 'void' AND created_at BETWEEN %s AND %s", $f, $l ) ); // phpcs:ignore WordPress.DB
		$revenue = 0.0; $commission = 0.0; $discount = 0.0;
		foreach ( (array) $rows as $r ) {
			$revenue    += (float) $r->order_total_base;
			$commission += (float) $r->amount;
			if ( 'coupon' === $r->attribution ) {
				$order = wc_get_order( $r->order_id );
				$discount += $order ? (float) $order->get_discount_total() + (float) $order->get_discount_tax() : 0.0;
			}
		}
		return array( 'revenue' => $revenue, 'commission' => $commission, 'discount' => $discount, 'rate' => $revenue > 0 ? round( 100 * ( $commission + $discount ) / $revenue, 1 ) : 0 );
	}

	/** Ürün grubuna göre atıflı ciro ve komisyon (breakdown JSON'dan). */
	public static function group_revenue( $from = '', $to = '' ) {
		global $wpdb;
		$t = SSA_Install::tables()['commissions'];
		list( $f, $l ) = self::range( $from, $to );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT breakdown FROM {$t} WHERE adjustment_of IS NULL AND status <> 'void' AND created_at BETWEEN %s AND %s", $f, $l ) ); // phpcs:ignore WordPress.DB
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$b = json_decode( (string) $r->breakdown, true );
			foreach ( (array) ( isset( $b['items'] ) ? $b['items'] : array() ) as $it ) {
				$key = (string) $it['share'];
				if ( ! isset( $out[ $key ] ) ) {
					$out[ $key ] = array( 'share' => (float) $it['share'], 'commission' => 0.0, 'items' => 0 );
				}
				$out[ $key ]['commission'] += (float) $it['amount'];
				$out[ $key ]['items']++;
			}
		}
		krsort( $out );
		return array_values( $out );
	}

	/** Kod anomalileri: son N günde kod başına farklı müşteri sayısı; ortalamanın 3 katı → flag. */
	public static function code_anomalies( $days = 30 ) {
		global $wpdb;
		$t = SSA_Install::tables();
		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $days * DAY_IN_SECONDS );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT c.partner_id, p.code, COUNT(*) orders, COUNT(DISTINCT c.order_id) distinct_orders, SUM(c.is_new_customer) new_customers FROM {$t['commissions']} c JOIN {$t['partners']} p ON p.id = c.partner_id WHERE c.attribution = 'coupon' AND c.adjustment_of IS NULL AND c.created_at >= %s GROUP BY c.partner_id, p.code", $since ) ); // phpcs:ignore WordPress.DB
		$rows  = (array) $rows;
		$avg   = $rows ? array_sum( wp_list_pluck( $rows, 'orders' ) ) / count( $rows ) : 0;
		foreach ( $rows as $r ) {
			$r->new_rate = $r->orders ? round( 100 * $r->new_customers / $r->orders, 1 ) : 0;
			$r->flag     = $avg > 0 && $r->orders >= 5 && $r->orders > 3 * $avg;
		}
		usort( $rows, function ( $a, $b ) { return (int) $b->orders - (int) $a->orders; } );
		return array( 'average' => round( $avg, 1 ), 'rows' => $rows );
	}

	/** En çok kazandıran ortaklar. */
	public static function leaderboard( $from = '', $to = '', $limit = 10 ) {
		global $wpdb;
		$t = SSA_Install::tables();
		list( $f, $l ) = self::range( $from, $to );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT p.id, p.code, p.tier, COUNT(c.id) orders, COALESCE(SUM(c.order_total_base),0) revenue, COALESCE(SUM(c.amount),0) commission FROM {$t['partners']} p JOIN {$t['commissions']} c ON c.partner_id = p.id AND c.status <> 'void' AND c.adjustment_of IS NULL AND c.created_at BETWEEN %s AND %s GROUP BY p.id ORDER BY revenue DESC LIMIT %d", $f, $l, $limit ) ); // phpcs:ignore WordPress.DB
	}
}
