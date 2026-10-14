<?php
/**
 * Saf komisyon hesaplayıcı — WordPress'e bağımlı değildir, WP'siz test edilir.
 *
 * Model: her kalem için grup payı (kategoriye göre) bulunur.
 *  - Ortağın kuponu kalemi kapsıyorsa: komisyon oranı = pay − kupon indirimi.
 *  - Kapsam dışı kalem veya kuponsuz (link) sipariş: komisyon oranı = min(link oranı, pay).
 * Sipariş bazında minimum sepet eşiği, tekrar müşteri faktörü ve tavan uygulanır.
 *
 * 1.4.0 — PAY KAPISI: kupon indirimi kalemin payını aşıyorsa kalem kupon kapsamı
 * DIŞINDA kalır (indirim de uygulanmaz). Bu sınıf kapsamın tek otoritesidir;
 * WooCommerce tarafı da aynı kararı `SSA_Coupon::scope_check()` ile buradan okur,
 * böylece müşterinin ödediği tutar ile ortağın kazancı birbirine ters düşemez.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Calculator {

	/**
	 * Kalemin kategorilerine göre ortaklık payı (%).
	 * Hariç kategori → 0; grup eşleşmelerinden en düşük pay; yoksa varsayılan pay.
	 *
	 * @param int[] $category_ids Ürünün kategorileri + ataları (düz liste).
	 * @param array $rules        default_share, group_shares (cat_id => pct), excluded_categories.
	 */
	public static function share_for( array $category_ids, array $rules ) {
		$category_ids = array_map( 'intval', $category_ids );
		$excluded     = array_map( 'intval', (array) ( isset( $rules['excluded_categories'] ) ? $rules['excluded_categories'] : array() ) );
		if ( array_intersect( $category_ids, $excluded ) ) {
			return 0.0;
		}
		$matches = array();
		foreach ( (array) ( isset( $rules['group_shares'] ) ? $rules['group_shares'] : array() ) as $cat => $pct ) {
			if ( in_array( (int) $cat, $category_ids, true ) ) {
				$matches[] = (float) $pct;
			}
		}
		return $matches ? min( $matches ) : (float) $rules['default_share'];
	}

	/**
	 * Kalemin gerçek payı (%): grup payı + kampanya tabanı + ürün hızlandırıcıları.
	 *
	 * Hem komisyon hesabı hem WooCommerce kapsam kontrolü aynı payı görmeli;
	 * bu yüzden ayrı bir metot (bkz. SSA_Coupon::scope_check).
	 *
	 * @param int   $product_id   Ana ürün id'si.
	 * @param int[] $category_ids Kategoriler + ataları.
	 * @param array $rules        SSA_Settings::rules() çıktısı.
	 */
	public static function effective_share( $product_id, array $category_ids, array $rules ) {
		$share = self::share_for( $category_ids, $rules );
		if ( $share <= 0 ) {
			return 0.0; // Hariç kategori: kampanya da hızlandırıcı da açmaz.
		}
		if ( isset( $rules['campaign_share'] ) && null !== $rules['campaign_share'] ) {
			$share = max( $share, (float) $rules['campaign_share'] );
		}
		foreach ( (array) ( isset( $rules['boosters'] ) ? $rules['boosters'] : array() ) as $b ) {
			if ( in_array( (int) $product_id, array_map( 'intval', (array) $b['product_ids'] ), true ) ) {
				$share += (float) $b['pct'];
			}
		}
		return (float) $share;
	}

	/**
	 * Kupon bu kalemi kapsıyor mu? (indirim de komisyon da bu karara uyar)
	 *
	 * @param array|null $coupon       discount_pct, scope_type (all|products|categories|brands), scope_ids (int[]).
	 * @param int        $product_id   Ana ürün id'si.
	 * @param int[]      $category_ids Kategoriler + ataları.
	 * @param int[]      $brand_ids    Marka terim kimlikleri.
	 * @param float|null $share        Kalemin payı (%). Verilirse pay kapısı uygulanır:
	 *                                 indirim payı AŞIYORSA kalem kapsam dışıdır.
	 */
	public static function covers( $coupon, $product_id, array $category_ids, array $brand_ids = array(), $share = null ) {
		if ( ! $coupon ) {
			return false;
		}

		/*
		 * PAY KAPISI (1.4.0). Ortak %15 kupon yapıp satış payı %8 olan bir gruptan
		 * geldiğinde eskiden indirim uygulanıyor, komisyon max(0, 8−15) = 0 oluyordu:
		 * mağaza bütçesinin iki katı indirim veriyor, ortak hiçbir şey kazanmıyordu.
		 * Artık böyle kalemler kupon kapsamı dışında kalır ve link oranı kazandırır.
		 * Eşitlikte (indirim = pay) kapsam sürer; mağazanın maliyeti aynıdır ve
		 * ortağın payını tamamen indirime dönüştürmesi bilinçli bir tercihtir.
		 */
		if ( null !== $share && (float) ( isset( $coupon['discount_pct'] ) ? $coupon['discount_pct'] : 0 ) > (float) $share ) {
			return false;
		}

		$type = isset( $coupon['scope_type'] ) ? (string) $coupon['scope_type'] : 'all';
		$ids  = array_map( 'intval', (array) ( isset( $coupon['scope_ids'] ) ? $coupon['scope_ids'] : array() ) );

		if ( 'products' === $type ) {
			return in_array( (int) $product_id, $ids, true );
		}
		if ( 'categories' === $type ) {
			return (bool) array_intersect( array_map( 'intval', $category_ids ), $ids );
		}
		if ( 'brands' === $type ) {
			return (bool) array_intersect( array_map( 'intval', $brand_ids ), $ids );
		}

		/*
		 * 'all' kapsamı: hariç tutulanlar düşülür (2026-09-09).
		 * Kupon tüm mağazada geçerlidir ama ortak birkaç ürün/kategori/markayı
		 * dışarıda bırakmış olabilir; komisyon hesabı da aynı kapsamı görmeli,
		 * aksi hâlde WooCommerce indirim uygulamazken biz uygulanmış sayardık.
		 */
		$haric = isset( $coupon['exclude_ids'] ) ? (array) $coupon['exclude_ids'] : array();
		if ( ! empty( $haric['products'] ) && in_array( (int) $product_id, array_map( 'intval', (array) $haric['products'] ), true ) ) {
			return false;
		}
		if ( ! empty( $haric['categories'] ) && array_intersect( array_map( 'intval', $category_ids ), array_map( 'intval', (array) $haric['categories'] ) ) ) {
			return false;
		}
		if ( ! empty( $haric['brands'] ) && array_intersect( array_map( 'intval', $brand_ids ), array_map( 'intval', (array) $haric['brands'] ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param array $items Her biri: product_id, category_ids (int[]), paid_total (indirim sonrası, KDV dahil).
	 * @param array $ctx   coupon (null|array), link_commission_pct, order_base_total, is_new_customer, rules.
	 * @return array amount, base_total, reason, breakdown[], factors{returning, capped}
	 */
	public static function calculate( array $items, array $ctx ) {
		$rules = $ctx['rules'];
		$base  = (float) $ctx['order_base_total'];
		$out   = array(
			'amount'     => 0.0,
			'base_total' => $base,
			'reason'     => null,
			'breakdown'  => array(),
			'factors'    => array( 'returning' => 1.0, 'capped' => false ),
		);

		if ( $base < (float) $rules['min_order'] ) {
			$out['reason'] = 'below_min';
			return $out;
		}

		$coupon   = ! empty( $ctx['coupon'] ) ? (array) $ctx['coupon'] : null;
		$discount = $coupon ? (float) $coupon['discount_pct'] : 0.0;
		$link_pct = isset( $ctx['link_commission_pct'] ) ? (float) $ctx['link_commission_pct'] : 0.0;
		$total    = 0.0;

		foreach ( $items as $it ) {
			$share   = self::effective_share( (int) $it['product_id'], (array) $it['category_ids'], $rules );
			$brands  = (array) ( isset( $it['brand_ids'] ) ? $it['brand_ids'] : array() );
			$covered = self::covers( $coupon, (int) $it['product_id'], (array) $it['category_ids'], $brands, $share );
			// Kapsam dışı kalma sebebi pay kapısı mı, yoksa kuponun kapsamı mı? Arayüz ayrımı gösterir.
			$paydan  = ! $covered && $coupon && $discount > $share
				&& self::covers( $coupon, (int) $it['product_id'], (array) $it['category_ids'], $brands );
			$eff     = $covered ? max( 0.0, $share - $discount ) : max( 0.0, min( $link_pct, $share ) );
			$amt     = (float) $it['paid_total'] * $eff / 100;

			$out['breakdown'][] = array(
				'product_id'        => (int) $it['product_id'],
				'share'             => $share,
				'covered'           => $covered,
				'excluded_by_share' => (bool) $paydan,
				'effective_pct'     => $eff,
				'amount'            => round( $amt, 4 ),
			);
			$total += $amt;
		}

		if ( empty( $ctx['is_new_customer'] ) ) {
			$out['factors']['returning'] = (float) $rules['returning_factor'];
			$total                      *= $out['factors']['returning'];
		}
		if ( $total > (float) $rules['max_commission'] ) {
			$total                    = (float) $rules['max_commission'];
			$out['factors']['capped'] = true;
		}

		$rounding      = isset( $rules['rounding'] ) ? $rules['rounding'] : 'lira';
		$out['amount'] = ( 'kurus' === $rounding ) ? round( $total, 2 ) : (float) round( $total, 0 );
		if ( 0.0 === $out['amount'] && null === $out['reason'] ) {
			/*
			 * 2026-09-09: Tek bir "zero" sebebi ortağa hiçbir şey anlatmıyordu.
			 * En sık karşılaşılan durum, kupon indiriminin o ürünlerin grup
			 * payını AŞMASI: pay %8 iken ortak %10 indirim verirse
			 * komisyon = max(0, 8 - 10) = 0 olur. Bu, siparişin iptal olması
			 * DEĞİLDİR ve ortağın aksiyon alabileceği bir durumdur; ayrı
			 * sebep koduyla işaretleniyor ki arayüz doğru mesajı gösterebilsin.
			 */
			$kapsanan = 0;
			$asilan   = 0;
			foreach ( $out['breakdown'] as $satir ) {
				if ( ! empty( $satir['covered'] ) ) {
					$kapsanan++;
					if ( (float) $satir['share'] <= $discount ) {
						$asilan++;
					}
				}
			}
			$out['reason'] = ( $kapsanan > 0 && $kapsanan === $asilan ) ? 'discount_over_share' : 'zero';
		}
		return $out;
	}
}
