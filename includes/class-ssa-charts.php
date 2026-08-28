<?php
/**
 * Kütüphanesiz inline SVG grafikler: sütun (gruplu), donut, sparkline, dağılım çubuğu.
 * Renkler CSS değişkenlerinden (--ssa-s1..s3) gelir; her grafik <title>/<desc> taşır ve
 * hover için veri özniteliklerini JS tooltip'e bırakır (assets/js/ssa-charts.js).
 */

defined( 'ABSPATH' ) || exit;

class SSA_Charts {

	/** Kompakt sayı: 1.284 / 12,9K / 4,2M */
	public static function compact( $n ) {
		$n = (float) $n;
		if ( abs( $n ) >= 1000000 ) {
			return number_format_i18n( $n / 1000000, 1 ) . 'M';
		}
		if ( abs( $n ) >= 10000 ) {
			return number_format_i18n( $n / 1000, 1 ) . 'K';
		}
		return number_format_i18n( $n, 0 );
	}

	/** Y ekseni için temiz üst sınır ve adım. */
	private static function nice_max( $max, $ticks = 4 ) {
		if ( $max <= 0 ) {
			return array( 1, 1 );
		}
		$raw  = $max / $ticks;
		$pow  = pow( 10, floor( log10( $raw ) ) );
		$norm = $raw / $pow;
		$step = ( $norm <= 1 ? 1 : ( $norm <= 2 ? 2 : ( $norm <= 5 ? 5 : 10 ) ) ) * $pow;
		return array( $step * $ticks, $step );
	}

	/**
	 * Gruplu sütun grafiği.
	 *
	 * @param array $labels  X etiketleri.
	 * @param array $series  [ ['name'=>'Ciro','values'=>[...], 'format'=>'money'|'int'|'pct'], ... ] (en fazla 3)
	 * @param array $opts    height, title, desc, id
	 */
	public static function columns( array $labels, array $series, array $opts = array() ) {
		$opts   = wp_parse_args( $opts, array( 'height' => 220, 'width' => 720, 'title' => '', 'desc' => '', 'id' => 'ssa-chart-' . wp_rand( 1000, 9999 ), 'format' => 'money' ) );
		$w      = (int) $opts['width'];
		$h      = (int) $opts['height'];
		$padL   = 52;
		$padR   = 12;
		$padT   = 12;
		$padB   = 28;
		$plotW  = $w - $padL - $padR;
		$plotH  = $h - $padT - $padB;
		$n      = max( 1, count( $labels ) );
		$max    = 0;
		foreach ( $series as $s ) {
			$max = max( $max, $s['values'] ? max( $s['values'] ) : 0 );
		}
		list( $ymax, $step ) = self::nice_max( $max );
		$slot   = $plotW / $n;
		$k      = max( 1, count( $series ) );
		$barW   = min( 24, ( $slot * 0.7 - 2 * ( $k - 1 ) ) / $k );
		$groupW = $barW * $k + 2 * ( $k - 1 );

		$svg  = '<figure class="ssa-chart ssa-chart--columns" id="' . esc_attr( $opts['id'] ) . '">';
		$svg .= '<svg viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-labelledby="' . esc_attr( $opts['id'] ) . '-t ' . esc_attr( $opts['id'] ) . '-d" preserveAspectRatio="xMidYMid meet">';
		$svg .= '<title id="' . esc_attr( $opts['id'] ) . '-t">' . esc_html( $opts['title'] ) . '</title><desc id="' . esc_attr( $opts['id'] ) . '-d">' . esc_html( $opts['desc'] ) . '</desc>';
		// grid + y ticks
		for ( $v = 0; $v <= $ymax + 0.0001; $v += $step ) {
			$y = $padT + $plotH - ( $v / $ymax ) * $plotH;
			$svg .= '<line class="ssa-grid" x1="' . $padL . '" x2="' . ( $w - $padR ) . '" y1="' . round( $y, 1 ) . '" y2="' . round( $y, 1 ) . '"/>';
			$svg .= '<text class="ssa-tick" x="' . ( $padL - 8 ) . '" y="' . round( $y + 4, 1 ) . '" text-anchor="end">' . esc_html( self::compact( $v ) ) . '</text>';
		}
		// bars
		foreach ( $labels as $i => $label ) {
			$x0 = $padL + $slot * $i + ( $slot - $groupW ) / 2;
			foreach ( $series as $j => $s ) {
				$val = isset( $s['values'][ $i ] ) ? (float) $s['values'][ $i ] : 0;
				$bh  = $ymax > 0 ? ( $val / $ymax ) * $plotH : 0;
				$x   = $x0 + $j * ( $barW + 2 );
				$y   = $padT + $plotH - $bh;
				$r   = max( 0, min( 4, $bh / 2, $barW / 2 ) );
				$up  = round( $bh - $r, 1 );
				// Tabandan yukarı, üst köşeler yuvarlak (4px), taban köşeleri düz.
				$path = sprintf( 'M%1$s %2$s v-%3$s a%4$s %4$s 0 0 1 %4$s -%4$s h%5$s a%4$s %4$s 0 0 1 %4$s %4$s v%3$s z', round( $x, 1 ), round( $padT + $plotH, 1 ), $up, $r, round( $barW - 2 * $r, 1 ) );
				$svg .= '<path class="ssa-bar ssa-s' . ( $j + 1 ) . '" d="' . $path . '" data-label="' . esc_attr( $label ) . '" data-series="' . esc_attr( $s['name'] ) . '" data-value="' . esc_attr( self::format( $val, isset( $s['format'] ) ? $s['format'] : $opts['format'] ) ) . '"><title>' . esc_html( $label . ' · ' . $s['name'] . ': ' . self::format( $val, isset( $s['format'] ) ? $s['format'] : $opts['format'] ) ) . '</title></path>';
			}
			$svg .= '<text class="ssa-tick" x="' . round( $padL + $slot * $i + $slot / 2, 1 ) . '" y="' . ( $h - 10 ) . '" text-anchor="middle">' . esc_html( $label ) . '</text>';
		}
		$svg .= '<line class="ssa-axis" x1="' . $padL . '" x2="' . ( $w - $padR ) . '" y1="' . ( $padT + $plotH ) . '" y2="' . ( $padT + $plotH ) . '"/>';
		$svg .= '</svg>';
		if ( count( $series ) > 1 ) {
			$svg .= '<figcaption class="ssa-legend">';
			foreach ( $series as $j => $s ) {
				$svg .= '<span><i class="ssa-swatch ssa-s' . ( $j + 1 ) . '"></i>' . esc_html( $s['name'] ) . '</span>';
			}
			$svg .= '</figcaption>';
		}
		$svg .= self::table( $labels, $series, $opts );
		$svg .= '</figure>';
		return $svg;
	}

