<?php
/**
 * Kurulum: tablolar (dbDelta), ortak rolü, varsayılan ayarlar, cron, sürüm yükseltme.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Install {

	const DB_VERSION = '1.0.0';
	const ROLE       = 'ssa_partner';

	/** Tam tablo adları. */
	public static function tables() {
		global $wpdb;
		return array(
			'partners'    => $wpdb->prefix . 'ssa_partners',
			'commissions' => $wpdb->prefix . 'ssa_commissions',
			'payouts'     => $wpdb->prefix . 'ssa_payouts',
			'clicks'      => $wpdb->prefix . 'ssa_clicks',
		);
	}

	/** Varsayılan ayarlar (Lezzet Ortakları programı). */
	public static function default_options() {
		return array(
			'default_share'                  => 15,
			'group_shares'                   => array(),   // cat_id => pct
			'excluded_categories'            => array(),
			'min_order'                      => 750,
			'max_commission_per_order'       => 10000,
			'rounding'                       => 'lira',
			'default_commission_pct'         => 10,
			'default_discount_pct'           => 5,
			'split_change_interval_days'     => 30,
			'returning_customer_factor'      => 0.5,
			'cookie_days'                    => 15,
			'coupon_usage_limit_per_user'    => 1,
			'code_rotation_days'             => 90,
			'code_grace_days'                => 7,
			'hold_days'                      => 21,
			'payout_day'                     => 15,
			'min_payout'                     => 500,
			'deduct_refunds_from_next_payout' => 'yes',
			'campaign_periods'               => array(), // [ {from, to, share} ]
			'boosters'                       => array(), // [ {product_ids[], pct, from, to} ]
			'tiers'                          => array(
				array( 'name' => 'Partner', 'min_sales' => 1 ),
				array( 'name' => 'Active Partner', 'min_sales' => 26 ),
				array( 'name' => 'Ambassador', 'min_sales' => 51 ),
			),
			'tier_benefits'                  => '',
			'apply_page_id'                  => 0,
			'show_join_card'                 => 'yes',
			'legal_notice'                   => 'Posts must carry an "Ad" / "Sponsored" label and tag our account. Posts without the label do not earn commission.',
			'kit_attachments'                => array(),
			'kit_texts'                      => '',
			'endpoints'                      => array(
				'dashboard' => 'partner',
				'sales'     => 'partner-sales',
				'earnings'  => 'partner-earnings',
				'split'     => 'partner-split',
				'links'     => 'partner-links',
				'kit'       => 'partner-kit',
			),
			'program_name'                   => 'Partner Program',
		);
	}

	public static function activate() {
		self::create_tables();
		self::create_role();
		add_option( 'ssa_settings', self::default_options() );
		self::schedule_cron();
		update_option( 'ssa_db_version', self::DB_VERSION );
		if ( class_exists( 'SSA_Account' ) ) {
			SSA_Account::add_endpoints();
		}
		flush_rewrite_rules();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'ssa_daily' );
		wp_clear_scheduled_hook( 'ssa_monthly' );
		flush_rewrite_rules();
	}

	public static function maybe_upgrade() {
		if ( version_compare( (string) get_option( 'ssa_db_version', '0' ), self::DB_VERSION, '<' ) ) {
			self::activate();
		}
		if ( ! wp_next_scheduled( 'ssa_daily' ) ) {
			self::schedule_cron();
		}
	}

	public static function schedule_cron() {
		if ( ! wp_next_scheduled( 'ssa_daily' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 03:00' ), 'daily', 'ssa_daily' );
		}
		if ( ! wp_next_scheduled( 'ssa_monthly' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 03:30' ), 'daily', 'ssa_monthly' ); // gün kontrolü SSA_Cron'da
		}
	}

	public static function create_role() {
		$customer = get_role( 'customer' );
		$caps     = $customer ? $customer->capabilities : array( 'read' => true );
		$caps['ssa_partner'] = true;
		remove_role( self::ROLE );
		add_role( self::ROLE, __( 'Affiliate Partner', 'splitshare-affiliates' ), $caps );
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'ssa_manage' );
		}
		$manager = get_role( 'shop_manager' );
		if ( $manager ) {
			$manager->add_cap( 'ssa_manage' );
		}
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t       = self::tables();
		$collate = $wpdb->get_charset_collate();

		dbDelta( "CREATE TABLE {$t['partners']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			code varchar(20) NOT NULL DEFAULT '',
			previous_code varchar(20) NOT NULL DEFAULT '',
			previous_code_expires datetime NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			commission_pct decimal(5,2) NOT NULL DEFAULT 0,
			discount_pct decimal(5,2) NOT NULL DEFAULT 0,
			split_changed_at datetime NULL,
			tier varchar(60) NOT NULL DEFAULT '',
			payout_details longtext NULL,
			application longtext NULL,
			applied_at datetime NULL,
			approved_at datetime NULL,
			code_rotated_at datetime NULL,
			admin_note text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_id (user_id),
			KEY code (code),
			KEY status (status)
		) $collate;" );

		dbDelta( "CREATE TABLE {$t['commissions']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			partner_id bigint(20) unsigned NOT NULL,
			attribution varchar(20) NOT NULL DEFAULT 'coupon',
			code_used varchar(20) NOT NULL DEFAULT '',
			order_total_base decimal(12,2) NOT NULL DEFAULT 0,
			discount_pct decimal(5,2) NOT NULL DEFAULT 0,
			commission_pct decimal(5,2) NOT NULL DEFAULT 0,
			breakdown longtext NULL,
			amount decimal(12,2) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			reason varchar(40) NOT NULL DEFAULT '',
			available_at datetime NULL,
			payout_id bigint(20) unsigned NULL,
			is_new_customer tinyint(1) NOT NULL DEFAULT 1,
			adjustment_of bigint(20) unsigned NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY partner_status (partner_id,status),
			KEY available_at (available_at),
			KEY payout_id (payout_id)
		) $collate;" );

		dbDelta( "CREATE TABLE {$t['payouts']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			partner_id bigint(20) unsigned NOT NULL,
			period varchar(7) NOT NULL,
			amount decimal(12,2) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'open',
			paid_at datetime NULL,
			reference varchar(120) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY partner_period (partner_id,period),
			KEY status (status)
		) $collate;" );

		dbDelta( "CREATE TABLE {$t['clicks']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			partner_id bigint(20) unsigned NOT NULL,
			landing varchar(255) NOT NULL DEFAULT '',
			product_id bigint(20) unsigned NULL,
			visitor_hash char(32) NOT NULL DEFAULT '',
			order_id bigint(20) unsigned NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY partner_created (partner_id,created_at),
			KEY visitor (visitor_hash,partner_id)
		) $collate;" );
	}
}
