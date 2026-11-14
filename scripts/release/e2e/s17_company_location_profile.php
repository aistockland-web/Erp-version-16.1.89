<?php
// Company identity and branch address/relocation: editable in System Settings, validated, audited, Owner-only.
require_once __DIR__ . '/lib.php';
$fail = 0;
$check = function ( $label, $cond ) use ( &$fail ) {
	echo ( $cond ? 'PASS ' : 'FAIL ' ) . $label . "\n";
	if ( ! $cond ) {
		++$fail;
	}
};
$uid = static function ( $login ) {
	$u = get_user_by( 'login', $login );
	return $u ? (int) $u->ID : 0;
};
$owner = $uid( 'owner2' );
$brmgr = $uid( 'brmgr' );
$check( 'fixture users exist', $owner > 0 && $brmgr > 0 );

// Company settings.
list( $s ) = call( 'POST', '/app/settings', array( 'company_email' => 'not-an-email' ), $owner );
$check( 'invalid company email rejected', 400 === $s );
list( $s ) = call( 'POST', '/app/settings', array( 'company_website' => 'http://insecure.example.invalid' ), $owner );
$check( 'non-https website rejected', 400 === $s );
list( $s ) = call( 'POST', '/app/settings', array( 'company_phone' => 'call me maybe' ), $owner );
$check( 'invalid company phone rejected', 400 === $s );
list( $s ) = call( 'POST', '/app/settings', array( 'business_name' => str_repeat( 'x', 201 ) ), $owner );
$check( 'over-long setting rejected', 400 === $s );
list( $s ) = call( 'POST', '/app/settings', array( 'business_name' => 'Stockland Ethiopia', 'company_legal_name' => 'Stockland Trading PLC (test)', 'company_address' => 'Bole, Addis Ababa', 'company_phone' => '+251 11 000 0000', 'company_email' => 'info@stockland.example.invalid', 'company_website' => 'https://stockland.example.invalid', 'careers_url' => 'https://stockland.example.invalid/careers' ), $owner );
$check( 'owner saves company identity', 200 === $s );
list( $s, $d ) = call( 'GET', '/app/settings', array(), $owner );
$map = array();
foreach ( (array) $d as $row ) {
	$map[ $row['setting_key'] ] = $row['setting_value'];
}
$check( 'saved company identity reads back', 'Stockland Trading PLC (test)' === ( $map['company_legal_name'] ?? '' ) && 'https://stockland.example.invalid/careers' === ( $map['careers_url'] ?? '' ) );
list( $s ) = call( 'POST', '/app/settings', array( 'company_legal_name' => 'Hijack' ), $brmgr );
$check( 'branch manager cannot change company identity', in_array( $s, array( 401, 403 ), true ) );

// Branch address and relocation.
list( $s, $d ) = call( 'GET', '/organization/locations', array(), $owner );
$rows = array();
foreach ( (array) $d as $r ) {
	$rows[ (int) $r['id'] ] = $r;
}
$branch = 0;
foreach ( $rows as $id => $r ) {
	if ( 'Branch' === $r['location_type'] ) {
		$branch = $id;
		break;
	}
}
$check( 'locations list includes an address profile', 200 === $s && $branch > 0 && isset( $rows[ $branch ]['profile']['address_line'] ) );
$orig_name = $rows[ $branch ]['location_name'];
// Repeatable on a reused disposable site: start from a branch with no stored address.
global $wpdb;
$wpdb->delete( Stockland_DB::table( 'settings' ), array( 'setting_key' => 'location_profile_' . $branch ) );

