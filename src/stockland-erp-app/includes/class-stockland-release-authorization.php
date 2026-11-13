<?php
/**
 * Owner go-live authorization.
 *
 * The ERP never marks itself "live ready" on its own. An active Stockland Owner
 * records one written authorization, bound to this exact ERP version and to the
 * exact physical application table inventory plus HR DDL fingerprint. The record
 * is written to the immutable audit chain. If the version or schema inventory
 * changes, the authorization is no longer current and the Owner must renew it.
 *
 * @package Stockland_ERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Stockland_Release_Authorization {

	const OPTION = 'stockland_release_authorization';

	/**
	 * Fingerprint of the exact schema this build declares.
	 *
	 * @return string SHA-256 fingerprint.
	 */
	public static function fingerprint() {
		$keys = Stockland_Activator::application_table_manifest();
		sort( $keys );
		$hr = class_exists( 'Stockland_HR' ) ? (string) Stockland_HR::schema_signature() : '';
		return hash( 'sha256', STOCKLAND_VERSION . '|' . implode( ',', $keys ) . '|' . $hr );
	}

	/**
	 * Current authorization record, if it matches this build.
	 *
	 * @return array{authorized:bool,status:string,record:array} Authorization state.
	 */
	public static function status() {
		$rec = get_option( self::OPTION, array() );
		if ( ! is_array( $rec ) || empty( $rec['fingerprint'] ) ) {
			return array(
				'authorized' => false,
				'status'     => 'AWAITING_OWNER_AUTHORIZATION',
				'record'     => array(),
			);
		}
		$current = (string) ( $rec['version'] ?? '' ) === STOCKLAND_VERSION
			&& hash_equals( self::fingerprint(), (string) $rec['fingerprint'] )
			&& (int) ( $rec['authorized_by'] ?? 0 ) > 0;
		return array(
			'authorized' => $current,
			'status'     => $current ? 'AUTHORIZED_BY_OWNER' : 'AUTHORIZATION_STALE_RENEWAL_REQUIRED',
			'record'     => array(
				'version'       => (string) ( $rec['version'] ?? '' ),
				'authorized_by' => (int) ( $rec['authorized_by'] ?? 0 ),
				'authorized_at' => (string) ( $rec['authorized_at'] ?? '' ),
				'reason'        => (string) ( $rec['reason'] ?? '' ),
			),
		);
	}

	/**
	 * Whether this build is currently authorized for live operation.
	 *
	 * @return bool Authorization state.
	 */
	public static function is_authorized() {
		$s = self::status();
		return ! empty( $s['authorized'] );
	}

	/**
	 * Record the Owner's authorization.
	 *
	 * @param int    $user_id WordPress user ID of the authorizing Owner.
	 * @param string $reason  Written reason (minimum 12 characters).
	 * @return true|WP_Error Success or the reason it was refused.
	 */
	public static function authorize( $user_id, $reason ) {
		$user_id = (int) $user_id;
		$reason  = sanitize_text_field( (string) $reason );
		if ( ! $user_id || ! Stockland_Authority::is_owner( $user_id ) ) {
			return new WP_Error( 'owner_required', 'Only the active Stockland Owner can authorize live operation.', array( 'status' => 403 ) );
		}
		if ( strlen( trim( $reason ) ) < 12 ) {
			return new WP_Error( 'reason_required', 'A written authorization reason of at least 12 characters is required.', array( 'status' => 400 ) );
		}
		$missing = Stockland_Activator::application_missing_tables();
		if ( ! empty( $missing ) ) {
			return new WP_Error( 'schema_incomplete', 'The Core/Finance ERP database is incomplete. Missing: ' . implode( ', ', $missing ), array( 'status' => 409 ) );
		}
		if ( ! Stockland_HR::is_schema_ready() ) {
			return new WP_Error( 'hr_schema_pending', 'Live release authorization is blocked until the active Owner approves the exact HR/Payroll schema fingerprint and physical verification passes.', array( 'status' => 409 ) );
		}
		$fp = self::fingerprint();
		if ( ! Stockland_Audit::log( $user_id, null, 'RELEASE_AUTHORIZED_FOR_LIVE', null, null, null, STOCKLAND_VERSION . ':' . $fp, $reason, 'RELEASE_' . STOCKLAND_VERSION, $user_id ) ) {
			return new WP_Error( 'audit_failed', 'The authorization could not be written to the immutable audit chain. Nothing was changed.', array( 'status' => 500 ) );
		}
		update_option(
			self::OPTION,
			array(
				'version'       => STOCKLAND_VERSION,
				'fingerprint'   => $fp,
				'authorized_by' => $user_id,
				'authorized_at' => current_time( 'mysql' ),
				'reason'        => $reason,
			),
			false
		);
		return true;
	}

	/** Register the Owner-only REST routes. */
	public static function register_routes() {
		register_rest_route(
			STOCKLAND_REST_NAMESPACE,
			'/system/release-authorization',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => function () {
						return rest_ensure_response( self::status() );
					},
					'permission_callback' => function () {
						return Stockland_Authority::can_view_system_diagnostics() ? true : new WP_Error( 'forbidden', 'Restricted to administrators, Owner and Management.', array( 'status' => 403 ) );
					},
				),
				array(
					'methods'             => 'POST',
					'callback'            => function ( WP_REST_Request $r ) {
						$out = self::authorize( get_current_user_id(), (string) $r->get_param( 'reason' ) );
						return is_wp_error( $out ) ? $out : rest_ensure_response( self::status() );
					},
					'permission_callback' => function () {
						return Stockland_Authority::is_owner() ? true : new WP_Error( 'owner_required', 'Only the active Stockland Owner can authorize live operation.', array( 'status' => 403 ) );
					},
				),
			)
		);
	}
}
