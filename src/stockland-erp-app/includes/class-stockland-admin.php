<?php
/**
 * Adds Stockland ERP entry points to the native WordPress administration UI.
 *
 * @package Stockland_ERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress admin integration.
 *
 * Stockland ERP is a normal WordPress plugin. It MUST NOT redirect or block
 * wp-admin, remove the Dashboard, or take ownership of WordPress administration.
 * Operational users may use the dedicated ERP frontend, while administrators
 * retain full WordPress backend control.
 *
 * @package Stockland_ERP
 */
class Stockland_Admin {
	/**
	 * Registers the ERP admin menu and toolbar entry.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'redirect_erp_launcher' ), 1 );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 90 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		// ERP adds only its own menu entries. It deliberately does not redirect, replace,
		// remove, hide or filter native wp-admin Dashboard/admin pages. Plugin/theme/user
		// administration therefore remains available through normal WordPress controls.
	}



	/**
	 * Enqueues the protected browser-driven setup runner only on System Control.
	 *
	 * @param string $hook_suffix Current WordPress admin page hook.
	 * @return void
	 */
	public function admin_assets( $hook_suffix ) {
		if ( 'stockland-erp_page_stockland-system-control' !== $hook_suffix || ! current_user_can( 'stockland_install_schema' ) ) {
			return;
		}
		$file = STOCKLAND_PLUGIN_DIR . 'assets/admin-setup.js';
		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			return;
		}
		$version = hash_file( 'sha256', $file );
		wp_enqueue_script( 'stockland-admin-setup', STOCKLAND_PLUGIN_URL . 'assets/admin-setup.js', array(), STOCKLAND_VERSION . '-' . substr( (string) $version, 0, 16 ), true );
		wp_localize_script(
			'stockland-admin-setup',
			'StocklandAdminSetup',
			array(
				'restUrl'     => esc_url_raw( rest_url( STOCKLAND_REST_NAMESPACE . '/setup' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'maxBatches'  => 160,
				'statusTarget' => 'stockland-admin-setup-status',
			)
		);
	}

	/**
	 * Redirect only the explicit ERP launcher before wp-admin emits output.
	 * Native WordPress Dashboard and administration remain available.
	 *
	 * @return void
	 */
	public function redirect_erp_launcher() {
		if ( ! is_admin() || ! isset( $_GET['page'] ) || 'stockland-erp' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}
		if ( ! current_user_can( 'read' ) ) {
			wp_die( esc_html__( 'You do not have permission to access Stockland ERP.', 'stockland-inventory' ) );
		}
		wp_safe_redirect( self::erp_url() );
		exit;
	}

	/**
	 * Adds the ERP launcher and owner-only System Control submenu.
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_menu_page(
			'Stockland ERP',
			'Stockland ERP',
			'read',
			'stockland-erp',
			array( $this, 'erp_page' ),
			'dashicons-store',
			25
		);
		add_submenu_page( 'stockland-erp', 'ERP Dashboard', 'ERP Dashboard', 'read', 'stockland-erp', array( $this, 'erp_page' ) );
		add_submenu_page( 'stockland-erp', 'System Control', 'System Control', 'stockland_install_schema', 'stockland-system-control', array( $this, 'system_control' ) );
		add_submenu_page( 'stockland-erp', 'WordPress Dashboard', 'WordPress Dashboard', 'manage_options', 'index.php' );
	}

	/**
	 * Adds a Stockland ERP shortcut to the WordPress admin toolbar.
	 *
	 * @param WP_Admin_Bar $bar WordPress admin toolbar API.
	 * @return void
	 */
	public function admin_bar( $bar ) {
		if ( ! is_admin_bar_showing() ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'stockland-erp',
				'title' => 'Stockland ERP',
				'href'  => esc_url( self::erp_url() ),
			)
		);
	}

	/**
	 * Returns the authoritative ERP frontend entry URL.
	 *
	 * @return string ERP frontend URL.
	 */
	private static function erp_url() {
		return Stockland_Frontend::page_url();
	}

