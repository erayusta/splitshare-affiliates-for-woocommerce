<?php
/**
 * Başvuru formu: [ssa_apply] shortcode + POST işleme.
 * Giriş yapmamış başvuran için WooCommerce müşteri hesabı oluşturulur.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Application {

	const NONCE = 'ssa_apply';

	/** @var array */
	private static $errors = array();

	/** @var bool */
	private static $done = false;

	public static function init() {
		add_shortcode( 'ssa_apply', array( __CLASS__, 'render' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_handle' ) );
	}

	public static function fields() {
		return array(
			'name'         => array( 'label' => __( 'Full name', 'splitshare-affiliates' ), 'type' => 'text', 'required' => true ),
			'email'        => array( 'label' => __( 'E-mail', 'splitshare-affiliates' ), 'type' => 'email', 'required' => true ),
			'phone'        => array( 'label' => __( 'Phone', 'splitshare-affiliates' ), 'type' => 'tel', 'required' => true ),
			'city'         => array( 'label' => __( 'City', 'splitshare-affiliates' ), 'type' => 'text', 'required' => false ),
			'instagram'    => array( 'label' => __( 'Instagram profile', 'splitshare-affiliates' ), 'type' => 'url', 'required' => false ),
			'tiktok'       => array( 'label' => __( 'TikTok profile', 'splitshare-affiliates' ), 'type' => 'url', 'required' => false ),
			'youtube'      => array( 'label' => __( 'YouTube channel', 'splitshare-affiliates' ), 'type' => 'url', 'required' => false ),
			'followers'    => array( 'label' => __( 'Total followers', 'splitshare-affiliates' ), 'type' => 'number', 'required' => false ),
			'content_type' => array( 'label' => __( 'What do you create?', 'splitshare-affiliates' ), 'type' => 'text', 'required' => false, 'placeholder' => __( 'e.g. recipes, coffee, lifestyle', 'splitshare-affiliates' ) ),
			'note'         => array( 'label' => __( 'Anything you want to add', 'splitshare-affiliates' ), 'type' => 'textarea', 'required' => false ),
		);
	}

	public static function render() {
		wp_enqueue_style( 'ssa-account', SSA_URL . 'assets/css/account.css', array(), SSA_VERSION );
		$partner = is_user_logged_in() ? SSA_Partners::get_by_user( get_current_user_id() ) : null;
		ob_start();
		$file = self::locate_template( 'apply-form.php' );
		$args = array(
			'fields'  => self::fields(),
			'errors'  => self::$errors,
			'done'    => self::$done,
			'partner' => $partner,
			'values'  => self::posted_values(),
			'legal'   => SSA_Settings::get( 'legal_notice' ),
			'program' => SSA_Settings::get( 'program_name' ),
		);
		extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- şablon değişkenleri
		include $file;
		return ob_get_clean();
	}

	private static function posted_values() {
		$out = array();
		foreach ( self::fields() as $key => $f ) {
			$out[ $key ] = isset( $_POST[ 'ssa_' . $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'ssa_' . $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( is_user_logged_in() ) {
			$u = wp_get_current_user();
			$out['name']  = '' !== $out['name'] ? $out['name'] : trim( $u->first_name . ' ' . $u->last_name );
			$out['email'] = '' !== $out['email'] ? $out['email'] : $u->user_email;
		}
		return $out;
	}

	public static function maybe_handle() {
		if ( empty( $_POST['ssa_apply_submit'] ) ) {
			return;
		}
		if ( ! isset( $_POST['_ssa_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_ssa_nonce'] ), self::NONCE ) ) {
			self::$errors[] = __( 'Security check failed, please try again.', 'splitshare-affiliates' );
			return;
		}
		if ( ! empty( $_POST['ssa_website'] ) ) { // honeypot
			self::$done = true;
			return;
		}
		$values = self::posted_values();
		foreach ( self::fields() as $key => $f ) {
			if ( $f['required'] && '' === $values[ $key ] ) {
				/* translators: %s: field label */
				self::$errors[] = sprintf( __( '%s is required.', 'splitshare-affiliates' ), $f['label'] );
			}
		}
		if ( ! is_email( $values['email'] ) ) {
			self::$errors[] = __( 'Please enter a valid e-mail address.', 'splitshare-affiliates' );
		}
		if ( empty( $_POST['ssa_legal_ok'] ) ) {
			self::$errors[] = __( 'Please accept the program terms.', 'splitshare-affiliates' );
		}
		if ( self::$errors ) {
			return;
		}

		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
		} else {
			$existing = get_user_by( 'email', $values['email'] );
			if ( $existing ) {
				$user_id = $existing->ID;
			} else {
				$parts   = explode( ' ', $values['name'], 2 );
				$user_id = wc_create_new_customer( $values['email'], '', '', array( 'first_name' => $parts[0], 'last_name' => isset( $parts[1] ) ? $parts[1] : '' ) );
				if ( is_wp_error( $user_id ) ) {
					self::$errors[] = $user_id->get_error_message();
					return;
				}
			}
		}

		$result = SSA_Partners::create_application( array( 'user_id' => $user_id, 'application' => $values ) );
		if ( is_wp_error( $result ) ) {
			self::$errors[] = $result->get_error_message();
			return;
		}
		self::$done = true;
	}

	public static function locate_template( $name ) {
		$theme = locate_template( array( 'woocommerce/splitshare-affiliates/' . $name, 'splitshare-affiliates/' . $name ) );
		return $theme ? $theme : SSA_PATH . 'templates/' . $name;
	}

	public static function apply_url() {
		$id = (int) SSA_Settings::get( 'apply_page_id' );
		return $id ? get_permalink( $id ) : '';
	}
}
