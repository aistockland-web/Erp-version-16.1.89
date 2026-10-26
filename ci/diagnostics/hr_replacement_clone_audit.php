<?php
/**
 * Read-only HR replacement data-health audit for a restored disposable WordPress clone.
 * Run: wp eval-file ci/diagnostics/hr_replacement_clone_audit.php > hr-clone-audit.json
 * Never run against production. Output contains aggregate counts/schema metadata only, no row-level PII.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	fwrite( STDERR, "STOP: run with WP-CLI on a restored disposable clone only.\n" );
	exit( 2 );
}
global $wpdb;
if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) || ! method_exists( $wpdb, 'get_var' ) ) {
	fwrite( STDERR, "STOP: WordPress database gateway unavailable.\n" );
	exit( 2 );
}
final class Stockland_HR_Replacement_Clone_Audit {
	const BASE_SHA = '494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625';
	private static function keys() {
		return array('adjustments','audit_logs','balances','count_batches','count_lines','customer_invoices','customers','daily_closings','employee_compensation','employees','expenses','gl_accounts','gl_journals','gl_lines','import_jobs','import_profiles','import_rows','invoice_items','locations','notifications','payment_allocations','payments','payroll_lines','payroll_periods','price_history','product_code_maps','product_sku_history','products','purchase_order_items','purchase_orders','purchase_receipt_items','purchase_receipts','receipts','requisition_items','requisitions','reservations','returns','sale_batches','sales','settings','supplier_invoice_items','supplier_invoices','suppliers','transactions','transfers','user_locations','users','wholesale_sales','workflow_events');
	}
	private static function qi( $s ) {
		$t = chr( 96 );
		return $t . str_replace( $t, $t . $t, (string) $s ) . $t;
	}
	private static function count_query( $name, $sql, &$errors ) {
		global $wpdb;
		$v = $wpdb->get_var( $sql );
		if ( '' !== (string) $wpdb->last_error || null === $v || ! is_numeric( $v ) ) {
			$errors[] = $name;
			return array('status'=>'NOT_RUN','count'=>null,'reason'=>'QUERY_FAILED_OR_NO_AGGREGATE_RESULT');
		}
		return array('status'=>'OBSERVED','count'=>(int) $v);
	}
	private static function check_schema( $spec, $columns ) {
		foreach ( $spec as $table => $required ) {
			if ( ! isset( $columns[$table] ) ) { return false; }
			if ( array_diff( $required, $columns[$table] ) ) { return false; }
		}
		return true;
	}
	public static function run() {
		global $wpdb;
		$prefix = (string) $wpdb->prefix . 'stock_';
		$plen = strlen( $prefix );
		$db = isset( $wpdb->dbname ) ? (string) $wpdb->dbname : '';
		$tables = $wpdb->get_results( $wpdb->prepare(
			'SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, TABLE_ROWS, AUTO_INCREMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=%s AND LEFT(TABLE_NAME,CHAR_LENGTH(%s))=%s ORDER BY TABLE_NAME',
			$db,$prefix,$prefix
		), ARRAY_A );
		if ( '' !== (string) $wpdb->last_error || ! is_array( $tables ) ) {
			fwrite( STDERR, "STOP: table metadata could not be read.\n" ); exit( 2 );
		}
		$actual = array(); $table_meta = array();
		foreach ( $tables as $row ) {
			$name = (string) $row['TABLE_NAME']; $key = substr( $name, $plen ); $actual[] = $key;
			$table_meta[$key] = array('table_name'=>$name,'engine'=>$row['ENGINE'],'collation'=>$row['TABLE_COLLATION'],'approx_rows'=>isset($row['TABLE_ROWS'])?(int)$row['TABLE_ROWS']:null,'auto_increment'=>isset($row['AUTO_INCREMENT'])?(int)$row['AUTO_INCREMENT']:null);
		}
		sort($actual); $expected=self::keys(); sort($expected);
		$missing=array_values(array_diff($expected,$actual)); $unexpected=array_values(array_diff($actual,$expected));
		$cols=$wpdb->get_results($wpdb->prepare(
			'SELECT TABLE_NAME,COLUMN_NAME,ORDINAL_POSITION,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,CHARACTER_SET_NAME,COLLATION_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=%s AND LEFT(TABLE_NAME,CHAR_LENGTH(%s))=%s ORDER BY TABLE_NAME,ORDINAL_POSITION',
			$db,$prefix,$prefix
		),ARRAY_A);
		$idx=$wpdb->get_results($wpdb->prepare(
			'SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME,SUB_PART,INDEX_TYPE FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=%s AND LEFT(TABLE_NAME,CHAR_LENGTH(%s))=%s ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX',
			$db,$prefix,$prefix
		),ARRAY_A);
		$fks=$wpdb->get_results($wpdb->prepare(
			'SELECT TABLE_NAME,COLUMN_NAME,CONSTRAINT_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=%s AND REFERENCED_TABLE_NAME IS NOT NULL AND LEFT(TABLE_NAME,CHAR_LENGTH(%s))=%s ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION',
			$db,$prefix,$prefix
		),ARRAY_A);
		if ( '' !== (string) $wpdb->last_error || ! is_array($cols) || ! is_array($idx) || ! is_array($fks) ) {
			fwrite(STDERR,"STOP: column/index/constraint metadata could not be read.\n"); exit(2);
		}
		$column_map=array(); $col_fingerprint=array(); $idx_fingerprint=array(); $table_fingerprint=array();
		foreach($cols as $r){
			$key=substr((string)$r['TABLE_NAME'],$plen);
			if(!isset($column_map[$key]))$column_map[$key]=array();
			$column_map[$key][]=(string)$r['COLUMN_NAME'];
			$col_fingerprint[]=array('table'=>$key,'column'=>$r['COLUMN_NAME'],'pos'=>(int)$r['ORDINAL_POSITION'],'type'=>$r['COLUMN_TYPE'],'nullable'=>$r['IS_NULLABLE'],'default'=>$r['COLUMN_DEFAULT'],'extra'=>$r['EXTRA'],'charset'=>$r['CHARACTER_SET_NAME'],'collation'=>$r['COLLATION_NAME']);
		}
		foreach($idx as $r)$idx_fingerprint[]=array('table'=>substr((string)$r['TABLE_NAME'],$plen),'index'=>$r['INDEX_NAME'],'non_unique'=>(int)$r['NON_UNIQUE'],'pos'=>(int)$r['SEQ_IN_INDEX'],'column'=>$r['COLUMN_NAME'],'sub_part'=>$r['SUB_PART'],'type'=>$r['INDEX_TYPE']);
		$engines=array();
		foreach($table_meta as $key=>$t){$table_fingerprint[]=array('table'=>$key,'engine'=>$t['engine'],'collation'=>$t['collation']);$eng=strtoupper((string)$t['engine']);$engines[$eng]=($engines[$eng]??0)+1;}
		ksort($engines);
		$fingerprint=hash('sha256',(string)wp_json_encode(array('tables'=>$table_fingerprint,'columns'=>$col_fingerprint,'indexes'=>$idx_fingerprint,'foreign_keys'=>$fks),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
		$errors=array();$checks=array();$asof=function_exists('current_time')?current_time('Y-m-d'):gmdate('Y-m-d');
		$e=self::qi($prefix.'employees');$c=self::qi($prefix.'employee_compensation');$p=self::qi($prefix.'payroll_periods');$l=self::qi($prefix.'payroll_lines');$g=self::qi($prefix.'gl_journals');$gl=self::qi($prefix.'gl_lines');
		$specs=array(
			'duplicate_employee_codes'=>array(array('employees'=>array('employee_id')),"SELECT COUNT(*) FROM (SELECT employee_id FROM {$e} GROUP BY employee_id HAVING COUNT(*)>1) q"),
			'blank_employee_codes'=>array(array('employees'=>array('employee_id')),"SELECT COUNT(*) FROM {$e} WHERE employee_id IS NULL OR TRIM(employee_id)=''"),
			'duplicate_compensation_effective_dates'=>array(array('employee_compensation'=>array('employee_id','effective_from')),"SELECT COUNT(*) FROM (SELECT employee_id,effective_from FROM {$c} GROUP BY employee_id,effective_from HAVING COUNT(*)>1) q"),
			'orphan_compensation_employee_refs'=>array(array('employees'=>array('id'),'employee_compensation'=>array('employee_id')),"SELECT COUNT(*) FROM {$c} c LEFT JOIN {$e} e ON e.id=c.employee_id WHERE e.id IS NULL"),
			'negative_compensation_amounts'=>array(array('employee_compensation'=>array('base_salary','allowance_total','standard_deduction_total')),"SELECT COUNT(*) FROM {$c} WHERE base_salary<0 OR allowance_total<0 OR standard_deduction_total<0"),
			'active_employees_missing_effective_compensation'=>array(array('employees'=>array('id','status'),'employee_compensation'=>array('employee_id','effective_from')),$wpdb->prepare("SELECT COUNT(*) FROM {$e} e WHERE e.status='ACTIVE' AND NOT EXISTS(SELECT 1 FROM {$c} c WHERE c.employee_id=e.id AND c.effective_from<=%s)",$asof)),
			'compensation_missing_statutory_fields'=>array(array('employee_compensation'=>array('base_salary','taxable_allowance_total','exempt_allowance_total','pensionable_salary')),"SELECT COUNT(*) FROM {$c} WHERE base_salary IS NULL OR taxable_allowance_total IS NULL OR exempt_allowance_total IS NULL OR pensionable_salary IS NULL"),
			'orphan_payroll_line_employee_refs'=>array(array('employees'=>array('id'),'payroll_lines'=>array('employee_id')),"SELECT COUNT(*) FROM {$l} x LEFT JOIN {$e} e ON e.id=x.employee_id WHERE e.id IS NULL"),
			'orphan_payroll_line_period_refs'=>array(array('payroll_periods'=>array('id'),'payroll_lines'=>array('period_id')),"SELECT COUNT(*) FROM {$l} x LEFT JOIN {$p} p ON p.id=x.period_id WHERE p.id IS NULL"),
			'duplicate_period_employee_lines'=>array(array('payroll_lines'=>array('period_id','employee_id')),"SELECT COUNT(*) FROM (SELECT period_id,employee_id FROM {$l} GROUP BY period_id,employee_id HAVING COUNT(*)>1) q"),
			'invalid_payroll_period_ranges'=>array(array('payroll_periods'=>array('start_date','end_date')),"SELECT COUNT(*) FROM {$p} WHERE start_date IS NULL OR end_date IS NULL OR start_date>end_date"),
			'overlapping_payroll_period_pairs'=>array(array('payroll_periods'=>array('id','start_date','end_date')),"SELECT COUNT(*) FROM {$p} a INNER JOIN {$p} b ON a.id<b.id AND a.start_date<=b.end_date AND b.start_date<=a.end_date"),
			'approved_or_paid_periods_missing_journal'=>array(array('payroll_periods'=>array('status','journal_id')),"SELECT COUNT(*) FROM {$p} WHERE UPPER(status) IN('APPROVED','PAID','CLOSED') AND (journal_id IS NULL OR journal_id=0)"),
			'period_journal_refs_missing'=>array(array('payroll_periods'=>array('journal_id'),'gl_journals'=>array('id')),"SELECT COUNT(*) FROM {$p} p LEFT JOIN {$g} g ON g.id=p.journal_id WHERE p.journal_id IS NOT NULL AND p.journal_id<>0 AND g.id IS NULL"),
			'period_header_line_totals_mismatch'=>array(array('payroll_periods'=>array('id','gross_total','deduction_total','income_tax_total','employee_pension_total','employer_pension_total','other_deduction_total','net_total'),'payroll_lines'=>array('period_id','gross_pay','deduction_total','income_tax','employee_pension','employer_pension','other_deduction_total','net_pay')),"SELECT COUNT(*) FROM (SELECT p.id FROM {$p} p LEFT JOIN {$l} x ON x.period_id=p.id GROUP BY p.id,p.gross_total,p.deduction_total,p.income_tax_total,p.employee_pension_total,p.employer_pension_total,p.other_deduction_total,p.net_total HAVING ABS(COALESCE(SUM(x.gross_pay),0)-p.gross_total)>0.01 OR ABS(COALESCE(SUM(x.deduction_total),0)-p.deduction_total)>0.01 OR ABS(COALESCE(SUM(x.income_tax),0)-p.income_tax_total)>0.01 OR ABS(COALESCE(SUM(x.employee_pension),0)-p.employee_pension_total)>0.01 OR ABS(COALESCE(SUM(x.employer_pension),0)-p.employer_pension_total)>0.01 OR ABS(COALESCE(SUM(x.other_deduction_total),0)-p.other_deduction_total)>0.01 OR ABS(COALESCE(SUM(x.net_pay),0)-p.net_total)>0.01) q"),
		);
		foreach($specs as $name=>$entry){
			$ready=true;
			foreach($entry[0] as $table=>$required){
				if(!isset($column_map[$table])||array_diff($required,$column_map[$table])){$ready=false;break;}
			}
			$checks[$name]=$ready?self::count_query($name,$entry[1],$errors):array('status'=>'NOT_RUN','count'=>null,'reason'=>'REQUIRED_SCHEMA_UNAVAILABLE');
		}
		$gl_unbalanced=array('status'=>'NOT_RUN','count'=>null,'reason'=>'REQUIRED_SCHEMA_UNAVAILABLE');
		if(self::check_schema(array('payroll_periods'=>array('journal_id'),'gl_journals'=>array('id'),'gl_lines'=>array('journal_id','debit','credit')),$column_map)){
			$sql="SELECT g.id FROM {$g} g INNER JOIN {$p} p ON p.journal_id=g.id LEFT JOIN {$gl} l ON l.journal_id=g.id GROUP BY g.id HAVING ABS(COALESCE(SUM(l.debit),0)-COALESCE(SUM(l.credit),0))>0.01";
			$res=$wpdb->get_results($sql,ARRAY_A);
			if(''!== (string)$wpdb->last_error || !is_array($res)){$errors[]='referenced_payroll_journals_unbalanced';}
			else{$gl_unbalanced=array('status'=>'OBSERVED','count'=>count($res));}
		}
		$checks['referenced_payroll_journals_unbalanced']=$gl_unbalanced;
		$ext=array();foreach(array('mysqli','json','mbstring','zip','xml','dom','simplexml','fileinfo','openssl') as $name)$ext[$name]=extension_loaded($name);
		return array(
			'report_type'=>'STOCKLAND_HR_REPLACEMENT_LIVE_CLONE_AUDIT','report_version'=>'1.0','as_of_date'=>$asof,'generated_at_utc'=>gmdate('c'),
			'no_mutations_performed'=>true,'row_level_data_emitted'=>false,
			'baseline'=>array('expected_artifact_release'=>'16.1.95','expected_artifact_sha256'=>self::BASE_SHA,'installed_source_parity'=>'NOT_VERIFIED_BY_THIS_SCRIPT','plugin_version'=>defined('STOCKLAND_VERSION')?STOCKLAND_VERSION:null,'hr_schema_version'=>defined('STOCKLAND_HR_SCHEMA_VERSION')?STOCKLAND_HR_SCHEMA_VERSION:null),
			'host'=>array('wordpress_version'=>function_exists('get_bloginfo')?get_bloginfo('version'):null,'php_version'=>PHP_VERSION,'database_server_version'=>$wpdb->get_var('SELECT VERSION()'),'database_charset'=>$wpdb->get_var('SELECT @@character_set_database'),'database_collation'=>$wpdb->get_var('SELECT @@collation_database'),'php_extensions'=>$ext,'upload_max_filesize'=>ini_get('upload_max_filesize'),'post_max_size'=>ini_get('post_max_size'),'memory_limit'=>ini_get('memory_limit'),'max_execution_time_seconds'=>(int)ini_get('max_execution_time'),'default_timezone'=>date_default_timezone_get(),'wordpress_timezone'=>function_exists('wp_timezone_string')?wp_timezone_string():null),
			'schema'=>array('application_prefix'=>'(redacted)','expected_registered_table_keys'=>count($expected),'actual_matching_table_count'=>count($actual),'missing_expected_keys'=>$missing,'unexpected_keys'=>$unexpected,'table_engines'=>$engines,'table_metadata'=>array_values($table_meta),'column_count'=>count($cols),'index_column_count'=>count($idx),'foreign_key_metadata'=>array('constraint_column_rows'=>count($fks),'constraints'=>$fks),'schema_fingerprint_sha256'=>$fingerprint),
			'legacy_hr_data_health_counts'=>$checks,'query_errors'=>array_values(array_unique($errors)),
			'known_source_capability_gaps'=>array('legacy pay action does not require external payout/batch reference or per-recipient paid/failed/returned settlement evidence','legacy HR routes do not implement recruitment/contracts/onboarding/attendance/leave/promotion/exit/final settlement or secure private document downloads','legacy statutory calculator requires a qualified current legal-rule approval gate','static source declares no explicit SQL foreign-key constraints; this report records actual clone metadata'),
			'cutover_status'=>'NO_GO_UNTIL_REVIEWED'
		);
	}
}
echo wp_json_encode( Stockland_HR_Replacement_Clone_Audit::run(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
