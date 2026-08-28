<?php
/**
 * Panel — Payımı böl: kaydırıcı + canlı dağılım çubuğu + örnek sepet hesabı.
 *
 * @var SSA_Partner $partner; float $share; int $interval, $next; bool $can; array $groups, $notices, $settings
 */

defined( 'ABSPATH' ) || exit;
$example = 1000;
?>
<?php foreach ( $notices as $n ) : ?>
	<p class="ssa-notice ssa-notice-<?php echo esc_attr( $n['type'] ); ?>"><?php echo esc_html( $n['message'] ); ?></p>
<?php endforeach; ?>

<p><?php printf( esc_html__( 'On every sale, %s%% of the basket is yours to split: keep it as commission, give it to your followers as a discount, or anything in between. The store\'s cost is the same either way.', 'splitshare-affiliates' ), esc_html( wc_format_decimal( $share, 1 ) ) ); ?></p>

<form method="post" class="ssa-split-editor" data-share="<?php echo esc_attr( $share ); ?>" data-example="<?php echo esc_attr( $example ); ?>">
	<?php wp_nonce_field( 'ssa_panel', '_ssa_nonce' ); ?>
	<input type="hidden" name="ssa_action" value="set_split" />
	<div class="ssa-split-editor__bar">
		<?php echo SSA_Charts::split_bar( $partner->commission_pct, $partner->discount_pct, $share, array( 'size' => 'lg', 'labels' => false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<input type="range" name="commission_pct" id="ssa-split-range" min="0" max="<?php echo esc_attr( $share ); ?>" step="0.5" value="<?php echo esc_attr( $partner->commission_pct ); ?>" <?php disabled( ! $can ); ?> aria-label="<?php esc_attr_e( 'My commission', 'splitshare-affiliates' ); ?>" />
	<div class="ssa-split__readout">
		<span><i class="ssa-swatch ssa-swatch--c"></i><?php esc_html_e( 'My commission', 'splitshare-affiliates' ); ?>: <output id="ssa-split-commission"><?php echo esc_html( wc_format_decimal( $partner->commission_pct, 1 ) ); ?></output>%</span>
		<span><i class="ssa-swatch ssa-swatch--d"></i><?php esc_html_e( 'Follower discount', 'splitshare-affiliates' ); ?>: <output id="ssa-split-discount"><?php echo esc_html( wc_format_decimal( $partner->discount_pct, 1 ) ); ?></output>%</span>
	</div>
	<div class="ssa-presets">
		<?php foreach ( array( array( $share, __( 'Link only', 'splitshare-affiliates' ) ), array( round( $share * 2 / 3, 1 ), __( 'Balanced', 'splitshare-affiliates' ) ), array( round( $share / 2, 1 ), __( 'Even', 'splitshare-affiliates' ) ), array( round( $share / 3, 1 ), __( 'Discount heavy', 'splitshare-affiliates' ) ) ) as $preset ) : ?>
			<button type="button" class="ssa-preset" data-value="<?php echo esc_attr( $preset[0] ); ?>" <?php disabled( ! $can ); ?>><?php echo esc_html( $preset[1] ); ?><small><?php echo esc_html( $preset[0] . ' / ' . ( $share - $preset[0] ) ); ?></small></button>
		<?php endforeach; ?>
	</div>

	<div class="ssa-example">
		<div class="ssa-example__title"><?php printf( esc_html__( 'Example: a %s basket', 'splitshare-affiliates' ), wp_kses_post( wc_price( $example ) ) ); ?></div>
		<div class="ssa-example__row"><span><?php esc_html_e( 'Your follower pays', 'splitshare-affiliates' ); ?></span><strong id="ssa-ex-pays"></strong></div>
		<div class="ssa-example__row"><span><?php esc_html_e( 'You earn', 'splitshare-affiliates' ); ?></span><strong id="ssa-ex-earn"></strong></div>
		<div class="ssa-example__row ssa-muted"><span><?php esc_html_e( 'Without your code (link only), you earn', 'splitshare-affiliates' ); ?></span><strong id="ssa-ex-link"></strong></div>
	</div>

	<?php if ( $can ) : ?>
		<p><button class="button ssa-button"><?php esc_html_e( 'Save my split', 'splitshare-affiliates' ); ?></button>
		<?php if ( $interval > 0 ) : ?><span class="ssa-muted"> <?php printf( esc_html__( 'You can change it once every %d days.', 'splitshare-affiliates' ), (int) $interval ); ?></span><?php endif; ?></p>
	<?php else : ?>
		<p class="ssa-notice ssa-notice-info"><?php printf( esc_html__( 'You changed your split recently. Next change possible on %s.', 'splitshare-affiliates' ), esc_html( date_i18n( get_option( 'date_format' ), $next ) ) ); ?></p>
	<?php endif; ?>
</form>

<?php if ( $groups ) : ?>
	<section class="ssa-block">
		<h3><?php esc_html_e( 'Share by product group', 'splitshare-affiliates' ); ?></h3>
		<p class="ssa-muted"><?php esc_html_e( 'Some groups have a smaller share because of their margin; your discount is subtracted from that group\'s share, so your commission there can be lower.', 'splitshare-affiliates' ); ?></p>
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
