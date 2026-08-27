<?php
/**
 * Başvuru formu şablonu. Override: yourtheme/woocommerce/splitshare-affiliates/apply-form.php
 *
 * @var array $fields, $errors, $values; bool $done; SSA_Partner|null $partner; string $legal, $program
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ssa-apply">
	<?php if ( $partner ) : ?>
		<?php if ( 'pending' === $partner->status ) : ?>
			<p class="ssa-notice ssa-notice-info"><?php esc_html_e( 'Your application is being reviewed. We will e-mail you as soon as it is approved.', 'splitshare-affiliates' ); ?></p>
		<?php elseif ( $partner->can_access_panel() ) : ?>
			<p class="ssa-notice ssa-notice-success"><?php esc_html_e( 'You are already a partner.', 'splitshare-affiliates' ); ?> <a href="<?php echo esc_url( wc_get_account_endpoint_url( SSA_Settings::endpoint( 'dashboard' ) ) ); ?>"><?php esc_html_e( 'Open your partner panel', 'splitshare-affiliates' ); ?></a></p>
		<?php else : ?>
			<p class="ssa-notice ssa-notice-info"><?php esc_html_e( 'Your previous application was not approved. Please contact us if you think this is a mistake.', 'splitshare-affiliates' ); ?></p>
		<?php endif; ?>
	<?php elseif ( $done ) : ?>
		<p class="ssa-notice ssa-notice-success"><?php esc_html_e( 'Thank you! Your application has been received. We will get back to you within a few days.', 'splitshare-affiliates' ); ?></p>
	<?php else : ?>
		<?php foreach ( $errors as $error ) : ?>
			<p class="ssa-notice ssa-notice-error"><?php echo esc_html( $error ); ?></p>
		<?php endforeach; ?>
		<form method="post" class="ssa-apply-form">
			<?php wp_nonce_field( SSA_Application::NONCE, '_ssa_nonce' ); ?>
			<input type="text" name="ssa_website" value="" class="ssa-hp" tabindex="-1" autocomplete="off" aria-hidden="true" />
			<?php foreach ( $fields as $key => $f ) : ?>
				<p class="ssa-field ssa-field-<?php echo esc_attr( $key ); ?>">
					<label for="ssa-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $f['label'] ); ?><?php echo $f['required'] ? ' <span class="required">*</span>' : ''; ?></label>
					<?php if ( 'textarea' === $f['type'] ) : ?>
						<textarea id="ssa-<?php echo esc_attr( $key ); ?>" name="ssa_<?php echo esc_attr( $key ); ?>" rows="4"><?php echo esc_textarea( $values[ $key ] ); ?></textarea>
					<?php else : ?>
						<input id="ssa-<?php echo esc_attr( $key ); ?>" type="<?php echo esc_attr( $f['type'] ); ?>" name="ssa_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $values[ $key ] ); ?>" placeholder="<?php echo esc_attr( isset( $f['placeholder'] ) ? $f['placeholder'] : '' ); ?>" <?php echo $f['required'] ? 'required' : ''; ?> />
					<?php endif; ?>
				</p>
			<?php endforeach; ?>
			<?php if ( $legal ) : ?>
				<p class="ssa-legal"><?php echo esc_html( $legal ); ?></p>
			<?php endif; ?>
			<p class="ssa-field ssa-field-legal">
				<label><input type="checkbox" name="ssa_legal_ok" value="1" required /> <?php
					/* translators: %s: program name */
					echo esc_html( sprintf( __( 'I accept the %s terms and will label sponsored posts as required by law.', 'splitshare-affiliates' ), $program ) );
				?></label>
			</p>
			<p><button type="submit" name="ssa_apply_submit" value="1" class="button ssa-button"><?php esc_html_e( 'Apply', 'splitshare-affiliates' ); ?></button></p>
		</form>
	<?php endif; ?>
</div>
