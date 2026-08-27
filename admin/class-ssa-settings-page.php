<?php
/**
 * WooCommerce → Settings → Affiliates sekmesi.
 * Tüm alanlar `ssa_settings[key]` olarak tek seçenekte saklanır.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Settings_Page extends WC_Settings_Page {

	public function __construct() {
		$this->id    = 'ssa';
		$this->label = __( 'Affiliates', 'splitshare-affiliates' );
		parent::__construct();

		foreach ( array( 'group_shares', 'periods', 'boosters', 'tiers', 'endpoints', 'media_ids' ) as $type ) {
			add_action( 'woocommerce_admin_field_ssa_' . $type, array( $this, 'render_' . $type ) );
		}
		foreach ( array( 'group_shares', 'campaign_periods', 'boosters', 'tiers', 'endpoints', 'kit_attachments' ) as $key ) {
			add_filter( 'woocommerce_admin_settings_sanitize_option_ssa_settings[' . $key . ']', array( $this, 'sanitize_' . $key ), 10, 3 );
		}
		add_action( 'admin_enqueue_scripts', function () {
			if ( isset( $_GET['tab'] ) && 'ssa' === $_GET['tab'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				wp_enqueue_media();
				wp_enqueue_style( 'ssa-admin', SSA_URL . 'assets/css/admin.css', array(), SSA_VERSION );
				wp_enqueue_script( 'ssa-admin', SSA_URL . 'assets/js/admin.js', array( 'jquery', 'wc-enhanced-select' ), SSA_VERSION, true );
			}
		} );
	}

	public function get_sections() {
		return array(
			''          => __( 'Program', 'splitshare-affiliates' ),
			'groups'    => __( 'Groups & Campaigns', 'splitshare-affiliates' ),
			'payouts'   => __( 'Attribution & Payouts', 'splitshare-affiliates' ),
			'panel'     => __( 'Partner Panel', 'splitshare-affiliates' ),
		);
	}

	private function f( $key, array $field ) {
		$field['id'] = 'ssa_settings[' . $key . ']';
		if ( ! isset( $field['default'] ) ) {
			$field['default'] = SSA_Install::default_options()[ $key ];
		}
		return $field;
	}

	public function get_settings_for_default_section() {
		return array(
			array( 'title' => __( 'Program rules', 'splitshare-affiliates' ), 'type' => 'title', 'id' => 'ssa_program', 'desc' => __( 'A fixed share of every sale is set aside; the partner decides how much of it is commission and how much is a follower discount.', 'splitshare-affiliates' ) ),
			$this->f( 'program_name', array( 'title' => __( 'Program name', 'splitshare-affiliates' ), 'type' => 'text', 'desc_tip' => __( 'Shown to partners in My Account and emails.', 'splitshare-affiliates' ) ) ),
			$this->f( 'default_share', array( 'title' => __( 'Default share (%)', 'splitshare-affiliates' ), 'type' => 'number', 'custom_attributes' => array( 'min' => 0, 'max' => 100, 'step' => '0.5' ) ) ),
			$this->f( 'default_commission_pct', array( 'title' => __( 'Default commission (%)', 'splitshare-affiliates' ), 'type' => 'number', 'custom_attributes' => array( 'min' => 0, 'max' => 100, 'step' => '0.5' ), 'desc_tip' => __( 'Split assigned to newly approved partners. Discount = share − commission.', 'splitshare-affiliates' ) ) ),
			$this->f( 'min_order', array( 'title' => __( 'Minimum order total', 'splitshare-affiliates' ), 'type' => 'number', 'custom_attributes' => array( 'min' => 0, 'step' => '0.01' ), 'desc_tip' => __( 'Commission and the partner code only apply to orders at or above this amount (after discount, excluding shipping).', 'splitshare-affiliates' ) ) ),
			$this->f( 'max_commission_per_order', array( 'title' => __( 'Commission cap per order', 'splitshare-affiliates' ), 'type' => 'number', 'custom_attributes' => array( 'min' => 0, 'step' => '0.01' ) ) ),
			$this->f( 'rounding', array( 'title' => __( 'Commission rounding', 'splitshare-affiliates' ), 'type' => 'select', 'options' => array( 'lira' => __( 'Whole currency units', 'splitshare-affiliates' ), 'kurus' => __( 'Two decimals', 'splitshare-affiliates' ) ) ) ),
			$this->f( 'split_change_interval_days', array( 'title' => __( 'Split change interval (days)', 'splitshare-affiliates' ), 'type' => 'number', 'custom_attributes' => array( 'min' => 0 ), 'desc_tip' => __( 'How often a partner may change their split. 0 = unlimited.', 'splitshare-affiliates' ) ) ),
			$this->f( 'returning_customer_factor', array( 'title' => __( 'Returning customer factor', 'splitshare-affiliates' ), 'type' => 'number', 'custom_attributes' => array( 'min' => 0, 'max' => 1, 'step' => '0.05' ), 'desc_tip' => __( 'Commission multiplier when the customer has ordered before (1 = full commission).', 'splitshare-affiliates' ) ) ),
			$this->f( 'tiers', array( 'title' => __( 'Partner tiers', 'splitshare-affiliates' ), 'type' => 'ssa_tiers', 'desc' => __( 'Non-monetary statuses by number of approved sales.', 'splitshare-affiliates' ) ) ),
			$this->f( 'tier_benefits', array( 'title' => __( 'Tier benefits text', 'splitshare-affiliates' ), 'type' => 'textarea', 'css' => 'width:100%;max-width:600px;height:80px' ) ),
			$this->f( 'legal_notice', array( 'title' => __( 'Legal notice for partners', 'splitshare-affiliates' ), 'type' => 'textarea', 'css' => 'width:100%;max-width:600px;height:80px' ) ),
			array( 'type' => 'sectionend', 'id' => 'ssa_program' ),
		);
	}

	public function get_settings_for_groups_section() {
		return array(
			array( 'title' => __( 'Product groups', 'splitshare-affiliates' ), 'type' => 'title', 'id' => 'ssa_groups', 'desc' => __( 'Categories with a share different from the default. Child categories inherit. If a product matches several rows, the lowest share wins.', 'splitshare-affiliates' ) ),
			$this->f( 'group_shares', array( 'title' => __( 'Group shares', 'splitshare-affiliates' ), 'type' => 'ssa_group_shares' ) ),
			$this->f( 'excluded_categories', array( 'title' => __( 'Excluded categories', 'splitshare-affiliates' ), 'type' => 'multiselect', 'class' => 'wc-enhanced-select', 'options' => $this->category_options(), 'desc_tip' => __( 'No share and no partner discount (e.g. tobacco accessories, adult products).', 'splitshare-affiliates' ) ) ),
			array( 'type' => 'sectionend', 'id' => 'ssa_groups' ),
			array( 'title' => __( 'Campaigns & boosters', 'splitshare-affiliates' ), 'type' => 'title', 'id' => 'ssa_campaigns' ),
			$this->f( 'campaign_periods', array( 'title' => __( 'Campaign periods', 'splitshare-affiliates' ), 'type' => 'ssa_periods', 'desc' => __( 'Temporarily raise the share for all groups (e.g. back-to-school, New Year).', 'splitshare-affiliates' ) ) ),
			$this->f( 'boosters', array( 'title' => __( 'Product boosters', 'splitshare-affiliates' ), 'type' => 'ssa_boosters', 'desc' => __( 'Extra share on selected products for a period; shown to partners as "products earning extra this week".', 'splitshare-affiliates' ) ) ),
			array( 'type' => 'sectionend', 'id' => 'ssa_campaigns' ),
		);
	}

	public function get_settings_for_payouts_section() {
		return array(
			array( 'title' => __( 'Attribution', 'splitshare-affiliates' ), 'type' => 'title', 'id' => 'ssa_attr' ),
			$this->f( 'cookie_days', array( 'title' => __( 'Referral link validity (days)', 'splitshare-affiliates' ), 'type' => 'number', 'custom_attributes' => array( 'min' => 1 ) ) ),
			$this->f( 'coupon_usage_limit_per_user', array( 'title' => __( 'Code uses per customer', 'splitshare-affiliates' ), 'type' => 'number', 'custom_attributes' => array( 'min' => 0 ), 'desc_tip' => __( '0 = unlimited.', 'splitshare-affiliates' ) ) ),
			$this->f( 'code_rotation_days', array( 'title' => __( 'Code rotation (days)', 'splitshare-affiliates' ), 'type' => 'number', 'custom_attributes' => array( 'min' => 0 ), 'desc_tip' => __( 'Automatically issue a new code after this many days (leak protection). 0 = off.', 'splitshare-affiliates' ) ) ),
			$this->f( 'code_grace_days', array( 'title' => __( 'Old code grace (days)', 'splitshare-affiliates' ), 'type' => 'number', 'custom_attributes' => array( 'min' => 0 ) ) ),
			array( 'type' => 'sectionend', 'id' => 'ssa_attr' ),
			array( 'title' => __( 'Payouts', 'splitshare-affiliates' ), 'type' => 'title', 'id' => 'ssa_pay' ),
			$this->f( 'hold_days', array( 'title' => __( 'Hold period (days)', 'splitshare-affiliates' ), 'type' => 'number', 'custom_attributes' => array( 'min' => 0 ), 'desc_tip' => __( 'Commissions are approved this many days after the order is completed (return window).', 'splitshare-affiliates' ) ) ),
			$this->f( 'payout_day', array( 'title' => __( 'Payout day of month', 'splitshare-affiliates' ), 'type' => 'number', 'custom_attributes' => array( 'min' => 1, 'max' => 28 ) ) ),
			$this->f( 'min_payout', array( 'title' => __( 'Minimum payout', 'splitshare-affiliates' ), 'type' => 'number', 'custom_attributes' => array( 'min' => 0, 'step' => '0.01' ), 'desc_tip' => __( 'Balances below this carry over to the next month.', 'splitshare-affiliates' ) ) ),
			$this->f( 'deduct_refunds_from_next_payout', array( 'title' => __( 'Deduct refunds of paid commissions from the next payout', 'splitshare-affiliates' ), 'type' => 'checkbox' ) ),
			array( 'type' => 'sectionend', 'id' => 'ssa_pay' ),
		);
	}

	public function get_settings_for_panel_section() {
		$pages = array( 0 => __( '— Select —', 'splitshare-affiliates' ) );
		foreach ( get_pages( array( 'number' => 500 ) ) as $p ) {
			$pages[ $p->ID ] = $p->post_title;
		}
		return array(
			array( 'title' => __( 'Partner panel', 'splitshare-affiliates' ), 'type' => 'title', 'id' => 'ssa_panel' ),
			$this->f( 'apply_page_id', array( 'title' => __( 'Application page', 'splitshare-affiliates' ), 'type' => 'select', 'class' => 'wc-enhanced-select', 'options' => $pages, 'desc_tip' => __( 'Page containing the [ssa_apply] shortcode.', 'splitshare-affiliates' ) ) ),
			$this->f( 'show_join_card', array( 'title' => __( 'Show "become a partner" card in My Account', 'splitshare-affiliates' ), 'type' => 'checkbox' ) ),
			$this->f( 'endpoints', array( 'title' => __( 'Panel endpoints', 'splitshare-affiliates' ), 'type' => 'ssa_endpoints', 'desc' => __( 'URL slugs under My Account.', 'splitshare-affiliates' ) ) ),
			$this->f( 'kit_attachments', array( 'title' => __( 'Content kit media', 'splitshare-affiliates' ), 'type' => 'ssa_media_ids', 'desc' => __( 'Images/videos partners can download.', 'splitshare-affiliates' ) ) ),
			$this->f( 'kit_texts', array( 'title' => __( 'Content kit texts', 'splitshare-affiliates' ), 'type' => 'textarea', 'css' => 'width:100%;max-width:600px;height:140px', 'desc' => __( 'Ready-made captions; separate entries with a blank line.', 'splitshare-affiliates' ) ) ),
			array( 'type' => 'sectionend', 'id' => 'ssa_panel' ),
		);
	}

	/* ---------- Özel alanlar ---------- */

	private function category_options() {
		$out = array();
		foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) as $t ) {
			$out[ $t->term_id ] = $t->name;
		}
		return $out;
	}

	private function rows_table( $field, array $columns, array $rows, $template_row ) {
		$name = esc_attr( $field['id'] );
		echo '<tr valign="top"><th scope="row" class="titledesc"><label>' . esc_html( $field['title'] ) . '</label></th><td class="forminp">';
		echo '<table class="ssa-rows widefat" data-name="' . $name . '"><thead><tr>';
		foreach ( $columns as $c ) {
			echo '<th>' . esc_html( $c ) . '</th>';
		}
		echo '<th></th></tr></thead><tbody>';
		foreach ( $rows as $i => $row ) {
			echo '<tr>' . $template_row( $i, $row ) . '<td><a href="#" class="ssa-row-remove">&times;</a></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</tbody></table>';
		echo '<script type="text/template" class="ssa-row-template"><tr>' . $template_row( '__i__', array() ) . '<td><a href="#" class="ssa-row-remove">&times;</a></td></tr></script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<p><a href="#" class="button ssa-row-add">' . esc_html__( 'Add row', 'splitshare-affiliates' ) . '</a>';
		if ( ! empty( $field['desc'] ) ) {
			echo ' <span class="description">' . esc_html( $field['desc'] ) . '</span>';
		}
		echo '</p></td></tr>';
	}

	public function render_group_shares( $field ) {
		$cats = $this->category_options();
		$this->rows_table( $field, array( __( 'Category', 'splitshare-affiliates' ), __( 'Share (%)', 'splitshare-affiliates' ) ), (array) SSA_Settings::get( 'group_shares', array() ), function ( $i, $row ) use ( $cats, $field ) {
			$n = esc_attr( $field['id'] ) . '[' . $i . ']';
			$h = '<td><select name="' . $n . '[category]"><option value="0">—</option>';
			foreach ( $cats as $id => $name ) {
				$h .= '<option value="' . (int) $id . '"' . selected( (int) ( $row['category'] ?? 0 ), (int) $id, false ) . '>' . esc_html( $name ) . '</option>';
			}
			$h .= '</select></td><td><input type="number" step="0.5" min="0" max="100" name="' . $n . '[pct]" value="' . esc_attr( $row['pct'] ?? '' ) . '" /></td>';
			return $h;
		} );
	}

	public function render_periods( $field ) {
		$this->rows_table( $field, array( __( 'From', 'splitshare-affiliates' ), __( 'To', 'splitshare-affiliates' ), __( 'Share (%)', 'splitshare-affiliates' ) ), (array) SSA_Settings::get( 'campaign_periods', array() ), function ( $i, $row ) use ( $field ) {
			$n = esc_attr( $field['id'] ) . '[' . $i . ']';
			return '<td><input type="date" name="' . $n . '[from]" value="' . esc_attr( $row['from'] ?? '' ) . '" /></td><td><input type="date" name="' . $n . '[to]" value="' . esc_attr( $row['to'] ?? '' ) . '" /></td><td><input type="number" step="0.5" min="0" max="100" name="' . $n . '[share]" value="' . esc_attr( $row['share'] ?? '' ) . '" /></td>';
		} );
	}

	public function render_boosters( $field ) {
		$this->rows_table( $field, array( __( 'Product IDs (comma separated)', 'splitshare-affiliates' ), __( 'Extra share (%)', 'splitshare-affiliates' ), __( 'From', 'splitshare-affiliates' ), __( 'To', 'splitshare-affiliates' ) ), (array) SSA_Settings::get( 'boosters', array() ), function ( $i, $row ) use ( $field ) {
			$n   = esc_attr( $field['id'] ) . '[' . $i . ']';
			$ids = isset( $row['product_ids'] ) ? implode( ',', (array) $row['product_ids'] ) : '';
			return '<td><input type="text" class="regular-text" name="' . $n . '[product_ids]" value="' . esc_attr( $ids ) . '" placeholder="12, 34" /></td><td><input type="number" step="0.5" min="0" max="100" name="' . $n . '[pct]" value="' . esc_attr( $row['pct'] ?? '' ) . '" /></td><td><input type="date" name="' . $n . '[from]" value="' . esc_attr( $row['from'] ?? '' ) . '" /></td><td><input type="date" name="' . $n . '[to]" value="' . esc_attr( $row['to'] ?? '' ) . '" /></td>';
		} );
	}

	public function render_tiers( $field ) {
		$this->rows_table( $field, array( __( 'Tier name', 'splitshare-affiliates' ), __( 'Min. approved sales', 'splitshare-affiliates' ) ), (array) SSA_Settings::get( 'tiers', array() ), function ( $i, $row ) use ( $field ) {
			$n = esc_attr( $field['id'] ) . '[' . $i . ']';
			return '<td><input type="text" name="' . $n . '[name]" value="' . esc_attr( $row['name'] ?? '' ) . '" /></td><td><input type="number" min="0" name="' . $n . '[min_sales]" value="' . esc_attr( $row['min_sales'] ?? '' ) . '" /></td>';
		} );
	}

	public function render_endpoints( $field ) {
		$labels = array( 'dashboard' => __( 'Dashboard', 'splitshare-affiliates' ), 'sales' => __( 'Sales', 'splitshare-affiliates' ), 'earnings' => __( 'Earnings', 'splitshare-affiliates' ), 'split' => __( 'Split', 'splitshare-affiliates' ), 'links' => __( 'Links', 'splitshare-affiliates' ), 'kit' => __( 'Content kit', 'splitshare-affiliates' ) );
		$eps    = (array) SSA_Settings::get( 'endpoints', array() );
		echo '<tr valign="top"><th scope="row" class="titledesc"><label>' . esc_html( $field['title'] ) . '</label></th><td class="forminp"><table class="widefat ssa-rows-static"><tbody>';
		foreach ( $labels as $key => $label ) {
			echo '<tr><td>' . esc_html( $label ) . '</td><td><input type="text" name="' . esc_attr( $field['id'] ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $eps[ $key ] ?? '' ) . '" /></td></tr>';
		}
		echo '</tbody></table><p class="description">' . esc_html( $field['desc'] ) . '</p></td></tr>';
	}

	public function render_media_ids( $field ) {
		$ids = implode( ',', array_map( 'intval', (array) SSA_Settings::get( 'kit_attachments', array() ) ) );
		echo '<tr valign="top"><th scope="row" class="titledesc"><label>' . esc_html( $field['title'] ) . '</label></th><td class="forminp">';
		echo '<input type="text" class="regular-text ssa-media-ids" name="' . esc_attr( $field['id'] ) . '" value="' . esc_attr( $ids ) . '" /> <a href="#" class="button ssa-media-pick">' . esc_html__( 'Select media', 'splitshare-affiliates' ) . '</a>';
		echo '<p class="description">' . esc_html( $field['desc'] ) . '</p></td></tr>';
	}

	/* ---------- Sanitize ---------- */

	private function clean_rows( $value, array $keys ) {
		$out = array();
		foreach ( (array) $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean = array();
			foreach ( $keys as $k => $type ) {
				$v = isset( $row[ $k ] ) ? wp_unslash( $row[ $k ] ) : '';
				$clean[ $k ] = ( 'float' === $type ) ? (float) $v : ( ( 'int' === $type ) ? (int) $v : sanitize_text_field( $v ) );
			}
			$out[] = $clean;
		}
		return $out;
	}

	public function sanitize_group_shares( $value ) {
		return array_values( array_filter( $this->clean_rows( $value, array( 'category' => 'int', 'pct' => 'float' ) ), function ( $r ) { return $r['category'] > 0; } ) );
	}

	public function sanitize_campaign_periods( $value ) {
		return $this->clean_rows( $value, array( 'from' => 'text', 'to' => 'text', 'share' => 'float' ) );
	}

	public function sanitize_boosters( $value ) {
		$rows = $this->clean_rows( $value, array( 'product_ids' => 'text', 'pct' => 'float', 'from' => 'text', 'to' => 'text' ) );
		foreach ( $rows as &$r ) {
			$r['product_ids'] = array_values( array_filter( array_map( 'intval', explode( ',', $r['product_ids'] ) ) ) );
		}
		return $rows;
	}

	public function sanitize_tiers( $value ) {
		return array_values( array_filter( $this->clean_rows( $value, array( 'name' => 'text', 'min_sales' => 'int' ) ), function ( $r ) { return '' !== $r['name']; } ) );
	}

	public function sanitize_endpoints( $value ) {
		$out = array();
		foreach ( (array) $value as $k => $v ) {
			$out[ sanitize_key( $k ) ] = sanitize_title( wp_unslash( $v ) );
		}
		return $out;
	}

	public function sanitize_kit_attachments( $value ) {
		return array_values( array_filter( array_map( 'intval', explode( ',', (string) $value ) ) ) );
	}
}
