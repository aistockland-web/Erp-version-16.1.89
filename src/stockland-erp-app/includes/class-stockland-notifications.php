<?php
/**
 * Persists role-scoped operational notifications for Stockland ERP.
 *
 * @package Stockland_ERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notification persistence and role/location delivery helpers.
 *
 * @package Stockland_ERP
 */
class Stockland_Notifications {
	/**
	 * Creates a direct notification for one WordPress user.
	 *
	 * @param int         $user_id Target user identifier.
	 * @param string      $type Notification type key.
	 * @param string      $title Notification title.
	 * @param string      $message Notification body.
	 * @param string|null $entity_type Optional related entity type.
	 * @param mixed       $entity_id Optional related entity identifier.
	 * @param string      $severity Notification severity.
	 * @return bool Whether the notification row was inserted.
	 */
	public static function push_user( $user_id, $type, $title, $message, $entity_type = null, $entity_id = null, $severity = 'info' ) {
		global $wpdb;
		if ( ! $user_id ) {
			return false;
		}
		return false !== Stockland_DB::insert_record(
			Stockland_DB::table( 'notifications' ),
			array(
				'user_id'           => (int) $user_id,
				'audience_role'     => null,
				'location_id'       => null,
				'notification_type' => sanitize_key( $type ),
				'title'             => sanitize_text_field( $title ),
				'message'           => wp_kses_post( $message ),
				'entity_type'       => $entity_type ? sanitize_key( $entity_type ) : null,
				'entity_id'         => null !== $entity_id ? sanitize_text_field( (string) $entity_id ) : null,
				'severity'          => sanitize_key( $severity ),
				'is_read'           => 0,
				'created_at'        => current_time( 'mysql' ),
			)
		);
	}
	/**
	 * Creates a notification for a role, optionally scoped to a location.
	 *
	 * @param string      $role Target ERP role.
	 * @param int|null    $location_id Optional target location identifier.
	 * @param string      $type Notification type key.
	 * @param string      $title Notification title.
	 * @param string      $message Notification body.
	 * @param string|null $entity_type Optional related entity type.
	 * @param mixed       $entity_id Optional related entity identifier.
	 * @param string      $severity Notification severity.
	 * @return bool Whether the notification row was inserted.
	 */
	public static function push_role( $role, $location_id, $type, $title, $message, $entity_type = null, $entity_id = null, $severity = 'info' ) {
		global $wpdb;
		return false !== Stockland_DB::insert_record(
			Stockland_DB::table( 'notifications' ),
			array(
				'user_id'           => null,
				'audience_role'     => sanitize_key( $role ),
				'location_id'       => $location_id ? (int) $location_id : null,
				'notification_type' => sanitize_key( $type ),
				'title'             => sanitize_text_field( $title ),
				'message'           => wp_kses_post( $message ),
				'entity_type'       => $entity_type ? sanitize_key( $entity_type ) : null,
				'entity_id'         => null !== $entity_id ? sanitize_text_field( (string) $entity_id ) : null,
				'severity'          => sanitize_key( $severity ),
				'is_read'           => 0,
				'created_at'        => current_time( 'mysql' ),
			)
		);
	}
	/**
	 * Lists unread notifications visible to a user's role and assigned locations.
	 *
	 * @param int|null $user_id User identifier; defaults to the current user.
	 * @param int      $limit Maximum number of rows to return, capped at 100.
	 * @return array Unread notification rows visible to the user.
	 */
	public static function unread( $user_id = null, $limit = 50 ) {
		global $wpdb;
		$user_id = $user_id ? $user_id : get_current_user_id();
		$limit   = max( 1, min( 100, (int) $limit ) );
		$profile = Stockland_Authority::profile( $user_id );
		if ( ! $profile ) {
			return array();
		}
		$role           = sanitize_key( $profile['role'] );
		$loc            = (int) ( $profile['branch_location_id'] ?? 0 );
		$assigned_table = $wpdb->prefix . 'stock_user_locations';
		$sql            = 'SELECT n.*,l.location_name FROM %i n LEFT JOIN %i l ON l.id=n.location_id WHERE n.is_read=0 AND (n.user_id=%d OR (n.audience_role=%s AND (n.location_id IS NULL OR n.location_id=%d OR EXISTS (SELECT 1 FROM %i sul WHERE sul.wp_user_id=%d AND sul.location_id=n.location_id AND sul.is_active=1 AND (sul.effective_from IS NULL OR sul.effective_from<=CURDATE()) AND (sul.effective_to IS NULL OR sul.effective_to>=CURDATE()))))) ORDER BY n.id DESC LIMIT %d';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is a fixed query template with placeholders for both tables, the capped LIMIT and every user/role/location value; execution always passes through prepare().
		return Stockland_DB::read_results( $wpdb->prepare( $sql, Stockland_DB::table( 'notifications' ), Stockland_DB::table( 'locations' ), (int) $user_id, $role, $loc, $assigned_table, (int) $user_id, $limit ), ARRAY_A );
	}
	/**
	 * Marks a notification read only when it is visible to the user.
	 *
	 * @param int      $id Notification identifier.
	 * @param int|null $user_id User identifier; defaults to the current user.
	 * @return bool Whether the scoped update succeeded.
	 */
	public static function mark_read( $id, $user_id = null ) {
		global $wpdb;
		$user_id = $user_id ? $user_id : get_current_user_id();
		$profile = Stockland_Authority::profile( $user_id );
		if ( ! $profile ) {
			return false;
		}
		$role           = sanitize_key( $profile['role'] );
		$loc            = (int) ( $profile['branch_location_id'] ?? 0 );
		$assigned_table = $wpdb->prefix . 'stock_user_locations';
		$sql            = 'UPDATE %i AS n SET is_read=1,read_at=%s WHERE n.id=%d AND (n.user_id=%d OR (n.audience_role=%s AND (n.location_id IS NULL OR n.location_id=%d OR EXISTS (SELECT 1 FROM %i sul WHERE sul.wp_user_id=%d AND sul.location_id=n.location_id AND sul.is_active=1 AND (sul.effective_from IS NULL OR sul.effective_from<=CURDATE()) AND (sul.effective_to IS NULL OR sul.effective_to>=CURDATE())))))';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is a fixed UPDATE template; table identifiers, timestamp, notification ID, user, role, location and assignment table are all bound through prepare().
		$updated = Stockland_DB::execute_query( $wpdb->prepare( $sql, Stockland_DB::table( 'notifications' ), current_time( 'mysql' ), (int) $id, (int) $user_id, $role, $loc, $assigned_table, (int) $user_id ) );
		// wpdb::query() returns 0 when the ID exists but is outside the user's role/location
		// scope. Treat that as denied; false-only checking would disclose success for no-op updates.
		return is_int( $updated ) && $updated > 0;
	}
	/**
	 * Notifies Management and Owner that a requisition awaits review.
	 *
	 * @param mixed  $requisition_id Requisition identifier.
	 * @param int    $branch_location_id Requesting branch identifier.
	 * @param string $title Notification title.
	 * @param string $message Notification body.
	 * @return bool Whether notification dispatch was requested.
	 */
	public static function notify_approvers( $requisition_id, $branch_location_id, $title, $message ) {
		self::push_role( 'management', null, 'requisition', $title, $message, 'requisition', $requisition_id, 'action' );
		self::push_role( 'owner', null, 'requisition', $title, $message, 'requisition', $requisition_id, 'action' );
		return true;
	}
	/**
	 * Notifies warehouse staff about a requisition workflow action.
	 *
	 * @param int    $location_id Warehouse location identifier.
	 * @param mixed  $requisition_id Requisition identifier.
	 * @param string $title Notification title.
	 * @param string $message Notification body.
	 * @return bool Whether notification dispatch was requested.
	 */
	public static function notify_warehouse( $location_id, $requisition_id, $title, $message ) {
		self::push_role( 'warehouse_employee', $location_id, 'warehouse_requisition', $title, $message, 'requisition', $requisition_id, 'action' );
		self::push_role( 'warehouse_manager', $location_id, 'warehouse_requisition', $title, $message, 'requisition', $requisition_id, 'action' );
		return true;
	}
	/**
	 * Notifies branch staff about a requisition workflow action.
	 *
	 * @param int    $location_id Branch location identifier.
	 * @param mixed  $requisition_id Requisition identifier.
	 * @param string $title Notification title.
	 * @param string $message Notification body.
	 * @param string $severity Notification severity.
	 * @return bool Whether notification dispatch was requested.
	 */
	public static function notify_branch( $location_id, $requisition_id, $title, $message, $severity = 'action' ) {
		self::push_role( 'branch_manager', $location_id, 'requisition', $title, $message, 'requisition', $requisition_id, $severity );
		self::push_role( 'sales_employee', $location_id, 'requisition', $title, $message, 'requisition', $requisition_id, $severity );
		return true;
	}
	/**
	 * Notifies the right people about a stock transfer (stock moving between locations).
	 *
	 * Called after the transfer transaction has committed, so a notification problem can never undo stock movement.
	 *
	 * @param string $event Event: REQUESTED, APPROVED, REJECTED, RESERVED, PICKED, IN_TRANSIT, RECEIVED, COMPLETED, CANCELLED.
	 * @param array  $t     Transfer row (needs transfer_id, sku, quantity, from/to location id and name, requested_by).
	 * @return bool Whether notifications were requested.
	 */
	public static function notify_transfer( $event, array $t ) {
		$id    = (string) ( $t['transfer_id'] ?? '' );
		$from  = (int) ( $t['from_location_id'] ?? 0 );
		$to    = (int) ( $t['to_location_id'] ?? 0 );
		$what  = 'Transfer ' . $id . ' (' . (string) ( $t['product_name'] ?? $t['sku'] ?? '' ) . ' x ' . (string) ( $t['quantity'] ?? '' ) . ') from ' . (string) ( $t['from_location_name'] ?? '' ) . ' to ' . (string) ( $t['to_location_name'] ?? '' );
		$sends = array();
		switch ( strtoupper( (string) $event ) ) {
			case 'REQUESTED':
				$sends[] = array( 'warehouse_manager', $from, 'Transfer requested', $what . ' is waiting for approval.', 'action' );
				$sends[] = array( 'management', null, 'Transfer requested', $what . ' was requested.', 'info' );
				break;
			case 'APPROVED':
				$sends[] = array( 'warehouse_manager', $from, 'Transfer approved: reserve and pick', $what . ' is approved. Reserve and pick the stock.', 'action' );
				$sends[] = array( 'branch_manager', $to, 'Transfer approved', $what . ' is approved and will be sent to you.', 'info' );
				break;
			case 'RESERVED':
			case 'PICKED':
				$sends[] = array( 'warehouse_manager', $from, 'Transfer ' . strtolower( $event ), $what . ' is ' . strtolower( $event ) . '.', 'info' );
				break;
			case 'IN_TRANSIT':
				$sends[] = array( 'branch_manager', $to, 'Stock on the way: receive it', $what . ' has been dispatched. Receive and count it on arrival.', 'action' );
				$sends[] = array( 'sales_employee', $to, 'Stock on the way', $what . ' has been dispatched to your location.', 'info' );
				$sends[] = array( 'management', null, 'Transfer dispatched', $what . ' is in transit.', 'info' );
				break;
			case 'RECEIVED':
				$sends[] = array( 'warehouse_manager', $from, 'Transfer received at destination', $what . ' was received at the destination and recorded. Review the receipt and any variance.', 'info' );
				$sends[] = array( 'branch_manager', $to, 'Transfer received', $what . ' was received and recorded at your location.', 'info' );
				$sends[] = array( 'sales_employee', $to, 'New stock received', $what . ' was received at your location and recorded in inventory.', 'info' );
				$sends[] = array( 'management', null, 'Transfer received', $what . ' was received and recorded.', 'info' );
				$sends[] = array( 'owner', null, 'Transfer received', $what . ' was received and recorded.', 'info' );
				break;
			case 'COMPLETED':
				$sends[] = array( 'warehouse_manager', $from, 'Transfer completed', $what . ' has completed at the destination. Review the source and destination movement records.', 'info' );
				$sends[] = array( 'branch_manager', $to, 'Transfer completed', $what . ' is complete and recorded at your location.', 'info' );
				$sends[] = array( 'sales_employee', $to, 'Transfer completed', $what . ' is complete in inventory.', 'info' );
				$sends[] = array( 'management', null, 'Transfer completed', $what . ' is complete.', 'info' );
				$sends[] = array( 'owner', null, 'Transfer completed', $what . ' is complete.', 'info' );
				break;
			case 'REJECTED':
			case 'CANCELLED':
				$sends[] = array( 'branch_manager', $to, 'Transfer ' . strtolower( $event ), $what . ' was ' . strtolower( $event ) . '. No stock will arrive.', 'warning' );
				$sends[] = array( 'warehouse_manager', $from, 'Transfer ' . strtolower( $event ), $what . ' was ' . strtolower( $event ) . '. Release any reserved stock.', 'warning' );
				$sends[] = array( 'management', null, 'Transfer ' . strtolower( $event ), $what . ' was ' . strtolower( $event ) . '.', 'warning' );
				break;
			default:
				return false;
		}
		$ok = true;
		foreach ( $sends as $n ) {
			$ok = self::push_role( $n[0], $n[1], 'transfer_' . strtolower( $event ), $n[2], $n[3], 'transfer', $id, $n[4] ) && $ok;
		}
		if ( ! empty( $t['requested_by'] ) && in_array( strtoupper( (string) $event ), array( 'REQUESTED', 'APPROVED', 'RESERVED', 'PICKED', 'IN_TRANSIT', 'RECEIVED', 'COMPLETED', 'REJECTED', 'CANCELLED' ), true ) ) {
			$ok = self::push_user( (int) $t['requested_by'], 'transfer_' . strtolower( $event ), 'Your transfer ' . $id . ': ' . strtolower( str_replace( '_', ' ', $event ) ), $what . '.', 'transfer', $id, in_array( strtoupper( $event ), array( 'REJECTED', 'CANCELLED' ), true ) ? 'warning' : 'info' ) && $ok;
		}
		return $ok;
	}
	/**
	 * Sends a high-priority exception notice to Management and Owner.
	 *
	 * @param string      $title Notification title.
	 * @param string      $message Notification body.
	 * @param string|null $entity_type Optional related entity type.
	 * @param mixed       $entity_id Optional related entity identifier.
	 * @return bool Whether notification dispatch was requested.
	 */
	public static function notify_exception_owners( $title, $message, $entity_type = null, $entity_id = null ) {
		$management_ok = self::push_role( 'management', null, 'exception', $title, $message, $entity_type, $entity_id, 'danger' );
		$owner_ok      = self::push_role( 'owner', null, 'exception', $title, $message, $entity_type, $entity_id, 'danger' );
		return $management_ok && $owner_ok;
	}
	/**
	 * Notifies Management and Owner after a committed Product Master change.
	 *
	 * Notification failures must never roll back the already committed product.
	 *
	 * @param string $action Product Master action code.
	 * @param array  $product Saved Product Master record and optional counts.
	 * @return bool Whether all applicable notifications were recorded.
	 */
	public static function notify_product_master( $action, $product ) {
		$sku  = sanitize_text_field( (string) ( $product['sku'] ?? '' ) );
		$name = sanitize_text_field( (string) ( $product['product_name'] ?? $sku ) );
		if ( '' === $sku ) {
			return false;
		}
		$count = isset( $product['created_count'] ) ? (int) $product['created_count'] : 0;
		if ( 'VARIANT_MATRIX' === $action ) {
			$title   = 'Product variants created';
			$message = $count . ' new variant(s) were created under ' . $sku . ' — ' . ( $name ? $name : $sku ) . '. Review Product Master when needed.';
		} else {
			$verb    = 'UPDATED' === $action ? 'updated' : 'created';
			$title   = 'Product Master ' . $verb;
			$message = $name . ' (' . $sku . ') was ' . $verb . ' in Product Master. No inventory quantity was changed.';
		}
		$ok1 = self::push_role( 'management', null, 'product_master', $title, $message, 'product', $sku, 'info' );
		$ok2 = self::push_role( 'owner', null, 'product_master', $title, $message, 'product', $sku, 'info' );
		// The user who performed the action also gets a direct confirmation when their
		// current ERP role is not already covered by the management/owner audiences.
		$actor_id   = (int) get_current_user_id();
		$actor_role = '';
		try {
			$actor_profile = Stockland_Authority::profile( $actor_id );
			$actor_role    = sanitize_key( (string) ( $actor_profile['role'] ?? '' ) ); } catch ( Throwable $ignore ) {
				// A missing profile must not block confirmation of the committed Product Master action.
				$actor_role = '';
			}
			$ok3 = ( $actor_id > 0 && ! in_array( $actor_role, array( 'management', 'owner' ), true ) )
			? self::push_user( $actor_id, 'product_master', $title, $message, 'product', $sku, 'info' )
			: true;
			return $ok1 && $ok2 && $ok3;
	}
}
