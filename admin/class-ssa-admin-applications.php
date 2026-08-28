<?php
/**
 * Admin → Applications: kart grid, onayla/reddet.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Admin_Applications {

	public static function handle_actions() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id     = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $id || ! in_array( $action, array( 'approve', 'reject' ), true ) ) {
			return;
		}
		check_admin_referer( 'ssa_application_' . $action . '_' . $id );
		$note = isset( $_REQUEST['note'] ) ? sanitize_textarea_field( wp_unslash( $_REQUEST['note'] ) ) : '';
		if ( 'approve' === $action ) {
			$opts = array( 'note' => $note );
			if ( ! empty( $_REQUEST['code'] ) ) {
				$opts['code'] = sanitize_text_field( wp_unslash( $_REQUEST['code'] ) );
			}
			$r = SSA_Partners::approve( $id, $opts );
			if ( is_wp_error( $r ) ) {
				SSA_Admin_Menu::redirect_with( 'applications', $r->get_error_message(), 'error' );
			}
			$p = SSA_Partners::get( $id );
			/* translators: 1: name, 2: code */
			SSA_Admin_Menu::redirect_with( 'applications', sprintf( __( '%1$s approved. Code: %2$s', 'splitshare-affiliates' ), $p->display_name(), $p->code ) );
		}
		SSA_Partners::reject( $id, $note );
		SSA_Admin_Menu::redirect_with( 'applications', __( 'Application declined.', 'splitshare-affiliates' ) );
	}

	public static function render() {
		$items = SSA_Partners::query( array( 'status' => 'pending', 'limit' => 200, 'order' => 'ASC' ) );
		if ( ! $items ) {
			echo SSA_Admin_UI::empty_state( __( 'No pending applications', 'splitshare-affiliates' ), __( 'New applications from the application page land here for review.', 'splitshare-affiliates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		$social = array( 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube' );
		echo '<div class="ssa-appgrid">';
		foreach ( $items as $p ) {
			$a = $p->application;
			echo '<article class="ssa-appcard">';
			echo '<div class="ssa-appcard__head">' . SSA_Admin_UI::avatar( $p ) . '<div><strong>' . esc_html( $p->display_name() ) . '</strong><small>' . esc_html( $p->email() ) . ( ! empty( $a['phone'] ) ? ' · ' . esc_html( $a['phone'] ) : '' ) . '</small></div><span class="ssa-muted" style="margin-left:auto;font-size:12px">' . esc_html( human_time_diff( strtotime( $p->applied_at ), current_time( 'timestamp' ) ) ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<div class="ssa-appcard__social">';
			foreach ( $social as $key => $label ) {
				if ( ! empty( $a[ $key ] ) ) {
					echo '<a href="' . esc_url( $a[ $key ] ) . '" target="_blank" rel="noopener">' . SSA_Admin_UI::icon( 'external' ) . esc_html( $label ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
			echo '</div>';
			echo '<dl class="ssa-appcard__meta">';
			foreach ( array( 'followers' => __( 'Followers', 'splitshare-affiliates' ), 'city' => __( 'City', 'splitshare-affiliates' ), 'content_type' => __( 'Creates', 'splitshare-affiliates' ) ) as $key => $label ) {
				if ( ! empty( $a[ $key ] ) ) {
					echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( 'followers' === $key ? number_format_i18n( (float) $a[ $key ] ) : $a[ $key ] ) . '</dd></div>';
				}
			}
			echo '</dl>';
			if ( ! empty( $a['note'] ) ) {
				echo '<div class="ssa-appcard__note">' . esc_html( $a['note'] ) . '</div>';
			}
			echo '<form method="post" action="' . esc_url( SSA_Admin_Menu::url( 'applications' ) ) . '">';
			echo '<input type="hidden" name="id" value="' . (int) $p->id . '" />';
			echo '<input type="text" name="code" placeholder="' . esc_attr__( 'Code (optional, auto)', 'splitshare-affiliates' ) . '" pattern="[A-Za-z0-9]{4,12}" />';
			echo '<span class="ssa-muted" style="font-size:12px;align-self:center">' . esc_html( sprintf( __( 'Default split: %1$s%% + %2$s%%', 'splitshare-affiliates' ), SSA_Settings::get( 'default_commission_pct' ), (float) SSA_Settings::get( 'default_share' ) - (float) SSA_Settings::get( 'default_commission_pct' ) ) ) . '</span>';
			echo '<textarea name="note" placeholder="' . esc_attr__( 'Note (sent on decline)', 'splitshare-affiliates' ) . '"></textarea>';
			echo '<div class="ssa-actions"><button class="button button-primary" name="action" value="approve" formaction="' . esc_url( wp_nonce_url( SSA_Admin_Menu::url( 'applications' ), 'ssa_application_approve_' . $p->id ) ) . '">' . esc_html__( 'Approve', 'splitshare-affiliates' ) . '</button>';
			echo '<button class="button ssa-confirm" data-confirm="' . esc_attr__( 'Decline this application?', 'splitshare-affiliates' ) . '" name="action" value="reject" formaction="' . esc_url( wp_nonce_url( SSA_Admin_Menu::url( 'applications' ), 'ssa_application_reject_' . $p->id ) ) . '">' . esc_html__( 'Decline', 'splitshare-affiliates' ) . '</button></div>';
			echo '</form></article>';
		}
		echo '</div>';
	}
}
