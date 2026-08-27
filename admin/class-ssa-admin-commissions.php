<?php
/**
 * Admin → Commissions: filtreli liste, elle onay/iptal.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class SSA_Admin_Commissions {

	public static function handle_actions() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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

	public static function render() {
		$table = new SSA_Commissions_Table();
		$table->prepare_items();
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( SSA_Admin_Menu::SLUG ) . '" /><input type="hidden" name="tab" value="commissions" />';
		$table->views();
		self::filters();
		$table->display();
		echo '</form>';
	}

	private static function filters() {
		$partner_id = isset( $_GET['partner_id'] ) ? (int) $_GET['partner_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$from       = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$to         = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<p class="ssa-filters"><select name="partner_id"><option value="0">' . esc_html__( 'All partners', 'splitshare-affiliates' ) . '</option>';
		foreach ( SSA_Partners::query( array( 'limit' => 500, 'exclude_pending' => true, 'orderby' => 'code', 'order' => 'ASC' ) ) as $p ) {
			echo '<option value="' . (int) $p->id . '"' . selected( $partner_id, $p->id, false ) . '>' . esc_html( $p->display_name() . ' (' . $p->code . ')' ) . '</option>';
		}
		echo '</select> <input type="date" name="from" value="' . esc_attr( $from ) . '" /> – <input type="date" name="to" value="' . esc_attr( $to ) . '" /> <button class="button">' . esc_html__( 'Filter', 'splitshare-affiliates' ) . '</button></p>';
	}
}

class SSA_Commissions_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array( 'singular' => 'commission', 'plural' => 'commissions', 'ajax' => false ) );
	}

	public function get_columns() {
		return array(
			'cb'       => '<input type="checkbox" />',
			'order'    => __( 'Order', 'splitshare-affiliates' ),
			'partner'  => __( 'Partner', 'splitshare-affiliates' ),
			'attr'     => __( 'Attribution', 'splitshare-affiliates' ),
			'base'     => __( 'Basis', 'splitshare-affiliates' ),
			'pct'      => __( 'Split', 'splitshare-affiliates' ),
			'amount'   => __( 'Commission', 'splitshare-affiliates' ),
			'status'   => __( 'Status', 'splitshare-affiliates' ),
			'date'     => __( 'Date', 'splitshare-affiliates' ),
		);
	}

	public function get_bulk_actions() {
		return array( 'approve' => __( 'Approve', 'splitshare-affiliates' ), 'void' => __( 'Void', 'splitshare-affiliates' ) );
	}

	protected function get_views() {
		global $wpdb;
		$t       = SSA_Install::tables()['commissions'];
		$counts  = array( '' => 0 );
		foreach ( (array) $wpdb->get_results( "SELECT status, COUNT(*) c FROM {$t} GROUP BY status" ) as $r ) { // phpcs:ignore WordPress.DB
			$counts[ $r->status ] = (int) $r->c;
			$counts['']         += (int) $r->c;
		}
		$current = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$out     = array();
		foreach ( array( '' => __( 'All', 'splitshare-affiliates' ), 'pending' => __( 'Pending', 'splitshare-affiliates' ), 'approved' => __( 'Approved', 'splitshare-affiliates' ), 'paid' => __( 'Paid', 'splitshare-affiliates' ), 'void' => __( 'Void', 'splitshare-affiliates' ) ) as $key => $label ) {
			$out[ $key ? $key : 'all' ] = '<a href="' . esc_url( SSA_Admin_Menu::url( 'commissions', $key ? array( 'status' => $key ) : array() ) ) . '"' . ( $current === $key ? ' class="current"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . ( isset( $counts[ $key ] ) ? (int) $counts[ $key ] : 0 ) . ')</span></a>';
		}
		return $out;
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
				$link    = $order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . (int) $item->order_id . '</a>' : '#' . (int) $item->order_id;
				$actions = array();
				if ( 'pending' === $item->status ) {
					$actions['approve'] = '<a href="' . esc_url( wp_nonce_url( SSA_Admin_Menu::url( 'commissions', array( 'action' => 'approve', 'id' => $item->id ) ), 'ssa_commission_approve_' . $item->id ) ) . '">' . esc_html__( 'Approve now', 'splitshare-affiliates' ) . '</a>';
				}
				if ( in_array( $item->status, array( 'pending', 'approved' ), true ) ) {
					$actions['void'] = '<a href="' . esc_url( wp_nonce_url( SSA_Admin_Menu::url( 'commissions', array( 'action' => 'void', 'id' => $item->id ) ), 'ssa_commission_void_' . $item->id ) ) . '">' . esc_html__( 'Void', 'splitshare-affiliates' ) . '</a>';
				}
				return $link . ( $item->adjustment_of ? ' <span class="ssa-badge">' . esc_html__( 'adjustment', 'splitshare-affiliates' ) . '</span>' : '' ) . $this->row_actions( $actions );
			case 'partner':
				$p = SSA_Partners::get( $item->partner_id );
				return $p ? SSA_Admin_Menu::partner_link( $p ) : '#' . (int) $item->partner_id;
			case 'attr':
				return esc_html( $item->attribution ) . ( $item->code_used ? ' <code>' . esc_html( $item->code_used ) . '</code>' : '' ) . ( $item->is_new_customer ? '' : '<br><small>' . esc_html__( 'returning customer', 'splitshare-affiliates' ) . '</small>' );
			case 'base':
				return wp_kses_post( wc_price( $item->order_total_base ) );
			case 'pct':
				return esc_html( $item->commission_pct . '% / ' . $item->discount_pct . '%' );
			case 'amount':
				return '<strong>' . wp_kses_post( wc_price( $item->amount ) ) . '</strong>';
			case 'status':
				$html = SSA_Admin_Menu::badge( $item->status );
				if ( 'pending' === $item->status && $item->available_at ) {
					$html .= '<br><small>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item->available_at ) ) ) . '</small>';
				}
				if ( $item->reason ) {
					$html .= '<br><small>' . esc_html( $item->reason ) . '</small>';
				}
				return $html;
			case 'date':
				return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item->created_at ) ) );
		}
		return '';
	}
}
