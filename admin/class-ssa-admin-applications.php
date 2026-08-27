<?php
/**
 * Admin → Applications: bekleyen başvurular, onayla/reddet.
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
			echo '<p>' . esc_html__( 'No pending applications.', 'splitshare-affiliates' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped ssa-applications"><thead><tr><th>' . esc_html__( 'Applicant', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Details', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Applied', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Decision', 'splitshare-affiliates' ) . '</th></tr></thead><tbody>';
		foreach ( $items as $p ) {
			echo '<tr><td><strong>' . esc_html( $p->display_name() ) . '</strong><br><small>' . esc_html( $p->email() ) . '</small></td><td>';
			foreach ( $p->application as $k => $v ) {
				if ( '' !== (string) $v && ! in_array( $k, array( 'name', 'email' ), true ) ) {
					$val = filter_var( $v, FILTER_VALIDATE_URL ) ? '<a href="' . esc_url( $v ) . '" target="_blank" rel="noopener">' . esc_html( $v ) . '</a>' : esc_html( $v );
					echo '<strong>' . esc_html( ucfirst( str_replace( '_', ' ', $k ) ) ) . ':</strong> ' . $val . '<br>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
			echo '</td><td>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $p->applied_at ) ) ) . '</td><td>';
			echo '<form method="post" action="' . esc_url( SSA_Admin_Menu::url( 'applications' ) ) . '" class="ssa-decision">';
			echo '<input type="hidden" name="id" value="' . (int) $p->id . '" />';
			echo '<p><input type="text" name="code" placeholder="' . esc_attr__( 'Code (optional, auto)', 'splitshare-affiliates' ) . '" pattern="[A-Za-z0-9]{4,12}" /></p>';
			echo '<p><textarea name="note" rows="2" placeholder="' . esc_attr__( 'Note (sent on decline)', 'splitshare-affiliates' ) . '"></textarea></p>';
			echo '<p><button class="button button-primary" name="action" value="approve" formaction="' . esc_url( wp_nonce_url( SSA_Admin_Menu::url( 'applications' ), 'ssa_application_approve_' . $p->id ) ) . '">' . esc_html__( 'Approve', 'splitshare-affiliates' ) . '</button> ';
			echo '<button class="button ssa-confirm" data-confirm="' . esc_attr__( 'Decline this application?', 'splitshare-affiliates' ) . '" name="action" value="reject" formaction="' . esc_url( wp_nonce_url( SSA_Admin_Menu::url( 'applications' ), 'ssa_application_reject_' . $p->id ) ) . '">' . esc_html__( 'Decline', 'splitshare-affiliates' ) . '</button></p>';
			echo '</form></td></tr>';
		}
		echo '</tbody></table>';
	}
}
