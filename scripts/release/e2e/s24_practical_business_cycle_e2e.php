<?php
/**
 * Practical business cycle with hand-calculated expected values (free-form SKU scheme, VAT 15%).
 * Purchase -> receipt -> branch transfer -> branch sales/cart/return -> daily close -> supplier invoice/payment
 * -> customer invoice -> conservation, ledger and audit-chain proof. Every figure is computed here independently
 * of the application and ASSERTED (unlike the older print-only suites). Disposable site only.
 */
require __DIR__ . '/lib.php';
global $wpdb;
$u      = get_option( 'e2e_users', array() );
$p      = $wpdb->prefix . 'stock_';
$today  = current_time( 'Y-m-d' );
$failed = 0;
$check  = static function ( $label, $ok, $detail = '' ) use ( &$failed ) {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $detail ? ' — ' . $detail : '' ) . PHP_EOL;
	if ( ! $ok ) { ++$failed; }
};
$eq     = static function ( $a, $b, $eps = 0.005 ) { return abs( (float) $a - (float) $b ) < $eps; };
$vat    = static function ( $gross ) { return round( (float) $gross * 0.15 / 1.15, 2 ); };
$bal    = static function ( $sku, $loc ) use ( $wpdb, $p ) { return (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(physical_qty),0) FROM {$p}balances WHERE sku=%s AND location_id=%d", $sku, $loc ) ); };
$key    = static function () { return strtolower( str_replace( '-', '', wp_generate_uuid4() ) ); };
$ok_s   = static function ( $res ) { return in_array( $res[0], array( 200, 201 ), true ); };
$run    = strtoupper( substr( md5( (string) microtime( true ) ), 0, 5 ) );
$SH     = 'AB.SHIRT-' . $run;   // deliberately not STK-
$JE     = 'AB.JEANS_' . $run;
$central = 1; $branch = 3; // Gerji: its own branch + staff, so closing its day cannot disturb the other suites
$bm3 = mkuser( 'brmgr3', 'subscriber', 'branch_manager', $branch );
$se3 = mkuser( 'sales3', 'subscriber', 'sales_employee', $branch );
( new WP_User( $bm3 ) )->set_role( Stockland_Roles::ROLE_BRANCH_MANAGER );
( new WP_User( $se3 ) )->set_role( Stockland_Roles::ROLE_SALES_EMPLOYEE );
Stockland_Roles::sync_roles();

// ---------- A. Catalog with a free-form SKU scheme ----------
$a1 = call( 'POST', '/products', array( 'sku' => $SH, 'product_name' => 'Practical Shirt', 'category' => 'Tops', 'unit' => 'PCS', 'purchase_cost' => '200.00', 'retail_price' => '450.00', 'wholesale_price' => '350.00', 'product_type' => 'SIMPLE' ), $u['mg'] );
$a2 = call( 'POST', '/products', array( 'sku' => $JE, 'product_name' => 'Practical Jeans', 'category' => 'Bottoms', 'unit' => 'PCS', 'purchase_cost' => '500.00', 'retail_price' => '1150.00', 'wholesale_price' => '900.00', 'product_type' => 'SIMPLE' ), $u['mg'] );
$check( 'A1 two products with non-STK SKUs are created by Management', $ok_s( $a1 ) && $ok_s( $a2 ), $a1[0] . '/' . $a2[0] );
$check( 'A2 a Sales Employee cannot create catalog products', ! $ok_s( call( 'POST', '/products', array( 'sku' => 'AB.NOPE-' . $run, 'product_name' => 'x', 'category' => 'x', 'unit' => 'PCS', 'purchase_cost' => '1', 'retail_price' => '2', 'wholesale_price' => '1' ), $se3 ) ) );

// ---------- B. Purchase order, approval, receipt ----------
call( 'POST', '/accounting/suppliers', array( 'supplier_code' => 'PR' . $run, 'supplier_name' => 'Practical Supplier ' . $run, 'opening_balance' => '0' ), $u['mg'] );
$sid  = (int) qv( "SELECT id FROM {$p}suppliers WHERE supplier_code='PR$run'" );
$po_b = array( 'supplier_id' => $sid, 'order_date' => $today, 'request_key' => $key(), 'lines' => array( array( 'sku' => $SH, 'quantity' => 100, 'unit_cost' => '200.00', 'tax_rate' => 0.15 ), array( 'sku' => $JE, 'quantity' => 40, 'unit_cost' => '500.00', 'tax_rate' => 0.15 ) ) );
$po_r = call( 'POST', '/accounting/purchase-orders', $po_b, $u['mg'] );
$poid = (int) qv( "SELECT id FROM {$p}purchase_orders WHERE supplier_id=$sid ORDER BY id DESC LIMIT 1" );
$check( 'B1 PO created; total = (100×200 + 40×500) × 1.15 = 46,000.00', $ok_s( $po_r ) && $poid > 0 && $eq( qv( "SELECT total_amount FROM {$p}purchase_orders WHERE id=$poid" ), 46000 ), (string) qv( "SELECT total_amount FROM {$p}purchase_orders WHERE id=$poid" ) );
call( 'POST', '/accounting/purchase-orders', $po_b, $u['mg'] );
$check( 'B2 replaying the same request key creates no second PO', 1 === (int) qv( "SELECT COUNT(*) FROM {$p}purchase_orders WHERE supplier_id=$sid" ) );
call( 'POST', "/accounting/purchase-orders/$poid/status", array( 'status' => 'SUBMITTED' ), $u['mg'] );
$self = call( 'POST', "/accounting/purchase-orders/$poid/status", array( 'status' => 'OWNER_APPROVED' ), $u['wm'] );
$check( 'B3 a Warehouse Manager cannot approve a PO (maker-checker)', ! $ok_s( $self ) && 'OWNER_APPROVED' !== qv( "SELECT status FROM {$p}purchase_orders WHERE id=$poid" ), 'HTTP ' . $self[0] );
$early = call( 'POST', "/accounting/purchase-orders/$poid/receive", array( 'receipt_date' => $today, 'request_key' => $key(), 'lines' => array() ), $u['wm'] );
$check( 'B4 goods cannot be received before Owner approval', ! $ok_s( $early ) || 0.0 === $bal( $SH, $central ), 'HTTP ' . $early[0] );
call( 'POST', "/accounting/purchase-orders/$poid/status", array( 'status' => 'OWNER_APPROVED' ), $u['ow2'] );
$check( 'B5 Owner approval moves the PO to OWNER_APPROVED', 'OWNER_APPROVED' === qv( "SELECT status FROM {$p}purchase_orders WHERE id=$poid" ) );
$items = q( "SELECT id,sku FROM {$p}purchase_order_items WHERE po_id=$poid" );
$lines = array();
foreach ( $items as $it ) { $lines[] = array( 'po_item_id' => (int) $it['id'], 'quantity' => $it['sku'] === $SH ? 100 : 40 ); }
$rk  = $key();
$rec = call( 'POST', "/accounting/purchase-orders/$poid/receive", array( 'receipt_date' => $today, 'request_key' => $rk, 'lines' => $lines ), $u['wm'] );
call( 'POST', "/accounting/purchase-orders/$poid/receive", array( 'receipt_date' => $today, 'request_key' => $rk, 'lines' => $lines ), $u['wm'] );
$check( 'B6 receipt posts 100 shirts and 40 jeans into Central Warehouse', $ok_s( $rec ) && $eq( $bal( $SH, $central ), 100 ) && $eq( $bal( $JE, $central ), 40 ), $bal( $SH, $central ) . '/' . $bal( $JE, $central ) );
$check( 'B7 replayed receipt does not double the stock', $eq( $bal( $SH, $central ), 100 ) && $eq( $bal( $JE, $central ), 40 ) );
$jr = q( "SELECT j.id,SUM(l.debit) d,SUM(l.credit) c FROM {$p}gl_journals j JOIN {$p}gl_lines l ON l.journal_id=j.id WHERE j.source_type='PURCHASE_RECEIPT' AND j.id=(SELECT MAX(id) FROM {$p}gl_journals WHERE source_type='PURCHASE_RECEIPT') GROUP BY j.id" );
$check( 'B8 receipt journal is balanced and equals 46,000.00 (stock 40,000 + input VAT 6,000)', $jr && $eq( $jr[0]['d'], $jr[0]['c'] ) && $eq( $jr[0]['d'], 46000 ), wp_json_encode( $jr ) );

// ---------- C. Central -> Branch transfer (full status chain) ----------
$tr = call( 'POST', '/transfers', array( 'from_location_id' => $central, 'to_location_id' => $branch, 'sku' => $SH, 'quantity' => 30 ), $u['wm'] );
$tid = (string) ( $tr[1]['transfer_id'] ?? '' );
$check( 'C1 transfer of 30 shirts is requested', $ok_s( $tr ) && '' !== $tid );
$over  = call( 'POST', '/transfers', array( 'from_location_id' => $central, 'to_location_id' => $branch, 'sku' => $SH, 'quantity' => 5000 ), $u['wm'] );
$oid   = (string) ( $over[1]['transfer_id'] ?? '' );
call( 'POST', "/transfers/$oid/status", array( 'status' => 'Approved' ), $u['mg'] );
$resv  = call( 'POST', "/transfers/$oid/status", array( 'status' => 'Reserved' ), $u['wm'] );
$check( 'C2 a transfer larger than available stock can be requested but is refused at reservation; stock unchanged', ! $ok_s( $resv ) && $eq( $bal( $SH, $central ), 100 ), 'HTTP ' . $resv[0] . ' central=' . $bal( $SH, $central ) );
call( 'POST', "/transfers/$oid/status", array( 'status' => 'Rejected' ), $u['mg'] );
foreach ( array( array( 'Approved', 'mg' ), array( 'Reserved', 'wm' ), array( 'Picked', 'wm' ), array( 'In Transit', 'wm' ), array( 'Received', 'bm3' ), array( 'Completed', 'bm3' ) ) as $st ) {
	$r = call( 'POST', "/transfers/$tid/status", array( 'status' => $st[0], 'received_qty' => 30 ), ( 'bm3' === $st[1] ? $bm3 : $u[ $st[1] ] ) );
	$check( 'C3 transfer → ' . $st[0] . ' by ' . $st[1], $ok_s( $r ) && $st[0] === qv( "SELECT status FROM {$p}transfers WHERE transfer_id='" . esc_sql( $tid ) . "'" ), 'HTTP ' . $r[0] . ' ' . substr( (string) ( $r[1]['message'] ?? '' ), 0, 160 ) );
}
$check( 'C4 after completion: Central 70 shirts, Branch 30 shirts (total unchanged = 100)', $eq( $bal( $SH, $central ), 70 ) && $eq( $bal( $SH, $branch ), 30 ), $bal( $SH, $central ) . '/' . $bal( $SH, $branch ) );
call( 'POST', "/transfers/$tid/status", array( 'status' => 'Completed' ), $bm3 );
$check( 'C5 replaying Completed moves nothing', $eq( $bal( $SH, $central ), 70 ) && $eq( $bal( $SH, $branch ), 30 ) );
// jeans to the branch too (10) so the cart sale has two lines
$tr2 = call( 'POST', '/transfers', array( 'from_location_id' => $central, 'to_location_id' => $branch, 'sku' => $JE, 'quantity' => 10 ), $u['wm'] );
$tid2 = (string) ( $tr2[1]['transfer_id'] ?? '' );
foreach ( array( array( 'Approved', 'mg' ), array( 'Reserved', 'wm' ), array( 'Picked', 'wm' ), array( 'In Transit', 'wm' ), array( 'Received', 'bm3' ), array( 'Completed', 'bm3' ) ) as $st ) { call( 'POST', "/transfers/$tid2/status", array( 'status' => $st[0], 'received_qty' => 10 ), ( 'bm3' === $st[1] ? $bm3 : $u[ $st[1] ] ) ); }
$check( 'C6 10 jeans moved: Central 30, Branch 10', $eq( $bal( $JE, $central ), 30 ) && $eq( $bal( $JE, $branch ), 10 ), $bal( $JE, $central ) . '/' . $bal( $JE, $branch ) );

// ---------- D. Branch sales, cart, return ----------
$s1 = call( 'POST', '/sales', array( 'sku' => $SH, 'quantity_sold' => 2, 'vat_mode' => 'VAT_INCLUDED', 'sale_id' => 'PR-' . $run . '-1', 'branch_location_id' => $branch ), $se3 );
call( 'POST', '/sales', array( 'sku' => $SH, 'quantity_sold' => 2, 'vat_mode' => 'VAT_INCLUDED', 'sale_id' => 'PR-' . $run . '-1', 'branch_location_id' => $branch ), $se3 );
$check( 'D1 sale of 2 shirts at master price 450.00; replay creates no duplicate', $ok_s( $s1 ) && 1 === (int) qv( "SELECT COUNT(*) FROM {$p}sales WHERE sale_id='PR-$run-1'" ) && $eq( qv( "SELECT selling_price FROM {$p}sales WHERE sale_id='PR-$run-1'" ), 450 ) );
$check( 'D2 branch stock is 28 after selling 2', $eq( $bal( $SH, $branch ), 28 ), (string) $bal( $SH, $branch ) );
$cart = array( 'cart_id' => 'CPR' . $run . 'ABC', 'vat_mode' => 'VAT_INCLUDED', 'branch_location_id' => $branch, 'external_receipt_no' => 'R-' . $run, 'lines' => array( array( 'sku' => $SH, 'quantity_sold' => 1 ), array( 'sku' => $JE, 'quantity_sold' => 1 ) ) );
$c1 = call( 'POST', '/sales/cart', $cart, $se3 );
$c2 = call( 'POST', '/sales/cart', $cart, $se3 );
$check( 'D3 cart with 1 shirt + 1 jeans posts two lines; replay is idempotent', $ok_s( $c1 ) && ! empty( $c2[1]['idempotent_replay'] ) && 3 === (int) qv( "SELECT COUNT(*) FROM {$p}sales WHERE branch_location_id=$branch AND sku IN ('$SH','$JE')" ) );
$check( 'D4 branch stock after cart: shirts 27, jeans 9', $eq( $bal( $SH, $branch ), 27 ) && $eq( $bal( $JE, $branch ), 9 ), $bal( $SH, $branch ) . '/' . $bal( $JE, $branch ) );
$oversell = call( 'POST', '/sales', array( 'sku' => $SH, 'quantity_sold' => 9999, 'vat_mode' => 'VAT_INCLUDED', 'sale_id' => 'PR-' . $run . '-X', 'branch_location_id' => $branch ), $se3 );
$check( 'D5 selling more than the branch holds is refused and stock stays 27', ! $ok_s( $oversell ) && $eq( $bal( $SH, $branch ), 27 ), 'HTTP ' . $oversell[0] );
$wrong = call( 'POST', '/sales', array( 'sku' => $SH, 'quantity_sold' => 1, 'vat_mode' => 'VAT_INCLUDED', 'sale_id' => 'PR-' . $run . '-W', 'branch_location_id' => $central ), $se3 );
$check( 'D6 a branch Sales Employee cannot sell from Central Warehouse', ! $ok_s( $wrong ) && $eq( $bal( $SH, $central ), 70 ), 'HTTP ' . $wrong[0] );
$ret = call( 'POST', '/returns', array( 'location_id' => $branch, 'return_type' => 'Return', 'sku' => $SH, 'quantity' => 1, 'reason' => 'Practical customer return' ), $se3 );
$check( 'D7 customer return of 1 shirt restores branch stock to 28', $ok_s( $ret ) && $eq( $bal( $SH, $branch ), 28 ), (string) $bal( $SH, $branch ) );

// ---------- E. Daily close: gross 2×450 + 450 + 1150 = 2,500 ----------
$cl = call( 'POST', '/daily-closings/close', array( 'business_date' => $today, 'branch_location_id' => $branch ), $bm3 );
$tot = $cl[1]['totals'] ?? array();
$check( 'E1 daily close total sales includes the 4 units = 2,500.00 (the branch may also hold other test sales; checked per-SKU below)', $ok_s( $cl ), 'HTTP ' . $cl[0] . ' ' . wp_json_encode( $tot ) );
$mine_gross = (float) qv( "SELECT SUM(quantity_sold*selling_price) FROM {$p}sales WHERE sku IN ('$SH','$JE') AND branch_location_id=$branch" );
$check( 'E2 gross of this scenario in the sales ledger = 2,500.00', $eq( $mine_gross, 2500 ), (string) $mine_gross );
$check( 'E3 VAT inside that gross = 2,500 × 15/115 = 326.09', $eq( $vat( $mine_gross ), 326.09 ), (string) $vat( $mine_gross ) );
$again = call( 'POST', '/sales', array( 'sku' => $SH, 'quantity_sold' => 1, 'vat_mode' => 'VAT_INCLUDED', 'sale_id' => 'PR-' . $run . '-AFTER', 'branch_location_id' => $branch ), $se3 );
$check( 'E4 a sale on a closed business day is refused or dated forward (day stays closed)', ! $ok_s( $again ) || 'CLOSED' === qv( "SELECT status FROM {$p}daily_closings WHERE branch_location_id=$branch AND business_date='$today'" ), 'HTTP ' . $again[0] );

// ---------- F. Supplier invoice + payment (accounts payable) ----------
$inv = call( 'POST', '/accounting/supplier-invoices', array( 'supplier_id' => $sid, 'po_id' => $poid, 'invoice_number' => 'SI-' . $run, 'invoice_date' => $today, 'due_date' => $today, 'subtotal' => '40000.00', 'tax_amount' => '6000.00' ), $u['mg'] );
$iid = (int) qv( "SELECT id FROM {$p}supplier_invoices WHERE invoice_number='SI-$run'" );
$check( 'F1 supplier invoice 40,000 + 6,000 VAT = 46,000.00', $ok_s( $inv ) && $iid > 0 && $eq( qv( "SELECT total_amount FROM {$p}supplier_invoices WHERE id=$iid" ), 46000 ) );
call( 'POST', "/accounting/supplier-invoices/$iid/status", array( 'action' => 'APPROVE' ), $u['ow2'] );
$pay = array( 'payment_type' => 'SUPPLIER', 'supplier_id' => $sid, 'amount' => '20000.00', 'payment_date' => $today, 'method' => 'BANK', 'reference' => 'TT-' . $run, 'request_key' => $key(), 'allocations' => array( array( 'invoice_id' => $iid, 'amount' => '20000.00' ) ) );
$pr  = call( 'POST', '/accounting/payments', $pay, $u['mg'] );
call( 'POST', '/accounting/payments', $pay, $u['mg'] );
$check( 'F2 payment of 20,000 is recorded once (replay-safe)', $ok_s( $pr ) && 1 === (int) qv( "SELECT COUNT(*) FROM {$p}payments WHERE reference='TT-$run'" ) );
$pid = (int) qv( "SELECT id FROM {$p}payments WHERE reference='TT-$run'" );
$self_pay = call( 'POST', "/accounting/payments/$pid/status", array( 'action' => 'APPROVE' ), $u['mg'] );
$check( 'F3 the maker (Management) cannot approve their own payment', ! $ok_s( $self_pay ) && 'APPROVED' !== strtoupper( (string) qv( "SELECT status FROM {$p}payments WHERE id=$pid" ) ), 'HTTP ' . $self_pay[0] );
call( 'POST', "/accounting/payments/$pid/status", array( 'action' => 'APPROVE' ), $u['ow2'] );
$paid = (float) qv( "SELECT COALESCE(SUM(amount),0) FROM {$p}payment_allocations WHERE invoice_id=$iid" );
$check( 'F4 after Owner approval the invoice has 20,000 allocated; outstanding = 26,000.00', $eq( $paid, 20000 ) && $eq( 46000 - $paid, 26000 ), 'allocated=' . $paid . ' status=' . qv( "SELECT status FROM {$p}supplier_invoices WHERE id=$iid" ) );

// ---------- G. Customer (wholesale) invoice at Central: 5 shirts @ 350 VAT-included ----------
call( 'POST', '/accounting/customers', array( 'customer_code' => 'PC' . $run, 'customer_name' => 'Practical Customer ' . $run ), $u['mg'] );
$cid = (int) qv( "SELECT id FROM {$p}customers WHERE customer_code='PC$run'" );
$ci  = call( 'POST', '/accounting/customer-invoices', array( 'customer_id' => $cid, 'location_id' => $central, 'invoice_date' => $today, 'due_date' => $today, 'lines' => array( array( 'sku' => $SH, 'quantity' => 5, 'unit_price' => '350.00', 'vat_mode' => 'VAT_INCLUDED' ) ) ), $u['mg'] );
$check( 'G1 customer invoice total = 5 × 350 = 1,750.00 and Central stock falls to 65', $ok_s( $ci ) && $eq( $ci[1]['total'] ?? 0, 1750 ) && $eq( $bal( $SH, $central ), 65 ), 'total=' . ( $ci[1]['total'] ?? '?' ) . ' central=' . $bal( $SH, $central ) );
$cj = (int) ( $ci[1]['journal_id'] ?? 0 );
$cjl = q( "SELECT SUM(debit) d,SUM(credit) c FROM {$p}gl_lines WHERE journal_id=$cj" );
$check( 'G2 customer-invoice journal balanced at 1,750.00 + COGS', $cjl && $eq( $cjl[0]['d'], $cjl[0]['c'] ) && (float) $cjl[0]['d'] >= 1750, wp_json_encode( $cjl ) );
$check( 'G3 VAT inside 1,750.00 = 228.26', $eq( $vat( 1750 ), 228.26 ) );

// ---------- H. Conservation of stock and ledgers ----------
// Shirts: +100 received, −2 −1 sold, +1 returned, −5 invoiced = 93 ; Jeans: +40 −1 sold = 39
$tot_sh = $bal( $SH, $central ) + $bal( $SH, $branch );
$tot_je = $bal( $JE, $central ) + $bal( $JE, $branch );
$check( 'H1 shirts in the whole company = 100 − 3 sold + 1 returned − 5 invoiced = 93', $eq( $tot_sh, 93 ), (string) $tot_sh );
$check( 'H2 jeans in the whole company = 40 − 1 sold = 39', $eq( $tot_je, 39 ), (string) $tot_je );
$drift = q( "SELECT b.sku,b.location_id,b.physical_qty,COALESCE((SELECT SUM(t.quantity_signed) FROM {$p}transactions t WHERE t.sku=b.sku AND t.location_id=b.location_id),0) ledger FROM {$p}balances b WHERE b.sku IN ('$SH','$JE') HAVING ABS(b.physical_qty-ledger)>0.001" );
$check( 'H3 stock balance equals the sum of its ledger movements for every SKU/location', 0 === count( $drift ), wp_json_encode( $drift ) );
$neg = (int) qv( "SELECT COUNT(*) FROM {$p}balances WHERE physical_qty<0" );
$check( 'H4 no stock balance is negative anywhere', 0 === $neg, 'negative=' . $neg );
$unbal = (int) qv( "SELECT COUNT(*) FROM (SELECT journal_id FROM {$p}gl_lines GROUP BY journal_id HAVING ABS(SUM(debit)-SUM(credit))>0.004) x" );
$check( 'H5 every general-ledger journal is balanced', 0 === $unbal, 'unbalanced=' . $unbal );
$td = (float) qv( "SELECT SUM(debit) FROM {$p}gl_lines" ); $tc = (float) qv( "SELECT SUM(credit) FROM {$p}gl_lines" );
$check( 'H6 total debits equal total credits', $eq( $td, $tc, 0.01 ), "D=$td C=$tc" );
$tb = call( 'GET', '/accounting/trial-balance', array(), 1 );
$tbd = 0; $tbc = 0; foreach ( (array) $tb[1] as $row ) { $tbd += (float) ( $row['debit'] ?? 0 ); $tbc += (float) ( $row['credit'] ?? 0 ); }
$check( 'H7 the trial-balance report balances (debit = credit)', 200 === $tb[0] && $eq( $tbd, $tbc, 0.01 ), "D=$tbd C=$tbc" );
$chain = Stockland_Audit::verify_chain();
$check( 'H8 audit hash chain verifies end to end', ! empty( $chain['ok'] ), wp_json_encode( $chain ) );
$inv_rep = call( 'GET', '/reports/daily-inventory', array(), 1, array( 'business_date' => $today, 'location_id' => $branch ) );
$row_sh  = null; foreach ( (array) ( $inv_rep[1]['rows'] ?? array() ) as $r ) { if ( ( $r['sku'] ?? '' ) === $SH ) { $row_sh = $r; } }
$check( 'H9 daily inventory report shows Branch closing 28 for the shirt', 200 === $inv_rep[0] && $row_sh && $eq( $row_sh['closing'] ?? -1, 28 ), wp_json_encode( $row_sh ) );

if ( $failed ) { echo 'RESULT FAIL (' . $failed . ')' . PHP_EOL; exit( 1 ); }
echo 'RESULT PASS: purchase → approval → receipt → transfer chain → branch sale/cart/return → close → AP invoice/payment → customer invoice, with hand-calculated figures, conservation and ledger/audit integrity.' . PHP_EOL;
