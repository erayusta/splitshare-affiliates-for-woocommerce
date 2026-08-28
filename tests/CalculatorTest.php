<?php
use PHPUnit\Framework\TestCase;

/**
 * Sepet örnekleri "Lezzet Ortakları — Satış Ortaklığı Programı Önerisi" dokümanından;
 * 1.2.0 modeli: kupon kapsamındaki kalem = pay − indirim, diğerleri = link oranı.
 */
final class CalculatorTest extends TestCase {

	private function rules( array $over = array() ) {
		return array_merge( array(
			'default_share'       => 15.0,
			'group_shares'        => array( 522 => 8.0, 119 => 8.0, 992 => 13.0, 965 => 13.0 ),
			'excluded_categories' => array( 900 ),
			'min_order'           => 750.0,
			'max_commission'      => 10000.0,
			'returning_factor'    => 0.5,
			'campaign_share'      => null,
			'boosters'            => array(),
			'rounding'            => 'lira',
		), $over );
	}

	private function coupon( $discount = 5.0, $scope = 'all', array $ids = array() ) {
		return array( 'discount_pct' => $discount, 'scope_type' => $scope, 'scope_ids' => $ids );
	}

	private function ctx( array $over = array() ) {
		return array_merge( array(
			'coupon'              => $this->coupon( 5.0 ),
			'link_commission_pct' => 10.0,
			'order_base_total'    => 0.0,
			'is_new_customer'     => true,
			'rules'               => $this->rules(),
		), $over );
	}

	public function test_below_min_order_gives_zero() {
		$r = SSA_Calculator::calculate( array( array( 'product_id' => 1, 'category_ids' => array( 114 ), 'paid_total' => 429.60 ) ), $this->ctx( array( 'order_base_total' => 429.60 ) ) );
		$this->assertSame( 0.0, $r['amount'] );
		$this->assertSame( 'below_min', $r['reason'] );
	}

	public function test_document_cold_coffee_basket_109() {
		// 1.144,30 sepet, %5 kupon → 1.087,08 ödendi; pay 15 − 5 = %10 → 108,71 → lira yuvarlama 109
		$items = array( array( 'product_id' => 1, 'category_ids' => array( 113 ), 'paid_total' => 1087.085 ) );
		$r     = SSA_Calculator::calculate( $items, $this->ctx( array( 'order_base_total' => 1087.085 ) ) );
		$this->assertSame( 109.0, $r['amount'] );
		$this->assertTrue( $r['breakdown'][0]['covered'] );
	}

	public function test_kurus_rounding() {
		$items = array( array( 'product_id' => 1, 'category_ids' => array( 113 ), 'paid_total' => 1087.085 ) );
		$r     = SSA_Calculator::calculate( $items, $this->ctx( array( 'order_base_total' => 1087.085, 'rules' => $this->rules( array( 'rounding' => 'kurus' ) ) ) ) );
		$this->assertSame( 108.71, $r['amount'] );
	}

	public function test_document_wholesale_1140() {
		// toptan payı %8, kupon %5 → efektif %3; 38.000 → 1.140
		$items = array( array( 'product_id' => 1, 'category_ids' => array( 522 ), 'paid_total' => 38000.0 ) );
		$r     = SSA_Calculator::calculate( $items, $this->ctx( array( 'order_base_total' => 38000.0 ) ) );
		$this->assertSame( 1140.0, $r['amount'] );
		$this->assertSame( 3.0, $r['breakdown'][0]['effective_pct'] );
	}

	public function test_document_ayse_1680() {
		$items = array( array( 'product_id' => 1, 'category_ids' => array( 114 ), 'paid_total' => 16800.0 ) );
		$r     = SSA_Calculator::calculate( $items, $this->ctx( array( 'order_base_total' => 16800.0 ) ) );
		$this->assertSame( 1680.0, $r['amount'] );
	}

	public function test_link_order_uses_link_rate_capped_by_share() {
		$items = array(
			array( 'product_id' => 1, 'category_ids' => array( 522 ), 'paid_total' => 1000.0 ), // pay 8 → min(10, 8) = 8
			array( 'product_id' => 2, 'category_ids' => array( 114 ), 'paid_total' => 1000.0 ), // pay 15 → 10
		);
		$r = SSA_Calculator::calculate( $items, $this->ctx( array( 'coupon' => null, 'order_base_total' => 2000.0 ) ) );
		$this->assertSame( 180.0, $r['amount'] );
		$this->assertFalse( $r['breakdown'][0]['covered'] );
	}