	/**
	 * Renders the admin launcher for the standalone ERP frontend.
	 *
	 * @return void
	 */
	public function erp_page() {
		if ( ! current_user_can( 'read' ) ) {
			wp_die( esc_html__( 'You do not have permission to access Stockland ERP.', 'stockland-inventory' ) );
		}
		// The WordPress menu is only a launcher. The operational ERP always opens
		// on its dedicated frontend so users never operate the ERP inside wp-admin.
		wp_safe_redirect( self::erp_url() );
		exit;
	}

	/**
	 * Checks whether the current source-declared HR schema version has recorded Owner approval.
	 *
	 * @return bool Whether a current approval record is present.
	 */
	private function hr_schema_approval_is_current() {
		$approval = get_option( 'stockland_hr_schema_migration_approval', array() );
		$signature = Stockland_HR::schema_signature();
		return is_array( $approval )
			&& '' !== $signature
			&& Stockland_HR::DB_VERSION === (string) ( $approval['schema_version'] ?? '' )
			&& hash_equals( $signature, (string) ( $approval['schema_signature'] ?? '' ) )
			&& (int) ( $approval['approved_by'] ?? 0 ) > 0
			&& Stockland_Authority::is_owner( (int) ( $approval['approved_by'] ?? 0 ) )
			&& ! empty( $approval['approved_at'] )
			&& strlen( trim( (string) ( $approval['reason'] ?? '' ) ) ) >= 12;
	}

	/**
	 * Records explicit Owner approval for the current additive HR schema in the immutable audit chain.
	 *
	 * @param string $reason Written approval reason.
	 * @return bool Whether approval was recorded successfully.
	 */
	private function record_hr_schema_approval( $reason ) {
		$reason = sanitize_text_field( (string) $reason );
		if ( strlen( trim( $reason ) ) < 12 || ! current_user_can( 'stockland_install_schema' ) || ! Stockland_Authority::is_owner() ) {
			return false;
		}
		$actor = get_current_user_id();
		$stamp = current_time( 'mysql' );
		if ( ! Stockland_Audit::log( $actor, null, 'HR_SCHEMA_MIGRATION_APPROVED', null, null, null, Stockland_HR::DB_VERSION . ':' . Stockland_HR::schema_signature(), $reason, 'HR_SCHEMA_' . Stockland_HR::DB_VERSION, $actor ) ) {
			return false;
		}
		update_option(
			'stockland_hr_schema_migration_approval',
			array(
				'schema_version' => Stockland_HR::DB_VERSION,
				'schema_signature' => Stockland_HR::schema_signature(),
				'approved_by'    => $actor,
				'approved_at'    => $stamp,
				'reason'         => $reason,
				'source'         => 'WP_ADMIN_SYSTEM_CONTROL',
			),
			false
		);
		return $this->hr_schema_approval_is_current();
	}

