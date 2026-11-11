<?php
/**
 * One-pack installer orchestration.
 *
 * Runs the existing, bounded, add-only schema steps (Core -> Accounting/Procurement
 * -> HR/Payroll) to completion inside a time budget, verifying the physical
 * database after every step. It never drops, truncates, renames or reseeds a
 * table. The four legacy HR/payroll tables belong to the 49-table operational baseline.
 * Existing legacy-table alterations require explicit Owner approval. The separate
 * 37-table lifecycle extension is never created by this installer; it has its own
 * DDL fingerprint and protected approval workflow.
 *
 * @package Stockland_ERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Stockland_Installer {

	const LOCK = 'stockland_installer_lock';

	/**
	 * Whether the current HR schema version/DDL fingerprint already has a recorded approval.
	 *
	 * @return bool Approval state.
	 */
	public static function hr_approval_is_current() {
		$a   = get_option( 'stockland_hr_schema_migration_approval', array() );
		$sig = (string) Stockland_HR::schema_signature();
		return is_array( $a ) && '' !== $sig
			&& Stockland_HR::DB_VERSION === (string) ( $a['schema_version'] ?? '' )
			&& hash_equals( $sig, (string) ( $a['schema_signature'] ?? '' ) )
			&& (int) ( $a['approved_by'] ?? 0 ) > 0
			&& ! empty( $a['approved_at'] )
			&& strlen( trim( (string) ( $a['reason'] ?? '' ) ) ) >= 12;
	}

	/**
	 * Record the additive HR schema approval in the audit chain and options.
	 *
	 * @param int    $actor  WordPress user ID.
	 * @param string $reason Written reason.
	 * @param string $source Approval source label.
	 * @return bool Whether the approval is now current.
	 */
	public static function record_hr_approval( $actor, $reason, $source ) {
		$reason = sanitize_text_field( (string) $reason );
		if ( strlen( trim( $reason ) ) < 12 || (int) $actor < 1
			|| ! is_user_logged_in()
			|| (int) get_current_user_id() !== (int) $actor
			|| ! Stockland_Authority::is_owner( (int) $actor )
			|| ! current_user_can( 'stockland_install_schema' ) ) {
			return false;
		}
		$sig = (string) Stockland_HR::schema_signature();
		if ( ! Stockland_Audit::log( (int) $actor, null, 'HR_SCHEMA_MIGRATION_APPROVED', null, null, null, Stockland_HR::DB_VERSION . ':' . $sig, $reason, 'HR_SCHEMA_' . Stockland_HR::DB_VERSION, (int) $actor ) ) {
			return false;
		}
		update_option(
			'stockland_hr_schema_migration_approval',
			array(
				'schema_version'   => Stockland_HR::DB_VERSION,
				'schema_signature' => $sig,
				'approved_by'      => (int) $actor,
				'approved_at'      => current_time( 'mysql' ),
				'reason'           => $reason,
				'source'           => $source,
			),
			false
		);
		return self::hr_approval_is_current();
	}

	/**
	 * Remaining physical schema work as one comparable number.
	 *
	 * @return int Count of missing tables plus structural gaps.
	 */
	private static function remaining() {
		$n = count( Stockland_Activator::missing_core_tables() );
		$n += count( Stockland_Accounting::missing_tables() ) + count( (array) Stockland_Accounting::upgrade_gaps() );
		$n += count( Stockland_HR::missing_tables() ) + count( (array) Stockland_HR::upgrade_gaps() );
		return $n;
	}

	/**
	 * Install everything that is missing.
	 *
	 * @param int   $actor       WordPress user ID performing the install.
	 * @param int   $time_budget Seconds before returning a resumable partial result.
	 * @param array $approval    Optional array( 'reason' => string ) for HR DDL on an existing install.
	 * @return array Result with 'complete', 'needs_hr_approval', 'errors' and remaining lists.
	 */
	public static function run( $actor = 0, $time_budget = 20, array $approval = array() ) {
		$t0     = microtime( true );
		$errors = array();
		$result = array(
			'complete'          => false,
			'needs_hr_approval' => false,
			'errors'            => array(),
		);
		if ( get_transient( self::LOCK ) ) {
			$result['errors'][] = 'Another installer request is running. Retry in a few seconds.';
			return $result;
		}
		set_transient( self::LOCK, 1, 120 );
		if ( (int) $actor > 0 ) {
			// Rebuild the user object so capabilities granted a moment ago (role sync during activation) are visible to the HR gate.
			$current = wp_get_current_user();
			if ( $current instanceof WP_User && (int) $current->ID === (int) $actor ) {
				$current->get_role_caps(); // wp_set_current_user() is a no-op for the same ID, so refresh the capability map directly.
			} else {
				wp_set_current_user( (int) $actor );
			}
		}
		try {
			$fresh = count( Stockland_Activator::application_missing_tables() ) === count( Stockland_Activator::application_table_manifest() );
			$time  = static function () use ( $t0, $time_budget ) {
				return ( microtime( true ) - $t0 ) < $time_budget;
			};
			$step  = static function ( callable $fn ) use ( &$errors ) {
				$before = self::remaining();
				try {
					$fn();
				} catch ( Throwable $e ) {
					$errors[] = $e->getMessage();
					return false;
				}
				if ( self::remaining() >= $before ) {
					$errors[] = 'No verified database progress in the last step (' . $before . ' items remain). Check database privileges (CREATE/ALTER) and the PHP error log.';
					return false;
				}
				return true;
			};

			while ( $time() && Stockland_Activator::missing_core_tables() ) {
				if ( ! $step( static function () {
					Stockland_Activator::activate( 1 );
				} ) ) {
					break;
				}
			}
			if ( empty( $errors ) ) {
				while ( $time() && ( Stockland_Accounting::missing_tables() || Stockland_Accounting::upgrade_gaps() ) ) {
					if ( ! $step( static function () {
						Stockland_Accounting::install( 1 );
					} ) ) {
						break;
					}
				}
			}
			$core_acc_done = empty( Stockland_Activator::missing_core_tables() ) && empty( Stockland_Accounting::missing_tables() ) && empty( Stockland_Accounting::upgrade_gaps() );
			if ( empty( $errors ) && $core_acc_done && ( Stockland_HR::missing_tables() || Stockland_HR::upgrade_gaps() ) ) {
				if ( ! self::hr_approval_is_current() ) {
					$actor_id = (int) $actor;
					if ( $actor_id > 0 && ! empty( $approval['reason'] )
						&& Stockland_Authority::is_owner( $actor_id )
						&& current_user_can( 'stockland_install_schema' ) ) {
						self::record_hr_approval( $actor_id, (string) $approval['reason'], 'ONE_CLICK_INSTALLER_EXPLICIT_OWNER' );
					}
				}
				if ( ! self::hr_approval_is_current() ) {
					$result['needs_hr_approval'] = true;
				} else {
					while ( $time() && ( Stockland_HR::missing_tables() || Stockland_HR::upgrade_gaps() ) ) {
						if ( ! $step( static function () {
							Stockland_HR::install( 1 );
						} ) ) {
							break;
						}
					}
				}
			}

			$complete = empty( Stockland_Activator::application_missing_tables() )
				&& empty( Stockland_Accounting::upgrade_gaps() )
				&& empty( Stockland_HR::upgrade_gaps() );
			if ( $complete ) {
				self::finalize( (int) $actor );
				delete_option( 'stockland_setup_error' );
				delete_option( 'stockland_hr_install_error' );
				delete_option( 'stockland_activation_error' );
			} elseif ( $errors ) {
				update_option(
					'stockland_setup_error',
					array(
						'message' => implode( ' | ', $errors ),
						'time'    => current_time( 'mysql' ),
					),
					false
				);
			}
			$result = array(
				'complete'           => $complete,
				'needs_hr_approval'  => $result['needs_hr_approval'],
				'errors'             => $errors,
				'core_missing'       => Stockland_Activator::missing_core_tables(),
				'accounting_missing' => Stockland_Accounting::missing_tables(),
				'hr_missing'         => Stockland_HR::missing_tables(),
				'hr_lifecycle_missing' => Stockland_HR_Lifecycle::missing_tables(),
				'structural_gaps'    => array_merge( (array) Stockland_Accounting::upgrade_gaps(), (array) Stockland_HR::upgrade_gaps() ),
				'fresh_install'      => $fresh,
				'seconds'            => round( microtime( true ) - $t0, 2 ),
			);
		} catch ( Throwable $e ) {
			$result['errors'][] = $e->getMessage();
			Stockland_Runtime_Diagnostics::log_error( 'Stockland installer error: ' . $e->getMessage() );
		} finally {
			delete_transient( self::LOCK );
		}
		return $result;
	}

	/**
	 * Mark the schema ready and make sure the installing administrator has an Owner profile.
	 *
	 * @param int $actor WordPress user ID.
	 * @return void
	 */
	private static function finalize( $actor ) {
		update_option( 'stockland_setup_state', 'INVARIANTS_PENDING', false );
		update_option( 'stockland_application_schema_ready', 1, false );
		update_option( 'stockland_db_version', STOCKLAND_DB_VERSION, false );
		update_option( 'stockland_core_schema_version', STOCKLAND_CORE_SCHEMA_VERSION, false );
		update_option( 'stockland_accounting_schema_version', STOCKLAND_ACCOUNTING_SCHEMA_VERSION, false );
		update_option( 'stockland_hr_schema_version', STOCKLAND_HR_SCHEMA_VERSION, false );
		delete_option( 'stockland_activation_pending' );
		delete_option( 'stockland_accounting_pending' );
		self::ensure_owner_profile( $actor );
		Stockland_Roles::register_roles();
		Stockland_Roles::sync_roles();
		update_option( 'stockland_setup_state', 'LIVE_SCHEMA_READY', false );
	}

	/**
	 * Create an Owner profile for the installing administrator when no active Owner exists yet.
	 *
	 * @param int $actor WordPress user ID.
	 * @return void
	 */
	private static function ensure_owner_profile( $actor ) {
		global $wpdb;
		if ( $actor < 1 || ! user_can( $actor, 'manage_options' ) ) {
			return;
		}
		$table = Stockland_DB::table( 'users' );
		$owner = (int) Stockland_DB::read_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE role=%s AND is_active=1', $table, 'owner' ) );
		if ( $owner > 0 ) {
			return;
		}
		$exists = (int) Stockland_DB::read_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE wp_user_id=%d', $table, $actor ) );
		if ( $exists ) {
			Stockland_DB::execute_query( $wpdb->prepare( 'UPDATE %i SET role=%s, is_active=1 WHERE wp_user_id=%d', $table, 'owner', $actor ) );
		} else {
			Stockland_DB::execute_query( $wpdb->prepare( 'INSERT INTO %i (wp_user_id, role, branch_location_id, is_active) VALUES (%d, %s, NULL, 1)', $table, $actor, 'owner' ) );
		}
	}
}
