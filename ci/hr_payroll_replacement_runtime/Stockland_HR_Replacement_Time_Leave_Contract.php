<?php
/**
 * Validation and workflow contracts for the replacement HR time/leave domain.
 * No statutory pay multiplier, entitlement, tax rate or pension rate is embedded here.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'STOCKLAND_HR_REPLACEMENT_TESTING' ) ) { exit; }

final class Stockland_HR_Replacement_Time_Leave_Contract {
	public static function deny( $code, $message, $status = 422 ) {
		throw new DomainException( $code . ': ' . $message, (int) $status );
	}
	public static function text( $value, $field, $min = 1, $max = 190 ) {
		if ( ! is_string( $value ) ) { self::deny( 'HRXTL_INVALID_' . strtoupper( $field ), $field . ' must be text.' ); }
		$value = trim( preg_replace( '/\s+/u', ' ', strip_tags( $value ) ) );
		if ( strlen( $value ) < $min || strlen( $value ) > $max || preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value ) ) {
			self::deny( 'HRXTL_INVALID_' . strtoupper( $field ), $field . ' length or characters are outside the allowed range.' );
		}
		return $value;
	}
	public static function code( $value, $field, $max = 48 ) {
		$value = strtoupper( self::text( $value, $field, 3, $max ) );
		if ( ! preg_match( '/^[A-Z0-9][A-Z0-9._\/-]*$/D', $value ) ) { self::deny( 'HRXTL_INVALID_' . strtoupper( $field ), $field . ' contains unsupported characters.' ); }
		return $value;
	}
	public static function id( $value, $field ) {
		if ( ! ( is_int( $value ) || ( is_string( $value ) && preg_match( '/^[1-9][0-9]*$/D', $value ) ) ) ) { self::deny( 'HRXTL_INVALID_' . strtoupper( $field ), $field . ' must be a positive integer.' ); }
		$n = (int) $value;
		if ( $n < 1 || $n > 2147483647 ) { self::deny( 'HRXTL_INVALID_' . strtoupper( $field ), $field . ' is outside the supported range.' ); }
		return $n;
	}
	public static function date( $value, $field, $nullable = false ) {
		if ( $nullable && ( null === $value || '' === $value ) ) { return null; }
		if ( ! is_string( $value ) || ! preg_match( '/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			self::deny( 'HRXTL_INVALID_' . strtoupper( $field ), $field . ' must be a real YYYY-MM-DD date.' );
		}
		return $value;
	}
	public static function datetime( $value, $field, $nullable = false ) {
		if ( $nullable && ( null === $value || '' === $value ) ) { return null; }
		if ( ! is_string( $value ) || ! preg_match( '/^([0-9]{4}-[0-9]{2}-[0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})$/D', $value, $m ) ) {
			self::deny( 'HRXTL_INVALID_' . strtoupper( $field ), $field . ' must be YYYY-MM-DD HH:MM:SS.' );
		}
		self::date( $m[1], $field );
		if ( (int) $m[2] > 23 || (int) $m[3] > 59 || (int) $m[4] > 59 ) { self::deny( 'HRXTL_INVALID_' . strtoupper( $field ), $field . ' has an invalid time component.' ); }
		return $value;
	}
	public static function time( $value, $field ) {
		if ( ! is_string( $value ) || ! preg_match( '/^([0-9]{2}):([0-9]{2})(?::([0-9]{2}))?$/D', $value, $m ) || (int) $m[1] > 23 || (int) $m[2] > 59 || ( isset( $m[3] ) && (int) $m[3] > 59 ) ) {
			self::deny( 'HRXTL_INVALID_' . strtoupper( $field ), $field . ' must be a valid 24-hour time.' );
		}
		return sprintf( '%02d:%02d:%02d', (int) $m[1], (int) $m[2], isset( $m[3] ) ? (int) $m[3] : 0 );
	}
	public static function units( $value, $field, $allow_zero = true ) {
		if ( ! is_string( $value ) || ! preg_match( '/^(0|[1-9][0-9]{0,7})(?:\.([0-9]{1,4}))?$/D', $value ) ) {
			self::deny( 'HRXTL_INVALID_' . strtoupper( $field ), $field . ' must be a decimal string with up to four places; floats and implicit rounding are prohibited.' );
		}
		$parts = explode( '.', $value, 2 );
		$whole = $parts[0];
		$fraction = isset( $parts[1] ) ? str_pad( $parts[1], 4, '0' ) : '0000';
		$minor = ( (int) $whole * 10000 ) + (int) $fraction;
		if ( ! $allow_zero && $minor <= 0 ) { self::deny( 'HRXTL_INVALID_' . strtoupper( $field ), $field . ' must be greater than zero.' ); }
		return array( 'minor' => $minor, 'display' => $whole . '.' . $fraction );
	}
	public static function choice( $value, $field, $allowed ) {
		if ( ! is_string( $value ) ) { self::deny( 'HRXTL_INVALID_' . strtoupper( $field ), $field . ' is required.' ); }
		$value = strtoupper( trim( $value ) );
		if ( ! in_array( $value, $allowed, true ) ) { self::deny( 'HRXTL_INVALID_' . strtoupper( $field ), $field . ' must be one of: ' . implode( ', ', $allowed ) . '.' ); }
		return $value;
	}
	public static function hash( $value, $field ) {
		if ( ! is_string( $value ) || ! preg_match( '/^[a-f0-9]{64}$/iD', $value ) ) { self::deny( 'HRXTL_INVALID_' . strtoupper( $field ), $field . ' must be a SHA-256 hash.' ); }
		return strtolower( $value );
	}
	public static function json_object( $value, $field, $max_bytes = 12000 ) {
		if ( ! is_string( $value ) || strlen( $value ) > $max_bytes ) { self::deny( 'HRXTL_INVALID_' . strtoupper( $field ), $field . ' must be a bounded JSON object string.' ); }
		$decoded = json_decode( $value, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) || array_is_list( $decoded ) ) {
			self::deny( 'HRXTL_INVALID_' . strtoupper( $field ), $field . ' must be a JSON object, not arbitrary code or a list.' );
		}
		return $value;
	}
	public static function effective_dates( $from, $to, $field = 'effective_period' ) {
		$from = self::date( $from, $field . '_from' );
		$to = self::date( $to, $field . '_to', true );
		if ( null !== $to && $to < $from ) { self::deny( 'HRXTL_INVALID_' . strtoupper( $field ), 'Effective-to date cannot precede effective-from date.' ); }
		return array( $from, $to );
	}
	public static function independent( $actor, $maker, $action ) {
		if ( (int) $actor < 1 ) { self::deny( 'HRXTL_ACTOR_REQUIRED', 'An authenticated actor is required.', 401 ); }
		if ( (int) $actor === (int) $maker ) { self::deny( 'HRXTL_MAKER_CHECKER', 'The preparer cannot ' . $action . ' their own record.', 403 ); }
	}
	public static function worked_minutes( $clock_in, $clock_out, $break_minutes ) {
		$in = strtotime( self::datetime( $clock_in, 'clock_in' ) . ' UTC' );
		$out = strtotime( self::datetime( $clock_out, 'clock_out' ) . ' UTC' );
		if ( false === $in || false === $out || $out <= $in ) { self::deny( 'HRXTL_CLOCK_RANGE_INVALID', 'Clock-out must be later than clock-in; overnight shifts must use explicit datetimes.' ); }
		$elapsed = (int) floor( ( $out - $in ) / 60 );
		if ( $elapsed > 2160 ) { self::deny( 'HRXTL_CLOCK_RANGE_TOO_LONG', 'A single attendance record cannot exceed 36 elapsed hours; split and review unusual shifts.' ); }
		if ( ! is_int( $break_minutes ) && ! ( is_string( $break_minutes ) && preg_match( '/^[0-9]+$/D', $break_minutes ) ) ) { self::deny( 'HRXTL_INVALID_BREAK_MINUTES', 'Break minutes must be a non-negative integer.' ); }
		$break_minutes = (int) $break_minutes;
		if ( $break_minutes < 0 || $break_minutes > $elapsed ) { self::deny( 'HRXTL_INVALID_BREAK_MINUTES', 'Break minutes cannot exceed elapsed minutes.' ); }
		return $elapsed - $break_minutes;
	}
	public static function request_hash( $payload ) {
		return hash( 'sha256', (string) wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}
}
