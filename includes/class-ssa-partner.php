<?php
/**
 * Ortak modeli — ssa_partners satırının nesne hâli.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Partner {

	public $id = 0;
	public $user_id = 0;
	public $code = '';
	public $previous_code = '';
	public $previous_code_expires = null;
	public $status = 'pending';
	public $commission_pct = 0.0;
	public $discount_pct = 0.0;
	public $split_changed_at = null;
	public $tier = '';
	public $payout_details = array();
	public $application = array();
	public $applied_at = null;
	public $approved_at = null;
	public $code_rotated_at = null;
	public $admin_note = '';
	public $created_at = null;
	public $updated_at = null;

	public function __construct( $row = null ) {
		if ( $row ) {
			foreach ( (array) $row as $k => $v ) {
				if ( property_exists( $this, $k ) ) {
					$this->$k = $v;
				}
			}
			$this->id             = (int) $this->id;
			$this->user_id        = (int) $this->user_id;
			$this->commission_pct = (float) $this->commission_pct;
			$this->discount_pct   = (float) $this->discount_pct;
			$this->payout_details = is_string( $this->payout_details ) ? (array) json_decode( $this->payout_details, true ) : (array) $this->payout_details;
			$this->application    = is_string( $this->application ) ? (array) json_decode( $this->application, true ) : (array) $this->application;
		}
	}

	public function user() {
		return $this->user_id ? get_user_by( 'id', $this->user_id ) : null;
	}

	public function display_name() {
		$u = $this->user();
		if ( $u ) {
			$name = trim( $u->first_name . ' ' . $u->last_name );
			return '' !== $name ? $name : $u->display_name;
		}
		return isset( $this->application['name'] ) ? (string) $this->application['name'] : '';
	}

	public function email() {
		$u = $this->user();
		return $u ? $u->user_email : ( isset( $this->application['email'] ) ? (string) $this->application['email'] : '' );
	}

	public function is_active() {
		return 'active' === $this->status;
	}

	/** Panel erişimi (aktif veya duraklatılmış — pasif ortak geçmişini görebilir). */
	public function can_access_panel() {
		return in_array( $this->status, array( 'active', 'paused' ), true );
	}

	public function can_change_split( $interval_days, $now = null ) {
		$interval_days = (int) $interval_days;
		if ( $interval_days <= 0 || empty( $this->split_changed_at ) ) {
			return true;
		}
		$now = $now ? (int) $now : time();
		return ( strtotime( $this->split_changed_at ) + $interval_days * DAY_IN_SECONDS ) <= $now;
	}

	public function next_split_change_at( $interval_days ) {
		if ( empty( $this->split_changed_at ) ) {
			return 0;
		}
		return strtotime( $this->split_changed_at ) + (int) $interval_days * DAY_IN_SECONDS;
	}

	/** Eski kod hâlâ geçerli mi? */
	public function previous_code_valid( $now = null ) {
		if ( '' === $this->previous_code || empty( $this->previous_code_expires ) ) {
			return false;
		}
		return strtotime( $this->previous_code_expires ) > ( $now ? (int) $now : time() );
	}
}
