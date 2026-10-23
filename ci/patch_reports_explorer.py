#!/usr/bin/env python3
"""Apply the audited, read-only Reports & Analytics explorer patch to ERP 16.1.92."""
from __future__ import annotations
import hashlib, json, re, zipfile
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
		$stock_sql .= ' ORDER BY l.location_name,p.sku';""", """		$stock_args = array( $prod, $loc, $tx, $res, $tr, $prod );
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
					$rows = array_values( array_filter( $rows, static function ( $row ) {
						$reorder_minor   = Stockland_Money::minor( $row['reorder_level'] ?? '0' );
						$available_minor = Stockland_Money::minor( $row['available_stock'] ?? '0' );
						return $reorder_minor > 0 && $available_minor <= $reorder_minor;
					} ) );
					foreach ( $rows as &$row ) {
						$reorder_minor   = Stockland_Money::minor( $row['reorder_level'] ?? '0' );
						$available_minor = Stockland_Money::minor( $row['available_stock'] ?? '0' );
						$row['suggested_replenishment'] = Stockland_Money::decimal( max( 0, $reorder_minor - $available_minor ) );
					}
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
    stock_query_old = """\t\t$stock_sql  = "SELECT l.location_name,p.sku,p.product_name,p.brand,p.size,p.color,
                    COALESCE(b.physical_stock,0) physical_stock,COALESCE(a.reserved_stock,0) reserved_stock,
                    COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0) available_stock,
                    IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level) reorder_level
             FROM %i p JOIN %i l ON l.location_type IN('Warehouse','Branch')
             LEFT JOIN (SELECT location_id,variant_id,SUM(quantity_signed) physical_stock FROM %i WHERE variant_id IS NOT NULL GROUP BY location_id,variant_id) b
               ON b.variant_id=p.variant_id AND b.location_id=l.id
             LEFT JOIN (SELECT location_id,variant_id,SUM(quantity_reserved) reserved_stock FROM %i WHERE status='Active' AND variant_id IS NOT NULL GROUP BY location_id,variant_id) a
               ON a.variant_id=p.variant_id AND a.location_id=l.id
             WHERE p.is_active=1";"""
    stock_query_new = """\t\t$stock_sql  = "SELECT l.location_name,p.sku,p.product_name,p.brand,p.size,p.color,
                    COALESCE(b.physical_stock,0) physical_stock,
                    COALESCE(a.reserved_stock,0) reserved_stock,
                    COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0) available_stock,
                    COALESCE(i.inbound_stock,0) inbound_stock,
                    COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0)+COALESCE(i.inbound_stock,0) reorder_position,
                    IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level) reorder_level
             FROM %i p JOIN %i l ON l.location_type IN('Warehouse','Branch') AND l.is_active=1
             LEFT JOIN (SELECT location_id,variant_id,SUM(quantity_signed) physical_stock FROM %i WHERE variant_id IS NOT NULL GROUP BY location_id,variant_id) b
               ON b.variant_id=p.variant_id AND b.location_id=l.id
             LEFT JOIN (SELECT location_id,variant_id,SUM(quantity_reserved) reserved_stock FROM %i WHERE status='Active' AND variant_id IS NOT NULL GROUP BY location_id,variant_id) a
               ON a.variant_id=p.variant_id AND a.location_id=l.id
             LEFT JOIN (
                 SELECT t.to_location_id AS location_id,COALESCE(t.variant_id,ip.variant_id) AS variant_id,
                        SUM(GREATEST(COALESCE(t.dispatched_qty,0)-COALESCE(t.received_qty,0),0)) AS inbound_stock
                 FROM %i t
                 LEFT JOIN %i ip ON ((t.variant_id IS NOT NULL AND ip.variant_id=t.variant_id) OR (t.variant_id IS NULL AND ip.sku=t.sku))
                 WHERE t.status='In Transit'
                   AND COALESCE(t.dispatched_qty,0)>COALESCE(t.received_qty,0)
                   AND COALESCE(t.variant_id,ip.variant_id) IS NOT NULL
                 GROUP BY t.to_location_id,COALESCE(t.variant_id,ip.variant_id)
             ) i ON i.variant_id=p.variant_id AND i.location_id=l.id
             WHERE p.is_active=1";"""
    source = replace_once(source, stock_query_old, stock_query_new, "reorder query uses current active locations and confirmed inbound transfers")
    source = replace_once(source, "$stock_args = array( $prod, $loc, $tx, $res );", "$stock_args = array( $prod, $loc, $tx, $res, $tr, $prod );", "bind inbound transfer table arguments")

    method = replace_once(method,
        """\t\t\t\t\tarray( 'key' => 'available_stock', 'label' => 'Available', 'format' => 'number' ),
\t\t\t\t\tarray( 'key' => 'reorder_level', 'label' => 'Reorder level', 'format' => 'number' ),""",
        """\t\t\t\t\tarray( 'key' => 'available_stock', 'label' => 'Available now', 'format' => 'number' ),
\t\t\t\t\tarray( 'key' => 'inbound_stock', 'label' => 'Inbound transfer', 'format' => 'number' ),
\t\t\t\t\tarray( 'key' => 'reorder_position', 'label' => 'Reorder position', 'format' => 'number' ),
\t\t\t\t\tarray( 'key' => 'reorder_level', 'label' => 'Reorder level', 'format' => 'number' ),""",
        "show physical availability and inbound reorder position")
    method = replace_once(method,
        """\t\t\t\t\t\t$available_minor = Stockland_Money::minor( $row['available_stock'] ?? '0' );
\t\t\t\t\t\treturn $reorder_minor > 0 && $available_minor <= $reorder_minor;""",
        """\t\t\t\t\t\t$position_minor = Stockland_Money::minor( $row['reorder_position'] ?? $row['available_stock'] ?? '0' );
\t\t\t\t\t\treturn $reorder_minor > 0 && $position_minor <= $reorder_minor;""",
        "critical reorder uses available plus inbound transfer position")
    method = replace_once(method,
        """\t\t\t\t\t\t$available_minor = Stockland_Money::minor( $row['available_stock'] ?? '0' );
\t\t\t\t\t\t$row['suggested_replenishment'] = Stockland_Money::decimal( max( 0, $reorder_minor - $available_minor ) );""",
        """\t\t\t\t\t\t$position_minor = Stockland_Money::minor( $row['reorder_position'] ?? $row['available_stock'] ?? '0' );
\t\t\t\t\t\t$row['suggested_replenishment'] = Stockland_Money::decimal( max( 100, $reorder_minor - $position_minor ) );""",
        "replenishment suggestion subtracts dispatched inbound transfers")
    method = replace_once(method,
        """\t\t\t\t\t$description = 'Owner-only read-only list. Available stock equals physical stock less active reservations; suggested replenishment is the minimum quantity needed to reach the configured reorder level. No stock is ordered or changed.';""",
        """\t\t\t\t\t$description = 'Owner-only read-only list. Physical, reserved, available and dispatched-but-unreceived transfer quantities are shown separately. Reorder position equals current available stock plus confirmed inbound transfer remainder; the suggestion is the non-negative quantity needed to reach the configured location reorder level. In-transit quantity does not change physical stock. No order or stock movement is created.';""",
        "explain inbound-aware reorder semantics")

    source = replace_once(source, "\n\t/**\n\t * Builds a date-scoped summary of operational transactions.\n", "\n" + method + "\n\t/**\n\t * Builds a date-scoped summary of operational transactions.\n", "add reports_explorer method")
    source = replace_once(source, stock_query_new, """\t\t$stock_sql  = "SELECT l.location_name,p.sku,p.product_name,p.brand,p.size,p.color,
                    COALESCE(b.physical_stock,0)+COALESCE(bl.physical_stock,0) physical_stock,
                    COALESCE(a.reserved_stock,0)+COALESCE(al.reserved_stock,0) reserved_stock,
                    (COALESCE(b.physical_stock,0)+COALESCE(bl.physical_stock,0))-(COALESCE(a.reserved_stock,0)+COALESCE(al.reserved_stock,0)) available_stock,
                    COALESCE(i.inbound_stock,0) inbound_stock,
                    (COALESCE(b.physical_stock,0)+COALESCE(bl.physical_stock,0))-(COALESCE(a.reserved_stock,0)+COALESCE(al.reserved_stock,0))+COALESCE(i.inbound_stock,0) reorder_position,
                    IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level) reorder_level
             FROM %i p JOIN %i l ON l.location_type IN('Warehouse','Branch') AND l.is_active=1
             LEFT JOIN (SELECT location_id,variant_id,SUM(quantity_signed) physical_stock FROM %i WHERE variant_id IS NOT NULL GROUP BY location_id,variant_id) b
               ON b.variant_id=p.variant_id AND b.location_id=l.id
             LEFT JOIN (SELECT location_id,sku,SUM(quantity_signed) physical_stock FROM %i WHERE variant_id IS NULL GROUP BY location_id,sku) bl
               ON bl.location_id=l.id AND bl.sku=p.sku
             LEFT JOIN (SELECT location_id,variant_id,SUM(quantity_reserved) reserved_stock FROM %i WHERE status='Active' AND variant_id IS NOT NULL GROUP BY location_id,variant_id) a
               ON a.variant_id=p.variant_id AND a.location_id=l.id
             LEFT JOIN (SELECT location_id,sku,SUM(quantity_reserved) reserved_stock FROM %i WHERE status='Active' AND variant_id IS NULL GROUP BY location_id,sku) al
               ON al.location_id=l.id AND al.sku=p.sku
             LEFT JOIN (
                 SELECT t.to_location_id AS location_id,COALESCE(t.variant_id,ip.variant_id) AS variant_id,
                        SUM(GREATEST(COALESCE(t.dispatched_qty,0)-COALESCE(t.received_qty,0),0)) AS inbound_stock
                 FROM %i t
                 LEFT JOIN %i ip ON ((t.variant_id IS NOT NULL AND ip.variant_id=t.variant_id) OR (t.variant_id IS NULL AND ip.sku=t.sku))
                 WHERE t.status='In Transit'
                   AND COALESCE(t.dispatched_qty,0)>COALESCE(t.received_qty,0)
                   AND COALESCE(t.variant_id,ip.variant_id) IS NOT NULL
                 GROUP BY t.to_location_id,COALESCE(t.variant_id,ip.variant_id)
             ) i ON i.variant_id=p.variant_id AND i.location_id=l.id
             WHERE p.is_active=1 AND p.product_type<>'STYLE'";""", "legacy and inbound stock / reservation parity")
    source = replace_once(source, """\t\t$stock_args = array( $prod, $loc, $tx, $res, $tr, $prod );
\t\t$stock_sql .= $this->append_scope_filter( 'l.id', $stock_args, $scope );
\t\tif ( '' !== $sku ) {
\t\t\t$stock_sql   .= ' AND p.sku=%s';
\t\t\t$stock_args[] = $sku;
\t\t}
\t\t$stock_sql .= ' ORDER BY l.location_name,p.sku';""", """\t\t$stock_args = array( $prod, $loc, $tx, $tx, $res, $res, $tr, $prod );
\t\t$stock_sql .= $this->append_scope_filter( 'l.id', $stock_args, $scope );
\t\tif ( '' !== $sku ) {
\t\t\t$stock_sql   .= ' AND p.sku=%s';
\t\t\t$stock_args[] = $sku;
\t\t}
\t\t$stock_sql .= ' ORDER BY l.location_name,p.sku';""", "legacy and inbound stock query placeholder order")
    source = replace_once(source, """WHERE l.location_type='Branch'";""", """WHERE l.location_type='Branch' AND l.is_active=1";""", "exclude inactive branches from branch performance report")
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
  }catch(e){state.report=null;el.innerHTML='<div class="sl-notice sl-danger-block"><b>Report could not load.</b> '+esc(e.message)+'<div class="sl-actions" style="margin-top:10px"><button type="button" class="sl-btn" id="retryOpsReport">RETRY REPORT</button></div></div>';document.getElementById('retryOpsReport')?.addEventListener('click',()=>loadReport());return null;
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
    # Desktop-theme hardening is injected at runtime because the release preserves source/compiled CSS.
    # A desktop header is a higher grid-item stacking context (z-index 50); if host theme CSS widens
    # the global-search popup, it can sit above the Product Master action row. Raise only title actions
    # above that header and keep dialogs/modals at their existing much higher z-index.
    product_click_guard = """if(!document.getElementById('sl-desktop-title-action-layer-fix')){
  const style=document.createElement('style');
  style.id='sl-desktop-title-action-layer-fix';
  style.textContent='@media (min-width:992px){#stockland-app .sl-global-search{position:relative!important;min-width:0!important;width:100%!important;max-width:440px!important;box-sizing:border-box!important}#stockland-app #slGlobalSearchResults{position:absolute!important;top:calc(100% + 8px)!important;left:0!important;right:0!important;inset-inline-start:0!important;inset-inline-end:0!important;width:auto!important;min-width:0!important;max-width:min(440px,calc(100vw - 24px))!important;box-sizing:border-box!important;max-height:min(62vh,520px)!important;overflow:auto!important;z-index:2147483647!important}#stockland-app .sl-content-inner>.sl-page-title>.sl-actions{position:relative!important;z-index:80!important;isolation:isolate;pointer-events:auto!important}#stockland-app .sl-content-inner>.sl-page-title>.sl-actions>*{pointer-events:auto!important}}';
  (document.head||document.documentElement).appendChild(style);
}
if(!document.__slNewProductCaptureBound){
  document.__slNewProductCaptureBound=true;
  document.addEventListener('click',function(e){
    const target=e.target instanceof Element?e.target.closest('#newProduct'):null;
    const appRoot=document.getElementById('stockland-app');
    if(!target||!appRoot||!appRoot.contains(target)||target.disabled)return;
    e.preventDefault();e.stopImmediatePropagation();openProductEditor(null);
  },true);
}"""
    source = replace_once(source, "if($('#newProduct'))$('#newProduct').onclick=()=>openProductEditor(null);", product_click_guard + "\nif($('#newProduct'))$('#newProduct').onclick=()=>openProductEditor(null);", "desktop product create layering + capture delegation")
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
    app = patch_js(files[js_path].decode("utf-8"))
    app = replace_once(app, "setInterval(()=>{if(document.visibilityState==='visible')refreshLiveIndicators()},8000)", "setInterval(()=>{if(document.visibilityState==='visible')refreshLiveIndicators()},60000)", "InfinityFree activity refresh cadence")
    app = replace_once(app, "await new Promise(r=>setTimeout(r,60));", "await new Promise(r=>setTimeout(r,300));", "Product Master import chunk pacing")
    app = replace_once(app, "await new Promise(r=>setTimeout(r,80));", "await new Promise(r=>setTimeout(r,300));", "Daily POS import chunk pacing")
    files[js_path] = app.encode("utf-8")
    files[php_path] = patch_php(files[php_path].decode("utf-8")).encode("utf-8")
    entry_path = "stockland-erp-app/stockland-inventory.php"
    entry = files[entry_path].decode("utf-8")
    entry = replace_once(entry, "* Version:           16.1.92", "* Version:           16.1.93", "plugin header version")
    entry = replace_once(entry, "define( 'STOCKLAND_VERSION', '16.1.92' );", "define( 'STOCKLAND_VERSION', '16.1.93' );", "plugin constant version")
    entry = replace_once(entry, " * Requires PHP:      8.4", " * Requires PHP:      8.3", "InfinityFree PHP minimum")
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
    css_manifest["description"] = "Six ordered, locally bundled CSS modules; original Stockland design system with responsive ERP tables and work areas."
    files[css_manifest_path] = (json.dumps(css_manifest, ensure_ascii=False, indent=2) + "\n").encode("utf-8")
    manifest_path = "stockland-erp-app/RELEASE_MANIFEST.json"
    manifest = {
        "name": "Stockland Ethiopia ERP",
        "release": "16.1.93",
        "release_label": "16.1.93 — Reports Explorer, desktop Product Master protection and exact reorder arithmetic",
        "baseline_release": "16.1.92",
        "minimum_php": "8.3",
        "hosting_profile": {
            "target": "InfinityFree free WordPress hosting",
            "normal_operations": "same-origin actions initiated through a signed-in user's browser",
            "external_api_host_required": False,
            "external_runtime_dependencies_added": False,
            "scheduled_worker_required": False,
            "activity_refresh_seconds": 60,
            "import_chunk_delay_milliseconds": 300,
            "database_schema_changed": False,
            "business_data_migration_included": False
        },
        "baseline_sha256": BASELINE_SHA256,
        "package_root": "stockland-erp-app/",
        "archive_file_count": len(names),
        "database": {
            "schema_version": "16.1.1",
            "table_contract": {"total": 49, "core_operations": 29, "finance_procurement": 16, "hr_payroll": 4},
            "schema_changed": False,
            "database_version_changed": False,
            "activator_changed": False
        },
        "user_interface": {
            "design": "Original Stockland visual system with consistent responsive work areas, page actions, spreadsheet-style data tables and accessible operation states",
            "desktop_product_create_guard": "Global search results are constrained to the search field on desktop; title actions retain a separate stacking layer.",
            "selected_report_actions": ["Print / Save PDF", "Download selected CSV"],
            "default_report": "Branch performance",
            "default_period": "Today",
            "default_location": "All authorized locations",
            "default_sku_filter": ""
        },
        "inventory_and_finance": {
            "stock_authority": "Posted stock ledger",
            "available_stock": "Physical on-hand less active reservations; inbound and in-transit quantities remain separate",
            "critical_reorder_access": "Owner only",
            "critical_reorder_arithmetic": "Uses decimal-safe quantity arithmetic; reorder position equals available stock plus dispatched-but-unreceived transfer quantity, and replenishment is max(0, configured location reorder level minus reorder position).",
            "reorder_is_read_only": True,
            "money_arithmetic": "Stockland_Money decimal-safe service",
            "historical_cost_policy": "Transaction-staged COGS; current Product Master cost does not silently rewrite posted history",
            "negotiated_price_policy": "Actual sale price is recorded per transaction; Product Master defaults and history remain separate"
        },
        "internal_module_links": {
            "product_master_to_pos": "Canonical variant SKU and product identity",
            "pos_to_stock_ledger": "Posted sale inventory movement and audit trail",
            "pos_to_finance": "Transaction price, VAT, staged COGS and accounting posting",
            "receiving_to_stock": "Confirmed receipt creates posted stock movement",
            "requisition_reservation_transfer": "Role-gated workflow with source/destination and quantity history",
            "returns_adjustments": "Reasoned compensating inventory and accounting events",
            "reports": "Authorized location scope and selected-report export"
        },
        "external_connections": {
            "microsoft_entra_sign_in": "Requires owner-provided application registration, tenant policy and redirect URI configuration",
            "microsoft_teams": "Requires approved Microsoft provider permissions and server-side credentials before connection",
            "slack": "Requires a Slack OAuth application, approved scopes and server-side credentials before connection",
            "secret_handling": "Provider secrets must remain server-side and must not be placed in the browser bundle"
        },
        "preserved": {
            "css_source_and_compiled_files_changed": False,
            "single_plugin_root": True,
            "live_records_rewritten_by_package_build": False,
            "automated_orders_or_stock_changes_from_reorder_report": False
        },
        "update_instructions": [
            "Back up WordPress files and database before replacing plugin files.",
            "Update the existing stockland-erp-app plugin folder as a whole.",
            "Do not uninstall the plugin or delete its database tables.",
            "Clear page/browser/PWA caches after file replacement."
        ]
    }
    files[manifest_path] = (json.dumps(manifest, ensure_ascii=False, indent=2) + "\n").encode("utf-8")
    readme_path = "stockland-erp-app/README.md"
    readme = """# Stockland Ethiopia ERP 16.1.93

A responsive operations and finance system for Stockland Ethiopia, designed around its Central Warehouse and Goro, Gerji, Mexico and Megenagna branches.

## Work areas

- Command Center, Product Master, Inventory, Daily Inventory, Retail POS, Wholesale and Central Warehouse.
- Receiving, purchase orders, requisitions, reservations, transfers, physical counts, returns, damage and approved adjustments.
- Finance and Procurement, customer/vendor balances, AP/AR, expenses, general ledger, HR/Payroll, Reports & Analytics, Users, Audit Log and System Health.
- Role- and location-scoped operations with an original Stockland visual system, consistent page titles/action rows, reusable data-table holders, responsive grids, and readable loading, empty, success and error states.

## Desktop Product Master

On desktop-sized layouts, the global-search panel is constrained to the search field instead of being allowed to expand across the page. Product Master title actions retain their own stacking layer. Creating a product continues through the existing form validation, protected ERP endpoint, returned-SKU verification and catalog refresh.

## Stock, finance and reorder rules

- Posted stock-ledger movements are authoritative. Physical stock, reservations, available stock and inbound/in-transit quantities are distinct.
- Money arithmetic uses the shared Stockland decimal-safe service. VAT mode/rate is centrally controlled and posted transaction values are preserved.
- Historical COGS is staged at transaction time. Updating Product Master cost does not rewrite posted history; negotiated sale prices are kept with their transactions.
- Critical reorder is Owner-only and read-only. It shows physical, reserved and currently available stock separately, plus dispatched-but-unreceived transfers. Reorder position is available stock plus confirmed inbound transfer remainder. Suggested replenishment targets the location-specific reorder level without treating inbound stock as physically received; it does not create a purchase order, reserve stock or post a stock movement.
- The schema contract remains 49 tables: 29 Core/Operations, 16 Finance/Procurement, and 4 HR/Payroll. Database schema version remains 16.1.1.

## Low-resource hosting profile

- Visible-session activity refresh runs every 60 seconds. Successful user transactions still refresh the affected work area immediately.
- Daily POS and Product Master commit chunks are spaced by 300 milliseconds when more chunks remain, reducing rapid request bursts.
- The package adds no remote runtime assets, external API service, scheduled worker or separate database.
- ERP requests are same-origin and initiated from the signed-in user's regular browser. Do not operate the site through external scripts, command-line API clients or cross-domain webhook callers.

## Update an existing InfinityFree WordPress installation

1. Back up the WordPress files and database.
2. Replace the files in the existing wp-content/plugins/stockland-erp-app/ folder with the contents of this package. Keep the same plugin folder name.
3. Do not uninstall the plugin and do not delete its database tables.
4. Clear page/browser/PWA caches so the new JavaScript and route-cache version load.
5. Sign in through the ERP frontend and review System Health and the user's authorized location before posting business transactions.

## External identity and collaboration

Microsoft Entra sign-in, Teams and Slack require owner-provided provider application registration, approved permissions/scopes and server-side credentials before those connections can be activated. Keep provider secrets out of page source, JavaScript bundles and browser storage.
"""
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


APPROVAL_LAYOUT_MARKER = "/* Requisition approval routing — keep decisions readable on phones and desktop."
APPROVAL_LAYOUT_CSS = """/* Requisition approval routing — keep decisions readable on phones and desktop.
 * Modal markup is appended to document.body, outside #stockland-app; scope to its overlay.
 * Keep two approval cards per row on tablets/desktops, with one full-width card per row on phones.
 * Allow long names, notes and status pills to wrap inside each card. Presentation only; no workflow changes.
 */
.sl-overlay .sl-req-drawer .sl-approval-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
}
.sl-overlay .sl-req-drawer .sl-approval-line {
  min-width: 0;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 10px;
}
.sl-overlay .sl-req-drawer .sl-approval-person {
  flex: 1 1 12rem;
  min-width: 0;
  grid-template-columns: 28px minmax(0, 1fr);
  align-items: start;
  gap: 10px;
}
.sl-overlay .sl-req-drawer .sl-approval-person > span:last-child {
  min-width: 0;
}
.sl-overlay .sl-req-drawer .sl-approval-person strong,
.sl-overlay .sl-req-drawer .sl-approval-person small {
  display: block;
  width: 100%;
  max-width: 100%;
  overflow-wrap: anywhere;
  white-space: normal;
  line-height: 1.4;
}
.sl-overlay .sl-req-drawer .sl-approval-line > div:last-child {
  flex: 0 1 auto;
  min-width: 0;
  max-width: 100%;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-start;
  gap: 6px;
}
@media (max-width: 767px) {
  .sl-overlay .sl-req-drawer .sl-approval-grid {
    grid-template-columns: minmax(0, 1fr) !important;
  }
  .sl-overlay .sl-req-drawer .sl-approval-line {
    display: flex;
    flex-direction: column;
    align-items: stretch;
  }
  .sl-overlay .sl-req-drawer .sl-approval-person {
    flex: 1 1 100%;
  }
}
"""


def patch_requisition_mobile_approval_layout(archive_path: Path) -> dict:
    """Apply a tested presentation-only fix to approval history in the body-level requisition drawer."""
    before_sha = hashlib.sha256(archive_path.read_bytes()).hexdigest()
    with zipfile.ZipFile(archive_path) as zin:
        if zin.testzip() is not None:
            raise ValueError("Approval-layout input ZIP CRC failure")
        infos = [item for item in zin.infolist() if not item.is_dir()]
        names = [item.filename for item in infos]
        if len(names) != 75 or len(names) != len(set(names)):
            raise ValueError("Approval-layout patch requires the exact 75-file plugin inventory")
        if not all(PurePosixPath(name).parts[0] == "stockland-erp-app" and ".." not in PurePosixPath(name).parts for name in names):
            raise ValueError("Approval-layout patch rejected an unexpected archive path")
        files = {name: zin.read(name) for name in names}

    module_path = "stockland-erp-app/assets/css/06-operational-guards.css"
    app_css_path = "stockland-erp-app/assets/app.css"
    app_min_path = "stockland-erp-app/assets/app.min.css"
    manifest_path = "stockland-erp-app/RELEASE_MANIFEST.json"
    modules_path = "stockland-erp-app/assets/css/modules.json"
    module = files[module_path].decode("utf-8")
    if APPROVAL_LAYOUT_MARKER in module:
        if module.count(APPROVAL_LAYOUT_MARKER) != 1 or ".sl-overlay .sl-req-drawer .sl-approval-grid" not in module:
            raise ValueError("Existing approval-layout marker is partial or duplicated")
        applied = False
    else:
        module = module.rstrip() + "\n\n" + APPROVAL_LAYOUT_CSS.strip() + "\n"
        applied = True
    files[module_path] = module.encode("utf-8")

    modules = json.loads(files[modules_path].decode("utf-8"))
    if modules.get("release") != "16.1.93" or len(modules.get("modules", [])) != 6 or len(set(modules["modules"])) != 6:
        raise ValueError("Approval-layout CSS module manifest/release mismatch")
    chunks = [
        files["stockland-erp-app/assets/css/" + name].decode("utf-8").strip()
        for name in modules["modules"]
    ]
    app_css = "\n\n".join(chunks) + "\n"
    normalized = re.sub(r"/\*[\s\S]*?\*/", " ", app_css)
    normalized = re.sub(r"\s+", " ", normalized).strip()
    normalized = re.sub(r"\s*([{}:;,])\s*", r"\1", normalized).replace(";}", "}")
    files[app_css_path] = app_css.encode("utf-8")
    files[app_min_path] = normalized.encode("utf-8")

    manifest = json.loads(files[manifest_path].decode("utf-8"))
    if manifest.get("release") != "16.1.93":
        raise ValueError("Approval-layout release manifest mismatch")
    ui = manifest.setdefault("user_interface", {})
    ui["requisition_approval_history"] = "Responsive single-column decision rows; long approver labels, notes and status pills wrap without overlapping."
    preserved = manifest.setdefault("preserved", {})
    preserved["css_source_and_compiled_files_changed"] = True
    preserved["css_source_build_parity_verified"] = True
    files[manifest_path] = (json.dumps(manifest, ensure_ascii=False, indent=2) + "\n").encode("utf-8")

    temporary = archive_path.with_name(archive_path.name + ".approval-layout.tmp")
    with zipfile.ZipFile(temporary, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as zout:
        for info in infos:
            zi = zipfile.ZipInfo(info.filename, date_time=info.date_time)
            zi.compress_type = zipfile.ZIP_DEFLATED
            zi.external_attr = info.external_attr
            zi.create_system = info.create_system
            zi.flag_bits = info.flag_bits
            zout.writestr(zi, files[info.filename], compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    with zipfile.ZipFile(temporary) as check:
        if check.testzip() is not None:
            temporary.unlink(missing_ok=True)
            raise ValueError("Approval-layout output ZIP CRC failure")
        after_names = [item.filename for item in check.infolist() if not item.is_dir()]
        if after_names != names:
            temporary.unlink(missing_ok=True)
            raise ValueError("Approval-layout patch changed archive inventory/order")
        if check.read(app_css_path).decode("utf-8") != app_css:
            temporary.unlink(missing_ok=True)
            raise ValueError("Approval-layout CSS source bundle is not deterministic")
        if check.read(app_min_path).decode("utf-8") != normalized:
            temporary.unlink(missing_ok=True)
            raise ValueError("Approval-layout CSS compiled bundle is not deterministic")
    temporary.replace(archive_path)
    return {
        "applied": applied,
        "baseline_candidate_sha256": before_sha,
        "candidate_sha256": hashlib.sha256(archive_path.read_bytes()).hexdigest(),
        "plugin_files": len(names),
        "css_module_changed": module_path,
        "compiled_css_rebuilt": [app_css_path, app_min_path],
        "css_source_build_parity": True,
        "schema_or_business_data_changed": False,
        "mobile_approval_rows": "two-column tablet/desktop grid and single-column phone grid; wrapped labels/notes/status pills",
    }

def main():
    inp = TARGET / "STOCKLAND_ERP_16.1.92_CANONICAL_WORDPRESS_UPDATE.zip"
    out = TARGET / "STOCKLAND_ERP_16.1.93_WORDPRESS_UPDATE.zip"
    reports = apply_to_installer(inp, out)
    from patch_stock_reconciliation import rebuild_zip
    from patch_production_hardening import patch_archive
    from patch_domain_cleanup import clean_archive
    stock = rebuild_zip(out)
    production = patch_archive(out)
    domain = clean_archive(out)
    approval_layout = patch_requisition_mobile_approval_layout(out)
    print(json.dumps({
        "installer": out.name,
        "reports_explorer": reports,
        "stock_reconciliation": stock,
        "production_hardening": production,
        "domain_cleanup": domain,
        "responsive_approval_layout": approval_layout
    }, indent=2))

if __name__ == "__main__":
    main()
