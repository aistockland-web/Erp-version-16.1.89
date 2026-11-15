<?php
/**
 * Private HR library: documents are edited inside the ERP and saved as a new, independently approved version.
 *
 * SYNTHETIC DOCUMENTS ONLY, written to a disposable encrypted vault that is removed at the end. Runs AFTER
 * s16_payroll_cycle.php, which approves the HR lifecycle schema on the disposable site and creates its fixtures.
 * Run: STOCKLAND_DISPOSABLE_SITE=yes wp eval-file scripts/release/e2e/s18_hr_library_edit.php
 */
if ( 'yes' !== getenv( 'STOCKLAND_DISPOSABLE_SITE' ) ) { fwrite( STDERR, "Refusing to run: set STOCKLAND_DISPOSABLE_SITE=yes\n" ); exit( 2 ); }
$home_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
if ( ! in_array( DB_HOST, array( '127.0.0.1', 'localhost', '::1', '127.0.0.1:3306', 'localhost:3306' ), true ) || ! in_array( $home_host, array( '127.0.0.1', 'localhost', '::1' ), true ) ) {
	fwrite( STDERR, "Refusing to run: the WordPress home and database host must be loopback/disposable.\n" ); exit( 2 );
}
require_once __DIR__ . '/lib.php';
$vault_dir = rtrim( sys_get_temp_dir(), '/' ) . '/stockland-s18-vault-' . bin2hex( random_bytes( 6 ) );
if ( ! defined( 'STOCKLAND_PRIVATE_VAULT_KEY' ) ) { define( 'STOCKLAND_PRIVATE_VAULT_KEY', bin2hex( random_bytes( 32 ) ) ); }
if ( ! defined( 'STOCKLAND_PRIVATE_VAULT_DIR' ) ) { define( 'STOCKLAND_PRIVATE_VAULT_DIR', $vault_dir ); }
$fail = 0; $pass = 0;
$ok = function ( $label, $cond, $detail = '' ) use ( &$fail, &$pass ) { if ( $cond ) { ++$pass; echo "PASS $label\n"; } else { ++$fail; echo "FAIL $label" . ( '' !== $detail ? " :: $detail" : '' ) . "\n"; } return (bool) $cond; };
$finish = function () use ( &$fail, &$pass, &$vault_dir ) {
	if ( is_dir( $vault_dir ) && false !== strpos( $vault_dir, '/stockland-s18-vault-' ) ) {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $vault_dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) { $f->isDir() ? @rmdir( $f->getPathname() ) : @unlink( $f->getPathname() ); }
		@rmdir( $vault_dir );
	}
	echo ( $fail ? "RESULT FAIL ($fail failed, $pass passed)\n" : "RESULT PASS ($pass assertions)\n" ); exit( $fail ? 1 : 0 );
};
$uid = function ( $login ) { $u = get_user_by( 'login', $login ); return $u ? (int) $u->ID : 0; };
$mg = $uid( 'pay-mgmt' ); $o2 = $uid( 'pay-owner2' ); $o1 = 1;
global $wpdb;
if ( ! $mg || ! $o2 || ! qv( "SHOW TABLES LIKE '" . Stockland_DB::table( 'hr_resources' ) . "'" ) ) { fwrite( STDERR, "Run s16_payroll_cycle.php first (it approves the HR schema and creates the fixture users).\n" ); exit( 2 ); }
$mkuser = function ( $login ) { $u = get_user_by( 'login', $login ); return $u ? (int) $u->ID : (int) mkuser( $login, 'subscriber', 'cashier' ); };
$clerk = $mkuser( 's18-clerk' );

$register = function ( $code, $title, $file_name, $bytes, $creator ) use ( $ok ) {
	$stored = Stockland_HR_Private_Vault::store_content( $bytes, $file_name );
	if ( ! $ok( "fixture $code stored encrypted in the private vault", ! is_wp_error( $stored ), is_wp_error( $stored ) ? $stored->get_error_message() : '' ) ) { return 0; }
	Stockland_DB::insert_record( Stockland_DB::table( 'hr_resources' ), array( 'resource_code' => $code, 'title' => $title, 'description' => 'SYNTHETIC fixture', 'role_code' => 'ALL', 'language_code' => 'en-am', 'resource_type' => 'POLICY', 'version_label' => '1.0', 'supersedes_resource_id' => null, 'private_file_ref' => $stored['private_file_ref'], 'file_sha256' => $stored['file_sha256'], 'file_name' => $stored['file_name'], 'mime_type' => $stored['mime_type'], 'status' => 'DRAFT', 'created_by' => $creator ) );
	global $wpdb; return (int) $wpdb->insert_id;
};
$text_of = function ( $res ) { return is_array( $res[1] ) ? (string) ( $res[1]['text'] ?? '' ) : ''; };

