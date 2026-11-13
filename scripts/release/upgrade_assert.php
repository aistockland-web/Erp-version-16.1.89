<?php
// After an in-place upgrade of a populated site to the master installer: the HR default migration must be gated, then complete after Owner approval.
if ( 'yes' !== getenv( 'STOCKLAND_DISPOSABLE_SITE' ) ) { fwrite( STDERR, "Refusing: STOCKLAND_DISPOSABLE_SITE=yes required\n" ); exit( 2 ); }
wp_set_current_user( 1 ); $fail = 0;
$chk = function ( $l, $c, $d = '' ) use ( &$fail ) { echo ( $c ? 'PASS ' : 'FAIL ' ) . $l . ( $c ? '' : " :: $d" ) . "\n"; if ( ! $c ) { ++$fail; } };
$chk( 'plugin is 16.2.18 after in-place upgrade', '16.2.18' === STOCKLAND_VERSION, STOCKLAND_VERSION );
$chk( 'all 86 application tables present', 0 === count( Stockland_Activator::application_missing_tables() ) );
$gaps = array_merge( (array) Stockland_Accounting::upgrade_gaps(), (array) Stockland_HR::upgrade_gaps() ); sort( $gaps );
$r = Stockland_Installer::run( 1, 20 );
$chk( 'HR default migration is blocked until the Owner gives a written reason', ! empty( $gaps ) ? ( empty( $r['complete'] ) && ! empty( $r['needs_hr_approval'] ) ) : true, json_encode( $r ) );
$chk( 'blocked step reports exactly the neutral-default gaps', empty( $gaps ) || ! array_diff( $gaps, array( 'payroll_lines.income_tax_rate.__neutral_default__', 'payroll_lines.employee_pension_rate.__neutral_default__', 'payroll_lines.employer_pension_rate.__neutral_default__' ) ), json_encode( $gaps ) );
$r = Stockland_Installer::run( 1, 20, array( 'reason' => 'Owner approval of neutral pension and tax defaults during upgrade acceptance on a disposable site' ) );
$chk( 'migration completes after the written Owner approval', ! empty( $r['complete'] ) && empty( $r['structural_gaps'] ), json_encode( $r ) );
$chk( 'approval is recorded and current for this schema', Stockland_Installer::hr_approval_is_current() );
$chk( 'go-live authorization is not inherited from the old version', ! Stockland_Release_Authorization::is_authorized() );
echo $fail ? "RESULT FAIL ($fail)\n" : "RESULT PASS\n"; if ( $fail ) { exit( 1 ); }
