<?php
/**
 * Plugin Name:       SplitShare Affiliates for WooCommerce
 * Plugin URI:        https://github.com/erayusta/splitshare-affiliates-for-woocommerce
 * Description:       Influencer affiliate program: partners split a fixed share of each sale between their own commission and a follower discount. Coupon + link tracking, hold period, monthly payouts, My Account panel.
 * Version:           1.1.1
 * Author:            Eray Usta
 * Author URI:        https://github.com/erayusta
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       splitshare-affiliates
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * Requires Plugins:  woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'SSA_VERSION', '1.1.1' );
define( 'SSA_FILE', __FILE__ );
define( 'SSA_PATH', plugin_dir_path( __FILE__ ) );
define( 'SSA_URL', plugin_dir_url( __FILE__ ) );

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
} );

require_once SSA_PATH . 'includes/class-ssa-install.php';
require_once SSA_PATH . 'includes/class-ssa-plugin.php';

register_activation_hook( __FILE__, array( 'SSA_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SSA_Install', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'SSA_Plugin', 'instance' ), 11 );
