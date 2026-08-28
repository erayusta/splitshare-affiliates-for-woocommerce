<?php
/**
 * Eklenti başlatıcısı: sınıfları yükler, hook'ları bağlar.
 */

defined( 'ABSPATH' ) || exit;

final class SSA_Plugin {

	/** @var SSA_Plugin|null */
	private static $instance = null;

	/** Asset sürümü: dosya değişim zamanı (cache-bust). */
	public static function asset_ver( $rel ) {
		$file = SSA_PATH . ltrim( $rel, '/' );
		return file_exists( $file ) ? (string) filemtime( $file ) : SSA_VERSION;
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', function () {
			load_plugin_textdomain( 'splitshare-affiliates', false, dirname( plugin_basename( SSA_FILE ) ) . '/languages' );
		}, 1 );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error"><p><strong>SplitShare Affiliates:</strong> ' . esc_html__( 'WooCommerce is required for this plugin to work.', 'splitshare-affiliates' ) . '</p></div>';
			} );
			return;
		}

		foreach ( array(
			'includes/class-ssa-calculator.php',
			'includes/class-ssa-charts.php',
			'includes/class-ssa-settings.php',
			'includes/class-ssa-partner.php',
			'includes/class-ssa-partners.php',
			'includes/class-ssa-partner-coupons.php',
			'includes/class-ssa-coupon.php',
			'includes/class-ssa-tracking.php',
			'includes/class-ssa-commissions.php',
			'includes/class-ssa-payouts.php',
			'includes/class-ssa-cron.php',
			'includes/class-ssa-account.php',
			'includes/class-ssa-application.php',
			'includes/class-ssa-emails.php',
			'includes/class-ssa-reports.php',
			'admin/class-ssa-admin-ui.php',
			'admin/class-ssa-admin-menu.php',
			'admin/class-ssa-admin-order.php',
		) as $file ) {
			if ( file_exists( SSA_PATH . $file ) ) {
				require_once SSA_PATH . $file;
			}
		}

		SSA_Install::maybe_upgrade();

		foreach ( array( 'SSA_Settings', 'SSA_Coupon', 'SSA_Tracking', 'SSA_Commissions', 'SSA_Cron', 'SSA_Account', 'SSA_Application', 'SSA_Emails', 'SSA_Admin_Menu', 'SSA_Admin_Order' ) as $class ) {
			if ( class_exists( $class ) && method_exists( $class, 'init' ) ) {
				call_user_func( array( $class, 'init' ) );
			}
		}

		add_filter( 'plugin_action_links_' . plugin_basename( SSA_FILE ), function ( $links ) {
			array_unshift(
				$links,
				'<a href="' . esc_url( admin_url( 'admin.php?page=ssa-affiliates' ) ) . '">' . esc_html__( 'Partners', 'splitshare-affiliates' ) . '</a>',
				'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=ssa' ) ) . '">' . esc_html__( 'Settings', 'splitshare-affiliates' ) . '</a>'
			);
			return $links;
		} );
	}
}
