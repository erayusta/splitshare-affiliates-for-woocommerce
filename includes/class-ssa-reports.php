<?php
/**
 * Raporlar: KPI'lar (dönem + önceki dönem), aylık seriler, yeni/tekrar müşteri, dağılım, gruplar,
 * kod anomalileri, ortak trafiği ve tahmini/kesin kazanç.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Reports {

	private static function range( $from, $to ) {
		return array( $from ? $from . ' 00:00:00' : '1970-01-01 00:00:00', $to ? $to . ' 23:59:59' : '2100-01-01 00:00:00' );
	}

	/** Dönem özeti: ciro, komisyon, indirim, sipariş, yeni müşteri sayısı. */
	public static function summary( $from = '', $to = '', $partner_id = 0 ) {
		global $wpdb;
		$t = SSA_Install::tables()['commissions'];
		list( $f, $l ) = self::range( $from, $to );
		$where = $wpdb->prepare( "adjustment_of IS NULL AND status <> 'void' AND created_at BETWEEN %s AND %s", $f, $l );
		if ( $partner_id ) {
			$where .= $wpdb->prepare( ' AND partner_id = %d', $partner_id );
		}
		$r = $wpdb->get_row( "SELECT COUNT(*) orders, COALESCE(SUM(order_total_base),0) revenue, COALESCE(SUM(amount),0) commission, COALESCE(SUM(is_new_customer),0) new_customers, COALESCE(SUM(order_total_base * discount_pct / 100 * (attribution = 'coupon')),0) discount_est FROM {$t} WHERE {$where}" ); // phpcs:ignore WordPress.DB
		$out = array(
			'orders'     => (int) $r->orders,
			'revenue'    => (float) $r->revenue,
			'commission' => (float) $r->commission,
			'new'        => (int) $r->new_customers,
			'discount'   => (float) $r->discount_est,
		);
		$out['new_rate']  = $out['orders'] ? round( 100 * $out['new'] / $out['orders'], 1 ) : 0;
		$out['cost_rate'] = $out['revenue'] > 0 ? round( 100 * ( $out['commission'] + $out['discount'] ) / $out['revenue'], 1 ) : 0;
		return $out;
	}

	/** Son N ay: etiketler + ciro/komisyon/sipariş/yeni/tekrar serileri. */
	public static function monthly( $months = 12, $partner_id = 0 ) {
		global $wpdb;
		$t     = SSA_Install::tables()['commissions'];
		$now   = current_time( 'timestamp' );
		$start = gmdate( 'Y-m-01 00:00:00', strtotime( '-' . ( $months - 1 ) . ' months', strtotime( gmdate( 'Y-m-01', $now ) ) ) );
		$where = $wpdb->prepare( "adjustment_of IS NULL AND status <> 'void' AND created_at >= %s", $start );
		if ( $partner_id ) {
			$where .= $wpdb->prepare( ' AND partner_id = %d', $partner_id );
		}
		$rows = $wpdb->get_results( "SELECT DATE_FORMAT(created_at, '%Y-%m') ym, COUNT(*) orders, SUM(order_total_base) revenue, SUM(amount) commission, SUM(is_new_customer) new_customers FROM {$t} WHERE {$where} GROUP BY ym" ); // phpcs:ignore WordPress.DB
		$map  = array();
		foreach ( (array) $rows as $r ) {
			$map[ $r->ym ] = $r;
		}
		$out = array( 'labels' => array(), 'revenue' => array(), 'commission' => array(), 'orders' => array(), 'new' => array(), 'returning' => array() );
		for ( $i = $months - 1; $i >= 0; $i-- ) {
			$ts  = strtotime( '-' . $i . ' months', strtotime( gmdate( 'Y-m-01', $now ) ) );
			$ym  = gmdate( 'Y-m', $ts );
			$r   = isset( $map[ $ym ] ) ? $map[ $ym ] : null;
			$out['labels'][]     = date_i18n( 'M', $ts ) . ( '01' === gmdate( 'm', $ts ) || $i === $months - 1 ? " '" . gmdate( 'y', $ts ) : '' );
			$out['revenue'][]    = $r ? round( (float) $r->revenue, 2 ) : 0;
			$out['commission'][] = $r ? round( (float) $r->commission, 2 ) : 0;
			$out['orders'][]     = $r ? (int) $r->orders : 0;
			$out['new'][]        = $r ? (int) $r->new_customers : 0;
			$out['returning'][]  = $r ? (int) $r->orders - (int) $r->new_customers : 0;
		}
		return $out;
	}

	/** Günlük tıklama/dönüşüm (ortak paneli trafik grafiği). */
	public static function daily_clicks( $partner_id, $days = 30 ) {
		global $wpdb;
		$t     = SSA_Install::tables()['clicks'];
		$now   = current_time( 'timestamp' );
		$since = gmdate( 'Y-m-d 00:00:00', $now - ( $days - 1 ) * DAY_IN_SECONDS );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(created_at) d, COUNT(*) clicks, COUNT(order_id) conversions FROM {$t} WHERE partner_id = %d AND created_at >= %s GROUP BY d", $partner_id, $since ) ); // phpcs:ignore WordPress.DB
		$map   = array();
		foreach ( (array) $rows as $r ) {
			$map[ $r->d ] = $r;
		}
		$out = array( 'labels' => array(), 'clicks' => array(), 'conversions' => array() );
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$d = gmdate( 'Y-m-d', $now - $i * DAY_IN_SECONDS );
			$out['labels'][]      = ( 0 === $i % 5 || $i === $days - 1 ) ? date_i18n( 'j M', strtotime( $d ) ) : '';
			$out['clicks'][]      = isset( $map[ $d ] ) ? (int) $map[ $d ]->clicks : 0;
			$out['conversions'][] = isset( $map[ $d ] ) ? (int) $map[ $d ]->conversions : 0;
		}
		return $out;
	}

	/** Ortak kazanç durumu: tahmini (bekleyen), kesin (onaylı + ödenen), ödenen, iptal. */
	public static function partner_earnings( $partner_id ) {
		$t = SSA_Commissions::totals_for_partner( $partner_id );
		return array(
			'estimated' => (float) $t['pending'],
			'confirmed' => (float) $t['approved'] + (float) $t['paid'],
			'approved'  => (float) $t['approved'],
			'paid'      => (float) $t['paid'],
			'void'      => (float) $t['void'],
			'unpaid'    => (float) $t['unpaid'],
			'sales'     => (int) $t['sales_count'],
			'month_sales'      => (float) $t['month_sales'],
			'month_commission' => (float) $t['month_commission'],
		);
	}

	/** Ortak için aylık komisyon sparkline'ı (son 6 ay). */
	public static function partner_spark( $partner_id, $months = 6 ) {
		return self::monthly( $months, $partner_id )['commission'];
	}

	/** Ortakların dağılım tercihleri ve her grubun cirosu. */
	public static function split_distribution( $from = '', $to = '' ) {
		global $wpdb;
		$t = SSA_Install::tables();
		list( $f, $l ) = self::range( $from, $to );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT p.commission_pct, p.discount_pct, COUNT(DISTINCT p.id) partners, COALESCE(SUM(c.order_total_base),0) revenue, COALESCE(SUM(c.amount),0) commission FROM {$t['partners']} p LEFT JOIN {$t['commissions']} c ON c.partner_id = p.id AND c.status <> 'void' AND c.adjustment_of IS NULL AND c.created_at BETWEEN %s AND %s WHERE p.status IN ('active','paused') GROUP BY p.commission_pct, p.discount_pct ORDER BY p.commission_pct DESC", $f, $l ) ); // phpcs:ignore WordPress.DB
	}

	/** Dağılımı üç kovaya indirger (donut için): komisyon ağırlıklı / dengeli / indirim ağırlıklı. */
	public static function split_buckets() {
		global $wpdb;
		$t     = SSA_Install::tables()['partners'];
		$share = max( 0.01, (float) SSA_Settings::get( 'default_share' ) );
		$rows  = (array) $wpdb->get_results( "SELECT commission_pct FROM {$t} WHERE status IN ('active','paused')" ); // phpcs:ignore WordPress.DB
		$b     = array( 'commission' => 0, 'balanced' => 0, 'discount' => 0 );
		foreach ( $rows as $r ) {
			$ratio = (float) $r->commission_pct / $share;
			if ( $ratio > 0.66 ) {
				$b['commission']++;
			} elseif ( $ratio < 0.34 ) {
				$b['discount']++;
			} else {
				$b['balanced']++;
			}
		}
		return $b;
	}

	/** Ürün grubuna göre komisyon (breakdown JSON'dan). */
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

	/** Kod anomalileri: son N günde kod başına sipariş; ortalamanın 3 katı → flag. */
	public static function code_anomalies( $days = 30 ) {
		global $wpdb;
		$t     = SSA_Install::tables();
		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $days * DAY_IN_SECONDS );
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT c.partner_id, p.code, COUNT(*) orders, SUM(c.is_new_customer) new_customers FROM {$t['commissions']} c JOIN {$t['partners']} p ON p.id = c.partner_id WHERE c.attribution = 'coupon' AND c.adjustment_of IS NULL AND c.created_at >= %s GROUP BY c.partner_id, p.code", $since ) ); // phpcs:ignore WordPress.DB
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

	/** Komisyon durum toplamları (komisyon sekmesi şeridi). */
	public static function status_totals( array $args = array() ) {
		global $wpdb;
		$t   = SSA_Install::tables()['commissions'];
		$out = array( 'pending' => 0.0, 'approved' => 0.0, 'paid' => 0.0, 'void' => 0.0 );
		$w   = '1=1';
		if ( ! empty( $args['partner_id'] ) ) {
			$w .= $wpdb->prepare( ' AND partner_id = %d', $args['partner_id'] );
		}
		foreach ( (array) $wpdb->get_results( "SELECT status, SUM(amount) total FROM {$t} WHERE {$w} GROUP BY status" ) as $r ) { // phpcs:ignore WordPress.DB
			$out[ $r->status ] = (float) $r->total;
		}
		return $out;
	}

	/** Genel bakış için "ilgi bekleyenler". */
	public static function attention() {
		global $wpdb;
		$t   = SSA_Install::tables();
		$out = array(
			'applications' => SSA_Partners::counts_by_status()['pending'],
			'open_payouts' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['payouts']} WHERE status = 'open'" ), // phpcs:ignore WordPress.DB
			'open_amount'  => (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$t['payouts']} WHERE status = 'open'" ), // phpcs:ignore WordPress.DB
			'suspicious'   => count( array_filter( self::code_anomalies()['rows'], function ( $r ) { return $r->flag; } ) ),
			'approving_7d' => (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$t['commissions']} WHERE status = 'pending' AND available_at <= %s", gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + 7 * DAY_IN_SECONDS ) ) ), // phpcs:ignore WordPress.DB
		);
		return $out;
	}
}
