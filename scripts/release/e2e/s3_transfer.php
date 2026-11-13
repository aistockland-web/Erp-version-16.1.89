<?php
require __DIR__ . '/lib.php';
$u=get_option('e2e_users');
$t=show('transfer create (whmgr)',call('POST','/transfers',array('from_location_id'=>1,'to_location_id'=>2,'sku'=>'STK-TEE-001','quantity'=>30),$u['wm']));
$transfer_data = is_array( $t[1] ?? null ) ? $t[1] : array();
$tid = sanitize_text_field( (string) ( $transfer_data['transfer_id'] ?? '' ) );
if ( '' === $tid ) {
  echo "FAIL transfer create did not return a transfer_id; stopping transfer notification E2E.\n";
  exit( 1 );
}
update_option( 'stockland_e2e_transfer_notification_fixture', array( 'transfer_id' => $tid, 'requester_id' => (int) $u['wm'] ), false );
foreach(array(array('Approved','mg'),array('Reserved','wm'),array('Picked','wm'),array('In Transit','wm'),array('Received','bm'),array('Completed','bm')) as $st){
  show("transfer -> {$st[0]} ({$st[1]})",call('POST',"/transfers/$tid/status",array('status'=>$st[0],'received_qty'=>30),$u[$st[1]]));
  echo "     status=".qv("select status from wp_stock_transfers where transfer_id='$tid'")." bal=".json_encode(q("select location_id,physical_qty from wp_stock_balances order by location_id"))."\n";
}
echo "tx: ".json_encode(q("select movement_type,quantity_signed,location_id,reference_type,reference_id from wp_stock_transactions"))."\n";
// replay completion
show('transfer Completed REPLAY',call('POST',"/transfers/$tid/status",array('status'=>'Completed'),$u['bm']));
echo "bal after replay: ".json_encode(q("select location_id,physical_qty from wp_stock_balances order by location_id"))."\n";
