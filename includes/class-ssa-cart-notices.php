<?php
/**
 * Sepet/ödeme sayfasında ortak kuponunu MÜŞTERİYE anlatan bildirimler.
 *
 * Ortak kuponları WooCommerce'in standart kuponlarından iki yönden farklı
 * davranır ve müşteri bunu hiçbir yerden göremiyordu:
 *
 *  1. Kapsam — kupon yalnızca belirli ürün/kategori/markalarda geçerli olabilir
 *     (ayrıca payı indirimden düşük olan ürünler kapsam dışında kalır, bkz.
 *     SSA_Coupon::scope_check). Karma sepette indirim yalnızca kapsamdaki
 *     kalemlere işler; müşteri toplam indirimi görüp "eksik hesaplanmış"
 *     sanıyordu.
 *  2. Minimum sepet — kupon eşiğin altında reddedilir. WooCommerce'in kuru
 *     mesajı yerine ne kadar eksik olduğunu söyleyen bir mesaj gösteriyoruz.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Cart_Notices {

	public static function init() {
		add_filter( 'woocommerce_coupon_error', array( __CLASS__, 'coupon_error' ), 20, 3 );
		add_action( 'woocommerce_before_cart', array( __CLASS__, 'scope_notice' ), 20 );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'scope_notice' ), 20 );
	}

	/* ---------- Kupon reddedildiğinde ---------- */

	/**
	 * @param string         $err      WooCommerce'in mesajı.
	 * @param int            $err_code WC_Coupon::E_WC_COUPON_* kodu.
	 * @param WC_Coupon|null $coupon   Kupon (kod bulunamadıysa null).
	 */
	public static function coupon_error( $err, $err_code, $coupon ) {
		if ( ! ( $coupon instanceof WC_Coupon ) || ! class_exists( 'SSA_Coupon' ) ) {
			return $err;
		}
		$row = SSA_Coupon::row_for( $coupon );
		if ( ! $row ) {
			return $err;
		}
		$kod = strtoupper( $coupon->get_code() );

		if ( (int) $err_code === WC_Coupon::E_WC_COUPON_MIN_SPEND_LIMIT_NOT_MET ) {
			$esik = (float) $coupon->get_minimum_amount();
			$eksik = $esik - self::cart_total();
			if ( $eksik > 0 ) {
				return sprintf(
					/* translators: 1: coupon code, 2: minimum amount, 3: amount still needed */
					__( '%1$s coupon works on baskets of %2$s and above — add %3$s more and apply it again.', 'splitshare-affiliates' ),
					$kod,
					wp_strip_all_tags( wc_price( $esik ) ),
					wp_strip_all_tags( wc_price( $eksik ) )
				);
			}
			return sprintf(
				/* translators: 1: coupon code, 2: minimum amount */
				__( '%1$s coupon works on baskets of %2$s and above.', 'splitshare-affiliates' ),
				$kod,
				wp_strip_all_tags( wc_price( $esik ) )
			);
		}

		if ( in_array( (int) $err_code, array( WC_Coupon::E_WC_COUPON_NOT_APPLICABLE, WC_Coupon::E_WC_COUPON_EXCLUDED_PRODUCTS, WC_Coupon::E_WC_COUPON_EXCLUDED_CATEGORIES ), true ) ) {
			$kapsam = self::scope_text( $row );
			if ( '' === $kapsam ) {
				// Kupon tüm mağaza için; sepetteki ürünler kampanya dışında (hariç tutulmuş ya da payı düşük).
				return sprintf(
					/* translators: %s: coupon code */
					__( '%s coupon does not apply to the products in your basket — they are outside this campaign.', 'splitshare-affiliates' ),
					$kod
				);
			}
			return sprintf(
				/* translators: 1: coupon code, 2: scope description */
				__( '%1$s coupon is only valid for %2$s. There is nothing in your basket it applies to.', 'splitshare-affiliates' ),
				$kod,
				$kapsam
			);
		}

		return $err;
	}

	/* ---------- Karma sepet bildirimi ---------- */

	/**
	 * Uygulanmış ortak kuponu sepetin yalnızca bir kısmını kapsıyorsa açıklar.
	 * Tek seferlik `wc_add_notice` yerine sayfa her açıldığında gösterilir —
	 * müşteri kuponu bir kere uygulayıp bildirimi kaçırabiliyordu.
	 */
	public static function scope_notice() {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		foreach ( WC()->cart->get_applied_coupons() as $kod ) {
			$coupon = new WC_Coupon( $kod );
			$row    = $coupon->get_id() ? SSA_Coupon::row_for( $coupon ) : null;
			if ( ! $row ) {
				continue;
			}
			$kapsamda = 0;
			$disarida = array();
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( empty( $item['data'] ) || ! ( $item['data'] instanceof WC_Product ) ) {
					continue;
				}
				if ( $coupon->is_valid_for_product( $item['data'], $item ) ) {
					$kapsamda++;
				} else {
					$disarida[] = $item['data']->get_name();
				}
			}
			if ( ! $disarida || ! $kapsamda ) {
				continue; // Tamamı kapsamda ya da kupon zaten reddedilmiş.
			}
			$liste = count( $disarida ) > 3
				? implode( ', ', array_slice( $disarida, 0, 3 ) ) . ' ' . sprintf(
					/* translators: %d: number of further products */
					_n( 'and %d more', 'and %d more', count( $disarida ) - 3, 'splitshare-affiliates' ),
					count( $disarida ) - 3
				)
				: implode( ', ', $disarida );

			wc_print_notice(
				sprintf(
					/* translators: 1: coupon code, 2: number of items in scope, 3: total items, 4: product names outside the scope */
					__( '%1$s coupon applies to %2$d of the %3$d items in your basket — the discount was applied to those only. Outside the coupon: %4$s.', 'splitshare-affiliates' ),
					strtoupper( $kod ),
					$kapsamda,
					$kapsamda + count( $disarida ),
					$liste
				),
				'notice'
			);
		}
	}

	/* ---------- Yardımcılar ---------- */

	/**
	 * Kuponun kapsamı, müşterinin anlayacağı dilde (ürün/kategori/marka adları).
	 * Tüm mağaza kapsamında '' döner — orada kapsam saymanın anlamı yok.
	 */
	public static function scope_text( $row ) {
		if ( 'all' === $row->scope_type ) {
			return '';
		}
		return implode( ', ', SSA_Partner_Coupons::scope_names( $row, 4 ) );
	}

	/**
	 * Eşikle kıyaslanan sepet tutarı.
	 * WooCommerce minimum sepet kontrolünde `get_displayed_subtotal()` kullanır
	 * (WC_Discounts::get_object_subtotal); eksik tutarı aynı tabandan hesaplarız,
	 * yoksa müşteriye yanlış rakam söyleriz.
	 */
	private static function cart_total() {
		return WC()->cart ? (float) WC()->cart->get_displayed_subtotal() : 0.0;
	}
}
