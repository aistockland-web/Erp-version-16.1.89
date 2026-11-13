<?php
global $wpdb;
// Read-only assertion suite for the schema-governance boundary.
$prefix = $wpdb->prefix . 'stock_';
$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $prefix ) . '%' ) );
$approval = get_option( 'stockland_hr_schema_migration_approval' );
$checks = array(
	'49 baseline tables only' => 49 === count( $tables ),
	'Core tables physically complete' => empty( Stockland_Activator::missing_core_tables() ),
	'Finance tables physically complete' => empty( Stockland_Accounting::missing_tables() ) && empty( Stockland_Accounting::upgrade_gaps() ),
	'four legacy HR tables present' => empty( Stockland_HR::missing_legacy_tables() ),
	'37 lifecycle tables not created' => 37 === count( Stockland_HR::missing_lifecycle_tables() ),
	'no implicit HR approval record' => empty( $approval ),
	'HR module readiness remains false' => ! Stockland_HR::is_schema_ready(),
	'Core/Finance operational gate true' => 1 === (int) get_option( 'stockland_core_finance_schema_ready' ),
	'global application schema gate true' => 1 === (int) get_option( 'stockland_application_schema_ready' ),
);

$setup_response = rest_do_request( new WP_REST_Request( 'GET', '/stockland/v16/setup/status' ) );
$setup_payload  = $setup_response instanceof WP_REST_Response ? $setup_response->get_data() : array();
$checks['setup status exposes the operational baseline'] = $setup_response instanceof WP_REST_Response
	&& 200 === $setup_response->get_status()
	&& ! empty( $setup_payload['operational_ready'] )
	&& 'CORE_FINANCE_READY_HR_PENDING' === ( $setup_payload['operational_state'] ?? '' );
$checks['setup does not auto-advance into unapproved HR lifecycle DDL'] = ! empty( $setup_payload['hr_pending_approval'] )
	&& empty( $setup_payload['can_advance'] )
	&& false === ( $setup_payload['ready'] ?? null )
	&& empty( get_option( 'stockland_hr_schema_migration_approval' ) );
$checks['setup preserves 37 unapproved HR lifecycle tables as pending'] = 37 === count( $setup_payload['hr']['lifecycle_missing'] ?? array() );

$app_response = rest_do_request( new WP_REST_Request( 'GET', '/stockland/v16/app/bootstrap' ) );
$app_payload  = $app_response instanceof WP_REST_Response ? $app_response->get_data() : array();
$checks['ERP bootstrap responds on operational baseline while HR is pending'] = $app_response instanceof WP_REST_Response
	&& 200 === $app_response->get_status()
	&& is_array( $app_payload )
	&& isset( $app_payload['user'], $app_payload['dashboard'], $app_payload['locations'] );
$failed = 0;
foreach ( $checks as $label => $ok ) {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) { $failed++; }
}
echo $failed ? "RESULT: FAIL ($failed)\n" : "RESULT: PASS\n";
if ( $failed ) { exit( 1 ); }
