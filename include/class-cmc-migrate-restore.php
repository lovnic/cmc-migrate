<?php
/*
package: cmc_migrate
since: 0.0.3
*/

class cmcmg_restore{
	/**
     *  Internally Restore an exported wordpress installation
     */
	public static function restore( $file, $param ){	
		$base = dirname($file); if( $base != '.' ) return false; $response = array();
		$zipfile = CMCMG_EXPORT_DIR. cmcmg::get_current_blog_id().DIRECTORY_SEPARATOR.$file; if( !file_exists( $zipfile ) ) return false;
		$zip = new ZipArchive; if( !$zip->open($zipfile) ) return false;
		$meta = @file_get_contents('zip://'.$zipfile.'#meta.json'); $meta = json_decode($meta, true);
		
		if( !is_array($meta) ){
			$response['success'] = false; 
			$response['message'] = "Could not read <b>meta.json</b> in the restore file: $file";
		}else if( is_multisite() && !$meta['is_network'] ){
			$response['success'] = false;  
			$response['error'] = 'feature_not_supported';
			$response['error_code'] = 'multisite_single_site_restore';
			$response['message'] = 'Restore to multisite single site is not support in this version';
		}
		
		$param = array_merge( (array)$param, array( 'zip'=>&$zip, 'file'=>&$file, 'meta'=>&$meta ));
		$response['param'] = &$param;
		$response = apply_filters('cmcmg_restore', $response);
		
		if( $response['success'] === false){
			return $response;
		}		
		
		$response = self::_restore_site( $param );
		return $response;		
	}
	
	/**
     *  Internally Restore an exported wordpress installation 
	 *
	 *	@param	array	varriable needed for restore
     */
	private static function _restore_site( $param ){
		$response = array(); $base_name = basename($param['file'], '.zip');	
		$temp_dir = CMCMG_IMPORT_DIR . cmcmg::get_current_blog_id().DIRECTORY_SEPARATOR; //.date('Y-m-d_H-i-s');
		$log_dir = CMCMG_LOG_DIR . cmcmg::get_current_blog_id().DIRECTORY_SEPARATOR;
		$log_file = $log_dir . "restore_log_".$base_name.".txt";		
		$upload_dir = wp_upload_dir(); $uploads = $upload_dir['basedir']; $upload_temp = $temp_dir.'wp-content/uploads';
		$dump_file = $temp_dir. 'dump.sql'; $users_file = $temp_dir. 'users.sql';
		
		$zip = &$param['zip']; $file = &$param['file']; $meta = &$param['meta'];
		$param = array_merge( $param, array('temp_dir'=>&$temp_dir, 'log_file'=> &$log_file,
		'uploads'=> &$uploads, 'uploads_temp'=> &$upload_temp, 'dump_file'=>&$dump_file, 'users_file'=>&$users_file ) );		
		$param['delete_files'] = !is_multisite();
		$param['delete_files_ignore_wpcontent'] = array('cmc-migrate', 'plugins', 'themes', 'index.php', 'uploads');
		$param['delete_files_ignore_plugins'] = array('cmc-migrate', 'index.php');
		$param['delete_files_ignore_themes'] = array();
		
		$param['dump_limit'] = 200; $param['php_time_limit'] = 0;
		$param['run_dump_large_size'] = 1024000; $param['run_dump_large'] = true;
		$param['php_memory_limit'] = '5120M';
		$param['mysql_dump_type'] = 'php';
		
		$param = apply_filters('cmcmg_restore_param', $param);
		if( $param === false ) return;
		$response['param'] = &$param;
		
		set_time_limit ( $param['php_time_limit'] );
		ini_set( 'memory_limit', $param['php_memory_limit'] );
		
		if( !file_exists( CMCMG_IMPORT_DIR ) )  mkdir( CMCMG_IMPORT_DIR, 0777, true );
		if( !file_exists( CMCMG_IMPORT_DIR.'index.php' ) ) file_put_contents( CMCMG_IMPORT_DIR.'index.php', '//Silence is golden' );

		if( !file_exists( $temp_dir ) )  mkdir( $temp_dir, 0777, true );
		if( !file_exists( $temp_dir.'index.php' ) ) file_put_contents( $temp_dir.'index.php', '//Silence is golden');
		
		
		//--------- Log file ------------------------------		
		if( !file_exists( $log_dir ) )  mkdir( $log_dir, 0777, true );
		if( !file_exists( $log_dir.'index.php' ) ) file_put_contents( $log_dir.'index.php', '//Silence is golden');
		if( !file_exists( CMCMG_LOG_DIR.'index.php' ) ) file_put_contents( CMCMG_LOG_DIR.'index.php', '//Silence is golden');
			
		file_put_contents( $log_file, '');
		cmcmg::log_write( "CMC MIGRATE RESTORE LOG : ".date('Y-m-d_H-i-s'), $log_file );
		cmcmg::log_write( "Restoring from migration : '{$base_name}'", $log_file, array('echo'=>$param['echo_log']) );
		cmcmg::log_write( "Extracting Files from {$base_name}.zip", $log_file, array('echo'=>$param['echo_log']) );
		$zip->extractTo($temp_dir);  $zip->close();
	
		self::replace_text( $param );		
		self::restore_databse( $param );		
		if( $param['delete_files'] ){
			self::delete_files( $param );
		}
		self::copy_files( $param );
		
		$temp_dir = rtrim($temp_dir, '/\\');
		cmcmg::log_write( "Deleting temp directory : ". $temp_dir, $log_file, array('echo'=>$param['echo_log']) );
		cmcmg::recursiveDelete($temp_dir);		
		
		cmcmg::log_write( "Appling custom functions ", $log_file, array('echo'=>$param['echo_log']) );
		$response['success'] = true; $response['message'] = "Restore successful";
		$response = apply_filters( 'cmcmg_restored', $response, $param );
		
		cmcmg::log_write( "Finished restore ", $log_file, array('echo'=>$param['echo_log']) );
		
		return $response;
	}
	
