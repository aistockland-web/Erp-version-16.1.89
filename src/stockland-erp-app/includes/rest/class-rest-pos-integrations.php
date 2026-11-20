<?php
/**
 * Vendor-neutral POS integration registry for Stockland ERP 16.2.18.
 *
 * This first additive slice registers branch POS devices/connectors in the
 * existing Core settings store. It does not create schema, store credentials,
 * claim a vendor API is connected, or write sales/stock/finance records.
 * Every future inbound transaction adapter must normalize into the existing
 * Daily POS preview/staging and Branch Manager release workflow.
 *
 * @package Stockland_ERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Stockland_REST_POS_Integrations extends Stockland_REST_Base {

	const DEVICE_PREFIX = 'pos_int_device_';
	const MAX_DEVICES   = 100;

	/**
	 * Register REST hooks after the existing Core operational schema is ready.
	 */
	public function __construct() {
		add_action( 'stockland_register_rest_routes', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the universal POS integration registry endpoints.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/pos-integrations/adapters',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'adapter_catalog' ),
				'permission_callback' => array( $this, 'can_read' ),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/pos-integrations/devices',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_devices' ),
					'permission_callback' => array( $this, 'can_read' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'register_device' ),
					'permission_callback' => array( $this, 'can_register_device' ),
				),
			)
		);
	}

	/**
	 * View access is available to Owner, Management and an assigned Branch Manager.
	 *
	 * @return true|WP_Error
	 */
	public function can_read() {
		$role = Stockland_Authority::role();
		if ( Stockland_Authority::is_management() || 'branch_manager' === $role || Stockland_Authority::can_daily_import() ) {
			return true;
		}
		return new WP_Error( 'pos_integrations_forbidden', 'POS integration registry is available to Owner, Management and assigned Daily POS operators within their branch scope.', array( 'status' => 403 ) );
	}

	/**
	 * Device registration is branch-scoped for Branch Managers and company-wide for Owner/Management.
	 *
	 * @return true|WP_Error
	 */
	public function can_register_device() {
		$role = Stockland_Authority::role();
		if ( Stockland_Authority::is_management() || ( 'branch_manager' === $role && Stockland_Authority::can_daily_import() ) ) {
			return true;
		}
		return new WP_Error( 'pos_device_register_forbidden', 'Only Owner/Management or an assigned Branch Manager may register POS devices.', array( 'status' => 403 ) );
	}

	/**
	 * Return supported connection patterns without claiming a provider adapter is live.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function adapter_catalog( WP_REST_Request $request ) {
		return rest_ensure_response(
			array(
				'architecture' => 'STOCKLAND_UNIVERSAL_POS_INTEGRATION_HUB',
				'transaction_contract' => 'DAILY_POS_PREVIEW_STAGING_BRANCH_MANAGER_RELEASE',
				'connection_patterns' => array(
					array(
						'code' => 'REST_API',
						'label' => 'Vendor REST API',
						'state' => 'ADAPTER_REQUIRED',
						'description' => 'Authenticated supplier API integration with paging, replay protection and reconciliation.',
					),
					array(
						'code' => 'WEBHOOK',
						'label' => 'Vendor Webhook',
						'state' => 'ADAPTER_REQUIRED',
						'description' => 'Signed inbound events accumulated into a reconciled daily batch before ERP staging.',
					),
					array(
						'code' => 'LOCAL_GATEWAY',
						'label' => 'Branch Computer / Local Gateway',
						'state' => 'FRAMEWORK_AVAILABLE',
						'description' => 'A branch-side service submits supported POS exports over outbound HTTPS; it cannot release transactions.',
					),
					array(
						'code' => 'DEVICE_SDK',
						'label' => 'Device SDK / Manufacturer Service',
						'state' => 'ADAPTER_REQUIRED',
						'description' => 'A separate adapter uses the manufacturer-supported SDK or device service.',
					),
					array(
						'code' => 'FILE_EXCHANGE',
						'label' => 'File Exchange',
						'state' => 'FRAMEWORK_AVAILABLE',
						'description' => 'CSV/XLSX file intake uses the existing Daily POS preview, validation and staging workflow.',
					),
				),
				'controls' => array(
					'separate_vendor_adapters' => true,
					'branch_scope_enforced' => true,
					'credentials_stored_in_erp' => false,
					'direct_sales_or_ledger_write' => false,
					'connected_state_requires_authenticated_test' => true,
				),
				'notice' => 'A connection pattern is not a connected vendor. Provider adapters remain NOT CONNECTED until official technical documentation, authorized credentials and transaction replay/reconciliation tests are completed.',
			)
		);
	}

	/**
	 * Return registered devices within the current user's authorized branch scope.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_devices( WP_REST_Request $request ) {
		global $wpdb;
		$table = $this->settings_table();
		if ( is_wp_error( $table ) ) {
			return $table;
		}

		$rows = Stockland_DB::read_results(
			$wpdb->prepare(
				'SELECT setting_key, setting_value, updated_at FROM %i WHERE setting_key LIKE %s ORDER BY updated_at DESC, setting_key DESC LIMIT %d',
				$table,
				$wpdb->esc_like( self::DEVICE_PREFIX ) . '%',
				self::MAX_DEVICES
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return new WP_Error( 'pos_device_read_failed', 'POS device registry could not be read.', array( 'status' => 500 ) );
		}

		$company_scope = Stockland_Authority::is_management();
		$allowed       = array_map( 'intval', Stockland_Authority::location_ids() );
		$devices       = array();

		foreach ( $rows as $row ) {
			$key = (string) ( $row['setting_key'] ?? '' );
			if ( 0 !== strpos( $key, self::DEVICE_PREFIX ) ) {
				return new WP_Error( 'pos_device_scope_invalid', 'The device query returned a record outside its namespace.', array( 'status' => 500 ) );
			}
			$device = json_decode( (string) ( $row['setting_value'] ?? '' ), true );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $device ) || empty( $device['device_id'] ) || empty( $device['branch_location_id'] ) ) {
				return new WP_Error( 'pos_device_record_invalid', 'A POS device registry record is invalid. It was not silently omitted.', array( 'status' => 500 ) );
			}
			if ( ! $company_scope && ! in_array( (int) $device['branch_location_id'], $allowed, true ) ) {
				continue;
			}
			$devices[] = $this->public_device_record( $device );
		}

		return rest_ensure_response(
			array(
				'devices' => $devices,
				'count' => count( $devices ),
				'scope' => $company_scope ? 'COMPANY' : 'ASSIGNED_BRANCHES',
				'storage' => 'EXISTING_CORE_SETTINGS_NO_SCHEMA_CHANGE',
				'workflow' => 'REGISTERED_DEVICE_DATA_IS_NOT_CONNECTED_OR_AUTHORIZED_TO_POST',
			)
		);
	}

	/**
	 * Register one branch POS device. No password, token, API key or fiscal secret is accepted.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function register_device( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'pos_device_payload_invalid', 'Send a JSON device registration.', array( 'status' => 400 ) );
		}

		foreach ( array( 'password', 'api_key', 'token', 'secret', 'client_secret', 'access_token' ) as $secret_field ) {
			if ( array_key_exists( $secret_field, $payload ) ) {
				return new WP_Error( 'pos_device_secret_not_accepted', 'Do not submit credentials in device registration. Provider credentials must be provisioned outside ERP and referenced by an authorized adapter.', array( 'status' => 400 ) );
			}
		}

		$device_name = sanitize_text_field( (string) ( $payload['device_name'] ?? '' ) );
		$vendor      = sanitize_text_field( (string) ( $payload['vendor'] ?? '' ) );
		$model       = sanitize_text_field( (string) ( $payload['model'] ?? '' ) );
		$transport   = strtoupper( sanitize_key( (string) ( $payload['connection_type'] ?? '' ) ) );
		if ( '' === $device_name || strlen( $device_name ) > 80 || '' === $vendor || strlen( $vendor ) > 80 || strlen( $model ) > 80 ) {
			return new WP_Error( 'pos_device_details_invalid', 'Provide a device name and vendor; each field is limited to 80 characters.', array( 'status' => 400 ) );
		}
		$allowed_transports = array( 'REST_API', 'WEBHOOK', 'LOCAL_GATEWAY', 'DEVICE_SDK', 'FILE_EXCHANGE' );
		if ( ! in_array( $transport, $allowed_transports, true ) ) {
			return new WP_Error( 'pos_device_transport_invalid', 'Choose a supported connection pattern from the integration registry.', array( 'status' => 400 ) );
		}

		$branch_id = absint( $payload['branch_location_id'] ?? 0 );
		$branch    = $this->active_branch( $branch_id );
		if ( is_wp_error( $branch ) ) {
			return $branch;
		}
		if ( ! Stockland_Authority::is_management() && ! Stockland_Authority::can_access_location( $branch_id ) ) {
			return new WP_Error( 'pos_device_branch_forbidden', 'A Branch Manager may register devices only for assigned branch locations.', array( 'status' => 403 ) );
		}

		try {
			$device_id = strtoupper( bin2hex( random_bytes( 8 ) ) );
			$source_system = 'POS_' . substr( hash( 'sha256', $device_id ), 0, 16 );
			$record = array(
				'device_id' => $device_id,
				'vendor' => $vendor,
				'device_name' => $device_name,
				'model' => $model,
				'connection_type' => $transport,
				'branch_location_id' => $branch_id,
				'branch_name' => (string) $branch['location_name'],
				'source_system' => $source_system,
				'data_direction' => 'INBOUND_POS_SALES',
				'adapter_state' => 'ADAPTER_REQUIRED',
				'connection_status' => 'NOT_CONNECTED',
				'credential_status' => 'NOT_CONFIGURED',
				'last_sync_at' => null,
				'registered_by' => get_current_user_id(),
				'registered_at' => current_time( 'mysql' ),
				'posting_path' => 'IMPORT_VALIDATE_STAGE_RECONCILE_BRANCH_MANAGER_RELEASE',
				'notes' => 'Every file upload, including one performed by the Branch Manager, is staging only and never constitutes approval or posting. The assigned Branch Manager must separately review and invoke the explicit Daily POS release action. That release is the single final business approval; no additional Owner or Management approval follows. The source becomes CONNECTED only after its first successful released import.',
			);
		} catch ( Throwable $error ) {
			return new WP_Error( 'pos_device_id_failed', 'A unique device registration ID could not be generated.', array( 'status' => 500 ) );
		}

		$key = self::DEVICE_PREFIX . strtolower( $device_id );
		$saved = $this->store_device_record( $key, $record, $branch_id );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return rest_ensure_response(
			array(
				'device' => $this->public_device_record( $record ),
				'status' => 'NOT_CONNECTED',
				'message' => 'Source registered. Every upload is validated, staged and reconciled only; even a Branch Manager upload is not approval. The assigned Branch Manager must separately invoke the explicit Daily POS release action, which is the single final business approval. No additional Owner or Management approval follows.',
			)
		);
	}

	/**
	 * Resolve an active retail branch, never a warehouse or sales channel.
	 *
	 * @param int $branch_id Location ID.
	 * @return array|WP_Error
	 */
	private function active_branch( $branch_id ) {
		global $wpdb;
		if ( $branch_id <= 0 ) {
			return new WP_Error( 'pos_device_branch_required', 'Select an active branch for this device.', array( 'status' => 400 ) );
		}
		$branch = Stockland_DB::read_row(
			$wpdb->prepare(
				'SELECT id, location_name, location_type FROM %i WHERE id=%d AND is_active=1 LIMIT 1',
				Stockland_DB::table( 'locations' ),
				$branch_id
			),
			ARRAY_A
		);
		if ( ! $branch || 'Branch' !== (string) ( $branch['location_type'] ?? '' ) ) {
			return new WP_Error( 'pos_device_branch_invalid', 'POS devices can be assigned only to active retail branches.', array( 'status' => 409 ) );
		}
		return $branch;
	}

	/**
	 * Verify Core settings storage is physically available.
	 *
	 * @return string|WP_Error
	 */
	private function settings_table() {
		$table = Stockland_DB::table( 'settings' );
		if ( ! Stockland_DB::table_exists( $table ) ) {
			return new WP_Error( 'pos_device_schema_unavailable', 'Core settings storage is unavailable. No connector registry record was saved.', array( 'status' => 503 ) );
		}
		return $table;
	}

	/**
	 * Append a registration record and bind it to the immutable audit chain.
	 *
	 * @param string $key Unique Core settings key.
	 * @param array  $record Device data.
	 * @param int    $branch_id Branch location ID.
	 * @return true|WP_Error
	 */
	private function store_device_record( $key, $record, $branch_id ) {
		$table = $this->settings_table();
		if ( is_wp_error( $table ) ) {
			return $table;
		}
		if ( strlen( $key ) > 100 ) {
			return new WP_Error( 'pos_device_key_invalid', 'The registration key exceeds the existing Core settings schema limit.', array( 'status' => 500 ) );
		}
		$json = wp_json_encode( $record );
		if ( ! is_string( $json ) ) {
			return new WP_Error( 'pos_device_encode_failed', 'The device record could not be encoded.', array( 'status' => 500 ) );
		}
		if ( false === Stockland_DB::insert_record(
			$table,
			array(
				'setting_key' => $key,
				'setting_value' => $json,
				'updated_by' => get_current_user_id(),
			),
			array( '%s', '%s', '%d' )
		) ) {
			return new WP_Error( 'pos_device_register_failed', 'The registration could not be saved. Existing records were not overwritten.', array( 'status' => 409 ) );
		}
		$audit_pointer = 'CORE_SETTINGS:' . $key . ':sha256:' . hash( 'sha256', $json );
		$audited = strlen( $audit_pointer ) <= 255 && Stockland_Audit::log(
			get_current_user_id(),
			$branch_id,
			'POS_DEVICE_REGISTER',
			null,
			null,
			null,
			$audit_pointer,
			'POS source registered; credentials and live endpoint were not stored.',
			$record['device_id'],
			get_current_user_id()
		);
		if ( ! $audited ) {
			$deleted = Stockland_DB::delete_record( $table, array( 'setting_key' => $key ), array( '%s' ) );
			if ( false === $deleted ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Critical rollback failure must be recorded; this fixed message contains no user data or credentials.
				error_log( 'Stockland POS integrations: audit failure and pending device rollback both failed.' );
			}
			return new WP_Error( 'pos_device_audit_failed', 'The immutable audit event could not be recorded. The device registration was rejected.', array( 'status' => 500 ) );
		}
		return true;
	}

	/**
	 * Resolve a registered POS device to its source-system namespace for one branch import.
	 *
	 * The request still must pass the existing Daily POS REST capability and branch
	 * authorization checks. This lookup only selects the product-code mapping namespace;
	 * it does not authenticate a device or change its NOT_CONNECTED status.
	 *
	 * @param string $device_id Registered device ID.
	 * @param int    $branch_id Authorized branch ID.
	 * @return array|WP_Error Registered device metadata or a fail-closed error.
	 */
	public static function resolve_device_for_import( $device_id, $branch_id ) {
		global $wpdb;
		$device_id = strtoupper( sanitize_text_field( (string) $device_id ) );
		$branch_id = (int) $branch_id;
		if ( ! preg_match( '/^[A-F0-9]{16}$/', $device_id ) || $branch_id <= 0 ) {
			return new WP_Error( 'pos_device_reference_invalid', 'The selected POS device reference is invalid.', array( 'status' => 400 ) );
		}
		$table = Stockland_DB::table( 'settings' );
		if ( ! Stockland_DB::table_exists( $table ) ) {
			return new WP_Error( 'pos_device_registry_unavailable', 'The POS device registry is unavailable.', array( 'status' => 503 ) );
		}
		$key = self::DEVICE_PREFIX . strtolower( $device_id );
		$json = Stockland_DB::read_var(
			$wpdb->prepare( 'SELECT setting_value FROM %i WHERE setting_key=%s LIMIT 1', $table, $key )
		);
		$device = json_decode( (string) $json, true );
		if ( ! is_array( $device ) || JSON_ERROR_NONE !== json_last_error() || strtoupper( (string) ( $device['device_id'] ?? '' ) ) !== $device_id ) {
			return new WP_Error( 'pos_device_not_registered', 'The selected POS device is not registered in Stockland ERP.', array( 'status' => 404 ) );
		}
		if ( (int) ( $device['branch_location_id'] ?? 0 ) !== $branch_id ) {
			return new WP_Error( 'pos_device_branch_mismatch', 'The selected POS device is registered to a different branch. Select a device registered to this branch.', array( 'status' => 403 ) );
		}
		$source_system = (string) ( $device['source_system'] ?? '' );
		if ( ! preg_match( '/^POS_[a-f0-9]{16}$/', $source_system ) ) {
			return new WP_Error( 'pos_device_source_invalid', 'The selected POS device has an invalid source-system mapping namespace.', array( 'status' => 409 ) );
		}
		return $device;
	}

	/**
	 * Mark a registered POS source CONNECTED after its staged Daily POS job is fully POSTED.
	 *
	 * @param string $source_system Source-system namespace stored on the released import job.
	 * @param int    $branch_id     Branch that owns the released job.
	 * @param int    $job_id        Fully posted Daily POS import job ID.
	 * @return bool True when exactly one matching device record was updated and verified.
	 */
	public static function mark_synced_for_source( $source_system, $branch_id, $job_id ) {
		global $wpdb;
		$source_system = sanitize_text_field( (string) $source_system );
		$branch_id     = (int) $branch_id;
		$job_id        = (int) $job_id;
		if ( ! preg_match( '/^POS_[a-f0-9]{16}$/', $source_system ) || $branch_id < 1 || $job_id < 1 ) {
			return false;
		}
		$table = Stockland_DB::table( 'settings' );
		if ( ! Stockland_DB::table_exists( $table ) ) {
			return false;
		}
		$wpdb->last_error = '';
		$rows = Stockland_DB::read_results(
			$wpdb->prepare(
				'SELECT setting_key,setting_value FROM %i WHERE setting_key LIKE %s',
				$table,
				$wpdb->esc_like( self::DEVICE_PREFIX ) . '%'
			),
			ARRAY_A
		);
		if ( '' !== (string) $wpdb->last_error || ! is_array( $rows ) ) {
			return false;
		}
		$matches = array();
		foreach ( $rows as $row ) {
			$device = json_decode( (string) ( $row['setting_value'] ?? '' ), true );
			if ( is_array( $device )
				&& (string) ( $device['source_system'] ?? '' ) === $source_system
				&& (int) ( $device['branch_location_id'] ?? 0 ) === $branch_id
			) {
				$matches[] = array( 'key' => (string) ( $row['setting_key'] ?? '' ), 'device' => $device );
			}
		}
		if ( 1 !== count( $matches ) ) {
			return false;
		}
		return self::mark_synced( $matches[0]['key'], $matches[0]['device'], $job_id );
	}

	/**
	 * Persist and verify the CONNECTED state after a fully posted Daily POS job.
	 *
	 * @param string $key    Existing Core settings key for the registered device.
	 * @param array  $device Registered device record.
	 * @param int    $job_id Fully posted Daily POS import job ID.
	 * @return bool True only when the stored connection metadata reads back exactly.
	 */
	private static function mark_synced( $key, $device, $job_id ) {
		global $wpdb;
		$table = Stockland_DB::table( 'settings' );
		if ( ! preg_match( '/^' . preg_quote( self::DEVICE_PREFIX, '/' ) . '[a-f0-9]{16}$/', (string) $key ) ) {
			return false;
		}
		$device['connection_status'] = 'CONNECTED';
		$device['adapter_state']     = 'ACTIVE';
		$device['last_sync_at']      = current_time( 'mysql' );
		$device['last_job_id']       = (int) $job_id;
		$json = wp_json_encode( $device );
		if ( ! is_string( $json ) || false === Stockland_DB::update_record( $table, array( 'setting_value' => $json ), array( 'setting_key' => $key ) ) ) {
			return false;
		}
		$wpdb->last_error = '';
		$stored = Stockland_DB::read_var( $wpdb->prepare( 'SELECT setting_value FROM %i WHERE setting_key=%s LIMIT 1', $table, $key ) );
		if ( '' !== (string) $wpdb->last_error ) {
			return false;
		}
		$verified = json_decode( (string) $stored, true );
		return is_array( $verified )
			&& 'CONNECTED' === (string) ( $verified['connection_status'] ?? '' )
			&& 'ACTIVE' === (string) ( $verified['adapter_state'] ?? '' )
			&& (int) ( $verified['last_job_id'] ?? 0 ) === (int) $job_id;
	}

	/**
	 * Return only non-secret device fields.
	 *
	 * @param array $device Stored device data.
	 * @return array Public device fields.
	 */
	private function public_device_record( $device ) {
		return array(
			'device_id' => (string) ( $device['device_id'] ?? '' ),
			'vendor' => (string) ( $device['vendor'] ?? '' ),
			'device_name' => (string) ( $device['device_name'] ?? '' ),
			'model' => (string) ( $device['model'] ?? '' ),
			'connection_type' => (string) ( $device['connection_type'] ?? '' ),
			'branch_location_id' => (int) ( $device['branch_location_id'] ?? 0 ),
			'branch_name' => (string) ( $device['branch_name'] ?? '' ),
			'source_system' => (string) ( $device['source_system'] ?? '' ),
			'data_direction' => 'INBOUND_POS_SALES',
			'adapter_state' => (string) ( $device['adapter_state'] ?? 'ADAPTER_REQUIRED' ),
			'connection_status' => (string) ( $device['connection_status'] ?? 'NOT_CONNECTED' ),
			'credential_status' => 'NOT_CONFIGURED',
			'last_sync_at' => $device['last_sync_at'] ?? null,
			'registered_by' => (int) ( $device['registered_by'] ?? 0 ),
			'registered_at' => (string) ( $device['registered_at'] ?? '' ),
			'posting_path' => (string) ( $device['posting_path'] ?? 'IMPORT_VALIDATE_STAGE_RECONCILE_BRANCH_MANAGER_RELEASE' ),
			'upload_is_approval' => false,
			'release_action' => 'EXPLICIT_DAILY_POS_COMMIT',
			'release_route' => '/stockland/v16/import/daily-sales/commit',
			'release_authority' => 'ASSIGNED_BRANCH_MANAGER_ONLY',
		);
	}
}
