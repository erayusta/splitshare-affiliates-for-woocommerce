<?php
/**
 * Panel — Satışlar.
 *
 * @var SSA_Partner $partner; array $rows; int $total, $page, $pages
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( ! $rows ) : ?>
	<p class="ssa-muted"><?php esc_html_e( 'No sales yet.', 'splitshare-affiliates' ); ?></p>
<?php else : ?>
	<table class="ssa-table">
		<thead><tr>
			<th><?php esc_html_e( 'Date', 'splitshare-affiliates' ); ?></th>
			<th><?php esc_html_e( 'Order', 'splitshare-affiliates' ); ?></th>
			<th><?php esc_html_e( 'Basket', 'splitshare-affiliates' ); ?></th>
			<th><?php esc_html_e( 'Via', 'splitshare-affiliates' ); ?></th>
			<th><?php esc_html_e( 'Commission', 'splitshare-affiliates' ); ?></th>
			<th><?php esc_html_e( 'Status', 'splitshare-affiliates' ); ?></th>
		</tr></thead>
		<tbody>
		<?php foreach ( $rows as $c ) : ?>
			<tr>
				<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $c->created_at ) ) ); ?></td>
				<td>#…<?php echo esc_html( substr( (string) $c->order_id, -4 ) ); ?><?php echo $c->adjustment_of ? ' <small>(' . esc_html__( 'refund adjustment', 'splitshare-affiliates' ) . ')</small>' : ''; ?></td>
				<td><?php echo wp_kses_post( wc_price( $c->order_total_base ) ); ?></td>
				<td><?php echo 'coupon' === $c->attribution ? esc_html__( 'Code', 'splitshare-affiliates' ) : esc_html__( 'Link', 'splitshare-affiliates' ); ?><?php echo $c->is_new_customer ? '' : '<br><small class="ssa-muted">' . esc_html__( 'returning customer', 'splitshare-affiliates' ) . '</small>'; ?></td>
				<td><strong><?php echo wp_kses_post( wc_price( $c->amount ) ); ?></strong></td>
				<td><?php echo SSA_Account::status_label( $c->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php if ( 'pending' === $c->status && $c->available_at ) : ?><br><small class="ssa-muted"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $c->available_at ) ) ); ?></small><?php endif; ?>
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
