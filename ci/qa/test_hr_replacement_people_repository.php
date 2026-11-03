<?php
/**
 * Repository readiness tests for transaction-backed HRX schema dependencies.
 * Fake wpdb only; no WordPress or MariaDB connection is made.
 */
define( 'STOCKLAND_HR_REPLACEMENT_TESTING', true );
if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }
require_once __DIR__ . '/../hr_payroll_replacement_runtime/Stockland_HR_Replacement_People_Repository.php';

final class HRXP_Readiness_Fake_WPDB {
	public $prefix = 'wp_';
	public $last_error = '';
	public $insert_id = 0;
	public $engine = 'InnoDB';
	public $missing_table = '';
	public function prepare( $query, ...$args ) {
		$i = 0;
		return preg_replace_callback( '/%[si]/', function( $m ) use ( &$i, $args ) {
			if ( ! array_key_exists( $i, $args ) ) { throw new RuntimeException( 'Fake prepare placeholder mismatch.' ); }
			$value = (string) $args[ $i++ ];
			return "'" . str_replace( "'", "''", $value ) . "'";
		}, $query );
	}
	public function esc_like( $value ) { return (string) $value; }
	public function get_var( $sql ) {
		$this->last_error = '';
		if ( preg_match( "/SHOW TABLES LIKE '([^']+)'/", $sql, $m ) ) {
			if ( $this->missing_table === $m[1] ) { return null; }
			return $m[1];
		}
		$this->last_error = 'Unexpected query in readiness test.';
		return null;
	}
	public function get_row( $sql, $output = null ) {
		$this->last_error = '';
		if ( preg_match( "/SHOW TABLE STATUS WHERE Name = '([^']+)'/", $sql, $m ) ) {
			if ( $this->missing_table === $m[1] ) { return null; }
			return array( 'Name' => $m[1], 'Engine' => $this->engine );
		}
		$this->last_error = 'Unexpected row query in readiness test.';
		return null;
	}
}

function hrxp_repo_assert( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
}

$db = new HRXP_Readiness_Fake_WPDB();
$repo = new Stockland_HR_Replacement_People_WPDB_Repository( $db );
hrxp_repo_assert( true === $repo->tables_ready(), 'all expected HRX tables on InnoDB report ready' );
$db->engine = 'MyISAM';
hrxp_repo_assert( false === $repo->tables_ready(), 'non-transactional MyISAM tables fail closed' );
$db->engine = 'InnoDB';
$db->missing_table = 'wp_stockland_hrx_contract_versions';
hrxp_repo_assert( false === $repo->tables_ready(), 'a missing required HRX table fails closed' );

echo "PASS HRX repository readiness: InnoDB engine and full table presence are required. Fake wpdb only; no database mutated.\n";