	/** Erişilebilirlik: grafik verisinin tablo görünümü (details içinde). */
	private static function table( array $labels, array $series, array $opts ) {
		$html = '<details class="ssa-chart-table"><summary>' . esc_html__( 'Show data', 'splitshare-affiliates' ) . '</summary><table><thead><tr><th></th>';
		foreach ( $series as $s ) {
			$html .= '<th>' . esc_html( $s['name'] ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';
		foreach ( $labels as $i => $label ) {
			$html .= '<tr><th>' . esc_html( $label ) . '</th>';
			foreach ( $series as $s ) {
				$html .= '<td>' . esc_html( self::format( isset( $s['values'][ $i ] ) ? $s['values'][ $i ] : 0, isset( $s['format'] ) ? $s['format'] : $opts['format'] ) ) . '</td>';
			}
			$html .= '</tr>';
		}
		return $html . '</tbody></table></details>';
	}

	public static function format( $v, $format = 'money' ) {
		switch ( $format ) {
			case 'int':
				return number_format_i18n( (float) $v, 0 );
			case 'pct':
				return number_format_i18n( (float) $v, 1 ) . '%';
			default:
				return function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $v ) ) : number_format_i18n( (float) $v, 2 );
		}
	}

	/**
	 * Donut. $parts: [ ['label'=>..,'value'=>..], ... ] (en fazla 3 + Diğer). Ortada başlık/değer.
	 */
	public static function donut( array $parts, array $opts = array() ) {
		$opts  = wp_parse_args( $opts, array( 'size' => 160, 'center' => '', 'center_label' => '', 'title' => '', 'id' => 'ssa-donut-' . wp_rand( 1000, 9999 ), 'format' => 'int' ) );
		$total = 0.0;
		foreach ( $parts as $p ) {
			$total += (float) $p['value'];
		}
		$size = (int) $opts['size'];
		$r    = $size / 2 - 12;
		$c    = 2 * M_PI * $r;
		$off  = 0.0;
		$svg  = '<figure class="ssa-chart ssa-chart--donut" id="' . esc_attr( $opts['id'] ) . '"><svg viewBox="0 0 ' . $size . ' ' . $size . '" role="img" aria-labelledby="' . esc_attr( $opts['id'] ) . '-t"><title id="' . esc_attr( $opts['id'] ) . '-t">' . esc_html( $opts['title'] ) . '</title>';
		$svg .= '<circle class="ssa-donut-track" cx="' . $size / 2 . '" cy="' . $size / 2 . '" r="' . $r . '"/>';
		foreach ( $parts as $i => $p ) {
			$frac = $total > 0 ? (float) $p['value'] / $total : 0;
			$len  = max( 0, $c * $frac - 2 );
			$svg .= '<circle class="ssa-donut-seg ssa-s' . ( $i + 1 ) . '" cx="' . $size / 2 . '" cy="' . $size / 2 . '" r="' . $r . '" stroke-dasharray="' . round( $len, 2 ) . ' ' . round( $c - $len, 2 ) . '" stroke-dashoffset="' . round( -$off, 2 ) . '" data-label="' . esc_attr( $p['label'] ) . '" data-value="' . esc_attr( self::format( $p['value'], $opts['format'] ) . ' (' . number_format_i18n( $frac * 100, 0 ) . '%)' ) . '"><title>' . esc_html( $p['label'] . ': ' . self::format( $p['value'], $opts['format'] ) ) . '</title></circle>';
			$off += $c * $frac;
		}
		if ( '' !== $opts['center'] ) {
			$svg .= '<text class="ssa-donut-center" x="' . $size / 2 . '" y="' . ( $size / 2 + 2 ) . '" text-anchor="middle">' . esc_html( $opts['center'] ) . '</text>';
			$svg .= '<text class="ssa-donut-sub" x="' . $size / 2 . '" y="' . ( $size / 2 + 18 ) . '" text-anchor="middle">' . esc_html( $opts['center_label'] ) . '</text>';
		}
		$svg .= '</svg><figcaption class="ssa-legend ssa-legend--stack">';
		foreach ( $parts as $i => $p ) {
			$frac = $total > 0 ? (float) $p['value'] / $total * 100 : 0;
			$svg .= '<span><i class="ssa-swatch ssa-s' . ( $i + 1 ) . '"></i>' . esc_html( $p['label'] ) . ' <b>' . esc_html( number_format_i18n( $frac, 0 ) ) . '%</b></span>';
		}
		$svg .= '</figcaption></figure>';
		return $svg;
	}

