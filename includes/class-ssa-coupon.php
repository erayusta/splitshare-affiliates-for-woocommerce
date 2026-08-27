<?php
/**
 * Ortak kodu ↔ WooCommerce kuponu.
 * Kupon: yüzde indirim = ortağın indirim %'si, tekil kullanım, kullanıcı başına limit,
 * minimum sepet, hariç kategoriler. Meta `_ssa_partner_id` sahipliği belirtir.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Coupon {

	const META = '_ssa_partner_id';

	/** Kupon id (yayımlanmamış/draft dahil, çöp hariç). wc_get_coupon_id_by_code yalnızca yayımlananı bulur. */
	public static function coupon_id( $code ) {
		global $wpdb;
		$code = (string) $code;
		if ( '' === $code ) {
			return 0;
		}
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_coupon' AND post_status <> 'trash' AND post_title = %s ORDER BY post_status = 'publish' DESC, ID DESC LIMIT 1", $code ) ); // phpcs:ignore WordPress.DB
	}

	public static function init() {
		// Kupon düzenleme ekranında ortak kuponlarını uyar.
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		// Eklenti kupon ayarlarını her kayıtta yeniden uygular (elle değişiklik ezilir).
		add_action( 'woocommerce_coupon_options_save', array( __CLASS__, 'resync_on_save' ), 20, 2 );
	}

	/** @return int|WP_Error kupon id */
	public static function sync( SSA_Partner $p ) {
		if ( '' === $p->code ) {
			return new WP_Error( 'ssa_no_code', __( 'Partner has no code.', 'splitshare-affiliates' ) );
		}
		$coupon = self::find_for_partner( $p->id, $p->code );
		if ( ! $coupon ) {
			$existing = self::coupon_id( $p->code );
			if ( $existing && (int) get_post_meta( $existing, self::META, true ) !== $p->id ) {
				return new WP_Error( 'ssa_code_taken', __( 'A coupon with this code already exists.', 'splitshare-affiliates' ) );
			}
			$coupon = $existing ? new WC_Coupon( $existing ) : new WC_Coupon();
			$coupon->set_code( $p->code );
		}
		self::apply_rules( $coupon, $p );
		$coupon->update_meta_data( self::META, $p->id );
		$coupon->set_date_expires( null );
		$coupon->set_status( $p->is_active() ? 'publish' : 'draft' );
		$id = $coupon->save();
		return $id ? $id : new WP_Error( 'ssa_coupon', __( 'Could not save the coupon.', 'splitshare-affiliates' ) );
	}

	/** Eski kod kuponuna son kullanma tarihi ver. */
	public static function expire_previous( SSA_Partner $p ) {
		if ( '' === $p->previous_code ) {
			return;
		}
		$id = self::coupon_id( $p->previous_code );
		if ( ! $id || (int) get_post_meta( $id, self::META, true ) !== $p->id ) {
			return;
		}
		$coupon = new WC_Coupon( $id );
		$coupon->set_date_expires( $p->previous_code_expires ? strtotime( $p->previous_code_expires ) : time() );
		$coupon->save();
	}

	private static function apply_rules( WC_Coupon $coupon, SSA_Partner $p ) {
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( max( 0.0, (float) $p->discount_pct ) );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit_per_user( (int) SSA_Settings::get( 'coupon_usage_limit_per_user' ) );
		$coupon->set_minimum_amount( (float) SSA_Settings::get( 'min_order' ) );
		$coupon->set_excluded_product_categories( array_map( 'intval', (array) SSA_Settings::get( 'excluded_categories', array() ) ) );
		$coupon->set_description( sprintf(
			/* translators: 1: program name, 2: partner name */
			__( '%1$s partner code — %2$s (managed by SplitShare Affiliates, do not edit).', 'splitshare-affiliates' ),
			SSA_Settings::get( 'program_name' ),
			$p->display_name()
		) );
	}

	/** @return WC_Coupon|null */
	public static function find_for_partner( $partner_id, $code ) {
		$id = self::coupon_id( $code );
		if ( $id && (int) get_post_meta( $id, self::META, true ) === (int) $partner_id ) {
			return new WC_Coupon( $id );
		}
		return null;
	}

	public static function partner_id_for_coupon( $coupon ) {
		if ( is_string( $coupon ) ) {
			$id = self::coupon_id( $coupon );
		} elseif ( $coupon instanceof WC_Coupon ) {
			$id = $coupon->get_id();
		} else {
			$id = (int) $coupon;
		}
		return $id ? (int) get_post_meta( $id, self::META, true ) : 0;
	}

	/** Sepetteki ortak kuponunu bul. @return array{partner_id:int, code:string}|null */
	public static function partner_coupon_in( array $codes ) {
		foreach ( $codes as $code ) {
			$pid = self::partner_id_for_coupon( $code );
			if ( $pid ) {
				return array( 'partner_id' => $pid, 'code' => strtoupper( $code ) );
			}
		}
		return null;
	}

	public static function admin_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'shop_coupon' !== $screen->id || empty( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$pid = (int) get_post_meta( (int) $_GET['post'], self::META, true ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $pid ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'This coupon is a partner code managed by SplitShare Affiliates. Discount, usage and category rules are re-applied automatically; edit them from the Affiliates settings instead.', 'splitshare-affiliates' ) . '</p></div>';
		}
	}

	public static function resync_on_save( $post_id, $coupon ) {
		$pid = (int) get_post_meta( $post_id, self::META, true );
		if ( $pid && class_exists( 'SSA_Partners' ) ) {
			$p = SSA_Partners::get( $pid );
			if ( $p && strtoupper( $p->code ) === strtoupper( $coupon->get_code() ) ) {
				self::sync( $p );
			}
		}
	}
}
