<?php
/**
 * Sipariş ekranında "Affiliate" kutusu: ortak, kod, atıf, komisyon; yeniden hesapla.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Admin_Order {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_box' ), 30, 2 );
		add_action( 'admin_post_ssa_recalculate', array( __CLASS__, 'recalculate' ) );
	}

	public static function add_box( $post_type_or_screen, $post_or_order = null ) {
		$screen = class_exists( 'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( 'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';
		add_meta_box( 'ssa-order', __( 'Affiliate', 'splitshare-affiliates' ), array( __CLASS__, 'render' ), $screen, 'side', 'default' );
	}

	public static function render( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		$partner_id = (int) $order->get_meta( '_ssa_partner_id' );
		echo '<div class="ssa-admin-box">';
		if ( ! $partner_id ) {
			echo '<p class="ssa-muted">' . esc_html__( 'No affiliate attribution.', 'splitshare-affiliates' ) . '</p></div>';
			return;
		}
		$partner    = SSA_Partners::get( $partner_id );
		$commission = class_exists( 'SSA_Commissions' ) ? SSA_Commissions::for_order( $order->get_id() ) : null;
		echo '<p><strong>' . esc_html__( 'Partner:', 'splitshare-affiliates' ) . '</strong> ' . ( $partner ? SSA_Admin_Menu::partner_link( $partner ) : '#' . (int) $partner_id ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$attr = (string) $order->get_meta( '_ssa_attribution' );
		$row  = (int) $order->get_meta( '_ssa_coupon_id' ) ? SSA_Partner_Coupons::get( (int) $order->get_meta( '_ssa_coupon_id' ) ) : null;
		echo '<p><strong>' . esc_html( 'coupon' === $attr ? __( 'Coupon:', 'splitshare-affiliates' ) : __( 'Link code:', 'splitshare-affiliates' ) ) . '</strong> <code>' . esc_html( $order->get_meta( '_ssa_code' ) ) . '</code>' . ( $row ? ' · ' . esc_html( wc_format_decimal( $row->discount_pct, 1 ) . '% · ' . SSA_Partner_Coupons::scope_label( $row ) ) : '' ) . '</p>';
		if ( $commission ) {
			echo '<p><strong>' . esc_html__( 'Commission:', 'splitshare-affiliates' ) . '</strong> ' . wp_kses_post( wc_price( $commission->amount ) ) . ' ' . SSA_Admin_Menu::badge( $commission->status ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( $commission->available_at && 'pending' === $commission->status ) {
				echo '<p class="ssa-muted">' . esc_html( sprintf( __( 'Approves on %s', 'splitshare-affiliates' ), date_i18n( get_option( 'date_format' ), strtotime( $commission->available_at ) ) ) ) . '</p>';
			}
			if ( $commission->reason ) {
				echo '<p class="ssa-muted">' . esc_html( SSA_Commissions::reason_label( $commission->reason ) ) . '</p>';
			}
		} else {
			echo '<p class="ssa-muted">' . esc_html__( 'Commission is recorded when the order is paid.', 'splitshare-affiliates' ) . '</p>';
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=ssa_recalculate&order_id=' . $order->get_id() ), 'ssa_recalculate_' . $order->get_id() );
		echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Recalculate', 'splitshare-affiliates' ) . '</a></p></div>';
	}

	public static function recalculate() {
		$order_id = isset( $_GET['order_id'] ) ? (int) $_GET['order_id'] : 0;
		if ( ! $order_id || ! current_user_can( 'ssa_manage' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'splitshare-affiliates' ) );
		}
		check_admin_referer( 'ssa_recalculate_' . $order_id );
		if ( class_exists( 'SSA_Commissions' ) ) {
			SSA_Commissions::recalculate( $order_id );
		}
		$order = wc_get_order( $order_id );
		wp_safe_redirect( $order ? $order->get_edit_order_url() : admin_url() );
		exit;
	}
}
