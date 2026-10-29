<?php
/**
 * Atomic multi-line retail POS cart.
 *
 * Every line is a CLOSED sale inside one database transaction. A failed line,
 * inventory ledger post, GL journal or audit write rolls back the entire cart.
 * This controller never calls POST /sales sequentially.
 *
 * @package Stockland_ERP
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Stockland_REST_POS_Cart extends Stockland_REST_Sales {
	public function __construct() {
		add_action( 'stockland_register_rest_routes', array( $this, 'register_cart_routes' ) );
	}

	public function register_cart_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/sales/cart',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_cart' ),
				'permission_callback' => function () {
					if ( ! current_user_can( 'stockland_create_sale' ) ) {
						return new WP_Error( 'forbidden', 'Your Stockland role is not authorized to create retail POS carts.', array( 'status' => 403 ) );
					}
					return Stockland_Authority::assert_profile();
				},
			)
		);
	}

	private function cart_error( $code, $message, $status = 409 ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	private function cart_totals( $rows ) {
		$totals = array(
			'sales_total'       => '0.00',
			'vat_amount'        => '0.00',
			'net_sales_excl_vat'=> '0.00',
			'cogs'              => '0.00',
			'gross_profit'      => '0.00',
		);
		foreach ( $rows as $row ) {
			foreach ( array_keys( $totals ) as $field ) {
				$totals[ $field ] = Stockland_Money::add( $totals[ $field ], $row[ $field ] ?? '0.00' );
			}
		}
		return $totals;
	}

	private function is_discrete_unit( $unit ) {
		return in_array( strtoupper( trim( (string) $unit ) ), array( '', 'PCS', 'PC', 'PIECE', 'PIECES', 'EA', 'EACH', 'UNIT', 'UNITS' ), true );
	}

	private function normalize_cart_lines( $raw_lines ) {
		if ( ! is_array( $raw_lines ) || count( $raw_lines ) < 1 || count( $raw_lines ) > 50 ) {
			throw new InvalidArgumentException( 'A cart must contain between 1 and 50 lines.' );
		}
		$lines = array();
		$seen  = array();
		foreach ( array_values( $raw_lines ) as $index => $line ) {
			if ( ! is_array( $line ) || empty( $line['sku'] ) || ! isset( $line['quantity_sold'] ) ) {
				throw new InvalidArgumentException( 'Every cart line requires a SKU and quantity.' );
			}
			$sku = sanitize_text_field( (string) $line['sku'] );
			if ( ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D', $sku ) ) {
				throw new InvalidArgumentException( 'A cart line has an invalid SKU.' );
			}
			$identity = strtoupper( $sku );
			if ( isset( $seen[ $identity ] ) ) {
				throw new InvalidArgumentException( 'Each SKU may appear only once in a cart; increase its quantity on the existing line.' );
			}
			$seen[ $identity ] = true;
			$qty_raw = trim( (string) $line['quantity_sold'] );
			if ( ! preg_match( '/^(?:\d+)(?:\.\d{1,2})?$/D', $qty_raw ) || (float) $qty_raw <= 0 ) {
				throw new InvalidArgumentException( 'Cart quantities must be positive numbers with no more than two decimal places.' );
			}
			$price = null;
			if ( isset( $line['selling_price'] ) && '' !== (string) $line['selling_price'] ) {
				$price_raw = trim( (string) $line['selling_price'] );
				if ( ! preg_match( '/^\d+(?:\.\d{1,2})?$/D', $price_raw ) || Stockland_Money::minor( $price_raw ) <= 0 ) {
					throw new InvalidArgumentException( 'An overridden line price must be a positive ETB amount with at most two decimal places.' );
				}
				$price = Stockland_Money::decimal( Stockland_Money::minor( $price_raw ) );
			}
			$lines[] = array(
				'index'          => $index + 1,
				'sale_id'        => '',
				'sku'            => $sku,
				'quantity_sold'  => Stockland_Money::decimal( Stockland_Money::minor( $qty_raw ) ),
				'quantity_raw'   => $qty_raw,
				'selling_price'  => $price,
			);
		}
		return $lines;
	}

	private function same_replay_line( $row, $line, $branch, $date, $vat_mode, $receipt ) {
		if ( (int) $row['branch_location_id'] !== (int) $branch ||
			(string) $row['sale_date'] !== (string) $date ||
			strtoupper( (string) $row['sku'] ) !== strtoupper( (string) $line['sku'] ) ||
			abs( (float) $row['quantity_sold'] - (float) $line['quantity_sold'] ) > 0.00001 ||
			strtoupper( (string) $row['vat_mode'] ) !== $vat_mode ||
			(string) ( $row['external_receipt_no'] ?? '' ) !== (string) $receipt ||
			'CLOSED' !== strtoupper( (string) $row['posting_status'] ) ) {
			return false;
		}
		if ( null !== $line['selling_price'] && ! Stockland_Sale_Math::same_money( $row['selling_price'], $line['selling_price'] ) ) {
			return false;
		}
		return true;
	}

	public function create_cart( WP_REST_Request $request ) {
		$p = $request->get_json_params();
		$cart_id = strtoupper( sanitize_text_field( (string) ( $p['cart_id'] ?? '' ) ) );
		if ( ! preg_match( '/^C[A-Z0-9]{8,13}$/D', $cart_id ) ) {
			return $this->cart_error( 'pos_cart_id_invalid', 'cart_id must be a stable client-generated identifier beginning with C and containing 9–14 uppercase letters/numbers.', 400 );
		}
		try {
			$lines = $this->normalize_cart_lines( $p['lines'] ?? null );
			$vat_mode = strtoupper( (string) ( $p['vat_mode'] ?? '' ) );
			if ( ! in_array( $vat_mode, array( 'VAT_INCLUDED', 'WITHOUT_VAT' ), true ) ) {
				throw new InvalidArgumentException( 'vat_mode must be VAT_INCLUDED or WITHOUT_VAT.' );
			}
		} catch ( Throwable $e ) {
			return $this->cart_error( 'pos_cart_input_invalid', $e->getMessage(), 400 );
		}
		$branch = $this->resolve_branch_for_user( $p['branch_location_id'] ?? null );
		if ( is_wp_error( $branch ) ) { return $branch; }
		if ( ! $branch ) { return $this->cart_error( 'pos_cart_branch_required', 'A permitted branch/location is required.', 400 ); }
		$date = isset( $p['sale_date'] ) && '' !== $p['sale_date'] ? sanitize_text_field( (string) $p['sale_date'] ) : current_time( 'Y-m-d' );
		if ( ! Stockland_Business_Calendar::valid_date( $date ) ) {
			return $this->cart_error( 'pos_cart_date_invalid', 'sale_date must be a real YYYY-MM-DD date.', 400 );
		}
		$receipt = sanitize_text_field( (string) ( $p['external_receipt_no'] ?? '' ) );
		if ( strlen( $receipt ) > 80 ) { return $this->cart_error( 'pos_cart_receipt_invalid', 'External receipt number cannot exceed 80 characters.', 400 ); }
		$prefix = $cart_id . '-';
		foreach ( $lines as &$line ) {
			$line['sale_id'] = $prefix . sprintf( '%02d', (int) $line['index'] );
			if ( strlen( $line['sale_id'] ) > 20 ) { return $this->cart_error( 'pos_cart_id_invalid', 'Cart identifier generates a sale ID that exceeds the storage limit.', 400 ); }
		}
		unset( $line );

		global $wpdb;
		$sales_table = Stockland_DB::table( 'sales' );
		$day_lock = Stockland_DB::acquire_day_lock( (int) $branch, $date );
		if ( empty( $day_lock['ok'] ) ) {
			return $this->cart_error( 'pos_cart_busy', 'Another POS/inventory operation is being processed for this branch/date. Retry this same cart identifier.', 409 );
		}
		if ( false === Stockland_DB::execute_query( 'START TRANSACTION' ) ) {
			Stockland_DB::release_day_lock( $day_lock );
			return $this->cart_error( 'pos_cart_transaction_failed', 'Could not start the cart transaction.', 500 );
		}
		$user_id = get_current_user_id();
		try {
			// Check idempotent replay before day-close validation so a committed cart can
			// be safely recovered after a lost response or a subsequent daily close.
			$wpdb->last_error = '';
			$existing_rows = Stockland_DB::read_results(
				$wpdb->prepare( 'SELECT * FROM %i WHERE sale_id LIKE %s ORDER BY sale_id FOR UPDATE', $sales_table, $wpdb->esc_like( $prefix ) . '%' ),
				ARRAY_A
			);
			if ( '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'Could not check the cart retry key.' ); }
			if ( is_array( $existing_rows ) && count( $existing_rows ) > 0 ) {
				if ( count( $existing_rows ) !== count( $lines ) ) { throw new RuntimeException( 'This cart identifier is already partially present or has a different line count. Do not submit a new cart; ask a manager to inspect Recent Posted Sales.' ); }
				$by_id = array();
				foreach ( $existing_rows as $row ) { $by_id[ (string) $row['sale_id'] ] = $row; }
				$replay = array();
				foreach ( $lines as $line ) {
					$row = $by_id[ $line['sale_id'] ] ?? null;
					if ( ! $row || ! $this->same_replay_line( $row, $line, $branch, $date, $vat_mode, $receipt ) ) {
						throw new RuntimeException( 'This cart identifier has already been used by a different transaction. No additional lines were posted.' );
					}
					$replay[] = $row;
				}
				if ( false === Stockland_DB::execute_query( 'COMMIT' ) ) { throw new RuntimeException( 'Cart retry confirmation failed.' ); }
				Stockland_DB::release_day_lock( $day_lock );
				$totals = $this->cart_totals( $replay );
				return rest_ensure_response( array( 'cart_id' => $cart_id, 'status' => 'CLOSED', 'idempotent_replay' => true, 'line_count' => count( $replay ), 'lines' => $replay, 'totals' => $totals ) );
			}
			Stockland_Business_Calendar::assert_business_day( $date );
			if ( Stockland_DB::is_day_closed( (int) $branch, $date ) ) { throw new RuntimeException( 'This branch business day is already closed.' ); }

			// Acquire Product Master locks in stable SKU order to reduce deadlock risk.
			$sku_order = array_values( array_unique( array_map( static function ( $line ) { return (string) $line['sku']; }, $lines ) ) );
			usort( $sku_order, 'strcasecmp' );
			$products = array();
			foreach ( $sku_order as $sku ) {
				$wpdb->last_error = '';
				$product = Stockland_DB::read_row(
					$wpdb->prepare( 'SELECT * FROM %i WHERE sku=%s AND is_active=1 LIMIT 1 FOR UPDATE', Stockland_DB::table( 'products' ), $sku ),
					ARRAY_A
				);
				if ( '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'Product Master lookup failed; no cart lines were posted.' ); }
				if ( ! $product || ( $product['product_type'] ?? 'SIMPLE' ) === 'STYLE' ) { throw new RuntimeException( 'SKU ' . $sku . ' is missing, inactive or is not a stockable variant.' ); }
				$products[ strtoupper( $sku ) ] = $product;
			}
			$vat_rate = (float) Stockland_DB::get_vat_rate();
			$posted_rows = array();
			foreach ( $lines as $line ) {
				$product = $products[ strtoupper( $line['sku'] ) ];
				if ( $this->is_discrete_unit( $product['unit'] ?? 'PCS' ) && abs( (float) $line['quantity_sold'] - round( (float) $line['quantity_sold'] ) ) > 0.000001 ) {
					throw new RuntimeException( 'SKU ' . $line['sku'] . ' uses a piece/unit UOM. Fractional quantity is not allowed.' );
				}
				$master_price = Stockland_Money::decimal( Stockland_Money::minor( $product['retail_price'] ) );
				$selling_price = null === $line['selling_price'] ? $master_price : $line['selling_price'];
				$calc = Stockland_Sale_Math::calculate( $line['quantity_sold'], $selling_price, $vat_mode, $vat_rate );
				$cogs = Stockland_Money::multiply( $line['quantity_sold'], $product['purchase_cost'] );
				$profit = Stockland_Money::subtract( $calc['net_sales'], $cogs );

				$wpdb->last_error = '';
				$available = Stockland_DB::get_available_stock( (int) $branch, $line['sku'] );
				if ( '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'Stock availability could not be verified for ' . $line['sku'] . '; no cart lines were posted.' ); }
				if ( $available < (float) $line['quantity_sold'] ) { throw new RuntimeException( sprintf( 'Insufficient available stock for %s. Available %s, requested %s. No cart lines were posted.', $line['sku'], $available, $line['quantity_sold'] ) ); }
				$row = array(
					'sale_id' => $line['sale_id'], 'sale_date' => $date, 'branch_location_id' => (int) $branch,
					'sku' => $line['sku'], 'quantity_sold' => (float) $line['quantity_sold'], 'variant_id' => (int) $product['variant_id'],
					'size' => $product['size'], 'color' => $product['color'],
					'barcode' => ! empty( $product['barcode'] ) ? $product['barcode'] : ( $product['gtin'] ?? null ),
					'master_price_at_sale' => $master_price, 'selling_price' => $selling_price,
					'price_overridden' => Stockland_Sale_Math::same_money( $selling_price, $master_price ) ? 0 : 1,
					'vat_mode' => $calc['vat_mode'], 'vat_rate_used' => $vat_rate,
					'sales_total' => $calc['sales_total'], 'vat_amount' => $calc['vat_amount'],
					'net_sales_excl_vat' => $calc['net_sales'], 'cogs' => $cogs, 'gross_profit' => $profit,
					'posting_status' => 'CLOSED', 'staff_user_id' => $user_id,
					'closed_at' => current_time( 'mysql' ), 'closed_by' => $user_id,
					'external_receipt_no' => '' !== $receipt ? $receipt : null,
				);
				if ( false === Stockland_DB::insert_record( $sales_table, $row ) ) { throw new RuntimeException( $wpdb->last_error ? $wpdb->last_error : 'Could not save cart line.' ); }
				$sale = Stockland_DB::read_row( $wpdb->prepare( 'SELECT * FROM %i WHERE sale_id=%s LIMIT 1 FOR UPDATE', $sales_table, $line['sale_id'] ), ARRAY_A );
				if ( ! $sale ) { throw new RuntimeException( 'Cart line was inserted but could not be re-read for posting.' ); }
				if ( false === Stockland_Ledger::post_sale_closed( $sale, $user_id ) ) { throw new RuntimeException( 'Inventory ledger posting failed for cart line ' . $line['sku'] . '; entire cart rolled back.' ); }
				$journal = Stockland_Accounting::post_journal(
					$date,
					'POS_SALE',
					$line['sale_id'],
					'Retail POS cart ' . $cart_id . ' line ' . sprintf( '%02d', (int) $line['index'] ),
					array(
						array( 'account_code' => '1020', 'debit' => $sale['sales_total'], 'credit' => '0.00', 'location_id' => (int) $branch ),
						array( 'account_code' => '4000', 'debit' => '0.00', 'credit' => $sale['net_sales_excl_vat'], 'location_id' => (int) $branch ),
						array( 'account_code' => '2100', 'debit' => '0.00', 'credit' => $sale['vat_amount'], 'location_id' => (int) $branch ),
						array( 'account_code' => '5000', 'debit' => $sale['cogs'], 'credit' => '0.00', 'location_id' => (int) $branch ),
						array( 'account_code' => '1200', 'debit' => '0.00', 'credit' => $sale['cogs'], 'location_id' => (int) $branch ),
					),
					$user_id
				);
				if ( ! $journal ) { throw new RuntimeException( 'GL journal failed for cart line ' . $line['sku'] . '; entire cart rolled back.' ); }
				if ( ! Stockland_Audit::log( $user_id, (int) $branch, 'POS_SALE', $line['sku'], (float) $line['quantity_sold'], $master_price, $selling_price, 'Retail POS cart posted atomically', $line['sale_id'], $user_id ) ) {
					throw new RuntimeException( 'Audit event failed for cart line ' . $line['sku'] . '; entire cart rolled back.' );
				}
				$posted_rows[] = $sale;
			}
			if ( false === Stockland_DB::execute_query( 'COMMIT' ) ) { throw new RuntimeException( 'Cart commit failed; do not submit a different cart identifier.' ); }
			Stockland_DB::release_day_lock( $day_lock );
			$verified = array();
			foreach ( $lines as $line ) {
				$row = Stockland_DB::read_row( $wpdb->prepare( 'SELECT * FROM %i WHERE sale_id=%s LIMIT 1', $sales_table, $line['sale_id'] ), ARRAY_A );
				if ( ! $row || 'CLOSED' !== strtoupper( (string) $row['posting_status'] ) ) {
					return $this->cart_error( 'pos_cart_verification_failed', 'Cart committed but a line did not verify CLOSED. Retry the same cart ID or inspect Recent Posted Sales; do not start a new cart.', 409 );
				}
				$verified[] = $row;
			}
			$totals = $this->cart_totals( $verified );
			return rest_ensure_response( array( 'cart_id' => $cart_id, 'status' => 'CLOSED', 'idempotent_replay' => false, 'line_count' => count( $verified ), 'lines' => $verified, 'totals' => $totals ) );
		} catch ( Throwable $e ) {
			Stockland_DB::execute_query( 'ROLLBACK' );
			Stockland_DB::release_day_lock( $day_lock );
			return $this->cart_error( 'pos_cart_failed', $e->getMessage(), 409 );
		}
	}
}
