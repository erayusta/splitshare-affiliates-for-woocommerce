<?php
/**
 * Admin UI bileşenleri: sayfa başlığı, KPI kartı, tarih aralığı, toolbar, boş durum, avatar, rozet.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Admin_UI {

	/** Tarih aralığı: ?range=this_month|last_month|30d|90d|year|custom (+from/to). */
	public static function range() {
		$range = isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : '30d'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$now   = current_time( 'timestamp' );
		$to    = gmdate( 'Y-m-d', $now );
		switch ( $range ) {
			case 'this_month':
				$from = gmdate( 'Y-m-01', $now );
				break;
			case 'last_month':
				$from = gmdate( 'Y-m-01', strtotime( 'first day of last month', $now ) );
				$to   = gmdate( 'Y-m-t', strtotime( 'first day of last month', $now ) );
				break;
			case '90d':
				$from = gmdate( 'Y-m-d', $now - 89 * DAY_IN_SECONDS );
				break;
			case 'year':
				$from = gmdate( 'Y-01-01', $now );
				break;
			case 'custom':
				$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : gmdate( 'Y-m-01', $now ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$to   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : $to; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				break;
			default:
				$range = '30d';
				$from  = gmdate( 'Y-m-d', $now - 29 * DAY_IN_SECONDS );
		}
		$days      = max( 1, (int) round( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS ) + 1 );
		$prev_to   = gmdate( 'Y-m-d', strtotime( $from ) - DAY_IN_SECONDS );
		$prev_from = gmdate( 'Y-m-d', strtotime( $prev_to ) - ( $days - 1 ) * DAY_IN_SECONDS );
		return compact( 'range', 'from', 'to', 'prev_from', 'prev_to', 'days' );
	}

	public static function range_bar( $tab, array $r ) {
		$presets = array(
			'this_month' => __( 'This month', 'splitshare-affiliates' ),
			'last_month' => __( 'Last month', 'splitshare-affiliates' ),
			'30d'        => __( 'Last 30 days', 'splitshare-affiliates' ),
			'90d'        => __( 'Last 90 days', 'splitshare-affiliates' ),
			'year'       => __( 'This year', 'splitshare-affiliates' ),
		);
		$html = '<div class="ssa-rangebar"><div class="ssa-rangebar__presets">';
		foreach ( $presets as $key => $label ) {
			$html .= '<a class="ssa-chip' . ( $r['range'] === $key ? ' is-active' : '' ) . '" href="' . esc_url( SSA_Admin_Menu::url( $tab, array( 'range' => $key ) ) ) . '">' . esc_html( $label ) . '</a>';
		}
		$html .= '</div><form method="get" class="ssa-rangebar__custom"><input type="hidden" name="page" value="' . esc_attr( SSA_Admin_Menu::SLUG ) . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '"><input type="hidden" name="range" value="custom">';
		$html .= '<input type="date" name="from" value="' . esc_attr( $r['from'] ) . '"><span>–</span><input type="date" name="to" value="' . esc_attr( $r['to'] ) . '"><button class="button">' . esc_html__( 'Apply', 'splitshare-affiliates' ) . '</button></form></div>';
		return $html;
	}

	/**
	 * KPI kartı.
	 * @param array $a label, value (html), delta (float|null, %), delta_label, hint, good_up (bool), spark (array|null), icon
	 */
	public static function kpi( array $a ) {
		$a    = wp_parse_args( $a, array( 'label' => '', 'value' => '', 'delta' => null, 'delta_label' => '', 'hint' => '', 'good_up' => true, 'spark' => null, 'icon' => '', 'tone' => '' ) );
		$html = '<div class="ssa-kpi' . ( $a['tone'] ? ' ssa-kpi--' . esc_attr( $a['tone'] ) : '' ) . '">';
		$html .= '<div class="ssa-kpi__label">' . ( $a['icon'] ? '<span class="ssa-kpi__icon">' . $a['icon'] . '</span>' : '' ) . esc_html( $a['label'] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$html .= '<div class="ssa-kpi__row"><div class="ssa-kpi__value">' . wp_kses_post( $a['value'] ) . '</div>';
		if ( is_array( $a['spark'] ) && count( $a['spark'] ) > 1 ) {
			$html .= SSA_Charts::sparkline( $a['spark'], array( 'class' => 'ssa-s1' ) );
		}
		$html .= '</div>';
		if ( null !== $a['delta'] ) {
			$up   = $a['delta'] >= 0;
			$good = $up === (bool) $a['good_up'];
			$html .= '<div class="ssa-kpi__delta ' . ( $good ? 'is-good' : 'is-bad' ) . '"><span class="ssa-kpi__arrow">' . ( $up ? '▲' : '▼' ) . '</span> ' . esc_html( number_format_i18n( abs( $a['delta'] ), 1 ) . '%' ) . ' <span class="ssa-kpi__vs">' . esc_html( $a['delta_label'] ) . '</span></div>';
		} elseif ( $a['hint'] ) {
			$html .= '<div class="ssa-kpi__hint">' . esc_html( $a['hint'] ) . '</div>';
		}
		return $html . '</div>';
	}

	public static function delta( $current, $previous ) {
		if ( (float) $previous == 0.0 ) { // phpcs:ignore Universal.Operators.StrictComparisons
			return null;
		}
		return ( (float) $current - (float) $previous ) / abs( (float) $previous ) * 100;
	}

	public static function card_open( $title = '', $actions = '', $class = '' ) {
		return '<section class="ssa-card ' . esc_attr( $class ) . '">' . ( $title ? '<header class="ssa-card__head"><h2>' . esc_html( $title ) . '</h2>' . $actions . '</header>' : '' ) . '<div class="ssa-card__body">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function card_close() {
		return '</div></section>';
	}

	public static function empty_state( $title, $text = '', $cta_html = '' ) {
		return '<div class="ssa-empty"><div class="ssa-empty__icon">' . self::icon( 'inbox' ) . '</div><h3>' . esc_html( $title ) . '</h3>' . ( $text ? '<p>' . esc_html( $text ) . '</p>' : '' ) . $cta_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function avatar( SSA_Partner $p, $size = 'sm' ) {
		$parts = preg_split( '/\s+/', trim( $p->display_name() ) );
		$ini   = mb_strtoupper( mb_substr( $parts[0], 0, 1 ) . ( count( $parts ) > 1 ? mb_substr( end( $parts ), 0, 1 ) : '' ) );
		$hue   = crc32( $p->code . $p->id ) % 360;
		return '<span class="ssa-avatar ssa-avatar--' . esc_attr( $size ) . '" style="--h:' . (int) $hue . '">' . esc_html( $ini ) . '</span>';
	}

	public static function code_chip( $code ) {
		return '<span class="ssa-codechip"><code>' . esc_html( $code ) . '</code><button type="button" class="ssa-copy" data-copy="' . esc_attr( $code ) . '" title="' . esc_attr__( 'Copy', 'splitshare-affiliates' ) . '">' . self::icon( 'copy' ) . '</button></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function badge( $status ) {
		$map = array(
			'active'   => array( __( 'Active', 'splitshare-affiliates' ), 'good' ),
			'approved' => array( __( 'Approved', 'splitshare-affiliates' ), 'good' ),
			'paid'     => array( __( 'Paid', 'splitshare-affiliates' ), 'good' ),
			'pending'  => array( __( 'Pending', 'splitshare-affiliates' ), 'warn' ),
			'open'     => array( __( 'Open', 'splitshare-affiliates' ), 'warn' ),
			'paused'   => array( __( 'Paused', 'splitshare-affiliates' ), 'muted' ),
			'void'     => array( __( 'Void', 'splitshare-affiliates' ), 'bad' ),
			'rejected' => array( __( 'Rejected', 'splitshare-affiliates' ), 'bad' ),
		);
		$m = isset( $map[ $status ] ) ? $map[ $status ] : array( ucfirst( $status ), 'muted' );
		return '<span class="ssa-pill ssa-pill--' . esc_attr( $m[1] ) . '"><i></i>' . esc_html( $m[0] ) . '</span>';
	}

	public static function toolbar_open() {
		return '<div class="ssa-toolbar">';
	}

	public static function toolbar_close() {
		return '</div>';
	}

	/** Basit satır içi ikonlar (24px viewBox). */
	public static function icon( $name ) {
		$icons = array(
			'copy'     => '<path d="M8 8h11v11H8z"/><path d="M5 16V5h11"/>',
			'inbox'    => '<path d="M3 13h5l2 3h4l2-3h5"/><path d="M5 5h14l2 8v6H3v-6z"/>',
			'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
			'revenue'  => '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>',
			'wallet'   => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="16" cy="12" r="2"/>',
			'percent'  => '<path d="M19 5 5 19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
			'new'      => '<path d="M12 5v14M5 12h14"/>',
			'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'alert'    => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
			'link'     => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
			'external' => '<path d="M14 4h6v6"/><path d="M20 4 10 14"/><path d="M18 13v7H4V6h7"/>',
		);
		return '<svg class="ssa-icon ssa-icon--' . esc_attr( $name ) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( isset( $icons[ $name ] ) ? $icons[ $name ] : '' ) . '</svg>';
	}
}
