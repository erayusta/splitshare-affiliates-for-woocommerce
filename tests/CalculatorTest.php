<?php
use PHPUnit\Framework\TestCase;

/**
 * Sepet örnekleri "Lezzet Ortakları — Satış Ortaklığı Programı Önerisi" dokümanından.
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

	private function ctx( array $over = array() ) {
		return array_merge( array(
			'commission_pct'   => 10.0,
			'discount_pct'     => 5.0,
			'discount_applied' => true,
			'order_base_total' => 0.0,
			'is_new_customer'  => true,
			'rules'            => $this->rules(),
		), $over );
	}

	public function test_below_min_order_gives_zero() {
		$r = SSA_Calculator::calculate( array( array( 'product_id' => 1, 'category_ids' => array( 114 ), 'paid_total' => 429.60 ) ), $this->ctx( array( 'order_base_total' => 429.60 ) ) );
		$this->assertSame( 0.0, $r['amount'] );
		$this->assertSame( 'below_min', $r['reason'] );
	}

	public function test_document_cold_coffee_basket_109() {
		// 1.144,30 sepet, %5 indirim → 1.087,08 ödendi; %10 komisyon → 108,71 → lira yuvarlama 109
		$items = array( array( 'product_id' => 1, 'category_ids' => array( 113 ), 'paid_total' => 1087.085 ) );
		$r     = SSA_Calculator::calculate( $items, $this->ctx( array( 'order_base_total' => 1087.085 ) ) );
		$this->assertSame( 109.0, $r['amount'] );
	}

	public function test_kurus_rounding() {
		$items = array( array( 'product_id' => 1, 'category_ids' => array( 113 ), 'paid_total' => 1087.085 ) );
		$r     = SSA_Calculator::calculate( $items, $this->ctx( array( 'order_base_total' => 1087.085, 'rules' => $this->rules( array( 'rounding' => 'kurus' ) ) ) ) );
		$this->assertSame( 108.71, $r['amount'] );
	}

	public function test_document_wholesale_1140() {
		// toptan payı %8, indirim %5, komisyon %10 → efektif %3; 38.000 → 1.140
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

	public function test_link_only_no_discount_deduction_but_capped_by_share() {
		$items = array( array( 'product_id' => 1, 'category_ids' => array( 522 ), 'paid_total' => 1000.0 ) );
		$r     = SSA_Calculator::calculate( $items, $this->ctx( array( 'discount_applied' => false, 'order_base_total' => 1000.0 ) ) );
		$this->assertSame( 80.0, $r['amount'] ); // min(10, 8) = 8
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
		$r     = SSA_Calculator::calculate( $items, $this->ctx( array( 'order_base_total' => 1000.0, 'commission_pct' => 20.0, 'discount_pct' => 0.0, 'rules' => $rules ) ) );
		$this->assertSame( 200.0, $r['amount'] ); // share 18 + 2 = 20
	}

	public function test_share_for_picks_lowest_group_and_excluded() {
		$this->assertSame( 8.0, SSA_Calculator::share_for( array( 522, 114 ), $this->rules() ) );
		$this->assertSame( 0.0, SSA_Calculator::share_for( array( 900, 114 ), $this->rules() ) );
		$this->assertSame( 15.0, SSA_Calculator::share_for( array( 114 ), $this->rules() ) );
	}

	public function test_negative_effective_pct_is_zero() {
		// grup payı %8, indirim %10 → efektif 0
		$items = array( array( 'product_id' => 1, 'category_ids' => array( 522 ), 'paid_total' => 1000.0 ) );
		$r     = SSA_Calculator::calculate( $items, $this->ctx( array( 'discount_pct' => 10.0, 'order_base_total' => 1000.0 ) ) );
		$this->assertSame( 0.0, $r['amount'] );
	}
}
