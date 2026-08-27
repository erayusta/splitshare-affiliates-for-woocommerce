<?php
/**
 * Panel — Pano. Override: yourtheme/woocommerce/splitshare-affiliates/account/dashboard.php
 *
 * @var SSA_Partner $partner; array $settings, $totals, $recent, $notices; int $next_payout
 */

defined( 'ABSPATH' ) || exit;
?>
<?php foreach ( $notices as $n ) : ?>
	<p class="ssa-notice ssa-notice-<?php echo esc_attr( $n['type'] ); ?>"><?php echo esc_html( $n['message'] ); ?></p>
<?php endforeach; ?>

<div class="ssa-cards">
	<div class="ssa-card ssa-card--code">
		<div class="ssa-card__label"><?php esc_html_e( 'Your code', 'splitshare-affiliates' ); ?></div>
		<div class="ssa-code"><span><?php echo esc_html( $partner->code ); ?></span><button type="button" class="ssa-copy" data-copy="<?php echo esc_attr( $partner->code ); ?>"><?php esc_html_e( 'Copy', 'splitshare-affiliates' ); ?></button></div>
		<div class="ssa-card__hint"><?php printf( esc_html__( '%1$s%% commission + %2$s%% follower discount', 'splitshare-affiliates' ), esc_html( wc_format_decimal( $partner->commission_pct, 1 ) ), esc_html( wc_format_decimal( $partner->discount_pct, 1 ) ) ); ?></div>
	</div>
	<div class="ssa-card">
		<div class="ssa-card__label"><?php esc_html_e( 'This month', 'splitshare-affiliates' ); ?></div>
		<div class="ssa-card__value"><?php echo wp_kses_post( wc_price( $totals['month_commission'] ) ); ?></div>
		<div class="ssa-card__hint"><?php printf( esc_html__( 'on %s sales', 'splitshare-affiliates' ), wp_kses_post( wc_price( $totals['month_sales'] ) ) ); ?></div>
	</div>
	<div class="ssa-card">
		<div class="ssa-card__label"><?php esc_html_e( 'Pending', 'splitshare-affiliates' ); ?></div>
		<div class="ssa-card__value"><?php echo wp_kses_post( wc_price( $totals['pending'] ) ); ?></div>
		<div class="ssa-card__hint"><?php printf( esc_html__( 'approved %d days after delivery', 'splitshare-affiliates' ), (int) $settings['hold_days'] ); ?></div>
	</div>
	<div class="ssa-card">
		<div class="ssa-card__label"><?php esc_html_e( 'Ready for payout', 'splitshare-affiliates' ); ?></div>
		<div class="ssa-card__value"><?php echo wp_kses_post( wc_price( max( 0, $totals['unpaid'] ) ) ); ?></div>
		<div class="ssa-card__hint"><?php printf( esc_html__( 'next payout %s', 'splitshare-affiliates' ), esc_html( date_i18n( get_option( 'date_format' ), $next_payout ) ) ); ?></div>
	</div>
	<div class="ssa-card">
		<div class="ssa-card__label"><?php esc_html_e( 'Paid so far', 'splitshare-affiliates' ); ?></div>
		<div class="ssa-card__value"><?php echo wp_kses_post( wc_price( $totals['paid'] ) ); ?></div>
		<div class="ssa-card__hint"><?php printf( esc_html__( '%d approved sales', 'splitshare-affiliates' ), (int) $totals['sales_count'] ); ?><?php echo $partner->tier ? ' · <strong>' . esc_html( $partner->tier ) . '</strong>' : ''; ?></div>
	</div>
</div>

<?php if ( empty( $partner->split_changed_at ) ) : ?>
	<p class="ssa-notice ssa-notice-info"><?php esc_html_e( 'First step: decide how to split your share between your commission and your followers\' discount.', 'splitshare-affiliates' ); ?> <a href="<?php echo esc_url( SSA_Account::url( 'split' ) ); ?>"><?php esc_html_e( 'Split my share', 'splitshare-affiliates' ); ?> →</a></p>
<?php endif; ?>

<h3><?php esc_html_e( 'Recent sales', 'splitshare-affiliates' ); ?></h3>
<?php if ( $recent ) : ?>
	<table class="ssa-table">
		<thead><tr><th><?php esc_html_e( 'Date', 'splitshare-affiliates' ); ?></th><th><?php esc_html_e( 'Basket', 'splitshare-affiliates' ); ?></th><th><?php esc_html_e( 'Commission', 'splitshare-affiliates' ); ?></th><th><?php esc_html_e( 'Status', 'splitshare-affiliates' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $recent as $c ) : ?>
			<tr><td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $c->created_at ) ) ); ?></td><td><?php echo wp_kses_post( wc_price( $c->order_total_base ) ); ?></td><td><?php echo wp_kses_post( wc_price( $c->amount ) ); ?></td><td><?php echo SSA_Account::status_label( $c->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td></tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p><a href="<?php echo esc_url( SSA_Account::url( 'sales' ) ); ?>"><?php esc_html_e( 'All sales', 'splitshare-affiliates' ); ?> →</a></p>
<?php else : ?>
	<p class="ssa-muted"><?php esc_html_e( 'No sales yet. Share your code or a link to get started.', 'splitshare-affiliates' ); ?> <a href="<?php echo esc_url( SSA_Account::url( 'links' ) ); ?>"><?php esc_html_e( 'Create a link', 'splitshare-affiliates' ); ?> →</a></p>
<?php endif; ?>

<?php if ( ! empty( $settings['legal_notice'] ) ) : ?>
	<div class="ssa-legal-box"><?php echo esc_html( $settings['legal_notice'] ); ?></div>
<?php endif; ?>
