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
// A non-Owner cannot turn the explicit approval argument into an HR migration.
$wrong_actor = Stockland_Installer::run( 999999, 20, array( 'reason' => 'Attempted approval from a non-Owner QA actor' ) );
$wrong_actor_gaps = Stockland_HR::upgrade_gaps();
sort( $wrong_actor_gaps );
$chk(
	'non-Owner approval request is rejected without changing HR schema',
	! Stockland_Installer::hr_approval_is_current()
		&& ! empty( $wrong_actor['needs_hr_approval'] )
		&& ! empty( $wrong_actor['errors'] )
		&& $gaps === $wrong_actor_gaps,
	json_encode( array( 'result' => $wrong_actor, 'gaps' => $wrong_actor_gaps ) )
);

// A current Owner with an inadequate written reason also remains fail-closed.
$short_reason = Stockland_Installer::run( 1, 20, array( 'reason' => 'too short' ) );
$short_reason_gaps = Stockland_HR::upgrade_gaps();
sort( $short_reason_gaps );
$chk(
	'short Owner reason is rejected without changing HR schema',
	! Stockland_Installer::hr_approval_is_current()
		&& ! empty( $short_reason['needs_hr_approval'] )
		&& ! empty( $short_reason['errors'] )
		&& $gaps === $short_reason_gaps,
	json_encode( array( 'result' => $short_reason, 'gaps' => $short_reason_gaps ) )
);

$approval_reason = 'Owner approval of neutral pension and tax defaults during upgrade acceptance on a disposable site';
$r = Stockland_Installer::run( 1, 20, array( 'reason' => $approval_reason ) );
$approval_record = get_option( 'stockland_hr_schema_migration_approval', array() );
$approval_is_bound_to_current_source = is_array( $approval_record )
	&& Stockland_HR::DB_VERSION === (string) ( $approval_record['schema_version'] ?? '' )
	&& hash_equals( Stockland_HR::schema_signature(), (string) ( $approval_record['schema_signature'] ?? '' ) )
	&& 1 === (int) ( $approval_record['approved_by'] ?? 0 )
	&& ! empty( $approval_record['approved_at'] )
	&& $approval_reason === (string) ( $approval_record['reason'] ?? '' )
	&& Stockland_Installer::hr_approval_is_current();
$chk(
	'active Owner approval is recorded against the exact HR DDL signature and audit-gated helper',
	$approval_is_bound_to_current_source,
	json_encode( $approval_record )
);
$chk( 'migration completes after the written Owner approval', ! empty( $r['complete'] ) && empty( $r['structural_gaps'] ), json_encode( $r ) );
$approval_timestamp = (string) ( $approval_record['approved_at'] ?? '' );
$repeat = Stockland_Installer::run( 1, 20, array( 'reason' => 'A second request must not replace the current approval' ) );
$approval_after_repeat = get_option( 'stockland_hr_schema_migration_approval', array() );
$chk(
	'repeat setup preserves the existing approval without writing a duplicate approval',
	! empty( $repeat['complete'] )
		&& $approval_timestamp === (string) ( $approval_after_repeat['approved_at'] ?? '' )
		&& $approval_reason === (string) ( $approval_after_repeat['reason'] ?? '' )
		&& Stockland_Installer::hr_approval_is_current(),
	json_encode( array( 'repeat' => $repeat, 'approval' => $approval_after_repeat ) )
);
$chk( 'go-live authorization is not inherited from the old version', ! Stockland_Release_Authorization::is_authorized() );
echo $fail ? "RESULT FAIL ($fail)\n" : "RESULT PASS\n"; if ( $fail ) { exit( 1 ); }