	/**
	 * Renders the protected System Control entry point for technical administrators.
	 *
	 * @return void
	 */
	public function system_control() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'stockland_install_schema' ) ) {
			wp_die( esc_html__( 'You do not have permission to access system control.', 'stockland-inventory' ) );
		}


		if ( isset( $_POST['stockland_one_click_install'] ) && check_admin_referer( 'stockland_one_click_install' ) && current_user_can( 'stockland_install_schema' ) ) {
			$confirmed = isset( $_POST['stockland_confirm_hr_schema'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['stockland_confirm_hr_schema'] ) );
			$reason    = isset( $_POST['stockland_hr_schema_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['stockland_hr_schema_reason'] ) ) : '';
			$result    = Stockland_Installer::run( get_current_user_id(), 25, $confirmed ? array( 'reason' => $reason ) : array() );
			if ( ! empty( $result['complete'] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p><strong>Installation complete.</strong> All Core, Accounting/Procurement and HR/Payroll tables are physically verified.</p></div>';
			} elseif ( ! empty( $result['needs_hr_approval'] ) ) {
				echo '<div class="notice notice-warning is-dismissible"><p>Core and Accounting are complete. HR/Payroll needs your written approval reason and backup confirmation (form below) because this site already had ERP tables.</p></div>';
			} elseif ( ! empty( $result['errors'] ) ) {
				echo '<div class="notice notice-error is-dismissible"><p><strong>Installer stopped:</strong> ' . esc_html( implode( ' | ', (array) $result['errors'] ) ) . '</p></div>';
			} else {
				echo '<div class="notice notice-info is-dismissible"><p>Installation progressed within its time budget. Press the button again to continue; nothing is repeated or overwritten.</p></div>';
			}
		}

		if ( isset( $_POST['stockland_authorize_release'] ) && check_admin_referer( 'stockland_authorize_release' ) ) {
			$reason = isset( $_POST['stockland_release_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['stockland_release_reason'] ) ) : '';
			$done   = ( isset( $_POST['stockland_release_confirm'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['stockland_release_confirm'] ) ) ) ? Stockland_Release_Authorization::authorize( get_current_user_id(), $reason ) : new WP_Error( 'confirm', 'Tick the confirmation box.' );
			echo is_wp_error( $done )
				? '<div class="notice notice-error is-dismissible"><p>' . esc_html( $done->get_error_message() ) . '</p></div>'
				: '<div class="notice notice-success is-dismissible"><p><strong>Live operation authorized</strong> for version ' . esc_html( STOCKLAND_VERSION ) . ' and recorded in the immutable audit chain.</p></div>';
		}

		if ( isset( $_POST['stockland_force_accounting_install'] ) && check_admin_referer( 'stockland_force_accounting_install' ) && current_user_can( 'stockland_install_schema' ) ) {
			update_option( 'stockland_accounting_pending', 1, false );
			Stockland_Activator::maybe_install_accounting( true );
			echo '<div class="notice notice-info is-dismissible"><p>Accounting module install attempted. Status below is refreshed.</p></div>';
		}

		if ( isset( $_POST['stockland_force_hr_install'] ) && check_admin_referer( 'stockland_force_hr_install' ) && current_user_can( 'stockland_install_schema' ) ) {
			$approval_blocked = false;
			if ( ! $this->hr_schema_approval_is_current() ) {
				$confirmed = isset( $_POST['stockland_confirm_hr_schema'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['stockland_confirm_hr_schema'] ) );
				$reason    = isset( $_POST['stockland_hr_schema_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['stockland_hr_schema_reason'] ) ) : '';
				if ( ! $confirmed || ! $this->record_hr_schema_approval( $reason ) ) {
					$approval_blocked = true;
					echo '<div class="notice notice-warning is-dismissible"><p>HR schema changes were not attempted. A written Owner approval reason and confirmation are required and must be recorded in the immutable audit chain.</p></div>';
				}
			}
			if ( ! $approval_blocked ) {
			$core_missing_before = Stockland_Activator::missing_core_tables();
			$finance_missing_before = Stockland_Accounting::missing_tables();
			$finance_gaps_before = Stockland_Accounting::upgrade_gaps();
			if ( ! empty( $core_missing_before ) || ! empty( $finance_missing_before ) || ! empty( $finance_gaps_before ) ) {
				echo '<div class="notice notice-warning is-dismissible"><p>HR setup is safely queued behind Core and Accounting/Procurement. Complete those verified stages first; no HR schema changes were attempted.</p></div>';
			} elseif ( get_transient( 'stockland_hr_admin_setup_lock' ) ) {
				echo '<div class="notice notice-warning is-dismissible"><p>Another HR setup request is still running. Please wait and refresh System Control.</p></div>';
			} else {
				set_transient( 'stockland_hr_admin_setup_lock', 1, 60 );
				try {
					$hr_missing_before = Stockland_HR::missing_tables();
					$hr_gaps_before = Stockland_HR::upgrade_gaps();
					if ( empty( $hr_missing_before ) && empty( $hr_gaps_before ) ) {
						echo '<div class="notice notice-success is-dismissible"><p>HR and Payroll schema is already physically verified. No schema change was needed.</p></div>';
					} else {
						Stockland_HR::install( 1 );
						$hr_missing_after = Stockland_HR::missing_tables();
						$hr_gaps_after = Stockland_HR::upgrade_gaps();
						$made_progress = count( $hr_missing_after ) < count( $hr_missing_before ) || count( $hr_gaps_after ) < count( $hr_gaps_before );
						if ( ! $made_progress && ( ! empty( $hr_missing_after ) || ! empty( $hr_gaps_after ) ) ) {
							throw new RuntimeException( 'HR setup made no physically verified progress. Review the HR install error and database permissions before retrying.' );
						}
						if ( empty( $hr_missing_after ) && empty( $hr_gaps_after ) ) {
							delete_option( 'stockland_hr_install_error' );
							echo '<div class="notice notice-success is-dismissible"><p>HR and Payroll schema is physically verified complete.</p></div>';
						} else {
							echo '<div class="notice notice-info is-dismissible"><p>HR setup advanced by one verified table or structural migration step. Remaining tables: ' . esc_html( implode( ', ', $hr_missing_after ) ) . '. Remaining structural checks: ' . esc_html( implode( ', ', $hr_gaps_after ) ) . '.</p></div>';
						}
					}
				} catch ( Throwable $e ) {
					update_option( 'stockland_hr_install_error', array( 'message' => $e->getMessage(), 'time' => current_time( 'mysql' ) ), false );
					Stockland_Runtime_Diagnostics::log_error( 'Stockland HR admin setup error: ' . $e->getMessage() );
					echo '<div class="notice notice-error is-dismissible"><p><strong>HR setup did not complete:</strong> ' . esc_html( $e->getMessage() ) . '</p></div>';
				} finally {
					delete_transient( 'stockland_hr_admin_setup_lock' );
				}
			}
			}
		}

		if ( isset( $_POST['stockland_repair_core_schema'] ) && check_admin_referer( 'stockland_repair_core_schema' ) && current_user_can( 'stockland_install_schema' ) ) {
			if ( get_transient( 'stockland_core_repair_backoff' ) ) {
				echo '<div class="notice notice-warning is-dismissible"><p>Core setup recently failed. Please retry in a few seconds.</p></div>';
			} else {
				try {
					$core_missing_now = Stockland_Activator::missing_core_tables();
					if ( empty( $core_missing_now ) ) {
						delete_option( 'stockland_setup_error' );
						echo '<div class="notice notice-success is-dismissible"><p><strong>Core schema is already physically complete (29/29).</strong> No Core repair request was executed.</p></div>';
						$progress = array(
							'complete'            => true,
							'installed_batch'     => 0,
							'missing_core_tables' => array(),
							'verified'            => true,
						);
					} else {
						$progress = Stockland_Activator::activate( 1 );
					}
					if ( ! empty( $progress['complete'] ) ) {
						echo '<div class="notice notice-success is-dismissible"><p><strong>Core schema is complete.</strong> The ERP is ready to continue protected module setup.</p></div>';
					} else {
						echo '<div class="notice notice-info is-dismissible"><p><strong>Core setup continued in a protected batch.</strong> Remaining Core tables: ' . esc_html( implode( ', ', (array) ( $progress['missing_core_tables'] ?? array() ) ) ) . '</p></div>';
						if ( ! empty( $progress['errors'] ) ) {
							echo '<div class="notice notice-error is-dismissible"><p><strong>Database/upgrade detail:</strong> ' . esc_html( implode( ' | ', (array) $progress['errors'] ) ) . '</p></div>';
						}
					}
				} catch ( Throwable $e ) {
					set_transient( 'stockland_core_repair_backoff', 1, 30 );
					echo '<div class="notice notice-error is-dismissible"><p><strong>Core schema repair failed:</strong> ' . esc_html( $e->getMessage() ) . '</p></div>';
				}
			}
		}

		global $wpdb;
		$db  = get_option( 'stockland_db_version', 'Not initialized' );
		$app = Stockland_Frontend::page_url();

		$activation_error   = get_option( 'stockland_activation_error' );
		$boot_error         = get_option( 'stockland_boot_error' );
		$accounting_error   = get_option( 'stockland_accounting_error' );
		$hr_install_error   = get_option( 'stockland_hr_install_error' );
		$accounting_pending = (bool) get_option( 'stockland_accounting_pending' );

		$expected_core       = Stockland_Activator::core_table_keys();
		$expected_accounting = array(
			'suppliers',
			'customers',
			'purchase_orders',
			'purchase_order_items',
			'purchase_receipts',
			'purchase_receipt_items',
			'supplier_invoices',
			'supplier_invoice_items',
			'customer_invoices',
			'invoice_items',
			'expenses',
			'payments',
			'payment_allocations',
			'gl_accounts',
			'gl_journals',
			'gl_lines',
		);

		$like        = $wpdb->esc_like( $wpdb->prefix . 'stock_' ) . '%';
		$found       = Stockland_DB::read_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		$prefix_len  = strlen( $wpdb->prefix . 'stock_' );
		$found_short = array_map(
			function ( $t ) use ( $prefix_len ) {
				return substr( $t, $prefix_len );
			},
			(array) $found
		);

		$core_missing         = array_values( array_diff( $expected_core, $found_short ) );
		$accounting_missing   = array_values( array_diff( $expected_accounting, $found_short ) );
		$finance_upgrade_gaps = class_exists( 'Stockland_Accounting' ) ? Stockland_Accounting::upgrade_gaps() : array();
		if ( empty( $core_missing ) ) {
			delete_option( 'stockland_setup_error' );
			$activation_error = null; }
		if ( empty( $accounting_missing ) && empty( $finance_upgrade_gaps ) ) {
			delete_option( 'stockland_accounting_error' );
			delete_option( 'stockland_accounting_pending' );
			$accounting_error = null; }

		echo '<div class="wrap"><h1>Stockland System Control</h1><p>Table totals are source-declared diagnostics, not an Owner-approved schema contract. HR/Payroll changes require a version- and DDL-fingerprint-bound Owner approval.</p>';
		echo '<div class="notice notice-success"><p><strong>WordPress backend is preserved.</strong> Stockland does not block, replace, or redirect the WordPress Dashboard.</p></div>';

		foreach ( array(
			'Activation error'        => $activation_error,
			'Startup error'           => $boot_error,
			'Accounting module error' => $accounting_error,
			'HR / Payroll setup error' => $hr_install_error,
		) as $label => $err ) {
			if ( is_array( $err ) && ! empty( $err['message'] ) ) {
				echo '<div class="notice notice-error"><p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $err['message'] );
				if ( ! empty( $err['time'] ) ) {
					echo ' <em>(' . esc_html( $err['time'] ) . ')</em>';
				}
				echo '</p></div>';
			}
		}

		$expected_hr = array_values( array_unique( array_merge( array( 'employees', 'employee_compensation', 'payroll_periods', 'payroll_lines' ), class_exists( 'Stockland_HR_Lifecycle' ) ? Stockland_HR_Lifecycle::table_keys() : array() ) ) );
		$full_expected = array_values( array_unique( array_merge( $expected_core, $expected_accounting, $expected_hr ) ) );
		$full_missing  = array_values( array_diff( $full_expected, $found_short ) );

		echo '<h2>Core system</h2>';
		echo '<table class="widefat striped" style="max-width:900px"><tbody>';
		echo '<tr><td><strong>ERP Version</strong></td><td>' . esc_html( STOCKLAND_VERSION ) . '</td></tr>';
		echo '<tr><td><strong>Database Version</strong></td><td>' . esc_html( $db ) . '</td></tr>';
		echo '<tr><td><strong>Core inventory/POS/ledger/authorization tables</strong></td><td>';
		if ( empty( $core_missing ) ) {
			echo '<span style="color:#00a32a">&#10003; all ' . count( $expected_core ) . ' present</span>';
		} else {
			echo '<span style="color:#d63638">&#9888; ' . ( count( $expected_core ) - count( $core_missing ) ) . ' of ' . count( $expected_core ) . ' present — missing: ' . esc_html( implode( ', ', $core_missing ) ) . '</span>';
		}
		echo '</td></tr>';
		echo '<tr><td><strong>Full source-declared ERP schema</strong></td><td>' . ( empty( $full_missing ) ? '<span style="color:#00a32a">&#10003; all ' . count( $full_expected ) . ' application tables present</span>' : '<span style="color:#d63638">&#9888; ' . ( count( $full_expected ) - count( $full_missing ) ) . ' of ' . count( $full_expected ) . ' present — missing: ' . esc_html( implode( ', ', $full_missing ) ) . '</span>' ) . '</td></tr>';
		
		$hr_missing  = array_values( array_diff( $expected_hr, $found_short ) );
		echo '<tr><td><strong>HR / Payroll source-declared schema</strong></td><td>' . ( empty( $hr_missing ) ? '<span style="color:#00a32a">&#10003; all ' . count( $expected_hr ) . ' present</span>' : '<span style="color:#d63638">&#9888; ' . ( count( $expected_hr ) - count( $hr_missing ) ) . ' of ' . count( $expected_hr ) . ' present — missing: ' . esc_html( implode( ', ', $hr_missing ) ) . '</span>' ) . '</td></tr>';
		$hr_upgrade_gaps = class_exists( 'Stockland_HR' ) ? Stockland_HR::upgrade_gaps() : array( 'hr_service_unavailable' );
		if ( empty( $hr_missing ) && empty( $hr_upgrade_gaps ) ) {
			delete_option( 'stockland_hr_install_error' );
			$hr_install_error = null;
		}
		if ( current_user_can( 'stockland_install_schema' ) ) {
			echo '<tr><td><strong>HR / Payroll schema setup</strong></td><td>';
			if ( empty( $core_missing ) && empty( $accounting_missing ) && empty( $finance_upgrade_gaps ) ) {
				if ( empty( $hr_missing ) && empty( $hr_upgrade_gaps ) ) {
					echo '<span style="color:#00a32a;font-weight:600">Verified complete — no HR setup action is required.</span>';
				} else {
					echo '<form method="post" style="display:block;max-width:760px">';
					wp_nonce_field( 'stockland_force_hr_install' );
					if ( ! $this->hr_schema_approval_is_current() ) {
						echo '<p><label for="stockland_hr_schema_reason"><strong>Owner approval reason (required)</strong></label><br><input id="stockland_hr_schema_reason" name="stockland_hr_schema_reason" type="text" class="regular-text" minlength="12" required placeholder="Reviewed additive HR schema and verified backup"></p>';
						echo '<p><label><input type="checkbox" name="stockland_confirm_hr_schema" value="1" required> I approve additive HR/Payroll schema version ' . esc_html( Stockland_HR::DB_VERSION ) . ' after reviewing the source DDL and confirming a restorable backup.</label></p>';
					} else {
						echo '<p class="description">Owner approval for HR schema version ' . esc_html( Stockland_HR::DB_VERSION ) . ' is recorded in the immutable audit chain.</p>';
					}
					echo '<button type="submit" name="stockland_force_hr_install" value="1" class="button button-primary">Continue HR &amp; Payroll setup (one verified table / migration step)</button></form>';
					echo '<p class="description">Runs only after Core and Accounting/Procurement are physically complete. Each request creates or verifies one source-declared HR table or advances one structural migration; it never drops, truncates or reseeds existing HR records.</p>';
				}
			} else {
				echo '<span style="color:#996800">Waiting for Core and Accounting/Procurement to complete. No HR migration is attempted before those prerequisites pass.</span>';
			}
			echo '</td></tr>';
		}
		echo '<tr><td><strong>Core installation state</strong></td><td>' . ( empty( $core_missing ) ? '<span style="color:#00a32a">&#10003; Core setup complete</span>' : '<span style="color:#dba617">Resumable protected setup — no heavy schema work runs during plugin activation</span>' ) . '</td></tr>';
		if ( current_user_can( 'stockland_install_schema' ) ) {
			echo '<tr><td><strong>Core schema setup</strong></td><td>';
			if ( empty( $core_missing ) ) {
				echo '<span style="color:#00a32a;font-weight:600">Verified complete — no Core setup action is required.</span>';
			} else {
				echo '<form method="post" style="display:inline">';
				wp_nonce_field( 'stockland_repair_core_schema' );
				echo '<button type="submit" name="stockland_repair_core_schema" value="1" class="button button-primary">Continue Core setup (one verified table per request)</button></form>';
			}
			echo '</td></tr>';
		}
		echo '<tr><td><strong>ERP Frontend</strong></td><td><a href="' . esc_url( $app ) . '" target="_blank" rel="noopener">Open Stockland ERP</a></td></tr>';
		echo '<tr><td><strong>WordPress Admin</strong></td><td><a href="' . esc_url( admin_url() ) . '">Open Dashboard</a></td></tr>';
		if ( current_user_can( 'stockland_install_schema' ) && ( ! empty( $core_missing ) || ! empty( $accounting_missing ) || ! empty( $finance_upgrade_gaps ) || ! empty( $hr_missing ) || ! empty( $hr_upgrade_gaps ) ) ) {
			echo '<tr><td><strong>Complete protected setup</strong></td><td><button type="button" id="stockland-run-full-setup" class="button button-primary">Continue all remaining setup</button><p class="description">Runs Core → Accounting/Procurement → HR/Payroll in sequential, bounded, physically verified requests. Existing tables and business records are preserved.</p><div id="stockland-admin-setup-status" role="status" aria-live="polite"></div></td></tr>';
		}
		echo '</tbody></table>';

		if ( current_user_can( 'stockland_install_schema' ) ) {
			echo '<h2 style="margin-top:24px">One-click installation</h2>';
			if ( Stockland_HR::is_schema_ready() && empty( $full_missing ) && empty( $finance_upgrade_gaps ) && empty( $hr_upgrade_gaps ) ) {
				echo '<p style="color:#00a32a;font-weight:600">&#10003; Installation complete &mdash; every Core, Accounting/Procurement and HR/Payroll table is present.</p>';
			} else {
				echo '<form method="post" style="max-width:760px">';
				wp_nonce_field( 'stockland_one_click_install' );
				if ( ! Stockland_Installer::hr_approval_is_current() && Stockland_Authority::is_owner() ) {
					echo '<p><label for="stockland_hr_schema_reason"><strong>Owner approval reason (needed once, because ERP tables already exist)</strong></label><br><input id="stockland_hr_schema_reason" name="stockland_hr_schema_reason" type="text" class="regular-text" minlength="12" placeholder="Reviewed additive HR schema and verified backup"></p>';
					echo '<p><label><input type="checkbox" name="stockland_confirm_hr_schema" value="1"> I approve additive HR/Payroll schema version ' . esc_html( Stockland_HR::DB_VERSION ) . ' and confirm a restorable backup exists.</label></p>';
				}
				echo '<button type="submit" name="stockland_one_click_install" value="1" class="button button-primary button-hero">Install / complete everything now</button></form>';
				echo '<p class="description">Creates every missing table in order (Core, Accounting/Procurement, HR/Payroll), verifying the physical database after each step. Add-only: no table is dropped, truncated, renamed or reseeded.</p>';
			}

			echo '<h2 style="margin-top:24px">Go-live authorization</h2>';
			$auth = Stockland_Release_Authorization::status();
			if ( ! empty( $auth['authorized'] ) ) {
				echo '<p style="color:#00a32a;font-weight:600">&#10003; Version ' . esc_html( STOCKLAND_VERSION ) . ' is authorized for live operation by the Owner (' . esc_html( $auth['record']['authorized_at'] ) . ').</p>';
			} elseif ( empty( $full_missing ) && empty( $finance_upgrade_gaps ) && empty( $hr_upgrade_gaps ) ) {
				if ( Stockland_Authority::is_owner() ) {
					echo '<form method="post" style="max-width:760px">';
					wp_nonce_field( 'stockland_authorize_release' );
					echo '<p><label for="stockland_release_reason"><strong>Owner authorization reason (required)</strong></label><br><input id="stockland_release_reason" name="stockland_release_reason" type="text" class="regular-text" minlength="12" required placeholder="Backup verified, roles assigned, opening data loaded"></p>';
					echo '<p><label><input type="checkbox" name="stockland_release_confirm" value="1" required> I, the Stockland Owner, authorize version ' . esc_html( STOCKLAND_VERSION ) . ' for live operation. This is recorded in the immutable audit chain and lapses if the version or schema changes.</label></p>';
					echo '<button type="submit" name="stockland_authorize_release" value="1" class="button button-primary">Authorize live operation</button></form>';
				} else {
					echo '<p>Waiting for the Stockland Owner to authorize live operation (' . esc_html( $auth['status'] ) . ').</p>';
				}
			} else {
				echo '<p>Available once installation is complete.</p>';
			}
		}

		echo '<h2 style="margin-top:24px">Accounting / procurement module</h2>';
		echo '<table class="widefat striped" style="max-width:900px"><tbody>';
		echo '<tr><td><strong>Status</strong></td><td>';
		if ( empty( $accounting_missing ) && empty( $finance_upgrade_gaps ) && ! $accounting_pending ) {
			echo '<span style="color:#00a32a">&#10003; installed — all ' . count( $expected_accounting ) . ' tables present and structural upgrade checks passed</span>';
		} elseif ( empty( $accounting_missing ) && ! empty( $finance_upgrade_gaps ) ) {
			echo '<span style="color:#dba617">&#9888; all ' . count( $expected_accounting ) . ' tables present, but compatibility repair is pending: ' . esc_html( implode( ', ', $finance_upgrade_gaps ) ) . '</span>';
		} elseif ( $accounting_pending && empty( $core_missing ) ) {
			echo '<span style="color:#dba617">On-demand setup — this module is completed during the protected administrator setup flow. ' . ( count( $expected_accounting ) - count( $accounting_missing ) ) . ' of ' . count( $expected_accounting ) . ' tables present so far.</span>';
		} else {
			echo '<span style="color:#d63638">Not yet complete — ' . ( count( $expected_accounting ) - count( $accounting_missing ) ) . ' of ' . count( $expected_accounting ) . ' tables present. Missing: ' . esc_html( implode( ', ', $accounting_missing ) ) . '</span>';
		}
		echo '</td></tr>';
		echo '</tbody></table>';
		if ( empty( $accounting_missing ) && empty( $finance_upgrade_gaps ) && ! $accounting_pending ) {
			echo '<p style="margin-top:10px;color:#00a32a;font-weight:600">Accounting / Procurement verified complete — no installation action is required.</p>';
		} else {
			echo '<form method="post" style="margin-top:10px">';
			wp_nonce_field( 'stockland_force_accounting_install' );
			echo '<button type="submit" name="stockland_force_accounting_install" value="1" class="button">Install / continue Accounting + Procurement (one verified table per request)</button>';
			echo '<p class="description">Protected administrator action. It advances only a small bounded schema batch and verifies the physical database after the change; it does not require Cron, a worker or a background service.</p>';
			echo '</form>';
		}

		echo '<h2 style="margin-top:24px">WordPress hosting compatibility</h2>';
		echo '<table class="widefat striped" style="max-width:900px"><tbody>';
		echo '<tr><td><strong>PHP version</strong></td><td>' . esc_html( phpversion() ) . ' (plugin requires 8.3+)</td></tr>';
		echo '<tr><td><strong>MySQL/MariaDB version</strong></td><td>' . esc_html( $wpdb->db_version() ) . '</td></tr>';
		echo '<tr><td><strong>memory_limit</strong></td><td>' . esc_html( ini_get( 'memory_limit' ) ) . '</td></tr>';
		echo '<tr><td><strong>max_execution_time</strong></td><td>' . esc_html( ini_get( 'max_execution_time' ) ) . ' seconds</td></tr>';
		echo '</tbody></table>';

		echo '<p style="margin-top:20px">Use the Stockland ERP menu for business operations. Use normal WordPress menus for plugin, user, theme, media and site administration.</p>';
		echo '</div>';
	}
}
