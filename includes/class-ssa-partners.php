<?php
/**
 * Ortak repository: sorgu, başvuru, onay/red, link kodu üretimi, statü.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Partners {

	private static function table() {
		return SSA_Install::tables()['partners'];
	}

	/** @return SSA_Partner|null */
	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB
		return $row ? new SSA_Partner( $row ) : null;
	}

	/** @return SSA_Partner|null */
	public static function get_by_user( $user_id ) {
		global $wpdb;
		if ( ! $user_id ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE user_id = %d', $user_id ) ); // phpcs:ignore WordPress.DB
		return $row ? new SSA_Partner( $row ) : null;
	}

	/** Link koduyla ortak (tam eşleşme). @return SSA_Partner|null */
	public static function get_by_link_code( $code ) {
		global $wpdb;
		$code = strtoupper( sanitize_text_field( (string) $code ) );
		if ( '' === $code ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE code = %s LIMIT 1', $code ) ); // phpcs:ignore WordPress.DB
		return $row ? new SSA_Partner( $row ) : null;
	}

	/** ?ref= kodu: link kodu, yoksa ortağın bir kupon kodu (eski ana kodlar). @return SSA_Partner|null */
	public static function get_by_code( $code ) {
		$p = self::get_by_link_code( $code );
		if ( $p ) {
			return $p;
		}
		if ( class_exists( 'SSA_Partner_Coupons' ) ) {
			$c = SSA_Partner_Coupons::get_by_code( $code );
			if ( $c ) {
				return self::get( $c->partner_id );
			}
		}
		return null;
	}

	/** Liste sorgusu. $args: status, search, orderby, order, limit, offset. @return SSA_Partner[] */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args( $args, array( 'status' => '', 'search' => '', 'orderby' => 'created_at', 'order' => 'DESC', 'limit' => 20, 'offset' => 0 ) );
		$where = self::where( $args );
		$orderby = in_array( $args['orderby'], array( 'created_at', 'approved_at', 'code', 'status', 'commission_pct' ), true ) ? $args['orderby'] : 'created_at';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$sql     = 'SELECT p.* FROM ' . self::table() . " p {$where} ORDER BY p.{$orderby} {$order}";
		if ( (int) $args['limit'] > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $args['limit'], (int) $args['offset'] );
		}
		return array_map( function ( $r ) { return new SSA_Partner( $r ); }, (array) $wpdb->get_results( $sql ) ); // phpcs:ignore WordPress.DB
	}

	public static function count( array $args = array() ) {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() . ' p ' . self::where( $args ) ); // phpcs:ignore WordPress.DB
	}

	private static function where( array $args ) {
		global $wpdb;
		$w = array( '1=1' );
		if ( ! empty( $args['status'] ) ) {
			$w[] = $wpdb->prepare( 'p.status = %s', $args['status'] );
		} elseif ( ! empty( $args['exclude_pending'] ) ) {
			$w[] = "p.status <> 'pending'";
		}
		if ( ! empty( $args['search'] ) ) {
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$w[]  = $wpdb->prepare( '(p.code LIKE %s OR p.application LIKE %s OR p.user_id IN (SELECT ID FROM ' . $wpdb->users . ' WHERE user_email LIKE %s OR display_name LIKE %s))', $like, $like, $like, $like );
		}
		return 'WHERE ' . implode( ' AND ', $w );
	}

	public static function counts_by_status() {
		global $wpdb;
		$out = array( 'pending' => 0, 'active' => 0, 'paused' => 0, 'rejected' => 0 );
		foreach ( (array) $wpdb->get_results( 'SELECT status, COUNT(*) c FROM ' . self::table() . ' GROUP BY status' ) as $r ) { // phpcs:ignore WordPress.DB
			$out[ $r->status ] = (int) $r->c;
		}
		return $out;
	}

	/**
	 * Başvuru kaydı. $data: user_id, application (array).
	 * @return int|WP_Error
	 */
	public static function create_application( array $data ) {
		global $wpdb;
		$user_id = (int) $data['user_id'];
		if ( self::get_by_user( $user_id ) ) {
			return new WP_Error( 'ssa_exists', __( 'There is already an application or partner account for this user.', 'splitshare-affiliates' ) );
		}
		$now = current_time( 'mysql' );
		$ok  = $wpdb->insert( self::table(), array( // phpcs:ignore WordPress.DB
			'user_id'        => $user_id,
			'status'         => 'pending',
			'application'    => wp_json_encode( (array) $data['application'], JSON_UNESCAPED_UNICODE ),
			'payout_details' => wp_json_encode( isset( $data['payout_details'] ) ? (array) $data['payout_details'] : array() ),
			'applied_at'     => $now,
			'created_at'     => $now,
			'updated_at'     => $now,
		) );
		if ( ! $ok ) {
			return new WP_Error( 'ssa_db', __( 'Could not save the application.', 'splitshare-affiliates' ) );
		}
		$partner = self::get( (int) $wpdb->insert_id );
		do_action( 'ssa_application_received', $partner );
		return $partner->id;
	}

	public static function update( $id, array $fields ) {
		global $wpdb;
		foreach ( array( 'payout_details', 'application' ) as $json ) {
			if ( isset( $fields[ $json ] ) && is_array( $fields[ $json ] ) ) {
				$fields[ $json ] = wp_json_encode( $fields[ $json ], JSON_UNESCAPED_UNICODE );
			}
		}
		$fields['updated_at'] = current_time( 'mysql' );
		return false !== $wpdb->update( self::table(), $fields, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Onay: rol + link kodu. Kuponları ortak panelden kendisi oluşturur.
	 * @return true|WP_Error
	 */
	public static function approve( $id, array $opts = array() ) {
		$p = self::get( $id );
		if ( ! $p ) {
			return new WP_Error( 'ssa_missing', __( 'Partner not found.', 'splitshare-affiliates' ) );
		}
		$user = $p->user();
		if ( ! $user ) {
			return new WP_Error( 'ssa_user', __( 'Partner user account not found.', 'splitshare-affiliates' ) );
		}
		$code = '' !== $p->code ? $p->code : self::generate_code( $p->display_name() );
		$user->add_role( SSA_Install::ROLE );
		self::update( $p->id, array(
			'status'      => 'active',
			'code'        => $code,
			'approved_at' => $p->approved_at ? $p->approved_at : current_time( 'mysql' ),
			'admin_note'  => isset( $opts['note'] ) ? sanitize_textarea_field( $opts['note'] ) : $p->admin_note,
		) );
		$p = self::get( $p->id );
		SSA_Partner_Coupons::resync_partner( $p->id );
		do_action( 'ssa_partner_approved', $p );
		return true;
	}

	public static function reject( $id, $note = '' ) {
		$p = self::get( $id );
		if ( ! $p ) {
			return false;
		}
		self::update( $id, array( 'status' => 'rejected', 'admin_note' => sanitize_textarea_field( $note ) ) );
		do_action( 'ssa_partner_rejected', self::get( $id ) );
		return true;
	}

	public static function set_status( $id, $status ) {
		if ( ! in_array( $status, array( 'active', 'paused', 'rejected', 'pending' ), true ) ) {
			return false;
		}
		self::update( $id, array( 'status' => $status ) );
		SSA_Partner_Coupons::resync_partner( (int) $id );
		return true;
	}

	public static function update_payout_details( $id, array $details ) {
		$clean = array();
		foreach ( array( 'holder', 'iban', 'company', 'tax_no', 'invoices' ) as $k ) {
			$clean[ $k ] = isset( $details[ $k ] ) ? sanitize_text_field( $details[ $k ] ) : '';
		}
		return self::update( $id, array( 'payout_details' => $clean ) );
	}

	/** Kod: ad baş harfleri (2-3) + 4 rastgele. */
	public static function generate_code( $name = '' ) {
		$initials = '';
		foreach ( preg_split( '/\s+/', trim( (string) $name ) ) as $part ) {
			$part = preg_replace( '/[^A-Za-z]/', '', remove_accents( $part ) );
			if ( '' !== $part ) {
				$initials .= strtoupper( $part[0] );
			}
			if ( strlen( $initials ) >= 3 ) {
				break;
			}
		}
		if ( strlen( $initials ) < 2 ) {
			$initials = 'SP';
		}
		$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		do {
			$rand = '';
			for ( $i = 0; $i < 4; $i++ ) {
				$rand .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
			}
			$code = $initials . $rand;
		} while ( ! self::code_available( $code ) );
		return $code;
	}

	/** Link kodu uygun mu? (ortak link kodları, ortak kuponları, WC kuponları) */
	public static function code_available( $code, $except_id = 0 ) {
		global $wpdb;
		$code = strtoupper( (string) $code );
		if ( ! preg_match( '/^[A-Z0-9]{4,20}$/', $code ) ) {
			return false;
		}
		$taken = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE code = %s AND id <> %d', $code, (int) $except_id ) ); // phpcs:ignore WordPress.DB
		if ( $taken ) {
			return false;
		}
		if ( class_exists( 'SSA_Partner_Coupons' ) && SSA_Partner_Coupons::get_by_code( $code ) ) {
			return false;
		}
		return ! SSA_Coupon::coupon_id( $code );
	}

	/** Statüleri onaylı/ödenmiş satış sayısına göre güncelle (cron). */
	public static function update_tiers() {
		global $wpdb;
		$t = SSA_Install::tables();
		$rows = $wpdb->get_results( "SELECT p.id, p.tier, (SELECT COUNT(*) FROM {$t['commissions']} c WHERE c.partner_id = p.id AND c.status IN ('approved','paid') AND c.amount > 0) AS sales FROM {$t['partners']} p WHERE p.status IN ('active','paused')" ); // phpcs:ignore WordPress.DB
		$n = 0;
		foreach ( (array) $rows as $r ) {
			$tier = SSA_Settings::tier_for_sales( (int) $r->sales );
			if ( $tier !== $r->tier ) {
				self::update( (int) $r->id, array( 'tier' => $tier ) );
				$n++;
			}
		}
		return $n;
	}

	/** Giriş yapmış kullanıcı ortak mı? */
	public static function current() {
		return self::get_by_user( get_current_user_id() );
	}
}