	public function test_product_scoped_coupon_covers_only_listed_products() {
		$items = array(
			array( 'product_id' => 7, 'category_ids' => array( 114 ), 'paid_total' => 500.0 ), // kapsamda: 15 − 10 = 5 → 25
			array( 'product_id' => 8, 'category_ids' => array( 114 ), 'paid_total' => 500.0 ), // kapsam dışı: link 10 → 50
		);
		$r = SSA_Calculator::calculate( $items, $this->ctx( array( 'coupon' => $this->coupon( 10.0, 'products', array( 7 ) ), 'order_base_total' => 1000.0 ) ) );
		$this->assertSame( 75.0, $r['amount'] );
		$this->assertTrue( $r['breakdown'][0]['covered'] );
		$this->assertFalse( $r['breakdown'][1]['covered'] );
	}

	public function test_category_scoped_coupon_matches_ancestors() {
		$items = array(
			array( 'product_id' => 7, 'category_ids' => array( 3001, 992 ), 'paid_total' => 1000.0 ), // 992 kapsamda, pay 13 − 3 = 10 → 100
			array( 'product_id' => 8, 'category_ids' => array( 114 ), 'paid_total' => 1000.0 ),       // link 10 → 100
		);
		$r = SSA_Calculator::calculate( $items, $this->ctx( array( 'coupon' => $this->coupon( 3.0, 'categories', array( 992 ) ), 'order_base_total' => 2000.0 ) ) );
		$this->assertSame( 200.0, $r['amount'] );
	}

	public function test_excluded_category_and_default_share_mix() {
		$items = array(
			array( 'product_id' => 1, 'category_ids' => array( 900 ), 'paid_total' => 500.0 ),
			array( 'product_id' => 2, 'category_ids' => array( 114 ), 'paid_total' => 500.0 ),
		);
		$r = SSA_Calculator::calculate( $items, $this->ctx( array( 'order_base_total' => 1000.0 ) ) );
		$this->assertSame( 50.0, $r['amount'] );
	}

	public function test_returning_customer_half_and_cap() {
		$items = array( array( 'product_id' => 1, 'category_ids' => array( 114 ), 'paid_total' => 300000.0 ) );
		$r     = SSA_Calculator::calculate( $items, $this->ctx( array( 'order_base_total' => 300000.0, 'is_new_customer' => false ) ) );
		$this->assertSame( 10000.0, $r['amount'] );
		$this->assertTrue( $r['factors']['capped'] );
		$this->assertSame( 0.5, $r['factors']['returning'] );
	}

	public function test_campaign_share_and_booster() {
		$items = array( array( 'product_id' => 7, 'category_ids' => array( 114 ), 'paid_total' => 1000.0 ) );
		$rules = $this->rules( array( 'campaign_share' => 18.0, 'boosters' => array( array( 'product_ids' => array( 7 ), 'pct' => 2.0 ) ) ) );
		$r     = SSA_Calculator::calculate( $items, $this->ctx( array( 'order_base_total' => 1000.0, 'coupon' => $this->coupon( 0.0 ), 'rules' => $rules ) ) );
		$this->assertSame( 200.0, $r['amount'] ); // pay 18 + 2 = 20
	}

	public function test_share_for_picks_lowest_group_and_excluded() {
		$this->assertSame( 8.0, SSA_Calculator::share_for( array( 522, 114 ), $this->rules() ) );
		$this->assertSame( 0.0, SSA_Calculator::share_for( array( 900, 114 ), $this->rules() ) );
		$this->assertSame( 15.0, SSA_Calculator::share_for( array( 114 ), $this->rules() ) );
	}

	public function test_discount_above_group_share_gives_zero() {
		// grup payı %8, kupon %10 → efektif 0
		$items = array( array( 'product_id' => 1, 'category_ids' => array( 522 ), 'paid_total' => 1000.0 ) );
		$r     = SSA_Calculator::calculate( $items, $this->ctx( array( 'coupon' => $this->coupon( 10.0 ), 'order_base_total' => 1000.0 ) ) );
		$this->assertSame( 0.0, $r['amount'] );
		$this->assertSame( 'zero', $r['reason'] );
	}

	public function test_covers_helper() {
		$this->assertFalse( SSA_Calculator::covers( null, 1, array( 1 ) ) );
		$this->assertTrue( SSA_Calculator::covers( $this->coupon( 5, 'all' ), 1, array() ) );
		$this->assertTrue( SSA_Calculator::covers( $this->coupon( 5, 'products', array( '4', 9 ) ), 4, array() ) );
		$this->assertFalse( SSA_Calculator::covers( $this->coupon( 5, 'categories', array( 12 ) ), 4, array( 13 ) ) );
	}
}