	/**
     *  Replact text in the database
	 *
	 *	@param	array	varriable needed for restore
     */
	private static function replace_text( &$param ){
		global $wpdb; $meta = &$param['meta']; 		
		$prefix = $wpdb->prefix; $site_url = get_bloginfo('siteurl'); $home_url = get_bloginfo('home');
		$users_tbl = $wpdb->users; $usermeta_tbl = $wpdb->usermeta; $site_url_old = $meta['siteurl'];
		$site_url  = preg_replace( '/^https?:\/\//', '', $site_url);
		$site_url_old = preg_replace( '/^https?:\/\//', '', $site_url_old); 
		$textreplace = array(); //^https?:\/\/(?:www.)?
		if( !empty($param['replacetext']) && is_array($param['replacetext']) ){
			foreach($param['replacetext'] as $v){
				if( empty($v['text']) )continue;
				$textreplace[$v['text']] = $v['replace'];
			}
		}
		
		//---------- Dump.sql replace string  ------------------
		 
		cmcmg::log_write( "Determining text replace in dump.sql", $param['log_file'], array('echo'=>$param['echo_log']) );
		$replaces = array($site_url_old=>$site_url, $meta['table_prefix']=> $prefix, $meta['users_table']=> $users_tbl,
			$meta['usermeta_table']=> $usermeta_tbl);
		if( !empty( $meta['ABSPATH'] ) ) $replaces[$meta['ABSPATH']] = ABSPATH;
		$replaces = array_merge( $replaces, $textreplace);
		$replaces = apply_filters('cmcmg_restore_sql_replace_text', $replaces, $param, 'dump' );
		$param['replace_text_dump'] = $replaces;
		//$dump = file_get_contents( $param['dump_file'] );
		//foreach($replaces as $repk => $repv){
		//	$dump = str_replace( $repk, $repv, $dump);	
		//}
		//file_put_contents( $param['dump_file'], $dump ); unset($dump);
		
		//----------- User.sql replace string --------------------			
		$replaces = array($site_url_old=>$site_url, $meta['users_table']=> $users_tbl, $meta['usermeta_table']=> $usermeta_tbl);
		cmcmg::log_write( "Determining text to replace in users.sql", $param['log_file'], array('echo'=>$param['echo_log']) );
		$replaces = array_merge( $replaces, $textreplace);
		$replaces = apply_filters('cmcmg_restore_sql_replace_text', $replaces, $param, 'users' );
		$param['replace_text_users'] = $replaces;
		//$users_dump = file_get_contents( $param['users_file'] );
		//foreach($replaces as $repk => $repv){
		//	$users_dump = str_replace( $repk, $repv , $users_dump);	
		//}		
		//file_put_contents( $param['users_file'], $users_dump); unset($users_dump); unset($replaces);	
	}

