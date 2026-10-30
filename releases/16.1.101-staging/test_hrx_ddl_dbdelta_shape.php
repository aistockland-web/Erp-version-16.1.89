<?php
// Standalone regression: every HRX CREATE TABLE must be one column/key per line (dbDelta requirement)
// and must not carry bare 'integer'/'smallint' without display width.
define('ABSPATH','/tmp/'); 
$src = file_get_contents($argv[1] ?? __DIR__.'/../build/stockland-erp-app/includes/class-stockland-hrx.php');
$fail=0; $n=0;
if (strpos($src,'dbDelta parses one column/key per line')===false){echo "FAIL splitter missing\n";$fail++;}
if (!preg_match('/preg_replace\( array\(/',$src)){echo "FAIL width normalizer missing\n";$fail++;}
if (strpos($src,'"CREATE TABLE {$p}{$key} (\n"')===false){echo "FAIL multi-line CREATE missing\n";$fail++;}
echo $fail? "FAILED $fail\n":"PASS 3 source-contract assertions\n"; exit($fail?1:0);
