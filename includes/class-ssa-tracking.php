<?php
/**
 * Takip: ?ref=KOD → cookie + tıklama kaydı + temiz URL'e yönlendirme; checkout'ta sipariş atfı.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Tracking {

	const COOKIE = 'ssa_ref';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_capture_ref' ), 5 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'attribute_order' ), 20 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'attribute_order' ), 20 );
	}

	/** ?ref= yakala. */
	public static function maybe_capture_ref() {
		if ( empty( $_GET['ref'] ) || is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$code = strtoupper( sanitize_text_field( wp_unslash( $_GET['ref'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( preg_match( '/^[A-Z0-9]{4,12}$/', $code ) ) {
			$partner = SSA_Partners::get_by_code( $code );
			if ( $partner && $partner->is_active() ) {
				self::set_cookie( $partner->code );
				self::record_click( $partner->id );
			}
		}
		nocache_headers();
		$clean = remove_query_arg( 'ref' );
		wp_safe_redirect( $clean ? $clean : home_url( '/' ), 302 );
		exit;
	}

	private static function set_cookie( $code ) {
		$days = max( 1, (int) SSA_Settings::get( 'cookie_days' ) );
		$val  = $code . '|' . time();
		setcookie( self::COOKIE, $val, time() + $days * DAY_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );
		$_COOKIE[ self::COOKIE ] = $val;
	}

	/** Geçerli cookie kodu (süre kontrolü). */
	public static function current_ref() {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return null;
		}
		$parts = explode( '|', sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) );
		$code  = strtoupper( $parts[0] );
		$ts    = isset( $parts[1] ) ? (int) $parts[1] : 0;
		$days  = max( 1, (int) SSA_Settings::get( 'cookie_days' ) );
		if ( ! preg_match( '/^[A-Z0-9]{4,12}$/', $code ) || $ts + $days * DAY_IN_SECONDS < time() ) {
			return null;
		}
		return $code;
	}

	public static function record_click( $partner_id ) {
		global $wpdb;
		$t    = SSA_Install::tables()['clicks'];
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ua   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$hash = substr( hash( 'sha256', $ip . '|' . $ua ), 0, 32 );
		$path = isset( $_SERVER['REQUEST_URI'] ) ? substr( sanitize_text_field( wp_unslash( remove_query_arg( 'ref', $_SERVER['REQUEST_URI'] ) ) ), 0, 255 ) : '/';

		// Aynı ziyaretçi + ortak için 1 saat içinde tekrar sayma.
		$recent = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE partner_id = %d AND visitor_hash = %s AND created_at > %s", $partner_id, $hash, gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - HOUR_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB
		if ( $recent ) {
			return;
		}
		$product_id = null;
		if ( function_exists( 'url_to_postid' ) ) {
			$pid = url_to_postid( home_url( $path ) );
			if ( $pid && 'product' === get_post_type( $pid ) ) {
				$product_id = $pid;
			}
		}
		$wpdb->insert( $t, array( // phpcs:ignore WordPress.DB
			'partner_id'   => (int) $partner_id,
			'landing'      => $path,
			'product_id'   => $product_id,
			'visitor_hash' => $hash,
			'created_at'   => current_time( 'mysql' ),
		) );
	}

	/**
	 * Siparişi ortağa atfeder: kupon > cookie. Ortağın kendi siparişi atfedilmez.
	 *
	 * @param WC_Order $order
	 */
	public static function attribute_order( $order ) {
		if ( ! $order instanceof WC_Order || '' !== (string) $order->get_meta( '_ssa_partner_id' ) ) {
			return;
		}
		$partner     = null;
		$attribution = '';
		$code        = '';

		$coupon = SSA_Coupon::partner_coupon_in( $order->get_coupon_codes() );
		if ( $coupon ) {
			$partner     = SSA_Partners::get( $coupon['partner_id'] );
			$attribution = 'coupon';
			$code        = $coupon['code'];
		} else {
			$ref = self::current_ref();
			if ( $ref ) {
				$partner     = SSA_Partners::get_by_code( $ref );
				$attribution = 'link';
				$code        = $ref;
			}
		}
		if ( ! $partner || ! in_array( $partner->status, array( 'active', 'paused' ), true ) ) {
			return;
		}
		if ( self::is_self_order( $order, $partner ) ) {
			$order->add_order_note( __( 'Affiliate: order placed by the partner themselves — no commission.', 'splitshare-affiliates' ) );
			return;
		}
		$order->update_meta_data( '_ssa_partner_id', $partner->id );
		$order->update_meta_data( '_ssa_code', $code );
		$order->update_meta_data( '_ssa_attribution', $attribution );
		if ( 'link' === $attribution ) {
			self::mark_conversion( $partner->id, $order->get_id() );
		}
	}

	public static function is_self_order( WC_Order $order, SSA_Partner $partner ) {
		if ( $order->get_customer_id() && (int) $order->get_customer_id() === (int) $partner->user_id ) {
			return true;
		}
		$email = strtolower( (string) $order->get_billing_email() );
		return '' !== $email && $email === strtolower( (string) $partner->email() );
	}

	private static function mark_conversion( $partner_id, $order_id ) {
		global $wpdb;
		$t    = SSA_Install::tables()['clicks'];
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ua   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$hash = substr( hash( 'sha256', $ip . '|' . $ua ), 0, 32 );
		$id   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE partner_id = %d AND visitor_hash = %s AND order_id IS NULL ORDER BY created_at DESC LIMIT 1", $partner_id, $hash ) ); // phpcs:ignore WordPress.DB
		if ( $id ) {
			$wpdb->update( $t, array( 'order_id' => (int) $order_id ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		}
	}

	/** Son N gün tıklama/sipariş sayıları (panel). */
	public static function click_stats( $partner_id, $days = 30 ) {
		global $wpdb;
		$t     = SSA_Install::tables()['clicks'];
		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $days * DAY_IN_SECONDS );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) clicks, COUNT(order_id) conversions FROM {$t} WHERE partner_id = %d AND created_at >= %s", $partner_id, $since ) ); // phpcs:ignore WordPress.DB
		return array( 'clicks' => (int) $row->clicks, 'conversions' => (int) $row->conversions );
	}

	/** Takip linki üret. */
	public static function link( $code, $url = '' ) {
		$url = $url ? $url : home_url( '/' );
		return add_query_arg( 'ref', rawurlencode( $code ), $url );
	}
}
