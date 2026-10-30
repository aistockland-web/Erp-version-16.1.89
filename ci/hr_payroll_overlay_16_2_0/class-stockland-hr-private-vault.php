<?php
/**
 * Private HR resource storage. Fails closed unless an administrator configures an
 * absolute vault directory outside the web document root (STOCKLAND_PRIVATE_VAULT_DIR).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Stockland_HR_Private_Vault {
	const MAX_BYTES = 10485760;

	private static function error( $code, $message, $status = 409 ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	private static function root() {
		if ( ! defined( 'STOCKLAND_PRIVATE_VAULT_DIR' ) || ! is_string( STOCKLAND_PRIVATE_VAULT_DIR ) || '' === trim( STOCKLAND_PRIVATE_VAULT_DIR ) ) {
			return self::error( 'hr_private_vault_unconfigured', 'Private HR document storage is not configured. Define STOCKLAND_PRIVATE_VAULT_DIR outside the public web root before enabling uploads/downloads.', 503 );
		}
		$path = STOCKLAND_PRIVATE_VAULT_DIR;
		if ( ! str_starts_with( $path, DIRECTORY_SEPARATOR ) ) {
			return self::error( 'hr_private_vault_path_invalid', 'The private vault path must be absolute.', 503 );
		}
		if ( ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) {
			return self::error( 'hr_private_vault_unavailable', 'The private vault directory could not be created.', 503 );
		}
		$real = realpath( $path );
		$web  = realpath( ABSPATH );
		$document_root = '';
		if ( isset( $_SERVER['DOCUMENT_ROOT'] ) ) {
			$document_root = sanitize_text_field( wp_unslash( (string) $_SERVER['DOCUMENT_ROOT'] ) );
		}
		$doc = '' !== $document_root ? realpath( $document_root ) : false;
		if ( false === $real || ( false !== $web && ( $real === $web || str_starts_with( $real . DIRECTORY_SEPARATOR, rtrim( $web, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR ) ) ) || ( false !== $doc && ( $real === $doc || str_starts_with( $real . DIRECTORY_SEPARATOR, rtrim( $doc, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR ) ) ) ) {
			return self::error( 'hr_private_vault_public_path', 'The configured HR vault is within the public web root. Private documents are disabled until the vault is moved outside it.', 503 );
		}
		if ( ! is_writable( $real ) ) {
			return self::error( 'hr_private_vault_not_writable', 'The private HR vault is not writable.', 503 );
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
		$root = self::root();
		if ( is_wp_error( $root ) ) { return $root; }
		$sub = $root . DIRECTORY_SEPARATOR . gmdate( 'Y' ) . DIRECTORY_SEPARATOR . gmdate( 'm' );
		if ( ! is_dir( $sub ) && ! wp_mkdir_p( $sub ) ) {
			return self::error( 'hr_vault_subdir_failed', 'Could not prepare the private resource storage directory.', 503 );
		}
		@chmod( $sub, 0700 );
		$reference = 'hrv_' . bin2hex( random_bytes( 24 ) );
		$filename = $reference . '.' . $ext;
		$target = $sub . DIRECTORY_SEPARATOR . $filename;
		if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
			return self::error( 'hr_private_store_failed', 'Could not store the file in the private HR vault.', 500 );
		}
		@chmod( $target, 0600 );
		$hash = hash_file( 'sha256', $target );
		if ( ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{64}$/', $hash ) ) {
			@unlink( $target );
			return self::error( 'hr_private_hash_failed', 'Stored file integrity could not be verified; the file was discarded.', 500 );
		}
		return array(
			'private_file_ref' => $reference,
			'file_sha256' => $hash,
			'file_name' => $original,
			'mime_type' => $mime,
			'size_bytes' => filesize( $target ),
			'stored_at' => gmdate( 'c' ),
		);
	}

	public static function resolve( $reference, $expected_hash = '' ) {
		if ( ! is_string( $reference ) || ! preg_match( '/^hrv_[a-f0-9]{48}$/D', $reference ) ) {
			return self::error( 'hr_private_ref_invalid', 'The private resource reference is invalid.', 404 );
		}
		$root = self::root();
		if ( is_wp_error( $root ) ) { return $root; }
		$matches = glob( $root . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . $reference . '.*', GLOB_NOSORT );
		if ( ! is_array( $matches ) || 1 !== count( $matches ) ) {
			return self::error( 'hr_private_file_missing', 'The private resource could not be found.', 404 );
		}
		$path = realpath( $matches[0] );
		if ( false === $path || ! is_file( $path ) || ! is_readable( $path ) || ! str_starts_with( $path, rtrim( $root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR ) ) {
			return self::error( 'hr_private_file_unavailable', 'The private resource is not safely readable.', 404 );
		}
		$hash = hash_file( 'sha256', $path );
		if ( ! is_string( $hash ) || ( '' !== $expected_hash && ! hash_equals( strtolower( $expected_hash ), strtolower( $hash ) ) ) ) {
			return self::error( 'hr_private_integrity_failed', 'The stored private resource does not match its approved SHA-256.', 409 );
		}
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$mime_map = array( 'pdf'=>'application/pdf', 'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'txt'=>'text/plain; charset=UTF-8', 'png'=>'image/png', 'jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg' );
		if ( ! isset( $mime_map[ $ext ] ) ) { return self::error( 'hr_private_mime_blocked', 'Stored resource MIME type is not permitted.', 415 ); }
		return array( 'path'=>$path, 'sha256'=>$hash, 'mime_type'=>$mime_map[ $ext ], 'size_bytes'=>filesize( $path ) );
	}
}