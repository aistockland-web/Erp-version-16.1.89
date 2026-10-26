<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Access-controlled downloads for general HR process resources.
 *
 * Resource payloads are embedded in a PHP class so the .docx bytes are not placed
 * at predictable public URLs under the WordPress plugin directory.
 */
final class Stockland_HR_Resources {
	const ACTION = 'stockland_hr_resource_download';
	const MIME_DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

	/**
	 * Register authenticated and anonymous download paths.
	 */
	public function __construct() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'download' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'deny_anonymous' ) );
	}

	/**
	 * Authoritative allow-list. No user-supplied filename is ever used as a path.
	 *
	 * @return array<string,array<string,string>> Resource metadata.
	 */
	private static function catalog() {
		return array(
			'process-center-guide' => array(
				'filename'    => 'Stockland_HR_Process_Center_Guide_v1.docx',
				'title'       => 'HR Process Center Guide',
				'category'    => 'PROCESS GUIDE',
				'description' => 'Employee lifecycle, maker-checker, privacy, legal-rule and monthly payroll controls.',
			),
			'onboarding-checklist' => array(
				'filename'    => 'Stockland_Employee_Onboarding_Checklist_v1.docx',
				'title'       => 'Employee Onboarding Checklist',
				'category'    => 'ONBOARDING',
				'description' => 'Blank checklist for contract, verified documents, payroll setup, induction and access sign-off.',
			),
			'payroll-review-checklist' => array(
				'filename'    => 'Stockland_Monthly_Payroll_Review_Checklist_v1.docx',
				'title'       => 'Monthly Payroll Review Checklist',
				'category'    => 'PAYROLL CONTROL',
				'description' => 'Management preparation, independent Owner approval, exception review and GL/PLEDGER reconciliation.',
			),
		);
	}

	/**
	 * Builds download links only for users authorized to view Stockland HR.
	 *
	 * @return array<int,array<string,string>> Safe UI metadata and signed links.
	 */
	public static function downloads() {
		if ( ! class_exists( 'Stockland_Authority' ) || ! Stockland_Authority::can_view_hr() ) {
			return array();
		}
		$resources = array();
		foreach ( self::catalog() as $key => $item ) {
			$url = add_query_arg(
				array(
					'action'   => self::ACTION,
					'resource' => $key,
				),
				admin_url( 'admin-post.php' )
			);
			$url = wp_nonce_url( $url, self::ACTION . '_' . $key, '_wpnonce' );
			$resources[] = array(
				'id'          => $key,
				'filename'    => $item['filename'],
				'title'       => $item['title'],
				'category'    => $item['category'],
				'description' => $item['description'],
				'language'    => 'English',
				'version'     => 'v1.0',
				'download_url'=> esc_url_raw( $url ),
			);
		}
		return $resources;
	}

	/**
	 * Reject anonymous requests with a clear status instead of returning "0".
	 */
	public function deny_anonymous() {
		status_header( 401 );
		wp_die( esc_html__( 'Sign in with an authorized Stockland HR account to download this resource.', 'stockland-erp' ), esc_html__( 'HR resource access denied', 'stockland-erp' ), array( 'response' => 401 ) );
	}

	/**
	 * Stream a genuine DOCX payload following nonce and server-side role checks.
	 *
	 * @return void
	 */
	public function download() {
		$key = isset( $_GET['resource'] ) ? sanitize_key( wp_unslash( $_GET['resource'] ) ) : '';
		$catalog = self::catalog();
		if ( ! isset( $catalog[ $key ] ) ) {
			status_header( 404 );
			wp_die( esc_html__( 'The requested HR resource is not available.', 'stockland-erp' ), esc_html__( 'HR resource not found', 'stockland-erp' ), array( 'response' => 404 ) );
		}
		if ( ! is_user_logged_in() || ! class_exists( 'Stockland_Authority' ) || ! Stockland_Authority::can_view_hr() ) {
			status_header( 403 );
			wp_die( esc_html__( 'You do not have permission to download HR resources.', 'stockland-erp' ), esc_html__( 'HR resource access denied', 'stockland-erp' ), array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION . '_' . $key, '_wpnonce' );
		if ( ! class_exists( 'Stockland_HR_Resource_Files' ) ) {
			status_header( 500 );
			wp_die( esc_html__( 'The HR resource library is not available in this package.', 'stockland-erp' ), esc_html__( 'HR resource error', 'stockland-erp' ), array( 'response' => 500 ) );
		}
		$bytes = Stockland_HR_Resource_Files::get( $key );
		if ( ! is_string( $bytes ) || strlen( $bytes ) < 1500 || 0 !== substr_compare( $bytes, "PK\x03\x04", 0, 4 ) ) {
			status_header( 500 );
			wp_die( esc_html__( 'The requested DOCX failed its integrity check. No document was sent.', 'stockland-erp' ), esc_html__( 'HR resource integrity error', 'stockland-erp' ), array( 'response' => 500 ) );
		}
		$filename = $catalog[ $key ]['filename'];
		if ( ob_get_level() ) {
			while ( ob_get_level() ) {
				ob_end_clean();
			}
		}
		nocache_headers();
		header( 'Content-Type: ' . self::MIME_DOCX );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $bytes ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, no-store, max-age=0, must-revalidate' );
		echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary OOXML DOCX bytes are intentionally streamed as an attachment.
		exit;
	}
}
