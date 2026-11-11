<?php
// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName -- The established REST controller path is loaded by the plugin bootstrap; renaming it would break the include contract.
/**
 * REST controller source for Stockland ERP operational workflows.
 *
 * @package Stockland_ERP
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename is a loader compatibility contract; only this sniff is disabled.
/**
 * Read-only stock and reorder-status REST endpoints.
 *
 * @package Stockland_ERP
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only stock endpoints. Always derives stock from the immutable transaction ledger and active reservations, never a stored balance
 * column — see Stockland_Ledger. Branch-scoped per section 13.
 *
 * @package Stockland_ERP
 */
class Stockland_REST_Stock extends Stockland_REST_Base {

	/**
	 * Registers stock and reorder-status route hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'stockland_register_rest_routes', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers stock and reorder-status read routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/stock',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_stock' ),
				'permission_callback' => function () {
					return Stockland_Authority::assert_profile(); },
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/stock/reorder',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_reorder_status' ),
				'permission_callback' => function () {
					return Stockland_Authority::assert_profile(); },
			)
		);
	}

	/**
	 * Returns ledger-derived stock for the user's authorized location.
	 *
	 * @param WP_REST_Request $request REST request containing location and product filters.
	 * @return mixed REST response with stock rows or a WP_Error.
	 */
	public function get_stock( WP_REST_Request $request ) {
		$location_id = $this->resolve_branch_for_user( $request->get_param( 'location_id' ) );
		if ( is_wp_error( $location_id ) ) {
			return $location_id;
		}
		global $wpdb;
		$tx       = Stockland_DB::table( 'transactions' );
		$res      = Stockland_DB::table( 'reservations' );
		$products = Stockland_DB::table( 'products' );

		$variant_id = 0;
		$has_sku    = (bool) $request->get_param( 'sku' );
		if ( $has_sku ) {
			$product = Stockland_DB::get_product( sanitize_text_field( $request->get_param( 'sku' ) ) );
			if ( ! $product ) {
				return rest_ensure_response( array() );
			}
			$variant_id = (int) ( $product['variant_id'] ?? 0 );

			// A valid Product Master variant may have no posted movement yet.
			// For a location-scoped SKU lookup, return its authoritative zero balance
			// instead of hiding it or confusing it with a missing SKU.
			if ( $location_id ) {
				$sku_rows = Stockland_DB::read_results(
					$wpdb->prepare(
						"SELECT %d location_id,p.variant_id,p.sku,p.product_name,p.product_type,p.style_code,p.parent_sku,p.color,p.size,p.barcode,p.gtin,
						COALESCE((SELECT SUM(t.quantity_signed) FROM %i t WHERE t.location_id=%d AND (t.variant_id=p.variant_id OR (t.variant_id IS NULL AND t.sku=p.sku))),0) physical_stock,
						COALESCE((SELECT SUM(r.quantity_reserved) FROM %i r WHERE r.location_id=%d AND r.status='Active' AND (r.variant_id=p.variant_id OR (r.variant_id IS NULL AND r.sku=p.sku))),0) reserved_stock,
						GREATEST(0,COALESCE((SELECT SUM(t.quantity_signed) FROM %i t WHERE t.location_id=%d AND (t.variant_id=p.variant_id OR (t.variant_id IS NULL AND t.sku=p.sku))),0)-
						COALESCE((SELECT SUM(r.quantity_reserved) FROM %i r WHERE r.location_id=%d AND r.status='Active' AND (r.variant_id=p.variant_id OR (r.variant_id IS NULL AND r.sku=p.sku))),0)) available_stock
						FROM %i p WHERE p.is_active=1 AND p.product_type<>'STYLE' AND p.sku=%s LIMIT 1",
						(int) $location_id,
						$tx,
						(int) $location_id,
						$res,
						(int) $location_id,
						$tx,
						(int) $location_id,
						$res,
						(int) $location_id,
						$products,
						(string) $product['sku']
					),
					ARRAY_A
				);
				if ( $wpdb->last_error ) {
					return new WP_Error( 'stock_read_failed', 'Live branch stock could not be read. No stock balance was assumed; refresh or contact ERP support.', array( 'status' => 503 ) );
				}
				if ( ! is_array( $sku_rows ) || empty( $sku_rows ) ) {
					return new WP_Error( 'stock_identity_changed', 'The selected SKU is no longer available as an active stockable Product Master variant. Refresh Product Master search before requesting stock.', array( 'status' => 409 ) );
				}
				return rest_ensure_response( $sku_rows );
			}
		}

		$rows = Stockland_DB::read_results(
			$wpdb->prepare(
				"SELECT v.location_id,v.variant_id,p.sku,p.product_name,p.product_type,p.style_code,p.parent_sku,p.color,p.size,p.barcode,p.gtin,
                v.physical_stock,COALESCE(r.reserved_stock,0) reserved_stock,
                GREATEST(0,v.physical_stock-COALESCE(r.reserved_stock,0)) available_stock,
                CASE WHEN COALESCE(r.reserved_stock,0)>v.physical_stock THEN 'OVER-RESERVED' ELSE '' END availability_exception
				FROM (
                    SELECT t.location_id,COALESCE(t.variant_id,legacy_p.variant_id) variant_id,SUM(t.quantity_signed) physical_stock
                    FROM %i t LEFT JOIN %i legacy_p ON t.variant_id IS NULL AND legacy_p.sku=t.sku
                    WHERE COALESCE(t.variant_id,legacy_p.variant_id) IS NOT NULL
                    GROUP BY t.location_id,COALESCE(t.variant_id,legacy_p.variant_id)
                ) v
				JOIN %i p ON p.variant_id=v.variant_id
				LEFT JOIN (
                    SELECT r.location_id,COALESCE(r.variant_id,legacy_r.variant_id) variant_id,SUM(r.quantity_reserved) reserved_stock
                    FROM %i r LEFT JOIN %i legacy_r ON r.variant_id IS NULL AND legacy_r.sku=r.sku
                    WHERE r.status='Active' AND COALESCE(r.variant_id,legacy_r.variant_id) IS NOT NULL
                    GROUP BY r.location_id,COALESCE(r.variant_id,legacy_r.variant_id)
                ) r ON r.location_id=v.location_id AND r.variant_id=p.variant_id
				WHERE p.is_active=1 AND p.product_type<>'STYLE'
					AND (%d = 0 OR v.location_id=%d)
					AND (%d = 0 OR v.variant_id=%d)
				ORDER BY v.location_id,p.sku",
				$tx,
				$products,
				$products,
				$res,
				$products,
				$location_id ? 1 : 0,
				$location_id ? (int) $location_id : 0,
				$has_sku ? 1 : 0,
				$variant_id
			),
			ARRAY_A
		);
		return rest_ensure_response( $rows );
	}

