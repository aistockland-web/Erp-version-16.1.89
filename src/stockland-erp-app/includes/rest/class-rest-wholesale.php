<?php
// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName -- The established REST controller path is loaded by the plugin bootstrap; renaming it would break the include contract.
/**
 * REST controller source for Stockland ERP operational workflows.
 *
 * @package Stockland_ERP
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename is a loader compatibility contract; only this sniff is disabled.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * REST endpoint for Central Warehouse wholesale sales.
 *
 * @package Stockland_ERP
 */
class Stockland_REST_Wholesale extends Stockland_REST_Base {
	/**
	 * Registers the wholesale sales endpoint.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'stockland_register_rest_routes', array( $this, 'routes' ) ); }
	/**
	 * Registers the wholesale creation route and its permission gate.
	 *
	 * @return void
	 */
	public function routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/wholesale/surplus-advice',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'surplus_advice' ),
				'permission_callback' => array( $this, 'owner_permission' ),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/wholesale',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create' ),
				'permission_callback' => array( $this, 'owner_permission' ),
			)
		);
	}

	/**
	 * Enforces that wholesale inventory disposition remains an Owner-only decision.
	 *
	 * @param WP_REST_Request|null $request REST request (unused; required by the WP permission callback contract).
	 * @return true|WP_Error True for an active ERP Owner with the wholesale capability.
	 */
	public function owner_permission( $request = null ) {
		unset( $request );
		if ( Stockland_Authority::role() !== Stockland_Authority::OWNER || ! current_user_can( 'stockland_create_wholesale' ) ) {
			return new WP_Error( 'wholesale_owner_only', 'Wholesale surplus advice and bulk sales are available only to the active Stockland Owner profile.', array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Returns a read-only advisory calculated from the authoritative ledger and workflow queues.
	 *
	 * No stock is reserved and no order, audit event, notification or ledger entry is created.
	 * Query errors are returned as unavailable; they are never treated as zero demand or stock.
	 *
	 * @param WP_REST_Request $request Optional `sku` filter for a fresh pre-sale check.
	 * @return WP_REST_Response|WP_Error Read-only suggestion result or a fail-closed error.
	 */
	public function surplus_advice( WP_REST_Request $request ) {
		$permission = $this->owner_permission( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$sku    = sanitize_text_field( (string) $request->get_param( 'sku' ) );
		$result = $this->calculate_surplus_advice( '' !== $sku ? $sku : null );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response(
			array(
				'checked_at' => current_time( 'mysql' ),
				'read_only'  => true,
				'policy'     => 'ADVISORY_ONLY_OWNER_DECIDES',
				'summary'    => $result['summary'],
				'rows'       => $result['rows'],
			)
		);
	}

	/**
	 * Calculates saleable surplus after protecting branch and central warehouse needs.
	 *
	 * Every data source is read-only. `stock_transactions` is the quantity authority;
	 * active reservations are deducted, picked/in-transit transfers are treated as branch
	 * inbound, and outstanding requests/approved uncollected commitments are protected.
	 *
	 * @param string|null $only_sku Optional exact SKU to recalculate at sale time.
	 * @return array|WP_Error Rows and totals, or a fail-closed read error.
	 */
	private function calculate_surplus_advice( $only_sku = null ) {
		global $wpdb;
		$products_table          = Stockland_DB::table( 'products' );
		$locations_table         = Stockland_DB::table( 'locations' );
		$transactions_table      = Stockland_DB::table( 'transactions' );
		$reservations_table      = Stockland_DB::table( 'reservations' );
		$transfers_table         = Stockland_DB::table( 'transfers' );
		$requisitions_table      = Stockland_DB::table( 'requisitions' );
		$requisition_items_table = Stockland_DB::table( 'requisition_items' );

		$read_rows = static function ( $label, $sql, array $args ) use ( $wpdb ) {
			$wpdb->last_error = '';
			try {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query template identifiers use allow-listed %i placeholders and all values use separate prepared arguments.
				$prepared = $wpdb->prepare( $sql, $args );
			} catch ( Throwable $e ) {
				return new WP_Error( 'surplus_query_prepare_failed', 'Wholesale advice could not prepare the ' . $label . ' read. No data was changed.', array( 'status' => 503 ) );
			}
			if ( ! is_string( $prepared ) || '' === $prepared ) {
				return new WP_Error( 'surplus_query_prepare_failed', 'Wholesale advice could not validate the ' . $label . ' query. No data was changed.', array( 'status' => 503 ) );
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $prepared comes from $wpdb->prepare above; live stock and workflow commitment reads must remain fresh.
			$rows = $wpdb->get_results( $prepared, ARRAY_A );
			if ( '' !== (string) $wpdb->last_error ) {
				return new WP_Error( 'surplus_query_failed', 'Wholesale advice is unavailable because the ' . $label . ' read failed. No stock, order or ledger data was changed.', array( 'status' => 503 ) );
			}
			if ( ! is_array( $rows ) ) {
				return new WP_Error( 'surplus_query_unknown', 'Wholesale advice could not confirm the ' . $label . ' result. No data was changed.', array( 'status' => 503 ) );
			}
			return $rows;
		};

		$product_sql  = "SELECT variant_id,sku,product_name,brand,color,size,unit,wholesale_price,branch_reorder_level,central_reorder_level FROM %i WHERE is_active=1 AND product_type<>'STYLE' AND variant_id IS NOT NULL";
		$product_args = array( $products_table );
		if ( null !== $only_sku ) {
			$product_sql   .= ' AND sku=%s';
			$product_args[] = $only_sku;
		}
		$product_sql .= ' ORDER BY product_name,sku';
		$products     = $read_rows( 'Product Master', $product_sql, $product_args );
		if ( is_wp_error( $products ) ) {
			return $products;
		}
		if ( null !== $only_sku && ! $products ) {
			return new WP_Error( 'surplus_sku_not_found', 'The selected SKU is not an active, stockable Product Master variant. Refresh the Product Master before sale.', array( 'status' => 404 ) );
		}
		if ( ! $products ) {
			return array(
				'summary' => array(
					'sku_count'             => 0,
					'potential_sku_count'   => 0,
					'safe_wholesale_units'  => 0,
					'branch_need_skus'      => 0,
					'pending_request_units' => 0,
					'integrity_hold_skus'   => 0,
				),
				'rows'    => array(),
			);
		}

		$locations = $read_rows(
			'active locations',
			"SELECT id,location_name,location_type FROM %i WHERE is_active=1 AND location_type IN('Branch','Warehouse') ORDER BY CASE WHEN location_type='Warehouse' THEN 0 ELSE 1 END,id",
			array( $locations_table )
		);
		if ( is_wp_error( $locations ) ) {
			return $locations;
		}
		$branches   = array();
		$central_id = 0;
		foreach ( $locations as $location ) {
			if ( 'Branch' === (string) $location['location_type'] ) {
				$branches[] = array(
					'id'   => (int) $location['id'],
					'name' => (string) $location['location_name'],
				);
			} elseif ( 0 === strcasecmp( (string) $location['location_name'], 'Central Warehouse' ) && ! $central_id ) {
				$central_id = (int) $location['id'];
			}
		}
		if ( ! $central_id ) {
			return new WP_Error( 'central_warehouse_unresolved', 'Wholesale advice is withheld because the active Central Warehouse location could not be resolved. No data was changed.', array( 'status' => 503 ) );
		}
		$branch_ids   = array_values( array_map( 'intval', array_column( $branches, 'id' ) ) );
		$location_ids = array_merge( array( $central_id ), $branch_ids );
		$variant_ids  = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $p ) {
							return (int) ( $p['variant_id'] ?? 0 );
						},
						$products
					)
				)
			)
		);
		if ( ! $variant_ids || ! $location_ids ) {
			return new WP_Error( 'surplus_scope_unresolved', 'Wholesale advice could not resolve the Product Master or location scope. No data was changed.', array( 'status' => 503 ) );
		}
		$variant_in  = implode( ',', array_fill( 0, count( $variant_ids ), '%d' ) );
		$location_in = implode( ',', array_fill( 0, count( $location_ids ), '%d' ) );
		$branch_in   = $branch_ids ? implode( ',', array_fill( 0, count( $branch_ids ), '%d' ) ) : '0';

		$physical_rows = $read_rows(
			'inventory ledger',
			"SELECT t.location_id,COALESCE(t.variant_id,p.variant_id) variant_id,SUM(t.quantity_signed) physical_qty FROM %i t LEFT JOIN %i p ON ((t.variant_id IS NOT NULL AND p.variant_id=t.variant_id) OR (t.variant_id IS NULL AND p.sku=t.sku)) WHERE t.location_id IN({$location_in}) AND COALESCE(t.variant_id,p.variant_id) IN({$variant_in}) GROUP BY t.location_id,COALESCE(t.variant_id,p.variant_id)",
			array_merge( array( $transactions_table, $products_table ), $location_ids, $variant_ids )
		);
		if ( is_wp_error( $physical_rows ) ) {
			return $physical_rows; }

		$reservation_rows = $read_rows(
			'active reservations',
			"SELECT r.location_id,COALESCE(r.variant_id,p.variant_id) variant_id,SUM(r.quantity_reserved) reserved_qty FROM %i r LEFT JOIN %i p ON ((r.variant_id IS NOT NULL AND p.variant_id=r.variant_id) OR (r.variant_id IS NULL AND p.sku=r.sku)) WHERE r.status='Active' AND r.location_id IN({$location_in}) AND COALESCE(r.variant_id,p.variant_id) IN({$variant_in}) GROUP BY r.location_id,COALESCE(r.variant_id,p.variant_id)",
			array_merge( array( $reservations_table, $products_table ), $location_ids, $variant_ids )
		);
		if ( is_wp_error( $reservation_rows ) ) {
			return $reservation_rows; }

		$inbound_rows = $read_rows(
			'in-transit branch receipts',
			"SELECT t.to_location_id location_id,COALESCE(t.variant_id,p.variant_id) variant_id,SUM(GREATEST(COALESCE(t.dispatched_qty,t.quantity)-COALESCE(t.received_qty,0),0)) pending_inbound FROM %i t LEFT JOIN %i p ON ((t.variant_id IS NOT NULL AND p.variant_id=t.variant_id) OR (t.variant_id IS NULL AND p.sku=t.sku)) WHERE t.to_location_id IN({$branch_in}) AND t.status IN('Picked','In Transit') AND COALESCE(t.variant_id,p.variant_id) IN({$variant_in}) GROUP BY t.to_location_id,COALESCE(t.variant_id,p.variant_id)",
			array_merge( array( $transfers_table, $products_table ), $branch_ids, $variant_ids )
		);
		if ( is_wp_error( $inbound_rows ) ) {
			return $inbound_rows; }

		$req_rows = $read_rows(
			'open and approved requisitions',
			"SELECT r.requisition_id,r.branch_location_id,r.status,COALESCE(ri.variant_id,p.variant_id) variant_id,SUM(COALESCE(ri.quantity_requested,0)) requested_qty,SUM(COALESCE(ri.quantity_approved,0)) approved_qty,SUM(COALESCE(ri.quantity_dispatched,0)) dispatched_qty FROM %i r JOIN %i ri ON ri.requisition_id=r.requisition_id LEFT JOIN %i p ON ((ri.variant_id IS NOT NULL AND p.variant_id=ri.variant_id) OR (ri.variant_id IS NULL AND p.sku=ri.sku)) WHERE r.status IN('Requested','Reviewed','Approved','Reserved','Picked') AND COALESCE(ri.variant_id,p.variant_id) IN({$variant_in}) GROUP BY r.requisition_id,r.branch_location_id,r.status,COALESCE(ri.variant_id,p.variant_id)",
			array_merge( array( $requisitions_table, $requisition_items_table, $products_table ), $variant_ids )
		);
		if ( is_wp_error( $req_rows ) ) {
			return $req_rows; }

		$req_reserved_rows = $read_rows(
			'active requisition reservations',
			"SELECT r.requisition_id,COALESCE(r.variant_id,p.variant_id) variant_id,SUM(r.quantity_reserved) reserved_qty FROM %i r LEFT JOIN %i p ON ((r.variant_id IS NOT NULL AND p.variant_id=r.variant_id) OR (r.variant_id IS NULL AND p.sku=r.sku)) WHERE r.status='Active' AND r.location_id=%d AND r.requisition_id IS NOT NULL AND COALESCE(r.variant_id,p.variant_id) IN({$variant_in}) GROUP BY r.requisition_id,COALESCE(r.variant_id,p.variant_id)",
			array_merge( array( $reservations_table, $products_table, $central_id ), $variant_ids )
		);
		if ( is_wp_error( $req_reserved_rows ) ) {
			return $req_reserved_rows; }

		$direct_transfer_rows = $read_rows(
			'direct warehouse-to-branch commitments',
			"SELECT t.transfer_id,t.to_location_id,t.status,COALESCE(t.variant_id,p.variant_id) variant_id,t.quantity,t.dispatched_qty,t.received_qty FROM %i t LEFT JOIN %i p ON ((t.variant_id IS NOT NULL AND p.variant_id=t.variant_id) OR (t.variant_id IS NULL AND p.sku=t.sku)) WHERE t.from_location_id=%d AND t.to_location_id IN({$branch_in}) AND (t.requisition_id IS NULL OR t.requisition_id='') AND t.status IN('Requested','Approved','Reserved','Picked','In Transit') AND COALESCE(t.variant_id,p.variant_id) IN({$variant_in}) ORDER BY t.created_at,t.transfer_id",
			array_merge( array( $transfers_table, $products_table, $central_id ), $branch_ids, $variant_ids )
		);
		if ( is_wp_error( $direct_transfer_rows ) ) {
			return $direct_transfer_rows; }

		$direct_reserved_rows = $read_rows(
			'active direct-transfer reservations',
			"SELECT r.transfer_id,COALESCE(r.variant_id,p.variant_id) variant_id,SUM(r.quantity_reserved) reserved_qty FROM %i r LEFT JOIN %i p ON ((r.variant_id IS NOT NULL AND p.variant_id=r.variant_id) OR (r.variant_id IS NULL AND p.sku=r.sku)) WHERE r.status='Active' AND r.location_id=%d AND r.transfer_id IS NOT NULL AND COALESCE(r.variant_id,p.variant_id) IN({$variant_in}) GROUP BY r.transfer_id,COALESCE(r.variant_id,p.variant_id)",
			array_merge( array( $reservations_table, $products_table, $central_id ), $variant_ids )
		);
		if ( is_wp_error( $direct_reserved_rows ) ) {
			return $direct_reserved_rows; }

		$physical = array();
		foreach ( $physical_rows as $row ) {
			$physical[ (int) $row['location_id'] . '|' . (int) $row['variant_id'] ] = (float) $row['physical_qty']; }
		$reserved = array();
		foreach ( $reservation_rows as $row ) {
			$reserved[ (int) $row['location_id'] . '|' . (int) $row['variant_id'] ] = (float) $row['reserved_qty']; }
		$inbound = array();
		foreach ( $inbound_rows as $row ) {
			$inbound[ (int) $row['location_id'] . '|' . (int) $row['variant_id'] ] = (float) $row['pending_inbound']; }
		$req_reserved = array();
		foreach ( $req_reserved_rows as $row ) {
			$req_reserved[ (string) $row['requisition_id'] . '|' . (int) $row['variant_id'] ] = (float) $row['reserved_qty']; }
		$direct_reserved = array();
		foreach ( $direct_reserved_rows as $row ) {
			$direct_reserved[ (string) $row['transfer_id'] . '|' . (int) $row['variant_id'] ] = (float) $row['reserved_qty'];
		}

		$req_by_branch = array();
		foreach ( $req_rows as $row ) {
			$variant = (int) $row['variant_id'];
			$branch  = (int) $row['branch_location_id'];
			$key     = $branch . '|' . $variant;
			if ( ! isset( $req_by_branch[ $key ] ) ) {
				$req_by_branch[ $key ] = array(
					'open_requested'      => 0.0,
					'approved_remaining'  => 0.0,
					'approved_unreserved' => 0.0,
				); }
			$status = (string) $row['status'];
			if ( in_array( $status, array( 'Requested', 'Reviewed' ), true ) ) {
				$req_by_branch[ $key ]['open_requested'] += max( 0, (float) $row['requested_qty'] );
			} else {
				$remaining                                     = max( 0, (float) $row['approved_qty'] - (float) $row['dispatched_qty'] );
				$already_reserved                              = max( 0, (float) ( $req_reserved[ (string) $row['requisition_id'] . '|' . $variant ] ?? 0 ) );
				$req_by_branch[ $key ]['approved_remaining']  += $remaining;
				$req_by_branch[ $key ]['approved_unreserved'] += max( 0, $remaining - $already_reserved );
			}
		}

		$direct_by_branch = array();
		foreach ( $direct_transfer_rows as $row ) {
			$variant = (int) $row['variant_id'];
			$branch  = (int) $row['to_location_id'];
			$key     = $branch . '|' . $variant;
			if ( ! isset( $direct_by_branch[ $key ] ) ) {
				$direct_by_branch[ $key ] = array(
					'open_requested'                 => 0.0,
					'open_requested_unreserved'      => 0.0,
					'approved_expected'              => 0.0,
					'approved_unreserved_commitment' => 0.0,
				); }
			$status           = (string) $row['status'];
			$quantity         = max( 0, (float) $row['quantity'] );
			$dispatched       = max( 0, (float) $row['dispatched_qty'] );
			$remaining        = max( 0, $quantity - $dispatched );
			$already_reserved = max( 0, (float) ( $direct_reserved[ (string) $row['transfer_id'] . '|' . $variant ] ?? 0 ) );
			if ( 'Requested' === $status ) {
				$direct_by_branch[ $key ]['open_requested']            += $quantity;
				$direct_by_branch[ $key ]['open_requested_unreserved'] += max( 0, $quantity - $already_reserved );
			} else {
				$direct_by_branch[ $key ]['approved_expected']              += $remaining;
				$direct_by_branch[ $key ]['approved_unreserved_commitment'] += max( 0, $remaining - $already_reserved );
			}
		}

		$branch_default        = max( 0, (float) Stockland_DB::get_setting( 'branch_reorder_alert_level', '2' ) );
		$central_default       = max( 0, (float) Stockland_DB::get_setting( 'central_reorder_alert_level', '200' ) );
		$rows                  = array();
		$total_safe_units      = 0.0;
		$potential_skus        = 0;
		$branch_need_skus      = 0;
		$pending_request_units = 0.0;
		$integrity_hold_skus   = 0;

		foreach ( $products as $product ) {
			$variant           = (int) $product['variant_id'];
			$sku               = (string) $product['sku'];
			$central_physical  = (float) ( $physical[ $central_id . '|' . $variant ] ?? 0 );
			$central_reserved  = (float) ( $reserved[ $central_id . '|' . $variant ] ?? 0 );
			$central_available = max( 0, $central_physical - $central_reserved );
			$central_reorder   = ( null === $product['central_reorder_level'] || '' === (string) $product['central_reorder_level'] ) ? $central_default : max( 0, (float) $product['central_reorder_level'] );
			$branch_reorder    = ( null === $product['branch_reorder_level'] || '' === (string) $product['branch_reorder_level'] ) ? $branch_default : max( 0, (float) $product['branch_reorder_level'] );
			$commitments       = array(
				'unapproved_requisitions'          => 0.0,
				'approved_unreserved_requisitions' => 0.0,
				'direct_transfer_requests'         => 0.0,
				'approved_unreserved_transfers'    => 0.0,
				'unrequested_branch_topups'        => 0.0,
			);
			$branch_need_units = 0.0;
			$branches_out      = array();
			$integrity_hold    = $central_physical < -0.0001 || $central_reserved - $central_physical > 0.0001;
			foreach ( $branches as $branch_row ) {
				$branch_id                               = (int) $branch_row['id'];
				$key                                     = $branch_id . '|' . $variant;
				$branch_physical                         = (float) ( $physical[ $key ] ?? 0 );
				$branch_reserved                         = (float) ( $reserved[ $key ] ?? 0 );
				$branch_available                        = max( 0, $branch_physical - $branch_reserved );
				$inbound_qty                             = max( 0, (float) ( $inbound[ $key ] ?? 0 ) );
				$req                                     = $req_by_branch[ $key ] ?? array(
					'open_requested'      => 0.0,
					'approved_remaining'  => 0.0,
					'approved_unreserved' => 0.0,
				);
				$direct                                  = $direct_by_branch[ $key ] ?? array(
					'open_requested'                 => 0.0,
					'open_requested_unreserved'      => 0.0,
					'approved_expected'              => 0.0,
					'approved_unreserved_commitment' => 0.0,
				);
				$open_req                                = max( 0, (float) $req['open_requested'] );
				$approved_remaining                      = max( 0, (float) $req['approved_remaining'] );
				$open_direct                             = max( 0, (float) $direct['open_requested'] );
				$approved_direct_expected                = max( 0, (float) $direct['approved_expected'] );
				$unapproved_total                        = $open_req + $open_direct;
				$approved_expected_total                 = $approved_remaining + $approved_direct_expected;
				$raw_shortfall                           = max( 0, $branch_reorder - $branch_available - $inbound_qty - $approved_expected_total );
				$extra_topup                             = max( 0, $raw_shortfall - $unapproved_total );
				$commitments['unapproved_requisitions'] += $open_req;
				$commitments['approved_unreserved_requisitions'] += max( 0, (float) $req['approved_unreserved'] );
				$commitments['direct_transfer_requests']         += max( 0, (float) $direct['open_requested_unreserved'] );
				$commitments['approved_unreserved_transfers']    += max( 0, (float) $direct['approved_unreserved_commitment'] );
				$commitments['unrequested_branch_topups']        += $extra_topup;
				$branch_need                                      = $unapproved_total + $approved_expected_total + $extra_topup;
				$branch_need_units                               += $branch_need;
				$pending_request_units                           += $open_req + $open_direct;
				if ( $branch_physical < -0.0001 || $branch_reserved - $branch_physical > 0.0001 ) {
					$integrity_hold = true; }
				$branches_out[] = array(
					'location_id'                 => $branch_id,
					'location_name'               => (string) $branch_row['name'],
					'physical_qty'                => round( $branch_physical, 2 ),
					'reserved_qty'                => round( $branch_reserved, 2 ),
					'available_qty'               => round( $branch_available, 2 ),
					'reorder_level'               => round( $branch_reorder, 2 ),
					'pending_inbound_qty'         => round( $inbound_qty, 2 ),
					'open_request_qty'            => round( $open_req + $open_direct, 2 ),
					'approved_uncollected_qty'    => round( $approved_expected_total, 2 ),
					'additional_topup_need_qty'   => round( $extra_topup, 2 ),
					'protected_internal_need_qty' => round( $branch_need, 2 ),
				);
			}
			$total_commitments = array_sum( $commitments );
			$surplus           = $integrity_hold ? 0.0 : max( 0, $central_available - $total_commitments - $central_reorder );
			if ( $surplus > 0.0001 ) {
				++$potential_skus;
				$total_safe_units += $surplus; }
			if ( $branch_need_units > 0.0001 ) {
				++$branch_need_skus; }
			if ( $integrity_hold ) {
				++$integrity_hold_skus; }
			$rows[] = array(
				'variant_id'                    => $variant,
				'sku'                           => $sku,
				'product_name'                  => (string) $product['product_name'],
				'brand'                         => (string) ( $product['brand'] ?? '' ),
				'color'                         => (string) ( $product['color'] ?? '' ),
				'size'                          => (string) ( $product['size'] ?? '' ),
				'unit'                          => (string) ( $product['unit'] ?? 'PCS' ),
				'wholesale_price'               => round( (float) ( $product['wholesale_price'] ?? 0 ), 2 ),
				'central_physical_qty'          => round( $central_physical, 2 ),
				'central_reserved_qty'          => round( $central_reserved, 2 ),
				'central_available_qty'         => round( $central_available, 2 ),
				'central_reorder_level'         => round( $central_reorder, 2 ),
				'internal_commitments'          => array_map(
					static function ( $n ) {
						return round( (float) $n, 2 ); },
					$commitments
				),
				'internal_commitment_total_qty' => round( $total_commitments, 2 ),
				'branch_protected_need_qty'     => round( $branch_need_units, 2 ),
				'wholesale_surplus_qty'         => round( $surplus, 2 ),
				'integrity_hold'                => $integrity_hold,
				'status'                        => $integrity_hold ? 'INTEGRITY_HOLD' : ( $surplus > 0 ? 'SURPLUS_AVAILABLE' : ( $total_commitments > 0 ? 'PROTECT_INTERNAL_NEED' : 'NO_SAFE_SURPLUS' ) ),
				'branches'                      => $branches_out,
			);
		}
		usort(
			$rows,
			static function ( $a, $b ) {
				if ( (float) $a['wholesale_surplus_qty'] !== (float) $b['wholesale_surplus_qty'] ) {
					return (float) $a['wholesale_surplus_qty'] > (float) $b['wholesale_surplus_qty'] ? -1 : 1; }
				if ( (float) $a['branch_protected_need_qty'] !== (float) $b['branch_protected_need_qty'] ) {
					return (float) $a['branch_protected_need_qty'] > (float) $b['branch_protected_need_qty'] ? -1 : 1; }
				return strcmp( (string) $a['sku'], (string) $b['sku'] );
			}
		);
		return array(
			'rows'    => $rows,
			'summary' => array(
				'sku_count'             => count( $rows ),
				'potential_sku_count'   => $potential_skus,
				'safe_wholesale_units'  => round( $total_safe_units, 2 ),
				'branch_need_skus'      => $branch_need_skus,
				'pending_request_units' => round( $pending_request_units, 2 ),
				'integrity_hold_skus'   => $integrity_hold_skus,
			),
		);
	}
	/**
	 * Posts an authorized wholesale transaction against Central Warehouse stock.
	 *
	 * @param WP_REST_Request $request REST request containing SKU, quantity, price and date.
	 * @return WP_REST_Response|WP_Error Posted transaction response or a validation/persistence error.
	 * @throws Exception When stock, ledger or audit writes cannot be committed atomically.
	 */
	public function create( WP_REST_Request $request ) {
		$permission = $this->owner_permission( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$p        = $request->get_json_params();
		$sku      = sanitize_text_field( $p['sku'] ?? '' );
		$customer = sanitize_text_field( $p['customer'] ?? '' );
		$q        = (float) ( $p['quantity'] ?? 0 );
		$wh       = (int) Stockland_DB::get_location_id( 'Central Warehouse' );
		$date     = sanitize_text_field( $p['sale_date'] ?? current_time( 'Y-m-d' ) );
		if ( ! $wh || ! $sku || '' === $customer || $q <= 0 || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return new WP_Error( 'invalid', 'Invalid wholesale sale.', array( 'status' => 400 ) );
		}
		$prod = Stockland_DB::get_product( $sku );
		if ( ! $prod || ( $prod['product_type'] ?? 'SIMPLE' ) === 'STYLE' ) {
			return new WP_Error( 'invalid', 'Select a stockable variant/SKU.', array( 'status' => 400 ) );
		}
		try {
			$price = isset( $p['price'] ) ? Stockland_Money::decimal( Stockland_Money::minor( $p['price'] ) ) : Stockland_Money::decimal( Stockland_Money::minor( $prod['wholesale_price'] ) );
		} catch ( Throwable $e ) {
			return new WP_Error( 'invalid_price', 'Wholesale price must be a valid decimal amount.', array( 'status' => 400 ) );
		}if ( Stockland_Money::minor( $price ) < 0 ) {
			return new WP_Error( 'invalid_price', 'Wholesale price cannot be negative.', array( 'status' => 400 ) );
		}
		$lock = Stockland_DB::acquire_day_lock( $wh, $date );
		if ( ! $lock['ok'] ) {
			return new WP_Error( 'busy', 'Another warehouse operation is being processed for this date. Please retry.', array( 'status' => 409 ) );
		}
		global $wpdb;
		if ( false === Stockland_DB::execute_query( 'START TRANSACTION' ) ) {
			Stockland_DB::release_day_lock( $lock );
			return new WP_Error( 'transaction_failed', 'Could not start the wholesale transaction.', array( 'status' => 500 ) );}
		try {
			if ( Stockland_DB::is_day_closed( $wh, $date ) ) {
				throw new Exception( 'This Central Warehouse business day is already closed.' );
			}
			Stockland_DB::read_var( $wpdb->prepare( 'SELECT sku FROM %i WHERE sku=%s FOR UPDATE', Stockland_DB::table( 'products' ), $sku ) );
			if ( Stockland_DB::get_available_stock( $wh, $sku ) < $q ) {
				throw new Exception( 'Insufficient available Central Warehouse stock. No wholesale sale was committed.' );
			}
			$advice = $this->calculate_surplus_advice( $sku );
			if ( is_wp_error( $advice ) || empty( $advice['rows'][0] ) ) {
				throw new Exception( is_wp_error( $advice ) ? $advice->get_error_message() : 'Wholesale surplus could not be safely recalculated. No sale was committed.' );
			}
			if ( ! empty( $advice['rows'][0]['integrity_hold'] ) ) {
				throw new Exception( 'Wholesale sale is blocked because the selected SKU has an inventory integrity hold. Reconcile the ledger and reservations before any sale. No order, stock or ledger change was committed.' );
			}
			$current_safe_qty   = (float) $advice['rows'][0]['wholesale_surplus_qty'];
			$override_confirmed = ! empty( $p['override_internal_need_confirmed'] );
			$override_reason    = sanitize_textarea_field( (string) ( $p['override_reason'] ?? '' ) );
			$seen_safe_raw      = $p['advisory_surplus_qty'] ?? null;
			$seen_safe_qty      = is_numeric( $seen_safe_raw ) ? (float) $seen_safe_raw : null;
			$audit_reason       = null;
			if ( $q > $current_safe_qty + 0.0001 ) {
				if ( ! $override_confirmed || null === $seen_safe_qty || abs( $seen_safe_qty - $current_safe_qty ) > 0.01 || strlen( trim( $override_reason ) ) < 10 ) {
					throw new Exception( 'This quantity exceeds the safe wholesale suggestion after branch needs, open requests, active reservations and the Central Warehouse reorder floor. Refresh the advice; an Owner-only override requires a matching fresh surplus amount and a documented reason of at least 10 characters. No sale was committed.' );
				}
				$audit_reason = 'OWNER OVERRIDE OF SAFE WHOLESALE SURPLUS. Safe suggestion at review: ' . number_format( $current_safe_qty, 2, '.', '' ) . '; sale quantity: ' . number_format( $q, 2, '.', '' ) . '. Reason: ' . $override_reason;
			}
			$rate  = (float) Stockland_DB::get_vat_rate();
			$gross = Stockland_Money::multiply( $q, $price );
			$basis = Stockland_DB::get_vat_basis();
			if ( 'INCLUSIVE' === $basis ) {
				$vat = Stockland_Money::inclusive_tax( $gross, $rate );
				$net = Stockland_Money::subtract( $gross, $vat );
			} else {
				$vat   = Stockland_Money::percentage( $gross, $rate );
				$net   = $gross;
				$gross = Stockland_Money::add( $gross, $vat );
			} $cogs = Stockland_Money::multiply( $q, $prod['purchase_cost'] );
			$id     = Stockland_Sequence::next( 'WHO-', $date );
			if ( false === Stockland_DB::insert_record(
				Stockland_DB::table( 'wholesale_sales' ),
				array(
					'sale_id'            => $id,
					'sale_date'          => $date,
					'customer'           => $customer,
					'sku'                => $sku,
					'variant_id'         => (int) $prod['variant_id'],
					'quantity'           => $q,
					'wholesale_price'    => $price,
					'sales_value'        => $gross,
					'payment_status'     => sanitize_text_field( $p['payment_status'] ?? 'Unpaid' ),
					'vat_amount'         => $vat,
					'net_sales_excl_vat' => $net,
					'cogs'               => $cogs,
					'gross_profit'       => Stockland_Money::subtract( $net, $cogs ),
					'created_by'         => get_current_user_id(),
				)
			) ) {
				throw new Exception( 'Could not save wholesale sale.' );
			}
			if ( false === Stockland_Ledger::record( $wh, $sku, 'WHOLESALE_SALE', -$q, 'wholesale_sale', $id, $date, get_current_user_id(), $id ) ) {
				throw new Exception( 'Ledger post failed.' );
			}
			// Wholesale is an authoritative business order. It must have the same
			// Order -> Inventory -> PLEDGER/GL -> Audit atomic contract as Daily POS.
			// Payment status controls whether the receivable is settled immediately;
			// Partial without a payment amount is retained as an order-state marker and
			// remains an AR invoice until an explicit Finance payment is posted.
			$payment_status = strtoupper( sanitize_text_field( $p['payment_status'] ?? 'UNPAID' ) );
			if ( ! in_array( $payment_status, array( 'PAID', 'UNPAID', 'PARTIAL' ), true ) ) {
				throw new Exception( 'Invalid wholesale payment status.' );
			}
			$debit_account = 'PAID' === $payment_status ? '1000' : '1100';
			$jid           = Stockland_Accounting::post_journal(
				$date,
				'WHOLESALE_SALE',
				$id,
				'Wholesale sale ' . $id,
				array(
					array(
						'account_code' => $debit_account,
						'debit'        => $gross,
						'credit'       => 0,
						'location_id'  => $wh,
						'memo'         => 'Wholesale sale proceeds / receivable',
					),
					array(
						'account_code' => '4100',
						'debit'        => 0,
						'credit'       => $net,
						'location_id'  => $wh,
						'memo'         => 'Wholesale sales revenue',
					),
					array(
						'account_code' => '2100',
						'debit'        => 0,
						'credit'       => $vat,
						'location_id'  => $wh,
						'memo'         => 'VAT payable',
					),
					array(
						'account_code' => '5000',
						'debit'        => $cogs,
						'credit'       => 0,
						'location_id'  => $wh,
						'memo'         => 'Cost of goods sold',
					),
					array(
						'account_code' => '1200',
						'debit'        => 0,
						'credit'       => $cogs,
						'location_id'  => $wh,
						'memo'         => 'Inventory relief',
					),
				),
				get_current_user_id()
			);
			if ( ! $jid ) {
				throw new Exception( 'Wholesale PLEDGER/GL journal failed. The order was rolled back and no partial stock post was allowed.' );
			}
			if ( ! Stockland_Audit::log( get_current_user_id(), $wh, 'WHOLESALE_SALE', $sku, $q, null, $price, $audit_reason, $id ) ) {
				throw new Exception( 'Audit event could not be recorded; wholesale sale was not committed.' );
			}
			if ( false === Stockland_DB::execute_query( 'COMMIT' ) ) {
				throw new Exception( 'Wholesale sale commit failed.' );
			}
			Stockland_DB::release_day_lock( $lock );
			return rest_ensure_response(
				array(
					'sale_id' => $id,
					'total'   => $gross,
					'net'     => $net,
					'vat'     => $vat,
				)
			);
		} catch ( Throwable $e ) {
			Stockland_DB::execute_query( 'ROLLBACK' );
			Stockland_DB::release_day_lock( $lock );
			return new WP_Error( 'wholesale_failed', $e->getMessage(), array( 'status' => 409 ) );}
	}
}

// phpcs:enable WordPress.Files.FileName.InvalidClassFileName -- End the narrowly scoped legacy filename exception.
