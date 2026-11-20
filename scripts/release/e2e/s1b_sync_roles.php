<?php
$u=get_option('e2e_users');
$map=array('mg'=>Stockland_Roles::ROLE_MANAGEMENT,'wm'=>Stockland_Roles::ROLE_WAREHOUSE_MANAGER,'bm'=>Stockland_Roles::ROLE_BRANCH_MANAGER,'se'=>Stockland_Roles::ROLE_SALES_EMPLOYEE,'ow2'=>Stockland_Roles::ROLE_OWNER);
foreach($map as $k=>$r){ $x=new WP_User($u[$k]); $x->set_role($r); echo "$k -> ".implode(',',$x->roles)."\n"; }
Stockland_Roles::sync_roles();

$fail = 0;
$check = static function ( $label, $condition ) use ( &$fail ) {
	echo ( $condition ? 'PASS ' : 'FAIL ' ) . $label . "\n";
	if ( ! $condition ) { ++$fail; }
};

// Platform-only Administrator fixture: no Stockland profile is created for this account.
$platform_admin = get_user_by( 'login', 'e2e-platform-admin' );
if ( ! $platform_admin ) {
	$platform_admin_id = wp_insert_user( array(
		'user_login' => 'e2e-platform-admin',
		'user_pass'  => 'Pw!e2e-platform-admin12345',
		'user_email' => 'e2e-platform-admin@example.com',
		'role'       => 'administrator',
	) );
} else {
	$platform_admin_id = (int) $platform_admin->ID;
}
$check( 'platform administrator fixture exists', ! is_wp_error( $platform_admin_id ) && (int) $platform_admin_id > 0 );
$admin_role = get_role( 'administrator' );
$check( 'shared Administrator role has no Stockland Owner capability', $admin_role && ! $admin_role->has_cap( 'stockland_owner' ) );
$check( 'platform Administrator retains WordPress platform access', user_can( (int) $platform_admin_id, 'manage_options' ) );
$check( 'platform-only Administrator is not a Stockland business Owner', ! Stockland_Authority::is_owner( (int) $platform_admin_id ) );
$platform_admin_user = new WP_User( (int) $platform_admin_id );
$platform_admin_user->add_cap( 'stockland_owner' );
$check( 'a direct stale Owner capability cannot bypass an active Stockland Owner profile', ! Stockland_Authority::is_owner( (int) $platform_admin_id ) );
$platform_admin_user->remove_cap( 'stockland_owner' );
$active_owner_id = (int) ( $u['ow2'] ?? 0 );
$check( 'active Stockland Owner profile remains authorized', $active_owner_id > 0 && Stockland_Authority::is_owner( $active_owner_id ) );
if ( $fail ) { exit( 1 ); }
