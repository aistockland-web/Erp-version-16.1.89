<?php
/**
 * Encrypted private document vault for candidate/employee HR records.
 *
 * Files are never written to public uploads or exposed through a public URL.
 * AES-256-GCM uses a key derived from WordPress AUTH salt; if secure salts or
 * OpenSSL are unavailable, the service fails closed. Hard cap: 3 MB per file.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
if ( ! class_exists( 'Stockland_HR_Replacement_Workforce_WPDB_Repository' ) || ! class_exists( 'Stockland_HR_Replacement_Workforce_WP_Access' ) ) {
	return;
}

final class Stockland_HR_Replacement_Private_Vault {
	const ACTION = 'stockland_hrx_document_download';
	const MIME_DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
	const MAX_BYTES = 3000000;
	const ENCRYPTION_VERSION = 'AES-256-GCM-v1';

	private $repo;
	private $access;

	public function __construct() {
		$this->repo = new Stockland_HR_Replacement_Workforce_WPDB_Repository();
		$this->access = new Stockland_HR_Replacement_Workforce_WP_Access();
		add_action( 'admin_post_' . self::ACTION, array( $this, 'download_handler' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'deny_anonymous' ) );
	}

	private function error( $code, $message, $status = 400 ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	private function encryption_key() {
		if ( ! function_exists( 'wp_salt' ) || ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'openssl_decrypt' ) || ! function_exists( 'random_bytes' ) ) {
			return $this->error( 'hrx_vault_crypto_unavailable', 'Secure document encryption is unavailable on this host. Upload is blocked.', 503 );
		}
		$secret = (string) wp_salt( 'auth' );
		if ( strlen( $secret ) < 32 || false !== stripos( $secret, 'put your unique phrase here' ) ) {
			return $this->error( 'hrx_vault_secret_unconfigured', 'WordPress AUTH salts must be securely configured before private HR documents can be stored.', 503 );
		}
		return hash_hmac( 'sha256', 'Stockland HR replacement private vault v1', $secret, true );
	}

	private function safe_filename( $filename ) {
		$filename = wp_basename( (string) $filename );
		$filename = sanitize_file_name( $filename );
		$filename = str_replace( array( "\r", "\n", '"', "'" ), '', $filename );
		if ( '' === $filename || strlen( $filename ) > 190 ) {
			return false;
		}
		return $filename;
	}

	private function validate_file( $file ) {
		if ( ! is_array( $file ) || ! isset( $file['error'], $file['tmp_name'], $file['name'], $file['size'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return $this->error( 'hrx_vault_upload_invalid', 'The uploaded document is missing or PHP reported an upload error.', 422 );
		}
		if ( (int) $file['size'] < 8 || (int) $file['size'] > self::MAX_BYTES || ! is_file( $file['tmp_name'] ) ) {
			return $this->error( 'hrx_vault_upload_size', 'The document must be between 8 bytes and 3 MB.', 413 );
		}
		if ( function_exists( 'is_uploaded_file' ) && ! is_uploaded_file( $file['tmp_name'] ) ) {
			return $this->error( 'hrx_vault_upload_origin', 'The server did not confirm an HTTP file upload.', 422 );
		}
		$filename = $this->safe_filename( $file['name'] );
		if ( false === $filename ) {
			return $this->error( 'hrx_vault_filename_invalid', 'The document filename is invalid.', 422 );
		}
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$mime_by_extension = array(
			'pdf' => 'application/pdf',
			'docx' => self::MIME_DOCX,
			'png' => 'image/png',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
		);
		if ( ! isset( $mime_by_extension[ $extension ] ) ) {
			return $this->error( 'hrx_vault_type_denied', 'Allowed document types are PDF, genuine DOCX, PNG and JPEG.', 415 );
		}
		$bytes = file_get_contents( $file['tmp_name'] );
		if ( ! is_string( $bytes ) || strlen( $bytes ) !== (int) $file['size'] ) {
			return $this->error( 'hrx_vault_read_failed', 'The uploaded document could not be read completely.', 422 );
		}
		$mime = $mime_by_extension[ $extension ];
		if ( 'pdf' === $extension && 0 !== strpos( $bytes, '%PDF-' ) ) {
			return $this->error( 'hrx_vault_magic_mismatch', 'The file extension does not match PDF content.', 415 );
		}
		if ( 'png' === $extension && 0 !== strpos( $bytes, "\x89PNG\r\n\x1a\n" ) ) {
			return $this->error( 'hrx_vault_magic_mismatch', 'The file extension does not match PNG content.', 415 );
		}
		if ( in_array( $extension, array( 'jpg', 'jpeg' ), true ) && 0 !== strpos( $bytes, "\xFF\xD8\xFF" ) ) {
			return $this->error( 'hrx_vault_magic_mismatch', 'The file extension does not match JPEG content.', 415 );
		}
		if ( 'docx' === $extension ) {
			if ( 0 !== strpos( $bytes, "PK\x03\x04" ) || ! class_exists( 'ZipArchive' ) ) {
				return $this->error( 'hrx_vault_docx_invalid', 'A real Office Open XML DOCX archive is required.', 415 );
			}
			$zip = new ZipArchive();
			if ( true !== $zip->open( $file['tmp_name'] ) || false === $zip->locateName( '[Content_Types].xml' ) || false === $zip->locateName( 'word/document.xml' ) ) {
				if ( true === $zip->status ) {
					$zip->close();
				}
				return $this->error( 'hrx_vault_docx_invalid', 'The uploaded ZIP does not contain the required DOCX document parts.', 415 );
			}
			$zip->close();
		}
		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$detected = $finfo ? finfo_file( $finfo, $file['tmp_name'] ) : false;
			if ( $finfo ) {
				finfo_close( $finfo );
			}
			$valid_mimes = array( $mime );
			if ( 'docx' === $extension ) {
				$valid_mimes[] = 'application/zip';
				$valid_mimes[] = 'application/octet-stream';
			}
			if ( ! is_string( $detected ) || ! in_array( $detected, $valid_mimes, true ) ) {
				return $this->error( 'hrx_vault_mime_mismatch', 'File-content detection does not match the permitted document type.', 415 );
			}
		}
		return array( 'filename' => $filename, 'content_type' => $mime, 'bytes' => $bytes );
	}

	/**
	 * Add metadata and encrypted content in the caller's existing DB transaction.
	 *
	 * @param array $meta      document_code, subject_type, subject_id, document_type, issued_on, expires_on, retention_until.
	 * @param array $file      Uploaded file from WP_REST_Request::get_file_params().
	 * @return array|WP_Error  Metadata-only response; plaintext is never returned.
	 */
	public function store( $meta, $file ) {
		global $wpdb;
		$validated = $this->validate_file( $file );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		$key = $this->encryption_key();
		if ( is_wp_error( $key ) ) {
			return $key;
		}
		$subject_type = strtoupper( sanitize_key( (string) ( $meta['subject_type'] ?? '' ) ) );
		if ( ! in_array( $subject_type, array( 'CANDIDATE', 'EMPLOYMENT', 'CONTRACT', 'EXIT_CASE', 'PAYEE', 'PAYROLL_RUN' ), true ) ) {
			return $this->error( 'hrx_vault_subject_invalid', 'The document subject type is not supported.', 422 );
		}
		$subject_id = isset( $meta['subject_id'] ) ? absint( $meta['subject_id'] ) : 0;
		if ( $subject_id < 1 ) {
			return $this->error( 'hrx_vault_subject_required', 'A valid linked HR record is required.', 422 );
		}
		$document_type = strtoupper( sanitize_key( (string) ( $meta['document_type'] ?? '' ) ) );
		if ( ! preg_match( '/^[A-Z0-9_-]{2,64}$/', $document_type ) ) {
			return $this->error( 'hrx_vault_document_type_invalid', 'A controlled document type is required.', 422 );
		}
		try {
			$nonce = random_bytes( 12 );
			$tag = '';
			$ciphertext = openssl_encrypt( $validated['bytes'], 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'stockland-hrx-document-v1', 16 );
			if ( ! is_string( $ciphertext ) || 16 !== strlen( $tag ) ) {
				return $this->error( 'hrx_vault_encrypt_failed', 'The document could not be encrypted; nothing was stored.', 500 );
			}
			$vault_reference = 'hrx-vault:' . bin2hex( random_bytes( 24 ) );
		} catch ( Throwable $e ) {
			return $this->error( 'hrx_vault_crypto_failed', 'Secure document storage failed. Nothing was stored.', 500 );
		}
		$issued = isset( $meta['issued_on'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $meta['issued_on'] ) ? $meta['issued_on'] : null;
		$expires = isset( $meta['expires_on'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $meta['expires_on'] ) ? $meta['expires_on'] : null;
		$retention = isset( $meta['retention_until'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $meta['retention_until'] ) ? $meta['retention_until'] : null;
		try {
			$doc = $this->repo->insert( 'documents', array(
				'document_code' => sanitize_text_field( (string) ( $meta['document_code'] ?? '' ) ),
				'subject_type' => $subject_type,
				'subject_id' => $subject_id,
				'document_type' => $document_type,
				'original_filename' => $validated['filename'],
				'vault_reference' => $vault_reference,
				'document_sha256' => hash( 'sha256', $validated['bytes'] ),
				'content_type' => $validated['content_type'],
				'file_size_bytes' => strlen( $validated['bytes'] ),
				'status' => 'PENDING_VERIFICATION',
				'verified_by' => null,
				'verified_at' => null,
				'approved_by' => null,
				'approved_at' => null,
				'issued_on' => $issued,
				'expires_on' => $expires,
				'retention_until' => $retention,
				'version_no' => 1,
				'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql', true ),
			) );
			$blob_result = $wpdb->insert(
				$this->repo->document_blob_table(),
				array(
					'document_id' => (int) $doc['id'],
					'encryption_version' => self::ENCRYPTION_VERSION,
					'nonce' => $nonce,
					'auth_tag' => $tag,
					'ciphertext' => $ciphertext,
					'created_by' => get_current_user_id(),
					'created_at' => current_time( 'mysql', true ),
				),
				array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
			);
			if ( false === $blob_result ) {
				throw new RuntimeException( 'Encrypted document payload could not be saved.' );
			}
			return array(
				'id' => (int) $doc['id'],
				'document_code' => (string) $doc['document_code'],
				'document_type' => (string) $doc['document_type'],
				'original_filename' => (string) $doc['original_filename'],
				'content_type' => (string) $doc['content_type'],
				'file_size_bytes' => (int) $doc['file_size_bytes'],
				'document_sha256' => (string) $doc['document_sha256'],
				'status' => 'PENDING_VERIFICATION',
				'message' => 'Encrypted private document stored. A different authorized reviewer must verify and approve it before it can be downloaded or used to activate employment.',
			);
		} catch ( Throwable $e ) {
			error_log( '[Stockland HRX vault] private document storage failed (' . get_class( $e ) . ').' );
			return $this->error( 'hrx_vault_store_failed', 'The document was not saved. Retry only after verifying the record and upload status.', 500 );
		}
	}

	private function decrypt( $document_id ) {
		global $wpdb;
		$key = $this->encryption_key();
		if ( is_wp_error( $key ) ) {
			return $key;
		}
		$names = Stockland_HR_Replacement_Workforce_Schema::table_names();
		$blob = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE document_id = %d LIMIT 1', $names['document_blobs'], absint( $document_id ) ),
			ARRAY_A
		);
		if ( ! is_array( $blob ) || self::ENCRYPTION_VERSION !== $blob['encryption_version'] ) {
			return $this->error( 'hrx_vault_payload_missing', 'The encrypted private document payload is not available.', 404 );
		}
		$plaintext = openssl_decrypt( $blob['ciphertext'], 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $blob['nonce'], $blob['auth_tag'], 'stockland-hrx-document-v1' );
		if ( ! is_string( $plaintext ) ) {
			return $this->error( 'hrx_vault_decrypt_failed', 'Document authentication failed. No file was released.', 500 );
		}
		$document = $this->repo->get( 'documents', $document_id );
		if ( ! $document || strlen( $plaintext ) !== (int) $document['file_size_bytes'] || ! hash_equals( (string) $document['document_sha256'], hash( 'sha256', $plaintext ) ) ) {
			return $this->error( 'hrx_vault_integrity_failed', 'The decrypted document does not match its stored integrity receipt.', 500 );
		}
		return array( 'document' => $document, 'bytes' => $plaintext );
	}

	public function verify_document( $document_id, $note, $evidence_reference ) {
		$document = $this->repo->get( 'documents', $document_id, true );
		if ( ! $document ) {
			return $this->error( 'hrx_document_not_found', 'The document does not exist.', 404 );
		}
		if ( 'PENDING_VERIFICATION' !== $document['status'] ) {
			return $this->error( 'hrx_document_state_invalid', 'Only a document pending verification can be verified.', 409 );
		}
		if ( (int) $document['created_by'] === get_current_user_id() ) {
			return $this->error( 'hrx_document_self_verification_blocked', 'The uploader cannot verify their own document.', 403 );
		}
		$note = trim( sanitize_textarea_field( (string) $note ) );
		$evidence_reference = sanitize_text_field( (string) $evidence_reference );
		if ( strlen( $note ) < 20 || strlen( $evidence_reference ) < 8 ) {
			return $this->error( 'hrx_document_verification_evidence_required', 'Provide a meaningful verification note and evidence reference.', 422 );
		}
		$decoded = $this->decrypt( $document_id );
		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}
		$updated = $this->repo->transition( 'documents', (int) $document['id'], (int) $document['version_no'], 'PENDING_VERIFICATION', 'VERIFIED', array(
			'verified_by' => get_current_user_id(),
			'verified_at' => current_time( 'mysql', true ),
		) );
		$this->repo->append_event( 'document', (int) $updated['id'], 'PRIVATE_DOCUMENT_VERIFIED', 'PENDING_VERIFICATION', 'VERIFIED', get_current_user_id(), hash( 'sha256', 'verify:' . $document_id . ':' . $document['document_sha256'] ), $note, $evidence_reference );
		return array( 'id' => (int) $updated['id'], 'status' => 'VERIFIED', 'sha256' => (string) $updated['document_sha256'], 'message' => 'Encrypted document integrity and reviewer evidence were verified.' );
	}

	public function approve_document( $document_id, $note, $evidence_reference ) {
		$document = $this->repo->get( 'documents', $document_id, true );
		if ( ! $document ) {
			return $this->error( 'hrx_document_not_found', 'The document does not exist.', 404 );
		}
		if ( 'VERIFIED' !== $document['status'] ) {
			return $this->error( 'hrx_document_state_invalid', 'Only a verified document can be approved.', 409 );
		}
		if ( (int) $document['created_by'] === get_current_user_id() || (int) $document['verified_by'] === get_current_user_id() ) {
			return $this->error( 'hrx_document_independent_approval_required', 'The uploader and verifier cannot approve the same private document.', 403 );
		}
		$note = trim( sanitize_textarea_field( (string) $note ) );
		$evidence_reference = sanitize_text_field( (string) $evidence_reference );
		if ( strlen( $note ) < 20 || strlen( $evidence_reference ) < 8 ) {
			return $this->error( 'hrx_document_approval_evidence_required', 'Provide the approval rationale and source reference.', 422 );
		}
		$updated = $this->repo->transition( 'documents', (int) $document['id'], (int) $document['version_no'], 'VERIFIED', 'APPROVED', array(
			'approved_by' => get_current_user_id(),
			'approved_at' => current_time( 'mysql', true ),
		) );
		$this->repo->append_event( 'document', (int) $updated['id'], 'PRIVATE_DOCUMENT_APPROVED', 'VERIFIED', 'APPROVED', get_current_user_id(), hash( 'sha256', 'approve:' . $document_id . ':' . $document['document_sha256'] ), $note, $evidence_reference );
		return array( 'id' => (int) $updated['id'], 'status' => 'APPROVED', 'message' => 'Document approved for its linked HR workflow. Downloads remain authenticated and location-scoped.' );
	}

	public function download_url( $document_id ) {
		$document = $this->repo->get( 'documents', $document_id );
		if ( ! $document || 'APPROVED' !== $document['status'] ) {
			return $this->error( 'hrx_document_not_downloadable', 'Only an approved private document can be downloaded.', 403 );
		}
		if ( ! $this->authorize_document( $document ) ) {
			return $this->error( 'hrx_document_access_denied', 'You do not have permission to download this HR document.', 403 );
		}
		$url = add_query_arg( array( 'action' => self::ACTION, 'document_id' => (int) $document_id ), admin_url( 'admin-post.php' ) );
		return wp_nonce_url( $url, self::ACTION . '_' . (int) $document_id, '_wpnonce' );
	}

	private function authorize_document( $document ) {
		if ( ! is_user_logged_in() || ! $this->access->can_view() ) {
			return false;
		}
		$type = strtoupper( (string) $document['document_type'] );
		if ( false !== strpos( $type, 'PAYROLL' ) || false !== strpos( $type, 'PAYSLIP' ) || false !== strpos( $type, 'TAX' ) ) {
			if ( ! class_exists( 'Stockland_Authority' ) || ! Stockland_Authority::can_view_payroll() ) {
				return false;
			}
		}
		$subject_type = strtoupper( (string) $document['subject_type'] );
		if ( 'EMPLOYMENT' === $subject_type ) {
			$employment = $this->repo->get( 'employments', (int) $document['subject_id'] );
			return $employment && ! is_wp_error( $this->access->assert_branch( (int) $employment['location_id'] ) );
		}
		if ( 'CONTRACT' === $subject_type ) {
			$contract = $this->repo->get( 'contracts', (int) $document['subject_id'] );
			if ( ! $contract ) return false;
			$employment = $this->repo->get( 'employments', (int) $contract['employment_id'] );
			return $employment && ! is_wp_error( $this->access->assert_branch( (int) $employment['location_id'] ) );
		}
		if ( 'EXIT_CASE' === $subject_type ) {
			$exit = $this->repo->get( 'exit_cases', (int) $document['subject_id'] );
			if ( ! $exit ) return false;
			$employment = $this->repo->get( 'employments', (int) $exit['employment_id'] );
			return $employment && ! is_wp_error( $this->access->assert_branch( (int) $employment['location_id'] ) );
		}
		// Candidates, payees and payroll-wide documents require central HR company scope.
		return $this->access->company_scope() || $this->access->can_manage_structure();
	}

	private function record_access( $document_id, $action, $outcome, $request_id = '' ) {
		global $wpdb;
		$names = Stockland_HR_Replacement_Workforce_Schema::table_names();
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$secret = function_exists( 'wp_salt' ) ? (string) wp_salt( 'auth' ) : 'stockland-hrx-no-ip-secret';
		$result = $wpdb->insert(
			$names['access_audit_events'],
			array(
				'event_code' => 'DOC-' . bin2hex( random_bytes( 12 ) ),
				'actor_id' => get_current_user_id(),
				'subject_type' => 'DOCUMENT',
				'subject_id' => absint( $document_id ),
				'action_code' => sanitize_key( $action ),
				'outcome' => sanitize_key( $outcome ),
				'request_id' => sanitize_text_field( $request_id ),
				'client_ip_hash' => '' !== $ip ? hash_hmac( 'sha256', $ip, $secret ) : null,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		return false !== $result;
	}

	public function deny_anonymous() {
		status_header( 401 );
		wp_die( esc_html__( 'Sign in with an authorized Stockland HR account to download this document.', 'stockland-erp' ), esc_html__( 'Private HR document access denied', 'stockland-erp' ), array( 'response' => 401 ) );
	}

	public function download_handler() {
		$id = isset( $_GET['document_id'] ) ? absint( wp_unslash( $_GET['document_id'] ) ) : 0;
		if ( $id < 1 || ! is_user_logged_in() ) {
			status_header( 401 );
			wp_die( esc_html__( 'Sign in to download this private document.', 'stockland-erp' ), esc_html__( 'Private HR document access denied', 'stockland-erp' ), array( 'response' => 401 ) );
		}
		check_admin_referer( self::ACTION . '_' . $id, '_wpnonce' );
		$document = $this->repo->get( 'documents', $id );
		if ( ! $document || 'APPROVED' !== $document['status'] || ! $this->authorize_document( $document ) ) {
			$this->record_access( $id, 'DOWNLOAD', 'DENIED' );
			status_header( 403 );
			wp_die( esc_html__( 'You are not authorized to download this document, or it has not been approved.', 'stockland-erp' ), esc_html__( 'Private HR document access denied', 'stockland-erp' ), array( 'response' => 403 ) );
		}
		$decoded = $this->decrypt( $id );
		if ( is_wp_error( $decoded ) ) {
			$this->record_access( $id, 'DOWNLOAD', 'INTEGRITY_FAILED' );
			status_header( 500 );
			wp_die( esc_html__( 'Document integrity verification failed. No file was released.', 'stockland-erp' ), esc_html__( 'Private HR document error', 'stockland-erp' ), array( 'response' => 500 ) );
		}
		if ( ! $this->record_access( $id, 'DOWNLOAD', 'SUCCESS' ) ) {
			status_header( 503 );
			wp_die( esc_html__( 'The access audit could not be recorded. The document was not released.', 'stockland-erp' ), esc_html__( 'Private HR document audit unavailable', 'stockland-erp' ), array( 'response' => 503 ) );
		}
		$filename = $this->safe_filename( $document['original_filename'] );
		if ( false === $filename ) {
			$filename = 'Stockland_HR_Document_' . $id;
		}
		if ( ob_get_level() ) {
			while ( ob_get_level() ) {
				ob_end_clean();
			}
		}
		nocache_headers();
		header( 'Content-Type: ' . sanitize_text_field( $document['content_type'] ) );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $decoded['bytes'] ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, no-store, max-age=0, must-revalidate' );
		echo $decoded['bytes']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Authenticated encrypted-vault attachment bytes.
		exit;
	}
}
