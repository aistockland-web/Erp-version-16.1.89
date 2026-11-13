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
$failed = 0;
foreach ( $checks as $label => $ok ) {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) { $failed++; }
}
echo $failed ? "RESULT: FAIL ($failed)\n" : "RESULT: PASS\n";
if ( $failed ) { exit( 1 ); }
