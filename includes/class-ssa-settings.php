<?php
/**
 * Ayarlar: tek `ssa_settings` seçeneği; WooCommerce → Settings → Affiliates sekmesi.
 * `rules()` hesaplayıcının beklediği kural dizisini bugünün tarihine göre üretir.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Settings {

	/** @var array|null */
	private static $cache = null;

	public static function init() {
		add_filter( 'woocommerce_get_settings_pages', function ( $pages ) {
			require_once SSA_PATH . 'admin/class-ssa-settings-page.php';
			$pages[] = new SSA_Settings_Page();
			return $pages;
		} );
		add_action( 'update_option_ssa_settings', function () {
			self::$cache = null;
			if ( class_exists( 'SSA_Account' ) ) {
				SSA_Account::add_endpoints();
				flush_rewrite_rules();
			}
		} );
	}

	public static function all() {
		if ( null === self::$cache ) {
			self::$cache = wp_parse_args( (array) get_option( 'ssa_settings', array() ), SSA_Install::default_options() );
		}
		return self::$cache;
	}

	public static function get( $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	public static function update( array $partial ) {
		$all = array_merge( self::all(), $partial );
		update_option( 'ssa_settings', $all );
		self::$cache = null;
	}

	/** Hesap kuralları (SSA_Calculator::calculate `rules`). */
	public static function rules( $timestamp = null ) {
		$timestamp = $timestamp ? (int) $timestamp : current_time( 'timestamp' );
		$s         = self::all();

		$group_shares = array();
		foreach ( (array) $s['group_shares'] as $row ) {
			if ( isset( $row['category'], $row['pct'] ) && (int) $row['category'] > 0 ) {
				$group_shares[ (int) $row['category'] ] = (float) $row['pct'];
			}
		}

		$campaign_share = null;
		foreach ( (array) $s['campaign_periods'] as $p ) {
			if ( self::in_period( $timestamp, $p ) && isset( $p['share'] ) ) {
				$campaign_share = max( (float) $campaign_share, (float) $p['share'] );
			}
		}

		$boosters = array();
		foreach ( (array) $s['boosters'] as $b ) {
			if ( self::in_period( $timestamp, $b ) && ! empty( $b['product_ids'] ) ) {
				$ids = is_array( $b['product_ids'] ) ? $b['product_ids'] : array_map( 'trim', explode( ',', (string) $b['product_ids'] ) );
				$boosters[] = array( 'product_ids' => array_map( 'intval', $ids ), 'pct' => (float) $b['pct'] );
			}
		}

		return array(
			'default_share'       => (float) $s['default_share'],
			'group_shares'        => $group_shares,
			'excluded_categories' => array_map( 'intval', (array) $s['excluded_categories'] ),
			'min_order'           => (float) $s['min_order'],
			'max_commission'      => (float) $s['max_commission_per_order'],
			'returning_factor'    => (float) $s['returning_customer_factor'],
			'campaign_share'      => $campaign_share,
			'boosters'            => $boosters,
			'rounding'            => ( 'kurus' === $s['rounding'] ) ? 'kurus' : 'lira',
			'link_commission_pct' => (float) $s['link_commission_pct'],
		);
	}

	/** Bugün aktif hızlandırıcı ürünler (panel listesi için). */
	public static function active_booster_products() {
		$ids = array();
		foreach ( self::rules()['boosters'] as $b ) {
			$ids = array_merge( $ids, $b['product_ids'] );
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/** Statü adı: onaylı satış sayısına göre. */
	public static function tier_for_sales( $count ) {
		$tier = '';
		$tiers = (array) self::get( 'tiers', array() );
		usort( $tiers, function ( $a, $b ) { return (int) $a['min_sales'] - (int) $b['min_sales']; } );
		foreach ( $tiers as $t ) {
			if ( (int) $count >= (int) $t['min_sales'] ) {
				$tier = (string) $t['name'];
			}
		}
		return $tier;
	}

	public static function endpoint( $key ) {
		$eps = (array) self::get( 'endpoints', array() );
		$def = SSA_Install::default_options()['endpoints'];
		return isset( $eps[ $key ] ) && '' !== trim( (string) $eps[ $key ] ) ? sanitize_title( $eps[ $key ] ) : $def[ $key ];
	}

	private static function in_period( $timestamp, array $p ) {
		$from = ! empty( $p['from'] ) ? strtotime( $p['from'] . ' 00:00:00' ) : null;
		$to   = ! empty( $p['to'] ) ? strtotime( $p['to'] . ' 23:59:59' ) : null;
		return ( null === $from || $timestamp >= $from ) && ( null === $to || $timestamp <= $to );
	}
}
