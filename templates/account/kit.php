<?php
/**
 * Panel — İçerik kiti.
 *
 * @var SSA_Partner $partner; array $media, $texts, $settings
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( ! empty( $settings['legal_notice'] ) ) : ?>
	<div class="ssa-legal-box"><?php echo esc_html( $settings['legal_notice'] ); ?></div>
<?php endif; ?>

<?php if ( $texts ) : ?>
	<h3><?php esc_html_e( 'Ready-made captions', 'splitshare-affiliates' ); ?></h3>
	<?php foreach ( $texts as $i => $text ) : ?>
		<?php $text = str_replace( array( '{code}', '{discount}' ), array( $partner->code, wc_format_decimal( $partner->discount_pct, 1 ) . '%' ), $text ); ?>
		<div class="ssa-kit-text" id="ssa-kit-text-<?php echo (int) $i; ?>"><?php echo esc_html( $text ); ?></div>
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
