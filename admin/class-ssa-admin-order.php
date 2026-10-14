<?php
/**
 * Sipariş ekranında ortaklık bilgisi:
 *  - sipariş LİSTESİNDE sütun (hangi sipariş hangi ortaktan geldi),
 *  - sipariş DETAYINDA kutu (ortak, kod, kapsam, komisyon; yeniden hesapla).
 *
 * 2026-09-23: Liste sütunu eklendi. Bilgi yalnızca detay sayfasının kenarındaki
 * kutuda duruyordu; sipariş listesine bakan kişi bir siparişin ortaktan geldiğini
 * ve kime bağlı olduğunu göremiyordu.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Admin_Order {

	const COLUMN = 'ssa_partner';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_box' ), 30, 2 );
		add_action( 'admin_post_ssa_recalculate', array( __CLASS__, 'recalculate' ) );

		// Sipariş listesi sütunu — HPOS ve klasik (post tablosu) için ayrı hook'lar.
		add_filter( 'woocommerce_shop_order_list_table_columns', array( __CLASS__, 'columns' ) );
		add_action( 'woocommerce_shop_order_list_table_custom_column', array( __CLASS__, 'column_hpos' ), 10, 2 );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'column_legacy' ), 10, 2 );
		add_action( 'admin_head', array( __CLASS__, 'column_css' ) );
	}

	/** Sütun başlığı: program adı. Toplamdan önce eklenir. */
	public static function columns( $columns ) {
		if ( isset( $columns[ self::COLUMN ] ) ) {
			return $columns;
		}
		$yeni = array();
		foreach ( $columns as $anahtar => $baslik ) {
			if ( 'order_total' === $anahtar ) {
				$yeni[ self::COLUMN ] = SSA_Settings::get( 'program_name' );
			}
			$yeni[ $anahtar ] = $baslik;
		}
		if ( ! isset( $yeni[ self::COLUMN ] ) ) {
			$yeni[ self::COLUMN ] = SSA_Settings::get( 'program_name' );
		}
		return $yeni;
	}

	public static function column_hpos( $column, $order ) {
		if ( self::COLUMN === $column ) {
			self::render_column( $order instanceof WC_Order ? $order : wc_get_order( $order ) );
		}
	}

	public static function column_legacy( $column, $post_id ) {
		if ( self::COLUMN === $column ) {
			self::render_column( wc_get_order( $post_id ) );
		}
	}

	/** Hücre: ortak adı (profiline link), kod/atıf, komisyon. */
	private static function render_column( $order ) {
		$partner_id = $order ? (int) $order->get_meta( '_ssa_partner_id' ) : 0;
		if ( ! $partner_id ) {
			echo '<span class="ssa-col-empty">&mdash;</span>';
			return;
		}
		$partner = SSA_Partners::get( $partner_id );
		// Liste hücresi dar: avatar/e-posta yok, sadece ad + profil linki.
		echo '<strong>' . ( $partner
			? '<a href="' . esc_url( SSA_Admin_Menu::url( 'partners', array( 'action' => 'edit', 'id' => $partner->id ) ) ) . '">' . esc_html( $partner->display_name() ) . '</a>'
			: '#' . (int) $partner_id ) . '</strong>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$kod  = (string) $order->get_meta( '_ssa_code' );
		$attr = 'coupon' === (string) $order->get_meta( '_ssa_attribution' )
			? __( 'coupon', 'splitshare-affiliates' )
			: __( 'link', 'splitshare-affiliates' );
		if ( $kod ) {
			echo '<br /><small class="ssa-col-code"><code>' . esc_html( $kod ) . '</code> · ' . esc_html( $attr ) . '</small>';
		}

		$commission = class_exists( 'SSA_Commissions' ) ? SSA_Commissions::for_order( $order->get_id() ) : null;
		if ( $commission ) {
			echo '<br /><small class="ssa-col-amount">' . wp_kses_post( wc_price( $commission->amount ) ) . ' ' . SSA_Admin_UI::badge( $commission->status, $commission->reason ) . '</small>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/** Sütun dar kalsın, satırlar sıkışık görünsün. */
	public static function column_css() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'shop_order' ) ) {
			return;
		}
		echo '<style>.column-' . esc_attr( self::COLUMN ) . '{width:12em}.column-' . esc_attr( self::COLUMN ) . ' small{display:inline-block;margin-top:2px;line-height:1.5}.ssa-col-empty{color:#c3c4c7}</style>';
	}

	public static function add_box( $post_type_or_screen, $post_or_order = null ) {
		$screen = class_exists( 'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( 'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';
		// Başlık program adı olsun ("Ortaklık" jenerik kalıyordu, kutu gözden kaçıyordu).
		add_meta_box( 'ssa-order', SSA_Settings::get( 'program_name' ), array( __CLASS__, 'render' ), $screen, 'side', 'high' );
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
		echo '<p class="ssa-order-partner">' . esc_html__( 'This order came through a partner:', 'splitshare-affiliates' ) . '</p>';
		echo '<p>' . ( $partner ? SSA_Admin_Menu::partner_link( $partner ) : '#' . (int) $partner_id ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$attr = (string) $order->get_meta( '_ssa_attribution' );
		$row  = (int) $order->get_meta( '_ssa_coupon_id' ) ? SSA_Partner_Coupons::get( (int) $order->get_meta( '_ssa_coupon_id' ) ) : null;
		echo '<p><strong>' . esc_html( 'coupon' === $attr ? __( 'Coupon:', 'splitshare-affiliates' ) : __( 'Link code:', 'splitshare-affiliates' ) ) . '</strong> <code>' . esc_html( $order->get_meta( '_ssa_code' ) ) . '</code>' . ( $row ? ' · ' . esc_html( wc_format_decimal( $row->discount_pct, 1 ) . '% · ' . SSA_Partner_Coupons::scope_label( $row ) ) : '' ) . '</p>';
		if ( $commission ) {
			echo '<p><strong>' . esc_html__( 'Commission:', 'splitshare-affiliates' ) . '</strong> ' . wp_kses_post( wc_price( $commission->amount ) ) . ' ' . SSA_Admin_UI::badge( $commission->status, $commission->reason ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
