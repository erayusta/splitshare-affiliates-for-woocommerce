<?php
/**
 * Ortak kuponu (ssa_coupons satırı) ↔ WooCommerce kuponu köprüsü.
 * Kupon: yüzde indirim, tek başına kullanım, müşteri başına limit, minimum sepet,
 * hariç kategoriler, kapsam (ürün/kategori kısıtı), bitiş tarihi.
 * Meta `_ssa_partner_id` sahipliği, `_ssa_coupon_id` satırı belirtir.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Coupon {

	const META        = '_ssa_partner_id';
	const META_COUPON = '_ssa_coupon_id';

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
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		// Eklenti kupon ayarlarını her kayıtta yeniden uygular (elle değişiklik ezilir).
		add_action( 'woocommerce_coupon_options_save', array( __CLASS__, 'resync_on_save' ), 20, 2 );
	}

	/**
	 * Kupon satırını WC kuponuna yaz (oluştur/güncelle).
	 * @param object $row ssa_coupons satırı (SSA_Partner_Coupons::get)
	 * @return int|WP_Error WC kupon id
	 */
	public static function sync( $row ) {
		$coupon = null;
		if ( $row->wc_coupon_id ) {
			$c = new WC_Coupon( $row->wc_coupon_id );
			if ( $c->get_id() && 'trash' !== get_post_status( $c->get_id() ) ) {
				$coupon = $c;
			}
		}
		if ( ! $coupon ) {
			$existing = self::coupon_id( $row->code );
			if ( $existing ) {
				$owner_row = (int) get_post_meta( $existing, self::META_COUPON, true );
				$owner_p   = (int) get_post_meta( $existing, self::META, true );
				$mine      = ( $owner_row && $owner_row === $row->id ) || ( ! $owner_row && $owner_p && $owner_p === $row->partner_id );
				if ( ! $mine ) {
					return new WP_Error( 'ssa_code_taken', __( 'A coupon with this code already exists.', 'splitshare-affiliates' ) );
				}
			}
			$coupon = $existing ? new WC_Coupon( $existing ) : new WC_Coupon();
			$coupon->set_code( $row->code );
		}
		$partner = SSA_Partners::get( $row->partner_id );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( max( 0.0, (float) $row->discount_pct ) );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit_per_user( (int) SSA_Settings::get( 'coupon_usage_limit_per_user' ) );
		$coupon->set_minimum_amount( (float) SSA_Settings::get( 'min_order' ) );
		$coupon->set_excluded_product_categories( array_map( 'intval', (array) SSA_Settings::get( 'excluded_categories', array() ) ) );
		$coupon->set_product_ids( 'products' === $row->scope_type ? $row->scope_ids : array() );
		$coupon->set_product_categories( 'categories' === $row->scope_type ? $row->scope_ids : array() );
		$coupon->set_date_expires( $row->expires_at ? strtotime( $row->expires_at ) : null );
		$coupon->set_description( sprintf(
			/* translators: 1: program name, 2: partner name, 3: campaign name */
			__( '%1$s partner coupon — %2$s%3$s (managed by SplitShare Affiliates, do not edit).', 'splitshare-affiliates' ),
			SSA_Settings::get( 'program_name' ),
			$partner ? $partner->display_name() : '#' . $row->partner_id,
			$row->name ? ' · ' . $row->name : ''
		) );
		$coupon->update_meta_data( self::META, $row->partner_id );
		$coupon->update_meta_data( self::META_COUPON, $row->id );
		$live = 'active' === $row->status && $partner && $partner->is_active();
		$coupon->set_status( $live ? 'publish' : 'draft' );
		$id = $coupon->save();
		return $id ? $id : new WP_Error( 'ssa_coupon', __( 'Could not save the coupon.', 'splitshare-affiliates' ) );
	}

	/** Satır silinince WC kuponunu çöpe at. */
	public static function trash( $row ) {
		$id = $row->wc_coupon_id ? $row->wc_coupon_id : self::coupon_id( $row->code );
		if ( $id && (int) get_post_meta( $id, self::META_COUPON, true ) === (int) $row->id ) {
			wp_trash_post( $id );
		}
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

	/** Sepetteki ortak kuponunu bul. @return array{partner_id:int, coupon_id:int, code:string}|null */
	public static function partner_coupon_in( array $codes ) {
		foreach ( $codes as $code ) {
			$id = self::coupon_id( $code );
			if ( ! $id ) {
				continue;
			}
			$pid = (int) get_post_meta( $id, self::META, true );
			if ( $pid ) {
				return array( 'partner_id' => $pid, 'coupon_id' => (int) get_post_meta( $id, self::META_COUPON, true ), 'code' => strtoupper( $code ) );
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
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'This coupon belongs to an affiliate partner and is managed by SplitShare Affiliates. Discount, usage, scope and category rules are re-applied automatically; manage it from the partner profile instead.', 'splitshare-affiliates' ) . '</p></div>';
		}
	}

	public static function resync_on_save( $post_id, $coupon ) {
		$cid = (int) get_post_meta( $post_id, self::META_COUPON, true );
		if ( $cid && class_exists( 'SSA_Partner_Coupons' ) ) {
			$row = SSA_Partner_Coupons::get( $cid );
			if ( $row && strtoupper( $row->code ) === strtoupper( $coupon->get_code() ) ) {
				self::sync( $row );
			}
		}
	}
}
