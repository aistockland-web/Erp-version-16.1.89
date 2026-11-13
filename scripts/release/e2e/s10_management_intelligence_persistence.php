<?php
/**
 * Real WordPress REST + database acceptance for Management Intelligence.
 * Runs only through run_all.sh on a disposable synthetic QA database.
 */
require __DIR__ . '/lib.php';
global $wpdb;

$fail = 0;
$check = static function ( $label, $condition, $detail = '' ) use ( &$fail ) {
	echo ( $condition ? 'PASS ' : 'FAIL ' ) . $label . ( $condition ? '' : ' :: ' . $detail ) . "\n";
	if ( ! $condition ) {
		++$fail;
	}
};

$users = get_option( 'e2e_users', array() );
$management_id = (int) ( $users['mg'] ?? 0 );
$branch_id     = (int) ( $users['bm'] ?? 0 );
$auditor_id    = mkuser( 'mi-auditor', 'subscriber', 'auditor' );

$check( 'existing E2E Management and Branch Manager fixtures are available', $management_id > 0 && $branch_id > 0 );
if ( $management_id < 1 || $branch_id < 1 ) {
	echo "RESULT FAIL (missing role fixtures)\n";
	exit( 1 );
}

// These are isolated QA identities. Give them the same WordPress role/capability
// mapping used by actual Stockland accounts while retaining their separate ERP profiles.
$management_user = new WP_User( $management_id );
$management_user->set_role( Stockland_Roles::ROLE_MANAGEMENT );
$branch_user = new WP_User( $branch_id );
$branch_user->set_role( Stockland_Roles::ROLE_BRANCH_MANAGER );
$auditor_user = new WP_User( $auditor_id );
$auditor_user->set_role( Stockland_Roles::ROLE_AUDITOR );

$check(
	'Management profile and capability authorize Management Intelligence',
	'management' === Stockland_Authority::role( $management_id )
		&& user_can( $management_id, 'stockland_view_cost_and_profit' )
		&& Stockland_Authority::can_view_management_intelligence( $management_id )
);
$check(
	'Branch Manager is not authorized for company Management Intelligence',
	'branch_manager' === Stockland_Authority::role( $branch_id )
		&& ! Stockland_Authority::can_view_management_intelligence( $branch_id )
);
$check(
	'Auditor cannot use Management-only campaign planning',
	'auditor' === Stockland_Authority::role( $auditor_id )
		&& ! Stockland_Authority::can_manage_management_intelligence( $auditor_id )
);

$settings_table = Stockland_DB::table( 'settings' );
$audit_table    = Stockland_DB::table( 'audit_logs' );
$check( 'Core settings and immutable audit tables are present', Stockland_DB::table_exists( $settings_table ) && Stockland_DB::table_exists( $audit_table ) );

$ledger_keys = array( 'transactions', 'sales', 'expenses', 'journals', 'journal_entries', 'gl_entries', 'cash_transactions', 'bank_transactions' );
$ledger_before = array();
foreach ( $ledger_keys as $key ) {
	$table = Stockland_DB::table( $key );
	if ( Stockland_DB::table_exists( $table ) ) {
		$ledger_before[ $key ] = (int) Stockland_DB::read_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
	}
}

$created = call(
	'POST',
	'/management/social',
	array(
		'title'           => 'Synthetic CI campaign — no publishing',
		'platform'        => 'INSTAGRAM',
		'account_label'   => 'Stockland QA account',
		'start_date'      => '2026-11-01',
		'end_date'        => '2026-11-30',
		'budget'          => '1250.00',
	),
	$management_id
);
list( $create_status, $create_data ) = $created;
$campaign = is_array( $create_data ) ? ( $create_data['campaign'] ?? array() ) : array();
$campaign_code = (string) ( $campaign['campaign_code'] ?? '' );
$check( 'real REST campaign POST succeeds for Management', 200 === $create_status && preg_match( '/^CMP-[A-F0-9]{12}$/', $campaign_code ) );
$check(
	'campaign is persisted as DRAFT planning-only data',
	'DRAFT' === ( $campaign['status'] ?? '' )
		&& 'PLANNING_ESTIMATE_NOT_APPROVED_EXPENSE' === ( $campaign['budget_class'] ?? '' )
		&& '1250.00' === (string) ( $campaign['budget'] ?? '' )
);
$campaign_json = '' !== $campaign_code ? Stockland_DB::get_setting( 'mi_social_campaign_' . $campaign_code ) : null;
$campaign_row = is_string( $campaign_json ) ? json_decode( $campaign_json, true ) : null;
$check( 'campaign can be read back from the real Core settings row', is_array( $campaign_row ) && $campaign_code === ( $campaign_row['campaign_code'] ?? '' ) );

$listed = call( 'GET', '/management/social', array(), $management_id );
list( $list_status, $list_data ) = $listed;
$listed_campaigns = is_array( $list_data ) ? ( $list_data['campaigns'] ?? array() ) : array();
$check(
	'real REST GET returns the persisted campaign',
	200 === $list_status && (bool) array_filter( $listed_campaigns, static function ( $row ) use ( $campaign_code ) {
		return ( $row['campaign_code'] ?? '' ) === $campaign_code;
	} )
);

