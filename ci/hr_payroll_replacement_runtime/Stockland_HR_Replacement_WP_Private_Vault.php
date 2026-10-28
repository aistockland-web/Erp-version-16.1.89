<?php
/**
 * Fail-closed adapter boundary for the host's private document repository.
 *
 * A host integration must return verified=true, public_access=false, and the actual
 * SHA-256 of the object. Without that signed-off integration, documents cannot be
 * marked VERIFIED and contract approval is blocked.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'STOCKLAND_HR_REPLACEMENT_TESTING' ) ) { exit; }
final class Stockland_HR_Replacement_WP_Private_Vault implements Stockland_HR_Replacement_Private_Vault {
	public function verify_reference( $private_reference, $expected_sha256, $actor_id ) {
		if ( ! function_exists( 'apply_filters' ) ) {
			return array( 'verified' => false, 'public_access' => true, 'reason' => 'WordPress private-vault integration is unavailable.' );
		}
		$result = apply_filters( 'stockland_hr_private_document_verify', null, (string) $private_reference, (string) $expected_sha256, (int) $actor_id );
		if ( ! is_array( $result ) ) {
			return array( 'verified' => false, 'public_access' => true, 'reason' => 'No approved private-vault adapter answered the verification request.' );
		}
		return $result;
	}
}
