<?php
global $wpdb;
function mkuser($login,$wprole,$prole,$branch=null){ global $wpdb;
  $u=get_user_by('login',$login); if(!$u){ $id=wp_insert_user(array('user_login'=>$login,'user_pass'=>'Pw!'.$login.'12345','user_email'=>"$login@example.com",'role'=>$wprole)); } else $id=$u->ID;
  $t=$wpdb->prefix.'stock_users'; $wpdb->query($wpdb->prepare("INSERT INTO $t (wp_user_id,role,branch_location_id,is_active) VALUES (%d,%s,%s,1) ON DUPLICATE KEY UPDATE role=VALUES(role),branch_location_id=VALUES(branch_location_id),is_active=1",$id,$prole,$branch));
  return $id; }
function call($m,$route,$body=array(),$uid=1,$q=array()){
  wp_set_current_user($uid); $r=new WP_REST_Request($m,'/stockland/v16'.$route);
  foreach($q as $k=>$v)$r->set_param($k,$v);
  if($body){ $r->set_header('content-type','application/json'); $r->set_body(wp_json_encode($body)); foreach($body as $k=>$v)$r->set_param($k,$v);}
  $res=rest_do_request($r); $d=$res->get_data(); return array($res->get_status(),$d); }
function show($label,$res){ list($s,$d)=$res; $x=is_array($d)?(isset($d['message'])?$d['message']:json_encode($d)):json_encode($d); echo str_pad($label,34)." HTTP $s  ".substr($x,0,230)."\n"; return $res; }
function q($sql){ global $wpdb; return $wpdb->get_results($sql,ARRAY_A); }
function qv($sql){ global $wpdb; return $wpdb->get_var($sql); }
