<?php
/*
package: cmc_migrate
since: 0.0.3
*/

class cmcmg_export{
	
	/**
     *  Internally import a wordpress installation based on plugins, themes, wp-content files, media and database
     */
	public static function export( $param ){
		global $wpdb; $response = array(); $model = &$param['model'];
		//---------- meta ----------------------
		$meta = array('name' => get_bloginfo('name'), 'datetime_start'=> date('Y-m-d H:i:s'));	
		$meta['table_prefix'] = $wpdb->prefix; $meta['siteurl'] = get_bloginfo('siteurl'); $meta['homeurl'] = get_bloginfo('home');
		$meta['blog_id'] = cmcmg::get_current_blog_id(); $meta['blog_description'] = get_bloginfo('description'); $meta['admin_email'] = get_option('admin_email');
		$meta['description'] = $model['description']; $meta['ABSPATH'] = ABSPATH; $meta['is_network'] = $_REQUEST['blog_id'] == -1;		
		$meta['mysql_dump_type'] = 'php'; $meta['mysql_user_type'] = 'php'; $upload_dir = wp_upload_dir(); $upload = $upload_dir['basedir'];
		$meta['from_multisite'] = is_multisite();  $meta['wp_version'] = get_bloginfo('version');
		$meta['mysql_ver'] = $wpdb->db_version(); $meta['php_ver'] = phpversion();
		if( is_multisite() ){
			$current_site = get_current_site();
			$meta['site_id'] = $current_site->id ; $meta['site_name'] = $current_site->site_name; 
			$meta['domain'] = $current_site->domain ; $meta['domain_path'] = $current_site->path;
			$meta['network_siteurl'] = network_site_url();
		}
		$meta['users_table'] = $wpdb->users; $meta['usermeta_table'] = $wpdb->usermeta;
		
		//--------- Export dir -----------------		
		$export_dir = CMCMG_EXPORT_DIR . cmcmg::get_current_blog_id() .'/';
		$dump_file = $export_dir."dump.sql"; $user_file = $export_dir."users.sql";	$log_file = $export_dir."log.txt";
			
		$zip_file_name = $meta['name'].'_'.date('YmdHis').'.zip';
		//$zip_file_name = preg_replace('/[^a-zA-Z0-9\-\._]/','', $zip_file_name);		
		$zip_file = $export_dir . $zip_file_name; if( file_exists($zip_file) ) unlink($zip_file); $zip = new ZipArchive();
			
		$param = array_merge( $param, array('meta'=> &$meta, 'export_dir'=> &$export_dir, 'log_file'=> &$log_file, 'dump_file'=>&$dump_file, 'user_file'=> &$user_file,
			'zip_file_name'=>&$zip_file_name, 'zip_file'=>&$zip_file, 'zip'=>&$zip, 'upload'=>&$upload ) );
		$param['dump_limit'] = 200; $param['php_time_limit'] = 0;
		$param['php_memory_limit'] = '5120M';
		$param = apply_filters('cmcmg_export_params', $param );		
		if( $param === false ) return;
		$response['param'] = &$param;

		if( !file_exists( CMCMG_EXPORT_DIR.'/index.php' ) )  file_put_contents( CMCMG_EXPORT_DIR.'/index.php', "" );
		if( !file_exists( CMCMG_EXPORT_DIR.'/.htaccess' ) )  file_put_contents( CMCMG_EXPORT_DIR.'/.htaccess', "deny from all" );
		if( !file_exists( $export_dir ) )  mkdir( $export_dir, 0777, true );
		if( !file_exists( $export_dir.'index.php' ) )  file_put_contents( $export_dir.'index.php', "" );
		
		cmcmg::log_write( "CMC MIGRATE MIGRATION LOG : ".date('Y-m-d_H-i-s'), $log_file, array('echo'=>$param['echo_log']) );

		//------------  Create Zip file  ----------------
		$zip->open( $zip_file, ZipArchive::CREATE );	
		cmcmg::log_write( "Created zip_file: '$zip_file file'", $log_file, array('echo'=>$param['echo_log']) );
		
		set_time_limit ( $param['php_time_limit'] );
		ini_set( 'memory_limit', $param['php_memory_limit'] );
		
		self::create_dump_file( $param );
		self::add_files( $param );
	
		cmcmg::log_write( "Packaging zip file", $log_file, array('echo'=>$param['echo_log']) );
		$meta['datetime'] = date('Y-m-d H:i:s');
		$meta_json = json_encode($meta);
		$zip->addFromString('meta.json', $meta_json );		
		$zip->addfile( $log_file, 'log.txt' );
		$zip->close();	
		cmcmg::log_write( "Deleting temp files", $log_file, array('echo'=>$param['echo_log']) );
		@unlink( $dump_file ); @unlink( $user_file ); @unlink( $log_file );		
		$response['success'] =  true; $response['message'] =  'Export successful';
		
		cmcmg::log_write( "Applying custom functions", $log_file, array('echo'=>$param['echo_log']) );
		$response = apply_filters( 'cmcmg_exported', $response, $param );
		cmcmg::log_write( "Finished migration : $zip_file_name", $log_file, array('echo'=>$param['echo_log']) );
		return $response;
	}

