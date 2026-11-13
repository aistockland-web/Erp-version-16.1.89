<?php
// Physical schema + row fingerprints of every Stockland application table.
// Output: JSON file at $STOCKLAND_SNAPSHOT_OUT. Refuses to claim complete row
// preservation when a populated table cannot be fingerprinted in full.
global $wpdb;
$out  = getenv( 'STOCKLAND_SNAPSHOT_OUT' ) ?: '/tmp/stockland-snapshot.json';
$db   = $wpdb->get_var( 'SELECT DATABASE()' );
$tables = $wpdb->get_col( $wpdb->prepare( 'SELECT table_name FROM information_schema.tables WHERE table_schema=%s AND table_name LIKE %s ORDER BY table_name', $db, $wpdb->esc_like( $wpdb->prefix . 'stock' ) . '%' ) );
$snap = array(
	'version' => defined( 'STOCKLAND_VERSION' ) ? STOCKLAND_VERSION : '',
	'server'  => $wpdb->db_server_info(),
	'tables'  => array(),
);
$fingerprint_limit = 5000;
foreach ( $tables as $table ) {
	$columns = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT column_name, column_type, is_nullable, column_default, extra, character_set_name, collation_name FROM information_schema.columns WHERE table_schema=%s AND table_name=%s ORDER BY ordinal_position',
			$db,
			$table
		),
		ARRAY_A
	);
	$indexes = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT index_name, non_unique, index_type, GROUP_CONCAT(CONCAT(column_name, \':\', COALESCE(sub_part, \'\'), \':\', COALESCE(collation, \'\')) ORDER BY seq_in_index) cols FROM information_schema.statistics WHERE table_schema=%s AND table_name=%s GROUP BY index_name, non_unique, index_type ORDER BY index_name',
			$db,
			$table
		),
		ARRAY_A
	);
	$foreign_keys = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT constraint_name, column_name, referenced_table_name, referenced_column_name, ordinal_position FROM information_schema.key_column_usage WHERE table_schema=%s AND table_name=%s AND referenced_table_name IS NOT NULL ORDER BY constraint_name, ordinal_position',
			$db,
			$table
		),
		ARRAY_A
	);
	$row_count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM \`' . esc_sql( $table ) . '\`' );
	$pk_columns = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT column_name FROM information_schema.statistics WHERE table_schema=%s AND table_name=%s AND index_name=%s ORDER BY seq_in_index',
			$db,
			$table,
			'PRIMARY'
		)
	);
	$row_fingerprints = array();
	$fingerprints_complete = 0 === $row_count;
	if ( $row_count > 0 && $row_count <= $fingerprint_limit && $pk_columns ) {
		$order_by = implode( ', ', array_map( static function ( $column ) {
			return '\`' . str_replace( '\`', '\`\`', (string) $column ) . '\`';
		}, $pk_columns ) );
		$sample = $wpdb->get_results( 'SELECT * FROM \`' . esc_sql( $table ) . '\` ORDER BY ' . $order_by, ARRAY_A );
		if ( is_array( $sample ) && count( $sample ) === $row_count ) {
			foreach ( $sample as $row ) {
				$key_parts = array();
				foreach ( $pk_columns as $column ) {
					$key_parts[ $column ] = $row[ $column ] ?? null;
				}
				$key = hash( 'sha256', serialize( $key_parts ) );
				$row_fingerprints[ $key ] = hash( 'sha256', serialize( $row ) );
			}
			$fingerprints_complete = count( $row_fingerprints ) === $row_count;
		}
	}
	// Retain the earlier per-column diagnostic digest for readable reports.
	$digest = array();
	if ( $row_count > 0 && $row_count <= 200000 && $pk_columns ) {
		$pk_order = implode( ', ', array_map( static function ( $column ) {
			return '\`' . str_replace( '\`', '\`\`', (string) $column ) . '\`';
		}, $pk_columns ) );
		foreach ( (array) $columns as $column ) {
			$name = (string) $column['column_name'];
			$digest[ $name ] = md5( (string) $wpdb->get_var( 'SELECT GROUP_CONCAT(COALESCE(CAST(\`' . esc_sql( $name ) . '\` AS CHAR),\\'<NULL>\\') ORDER BY ' . $pk_order . ' SEPARATOR \\'|\\') FROM (SELECT * FROM \`' . esc_sql( $table ) . '\` ORDER BY ' . $pk_order . ' LIMIT 5000) x' ) );
		}
	}
	$snap['tables'][ substr( $table, strlen( $wpdb->prefix ) ) ] = array(
		'rows'                     => $row_count,
		'columns'                  => $columns,
		'indexes'                  => $indexes,
		'foreign_keys'             => $foreign_keys,
		'primary_key_columns'      => $pk_columns,
		'row_fingerprints'         => $row_fingerprints,
		'row_fingerprints_complete'=> $fingerprints_complete,
		'row_fingerprints_limit'   => $fingerprint_limit,
		'digest'                   => $digest,
	);
}
file_put_contents( $out, wp_json_encode( $snap, JSON_PRETTY_PRINT ) );
echo 'snapshot ' . count( $snap['tables'] ) . ' tables, ' . array_sum( array_column( $snap['tables'], 'rows' ) ) . " rows -> $out\n";
