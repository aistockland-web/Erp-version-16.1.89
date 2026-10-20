#!/usr/bin/env python3
"""Apply the audited, read-only Reports & Analytics explorer patch to ERP 16.1.92."""
from __future__ import annotations
import hashlib, json, zipfile
from pathlib import Path, PurePosixPath

TARGET = Path(__file__).resolve().parents[1]
BASELINE_SHA256 = "adec43b84f2b9a62d8913f84bad20588166800b2b6571d22d75abb86fcfd3fcd"
BASELINE_SIZE = 1_113_446

def replace_once(source: str, old: str, new: str, label: str) -> str:
    count = source.count(old)
    if count != 1:
        raise ValueError(f"{label}: expected exactly one anchor, got {count}: {old[:120]!r}")
    return source.replace(old, new, 1)

def patch_php(source: str) -> str:
    source = replace_once(source, """		register_rest_route(
			self::NAMESPACE_V1,
			'/app/global-search',""", """		register_rest_route(
			self::NAMESPACE_V1,
			'/app/reports/explorer',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'reports_explorer' ),
				'permission_callback' => function ( WP_REST_Request $request ) {
					if ( 'critical_reorder' === sanitize_key( (string) $request->get_param( 'type' ) ) && ! Stockland_Authority::is_owner() ) {
						return new WP_Error( 'reports_owner_only', 'Critical reorder reports are restricted to the Stockland Owner.', array( 'status' => 403 ) );
					}
					return current_user_can( 'stockland_view_branch_reports' ) || current_user_can( 'stockland_view_all_branches' ) || current_user_can( 'stockland_view_warehouse' );
				},
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/app/global-search',""", "explorer route")
    source = replace_once(source, """		$scope = $this->scope_location();
		if ( false === $scope ) {
			return new WP_Error( 'location_scope_unavailable', 'A valid assigned location is required for this report. Ask Management to correct your ERP location assignment.', array( 'status' => 403 ) );
		}
		$sales = Stockland_DB::table( 'sales' );""", """		$scope = current_user_can( 'stockland_view_all_branches' ) ? null : $this->scope_locations();
		if ( false === $scope ) {
			return new WP_Error( 'location_scope_unavailable', 'A valid assigned location is required for this report. Ask Management to correct your ERP location assignment.', array( 'status' => 403 ) );
		}
		$requested_location = trim( (string) $r->get_param( 'location_id' ) );
		if ( '' !== $requested_location && 'all' !== strtolower( $requested_location ) ) {
			if ( ! ctype_digit( $requested_location ) || (int) $requested_location < 1 ) {
				return new WP_Error( 'invalid_report_location', 'Choose All authorized locations or a valid location.', array( 'status' => 400 ) );
			}
			$selected_location = (int) $requested_location;
			if ( ! current_user_can( 'stockland_view_all_branches' ) && ! Stockland_Authority::can_access_location( $selected_location ) ) {
				return new WP_Error( 'forbidden_report_location', 'You are not authorized to report on that location.', array( 'status' => 403 ) );
			}
			$active_location = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE id=%d AND is_active=1 AND location_type IN (%s,%s)', Stockland_DB::table( 'locations' ), $selected_location, 'Branch', 'Warehouse' ) );
			if ( ! $active_location ) {
				return new WP_Error( 'invalid_report_location', 'The selected active branch or warehouse was not found.', array( 'status' => 400 ) );
			}
			$scope = array( $selected_location );
		}
		$sku = trim( sanitize_text_field( (string) $r->get_param( 'sku' ) ) );
		if ( strlen( $sku ) > 128 ) {
			return new WP_Error( 'invalid_report_sku', 'SKU search must be 128 characters or fewer.', array( 'status' => 400 ) );
		}
		$sales = Stockland_DB::table( 'sales' );""", "reports scope resolution")
    source = replace_once(source, """		$rt_scope = $scope ? ' AND branch_location_id=%d' : '';
		$rt_args  = array( $sales, $from, $to );
		if ( $scope ) {
			$rt_args[] = (int) $scope;
		}
		$rt_sql = "SELECT COALESCE(SUM(sales_total),0) sales,COALESCE(SUM(net_sales_excl_vat),0) net,
                    COALESCE(SUM(vat_amount),0) vat,COALESCE(SUM(gross_profit),0) profit,
                    COALESCE(SUM(quantity_sold),0) units
             FROM %i WHERE posting_status='CLOSED' AND sale_date BETWEEN %s AND %s" . $rt_scope;""", """		$rt_args = array( $sales, $from, $to );
		$rt_sql  = "SELECT COALESCE(SUM(sales_total),0) sales,COALESCE(SUM(net_sales_excl_vat),0) net,
                    COALESCE(SUM(vat_amount),0) vat,COALESCE(SUM(gross_profit),0) profit,
                    COALESCE(SUM(quantity_sold),0) units
             FROM %i WHERE posting_status='CLOSED' AND sale_date BETWEEN %s AND %s";
		$rt_sql .= $this->append_scope_filter( 'branch_location_id', $rt_args, $scope );
		if ( '' !== $sku ) {
			$rt_sql   .= ' AND sku=%s';
			$rt_args[] = $sku;
		}""", "sales totals scope and SKU")
    source = replace_once(source, """		$wt = $scope ? array(
			'sales'  => 0,
			'net'    => 0,
			'vat'    => 0,
			'profit' => 0,
			'units'  => 0,
		) : Stockland_DB::read_row(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(sales_value),0) sales,COALESCE(SUM(net_sales_excl_vat),0) net,
                    COALESCE(SUM(vat_amount),0) vat,COALESCE(SUM(gross_profit),0) profit,
                    COALESCE(SUM(quantity),0) units
             FROM %i WHERE sale_date BETWEEN %s AND %s',
				$wh,
				$from,
				$to
			),
			ARRAY_A
		);""", """		$wh_args = array( $wh, $from, $to );
		$wh_sql  = 'SELECT COALESCE(SUM(sales_value),0) sales,COALESCE(SUM(net_sales_excl_vat),0) net,
                    COALESCE(SUM(vat_amount),0) vat,COALESCE(SUM(gross_profit),0) profit,
                    COALESCE(SUM(quantity),0) units
             FROM %i WHERE sale_date BETWEEN %s AND %s';
		if ( '' !== $sku ) {
			$wh_sql   .= ' AND sku=%s';
			$wh_args[] = $sku;
		}
		$wt = null !== $scope ? array(
			'sales'  => 0,
			'net'    => 0,
			'vat'    => 0,
			'profit' => 0,
			'units'  => 0,
		) : Stockland_DB::read_row( $wpdb->prepare( $wh_sql, $wh_args ), ARRAY_A );""", "wholesale summary scope")
    source = replace_once(source, """		$branch_args = array( $loc, $sales, $from, $to );
		if ( $scope ) {
			$branch_sql   .= ' AND l.id=%d';
			$branch_args[] = (int) $scope;
		}""", """		$branch_args = array( $loc, $sales, $from, $to );
		if ( '' !== $sku ) {
			$branch_sql    = str_replace( 'AND s.sale_date BETWEEN %s AND %s', 'AND s.sale_date BETWEEN %s AND %s AND s.sku=%s', $branch_sql );
			$branch_args[] = $sku;
		}
		$branch_sql .= $this->append_scope_filter( 'l.id', $branch_args, $scope );""", "branch scope and SKU")
    source = replace_once(source, """		$top_args = array( $sales, $prod, $from, $to );
		if ( $scope ) {
			$top_sql   .= ' AND s.branch_location_id=%d';
			$top_args[] = (int) $scope;
		}
		$top_sql .= ' GROUP BY s.sku,p.product_name ORDER BY units DESC LIMIT 10';""", """		$top_args = array( $sales, $prod, $from, $to );
		$top_sql .= $this->append_scope_filter( 's.branch_location_id', $top_args, $scope );
		if ( '' !== $sku ) {
			$top_sql   .= ' AND s.sku=%s';
			$top_args[] = $sku;
		}
		$top_sql .= ' GROUP BY s.sku,p.product_name ORDER BY units DESC LIMIT 10';""", "top products scope and SKU")
    source = replace_once(source, """		$returns_args = array( $ret, $from, $to );
		if ( $scope ) {
			$returns_sql   .= ' AND location_id=%d';
			$returns_args[] = (int) $scope;
		}""", """		$returns_args = array( $ret, $from, $to );
		$returns_sql .= $this->append_scope_filter( 'location_id', $returns_args, $scope );
		if ( '' !== $sku ) {
			$returns_sql   .= ' AND sku=%s';
			$returns_args[] = $sku;
		}""", "returns scope and SKU")
    source = replace_once(source, """		$stock_args = array( $prod, $loc, $tx, $res );
		if ( $scope ) {
			$stock_sql   .= ' AND l.id=%d';
			$stock_args[] = (int) $scope;
		}
		$stock_sql .= ' ORDER BY l.location_name,p.sku';""", """		$stock_args = array( $prod, $loc, $tx, $res );
		$stock_sql .= $this->append_scope_filter( 'l.id', $stock_args, $scope );
		if ( '' !== $sku ) {
			$stock_sql   .= ' AND p.sku=%s';
			$stock_args[] = $sku;
		}
		$stock_sql .= ' ORDER BY l.location_name,p.sku';""", "inventory scope and SKU")
    source = replace_once(source, """		$targs         = array( $tr, $prod, $loc, $loc, $from, $to );
		if ( $scope ) {
			$transfers_sql .= ' AND (t.from_location_id=%d OR t.to_location_id=%d)';
			$targs[]        = (int) $scope;
			$targs[]        = (int) $scope;
		}
		$transfers_sql .= ' ORDER BY t.created_at DESC LIMIT 500';""", """		$targs = array( $tr, $prod, $loc, $loc, $from, $to );
		if ( '' !== $sku ) {
			$transfers_sql .= ' AND t.sku=%s';
			$targs[]        = $sku;
		}
		if ( null !== $scope ) {
			$scope_ids      = array_map( 'intval', (array) $scope );
			$scope_ph       = implode( ',', array_fill( 0, count( $scope_ids ), '%d' ) );
			$transfers_sql .= ' AND (t.from_location_id IN (' . $scope_ph . ') OR t.to_location_id IN (' . $scope_ph . '))';
			$targs          = array_merge( $targs, $scope_ids, $scope_ids );
		}
		$transfers_sql .= ' ORDER BY t.created_at DESC LIMIT 500';""", "transfer scope and SKU")
    source = replace_once(source, """		$people_args = array( $sales, $loc, $wpdb->users, $from, $to );
		if ( $scope ) {
			$people_sql   .= ' AND s.branch_location_id=%d';
			$people_args[] = (int) $scope;
		}
		$people_sql .= ' GROUP BY l.id,s.staff_user_id,u.display_name ORDER BY revenue DESC LIMIT 200';""", """		$people_args = array( $sales, $loc, $wpdb->users, $from, $to );
		$people_sql .= $this->append_scope_filter( 's.branch_location_id', $people_args, $scope );
		if ( '' !== $sku ) {
			$people_sql   .= ' AND s.sku=%s';
			$people_args[] = $sku;
		}
		$people_sql .= ' GROUP BY l.id,s.staff_user_id,u.display_name ORDER BY revenue DESC LIMIT 200';""", "people scope and SKU")
    source = replace_once(source, """		$variance_args = array( $tr, $prod, $loc, $loc, $from, $to );
		if ( $scope ) {
			$variance_sql   .= ' AND (t.from_location_id=%d OR t.to_location_id=%d)';
			$variance_args[] = (int) $scope;
			$variance_args[] = (int) $scope;
		}
		$variance_sql .= ' ORDER BY t.received_at DESC LIMIT 500';""", """		$variance_args = array( $tr, $prod, $loc, $loc, $from, $to );
		if ( '' !== $sku ) {
			$variance_sql   .= ' AND t.sku=%s';
			$variance_args[] = $sku;
		}
		if ( null !== $scope ) {
			$scope_ids       = array_map( 'intval', (array) $scope );
			$scope_ph        = implode( ',', array_fill( 0, count( $scope_ids ), '%d' ) );
			$variance_sql   .= ' AND (t.from_location_id IN (' . $scope_ph . ') OR t.to_location_id IN (' . $scope_ph . '))';
			$variance_args   = array_merge( $variance_args, $scope_ids, $scope_ids );
		}
		$variance_sql .= ' ORDER BY t.received_at DESC LIMIT 500';""", "transfer variance scope and SKU")
    source = replace_once(source, """		$cargs     = array( $cb, $loc, $cl, $from, $to );
		if ( $scope ) {
			$count_sql .= ' AND b.location_id=%d';
			$cargs[]    = (int) $scope;
		}
		$count_sql .= ' GROUP BY b.id ORDER BY b.created_at DESC LIMIT 200';""", """		$cargs = array( $cb, $loc, $cl, $from, $to );
		$count_sql .= $this->append_scope_filter( 'b.location_id', $cargs, $scope );
		$count_sql .= ' GROUP BY b.id ORDER BY b.created_at DESC LIMIT 200';""", "physical count scope")

    method = r'''
	/**
	 * Returns one selected read-only report with location/SKU scope enforced by reports_summary().
	 *
	 * @param WP_REST_Request $r Report filters.
	 * @return mixed Selected report or REST error.
	 */
	public function reports_explorer( WP_REST_Request $r ) {
		$type = sanitize_key( (string) $r->get_param( 'type' ) );
		if ( '' === $type ) { $type = 'branch_performance'; }
		$titles = array(
			'branch_performance' => 'Branch performance',
			'sales_performance' => 'Sales performance',
			'product_sku' => 'Product / SKU performance',
			'inventory_availability' => 'Inventory availability',
			'critical_reorder' => 'Critical reorder (Owner only)',
			'transfer_status' => 'Receiving / transfer status',
			'transfer_variance' => 'Transfer variance',
			'physical_count_variance' => 'Physical-count variance',
			'returns_adjustments' => 'Returns & adjustments summary',
		);
		if ( ! isset( $titles[ $type ] ) ) {
			return new WP_Error( 'invalid_report_type', 'Choose a supported Reports & Analytics report type.', array( 'status' => 400 ) );
		}
		if ( 'critical_reorder' === $type && ! Stockland_Authority::is_owner() ) {
			return new WP_Error( 'reports_owner_only', 'Critical reorder reports are restricted to the Stockland Owner.', array( 'status' => 403 ) );
		}
		$sku = trim( sanitize_text_field( (string) $r->get_param( 'sku' ) ) );
		if ( '' !== $sku && ! in_array( $type, array( 'product_sku', 'inventory_availability', 'critical_reorder', 'transfer_status', 'transfer_variance' ), true ) ) {
			return new WP_Error( 'sku_filter_not_supported', 'SKU filtering is available for product, inventory, reorder and transfer reports.', array( 'status' => 400 ) );
		}
		$summary = $this->reports_summary( $r );
		if ( is_wp_error( $summary ) ) { return $summary; }
		$data = $summary instanceof WP_REST_Response ? $summary->get_data() : (array) $summary;
		if ( ! is_array( $data ) || ! isset( $data['totals'] ) ) {
			return new WP_Error( 'report_data_unavailable', 'The selected report did not return a valid summary.', array( 'status' => 500 ) );
		}
		$can_cost = current_user_can( 'stockland_view_cost_and_profit' );
		$columns = array();
		$rows = array();
		$description = 'Read-only report generated from posted ERP records. No inventory, accounting or approval workflow is changed.';
		switch ( $type ) {
			case 'branch_performance':
				$columns = array(
					array( 'key' => 'location_name', 'label' => 'Branch' ),
					array( 'key' => 'retail_units', 'label' => 'Units sold', 'format' => 'number' ),
					array( 'key' => 'retail_sales', 'label' => 'Retail sales', 'format' => 'money' ),
				);
				if ( $can_cost ) { $columns[] = array( 'key' => 'retail_profit', 'label' => 'Gross profit', 'format' => 'money' ); }
				$rows = is_array( $data['branches'] ?? null ) ? $data['branches'] : array();
				break;
			case 'sales_performance':
				$rows = array(
					array( 'metric' => 'Gross sales', 'value' => (float) ( $data['totals']['sales'] ?? 0 ), 'format' => 'money' ),
					array( 'metric' => 'Net sales (excluding VAT)', 'value' => (float) ( $data['totals']['net'] ?? 0 ), 'format' => 'money' ),
					array( 'metric' => 'Recorded VAT', 'value' => (float) ( $data['totals']['vat'] ?? 0 ), 'format' => 'money' ),
					array( 'metric' => 'Units sold', 'value' => (float) ( $data['totals']['units'] ?? 0 ), 'format' => 'number' ),
					array( 'metric' => 'Return units', 'value' => (float) ( $data['returns']['units'] ?? 0 ), 'format' => 'number' ),
					array( 'metric' => 'Return records', 'value' => (float) ( $data['returns']['rows'] ?? 0 ), 'format' => 'number' ),
				);
				if ( $can_cost ) { $rows[] = array( 'metric' => 'Gross profit', 'value' => (float) ( $data['totals']['profit'] ?? 0 ), 'format' => 'money' ); }
				$columns = array( array( 'key' => 'metric', 'label' => 'Metric' ), array( 'key' => 'value', 'label' => 'Value', 'format' => 'metric' ) );
				break;
			case 'product_sku':
				$columns = array(
					array( 'key' => 'sku', 'label' => 'SKU' ),
					array( 'key' => 'product_name', 'label' => 'Product' ),
					array( 'key' => 'units', 'label' => 'Units sold', 'format' => 'number' ),
					array( 'key' => 'sales', 'label' => 'Sales', 'format' => 'money' ),
				);
				if ( $can_cost ) { $columns[] = array( 'key' => 'profit', 'label' => 'Gross profit', 'format' => 'money' ); }
				$rows = is_array( $data['top_products'] ?? null ) ? $data['top_products'] : array();
				break;
			case 'inventory_availability':
			case 'critical_reorder':
				$columns = array(
					array( 'key' => 'location_name', 'label' => 'Location' ),
					array( 'key' => 'sku', 'label' => 'SKU' ),
					array( 'key' => 'product_name', 'label' => 'Product' ),
					array( 'key' => 'brand', 'label' => 'Brand' ),
					array( 'key' => 'size', 'label' => 'Size' ),
					array( 'key' => 'color', 'label' => 'Color' ),
					array( 'key' => 'physical_stock', 'label' => 'Physical stock', 'format' => 'number' ),
					array( 'key' => 'reserved_stock', 'label' => 'Reserved', 'format' => 'number' ),
					array( 'key' => 'available_stock', 'label' => 'Available', 'format' => 'number' ),
					array( 'key' => 'reorder_level', 'label' => 'Reorder level', 'format' => 'number' ),
				);
				$rows = is_array( $data['stock'] ?? null ) ? $data['stock'] : array();
				if ( 'critical_reorder' === $type ) {
					$rows = array_values( array_filter( $rows, static function ( $row ) { return (float) ( $row['reorder_level'] ?? 0 ) > 0 && (float) ( $row['available_stock'] ?? 0 ) <= (float) ( $row['reorder_level'] ?? 0 ); } ) );
					foreach ( $rows as &$row ) { $row['suggested_replenishment'] = max( 0, (float) ( $row['reorder_level'] ?? 0 ) - (float) ( $row['available_stock'] ?? 0 ) ); }
					unset( $row );
					$columns[] = array( 'key' => 'suggested_replenishment', 'label' => 'Suggested replenishment', 'format' => 'number' );
					$description = 'Owner-only read-only list. Available stock equals physical stock less active reservations; suggested replenishment is the minimum quantity needed to reach the configured reorder level. No stock is ordered or changed.';
				}
				break;
			case 'transfer_status':
				$columns = array(
					array( 'key' => 'transfer_id', 'label' => 'Transfer' ), array( 'key' => 'transfer_date', 'label' => 'Date' ),
					array( 'key' => 'from_location_name', 'label' => 'From' ), array( 'key' => 'to_location_name', 'label' => 'To' ),
					array( 'key' => 'sku', 'label' => 'SKU' ), array( 'key' => 'product_name', 'label' => 'Product' ),
					array( 'key' => 'quantity', 'label' => 'Requested qty', 'format' => 'number' ),
					array( 'key' => 'dispatched_qty', 'label' => 'Sent qty', 'format' => 'number' ),
					array( 'key' => 'received_qty', 'label' => 'Received qty', 'format' => 'number' ),
					array( 'key' => 'status', 'label' => 'Status' ),
				);
				$rows = is_array( $data['transfers'] ?? null ) ? $data['transfers'] : array();
				break;
			case 'transfer_variance':
				$columns = array(
					array( 'key' => 'transfer_id', 'label' => 'Transfer' ), array( 'key' => 'transfer_date', 'label' => 'Date' ),
					array( 'key' => 'sku', 'label' => 'SKU' ), array( 'key' => 'product_name', 'label' => 'Product' ),
					array( 'key' => 'from_location_name', 'label' => 'From' ), array( 'key' => 'to_location_name', 'label' => 'To' ),
					array( 'key' => 'dispatched_qty', 'label' => 'Sent qty', 'format' => 'number' ),
					array( 'key' => 'received_qty', 'label' => 'Received qty', 'format' => 'number' ),
					array( 'key' => 'variance_qty', 'label' => 'Variance', 'format' => 'number' ),
				);
				$rows = is_array( $data['variances'] ?? null ) ? $data['variances'] : array();
				break;
			case 'physical_count_variance':
				$columns = array(
					array( 'key' => 'batch_id', 'label' => 'Count batch' ), array( 'key' => 'count_date', 'label' => 'Date' ),
					array( 'key' => 'location_name', 'label' => 'Location' ), array( 'key' => 'status', 'label' => 'Status' ),
					array( 'key' => 'lines', 'label' => 'Lines', 'format' => 'number' ),
					array( 'key' => 'variance_lines', 'label' => 'Variance lines', 'format' => 'number' ),
					array( 'key' => 'counted_qty', 'label' => 'Counted units', 'format' => 'number' ),
					array( 'key' => 'system_qty', 'label' => 'System units', 'format' => 'number' ),
					array( 'key' => 'variance_qty', 'label' => 'Variance units', 'format' => 'number' ),
				);
				$rows = is_array( $data['physical_counts'] ?? null ) ? $data['physical_counts'] : array();
				$rows = array_values( array_filter( $rows, static function ( $row ) { return abs( (float) ( $row['variance_qty'] ?? 0 ) ) > 0.00001 || (int) ( $row['variance_lines'] ?? 0 ) > 0; } ) );
				break;
			case 'returns_adjustments':
				$columns = array( array( 'key' => 'metric', 'label' => 'Metric' ), array( 'key' => 'value', 'label' => 'Value', 'format' => 'number' ) );
				$rows = array(
					array( 'metric' => 'Return / adjustment units', 'value' => (float) ( $data['returns']['units'] ?? 0 ) ),
					array( 'metric' => 'Return / adjustment records', 'value' => (float) ( $data['returns']['rows'] ?? 0 ) ),
				);
				break;
		}
		$location_request = trim( (string) $r->get_param( 'location_id' ) );
		$location_label = 'All authorized locations';
		if ( '' !== $location_request && 'all' !== strtolower( $location_request ) ) {
			global $wpdb;
			$location_label = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT location_name FROM %i WHERE id=%d AND is_active=1', Stockland_DB::table( 'locations' ), (int) $location_request ) );
		}
		if ( '' === $location_label ) { $location_label = 'Authorized location'; }
		return rest_ensure_response( array(
			'type' => $type, 'title' => $titles[$type], 'description' => $description,
			'from' => (string) $data['from'], 'to' => (string) $data['to'],
			'location_label' => $location_label, 'generated_at' => current_time( 'mysql' ),
			'can_cost' => (bool) $can_cost, 'columns' => $columns, 'rows' => array_values( $rows ),
		) );
	}
'''
    source = replace_once(source, "\n\t/**\n\t * Builds a date-scoped summary of operational transactions.\n", "\n" + method + "\n\t/**\n\t * Builds a date-scoped summary of operational transactions.\n", "add reports_explorer method")
    return source

