<?php
use PHPUnit\Framework\TestCase;

/** Saf kod doğrulama kuralları (WP'siz). */
final class PartnerCouponsTest extends TestCase {

	public function test_normalizes_to_uppercase_and_trims() {
		$this->assertSame( 'AYSE10', SSA_Partner_Coupons::normalize_code( '  ayse10 ' ) );
	}

	public function test_code_error_keys() {
		$this->assertSame( 'empty', SSA_Partner_Coupons::code_error( '', 4, 20 ) );
		$this->assertSame( 'length', SSA_Partner_Coupons::code_error( 'AB1', 4, 20 ) );
		$this->assertSame( 'length', SSA_Partner_Coupons::code_error( str_repeat( 'A', 21 ), 4, 20 ) );
		$this->assertSame( 'chars', SSA_Partner_Coupons::code_error( 'AYSE-10', 4, 20 ) );
		$this->assertSame( 'chars', SSA_Partner_Coupons::code_error( 'AYŞE10', 4, 20 ) );
		$this->assertSame( '', SSA_Partner_Coupons::code_error( 'AYSE10', 4, 20 ) );
	}

	public function test_discount_bounds() {
		$this->assertSame( 'min', SSA_Partner_Coupons::discount_error( 2.0, 5.0, 15.0 ) );
		$this->assertSame( 'max', SSA_Partner_Coupons::discount_error( 16.0, 5.0, 15.0 ) );
		$this->assertSame( '', SSA_Partner_Coupons::discount_error( 10.0, 5.0, 15.0 ) );
		$this->assertSame( '', SSA_Partner_Coupons::discount_error( 15.0, 5.0, 15.0 ) );
	}
}
