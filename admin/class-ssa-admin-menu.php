<?php
/**
 * WooCommerce → Affiliates menüsü; sekmeler: Partners, Applications, Commissions, Payouts, Reports.
 * Sekme sınıfları admin/class-ssa-admin-*.php içinde; her biri render() ve handle_actions() sunar.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Admin_Menu {

	const SLUG = 'ssa-affiliates';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function tabs() {
		$tabs = array(
			'partners'     => array( 'label' => __( 'Partners', 'splitshare-affiliates' ), 'class' => 'SSA_Admin_Partners' ),
			'applications' => array( 'label' => __( 'Applications', 'splitshare-affiliates' ), 'class' => 'SSA_Admin_Applications' ),
			'commissions'  => array( 'label' => __( 'Commissions', 'splitshare-affiliates' ), 'class' => 'SSA_Admin_Commissions' ),
			'payouts'      => array( 'label' => __( 'Payouts', 'splitshare-affiliates' ), 'class' => 'SSA_Admin_Payouts' ),
			'reports'      => array( 'label' => __( 'Reports', 'splitshare-affiliates' ), 'class' => 'SSA_Admin_Reports' ),
		);
		foreach ( $tabs as $key => $tab ) {
			$file = SSA_PATH . 'admin/class-ssa-admin-' . $key . '.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
		return $tabs;
	}

	public static function current_tab() {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'partners'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = self::tabs();
		return isset( $tabs[ $tab ] ) ? $tab : 'partners';
	}

	public static function url( $tab = 'partners', array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG, 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
	}

	public static function menu() {
		$pending = class_exists( 'SSA_Partners' ) ? SSA_Partners::counts_by_status()['pending'] : 0;
		$label   = __( 'Affiliates', 'splitshare-affiliates' ) . ( $pending ? ' <span class="awaiting-mod">' . (int) $pending . '</span>' : '' );
		add_submenu_page( 'woocommerce', __( 'Affiliates', 'splitshare-affiliates' ), $label, 'ssa_manage', self::SLUG, array( __CLASS__, 'render' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( $hook, self::SLUG ) && 'woocommerce_page_wc-orders' !== $hook && 'post.php' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'ssa-admin', SSA_URL . 'assets/css/admin.css', array(), SSA_VERSION );
		wp_enqueue_script( 'ssa-admin', SSA_URL . 'assets/js/admin.js', array( 'jquery' ), SSA_VERSION, true );
	}

	public static function handle_actions() {
		if ( empty( $_REQUEST['page'] ) || self::SLUG !== $_REQUEST['page'] || ! current_user_can( 'ssa_manage' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$tab   = self::current_tab();
		$class = self::tabs()[ $tab ]['class'];
		if ( class_exists( $class ) && method_exists( $class, 'handle_actions' ) ) {
			call_user_func( array( $class, 'handle_actions' ) );
		}
	}

	public static function render() {
		$tabs    = self::tabs();
		$current = self::current_tab();
		echo '<div class="wrap ssa-admin"><h1>' . esc_html( SSA_Settings::get( 'program_name' ) ) . ' <small>— ' . esc_html__( 'Affiliates', 'splitshare-affiliates' ) . '</small></h1>';
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $tab ) {
			$badge = ( 'applications' === $key ) ? SSA_Partners::counts_by_status()['pending'] : 0;
			echo '<a class="nav-tab' . ( $key === $current ? ' nav-tab-active' : '' ) . '" href="' . esc_url( self::url( $key ) ) . '">' . esc_html( $tab['label'] ) . ( $badge ? ' <span class="awaiting-mod">' . (int) $badge . '</span>' : '' ) . '</a>';
		}
		echo '<a class="nav-tab" href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=ssa' ) ) . '">' . esc_html__( 'Settings', 'splitshare-affiliates' ) . ' ↗</a>';
		echo '</nav>';
		self::notices();
		$class = $tabs[ $current ]['class'];
		if ( class_exists( $class ) ) {
			call_user_func( array( $class, 'render' ) );
		} else {
			echo '<p>' . esc_html__( 'Coming soon.', 'splitshare-affiliates' ) . '</p>';
		}
		echo '</div>';
	}

	public static function redirect_with( $tab, $message, $type = 'success', array $args = array() ) {
		set_transient( 'ssa_notice_' . get_current_user_id(), array( 'message' => $message, 'type' => $type ), 60 );
		wp_safe_redirect( self::url( $tab, $args ) );
		exit;
	}

	private static function notices() {
		$n = get_transient( 'ssa_notice_' . get_current_user_id() );
		if ( $n ) {
			delete_transient( 'ssa_notice_' . get_current_user_id() );
			echo '<div class="notice notice-' . esc_attr( $n['type'] ) . ' is-dismissible"><p>' . esc_html( $n['message'] ) . '</p></div>';
		}
	}

	/** Ortak adı + link (tablolar için). */
	public static function partner_link( SSA_Partner $p ) {
		return '<a href="' . esc_url( self::url( 'partners', array( 'action' => 'edit', 'id' => $p->id ) ) ) . '">' . esc_html( $p->display_name() ) . '</a><br><small>' . esc_html( $p->email() ) . '</small>';
	}

	public static function badge( $status ) {
		return '<span class="ssa-badge ssa-badge-' . esc_attr( $status ) . '">' . esc_html( ucfirst( $status ) ) . '</span>';
	}
}
