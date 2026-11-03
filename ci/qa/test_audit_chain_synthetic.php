<?php
/**
 * Synthetic-only audit-chain regression. Never reads or connects to a live database.
 * Usage: php test_audit_chain_synthetic.php <extracted-stockland-erp-app-root>
 */
define('ABSPATH', '/tmp/');
define('ARRAY_A', 'ARRAY_A');
$GLOBALS['audit_test_rows'] = array();
function wp_json_encode($data) { return json_encode($data); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
class FakeWpdb {
    public $prefix = 'wp_';
    public function prepare($query, ...$args) { return $query; }
}
$wpdb = new FakeWpdb();
class Stockland_DB {
    public static function table($name) { return 'wp_stock_' . $name; }
    public static function read_results($query, $format) { return $GLOBALS['audit_test_rows']; }
}
$root = isset($argv[1]) ? rtrim((string) $argv[1], '/\\') : '';
if ('' === $root || ! is_file($root . '/includes/class-stockland-audit.php')) {
    fwrite(STDERR, "Usage: php test_audit_chain_synthetic.php <extracted-stockland-erp-app-root>\n");
    exit(64);
}
require $root . '/includes/class-stockland-audit.php';

$payloadMethod = new ReflectionMethod('Stockland_Audit', 'payload');
$payloadMethod->setAccessible(true);
$rows = array();
$previous = '';
$actions = array('POS_SALE', 'PO_CREATED', 'PO_APPROVED');
for ($id = 1; $id <= 3; $id++) {
    $row = array(
        'id' => $id,
        'user_id' => 7,
        'branch_location_id' => (1 === $id ? null : 2),
        'action_time' => sprintf('2026-10-29 12:00:%02d', $id),
        'action' => $actions[$id - 1],
        'sku' => (1 === $id ? 'STK-001' : null),
        'quantity' => (1 === $id ? '2.00' : null),
        'old_value' => null,
        'new_value' => (1 === $id ? 'sale-100' : null),
        'reason' => (2 === $id ? 'supplier order approved' : null),
        'reference' => (1 === $id ? 'SALE-100' : 'PO-100'),
        'approver_id' => (3 === $id ? 9 : null),
        'previous_hash' => $previous,
        'hash_version' => 2,
    );
    $payload = $payloadMethod->invoke(
        null,
        $row['user_id'], $row['branch_location_id'], $row['action'], $row['sku'],
        $row['quantity'], $row['old_value'], $row['new_value'], $row['reason'],
        $row['reference'], $row['approver_id'], $row['action_time'], $previous, 2
    );
    $row['event_hash'] = hash('sha256', '2|' . $previous . '|' . $payload);
    $previous = $row['event_hash'];
    $rows[] = $row;
}
$GLOBALS['audit_test_rows'] = $rows;
$valid = Stockland_Audit::verify_chain();
if (empty($valid['ok']) || 3 !== $valid['checked']) {
    fwrite(STDERR, 'valid synthetic chain failed: ' . json_encode($valid) . "\n");
    exit(1);
}
$tampered = $rows;
$tampered[1]['new_value'] = 'tampered';
$GLOBALS['audit_test_rows'] = $tampered;
$tamperResult = Stockland_Audit::verify_chain();
if (!empty($tamperResult['ok']) || false === strpos($tamperResult['reason'] ?? '', 'hash mismatch')) {
    fwrite(STDERR, 'tampered event was not rejected: ' . json_encode($tamperResult) . "\n");
    exit(2);
}
$broken = $rows;
$broken[2]['previous_hash'] = 'deadbeef';
$GLOBALS['audit_test_rows'] = $broken;
$continuity = Stockland_Audit::verify_chain();
if (!empty($continuity['ok']) || false === strpos($continuity['reason'] ?? '', 'continuity failure')) {
    fwrite(STDERR, 'broken continuity was not rejected: ' . json_encode($continuity) . "\n");
    exit(3);
}
echo json_encode(array(
    'valid_chain' => $valid,
    'tampered_event' => array('rejected' => true, 'reason' => $tamperResult['reason']),
    'broken_link' => array('rejected' => true, 'reason' => $continuity['reason']),
), JSON_PRETTY_PRINT) . "\n";
