<?php
/** Strict validation primitives for the isolated HR replacement people lifecycle. */
if ( ! defined( 'ABSPATH' ) && ! defined( 'STOCKLAND_HR_REPLACEMENT_TESTING' ) ) { exit; }

final class Stockland_HR_Replacement_People_Contract {
	public static function deny( $code, $message, $status = 422 ) {
		throw new DomainException( $code . ': ' . $message, (int) $status );
	}
	public static function text( $value, $field, $min = 1, $max = 190 ) {
		if ( ! is_string( $value ) ) { self::deny( 'HRXP_INVALID_' . strtoupper( $field ), $field . ' must be text.' ); }
		$value = trim( preg_replace( '/\s+/u', ' ', strip_tags( $value ) ) );
		if ( strlen( $value ) < $min || strlen( $value ) > $max ) { self::deny( 'HRXP_INVALID_' . strtoupper( $field ), $field . ' length is outside the permitted range.' ); }
		return $value;
	}
	public static function code( $value, $field ) {
		$value = strtoupper( self::text( $value, $field, 3, 48 ) );
		if ( ! preg_match( '/^[A-Z0-9][A-Z0-9._\/-]*$/D', $value ) ) { self::deny( 'HRXP_INVALID_' . strtoupper( $field ), $field . ' contains unsupported characters.' ); }
		return $value;
	}
	public static function positive_id( $value, $field ) {
		if ( ! ( is_int( $value ) || ( is_string( $value ) && preg_match( '/^[1-9][0-9]*$/D', $value ) ) ) ) { self::deny( 'HRXP_INVALID_' . strtoupper( $field ), $field . ' must be a positive integer.' ); }
		$n = (int) $value;
		if ( $n < 1 || $n > 2147483647 ) { self::deny( 'HRXP_INVALID_' . strtoupper( $field ), $field . ' is outside the supported range.' ); }
		return $n;
	}
	public static function date( $value, $field, $nullable = false ) {
		if ( $nullable && ( null === $value || '' === $value ) ) { return null; }
		if ( ! is_string( $value ) || ! preg_match( '/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			self::deny( 'HRXP_INVALID_' . strtoupper( $field ), $field . ' must be a real YYYY-MM-DD date.' );
		}
		return $value;
	}
	public static function datetime( $value, $field, $nullable = false ) {
		if ( $nullable && ( null === $value || '' === $value ) ) { return null; }
		if ( ! is_string( $value ) || ! preg_match( '/^([0-9]{4}-[0-9]{2}-[0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})$/D', $value, $m ) || ! self::date( $m[1], $field ) || (int) $m[2] > 23 || (int) $m[3] > 59 || (int) $m[4] > 59 ) {
			self::deny( 'HRXP_INVALID_' . strtoupper( $field ), $field . ' must be a real YYYY-MM-DD HH:MM:SS datetime.' );
		}
		return $value;
	}
	public static function hash( $value, $field ) {
		if ( ! is_string( $value ) || ! preg_match( '/^[a-f0-9]{64}$/iD', $value ) ) { self::deny( 'HRXP_INVALID_' . strtoupper( $field ), $field . ' must be a SHA-256 hash.' ); }
		return strtolower( $value );
	}
	public static function private_reference( $value ) {
		$value = self::text( $value, 'private_document_reference', 3, 190 );
		$allowed_vault_object = 1 === preg_match( '~^vault://[A-Za-z0-9][A-Za-z0-9._/-]{1,175}$~D', $value );
		$allowed_audit_receipt = 1 === preg_match( '~^vault-audit:[A-Za-z0-9][A-Za-z0-9._:-]{1,175}$~D', $value );
		$traversal = 1 === preg_match( '~(?:^|/)\.\.(?:/|$)~', $value );
		if ( ( ! $allowed_vault_object && ! $allowed_audit_receipt ) || $traversal || preg_match( '/[\x00-\x1F\x7F]/', $value ) ) {
			self::deny( 'HRXP_PUBLIC_DOCUMENT_REFERENCE', 'Private HR documents must use an approved vault:// object reference or vault-audit: receipt. URLs, unsupported schemes and path traversal are prohibited.' );
		}
		return $value;
	}

	public static function assert_independent( $actor, $maker, $action ) {
		if ( (int) $actor < 1 ) { self::deny( 'HRXP_ACTOR_REQUIRED', 'An authenticated actor is required.', 401 ); }
		if ( (int) $actor === (int) $maker ) { self::deny( 'HRXP_MAKER_CHECKER', 'The preparer cannot ' . $action . ' their own record.', 403 ); }
	}
	public static function application_stage( $from, $to, $context ) {
		$map = array(
			'APPLIED' => array( 'SCREENING', 'WITHDRAWN' ),
			'SCREENING' => array( 'INTERVIEW', 'WITHDRAWN' ),
			'INTERVIEW' => array( 'REFERENCE_REVIEW', 'WITHDRAWN' ),
			'REFERENCE_REVIEW' => array( 'RECOMMENDED', 'WITHDRAWN' ),
			'RECOMMENDED' => array( 'SELECTED', 'NOT_SELECTED', 'WITHDRAWN' ),
			'SELECTED' => array( 'OFFERED' ),
			'OFFERED' => array( 'ACCEPTED', 'DECLINED', 'WITHDRAWN' ),
			'ACCEPTED' => array( 'HIRED' ),
			'HIRED' => array(),
			'NOT_SELECTED' => array(),
			'DECLINED' => array(),
			'WITHDRAWN' => array(),
		);
		$from = strtoupper( (string) $from ); $to = strtoupper( (string) $to );
		if ( ! isset( $map[ $from ] ) || ! in_array( $to, $map[ $from ], true ) ) { self::deny( 'HRXP_TRANSITION_NOT_ALLOWED', 'The recruitment transition is not allowed.' ); }
		if ( in_array( $to, array( 'SELECTED', 'NOT_SELECTED', 'ACCEPTED', 'DECLINED' ), true ) ) {
			self::assert_independent( $context['actor_id'] ?? 0, $context['created_by'] ?? 0, 'finalize a recruitment decision' );
			if ( empty( $context['human_decision'] ) ) { self::deny( 'HRXP_HUMAN_DECISION_REQUIRED', 'Selection and rejection require an explicit human decision.' ); }
			self::text( (string) ( $context['rationale'] ?? '' ), 'decision_rationale', 20, 1000 );
		}
		return array( 'from' => $from, 'to' => $to );
	}
}
