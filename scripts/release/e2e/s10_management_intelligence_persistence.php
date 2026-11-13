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
		'budget'                => '1250.00',
		'account_owner_user_id' => $management_id,
		'operator_user_id'      => (int) ( $users['se'] ?? 0 ),
		'influencer_label'      => 'Synthetic CI creator',
	),
	$management_id
);
list( $create_status, $create_data ) = $created;
$campaign = is_array( $create_data ) ? ( $create_data['campaign'] ?? array() ) : array();
$campaign_code = (string) ( $campaign['campaign_code'] ?? '' );
$check( 'real REST campaign POST succeeds for Management', 200 === $create_status && preg_match( '/^CMP-[A-F0-9]{12}$/', $campaign_code ), 'HTTP ' . $create_status . ' :: ' . wp_json_encode( $create_data ) );
$check(
	'campaign is persisted as DRAFT planning-only data',
	'DRAFT' === ( $campaign['status'] ?? '' )
		&& 'PLANNING_ESTIMATE_NOT_APPROVED_EXPENSE' === ( $campaign['budget_class'] ?? '' )
		&& '1250.00' === (string) ( $campaign['budget'] ?? '' )
);
$operator_id = (int) ( $users['se'] ?? 0 );
$check(
	'campaign captures verified account owner, operator and optional influencer assignment',
	$operator_id > 0
		&& is_array( $campaign_row = json_decode( (string) Stockland_DB::get_setting( 'mi_social_campaign_' . $campaign_code ), true ) )
		&& (int) ( $campaign_row['account_owner_user_id'] ?? 0 ) === $management_id
		&& (int) ( $campaign_row['operator_user_id'] ?? 0 ) === $operator_id
		&& 'Synthetic CI creator' === ( $campaign_row['influencer_label'] ?? '' )
);
$planned_campaign = call(
	'POST',
	'/management/social/campaigns/' . rawurlencode( $campaign_code ),
	array( 'status' => 'PLANNED' ),
	$management_id
);
$check( 'DRAFT campaign advances to PLANNED only with active owner/operator and valid dates', 200 === $planned_campaign[0] && 'PLANNED' === ( $planned_campaign[1]['campaign']['status'] ?? '' ), wp_json_encode( $planned_campaign[1] ) );

$content_create = call(
	'POST',
	'/management/social/campaigns/' . rawurlencode( $campaign_code ) . '/content',
	array(
		'title' => 'Synthetic content calendar item',
		'content_type' => 'REEL',
		'planned_at' => '2026-11-15 13:30',
		'operator_user_id' => $operator_id,
		'influencer_label' => 'Synthetic CI creator',
		'brief' => 'Synthetic content planning only; do not publish from CI.',
	),
	$management_id
);
$content_item = is_array( $content_create[1] ) ? ( $content_create[1]['content_item'] ?? array() ) : array();
$content_code = (string) ( $content_item['content_code'] ?? '' );
$check( 'PLANNED campaign accepts a content-calendar item', 200 === $content_create[0] && preg_match( '/^CNT-[A-F0-9]{24}$/', $content_code ) && 'PLANNED' === ( $content_item['status'] ?? '' ), wp_json_encode( $content_create[1] ) );
$content_json = '' !== $content_code ? Stockland_DB::get_setting( 'mi_social_content_' . $content_code ) : null;
$content_row = is_string( $content_json ) ? json_decode( $content_json, true ) : null;
$check( 'content calendar persists in the existing Core settings store', is_array( $content_row ) && $content_code === ( $content_row['content_code'] ?? '' ) && (int) ( $content_row['operator_user_id'] ?? 0 ) === $operator_id );

