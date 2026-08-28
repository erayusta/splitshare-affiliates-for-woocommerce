<?php
/**
 * Panel — Hakedişler: bakiye + min. ödemeye ilerleme, ödeme geçmişi, banka bilgileri.
 *
 * @var SSA_Partner $partner; array $payouts, $earnings, $settings, $notices; float $carry_over; int $next_payout
 */

defined( 'ABSPATH' ) || exit;
$details = $partner->payout_details;
$min     = (float) $settings['min_payout'];
$balance = max( 0, (float) $carry_over );
?>
<?php foreach ( $notices as $n ) : ?>
	<p class="ssa-notice ssa-notice-<?php echo esc_attr( $n['type'] ); ?>"><?php echo esc_html( $n['message'] ); ?></p>
<?php endforeach; ?>

<div class="ssa-two">
	<section class="ssa-block ssa-block--balance">
		<span class="ssa-card__label"><?php esc_html_e( 'Ready for payout', 'splitshare-affiliates' ); ?></span>
		<div class="ssa-hero-number"><?php echo wp_kses_post( wc_price( $balance ) ); ?></div>
		<?php echo SSA_Charts::meter( $balance, $min, $balance >= $min ? sprintf( __( 'Above the %1$s minimum — paid on %2$s', 'splitshare-affiliates' ), wp_strip_all_tags( wc_price( $min ) ), date_i18n( get_option( 'date_format' ), $next_payout ) ) : sprintf( __( '%1$s more to reach the %2$s minimum; otherwise it carries over to next month', 'splitshare-affiliates' ), wp_strip_all_tags( wc_price( $min - $balance ) ), wp_strip_all_tags( wc_price( $min ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php if ( $carry_over < 0 ) : ?><p class="ssa-muted"><?php printf( esc_html__( 'A refund adjustment of %s will be deducted from your next payout.', 'splitshare-affiliates' ), wp_kses_post( wc_price( abs( $carry_over ) ) ) ); ?></p><?php endif; ?>
	</section>
	<section class="ssa-block">
		<div class="ssa-cards ssa-cards--2">
			<div class="ssa-card"><div class="ssa-card__label"><?php esc_html_e( 'Estimated', 'splitshare-affiliates' ); ?></div><div class="ssa-card__value"><?php echo wp_kses_post( wc_price( $earnings['estimated'] ) ); ?></div><div class="ssa-card__hint"><?php esc_html_e( 'pending approval', 'splitshare-affiliates' ); ?></div></div>
			<div class="ssa-card"><div class="ssa-card__label"><?php esc_html_e( 'Paid so far', 'splitshare-affiliates' ); ?></div><div class="ssa-card__value"><?php echo wp_kses_post( wc_price( $earnings['paid'] ) ); ?></div><div class="ssa-card__hint"><?php printf( esc_html__( '%d payouts', 'splitshare-affiliates' ), count( array_filter( $payouts, function ( $p ) { return 'paid' === $p->status; } ) ) ); ?></div></div>
		</div>
	</section>
</div>

<section class="ssa-block">
	<h3><?php esc_html_e( 'Payouts', 'splitshare-affiliates' ); ?></h3>
	<?php if ( $payouts ) : ?>
		<table class="ssa-table">
			<thead><tr><th><?php esc_html_e( 'Period', 'splitshare-affiliates' ); ?></th><th class="num"><?php esc_html_e( 'Amount', 'splitshare-affiliates' ); ?></th><th><?php esc_html_e( 'Status', 'splitshare-affiliates' ); ?></th><th><?php esc_html_e( 'Paid on', 'splitshare-affiliates' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $payouts as $p ) : ?>
				<tr><td><?php echo esc_html( date_i18n( 'F Y', strtotime( $p->period . '-01' ) ) ); ?></td><td class="num"><strong><?php echo wp_kses_post( wc_price( $p->amount ) ); ?></strong></td><td><?php echo SSA_Account::status_label( $p->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td><?php echo $p->paid_at ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $p->paid_at ) ) . ( $p->reference ? ' · ' . $p->reference : '' ) ) : '—'; ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p class="ssa-muted"><?php esc_html_e( 'No payouts yet.', 'splitshare-affiliates' ); ?></p>
	<?php endif; ?>
</section>

<section class="ssa-block">
	<h3><?php esc_html_e( 'Bank details', 'splitshare-affiliates' ); ?></h3>
	<form method="post" class="ssa-payout-form">
		<?php wp_nonce_field( 'ssa_panel', '_ssa_nonce' ); ?>
		<input type="hidden" name="ssa_action" value="payout_details" />
		<div class="ssa-form-grid">
		<?php foreach ( array( 'holder' => __( 'Account holder', 'splitshare-affiliates' ), 'iban' => 'IBAN', 'company' => __( 'Company / tax office (if you issue invoices)', 'splitshare-affiliates' ), 'tax_no' => __( 'Tax / ID number', 'splitshare-affiliates' ) ) as $k => $label ) : ?>
			<p class="form-row"><label for="ssa-payout-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></label><input type="text" id="ssa-payout-<?php echo esc_attr( $k ); ?>" name="payout[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( isset( $details[ $k ] ) ? $details[ $k ] : '' ); ?>" /></p>
		<?php endforeach; ?>
		</div>
		<p class="form-row"><label><input type="checkbox" name="payout[invoices]" value="yes" <?php checked( isset( $details['invoices'] ) ? $details['invoices'] : '', 'yes' ); ?> /> <?php esc_html_e( 'I issue invoices for my commission', 'splitshare-affiliates' ); ?></label></p>
		<p><button class="button ssa-button"><?php esc_html_e( 'Save', 'splitshare-affiliates' ); ?></button></p>
	</form>
</section>
