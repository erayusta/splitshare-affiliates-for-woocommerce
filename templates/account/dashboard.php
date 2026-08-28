<?php
/**
 * Panel — Pano: hero (link kodu + kuponlar), kesin/tahmini kazanç, trafik grafiği, son satışlar.
 * Override: yourtheme/woocommerce/splitshare-affiliates/account/dashboard.php
 *
 * @var SSA_Partner $partner; array $settings, $earnings, $traffic, $recent, $notices, $monthly, $coupons; int $next_payout
 */

defined( 'ABSPATH' ) || exit;
?>
<?php foreach ( $notices as $n ) : ?>
	<p class="ssa-notice ssa-notice-<?php echo esc_attr( $n['type'] ); ?>"><?php echo esc_html( $n['message'] ); ?></p>
<?php endforeach; ?>

<section class="ssa-hero">
	<div class="ssa-hero__code">
		<span class="ssa-card__label"><?php esc_html_e( 'Your referral link', 'splitshare-affiliates' ); ?></span>
		<div class="ssa-code ssa-code--link"><span><?php echo esc_html( preg_replace( '#^https?://#', '', $partner->link() ) ); ?></span><button type="button" class="ssa-copy" data-copy="<?php echo esc_attr( $partner->link() ); ?>"><?php esc_html_e( 'Copy', 'splitshare-affiliates' ); ?></button></div>
		<div class="ssa-hero__coupons">
			<strong><?php printf( esc_html( _n( '%d live coupon', '%d live coupons', (int) $coupons['active'], 'splitshare-affiliates' ) ), (int) $coupons['active'] ); ?></strong>
			<?php if ( $coupons['best'] ) : ?>
				<span class="ssa-muted"><?php printf( esc_html__( 'best: %1$s (%2$s%% off)', 'splitshare-affiliates' ), '<code>' . esc_html( $coupons['best']->code ) . '</code>', esc_html( wc_format_decimal( $coupons['best']->discount_pct, 1 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<?php endif; ?>
		</div>
		<a class="ssa-hero__link" href="<?php echo esc_url( SSA_Account::url( 'coupons' ) ); ?>"><?php esc_html_e( 'Manage my coupons', 'splitshare-affiliates' ); ?> →</a>
	</div>
	<div class="ssa-hero__stats">
		<div class="ssa-stat ssa-stat--confirmed">
			<span class="ssa-card__label"><?php esc_html_e( 'Confirmed earnings', 'splitshare-affiliates' ); ?></span>
			<strong><?php echo wp_kses_post( wc_price( $earnings['confirmed'] ) ); ?></strong>
			<small><?php printf( esc_html__( '%1$s paid · %2$s ready for payout', 'splitshare-affiliates' ), wp_kses_post( wc_price( $earnings['paid'] ) ), wp_kses_post( wc_price( max( 0, $earnings['unpaid'] ) ) ) ); ?></small>
		</div>
		<div class="ssa-stat ssa-stat--estimated">
			<span class="ssa-card__label"><?php esc_html_e( 'Estimated earnings', 'splitshare-affiliates' ); ?></span>
			<strong><?php echo wp_kses_post( wc_price( $earnings['estimated'] ) ); ?></strong>
			<small><?php printf( esc_html__( 'confirmed %d days after delivery, unless returned', 'splitshare-affiliates' ), (int) $settings['hold_days'] ); ?></small>
		</div>
		<div class="ssa-stat">
			<span class="ssa-card__label"><?php esc_html_e( 'This month', 'splitshare-affiliates' ); ?></span>
			<strong><?php echo wp_kses_post( wc_price( $earnings['month_commission'] ) ); ?></strong>
			<small><?php printf( esc_html__( 'on %s sales', 'splitshare-affiliates' ), wp_kses_post( wc_price( $earnings['month_sales'] ) ) ); ?></small>
		</div>
		<div class="ssa-stat">
			<span class="ssa-card__label"><?php esc_html_e( 'Next payout', 'splitshare-affiliates' ); ?></span>
			<strong><?php echo esc_html( date_i18n( 'j M', $next_payout ) ); ?></strong>
			<small><?php printf( esc_html__( 'minimum %s, the rest carries over', 'splitshare-affiliates' ), wp_kses_post( wc_price( $settings['min_payout'] ) ) ); ?></small>
		</div>
	</div>
</section>

<?php if ( ! $coupons['active'] ) : ?>
	<p class="ssa-notice ssa-notice-info"><?php esc_html_e( 'First step: create a coupon for your next post — pick the discount your followers get and where it applies.', 'splitshare-affiliates' ); ?> <a href="<?php echo esc_url( SSA_Account::url( 'coupons' ) ); ?>"><?php esc_html_e( 'Create a coupon', 'splitshare-affiliates' ); ?> →</a></p>
<?php endif; ?>

<div class="ssa-two">
	<section class="ssa-block">
		<h3><?php esc_html_e( 'Traffic, last 30 days', 'splitshare-affiliates' ); ?> <small class="ssa-muted"><?php printf( esc_html__( '%1$d clicks · %2$d orders from links', 'splitshare-affiliates' ), array_sum( $traffic['clicks'] ), array_sum( $traffic['conversions'] ) ); ?></small></h3>
		<?php if ( array_sum( $traffic['clicks'] ) > 0 ) : ?>
			<?php echo SSA_Charts::columns( $traffic['labels'], array( array( 'name' => __( 'Clicks', 'splitshare-affiliates' ), 'values' => $traffic['clicks'], 'format' => 'int' ), array( 'name' => __( 'Orders', 'splitshare-affiliates' ), 'values' => $traffic['conversions'], 'format' => 'int' ) ), array( 'title' => __( 'Daily clicks and orders from your links', 'splitshare-affiliates' ), 'height' => 200, 'format' => 'int', 'width' => 520 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php else : ?>
			<p class="ssa-muted"><?php esc_html_e( 'No clicks yet. Share a link to see your traffic here.', 'splitshare-affiliates' ); ?> <a href="<?php echo esc_url( SSA_Account::url( 'links' ) ); ?>"><?php esc_html_e( 'Create a link', 'splitshare-affiliates' ); ?> →</a></p>
		<?php endif; ?>
	</section>
	<section class="ssa-block">
		<h3><?php esc_html_e( 'Commission by month', 'splitshare-affiliates' ); ?></h3>
		<?php if ( array_sum( $monthly['commission'] ) > 0 ) : ?>
			<?php echo SSA_Charts::columns( $monthly['labels'], array( array( 'name' => __( 'Commission', 'splitshare-affiliates' ), 'values' => $monthly['commission'] ) ), array( 'title' => __( 'Monthly commission', 'splitshare-affiliates' ), 'height' => 200, 'width' => 520 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php else : ?>
			<p class="ssa-muted"><?php esc_html_e( 'No sales yet. Share your code or a link to get started.', 'splitshare-affiliates' ); ?></p>
		<?php endif; ?>
	</section>
</div>

<section class="ssa-block">
	<h3><?php esc_html_e( 'Recent sales', 'splitshare-affiliates' ); ?> <a class="ssa-more" href="<?php echo esc_url( SSA_Account::url( 'sales' ) ); ?>"><?php esc_html_e( 'All sales', 'splitshare-affiliates' ); ?> →</a></h3>
	<?php if ( $recent ) : ?>
		<table class="ssa-table">
			<thead><tr><th><?php esc_html_e( 'Date', 'splitshare-affiliates' ); ?></th><th><?php esc_html_e( 'Basket', 'splitshare-affiliates' ); ?></th><th><?php esc_html_e( 'Via', 'splitshare-affiliates' ); ?></th><th class="num"><?php esc_html_e( 'Commission', 'splitshare-affiliates' ); ?></th><th><?php esc_html_e( 'Status', 'splitshare-affiliates' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $recent as $c ) : ?>
				<tr><td><?php echo esc_html( date_i18n( 'j M', strtotime( $c->created_at ) ) ); ?></td><td><?php echo wp_kses_post( wc_price( $c->order_total_base ) ); ?></td><td><?php echo 'coupon' === $c->attribution ? esc_html__( 'Code', 'splitshare-affiliates' ) : esc_html__( 'Link', 'splitshare-affiliates' ); ?></td><td class="num"><strong><?php echo wp_kses_post( wc_price( $c->amount ) ); ?></strong></td><td><?php echo SSA_Account::status_label( $c->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p class="ssa-muted"><?php esc_html_e( 'No sales yet.', 'splitshare-affiliates' ); ?></p>
	<?php endif; ?>
</section>

<?php if ( ! empty( $settings['legal_notice'] ) ) : ?>
	<div class="ssa-legal-box"><?php echo esc_html( $settings['legal_notice'] ); ?></div>
<?php endif; ?>
