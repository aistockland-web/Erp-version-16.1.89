<?php
/**
 * Strict input and transition contracts for the Stockland HR replacement.
 *
 * No database writes, hardcoded statutory rules, or production side effects.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'STOCKLAND_HR_REPLACEMENT_TESTING' ) ) {
	exit;
}

final class Stockland_HR_Replacement_Workforce_Contract {
	public static function deny( $code, $message, $status = 422 ) {
		throw new DomainException( $code . ': ' . $message, (int) $status );
	}

	public static function text( $value, $field, $min = 1, $max = 190 ) {
		if ( ! is_string( $value ) ) {
			self::deny( 'invalid_' . $field, $field . ' must be text.' );
		}
		$value = trim( preg_replace( '/\s+/u', ' ', strip_tags( $value ) ) );
		if ( strlen( $value ) < $min || strlen( $value ) > $max ) {
			self::deny( 'invalid_' . $field, $field . ' must contain ' . $min . '–' . $max . ' characters.' );
		}
		return $value;
	}

	public static function code( $value, $field, $max = 48 ) {
		$value = self::text( $value, $field, 3, $max );
		$value = strtoupper( $value );
		if ( ! preg_match( '/^[A-Z0-9][A-Z0-9._\/-]*$/D', $value ) ) {
			self::deny( 'invalid_' . $field, $field . ' may contain only A–Z, digits, dot, underscore, slash and hyphen.' );
		}
		return $value;
	}

	public static function positive_int( $value, $field, $max = 1000000 ) {
		if ( is_string( $value ) && ! preg_match( '/^[0-9]+$/D', $value ) ) {
			self::deny( 'invalid_' . $field, $field . ' must be a whole number.' );
		}
		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			self::deny( 'invalid_' . $field, $field . ' must be a whole number.' );
		}
		$value = (int) $value;
		if ( $value < 1 || $value > $max ) {
			self::deny( 'invalid_' . $field, $field . ' is outside the permitted range.' );
		}
		return $value;
	}

	public static function optional_id( $value, $field ) {
		if ( null === $value || '' === $value || 0 === $value || '0' === $value ) {
			return null;
		}
		return self::positive_int( $value, $field );
	}

	/** Money must arrive as a decimal string; floats are intentionally rejected. */
	public static function money( $value, $field, $allow_zero = true ) {
		if ( ! is_string( $value ) || ! preg_match( '/^(0|[1-9][0-9]{0,11})(?:\.([0-9]{1,2}))?$/D', $value ) ) {
			self::deny( 'invalid_' . $field, $field . ' must be a decimal string with up to two decimal places; implicit rounding is not allowed.' );
		}
		$parts = explode( '.', $value, 2 );
		$whole = $parts[0];
		$fraction = isset( $parts[1] ) ? str_pad( $parts[1], 2, '0' ) : '00';
		$minor = ( (int) $whole * 100 ) + (int) $fraction;
		if ( ! $allow_zero && $minor <= 0 ) {
			self::deny( 'invalid_' . $field, $field . ' must be greater than zero.' );
		}
		return $whole . '.' . $fraction;
	}

	public static function currency( $value ) {
		$value = self::code( $value, 'currency', 3 );
		if ( ! preg_match( '/^[A-Z]{3}$/D', $value ) ) {
			self::deny( 'invalid_currency', 'Currency must be a three-letter ISO-style code.' );
		}
		return $value;
	}

	public static function date( $value, $field, $optional = false ) {
		if ( $optional && ( null === $value || '' === $value ) ) {
			return null;
		}
		if ( ! is_string( $value ) || ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value, $match ) ) {
			self::deny( 'invalid_' . $field, $field . ' must be a calendar date in YYYY-MM-DD format.' );
		}
		$parts = explode( '-', $value );
		if ( ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) {
			self::deny( 'invalid_' . $field, $field . ' is not a valid calendar date.' );
		}
		return $value;
	}

	public static function choice( $value, $field, $allowed ) {
		if ( ! is_string( $value ) ) {
			self::deny( 'invalid_' . $field, $field . ' is required.' );
		}
		$value = strtoupper( trim( $value ) );
		if ( ! in_array( $value, $allowed, true ) ) {
			self::deny( 'invalid_' . $field, $field . ' must be one of: ' . implode( ', ', $allowed ) . '.' );
		}
		return $value;
	}

	public static function idempotency_key( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,119}$/D', $value ) ) {
			self::deny( 'idempotency_key_required', 'Each change must include an Idempotency-Key header (8–120 safe characters).', 400 );
		}
		return $value;
	}

	public static function request_hash( $operation, $payload ) {
		$normalized = self::sort_recursive( array( 'operation' => (string) $operation, 'payload' => (array) $payload ) );
		return hash( 'sha256', (string) wp_json_encode( $normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	private static function sort_recursive( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
			ksort( $value );
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::sort_recursive( $item );
		}
		return $value;
	}

	public static function require_transition( $aggregate, $from, $to, $context ) {
		if ( ! class_exists( 'Stockland_HR_Workflow_State_Machine' ) ) {
			self::deny( 'workflow_contract_unavailable', 'The approved HR state-machine contract is not loaded.', 503 );
		}
		try {
			return Stockland_HR_Workflow_State_Machine::transition( $aggregate, $from, $to, (array) $context );
		} catch ( DomainException $e ) {
			throw new DomainException( 'HRX_TRANSITION_BLOCKED: ' . $e->getMessage(), 409, $e );
		}
	}

	public static function decode_decision( $value ) {
		return self::choice( $value, 'decision', array( 'APPROVE', 'REJECT', 'NEEDS_INFORMATION' ) );
	}
}
