<?php
// Physical schema + data snapshot of every Stockland application table. Output: JSON file at $STOCKLAND_SNAPSHOT_OUT.
// Used to prove add-only upgrades: columns/indexes never disappear, existing rows and values are preserved.
global $wpdb;
$out = getenv( 'STOCKLAND_SNAPSHOT_OUT' ) ?: '/tmp/stockland-snapshot.json';
$db  = $wpdb->get_var( 'SELECT DATABASE()' );
$tables = $wpdb->get_col( $wpdb->prepare( "SELECT table_name FROM information_schema.tables WHERE table_schema=%s AND table_name LIKE %s ORDER BY table_name", $db, $wpdb->esc_like( $wpdb->prefix . 'stock' ) . '%' ) );
$snap = array( 'version' => defined( 'STOCKLAND_VERSION' ) ? STOCKLAND_VERSION : '', 'server' => $wpdb->db_server_info(), 'tables' => array() );
foreach ( $tables as $t ) {
	$cols = $wpdb->get_results( $wpdb->prepare( 'SELECT column_name, column_type, is_nullable, column_default FROM information_schema.columns WHERE table_schema=%s AND table_name=%s ORDER BY ordinal_position', $db, $t ), ARRAY_A );
	$idx  = $wpdb->get_results( $wpdb->prepare( 'SELECT index_name, non_unique, GROUP_CONCAT(column_name ORDER BY seq_in_index) cols FROM information_schema.statistics WHERE table_schema=%s AND table_name=%s GROUP BY index_name, non_unique ORDER BY index_name', $db, $t ), ARRAY_A );
	$fks  = $wpdb->get_col( $wpdb->prepare( 'SELECT constraint_name FROM information_schema.table_constraints WHERE table_schema=%s AND table_name=%s AND constraint_type=%s ORDER BY 1', $db, $t, 'FOREIGN KEY' ) );
	$rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t`" );
	$names = array_column( $cols, 'column_name' );
	$pk = $wpdb->get_var( $wpdb->prepare( "SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index) FROM information_schema.statistics WHERE table_schema=%s AND table_name=%s AND index_name='PRIMARY'", $db, $t ) );
	// Per-column value digest over the columns that exist, ordered by primary key, so later added columns do not break comparison.
	$digest = array();
	if ( $rows > 0 && $rows <= 200000 && $pk ) {
		foreach ( $names as $c ) { $digest[ $c ] = md5( (string) $wpdb->get_var( "SELECT GROUP_CONCAT(COALESCE(CAST(`$c` AS CHAR),'<NULL>') ORDER BY $pk SEPARATOR '|') FROM (SELECT * FROM `$t` ORDER BY $pk LIMIT 5000) x" ) ); }
	}
	$snap['tables'][ substr( $t, strlen( $wpdb->prefix ) ) ] = array( 'rows' => $rows, 'columns' => $cols, 'indexes' => $idx, 'foreign_keys' => $fks, 'pk' => $pk, 'digest' => $digest );
}
file_put_contents( $out, wp_json_encode( $snap, JSON_PRETTY_PRINT ) );
echo 'snapshot ' . count( $snap['tables'] ) . ' tables, ' . array_sum( array_column( $snap['tables'], 'rows' ) ) . " rows -> $out\n";
