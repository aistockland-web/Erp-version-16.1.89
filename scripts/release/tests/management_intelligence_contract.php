<?php
/**
 * Isolated contract tests for Management Intelligence / HR ownership separation.
 *
 * This harness uses in-memory Core settings/audit stubs. It does not connect to
 * WordPress, MariaDB/MySQL, HR tables, or ERP financial/stock ledgers.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['mi_routes'] = array();
$GLOBALS['mi_user_id'] = 10;
$GLOBALS['mi_role'] = 'management';
$GLOBALS['mi_hr_schema_ready'] = false;
$GLOBALS['mi_profiles'] = array(
	10 => array( 'role' => 'management' ),
	20 => array( 'role' => 'management' ),
);
$GLOBALS['wpdb'] = new class() {
	public function prepare( $query, ...$args ) { $GLOBALS['mi_query_args'] = $args; return $query; }
	public function esc_like( $value ) { return addcslashes( $value, '_%\\' ); }
};

function add_action( ...$args ) {}
function register_rest_route( $namespace, $route, $args ) {
	$GLOBALS['mi_routes'][ trim( $namespace, '/' ) . $route ] = $args;
}
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function wp_json_encode( $value, $flags = 0, $depth = 512 ) { return json_encode( $value, $flags, $depth ); }
function rest_ensure_response( $value ) { return $value; }
function get_current_user_id() { return (int) $GLOBALS['mi_user_id']; }
function current_time( $type = 'mysql' ) { return '2026-11-13 12:00:00'; }
function absint( $value ) { return abs( (int) $value ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code, $message = '', $data = null ) {
		$this->code = $code;
		$this->message = $message;
		$this->data = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
class WP_REST_Server {
	const READABLE = 'GET';
	const CREATABLE = 'POST';
}
class WP_REST_Request implements ArrayAccess {
	private $params;
	private $json;
	public function __construct( $json = array(), $params = array() ) {
		$this->json = $json;
		$this->params = $params;
	}
	public function get_json_params() { return $this->json; }
	public function offsetExists( $offset ): bool { return array_key_exists( $offset, $this->params ); }
	public function offsetGet( $offset ): mixed { return $this->params[ $offset ] ?? null; }
	public function offsetSet( $offset, $value ): void { $this->params[ $offset ] = $value; }
	public function offsetUnset( $offset ): void { unset( $this->params[ $offset ] ); }
}
class Stockland_REST_Base {
	const NAMESPACE_V1 = 'stockland/v16';
}
class Stockland_Roles {
	public static function get_profile( $user_id ) {
		return $GLOBALS['mi_profiles'][ (int) $user_id ] ?? null;
	}
}
class Stockland_Authority {
	public static function can_view_management_intelligence() {
		return in_array( $GLOBALS['mi_role'], array( 'owner', 'management' ), true );
	}
	public static function can_manage_management_intelligence() {
		return self::can_view_management_intelligence();
	}
}
class Stockland_Money {
	public static function minor( $value ) {
		$value = trim( (string) $value );
		if ( ! preg_match( '/^(-?)([0-9]+)(?:\\.([0-9]{1,2}))?$/', $value, $m ) ) {
			throw new InvalidArgumentException( 'Invalid decimal.' );
		}
		$cents = (int) ( $m[2] ) * 100 + (int) str_pad( $m[3] ?? '', 2, '0' );
		return '-' === $m[1] ? -$cents : $cents;
	}
	public static function decimal( $minor ) {
		$minor = (int) $minor;
		$sign = $minor < 0 ? '-' : '';
		$minor = abs( $minor );
		return $sign . intdiv( $minor, 100 ) . '.' . str_pad( (string) ( $minor % 100 ), 2, '0', STR_PAD_LEFT );
	}
}
class Stockland_DB {
	public static $settings = array();
	public static $ledger_calls = 0;
	public static function table( $key ) { return 'wp_stock_' . $key; }
	public static function table_exists( $table ) { return 'wp_stock_settings' === $table; }
	public static function read_results( $query, $output = ARRAY_A ) {
		$args   = $GLOBALS['mi_query_args'] ?? array();
		$prefix = isset( $args[1] ) ? (string) $args[1] : '';
		$prefix = str_replace( array( '\\_', '\\%', '\\\\' ), array( '_', '%', '\\' ), $prefix );
		$prefix = rtrim( $prefix, '%' );
		$limit  = isset( $args[2] ) ? max( 0, (int) $args[2] ) : count( self::$settings );
		$rows   = array_values(
			array_filter(
				self::$settings,
				static function ( $row ) use ( $prefix ) {
					$key = (string) ( $row['setting_key'] ?? '' );
					return '' === $prefix || 0 === strpos( $key, $prefix );
				}
			)
		);
		return array_slice( $rows, 0, $limit );
	}
	public static function insert_record( $table, $data, $format = null ) {
		$key = $data['setting_key'];
		if ( isset( self::$settings[ $key ] ) ) {
			return false;
		}
		self::$settings[ $key ] = $data;
		return count( self::$settings );
	}
	public static function delete_record( $table, $where, $where_format = null ) {
		$key = $where['setting_key'];
		if ( ! isset( self::$settings[ $key ] ) ) {
			return 0;
		}
		unset( self::$settings[ $key ] );
		return 1;
	}
	public static function get_setting( $key, $fallback = null ) {
		return self::$settings[ $key ]['setting_value'] ?? $fallback;
	}
}
class Stockland_Audit {
	public static $events = array();
	public static $fail_next = false;
	public static function log( ...$args ) {
		if ( self::$fail_next ) {
			self::$fail_next = false;
			return false;
		}
		self::$events[] = $args;
		return true;
	}
}

require dirname( __DIR__, 3 ) . '/src/stockland-erp-app/includes/rest/class-rest-management-intelligence.php';

$checks = 0;
function check_result( $label, $condition, $detail = '' ) {
	global $checks;
	++$checks;
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$label}: {$detail}\n" );
		exit( 1 );
	}
	echo "PASS {$label}\n";
}
function reset_fakes() {
	Stockland_DB::$settings = array();
	Stockland_DB::$ledger_calls = 0;
	Stockland_Audit::$events = array();
	Stockland_Audit::$fail_next = false;
	$GLOBALS['mi_routes'] = array();
	$GLOBALS['mi_user_id'] = 10;
	$GLOBALS['mi_role'] = 'management';
	$GLOBALS['mi_hr_schema_ready'] = false;
}

reset_fakes();
$controller = new Stockland_REST_Management_Intelligence();
$controller->register_routes();

$campaign_route = $GLOBALS['mi_routes']['stockland/v16/management/social'] ?? null;
$snapshot_route = $GLOBALS['mi_routes']['stockland/v16/management/social/campaigns/(?P<campaign_code>[A-Z0-9-]+)/snapshots'] ?? null;
check_result( 'management social GET/POST routes registered outside HR namespace', is_array( $campaign_route ) && count( $campaign_route ) === 2 );
check_result( 'snapshot append route registered outside HR namespace', is_array( $snapshot_route ) && 'POST' === $snapshot_route['methods'] );

$get_permission = $campaign_route[0]['permission_callback'];
$post_permission = $campaign_route[1]['permission_callback'];
$snapshot_permission = $snapshot_route['permission_callback'];
$GLOBALS['mi_role'] = 'management';
check_result( 'Management can view workspace while HR lifecycle is unavailable', true === $get_permission() && false === $GLOBALS['mi_hr_schema_ready'] );
check_result( 'Management can create campaign drafts without HR capability', true === $post_permission() && true === $snapshot_permission() );
$GLOBALS['mi_role'] = 'owner';
check_result( 'Owner can access workspace', true === $get_permission() && true === $post_permission() );
$GLOBALS['mi_role'] = 'hr_manager';
check_result( 'HR-only role cannot view or write Management Intelligence', false === $get_permission() && false === $post_permission() && false === $snapshot_permission() );
$GLOBALS['mi_role'] = 'branch_manager';
check_result( 'Branch role cannot view or write Management Intelligence', false === $get_permission() && false === $post_permission() );

$GLOBALS['mi_role'] = 'management';
$GLOBALS['mi_user_id'] = 10;
$campaign = $controller->create_campaign(
	new WP_REST_Request(
		array(
			'title' => 'November product video campaign',
			'platform' => 'INSTAGRAM',
			'account_label' => 'Stockland main account',
			'start_date' => '2026-11-14',
			'end_date' => '2026-11-30',
			'budget' => '1250.50',
			'manager_user_id' => '20',
		)
	)
);
check_result( 'campaign draft saved in Core settings store', is_array( $campaign ) && isset( $campaign['campaign'] ) && 1 === count( Stockland_DB::$settings ) );
$campaign_record = $campaign['campaign'];
check_result( 'campaign remains DRAFT and budget is planning-only', 'DRAFT' === $campaign_record['status'] && 'PLANNING_ESTIMATE_NOT_APPROVED_EXPENSE' === $campaign_record['budget_class'] && '1250.50' === $campaign_record['budget'] );
check_result( 'campaign write has immutable audit event', 1 === count( Stockland_Audit::$events ) && 'MI_SOCIAL_CAMPAIGN_CREATE' === Stockland_Audit::$events[0][2] );
check_result( 'campaign write does not call financial/stock ledger services', 0 === Stockland_DB::$ledger_calls );

$listed = $controller->list_social( new WP_REST_Request() );
check_result( 'Management can list campaign and snapshots from Core storage', is_array( $listed ) && 1 === count( $listed['campaigns'] ) && 0 === count( $listed['snapshots'] ) && 'CORE_SETTINGS' === $listed['storage'] );

$snapshot_payload = array(
	'platform' => 'INSTAGRAM',
	'account_label' => 'Stockland main account',
	'period_start' => '2026-11-14',
	'period_end' => '2026-11-14',
	'report_as_of' => '2026-11-14T18:30',
	'source_reference' => 'instagram-insights-2026-11-14.csv',
	'source_sha256' => str_repeat( 'a', 64 ),
	'reach_count' => '',
	'view_count' => '',
	'engagement_count' => '42',
	'click_count' => '',
	'lead_count' => '',
	'follower_delta' => '',
	'watch_time_minutes' => '',
	'spend_amount' => '',
	'attributed_sales' => '',
);
$snapshot = $controller->create_snapshot(
	new WP_REST_Request( $snapshot_payload, array( 'campaign_code' => $campaign_record['campaign_code'] ) )
);
check_result( 'manual export snapshot accepts empty optional metrics', is_array( $snapshot ) && isset( $snapshot['snapshot'] ) );
$snapshot_record = $snapshot['snapshot'];
check_result( 'snapshot stores normalized explicit report timestamp', '2026-11-14 18:30:00' === $snapshot_record['report_as_of'] );
check_result( 'empty optional metrics stay NULL and numeric metrics remain whole numbers', null === $snapshot_record['reach_count'] && 42 === $snapshot_record['engagement_count'] && null === $snapshot_record['follower_delta'] );
check_result( 'snapshot is manual-export evidence with source hash', 'MANUAL_EXPORT' === $snapshot_record['source_kind'] && str_repeat( 'a', 64 ) === $snapshot_record['source_sha256'] );
check_result( 'snapshot append is audited and does not touch ledger', 2 === count( Stockland_Audit::$events ) && 'MI_SOCIAL_SNAPSHOT_APPEND' === Stockland_Audit::$events[1][2] && 0 === Stockland_DB::$ledger_calls );

$duplicate = $controller->create_snapshot(
	new WP_REST_Request( $snapshot_payload, array( 'campaign_code' => $campaign_record['campaign_code'] ) )
);
check_result( 'retry of identical export is rejected without duplicate append', is_wp_error( $duplicate ) && 409 === $duplicate->get_error_data()['status'] && 2 === count( Stockland_Audit::$events ) );

$invalid_hash_payload = $snapshot_payload;
$invalid_hash_payload['source_sha256'] = 'not-a-hash';
$invalid_hash = $controller->create_snapshot(
	new WP_REST_Request( $invalid_hash_payload, array( 'campaign_code' => $campaign_record['campaign_code'] ) )
);
check_result( 'invalid source hash is rejected', is_wp_error( $invalid_hash ) && 'management_social_snapshot_evidence_required' === $invalid_hash->get_error_code() );

$count_before_failure = count( Stockland_DB::$settings );
$audits_before_failure = count( Stockland_Audit::$events );
Stockland_Audit::$fail_next = true;
$failed_campaign = $controller->create_campaign(
	new WP_REST_Request(
		array(
			'title' => 'Audit failure rollback test',
			'platform' => 'FACEBOOK',
			'account_label' => 'Test page',
			'budget' => '0.00',
		)
	)
);
check_result( 'audit failure rejects campaign and removes unaccepted Core setting', is_wp_error( $failed_campaign ) && 'management_social_audit_failed' === $failed_campaign->get_error_code() && $count_before_failure === count( Stockland_DB::$settings ) && $audits_before_failure === count( Stockland_Audit::$events ) );

$GLOBALS['mi_role'] = 'hr_manager';
$forbidden_list = $get_permission() ? $controller->list_social( new WP_REST_Request() ) : false;
check_result( 'HR-only user has no Management Intelligence read access', false === $forbidden_list );

echo "RESULT PASS ({$checks} assertions)\n";
