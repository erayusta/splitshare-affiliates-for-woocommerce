<?php
/**
 * Admin → Partners: liste, düzenleme, eylemler (duraklat/devam, kod döndür, kilit aç).
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

		switch ( $action ) {
			case 'pause':
				SSA_Partners::set_status( $id, 'paused' );
				SSA_Admin_Menu::redirect_with( 'partners', __( 'Partner paused.', 'splitshare-affiliates' ) );
				break;
			case 'resume':
				SSA_Partners::set_status( $id, 'active' );
				SSA_Admin_Menu::redirect_with( 'partners', __( 'Partner reactivated.', 'splitshare-affiliates' ) );
				break;
			case 'rotate':
				$new = SSA_Partners::rotate_code( $id );
				/* translators: %s: new code */
				SSA_Admin_Menu::redirect_with( 'partners', sprintf( __( 'New code issued: %s', 'splitshare-affiliates' ), $new ), 'success', array( 'action' => 'edit', 'id' => $id ) );
				break;
			case 'unlock':
				SSA_Partners::unlock_split( $id );
				SSA_Admin_Menu::redirect_with( 'partners', __( 'Split change unlocked.', 'splitshare-affiliates' ), 'success', array( 'action' => 'edit', 'id' => $id ) );
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
			self::render_edit( (int) $_GET['id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$table = new SSA_Partners_Table();
		$table->prepare_items();
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( SSA_Admin_Menu::SLUG ) . '" /><input type="hidden" name="tab" value="partners" />';
		$table->views();
		$table->search_box( __( 'Search partners', 'splitshare-affiliates' ), 'ssa' );
		$table->display();
		echo '</form>';
	}

	private static function render_edit( $id ) {
		$p = SSA_Partners::get( $id );
		if ( ! $p ) {
			echo '<p>' . esc_html__( 'Partner not found.', 'splitshare-affiliates' ) . '</p>';
			return;
		}
		$totals   = class_exists( 'SSA_Commissions' ) ? SSA_Commissions::totals_for_partner( $p->id ) : array();
		$interval = (int) SSA_Settings::get( 'split_change_interval_days' );
		$share    = (float) SSA_Settings::get( 'default_share' );
		?>
		<h2><?php echo esc_html( $p->display_name() ); ?> <?php echo SSA_Admin_Menu::badge( $p->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
		<p><a href="<?php echo esc_url( SSA_Admin_Menu::url( 'partners' ) ); ?>">&larr; <?php esc_html_e( 'All partners', 'splitshare-affiliates' ); ?></a></p>
		<?php if ( $totals ) : ?>
		<div class="ssa-stat-cards">
			<div class="ssa-stat-card"><?php esc_html_e( 'Pending', 'splitshare-affiliates' ); ?><strong><?php echo wp_kses_post( wc_price( $totals['pending'] ) ); ?></strong></div>
			<div class="ssa-stat-card"><?php esc_html_e( 'Approved', 'splitshare-affiliates' ); ?><strong><?php echo wp_kses_post( wc_price( $totals['approved'] ) ); ?></strong></div>
			<div class="ssa-stat-card"><?php esc_html_e( 'Paid', 'splitshare-affiliates' ); ?><strong><?php echo wp_kses_post( wc_price( $totals['paid'] ) ); ?></strong></div>
			<div class="ssa-stat-card"><?php esc_html_e( 'Approved sales', 'splitshare-affiliates' ); ?><strong><?php echo (int) $totals['sales_count']; ?></strong></div>
		</div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( SSA_Admin_Menu::url( 'partners', array( 'action' => 'save', 'id' => $p->id ) ) ); ?>">
			<?php wp_nonce_field( 'ssa_partner_save_' . $p->id ); ?>
			<table class="form-table">
				<tr><th><?php esc_html_e( 'E-mail', 'splitshare-affiliates' ); ?></th><td><?php echo esc_html( $p->email() ); ?> · <a href="<?php echo esc_url( get_edit_user_link( $p->user_id ) ); ?>"><?php esc_html_e( 'user profile', 'splitshare-affiliates' ); ?></a></td></tr>
				<tr><th><?php esc_html_e( 'Code', 'splitshare-affiliates' ); ?></th><td><input type="text" name="code" value="<?php echo esc_attr( $p->code ); ?>" class="regular-text" pattern="[A-Za-z0-9]{4,12}" />
					<?php if ( $p->previous_code_valid() ) : ?><p class="description"><?php printf( esc_html__( 'Previous code %1$s valid until %2$s', 'splitshare-affiliates' ), esc_html( $p->previous_code ), esc_html( $p->previous_code_expires ) ); ?></p><?php endif; ?>
					<p><a class="button ssa-confirm" data-confirm="<?php esc_attr_e( 'Issue a new code? The old one stays valid for the grace period.', 'splitshare-affiliates' ); ?>" href="<?php echo esc_url( self::action_url( 'rotate', $p->id ) ); ?>"><?php esc_html_e( 'Rotate code', 'splitshare-affiliates' ); ?></a></p></td></tr>
				<tr><th><?php esc_html_e( 'Split', 'splitshare-affiliates' ); ?></th><td>
					<input type="number" name="commission_pct" value="<?php echo esc_attr( $p->commission_pct ); ?>" min="0" max="<?php echo esc_attr( $share ); ?>" step="0.5" /> % <?php esc_html_e( 'commission', 'splitshare-affiliates' ); ?> + <strong><?php echo esc_html( $p->discount_pct ); ?>%</strong> <?php esc_html_e( 'discount', 'splitshare-affiliates' ); ?> (<?php printf( esc_html__( 'share %s%%', 'splitshare-affiliates' ), esc_html( $share ) ); ?>)
					<?php if ( ! $p->can_change_split( $interval ) ) : ?><p class="description"><?php printf( esc_html__( 'Partner can change again on %s.', 'splitshare-affiliates' ), esc_html( date_i18n( get_option( 'date_format' ), $p->next_split_change_at( $interval ) ) ) ); ?> <a href="<?php echo esc_url( self::action_url( 'unlock', $p->id ) ); ?>"><?php esc_html_e( 'Unlock now', 'splitshare-affiliates' ); ?></a></p><?php endif; ?></td></tr>
				<tr><th><?php esc_html_e( 'Tier', 'splitshare-affiliates' ); ?></th><td><?php echo esc_html( $p->tier ? $p->tier : '—' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Application', 'splitshare-affiliates' ); ?></th><td><?php foreach ( $p->application as $k => $v ) { if ( '' !== (string) $v ) { echo '<strong>' . esc_html( ucfirst( str_replace( '_', ' ', $k ) ) ) . ':</strong> ' . esc_html( $v ) . '<br>'; } } ?></td></tr>
				<tr><th><?php esc_html_e( 'Payout details', 'splitshare-affiliates' ); ?></th><td>
					<?php foreach ( array( 'holder' => __( 'Account holder', 'splitshare-affiliates' ), 'iban' => 'IBAN', 'company' => __( 'Company / tax office', 'splitshare-affiliates' ), 'tax_no' => __( 'Tax / ID number', 'splitshare-affiliates' ), 'invoices' => __( 'Issues invoices? (yes/no)', 'splitshare-affiliates' ) ) as $k => $label ) : ?>
						<p><label><?php echo esc_html( $label ); ?><br><input type="text" class="regular-text" name="payout[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( isset( $p->payout_details[ $k ] ) ? $p->payout_details[ $k ] : '' ); ?>" /></label></p>
					<?php endforeach; ?></td></tr>
				<tr><th><?php esc_html_e( 'Admin note', 'splitshare-affiliates' ); ?></th><td><textarea name="admin_note" rows="3" class="large-text"><?php echo esc_textarea( $p->admin_note ); ?></textarea></td></tr>
			</table>
			<p class="submit">
				<button class="button button-primary"><?php esc_html_e( 'Save', 'splitshare-affiliates' ); ?></button>
				<?php if ( 'active' === $p->status ) : ?>
					<a class="button" href="<?php echo esc_url( self::action_url( 'pause', $p->id ) ); ?>"><?php esc_html_e( 'Pause partner', 'splitshare-affiliates' ); ?></a>
				<?php elseif ( 'paused' === $p->status ) : ?>
					<a class="button" href="<?php echo esc_url( self::action_url( 'resume', $p->id ) ); ?>"><?php esc_html_e( 'Reactivate', 'splitshare-affiliates' ); ?></a>
				<?php endif; ?>
			</p>
		</form>
		<?php
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
			'tier'   => __( 'Tier', 'splitshare-affiliates' ),
			'status' => __( 'Status', 'splitshare-affiliates' ),
			'sales'  => __( 'Approved sales', 'splitshare-affiliates' ),
			'earned' => __( 'Approved / Paid', 'splitshare-affiliates' ),
			'since'  => __( 'Since', 'splitshare-affiliates' ),
		);
	}

	protected function get_views() {
		$counts  = SSA_Partners::counts_by_status();
		$current = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$views   = array( '' => __( 'All', 'splitshare-affiliates' ), 'active' => __( 'Active', 'splitshare-affiliates' ), 'paused' => __( 'Paused', 'splitshare-affiliates' ), 'rejected' => __( 'Rejected', 'splitshare-affiliates' ) );
		$out     = array();
		foreach ( $views as $key => $label ) {
			$n = '' === $key ? array_sum( $counts ) - $counts['pending'] : $counts[ $key ];
			$out[ $key ? $key : 'all' ] = '<a href="' . esc_url( SSA_Admin_Menu::url( 'partners', $key ? array( 'status' => $key ) : array() ) ) . '"' . ( $current === $key ? ' class="current"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . (int) $n . ')</span></a>';
		}
		return $out;
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
		$this->items = SSA_Partners::query( $args );
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->set_pagination_args( array( 'total_items' => SSA_Partners::count( $args ), 'per_page' => $per_page ) );
	}

	public function column_default( $item, $col ) {
		$totals = class_exists( 'SSA_Commissions' ) ? SSA_Commissions::totals_for_partner( $item->id ) : array( 'approved' => 0, 'paid' => 0, 'sales_count' => 0 );
		switch ( $col ) {
			case 'name':
				$actions = array( 'edit' => '<a href="' . esc_url( SSA_Admin_Menu::url( 'partners', array( 'action' => 'edit', 'id' => $item->id ) ) ) . '">' . esc_html__( 'Edit', 'splitshare-affiliates' ) . '</a>' );
				if ( 'active' === $item->status ) {
					$actions['pause'] = '<a href="' . esc_url( SSA_Admin_Partners::action_url( 'pause', $item->id ) ) . '">' . esc_html__( 'Pause', 'splitshare-affiliates' ) . '</a>';
				} elseif ( 'paused' === $item->status ) {
					$actions['resume'] = '<a href="' . esc_url( SSA_Admin_Partners::action_url( 'resume', $item->id ) ) . '">' . esc_html__( 'Reactivate', 'splitshare-affiliates' ) . '</a>';
				}
				return SSA_Admin_Menu::partner_link( $item ) . $this->row_actions( $actions );
			case 'code':
				return '<code>' . esc_html( $item->code ) . '</code>';
			case 'split':
				return esc_html( $item->commission_pct . '% + ' . $item->discount_pct . '%' );
			case 'tier':
				return esc_html( $item->tier ? $item->tier : '—' );
			case 'status':
				return SSA_Admin_Menu::badge( $item->status );
			case 'sales':
				return (int) $totals['sales_count'];
			case 'earned':
				return wp_kses_post( wc_price( $totals['approved'] ) . ' / ' . wc_price( $totals['paid'] ) );
			case 'since':
				return esc_html( $item->approved_at ? date_i18n( get_option( 'date_format' ), strtotime( $item->approved_at ) ) : '—' );
		}
		return '';
	}
}
