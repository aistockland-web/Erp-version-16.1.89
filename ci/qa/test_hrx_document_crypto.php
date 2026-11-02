<?php
/**
 * Standalone authenticated-encryption regression tests for HRX private document blobs.
 * Run with: php ci/qa/test_hrx_document_crypto.php
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'STOCKLAND_HRX_DOCUMENT_KEYRING', array(
	'v1' => 'QUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUE=',
	'v2' => 'QkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkI=',
) );
define( 'STOCKLAND_HRX_DOCUMENT_CURRENT_KEY_VERSION', 'v2' );

final class WP_Error {
	private $code;
	private $message;
	public function __construct( $code, $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }

require dirname( __DIR__, 2 ) . '/hr_replacement_v2/class-stockland-hr-replacement.php';

function expect_true( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL " . $message . PHP_EOL );
		exit( 1 );
	}
	echo "PASS " . $message . PHP_EOL;
}

$plaintext = "Stockland HR contract synthetic fixture — no real personnel data.";
$document_id = 731;
$sha = hash( 'sha256', $plaintext );
$mime = 'application/pdf';

$encrypted_v2 = Stockland_HR_Replacement::encrypt_document_payload( $plaintext, $document_id, $sha, $mime );
expect_true( is_string( $encrypted_v2 ) && 0 === strpos( $encrypted_v2, 'SHRXGCM1' ), 'AES-256-GCM envelope is produced' );
expect_true( false === strpos( $encrypted_v2, $plaintext ), 'ciphertext does not expose plaintext document bytes' );
expect_true( 'v2' === Stockland_HR_Replacement::document_payload_key_version( $encrypted_v2 ), 'current key version is recorded in the envelope' );
expect_true( $plaintext === Stockland_HR_Replacement::decrypt_document_payload( $encrypted_v2, $document_id, $sha, $mime ), 'encrypted document decrypts and authenticates with matching metadata' );

$wrong_id = Stockland_HR_Replacement::decrypt_document_payload( $encrypted_v2, $document_id + 1, $sha, $mime );
expect_true( is_wp_error( $wrong_id ), 'document ID is authenticated as GCM additional data' );
$wrong_hash = Stockland_HR_Replacement::decrypt_document_payload( $encrypted_v2, $document_id, str_repeat( '0', 64 ), $mime );
expect_true( is_wp_error( $wrong_hash ), 'plaintext SHA-256 is authenticated as GCM additional data' );
$wrong_mime = Stockland_HR_Replacement::decrypt_document_payload( $encrypted_v2, $document_id, $sha, 'application/octet-stream' );
expect_true( is_wp_error( $wrong_mime ), 'MIME type is authenticated as GCM additional data' );

$tampered = $encrypted_v2;
$tampered[ strlen( $tampered ) - 1 ] = chr( ord( $tampered[ strlen( $tampered ) - 1 ] ) ^ 1 );
expect_true( is_wp_error( Stockland_HR_Replacement::decrypt_document_payload( $tampered, $document_id, $sha, $mime ) ), 'ciphertext tampering is rejected by GCM authentication' );

$encrypted_v1 = Stockland_HR_Replacement::encrypt_document_payload( $plaintext, $document_id + 1, $sha, $mime, 'v1' );
expect_true( is_string( $encrypted_v1 ) && 'v1' === Stockland_HR_Replacement::document_payload_key_version( $encrypted_v1 ), 'previous key version can encrypt during rotation tests' );
expect_true( $plaintext === Stockland_HR_Replacement::decrypt_document_payload( $encrypted_v1, $document_id + 1, $sha, $mime ), 'keyring retains decrypt access to the prior version' );

$status = Stockland_HR_Replacement::document_crypto_status();
expect_true( ! empty( $status['ready'] ) && 'AES-256-GCM' === $status['algorithm'] && in_array( 'v1', $status['available_key_versions'], true ) && in_array( 'v2', $status['available_key_versions'], true ), 'crypto health exposes readiness and key versions without revealing key material' );
expect_true( ! isset( $status['keys'] ) && ! isset( $status['keyring'] ), 'crypto health never returns secret key material' );

echo "PASS all private document encryption fixtures; no WordPress/database writes performed" . PHP_EOL;
