<?php
/**
 * E-postalar: tek generic WC_Email sınıfı, altı olay.
 * Şablon: templates/emails/notification.php (HTML) ve plain/notification.php; override
 * yolu yourtheme/woocommerce/splitshare-affiliates/emails/.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Emails {

	public static function init() {
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register' ) );
		// Tetikleyiciler (WC mailer yüklendikten sonra çalışsın diye closure ile).
		// Çeviri çağrısı yapmadan (init öncesi) sadece hook eşlemesini kullan.
		foreach ( self::hooks() as $id => $hook ) {
			add_action( $hook, function ( $arg = null, $extra = null ) use ( $id ) {
				$mailer = WC()->mailer();
				$emails = $mailer->get_emails();
				$key    = 'SSA_Email_' . $id;
				if ( isset( $emails[ $key ] ) ) {
					$emails[ $key ]->trigger( $arg, $extra );
				}
			}, 10, 2 );
		}
	}

	/** id => hook (çeviri içermez; plugins_loaded'da güvenle çağrılabilir). */
	public static function hooks() {
		return array(
			'application_received' => 'ssa_application_received',
			'application_admin'    => 'ssa_application_received',
			'partner_approved'     => 'ssa_partner_approved',
			'partner_rejected'     => 'ssa_partner_rejected',
			'code_rotated'         => 'ssa_code_rotated',
			'payout_paid'          => 'ssa_payout_paid',
		);
	}

	/** id => hook, recipient (partner|admin), title, subject, heading. init sonrasında çağrılmalı. */
	public static function definitions() {
		return array(
			'application_received' => array( 'hook' => 'ssa_application_received', 'to' => 'partner', 'title' => __( 'Affiliate: application received', 'splitshare-affiliates' ), 'subject' => __( 'We received your {program} application', 'splitshare-affiliates' ), 'heading' => __( 'Application received', 'splitshare-affiliates' ) ),
			'application_admin'    => array( 'hook' => 'ssa_application_received', 'to' => 'admin', 'title' => __( 'Affiliate: new application (admin)', 'splitshare-affiliates' ), 'subject' => __( '[{site_title}] New partner application: {partner_name}', 'splitshare-affiliates' ), 'heading' => __( 'New partner application', 'splitshare-affiliates' ) ),
			'partner_approved'     => array( 'hook' => 'ssa_partner_approved', 'to' => 'partner', 'title' => __( 'Affiliate: partner approved (welcome)', 'splitshare-affiliates' ), 'subject' => __( 'Welcome to {program}! Your code: {code}', 'splitshare-affiliates' ), 'heading' => __( 'Welcome aboard', 'splitshare-affiliates' ) ),
			'partner_rejected'     => array( 'hook' => 'ssa_partner_rejected', 'to' => 'partner', 'title' => __( 'Affiliate: application declined', 'splitshare-affiliates' ), 'subject' => __( 'About your {program} application', 'splitshare-affiliates' ), 'heading' => __( 'Your application', 'splitshare-affiliates' ) ),
			'code_rotated'         => array( 'hook' => 'ssa_code_rotated', 'to' => 'partner', 'title' => __( 'Affiliate: new partner code', 'splitshare-affiliates' ), 'subject' => __( 'Your new {program} code: {code}', 'splitshare-affiliates' ), 'heading' => __( 'Your code has been renewed', 'splitshare-affiliates' ) ),
			'payout_paid'          => array( 'hook' => 'ssa_payout_paid', 'to' => 'partner', 'title' => __( 'Affiliate: payout sent', 'splitshare-affiliates' ), 'subject' => __( 'Your {program} payout has been sent', 'splitshare-affiliates' ), 'heading' => __( 'Payout sent', 'splitshare-affiliates' ) ),
		);
	}

	public static function register( $emails ) {
		require_once SSA_PATH . 'includes/emails/class-ssa-email.php';
		foreach ( self::definitions() as $id => $def ) {
			$emails[ 'SSA_Email_' . $id ] = new SSA_Email( $id, $def );
		}
		return $emails;
	}

	/** E-posta gövde metni (HTML'e çevrilmeden önce paragraflar). */
	public static function body_paragraphs( $id, SSA_Partner $partner, $extra = null ) {
		$program = SSA_Settings::get( 'program_name' );
		$panel   = wc_get_account_endpoint_url( SSA_Settings::endpoint( 'dashboard' ) );
		$name    = $partner->display_name();
		$p       = array();
		switch ( $id ) {
			case 'application_received':
				/* translators: %s: name */
				$p[] = sprintf( __( 'Hello %s,', 'splitshare-affiliates' ), $name );
				/* translators: %s: program name */
				$p[] = sprintf( __( 'Thank you for applying to %s. We review every application personally and will get back to you within a few days.', 'splitshare-affiliates' ), $program );
				break;
			case 'application_admin':
				$p[] = sprintf( __( 'A new partner application was received from %1$s (%2$s).', 'splitshare-affiliates' ), $name, $partner->email() );
				foreach ( $partner->application as $k => $v ) {
					if ( '' !== (string) $v && ! in_array( $k, array( 'name', 'email' ), true ) ) {
						$p[] = ucfirst( str_replace( '_', ' ', $k ) ) . ': ' . $v;
					}
				}
				$p[] = admin_url( 'admin.php?page=ssa-affiliates&tab=applications' );
				break;
			case 'partner_approved':
				$p[] = sprintf( __( 'Welcome to the team, %s!', 'splitshare-affiliates' ), $name );
				/* translators: 1: code, 2: commission pct, 3: discount pct */
				$p[] = sprintf( __( 'Your partner code is %1$s. Your current split is %2$s%% commission + %3$s%% follower discount — you can change it in your panel.', 'splitshare-affiliates' ), '<strong>' . $partner->code . '</strong>', wc_format_decimal( $partner->commission_pct, 1 ), wc_format_decimal( $partner->discount_pct, 1 ) );
				$p[] = sprintf( __( 'Your partner panel: %s', 'splitshare-affiliates' ), '<a href="' . esc_url( $panel ) . '">' . esc_html( $panel ) . '</a>' );
				$p[] = __( 'First step: open "Split" in your panel and decide how much of your share goes to you and how much to your followers.', 'splitshare-affiliates' );
				/* translators: %s: amount */
				$p[] = sprintf( __( 'The code and commission apply to orders of %s and above. Payouts are sent monthly.', 'splitshare-affiliates' ), wp_strip_all_tags( wc_price( SSA_Settings::get( 'min_order' ) ) ) );
				if ( SSA_Settings::get( 'legal_notice' ) ) {
					$p[] = SSA_Settings::get( 'legal_notice' );
				}
				break;
			case 'partner_rejected':
				$p[] = sprintf( __( 'Hello %s,', 'splitshare-affiliates' ), $name );
				$p[] = sprintf( __( 'Thank you for your interest in %s. Unfortunately we cannot accept your application at this time.', 'splitshare-affiliates' ), $program );
				if ( '' !== (string) $partner->admin_note ) {
					$p[] = $partner->admin_note;
				}
				break;
			case 'code_rotated':
				$p[] = sprintf( __( 'Hello %s,', 'splitshare-affiliates' ), $name );
				$p[] = sprintf( __( 'For your protection we renew partner codes periodically. Your new code is %s.', 'splitshare-affiliates' ), '<strong>' . $partner->code . '</strong>' );
				if ( $partner->previous_code_valid() ) {
					$p[] = sprintf( __( 'Your previous code %1$s keeps working until %2$s.', 'splitshare-affiliates' ), $partner->previous_code, date_i18n( get_option( 'date_format' ), strtotime( $partner->previous_code_expires ) ) );
				}
				break;
			case 'payout_paid':
				$p[] = sprintf( __( 'Hello %s,', 'splitshare-affiliates' ), $name );
				if ( is_object( $extra ) ) {
					$p[] = sprintf( __( 'Your payout for %1$s (%2$s) has been sent to your bank account. Reference: %3$s', 'splitshare-affiliates' ), $extra->period, wp_strip_all_tags( wc_price( $extra->amount ) ), $extra->reference ? $extra->reference : '-' );
				}
				$p[] = sprintf( __( 'Details: %s', 'splitshare-affiliates' ), '<a href="' . esc_url( wc_get_account_endpoint_url( SSA_Settings::endpoint( 'earnings' ) ) ) . '">' . esc_html__( 'Earnings', 'splitshare-affiliates' ) . '</a>' );
				break;
		}
		return apply_filters( 'ssa_email_paragraphs', $p, $id, $partner, $extra );
	}
}
