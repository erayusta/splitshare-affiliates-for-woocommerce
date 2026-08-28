<?php
/**
 * Admin → Partners: liste (toolbar, avatar, kod chip'i, dağılım çubuğu, sparkline) ve profil sayfası.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class SSA_Admin_Partners {

	public static function handle_actions() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id     = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $id || in_array( $action, array( '', 'edit', '-1' ), true ) ) {
			return;
		}
		check_admin_referer( 'ssa_partner_' . $action . '_' . $id );
		$back = array( 'action' => 'edit', 'id' => $id );
		switch ( $action ) {
			case 'pause':
				SSA_Partners::set_status( $id, 'paused' );
				SSA_Admin_Menu::redirect_with( 'partners', __( 'Partner paused.', 'splitshare-affiliates' ), 'success', $back );
				break;
			case 'resume':
				SSA_Partners::set_status( $id, 'active' );
				SSA_Admin_Menu::redirect_with( 'partners', __( 'Partner reactivated.', 'splitshare-affiliates' ), 'success', $back );
				break;
			case 'rotate':
				$new = SSA_Partners::rotate_code( $id );
				/* translators: %s: new code */
				SSA_Admin_Menu::redirect_with( 'partners', sprintf( __( 'New code issued: %s', 'splitshare-affiliates' ), $new ), 'success', $back );
				break;
			case 'unlock':
				SSA_Partners::unlock_split( $id );
				SSA_Admin_Menu::redirect_with( 'partners', __( 'Split change unlocked.', 'splitshare-affiliates' ), 'success', $back );
				break;
			case 'save':
				self::save( $id );
				break;
		}
	}

	private static function save( $id ) {
		$p = SSA_Partners::get( $id );
		if ( ! $p ) {
			return;
		}
		$code = isset( $_POST['code'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['code'] ) ) ) : $p->code;
		if ( $code !== $p->code ) {
			if ( ! SSA_Partners::code_available( $code, $id ) ) {
				SSA_Admin_Menu::redirect_with( 'partners', __( 'That code is not available.', 'splitshare-affiliates' ), 'error', array( 'action' => 'edit', 'id' => $id ) );
			}
			SSA_Partners::update( $id, array( 'code' => $code ) );
		}
		if ( isset( $_POST['commission_pct'] ) && (float) $_POST['commission_pct'] !== $p->commission_pct ) {
			SSA_Partners::set_split( $id, (float) $_POST['commission_pct'], true );
		}
		SSA_Partners::update( $id, array( 'admin_note' => isset( $_POST['admin_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_note'] ) ) : '' ) );
		SSA_Partners::update_payout_details( $id, isset( $_POST['payout'] ) ? (array) wp_unslash( $_POST['payout'] ) : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		SSA_Coupon::sync( SSA_Partners::get( $id ) );
		SSA_Admin_Menu::redirect_with( 'partners', __( 'Partner saved.', 'splitshare-affiliates' ), 'success', array( 'action' => 'edit', 'id' => $id ) );
	}

	public static function action_url( $action, $id ) {
		return wp_nonce_url( SSA_Admin_Menu::url( 'partners', array( 'action' => $action, 'id' => $id ) ), 'ssa_partner_' . $action . '_' . $id );
	}

	public static function render() {
		if ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] && ! empty( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::render_profile( (int) $_GET['id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$counts  = SSA_Partners::counts_by_status();
		$status  = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo SSA_Admin_UI::toolbar_open(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="ssa-chips">';
		foreach ( array( '' => __( 'All', 'splitshare-affiliates' ), 'active' => __( 'Active', 'splitshare-affiliates' ), 'paused' => __( 'Paused', 'splitshare-affiliates' ), 'rejected' => __( 'Rejected', 'splitshare-affiliates' ) ) as $key => $label ) {
			$n = '' === $key ? array_sum( $counts ) - $counts['pending'] : $counts[ $key ];
			echo '<a class="ssa-chip' . ( $status === $key ? ' is-active' : '' ) . '" href="' . esc_url( SSA_Admin_Menu::url( 'partners', $key ? array( 'status' => $key ) : array() ) ) . '">' . esc_html( $label ) . ' <span>' . (int) $n . '</span></a>';
		}
		echo '</div>';
		echo '<form method="get" class="ssa-toolbar__search"><input type="hidden" name="page" value="' . esc_attr( SSA_Admin_Menu::SLUG ) . '"><input type="hidden" name="tab" value="partners">' . ( $status ? '<input type="hidden" name="status" value="' . esc_attr( $status ) . '">' : '' ) . '<input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search name, e-mail or code', 'splitshare-affiliates' ) . '"><button class="button">' . esc_html__( 'Search', 'splitshare-affiliates' ) . '</button></form>';
		echo SSA_Admin_UI::toolbar_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$table = new SSA_Partners_Table();
		$table->prepare_items();
		if ( ! $table->items ) {
			echo SSA_Admin_UI::empty_state( __( 'No partners yet', 'splitshare-affiliates' ), __( 'Approved applications appear here with their code, split and earnings.', 'splitshare-affiliates' ), '<p><a class="button" href="' . esc_url( SSA_Admin_Menu::url( 'applications' ) ) . '">' . esc_html__( 'Review applications', 'splitshare-affiliates' ) . '</a></p>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( SSA_Admin_Menu::SLUG ) . '" /><input type="hidden" name="tab" value="partners" />';
		$table->display();
		echo '</form>';
	}

	private static function render_profile( $id ) {
		$p = SSA_Partners::get( $id );
		if ( ! $p ) {
			echo SSA_Admin_UI::empty_state( __( 'Partner not found.', 'splitshare-affiliates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		$e        = SSA_Reports::partner_earnings( $p->id );
		$interval = (int) SSA_Settings::get( 'split_change_interval_days' );
		$share    = (float) SSA_Settings::get( 'default_share' );
		$clicks   = SSA_Tracking::click_stats( $p->id, 30 );
		$m        = SSA_Reports::monthly( 12, $p->id );

		echo '<p><a class="ssa-link" href="' . esc_url( SSA_Admin_Menu::url( 'partners' ) ) . '">← ' . esc_html__( 'All partners', 'splitshare-affiliates' ) . '</a></p>';
		echo '<div class="ssa-profile">' . SSA_Admin_UI::avatar( $p, 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="ssa-profile__id"><h2>' . esc_html( $p->display_name() ) . ' ' . SSA_Admin_UI::badge( $p->status ) . ( $p->tier ? ' <span class="ssa-pill ssa-pill--muted">' . esc_html( $p->tier ) . '</span>' : '' ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="ssa-muted">' . esc_html( $p->email() ) . ' · <a href="' . esc_url( get_edit_user_link( $p->user_id ) ) . '">' . esc_html__( 'user profile', 'splitshare-affiliates' ) . '</a>' . ( $p->approved_at ? ' · ' . esc_html( sprintf( __( 'since %s', 'splitshare-affiliates' ), date_i18n( get_option( 'date_format' ), strtotime( $p->approved_at ) ) ) ) : '' ) . '</div>';
		echo '<div style="margin-top:8px">' . SSA_Admin_UI::code_chip( $p->code ) . ( $p->previous_code_valid() ? ' <small class="ssa-muted">' . esc_html( sprintf( __( 'previous %1$s until %2$s', 'splitshare-affiliates' ), $p->previous_code, date_i18n( get_option( 'date_format' ), strtotime( $p->previous_code_expires ) ) ) ) . '</small>' : '' ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="ssa-profile__split">' . SSA_Charts::split_bar( $p->commission_pct, $p->discount_pct, $share, array( 'size' => 'lg' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="ssa-profile__actions">';
		if ( 'active' === $p->status ) {
			echo '<a class="button" href="' . esc_url( self::action_url( 'pause', $p->id ) ) . '">' . esc_html__( 'Pause', 'splitshare-affiliates' ) . '</a>';
		} elseif ( 'paused' === $p->status ) {
			echo '<a class="button" href="' . esc_url( self::action_url( 'resume', $p->id ) ) . '">' . esc_html__( 'Reactivate', 'splitshare-affiliates' ) . '</a>';
		}
		echo '<a class="button ssa-confirm" data-confirm="' . esc_attr__( 'Issue a new code? The old one stays valid for the grace period.', 'splitshare-affiliates' ) . '" href="' . esc_url( self::action_url( 'rotate', $p->id ) ) . '">' . esc_html__( 'Rotate code', 'splitshare-affiliates' ) . '</a>';
		echo '</div></div>';

		echo '<div class="ssa-kpis">';
		echo SSA_Admin_UI::kpi( array( 'label' => __( 'Confirmed earnings', 'splitshare-affiliates' ), 'value' => wc_price( $e['confirmed'] ), 'hint' => sprintf( __( '%1$s paid · %2$s awaiting payout', 'splitshare-affiliates' ), wp_strip_all_tags( wc_price( $e['paid'] ) ), wp_strip_all_tags( wc_price( max( 0, $e['unpaid'] ) ) ) ), 'spark' => $m['commission'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo SSA_Admin_UI::kpi( array( 'label' => __( 'Estimated (pending)', 'splitshare-affiliates' ), 'value' => wc_price( $e['estimated'] ), 'hint' => __( 'inside the hold period', 'splitshare-affiliates' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo SSA_Admin_UI::kpi( array( 'label' => __( 'Approved sales', 'splitshare-affiliates' ), 'value' => number_format_i18n( $e['sales'] ), 'hint' => sprintf( __( '%s this month', 'splitshare-affiliates' ), wp_strip_all_tags( wc_price( $e['month_sales'] ) ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo SSA_Admin_UI::kpi( array( 'label' => __( 'Traffic (30 days)', 'splitshare-affiliates' ), 'value' => number_format_i18n( $clicks['clicks'] ), 'hint' => sprintf( __( '%d orders from links', 'splitshare-affiliates' ), $clicks['conversions'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';

		echo '<div class="ssa-subtabs-wrap">';
		echo '<div class="ssa-subtabs"><button type="button" class="ssa-subtab is-active" data-target="#ssa-p-sales">' . esc_html__( 'Sales', 'splitshare-affiliates' ) . '</button><button type="button" class="ssa-subtab" data-target="#ssa-p-payouts">' . esc_html__( 'Payouts', 'splitshare-affiliates' ) . '</button><button type="button" class="ssa-subtab" data-target="#ssa-p-details">' . esc_html__( 'Details & bank', 'splitshare-affiliates' ) . '</button><button type="button" class="ssa-subtab" data-target="#ssa-p-split">' . esc_html__( 'Split & code', 'splitshare-affiliates' ) . '</button></div>';

		// Satışlar
		echo '<div id="ssa-p-sales" class="ssa-subpanel is-active">';
		echo SSA_Admin_UI::card_open( __( 'Commission, last 12 months', 'splitshare-affiliates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( array_sum( $m['revenue'] ) > 0 ) {
			echo SSA_Charts::columns( $m['labels'], array( array( 'name' => __( 'Revenue', 'splitshare-affiliates' ), 'values' => $m['revenue'] ), array( 'name' => __( 'Commission', 'splitshare-affiliates' ), 'values' => $m['commission'] ) ), array( 'title' => __( 'Monthly revenue and commission', 'splitshare-affiliates' ), 'height' => 240, 'width' => 1200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo SSA_Admin_UI::empty_state( __( 'No sales yet', 'splitshare-affiliates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo SSA_Admin_UI::card_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$rows = SSA_Commissions::query( array( 'partner_id' => $p->id, 'limit' => 50 ) );
		echo SSA_Admin_UI::card_open( __( 'Recent commissions', 'splitshare-affiliates' ), '<a class="ssa-link" href="' . esc_url( SSA_Admin_Menu::url( 'commissions', array( 'partner_id' => $p->id ) ) ) . '">' . esc_html__( 'All', 'splitshare-affiliates' ) . ' →</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $rows ) {
			echo '<table class="ssa-table"><thead><tr><th>' . esc_html__( 'Date', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Order', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Attribution', 'splitshare-affiliates' ) . '</th><th class="num">' . esc_html__( 'Basis', 'splitshare-affiliates' ) . '</th><th class="num">' . esc_html__( 'Commission', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Status', 'splitshare-affiliates' ) . '</th></tr></thead><tbody>';
			foreach ( $rows as $c ) {
				$order = wc_get_order( $c->order_id );
				echo '<tr><td>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $c->created_at ) ) ) . '</td><td>' . ( $order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . (int) $c->order_id . '</a>' : '#' . (int) $c->order_id ) . '</td><td>' . esc_html( $c->attribution ) . ( $c->is_new_customer ? '' : '<small>' . esc_html__( 'returning customer', 'splitshare-affiliates' ) . '</small>' ) . '</td><td class="num">' . wp_kses_post( wc_price( $c->order_total_base ) ) . '</td><td class="num"><strong>' . wp_kses_post( wc_price( $c->amount ) ) . '</strong></td><td>' . SSA_Admin_UI::badge( $c->status ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</tbody></table>';
		} else {
			echo '<p class="ssa-muted">' . esc_html__( 'No commissions yet.', 'splitshare-affiliates' ) . '</p>';
		}
		echo SSA_Admin_UI::card_close() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Hakedişler
		echo '<div id="ssa-p-payouts" class="ssa-subpanel">' . SSA_Admin_UI::card_open( __( 'Payouts', 'splitshare-affiliates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$payouts = SSA_Payouts::for_partner( $p->id );
		if ( $payouts ) {
			echo '<table class="ssa-table"><thead><tr><th>' . esc_html__( 'Period', 'splitshare-affiliates' ) . '</th><th class="num">' . esc_html__( 'Amount', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Status', 'splitshare-affiliates' ) . '</th><th>' . esc_html__( 'Paid on', 'splitshare-affiliates' ) . '</th></tr></thead><tbody>';
			foreach ( $payouts as $po ) {
				echo '<tr><td>' . esc_html( $po->period ) . '</td><td class="num">' . wp_kses_post( wc_price( $po->amount ) ) . '</td><td>' . SSA_Admin_UI::badge( $po->status ) . '</td><td>' . ( $po->paid_at ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $po->paid_at ) ) . ( $po->reference ? ' · ' . $po->reference : '' ) ) : '—' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</tbody></table>';
		} else {
			echo '<p class="ssa-muted">' . esc_html__( 'No payouts yet.', 'splitshare-affiliates' ) . '</p>';
		}
		echo SSA_Admin_UI::card_close() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Detaylar & banka + Dağılım & kod (tek form)
		echo '<form method="post" action="' . esc_url( SSA_Admin_Menu::url( 'partners', array( 'action' => 'save', 'id' => $p->id ) ) ) . '">';
		wp_nonce_field( 'ssa_partner_save_' . $p->id );
		echo '<div id="ssa-p-details" class="ssa-subpanel"><div class="ssa-grid ssa-grid--1-1">';
		echo SSA_Admin_UI::card_open( __( 'Application', 'splitshare-affiliates' ) ) . '<dl class="ssa-appcard__meta">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( $p->application as $k => $v ) {
			if ( '' !== (string) $v ) {
				$val = filter_var( $v, FILTER_VALIDATE_URL ) ? '<a href="' . esc_url( $v ) . '" target="_blank" rel="noopener">' . esc_html( preg_replace( '#^https?://(www\.)?#', '', $v ) ) . '</a>' : esc_html( $v );
				echo '<div><dt>' . esc_html( ucfirst( str_replace( '_', ' ', $k ) ) ) . '</dt><dd>' . $val . '</dd></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		echo '</dl>' . SSA_Admin_UI::card_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo SSA_Admin_UI::card_open( __( 'Bank details & notes', 'splitshare-affiliates' ) ) . '<div class="ssa-form-grid">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( array( 'holder' => __( 'Account holder', 'splitshare-affiliates' ), 'iban' => 'IBAN', 'company' => __( 'Company / tax office', 'splitshare-affiliates' ), 'tax_no' => __( 'Tax / ID number', 'splitshare-affiliates' ), 'invoices' => __( 'Issues invoices? (yes/no)', 'splitshare-affiliates' ) ) as $k => $label ) {
			echo '<div><label>' . esc_html( $label ) . '</label><input type="text" name="payout[' . esc_attr( $k ) . ']" value="' . esc_attr( isset( $p->payout_details[ $k ] ) ? $p->payout_details[ $k ] : '' ) . '"></div>';
		}
		echo '<div style="grid-column:1/-1"><label>' . esc_html__( 'Admin note', 'splitshare-affiliates' ) . '</label><textarea name="admin_note" rows="3">' . esc_textarea( $p->admin_note ) . '</textarea></div></div>';
		echo '<p><button class="button button-primary">' . esc_html__( 'Save', 'splitshare-affiliates' ) . '</button></p>' . SSA_Admin_UI::card_close() . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo '<div id="ssa-p-split" class="ssa-subpanel">' . SSA_Admin_UI::card_open( __( 'Split & code', 'splitshare-affiliates' ) ) . '<div class="ssa-form-grid">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div><label>' . esc_html__( 'Code', 'splitshare-affiliates' ) . '</label><input type="text" name="code" value="' . esc_attr( $p->code ) . '" pattern="[A-Za-z0-9]{4,12}"></div>';
		echo '<div><label>' . esc_html( sprintf( __( 'Commission %% (share %s%%)', 'splitshare-affiliates' ), $share ) ) . '</label><input type="number" name="commission_pct" value="' . esc_attr( $p->commission_pct ) . '" min="0" max="' . esc_attr( $share ) . '" step="0.5"> <span class="ssa-muted">+ ' . esc_html( $p->discount_pct ) . '% ' . esc_html__( 'discount', 'splitshare-affiliates' ) . '</span>';
		if ( ! $p->can_change_split( $interval ) ) {
			echo '<p class="ssa-muted">' . esc_html( sprintf( __( 'Partner can change again on %s.', 'splitshare-affiliates' ), date_i18n( get_option( 'date_format' ), $p->next_split_change_at( $interval ) ) ) ) . ' <a href="' . esc_url( self::action_url( 'unlock', $p->id ) ) . '">' . esc_html__( 'Unlock now', 'splitshare-affiliates' ) . '</a></p>';
		}
		echo '</div></div><p><button class="button button-primary">' . esc_html__( 'Save', 'splitshare-affiliates' ) . '</button></p>' . SSA_Admin_UI::card_close() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</form></div>';
	}
}

class SSA_Partners_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array( 'singular' => 'partner', 'plural' => 'partners', 'ajax' => false ) );
	}

	public function get_columns() {
		return array(
			'name'   => __( 'Partner', 'splitshare-affiliates' ),
			'code'   => __( 'Code', 'splitshare-affiliates' ),
			'split'  => __( 'Split', 'splitshare-affiliates' ),
			'status' => __( 'Status', 'splitshare-affiliates' ),
			'trend'  => __( 'Last 6 months', 'splitshare-affiliates' ),
			'sales'  => __( 'Sales', 'splitshare-affiliates' ),
			'earned' => __( 'Confirmed / Pending', 'splitshare-affiliates' ),
		);
	}

	public function prepare_items() {
		$per_page = 20;
		$status   = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$args     = array(
			'status'          => $status,
			'exclude_pending' => '' === $status,
			'search'          => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'limit'           => $per_page,
			'offset'          => ( $this->get_pagenum() - 1 ) * $per_page,
		);
		$this->items           = SSA_Partners::query( $args );
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->set_pagination_args( array( 'total_items' => SSA_Partners::count( $args ), 'per_page' => $per_page ) );
	}

	public function column_default( $item, $col ) {
		switch ( $col ) {
			case 'name':
				$actions = array( 'edit' => '<a href="' . esc_url( SSA_Admin_Menu::url( 'partners', array( 'action' => 'edit', 'id' => $item->id ) ) ) . '">' . esc_html__( 'Profile', 'splitshare-affiliates' ) . '</a>' );
				if ( 'active' === $item->status ) {
					$actions['pause'] = '<a href="' . esc_url( SSA_Admin_Partners::action_url( 'pause', $item->id ) ) . '">' . esc_html__( 'Pause', 'splitshare-affiliates' ) . '</a>';
				} elseif ( 'paused' === $item->status ) {
					$actions['resume'] = '<a href="' . esc_url( SSA_Admin_Partners::action_url( 'resume', $item->id ) ) . '">' . esc_html__( 'Reactivate', 'splitshare-affiliates' ) . '</a>';
				}
				return SSA_Admin_Menu::partner_link( $item ) . $this->row_actions( $actions );
			case 'code':
				return SSA_Admin_UI::code_chip( $item->code );
			case 'split':
				return SSA_Charts::split_bar( $item->commission_pct, $item->discount_pct, (float) SSA_Settings::get( 'default_share' ) );
			case 'status':
				return SSA_Admin_UI::badge( $item->status ) . ( $item->tier ? '<br><small class="ssa-muted">' . esc_html( $item->tier ) . '</small>' : '' );
			case 'trend':
				return SSA_Charts::sparkline( SSA_Reports::partner_spark( $item->id, 6 ) );
			case 'sales':
				return '<span class="num">' . (int) SSA_Reports::partner_earnings( $item->id )['sales'] . '</span>';
			case 'earned':
				$e = SSA_Reports::partner_earnings( $item->id );
				return '<strong>' . wp_kses_post( wc_price( $e['confirmed'] ) ) . '</strong><br><small class="ssa-muted">' . wp_kses_post( wc_price( $e['estimated'] ) ) . ' ' . esc_html__( 'pending', 'splitshare-affiliates' ) . '</small>';
		}
		return '';
	}
}