	/**
     *  Create dump files
     */
	private static function create_dump_file( &$param ){
		$model = &$param['model'];  $meta = &$param['meta'];
		$model['tables'] = array_unique( (array)apply_filters('cmcmg_export_tables', $model['tables'], $param ) );
		if( !empty($model['tables']) ){
			require_once( CMCMG_DIR_INCLUDE.'class-cmc-migrate-sql.php' );	
			$user_tables_array = array( $meta['users_table'], $meta['usermeta_table']);
			$tables = implode(' ', (array)$model['tables']); $user_tables = implode(' ', (array)$user_tables_array); 
			
			cmcmg::log_write( "Creating dump.sql file", $param['log_file'], array('echo'=>$param['echo_log']) );
			$host = explode(':', DB_HOST);
			if( $meta['mysql_dump_type'] == 'mysql' ){				
				$cmd = 'bin/mysqldump --host='.$host[0].' --user='.DB_USER.' --password='.DB_PASSWORD.' '.DB_NAME." $tables > $dump_file";
				cmcmg_sql::mysql_cmd( $cmd );
			}else if( $meta['mysql_dump_type'] == 'php' ){
				cmcmg_sql::phpsql_dump( $model['tables'], $param['dump_file'], $param );
			}
			cmcmg::log_write( "Creating users.sql file", $param['log_file'], array('echo'=>$param['echo_log']) );
			if( $meta['mysql_user_type'] == 'mysql' ){
				$cmd = 'bin/mysqldump --host='.$host[0].' --user='.DB_USER.' --password='.DB_PASSWORD.' '.DB_NAME." $user_tables > $user_file";
				cmcmg_sql::mysql_cmd( $cmd );
			}else if( $meta['mysql_user_type'] == 'php' ){
				cmcmg_sql::phpsql_dump( $user_tables_array, $param['user_file'], $param );
			}
			$meta['tables'] = $model['tables'];			
		}
		
		cmcmg::log_write( "Adding dump.sql and users.sql", $param['log_file'], array('echo'=>$param['echo_log']) );	
		$param['zip']->addfile( $param['dump_file'], 'dump.sql' );
		$param['zip']->addfile( $param['user_file'], 'users.sql' );	
	}
	
