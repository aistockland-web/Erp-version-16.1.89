<?php
/**
 * Opt-in schema contract for the isolated People/Recruitment/Contract/Onboarding slice.
 *
 * Not loaded by plugin activation and not installed by normal updates. It is additive
 * only and reuses the Stage 3 migration/events journals. No legacy HR table is touched.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'STOCKLAND_HR_REPLACEMENT_TESTING' ) ) { exit; }

final class Stockland_HR_Replacement_People_Schema {
	const VERSION = '0.1.0';
	const MIGRATION_KEY = 'hrx_people_lifecycle_0_1_0';
	private static $names = array(
		'people' => 'people',
		'candidates' => 'candidate_profiles',
		'applications' => 'applications',
		'interviews' => 'interviews',
		'employments' => 'employment_relationships',
		'contracts' => 'contract_versions',
		'documents' => 'private_documents',
		'onboarding' => 'onboarding_tasks',
	);
	public static function table_names() { return self::$names; }
	public static function ddl( $prefix, $charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' ) {
		if ( ! is_string( $prefix ) || ! preg_match( '/^[A-Za-z0-9_]{1,120}$/D', $prefix ) ) {
			throw new InvalidArgumentException( 'HRXP_SCHEMA_PREFIX_INVALID: Unsafe target table prefix.' );
		}
		if ( ! is_string( $charset_collate ) || false !== stripos( $charset_collate, ';' ) || false !== stripos( $charset_collate, '--' ) ) {
			throw new InvalidArgumentException( 'HRXP_SCHEMA_CHARSET_INVALID: Unsafe charset/collation clause.' );
		}
		$p = $prefix;
		return array(
			'people' => "CREATE TABLE {$p}people (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				person_code VARCHAR(48) NOT NULL,
				first_name VARCHAR(100) NOT NULL,
				last_name VARCHAR(100) NOT NULL,
				email VARCHAR(190) NULL,
				phone VARCHAR(40) NULL,
				personal_data_class VARCHAR(24) NOT NULL DEFAULT 'RESTRICTED',
				status VARCHAR(24) NOT NULL DEFAULT 'ACTIVE',
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				created_by BIGINT UNSIGNED NOT NULL,
				updated_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY person_code (person_code),
				KEY name_lookup (last_name, first_name),
				KEY status_created (status, created_at)
			) ENGINE=InnoDB {$charset_collate}",
			'candidates' => "CREATE TABLE {$p}candidate_profiles (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				person_id BIGINT UNSIGNED NOT NULL,
				candidate_code VARCHAR(48) NOT NULL,
				privacy_notice_version VARCHAR(48) NOT NULL,
				privacy_ack_at DATETIME NOT NULL,
				collection_purpose VARCHAR(190) NOT NULL,
				source_channel VARCHAR(40) NOT NULL,
				source_detail VARCHAR(190) NULL,
				retention_review_on DATE NULL,
				status VARCHAR(24) NOT NULL DEFAULT 'ACTIVE',
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				created_by BIGINT UNSIGNED NOT NULL,
				updated_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY candidate_code (candidate_code),
				UNIQUE KEY candidate_person (person_id),
				KEY retention_review (status, retention_review_on)
			) ENGINE=InnoDB {$charset_collate}",
			'applications' => "CREATE TABLE {$p}applications (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				application_code VARCHAR(48) NOT NULL,
				candidate_id BIGINT UNSIGNED NOT NULL,
				requisition_id BIGINT UNSIGNED NOT NULL,
				location_id BIGINT UNSIGNED NULL,
				stage VARCHAR(30) NOT NULL DEFAULT 'APPLIED',
				decision_rationale VARCHAR(1000) NULL,
				decision_by BIGINT UNSIGNED NULL,
				decision_at DATETIME NULL,
				human_decision BOOLEAN NOT NULL DEFAULT 0,
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				created_by BIGINT UNSIGNED NOT NULL,
				updated_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY application_code (application_code),
				UNIQUE KEY candidate_requisition (candidate_id, requisition_id),
				KEY requisition_stage (requisition_id, stage),
				KEY location_stage (location_id, stage),
				KEY candidate_stage (candidate_id, stage)
			) ENGINE=InnoDB {$charset_collate}",
			'interviews' => "CREATE TABLE {$p}interviews (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				interview_code VARCHAR(48) NOT NULL,
				application_id BIGINT UNSIGNED NOT NULL,
				round_no SMALLINT UNSIGNED NOT NULL,
				scheduled_at DATETIME NULL,
				interviewed_at DATETIME NULL,
				interviewer_id BIGINT UNSIGNED NOT NULL,
				rubric_version VARCHAR(48) NOT NULL,
				score DECIMAL(6,2) NULL,
				recommendation VARCHAR(24) NOT NULL DEFAULT 'PENDING',
				notes_reference VARCHAR(190) NULL,
				evidence_sha256 CHAR(64) NULL,
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				created_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY interview_code (interview_code),
				UNIQUE KEY application_round (application_id, round_no),
				KEY interviewer_date (interviewer_id, scheduled_at),
				KEY application_recommendation (application_id, recommendation)
			) ENGINE=InnoDB {$charset_collate}",
			'employments' => "CREATE TABLE {$p}employment_relationships (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				employment_code VARCHAR(48) NOT NULL,
				person_id BIGINT UNSIGNED NOT NULL,
				application_id BIGINT UNSIGNED NOT NULL,
				position_id BIGINT UNSIGNED NOT NULL,
				legal_employer_code VARCHAR(48) NOT NULL,
				location_id BIGINT UNSIGNED NOT NULL,
				reporting_manager_id BIGINT UNSIGNED NULL,
				start_date DATE NOT NULL,
				end_date DATE NULL,
				employment_type VARCHAR(24) NOT NULL,
				status VARCHAR(32) NOT NULL DEFAULT 'PENDING_CONTRACT',
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				created_by BIGINT UNSIGNED NOT NULL,
				updated_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY employment_code (employment_code),
				UNIQUE KEY employment_application (application_id),
				KEY person_status (person_id, status),
				KEY location_status (location_id, status),
				KEY effective_period (start_date, end_date)
			) ENGINE=InnoDB {$charset_collate}",
			'contracts' => "CREATE TABLE {$p}contract_versions (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				contract_code VARCHAR(48) NOT NULL,
				employment_id BIGINT UNSIGNED NOT NULL,
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				contract_type VARCHAR(32) NOT NULL,
				effective_from DATE NOT NULL,
				effective_to DATE NULL,
				probation_end DATE NULL,
				private_document_id BIGINT UNSIGNED NULL,
				signed_at DATETIME NULL,
				status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
				submitted_by BIGINT UNSIGNED NULL,
				submitted_at DATETIME NULL,
				approved_by BIGINT UNSIGNED NULL,
				approved_at DATETIME NULL,
				approval_note VARCHAR(500) NULL,
				created_by BIGINT UNSIGNED NOT NULL,
				updated_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY contract_code (contract_code),
				UNIQUE KEY employment_version (employment_id, version_no),
				KEY status_effective (status, effective_from)
			) ENGINE=InnoDB {$charset_collate}",
			'documents' => "CREATE TABLE {$p}private_documents (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				document_code VARCHAR(48) NOT NULL,
				subject_type VARCHAR(32) NOT NULL,
				subject_id BIGINT UNSIGNED NOT NULL,
				document_type VARCHAR(40) NOT NULL,
				private_reference VARCHAR(190) NOT NULL,
				content_sha256 CHAR(64) NOT NULL,
				issued_on DATE NULL,
				expires_on DATE NULL,
				status VARCHAR(24) NOT NULL DEFAULT 'PENDING_VERIFICATION',
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				verified_by BIGINT UNSIGNED NULL,
				verified_at DATETIME NULL,
				review_note VARCHAR(500) NULL,
				created_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY document_code (document_code),
				KEY subject_documents (subject_type, subject_id, status),
				KEY expiry_review (status, expires_on)
			) ENGINE=InnoDB {$charset_collate}",
			'onboarding' => "CREATE TABLE {$p}onboarding_tasks (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				task_code VARCHAR(48) NOT NULL,
				employment_id BIGINT UNSIGNED NOT NULL,
				task_type VARCHAR(40) NOT NULL,
				title VARCHAR(190) NOT NULL,
				assigned_role VARCHAR(40) NOT NULL,
				owner_user_id BIGINT UNSIGNED NULL,
				due_on DATE NULL,
				mandatory BOOLEAN NOT NULL DEFAULT 1,
				status VARCHAR(24) NOT NULL DEFAULT 'PENDING',
				evidence_reference VARCHAR(190) NULL,
				completed_by BIGINT UNSIGNED NULL,
				completed_at DATETIME NULL,
				version_no INT UNSIGNED NOT NULL DEFAULT 1,
				created_by BIGINT UNSIGNED NOT NULL,
				updated_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY task_code (task_code),
				UNIQUE KEY employment_task (employment_id, task_type),
				KEY onboarding_status (employment_id, status, mandatory)
			) ENGINE=InnoDB {$charset_collate}",
		);
	}

	/**
	 * Explicit staging-only additive installer. It never alters an existing target.
	 */
	public static function install_missing_tables( $wpdb, $explicit_authorization, $actor_id = 0 ) {
		if ( true !== $explicit_authorization ) {
			throw new RuntimeException( 'HRXP_SCHEMA_INSTALL_NOT_AUTHORIZED: Explicit staging authorization is required.' );
		}
		if ( ! is_object( $wpdb ) || empty( $wpdb->prefix ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'query' ) ) {
			throw new RuntimeException( 'HRXP_DATABASE_UNAVAILABLE: WordPress database gateway is unavailable.' );
		}
		$prefix = (string) $wpdb->prefix . 'stockland_hrx_';
		$ddl = self::ddl( $prefix, method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' );
		$missing = array();
		$validated = array();
		foreach ( $ddl as $key => $statement ) {
			$table = $prefix . self::$names[ $key ];
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'HRXP_SCHEMA_DISCOVERY_FAILED: Could not inspect the replacement schema.' ); }
			if ( null === $exists || '' === (string) $exists ) { $missing[] = array( 'key' => $key, 'table' => $table, 'ddl' => $statement ); continue; }
			$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ), ARRAY_A );
			if ( ! is_array( $status ) || 'InnoDB' !== (string) ( $status['Engine'] ?? '' ) ) { throw new RuntimeException( 'HRXP_SCHEMA_ENGINE_MISMATCH: Existing target table is not InnoDB: ' . $key ); }
			$columns = $wpdb->get_results( $wpdb->prepare( 'DESCRIBE %i', $table ), ARRAY_A );
			if ( ! is_array( $columns ) || ! $columns ) { throw new RuntimeException( 'HRXP_SCHEMA_DESCRIBE_FAILED: Target columns unavailable: ' . $key ); }
			$actual = array(); foreach ( $columns as $column ) { if ( isset( $column['Field'] ) ) { $actual[] = strtolower( (string) $column['Field'] ); } }
			preg_match_all( '/^\s{4}([a-z][a-z0-9_]*)\s+(?:BIGINT|INT|SMALLINT|VARCHAR|CHAR|DECIMAL|DATE|DATETIME|BOOLEAN)\b/im', $statement, $matches );
			if ( array_diff( array_values( array_unique( $matches[1] ) ), $actual ) ) { throw new RuntimeException( 'HRXP_SCHEMA_COLUMN_MISMATCH: Reviewed migration required; no ALTER performed: ' . $key ); }
			$validated[] = $key;
		}
		foreach ( $missing as $item ) {
			if ( false === $wpdb->query( $item['ddl'] ) ) { throw new RuntimeException( 'HRXP_SCHEMA_CREATE_FAILED: Could not create target table ' . $item['key'] . '.' ); }
		}
		$meta = $prefix . 'migrations';
		$prior = $wpdb->get_row( $wpdb->prepare( 'SELECT schema_version, ddl_sha256, status FROM %i WHERE migration_key = %s LIMIT 1', $meta, self::MIGRATION_KEY ), ARRAY_A );
		$checksum = hash( 'sha256', implode( "\n", $ddl ) );
		if ( ! is_array( $prior ) ) {
			if ( false === $wpdb->insert( $meta, array( 'migration_key' => self::MIGRATION_KEY, 'schema_version' => self::VERSION, 'ddl_sha256' => $checksum, 'status' => 'APPLIED', 'applied_by' => max( 0, (int) $actor_id ) ?: null, 'applied_at' => function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' ) ) ) ) {
				throw new RuntimeException( 'HRXP_MIGRATION_RECEIPT_WRITE_FAILED: Migration receipt was not recorded.' );
			}
		} elseif ( self::VERSION !== (string) ( $prior['schema_version'] ?? '' ) || $checksum !== (string) ( $prior['ddl_sha256'] ?? '' ) || 'APPLIED' !== (string) ( $prior['status'] ?? '' ) ) {
			throw new RuntimeException( 'HRXP_MIGRATION_RECEIPT_MISMATCH: Existing migration receipt differs; stop for review.' );
		}
		return array( 'schema_version' => self::VERSION, 'created_tables' => array_column( $missing, 'key' ), 'validated_existing_tables' => $validated, 'ddl_sha256' => $checksum, 'legacy_tables_modified' => array(), 'business_data_modified' => false, 'production_ready' => false );
	}
}