// 1. A DOCX policy (English + Amharic, tab, heading) approved by an independent Owner.
$orig = "# Leave Policy (SYNTHETIC)\nEvery employee follows this policy.\nየሰራተኛ ፈቃድ ፖሊሲ\nItem\tValue";
$docx = Stockland_HR_Document_Text::build_docx( $orig );
$ok( 'a clean DOCX is built from plain text', is_string( $docx ) && 0 === strpos( $docx, 'PK' ) );
$id1 = $register( 'S18-DOCX-' . bin2hex( random_bytes( 3 ) ), 'S18 leave policy', 'S18-leave-policy.docx', $docx, $mg );
$r = call( 'POST', "/hr/resources/$id1/approve", array( 'approval_note' => 'Synthetic policy verified for the edit test.' ), $mg );
$ok( 'maker cannot approve their own resource', $r[0] >= 400 );
$r = call( 'POST', "/hr/resources/$id1/approve", array( 'approval_note' => 'Synthetic policy verified for the edit test.' ), $o2 );
$ok( 'independent Owner approves the resource', 200 === $r[0] );

// 2. Open for editing.
$r = call( 'GET', "/hr/resources/$id1/content", array(), $mg );
$ok( 'manager opens the approved DOCX as editable text', 200 === $r[0] && true === $r[1]['editable'] && 'docx' === $r[1]['format'] );
$ok( 'text keeps headings, English, Amharic and tabs', false !== strpos( $text_of( $r ), 'Leave Policy (SYNTHETIC)' ) && false !== strpos( $text_of( $r ), 'የሰራተኛ ፈቃድ ፖሊሲ' ) && false !== strpos( $text_of( $r ), "Item\tValue" ) );
$ok( 'opening the document is audited', (int) qv( 'SELECT COUNT(*) FROM ' . Stockland_DB::table( 'hr_payroll_events' ) . " WHERE entity_type='hr-resource' AND entity_id=$id1 AND event_type='resource_content_opened'" ) >= 1 );
$r2 = call( 'GET', "/hr/resources/$id1/content", array(), $clerk );
$ok( 'a non-HR user cannot open document text', in_array( $r2[0], array( 401, 403 ), true ) );

// 3. Save edits as a new version.
$base = array( 'version_label' => '1.1', 'title' => 'S18 leave policy' );
$r = call( 'POST', "/hr/resources/$id1/revise", $base + array( 'content' => $orig ), $mg );
$ok( 'saving without any change is refused', 409 === $r[0] && 'hr_resource_no_changes' === ( $r[1]['code'] ?? '' ) );
$r = call( 'POST', "/hr/resources/$id1/revise", $base + array( 'content' => '' ), $mg );
$ok( 'empty text is refused', 400 === $r[0] );
$r = call( 'POST', "/hr/resources/$id1/revise", $base + array( 'content' => str_repeat( 'a', Stockland_HR_Document_Text::MAX_CHARS + 1 ) ), $mg );
$ok( 'over-long text is refused', 400 === $r[0] );
$edited = str_replace( 'Every employee follows this policy.', "Every employee follows this policy.\nAnnual leave is 16 working days (SYNTHETIC).", $orig );
$r = call( 'POST', "/hr/resources/$id1/revise", array( 'version_label' => '1.0', 'content' => $edited ), $mg );
$ok( 'reusing the version label is refused', 400 === $r[0] );
$r = call( 'POST', "/hr/resources/$id1/revise", $base + array( 'content' => $edited ), $clerk );
$ok( 'a non-HR user cannot save a new version', in_array( $r[0], array( 401, 403 ), true ) );
$r = call( 'POST', "/hr/resources/$id1/revise", $base + array( 'content' => $edited ), $mg );
$ok( 'edited text saves as a new DRAFT version', 200 === $r[0] && 'DRAFT' === ( $r[1]['status'] ?? '' ) && (int) ( $r[1]['supersedes_resource_id'] ?? 0 ) === $id1, wp_json_encode( $r[1] ) );
$id1b = (int) ( $r[1]['id'] ?? 0 );
$ok( 'the previously approved version stays current until approval', 'APPROVED' === qv( 'SELECT status FROM ' . Stockland_DB::table( 'hr_resources' ) . " WHERE id=$id1" ) );
$r = call( 'GET', "/hr/resources/$id1b/content", array(), $mg );
$ok( 'the draft round-trips exactly (edited text, Amharic and tab preserved)', 200 === $r[0] && Stockland_HR_Document_Text::normalise( $edited ) === $text_of( $r ), substr( $text_of( $r ), 0, 160 ) );
$new = q( 'SELECT * FROM ' . Stockland_DB::table( 'hr_resources' ) . " WHERE id=$id1b" )[0];
$f = Stockland_HR_Private_Vault::resolve( $new['private_file_ref'], $new['file_sha256'] );
$ok( 'new version is encrypted at rest and matches its SHA-256', ! is_wp_error( $f ) && hash_file( 'sha256', $f['path'] ) === $new['file_sha256'] && 'S18-leave-policy.docx' === $new['file_name'] );
$zip = new ZipArchive(); $opened = ! is_wp_error( $f ) && true === $zip->open( $f['path'] );
$ok( 'saved file is a valid Word document (opens as a package with word/document.xml)', $opened && false !== $zip->locateName( 'word/document.xml' ) );
$ok( 'saving the revision is audited', (int) qv( 'SELECT COUNT(*) FROM ' . Stockland_DB::table( 'hr_payroll_events' ) . " WHERE entity_type='hr-resource' AND entity_id=$id1b AND event_type='resource_revision_drafted'" ) === 1 );

