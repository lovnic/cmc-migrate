<?php
/*
package: cmc_migration
file: admin/migration_table.php
since: 0.0.2 
*/

class cmc_migrate_sql{

	public static function run_dump_large_orig( $file, $logfile ){
		// your config
		$filename = 'yourGigaByteDump.sql';
		$dbHost = 'localhost';
		$dbUser = 'user';
		$dbPass = '__pass__';
		$dbName = 'dbname';
		$maxRuntime = 8; // less then your max script execution limit


		$deadline = time()+$maxRuntime; 
		$progressFilename = $filename.'_filepointer'; // tmp file for progress
		$errorFilename = $filename.'_error'; // tmp file for erro

		mysql_connect($dbHost, $dbUser, $dbPass) OR die('connecting to host: '.$dbHost.' failed: '.mysql_error());
		mysql_select_db($dbName) OR die('select db: '.$dbName.' failed: '.mysql_error());

		($fp = fopen($filename, 'r')) OR die('failed to open file:'.$filename);

		// check for previous error
		if( file_exists($errorFilename) ){
			die('<pre> previous error: '.file_get_contents($errorFilename));
		}

		// activate automatic reload in browser
		echo '<html><head> <meta http-equiv="refresh" content="'.($maxRuntime+2).'"><pre>';

		// go to previous file position
		$filePosition = 0;
		if( file_exists($progressFilename) ){
			$filePosition = file_get_contents($progressFilename);
			fseek($fp, $filePosition);
		}

		$queryCount = 0;
		$query = '';
		while( $deadline>time() AND ($line=fgets($fp, 1024000)) ){
			if(substr($line,0,2)=='--' OR trim($line)=='' ){
				continue;
			}

			$query .= $line;
			if( substr(trim($query),-1)==';' ){
				if( !mysql_query($query) ){
					$error = 'Error performing query \'<strong>' . $query . '\': ' . mysql_error();
					file_put_contents($errorFilename, $error."\n");
					exit;
				}
				$query = '';
				file_put_contents($progressFilename, ftell($fp)); // save the current file position for 
				$queryCount++;
			}
		}

		if( feof($fp) ){
			echo 'dump successfully restored!';
		}else{
			echo ftell($fp).'/'.filesize($filename).' '.(round(ftell($fp)/filesize($filename), 2)*100).'%'."\n";
			echo $queryCount.' queries processed! please reload or wait for automatic browser refresh!';
		}
	}

	/*
	* 	run sql dump file
	*
	*	@param	$file 
	*/
	public static function run_dump_large( $file, $logfile ){
		global $wpdb; $fp = fopen($file, 'r');

		$queryCount = 0; $query = '';
		while( ($line = fgets($fp, 1024000)) ){
			if(substr($line,0,2)=='--' OR trim($line)=='' ){
				continue;
			}
			$query .= $line;
			if( substr(trim($query),-1)==';' ){
				if( $wpdb->query($query) === false ){
					if( !empty($logfile) ) cmc_migrate::log_write("Sql Error: ". $wpdb->last_error."\n Query: $query", $logfile);
					exit;
				}
				$query = ''; $queryCount++;
			}
		}
	}
	/*
	* 	run sql dump file
	*
	*	@param	$file Sql file
	*	@param	$logfile  Log file
	*/
	public static function run_dump( $file, $logfile ){	
		global $wpdb; ini_set('memory_limit', '5120M'); set_time_limit ( 0 );
		
		$dump = file_get_contents($file);
		$dump = (array)explode( ";\n", $dump ); ;
		if( count( $dump ) == 1 ){
			$dump = explode(";\r", $dump[0]);
		}
		$count = count( $dump );
		
		if( !empty($logfile) ) cmc_migrate::log_write( "Restoring Sql Dump file: $logfile" , $logfile);
		if( !empty($logfile) ) cmc_migrate::log_write( "Queries detected: $count queries" , $logfile);
		
		while( count( $dump ) ){
			$query = array_shift($dump);
			$result = $wpdb->query($query);
			if( $result === false){ //last_error;
				if( !empty($logfile) ) cmc_migrate::log_write("Sql Error: ". $wpdb->last_error."\n Query: $query", $logfile);
			}	
		}
	}
		
	/**
     *  Manuall Create sql dump
	 *
	 *	@param	array	$tables	Tables to be exported
	 *	@param	string	$file	address of the file
     */
	public static function phpsql_dump ( $tables, $file ){
		global $wpdb; $dump; ini_set('memory_limit', '5120M'); set_time_limit ( 0 );
        $dump = "/*\n*\tCMC MIGRATE MYSQL DUMP FILE MADE ON : " . @date("Y-m-d H:i:s");
		$dump .= "\n*\tWordpress : " . get_bloginfo('version');
		$dump .= "\n*\tPhp  : " . phpversion();	
		$dump .= "\n*\tMysql  : " . $wpdb->db_version();
		$dump .= "\n*\tCMC Migrate  : " . CMCMG_VERSION;		
		$dump .= "\n*/\n";
		$handle = fopen($file, 'w+');
        fwrite( $handle, $dump );

        //BUILD INSERTS: 
        //Create Insert in 100 row increments to better handle memory
        foreach ($tables as $table){
			$create = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
           	$dump = "\n\n--\n-- Table structure for table $table\n--\n";
			$dump .= 'DROP TABLE IF EXISTS '.$table.';';
			$dump .= "\n" . $create[1] . ";\n\n";
			@fwrite($handle, "$dump");	
		
			//$row_count = $wpdb->get_var("SELECT Count(*) FROM `{$table}`");

			$query = "SELECT * FROM `{$table}` ";
			$rows = $wpdb->get_results($query, ARRAY_A);
			$row_count = count($rows); $row_id = 1;
			if (is_array($rows) && $row_count > 0){				
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

            $sql = null;
            $rows = null;
        }

		$dump = "/* CMC MIGRATE Timestamp: " . date("Y-m-d H:i:s") . "*/\n";
        fwrite($handle, $dump);
        $wpdb->flush();
        fclose($handle);
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