def patch_js(source: str) -> str:
    start = source.find("function reports(){")
    end = source.find("function title(", start)
    if start < 0 or end < 0 or source.count("function reports(){") != 1:
        raise ValueError("Could not isolate Reports & Analytics UI block.")
    ui = r'''function reportDateRange(period){
  const parts=localDate().split('-').map(Number);const today=new Date(parts[0],parts[1]-1,parts[2]);const from=new Date(today);const to=new Date(today);
  if(period==='yesterday')from.setDate(from.getDate()-1),to.setDate(to.getDate()-1);
  else if(period==='last7')from.setDate(from.getDate()-6);
  else if(period==='this_month')from.setDate(1);
  const fmt=d=>d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');return{from:fmt(from),to:fmt(to)};
}
function reportUsesSku(type){return['product_sku','inventory_availability','critical_reorder','transfer_status','transfer_variance'].includes(type)}
function reports(){
  const user=state.boot?.user||{};const canDailyImport=!!user.capabilities?.daily_import;const owner=user.role==='owner';
  const actions='<button type="button" class="sl-btn" id="printOpsReport" disabled>PRINT / SAVE PDF</button><button type="button" class="sl-btn primary" id="downloadOpsReportCsv" disabled>DOWNLOAD SELECTED CSV</button>'+(canDailyImport?'<button type="button" class="sl-btn" id="openDailyPosImportFromReport">DAILY POS CSV IMPORT</button>':'');
  const types=[['branch_performance','Branch performance'],['sales_performance','Sales performance'],['product_sku','Product / SKU performance'],['inventory_availability','Inventory availability'],...(owner?[['critical_reorder','Critical reorder (Owner only)']]:[]),['transfer_status','Receiving / transfer status'],['transfer_variance','Transfer variance'],['physical_count_variance','Physical-count variance'],['returns_adjustments','Returns & adjustments summary']];
  const typeOptions=types.map(([v,l])=>'<option value="'+esc(v)+'" '+(v==='branch_performance'?'selected':'')+'>'+esc(l)+'</option>').join('');
  const locations=(state.boot?.locations||[]).filter(l=>Number(l.is_active)!==0&&['Branch','Warehouse'].includes(String(l.location_type||'')));
  const locationOptions='<option value="all" selected>All authorized locations</option>'+locations.map(l=>'<option value="'+esc(String(l.id))+'">'+esc(l.location_name||('Location '+l.id))+' — '+esc(l.location_type||'')+'</option>').join('');
  return title('Reports & Analytics','Select one report and print or save it as PDF. Server-side permissions and authorized location scope remain in force.',actions)+
    '<div class="sl-panel sl-report-panel"><div class="sl-block-head"><div><div class="sl-eyebrow">REPORT FILTER</div><h3>Build a report</h3></div><small>Read-only • live ERP records • authorized scope</small></div><form id="reportForm" class="sl-form sl-report-filter">'+
    '<div class="sl-field"><label for="reportType">Report type</label><select id="reportType" name="type">'+typeOptions+'</select></div>'+
    '<div class="sl-field"><label for="reportPeriod">Period</label><select id="reportPeriod" name="period"><option value="today" selected>Today</option><option value="yesterday">Yesterday</option><option value="last7">Last 7 days</option><option value="this_month">This month</option><option value="custom">Custom range</option></select></div>'+
    '<div class="sl-field"><label for="reportLocation">Location</label><select id="reportLocation" name="location_id">'+locationOptions+'</select></div>'+
    '<div class="sl-field" id="reportSkuField" hidden><label for="reportSku">SKU / product / barcode</label><input id="reportSku" name="sku" type="search" value="" placeholder="Search SKU, product or barcode…" autocomplete="off" aria-autocomplete="list" aria-controls="reportSkuSuggestions"><div id="reportSkuSuggestions" class="sl-sku-results" role="listbox" hidden></div><small class="sl-sub">Choose a Product Master match to filter by its exact SKU.</small></div>'+
    '<div id="reportCustomDates" class="sl-field full" hidden><div class="sl-form"><div class="sl-field"><label for="reportFrom">From</label><input id="reportFrom" name="from" type="date" value="'+esc(localDate())+'"></div><div class="sl-field"><label for="reportTo">To</label><input id="reportTo" name="to" type="date" value="'+esc(localDate())+'"></div></div></div>'+
    '<div class="sl-field full"><button type="submit" class="sl-btn primary" id="runOpsReport">RUN REPORT</button></div></form><div class="sl-sub">Today uses the ERP site-local date. Critical reorder is Owner-only on both the interface and server. PDF is produced through the browser print dialog.</div></div>'+
    '<div id="reportResult"><div class="sl-empty">Loading branch performance for today…</div></div><div id="reportRowDetail" class="sl-panel" hidden></div>';
}
function reportDisplayValue(value,format){
  if(value===null||value===undefined||value==='')return '—';
  if(format==='money')return money(Number(value)||0);
  if(format==='number'){const n=Number(value);return Number.isFinite(n)?String(n):esc(String(value));}
  return esc(String(value));
}
function renderSelectedReport(report){
  const el=document.getElementById('reportResult');if(!el)return;
  const cols=Array.isArray(report?.columns)?report.columns:[];const rows=Array.isArray(report?.rows)?report.rows:[];const critical=report?.type==='critical_reorder';
  const heading='<div class="sl-block-head"><div><div class="sl-eyebrow">STOCKLAND ETHIOPIA • REPORT</div><h3>'+esc(report?.title||'Selected report')+'</h3><small>'+esc(report?.from||'')+' to '+esc(report?.to||'')+' • '+esc(report?.location_label||'All authorized locations')+'</small></div><small>Generated '+esc(report?.generated_at||'')+'</small></div>';
  const headers=cols.map(c=>'<th>'+esc(c.label||c.key||'')+'</th>').join('')+(critical?'<th data-report-action-column>Details</th>':'');
  const body=rows.map((row,index)=>'<tr>'+cols.map(c=>'<td data-label="'+esc(c.label||c.key||'')+'">'+reportDisplayValue(row?.[c.key],c.format==='metric'?row?.format:c.format)+'</td>').join('')+(critical?'<td data-label="Details" data-report-action-column><button type="button" class="sl-btn" data-report-details="'+index+'">DETAILS</button></td>':'')+'</tr>').join('')||'<tr><td colspan="'+Math.max(1,cols.length+(critical?1:0))+'">No records matched this report and filter.</td></tr>';
  el.innerHTML='<div class="sl-panel" id="selectedOpsReport" data-report-type="'+esc(report?.type||'')+'">'+heading+'<div class="sl-sub">'+esc(report?.description||'Read-only report from authorized ERP records.')+'</div><div class="sl-table-wrap"><table class="sl-table sl-responsive-cards"><thead><tr>'+headers+'</tr></thead><tbody>'+body+'</tbody></table></div><div class="sl-sub">Rows: '+String(rows.length)+'</div></div>';
  document.querySelectorAll('[data-report-details]').forEach(button=>{button.onclick=()=>{const record=rows[Number(button.dataset.reportDetails)];const detail=document.getElementById('reportRowDetail');if(!record||!detail)return;detail.hidden=false;detail.innerHTML='<div class="sl-block-head"><div><div class="sl-eyebrow">REORDER ROW DETAIL</div><h3>'+esc(record.product_name||record.sku||'Stock item')+'</h3></div><button type="button" class="sl-btn" id="closeReportRowDetail">CLOSE</button></div><div class="sl-table-wrap"><table class="sl-table"><tbody>'+cols.map(c=>'<tr><th>'+esc(c.label||c.key||'')+'</th><td>'+reportDisplayValue(record[c.key],c.format)+'</td></tr>').join('')+'</tbody></table></div><div class="sl-notice">Suggested replenishment is a read-only calculation; no purchase order, reservation or stock movement was created.</div>';document.getElementById('closeReportRowDetail')?.addEventListener('click',()=>{detail.hidden=true;detail.innerHTML=''});detail.scrollIntoView({behavior:'smooth',block:'start'});}});
}
function buildSelectedReportCsv(report){
  const cols=Array.isArray(report?.columns)?report.columns:[];const rows=Array.isArray(report?.rows)?report.rows:[];
  const data=[['Stockland Ethiopia ERP — '+String(report?.title||'Selected report')],['From',report?.from||''],['To',report?.to||''],['Location',report?.location_label||'All authorized locations'],['Generated',report?.generated_at||''],[],cols.map(c=>c.label||c.key||'')];
  rows.forEach(row=>data.push(cols.map(c=>row?.[c.key]??'')));
  return '\uFEFF'+data.map(row=>row.map(reportCsvField).join(',')).join('\r\n')+'\r\n';
}
function downloadSelectedReportCsv(){const report=state.report;if(!report||!Array.isArray(report.columns)){toast('Run a report before downloading CSV.',true);return}const blob=new Blob([buildSelectedReportCsv(report)],{type:'text/csv;charset=utf-8;'});const url=URL.createObjectURL(blob);const link=document.createElement('a');link.href=url;link.download='stockland-'+String(report.type||'report')+'-'+String(report.from||'start')+'-to-'+String(report.to||'end')+'.csv';link.style.display='none';document.body.appendChild(link);link.click();link.remove();window.setTimeout(()=>URL.revokeObjectURL(url),1000)}
async function loadReport(){
  const el=document.getElementById('reportResult');if(!el)return;const form=document.getElementById('reportForm');if(!form)return;
  const type=form.elements.type.value;const period=form.elements.period.value;let from,to;
  if(period==='custom'){from=String(form.elements.from.value||'');to=String(form.elements.to.value||'');}else({from,to}=reportDateRange(period));
  if(!/^\d{4}-\d{2}-\d{2}$/.test(from)||!/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/.test(to)||from>to)throw new Error('Choose a valid date range. From must be on or before To.');
  if(period==='custom'){const validLocalDate=value=>{const p=value.split('-').map(Number);const d=new Date(p[0],p[1]-1,p[2]);return d.getFullYear()===p[0]&&d.getMonth()===p[1]-1&&d.getDate()===p[2]};if(!validLocalDate(from)||!validLocalDate(to))throw new Error('Choose valid calendar dates.');}
  const run=document.getElementById('runOpsReport');if(run){run.disabled=true;run.setAttribute('aria-busy','true');run.textContent='LOADING…';}
  const printButton=document.getElementById('printOpsReport');const csvButton=document.getElementById('downloadOpsReportCsv');if(printButton)printButton.disabled=true;if(csvButton)csvButton.disabled=true;
  const detail=document.getElementById('reportRowDetail');if(detail){detail.hidden=true;detail.innerHTML=''}el.innerHTML='<div class="sl-empty">Loading the selected authorized report…</div>';
  try{
    const params=new URLSearchParams({type,from,to,location_id:String(form.elements.location_id.value||'all')});const sku=reportUsesSku(type)?String(form.elements.sku?.value||'').trim():'';if(sku)params.set('sku',sku);
    const report=await api('/app/reports/explorer?'+params.toString());state.report=report||{};renderSelectedReport(state.report);
    if(printButton)printButton.disabled=!(report&&Array.isArray(report.columns));if(csvButton)csvButton.disabled=!(report&&Array.isArray(report.columns));
  }catch(e){state.report=null;el.innerHTML='<div class="sl-notice sl-danger-block"><b>Report could not load.</b> '+esc(e.message)+'<div class="sl-actions" style="margin-top:10px"><button type="button" class="sl-btn" id="retryOpsReport">RETRY REPORT</button></div></div>';document.getElementById('retryOpsReport')?.addEventListener('click',()=>loadReport().catch(err=>toast(err.message,true)));throw e;
  }finally{if(run){run.disabled=false;run.removeAttribute('aria-busy');run.textContent='RUN REPORT';}}
}
function bindReportExplorer(){
  const $=selector=>document.querySelector(selector);const form=$('#reportForm');if(!form)return;
  const type=form.elements.type;const period=form.elements.period;const sku=form.elements.sku;const skuField=$('#reportSkuField');const suggestionBox=$('#reportSkuSuggestions');const customDates=$('#reportCustomDates');
  const syncFilters=()=>{const usesSku=reportUsesSku(type.value);if(skuField)skuField.hidden=!usesSku;if(!usesSku&&sku)sku.value='';if(!usesSku&&suggestionBox){suggestionBox.hidden=true;suggestionBox.innerHTML='';}if(customDates)customDates.hidden=period.value!=='custom';};
  const updatePresetDates=()=>{if(period.value==='custom')return;const range=reportDateRange(period.value);if(form.elements.from)form.elements.from.value=range.from;if(form.elements.to)form.elements.to.value=range.to;};
  type.addEventListener('change',syncFilters);period.addEventListener('change',()=>{syncFilters();updatePresetDates()});syncFilters();updatePresetDates();
  form.onsubmit=async e=>{e.preventDefault();try{await loadReport()}catch(err){toast(err.message,true)}};
  let timer=null;let querySequence=0;
  if(sku&&suggestionBox){sku.addEventListener('input',()=>{if(timer)window.clearTimeout(timer);const q=sku.value.trim();const seq=++querySequence;if(q.length<1){suggestionBox.hidden=true;suggestionBox.innerHTML='';return}timer=window.setTimeout(async()=>{try{const found=await api16('/sku-search?q='+encodeURIComponent(q)+'&limit=8');if(seq!==querySequence||!Array.isArray(found))return;const results=found.slice(0,8);suggestionBox.innerHTML=results.map(x=>'<button type="button" class="sl-btn" role="option" data-report-sku="'+esc(String(x.sku||''))+'"><b>'+esc(String(x.sku||''))+'</b> · '+esc(String(x.product_name||''))+' '+esc(String(x.color||''))+' '+esc(String(x.size||''))+' <small>'+esc(String(x.barcode||''))+'</small></button>').join('')||'<div class="sl-empty">No matching Product Master item.</div>';suggestionBox.hidden=false;suggestionBox.querySelectorAll('[data-report-sku]').forEach(button=>button.addEventListener('click',()=>{sku.value=button.dataset.reportSku||'';suggestionBox.hidden=true;suggestionBox.innerHTML='';sku.focus()}));}catch(e){if(seq===querySequence){suggestionBox.hidden=true;suggestionBox.innerHTML='';}}},250)});sku.addEventListener('keydown',e=>{if(e.key==='Escape'&&suggestionBox){suggestionBox.hidden=true;suggestionBox.innerHTML=''}});if(!document.__slReportSkuDismissBound){document.__slReportSkuDismissBound=true;document.addEventListener('click',e=>{const liveBox=document.getElementById('reportSkuSuggestions');if(!e.target.closest('#reportSkuField')&&liveBox){liveBox.hidden=true}});}}
  $('#downloadOpsReportCsv')?.addEventListener('click',downloadSelectedReportCsv);
  $('#printOpsReport')?.addEventListener('click',()=>{if(!document.getElementById('selectedOpsReport')||!state.report){toast('Run a report before printing.',true);return}const filterPanel=document.querySelector('#stockland-app .sl-report-panel');const detail=document.getElementById('reportRowDetail');const previousDisplay=filterPanel?filterPanel.style.display:'';const actions=[...document.querySelectorAll('#selectedOpsReport [data-report-action-column]')].map(node=>({node,display:node.style.display}));if(filterPanel)filterPanel.style.display='none';if(detail)detail.style.display='none';actions.forEach(x=>x.node.style.display='none');const restore=()=>{if(filterPanel)filterPanel.style.display=previousDisplay;if(detail)detail.style.display='';actions.forEach(x=>x.node.style.display=x.display);window.removeEventListener('afterprint',restore)};window.addEventListener('afterprint',restore);window.print();});
  $('#openDailyPosImportFromReport')?.addEventListener('click',()=>{state.view='daily';render()});loadReport().catch(()=>{});
}
'''
    source = source[:start] + ui + source[end:]
    source = replace_once(source, """if($('#reportForm')){const rf=$('#reportForm');rf.onsubmit=async e=>{e.preventDefault();const d=Object.fromEntries(new FormData(e.currentTarget));try{await loadReport(d.from,d.to)}catch(err){toast(err.message,true)}};loadReport(rf.elements.from.value,rf.elements.to.value).catch(()=>{});}
document.querySelectorAll('[data-report-preset]').forEach(b=>{b.onclick=()=>{const range=reportPresetRange(b.dataset.reportPreset);const f=$('#reportForm');if(f){f.elements.from.value=range.from;f.elements.to.value=range.to;}loadReport(range.from,range.to).catch(err=>toast(err.message,true));}});
if($('#downloadOpsReportCsv'))$('#downloadOpsReportCsv').onclick=downloadPeriodSummaryCsv;
if($('#openDailyPosImportFromReport'))$('#openDailyPosImportFromReport').onclick=()=>{state.view='daily';render()};""", "bindReportExplorer();", "report form binding")
    source = replace_once(source, "if($('#printOpsReport'))$('#printOpsReport').onclick=()=>{if(!document.getElementById('reportResult')?.querySelector('.sl-panel')){toast('Generate a report first',true);return}window.print()};", "", "old print binding")
    # Defensive capture-phase delegation keeps the primary Product Master create
    # action working if desktop host scripts replace a node-level onclick handler
    # after rendering. The listener is registered once and calls the same editor
    # function as the existing handler; it does not bypass API validation/permissions.
    product_click_guard = """if(!document.__slNewProductCaptureBound){
  document.__slNewProductCaptureBound=true;
  document.addEventListener('click',function(e){
    const target=e.target instanceof Element?e.target.closest('#newProduct'):null;
    const appRoot=document.getElementById('stockland-app');
    if(!target||!appRoot||!appRoot.contains(target)||target.disabled)return;
    e.preventDefault();e.stopImmediatePropagation();openProductEditor(null);
  },true);
}"""
    source = replace_once(source, "if($('#newProduct'))$('#newProduct').onclick=()=>openProductEditor(null);", product_click_guard + "\\nif($('#newProduct'))$('#newProduct').onclick=()=>openProductEditor(null);", "desktop product create capture delegation")
    return source

