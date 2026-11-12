<?php
require __DIR__ . '/lib.php';
$mg=mkuser('mgmt','subscriber','management'); $wm=mkuser('whmgr','subscriber','warehouse_manager',1);
$bm=mkuser('brmgr','subscriber','branch_manager',2); $se=mkuser('sales1','subscriber','sales_employee',2); $ow2=mkuser('owner2','subscriber','owner');
Stockland_Roles::sync_roles();
foreach(array($mg,$wm,$bm,$se,$ow2) as $i){ $u=get_userdata($i); echo "user $i ".$u->user_login." profile=".Stockland_Authority::role($i)."\n"; }
update_option('e2e_users',array('mg'=>$mg,'wm'=>$wm,'bm'=>$bm,'se'=>$se,'ow2'=>$ow2));
// 1 product
show('product STYLE?',call('POST','/products',array('sku'=>'STK-TEE-001','product_name'=>'E2E Tee','category'=>'Tops','unit'=>'PCS','purchase_cost'=>'200.00','retail_price'=>'450.00','wholesale_price'=>'350.00','product_type'=>'SIMPLE')));
echo json_encode(q("select sku,product_type,variant_id,retail_price,is_active from wp_stock_products"))."\n";
