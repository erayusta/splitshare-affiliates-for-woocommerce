<?php
/**
 * Hesabım paneli: endpoint'ler, menü, şablonlar, form işleme (dağılım, ödeme bilgileri).
 */

defined( 'ABSPATH' ) || exit;

class SSA_Account {

	const KEYS = array( 'dashboard', 'sales', 'earnings', 'split', 'links', 'kit' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_endpoints' ) );
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu_items' ), 20 );
		foreach ( self::KEYS as $key ) {
			add_action( 'woocommerce_account_' . SSA_Settings::endpoint( $key ) . '_endpoint', function () use ( $key ) {
				SSA_Account::render( $key );
			} );
			add_filter( 'woocommerce_endpoint_' . SSA_Settings::endpoint( $key ) . '_title', function () use ( $key ) {
				return SSA_Account::titles()[ $key ];
			} );
		}
		add_action( 'template_redirect', array( __CLASS__, 'handle_forms' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'join_card' ), 5 );
		add_action( 'wc_ajax_ssa_search_products', array( __CLASS__, 'ajax_search_products' ) );
	}

	/** Link üreteci için ürün arama (yalnızca ortaklar; yayımlanmış ürünler). */
	public static function ajax_search_products() {
		check_ajax_referer( 'ssa_search', 'security' );
		if ( ! self::partner() ) {
			wp_send_json( array() );
		}
		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		if ( mb_strlen( $term ) < 2 ) {
			wp_send_json( array() );
		}
		$out = array();
		foreach ( wc_get_products( array( 's' => $term, 'limit' => 12, 'status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ) ) as $product ) {
			$out[] = array( 'id' => $product->get_id(), 'text' => $product->get_name(), 'url' => $product->get_permalink() );
		}
		wp_send_json( $out );
	}

	public static function titles() {
		return array(
			'dashboard' => SSA_Settings::get( 'program_name' ),
			'sales'     => __( 'Partner sales', 'splitshare-affiliates' ),
			'earnings'  => __( 'Earnings', 'splitshare-affiliates' ),
			'split'     => __( 'Split my share', 'splitshare-affiliates' ),
			'links'     => __( 'Links', 'splitshare-affiliates' ),
			'kit'       => __( 'Content kit', 'splitshare-affiliates' ),
		);
	}

	public static function add_endpoints() {
		foreach ( self::KEYS as $key ) {
			add_rewrite_endpoint( SSA_Settings::endpoint( $key ), EP_ROOT | EP_PAGES );
		}
	}

	public static function query_vars( $vars ) {
		foreach ( self::KEYS as $key ) {
			$vars[ SSA_Settings::endpoint( $key ) ] = SSA_Settings::endpoint( $key );
		}
		return $vars;
	}

	public static function url( $key ) {
		return wc_get_account_endpoint_url( SSA_Settings::endpoint( $key ) );
	}

	public static function partner() {
		$p = SSA_Partners::current();
		return ( $p && $p->can_access_panel() ) ? $p : null;
	}

	public static function menu_items( $items ) {
		if ( ! self::partner() ) {
			return $items;
		}
		$new = array();
		foreach ( $items as $k => $label ) {
			if ( 'customer-logout' === $k ) {
				foreach ( self::KEYS as $key ) {
					$new[ SSA_Settings::endpoint( $key ) ] = self::titles()[ $key ];
				}
			}
			$new[ $k ] = $label;
		}
		if ( ! isset( $items['customer-logout'] ) ) {
			foreach ( self::KEYS as $key ) {
				$new[ SSA_Settings::endpoint( $key ) ] = self::titles()[ $key ];
			}
		}
		return $new;
	}

	public static function assets() {
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			wp_enqueue_style( 'ssa-account', SSA_URL . 'assets/css/account.css', array(), SSA_Plugin::asset_ver( 'assets/css/account.css' ) );
			wp_enqueue_style( 'select2' );
			wp_enqueue_script( 'selectWoo' );
			wp_enqueue_script( 'ssa-account', SSA_URL . 'assets/js/account.js', array( 'jquery', 'selectWoo' ), SSA_Plugin::asset_ver( 'assets/js/account.js' ), true );
			wp_localize_script( 'ssa-account', 'ssa_account', array(
				'home'     => home_url( '/' ),
				'search'   => WC_AJAX::get_endpoint( 'ssa_search_products' ),
				'nonce'    => wp_create_nonce( 'ssa_search' ),
				'currency' => get_woocommerce_currency_symbol(),
				'decimals' => wc_get_price_decimals(),
				'dec_sep'  => wc_get_price_decimal_separator(),
				'th_sep'   => wc_get_price_thousand_separator(),
				'pos'      => get_option( 'woocommerce_currency_pos' ),
				'i18n'     => array( 'copied' => __( 'Copied!', 'splitshare-affiliates' ), 'copy' => __( 'Copy', 'splitshare-affiliates' ) ),
			) );
		}
	}

	/** Şablon bul: tema override → eklenti. */
	public static function template( $name, array $args = array() ) {
		$file = locate_template( array( 'woocommerce/splitshare-affiliates/account/' . $name . '.php' ) );
		if ( ! $file ) {
			$file = SSA_PATH . 'templates/account/' . $name . '.php';
		}
		extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include $file;
	}

	public static function render( $key ) {
		$partner = self::partner();
		if ( ! $partner ) {
			echo '<p>' . esc_html__( 'This area is for program partners.', 'splitshare-affiliates' ) . '</p>';
			return;
		}
		$args = array( 'partner' => $partner, 'settings' => SSA_Settings::all(), 'notices' => self::notices() );
		switch ( $key ) {
			case 'dashboard':
				$args['earnings']    = SSA_Reports::partner_earnings( $partner->id );
				$args['traffic']     = SSA_Reports::daily_clicks( $partner->id, 30 );
				$args['monthly']     = SSA_Reports::monthly( 6, $partner->id );
				$args['next_payout'] = SSA_Payouts::next_payout_date();
				$args['recent']      = SSA_Commissions::query( array( 'partner_id' => $partner->id, 'limit' => 6 ) );
				break;
			case 'sales':
				$per  = 20;
				$page = max( 1, (int) get_query_var( 'paged', 1 ) );
				if ( isset( $_GET['pg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$page = max( 1, (int) $_GET['pg'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}
				$args['earnings'] = SSA_Reports::partner_earnings( $partner->id );
				$args['rows']  = SSA_Commissions::query( array( 'partner_id' => $partner->id, 'limit' => $per, 'offset' => ( $page - 1 ) * $per ) );
				$args['total'] = SSA_Commissions::count( array( 'partner_id' => $partner->id ) );
				$args['page']  = $page;
				$args['pages'] = (int) ceil( $args['total'] / $per );
				break;
			case 'earnings':
				$args['payouts']    = SSA_Payouts::for_partner( $partner->id );
				$args['carry_over'] = SSA_Payouts::carry_over( $partner->id );
				$args['earnings']   = SSA_Reports::partner_earnings( $partner->id );
				$args['next_payout'] = SSA_Payouts::next_payout_date();
				break;
			case 'split':
				$args['share']    = (float) SSA_Settings::get( 'default_share' );
				$args['interval'] = (int) SSA_Settings::get( 'split_change_interval_days' );
				$args['can']      = $partner->can_change_split( $args['interval'] );
				$args['next']     = $partner->next_split_change_at( $args['interval'] );
				$args['groups']   = self::group_rows();
				break;
			case 'links':
				$args['stats']    = SSA_Tracking::click_stats( $partner->id, 30 );
				$args['traffic']  = SSA_Reports::daily_clicks( $partner->id, 30 );
				$args['boosters'] = array_filter( array_map( 'wc_get_product', SSA_Settings::active_booster_products() ) );
				$args['categories'] = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0 ) );
				break;
			case 'kit':
				$args['media'] = array_filter( array_map( 'get_post', array_map( 'intval', (array) SSA_Settings::get( 'kit_attachments', array() ) ) ) );
				$args['texts'] = array_filter( array_map( 'trim', preg_split( '/\n\s*\n/', (string) SSA_Settings::get( 'kit_texts' ) ) ) );
				break;
		}
		echo '<div class="ssa-panel ssa-panel-' . esc_attr( $key ) . '">';
		self::template( $key, $args );
		echo '</div>';
	}

	/** Grup payları (panelde açıklama için). */
	private static function group_rows() {
		$rows = array();
		foreach ( SSA_Settings::rules()['group_shares'] as $cat => $pct ) {
			$term = get_term( $cat, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$rows[] = array( 'name' => $term->name, 'pct' => $pct );
			}
		}
		return $rows;
	}

	private static function notices() {
		$key = 'ssa_panel_notice_' . get_current_user_id();
		$n   = get_transient( $key );
		if ( $n ) {
			delete_transient( $key );
		}
		return $n ? array( $n ) : array();
	}

	private static function notice( $message, $type = 'success' ) {
		set_transient( 'ssa_panel_notice_' . get_current_user_id(), array( 'message' => $message, 'type' => $type ), 60 );
	}

	public static function handle_forms() {
		if ( empty( $_POST['ssa_action'] ) || ! is_user_logged_in() ) {
			return;
		}
		$partner = self::partner();
		if ( ! $partner || ! isset( $_POST['_ssa_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_ssa_nonce'] ), 'ssa_panel' ) ) {
			return;
		}
		$action = sanitize_key( $_POST['ssa_action'] );
		if ( 'set_split' === $action ) {
			$r = SSA_Partners::set_split( $partner->id, isset( $_POST['commission_pct'] ) ? (float) $_POST['commission_pct'] : $partner->commission_pct );
			self::notice( is_wp_error( $r ) ? $r->get_error_message() : __( 'Your split has been updated. Your code now gives the new discount.', 'splitshare-affiliates' ), is_wp_error( $r ) ? 'error' : 'success' );
			wp_safe_redirect( self::url( 'split' ) );
			exit;
		}
		if ( 'payout_details' === $action ) {
			SSA_Partners::update_payout_details( $partner->id, isset( $_POST['payout'] ) ? (array) wp_unslash( $_POST['payout'] ) : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			self::notice( __( 'Payout details saved.', 'splitshare-affiliates' ) );
			wp_safe_redirect( self::url( 'earnings' ) );
			exit;
		}
	}

	/** Ortak olmayan müşteriye "ortak olun" kartı. */
	public static function join_card() {
		if ( 'yes' !== SSA_Settings::get( 'show_join_card' ) || self::partner() ) {
			return;
		}
		$url = SSA_Application::apply_url();
		if ( ! $url ) {
			return;
		}
		$existing = SSA_Partners::current();
		if ( $existing && 'pending' === $existing->status ) {
			echo '<div class="ssa-join-card"><strong>' . esc_html( SSA_Settings::get( 'program_name' ) ) . '</strong> — ' . esc_html__( 'your application is being reviewed.', 'splitshare-affiliates' ) . '</div>';
			return;
		}
		if ( $existing ) {
			return;
		}
		echo '<div class="ssa-join-card"><strong>' . esc_html( SSA_Settings::get( 'program_name' ) ) . '</strong><br>' . esc_html__( 'Create content, share your code and earn on every sale — you decide how to split your share between commission and follower discount.', 'splitshare-affiliates' ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Apply now', 'splitshare-affiliates' ) . ' →</a></div>';
	}

	/** Şablonlarda kullanılan yardımcılar. */
	public static function status_label( $status ) {
		$map = array(
			'pending'  => __( 'Pending', 'splitshare-affiliates' ),
			'approved' => __( 'Approved', 'splitshare-affiliates' ),
			'paid'     => __( 'Paid', 'splitshare-affiliates' ),
			'void'     => __( 'Cancelled', 'splitshare-affiliates' ),
			'open'     => __( 'Scheduled', 'splitshare-affiliates' ),
		);
		return '<span class="ssa-status ssa-status-' . esc_attr( $status ) . '">' . esc_html( isset( $map[ $status ] ) ? $map[ $status ] : $status ) . '</span>';
	}
}
