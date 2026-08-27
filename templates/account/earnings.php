<?php
/**
 * Panel — Hakedişler + ödeme bilgileri.
 *
 * @var SSA_Partner $partner; array $payouts, $totals, $settings, $notices; float $carry_over; int $next_payout
 */

defined( 'ABSPATH' ) || exit;
$details = $partner->payout_details;
?>
<?php foreach ( $notices as $n ) : ?>
	<p class="ssa-notice ssa-notice-<?php echo esc_attr( $n['type'] ); ?>"><?php echo esc_html( $n['message'] ); ?></p>
<?php endforeach; ?>

<div class="ssa-cards">
	<div class="ssa-card"><div class="ssa-card__label"><?php esc_html_e( 'Balance', 'splitshare-affiliates' ); ?></div><div class="ssa-card__value"><?php echo wp_kses_post( wc_price( $carry_over ) ); ?></div><div class="ssa-card__hint"><?php printf( esc_html__( 'paid on the %1$s if ≥ %2$s', 'splitshare-affiliates' ), esc_html( date_i18n( get_option( 'date_format' ), $next_payout ) ), wp_kses_post( wc_price( $settings['min_payout'] ) ) ); ?></div></div>
	<div class="ssa-card"><div class="ssa-card__label"><?php esc_html_e( 'Pending', 'splitshare-affiliates' ); ?></div><div class="ssa-card__value"><?php echo wp_kses_post( wc_price( $totals['pending'] ) ); ?></div></div>
	<div class="ssa-card"><div class="ssa-card__label"><?php esc_html_e( 'Paid so far', 'splitshare-affiliates' ); ?></div><div class="ssa-card__value"><?php echo wp_kses_post( wc_price( $totals['paid'] ) ); ?></div></div>
</div>

<h3><?php esc_html_e( 'Payouts', 'splitshare-affiliates' ); ?></h3>
<?php if ( $payouts ) : ?>
	<table class="ssa-table">
		<thead><tr><th><?php esc_html_e( 'Period', 'splitshare-affiliates' ); ?></th><th><?php esc_html_e( 'Amount', 'splitshare-affiliates' ); ?></th><th><?php esc_html_e( 'Status', 'splitshare-affiliates' ); ?></th><th><?php esc_html_e( 'Paid on', 'splitshare-affiliates' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $payouts as $p ) : ?>
			<tr><td><?php echo esc_html( $p->period ); ?></td><td><?php echo wp_kses_post( wc_price( $p->amount ) ); ?></td><td><?php echo SSA_Account::status_label( $p->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td><?php echo $p->paid_at ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $p->paid_at ) ) . ( $p->reference ? ' · ' . $p->reference : '' ) ) : '—'; ?></td></tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php else : ?>
	<p class="ssa-muted"><?php esc_html_e( 'No payouts yet.', 'splitshare-affiliates' ); ?></p>
<?php endif; ?>

<h3><?php esc_html_e( 'Bank details', 'splitshare-affiliates' ); ?></h3>
<form method="post" class="ssa-payout-form">
	<?php wp_nonce_field( 'ssa_panel', '_ssa_nonce' ); ?>
	<input type="hidden" name="ssa_action" value="payout_details" />
	<?php foreach ( array( 'holder' => __( 'Account holder', 'splitshare-affiliates' ), 'iban' => 'IBAN', 'company' => __( 'Company / tax office (if you issue invoices)', 'splitshare-affiliates' ), 'tax_no' => __( 'Tax / ID number', 'splitshare-affiliates' ) ) as $k => $label ) : ?>
		<p class="form-row form-row-wide"><label for="ssa-payout-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></label><input type="text" id="ssa-payout-<?php echo esc_attr( $k ); ?>" name="payout[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( isset( $details[ $k ] ) ? $details[ $k ] : '' ); ?>" /></p>
	<?php endforeach; ?>
	<p class="form-row"><label><input type="checkbox" name="payout[invoices]" value="yes" <?php checked( isset( $details['invoices'] ) ? $details['invoices'] : '', 'yes' ); ?> /> <?php esc_html_e( 'I issue invoices for my commission', 'splitshare-affiliates' ); ?></label></p>
	<p><button class="button ssa-button"><?php esc_html_e( 'Save', 'splitshare-affiliates' ); ?></button></p>
</form>
