<?php
// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName -- REST controller filenames follow the established Stockland loader convention.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owner/Management campaign planning and manual social export reporting.
 *
 * Records are stored as namespaced JSON values in the existing Core
 * stock_settings table. This avoids HR lifecycle tables, new schema DDL,
 * payroll permissions, external credentials and provider/API assumptions.
 * Snapshots are append-only. These records never post to stock, sales,
 * expenses, cash, bank or the general ledger.
 *
 * @package Stockland_ERP
 */
final class Stockland_REST_Management_Intelligence extends Stockland_REST_Base {

	const CAMPAIGN_PREFIX = 'mi_social_campaign_';
	const SNAPSHOT_PREFIX = 'mi_social_snapshot_';
	const PLATFORMS       = array( 'FACEBOOK', 'INSTAGRAM', 'TIKTOK', 'YOUTUBE', 'OTHER' );

	public function __construct() {
		add_action( 'stockland_register_rest_routes', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		$namespace = self::NAMESPACE_V1;
		register_rest_route(
			$namespace,
			'/management/social',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_social' ),
					'permission_callback' => function () {
						return Stockland_Authority::can_view_management_intelligence();
					},
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_campaign' ),
					'permission_callback' => function () {
						return Stockland_Authority::can_manage_management_intelligence();
					},
				),
			)
		);
		register_rest_route(
			$namespace,
			'/management/social/campaigns/(?P<campaign_code>[A-Z0-9-]+)/snapshots',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_snapshot' ),
				'permission_callback' => function () {
					return Stockland_Authority::can_manage_management_intelligence();
				},
			)
		);
	}

	private function bad( $code, $message, $status = 400 ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	private function settings_table() {
		$table = Stockland_DB::table( 'settings' );
		if ( ! Stockland_DB::table_exists( $table ) ) {
			return $this->bad( 'management_settings_schema_unavailable', 'Core settings storage is not ready. HR lifecycle readiness is not required for Management Intelligence.', 503 );
		}
		return $table;
	}

	private function date_value( $value, $optional = false ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value && $optional ) {
			return null;
		}
		$date = DateTime::createFromFormat( '!Y-m-d', $value );
		$errors = DateTime::getLastErrors();
		if ( ! $date || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) || $date->format( 'Y-m-d' ) !== $value ) {
			return false;
		}
		return $value;
	}

	private function money_value( $value, $field ) {
		try {
			$amount = Stockland_Money::decimal( Stockland_Money::minor( $value ) );
		} catch ( Throwable $error ) {
			return $this->bad( 'management_social_amount_invalid', $field . ' must be a valid ETB amount.' );
		}
		if ( Stockland_Money::minor( $amount ) < 0 ) {
			return $this->bad( 'management_social_amount_invalid', $field . ' cannot be negative.' );
		}
		return $amount;
	}

	private function read_records( $prefix, $limit ) {
		global $wpdb;
		$table = $this->settings_table();
		if ( is_wp_error( $table ) ) {
			return $table;
		}
		$rows = Stockland_DB::read_results(
			$wpdb->prepare(
				'SELECT setting_key, setting_value FROM %i WHERE setting_key LIKE %s ORDER BY updated_at DESC, setting_key DESC LIMIT %d',
				$table,
				$wpdb->esc_like( $prefix ) . '%',
				(int) $limit
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return $this->bad( 'management_social_read_failed', 'Management Intelligence records could not be read.', 500 );
		}
		$records = array();
		foreach ( $rows as $row ) {
			$key = isset( $row['setting_key'] ) ? (string) $row['setting_key'] : '';
			if ( 0 !== strpos( $key, $prefix ) ) {
				return $this->bad( 'management_social_scope_invalid', 'The Core settings query returned a record outside the requested Management Intelligence namespace.', 500 );
			}
			$record = json_decode( (string) ( $row['setting_value'] ?? '' ), true );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $record ) ) {
				return $this->bad( 'management_social_record_invalid', 'A stored Management Intelligence record is invalid. The result was not silently omitted.', 500 );
			}
			$records[] = $record;
		}
		return $records;
	}

	private function store_record( $key, $record, $action, $reference, $reason ) {
		$table = $this->settings_table();
		if ( is_wp_error( $table ) ) {
			return $table;
		}
		$json = wp_json_encode( $record );
		if ( ! is_string( $json ) ) {
			return $this->bad( 'management_social_encode_failed', 'The Management Intelligence record could not be encoded.', 500 );
		}
		$inserted = Stockland_DB::insert_record(
			$table,
			array(
				'setting_key'   => $key,
				'setting_value' => $json,
				'updated_by'    => get_current_user_id(),
			),
			array( '%s', '%s', '%d' )
		);
		if ( false === $inserted ) {
			return $this->bad( 'management_social_save_failed', 'The record could not be saved. No existing record was overwritten.', 409 );
		}
		// The immutable audit table's new_value column is VARCHAR(255). Keep
		// the full campaign/snapshot payload in Core settings and append a compact,
		// verifiable pointer to the audit chain instead of overflowing that column.
		$audit_pointer = 'CORE_SETTINGS:' . $key . ':sha256:' . hash( 'sha256', $json );
		if ( strlen( $audit_pointer ) > 255 ) {
			$deleted = Stockland_DB::delete_record( $table, array( 'setting_key' => $key ), array( '%s' ) );
			return $this->bad( 'management_social_audit_reference_too_long', 'The audit reference exceeds the protected audit schema limit. The record was not accepted.', 500 );
		}
		if ( ! Stockland_Audit::log( get_current_user_id(), null, $action, null, null, null, $audit_pointer, $reason, $reference ) ) {
			$deleted = Stockland_DB::delete_record( $table, array( 'setting_key' => $key ), array( '%s' ) );
			if ( false === $deleted ) {
				error_log( 'Stockland Management Intelligence: audit failed and the pending record could not be removed.' );
			}
			return $this->bad( 'management_social_audit_failed', 'The immutable audit event could not be recorded. The record was not accepted.', 500 );
		}
		return true;
	}

	public function list_social( WP_REST_Request $request ) {
		$campaigns = $this->read_records( self::CAMPAIGN_PREFIX, 100 );
		if ( is_wp_error( $campaigns ) ) {
			return $campaigns;
		}
		$snapshots = $this->read_records( self::SNAPSHOT_PREFIX, 200 );
		if ( is_wp_error( $snapshots ) ) {
			return $snapshots;
		}
		return rest_ensure_response(
			array(
				'campaigns'      => $campaigns,
				'snapshots'      => $snapshots,
				'storage'        => 'CORE_SETTINGS',
				'module'         => 'MANAGEMENT_INTELLIGENCE',
				'reporting_mode' => 'MANUAL_OR_EXPORT_SNAPSHOT',
				'warning'        => 'Metrics are valid only as of each recorded report timestamp. No live platform integration, credential storage or automatic posting is enabled.',
			)
		);
	}

	public function create_campaign( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$title   = sanitize_text_field( (string) ( $payload['title'] ?? '' ) );
		$platform = strtoupper( sanitize_key( (string) ( $payload['platform'] ?? '' ) ) );
		$account = sanitize_text_field( (string) ( $payload['account_label'] ?? '' ) );
		if ( '' === $title || strlen( $title ) > 190 || '' === $account || strlen( $account ) > 120 || ! in_array( $platform, self::PLATFORMS, true ) ) {
			return $this->bad( 'management_social_campaign_invalid', 'Provide a campaign title (maximum 190 characters), account label (maximum 120 characters) and supported platform.' );
		}
		$start = $this->date_value( $payload['start_date'] ?? '', true );
		$end   = $this->date_value( $payload['end_date'] ?? '', true );
		if ( false === $start || false === $end || ( $start && $end && $end < $start ) ) {
			return $this->bad( 'management_social_campaign_dates_invalid', 'The campaign date range is invalid.' );
		}
		$budget = $this->money_value( $payload['budget'] ?? '0.00', 'Planned campaign budget' );
		if ( is_wp_error( $budget ) ) {
			return $budget;
		}
		$manager_id = isset( $payload['manager_user_id'] ) && '' !== $payload['manager_user_id'] ? absint( $payload['manager_user_id'] ) : null;
		if ( $manager_id && ! Stockland_Roles::get_profile( $manager_id ) ) {
			return $this->bad( 'management_social_manager_invalid', 'The assigned manager must have an active Stockland ERP profile.' );
		}
		try {
			$code = 'CMP-' . strtoupper( bin2hex( random_bytes( 6 ) ) );
		} catch ( Throwable $error ) {
			return $this->bad( 'management_social_id_failed', 'A unique campaign reference could not be generated.', 500 );
		}
		$record = array(
			'campaign_code'      => $code,
			'title'              => $title,
			'platform'           => $platform,
			'account_label'      => $account,
			'manager_user_id'    => $manager_id,
			'start_date'         => $start,
			'end_date'           => $end,
			'budget'             => $budget,
			'budget_class'       => 'PLANNING_ESTIMATE_NOT_APPROVED_EXPENSE',
			'status'             => 'DRAFT',
			'created_by'         => get_current_user_id(),
			'created_at'         => current_time( 'mysql' ),
			'reporting_mode'     => 'MANUAL_OR_EXPORT_SNAPSHOT',
		);
		$saved = $this->store_record(
			self::CAMPAIGN_PREFIX . $code,
			$record,
			'MI_SOCIAL_CAMPAIGN_CREATE',
			$code,
			'Owner/Management campaign plan created; planning estimate only, no ERP ledger posting.'
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return rest_ensure_response( array( 'campaign' => $record, 'reporting_mode' => 'MANUAL_OR_EXPORT_SNAPSHOT' ) );
	}

	public function create_snapshot( WP_REST_Request $request ) {
		$campaign_code = strtoupper( sanitize_text_field( (string) $request['campaign_code'] ) );
		if ( ! preg_match( '/^CMP-[A-F0-9]{12}$/', $campaign_code ) ) {
			return $this->bad( 'management_social_campaign_invalid', 'The campaign reference is invalid.' );
		}
		$campaign_json = Stockland_DB::get_setting( self::CAMPAIGN_PREFIX . $campaign_code );
		$campaign      = is_string( $campaign_json ) ? json_decode( $campaign_json, true ) : null;
		if ( ! is_array( $campaign ) || ( $campaign['campaign_code'] ?? '' ) !== $campaign_code ) {
			return $this->bad( 'management_social_campaign_missing', 'The campaign was not found.', 404 );
		}
		$payload = $request->get_json_params();
		$platform = strtoupper( sanitize_key( (string) ( $payload['platform'] ?? '' ) ) );
		$account = sanitize_text_field( (string) ( $payload['account_label'] ?? '' ) );
		$start   = $this->date_value( $payload['period_start'] ?? '' );
		$end     = $this->date_value( $payload['period_end'] ?? '' );
		$as_of   = sanitize_text_field( (string) ( $payload['report_as_of'] ?? '' ) );
		if ( ! in_array( $platform, self::PLATFORMS, true ) || $platform !== (string) $campaign['platform'] || $account !== (string) $campaign['account_label'] || false === $start || false === $end || $start > $end ) {
			return $this->bad( 'management_social_snapshot_invalid', 'Platform/account must match the campaign and the report period must be valid.' );
		}
		$as_of_normalized = str_replace( 'T', ' ', $as_of );
		if ( preg_match( '/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}$/', $as_of_normalized ) ) {
			$as_of_normalized .= ':00';
		}
		$as_of_object = DateTime::createFromFormat( '!Y-m-d H:i:s', $as_of_normalized );
		if ( ! $as_of_object || $as_of_object->format( 'Y-m-d H:i:s' ) !== $as_of_normalized ) {
			return $this->bad( 'management_social_report_timestamp_required', 'Provide an explicit report-as-of timestamp in YYYY-MM-DD HH:MM format.' );
		}
		$source_reference = sanitize_text_field( (string) ( $payload['source_reference'] ?? '' ) );
		$source_sha256 = strtolower( sanitize_text_field( (string) ( $payload['source_sha256'] ?? '' ) ) );
		if ( '' === $source_reference || strlen( $source_reference ) > 1000 || ! preg_match( '/^[a-f0-9]{64}$/', $source_sha256 ) ) {
			return $this->bad( 'management_social_snapshot_evidence_required', 'Record the platform export/Insights reference and its source-file SHA-256.' );
		}
		$metrics = array();
		foreach ( array( 'reach_count', 'view_count', 'engagement_count', 'click_count', 'lead_count' ) as $field ) {
			$value = $payload[ $field ] ?? null;
			if ( null === $value || '' === $value ) {
				$metrics[ $field ] = null;
				continue;
			}
			if ( ! is_numeric( $value ) || (float) $value < 0 || floor( (float) $value ) !== (float) $value ) {
				return $this->bad( 'management_social_metric_invalid', 'Count metrics must be non-negative whole numbers.' );
			}
			$metrics[ $field ] = (int) $value;
		}
		$watch_time_minutes = $payload['watch_time_minutes'] ?? null;
		if ( null === $watch_time_minutes || '' === $watch_time_minutes ) {
			$watch_time_minutes = null;
		} else {
			$watch_time_minutes = sanitize_text_field( (string) $watch_time_minutes );
			if ( ! preg_match( '/^(?:0|[1-9][0-9]*)(?:[.][0-9]{1,2})?$/D', $watch_time_minutes ) ) {
				return $this->bad( 'management_social_watch_time_invalid', 'Watch time must be a non-negative decimal number of minutes.' );
			}
		}

		$amounts = array();
		foreach ( array( 'spend_amount', 'attributed_sales' ) as $field ) {
			$value = $payload[ $field ] ?? null;
			if ( null === $value || '' === $value ) {
				$amounts[ $field ] = null;
				continue;
			}
			$amount = $this->money_value( $value, $field );
			if ( is_wp_error( $amount ) ) {
				return $amount;
			}
			$amounts[ $field ] = $amount;
		}
		$follower_delta = $payload['follower_delta'] ?? null;
		if ( '' === $follower_delta ) {
			$follower_delta = null;
		}
		if ( null !== $follower_delta && filter_var( $follower_delta, FILTER_VALIDATE_INT ) === false ) {
			return $this->bad( 'management_social_delta_invalid', 'Follower delta must be a whole number.' );
		}
		// Deterministic identity prevents a retried request from appending the same export twice.
		$snapshot_identity = hash( 'sha256', $campaign_code . '|' . $source_sha256 . '|' . $start . '|' . $end . '|' . $as_of_normalized );
		$snapshot_code = 'SOC-' . strtoupper( substr( $snapshot_identity, 0, 24 ) );
		$record = array_merge(
			array(
				'snapshot_code'      => $snapshot_code,
				'campaign_code'      => $campaign_code,
				'platform'           => $platform,
				'account_label'      => $account,
				'period_start'       => $start,
				'period_end'         => $end,
				'report_as_of'       => $as_of_normalized,
				'source_kind'        => 'MANUAL_EXPORT',
				'source_reference'   => $source_reference,
				'source_sha256'      => $source_sha256,
				'watch_time_minutes' => $watch_time_minutes,
				'follower_delta'     => null === $follower_delta ? null : (int) $follower_delta,
				'attribution_method' => sanitize_text_field( (string) ( $payload['attribution_method'] ?? '' ) ),
				'notes'              => sanitize_textarea_field( (string) ( $payload['notes'] ?? '' ) ),
				'captured_by'        => get_current_user_id(),
				'created_at'         => current_time( 'mysql' ),
			),
			$metrics,
			$amounts
		);
		$saved = $this->store_record(
			self::SNAPSHOT_PREFIX . $snapshot_code,
			$record,
			'MI_SOCIAL_SNAPSHOT_APPEND',
			$snapshot_code,
			'Owner/Management appended a timestamped manual platform-export snapshot; no provider API or auto-posting.'
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return rest_ensure_response( array( 'snapshot' => $record, 'reporting_mode' => 'MANUAL_OR_EXPORT_SNAPSHOT' ) );
	}
}
