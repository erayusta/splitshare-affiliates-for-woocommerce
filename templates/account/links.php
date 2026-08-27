<?php
/**
 * Panel — Linkler: link üreteci, hızlandırıcı ürünler, tıklama istatistiği.
 *
 * @var SSA_Partner $partner; array $stats, $boosters, $categories, $settings
 */

defined( 'ABSPATH' ) || exit;
$code = $partner->code;
?>
<div class="ssa-cards">
	<div class="ssa-card"><div class="ssa-card__label"><?php esc_html_e( 'Clicks (30 days)', 'splitshare-affiliates' ); ?></div><div class="ssa-card__value"><?php echo (int) $stats['clicks']; ?></div></div>
	<div class="ssa-card"><div class="ssa-card__label"><?php esc_html_e( 'Orders from links (30 days)', 'splitshare-affiliates' ); ?></div><div class="ssa-card__value"><?php echo (int) $stats['conversions']; ?></div></div>
	<div class="ssa-card"><div class="ssa-card__label"><?php esc_html_e( 'Link validity', 'splitshare-affiliates' ); ?></div><div class="ssa-card__value"><?php printf( esc_html__( '%d days', 'splitshare-affiliates' ), (int) $settings['cookie_days'] ); ?></div><div class="ssa-card__hint"><?php esc_html_e( 'orders within this window count, even without the code', 'splitshare-affiliates' ); ?></div></div>
</div>

<h3><?php esc_html_e( 'Link generator', 'splitshare-affiliates' ); ?></h3>
<div class="ssa-link-generator" data-code="<?php echo esc_attr( $code ); ?>">
	<p><label for="ssa-link-url"><?php esc_html_e( 'Any page on the store', 'splitshare-affiliates' ); ?></label><input type="url" id="ssa-link-url" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>" /></p>
	<p><label for="ssa-link-category"><?php esc_html_e( '…or pick a category', 'splitshare-affiliates' ); ?></label>
		<select id="ssa-link-category"><option value=""><?php esc_html_e( 'Home page', 'splitshare-affiliates' ); ?></option>
		<?php foreach ( $categories as $cat ) : ?><option value="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></option><?php endforeach; ?>
		</select></p>
	<output id="ssa-link-output"><?php echo esc_html( SSA_Tracking::link( $code ) ); ?></output>
	<p><button type="button" class="button ssa-button ssa-copy" data-copy-target="#ssa-link-output"><?php esc_html_e( 'Copy link', 'splitshare-affiliates' ); ?></button></p>
	<p class="ssa-muted"><?php esc_html_e( 'Tip: add ?ref=CODE to any product URL to make it your link.', 'splitshare-affiliates' ); ?></p>
</div>

<?php if ( $boosters ) : ?>
	<h3><?php esc_html_e( 'Products earning extra this week', 'splitshare-affiliates' ); ?></h3>
	<table class="ssa-table">
		<tbody>
		<?php foreach ( $boosters as $product ) : ?>
			<tr><td><?php echo esc_html( $product->get_name() ); ?></td><td><?php echo wp_kses_post( $product->get_price_html() ); ?></td><td><button type="button" class="ssa-copy" data-copy="<?php echo esc_attr( SSA_Tracking::link( $code, $product->get_permalink() ) ); ?>"><?php esc_html_e( 'Copy link', 'splitshare-affiliates' ); ?></button></td></tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
