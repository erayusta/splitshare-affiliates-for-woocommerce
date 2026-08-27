<?php
// WP'siz birim testleri: yalnızca saf hesaplayıcı test edilir.
define( 'ABSPATH', __DIR__ . '/' );
if ( ! function_exists( '__' ) ) { function __( $t, $d = 'default' ) { return $t; } }
require_once __DIR__ . '/../includes/class-ssa-calculator.php';
