<?php
/**
 * Explicitly gated schema for the isolated HR replacement workforce slice.
 *
 * This class is not loaded by the production plugin bootstrap. Its installer is
 * intentionally opt-in and additive-only: existing tables are never altered,
 * dropped, renamed, truncated or repurposed. An existing partial/incompatible
 * target table is a hard error that requires a reviewed migration.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'STOCKLAND_HR_REPLACEMENT_TESTING' ) ) {
	exit;
}

final class Stockland_HR_Replacement_Workforce_Schema {
	const VERSION = '0.1.0';
	const MIGRATION_KEY = 'hrx_workforce_slice_0_1_0';

	private static $names = array(
		'migrations'  => 'migrations',
		'org_units'   => 'org_units',
		'positions'   => 'positions',
		'workforce_plans' => 'workforce_plans',
		'plan_lines'  => 'workforce_plan_lines',
		'requisitions'=> 'requisitions',
		'events'      => 'events',
	);

	/**
	 * Return the additive DDL contract for a caller-supplied trusted table prefix.
	 *
	 * @param string $prefix Trusted WordPress table prefix plus stockland_hrx_.
	 * @param string $charset_collate Trusted result of wpdb::get_charset_collate().
	 * @return array<string,string>
	 */
	public static function ddl( $prefix, $charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' ) {
		if ( ! is_string( $prefix ) || ! preg_match( '/^[A-Za-z0-9_]{1,120}$/D', $prefix ) ) {
			throw new InvalidArgumentException( 'HRX_SCHEMA_PREFIX_INVALID: Unsafe SQL table prefix.' );
		}
		if ( ! is_string( $charset_collate ) || false !== stripos( $charset_collate, ';' ) || false !== stripos( $charset_collate, '--' ) ) {
			throw new InvalidArgumentException( 'HRX_SCHEMA_CHARSET_INVALID: Unsafe charset/collation clause.' );
		}
		$p = $prefix;
		return array(
			'migrations' => "CREATE TABLE {$p}migrations (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				migration_key VARCHAR(100) NOT NULL,
				schema_version VARCHAR(30) NOT NULL,
				ddl_sha256 CHAR(64) NOT NULL,
				status VARCHAR(20) NOT NULL,
				applied_by BIGINT UNSIGNED NULL,
				applied_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY migration_key (migration_key),
				KEY status_version (status, schema_version)
			) ENGINE=InnoDB {$charset_collate}",
			'org_units' => "CREATE TABLE {$p}org_units (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				org_code VARCHAR(48) NOT NULL,
				name VARCHAR(120) NOT NULL,
				parent_id BIGINT UNSIGNED NULL,
				status VARCHAR(24) NOT NULL,
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				created_by BIGINT UNSIGNED NOT NULL,
				updated_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY org_code (org_code),
				KEY parent_status (parent_id, status),
				KEY status_id (status, id)
			) ENGINE=InnoDB {$charset_collate}",
			'positions' => "CREATE TABLE {$p}positions (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				position_code VARCHAR(48) NOT NULL,
				org_unit_id BIGINT UNSIGNED NOT NULL,
				title VARCHAR(120) NOT NULL,
				grade_code VARCHAR(32) NULL,
				parent_position_id BIGINT UNSIGNED NULL,
				location_id BIGINT UNSIGNED NULL,
				position_justification VARCHAR(500) NOT NULL,
				status VARCHAR(24) NOT NULL,
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				submitted_by BIGINT UNSIGNED NULL,
				submitted_at DATETIME NULL,
				approved_by BIGINT UNSIGNED NULL,
				approved_at DATETIME NULL,
				decision_note VARCHAR(500) NULL,
				created_by BIGINT UNSIGNED NOT NULL,
				updated_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY position_code (position_code),
				KEY org_status (org_unit_id, status),
				KEY location_status (location_id, status),
				KEY parent_position (parent_position_id),
				KEY approval_queue (status, submitted_at)
			) ENGINE=InnoDB {$charset_collate}",
			'workforce_plans' => "CREATE TABLE {$p}workforce_plans (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				plan_code VARCHAR(48) NOT NULL,
				fiscal_cycle VARCHAR(24) NOT NULL,
				org_unit_id BIGINT UNSIGNED NOT NULL,
				location_id BIGINT UNSIGNED NULL,
				title VARCHAR(160) NOT NULL,
				justification VARCHAR(500) NOT NULL,
				status VARCHAR(24) NOT NULL,
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				requested_headcount_total INT UNSIGNED NOT NULL DEFAULT 0,
				approved_headcount_total INT UNSIGNED NOT NULL DEFAULT 0,
				requested_budget_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
				approved_budget_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
				budget_currency CHAR(3) NULL,
				budget_reference VARCHAR(190) NULL,
				submitted_by BIGINT UNSIGNED NULL,
				submitted_at DATETIME NULL,
				approved_by BIGINT UNSIGNED NULL,
				approved_at DATETIME NULL,
				decision_note VARCHAR(500) NULL,
				created_by BIGINT UNSIGNED NOT NULL,
				updated_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY plan_code (plan_code),
				KEY fiscal_status (fiscal_cycle, status),
				KEY org_location (org_unit_id, location_id),
				KEY approval_queue (status, submitted_at)
			) ENGINE=InnoDB {$charset_collate}",
			'plan_lines' => "CREATE TABLE {$p}workforce_plan_lines (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				plan_id BIGINT UNSIGNED NOT NULL,
				position_id BIGINT UNSIGNED NOT NULL,
				budgeted_headcount INT UNSIGNED NOT NULL,
				budget_amount DECIMAL(14,2) NOT NULL,
				currency CHAR(3) NOT NULL,
				justification VARCHAR(500) NOT NULL,
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				created_by BIGINT UNSIGNED NOT NULL,
				updated_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY plan_position (plan_id, position_id),
				KEY position_plan (position_id, plan_id)
			) ENGINE=InnoDB {$charset_collate}",
			'requisitions' => "CREATE TABLE {$p}requisitions (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				requisition_code VARCHAR(48) NOT NULL,
				plan_line_id BIGINT UNSIGNED NOT NULL,
				openings INT UNSIGNED NOT NULL,
				filled_openings INT UNSIGNED NOT NULL DEFAULT 0,
				priority VARCHAR(16) NOT NULL DEFAULT 'NORMAL',
				requested_start_date DATE NULL,
				justification VARCHAR(500) NOT NULL,
				location_id BIGINT UNSIGNED NULL,
				status VARCHAR(24) NOT NULL,
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				budget_reference VARCHAR(190) NULL,
				submitted_by BIGINT UNSIGNED NULL,
				submitted_at DATETIME NULL,
				approved_by BIGINT UNSIGNED NULL,
				approved_at DATETIME NULL,
				decision_note VARCHAR(500) NULL,
				created_by BIGINT UNSIGNED NOT NULL,
				updated_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY requisition_code (requisition_code),
				KEY plan_line_status (plan_line_id, status),
				KEY location_status (location_id, status),
				KEY approval_queue (status, submitted_at)
			) ENGINE=InnoDB {$charset_collate}",
			'events' => "CREATE TABLE {$p}events (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				idempotency_key VARCHAR(120) NOT NULL,
				request_hash CHAR(64) NOT NULL,
				entity_type VARCHAR(48) NOT NULL,
				entity_id BIGINT UNSIGNED NOT NULL,
				event_type VARCHAR(64) NOT NULL,
				from_status VARCHAR(24) NULL,
				to_status VARCHAR(24) NULL,
				actor_id BIGINT UNSIGNED NOT NULL,
				maker_id BIGINT UNSIGNED NULL,
				reason VARCHAR(190) NULL,
				evidence_sha256 CHAR(64) NULL,
				record_version INT UNSIGNED NOT NULL,
				metadata_json LONGTEXT NOT NULL,
				occurred_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY idempotency_key (idempotency_key),
				KEY entity_timeline (entity_type, entity_id, id),
				KEY actor_time (actor_id, occurred_at),
				KEY event_type_time (event_type, occurred_at)
			) ENGINE=InnoDB {$charset_collate}",
		);
	}

	/**
	 * Install only missing target tables when an explicit caller opts in.
	 *
	 * This method is intentionally not wired to activation, REST or ordinary plugin
	 * update. Existing target tables are validated, not altered. Any incompatible
	 * table fails closed so a separately reviewed migration must handle drift.
	 *
	 * @param object $wpdb WordPress database gateway.
	 * @param bool   $explicit_authorization Explicit staging/admin migration decision.
	 * @param int    $actor_id Authorized actor ID for the new migration receipt.
	 * @return array Safe receipt without any HR or financial records.
	 */
	public static function install_missing_tables( $wpdb, $explicit_authorization, $actor_id = 0 ) {
		if ( true !== $explicit_authorization ) {
			throw new RuntimeException( 'HRX_SCHEMA_INSTALL_NOT_AUTHORIZED: This additive schema installer requires an explicit staging migration authorization.' );
		}
		if ( ! is_object( $wpdb ) || empty( $wpdb->prefix ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'query' ) ) {
			throw new RuntimeException( 'HRX_DATABASE_UNAVAILABLE: WordPress database gateway is unavailable.' );
		}
		$prefix = (string) $wpdb->prefix . 'stockland_hrx_';
		$ddl = self::ddl( $prefix, method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' );
		$missing = array();
		$validated = array();
		foreach ( $ddl as $key => $statement ) {
			$table = $prefix . self::target_name( $key );
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( '' !== (string) $wpdb->last_error ) {
				throw new RuntimeException( 'HRX_SCHEMA_DISCOVERY_FAILED: Database could not inspect the isolated HRX schema.' );
			}
			if ( null === $exists || '' === (string) $exists ) {
				$missing[] = array( 'key' => $key, 'table' => $table, 'ddl' => $statement );
				continue;
			}
			$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ), ARRAY_A );
			if ( ! is_array( $status ) || 'InnoDB' !== (string) ( $status['Engine'] ?? '' ) ) {
				throw new RuntimeException( 'HRX_SCHEMA_ENGINE_MISMATCH: Existing target table is not InnoDB: ' . $key );
			}
			$description = $wpdb->get_results( $wpdb->prepare( 'DESCRIBE %i', $table ), ARRAY_A );
			if ( ! is_array( $description ) || ! $description ) {
				throw new RuntimeException( 'HRX_SCHEMA_DESCRIBE_FAILED: Existing target table columns are unavailable: ' . $key );
			}
			$actual = array();
			foreach ( $description as $column ) {
				if ( isset( $column['Field'] ) ) {
					$actual[] = strtolower( (string) $column['Field'] );
				}
			}
			$expected = self::expected_columns( $statement );
			if ( array_diff( $expected, $actual ) ) {
				throw new RuntimeException( 'HRX_SCHEMA_COLUMN_MISMATCH: Existing target table needs a reviewed migration; no ALTER was executed: ' . $key );
			}
			$validated[] = $key;
		}
		$created = array();
		foreach ( $missing as $item ) {
			if ( false === $wpdb->query( $item['ddl'] ) ) {
				throw new RuntimeException( 'HRX_SCHEMA_CREATE_FAILED: Could not create target table ' . $item['key'] . '. Existing legacy tables were not targeted.' );
			}
			$created[] = $item['key'];
		}
		$table = $prefix . 'migrations';
		$row = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE migration_key = %s LIMIT 1', $table, self::MIGRATION_KEY ) );
		if ( '' !== (string) $wpdb->last_error ) {
			throw new RuntimeException( 'HRX_MIGRATION_RECEIPT_LOOKUP_FAILED: Target migration receipt could not be checked.' );
		}
		$checksum = hash( 'sha256', implode( "\n", $ddl ) );
		if ( null === $row ) {
			if ( false === $wpdb->insert(
				$table,
				array(
					'migration_key' => self::MIGRATION_KEY,
					'schema_version'=> self::VERSION,
					'ddl_sha256'    => $checksum,
					'status'        => 'APPLIED',
					'applied_by'    => max( 0, (int) $actor_id ) ?: null,
					'applied_at'    => function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' ),
				)
			) ) {
				throw new RuntimeException( 'HRX_MIGRATION_RECEIPT_WRITE_FAILED: Target migration receipt was not recorded.' );
			}
		} elseif ( '' !== (string) $checksum ) {
			$receipt = $wpdb->get_row( $wpdb->prepare( 'SELECT schema_version, ddl_sha256, status FROM %i WHERE migration_key = %s LIMIT 1', $table, self::MIGRATION_KEY ), ARRAY_A );
			if ( ! is_array( $receipt ) || self::VERSION !== (string) ( $receipt['schema_version'] ?? '' ) || $checksum !== (string) ( $receipt['ddl_sha256'] ?? '' ) || 'APPLIED' !== (string) ( $receipt['status'] ?? '' ) ) {
				throw new RuntimeException( 'HRX_MIGRATION_RECEIPT_MISMATCH: Existing migration receipt differs; no legacy table was changed.' );
			}
		}
		return array(
			'schema_version' => self::VERSION,
			'target_table_count' => count( $ddl ),
			'created_tables' => $created,
			'validated_existing_tables' => $validated,
			'ddl_sha256' => $checksum,
			'legacy_tables_modified' => array(),
			'business_data_modified' => false,
			'production_ready' => false,
		);
	}

	private static function expected_columns( $statement ) {
		preg_match_all( '/^\s{4}([a-z][a-z0-9_]*)\s+(?:BIGINT|INT|VARCHAR|CHAR|DECIMAL|DATE|DATETIME|LONGTEXT)\b/im', $statement, $matches );
		return array_values( array_unique( array_map( 'strtolower', $matches[1] ) ) );
	}

	private static function target_name( $key ) {
		if ( ! isset( self::$names[ $key ] ) ) {
			throw new InvalidArgumentException( 'HRX_SCHEMA_ENTITY_UNKNOWN: Unknown schema key.' );
		}
		return self::$names[ $key ];
	}
}