	/** Mirrors REORDER ACTIONS sheet: Current Stock vs Reorder Level, per location. */
	/**
	 * Returns ledger-derived reorder alerts for the authorized location scope.
	 *
	 * @param WP_REST_Request $request REST request containing optional location filters.
	 * @return mixed REST response with reorder rows or a WP_Error.
	 */
	public function get_reorder_status( WP_REST_Request $request ) {
		$location_id = $this->resolve_branch_for_user( $request->get_param( 'location_id' ) );
		if ( is_wp_error( $location_id ) ) {
			return $location_id;
		}
		global $wpdb;
		$locations = Stockland_DB::table( 'locations' );
		$products  = Stockland_DB::table( 'products' );
		$tx        = Stockland_DB::table( 'transactions' );
		$res       = Stockland_DB::table( 'reservations' );
		$rows      = Stockland_DB::read_results(
			$wpdb->prepare(
				"SELECT l.id location_id,l.location_type,l.location_name,p.variant_id,p.sku,p.product_name,p.style_code,p.brand,p.color,p.size,p.barcode,p.gtin,
                COALESCE(b.physical_stock,0) current_stock,COALESCE(b.physical_stock,0) physical_stock,
                COALESCE(a.reserved_stock,0) reserved_stock,
                GREATEST(0,COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0)) available_stock,
                CASE WHEN COALESCE(a.reserved_stock,0)>COALESCE(b.physical_stock,0) THEN 'OVER-RESERVED' ELSE '' END availability_exception,
                COALESCE(i.inbound_stock,0) inbound_stock,
                GREATEST(0,COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0))+COALESCE(i.inbound_stock,0) reorder_position,
                IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level) reorder_level
                FROM %i p CROSS JOIN %i l
                LEFT JOIN (
                    SELECT t.location_id,COALESCE(t.variant_id,legacy_p.variant_id) variant_id,SUM(t.quantity_signed) physical_stock
                    FROM %i t LEFT JOIN %i legacy_p ON t.variant_id IS NULL AND legacy_p.sku=t.sku
                    WHERE COALESCE(t.variant_id,legacy_p.variant_id) IS NOT NULL
                    GROUP BY t.location_id,COALESCE(t.variant_id,legacy_p.variant_id)
                ) b ON b.variant_id=p.variant_id AND b.location_id=l.id
                LEFT JOIN (
                    SELECT r.location_id,COALESCE(r.variant_id,legacy_r.variant_id) variant_id,SUM(r.quantity_reserved) reserved_stock
                    FROM %i r LEFT JOIN %i legacy_r ON r.variant_id IS NULL AND legacy_r.sku=r.sku
                    WHERE r.status='Active' AND COALESCE(r.variant_id,legacy_r.variant_id) IS NOT NULL
                    GROUP BY r.location_id,COALESCE(r.variant_id,legacy_r.variant_id)
                ) a ON a.variant_id=p.variant_id AND a.location_id=l.id
                LEFT JOIN (
                    SELECT t.to_location_id location_id,COALESCE(t.variant_id,inbound_p.variant_id) variant_id,
                           SUM(GREATEST(0,COALESCE(t.dispatched_qty,0)-COALESCE(t.received_qty,0))) inbound_stock
                    FROM %i t LEFT JOIN %i inbound_p ON t.variant_id IS NULL AND inbound_p.sku=t.sku
                    WHERE t.status='In Transit' AND COALESCE(t.variant_id,inbound_p.variant_id) IS NOT NULL
                    GROUP BY t.to_location_id,COALESCE(t.variant_id,inbound_p.variant_id)
                ) i ON i.variant_id=p.variant_id AND i.location_id=l.id
                WHERE l.location_type IN('Warehouse','Branch') AND l.is_active=1 AND p.is_active=1 AND p.product_type<>'STYLE'
                    AND (%d = 0 OR l.id=%d)
                ORDER BY reorder_position,p.sku",
				$products,
				$locations,
				$tx,
				$products,
				$res,
				$products,
				Stockland_DB::table( 'transfers' ),
				$products,
				$location_id ? 1 : 0,
				$location_id ? (int) $location_id : 0
			),
			ARRAY_A
		);
		if ( $wpdb->last_error ) {
			return new WP_Error( 'reorder_read_failed', 'Live reorder status could not be calculated. No empty or healthy status was assumed; retry the report.', array( 'status' => 503 ) );
		}
		if ( ! is_array( $rows ) ) {
			return new WP_Error( 'reorder_read_failed', 'Live reorder status returned an unexpected result. No empty or healthy status was assumed; retry the report.', array( 'status' => 503 ) );
		}
		foreach ( $rows as &$row ) {
			$position       = (float) $row['reorder_position'];
			$level          = (float) $row['reorder_level'];
			$row['status']  = $level <= 0.0 ? 'NO_LEVEL' : ( $position <= 0.0 ? 'CRITICAL' : ( $position <= $level ? 'REORDER NOW' : 'OK' ) );
			$row['suggested_replenishment'] = $level > 0.0 && $position <= $level ? max( 1, (int) ceil( max( 0, $level - $position ) ) ) : 0;
		}
		return rest_ensure_response( $rows );
	}
}

// phpcs:enable WordPress.Files.FileName.InvalidClassFileName -- End the narrowly scoped legacy filename exception.