$bad_publish = call(
	'POST',
	'/management/social/content/' . rawurlencode( $content_code ),
	array( 'status' => 'PUBLISHED', 'post_url' => 'http://example.invalid/post', 'published_at' => '2026-11-15 14:00' ),
	$management_id
);
$check( 'content publication refuses a non-HTTPS URL', 400 === $bad_publish[0] );
$publish_content = call(
	'POST',
	'/management/social/content/' . rawurlencode( $content_code ),
	array( 'status' => 'PUBLISHED', 'post_url' => 'https://example.invalid/stockland-ci-post', 'published_at' => '2026-11-15 14:00' ),
	$management_id
);
$check( 'PLANNED content can be marked PUBLISHED with HTTPS URL and timestamp', 200 === $publish_content[0] && 'PUBLISHED' === ( $publish_content[1]['content_item']['status'] ?? '' ) && 'https://example.invalid/stockland-ci-post' === ( $publish_content[1]['content_item']['post_url'] ?? '' ) );

$content_cancel_create = call(
	'POST',
	'/management/social/campaigns/' . rawurlencode( $campaign_code ) . '/content',
	array(
		'title' => 'Synthetic cancelled calendar item',
		'content_type' => 'STORY',
		'planned_at' => '2026-11-16 10:00',
		'operator_user_id' => $operator_id,
	),
	$management_id
);
$cancel_code = (string) ( $content_cancel_create[1]['content_item']['content_code'] ?? '' );
$cancel_content = call(
	'POST',
	'/management/social/content/' . rawurlencode( $cancel_code ),
	array( 'status' => 'CANCELLED', 'cancellation_reason' => 'Synthetic CI cancellation test.' ),
	$management_id
);
$check( 'PLANNED content can be cancelled with a recorded reason', 200 === $cancel_content[0] && 'CANCELLED' === ( $cancel_content[1]['content_item']['status'] ?? '' ) );

$publish_campaign = call(
	'POST',
	'/management/social/campaigns/' . rawurlencode( $campaign_code ),
	array( 'status' => 'PUBLISHED', 'post_url' => 'https://example.invalid/stockland-ci-campaign', 'published_at' => '2026-11-15 14:10' ),
	$management_id
);
$check( 'PLANNED campaign can be marked PUBLISHED with HTTPS URL and timestamp', 200 === $publish_campaign[0] && 'PUBLISHED' === ( $publish_campaign[1]['campaign']['status'] ?? '' ) );
$terminal_campaign = call( 'POST', '/management/social/campaigns/' . rawurlencode( $campaign_code ), array( 'status' => 'CANCELLED', 'cancellation_reason' => 'Must not change a published campaign.' ), $management_id );
$check( 'published campaign is terminal and cannot be silently cancelled', 409 === $terminal_campaign[0] );

$cancel_campaign_create = call(
	'POST',
	'/management/social',
	array(
		'title' => 'Synthetic cancellation path',
		'platform' => 'FACEBOOK',
		'account_label' => 'Stockland test account',
		'start_date' => '2026-11-20',
		'end_date' => '2026-11-25',
		'account_owner_user_id' => $management_id,
		'operator_user_id' => $operator_id,
	),
	$management_id
);
$cancel_campaign_code = (string) ( $cancel_campaign_create[1]['campaign']['campaign_code'] ?? '' );
$cancel_campaign = call(
	'POST',
	'/management/social/campaigns/' . rawurlencode( $cancel_campaign_code ),
	array( 'status' => 'CANCELLED', 'cancellation_reason' => 'Synthetic CI cancellation test.' ),
	$management_id
);
$check( 'DRAFT campaign can be cancelled with a required reason', 200 === $cancel_campaign[0] && 'CANCELLED' === ( $cancel_campaign[1]['campaign']['status'] ?? '' ) );

$listed_lifecycle = call( 'GET', '/management/social', array(), $management_id );
$list_content = is_array( $listed_lifecycle[1] ) ? ( $listed_lifecycle[1]['content_items'] ?? array() ) : array();
$check(
	'Management GET includes content calendar items and lifecycle states',
	200 === $listed_lifecycle[0]
		&& (bool) array_filter( $list_content, static function ( $row ) use ( $content_code ) {
			return ( $row['content_code'] ?? '' ) === $content_code && 'PUBLISHED' === ( $row['status'] ?? '' );
		} )
);

