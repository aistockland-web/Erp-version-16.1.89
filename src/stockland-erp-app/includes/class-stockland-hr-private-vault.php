<?php
/**
 * Encrypted private HR resource storage.
 *
 * Files are stored outside the public web root as AES-256-GCM ciphertext. The
 * 32-byte encryption keys are supplied only through wp-config.php as a
 * STOCKLAND_PRIVATE_VAULT_KEYRING (key ID => 64 hex characters or strict base64)
 * and STOCKLAND_PRIVATE_VAULT_ACTIVE_KEY_ID. The legacy single-key constant remains
 * supported for V1 decryption during controlled rotation. Keys are never stored in
 * the ERP database or plugin files. Existing legacy plaintext files
 * are migrated atomically after their stored SHA-256 is verified on an authorized read;
 * operators may also run the guarded WP-CLI migration after a verified backup.
 *
 * @package Stockland_ERP
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Stockland_HR_Private_Vault {
	const MAX_BYTES = 10485760;
	const FORMAT_MAGIC_V1 = "STKHRV1\0";
	const FORMAT_MAGIC_V2 = "STKHRV2\0";
	const NONCE_BYTES = 12;
	const TAG_BYTES = 16;
	const MAX_KEY_ID_BYTES = 32;

	private static function error( $code, $message, $status = 409 ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	private static function decode_key( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) { return false; }
		$value = trim( $value );
		if ( preg_match( '/^[a-f0-9]{64}$/iD', $value ) ) { return hex2bin( $value ); }
		if ( 0 === strpos( $value, 'base64:' ) ) { $value = substr( $value, 7 ); }
		$key = base64_decode( $value, true );
		return is_string( $key ) && 32 === strlen( $key ) ? $key : false;
	}

	/**
	 * Loads a versioned keyring. Keep retired keys in the ring until every file using
	 * them has been rotated and a verified backup has been retained.
	 *
	 * @return array|WP_Error Key material indexed by key ID and active key ID.
	 */
	private static function key_material() {
		$keys = array();
		if ( defined( 'STOCKLAND_PRIVATE_VAULT_KEYRING' ) ) {
			if ( ! is_array( STOCKLAND_PRIVATE_VAULT_KEYRING ) || ! STOCKLAND_PRIVATE_VAULT_KEYRING ) {
				return self::error( 'hr_private_vault_keyring_invalid', 'STOCKLAND_PRIVATE_VAULT_KEYRING must be a non-empty key-ID-to-key array.', 503 );
			}
			foreach ( STOCKLAND_PRIVATE_VAULT_KEYRING as $key_id => $encoded ) {
				$key_id = (string) $key_id;
				if ( ! preg_match( '/^[A-Za-z0-9._-]{1,32}$/D', $key_id ) ) {
					return self::error( 'hr_private_vault_key_id_invalid', 'Vault key IDs may contain 1–32 letters, numbers, dots, underscores or hyphens.', 503 );
				}
				$key = self::decode_key( $encoded );
				if ( false === $key ) {
					return self::error( 'hr_private_vault_key_invalid', 'Every vault key must decode to exactly 32 bytes (64 hexadecimal characters or strict base64).', 503 );
				}
				$keys[ $key_id ] = $key;
			}
		}
		// Backward compatibility for the original one-key configuration and V1 envelopes.
		if ( defined( 'STOCKLAND_PRIVATE_VAULT_KEY' ) ) {
			$legacy_key = self::decode_key( STOCKLAND_PRIVATE_VAULT_KEY );
			if ( false === $legacy_key ) {
				return self::error( 'hr_private_vault_key_invalid', 'STOCKLAND_PRIVATE_VAULT_KEY must decode to exactly 32 bytes.', 503 );
			}
			if ( ! isset( $keys['legacy-default'] ) ) { $keys['legacy-default'] = $legacy_key; }
		}
		if ( ! $keys ) {
			return self::error( 'hr_private_vault_key_missing', 'Encrypted HR document storage is disabled. Configure a private 32-byte vault key or keyring in wp-config.php before uploading or downloading employee documents.', 503 );
		}
		$active = defined( 'STOCKLAND_PRIVATE_VAULT_ACTIVE_KEY_ID' ) ? (string) STOCKLAND_PRIVATE_VAULT_ACTIVE_KEY_ID : 'legacy-default';
		if ( ! isset( $keys[ $active ] ) ) {
			return self::error( 'hr_private_vault_active_key_missing', 'The configured active vault key ID does not exist in STOCKLAND_PRIVATE_VAULT_KEYRING.', 503 );
		}
		if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'openssl_decrypt' ) || ! in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true ) ) {
			return self::error( 'hr_private_vault_crypto_unavailable', 'OpenSSL AES-256-GCM is unavailable; private HR document storage is disabled.', 503 );
		}
		return array( 'keys' => $keys, 'active_key_id' => $active );
	}

	private static function aad_v1( $reference, $extension ) {
		return 'stockland-hr-private-vault:v1:' . $reference . ':' . strtolower( $extension );
	}

	private static function aad_v2( $reference, $extension, $key_id ) {
		return 'stockland-hr-private-vault:v2:' . $key_id . ':' . $reference . ':' . strtolower( $extension );
	}

	/** New encrypted objects use a key-ID-bound V2 AES-256-GCM envelope. */
	private static function encrypt_payload( $plain, $reference, $extension, $keyring ) {
		$key_id = (string) $keyring['active_key_id'];
		$key = $keyring['keys'][ $key_id ] ?? null;
		if ( ! is_string( $key ) || ! preg_match( '/^[A-Za-z0-9._-]{1,32}$/D', $key_id ) ) {
			return self::error( 'hr_private_active_key_invalid', 'The active vault key is unavailable or invalid.', 503 );
		}
		$nonce = random_bytes( self::NONCE_BYTES );
		$tag = '';
		$ciphertext = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, self::aad_v2( $reference, $extension, $key_id ), self::TAG_BYTES );
		if ( ! is_string( $ciphertext ) || self::TAG_BYTES !== strlen( $tag ) ) {
			return self::error( 'hr_private_encrypt_failed', 'The HR document could not be encrypted; no plaintext file was stored.', 500 );
		}
		return self::FORMAT_MAGIC_V2 . chr( strlen( $key_id ) ) . $key_id . $nonce . $tag . $ciphertext;
	}

	/**
	 * Authenticates V1 (legacy-default) or V2 (key-ID-bound) ciphertext.
	 *
	 * @return array|WP_Error Plaintext and the key ID/format used.
	 */
	private static function decrypt_payload( $payload, $reference, $extension, $keyring ) {
		if ( ! is_string( $payload ) ) { return self::error( 'hr_private_payload_invalid', 'The encrypted private resource is malformed.', 409 ); }
		if ( 0 === strpos( $payload, self::FORMAT_MAGIC_V1 ) ) {
			$key_id = 'legacy-default';
			$offset = strlen( self::FORMAT_MAGIC_V1 );
			$key = $keyring['keys'][ $key_id ] ?? null;
			$aad = self::aad_v1( $reference, $extension );
			$format = 'AES-256-GCM-v1';
		} elseif ( 0 === strpos( $payload, self::FORMAT_MAGIC_V2 ) ) {
			$offset = strlen( self::FORMAT_MAGIC_V2 );
			if ( strlen( $payload ) < $offset + 1 ) { return self::error( 'hr_private_payload_invalid', 'The encrypted private resource header is truncated.', 409 ); }
			$id_length = ord( $payload[ $offset ] );
			++$offset;
			if ( $id_length < 1 || $id_length > self::MAX_KEY_ID_BYTES || strlen( $payload ) < $offset + $id_length ) {
				return self::error( 'hr_private_payload_invalid', 'The encrypted private resource key identifier is invalid.', 409 );
			}
			$key_id = substr( $payload, $offset, $id_length );
			$offset += $id_length;
			if ( ! preg_match( '/^[A-Za-z0-9._-]{1,32}$/D', $key_id ) ) { return self::error( 'hr_private_payload_invalid', 'The encrypted private resource key identifier is malformed.', 409 ); }
			$key = $keyring['keys'][ $key_id ] ?? null;
			$aad = self::aad_v2( $reference, $extension, $key_id );
			$format = 'AES-256-GCM-v2';
		} else {
			return self::error( 'hr_private_legacy_plaintext_migration_required', 'This HR document uses the legacy unencrypted vault format. Run the documented WP-CLI encryption migration before downloading it.', 409 );
		}
		$minimum = $offset + self::NONCE_BYTES + self::TAG_BYTES;
		if ( strlen( $payload ) < $minimum || ! is_string( $key ) || 32 !== strlen( $key ) ) {
			return self::error( 'hr_private_vault_key_unavailable', 'The key needed for this encrypted HR document is not configured. Restore that key before accessing or rotating the document.', 503 );
		}
		$nonce = substr( $payload, $offset, self::NONCE_BYTES );
		$offset += self::NONCE_BYTES;
		$tag = substr( $payload, $offset, self::TAG_BYTES );
		$offset += self::TAG_BYTES;
		$ciphertext = substr( $payload, $offset );
		$plain = openssl_decrypt( $ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, $aad );
		if ( ! is_string( $plain ) ) {
			return self::error( 'hr_private_authentication_failed', 'The document could not be authenticated. Check the configured keyring and ciphertext integrity.', 409 );
		}
		return array( 'plaintext' => $plain, 'key_id' => $key_id, 'format' => $format );
	}

	/** Reject symlinks in every existing component of a configured path. */
	private static function path_has_symlink_component( $path ) {
		if ( ! is_string( $path ) || '' === $path || DIRECTORY_SEPARATOR !== substr( $path, 0, 1 ) || preg_match( '#(^|/)\.\.?(/|$)#', $path ) ) {
			return true;
		}
		$cursor = DIRECTORY_SEPARATOR;
		$parts = array_values( array_filter( explode( DIRECTORY_SEPARATOR, $path ), 'strlen' ) );
		foreach ( $parts as $part ) {
			$cursor = rtrim( $cursor, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . $part;
			if ( file_exists( $cursor ) || is_link( $cursor ) ) {
				if ( is_link( $cursor ) ) { return true; }
			} else {
				// Once a component is absent, later components cannot yet be links.
				break;
			}
		}
		return false;
	}

	private static function root() {
		if ( ! defined( 'STOCKLAND_PRIVATE_VAULT_DIR' ) || ! is_string( STOCKLAND_PRIVATE_VAULT_DIR ) || '' === trim( STOCKLAND_PRIVATE_VAULT_DIR ) ) {
			return self::error( 'hr_private_vault_unconfigured', 'Private HR document storage is not configured. Define STOCKLAND_PRIVATE_VAULT_DIR outside the public web root before enabling uploads/downloads.', 503 );
		}
		$path = STOCKLAND_PRIVATE_VAULT_DIR;
		if ( ! str_starts_with( $path, DIRECTORY_SEPARATOR ) ) {
			return self::error( 'hr_private_vault_path_invalid', 'The private vault path must be absolute.', 503 );
		}
		if ( self::path_has_symlink_component( $path ) ) {
			return self::error( 'hr_private_vault_symlink_root', 'The configured private HR vault path must not contain symbolic-link components or dot segments.', 503 );
		}
		if ( ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) {
			return self::error( 'hr_private_vault_unavailable', 'The private vault directory could not be created.', 503 );
		}
		if ( self::path_has_symlink_component( $path ) ) {
			return self::error( 'hr_private_vault_symlink_root', 'A symbolic link appeared in the private HR vault path during creation.', 503 );
		}
		$real = realpath( $path );
		$web = realpath( ABSPATH );
		$document_root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? trim( (string) $_SERVER['DOCUMENT_ROOT'] ) : '';
		$doc = '' !== $document_root ? realpath( $document_root ) : false;
		if ( false === $real || ( false !== $web && ( $real === $web || str_starts_with( $real . DIRECTORY_SEPARATOR, rtrim( $web, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR ) ) ) || ( false !== $doc && ( $real === $doc || str_starts_with( $real . DIRECTORY_SEPARATOR, rtrim( $doc, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR ) ) ) ) {
			return self::error( 'hr_private_vault_public_path', 'The configured HR vault is within the public web root. Private documents are disabled until the vault is moved outside it.', 503 );
		}
		if ( ! is_writable( $real ) ) {
			return self::error( 'hr_private_vault_not_writable', 'The private HR vault is not writable.', 503 );
		}
		if ( ! @chmod( $real, 0700 ) || 0700 !== ( fileperms( $real ) & 0777 ) ) {
			return self::error( 'hr_private_vault_permissions_unsafe', 'The private HR vault directory must be owner-only (0700); document storage is disabled until permissions can be enforced.', 503 );
		}
		return $real;
	}

	private static function allowed_types() {
		return array(
			'pdf' => array( 'application/pdf' ),
			'docx' => array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip' ),
			'xlsx' => array( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip' ),
			'txt' => array( 'text/plain' ),
			'png' => array( 'image/png' ),
			'jpg' => array( 'image/jpeg' ),
			'jpeg' => array( 'image/jpeg' ),
		);
	}

	/** Write an encrypted object atomically with owner-only file permissions. */
	private static function write_atomic( $directory, $target, $contents, $replace = false ) {
		if ( is_link( $directory ) || is_link( $target ) || ! is_dir( $directory ) ) { return false; }
		$real_dir = realpath( $directory );
		if ( false === $real_dir || realpath( dirname( $target ) ) !== $real_dir ) { return false; }
		if ( ! $replace && file_exists( $target ) ) { return false; }
		$temp = $real_dir . DIRECTORY_SEPARATOR . '.' . basename( $target ) . '.tmp-' . bin2hex( random_bytes( 12 ) );
		$handle = @fopen( $temp, 'x+b' );
		if ( false === $handle ) { return false; }
		$ok = true;
		$length = strlen( $contents );
		$offset = 0;
		while ( $offset < $length ) {
			$written = fwrite( $handle, substr( $contents, $offset ) );
			if ( false === $written || 0 === $written ) { $ok = false; break; }
			$offset += $written;
		}
		if ( $ok ) { $ok = fflush( $handle ); }
		if ( $ok && function_exists( 'fsync' ) ) { $ok = fsync( $handle ); }
		fclose( $handle );
		if ( ! $ok || $offset !== $length || ! @chmod( $temp, 0600 ) || 0600 !== ( fileperms( $temp ) & 0777 ) ) {
			@unlink( $temp );
			return false;
		}
		if ( is_link( $target ) || ( ! $replace && file_exists( $target ) ) || ! @rename( $temp, $target ) ) {
			@unlink( $temp );
			return false;
		}
		return @chmod( $target, 0600 ) && 0600 === ( fileperms( $target ) & 0777 );
	}

	private static function locate( $reference ) {
		$root = self::root();
		if ( is_wp_error( $root ) ) { return $root; }
		$matches = glob( $root . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . $reference . '.*', GLOB_NOSORT );
		if ( ! is_array( $matches ) || 1 !== count( $matches ) ) {
			return self::error( 'hr_private_file_missing', 'The private resource could not be found.', 404 );
		}
		if ( is_link( $matches[0] ) || is_link( dirname( $matches[0] ) ) || is_link( dirname( dirname( $matches[0] ) ) ) ) {
			return self::error( 'hr_private_file_symlink_blocked', 'The private resource path contains a symbolic link and cannot be read.', 404 );
		}
		$path = realpath( $matches[0] );
		if ( false === $path || ! is_file( $path ) || ! is_readable( $path ) || ! str_starts_with( $path, rtrim( $root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR ) ) {
			return self::error( 'hr_private_file_unavailable', 'The private resource is not safely readable.', 404 );
		}
		if ( ! @chmod( $path, 0600 ) || 0600 !== ( fileperms( $path ) & 0777 ) ) {
			return self::error( 'hr_private_file_permissions_unsafe', 'The encrypted HR object must have owner-only permissions (0600).', 503 );
		}
		return $path;
	}

	public static function store_upload( $file ) {
		if ( ! is_array( $file ) || ! isset( $file['error'], $file['tmp_name'], $file['name'], $file['size'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return self::error( 'hr_upload_invalid', 'Select one valid file. The upload did not complete successfully.', 400 );
		}
		$size = (int) $file['size'];
		if ( $size < 1 || $size > self::MAX_BYTES || ! is_uploaded_file( $file['tmp_name'] ) || ! is_readable( $file['tmp_name'] ) ) {
			return self::error( 'hr_upload_size_or_source_invalid', 'The file is empty, exceeds the 10 MiB limit, or is not a valid HTTP upload.', 400 );
		}
		$original = sanitize_file_name( (string) $file['name'] );
		$ext = strtolower( pathinfo( $original, PATHINFO_EXTENSION ) );
		$types = self::allowed_types();
		if ( ! isset( $types[ $ext ] ) ) {
			return self::error( 'hr_upload_type_blocked', 'Allowed private HR resource types: PDF, DOCX, XLSX, TXT, PNG, JPG/JPEG. Legacy .doc files are not accepted as DOCX.', 415 );
		}
		if ( ! class_exists( 'finfo' ) ) {
			return self::error( 'hr_upload_mime_verification_unavailable', 'Server MIME verification is unavailable; uploads are disabled.', 503 );
		}
		$finfo = new finfo( FILEINFO_MIME_TYPE );
		$mime = (string) $finfo->file( $file['tmp_name'] );
		if ( ! in_array( $mime, $types[ $ext ], true ) ) {
			return self::error( 'hr_upload_mime_mismatch', 'The uploaded content does not match its file type.', 415 );
		}
		$keyring = self::key_material();
		if ( is_wp_error( $keyring ) ) { return $keyring; }
		$plain = file_get_contents( $file['tmp_name'] );
		if ( ! is_string( $plain ) || strlen( $plain ) !== $size ) {
			return self::error( 'hr_upload_read_failed', 'The uploaded file could not be read completely.', 500 );
		}
		$reference = 'hrv_' . bin2hex( random_bytes( 24 ) );
		$encrypted = self::encrypt_payload( $plain, $reference, $ext, $keyring );
		if ( is_wp_error( $encrypted ) ) { return $encrypted; }
		$root = self::root();
		if ( is_wp_error( $root ) ) { return $root; }
		$year_dir = $root . DIRECTORY_SEPARATOR . gmdate( 'Y' );
		$sub = $year_dir . DIRECTORY_SEPARATOR . gmdate( 'm' );
		if ( is_link( $year_dir ) || is_link( $sub ) || ( ! is_dir( $year_dir ) && ! wp_mkdir_p( $year_dir ) ) || ( ! is_dir( $sub ) && ! wp_mkdir_p( $sub ) ) ) {
			return self::error( 'hr_vault_subdir_failed', 'Could not prepare a safe private resource storage directory.', 503 );
		}
		if ( ! @chmod( $year_dir, 0700 ) || ! @chmod( $sub, 0700 ) || 0700 !== ( fileperms( $year_dir ) & 0777 ) || 0700 !== ( fileperms( $sub ) & 0777 ) ) {
			return self::error( 'hr_private_subdir_permissions_unsafe', 'Vault year/month directories must have owner-only permissions (0700).', 503 );
		}
		$target = $sub . DIRECTORY_SEPARATOR . $reference . '.' . $ext;
		if ( ! self::write_atomic( $sub, $target, $encrypted, false ) ) {
			return self::error( 'hr_private_store_failed', 'Could not atomically store the encrypted file in the private HR vault.', 500 );
		}
		return array(
			'private_file_ref' => $reference,
			'file_sha256' => hash( 'sha256', $plain ),
			'file_name' => $original,
			'mime_type' => $mime,
			'size_bytes' => strlen( $plain ),
			'stored_at' => gmdate( 'c' ),
			'encryption_version' => 'AES-256-GCM-v2',
			'key_id' => $keyring['active_key_id'],
		);
	}

	public static function resolve( $reference, $expected_hash = '' ) {
		if ( ! is_string( $reference ) || ! preg_match( '/^hrv_[a-f0-9]{48}$/D', $reference ) ) {
			return self::error( 'hr_private_ref_invalid', 'The private resource reference is invalid.', 404 );
		}
		if ( ! is_string( $expected_hash ) || ! preg_match( '/^[a-f0-9]{64}$/iD', $expected_hash ) ) {
			return self::error( 'hr_private_expected_hash_missing', 'The approved document SHA-256 is missing or invalid.', 409 );
		}
		$keyring = self::key_material();
		if ( is_wp_error( $keyring ) ) { return $keyring; }
		$path = self::locate( $reference );
		if ( is_wp_error( $path ) ) { return $path; }
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$mime_map = array( 'pdf'=>'application/pdf', 'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'txt'=>'text/plain; charset=UTF-8', 'png'=>'image/png', 'jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg' );
		if ( ! isset( $mime_map[ $ext ] ) ) { return self::error( 'hr_private_mime_blocked', 'Stored resource MIME type is not permitted.', 415 ); }
		$payload = file_get_contents( $path );
		if ( ! is_string( $payload ) ) { return self::error( 'hr_private_file_unavailable', 'The encrypted private resource could not be read.', 404 ); }
		// Authorized readers may trigger a one-file, hash-checked, atomic legacy
		// migration. No database rows or approval states are changed by this step.
		if ( 0 !== strpos( $payload, self::FORMAT_MAGIC_V1 ) && 0 !== strpos( $payload, self::FORMAT_MAGIC_V2 ) ) {
			$migrated = self::migrate_legacy_file( $reference, $expected_hash );
			if ( is_wp_error( $migrated ) ) { return $migrated; }
			$payload = file_get_contents( $path );
			if ( ! is_string( $payload ) || ( 0 !== strpos( $payload, self::FORMAT_MAGIC_V1 ) && 0 !== strpos( $payload, self::FORMAT_MAGIC_V2 ) ) ) {
				return self::error( 'hr_private_migration_verification_failed', 'Legacy document migration could not be verified; download was blocked.', 500 );
			}
		}
		$decrypted = self::decrypt_payload( $payload, $reference, $ext, $keyring );
		if ( is_wp_error( $decrypted ) ) { return $decrypted; }
		$plain = $decrypted['plaintext'];
		$hash = hash( 'sha256', $plain );
		if ( ! hash_equals( strtolower( $expected_hash ), strtolower( $hash ) ) ) {
			return self::error( 'hr_private_integrity_failed', 'The decrypted private resource does not match its approved SHA-256.', 409 );
		}
		$temp = tempnam( sys_get_temp_dir(), 'stockland-hr-' );
		if ( false === $temp ) { return self::error( 'hr_private_temp_unavailable', 'A private temporary download file could not be created.', 503 ); }
		if ( ! @chmod( $temp, 0600 ) ) {
			@unlink( $temp );
			return self::error( 'hr_private_temp_permissions_failed', 'A private download file could not be protected with owner-only permissions.', 503 );
		}
		$written = file_put_contents( $temp, $plain, LOCK_EX );
		if ( false === $written || $written !== strlen( $plain ) || 0600 !== ( fileperms( $temp ) & 0777 ) ) {
			@unlink( $temp );
			return self::error( 'hr_private_temp_write_failed', 'The authenticated document could not be prepared for download.', 500 );
		}
		register_shutdown_function( static function () use ( $temp ) {
			if ( is_file( $temp ) ) { @unlink( $temp ); }
		} );
		return array( 'path'=>$temp, 'sha256'=>$hash, 'mime_type'=>$mime_map[ $ext ], 'size_bytes'=>strlen( $plain ), 'encrypted_at_rest'=>true, 'encryption_version'=>$decrypted['format'], 'key_id'=>$decrypted['key_id'] );
	}

	public static function delete( $reference ) {
		if ( ! is_string( $reference ) || ! preg_match( '/^hrv_[a-f0-9]{48}$/D', $reference ) ) {
			return self::error( 'hr_private_ref_invalid', 'The private resource reference is invalid.', 404 );
		}
		$path = self::locate( $reference );
		if ( is_wp_error( $path ) ) { return $path; }
		return @unlink( $path );
	}

	/**
	 * Explicitly encrypt one legacy plaintext attachment in place after hash check.
	 * Run only from WP-CLI after taking an encrypted, restorable vault backup.
	 */
	public static function migrate_legacy_file( $reference, $expected_hash ) {
		if ( ! is_string( $reference ) || ! preg_match( '/^hrv_[a-f0-9]{48}$/D', $reference ) || ! is_string( $expected_hash ) || ! preg_match( '/^[a-f0-9]{64}$/iD', $expected_hash ) ) {
			return self::error( 'hr_private_migration_args_invalid', 'A valid private file reference and plaintext SHA-256 are required.', 400 );
		}
		$keyring = self::key_material();
		if ( is_wp_error( $keyring ) ) { return $keyring; }
		$path = self::locate( $reference );
		if ( is_wp_error( $path ) ) { return $path; }
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$plain = file_get_contents( $path );
		if ( ! is_string( $plain ) ) { return self::error( 'hr_private_migration_read_failed', 'Legacy document could not be read.', 500 ); }
		if ( 0 === strpos( $plain, self::FORMAT_MAGIC_V1 ) || 0 === strpos( $plain, self::FORMAT_MAGIC_V2 ) ) { return array( 'status'=>'ALREADY_ENCRYPTED', 'reference'=>$reference ); }
		if ( ! hash_equals( strtolower( $expected_hash ), hash( 'sha256', $plain ) ) ) {
			return self::error( 'hr_private_migration_hash_mismatch', 'Legacy plaintext SHA-256 does not match the approved database hash; no file was changed.', 409 );
		}
		$encrypted = self::encrypt_payload( $plain, $reference, $ext, $keyring );
		if ( is_wp_error( $encrypted ) ) { return $encrypted; }
		if ( ! self::write_atomic( dirname( $path ), $path, $encrypted, true ) ) {
			return self::error( 'hr_private_migration_replace_failed', 'Atomic encrypted replacement failed; the verified legacy file remains available for a controlled retry.', 500 );
		}
		return array( 'status'=>'ENCRYPTED', 'reference'=>$reference, 'sha256'=>$expected_hash, 'encryption'=>'AES-256-GCM-v2', 'key_id'=>$keyring['active_key_id'] );
	}

	/** Re-encrypts one encrypted object under the active key after its database hash verifies. */
	public static function rotate_file_key( $reference, $expected_hash ) {
		if ( ! is_string( $reference ) || ! preg_match( '/^hrv_[a-f0-9]{48}$/D', $reference ) || ! is_string( $expected_hash ) || ! preg_match( '/^[a-f0-9]{64}$/iD', $expected_hash ) ) {
			return self::error( 'hr_private_rotation_args_invalid', 'A valid private file reference and SHA-256 are required.', 400 );
		}
		$keyring = self::key_material();
		if ( is_wp_error( $keyring ) ) { return $keyring; }
		$path = self::locate( $reference );
		if ( is_wp_error( $path ) ) { return $path; }
		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$payload = file_get_contents( $path );
		if ( ! is_string( $payload ) ) { return self::error( 'hr_private_rotation_read_failed', 'Encrypted document could not be read.', 500 ); }
		$decrypted = self::decrypt_payload( $payload, $reference, $extension, $keyring );
		if ( is_wp_error( $decrypted ) ) { return $decrypted; }
		$plain = $decrypted['plaintext'];
		$actual_hash = hash( 'sha256', $plain );
		if ( ! hash_equals( strtolower( $expected_hash ), strtolower( $actual_hash ) ) ) {
			return self::error( 'hr_private_rotation_hash_mismatch', 'The plaintext SHA-256 does not match the approved record; no encrypted file was changed.', 409 );
		}
		if ( 'AES-256-GCM-v2' === $decrypted['format'] && $decrypted['key_id'] === $keyring['active_key_id'] ) {
			return array( 'status' => 'ALREADY_USING_ACTIVE_KEY', 'reference' => $reference, 'key_id' => $decrypted['key_id'] );
		}
		$encrypted = self::encrypt_payload( $plain, $reference, $extension, $keyring );
		if ( is_wp_error( $encrypted ) ) { return $encrypted; }
		if ( ! self::write_atomic( dirname( $path ), $path, $encrypted, true ) ) {
			return self::error( 'hr_private_rotation_replace_failed', 'Atomic key rotation failed; the original encrypted object remains available for retry.', 500 );
		}
		return array( 'status' => 'ROTATED', 'reference' => $reference, 'key_id' => $keyring['active_key_id'], 'sha256' => $actual_hash );
	}

	public static function cli_rotate_key( $args, $assoc_args ) {
		if ( ! class_exists( 'WP_CLI' ) || count( $args ) !== 2 ) {
			WP_CLI::error( 'Usage: wp stockland hr-vault-rotate <private_file_ref> <plaintext_sha256>' );
		}
		WP_CLI::confirm( 'This re-encrypts one verified private HR document using the active vault key. Confirm an encrypted backup and every referenced old key are recoverable.' );
		$result = self::rotate_file_key( $args[0], $args[1] );
		if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_message() ); }
		WP_CLI::success( 'Vault key rotation status: ' . $result['status'] . ' (' . $result['reference'] . ').' );
	}

	public static function cli_encrypt_legacy( $args, $assoc_args ) {
		if ( ! class_exists( 'WP_CLI' ) || count( $args ) !== 2 ) {
			WP_CLI::error( 'Usage: wp stockland hr-vault-encrypt <private_file_ref> <plaintext_sha256>' );
		}
		WP_CLI::confirm( 'This encrypts one verified legacy private HR document in place. Confirm you have an encrypted vault backup and recoverable key.' );
		$result = self::migrate_legacy_file( $args[0], $args[1] );
		if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_message() ); }
		WP_CLI::success( 'Vault migration status: ' . $result['status'] . ' (' . $result['reference'] . ').' );
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
	WP_CLI::add_command( 'stockland hr-vault-encrypt', array( 'Stockland_HR_Private_Vault', 'cli_encrypt_legacy' ) );
	WP_CLI::add_command( 'stockland hr-vault-rotate', array( 'Stockland_HR_Private_Vault', 'cli_rotate_key' ) );
}
