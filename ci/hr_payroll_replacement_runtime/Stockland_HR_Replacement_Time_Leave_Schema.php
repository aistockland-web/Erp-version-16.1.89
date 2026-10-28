<?php
/**
 * Opt-in additive schema for Stage 5 time, attendance, and leave.
 *
 * Uses the canonical stockland_hrx_ namespace and the shared migration/event journals.
 * It never alters the four legacy HR/payroll tables and is not loaded by the live plugin.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'STOCKLAND_HR_REPLACEMENT_TESTING' ) ) { exit; }

final class Stockland_HR_Replacement_Time_Leave_Schema {
	const VERSION = '0.1.0';
	const MIGRATION_KEY = 'hrx_time_leave_0_1_0';
	private static $names = array(
		'shift_patterns' => 'shift_patterns',
		'shift_assignments' => 'shift_assignments',
		'attendance_records' => 'attendance_records',
		'time_rule_sets' => 'time_rule_sets',
		'leave_policies' => 'leave_policy_versions',
		'leave_requests' => 'leave_requests',
		'leave_ledger' => 'leave_ledger',
		'cutoff_snapshots' => 'time_cutoff_snapshots',
	);
	public static function table_names() { return self::$names; }

	public static function ddl( $prefix, $charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' ) {
		if ( ! is_string( $prefix ) || ! preg_match( '/^[A-Za-z0-9_]{1,120}$/D', $prefix ) ) {
			throw new InvalidArgumentException( 'HRXTL_SCHEMA_PREFIX_INVALID: Unsafe SQL table prefix.' );
		}
		if ( ! is_string( $charset_collate ) || false !== stripos( $charset_collate, ';' ) || false !== stripos( $charset_collate, '--' ) ) {
			throw new InvalidArgumentException( 'HRXTL_SCHEMA_CHARSET_INVALID: Unsafe charset/collation clause.' );
		}
		$p = $prefix;
		return array(
			'shift_patterns' => "CREATE TABLE {$p}shift_patterns (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				pattern_code VARCHAR(48) NOT NULL,
				title VARCHAR(190) NOT NULL,
				clock_in_time TIME NOT NULL,
				clock_out_time TIME NOT NULL,
				crosses_midnight BOOLEAN NOT NULL DEFAULT 0,
				break_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				effective_from DATE NOT NULL,
				effective_to DATE NULL,
				status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				created_by BIGINT UNSIGNED NOT NULL,
				submitted_by BIGINT UNSIGNED NULL,
				submitted_at DATETIME NULL,
				approved_by BIGINT UNSIGNED NULL,
				approved_at DATETIME NULL,
				approval_reference VARCHAR(190) NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY pattern_version (pattern_code, version_no),
				KEY status_effective (status, effective_from, effective_to)
			) ENGINE=InnoDB {$charset_collate}",
			'shift_assignments' => "CREATE TABLE {$p}shift_assignments (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				assignment_code VARCHAR(48) NOT NULL,
				employment_id BIGINT UNSIGNED NOT NULL,
				shift_pattern_id BIGINT UNSIGNED NOT NULL,
				location_id BIGINT UNSIGNED NOT NULL,
				effective_from DATE NOT NULL,
				effective_to DATE NULL,
				status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				created_by BIGINT UNSIGNED NOT NULL,
				submitted_by BIGINT UNSIGNED NULL,
				submitted_at DATETIME NULL,
				approved_by BIGINT UNSIGNED NULL,
				approved_at DATETIME NULL,
				approval_reference VARCHAR(190) NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY assignment_code (assignment_code),
				KEY employment_period (employment_id, effective_from, effective_to),
				KEY location_status (location_id, status)
			) ENGINE=InnoDB {$charset_collate}",
			'attendance_records' => "CREATE TABLE {$p}attendance_records (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				record_code VARCHAR(48) NOT NULL,
				employment_id BIGINT UNSIGNED NOT NULL,
				location_id BIGINT UNSIGNED NOT NULL,
				work_date DATE NOT NULL,
				record_kind VARCHAR(24) NOT NULL DEFAULT 'ORIGINAL',
				supersedes_record_id BIGINT UNSIGNED NULL,
				shift_assignment_id BIGINT UNSIGNED NULL,
				clock_in_at DATETIME NULL,
				clock_out_at DATETIME NULL,
				break_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				worked_minutes INT UNSIGNED NULL,
				source_type VARCHAR(24) NOT NULL,
				source_reference VARCHAR(190) NULL,
				source_sha256 CHAR(64) NULL,
				evidence_reference VARCHAR(190) NULL,
				status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				created_by BIGINT UNSIGNED NOT NULL,
				submitted_by BIGINT UNSIGNED NULL,
				submitted_at DATETIME NULL,
				approved_by BIGINT UNSIGNED NULL,
				approved_at DATETIME NULL,
				approval_reference VARCHAR(190) NULL,
				reason VARCHAR(500) NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY record_code (record_code),
				KEY employment_work_date (employment_id, work_date, status),
				KEY supersedes_record (supersedes_record_id),
				KEY location_date (location_id, work_date, status)
			) ENGINE=InnoDB {$charset_collate}",
			'time_rule_sets' => "CREATE TABLE {$p}time_rule_sets (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				rule_code VARCHAR(48) NOT NULL,
				rule_type VARCHAR(32) NOT NULL,
				classification VARCHAR(24) NOT NULL,
				jurisdiction VARCHAR(80) NOT NULL,
				title VARCHAR(190) NOT NULL,
				effective_from DATE NOT NULL,
				effective_to DATE NULL,
				parameters_json LONGTEXT NOT NULL,
				source_reference VARCHAR(190) NOT NULL,
				source_sha256 CHAR(64) NOT NULL,
				status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				legal_reviewer_reference VARCHAR(190) NULL,
				legal_reviewed_at DATETIME NULL,
				submitted_by BIGINT UNSIGNED NULL,
				submitted_at DATETIME NULL,
				approved_by BIGINT UNSIGNED NULL,
				approved_at DATETIME NULL,
				approval_reference VARCHAR(190) NULL,
				created_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY rule_version (rule_code, version_no),
				KEY rule_effective (rule_type, status, effective_from, effective_to)
			) ENGINE=InnoDB {$charset_collate}",
			'leave_policies' => "CREATE TABLE {$p}leave_policy_versions (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				policy_code VARCHAR(48) NOT NULL,
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				leave_type VARCHAR(32) NOT NULL,
				unit VARCHAR(12) NOT NULL,
				classification VARCHAR(24) NOT NULL,
				accrual_method VARCHAR(24) NOT NULL DEFAULT 'MANUAL_ONLY',
				accrual_units DECIMAL(12,4) NULL,
				carry_forward_allowed BOOLEAN NOT NULL DEFAULT 0,
				carry_forward_cap DECIMAL(12,4) NULL,
				allow_negative_balance BOOLEAN NOT NULL DEFAULT 0,
				effective_from DATE NOT NULL,
				effective_to DATE NULL,
				source_reference VARCHAR(190) NOT NULL,
				source_sha256 CHAR(64) NOT NULL,
				status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
				legal_reviewer_reference VARCHAR(190) NULL,
				legal_reviewed_at DATETIME NULL,
				submitted_by BIGINT UNSIGNED NULL,
				submitted_at DATETIME NULL,
				approved_by BIGINT UNSIGNED NULL,
				approved_at DATETIME NULL,
				approval_reference VARCHAR(190) NULL,
				created_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY policy_version (policy_code, version_no),
				KEY leave_effective (leave_type, status, effective_from, effective_to)
			) ENGINE=InnoDB {$charset_collate}",
			'leave_requests' => "CREATE TABLE {$p}leave_requests (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				request_code VARCHAR(48) NOT NULL,
				employment_id BIGINT UNSIGNED NOT NULL,
				location_id BIGINT UNSIGNED NOT NULL,
				request_type VARCHAR(24) NOT NULL,
				leave_type VARCHAR(32) NOT NULL,
				policy_code VARCHAR(48) NOT NULL,
				policy_version INT UNSIGNED NOT NULL,
				start_date DATE NULL,
				end_date DATE NULL,
				effective_date DATE NOT NULL,
				quantity DECIMAL(12,4) NOT NULL,
				direction VARCHAR(12) NOT NULL,
				reason VARCHAR(500) NOT NULL,
				evidence_reference VARCHAR(190) NULL,
				status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				created_by BIGINT UNSIGNED NOT NULL,
				submitted_by BIGINT UNSIGNED NULL,
				submitted_at DATETIME NULL,
				approved_by BIGINT UNSIGNED NULL,
				approved_at DATETIME NULL,
				approval_reference VARCHAR(190) NULL,
				approved_ledger_id BIGINT UNSIGNED NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY request_code (request_code),
				KEY employment_status (employment_id, status, effective_date),
				KEY location_dates (location_id, start_date, end_date)
			) ENGINE=InnoDB {$charset_collate}",
			'leave_ledger' => "CREATE TABLE {$p}leave_ledger (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				transaction_code VARCHAR(48) NOT NULL,
				employment_id BIGINT UNSIGNED NOT NULL,
				location_id BIGINT UNSIGNED NOT NULL,
				leave_type VARCHAR(32) NOT NULL,
				policy_code VARCHAR(48) NOT NULL,
				policy_version INT UNSIGNED NOT NULL,
				effective_date DATE NOT NULL,
				entry_type VARCHAR(24) NOT NULL,
				direction VARCHAR(12) NOT NULL,
				quantity DECIMAL(12,4) NOT NULL,
				request_id BIGINT UNSIGNED NULL,
				source_reference VARCHAR(190) NOT NULL,
				reason VARCHAR(500) NOT NULL,
				maker_id BIGINT UNSIGNED NOT NULL,
				approver_id BIGINT UNSIGNED NOT NULL,
				approval_reference VARCHAR(190) NOT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY transaction_code (transaction_code),
				UNIQUE KEY unique_request_post (request_id),
				KEY balance_as_of (employment_id, leave_type, effective_date, id),
				KEY policy_source (policy_code, policy_version)
			) ENGINE=InnoDB {$charset_collate}",
			'cutoff_snapshots' => "CREATE TABLE {$p}time_cutoff_snapshots (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				snapshot_code VARCHAR(48) NOT NULL,
				snapshot_version INT UNSIGNED NOT NULL DEFAULT 1,
				employment_id BIGINT UNSIGNED NOT NULL,
				location_id BIGINT UNSIGNED NOT NULL,
				period_start DATE NOT NULL,
				period_end DATE NOT NULL,
				cutoff_at DATETIME NOT NULL,
				snapshot_json LONGTEXT NOT NULL,
				source_sha256 CHAR(64) NOT NULL,
				status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				created_by BIGINT UNSIGNED NOT NULL,
				approved_by BIGINT UNSIGNED NULL,
				approved_at DATETIME NULL,
				approval_reference VARCHAR(190) NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY snapshot_code (snapshot_code),
				UNIQUE KEY employment_period_version (employment_id, period_start, period_end, snapshot_version),
				KEY location_period (location_id, period_start, period_end, status)
			) ENGINE=InnoDB {$charset_collate}",
		);
	}

	/** Opt-in, staging-only, additive migration. Existing incompatible tables stop the install. */
	public static function install_missing_tables( $wpdb, $explicit_authorization, $actor_id = 0 ) {
		if ( true !== $explicit_authorization ) {
			throw new RuntimeException( 'HRXTL_SCHEMA_INSTALL_NOT_AUTHORIZED: Explicit staging authorization is required.' );
		}
		if ( ! is_object( $wpdb ) || empty( $wpdb->prefix ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'query' ) ) {
			throw new RuntimeException( 'HRXTL_DATABASE_UNAVAILABLE: WordPress database gateway is unavailable.' );
		}
		$prefix = (string) $wpdb->prefix . 'stockland_hrx_';
		foreach ( array( 'migrations', 'events', 'people', 'employment_relationships', 'positions', 'workforce_plan_lines', 'requisitions' ) as $dependency_name ) {
			$table = $prefix . $dependency_name;
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( null === $exists || '' === (string) $exists || '' !== (string) $wpdb->last_error ) {
				throw new RuntimeException( 'HRXTL_PREREQUISITE_MISSING: The workforce/People schema is not ready; no time/leave tables were created.' );
			}
			$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ), ARRAY_A );
			if ( ! is_array( $status ) || 'InnoDB' !== (string) ( $status['Engine'] ?? '' ) ) {
				throw new RuntimeException( 'HRXTL_PREREQUISITE_ENGINE_MISMATCH: Required shared table is not transactional.' );
			}
		}
		$ddl = self::ddl( $prefix, method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' );
		$missing = array();
		$validated = array();
		foreach ( $ddl as $key => $statement ) {
			$table = $prefix . self::$names[ $key ];
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'HRXTL_SCHEMA_DISCOVERY_FAILED: Could not inspect target table status.' ); }
			if ( null === $exists || '' === (string) $exists ) { $missing[] = array( 'key' => $key, 'table' => $table, 'ddl' => $statement ); continue; }
			$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ), ARRAY_A );
			if ( ! is_array( $status ) || 'InnoDB' !== (string) ( $status['Engine'] ?? '' ) ) { throw new RuntimeException( 'HRXTL_SCHEMA_ENGINE_MISMATCH: Existing target table is not transactional: ' . $key ); }
			$description = $wpdb->get_results( $wpdb->prepare( 'DESCRIBE %i', $table ), ARRAY_A );
			if ( ! is_array( $description ) || ! $description ) { throw new RuntimeException( 'HRXTL_SCHEMA_DESCRIBE_FAILED: Existing target columns are unavailable: ' . $key ); }
			$actual = array();
			foreach ( $description as $column ) { if ( isset( $column['Field'] ) ) { $actual[] = strtolower( (string) $column['Field'] ); } }
			$expected = self::expected_columns( $statement );
			if ( array_diff( $expected, $actual ) ) { throw new RuntimeException( 'HRXTL_SCHEMA_COLUMN_MISMATCH: Existing target needs a reviewed migration; no ALTER was performed: ' . $key ); }
			$validated[] = $key;
		}
		$created = array();
		foreach ( $missing as $item ) {
			if ( false === $wpdb->query( $item['ddl'] ) ) { throw new RuntimeException( 'HRXTL_SCHEMA_CREATE_FAILED: Could not create additive table ' . $item['key'] . '.' ); }
			$created[] = $item['key'];
		}
		$migration_table = $prefix . 'migrations';
		$prior = $wpdb->get_row( $wpdb->prepare( 'SELECT schema_version, ddl_sha256, status FROM %i WHERE migration_key = %s LIMIT 1', $migration_table, self::MIGRATION_KEY ), ARRAY_A );
		$checksum = hash( 'sha256', implode( "\n", $ddl ) );
		if ( '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'HRXTL_MIGRATION_RECEIPT_LOOKUP_FAILED: Migration receipt could not be checked.' ); }
		if ( ! is_array( $prior ) ) {
			if ( false === $wpdb->insert( $migration_table, array( 'migration_key' => self::MIGRATION_KEY, 'schema_version' => self::VERSION, 'ddl_sha256' => $checksum, 'status' => 'APPLIED', 'applied_by' => max( 0, (int) $actor_id ) ?: null, 'applied_at' => function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' ) ) ) ) {
				throw new RuntimeException( 'HRXTL_MIGRATION_RECEIPT_WRITE_FAILED: Migration receipt was not recorded.' );
			}
		} elseif ( self::VERSION !== (string) ( $prior['schema_version'] ?? '' ) || $checksum !== (string) ( $prior['ddl_sha256'] ?? '' ) || 'APPLIED' !== (string) ( $prior['status'] ?? '' ) ) {
			throw new RuntimeException( 'HRXTL_MIGRATION_RECEIPT_MISMATCH: Existing receipt differs; stop for review.' );
		}
		return array( 'schema_version' => self::VERSION, 'created_tables' => $created, 'validated_existing_tables' => $validated, 'target_table_count' => count( $ddl ), 'ddl_sha256' => $checksum, 'legacy_tables_modified' => array(), 'business_data_modified' => false, 'production_ready' => false );
	}
	private static function expected_columns( $statement ) {
		preg_match_all( '/^\s{4}([a-z][a-z0-9_]*)\s+(?:BIGINT|INT|SMALLINT|TINYINT|VARCHAR|CHAR|DECIMAL|DATE|DATETIME|TIME|BOOLEAN|LONGTEXT)\b/im', $statement, $matches );
		return array_values( array_unique( array_map( 'strtolower', $matches[1] ) ) );
	}
}
