<?php
/**
 * Admin → Overview: KPI'lar (önceki dönem kıyaslı), aylık trend, dağılım donut'u, ilgi bekleyenler, en iyi ortaklar.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Admin_Overview {

	public static function render() {
		$r    = SSA_Admin_UI::range();
		$cur  = SSA_Reports::summary( $r['from'], $r['to'] );
		$prev = SSA_Reports::summary( $r['prev_from'], $r['prev_to'] );
		$att  = SSA_Reports::attention();
		$cnt  = SSA_Partners::counts_by_status();
		$vs   = __( 'vs previous period', 'splitshare-affiliates' );

		echo SSA_Admin_UI::range_bar( 'overview', $r ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo '<div class="ssa-kpis">';
		echo SSA_Admin_UI::kpi( array( 'icon' => SSA_Admin_UI::icon( 'revenue' ), 'label' => __( 'Attributed revenue', 'splitshare-affiliates' ), 'value' => wc_price( $cur['revenue'] ), 'delta' => SSA_Admin_UI::delta( $cur['revenue'], $prev['revenue'] ), 'delta_label' => $vs, 'hint' => sprintf( _n( '%d order', '%d orders', $cur['orders'], 'splitshare-affiliates' ), $cur['orders'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo SSA_Admin_UI::kpi( array( 'icon' => SSA_Admin_UI::icon( 'wallet' ), 'label' => __( 'Commission', 'splitshare-affiliates' ), 'value' => wc_price( $cur['commission'] ), 'delta' => SSA_Admin_UI::delta( $cur['commission'], $prev['commission'] ), 'delta_label' => $vs, 'good_up' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo SSA_Admin_UI::kpi( array( 'icon' => SSA_Admin_UI::icon( 'new' ), 'label' => __( 'New customer rate', 'splitshare-affiliates' ), 'value' => number_format_i18n( $cur['new_rate'], 1 ) . '%', 'delta' => SSA_Admin_UI::delta( $cur['new_rate'], $prev['new_rate'] ), 'delta_label' => $vs, 'hint' => __( 'target ≥ 60%', 'splitshare-affiliates' ), 'tone' => $cur['new_rate'] >= 60 ? 'good' : ( $cur['orders'] ? 'warn' : '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo SSA_Admin_UI::kpi( array( 'icon' => SSA_Admin_UI::icon( 'percent' ), 'label' => __( 'Effective cost rate', 'splitshare-affiliates' ), 'value' => number_format_i18n( $cur['cost_rate'], 1 ) . '%', 'delta' => SSA_Admin_UI::delta( $cur['cost_rate'], $prev['cost_rate'] ), 'delta_label' => $vs, 'good_up' => false, 'tone' => $cur['cost_rate'] > (float) SSA_Settings::get( 'default_share' ) ? 'bad' : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo SSA_Admin_UI::kpi( array( 'icon' => SSA_Admin_UI::icon( 'users' ), 'label' => __( 'Active partners', 'splitshare-affiliates' ), 'value' => number_format_i18n( $cnt['active'] ), 'hint' => sprintf( __( '%d paused', 'splitshare-affiliates' ), $cnt['paused'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo SSA_Admin_UI::kpi( array( 'icon' => SSA_Admin_UI::icon( 'clock' ), 'label' => __( 'Approving within 7 days', 'splitshare-affiliates' ), 'value' => wc_price( $att['approving_7d'] ), 'hint' => __( 'pending commissions past their hold period soon', 'splitshare-affiliates' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';

		echo '<div class="ssa-grid ssa-grid--2-1">';
		// Aylık trend
		$m = SSA_Reports::monthly( 12 );
		echo SSA_Admin_UI::card_open( __( 'Revenue & commission, last 12 months', 'splitshare-affiliates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( array_sum( $m['revenue'] ) > 0 ) {
			echo SSA_Charts::columns( $m['labels'], array( array( 'name' => __( 'Revenue', 'splitshare-affiliates' ), 'values' => $m['revenue'] ), array( 'name' => __( 'Commission', 'splitshare-affiliates' ), 'values' => $m['commission'] ) ), array( 'title' => __( 'Monthly attributed revenue and commission', 'splitshare-affiliates' ), 'height' => 260, 'width' => 900 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo SSA_Admin_UI::empty_state( __( 'No attributed sales yet', 'splitshare-affiliates' ), __( 'Once partners share their codes and links, monthly revenue and commission show up here.', 'splitshare-affiliates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo SSA_Admin_UI::card_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// İlgi bekleyenler
		echo SSA_Admin_UI::card_open( __( 'Needs attention', 'splitshare-affiliates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$items = array(
			array( $att['applications'], __( 'pending applications', 'splitshare-affiliates' ), SSA_Admin_Menu::url( 'applications' ), 'users' ),
			array( $att['open_payouts'], sprintf( __( 'open payouts (%s)', 'splitshare-affiliates' ), wp_strip_all_tags( wc_price( $att['open_amount'] ) ) ), SSA_Admin_Menu::url( 'payouts' ), 'wallet' ),
			array( $att['suspicious'], __( 'suspicious codes', 'splitshare-affiliates' ), SSA_Admin_Menu::url( 'reports' ) . '#codes', 'alert' ),
		);
		echo '<ul class="ssa-attention">';
		foreach ( $items as $it ) {
			echo '<li class="' . ( $it[0] ? 'is-hot' : '' ) . '"><a href="' . esc_url( $it[2] ) . '">' . SSA_Admin_UI::icon( $it[3] ) . '<strong>' . (int) $it[0] . '</strong> ' . esc_html( $it[1] ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</ul>';
		$b = SSA_Partner_Coupons::discount_buckets();
		if ( array_sum( $b ) > 0 ) {
			echo '<h3 class="ssa-subhead">' . esc_html__( 'Live coupons by discount', 'splitshare-affiliates' ) . '</h3>';
			echo SSA_Charts::donut( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				array( 'label' => __( 'Up to 5%', 'splitshare-affiliates' ), 'value' => $b['low'] ),
				array( 'label' => __( '5–10%', 'splitshare-affiliates' ), 'value' => $b['mid'] ),
				array( 'label' => __( 'Over 10%', 'splitshare-affiliates' ), 'value' => $b['high'] ),
			), array( 'size' => 150, 'center' => number_format_i18n( array_sum( $b ) ), 'center_label' => __( 'coupons', 'splitshare-affiliates' ), 'title' => __( 'Coupon discounts', 'splitshare-affiliates' ) ) );
		}
		echo SSA_Admin_UI::card_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';

		// En iyi ortaklar + son komisyonlar
		echo '<div class="ssa-grid ssa-grid--1-1">';
		echo SSA_Admin_UI::card_open( __( 'Top partners', 'splitshare-affiliates' ), '<a class="ssa-link" href="' . esc_url( SSA_Admin_Menu::url( 'partners' ) ) . '">' . esc_html__( 'All partners', 'splitshare-affiliates' ) . ' →</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$top = SSA_Reports::leaderboard( $r['from'], $r['to'], 6 );
		if ( $top ) {
			echo '<table class="ssa-table"><thead><tr><th>' . esc_html__( 'Partner', 'splitshare-affiliates' ) . '</th><th class="num">' . esc_html__( 'Orders', 'splitshare-affiliates' ) . '</th><th class="num">' . esc_html__( 'Revenue', 'splitshare-affiliates' ) . '</th><th class="num">' . esc_html__( 'Commission', 'splitshare-affiliates' ) . '</th></tr></thead><tbody>';
			foreach ( $top as $row ) {
				$p = SSA_Partners::get( $row->id );
				echo '<tr><td>' . ( $p ? SSA_Admin_Menu::partner_link( $p ) : '' ) . '</td><td class="num">' . (int) $row->orders . '</td><td class="num">' . wp_kses_post( wc_price( $row->revenue ) ) . '</td><td class="num"><strong>' . wp_kses_post( wc_price( $row->commission ) ) . '</strong></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</tbody></table>';
		} else {
			echo SSA_Admin_UI::empty_state( __( 'No sales in this period', 'splitshare-affiliates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo SSA_Admin_UI::card_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo SSA_Admin_UI::card_open( __( 'Recent commissions', 'splitshare-affiliates' ), '<a class="ssa-link" href="' . esc_url( SSA_Admin_Menu::url( 'commissions' ) ) . '">' . esc_html__( 'All commissions', 'splitshare-affiliates' ) . ' →</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$recent = SSA_Commissions::query( array( 'limit' => 8 ) );
		if ( $recent ) {
			echo '<table class="ssa-table"><thead><tr><th>' . esc_html__( 'Order', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Partner', 'splitshare-affiliates' ) . '</th><th class="num">' . esc_html__( 'Commission', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Status', 'splitshare-affiliates' ) . '</th></tr></thead><tbody>';
			foreach ( $recent as $c ) {
				$p     = SSA_Partners::get( $c->partner_id );
				$order = wc_get_order( $c->order_id );
				echo '<tr><td>' . ( $order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . (int) $c->order_id . '</a>' : '#' . (int) $c->order_id ) . '<small>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $c->created_at ) ) ) . '</small></td><td>' . ( $p ? esc_html( $p->display_name() ) : '' ) . '</td><td class="num">' . wp_kses_post( wc_price( $c->amount ) ) . '</td><td>' . SSA_Admin_UI::badge( $c->status ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</tbody></table>';
		} else {
			echo SSA_Admin_UI::empty_state( __( 'No commissions yet', 'splitshare-affiliates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo SSA_Admin_UI::card_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}
}
