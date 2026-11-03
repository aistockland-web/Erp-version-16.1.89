<?php
/**
 * Compatibility shim for the canonical workforce access class.
 *
 * This keeps the legacy alias name working without redefining a competing class.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'STOCKLAND_HR_REPLACEMENT_TESTING' ) ) {
	exit;
}

if ( ! class_exists( 'Stockland_HR_Replacement_Workforce_WP_Access', false ) ) {
	require_once __DIR__ . '/Stockland_HR_Replacement_Workforce_WP_Access.php';
}
if ( ! interface_exists( 'Stockland_HR_Replacement_Workforce_Access', false ) ) {
	require_once __DIR__ . '/Stockland_HR_Replacement_Workforce_Service.php';
}
if ( ! class_exists( 'Stockland_HR_Replacement_WordPress_Access', false ) ) {
	class_alias( 'Stockland_HR_Replacement_Workforce_WP_Access', 'Stockland_HR_Replacement_WordPress_Access' );
}