def apply_to_installer(input_zip: Path, output_zip: Path) -> dict:
    raw = input_zip.read_bytes()
    digest = hashlib.sha256(raw).hexdigest()
    if digest != BASELINE_SHA256 or len(raw) != BASELINE_SIZE:
        raise ValueError(f"Baseline installer mismatch: bytes={len(raw)} sha256={digest}")
    with zipfile.ZipFile(input_zip) as zin:
        if zin.testzip():
            raise ValueError("Baseline ZIP CRC failure")
        infos = [i for i in zin.infolist() if not i.is_dir()]
        names = [i.filename for i in infos]
        if len(names) != 75 or len(set(names)) != 75 or not all(PurePosixPath(n).parts[0] == "stockland-erp-app" and ".." not in PurePosixPath(n).parts for n in names):
            raise ValueError("Unexpected baseline ZIP inventory/root/path")
        files = {name: zin.read(name) for name in names}
    js_path = "stockland-erp-app/assets/app.js"
    php_path = "stockland-erp-app/includes/rest/class-rest-app.php"
    css_paths = [n for n in names if (n.startswith("stockland-erp-app/assets/css/") and n.endswith(".css")) or n in ("stockland-erp-app/assets/app.css", "stockland-erp-app/assets/app.min.css", "stockland-erp-app/assets/bootstrap.min.css")]
    css_before = {n: hashlib.sha256(files[n]).hexdigest() for n in css_paths}
    files[js_path] = patch_js(files[js_path].decode("utf-8")).encode("utf-8")
    files[php_path] = patch_php(files[php_path].decode("utf-8")).encode("utf-8")
    entry_path = "stockland-erp-app/stockland-inventory.php"
    entry = files[entry_path].decode("utf-8")
    entry = replace_once(entry, "* Version:           16.1.92", "* Version:           16.1.93", "plugin header version")
    entry = replace_once(entry, "define( 'STOCKLAND_VERSION', '16.1.92' );", "define( 'STOCKLAND_VERSION', '16.1.93' );", "plugin constant version")
    files[entry_path] = entry.encode("utf-8")
    frontend_path = "stockland-erp-app/includes/class-stockland-frontend.php"
    frontend = files[frontend_path].decode("utf-8")
    frontend = replace_once(frontend, "const ROUTE_VERSION      = '16.1.92';", "const ROUTE_VERSION      = '16.1.93';", "frontend route version")
    files[frontend_path] = frontend.encode("utf-8")
    css_manifest_path = "stockland-erp-app/assets/css/modules.json"
    css_manifest = json.loads(files[css_manifest_path].decode("utf-8"))
    if css_manifest.get("release") != "16.1.92" or len(css_manifest.get("modules", [])) != 6:
        raise ValueError("CSS release metadata mismatch")
    css_manifest["release"] = "16.1.93"
    css_manifest["description"] = "16.1.93 cache/release metadata only; six approved CSS source modules and compiled CSS remain byte-identical."
    files[css_manifest_path] = (json.dumps(css_manifest, ensure_ascii=False, indent=2) + "\n").encode("utf-8")
    manifest_path = "stockland-erp-app/RELEASE_MANIFEST.json"
    manifest = json.loads(files[manifest_path].decode("utf-8"))
    manifest["release"] = "16.1.93"
    manifest["release_label"] = "16.1.93 — selected Reports & Analytics explorer"
    changes = list(manifest.get("release_candidate_changes", []))
    changes.append("16.1.93: selected read-only Reports & Analytics, site-local periods, authorized locations, exact SKU filter, selected-report Print/PDF and CSV; Owner-only critical reorder enforced server-side.")
    manifest["release_candidate_changes"] = changes
    manifest.setdefault("quality", {})["release"] = "16.1.93"
    manifest["quality"]["local_validation_status"] = "See 16.1.93 Reports Explorer CI artifact and QA receipt; target-host acceptance remains pending."
    manifest["quality"]["target_host_acceptance"] = "PENDING — no authenticated live WordPress/MariaDB run was performed by the build."
    manifest["quality"]["production_certification"] = "PENDING — live role-scoped acceptance, integrity/ledger reconciliation and verified backup restore/rollback have not been performed."
    manifest["reports_explorer_16_1_93"] = {
        "baseline_release": "16.1.92", "baseline_sha256": BASELINE_SHA256,
        "report_default": "branch_performance", "period_default": "today",
        "location_default": "all authorized locations", "sku_default": "empty",
        "selected_report_print_pdf": True, "server_enforced_location_scope": True,
        "critical_reorder_server_owner_only": True, "schema_changed": False,
        "database_version_changed": False, "css_source_or_compiled_bytes_changed": False,
        "live_deployment_performed": False,
        "note": "CSS release metadata changes only to align cache identity; no CSS source or compiled styles are modified."
    }
    files[manifest_path] = (json.dumps(manifest, ensure_ascii=False, indent=2) + "\n").encode("utf-8")
    readme_path = "stockland-erp-app/README.md"
    readme = files[readme_path].decode("utf-8")
    readme = replace_once(readme, "# Stockland Ethiopia ERP 16.1.92", "# Stockland Ethiopia ERP 16.1.93", "README heading")
    feature = """\n## Selected Reports & Analytics explorer — 16.1.93\n\n- Default selection: Branch performance, Today, All authorized locations, empty SKU search. Period options include Yesterday, Last 7 days, This month and a validated custom range using the ERP site-local date.\n- One selected report is returned at a time. Print / Save PDF prints that selected report only; selected-report CSV preserves CSV escaping and formula-injection mitigation.\n- Location IDs are checked against the current authenticated user's scope on the server. Critical reorder is Owner-only in both the REST permission callback and handler; its suggested replenishment is read-only and never changes stock.\n- Plugin and frontend route-cache identity are aligned to 16.1.93. Database/schema version markers stay unchanged. CSS source/compiled bytes are preserved; only CSS release metadata is bumped.\n- This candidate has not been deployed to the live WordPress site. CI and live-host acceptance are separate gates.\n\n"""
    readme = readme.replace("## Daily, weekly and monthly summary CSV — 16.1.92", feature + "## Daily, weekly and monthly summary CSV — 16.1.92", 1)
    files[readme_path] = readme.encode("utf-8")
    with zipfile.ZipFile(output_zip, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as zout:
        for info in infos:
            zi = zipfile.ZipInfo(info.filename, date_time=info.date_time)
            zi.compress_type = zipfile.ZIP_DEFLATED
            zi.external_attr = info.external_attr
            zi.create_system = info.create_system
            zi.flag_bits = info.flag_bits
            zout.writestr(zi, files[info.filename], compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    with zipfile.ZipFile(output_zip) as out:
        if out.testzip() is not None:
            raise ValueError("Candidate ZIP CRC failure")
        if [i.filename for i in out.infolist() if not i.is_dir()] != names:
            raise ValueError("Candidate archive inventory/order changed")
        for n, expected in css_before.items():
            if hashlib.sha256(out.read(n)).hexdigest() != expected:
                raise ValueError("CSS bytes changed: " + n)
    return {"baseline_sha256": digest, "baseline_bytes": len(raw), "candidate_sha256": hashlib.sha256(output_zip.read_bytes()).hexdigest(), "candidate_bytes": output_zip.stat().st_size, "files": len(names), "css_files_unchanged": len(css_paths)}

def main():
    inp = TARGET / "STOCKLAND_ERP_16.1.92_CANONICAL_WORDPRESS_UPDATE.zip"
    out = TARGET / "STOCKLAND_ERP_16.1.93_REPORTS_EXPLORER_CANDIDATE.zip"
    print(json.dumps(apply_to_installer(inp, out), indent=2))

if __name__ == "__main__":
    main()