$campaign_update_audits = (int) Stockland_DB::read_var( $wpdb->prepare(
	'SELECT COUNT(*) FROM %i WHERE reference=%s AND action=%s',
	$audit_table,
	$campaign_code,
	'MI_SOCIAL_CAMPAIGN_PLANNED'
) );
$content_create_audits = (int) Stockland_DB::read_var( $wpdb->prepare(
	'SELECT COUNT(*) FROM %i WHERE reference=%s AND action=%s',
	$audit_table,
	$content_code,
	'MI_SOCIAL_CONTENT_CREATE'
) );
$content_publish_audits = (int) Stockland_DB::read_var( $wpdb->prepare(
	'SELECT COUNT(*) FROM %i WHERE reference=%s AND action=%s',
	$audit_table,
	$content_code,
	'MI_SOCIAL_CONTENT_PUBLISHED'
) );
$updated_campaign_json = Stockland_DB::get_setting( 'mi_social_campaign_' . $campaign_code );
$campaign_update_audit = Stockland_DB::read_row( $wpdb->prepare(
	'SELECT new_value FROM %i WHERE reference=%s AND action=%s ORDER BY id DESC LIMIT 1',
	$audit_table,
	$campaign_code,
	'MI_SOCIAL_CAMPAIGN_PUBLISHED'
), ARRAY_A );
$expected_update_pointer = 'CORE_SETTINGS:mi_social_campaign_' . $campaign_code . ':sha256:' . hash( 'sha256', (string) $updated_campaign_json );
$check( 'campaign lifecycle updates append immutable audit events', $campaign_update_audits >= 1 );
$check( 'content creation and publication each append their own audit events', 1 === $content_create_audits && 1 === $content_publish_audits );
$check( 'updated campaign audit pointer verifies the full Core record hash', is_array( $campaign_update_audit ) && hash_equals( $expected_update_pointer, (string) ( $campaign_update_audit['new_value'] ?? '' ) ) && strlen( $expected_update_pointer ) <= 255 );

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
$check( 'real REST snapshot POST succeeds for Management', 200 === $snapshot_status && preg_match( '/^SOC-[A-F0-9]{24}$/', $snapshot_code ), 'HTTP ' . $snapshot_status . ' :: ' . wp_json_encode( $snapshot_data ) );
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
$campaign_audit = Stockland_DB::read_row( $wpdb->prepare(
	'SELECT new_value FROM %i WHERE action=%s AND reference=%s ORDER BY id DESC LIMIT 1',
	$audit_table,
	'MI_SOCIAL_CAMPAIGN_CREATE',
	$campaign_code
), ARRAY_A );
$campaign_audit_pointer = 'CORE_SETTINGS:mi_social_campaign_' . $campaign_code . ':sha256:' . hash( 'sha256', (string) $campaign_json );
$snapshot_audits = (int) Stockland_DB::read_var( $wpdb->prepare(
	'SELECT COUNT(*) FROM %i WHERE action=%s AND reference=%s',
	$audit_table,
	'MI_SOCIAL_SNAPSHOT_APPEND',
	$snapshot_code
) );
$snapshot_audit = Stockland_DB::read_row( $wpdb->prepare(
	'SELECT new_value FROM %i WHERE action=%s AND reference=%s ORDER BY id DESC LIMIT 1',
	$audit_table,
	'MI_SOCIAL_SNAPSHOT_APPEND',
	$snapshot_code
), ARRAY_A );
$snapshot_audit_pointer = 'CORE_SETTINGS:mi_social_snapshot_' . $snapshot_code . ':sha256:' . hash( 'sha256', (string) $snapshot_json );
$check( 'campaign creation has exactly one immutable audit event', 1 === $campaign_audits );
$check( 'campaign audit compact pointer verifies full Core record hash', is_array( $campaign_audit ) && hash_equals( $campaign_audit_pointer, (string) ( $campaign_audit['new_value'] ?? '' ) ) && strlen( $campaign_audit_pointer ) <= 255 );
$check( 'snapshot append has exactly one immutable audit event after duplicate retry', 1 === $snapshot_audits );
$check( 'snapshot audit compact pointer verifies full Core record hash', is_array( $snapshot_audit ) && hash_equals( $snapshot_audit_pointer, (string) ( $snapshot_audit['new_value'] ?? '' ) ) && strlen( $snapshot_audit_pointer ) <= 255 );

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
