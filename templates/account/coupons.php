<?php
/**
 * Panel — Kuponlarım: KPI'lar, kupon oluşturma formu (canlı örnek), kupon listesi.
 * Override: yourtheme/woocommerce/splitshare-affiliates/account/coupons.php
 *
 * @var SSA_Partner $partner; array $coupons, $limits, $kpis, $categories, $groups, $notices, $old, $settings; float $share, $link_pct; object|null $edit
 */

defined( 'ABSPATH' ) || exit;
$example = 1000;
$old     = wp_parse_args( $old, array( 'code' => '', 'name' => '', 'discount_pct' => '', 'scope_type' => 'all', 'scope_ids' => array(), 'expires_at' => '' ) );
$def_d   = '' !== $old['discount_pct'] ? (float) $old['discount_pct'] : min( $limits['max_discount'], max( $limits['min_discount'], round( $share / 3, 1 ) ) );
$old_products = array();
if ( 'products' === $old['scope_type'] ) {
	foreach ( (array) $old['scope_ids'] as $pid ) {
		$prod = wc_get_product( $pid );
		if ( $prod ) {
			$old_products[ $pid ] = $prod->get_name();
		}
	}
}
?>
<?php foreach ( $notices as $n ) : ?>
	<p class="ssa-notice ssa-notice-<?php echo esc_attr( $n['type'] ); ?>"><?php echo esc_html( $n['message'] ); ?></p>
<?php endforeach; ?>

<div class="ssa-cards ssa-cards--3">
	<div class="ssa-card"><div class="ssa-card__label"><?php esc_html_e( 'Live coupons', 'splitshare-affiliates' ); ?></div><div class="ssa-card__value"><?php echo (int) $kpis['active']; ?></div></div>
	<div class="ssa-card"><div class="ssa-card__label"><?php esc_html_e( 'Coupon orders, 30 days', 'splitshare-affiliates' ); ?></div><div class="ssa-card__value"><?php echo (int) $kpis['orders']; ?></div><div class="ssa-card__hint"><?php printf( esc_html__( 'on %s sales', 'splitshare-affiliates' ), wp_kses_post( wc_price( $kpis['revenue'] ) ) ); ?></div></div>
	<div class="ssa-card"><div class="ssa-card__label"><?php esc_html_e( 'Coupon earnings, 30 days', 'splitshare-affiliates' ); ?></div><div class="ssa-card__value"><?php echo wp_kses_post( wc_price( $kpis['commission'] ) ); ?></div></div>
</div>