	/**
     *  Add files to zip file
     */
	public static function add_files( &$param ){
		//------------ Add Uploads -----------------------		
		$meta = &$param['meta']; $model = &$param['model'];
		$sites_dir = cmcmg::get_current_blog_id() > 0 ? $param['upload'].'/sites':'';
		cmcmg::log_write( "Adding upload folder : " . $param['upload'], $param['log_file'], array('echo'=>$param['echo_log']) );
		if( file_exists( $sites_dir ) ){
			cmcmg::zip_dir( $param['upload'], $param['zip'], $param['upload'], 'wp-content/uploads', array($sites_dir));
		}else{
			cmcmg::zip_dir( $param['upload'], $param['zip'], $param['upload'], 'wp-content/uploads');
		}		
		$meta['uploads'] = true;
		
		//------------- Add Plugins ---------------------
		cmcmg::log_write( "Determining plugins to Add", $param['log_file'], array('echo'=>$param['echo_log']) );
		$plugins = array(); $plugins_net = array(); $plugins_blogs = array();
		if( cmcmg::get_current_blog_id() > 0 ){
			$plugins = (array)get_option('active_plugins', array()); 
		}
		if( $meta['is_network']){
			$plugins_blogs = cmcmg::get_network_blog_active_plugins();
		}
		
		if ( is_multisite() ) {
			$plugins_net = (array) get_site_option( 'active_sitewide_plugins', array() );
			$plugins_net = array_keys( $plugins_net );
		}		
		$plugins = array_merge( (array)$model['plugins'], $plugins, $plugins_net, $plugins_blogs );
		
		$plugins = array_unique( apply_filters( 'cmcmg_export_plugins', $plugins, $param ) );
		if( is_array($plugins) && !empty( $plugins ) ){
			cmcmg::log_write( "Adding plugins : " . implode(', ', $plugins), $param['log_file'], array('echo'=>$param['echo_log']) );
			foreach( $plugins as $f ){
				$fdir = dirname($f);
				if( $fdir == '.' || empty( $fdir ) ){
					$fdir_full = WP_PLUGIN_DIR .'/'.$f;
					if( !file_exists($fdir_full) ) continue;
					$meta['plugins'][] = $f;
					$param['zip']->addfile( $fdir_full, 'wp-content/plugins/'.$f);
				}else{
					$fdir_full = WP_PLUGIN_DIR .'/'.$fdir;
					if( !file_exists($fdir_full) ) continue;
					$meta['plugins'][] = $fdir;
					cmcmg::zip_dir( $fdir_full, $param['zip'], WP_PLUGIN_DIR, 'wp-content/plugins');	
				}
			}	
			$param['zip']->addfile( WP_PLUGIN_DIR.'/index.php', 'wp-content/plugins/index.php');
		}
		
		//------------- Add Themes --------------------
		cmcmg::log_write( "Determining themes to add", $param['log_file'], array('echo'=>$param['echo_log']) );
		$themes = array(); $themes_blogs = array();
		if( cmcmg::get_current_blog_id() > 0 ){
			$meta['active_theme'] = get_option( 'stylesheet' );
			$themes[] = $meta['active_theme'];
			$theme = wp_get_theme();
			if( !empty($theme->template) && $theme->template != $meta['active_theme'] ){
				$themes[] = $theme->template; $meta['theme_parent'] = $theme->Template;
			}
		}
		if( $meta['is_network'] ){
			$themes_blogs = cmcmg::get_network_blog_active_themes();
		}
		$themes = array_merge( (array)$model['themes'], $themes, $themes_blogs);
		
		$themes = array_unique( (array)apply_filters('cmcmg_export_themes', $themes, $param ) );
		if( is_array( $themes ) && !empty( $themes ) ){
			cmcmg::log_write( "Adding themes : ". implode(', ', $themes) , $param['log_file'], array('echo'=>$param['echo_log']) );
			foreach( $themes as $f){				
				$fdir = WP_CONTENT_DIR .'/themes';
				$fdir_full =  $fdir . "/" .$f;
				if( !file_exists($fdir_full) ) continue;
				cmcmg::zip_dir( $fdir_full, $param['zip'], $fdir, 'wp-content/themes');					
				$meta['themes'][] = $f;
			}
		}
		$param['zip']->addfile( WP_CONTENT_DIR .'/themes/index.php', 'wp-content/themes/index.php');		
	
		//----------- Add Other wp-content files ---------------
		$model['wp-content-files'] = (array)apply_filters('cmcmg_export_wp_content_files', $model['wp-content-files'], $param );
		if( is_array($model['wp-content-files']) && !empty( $model['wp-content-files'] ) ){
			cmcmg::log_write( "Adding wp-conten-files", $param['log_file'], array('echo'=>$param['echo_log']) );
			foreach( $model['wp-content-files'] as $f ){
				$fdir_full = WP_CONTENT_DIR .'/' . $f;
				if( !file_exists($fdir_full) ) continue;
				if( is_file($fdir_full) ) $param['zip']->addfile( $fdir_full, 'wp-content/' . $f);
				else cmcmg::zip_dir( $fdir_full, $param['zip'], WP_CONTENT_DIR, 'wp-content');
				
				$meta['wp-content-files'][] = $f;
			}	
		}
		$param['zip']->addfile( WP_CONTENT_DIR.'/index.php', 'wp-content/index.php');
	}
		
}
