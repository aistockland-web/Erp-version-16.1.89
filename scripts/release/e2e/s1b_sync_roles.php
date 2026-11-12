<?php
$u=get_option('e2e_users');
$map=array('mg'=>Stockland_Roles::ROLE_MANAGEMENT,'wm'=>Stockland_Roles::ROLE_WAREHOUSE_MANAGER,'bm'=>Stockland_Roles::ROLE_BRANCH_MANAGER,'se'=>Stockland_Roles::ROLE_SALES_EMPLOYEE,'ow2'=>Stockland_Roles::ROLE_OWNER);
foreach($map as $k=>$r){ $x=new WP_User($u[$k]); $x->set_role($r); echo "$k -> ".implode(',',$x->roles)."\n"; }
Stockland_Roles::sync_roles();
