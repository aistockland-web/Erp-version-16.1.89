<?php
/**
 * Plugin Name:       Stockland Ethiopia ERP
 * Description:       Stockland Ethiopia ERP for retail, wholesale, inventory, finance, HR and payroll operations.
 * Version:           16.2.18
 * Author:            Stockland Ethiopia
 * Text Domain:       stockland-inventory
 * Requires PHP:      8.3
 * Requires at least: 6.8
 *
 * @package Stockland_ERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'STOCKLAND_VERSION', '16.2.18' );
define( 'STOCKLAND_ROLE_CAPS_VERSION', '16.1.66-finance-procurement-authority-v1' );
define( 'STOCKLAND_CORE_SCHEMA_VERSION', '16.1.1' );
define( 'STOCKLAND_ACCOUNTING_SCHEMA_VERSION', '16.1.15' );
define( 'STOCKLAND_HR_SCHEMA_VERSION', '16.2.1' );
define( 'STOCKLAND_REST_NAMESPACE', 'stockland/v16' );
define( 'STOCKLAND_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STOCKLAND_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'STOCKLAND_DB_VERSION', '16.1.1' );
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-runtime-diagnostics.php';
Stockland_Runtime_Diagnostics::boot();

/**
 * ------------------------------------------------------------------
 * Core includes
 * ------------------------------------------------------------------
 * Kept as small single-responsibility classes so each phase in the
 * development order (Phase 1 foundation, Phase 2 roles, Phase 3
 * product master, Phase 5 ledger, Phase 6 sales, ...) maps to one
 * file rather than one giant plugin file.
 */
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-accounting.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-activator.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-deactivator.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-roles.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-db.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-settings.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-ledger.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-workflow.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-audit.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-admin.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-xlsx.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-csv.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-frontend.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-app.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-hr.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-product-service.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-sequence.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-money.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-hr-payroll-calculator.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-hr-private-vault.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-hr-lifecycle.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-payroll-statutory.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-sale-math.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-business-calendar.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-integrity.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-schema-contract.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-installer.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-release-authorization.php';

require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-controller.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-setup.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-products.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-stock.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-sales.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-pos-cart.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-calendar.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-transfers.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-receipts.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-returns.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-reports.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-app.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-accounting.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-wholesale.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-authority.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-notifications.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-requisition-service.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/class-stockland-sourcing.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-import-v16.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-inventory-report-v16.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-locations.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-requisition-v16.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-hr.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-hr-lifecycle.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-hr-payroll-cycle.php';
require_once STOCKLAND_PLUGIN_DIR . 'includes/rest/class-rest-sop-library.php';

/**
 * Activation / deactivation.
 * Activation only initializes a lightweight setup state. The protected administrator
 * setup path then creates/upgrades the ERP schema in bounded batches, verifies every
 * required table, and finalizes settings/roles. This avoids long activation requests
 * on constrained shared hosting.
 */
register_activation_hook(
	__FILE__,
	function () {
		try {
			Stockland_Activator::begin_activation();
			delete_option( 'stockland_activation_error' );
			// One-pack install: build Core, Accounting/Procurement and HR/Payroll inside
			// a bounded time budget. Anything unfinished (slow host, or an existing install
			// that needs the Owner's written HR approval) stays resumable from System Control.
			try {
				Stockland_Installer::run( get_current_user_id(), 20 );
			} catch ( Throwable $install_error ) {
				Stockland_Runtime_Diagnostics::log_error( 'Stockland one-pack installer deferred: ' . $install_error->getMessage() );
			}
		} catch ( Throwable $e ) {
			update_option(
				'stockland_activation_error',
				array(
					'message' => $e->getMessage(),
					'time'    => current_time( 'mysql' ),
				),
				false
			);
			Stockland_Runtime_Diagnostics::log_error( 'Stockland ERP activation error: ' . $e->getMessage() );
			deactivate_plugins( plugin_basename( __FILE__ ), true );
			wp_die(
				esc_html( 'Stockland ERP could not finish activating: ' . $e->getMessage() ) .
				'<br><br>' .
				esc_html( 'The plugin has been deactivated so the rest of the site keeps working. Fix the issue above, or check the WordPress/PHP error log for the full trace, then activate again.' ),
				esc_html__( 'Stockland ERP activation error', 'stockland-inventory' ),
				array( 'back_link' => true )
			);
		}
	}
);
register_deactivation_hook( __FILE__, array( 'Stockland_Deactivator', 'deactivate' ) );

/**
 * Boot the plugin once WordPress core (and its DB layer) is ready.
 */
