<?php
/*
package: cmc_migrate
since: 0.0.3
*/

class cmcmg_action{

	/**
     *  Restore an exported wordpress installation
     */
	public static function restore( $param = array() ){
		$param = !is_array( $param )? array() : $param;
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmcmg_restore_migration' ) ) {
            die( 'Cheating...' );
        }	
		
		$param['replacetext'] = (array)$_REQUEST['replacetext'];
		require_once( CMCMG_DIR_INCLUDE.'class-cmc-migrate-restore.php' );
		$response = cmcmg_restore::restore( $_REQUEST['id'], $param );
		return $response;
	}

	/**
     *  Export a wordpress installation based on plugins, themes, wp-content files, media and database
     */
	public static function export( $param = array() ){
		$param = !is_array($param)? array(): $param;
	
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmcmg_export_action' ) ) {
            die( 'Cheating...' );
        }
		global $wpdb; $model = array();
		$model['tables'] = empty($_POST['tables'])? array() : $_POST['tables'];
		$model['themes'] = empty($_POST['themes'])? array() : $_POST['themes'];
		$model['plugins'] = empty($_POST['plugins'])? array() : $_POST['plugins'];
		$model['description'] = sanitize_text_field($_POST['description']);
		
		require_once( CMCMG_DIR_INCLUDE.'class-cmc-migrate-export.php' );
		$param['model'] = &$model;
		$response = cmcmg_export::export( $param );
		return $response;
	}
	
	/**
     *  Send migration list to remote server
	 * 	@since 0.0.2
     */
	public static function remote_migration_list(){
		if(cmcmg::get_setting('allow_remote_connection') && !empty(cmcmg::get_setting('remote_connection_token')) && cmcmg::get_setting('remote_connection_token') == $_REQUEST['token'] ){
			require_once( CMCMG_DIR_INCLUDE ."class-cmc-migration-table.php");
			$migration_list = new cmc_migration_List( 'free' );
			$result = $migration_list->remote_prepare_items();
			return wp_send_json( $result );
		}
	}
	
	/**
     *  Get binary migration file
     */
	public static function remote_import_migration_file(){
		if( headers_sent()) die("Headers Already Sent. Unable To Download File");
		if(cmcmg::get_setting('allow_remote_connection') && !empty(cmcmg::get_setting('remote_connection_token')) && cmcmg::get_setting('remote_connection_token') == $_REQUEST['token'] ){
			$out = (array)self::get_migration_file_binary( $_REQUEST['id'] );
			//ini_set('max_execution_time', 0);
			@set_time_limit(0);
			$type = (empty($type))? 'application/zip' : $type;	
			header('Content-Description: File Transfer');		
			header('Content-Type: '.$type);
			header("Content-Transfer-Encoding: binary");
			echo $out['binary'];
		}	
		exit();
		//if (!headers_sent()) {
		//  foreach (headers_list() as $header)
		//	header_remove($header);
		//}
	}
	
	/**
     *  Download an exported file based on the file slug
     */
	public static function download(){
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and !wp_verify_nonce( $nonce, 'cmcmg_delete_action' ) ) {
            die( 'Cheating...' );
        }
		
		$file = $_REQUEST['id'];
		$out = (array)self::get_migration_file_binary( $file );
		cmcmg::output_file( $out['binary'], $file, filesize($out['file']) );
	}
		
	/**
     *  Get Migration file as binary
	 *
	 * 	@param	String	$file	Name of binary file
     */
	private static function get_migration_file_binary( $file ){
		$base = dirname($file); if( $base != '.' ) return ''; $response = array();
		$response['file'] = CMCMG_EXPORT_DIR . cmcmg::get_current_blog_id() .'/'.$file; 
		$response['binary'] = file_get_contents( $response['file'] );
		
		return $response;
	}
	
	/**
     *  Delete an exported file based on the file slug
     */
	public static function delete(){
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmcmg_delete_action' ) ) {
            die( 'Cheating...' );
        }
		$file = $_REQUEST['id']; 
		$response = self::_delete( array($file) );
		
		$bid = cmcmg::$url_blog_id;
		wp_redirect( "?page=cmcmg$bid" );				
		exit;
	}
	
	/**
     *  Internally Delete an exported file based on the file slug
	 *
	 *	@param	array	$files	file names of migrations to delete
     */
	private static function _delete( $files ){
		$response = array();		
		foreach( $files as $file ){
			$base = dirname($file); if( $base != '.' ) continue;
			$f_path = CMCMG_EXPORT_DIR . cmcmg::get_current_blog_id() .'/'.$file; @unlink($f_path);	
		}
		
		do_action('cmcmg_delete', $files );
		
		$response['success'] = true; $response['message'] = 'Deleted Successfully';
		return $response;
	}
	
	/**
     *  Import a migrated site
     */
	public static function import(){
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmcmg_import_action' ) ) {
            die( 'Cheating...' );
        }
		$file = $_FILES['cmcmg_file_import']; if( empty($file) )return;
		
		$response = self::_import( $file['tmp_name'], $file['name'] );
		
		$bid = cmcmg::$url_blog_id;	
		wp_redirect( "?page=cmcmg$bid" );
		exit();
	}

	/**
     *  Internally Import migrated site
	 *
	 *	@param	string	@file	path to file to import
	 *	@param	string	@name	name of file to import
    **/	
	private static function _import( $file, $name ){
		$response = array(); $file_b = basename( $file ); if( empty($file_b) || empty($name)) return;
		
		$export_dir = CMCMG_EXPORT_DIR . cmcmg::get_current_blog_id() .'/';
		if( !file_exists( $export_dir ) )  mkdir( $export_dir, 0777, true );
		
		$file_new = $export_dir . $name;
		$result = rename($file, $file_new);

		$response['success'] = true; $response['message'] = 'Import Successful';
		$response = apply_filters('cmcmg_import', $response, $file_new);
		return $response;
	}

	/**
     *  Import a migrated site remotely	
     */
	public static function remote_import(){
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmcmg_delete_action' ) ) {
            die( 'Cheating...' );
        }
		
		$file = $_REQUEST['id'];  
		$response = (array)self::_remote_import( $file );
		
		if( !empty( $response['message'] ) ){
			$admin_notice = ( is_network_admin()? 'network_':'' ) . 'admin_notices';
			add_action($admin_notice, function() use ( $response ){
				$notice = $response['success']?'success':'error';
				printf("<div class='notice notice-%s'><p>%s</p></div>", $notice, $response['message'] );
			});
		}
		
		$bid = cmcmg::$url_blog_id; $msg = '';
		$msg = cmcmg::url_message($response);
		
		wp_redirect( "?page=cmcmg$bid$msg" );				
		exit;
	}	
	
	/**
     *  Privately Import a migrated site remotely
	 *
	 *	@param string $file	file name of migration to import			
     */
	private static function _remote_import( $file ){
		if( empty($file) )return; $base = dirname($file); if( $base != '.' ) return;
		ini_set('max_execution_time', 0);
		$url = rtrim(cmcmg::get_setting('remote_migration_url'), '/\\'); $response = array();
		$token = cmcmg::get_setting('remote_migration_token');
		
		$source = add_query_arg( array('XDEBUG_SESSION_START'=>'' ), $url.'/wp-admin/admin-ajax.php' );		
		$resp = wp_remote_post( $source, array(
			'method' => 'POST', 'timeout' => 45, 'redirection' => 5,
			'httpversion' => '1.0', 'blocking' => true, 'headers' => array(),
			'body' => array('cmcmg'=>'migration_file', 'token'=>$token, 'id'=>$file)
			)  
		); 
		
		if ( is_wp_error( $resp ) ) {
			$response['success'] = false;
			$response['message'] = $resp->get_error_message();
		} else {
			$export_dir = CMCMG_EXPORT_DIR . cmcmg::get_current_blog_id() .'/';
			if( !file_exists( $export_dir ) )  mkdir( $export_dir, 0777, true );		
			$destination = $export_dir . $file; $f = fopen($destination, "w+");
			fputs($f, $resp['body']); fclose($f);
			$response['success'] = true;
			$response['message'] = "import successful";
			$response['file'] = $destination;
		}

		$response = apply_filters('cmcmg_remote_import', $response, $resp );	
		return $response;
	}

	/**
     *  Save Settings of cmc migrate
     */
	public static function save_settings(){
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmcmg-settings-save-nonce' ) ) {
            die( 'Cheating...' );
        }
		
		$response = self::_save_settings( $_POST );            
	}
	
	/**
     *  Privately save settings
	 *
	 * 	@param array $model items to save
     */
	private static function _save_settings( $model ){
		$data = array(); $response = array();
		if( !cmcmg::get_setting('version') ){
			$data['version'] = CMCMG_VERSION;
		}
		$data['allowed_users'] = wp_unslash( $model['allowed_users'] );
		$data['remote_migration_url'] = sanitize_text_field( wp_unslash( $model['remote_migration_url'] ) );
		$data['remote_connection_token'] = sanitize_text_field( wp_unslash( $model['remote_connection_token'] ) );
		$data['remote_migration_token'] = sanitize_text_field( wp_unslash( $model['remote_migration_token'] ) );
		$data['del_opt_uninstall'] = isset( $model['del_opt_uninstall'] ) ? 1 : 0;
		$data['del_folder_uninstall'] = isset( $model['del_folder_uninstall'] ) ? 1 : 0;
        $data['replace_dir_theme'] = isset( $model['replace_dir_theme'] ) ? 1 : 0;
		$data['replace_dir_plugin'] = isset( $model['replace_dir_plugin'] ) ? 1 : 0;
		$data['replace_dir_wpcontent'] = isset( $model['replace_dir_wpcontent'] ) ? 1 : 0; 
		$data['allow_remote_connection'] = isset( $model['allow_remote_connection'] ) ? 1 : 0;  
	
		$data = apply_filters( 'cmcmg_settings_data_save', $data);
        if( $data === false )return false;

        update_option(CMCMG_SETTINGS, $data);
		
		$response['success'] = true; $response['message'] = "Saved Successfully";
		
		do_action('cmcmg_settings_save', $data, $response);
		
		return $response;
	}
	
	/**
     *  Save Network Settings of cmc migrate
	 * 	@since 0.0.2
     */
	public static function save_net_settings(){
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmcmg-network-settings-save-nonce' ) ) {
            die( 'Cheating...' );
        }
		
		$response = self::_save_net_settings( $_POST );            
	}
	
	/**
     *  Privately save settings
	 * 	@since 0.0.2
	 *
	 * 	@param array $model items to save
     */
	private static function _save_net_settings( $model ){
		$data = array(); $response = array();		
		$data['version'] = CMCMG_VERSION;
		
		$data['allowed_sites'] = wp_unslash( $model['allowed_sites'] );
		$data['del_opt_uninstall'] = isset( $model['del_opt_uninstall'] ) ? 1 : 0;
		$data['del_folder_uninstall'] = isset( $model['del_folder_uninstall'] ) ? 1 : 0; 		
		
		$data = apply_filters( 'cmcmg_network_settings_data_save', $data);
        if( $data === false )return false;

        update_site_option(CMCMG_NET_SETTINGS, $data);
		
		do_action('cmcmg_network_settings_save', $data);
		
		$response['success'] = true; $response['message'] = "Saved Successfully";
		return $response;
	}
	
}
