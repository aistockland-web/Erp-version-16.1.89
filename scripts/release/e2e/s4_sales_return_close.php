<?php
require __DIR__ . '/lib.php';
$u=get_option('e2e_users'); $today=current_time('Y-m-d');
$s1=show('sale create (sales1)',call('POST','/sales',array('sku'=>'STK-TEE-001','quantity_sold'=>2,'vat_mode'=>'VAT_INCLUDED','sale_id'=>'S-E2E-1','branch_location_id'=>2),$u['se']));
show('sale REPLAY same sale_id',call('POST','/sales',array('sku'=>'STK-TEE-001','quantity_sold'=>2,'vat_mode'=>'VAT_INCLUDED','sale_id'=>'S-E2E-1','branch_location_id'=>2),$u['se']));
echo "sales rows: ".json_encode(q("select sale_id,sku,quantity_sold,selling_price,posting_status from wp_stock_sales"))." bal2=".qv("select physical_qty from wp_stock_balances where location_id=2")."\n";
show('cart sale (sales1)',call('POST','/sales/cart',array('cart_id'=>'CE2ECART001','vat_mode'=>'VAT_INCLUDED','branch_location_id'=>2,'lines'=>array(array('sku'=>'STK-TEE-001','quantity_sold'=>1)),'external_receipt_no'=>'R-1'),$u['se']));
show('cart REPLAY',call('POST','/sales/cart',array('cart_id'=>'CE2ECART001','vat_mode'=>'VAT_INCLUDED','branch_location_id'=>2,'lines'=>array(array('sku'=>'STK-TEE-001','quantity_sold'=>1)),'external_receipt_no'=>'R-1'),$u['se']));
echo "sales rows: ".qv("select count(*) from wp_stock_sales")." bal2=".qv("select physical_qty from wp_stock_balances where location_id=2")."\n";
show('return (sales1)',call('POST','/returns',array('location_id'=>2,'return_type'=>'Return','sku'=>'STK-TEE-001','quantity'=>1,'reason'=>'E2E customer return'),$u['se']));
echo "returns: ".json_encode(q("select * from wp_stock_returns"))."\n bal2=".qv("select physical_qty from wp_stock_balances where location_id=2")."\n";
show('daily close (brmgr)',call('POST','/daily-closings/close',array('business_date'=>$today,'branch_location_id'=>2),$u['bm']));
echo "closings: ".json_encode(q("select * from wp_stock_daily_closings"))."\n";
echo "tx: ".json_encode(q("select movement_type,quantity_signed,location_id,reference_type from wp_stock_transactions order by id"))."\n";
echo "GL journals: ".json_encode(q("select j.id,j.status,j.source_type,sum(l.debit) d,sum(l.credit) c from wp_stock_gl_journals j join wp_stock_gl_lines l on l.journal_id=j.id group by j.id"))."\n";
