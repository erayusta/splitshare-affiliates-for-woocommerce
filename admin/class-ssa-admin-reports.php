<?php
/**
 * Admin → Reports.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Admin_Reports {

	public static function render() {
		$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : gmdate( 'Y-m-01', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$to   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : gmdate( 'Y-m-d', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<form method="get" class="ssa-filters"><input type="hidden" name="page" value="' . esc_attr( SSA_Admin_Menu::SLUG ) . '" /><input type="hidden" name="tab" value="reports" />';
		echo '<p><input type="date" name="from" value="' . esc_attr( $from ) . '" /> – <input type="date" name="to" value="' . esc_attr( $to ) . '" /> <button class="button">' . esc_html__( 'Apply', 'splitshare-affiliates' ) . '</button></p></form>';

		$new  = SSA_Reports::new_customer_rate( $from, $to );
		$cost = SSA_Reports::effective_cost_rate( $from, $to );
		echo '<div class="ssa-stat-cards">';
		self::card( __( 'New customer rate', 'splitshare-affiliates' ), $new['rate'] . '%', sprintf( '%d / %d', $new['new'], $new['total'] ) . ' · ' . __( 'target ≥ 60%', 'splitshare-affiliates' ), $new['rate'] >= 60 );
		self::card( __( 'Effective cost rate', 'splitshare-affiliates' ), $cost['rate'] . '%', __( '(discount + commission) / attributed revenue · target ≤ share', 'splitshare-affiliates' ), $cost['rate'] <= (float) SSA_Settings::get( 'default_share' ) );
		self::card( __( 'Attributed revenue', 'splitshare-affiliates' ), wc_price( $cost['revenue'] ), '' );
		self::card( __( 'Commission', 'splitshare-affiliates' ), wc_price( $cost['commission'] ), sprintf( __( 'Discounts: %s', 'splitshare-affiliates' ), wp_strip_all_tags( wc_price( $cost['discount'] ) ) ) );
		echo '</div>';

		echo '<h2>' . esc_html__( 'Split distribution', 'splitshare-affiliates' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Split (commission / discount)', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Partners', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Revenue', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Commission', 'splitshare-affiliates' ) . '</th></tr></thead><tbody>';
		foreach ( SSA_Reports::split_distribution( $from, $to ) as $r ) {
			echo '<tr><td>' . esc_html( $r->commission_pct . '% / ' . $r->discount_pct . '%' ) . '</td><td>' . (int) $r->partners . '</td><td>' . wp_kses_post( wc_price( $r->revenue ) ) . '</td><td>' . wp_kses_post( wc_price( $r->commission ) ) . '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Top partners', 'splitshare-affiliates' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Partner', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Orders', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Revenue', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Commission', 'splitshare-affiliates' ) . '</th></tr></thead><tbody>';
		foreach ( SSA_Reports::leaderboard( $from, $to ) as $r ) {
			$p = SSA_Partners::get( $r->id );
			echo '<tr><td>' . ( $p ? SSA_Admin_Menu::partner_link( $p ) : '' ) . ' <code>' . esc_html( $r->code ) . '</code></td><td>' . (int) $r->orders . '</td><td>' . wp_kses_post( wc_price( $r->revenue ) ) . '</td><td>' . wp_kses_post( wc_price( $r->commission ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Share groups', 'splitshare-affiliates' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Share', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Line items', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Commission', 'splitshare-affiliates' ) . '</th></tr></thead><tbody>';
		foreach ( SSA_Reports::group_revenue( $from, $to ) as $g ) {
			echo '<tr><td>' . esc_html( $g['share'] ) . '%</td><td>' . (int) $g['items'] . '</td><td>' . wp_kses_post( wc_price( $g['commission'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';

		$an = SSA_Reports::code_anomalies( 30 );
		echo '<h2>' . esc_html__( 'Code usage (last 30 days)', 'splitshare-affiliates' ) . '</h2><p class="ssa-muted">' . esc_html( sprintf( __( 'Average %s orders per code. Codes with 3× the average and a low new-customer rate may have leaked to coupon sites.', 'splitshare-affiliates' ), $an['average'] ) ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Code', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Orders', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'New customer rate', 'splitshare-affiliates' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $an['rows'] as $r ) {
			echo '<tr><td><code>' . esc_html( $r->code ) . '</code></td><td>' . (int) $r->orders . '</td><td>' . esc_html( $r->new_rate ) . '%</td><td>' . ( $r->flag ? '<span class="ssa-flag">' . esc_html__( 'Suspicious', 'splitshare-affiliates' ) . '</span> <a href="' . esc_url( SSA_Admin_Partners::action_url( 'rotate', $r->partner_id ) ) . '">' . esc_html__( 'Rotate code', 'splitshare-affiliates' ) . '</a>' : '' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function card( $label, $value, $hint, $ok = null ) {
		echo '<div class="ssa-stat-card"><span>' . esc_html( $label ) . '</span><strong' . ( null === $ok ? '' : ( $ok ? ' style="color:#0a5c2b"' : ' style="color:#b32d2e"' ) ) . '>' . wp_kses_post( $value ) . '</strong><small class="ssa-muted">' . esc_html( $hint ) . '</small></div>';
	}
}
