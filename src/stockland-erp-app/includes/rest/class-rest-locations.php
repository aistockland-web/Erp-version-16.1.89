<?php
// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName -- The established REST controller path is loaded by the plugin bootstrap; renaming it would break the include contract.
/**
 * REST controller source for Stockland ERP operational workflows.
 *
 * @package Stockland_ERP
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Legacy filename is a loader compatibility contract; only this sniff is disabled.
/**
 * Location administration and assigned-scope endpoints for Stockland ERP.
 *
 * @package Stockland_ERP
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Provides role-checked organization location read/write operations.
 *
 * @package Stockland_ERP
 */
class Stockland_REST_Locations {
	const NS = 'stockland/v16';
	/**
	 * Registers organization location routes.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'stockland_register_rest_routes', array( $this, 'routes' ) );}
	/**
	 * Registers location listing, creation and update endpoints.
	 *
	 * @return void
	 */
	public function routes() {
		register_rest_route(
			self::NS,
			'/organization/locations',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list' ),
				'permission_callback' => function () {
					return Stockland_Authority::assert_profile();
				},
			)
		);
		register_rest_route(
			self::NS,
			'/organization/locations',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create' ),
				'permission_callback' => function () {
					return Stockland_Authority::can_manage_locations() ? true : new WP_Error( 'forbidden', 'Only Owner may add a branch/location.', array( 'status' => 403 ) );
				},
			)
		);
		register_rest_route(
			self::NS,
			'/organization/locations/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update' ),
				'permission_callback' => function () {
					return Stockland_Authority::can_manage_locations() ? true : new WP_Error( 'forbidden', 'Only Owner may change a branch/location.', array( 'status' => 403 ) );
				},
			)
		);
	}
	/**
	 * Lists active locations permitted by the current ERP role.
	 *
	 * @return mixed REST response containing locations or a WP_Error.
	 */
	public function list() {
		global $wpdb;
		$rows = Stockland_DB::read_results( $wpdb->prepare( "SELECT id,location_name,branch_code,location_type,role_description,is_active FROM %i ORDER BY (location_type='Warehouse') DESC, location_name", Stockland_DB::table( 'locations' ) ), ARRAY_A );
		foreach ( $rows as &$row ) {
			$row['profile'] = self::profile_for( (int) $row['id'] );
		}
		unset( $row );
		return rest_ensure_response( $rows );}
	/**
	 * Setting key that holds the editable address profile of one branch.
	 *
	 * @param int $location_id Location identifier.
	 * @return string Setting key.
	 */
	private static function profile_key( $location_id ) {
		return 'location_profile_' . (int) $location_id;
	}
	/**
	 * Reads the stored address profile of a branch, with empty defaults.
	 *
	 * @param int $location_id Location identifier.
	 * @return array Profile fields plus relocation history.
	 */
	public static function profile_for( $location_id ) {
		$blank  = array(
			'address_line' => '', 'city' => '', 'sub_city' => '', 'kebele' => '', 'landmark' => '', 'phone' => '', 'maps_url' => '',
			'history'      => array(), 'updated_at' => '',
		);
		$stored = json_decode( (string) Stockland_DB::get_setting( self::profile_key( $location_id ), '' ), true );
		return is_array( $stored ) ? array_merge( $blank, array_intersect_key( $stored, $blank ) ) : $blank;
	}
	/**
	 * Validates a submitted address profile and builds the next stored profile, keeping relocation history.
	 *
	 * @param array  $current Stored profile.
	 * @param array  $input Submitted profile fields.
	 * @param string $reason Relocation reason, required when an existing address changes.
	 * @param string $effective Date the new address takes effect (Y-m-d) or empty for today.
	 * @return array|WP_Error Next profile or validation error.
	 */
	private static function next_profile( $current, $input, $reason, $effective ) {
		$limits = array( 'address_line' => 160, 'city' => 80, 'sub_city' => 80, 'kebele' => 40, 'landmark' => 160, 'phone' => 30, 'maps_url' => 300 );
		$next   = $current;
		foreach ( $limits as $field => $max ) {
			if ( ! array_key_exists( $field, $input ) ) {
				continue;
			}
			$value = trim( sanitize_text_field( (string) $input[ $field ] ) );
			if ( strlen( $value ) > $max ) {
				return new WP_Error( 'invalid_profile', ucfirst( str_replace( '_', ' ', $field ) ) . ' is too long.', array( 'status' => 400 ) );
			}
			if ( 'phone' === $field && '' !== $value && ! preg_match( '/^[0-9+()\-\s]{5,30}$/D', $value ) ) {
				return new WP_Error( 'invalid_profile', 'Phone may contain digits, spaces, + ( ) and - only.', array( 'status' => 400 ) );
			}
			if ( 'maps_url' === $field && '' !== $value ) {
				$value = esc_url_raw( $value, array( 'https' ) );
				if ( '' === $value ) {
					return new WP_Error( 'invalid_profile', 'Map link must be a secure https address.', array( 'status' => 400 ) );
				}
			}
			$next[ $field ] = $value;
		}
		$place_fields = array( 'address_line', 'city', 'sub_city', 'kebele', 'landmark' );
		$moved        = false;
		$had_address  = false;
		foreach ( $place_fields as $field ) {
			if ( (string) $current[ $field ] !== (string) $next[ $field ] ) {
				$moved = true;
			}
			if ( '' !== (string) $current[ $field ] ) {
				$had_address = true;
			}
		}
		if ( $moved && $had_address ) {
			$reason = trim( sanitize_text_field( (string) $reason ) );
			if ( strlen( $reason ) < 5 ) {
				return new WP_Error( 'relocation_reason_required', 'Changing an existing branch address needs a relocation reason (at least 5 characters).', array( 'status' => 400 ) );
			}
			$date = '' === (string) $effective ? gmdate( 'Y-m-d' ) : (string) $effective;
			if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
				return new WP_Error( 'invalid_profile', 'Effective date must be a valid date (YYYY-MM-DD).', array( 'status' => 400 ) );
			}
			$entry = array( 'until' => $date, 'reason' => $reason, 'changed_by' => get_current_user_id() );
			foreach ( $place_fields as $field ) {
				$entry[ $field ] = (string) $current[ $field ];
			}
			$next['history']   = array_slice( array_merge( array( $entry ), (array) $current['history'] ), 0, 20 );
		}
		$next['updated_at'] = gmdate( 'Y-m-d H:i:s' );
		return $next;
	}
	/**
	 * Creates a location after Owner-only authority and field validation.
	 *
	 * @param WP_REST_Request $r REST request containing location fields.
	 * @return mixed REST response or a WP_Error.
	 * @throws Exception When the location or audit record cannot be saved atomically.
	 */
	public function create( WP_REST_Request $r ) {
		global $wpdb;
		$p    = $r->get_json_params();
		$name = sanitize_text_field( $p['location_name'] ?? '' );
		$type = in_array( $p['location_type'] ?? 'Branch', array( 'Branch', 'Sales channel' ), true ) ? $p['location_type'] : 'Branch';
		$desc = sanitize_text_field( $p['role_description'] ?? '' );
		$code = sanitize_key( $p['branch_code'] ?? '' );
		if ( '' === $name ) {
			return new WP_Error( 'invalid', 'Location name is required.', array( 'status' => 400 ) );
		}$exists = Stockland_DB::read_var( $wpdb->prepare( 'SELECT id FROM %i WHERE location_name=%s LIMIT 1', Stockland_DB::table( 'locations' ), $name ) );
		if ( $exists ) {
			return new WP_Error( 'duplicate', 'A location with this name already exists.', array( 'status' => 409 ) );
		}if ( 'Branch' === $type && '' === $code ) {
			$next = (int) Stockland_DB::read_var( $wpdb->prepare( 'SELECT COALESCE(MAX(id),0)+1 FROM %i', Stockland_DB::table( 'locations' ) ) );
			$code = 'BR' . str_pad( (string) $next, 3, '0', STR_PAD_LEFT );
		}if ( '' !== $code ) {
			$dup = Stockland_DB::read_var( $wpdb->prepare( 'SELECT id FROM %i WHERE branch_code=%s LIMIT 1', Stockland_DB::table( 'locations' ), $code ) );
			if ( $dup ) {
				return new WP_Error( 'duplicate_code', 'This branch/location code is already in use.', array( 'status' => 409 ) );
			}
		}if ( false === Stockland_DB::execute_query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'transaction_failed', 'Could not start location creation transaction.', array( 'status' => 500 ) );
		}try {
			if ( false === Stockland_DB::insert_record(
				Stockland_DB::table( 'locations' ),
				array(
					'location_name'    => $name,
					'branch_code'      => $code ? $code : null,
					'location_type'    => $type,
					'role_description' => $desc,
					'is_active'        => 1,
				)
			) ) {
				throw new Exception( 'Could not create location.' );
			}$id = (int) $wpdb->insert_id;
			if ( ! Stockland_Audit::log( get_current_user_id(), $id, 'LOCATION_CREATE', null, null, null, $name, 'Created by Owner', 'LOCATION-' . $id, get_current_user_id() ) ) {
				throw new Exception( 'Audit event could not be recorded; location was not committed.' );
			}if ( false === Stockland_DB::execute_query( 'COMMIT' ) ) {
				throw new Exception( 'Location commit failed.' );
			}return rest_ensure_response(
				array(
					'id'               => $id,
					'location_name'    => $name,
					'branch_code'      => $code,
					'location_type'    => $type,
					'role_description' => $desc,
					'is_active'        => 1,
				)
			);
		} catch ( Throwable $e ) {
			Stockland_DB::execute_query( 'ROLLBACK' );
			return new WP_Error( 'create_failed', $e->getMessage(), array( 'status' => 409 ) );}}
	/**
	 * Updates a location after Owner-only authority and field validation.
	 *
	 * @param WP_REST_Request $r REST request containing location ID and changed fields.
	 * @return mixed REST response or a WP_Error.
	 * @throws Exception When the location update or audit record cannot be saved atomically.
	 */
	public function update( WP_REST_Request $r ) {
		global $wpdb;
		$id  = (int) $r['id'];
		$row = Stockland_DB::read_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', Stockland_DB::table( 'locations' ), $id ), ARRAY_A );
		if ( ! $row ) {
			return new WP_Error( 'not_found', 'Location not found.', array( 'status' => 404 ) );
		}$p      = $r->get_json_params();
		$data    = array();
		$central = (int) Stockland_DB::get_location_id( 'Central Warehouse' );
		if ( $id === $central && isset( $p['location_name'] ) && sanitize_text_field( $p['location_name'] ) !== 'Central Warehouse' ) {
			return new WP_Error( 'central_fixed', 'Central Warehouse is a protected system location. Add/rename retail branches without changing the warehouse identity.', array( 'status' => 409 ) );
		}if ( isset( $p['location_name'] ) ) {
			$name = sanitize_text_field( $p['location_name'] );
			if ( '' === $name ) {
				return new WP_Error( 'invalid', 'Location name cannot be empty.', array( 'status' => 400 ) );
			}$dup = Stockland_DB::read_var( $wpdb->prepare( 'SELECT id FROM %i WHERE location_name=%s AND id<>%d', Stockland_DB::table( 'locations' ), $name, $id ) );
			if ( $dup ) {
				return new WP_Error( 'duplicate', 'Another location already uses that name.', array( 'status' => 409 ) );
			}$data['location_name'] = $name;
		}if ( isset( $p['branch_code'] ) ) {
			$code = sanitize_key( $p['branch_code'] );
			if ( '' === $code ) {
				return new WP_Error( 'invalid', 'Location code cannot be empty.', array( 'status' => 400 ) );
			} $dup = Stockland_DB::read_var( $wpdb->prepare( 'SELECT id FROM %i WHERE branch_code=%s AND id<>%d LIMIT 1', Stockland_DB::table( 'locations' ), $code, $id ) );
			if ( $dup ) {
				return new WP_Error( 'duplicate_code', 'Another location already uses this code.', array( 'status' => 409 ) );
			} $data['branch_code'] = $code;
		} if ( isset( $p['role_description'] ) ) {
			$data['role_description'] = sanitize_text_field( $p['role_description'] );
		}if ( isset( $p['is_active'] ) ) {
			$data['is_active'] = (int) (bool) $p['is_active'];
		}if ( isset( $p['location_type'] ) && in_array( $p['location_type'], array( 'Branch', 'Sales channel' ), true ) ) {
			$data['location_type'] = $p['location_type'];
		}
		$profile_json = null;
		$old_profile  = self::profile_for( $id );
		if ( isset( $p['profile'] ) && is_array( $p['profile'] ) ) {
			$next_profile = self::next_profile( $old_profile, $p['profile'], $p['relocation_reason'] ?? '', $p['effective_date'] ?? '' );
			if ( is_wp_error( $next_profile ) ) {
				return $next_profile;
			}
			$profile_json = wp_json_encode( $next_profile );
		}
		if ( ! $data && null === $profile_json ) {
			return rest_ensure_response( $row );
		}if ( false === Stockland_DB::execute_query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'transaction_failed', 'Could not start location update transaction.', array( 'status' => 500 ) );
		}try {
			if ( $data && false === Stockland_DB::update_record( Stockland_DB::table( 'locations' ), $data, array( 'id' => $id ) ) ) {
				throw new Exception( $wpdb->last_error ? $wpdb->last_error : 'No location change was saved.' );
			}if ( $data && ! Stockland_Audit::log( get_current_user_id(), $id, 'LOCATION_UPDATE', null, null, $row['location_name'], wp_json_encode( $data ), 'Owner changed organization structure', 'LOCATION-' . $id, get_current_user_id() ) ) {
				throw new Exception( 'Audit event could not be recorded; location was not committed.' );
			}
			if ( null !== $profile_json ) {
				$saved = Stockland_DB::execute_query( $wpdb->prepare( 'INSERT INTO %i (setting_key,setting_value,updated_by) VALUES(%s,%s,%d) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)', Stockland_DB::table( 'settings' ), self::profile_key( $id ), $profile_json, get_current_user_id() ) );
				if ( false === $saved ) {
					throw new Exception( 'Branch address could not be saved.' );
				}
				if ( ! Stockland_Audit::log( get_current_user_id(), $id, 'LOCATION_PROFILE_UPDATE', null, null, wp_json_encode( $old_profile ), $profile_json, 'Owner changed branch address/contact', 'LOCATION-PROFILE-' . $id, get_current_user_id() ) ) {
					throw new Exception( 'Audit event could not be recorded; branch address was not committed.' );
				}
			}if ( false === Stockland_DB::execute_query( 'COMMIT' ) ) {
				throw new Exception( 'Location update commit failed.' );
			}return rest_ensure_response( array_merge( $row, $data, array( 'profile' => self::profile_for( $id ) ) ) );
		} catch ( Throwable $e ) {
			Stockland_DB::execute_query( 'ROLLBACK' );
			return new WP_Error( 'update_failed', $e->getMessage(), array( 'status' => 409 ) );}}
}

// phpcs:enable WordPress.Files.FileName.InvalidClassFileName -- End the narrowly scoped legacy filename exception.
