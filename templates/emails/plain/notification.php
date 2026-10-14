<?php
/**
 * Ortaklık e-postası (düz metin).
 *
 * @var SSA_Email $email; string $email_heading; string[] $paragraphs
 */

defined( 'ABSPATH' ) || exit;

echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n\n";
foreach ( $paragraphs as $paragraph ) {
	echo esc_html( wp_strip_all_tags( $paragraph ) ) . "\n\n";
}
echo esc_html( wp_strip_all_tags( wptexturize( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) ) );
