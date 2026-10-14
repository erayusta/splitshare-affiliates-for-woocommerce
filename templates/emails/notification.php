<?php
/**
 * Ortaklık e-postası (HTML). Override: yourtheme/woocommerce/splitshare-affiliates/emails/notification.php
 *
 * @var SSA_Email $email; string $email_heading; string[] $paragraphs
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );

foreach ( $paragraphs as $paragraph ) {
	echo '<p>' . wp_kses_post( $paragraph ) . '</p>';
}

do_action( 'woocommerce_email_footer', $email );
