<?php
/**
 * Panel — İçerik kiti: kupon seçici + hazır metinler ({code}, {discount}) + görseller.
 *
 * @var SSA_Partner $partner; array $media, $texts, $settings, $coupons
 */

defined( 'ABSPATH' ) || exit;
$first = $coupons ? $coupons[0] : null;
?>
<?php if ( ! empty( $settings['legal_notice'] ) ) : ?>
	<div class="ssa-legal-box"><?php echo esc_html( $settings['legal_notice'] ); ?></div>
<?php endif; ?>

<?php if ( $texts ) : ?>
	<h3><?php esc_html_e( 'Ready-made captions', 'splitshare-affiliates' ); ?></h3>
	<?php if ( $coupons ) : ?>
		<p class="ssa-kit-pick">
			<label for="ssa-kit-coupon"><?php esc_html_e( 'Fill in with coupon:', 'splitshare-affiliates' ); ?></label>
			<select id="ssa-kit-coupon">
				<?php foreach ( $coupons as $c ) : ?>
					<option value="<?php echo (int) $c->id; ?>" data-code="<?php echo esc_attr( $c->code ); ?>" data-discount="<?php echo esc_attr( wc_format_decimal( $c->discount_pct, 1 ) ); ?>"><?php echo esc_html( $c->code . ' · %' . wc_format_decimal( $c->discount_pct, 1 ) . ( $c->name ? ' · ' . SSA_Partner_Coupons::display_name( $c ) : '' ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
	<?php else : ?>
		<p class="ssa-notice ssa-notice-info"><?php esc_html_e( 'Create a coupon first so the captions can include your code.', 'splitshare-affiliates' ); ?> <a href="<?php echo esc_url( SSA_Account::url( 'coupons' ) ); ?>"><?php esc_html_e( 'My coupons', 'splitshare-affiliates' ); ?> →</a></p>
	<?php endif; ?>
	<?php foreach ( $texts as $i => $text ) : ?>
		<?php $filled = str_replace( array( '{code}', '{discount}' ), array( $first ? $first->code : '…', $first ? wc_format_decimal( $first->discount_pct, 1 ) : '…' ), $text ); ?>
		<div class="ssa-kit-text" id="ssa-kit-text-<?php echo (int) $i; ?>" data-template="<?php echo esc_attr( $text ); ?>"><?php echo esc_html( $filled ); ?></div>
		<p><button type="button" class="ssa-copy" data-copy-target="#ssa-kit-text-<?php echo (int) $i; ?>"><?php esc_html_e( 'Copy', 'splitshare-affiliates' ); ?></button></p>
	<?php endforeach; ?>
<?php endif; ?>

<?php if ( $media ) : ?>
	<h3><?php esc_html_e( 'Images & videos', 'splitshare-affiliates' ); ?></h3>
	<div class="ssa-kit-grid">
		<?php foreach ( $media as $att ) : ?>
			<a href="<?php echo esc_url( wp_get_attachment_url( $att->ID ) ); ?>" download>
				<?php if ( wp_attachment_is_image( $att->ID ) ) : ?>
					<?php echo wp_get_attachment_image( $att->ID, 'medium' ); ?>
				<?php else : ?>
					<span class="ssa-kit-file"><?php echo esc_html( get_the_title( $att ) ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php if ( ! $texts && ! $media ) : ?>
	<p class="ssa-muted"><?php esc_html_e( 'The content kit is being prepared.', 'splitshare-affiliates' ); ?></p>
<?php endif; ?>
