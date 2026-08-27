<?php
/**
 * Saf komisyon hesaplayıcı — WordPress'e bağımlı değildir, WP'siz test edilir.
 *
 * Model: her kalem için grup payı (kategoriye göre) bulunur; kod kullanıldıysa ortağın
 * seçtiği indirim yüzdesi paydan düşülür; ortağın komisyon yüzdesi bu kalanla sınırlanır.
 * Sipariş bazında minimum sepet eşiği, tekrar müşteri faktörü ve tavan uygulanır.
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
	 * @param array $items Her biri: product_id, category_ids (int[]), paid_total (indirim sonrası, KDV dahil).
	 * @param array $ctx   commission_pct, discount_pct, discount_applied, order_base_total, is_new_customer, rules.
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

		$deduct = ! empty( $ctx['discount_applied'] ) ? (float) $ctx['discount_pct'] : 0.0;
		$total  = 0.0;

		foreach ( $items as $it ) {
			$share = self::share_for( (array) $it['category_ids'], $rules );
			if ( $share > 0 && isset( $rules['campaign_share'] ) && null !== $rules['campaign_share'] ) {
				$share = max( $share, (float) $rules['campaign_share'] );
			}
			foreach ( (array) ( isset( $rules['boosters'] ) ? $rules['boosters'] : array() ) as $b ) {
				if ( $share > 0 && in_array( (int) $it['product_id'], array_map( 'intval', (array) $b['product_ids'] ), true ) ) {
					$share += (float) $b['pct'];
				}
			}
			$eff = max( 0.0, min( (float) $ctx['commission_pct'], $share - $deduct ) );
			$amt = (float) $it['paid_total'] * $eff / 100;

			$out['breakdown'][] = array(
				'product_id'    => (int) $it['product_id'],
				'share'         => $share,
				'effective_pct' => $eff,
				'amount'        => round( $amt, 4 ),
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
			$out['reason'] = 'zero';
		}
		return $out;
	}
}