list( $s ) = call( 'POST', "/organization/locations/$branch", array( 'profile' => array( 'maps_url' => 'http://insecure.example.invalid/map' ) ), $owner );
$check( 'non-https map link rejected', 400 === $s );
list( $s ) = call( 'POST', "/organization/locations/$branch", array( 'profile' => array( 'phone' => 'abc' ) ), $owner );
$check( 'invalid branch phone rejected', 400 === $s );
list( $s ) = call( 'POST', "/organization/locations/$branch", array( 'profile' => array( 'address_line' => 'Old Building 1', 'city' => 'Addis Ababa', 'sub_city' => 'Bole', 'phone' => '+251 11 111 1111', 'maps_url' => 'https://maps.example.invalid/old' ) ), $owner );
$check( 'first address needs no relocation reason', 200 === $s );
list( $s, $d ) = call( 'POST', "/organization/locations/$branch", array( 'profile' => array( 'address_line' => 'New Plaza 7', 'city' => 'Addis Ababa', 'sub_city' => 'Kirkos' ) ), $owner );
$check( 'changing an existing address without a reason is refused', 400 === $s && 'relocation_reason_required' === ( $d['code'] ?? '' ) );
list( $s, $d ) = call( 'POST', "/organization/locations/$branch", array( 'profile' => array( 'address_line' => 'New Plaza 7', 'city' => 'Addis Ababa', 'sub_city' => 'Kirkos' ), 'relocation_reason' => 'Lease ended; moved to new plaza', 'effective_date' => '2026-11-20' ), $owner );
$check( 'relocation with reason and effective date saves', 200 === $s && 'New Plaza 7' === ( $d['profile']['address_line'] ?? '' ) );
$check( 'previous address kept in history with reason', 'Old Building 1' === ( $d['profile']['history'][0]['address_line'] ?? '' ) && '2026-11-20' === ( $d['profile']['history'][0]['until'] ?? '' ) && false !== strpos( (string) ( $d['profile']['history'][0]['reason'] ?? '' ), 'Lease ended' ) );
list( $s ) = call( 'POST', "/organization/locations/$branch", array( 'profile' => array( 'address_line' => 'Another' ), 'relocation_reason' => 'Bad date test', 'effective_date' => '2026-02-31' ), $owner );
$check( 'impossible effective date rejected', 400 === $s );
list( $s ) = call( 'POST', "/organization/locations/$branch", array( 'profile' => array( 'phone' => '+251 11 222 2222' ) ), $owner );
$check( 'contact-only change needs no relocation reason', 200 === $s );
$audits = (int) qv( "SELECT COUNT(*) FROM " . Stockland_DB::table( 'audit_logs' ) . " WHERE action='LOCATION_PROFILE_UPDATE'" );
$check( 'every profile change is audited', $audits >= 3 );

list( $s ) = call( 'POST', "/organization/locations/$branch", array( 'profile' => array( 'city' => 'Hijack' ) ), $brmgr );
$check( 'branch manager cannot edit a branch address', in_array( $s, array( 401, 403 ), true ) );

// Name stays editable; duplicates and the protected warehouse are guarded.
list( $s ) = call( 'POST', "/organization/locations/$branch", array( 'location_name' => $orig_name . ' Renamed' ), $owner );
$check( 'branch name editable', 200 === $s );
list( $s, $d ) = call( 'GET', '/organization/locations', array(), $owner );
$after = array();
foreach ( (array) $d as $r ) {
	$after[ (int) $r['id'] ] = $r;
}
$check( 'rename keeps the address profile', ( $orig_name . ' Renamed' ) === ( $after[ $branch ]['location_name'] ?? '' ) && 'New Plaza 7' === ( $after[ $branch ]['profile']['address_line'] ?? '' ) );
list( $s ) = call( 'POST', "/organization/locations/$branch", array( 'location_name' => $orig_name . ' Renamed' ), $owner );
$check( 'saving the same name again is a no-op success', 200 === $s );
call( 'POST', "/organization/locations/$branch", array( 'location_name' => $orig_name ), $owner );

echo $fail ? "RESULT FAIL ($fail)\n" : "RESULT PASS\n";
exit( $fail ? 1 : 0 );