// 4. Maker-checker approval of the edited version.
$r = call( 'POST', "/hr/resources/$id1b/approve", array( 'approval_note' => 'Edited policy reviewed independently.' ), $mg );
$ok( 'the editor cannot approve their own edit', $r[0] >= 400 );
$r = call( 'POST', "/hr/resources/$id1b/approve", array( 'approval_note' => 'Edited policy reviewed independently.' ), $o2 );
$ok( 'an independent Owner approves the edited version', 200 === $r[0] );
$ok( 'old version becomes SUPERSEDED, new version APPROVED', 'SUPERSEDED' === qv( 'SELECT status FROM ' . Stockland_DB::table( 'hr_resources' ) . " WHERE id=$id1" ) && 'APPROVED' === qv( 'SELECT status FROM ' . Stockland_DB::table( 'hr_resources' ) . " WHERE id=$id1b" ) );

// 5. Plain-text documents.
$id2 = $register( 'S18-TXT-' . bin2hex( random_bytes( 3 ) ), 'S18 code of conduct', 'S18-conduct.txt', "Be honest.\nBe on time.\n", $mg );
call( 'POST', "/hr/resources/$id2/approve", array( 'approval_note' => 'Synthetic text approved for the edit test.' ), $o2 );
$r = call( 'POST', "/hr/resources/$id2/revise", array( 'version_label' => '1.1', 'content' => "Be honest.\nBe on time.\nBe kind." ), $mg );
$ok( 'a TXT document is edited and saved as a new TXT draft', 200 === $r[0] );
$row = q( 'SELECT * FROM ' . Stockland_DB::table( 'hr_resources' ) . ' WHERE id=' . (int) ( $r[1]['id'] ?? 0 ) )[0] ?? array();
$f2 = $row ? Stockland_HR_Private_Vault::resolve( $row['private_file_ref'], $row['file_sha256'] ) : null;
$ok( 'saved TXT content matches the edit', $f2 && ! is_wp_error( $f2 ) && "Be honest.\nBe on time.\nBe kind.\n" === file_get_contents( $f2['path'] ) );

// 6. Tables are readable; types that cannot be edited say so; hostile markup is refused.
$tbl = tempnam( sys_get_temp_dir(), 's18tbl' ); $z = new ZipArchive(); $z->open( $tbl, ZipArchive::OVERWRITE );
$z->addFromString( '[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>' );
$z->addFromString( 'word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="' . Stockland_HR_Document_Text::W_NS . '"><w:body><w:p><w:r><w:t>Salary scale</w:t></w:r></w:p><w:tbl><w:tr><w:tc><w:p><w:r><w:t>Grade</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Amount</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:tc><w:p><w:r><w:t>A1</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>1000</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>' );
$z->close();
$x = Stockland_HR_Document_Text::extract( $tbl, 'docx' );
$ok( 'Word tables are shown as editable rows', $x['editable'] && false !== strpos( $x['text'], '| Grade | Amount |' ) && false !== strpos( $x['text'], '| A1 | 1000 |' ), $x['text'] );
$z = new ZipArchive(); $z->open( $tbl, ZipArchive::OVERWRITE );
$z->addFromString( 'word/document.xml', '<?xml version="1.0"?><!DOCTYPE d [<!ENTITY x SYSTEM "file:///etc/passwd">]><w:document xmlns:w="' . Stockland_HR_Document_Text::W_NS . '"><w:body><w:p><w:r><w:t>&x;</w:t></w:r></w:p></w:body></w:document>' );
$z->close();
$x = Stockland_HR_Document_Text::extract( $tbl, 'docx' );
$ok( 'a document with external-entity markup is refused (no file disclosure)', false === $x['editable'] && false === strpos( $x['text'], 'root:' ) );
@unlink( $tbl );
$x = Stockland_HR_Document_Text::extract( $tbl, 'pdf' );
$ok( 'PDF reports that it cannot be edited as text and points to Replace with a file', false === $x['editable'] && false !== stripos( $x['note'], 'replace' ) );
$wpdb->query( $wpdb->prepare( 'UPDATE %i SET file_name=%s WHERE id=%d', Stockland_DB::table( 'hr_resources' ), 'scan.pdf', $id2 ) );
$r = call( 'POST', "/hr/resources/$id2/revise", array( 'version_label' => '9.9', 'content' => 'x' ), $mg );
$ok( 'saving text over a non-editable file type is refused', 415 === $r[0] );

$finish();