	/**
     *  Restore migrated database
	 *
	 *	@param	array	varriable needed for restore
     */
	private static function restore_databse( &$param ){
		global $wpdb;
		//----------  Drop tables  --------------------------
		$tables = cmcmg::get_current_blog_tables();
		if( !is_multisite() ){			
			$tables[] = $wpdb->users; $tables[] = $wpdb->usermeta;
		}
		if( is_array($tables) && count($tables) > 0 ){
			cmcmg::log_write( "Dropping tables ", $param['log_file'], array('echo'=>$param['echo_log']) );
			$sql = "DROP TABLE ".implode(', ', $tables);		
			$result = $wpdb->query( $sql );
			if( $result === false ){
				cmcmg::log_write( "Drop tables Error : ". $wpdb->last_error, $param['log_file'], array('echo'=>$param['echo_log']) );
			}
			cmcmg::log_write( "Droped tables : ". implode(', ', $tables), $param['log_file'], array('echo'=>$param['echo_log']) );
		}
		
		//----------- Restore dump file  ----------------------
		require_once( CMCMG_DIR_INCLUDE.'class-cmc-migrate-sql.php' );	
		$host = explode(':', DB_HOST); $tables = implode(' ', (array)$model['tables']);
		
		
		//----------- dump.sql ----------------------
		cmcmg::log_write( "Started dump.sql restore ", $param['log_file'], array('echo'=>$param['echo_log']) );
		$param['replace_text_now'] = $param['replace_text_dump'];
		if( $param['mysql_dump_type'] == 'mysql' ){
			$cmd = 'bin/mysql --host='.$host[0].' --user='.DB_USER.' --password='.DB_PASSWORD.' '.DB_NAME." < $param[dump_file]";
			//cmc_migrate_sql::mysqlcmd( $cmd );
		}else if( $param['mysql_dump_type'] == 'php' ){			
			if( $param['run_dump_large'] ){
				cmcmg_sql::run_dump_large( $param['dump_file'], $param['log_file'], $param );
			}else{
				cmcmg_sql::run_dump( $param['dump_file'], $param['log_file'], $param );
			}	
		}
		cmcmg::log_write( "Finished dump.sql restore ", $param['log_file'], array('echo'=>$param['echo_log']) );
		
		
		//----------- users.sql ----------------------
		cmcmg::log_write( "Started users.sql restore ", $param['log_file'], array('echo'=>$param['echo_log']) );
		$param['replace_text_now'] = $param['replace_text_users'];
		if( $param['mysql_dump_type'] == 'mysql' ){
			$cmd = 'bin/mysql --host='.$host[0].' --user='.DB_USER.' --password='.DB_PASSWORD.' '.DB_NAME." < $param[users_file]";
			//cmc_migrate_sql::mysqlcmd( $cmd );
		}else if( $param['mysql_dump_type'] == 'php' ){
			if( $param['run_dump_large'] ){
				cmcmg_sql::run_dump_large( $param['users_file'], $param['log_file'], $param );
			}else{
				cmcmg_sql::run_dump( $param['users_file'], $param['log_file'], $param );
			}
		}
		cmcmg::log_write( "Finished users.sql restore ", $param['log_file'], array('echo'=>$param['echo_log']) );
	}
	
