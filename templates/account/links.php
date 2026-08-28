<?php
/**
 * Panel — Linkler: trafik özeti + günlük grafik, ürün aramalı link üreteci, hızlandırıcılar.
 *
 * @var SSA_Partner $partner; array $stats, $traffic, $boosters, $categories, $settings
 */

defined( 'ABSPATH' ) || exit;
$code = $partner->code;
?>
<div class="ssa-cards ssa-cards--3">
	<div class="ssa-card"><div class="ssa-card__label"><?php esc_html_e( 'Clicks (30 days)', 'splitshare-affiliates' ); ?></div><div class="ssa-card__value"><?php echo (int) $stats['clicks']; ?></div></div>
	<div class="ssa-card"><div class="ssa-card__label"><?php esc_html_e( 'Orders from links (30 days)', 'splitshare-affiliates' ); ?></div><div class="ssa-card__value"><?php echo (int) $stats['conversions']; ?></div><div class="ssa-card__hint"><?php echo $stats['clicks'] ? esc_html( sprintf( __( '%s%% conversion', 'splitshare-affiliates' ), number_format_i18n( 100 * $stats['conversions'] / $stats['clicks'], 1 ) ) ) : ''; ?></div></div>
	<div class="ssa-card"><div class="ssa-card__label"><?php esc_html_e( 'Link validity', 'splitshare-affiliates' ); ?></div><div class="ssa-card__value"><?php printf( esc_html__( '%d days', 'splitshare-affiliates' ), (int) $settings['cookie_days'] ); ?></div><div class="ssa-card__hint"><?php esc_html_e( 'orders within this window count, even without the code', 'splitshare-affiliates' ); ?></div></div>
</div>

<?php if ( array_sum( $traffic['clicks'] ) > 0 ) : ?>
	<section class="ssa-block">
		<h3><?php esc_html_e( 'Daily traffic', 'splitshare-affiliates' ); ?></h3>
		<?php echo SSA_Charts::columns( $traffic['labels'], array( array( 'name' => __( 'Clicks', 'splitshare-affiliates' ), 'values' => $traffic['clicks'], 'format' => 'int' ), array( 'name' => __( 'Orders', 'splitshare-affiliates' ), 'values' => $traffic['conversions'], 'format' => 'int' ) ), array( 'title' => __( 'Daily clicks and orders from your links', 'splitshare-affiliates' ), 'height' => 200, 'format' => 'int', 'width' => 1000 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</section>
<?php endif; ?>

<section class="ssa-block">
	<h3><?php esc_html_e( 'Link generator', 'splitshare-affiliates' ); ?></h3>
	<div class="ssa-link-generator" data-code="<?php echo esc_attr( $code ); ?>">
		<div class="ssa-form-grid">
			<p class="form-row"><label for="ssa-link-product"><?php esc_html_e( 'Search a product', 'splitshare-affiliates' ); ?></label>
				<select id="ssa-link-product" class="wc-product-search" data-placeholder="<?php esc_attr_e( 'Type to search…', 'splitshare-affiliates' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true"></select></p>
			<p class="form-row"><label for="ssa-link-category"><?php esc_html_e( '…or pick a category', 'splitshare-affiliates' ); ?></label>
				<select id="ssa-link-category"><option value=""><?php esc_html_e( 'Home page', 'splitshare-affiliates' ); ?></option>
				<?php foreach ( $categories as $cat ) : ?><option value="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></option><?php endforeach; ?>
				</select></p>
			<p class="form-row"><label for="ssa-link-url"><?php esc_html_e( '…or paste any page URL', 'splitshare-affiliates' ); ?></label><input type="url" id="ssa-link-url" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>" /></p>
		</div>
		<output id="ssa-link-output"><?php echo esc_html( SSA_Tracking::link( $code ) ); ?></output>
		<p><button type="button" class="button ssa-button ssa-copy" data-copy-target="#ssa-link-output"><?php esc_html_e( 'Copy link', 'splitshare-affiliates' ); ?></button></p>
		<p class="ssa-muted"><?php esc_html_e( 'Tip: add ?ref=CODE to any product URL to make it your link.', 'splitshare-affiliates' ); ?></p>
	</div>
</section>

<?php if ( $boosters ) : ?>
	<section class="ssa-block">
		<h3><?php esc_html_e( 'Products earning extra this week', 'splitshare-affiliates' ); ?></h3>
		<table class="ssa-table">
			<tbody>
			<?php foreach ( $boosters as $product ) : ?>
				<tr><td><?php echo wp_kses_post( $product->get_image( array( 40, 40 ) ) ); ?></td><td><?php echo esc_html( $product->get_name() ); ?><br><small class="ssa-muted"><?php echo wp_kses_post( $product->get_price_html() ); ?></small></td><td class="num"><button type="button" class="ssa-copy" data-copy="<?php echo esc_attr( SSA_Tracking::link( $code, $product->get_permalink() ) ); ?>"><?php esc_html_e( 'Copy link', 'splitshare-affiliates' ); ?></button></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</section>
<?php endif; ?>
