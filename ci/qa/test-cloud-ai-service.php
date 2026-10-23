<?php
/** Offline behavior tests for Stockland Cloud AI; no provider request leaves this test process. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'STOCKLAND_CLOUD_AI_API_KEY', 'sk-test-server-only-secret-123456789' );
define( 'STOCKLAND_CLOUD_AI_MODEL', 'gpt-test-model' );
class WP_Error { public $code; public $message; public $data; public function __construct( $c, $m, $d = array() ) { $this->code=$c; $this->message=$m; $this->data=$d; } public function get_error_code(){return $this->code;} public function get_error_message(){return $this->message;} public function get_error_data(){return $this->data;} }
function is_wp_error($v){return $v instanceof WP_Error;}
function is_user_logged_in(){return true;}
function get_current_user_id(){return 7;}
function sanitize_textarea_field($s){return trim(strip_tags((string)$s));}
function sanitize_text_field($s){return trim(strip_tags((string)$s));}
function wp_json_encode($v){return json_encode($v);}
function wp_strip_all_tags($s){return strip_tags($s);}
function rest_ensure_response($v){return $v;}
function get_transient($k){return $GLOBALS['transient'][$k]??0;}
function set_transient($k,$v,$ttl){$GLOBALS['transient'][$k]=$v;return true;}
function wp_remote_post($url,$args){$GLOBALS['requests'][]=array('url'=>$url,'args'=>$args);return array('response'=>array('code'=>200),'body'=>json_encode(array('output_text'=>'Sales rose based on the supplied aggregate facts.')));}
function wp_remote_retrieve_response_code($r){return $r['response']['code']??0;}
function wp_remote_retrieve_body($r){return $r['body']??'';}
class Stockland_Authority { public static function profile(){return array('id'=>7);} public static function is_management(){return true;} }
class Stockland_Audit { public static function log(...$args){$GLOBALS['audit'][]=$args;return true;} }
function cloud_ai_check($ok,$label){echo ($ok?'PASS ':'FAIL ').$label."\n";if(!$ok)$GLOBALS['fails']++;}
$plugin=isset($argv[1])?rtrim($argv[1],'/\\'):'';
if(!$plugin||!is_file($plugin.'/includes/class-stockland-cloud-ai.php')){fwrite(STDERR,"Pass the extracted stockland-erp-app path.\n");exit(2);}
require_once $plugin.'/includes/class-stockland-cloud-ai.php';
$GLOBALS['fails']=0;$GLOBALS['requests']=array();$GLOBALS['audit']=array();$GLOBALS['transient']=array();
$status=Stockland_Cloud_AI::configuration_status();
cloud_ai_check(true===$status['configured']&&'gpt-test-model'===$status['model']&&!array_key_exists('key',$status),'configuration status is secret-free');
$insights=array('range'=>array('from'=>'2026-10-17','to'=>'2026-10-24','month_from'=>'2026-10-01'),'trend'=>array(array('date'=>'2026-10-24','sales'=>1234)),'categories'=>array(array('category'=>'Kids','sales'=>1234)),'branches'=>array(array('location_name'=>'Goro','sales'=>1234)),'recent_sales'=>array(array('sale_id'=>'SENSITIVE-SALE-ID')),'employee_salary'=>987654,'customer_email'=>'hidden@example.com');
$rejected=Stockland_Cloud_AI::answer('Summarize sales',$insights,false);
cloud_ai_check(is_wp_error($rejected)&&'cloud_ai_consent_required'===$rejected->get_error_code()&&0===count($GLOBALS['requests']),'without consent no provider request is sent');
$out=Stockland_Cloud_AI::answer('Summarize sales',$insights,true);
cloud_ai_check(!is_wp_error($out)&&'Sales rose based on the supplied aggregate facts.'===$out['answer']&&true===$out['advisory_only']&&false===$out['transaction_posted'],'mocked provider response remains advisory and non-posting');
$r=$GLOBALS['requests'][0];$payload=json_decode($r['args']['body'],true);$context=$payload['input'][0]['content'][0]['text'];
cloud_ai_check('https://api.openai.com/v1/responses'===$r['url']&&false===$payload['store']&&!array_key_exists('tools',$payload)&&false!==strpos($r['args']['headers']['Authorization'],'sk-test-server-only-secret'),'fixed endpoint, store=false, no tools and server-only key');
cloud_ai_check(false!==strpos($context,'Goro')&&false!==strpos($context,'Kids')&&false===strpos($context,'SENSITIVE-SALE-ID')&&false===strpos($context,'hidden@example.com')&&false===strpos($context,'987654'),'only whitelisted aggregate context is sent');
cloud_ai_check(2===count($GLOBALS['audit'])&&'CLOUD_AI_REQUEST'===$GLOBALS['audit'][0][2]&&'CLOUD_AI_RESPONSE'===$GLOBALS['audit'][1][2]&&false===strpos(json_encode($GLOBALS['audit']),'Summarize sales'),'disclosure/response audit events omit the raw prompt/answer');
cloud_ai_check(1===($GLOBALS['transient']['stockland_cloud_ai_rate_7']??0),'per-user request counter is applied');
if($GLOBALS['fails'])exit(1);