	/**
     *  Delete old wp-content-files 
	 *
	 *	@param	array	varriable needed for restore
     */
	private static function delete_files( &$param ){
		global $wp_filesystem;
		//-------------  Delete wp-content files --------------------------
		cmcmg::log_write( "Deleting wp-content files ", $param['log_file'], array('echo'=>$param['echo_log']) );
		
		foreach(glob(WP_CONTENT_DIR.'/*') as $f ){
			$f_base = basename($f);
			if( !in_array($f_base, (array)$param['delete_files_ignore_wpcontent']) ){
				cmcmg::recursiveDelete( $f );
			}
		}
		//-------------  Delete plugins files --------------------------
		cmcmg::log_write( "Deleting plugin files", $param['log_file'], array('echo'=>$param['echo_log']) );
		foreach(glob(WP_PLUGIN_DIR.'/*') as $f ){
			$f_base = basename($f);
			if( !in_array($f_base, (array)$param['delete_files_ignore_plugins'] ) ){
				cmcmg::recursiveDelete( $f );
			}
		}
		//-------------  Delete theme files --------------------------
		cmcmg::log_write( "Deleting theme files", $param['log_file'], array('echo'=>$param['echo_log']) );
		foreach(glob(WP_CONTENT_DIR.'/themes/*') as $f ){
			$f_base = basename($f);
			if( !in_array($f_base, (array)$param['delete_files_ignore_themes'] ) ){
				cmcmg::recursiveDelete( $f );
			}
		}
		//-------------  Delete upload files --------------------------
		cmcmg::log_write( "Deleting upload files ", $param['log_file'], array('echo'=>$param['echo_log']) );
		if( file_exists($param['uploads']) )cmcmg::recursiveDelete($param['uploads']);
	}

	/**
     *  Copy new Wp-content-files
	 *
	 *	@param	array	varriable needed for restore
     */
	private static function copy_files( &$param ){
		global $wp_filesystem; $meta = &$param['meta'];
		//--------  WP-CONTENT FILES EXECEPT PLUGINS, THEMES, UPLOAD AND CMC-MIGRATE  -------------
		cmcmg::log_write( "Restoring new wp-content files : ". implode(', ', (array)$meta['wp-content-files']), $param['log_file'], array('echo'=>$param['echo_log']) );
		foreach( (array)$meta['wp-content-files'] as $f ){
			$fdir = dirname($f); if( $fdir != '.' ) continue;
			$fdir_full = WP_CONTENT_DIR .'/'.$f;
			if( !file_exists($fdir_full) ){					
				cmcmg::movedir( $param['temp_dir'].'wp-content/'.$f, $fdir_full);
			}
		}

		//---------  Plugins  ----------------------		
		cmcmg::log_write( "Restoring new plugin files : ". implode(', ', $meta['plugins']), $param['log_file'], array('echo'=>$param['echo_log']) );
		foreach( (array)$meta['plugins'] as $f ){
			$fdir = dirname($f); if( $fdir != '.' ) continue;
			$fdir_full = WP_PLUGIN_DIR .'/'.$f;
			if( !file_exists($fdir_full) ){					
				cmcmg::movedir( $param['temp_dir'].'wp-content/plugins/'.$f, $fdir_full);
			}
		}
		
		//-------  Themes  --------------------		
		cmcmg::log_write( "Restoring new themes files : ". implode(', ', $meta['themes']), $param['log_file'], array('echo'=>$param['echo_log']) );
		foreach( (array)$meta['themes'] as $f){
			$fdir = dirname($f); if( $fdir != '.' ) continue;
			$fdir_full = WP_CONTENT_DIR .'/themes/'.$f;
			if( !file_exists( $fdir_full ) ){
				cmcmg::movedir( $param['temp_dir'].'wp-content/themes/'.$f, $fdir_full);
			}			
		}
		
		//--------  Upload folder  -------------------
		if( $meta['uploads']){				
			cmcmg::log_write( "Restoring new upload files ", $param['log_file'], array('echo'=>$param['echo_log']) );
			cmcmg::movedir( $param['uploads_temp'], $param['uploads'] );
		}
	}
	
}
