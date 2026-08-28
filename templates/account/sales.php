<?php
/**
 * Panel — Satışlar: durum özeti, aya göre gruplu tablo.
 *
 * @var SSA_Partner $partner; array $rows, $earnings; int $total, $page, $pages
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ssa-cards ssa-cards--4">
	<div class="ssa-card"><div class="ssa-card__label"><?php esc_html_e( 'Estimated', 'splitshare-affiliates' ); ?></div><div class="ssa-card__value"><?php echo wp_kses_post( wc_price( $earnings['estimated'] ) ); ?></div><div class="ssa-card__hint"><?php esc_html_e( 'in the hold period', 'splitshare-affiliates' ); ?></div></div>
	<div class="ssa-card"><div class="ssa-card__label"><?php esc_html_e( 'Confirmed', 'splitshare-affiliates' ); ?></div><div class="ssa-card__value"><?php echo wp_kses_post( wc_price( $earnings['confirmed'] ) ); ?></div><div class="ssa-card__hint"><?php esc_html_e( 'approved + paid', 'splitshare-affiliates' ); ?></div></div>
	<div class="ssa-card"><div class="ssa-card__label"><?php esc_html_e( 'Approved sales', 'splitshare-affiliates' ); ?></div><div class="ssa-card__value"><?php echo (int) $earnings['sales']; ?></div><?php if ( $partner->tier ) : ?><div class="ssa-card__hint"><?php echo esc_html( $partner->tier ); ?></div><?php endif; ?></div>
	<div class="ssa-card"><div class="ssa-card__label"><?php esc_html_e( 'Cancelled', 'splitshare-affiliates' ); ?></div><div class="ssa-card__value"><?php echo wp_kses_post( wc_price( $earnings['void'] ) ); ?></div><div class="ssa-card__hint"><?php esc_html_e( 'returns & below minimum', 'splitshare-affiliates' ); ?></div></div>
</div>

<?php if ( ! $rows ) : ?>
	<p class="ssa-muted"><?php esc_html_e( 'No sales yet.', 'splitshare-affiliates' ); ?></p>
<?php else : ?>
	<?php $month = ''; ?>
	<table class="ssa-table ssa-table--grouped">
		<thead><tr>
			<th><?php esc_html_e( 'Date', 'splitshare-affiliates' ); ?></th>
			<th><?php esc_html_e( 'Order', 'splitshare-affiliates' ); ?></th>
			<th class="num"><?php esc_html_e( 'Basket', 'splitshare-affiliates' ); ?></th>
			<th><?php esc_html_e( 'Via', 'splitshare-affiliates' ); ?></th>
			<th class="num"><?php esc_html_e( 'Commission', 'splitshare-affiliates' ); ?></th>
			<th><?php esc_html_e( 'Status', 'splitshare-affiliates' ); ?></th>
		</tr></thead>
		<tbody>
		<?php foreach ( $rows as $c ) : ?>
			<?php $m = date_i18n( 'F Y', strtotime( $c->created_at ) ); if ( $m !== $month ) : $month = $m; ?>
				<tr class="ssa-table__group"><th colspan="6"><?php echo esc_html( $m ); ?></th></tr>
			<?php endif; ?>
			<tr>
				<td><?php echo esc_html( date_i18n( 'j M', strtotime( $c->created_at ) ) ); ?></td>
				<td>#…<?php echo esc_html( substr( (string) $c->order_id, -4 ) ); ?><?php echo $c->adjustment_of ? ' <small class="ssa-muted">' . esc_html__( 'refund adjustment', 'splitshare-affiliates' ) . '</small>' : ''; ?></td>
				<td class="num"><?php echo wp_kses_post( wc_price( $c->order_total_base ) ); ?></td>
				<td><?php echo 'coupon' === $c->attribution ? esc_html__( 'Code', 'splitshare-affiliates' ) : esc_html__( 'Link', 'splitshare-affiliates' ); ?><?php echo $c->is_new_customer ? '' : '<br><small class="ssa-muted">' . esc_html__( 'returning customer', 'splitshare-affiliates' ) . '</small>'; ?></td>
				<td class="num"><strong><?php echo wp_kses_post( wc_price( $c->amount ) ); ?></strong></td>
				<td><?php echo SSA_Account::status_label( $c->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php if ( 'pending' === $c->status && $c->available_at ) : ?><br><small class="ssa-muted"><?php printf( esc_html__( 'confirms %s', 'splitshare-affiliates' ), esc_html( date_i18n( 'j M', strtotime( $c->available_at ) ) ) ); ?></small><?php endif; ?>
					<?php if ( 'void' === $c->status && 'below_min' === $c->reason ) : ?><br><small class="ssa-muted"><?php esc_html_e( 'below minimum basket', 'splitshare-affiliates' ); ?></small><?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php if ( $pages > 1 ) : ?>
		<p class="ssa-pagination">
			<?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
				<?php if ( $i === $page ) : ?><strong><?php echo (int) $i; ?></strong><?php else : ?><a href="<?php echo esc_url( add_query_arg( 'pg', $i, SSA_Account::url( 'sales' ) ) ); ?>"><?php echo (int) $i; ?></a><?php endif; ?>
			<?php endfor; ?>
		</p>
	<?php endif; ?>
<?php endif; ?>