<section class="ssa-block" id="ssa-coupon-form">
	<h3><?php echo $edit ? esc_html( sprintf( __( 'Edit coupon %s', 'splitshare-affiliates' ), $edit->code ) ) : esc_html__( 'Create a coupon', 'splitshare-affiliates' ); ?><?php if ( $edit ) : ?> <a class="ssa-more" href="<?php echo esc_url( SSA_Account::url( 'coupons' ) ); ?>"><?php esc_html_e( 'Cancel', 'splitshare-affiliates' ); ?></a><?php endif; ?></h3>
	<p class="ssa-muted"><?php printf( esc_html__( '%1$s%% of every sale is your share. The discount you give comes out of it and the rest is your commission — the store\'s cost is the same either way. Orders through your link without a coupon earn %2$s%%.', 'splitshare-affiliates' ), esc_html( wc_format_decimal( $share, 1 ) ), esc_html( wc_format_decimal( $link_pct, 1 ) ) ); ?></p>

	<form method="post" class="ssa-coupon-form" data-share="<?php echo esc_attr( $share ); ?>" data-link="<?php echo esc_attr( $link_pct ); ?>" data-example="<?php echo esc_attr( $example ); ?>">
		<?php wp_nonce_field( 'ssa_panel', '_ssa_nonce' ); ?>
		<input type="hidden" name="ssa_action" value="<?php echo $edit ? 'coupon_update' : 'coupon_create'; ?>" />
		<?php if ( $edit ) : ?><input type="hidden" name="coupon_id" value="<?php echo (int) $edit->id; ?>" /><?php endif; ?>

		<div class="ssa-form-grid">
			<div class="ssa-field">
				<label for="ssa-c-code"><?php esc_html_e( 'Coupon code', 'splitshare-affiliates' ); ?></label>
				<input type="text" id="ssa-c-code" name="code" class="ssa-code-input" value="<?php echo esc_attr( $old['code'] ); ?>" minlength="<?php echo (int) $limits['code_min']; ?>" maxlength="<?php echo (int) $limits['code_max']; ?>" pattern="[A-Za-z0-9]+" <?php echo $edit ? 'readonly' : ''; ?> placeholder="<?php echo esc_attr( strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', remove_accents( $partner->display_name() ) ), 0, 5 ) ) . '10' ); ?>" required />
				<small class="ssa-muted"><?php $edit ? esc_html_e( 'The code cannot be changed; delete the coupon and create a new one instead.', 'splitshare-affiliates' ) : printf( esc_html__( 'Letters and digits, %1$d–%2$d characters. Your followers type this at checkout.', 'splitshare-affiliates' ), (int) $limits['code_min'], (int) $limits['code_max'] ); ?></small>
			</div>
			<div class="ssa-field">
				<label for="ssa-c-name"><?php esc_html_e( 'Campaign name (optional)', 'splitshare-affiliates' ); ?></label>
				<input type="text" id="ssa-c-name" name="name" value="<?php echo esc_attr( $old['name'] ); ?>" maxlength="120" placeholder="<?php esc_attr_e( 'e.g. Instagram reel, September', 'splitshare-affiliates' ); ?>" />
			</div>
		</div>

		<div class="ssa-field ssa-field--discount">
			<label for="ssa-c-range"><?php esc_html_e( 'Follower discount', 'splitshare-affiliates' ); ?></label>
			<div class="ssa-discount-row">
				<input type="range" id="ssa-c-range" min="<?php echo esc_attr( $limits['min_discount'] ); ?>" max="<?php echo esc_attr( $limits['max_discount'] ); ?>" step="0.5" value="<?php echo esc_attr( $def_d ); ?>" aria-label="<?php esc_attr_e( 'Follower discount', 'splitshare-affiliates' ); ?>" />
				<span class="ssa-discount-num"><input type="number" id="ssa-c-num" name="discount_pct" min="<?php echo esc_attr( $limits['min_discount'] ); ?>" max="<?php echo esc_attr( $limits['max_discount'] ); ?>" step="0.5" value="<?php echo esc_attr( $def_d ); ?>" required />%</span>
			</div>
			<div class="ssa-split ssa-split--lg" aria-hidden="true"><div class="ssa-split__track"><span class="ssa-split__c" id="ssa-c-bar-c"></span><span class="ssa-split__d" id="ssa-c-bar-d"></span></div></div>
			<div class="ssa-split__readout">
				<span><i class="ssa-swatch ssa-swatch--c"></i><?php esc_html_e( 'Your commission', 'splitshare-affiliates' ); ?>: <output id="ssa-c-commission"></output>%</span>
				<span><i class="ssa-swatch ssa-swatch--d"></i><?php esc_html_e( 'Follower discount', 'splitshare-affiliates' ); ?>: <output id="ssa-c-discount"></output>%</span>
			</div>
		</div>

		<div class="ssa-field">
			<span class="ssa-label"><?php esc_html_e( 'Where does it apply?', 'splitshare-affiliates' ); ?></span>
			<div class="ssa-scope">
				<label class="ssa-scope__opt"><input type="radio" name="scope_type" value="all" <?php checked( $old['scope_type'], 'all' ); ?> /> <?php esc_html_e( 'Whole store', 'splitshare-affiliates' ); ?></label>
				<label class="ssa-scope__opt"><input type="radio" name="scope_type" value="products" <?php checked( $old['scope_type'], 'products' ); ?> /> <?php esc_html_e( 'Selected products', 'splitshare-affiliates' ); ?></label>
				<label class="ssa-scope__opt"><input type="radio" name="scope_type" value="categories" <?php checked( $old['scope_type'], 'categories' ); ?> /> <?php esc_html_e( 'Selected categories', 'splitshare-affiliates' ); ?></label>
			</div>
			<div class="ssa-scope-panel" data-scope="products">
				<select id="ssa-c-products" name="scope_products[]" multiple="multiple" class="ssa-product-search" data-placeholder="<?php esc_attr_e( 'Search products…', 'splitshare-affiliates' ); ?>">
					<?php foreach ( $old_products as $pid => $pname ) : ?>
						<option value="<?php echo (int) $pid; ?>" selected="selected"><?php echo esc_html( $pname ); ?></option>
					<?php endforeach; ?>
				</select>
				<small class="ssa-muted"><?php esc_html_e( 'The discount applies only to these products; other items in the basket earn your link rate.', 'splitshare-affiliates' ); ?></small>
			</div>
			<div class="ssa-scope-panel" data-scope="categories">
				<select id="ssa-c-categories" name="scope_categories[]" multiple="multiple" class="ssa-category-select" data-placeholder="<?php esc_attr_e( 'Choose categories…', 'splitshare-affiliates' ); ?>">
					<?php foreach ( $categories as $cid => $cname ) : ?>
						<option value="<?php echo (int) $cid; ?>" <?php selected( 'categories' === $old['scope_type'] && in_array( (int) $cid, array_map( 'intval', (array) $old['scope_ids'] ), true ) ); ?>><?php echo esc_html( $cname ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="ssa-form-grid">
			<div class="ssa-field">
				<label for="ssa-c-expires"><?php esc_html_e( 'End date (optional)', 'splitshare-affiliates' ); ?></label>
				<input type="date" id="ssa-c-expires" name="expires_at" value="<?php echo esc_attr( $old['expires_at'] ); ?>" min="<?php echo esc_attr( date_i18n( 'Y-m-d' ) ); ?>" />
				<small class="ssa-muted"><?php esc_html_e( 'Leave empty for an open-ended coupon. You can pause or delete it any time.', 'splitshare-affiliates' ); ?></small>
			</div>
			<div class="ssa-example">
				<div class="ssa-example__title"><?php printf( esc_html__( 'Example: a %s basket', 'splitshare-affiliates' ), wp_kses_post( wc_price( $example ) ) ); ?></div>
				<div class="ssa-example__row"><span><?php esc_html_e( 'Your follower pays', 'splitshare-affiliates' ); ?></span><strong id="ssa-ex-pays"></strong></div>
				<div class="ssa-example__row"><span><?php esc_html_e( 'You earn', 'splitshare-affiliates' ); ?></span><strong id="ssa-ex-earn"></strong></div>
				<div class="ssa-example__row ssa-muted"><span><?php esc_html_e( 'Through your link, without a coupon', 'splitshare-affiliates' ); ?></span><strong id="ssa-ex-link"></strong></div>
			</div>
		</div>

		<p class="ssa-form-actions">
			<button class="button ssa-button"><?php $edit ? esc_html_e( 'Save changes', 'splitshare-affiliates' ) : esc_html_e( 'Create coupon', 'splitshare-affiliates' ); ?></button>
			<span class="ssa-muted"><?php printf( esc_html__( 'Each customer can use a coupon %d time(s). Coupons work on baskets of %s and above.', 'splitshare-affiliates' ), max( 1, (int) $settings['coupon_usage_limit_per_user'] ), wp_kses_post( wc_price( $settings['min_order'] ) ) ); ?></span>
		</p>
	</form>
</section>

<section class="ssa-block">
	<h3><?php esc_html_e( 'My coupons', 'splitshare-affiliates' ); ?></h3>
	<?php if ( $coupons ) : ?>
		<table class="ssa-table ssa-coupons">
			<thead><tr>
				<th><?php esc_html_e( 'Coupon', 'splitshare-affiliates' ); ?></th>
				<th class="num"><?php esc_html_e( 'Discount', 'splitshare-affiliates' ); ?></th>
				<th><?php esc_html_e( 'Applies to', 'splitshare-affiliates' ); ?></th>
				<th class="num"><?php esc_html_e( 'Orders', 'splitshare-affiliates' ); ?></th>
				<th class="num"><?php esc_html_e( 'Earned', 'splitshare-affiliates' ); ?></th>
				<th><?php esc_html_e( 'Ends', 'splitshare-affiliates' ); ?></th>
				<th><?php esc_html_e( 'Status', 'splitshare-affiliates' ); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $coupons as $c ) : ?>
				<?php $st = SSA_Partner_Coupons::stats( $c ); $names = SSA_Partner_Coupons::scope_names( $c, 2 ); ?>
				<tr class="<?php echo 'active' === $c->status ? '' : 'is-muted'; ?>">
					<td>
						<span class="ssa-code ssa-code--sm"><span><?php echo esc_html( $c->code ); ?></span><button type="button" class="ssa-copy" data-copy="<?php echo esc_attr( $c->code ); ?>" title="<?php esc_attr_e( 'Copy', 'splitshare-affiliates' ); ?>"><?php esc_html_e( 'Copy', 'splitshare-affiliates' ); ?></button></span>
						<?php if ( $c->name ) : ?><small class="ssa-muted"><?php echo esc_html( SSA_Partner_Coupons::display_name( $c ) ); ?></small><?php endif; ?>
					</td>
					<td class="num"><strong><?php echo esc_html( wc_format_decimal( $c->discount_pct, 1 ) ); ?>%</strong><br><small class="ssa-muted"><?php printf( esc_html__( 'you: %s%%', 'splitshare-affiliates' ), esc_html( wc_format_decimal( max( 0, $share - $c->discount_pct ), 1 ) ) ); ?></small></td>
					<td><?php echo esc_html( SSA_Partner_Coupons::scope_label( $c ) ); ?><?php if ( $names ) : ?><br><small class="ssa-muted"><?php echo esc_html( implode( ', ', $names ) ); ?></small><?php endif; ?></td>
					<td class="num"><?php echo (int) $st['orders']; ?></td>
					<td class="num"><strong><?php echo wp_kses_post( wc_price( $st['commission'] ) ); ?></strong></td>
					<td><?php echo $c->expires_at ? esc_html( date_i18n( 'j M Y', strtotime( $c->expires_at ) ) ) : '—'; ?></td>
					<td><?php echo SSA_Account::status_label( $c->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<td class="ssa-coupon-actions">
						<button type="button" class="ssa-copy ssa-linkbtn" data-copy="<?php echo esc_attr( SSA_Partner_Coupons::share_url( $c, $partner ) ); ?>"><?php esc_html_e( 'Copy link', 'splitshare-affiliates' ); ?></button>
						<a class="ssa-linkbtn" href="<?php echo esc_url( add_query_arg( 'edit', $c->id, SSA_Account::url( 'coupons' ) ) . '#ssa-coupon-form' ); ?>"><?php esc_html_e( 'Edit', 'splitshare-affiliates' ); ?></a>
						<?php if ( 'expired' !== $c->status ) : ?>
							<form method="post"><?php wp_nonce_field( 'ssa_panel', '_ssa_nonce' ); ?><input type="hidden" name="coupon_id" value="<?php echo (int) $c->id; ?>" /><input type="hidden" name="ssa_action" value="<?php echo 'active' === $c->status ? 'coupon_pause' : 'coupon_resume'; ?>" /><button class="ssa-linkbtn"><?php echo 'active' === $c->status ? esc_html__( 'Pause', 'splitshare-affiliates' ) : esc_html__( 'Resume', 'splitshare-affiliates' ); ?></button></form>
						<?php endif; ?>
						<form method="post" class="ssa-confirm" data-confirm="<?php esc_attr_e( 'Delete this coupon? It stops working immediately; past earnings are kept.', 'splitshare-affiliates' ); ?>"><?php wp_nonce_field( 'ssa_panel', '_ssa_nonce' ); ?><input type="hidden" name="coupon_id" value="<?php echo (int) $c->id; ?>" /><input type="hidden" name="ssa_action" value="coupon_delete" /><button class="ssa-linkbtn ssa-linkbtn--danger"><?php esc_html_e( 'Delete', 'splitshare-affiliates' ); ?></button></form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p class="ssa-muted"><?php esc_html_e( 'No coupons yet — create your first campaign above. Each coupon can have its own discount and product scope.', 'splitshare-affiliates' ); ?></p>
	<?php endif; ?>
</section>

<?php if ( $groups ) : ?>
	<section class="ssa-block">
		<h3><?php esc_html_e( 'Share by product group', 'splitshare-affiliates' ); ?></h3>
		<p class="ssa-muted"><?php esc_html_e( 'Some groups have a smaller share because of their margin; your coupon discount is subtracted from that group\'s share, so your commission there can be lower.', 'splitshare-affiliates' ); ?></p>
		<table class="ssa-table">
			<tbody>
			<tr><td><?php esc_html_e( 'Default', 'splitshare-affiliates' ); ?></td><td class="num"><?php echo esc_html( wc_format_decimal( $share, 1 ) ); ?>%</td></tr>
			<?php foreach ( $groups as $g ) : ?>
				<tr><td><?php echo esc_html( $g['name'] ); ?></td><td class="num"><?php echo esc_html( wc_format_decimal( $g['pct'], 1 ) ); ?>%</td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</section>
<?php endif; ?>
