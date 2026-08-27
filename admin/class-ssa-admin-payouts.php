<?php
/**
 * Admin → Payouts: partiler, ödendi işaretleme, CSV, elle hakediş oluşturma.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Admin_Payouts {

	public static function handle_actions() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		switch ( $action ) {
			case 'run':
				check_admin_referer( 'ssa_payout_run' );
				$res = SSA_Cron::monthly( true );
				/* translators: 1: created count, 2: carried count */
				SSA_Admin_Menu::redirect_with( 'payouts', sprintf( __( 'Payout run finished: %1$d payouts created, %2$d partners carried over.', 'splitshare-affiliates' ), count( $res['created'] ), count( $res['carried'] ) ) );
				break;
			case 'paid':
				$id = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				check_admin_referer( 'ssa_payout_paid_' . $id );
				SSA_Payouts::mark_paid( $id, isset( $_REQUEST['reference'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['reference'] ) ) : '' );
				SSA_Admin_Menu::redirect_with( 'payouts', __( 'Payout marked as paid and the partner has been notified.', 'splitshare-affiliates' ) );
				break;
			case 'csv':
				check_admin_referer( 'ssa_payout_csv' );
				$ids = isset( $_REQUEST['payout'] ) ? array_map( 'intval', (array) $_REQUEST['payout'] ) : wp_list_pluck( SSA_Payouts::query( array( 'status' => 'open', 'limit' => 0 ) ), 'id' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				header( 'Content-Type: text/csv; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename=payouts-' . gmdate( 'Y-m-d' ) . '.csv' );
				echo "\xEF\xBB\xBF" . SSA_Payouts::csv( $ids ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				exit;
		}
	}

	public static function render() {
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'open'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$items  = SSA_Payouts::query( array( 'status' => 'all' === $status ? '' : $status, 'limit' => 200 ) );
		$next   = SSA_Payouts::next_payout_date();
		echo '<p>';
		echo '<a class="button button-primary ssa-confirm" data-confirm="' . esc_attr__( 'Create payouts now for all approved, unpaid commissions?', 'splitshare-affiliates' ) . '" href="' . esc_url( wp_nonce_url( SSA_Admin_Menu::url( 'payouts', array( 'action' => 'run' ) ), 'ssa_payout_run' ) ) . '">' . esc_html__( 'Create payouts now', 'splitshare-affiliates' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( wp_nonce_url( SSA_Admin_Menu::url( 'payouts', array( 'action' => 'csv' ) ), 'ssa_payout_csv' ) ) . '">' . esc_html__( 'Download open payouts (CSV)', 'splitshare-affiliates' ) . '</a> ';
		/* translators: %s: date */
		echo '<span class="ssa-muted">' . esc_html( sprintf( __( 'Next scheduled run: %s', 'splitshare-affiliates' ), date_i18n( get_option( 'date_format' ), $next ) ) ) . '</span></p>';

		echo '<ul class="subsubsub">';
		foreach ( array( 'open' => __( 'Open', 'splitshare-affiliates' ), 'paid' => __( 'Paid', 'splitshare-affiliates' ), 'all' => __( 'All', 'splitshare-affiliates' ) ) as $k => $label ) {
			echo '<li><a href="' . esc_url( SSA_Admin_Menu::url( 'payouts', array( 'status' => $k ) ) ) . '"' . ( $status === $k ? ' class="current"' : '' ) . '>' . esc_html( $label ) . '</a> | </li>';
		}
		echo '</ul><br class="clear">';

		if ( ! $items ) {
			echo '<p>' . esc_html__( 'No payouts.', 'splitshare-affiliates' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Period', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Partner', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Bank details', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Amount', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Status', 'splitshare-affiliates' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $items as $p ) {
			$partner = SSA_Partners::get( $p->partner_id );
			$details = $partner ? $partner->payout_details : array();
			echo '<tr><td>' . esc_html( $p->period ) . '</td><td>' . ( $partner ? SSA_Admin_Menu::partner_link( $partner ) : '#' . (int) $p->partner_id ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td>' . esc_html( isset( $details['holder'] ) ? $details['holder'] : '' ) . '<br><code>' . esc_html( isset( $details['iban'] ) ? $details['iban'] : '—' ) . '</code></td>';
			echo '<td><strong>' . wp_kses_post( wc_price( $p->amount ) ) . '</strong></td><td>' . SSA_Admin_Menu::badge( $p->status ) . ( $p->paid_at ? '<br><small>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $p->paid_at ) ) . ( $p->reference ? ' · ' . $p->reference : '' ) ) . '</small>' : '' ) . '</td><td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( 'open' === $p->status ) {
				echo '<form method="post" action="' . esc_url( SSA_Admin_Menu::url( 'payouts' ) ) . '" style="display:flex;gap:6px">';
				wp_nonce_field( 'ssa_payout_paid_' . $p->id );
				echo '<input type="hidden" name="action" value="paid" /><input type="hidden" name="id" value="' . (int) $p->id . '" />';
				echo '<input type="text" name="reference" placeholder="' . esc_attr__( 'Bank reference', 'splitshare-affiliates' ) . '" /><button class="button button-primary">' . esc_html__( 'Mark paid', 'splitshare-affiliates' ) . '</button></form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
