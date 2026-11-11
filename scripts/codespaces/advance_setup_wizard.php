<?php
/**
 * Run the real Stockland setup REST endpoints as the isolated QA administrator.
 * This script is invoked with WP-CLI after a disposable WordPress site is ready.
 * It does not seed business transactions or access production systems.
 */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'WP_REST_Request' ) ) {
	fwrite( STDERR, "WordPress is not loaded; refusing setup acceptance.\n" );
	exit( 2 );
}
$login = getenv( 'STOCKLAND_DEV_WP_ADMIN_USER' );
if ( ! is_string( $login ) || '' === $login ) {
	$login = 'stockland_owner';
}
$user = get_user_by( 'login', $login );
if ( ! $user instanceof WP_User ) {
	fwrite( STDERR, "Isolated QA administrator was not found.\n" );
	exit( 2 );
}
wp_set_current_user( (int) $user->ID );
if ( ! is_user_logged_in() || ! current_user_can( 'stockland_install_schema' ) ) {
	fwrite( STDERR, "QA administrator lacks stockland_install_schema; refusing to bypass the REST permission check.\n" );
	exit( 2 );
}
rest_get_server();
$call_route = static function ( $method, $route ) {
	$request  = new WP_REST_Request( $method, $route );
	$response = rest_do_request( $request );
	if ( is_wp_error( $response ) ) {
		$code = $response->get_error_code();
		$data = $response->get_error_data();
		throw new RuntimeException( $code . ': ' . $response->get_error_message() . ' ' . wp_json_encode( $data ) );
	}
	if ( ! $response instanceof WP_REST_Response ) {
		throw new RuntimeException( 'Stockland setup route returned an unexpected response type.' );
	}
	$status = (int) $response->get_status();
	if ( $status < 200 || $status >= 300 ) {
		throw new RuntimeException( 'Stockland setup route returned HTTP ' . $status . ': ' . wp_json_encode( $response->get_data() ) );
	}
	$data = $response->get_data();
	if ( ! is_array( $data ) ) {
		throw new RuntimeException( 'Stockland setup route returned a non-object response.' );
	}
	return $data;
};
$maximum_batches = (int) getenv( 'STOCKLAND_QA_SETUP_MAX_BATCHES' );
if ( $maximum_batches < 1 || $maximum_batches > 200 ) {
	$maximum_batches = 160;
}
$history = array();
$initial = $call_route( 'GET', '/stockland/v16/setup/status' );
if ( ! array_key_exists( 'ready', $initial ) || ! isset( $initial['total']['present'], $initial['total']['total'] ) ) {
	throw new RuntimeException( 'Stockland setup/status response is missing its physical schema progress fields.' );
}
$history[] = array(
	'batch' => 0,
	'state' => (string) ( $initial['state'] ?? 'UNKNOWN' ),
	'present' => (int) $initial['total']['present'],
	'total' => (int) $initial['total']['total'],
	'ready' => (bool) $initial['ready'],
);
$current = $initial;
for ( $batch = 1; $batch <= $maximum_batches && empty( $current['ready'] ); ++$batch ) {
	$before_present = (int) $current['total']['present'];
	$before_finance = count( (array) ( $current['finance']['upgrade_gaps'] ?? array() ) );
	$before_hr      = count( (array) ( $current['hr']['upgrade_gaps'] ?? array() ) );
	$advanced       = $call_route( 'POST', '/stockland/v16/setup/advance' );
	if ( ! isset( $advanced['total']['present'], $advanced['total']['total'] ) || ! array_key_exists( 'ready', $advanced ) ) {
		throw new RuntimeException( 'Stockland setup/advance response is missing physical progress fields.' );
	}
	$after_present = (int) $advanced['total']['present'];
	$after_finance = count( (array) ( $advanced['finance']['upgrade_gaps'] ?? array() ) );
	$after_hr      = count( (array) ( $advanced['hr']['upgrade_gaps'] ?? array() ) );
	$gain          = max( 0, $after_present - $before_present );
	$gap_gain      = max( 0, ( $before_finance - $after_finance ) + ( $before_hr - $after_hr ) );
	if ( empty( $advanced['ready'] ) && 0 === $gain && 0 === $gap_gain ) {
		throw new RuntimeException( 'Stockland setup made no physically verified progress at batch ' . $batch . '; state=' . ( $advanced['state'] ?? 'UNKNOWN' ) );
	}
	$history[] = array(
		'batch' => $batch,
		'state' => (string) ( $advanced['state'] ?? 'UNKNOWN' ),
		'present' => $after_present,
		'total' => (int) $advanced['total']['total'],
		'gain' => $gain,
		'upgrade_gaps_resolved' => $gap_gain,
		'ready' => (bool) $advanced['ready'],
	);
	$current = $advanced;
}
if ( empty( $current['ready'] ) ) {
	throw new RuntimeException( 'Stockland setup did not reach READY within ' . $maximum_batches . ' bounded batches.' );
}
$final = $call_route( 'GET', '/stockland/v16/setup/status' );
if ( empty( $final['ready'] ) || (int) $final['total']['present'] !== (int) $final['total']['total'] ) {
	throw new RuntimeException( 'Independent setup/status re-read did not confirm physical schema readiness.' );
}
$receipt = array(
	'schema_setup_status' => 'PASS',
	'route_contract' => array(
		'status' => '/stockland/v16/setup/status GET',
		'advance' => '/stockland/v16/setup/advance POST',
		'authenticated_user' => $login,
		'permission' => 'stockland_install_schema',
	),
	'plugin_version' => defined( 'STOCKLAND_VERSION' ) ? STOCKLAND_VERSION : 'unknown',
	'database_version' => (string) get_option( 'stockland_db_version', 'unknown' ),
	'setup_state' => (string) get_option( 'stockland_setup_state', 'unknown' ),
	'application_schema_ready_option' => (string) get_option( 'stockland_application_schema_ready', '0' ),
	'initial' => $initial,
	'batches' => $history,
	'final_status' => $final,
	'deep_integrity' => 'NOT_RUN_BY_SETUP_WIZARD',
	'schema_contract_approval' => 'NOT_GRANTED_BY_SETUP_WIZARD',
);
echo wp_json_encode( $receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
