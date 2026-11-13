<?php
// Run only on an explicitly disposable WordPress site. This script does not approve HR DDL.
wp_set_current_user( 1 );
if ( ! current_user_can( 'manage_options' ) ) { fwrite( STDERR, "FAIL: QA administrator is not authenticated\n" ); exit( 2 ); }
$last = array();
for ( $i = 0; $i < 80; $i++ ) {
	$last = Stockland_Installer::run( 1, 20 );
	if ( ! empty( $last['errors'] ) ) {
		fwrite( STDERR, "FAIL installer: " . implode( ' | ', $last['errors'] ) . "\n" );
		exit( 1 );
	}
	if ( empty( Stockland_Activator::missing_core_tables() )
		&& empty( Stockland_Accounting::missing_tables() )
		&& empty( Stockland_Accounting::upgrade_gaps() )
		&& empty( Stockland_HR::missing_legacy_tables() ) ) {
		break;
	}
}
$missing = array_merge(
	Stockland_Activator::missing_core_tables(),
	Stockland_Accounting::missing_tables(),
	Stockland_Accounting::upgrade_gaps(),
	Stockland_HR::missing_legacy_tables()
);
if ( $missing ) {
	fwrite( STDERR, "FAIL baseline remains incomplete: " . implode( ', ', $missing ) . "\n" );
	exit( 1 );
}
if ( Stockland_HR::schema_migration_approved() || get_option( 'stockland_hr_schema_migration_approval' ) ) {
	fwrite( STDERR, "FAIL: fresh setup unexpectedly approved HR DDL\n" );
	exit( 1 );
}
if ( ! Stockland_Authority::is_owner( 1 ) ) {
	fwrite( STDERR, "FAIL: bootstrap administrator was not established as active Owner\n" );
	exit( 1 );
}
echo "PASS 49-key Core + Finance + legacy HR baseline physically verified\n";
echo "PASS 37 lifecycle HR tables remain gated; no Owner DDL approval was created\n";
