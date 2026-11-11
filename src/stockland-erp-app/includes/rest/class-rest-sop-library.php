<?php
/** Controlled ERP SOP catalogue and audit-logged document download. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Stockland_REST_SOP_Library extends Stockland_REST_Base {
	public function __construct() {
		add_action( 'stockland_register_rest_routes', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/hr/sops',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_sops' ),
				'permission_callback' => array( $this, 'can_view_sops' ),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/hr/sops/(?P<code>STK-ERP-SOP-[0-9]{3})/download',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'download_sop' ),
				'permission_callback' => array( $this, 'can_view_sops' ),
			)
		);
	}

	public function can_view_sops() {
		if ( current_user_can( 'manage_options' ) || Stockland_Authority::can_view_hr() || Stockland_Authority::can_view_payroll() ) {
			return true;
		}
		return new WP_Error( 'stockland_sop_forbidden', 'HR or Payroll viewing permission is required.', array( 'status' => 403 ) );
	}

	private function manifest() {
		$path = trailingslashit( STOCKLAND_PLUGIN_DIR ) . 'resources/erp-sops/manifest.json';
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'stockland_sop_manifest_unavailable', 'The ERP SOP catalogue is not installed.', array( 'status' => 503 ) );
		}
		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bundled, read-only plugin manifest.
		$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $data ) || empty( $data['documents'] ) || ! is_array( $data['documents'] ) ) {
			return new WP_Error( 'stockland_sop_manifest_invalid', 'The ERP SOP catalogue manifest could not be verified.', array( 'status' => 503 ) );
		}
		return $data;
	}

	private function resolve_document_file( $doc ) {
		if ( ! is_array( $doc ) || empty( $doc['file'] ) || empty( $doc['sha256'] ) || empty( $doc['download_filename'] ) ) {
			return new WP_Error( 'stockland_sop_entry_invalid', 'The SOP catalogue entry is incomplete.', array( 'status' => 503 ) );
		}
		$payload_name = (string) $doc['file'];
		$download_name = (string) $doc['download_filename'];
		if ( ! preg_match( '/\Apayloads\/STK_ERP_SOP_[0-9]{3}(?:_[A-Za-z0-9_-]+)?\.php\z/i', $payload_name ) || ! preg_match( '/\ASTK_ERP_SOP_[0-9]{3}(?:_[A-Za-z0-9_-]+)?\.docx\z/i', $download_name ) || ! preg_match( '/\A[a-f0-9]{64}\z/i', (string) $doc['sha256'] ) ) {
			return new WP_Error( 'stockland_sop_entry_invalid', 'The SOP payload or integrity reference is invalid.', array( 'status' => 503 ) );
		}
		$root = realpath( trailingslashit( STOCKLAND_PLUGIN_DIR ) . 'resources/erp-sops' );
		$file = $root ? realpath( $root . DIRECTORY_SEPARATOR . $payload_name ) : false;
		if ( ! $root || ! $file || 0 !== strpos( $file, $root . DIRECTORY_SEPARATOR ) || ! is_file( $file ) || ! is_readable( $file ) ) {
			return new WP_Error( 'stockland_sop_file_missing', 'The controlled SOP payload is not available.', array( 'status' => 404 ) );
		}
		// Payload files are PHP-guarded so a direct web request exits without exposing DOCX bytes.
		$encoded = include $file;
		$contents = is_string( $encoded ) ? base64_decode( $encoded, true ) : false;
		if ( ! is_string( $contents ) || '' === $contents ) {
			return new WP_Error( 'stockland_sop_payload_invalid', 'The controlled SOP payload could not be decoded.', array( 'status' => 503 ) );
		}
		$actual = hash( 'sha256', $contents );
		if ( ! is_string( $actual ) || ! hash_equals( strtolower( (string) $doc['sha256'] ), strtolower( $actual ) ) || ( isset( $doc['bytes'] ) && (int) $doc['bytes'] !== strlen( $contents ) ) ) {
			return new WP_Error( 'stockland_sop_hash_mismatch', 'The SOP document failed its integrity check; download blocked.', array( 'status' => 409 ) );
		}
		return array( 'contents' => $contents, 'filename' => $download_name, 'sha256' => $actual, 'bytes' => strlen( $contents ) );
	}

	public function list_sops() {
		$data = $this->manifest();
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$items = array();
		$seen  = array();
		foreach ( $data['documents'] as $doc ) {
			if ( ! is_array( $doc ) || empty( $doc['id'] ) || ! preg_match( '/\\ASTK-ERP-SOP-[0-9]{3}\\z/', (string) $doc['id'] ) || isset( $seen[ $doc['id'] ] ) ) {
				continue;
			}
			$seen[ $doc['id'] ] = true;
			$file = $this->resolve_document_file( $doc );
			$ok   = ! is_wp_error( $file );
			$items[] = array(
				'id'           => sanitize_text_field( $doc['id'] ),
				'title'        => sanitize_text_field( $doc['title'] ?? $doc['id'] ),
				'owner'        => sanitize_text_field( $doc['owner'] ?? '' ),
				'approver'     => sanitize_text_field( $doc['approver'] ?? '' ),
				'version'      => sanitize_text_field( $doc['document_version'] ?? 'v1.0-DRAFT' ),
				'status'       => sanitize_text_field( $doc['status'] ?? 'DRAFT_NOT_APPROVED' ),
				'language'     => sanitize_text_field( $doc['language'] ?? 'am-ET' ),
				'summary'      => sanitize_text_field( $doc['summary'] ?? '' ),
				'downloadable' => $ok,
				'bytes'        => $ok ? (int) $file['bytes'] : 0,
			);
		}
		return rest_ensure_response(
			array(
				'items'  => $items,
				'count'  => count( $items ),
				'status' => sanitize_text_field( $data['status'] ?? 'DRAFT_NOT_APPROVED' ),
				'note'   => 'General ERP procedures only. Employee contracts, personnel files and other private documents use the separate private-document workflow.',
			)
		);
	}

	public function download_sop( $request ) {
		$data = $this->manifest();
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$code = sanitize_text_field( (string) $request->get_param( 'code' ) );
		$doc  = null;
		foreach ( $data['documents'] as $candidate ) {
			if ( is_array( $candidate ) && isset( $candidate['id'] ) && hash_equals( (string) $candidate['id'], $code ) ) {
				$doc = $candidate;
				break;
			}
		}
		if ( ! $doc ) {
			return new WP_Error( 'stockland_sop_not_found', 'The requested ERP SOP was not found.', array( 'status' => 404 ) );
		}
		$file = $this->resolve_document_file( $doc );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		if ( headers_sent() ) {
			return new WP_Error( 'stockland_sop_headers_sent', 'The document response could not be prepared.', array( 'status' => 500 ) );
		}
		$contents = $file['contents'];
		if ( ! is_string( $contents ) || '' === $contents ) {
			return new WP_Error( 'stockland_sop_read_failed', 'The controlled SOP could not be read.', array( 'status' => 500 ) );
		}
		if ( ! class_exists( 'Stockland_Audit' ) || ! Stockland_DB::table_exists( Stockland_DB::table( 'audit_logs' ) ) ) {
			return new WP_Error( 'stockland_sop_audit_unavailable', 'The ERP audit log is unavailable; the document was not served.', array( 'status' => 503 ) );
		}
		$logged = false;
		try {
			$logged = Stockland_Audit::log( get_current_user_id(), null, 'ERP_SOP_DOWNLOAD', $code, 1, null, $file['sha256'], 'Controlled ERP SOP downloaded', $code, null );
		} catch ( Throwable $e ) {
			$logged = false;
		}
		if ( ! $logged ) {
			return new WP_Error( 'stockland_sop_audit_failed', 'The download event could not be recorded; the document was not served.', array( 'status' => 503 ) );
		}
		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $file['filename'] ) . '"' );
		header( 'Content-Length: ' . strlen( $contents ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow', true );
		echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary DOCX response.
		exit;
	}
}
