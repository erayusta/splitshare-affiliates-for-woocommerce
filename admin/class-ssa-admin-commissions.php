<?php
/**
 * Admin → Commissions: durum toplamları şeridi, toolbar filtreler, liste, elle onay/iptal, CSV.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class SSA_Admin_Commissions {

	public static function handle_actions() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'csv' === $action ) {
			check_admin_referer( 'ssa_commissions_csv' );
			self::csv();
		}
		if ( ! in_array( $action, array( 'approve', 'void' ), true ) ) {
			return;
		}
		$ids = isset( $_REQUEST['commission'] ) ? array_map( 'intval', (array) $_REQUEST['commission'] ) : ( isset( $_REQUEST['id'] ) ? array( (int) $_REQUEST['id'] ) : array() ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $ids ) {
			return;
		}
		check_admin_referer( isset( $_REQUEST['id'] ) ? 'ssa_commission_' . $action . '_' . (int) $_REQUEST['id'] : 'bulk-commissions' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$n = 0;
		foreach ( $ids as $id ) {
			$c = SSA_Commissions::get( $id );
			if ( ! $c || 'paid' === $c->status ) {
				continue;
			}
			SSA_Commissions::set_status( $id, 'approve' === $action ? 'approved' : 'void' );
			$n++;
		}
		/* translators: %d: count */
		SSA_Admin_Menu::redirect_with( 'commissions', sprintf( _n( '%d commission updated.', '%d commissions updated.', $n, 'splitshare-affiliates' ), $n ) );
	}

	private static function filters() {
		return array(
			'status'     => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'partner_id' => isset( $_GET['partner_id'] ) ? (int) $_GET['partner_id'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'from'       => isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'to'         => isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
	}

	private static function csv() {
		$rows = SSA_Commissions::query( array_merge( self::filters(), array( 'limit' => 0 ) ) );
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=commissions-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		echo "\xEF\xBB\xBF"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		fputcsv( $out, array( 'Date', 'Order', 'Partner', 'Code', 'Attribution', 'Basis', 'Commission %', 'Discount %', 'Commission', 'Status', 'Available at' ) );
		foreach ( $rows as $c ) {
			$p = SSA_Partners::get( $c->partner_id );
			fputcsv( $out, array( $c->created_at, $c->order_id, $p ? $p->display_name() : $c->partner_id, $c->code_used, $c->attribution, $c->order_total_base, $c->commission_pct, $c->discount_pct, $c->amount, $c->status, $c->available_at ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	public static function render() {
		$f      = self::filters();
		$totals = SSA_Reports::status_totals( array( 'partner_id' => $f['partner_id'] ) );
		echo '<div class="ssa-strip">';
		foreach ( array( 'pending' => array( __( 'Pending', 'splitshare-affiliates' ), 'clock' ), 'approved' => array( __( 'Approved', 'splitshare-affiliates' ), 'wallet' ), 'paid' => array( __( 'Paid', 'splitshare-affiliates' ), 'revenue' ), 'void' => array( __( 'Void', 'splitshare-affiliates' ), 'alert' ) ) as $key => $meta ) {
			echo '<div><div class="ssa-strip__label">' . SSA_Admin_UI::icon( $meta[1] ) . esc_html( $meta[0] ) . '</div><div class="ssa-strip__value">' . wp_kses_post( wc_price( $totals[ $key ] ) ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';

		echo SSA_Admin_UI::toolbar_open(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="ssa-chips">';
		foreach ( array( '' => __( 'All', 'splitshare-affiliates' ), 'pending' => __( 'Pending', 'splitshare-affiliates' ), 'approved' => __( 'Approved', 'splitshare-affiliates' ), 'paid' => __( 'Paid', 'splitshare-affiliates' ), 'void' => __( 'Void', 'splitshare-affiliates' ) ) as $key => $label ) {
			echo '<a class="ssa-chip' . ( $f['status'] === $key ? ' is-active' : '' ) . '" href="' . esc_url( SSA_Admin_Menu::url( 'commissions', array_filter( array( 'status' => $key, 'partner_id' => $f['partner_id'], 'from' => $f['from'], 'to' => $f['to'] ) ) ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</div>';
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( SSA_Admin_Menu::SLUG ) . '"><input type="hidden" name="tab" value="commissions">' . ( $f['status'] ? '<input type="hidden" name="status" value="' . esc_attr( $f['status'] ) . '">' : '' );
		echo '<select name="partner_id"><option value="0">' . esc_html__( 'All partners', 'splitshare-affiliates' ) . '</option>';
		foreach ( SSA_Partners::query( array( 'limit' => 500, 'exclude_pending' => true, 'orderby' => 'code', 'order' => 'ASC' ) ) as $p ) {
			echo '<option value="' . (int) $p->id . '"' . selected( $f['partner_id'], $p->id, false ) . '>' . esc_html( $p->display_name() . ' (' . $p->code . ')' ) . '</option>';
		}
		echo '</select><input type="date" name="from" value="' . esc_attr( $f['from'] ) . '"><span>–</span><input type="date" name="to" value="' . esc_attr( $f['to'] ) . '"><button class="button">' . esc_html__( 'Filter', 'splitshare-affiliates' ) . '</button></form>';
		echo '<a class="button" style="margin-left:auto" href="' . esc_url( wp_nonce_url( SSA_Admin_Menu::url( 'commissions', array_filter( array_merge( $f, array( 'action' => 'csv' ) ) ) ), 'ssa_commissions_csv' ) ) . '">' . esc_html__( 'Export CSV', 'splitshare-affiliates' ) . '</a>';
		echo SSA_Admin_UI::toolbar_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$table = new SSA_Commissions_Table();
		$table->prepare_items();
		if ( ! $table->items ) {
			echo SSA_Admin_UI::empty_state( __( 'No commissions match', 'splitshare-affiliates' ), __( 'Commissions are recorded when an attributed order is paid.', 'splitshare-affiliates' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( SSA_Admin_Menu::SLUG ) . '" /><input type="hidden" name="tab" value="commissions" />';
		$table->display();
		echo '</form>';
	}
}

class SSA_Commissions_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array( 'singular' => 'commission', 'plural' => 'commissions', 'ajax' => false ) );
	}

	public function get_columns() {
		return array(
			'cb'      => '<input type="checkbox" />',
			'order'   => __( 'Order', 'splitshare-affiliates' ),
			'partner' => __( 'Partner', 'splitshare-affiliates' ),
			'attr'    => __( 'Attribution', 'splitshare-affiliates' ),
			'base'    => __( 'Basis', 'splitshare-affiliates' ),
			'pct'     => __( 'Split', 'splitshare-affiliates' ),
			'amount'  => __( 'Commission', 'splitshare-affiliates' ),
			'status'  => __( 'Status', 'splitshare-affiliates' ),
		);
	}

	public function get_bulk_actions() {
		return array( 'approve' => __( 'Approve', 'splitshare-affiliates' ), 'void' => __( 'Void', 'splitshare-affiliates' ) );
	}

	public function prepare_items() {
		$per_page = 25;
		$args     = array(
			'status'     => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'partner_id' => isset( $_GET['partner_id'] ) ? (int) $_GET['partner_id'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'from'       => isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'to'         => isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'limit'      => $per_page,
			'offset'     => ( $this->get_pagenum() - 1 ) * $per_page,
		);
		$this->items           = SSA_Commissions::query( $args );
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->set_pagination_args( array( 'total_items' => SSA_Commissions::count( $args ), 'per_page' => $per_page ) );
	}

	public function column_cb( $item ) {
		return '<input type="checkbox" name="commission[]" value="' . (int) $item->id . '" />';
	}

	public function column_default( $item, $col ) {
		switch ( $col ) {
			case 'order':
				$order   = wc_get_order( $item->order_id );
				$link    = $order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '"><strong>#' . (int) $item->order_id . '</strong></a>' : '<strong>#' . (int) $item->order_id . '</strong>';
				$actions = array();
				if ( 'pending' === $item->status ) {
					$actions['approve'] = '<a href="' . esc_url( wp_nonce_url( SSA_Admin_Menu::url( 'commissions', array( 'action' => 'approve', 'id' => $item->id ) ), 'ssa_commission_approve_' . $item->id ) ) . '">' . esc_html__( 'Approve now', 'splitshare-affiliates' ) . '</a>';
				}
				if ( in_array( $item->status, array( 'pending', 'approved' ), true ) ) {
					$actions['void'] = '<a href="' . esc_url( wp_nonce_url( SSA_Admin_Menu::url( 'commissions', array( 'action' => 'void', 'id' => $item->id ) ), 'ssa_commission_void_' . $item->id ) ) . '">' . esc_html__( 'Void', 'splitshare-affiliates' ) . '</a>';
				}
				return $link . '<br><small class="ssa-muted">' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item->created_at ) ) ) . '</small>' . ( $item->adjustment_of ? ' <span class="ssa-pill ssa-pill--muted">' . esc_html__( 'adjustment', 'splitshare-affiliates' ) . '</span>' : '' ) . $this->row_actions( $actions );
			case 'partner':
				$p = SSA_Partners::get( $item->partner_id );
				return $p ? SSA_Admin_Menu::partner_link( $p ) : '#' . (int) $item->partner_id;
			case 'attr':
				return '<span class="ssa-pill ssa-pill--muted">' . esc_html( 'coupon' === $item->attribution ? __( 'Code', 'splitshare-affiliates' ) : __( 'Link', 'splitshare-affiliates' ) ) . '</span>' . ( $item->code_used ? ' <code>' . esc_html( $item->code_used ) . '</code>' : '' ) . ( $item->is_new_customer ? '' : '<br><small class="ssa-muted">' . esc_html__( 'returning customer', 'splitshare-affiliates' ) . '</small>' );
			case 'base':
				return '<span class="num">' . wp_kses_post( wc_price( $item->order_total_base ) ) . '</span>';
			case 'pct':
				return esc_html( $item->commission_pct . '% / ' . $item->discount_pct . '%' );
			case 'amount':
				return '<strong>' . wp_kses_post( wc_price( $item->amount ) ) . '</strong>';
			case 'status':
				$html = SSA_Admin_UI::badge( $item->status );
				if ( 'pending' === $item->status && $item->available_at ) {
					$html .= '<br><small class="ssa-muted">' . esc_html( sprintf( __( 'approves %s', 'splitshare-affiliates' ), date_i18n( get_option( 'date_format' ), strtotime( $item->available_at ) ) ) ) . '</small>';
				} elseif ( $item->reason && 'void' === $item->status ) {
					$html .= '<br><small class="ssa-muted">' . esc_html( str_replace( '_', ' ', $item->reason ) ) . '</small>';
				}
				return $html;
		}
		return '';
	}
}
