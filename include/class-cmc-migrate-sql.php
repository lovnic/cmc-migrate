<?php
/*
package: cmc_migration
file: admin/migration_table.php
since: 0.0.2 
*/

class cmcmg_sql{

	/*
	* 	Run sql dump file
	*
	*	@param	$file 
	*/
	public static function run_dump_large( $file, $logfile, &$param = array() ){
		global $wpdb; $fp = fopen($file, 'r');

		$queryCount = 0; $query = ''; 
		//while( ($line = fgets($fp, 1024000)) ){
		while( ($line = fgets($fp, $param['run_dump_large_size'])) ){
			if(substr($line,0,2)=='--' OR trim($line)=='' ){
				continue;
			}
			$query .= $line;
			if( substr(trim($query),-1)==';' ){
				if( is_array($param['replace_text_now']) || !empty(is_array($param['replace_text_now'])) ){
					$query = self::replace_text( $query, $param['replace_text_now'], $param );
				}
				if( $wpdb->query($query) === false ){
					if( !empty($logfile) ) cmcmg::log_write("Sql Error: ". $wpdb->last_error."\n Query: $query", $logfile, array('echo'=>$param['echo_log']) );
				}
				$query = ''; $queryCount++;
			}
		}
		
		if( !empty($logfile) ) cmcmg::log_write( "Queries executed: $queryCount queries" , $logfile, array('echo'=>$param['echo_log']));
		fclose( $fp ); unset($param['replace_text_now']);
	}
	
	/*
	* 	Run sql dump file
	*
	*	@param	$file Sql file
	*	@param	$logfile  Log file
	*/
	public static function run_dump( $file, $logfile, &$param = array() ){	
		global $wpdb; 		
		$dump = file_get_contents($file);
		$dump = (array)explode( ";\n", $dump ); ;
		if( count( $dump ) == 1 ){
			$dump = explode(";\r", $dump[0]);
		}
		$count = count( $dump );
		
		if( !empty($logfile) ) cmcmg::log_write( "Restoring Sql Dump file: $file" , $logfile, array('echo'=>$param['echo_log']));
		if( !empty($logfile) ) cmcmg::log_write( "Queries detected: $count queries" , $logfile, array('echo'=>$param['echo_log']));
		
		while( count( $dump ) ){
			$query = array_shift($dump);
			if( is_array($param['replace_text_now']) || !empty(is_array($param['replace_text_now'])) ){
				$query = self::replace_text( $query, $param['replace_text_now'], $param );
			}			
			$result = $wpdb->query($query);
			if( $result === false){ //last_error;
				if( !empty($logfile) ) cmcmg::log_write("Sql Error: ". $wpdb->last_error."\n Query: $query", $logfile, array('echo'=>$param['echo_log']) );
			}	
		}
		unset($param['replace_text_now']);
	}
		
	/*
	* 	Replace text in dabase queries
	*
	*	@param	string	$query Sql query
	*	@param	array	$replaces  array of keys old text and values for new text
	*/
	public static function replace_text( $query, $replaces, &$param = array() ){	
		foreach($replaces as $repk => $repv){
			if( strpos($query, $repk) !== false ){
				$query = str_replace( $repk, $repv, $query );	
			}			
		}
		return $query;
	}
		
	/**
     *  Manuall Create sql dump
	 *
	 *	@param	array	$tables	Tables to be exported
	 *	@param	string	$file	address of the file
     */
	public static function phpsql_dump ( $tables, $file, &$param = array() ){
		global $wpdb; $dump; 
        $dump = "/*\n*\tCMC MIGRATE MYSQL DUMP FILE MADE ON : " . @date("Y-m-d H:i:s");
		$dump .= "\n*\tWordpress : " . get_bloginfo('version');
		$dump .= "\n*\tPhp  : " . phpversion();	
		$dump .= "\n*\tMysql  : " . $wpdb->db_version();
		$dump .= "\n*\tCMC Migrate  : " . CMCMG_VERSION;
		$dump .= "\n*\tDatabase  : " . DB_NAME;		
		$dump .= "\n*/\n";
		$handle = fopen($file, 'w+');
        fwrite( $handle, $dump );

        //Create Insert in 100 row increments to better handle memory
        foreach ( $tables as $table ){
			$create = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
           	$dump = "\n\n--\n-- Table structure for table $table\n--\n";
			$dump .= 'DROP TABLE IF EXISTS '.$table.';';
			$dump .= "\n" . $create[1] . ";\n\n";
			@fwrite($handle, "$dump");				
			$row_count = $wpdb->get_var("SELECT Count(*) FROM `{$table}`");	
			if( $row_count > 0 ){
				$row_count = $row_count < $param['dump_limit']? 1 : ceil( $row_count / $param['dump_limit'] );
			
				for( $i = 0; $i < $row_count; $i++ ){	
					$param['dump_limit_start'] = $i * $param['dump_limit'];
					self::phpsql_write_table( $table, $handle, $param );
				}	
			}
        }

		$dump = "/* CMC MIGRATE Timestamp: " . date("Y-m-d H:i:s") . "*/\n";
        fwrite($handle, $dump);
        $wpdb->flush();
        fclose($handle);
	}
	
	/**
     *  Manuall Create sql dump
	 *
	 *	@param	array	$tables	Tables to be exported
	 *	@param	string	$file	address of the file
     */
	private static function phpsql_write_table( $table, $handle, &$param ){
		global $wpdb; //$query = "SELECT * FROM `{$table}` ";		
		$query = "SELECT * FROM `{$table}` LIMIT $param[dump_limit_start], $param[dump_limit]";
		$rows = $wpdb->get_results($query, ARRAY_A);
		$row_count = count($rows); $row_id = 1;
		if(is_array($rows) && $row_count > 0){				
			$sql = "INSERT INTO `{$table}` VALUES "; 
			foreach ($rows as $row){	
				$sql .= "\n(";
				$num_values = count($row); $num_counter = 1;
				foreach ($row as $value){
					if (is_null($value) || !isset($value)){
						($num_values == $num_counter) ? $sql .= 'NULL' : $sql .= 'NULL, ';
					}else{
						($num_values == $num_counter) ? $sql .= '"' . @esc_sql($value) . '"' : $sql .= '"' . @esc_sql($value) . '", ';
					}
					$num_counter++;
				}
				$sql .= ")";
				$sql .= ( $row_id  == $row_count )? ";\n":","; $row_id++;
			}
			fwrite($handle, $sql);
		}
		$sql = null;   $rows = null;
	}
	
	/**
     *  Execute mysql command
	 *
	 *	@param	string	$cmd	command
     */
	public static function mysql_cmd ( $cmd ){
		global $wpdb;
		$sql = "SHOW VARIABLES LIKE 'basedir'"; $results = $wpdb->get_results($sql, ARRAY_A);
		$mysql = $results[0]['Value']; $mysqlcomd = $mysql.$cmd;
		$output = array(); $status = '';
		//echo $mysqlcomd;
		$output = passthru( $mysqlcomd, $status );
		//$last_line = exec( $mysqlcomd, $output, $status );
		$response = array('output'=>$output, 'status' => $status, 'last_line'=>$last_line);
		return $response;
	}
			
}
