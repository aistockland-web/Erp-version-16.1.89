<?php
require __DIR__ . '/lib.php';
$u=get_option('e2e_users'); $today=current_time('Y-m-d'); echo "users: ".json_encode($u)."\n";
show('dup customer',call('POST','/accounting/customers',array('customer_code'=>'CUS1','customer_name'=>'E2E Customer'),$u['mg']));
$cid=(int)qv("select id from wp_stock_customers where customer_code='CUS1'");
foreach(array('mg','ow2','ac') as $k){ if(!isset($u[$k])) continue;
 $r=show("customer invoice ($k)",call('POST','/accounting/customer-invoices',array('customer_id'=>$cid,'location_id'=>1,'invoice_date'=>$today,'due_date'=>$today,'lines'=>array(array('sku'=>'STK-TEE-001','quantity'=>5,'unit_price'=>'350.00','vat_mode'=>'VAT_INCLUDED'))),$u[$k])); if($r[0]==200||$r[0]==201) break; }
echo "cust inv: ".json_encode(q("select id,status,total_amount from wp_stock_customer_invoices"))." bal1=".qv("select physical_qty from wp_stock_balances where location_id=1")."\n";
