<?php
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A','ARRAY_A');
$GLOBALS['options']=array('stockland_gitbook_bridge_token'=>'stkgb_test_secret');
function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
function add_option($k,$v,$x='',$a=true){$GLOBALS['options'][$k]=$v;return true;}
function update_option($k,$v,$a=null){$GLOBALS['options'][$k]=$v;return true;}
function wp_generate_password($l=12,$s=true,$e=false){return str_repeat('A',$l);}
function add_action($h,$c){}
function register_activation_hook($f,$c){}
function add_options_page(){}
function register_rest_route($n,$r,$a){$GLOBALS['routes'][$n.$r]=$a;}
function rest_url($p=''){return 'https://example.test/wp-json/'.$p;}
function current_user_can($c){return true;}
function check_admin_referer($a){}
function wp_nonce_field($a){}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function esc_attr($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function get_bloginfo($k){return '7.1-test';}
function rest_ensure_response($v){return $v;}
function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower($v));}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function absint($v){return abs((int)$v);}
function wp_json_encode($v,$f=0){return json_encode($v,$f);}
function is_wp_error($v){return $v instanceof WP_Error;}
class WP_Error {private $m;function __construct($c,$m,$d=array()){$this->m=$m;}function get_error_message(){return $this->m;}}
class WP_REST_Response {public $data,$status;function __construct($d,$s=200){$this->data=$d;$this->status=$s;}}
class WP_REST_Request {private $b,$h;function __construct($b=array(),$h=array()){$this->b=$b;$this->h=array_change_key_case($h,CASE_LOWER);}function get_json_params(){return $this->b;}function get_header($n){return $this->h[strtolower($n)]??'';}}
class FakeWpdb {public $prefix='wp_',$last_error='';function prepare($s,...$a){return array($s,$a);}function esc_like($s){return addcslashes($s,'_%\\');}function get_var($q){return 'wp_stock_products';}function get_results($q,$f){return array(array('variant_id'=>1,'sku'=>'STK-001','product_name'=>'Sample Shirt','product_type'=>'VARIANT','brand'=>'Sample','color'=>'Blue','size'=>'M','is_active'=>1));}function get_row($q,$f){return array('variant_id'=>1,'sku'=>'STK-001','product_name'=>'Sample Shirt','product_type'=>'VARIANT','brand'=>'Sample','color'=>'Blue','size'=>'M','is_active'=>1);}}
$GLOBALS['wpdb']=new FakeWpdb();
require __DIR__.'/../stockland-gitbook-bridge.php';
$bridge=(new ReflectionClass('Stockland_GitBook_Bridge'))->newInstanceWithoutConstructor();
$good=new WP_REST_Request(array('jsonrpc'=>'2.0','id'=>1,'method'=>'initialize'),array('Authorization'=>'Bearer stkgb_test_secret'));
if(true!==$bridge->authorize_request($good)){fwrite(STDERR,"valid bearer rejected\n");exit(1);}
$bad=new WP_REST_Request(array(),array('Authorization'=>'Bearer wrong'));
if(!is_wp_error($bridge->authorize_request($bad))){fwrite(STDERR,"invalid bearer accepted\n");exit(1);}
$init=$bridge->handle_mcp($good);if(!isset($init['result']['serverInfo']['name'])){fwrite(STDERR,"initialize failed\n");exit(1);}
$list=$bridge->handle_mcp(new WP_REST_Request(array('id'=>2,'method'=>'tools/list')));
if(count($list['result']['tools']??array())!==4){fwrite(STDERR,"tool list mismatch\n");exit(1);}
$call=function($id,$name,$args)use($bridge){return $bridge->handle_mcp(new WP_REST_Request(array('id'=>$id,'method'=>'tools/call','params'=>array('name'=>$name,'arguments'=>$args))));};
$s=$call(3,'stockland_product_search',array('query'=>'STK-001','limit'=>10));
$j=json_decode($s['result']['content'][0]['text']??'',true);if(($j['products'][0]['sku']??'')!=='STK-001'){fwrite(STDERR,"catalog search failed\n");exit(1);}
$l=$call(4,'stockland_sku_lookup',array('sku'=>'STK-001'));$j=json_decode($l['result']['content'][0]['text']??'',true);if(empty($j['found'])){fwrite(STDERR,"exact SKU lookup failed\n");exit(1);}
$c=$call(5,'stockland_product_import_contract',array());$j=json_decode($c['result']['content'][0]['text']??'',true);if(!in_array('product_name',$j['required_fields']??array(),true)){fwrite(STDERR,"import contract failed\n");exit(1);}
$u=$call(6,'stockland_post_transaction',array());if(empty($u['result']['isError'])){fwrite(STDERR,"unknown write tool not rejected\n");exit(1);}
echo "PASS: bearer auth; initialize; tools/list; product search; exact SKU lookup; import contract; unknown tool denied\n";
