<?php
/**
 * Admin → Reports: tarih ön ayarları, KPI + delta, aylık trend, yeni/tekrar müşteri, dağılım, gruplar, kod kullanımı.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Admin_Reports {

	public static function render() {
		$r    = SSA_Admin_UI::range();
		$cur  = SSA_Reports::summary( $r['from'], $r['to'] );
		$prev = SSA_Reports::summary( $r['prev_from'], $r['prev_to'] );
		$vs   = __( 'vs previous period', 'splitshare-affiliates' );
		$share = (float) SSA_Settings::get( 'default_share' );

		echo SSA_Admin_UI::range_bar( 'reports', $r ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="ssa-kpis">';
		echo SSA_Admin_UI::kpi( array( 'label' => __( 'Attributed revenue', 'splitshare-affiliates' ), 'value' => wc_price( $cur['revenue'] ), 'delta' => SSA_Admin_UI::delta( $cur['revenue'], $prev['revenue'] ), 'delta_label' => $vs ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo SSA_Admin_UI::kpi( array( 'label' => __( 'Orders', 'splitshare-affiliates' ), 'value' => number_format_i18n( $cur['orders'] ), 'delta' => SSA_Admin_UI::delta( $cur['orders'], $prev['orders'] ), 'delta_label' => $vs ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo SSA_Admin_UI::kpi( array( 'label' => __( 'Commission', 'splitshare-affiliates' ), 'value' => wc_price( $cur['commission'] ), 'delta' => SSA_Admin_UI::delta( $cur['commission'], $prev['commission'] ), 'delta_label' => $vs ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo SSA_Admin_UI::kpi( array( 'label' => __( 'Discounts given', 'splitshare-affiliates' ), 'value' => wc_price( $cur['discount'] ), 'delta' => SSA_Admin_UI::delta( $cur['discount'], $prev['discount'] ), 'delta_label' => $vs, 'good_up' => false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo SSA_Admin_UI::kpi( array( 'label' => __( 'New customer rate', 'splitshare-affiliates' ), 'value' => number_format_i18n( $cur['new_rate'], 1 ) . '%', 'delta' => SSA_Admin_UI::delta( $cur['new_rate'], $prev['new_rate'] ), 'delta_label' => $vs, 'tone' => $cur['new_rate'] >= 60 ? 'good' : ( $cur['orders'] ? 'warn' : '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo SSA_Admin_UI::kpi( array( 'label' => __( 'Effective cost rate', 'splitshare-affiliates' ), 'value' => number_format_i18n( $cur['cost_rate'], 1 ) . '%', 'delta' => SSA_Admin_UI::delta( $cur['cost_rate'], $prev['cost_rate'] ), 'delta_label' => $vs, 'good_up' => false, 'tone' => $cur['cost_rate'] > $share ? 'bad' : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';

		$m = SSA_Reports::monthly( 12 );
		echo '<div class="ssa-grid ssa-grid--1-1">';
		echo SSA_Admin_UI::card_open( __( 'Revenue & commission by month', 'splitshare-affiliates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo SSA_Charts::columns( $m['labels'], array( array( 'name' => __( 'Revenue', 'splitshare-affiliates' ), 'values' => $m['revenue'] ), array( 'name' => __( 'Commission', 'splitshare-affiliates' ), 'values' => $m['commission'] ) ), array( 'title' => __( 'Monthly revenue and commission', 'splitshare-affiliates' ), 'width' => 640 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo SSA_Admin_UI::card_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo SSA_Admin_UI::card_open( __( 'New vs. returning customers by month', 'splitshare-affiliates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo SSA_Charts::columns( $m['labels'], array( array( 'name' => __( 'New customers', 'splitshare-affiliates' ), 'values' => $m['new'], 'format' => 'int' ), array( 'name' => __( 'Returning', 'splitshare-affiliates' ), 'values' => $m['returning'], 'format' => 'int' ) ), array( 'title' => __( 'Orders by customer type', 'splitshare-affiliates' ), 'format' => 'int', 'width' => 640 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo SSA_Admin_UI::card_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';

		echo '<div class="ssa-grid ssa-grid--1-2">';
		$b = SSA_Reports::split_buckets();
		echo SSA_Admin_UI::card_open( __( 'How partners split their share', 'splitshare-affiliates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( array_sum( $b ) > 0 ) {
			echo SSA_Charts::donut( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				array( 'label' => __( 'Commission heavy', 'splitshare-affiliates' ), 'value' => $b['commission'] ),
				array( 'label' => __( 'Balanced', 'splitshare-affiliates' ), 'value' => $b['balanced'] ),
				array( 'label' => __( 'Discount heavy', 'splitshare-affiliates' ), 'value' => $b['discount'] ),
			), array( 'center' => number_format_i18n( array_sum( $b ) ), 'center_label' => __( 'partners', 'splitshare-affiliates' ), 'title' => __( 'Split preferences', 'splitshare-affiliates' ) ) );
			echo '<table class="ssa-table" style="margin-top:12px"><thead><tr><th>' . esc_html__( 'Split', 'splitshare-affiliates' ) . '</th><th class="num">' . esc_html__( 'Partners', 'splitshare-affiliates' ) . '</th><th class="num">' . esc_html__( 'Revenue', 'splitshare-affiliates' ) . '</th></tr></thead><tbody>';
			foreach ( SSA_Reports::split_distribution( $r['from'], $r['to'] ) as $row ) {
				echo '<tr><td>' . SSA_Charts::split_bar( $row->commission_pct, $row->discount_pct, $share, array( 'labels' => false ) ) . '<small class="ssa-muted">' . esc_html( $row->commission_pct . '% / ' . $row->discount_pct . '%' ) . '</small></td><td class="num">' . (int) $row->partners . '</td><td class="num">' . wp_kses_post( wc_price( $row->revenue ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</tbody></table>';
		} else {
			echo SSA_Admin_UI::empty_state( __( 'No active partners yet', 'splitshare-affiliates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo SSA_Admin_UI::card_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo SSA_Admin_UI::card_open( __( 'Top partners', 'splitshare-affiliates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$top = SSA_Reports::leaderboard( $r['from'], $r['to'] );
		if ( $top ) {
			echo '<table class="ssa-table"><thead><tr><th>' . esc_html__( 'Partner', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Code', 'splitshare-affiliates' ) . '</th><th class="num">' . esc_html__( 'Orders', 'splitshare-affiliates' ) . '</th><th class="num">' . esc_html__( 'Revenue', 'splitshare-affiliates' ) . '</th><th class="num">' . esc_html__( 'Commission', 'splitshare-affiliates' ) . '</th></tr></thead><tbody>';
			foreach ( $top as $row ) {
				$p = SSA_Partners::get( $row->id );
				echo '<tr><td>' . ( $p ? SSA_Admin_Menu::partner_link( $p ) : '' ) . '</td><td><code>' . esc_html( $row->code ) . '</code></td><td class="num">' . (int) $row->orders . '</td><td class="num">' . wp_kses_post( wc_price( $row->revenue ) ) . '</td><td class="num"><strong>' . wp_kses_post( wc_price( $row->commission ) ) . '</strong></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</tbody></table>';
		} else {
			echo SSA_Admin_UI::empty_state( __( 'No sales in this period', 'splitshare-affiliates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo SSA_Admin_UI::card_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';

		echo '<div class="ssa-grid ssa-grid--1-1">';
		echo SSA_Admin_UI::card_open( __( 'Commission by share group', 'splitshare-affiliates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$groups = SSA_Reports::group_revenue( $r['from'], $r['to'] );
		if ( $groups ) {
			echo SSA_Charts::columns( array_map( function ( $g ) { return $g['share'] . '%'; }, $groups ), array( array( 'name' => __( 'Commission', 'splitshare-affiliates' ), 'values' => wp_list_pluck( $groups, 'commission' ) ) ), array( 'title' => __( 'Commission by product-group share', 'splitshare-affiliates' ), 'height' => 200, 'width' => 640 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo SSA_Admin_UI::empty_state( __( 'No data', 'splitshare-affiliates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo SSA_Admin_UI::card_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$an = SSA_Reports::code_anomalies( 30 );
		echo '<div id="codes">' . SSA_Admin_UI::card_open( __( 'Code usage, last 30 days', 'splitshare-affiliates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<p class="ssa-muted" style="margin-top:0">' . esc_html( sprintf( __( 'Average %s orders per code. Codes with 3× the average and a low new-customer rate may have leaked to coupon sites.', 'splitshare-affiliates' ), $an['average'] ) ) . '</p>';
		if ( $an['rows'] ) {
			echo '<table class="ssa-table"><thead><tr><th>' . esc_html__( 'Code', 'splitshare-affiliates' ) . '</th><th class="num">' . esc_html__( 'Orders', 'splitshare-affiliates' ) . '</th><th class="num">' . esc_html__( 'New customer rate', 'splitshare-affiliates' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $an['rows'] as $row ) {
				echo '<tr><td><code>' . esc_html( $row->code ) . '</code></td><td class="num">' . (int) $row->orders . '</td><td class="num">' . esc_html( $row->new_rate ) . '%</td><td>' . ( $row->flag ? '<span class="ssa-pill ssa-pill--bad"><i></i>' . esc_html__( 'Suspicious', 'splitshare-affiliates' ) . '</span> <a class="ssa-link" href="' . esc_url( SSA_Admin_Partners::action_url( 'rotate', $row->partner_id ) ) . '">' . esc_html__( 'Rotate code', 'splitshare-affiliates' ) . '</a>' : '' ) . '</td></tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p class="ssa-muted">' . esc_html__( 'No code usage yet.', 'splitshare-affiliates' ) . '</p>';
		}
		echo SSA_Admin_UI::card_close() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}
}
