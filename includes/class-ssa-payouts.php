<?php
/**
 * Hakedişler: aylık parti oluşturma (min tutar/devir), ödendi işaretleme, CSV.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Payouts {

	private static function table() {
		return SSA_Install::tables()['payouts'];
	}

	public static function period_exists( $period ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE period = %s', $period ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Ortak başına onaylı & bağlanmamış komisyonları toplar; min tutarı geçenlere parti açar.
	 * @return array{created:int[], carried:int[]}
	 */
	public static function run_monthly( $period = null ) {
		global $wpdb;
		$ct     = SSA_Install::tables()['commissions'];
		$period = $period ? $period : gmdate( 'Y-m', strtotime( 'first day of last month', current_time( 'timestamp' ) ) );
		$min    = (float) SSA_Settings::get( 'min_payout' );
		$rows   = $wpdb->get_results( "SELECT partner_id, SUM(amount) total FROM {$ct} WHERE status = 'approved' AND payout_id IS NULL GROUP BY partner_id" ); // phpcs:ignore WordPress.DB
		$out    = array( 'created' => array(), 'carried' => array() );
		foreach ( (array) $rows as $r ) {
			$total = round( (float) $r->total, 2 );
			if ( $total < $min || $total <= 0 ) {
				$out['carried'][] = (int) $r->partner_id;
				continue;
			}
			$now = current_time( 'mysql' );
			$wpdb->insert( self::table(), array( 'partner_id' => (int) $r->partner_id, 'period' => $period, 'amount' => $total, 'status' => 'open', 'created_at' => $now ) ); // phpcs:ignore WordPress.DB
			$payout_id = (int) $wpdb->insert_id;
			$wpdb->query( $wpdb->prepare( "UPDATE {$ct} SET payout_id = %d, updated_at = %s WHERE partner_id = %d AND status = 'approved' AND payout_id IS NULL", $payout_id, $now, (int) $r->partner_id ) ); // phpcs:ignore WordPress.DB
			$out['created'][] = $payout_id;
			do_action( 'ssa_payout_created', self::get( $payout_id ) );
		}
		return $out;
	}

	public static function mark_paid( $id, $reference = '' ) {
		global $wpdb;
		$p = self::get( $id );
		if ( ! $p || 'paid' === $p->status ) {
			return false;
		}
		$now = current_time( 'mysql' );
		$wpdb->update( self::table(), array( 'status' => 'paid', 'paid_at' => $now, 'reference' => sanitize_text_field( $reference ) ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
		$wpdb->update( SSA_Install::tables()['commissions'], array( 'status' => 'paid', 'updated_at' => $now ), array( 'payout_id' => (int) $id ) ); // phpcs:ignore WordPress.DB
		$payout  = self::get( $id );
		$partner = SSA_Partners::get( $payout->partner_id );
		if ( $partner ) {
			do_action( 'ssa_payout_paid', $partner, $payout );
		}
		return true;
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB
	}

	/** $args: partner_id, status, period, limit, offset */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args = wp_parse_args( $args, array( 'partner_id' => 0, 'status' => '', 'period' => '', 'limit' => 20, 'offset' => 0 ) );
		$w    = array( '1=1' );
		if ( $args['partner_id'] ) {
			$w[] = $wpdb->prepare( 'partner_id = %d', $args['partner_id'] );
		}
		if ( $args['status'] ) {
			$w[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}
		if ( $args['period'] ) {
			$w[] = $wpdb->prepare( 'period = %s', $args['period'] );
		}
		$sql = 'SELECT * FROM ' . self::table() . ' WHERE ' . implode( ' AND ', $w ) . ' ORDER BY created_at DESC';
		if ( (int) $args['limit'] > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $args['limit'], (int) $args['offset'] );
		}
		return (array) $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB
	}

	public static function for_partner( $partner_id, $limit = 24 ) {
		return self::query( array( 'partner_id' => $partner_id, 'limit' => $limit ) );
	}

	/** Bir sonraki hakedişe devreden (onaylı, bağlanmamış) bakiye. */
	public static function carry_over( $partner_id ) {
		return SSA_Commissions::totals_for_partner( $partner_id )['unpaid'];
	}

	/** Bir sonraki ödeme günü. */
	public static function next_payout_date() {
		$day = (int) SSA_Settings::get( 'payout_day' );
		$now = current_time( 'timestamp' );
		$ts  = mktime( 0, 0, 0, (int) gmdate( 'n', $now ), $day, (int) gmdate( 'Y', $now ) );
		if ( $ts < $now ) {
			$ts = mktime( 0, 0, 0, (int) gmdate( 'n', $now ) + 1, $day, (int) gmdate( 'Y', $now ) );
		}
		return $ts;
	}

	/** CSV: ad, IBAN, tutar, açıklama. */
	public static function csv( array $ids ) {
		$lines = array( array( 'Partner', 'Account holder', 'IBAN', 'Amount', 'Period', 'Description' ) );
		foreach ( $ids as $id ) {
			$p = self::get( (int) $id );
			if ( ! $p ) {
				continue;
			}
			$partner = SSA_Partners::get( $p->partner_id );
			$details = $partner ? $partner->payout_details : array();
			$lines[] = array(
				$partner ? $partner->display_name() : '#' . $p->partner_id,
				isset( $details['holder'] ) ? $details['holder'] : '',
				isset( $details['iban'] ) ? $details['iban'] : '',
				number_format( (float) $p->amount, 2, '.', '' ),
				$p->period,
				SSA_Settings::get( 'program_name' ) . ' ' . $p->period,
			);
		}
		$out = fopen( 'php://temp', 'r+' );
		foreach ( $lines as $l ) {
			fputcsv( $out, $l );
		}
		rewind( $out );
		$csv = stream_get_contents( $out );
		fclose( $out );
		return $csv;
	}
}
