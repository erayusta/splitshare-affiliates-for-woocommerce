<?php
/**
 * Ortak kuponu (ssa_coupons satırı) ↔ WooCommerce kuponu köprüsü.
 * Kupon: yüzde indirim, tek başına kullanım, müşteri başına limit, minimum sepet,
 * hariç kategoriler, kapsam (ürün/kategori kısıtı), bitiş tarihi.
 * Marka kapsamı ve pay kapısı WooCommerce'e yazılamadığı için `scope_check()` ile
 * uygulanır — kapsamın tek otoritesi SSA_Calculator::covers().
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
		// Kapsam kararının tek otoritesi SSA_Calculator::covers(); WooCommerce de onu okur.
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( __CLASS__, 'scope_check' ), 20, 3 );
	}

	/**
	 * Ortak kuponu bu ürüne indirim uygulayabilir mi?
	 *
	 * WooCommerce kuponun kendi ürün/kategori kısıtlarını zaten uyguladı; burada
	 * yalnızca DARALTIYORUZ. İki şeyi kapatır:
	 *
	 *  1. MARKA kapsamı — WooCommerce kuponlarda marka kısıtını natif desteklemez,
	 *     kupon nesnesine yazılamıyor. Bu filtre olmadan "seçili markalar" kuponu
	 *     tüm mağazada indirim yapıyordu (komisyon ise yalnızca o markadan
	 *     hesaplanıyordu).
	 *  2. PAY KAPISI — kupon indirimi ürünün grup payını aşıyorsa indirim
	 *     uygulanmaz; ürün kupon kapsamı dışında kalır ve ortağa link oranı yazar.
	 *     Aksi hâlde mağaza payından fazla indirim verir, ortak hiç kazanmazdı.
	 *
	 * @param bool       $valid   WooCommerce'in kendi kararı.
	 * @param WC_Product $product Sepet/sipariş kalemi ürünü.
	 * @param WC_Coupon  $coupon  Kupon.
	 * @return bool
	 */
	public static function scope_check( $valid, $product, $coupon ) {
		// Yalnızca daraltırız: WooCommerce zaten "hayır" dediyse karışmayız.
		if ( ! $valid || ! ( $coupon instanceof WC_Coupon ) || ! ( $product instanceof WC_Product ) ) {
			return $valid;
		}
		$row = self::row_for( $coupon );
		if ( ! $row ) {
			return $valid;
		}
		$pid = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();

		return SSA_Partner_Coupons::covers(
			$row,
			$pid,
			SSA_Partner_Coupons::category_ids( $pid ),
			SSA_Partner_Coupons::brand_ids( $pid ),
			self::share_for_product( $pid )
		);
	}

	/** @var array İstek içi önbellek: wc_coupon_id => ssa_coupons satırı|null. */
	private static $row_cache = array();

	/** Kupon satırı değiştiğinde önbelleği boşalt (aynı istekte düzenle + doğrula). */
	public static function flush_cache() {
		self::$row_cache = array();
	}

	/** Kuponun ssa_coupons satırı (ortak kuponu değilse null). İstek başına önbellekli. */
	public static function row_for( $coupon ) {
		$id = $coupon instanceof WC_Coupon ? (int) $coupon->get_id() : (int) $coupon;
		if ( ! $id ) {
			return null;
		}
		if ( ! array_key_exists( $id, self::$row_cache ) ) {
			$row_id                 = (int) get_post_meta( $id, self::META_COUPON, true );
			self::$row_cache[ $id ] = $row_id && class_exists( 'SSA_Partner_Coupons' ) ? SSA_Partner_Coupons::get( $row_id ) : null;
		}
		return self::$row_cache[ $id ];
	}

	/** Ürünün ortaklık payı (%). İstek başına önbellekli — sepet başına çok kez sorulur. */
	public static function share_for_product( $product_id ) {
		static $cache = array();
		$product_id = (int) $product_id;
		if ( ! isset( $cache[ $product_id ] ) ) {
			$cache[ $product_id ] = SSA_Calculator::effective_share(
				$product_id,
				SSA_Partner_Coupons::category_ids( $product_id ),
				SSA_Settings::rules()
			);
		}
		return $cache[ $product_id ];
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
		/*
		 * 2026-09-09: Kapsam ve hariç tutulanlar.
		 *
		 * Program genelindeki hariç kategoriler + ortağın "Tüm mağaza"
		 * kapsamında kendi seçtiği hariç ürün/kategoriler birleşir.
		 * MARKA kısıtını ve pay kapısını WooCommerce natif desteklemez; onlar
		 * `woocommerce_coupon_is_valid_for_product` ile uygulanır
		 * (bkz. self::scope_check).
		 */
		$haric = isset( $row->exclude_ids ) ? (array) $row->exclude_ids : array();

		$haric_kategori = array_map( 'intval', (array) SSA_Settings::get( 'excluded_categories', array() ) );
		if ( ! empty( $haric['categories'] ) ) {
			$haric_kategori = array_merge( $haric_kategori, array_map( 'intval', (array) $haric['categories'] ) );
		}
		$coupon->set_excluded_product_categories( array_values( array_unique( $haric_kategori ) ) );
		$coupon->set_excluded_product_ids(
			! empty( $haric['products'] ) ? array_map( 'intval', (array) $haric['products'] ) : array()
		);

		$coupon->set_product_ids( 'products' === $row->scope_type ? $row->scope_ids : array() );
		$coupon->set_product_categories( 'categories' === $row->scope_type ? $row->scope_ids : array() );
		$coupon->set_date_expires( $row->expires_at ? (int) get_gmt_from_date( $row->expires_at, 'U' ) : null );
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
		self::flush_cache();
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
