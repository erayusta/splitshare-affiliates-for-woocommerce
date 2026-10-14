<?php
/**
 * Zamanlanmış işler: günlük (bekleme→onay, statüler, süresi dolan kuponlar) ve aylık hakediş.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Cron {

	public static function init() {
		add_action( 'ssa_daily', array( __CLASS__, 'daily' ) );
		add_action( 'ssa_monthly', array( __CLASS__, 'monthly' ) );
	}

	public static function daily() {
		$approved = SSA_Commissions::approve_due();
		$tiers    = SSA_Partners::update_tiers();
		$expired  = SSA_Partner_Coupons::expire_due();
		do_action( 'ssa_daily_done', compact( 'approved', 'tiers', 'expired' ) );
		return compact( 'approved', 'tiers', 'expired' );
	}

	/** Her gün tetiklenir; yalnızca ödeme gününde ve o dönem için parti yoksa çalışır. */
	public static function monthly( $force = false ) {
		$day    = (int) SSA_Settings::get( 'payout_day' );
		$today  = (int) current_time( 'j' );
		$period = gmdate( 'Y-m', strtotime( 'first day of last month', current_time( 'timestamp' ) ) );
		if ( ! $force && $today !== $day ) {
			return null;
		}
		if ( ! $force && SSA_Payouts::period_exists( $period ) ) {
			return null;
		}
		return SSA_Payouts::run_monthly( $period );
	}
}