	/** Sparkline (tek seri, 12 nokta gibi). */
	public static function sparkline( array $values, array $opts = array() ) {
		$opts = wp_parse_args( $opts, array( 'width' => 96, 'height' => 28, 'class' => 'ssa-s1' ) );
		$n    = count( $values );
		if ( $n < 2 ) {
			return '<svg class="ssa-spark" viewBox="0 0 ' . $opts['width'] . ' ' . $opts['height'] . '"></svg>';
		}
		$max = max( max( $values ), 0.0001 );
		$pts = array();
		foreach ( $values as $i => $v ) {
			$x     = $i / ( $n - 1 ) * ( $opts['width'] - 4 ) + 2;
			$y     = $opts['height'] - 3 - ( $v / $max ) * ( $opts['height'] - 6 );
			$pts[] = round( $x, 1 ) . ',' . round( $y, 1 );
		}
		$last = explode( ',', end( $pts ) );
		return '<svg class="ssa-spark ' . esc_attr( $opts['class'] ) . '" viewBox="0 0 ' . $opts['width'] . ' ' . $opts['height'] . '" aria-hidden="true"><polyline points="' . esc_attr( implode( ' ', $pts ) ) . '"/><circle cx="' . $last[0] . '" cy="' . $last[1] . '" r="3"/></svg>';
	}

	/** İmza öğesi: komisyon/indirim dağılım çubuğu. */
	public static function split_bar( $commission, $discount, $share = null, array $opts = array() ) {
		$opts  = wp_parse_args( $opts, array( 'labels' => true, 'size' => 'sm' ) );
		$share = null === $share ? (float) $commission + (float) $discount : (float) $share;
		$c     = $share > 0 ? (float) $commission / $share * 100 : 0;
		$d     = $share > 0 ? (float) $discount / $share * 100 : 0;
		$html  = '<div class="ssa-split ssa-split--' . esc_attr( $opts['size'] ) . '" role="img" aria-label="' . esc_attr( sprintf( __( '%1$s%% commission, %2$s%% discount', 'splitshare-affiliates' ), wc_format_decimal( $commission, 1 ), wc_format_decimal( $discount, 1 ) ) ) . '">';
		$html .= '<div class="ssa-split__track"><span class="ssa-split__c" style="width:' . round( $c, 1 ) . '%"></span><span class="ssa-split__d" style="width:' . round( $d, 1 ) . '%"></span></div>';
		if ( $opts['labels'] ) {
			$html .= '<div class="ssa-split__legend"><span><i class="ssa-swatch ssa-swatch--c"></i>' . esc_html( wc_format_decimal( $commission, 1 ) ) . '% ' . esc_html__( 'commission', 'splitshare-affiliates' ) . '</span><span><i class="ssa-swatch ssa-swatch--d"></i>' . esc_html( wc_format_decimal( $discount, 1 ) ) . '% ' . esc_html__( 'discount', 'splitshare-affiliates' ) . '</span></div>';
		}
		return $html . '</div>';
	}

	/** Ölçer: hedefe ilerleme (min. ödeme vb.). */
	public static function meter( $value, $max, $label = '' ) {
		$pct = $max > 0 ? min( 100, max( 0, $value / $max * 100 ) ) : 0;
		return '<div class="ssa-meter" role="progressbar" aria-valuenow="' . esc_attr( round( $pct ) ) . '" aria-valuemin="0" aria-valuemax="100"><span style="width:' . round( $pct, 1 ) . '%"></span></div>' . ( $label ? '<div class="ssa-meter__label">' . esc_html( $label ) . '</div>' : '' );
	}
}