function stockland_init_plugin() {
	try {
		// The ERP frontend and protected setup controller are always available even
		// when the business schema is incomplete. This prevents a partial schema from
		// taking down WordPress and lets the frontend setup flow repair the database.
		new Stockland_Admin();
		new Stockland_Frontend();
		new Stockland_REST_Setup();

		// Role/capability normalization is intentionally deferred to init. WordPress
		// has completed its normal bootstrap by then, and a plugin update cannot fail
		// the entire site merely because a role synchronization hook encounters an
		// unexpected runtime condition.
		add_action(
			'init',
			function () {
				try {
					// Reconcile the critical PO decision capability even if an earlier
					// partial update wrote the version marker before the role map healed.
					$owner_role            = get_role( Stockland_Roles::ROLE_OWNER );
					$management_role       = get_role( Stockland_Roles::ROLE_MANAGEMENT );
					$admin_role            = get_role( 'administrator' );
					$owner_po_cap_missing  = ! $owner_role || ! $owner_role->has_cap( 'stockland_approve_purchase_orders' );
					$management_po_cap_bad = $management_role && $management_role->has_cap( 'stockland_approve_purchase_orders' );
					$admin_po_cap_bad      = $admin_role && $admin_role->has_cap( 'stockland_approve_purchase_orders' );
					if ( get_option( 'stockland_roles_cap_version', '' ) !== STOCKLAND_ROLE_CAPS_VERSION || $owner_po_cap_missing || $management_po_cap_bad || $admin_po_cap_bad ) {
						Stockland_Roles::register_roles();
						Stockland_Roles::sync_roles();
						update_option( 'stockland_roles_cap_version', STOCKLAND_ROLE_CAPS_VERSION, false );
					}
				} catch ( Throwable $e ) {
					update_option(
						'stockland_boot_error',
						array(
							'message' => 'Role synchronization: ' . $e->getMessage(),
							'time'    => current_time( 'mysql' ),
						),
						false
					);
					Stockland_Runtime_Diagnostics::log_error( 'Stockland ERP role sync error: ' . $e->getMessage() );
				}
			},
			5
		);

		$application_missing = array_merge(
			Stockland_Activator::missing_core_tables(),
			class_exists( 'Stockland_Accounting' ) ? Stockland_Accounting::missing_tables() : array(),
			class_exists( 'Stockland_HR' ) ? Stockland_HR::missing_tables() : array()
		);

		// Table presence alone is not sufficient for accounting runtime safety.
		// The 16.1.x compatibility migration can require variant_id columns/indexes
		// on existing accounting tables. Keep all operational REST/App routes fail
		// closed until those physical upgrade gaps are also resolved.
		if ( class_exists( 'Stockland_Accounting' ) ) {
			try {
				$accounting_upgrade_gaps = Stockland_Accounting::upgrade_gaps();
				if ( ! empty( $accounting_upgrade_gaps ) ) {
					$application_missing = array_merge( $application_missing, $accounting_upgrade_gaps );
				}
			} catch ( Throwable $e ) {
				$application_missing[] = 'accounting_upgrade_check_failed';
				update_option(
					'stockland_boot_error',
					array(
						'message' => 'Accounting schema upgrade check: ' . $e->getMessage(),
						'time'    => current_time( 'mysql' ),
					),
					false
				);
				Stockland_Runtime_Diagnostics::log_error( 'Stockland ERP accounting schema gate error: ' . $e->getMessage() );
			}
		}
		if ( class_exists( 'Stockland_HR' ) ) {
			try {
				$hr_upgrade_gaps = Stockland_HR::upgrade_gaps();
				if ( ! empty( $hr_upgrade_gaps ) ) {
					$application_missing = array_merge( $application_missing, $hr_upgrade_gaps );
				}
			} catch ( Throwable $e ) {
				$application_missing[] = 'hr_upgrade_check_failed';
				update_option(
					'stockland_boot_error',
					array(
						'message' => 'HR schema upgrade check: ' . $e->getMessage(),
						'time'    => current_time( 'mysql' ),
					),
					false
				);
				Stockland_Runtime_Diagnostics::log_error( 'Stockland ERP HR schema gate error: ' . $e->getMessage() );
			}
		}

		if ( ! empty( $application_missing ) ) {
			update_option( 'stockland_application_schema_ready', 0, false );
			return;
		}

		update_option( 'stockland_application_schema_ready', 1, false );
		update_option( 'stockland_db_version', STOCKLAND_DB_VERSION, false );

		new Stockland_App();
		new Stockland_REST_Products();
		new Stockland_REST_Stock();
		new Stockland_REST_Sales();
		new Stockland_REST_POS_Cart();
		new Stockland_REST_Calendar();
		new Stockland_REST_Transfers();
		new Stockland_REST_Receipts();
		new Stockland_REST_Returns();
		new Stockland_REST_Reports();
		new Stockland_REST_App();
		new Stockland_REST_Accounting();
		new Stockland_REST_Import_V16();
		new Stockland_REST_Inventory_Report_V16();
		new Stockland_REST_Locations();
		new Stockland_REST_Requisition_V16();
		new Stockland_REST_HR();
		new Stockland_REST_HR_Lifecycle();
		new Stockland_REST_HR_Payroll_Cycle();
		new Stockland_REST_SOP_Library();
		new Stockland_REST_Wholesale();
		add_action( 'rest_api_init', array( 'Stockland_Release_Authorization', 'register_routes' ) );

		delete_option( 'stockland_boot_error' );
	} catch ( Throwable $e ) {
		update_option(
			'stockland_boot_error',
			array(
				'message' => $e->getMessage(),
				'time'    => current_time( 'mysql' ),
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
			),
			false
		);
		Stockland_Runtime_Diagnostics::log_error( 'Stockland ERP startup error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
		// Do not propagate a plugin runtime exception into a WordPress Critical Error.
		// Native WordPress remains usable while System Control records the exact failure.
	}
}
add_action( 'plugins_loaded', 'stockland_init_plugin' );
/**
 * Register REST routes. Done on rest_api_init by each controller's own
 * constructor (they hook themselves); this is just the umbrella action
 * WordPress expects to exist for namespace stockland/v16.
 */
add_action(
	'rest_api_init',
	function () {
		do_action( 'stockland_register_rest_routes' );
	}
);
