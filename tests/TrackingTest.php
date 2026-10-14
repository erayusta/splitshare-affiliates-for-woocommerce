<?php
use PHPUnit\Framework\TestCase;

/**
 * Atıf çerezinin host kapsamı.
 *
 * Ölçülmüş arıza (2026-10-06): `www.example.com/?ref=KOD` isteğinde
 * eklenti çerezi WWW host'una kuruyor, hemen ardından WordPress kanonik
 * yönlendirmesi ziyaretçiyi `example.com`'a taşıyor ve host'a bağlı
 * çerez ORAYA GÖNDERİLMİYOR. Tıklama kaydı düşüyor, çerez bir sonraki
 * istekte yok oluyor, sipariş kimseye atfedilmiyor.
 *
 * Çözüm: ref'i tüketmeden önce kanonik host'a taşı; çerez yalnızca kanonik
 * host'ta kurulsun.
 */
final class TrackingTest extends TestCase {

	public function test_canonical_host_needs_no_redirect() {
		$this->assertNull( SSA_Tracking::canonical_target( 'https://example.com', 'example.com', '/?ref=ABCD' ) );
	}

	public function test_www_request_is_moved_to_canonical_host_keeping_ref() {
		$this->assertSame(
			'https://example.com/?ref=ABCD',
			SSA_Tracking::canonical_target( 'https://example.com', 'www.example.com', '/?ref=ABCD' )
		);
	}

	public function test_full_path_and_all_query_args_are_preserved() {
		$this->assertSame(
			'https://example.com/kategori/?ref=ABCD&utm_source=instagram',
			SSA_Tracking::canonical_target( 'https://example.com', 'www.example.com', '/kategori/?ref=ABCD&utm_source=instagram' )
		);
	}

	public function test_canonical_host_may_itself_be_www() {
		// Mağazanın kanonik adresi www ise, çıplak host'tan gelen taşınır.
		$this->assertSame(
			'https://www.example.com/?ref=ABCD',
			SSA_Tracking::canonical_target( 'https://www.example.com', 'example.com', '/?ref=ABCD' )
		);
		$this->assertNull( SSA_Tracking::canonical_target( 'https://www.example.com', 'www.example.com', '/?ref=ABCD' ) );
	}

	public function test_host_comparison_ignores_case_and_port() {
		$this->assertNull( SSA_Tracking::canonical_target( 'https://example.com', 'Example.COM', '/?ref=ABCD' ) );
		// Port farkı host farkı DEĞİLDİR: yerel kurulumları sonsuz döngüye sokmaz.
		$this->assertNull( SSA_Tracking::canonical_target( 'http://localhost:8080', 'localhost:8080', '/?ref=ABCD' ) );
		$this->assertNull( SSA_Tracking::canonical_target( 'http://localhost', 'localhost:8080', '/?ref=ABCD' ) );
	}

	public function test_missing_or_unparseable_host_is_left_alone() {
		$this->assertNull( SSA_Tracking::canonical_target( 'https://example.com', '', '/?ref=ABCD' ) );
		$this->assertNull( SSA_Tracking::canonical_target( '', 'www.example.com', '/?ref=ABCD' ) );
	}

	public function test_scheme_follows_the_canonical_home_url() {
		// Ev adresi http ise hedef de http olur; şemayı uydurmayız.
		$this->assertSame(
			'http://example.com/?ref=ABCD',
			SSA_Tracking::canonical_target( 'http://example.com', 'www.example.com', '/?ref=ABCD' )
		);
	}

	public function test_request_uri_without_leading_slash_is_normalised() {
		$this->assertSame(
			'https://example.com/?ref=ABCD',
			SSA_Tracking::canonical_target( 'https://example.com', 'www.example.com', '?ref=ABCD' )
		);
	}
}