$branch_post = call(
	'POST',
	'/management/social',
	array( 'title' => 'Forbidden branch draft', 'platform' => 'INSTAGRAM', 'account_label' => 'Branch QA' ),
	$branch_id
);
$auditor_post = call(
	'POST',
	'/management/social',
	array( 'title' => 'Forbidden auditor draft', 'platform' => 'INSTAGRAM', 'account_label' => 'Auditor QA' ),
	$auditor_id
);
$check( 'Branch Manager REST campaign POST is denied', 403 === $branch_post[0] );
$check( 'Auditor REST campaign POST is denied', 403 === $auditor_post[0] );

$snapshot_status = 0;
$snapshot_data = array();
$snapshot_code = '';
if ( '' !== $campaign_code ) {
	$snapshot = call(
		'POST',
		'/management/social/campaigns/' . rawurlencode( $campaign_code ) . '/snapshots',
		array(
			'platform'           => 'INSTAGRAM',
			'account_label'      => 'Stockland QA account',
			'period_start'       => '2026-11-01',
			'period_end'         => '2026-11-12',
			'report_as_of'       => '2026-11-13 15:00',
			'source_reference'   => 'Synthetic CI export fixture; not a provider-connected metric.',
			'source_sha256'      => str_repeat( 'a', 64 ),
			'reach_count'        => 1240,
			'view_count'         => 2300,
			'engagement_count'   => 178,
			'click_count'        => 52,
			'lead_count'         => 9,
			'watch_time_minutes' => '312.75',
			'spend_amount'       => '15.50',
			'attributed_sales'   => '0.00',
			'follower_delta'     => -5,
			'attribution_method' => 'Synthetic QA',
			'notes'              => 'Test-only data. The supplied SHA-256 is a fixture, not a file upload verification.',
		),
		$management_id
	);
	$snapshot_status = $snapshot[0];
	$snapshot_data = is_array( $snapshot[1] ) ? ( $snapshot[1]['snapshot'] ?? array() ) : array();
	$snapshot_code = (string) ( $snapshot_data['snapshot_code'] ?? '' );
}
$check( 'real REST snapshot POST succeeds for Management', 200 === $snapshot_status && preg_match( '/^SOC-[A-F0-9]{24}$/', $snapshot_code ) );
$check(
	'manual snapshot stores timestamp and typed metrics',
	'2026-11-13 15:00:00' === ( $snapshot_data['report_as_of'] ?? '' )
		&& '312.75' === (string) ( $snapshot_data['watch_time_minutes'] ?? '' )
		&& 1240 === (int) ( $snapshot_data['reach_count'] ?? -1 )
);

$snapshot_json = '' !== $snapshot_code ? Stockland_DB::get_setting( 'mi_social_snapshot_' . $snapshot_code ) : null;
$snapshot_row = is_string( $snapshot_json ) ? json_decode( $snapshot_json, true ) : null;
$check( 'snapshot can be read back from the real Core settings row', is_array( $snapshot_row ) && $snapshot_code === ( $snapshot_row['snapshot_code'] ?? '' ) );

$duplicate = array( 0, array() );
if ( '' !== $campaign_code ) {
	$duplicate = call(
		'POST',
		'/management/social/campaigns/' . rawurlencode( $campaign_code ) . '/snapshots',
		array(
			'platform'           => 'INSTAGRAM',
			'account_label'      => 'Stockland QA account',
			'period_start'       => '2026-11-01',
			'period_end'         => '2026-11-12',
			'report_as_of'       => '2026-11-13 15:00',
			'source_reference'   => 'Synthetic CI export fixture; not a provider-connected metric.',
			'source_sha256'      => str_repeat( 'a', 64 ),
			'reach_count'        => 1240,
			'view_count'         => 2300,
			'engagement_count'   => 178,
			'click_count'        => 52,
			'lead_count'         => 9,
			'watch_time_minutes' => '312.75',
			'spend_amount'       => '15.50',
			'attributed_sales'   => '0.00',
			'follower_delta'     => -5,
		),
		$management_id
	);
}
$check( 'duplicate/retried export snapshot is rejected with conflict', 409 === $duplicate[0] );
$campaign_audits = (int) Stockland_DB::read_var( $wpdb->prepare(
	'SELECT COUNT(*) FROM %i WHERE action=%s AND reference=%s',
	$audit_table,
	'MI_SOCIAL_CAMPAIGN_CREATE',
	$campaign_code
) );
$snapshot_audits = (int) Stockland_DB::read_var( $wpdb->prepare(
	'SELECT COUNT(*) FROM %i WHERE action=%s AND reference=%s',
	$audit_table,
	'MI_SOCIAL_SNAPSHOT_APPEND',
	$snapshot_code
) );
$check( 'campaign creation has exactly one immutable audit event', 1 === $campaign_audits );
$check( 'snapshot append has exactly one immutable audit event after duplicate retry', 1 === $snapshot_audits );

$ledger_after = array();
foreach ( $ledger_before as $key => $before ) {
	$table = Stockland_DB::table( $key );
	$ledger_after[ $key ] = (int) Stockland_DB::read_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
}
$check( 'Management Intelligence creates no sales/stock/expense/GL rows', $ledger_before === $ledger_after );

echo $fail ? "RESULT FAIL ($fail)\n" : "RESULT PASS\n";
if ( $fail ) {
	exit( 1 );
}
