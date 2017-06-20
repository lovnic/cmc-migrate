<?php
/**
Plugin Name: cmc-migrate
Description: Migrate sites from installation to another
Version: 0.0.2
Author: Evans Edem Ladzagla
Author URI: https://profiles.wordpress.org/lovnic/
License:     GPL3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: cmcmg
**/ 

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */

if(!defined('ABSPATH')) { 
    header('HTTP/1.0 403 Forbidden');
    exit;
}

/**
 * Main cmc_migrate Class.
 *
 * @class cmc_migrate
 */
final class cmc_migrate {
	
	 /**
     * The single instance of the class.
     *
     * @var cmc_migrage
     */
	public static $_instance = null;
	
	 /**
     * migrations instance
     *
     * @var cmc_migration_List.
     */
    public static $migrations;
	
	 /**
     * Admin Page Url.
     *
     * @var string
     */
    public static $menu;
	
	/**
     * Current Blog id.
     *
     * @var int
     */
    public static $blog_id;
	
	/**
     * Current Url Blog id.
     *
     * @var int
     */
    public static $url_blog_id;
	
	/**
     * Main cmc_migrate Instance.
     *
     * Ensures only one instance of cmc_migrate is loaded or can be loaded.
     *
     * @static
     * @return cmc_migate - Main instance.
     */
	public static function instance(){
        if( self::$_instance == null ){
            self::$_instance = new self();
        }
        return self::$_instance;
    }
	
	/**
    * 	constructor of cmc_migrate
    */
	function __construct(){
		self::constants(); self::includes();	
		self::$blog_id = self::get_blog_id();
		self::$url_blog_id = is_network_admin()? '&blog_id='.self::get_blog_id():'';
		add_action( 'plugins_loaded', array( $this, 'init'));		
		register_activation_hook( __FILE__, array( __CLASS__, 'plugin_activate' ) );
        register_deactivation_hook( __FILE__, array( __CLASS__, 'plugin_deactivate' ) ); 
		register_uninstall_hook( __FILE__, array( __CLASS__, 'plugin_uninstall' ) ); 		
	}
	
	/**
    * Check whether current user is allowed to use cmc migrate
	* Allows Administrators by default
    */
	public static function is_user_allowed(){
		if( current_user_can('administrator') ) return true;
		$allowed_roles = self::get_setting('allowed_roles');  $allowed_roles = explode('\n', $allowed_roles);
		foreach($allowed_roles  as $role){
			if( current_user_can( $role ) ) return true;
		}
		return false;
	}
	
	/**
    * Check whether the plugin is activated network wide
	* @since 0.0.2
    */
	public static function is_network_activated(){
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}	
		return is_plugin_active_for_network( CMCMG_BASENAME ) && is_multisite();
	}
	
	
	/**
    * Init cmc-migrage when WordPress Initialises
    */
	public function init(){
		do_action( 'before_cmcmg_init' );
		
		if( self::is_network_activated() && !is_network_admin() ){
			$allowed_sites = (array)self::get_net_setting('allowed_sites', array());
			$allowed_sites = (array)apply_filters('cmcmg_allowed_sites', $allowed_sites );
			if( !in_array( get_current_blog_id(), $allowed_sites ) ){
				return;
			}
		}
		
		if( is_super_admin() && self::is_network_activated() && is_network_admin() ){
			switch( $_REQUEST['cmcmg_action'] ){
				case 'save_net_settings': self::save_net_settings(); break;	
			}
		}
		
		if( is_admin() ){ 				
			if( self::is_network_activated() && $_REQUEST['page'] == 'cmcmg' && is_network_admin() && $_REQUEST['blog_id'] != -1 ){
				switch_to_blog( self::$blog_id );
			}
				
			if( self::is_user_allowed() ){				
				switch( $_REQUEST['cmcmg_action'] ){
					case 'export': self::export(); break;
					case 'import': self::import(); break;
					case 'remote_import': self::remote_import(); break;			
					case 'download': self::download(); break;	
					case 'delete': self::delete(); break;	
					case 'restore': self::restore(); break;
					case 'save_settings': self::save_settings(); break;	
				}
				
				add_action( 'admin_menu', array( $this, 'admin_menu' ) );
				add_action( 'delete_blog', array( $this, 'delete_blog' ) );
				
				if( is_super_admin() && is_plugin_active_for_network( CMCMG_BASENAME ) && is_network_admin() ){
					self::network_admin_menu();
				}

				add_filter( 'set-screen-option', function($status, $option, $value ){return $value;}, 10, 3 );
			}
			if( defined( 'DOING_AJAX' ) && DOING_AJAX ){
				if( $_REQUEST['cmcmg'] == 'migrations' ){
					self::remote_migration_list();
				}	
				if( $_REQUEST['cmcmg'] == 'migration_file' ){
					self::remote_import_migration_file();
				}
			}
		}

		do_action( 'cmcmg_init' );
	}
	
	/**
     * Loads Admin Menu 
     * Page cmc-migrate is added to Tools
     */
    public function admin_menu(){
		if( is_network_admin() ){ 
			$hook = add_submenu_page('settings.php', __('CMC Migrate', 'cmcmg'), "CMC Migrate", 'manage_network', 'cmcmg', function(){ require_once( 'page/admin.php' ); });
		}else{
			$hook = add_submenu_page('tools.php', __('CMC Migrate', 'cmcmg'), 'CMC Migrate', 'export', 'cmcmg', function(){ require_once( 'page/admin.php' ); });
		}
        
        self::$menu = menu_page_url( 'cmcmg', false );
        add_action( "load-$hook", array( __CLASS__, "menu_load"));		
    }
	
	/**
     * On Admin Menu load this function run
	 * 	@since 0.0.2
     */
	private static function network_admin_menu(){
		add_action( 'network_admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_filter( 'cmcmg_admin_page_menu', function( $menu ){
			$bid = self::$url_blog_id;
			$menu['net_sets'] = array('text'=>__('Network Settings', 'cmcmg'), 'href'=>"?page=cmcmg&tab=net_sets$bid",    
				'sections' => array(
					'net_sets'=>array('page'=>function(){ 
						echo "<div id='cmcmg_admin_page_section_network_settings' class='cmcmg_section'>";
						require( CMCMG_PAGE_SECTION_DIR . "network_settings.php");  
						echo "<div>";
					})
				), 'default'=>'net_sets');
			return $menu;
		});
	}
	
	/**
     * On Admin Menu load this function run
	 * 	@since 0.0.2
     */
	public static function menu_load(){
			
        if( empty($_REQUEST['tab']) && empty($_REQUEST['section']) ){
			require_once("include/class-cmc-migration-table.php");
			$option = 'per_page';
			$args   = [
				'label'   => 'Migration',
				'default' => 5,
				'option'  => 'migrations_per_page'
			];
			add_screen_option( $option, $args );	
			self::$migrations = new cmc_migration_List(); 
			self::$migrations->process_bulk_action();
        }
		
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui-accordion' );
		wp_enqueue_script( 'tiptip_js', CMCMG_JS_URL.'TipTip/jquery.tipTip.js' ); 		
	
		wp_enqueue_style( 'tiptip_css', CMCMG_JS_URL.'TipTip/tipTip.css');
		wp_enqueue_style( 'font_font-awesome_css', CMCMG_CSS_URL.'font-awesome/css/font-awesome.min.css');
		wp_enqueue_style( 'jquery-ui_css', CMCMG_CSS_URL.'jquery-ui/jquery-ui.css');
	}
		
	/**
     * On Admin Menu load this function run
	 * 	@since 0.0.2
     */
	public static function menu_render( $slug, $section, $menus ){
		$menu = $menus[$slug];
		if( !empty($menu['sections']) ){
			self::menu_section_render( $slug, $section, $menus );
		}else{			
			if( is_callable($menu['page']) )
				call_user_func_array( $menu['page'], array() );
		}
	}

	/**
     * On Admin Menu load this function run
	 * 	@since 0.0.2
     */
	public static function menu_section_render( $slug, $section, $menus ){
		$menu = $menus[$slug]; $section = empty( $section )? $menu['default']: $section;
		$sections = apply_filters("cmcmg_admin_page_section-{$section}", $menu['sections'], $menus);		
		$page = $sections[$section];
		call_user_func( $page['page'], $menu, $slug, $section );
	}
	
	/**
     *  Send migration list to remote server
	 * 	@since 0.0.2
     */
	private static function remote_migration_list(){
		if(self::get_setting('allow_remote_connection') && !empty(self::get_setting('remote_connection_token')) && self::get_setting('remote_connection_token') == $_REQUEST['token'] ){
			require_once("include/class-cmc-migration-table.php");
			$migration_list = new cmc_migration_List( 'free' );
			$result = $migration_list->remote_prepare_items();
			return wp_send_json( $result );
		}
	}
	
	/**
     *  Get binary migration file
     */
	protected static function remote_import_migration_file(){
		if( headers_sent()) die("Headers Already Sent. Unable To Download File");
		if(self::get_setting('allow_remote_connection') && !empty(self::get_setting('remote_connection_token')) && self::get_setting('remote_connection_token') == $_REQUEST['token'] ){
			$out = self::get_migration_file_binary( $_REQUEST['id'] );
			
			$type = (empty($type))? 'application/zip' : $type;	
			header('Content-Description: File Transfer');		
			header('Content-Type: '.$type);
			echo $out;
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
	protected static function download(){
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmcmg_delete_action' ) ) {
            die( 'Cheating...' );
        }
		
		$file = $_REQUEST['id'];
		$out = self::get_migration_file_binary( $file );
		self::output_file( $out, $file );
	}
		
	/**
     *  Get Migration file as binary
	 *
	 * 	@param	String	$file	Name of binary file
     */
	private static function get_migration_file_binary( $file ){
		$base = dirname($file); if( $base != '.' ) return '';
		$f_path = CMCMG_EXPORT_DIR . self::get_current_blog_id() .'/'.$file; $binary = file_get_contents( $f_path );
		return $binary;
	}
	
	/**
     *  Restore an exported wordpress installation
     */
	protected static function restore(){
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmcmg_restore_migration' ) ) {
            die( 'Cheating...' );
        }		
		self::_restore( $_REQUEST['id'] );
	}
	
	/**
     *  Internally Restore an exported wordpress installation
     */
	private static function _restore( $file ){	
		$base = dirname($file); if( $base != '.' ) return false;
		$zipfile = CMCMG_EXPORT_DIR.get_current_blog_id().DIRECTORY_SEPARATOR.$file; if( !file_exists( $zipfile ) ) return false;
		$zip = new ZipArchive; if( !$zip->open($zipfile) ) return false;
		$bid = cmc_migrate::$url_blog_id;
			
		if( is_multisite() ){
			$restore_single_site_func =  apply_filters( 'cmcmg_restore_multisite_single_site_func', false );
				
			if( $restore_single_site_func === false ){
				$admin_notice = ( is_network_admin() ? 'network_':'' ).'admin_notices';
				add_action( $admin_notice , function(){
					echo sprintf("<div class='notice notice-error is-dismissible' ><p>%s</p></div>", 'Restore to multisite single site is not support in this version');
				});	
			}
			if( is_callable($restore_single_site_func) ){
				call_user_func( $restore_single_site_func, $zip );
			}		
		}else{
			$response = self::_restore_to_non_multisite_single_site( $zip, $file );
			if($response['success']){
				$notice = (is_network_admin()?'network_':'').'admin_notices';
				add_action( $notice, function(){
					$msg = "Site Restored Successfully. Log in again. Thank you.";
					printf("<div class='notice notice-success'><p>%s</p></div>", $msg);
				});
			}
		}
		
		do_action( 'cmcmg_restore', $zip, $file );
		
	}
	
	/**
     *  Internally Restore an exported wordpress installation to non multisite single site	
	 *
	 *	@param	string	filename of migration to restore
     */
	private static function _restore_to_non_multisite_single_site( $zip, $file ){
		$response = array(); $base_name = basename($file, '.zip');	
		$temp_dir = CMCMG_IMPORT_DIR .self::get_current_blog_id().DIRECTORY_SEPARATOR; //.date('Y-m-d_H-i-s');
		if( !file_exists( CMCMG_IMPORT_DIR ) )  mkdir( CMCMG_IMPORT_DIR, 0777, true );
		if( !file_exists( CMCMG_IMPORT_DIR.'index.php' ) ) file_put_contents( CMCMG_IMPORT_DIR.'index.php', '//Silence is golden' );

		if( !file_exists( $temp_dir ) )  mkdir( $temp_dir, 0777, true );
		if( !file_exists( $temp_dir.'index.php' ) ) file_put_contents( $temp_dir.'index.php', '//Silence is golden');
		$zip->extractTo($temp_dir);  $zip->close();
		
		global $wpdb; $meta = file_get_contents( $temp_dir.'meta.json' ); $meta = json_decode($meta, true);
				
		//--------- Log file ------------------------------
		$log_dir = CMCMG_LOG_DIR . self::get_current_blog_id().DIRECTORY_SEPARATOR;
		if( !file_exists( $log_dir ) )  mkdir( $log_dir, 0777, true );
		if( !file_exists( $log_dir.'index.php' ) ) file_put_contents( $log_dir.'index.php', '//Silence is golden');
		if( !file_exists( CMCMG_LOG_DIR.'index.php' ) ) file_put_contents( CMCMG_LOG_DIR.'index.php', '//Silence is golden');
		$log_file = $log_dir . "restore_log_".$base_name.".txt";
	
		file_put_contents( $log_file, '');
		self::log_write( "CMC MIGRATE RESTORE LOG : ".date('Y-m-d_H-i-s'), $log_file );
		self::log_write( "Restoring from migration : '{$base_name}'", $log_file );
		
		$prefix = $wpdb->prefix; $site_url = get_bloginfo('siteurl'); $home_url = get_bloginfo('home');
		$users_tbl = $wpdb->users; $usermeta_tbl = $wpdb->usermeta;
				
		//---------- Dump.sql replace string  ------------------
		$dump_file = $temp_dir . 'dump.sql'; $dump = file_get_contents( $dump_file );
		self::log_write( "Changing siteurl from : ". $meta['siteurl'] . " to: ". $site_url, $log_file );
		$dump = str_replace( $meta['siteurl'], $site_url, $dump);		
		$dump = str_replace( $meta['table_prefix'], $prefix, $dump);
		$dump = str_replace( $meta['users_table'], $users_tbl, $dump);
		$dump = str_replace( $meta['usermeta_table'], $usermeta_tbl, $dump);
		self::log_write( "Changing table prefix from : ". $meta['table_prefix'] . " to: ". $prefix, $log_file );
		file_put_contents( $dump_file, $dump); unset($dump);
		
		//----------- User.sql replace string --------------------
		$users_file = $temp_dir . 'users.sql'; $users_dump = file_get_contents( $users_file );	
		$users_dump = str_replace( $meta['siteurl'], $site_url, $users_dump);
		$users_dump = str_replace( $meta['users_table'], $users_tbl, $users_dump);
		$users_dump = str_replace( $meta['usermeta_table'], $usermeta_tbl, $users_dump);		
		file_put_contents( $users_file, $users_dump); unset($users_dump);		
		
		//----------  Drop tables  --------------------------
		$tables = self::get_current_blog_tables();
		//foreach ( $tables as $table ) {//$wpdb->query( "DROP TABLE $table" );//}
		self::log_write( "Dropping tables ", $log_file );
		$sql = "DROP TABLE ".implode(', ', $tables);		
		$result = $wpdb->query( $sql );
		if( $result === false ){
			self::log_write( "Drop tables Error : ". $wpdb->last_error, $log_file );
		}
		self::log_write( "Droped tables : ". implode(', ', $tables), $log_file );
		
		//----------- Restore dump file  ----------------------
		require_once( CMCMG_DIR_INCLUDE.'class-cmc-migrate-sql.php' );	
		$host = explode(':', DB_HOST); $tables = implode(' ', (array)$model['tables']);
		$cmd = 'bin/mysql --host='.$host[0].' --user='.DB_USER.' --password='.DB_PASSWORD.' '.DB_NAME." < $dump_file";
		//cmc_migrate_sql::mysqlcmd( $cmd );
		cmc_migrate_sql::run_dump( $dump_file, $log_file );
		self::log_write( "Finished dump.sql restore ", $log_file );
		$cmd = 'bin/mysql --host='.$host[0].' --user='.DB_USER.' --password='.DB_PASSWORD.' '.DB_NAME." < $users_file";
		//cmc_migrate_sql::mysqlcmd( $cmd );
		cmc_migrate_sql::run_dump( $users_file, $log_file );
		self::log_write( "Finished users.sql restore ", $log_file );
		
		//-------------  Delete wp-content files --------------------------
		foreach(glob(WP_CONTENT_DIR.'/*') as $f ){
			$f_base = basename($f);
			if( !in_array($f_base, array('cmc-migrate', 'plugins', 'themes', 'index.php', 'uploads')) ){
				self::recursiveDelete( $f );
			}
		}
		self::log_write( "Deleted wp-content files ", $log_file );
		
		//--------  WP-CONTENT FILES EXECEPT PLUGINS, THEMES, UPLOAD AND CMC-MIGRATE  -------------
		foreach( (array)$meta['wp-content-files'] as $f ){
			$fdir = dirname($f); if( $fdir != '.' ) continue;
			$fdir_full = WP_CONTENT_DIR .'/'.$f;
			if( !file_exists($fdir_full) ){					
				self::movedir( $temp_dir.'wp-content/'.$f, $fdir_full);
			}
		}
		self::log_write( "Restored wp-content files :". implode((array)$meta['wp-content-files']), $log_file );
		
		//---------  Plugins  ----------------------
		foreach(glob(WP_PLUGIN_DIR.'/*') as $f ){
			$f_base = basename($f);
			if( !in_array($f_base, array('cmc-migrate', 'index.php')) ){
				self::recursiveDelete( $f );
			}
		}
		self::log_write( "Delete wp plugin files :", $log_file );
		
		foreach( (array)$meta['plugins'] as $f ){
			$fdir = dirname($f); if( $fdir != '.' ) continue;
			$fdir_full = WP_PLUGIN_DIR .'/'.$f;
			if( !file_exists($fdir_full) ){					
				self::movedir( $temp_dir.'wp-content/plugins/'.$f, $fdir_full);
			}
		}
		self::log_write( "Restored wp Plugin files :". implode($meta['plugins']), $log_file );
		
		//-------  Themes  --------------------
		foreach(glob(WP_CONTENT_DIR.'/themes/*') as $f ){
			$f_base = basename($f);
			self::recursiveDelete( $f );
		}		
		self::log_write( "Deleted wp theme files :", $log_file );
		
		foreach((array)$meta['themes'] as $f){
			$fdir = dirname($f); if( $fdir != '.' ) continue;
			$fdir_full = WP_CONTENT_DIR .'/themes/'.$f;
			if( !file_exists( $fdir_full ) ){
				self::movedir( $temp_dir.'wp-content/themes/'.$f, $fdir_full);
			}			
		}
		self::log_write( "Restored wp themes files :". implode($meta['themes']), $log_file );
		
		//--------  Upload folder  -------------------
		if( $meta['uploads']){
			$upload_dir = wp_upload_dir(); $uploads = $upload_dir['basedir']; $upload_temp = $temp_dir.'wp-content/uploads';
			if( file_exists($uploads) )self::recursiveDelete($uploads);
			//rename( $upload_temp, $uploads);
			self::movedir( $upload_temp, $uploads );
		}
		self::log_write( "Restored Upload files :". $meta['uploads'], $log_file );
		
		$temp_dir = rtrim($temp_dir, '/\\');
		self::recursiveDelete($temp_dir);
		self::log_write( "Deleted temp directory : ". $temp_dir, $log_file );
		self::log_write( "Finished Restore ", $log_file );
		
		do_action( 'cmcmg_restore_to_non_multisite_single_site', $zip, $log_file );
		
		$response['success'] = false; $response['message'] = "Restore Successfull";
		return $response;
	}
	
	/**
     *  Internally Restore an exported wordpress installation to multisite single site	
     */
	private static function _restore_to_multisite_single_site(){
		$response = array(); $base = dirname($file); if( $base != '.' ) return false;
		$zipfile = CMCMG_EXPORT_DIR.get_current_blog_id().DIRECTORY_SEPARATOR.$file; if( !file_exists( $zipfile ) ) return false;
		$zip = new ZipArchive; if( !$zip->open($zipfile) ) return false;
		
		$temp_dir = CMCMG_IMPORT_DIR .'site'.DIRECTORY_SEPARATOR; //.date('Y-m-d_H-i-s');
		if( !file_exists( $temp_dir ) )  mkdir( $temp_dir, 0777, true );
		$zip->extractTo($temp_dir);  $zip->close();
		
		$meta = file_get_contents( $temp_dir.'meta.json' ); $meta = json_decode($meta, true);
		$dump_file = $temp_dir . 'dump.sql'; $dump = file_get_contents( $dump_file );
		$users_file = $temp_dir . 'users.sql'; $users_dump = file_get_contents( $users_file );		
		
		global $wpdb; $pre = $wpdb->prefix;
		$tables = $wpdb->get_col( "SHOW TABLES LIKE '{$pre}%'" );
		
		$site_url = get_bloginfo('siteurl'); $home_url = get_bloginfo('home');
		$users_tbl = $wpdb->users; $usermeta_tbl = $wpdb->usermeta;
		//$users_tbl = 'wp_14_users'; $usermeta_tbl = 'wp_14_usermeta';
		$dump = str_replace( $meta['siteurl'], get_bloginfo('siteurl'), $dump);
		$dump = str_replace( $meta['table_prefix'], $pre, $dump);
		$dump = str_replace( $meta['users_table'], $users_tbl, $dump);
		$dump = str_replace( $meta['usermeta_table'], $usermeta_tbl, $dump);
		file_put_contents( $dump_file, $dump); unset($dump);

		
		$users_dump = str_replace( $meta['users_table'], $users_tbl, $users_dump);
		$users_dump = str_replace( $meta['usermeta_table'], $usermeta_tbl, $users_dump);
		$users_dump = str_replace( $meta['siteurl'], get_bloginfo('siteurl'), $users_dump);
		file_put_contents( $users_file, $users_dump); unset($users_dump);		
		
		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE $table" );
		}
		
		// Restore dump file
		$host = explode(':', DB_HOST); $tables = implode(' ', (array)$model['tables']);
		$cmd = 'bin/mysql --host='.$host[0].' --user='.DB_USER.' --password='.DB_PASSWORD.' '.DB_NAME." < $dump_file";
		self::mysqlcmd( $cmd );
		$cmd = 'bin/mysql --host='.$host[0].' --user='.DB_USER.' --password='.DB_PASSWORD.' '.DB_NAME." < $users_file";
		self::mysqlcmd( $cmd );
		
		//Restore files
		//Plugins		
		foreach( (array)$meta['plugins'] as $f ){
			$fdir = dirname($f); if( $fdir != '.' ) continue;
			$fdir_full = WP_PLUGIN_DIR .'/'.$f;
			if( !file_exists($fdir_full) ){					
				rename( $temp_dir.'wp-content/plugins/'.$f, $fdir_full);
			}
		}
		
		//Themes
		foreach( (array)$meta['themes'] as $f ){
			$fdir = dirname($f); if( $fdir != '.' ) continue;
			$fdir_full = WP_CONTENT_DIR .'/themes/'.$f;
			if( !file_exists( $fdir_full ) ){
				rename( $temp_dir.'wp-content/themes/'.$f, $fdir_full);
			}			
		}
		
		// Upload folder
		if( $meta['uploads']){
			$upload_dir = wp_upload_dir(); $uploads = $upload_dir['basedir']; $upload_temp = $temp_dir.'wp-content/uploads';
			if( file_exists($uploads) )unlink($uploads);
			rename( $upload_temp, $uploads);
		}
		
		//wp-content files
		foreach((array)$meta['wp-files'] as $f){
			
		}
		
		$response['success'] = false; $response['message'] = "Restore Successfull";
		return $response;
	}
	
	
	/**
     *  Delete an exported file based on the file slug
     */
	protected static function delete(){
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmcmg_delete_action' ) ) {
            die( 'Cheating...' );
        }
		$file = $_REQUEST['id']; 
		$response = self::_delete( array($file) );
		
		$bid = cmc_migrate::$url_blog_id;
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
			$f_path = CMCMG_EXPORT_DIR . self::get_current_blog_id() .'/'.$file; unlink($f_path);	
		}
		
		do_action('cmcmg_delete', $files );
		
		$response['success'] = true; $response['message'] = 'Deleted Successfully';
		return $response;
	}
	
	/**
     *  Import a migrated site
     */
	protected static function import(){
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmcmg_import_action' ) ) {
            die( 'Cheating...' );
        }
		$file = $_FILES['cmcmg_file_import']; if( empty($file) )return;
		
		$response = self::_import( $file['tmp_name'], $file['name'] );
		
		$bid = cmc_migrate::$url_blog_id;	
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
		
		$export_dir = CMCMG_EXPORT_DIR . get_current_blog_id() .'/';
		if( !file_exists( $export_dir ) )  mkdir( $export_dir, 0777, true );
		
		$file_new = $export_dir . $name;
		$result = rename($file, $file_new);
		
		do_action('cmcmg_import', $file_new);
		
		$response['success'] = true; $response['message'] = 'Import Successful';
		return $response;
	}

	/**
     *  Import a migrated site remotely	
     */
	protected static function remote_import(){
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmcmg_delete_action' ) ) {
            die( 'Cheating...' );
        }
		
		$file = $_REQUEST['id'];  
		self::_remote_import( $file );
		
		//$response = self::_delete( array($file) );
		wp_redirect( '?page=cmcmg' );				
		exit;
	}	
	
	/**
     *  Privately Import a migrated site remotely
	 *
	 *	@param string $file	file name of migration to import			
     */
	private static function _remote_import( $file ){
		if( empty($file) )return; $base = dirname($file); if( $base != '.' ) return;
		$url = self::get_setting('remote_migration_url');
		$token = self::get_setting('remote_migration_token'); $data = '';
		
		$source = add_query_arg( array('cmcmg'=>'migration_file', 'token'=>$token, 'id'=>$file, 'XDEBUG_SESSION_START'=>'' ), $url );		
		$response = wp_remote_get( $source, array() ); 
		if( is_array($response) ) {
			$header = $response['headers']; // array of http header lines
			$data = $response['body']; // use the content
		}

		$export_dir = CMCMG_EXPORT_DIR . get_current_blog_id() .'/';
		if( !file_exists( $export_dir ) )  mkdir( $export_dir, 0777, true );		
		$destination = $export_dir . $file;
		$f = fopen($destination, "w+");
		fputs($f, $data);
		fclose($f);
		
		do_action('cmcmg_remote_import', $destination);		
	}
	
	/**
     *  Export a wordpress installation based on plugins, themes, wp-content files, media and database
     */
	protected static function export(){
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );                     
        }
        if ( !isset( $nonce ) and ! wp_verify_nonce( $nonce, 'cmcmg_export_action' ) ) {
            die( 'Cheating...' );
        }
		global $wpdb;
		$ini =  ini_get( 'max_input_vars' );
		$model = array();
		$model['tables'] = empty($_POST['tables'])? array() : $_POST['tables'];
		$model['themes'] = empty($_POST['themes'])? array() : $_POST['themes'];
		$model['plugins'] = empty($_POST['plugins'])? array() : $_POST['plugins'];
		$model['wp-content-files'] = empty($_POST['wp-content-files'])? array() : $_POST['wp-content-files'];
		$model['description'] = sanitize_text_field($_POST['description']);
		//echo "<pre>";
		//print_r($model['tables']);
		//echo  "</pre>";
		//return;
		$response = self::_export($model);
	}
	
	/**
     *  Internally import a wordpress installation based on plugins, themes, wp-content files, media and database
     */
	private static function _export( $model ){
		global $wpdb; $response = array();
		
		//-------- meta -------------------
		$meta = array('name' => get_bloginfo('name'), 'datetime_start'=> date('Y-m-d H:i:s'));	
		$meta['table_prefix'] = $wpdb->prefix; $meta['siteurl'] = get_bloginfo('siteurl'); $meta['homeurl'] = get_bloginfo('home');
		$meta['blog_description'] = get_bloginfo('description'); $meta['admin_email'] = get_option('admin_email');
		$meta['from_multisite'] = is_multisite(); $meta['is_network'] = $_REQUEST['blog_id'] == -1; $meta['wp_version'] = get_bloginfo('version');
		
		//--------- Export dir -----------------		
		$export_dir = CMCMG_EXPORT_DIR . self::get_current_blog_id() .'/';
		if( !file_exists( CMCMG_EXPORT_DIR.'/index.php' ) )  file_put_contents( CMCMG_EXPORT_DIR.'index.php', "" );
		
		$param = array('model'=> &$model, 'meta'=> &$meta, 'export_dir'=> &$export_dir);
		$param = apply_filters('cmcmg_export_params', $param );
		
		if( $param === false ) return;
		
		if( !file_exists( $export_dir ) )  mkdir( $export_dir, 0777, true );
		if( !file_exists( $export_dir.'index.php' ) )  file_put_contents( $export_dir.'index.php', "" );
		$dump_file = $export_dir."dump.sql"; $user_file = $export_dir."users.sql";	$log_file = $export_dir."log.txt";
		
		//---------------- Create sql dump file ----------------------
		if( !empty($model['tables']) ){
			require_once( CMCMG_DIR_INCLUDE.'class-cmc-migrate-sql.php' );	
			$user_tables_array = array( $wpdb->usermeta, $wpdb->users);
			$tables = implode(' ', (array)$model['tables']); $user_tables = implode(' ', (array)$user_tables_array); 
			
			self::log_write( "Creating dump.sql file", $log_file );
			$host = explode(':', DB_HOST);
			if( false ){				
				$cmd = 'bin/mysqldump --host='.$host[0].' --user='.DB_USER.' --password='.DB_PASSWORD.' '.DB_NAME." $tables > $dump_file";
				cmc_migrate_sql::mysql_cmd( $cmd );
				$meta['mysql_dump_type'] = 'mysql';
			}else{
				cmc_migrate_sql::phpsql_dump( $model['tables'], $dump_file );
				$meta['mysql_dump_type'] = 'php';
			}
			self::log_write( "Creating users.sql file", $log_file );
			if( false ){
				$cmd = 'bin/mysqldump --host='.$host[0].' --user='.DB_USER.' --password='.DB_PASSWORD.' '.DB_NAME." $user_tables > $user_file";
				cmc_migrate_sql::mysql_cmd( $cmd );
				$meta['mysql_user_type'] = 'mysql';
			}else{
				cmc_migrate_sql::phpsql_dump( $user_tables_array, $user_file );
				$meta['mysql_user_type'] = 'php';
			}
			self::log_write( "Created users.sql file", $log_file );
			$meta['tables'] = $model['tables'];
			$meta['users_table'] = $wpdb->users; $meta['usermeta_table'] = $wpdb->usermeta;
		}

		//------------  Create Zip file  ----------------
		$zip_file = $export_dir.$meta['name'].'_'.date('YmdHis').'.zip'; 
		if( file_exists($zip_file) ) unlink($zip_file); $zip = new ZipArchive(); $zip->open( $zip_file, ZipArchive::CREATE );	
		self::log_write( "Created zip_file: '$zip_file file'", $log_file );
		set_time_limit ( 200 );
		
		//--------- Add Dump file -----------------
		$zip->addfile( $dump_file, 'dump.sql' );
		$zip->addfile( $user_file, 'users.sql' );	
		self::log_write( "Added dump.sql and users.sql to zip file", $log_file );		
		
		//------------ Add Uploads -----------------------
		$upload_dir = wp_upload_dir();
		$sites_dir = self::get_current_blog_id() > 0 ? $upload_dir['basedir'].'/sites':'';
		if( file_exists( $sites_dir ) ){
			self::zip_dir( $upload_dir['basedir'], $zip, $upload_dir['basedir'], 'wp-content/uploads', array($sites_dir));
		}else{
			self::zip_dir( $upload_dir['basedir'], $zip, $upload_dir['basedir'], 'wp-content/uploads');
		}		
		$meta['uploads'] = true;
		
		//------------- Add Plugins ---------------------
		$plugins = array(); $plugins_net = array();
		if( self::get_current_blog_id() > 0 ){
			$plugins = (array)get_option('active_plugins', array()); 
		}		
		
		if ( is_multisite() ) {
			$plugins_net = (array) get_site_option( 'active_sitewide_plugins', array() );
			$plugins_net = array_keys( $plugins_net );
		}
		$plugins = array_merge( (array)$model['plugins'], $plugins, $plugins_net );
		
		if( is_array($plugins) && !empty( $plugins ) ){
			self::log_write( "Adding Plugin files to zip", $log_file );
			foreach( $plugins as $f ){
				$fdir = dirname($f);
				if( $fdir == '.' || empty( $fdir ) ){
					$fdir_full = WP_PLUGIN_DIR .'/'.$f;
					if( !file_exists($fdir_full) ) continue;
					$meta['plugins'][] = $f;
					$zip->addfile( $fdir_full, 'wp-content/plugins/'.$f);
				}else{
					$fdir_full = WP_PLUGIN_DIR .'/'.$fdir;
					if( !file_exists($fdir_full) ) continue;
					$meta['plugins'][] = $fdir;
					self::zip_dir( $fdir_full, $zip, WP_PLUGIN_DIR, 'wp-content/plugins');	
				}
			}	
			$zip->addfile( WP_PLUGIN_DIR.'/index.php', 'wp-content/plugins/index.php');
			self::log_write( "Finished Adding Plugin files to zip", $log_file );
		}
		
		//------------- Add Themes --------------------
		$themes = array();
		if( self::get_current_blog_id() > 0 ){
			$meta['active_theme'] = get_option( 'stylesheet' );
			$themes = array( $meta['active_theme'] );
			$theme = wp_get_theme();
			if( !empty($theme->template) && $theme->template != $meta['active_theme'] ){
				$themes[] = $theme->template; $meta['theme_parent'] = $theme->Template;
			}
		}

		$themes = array_merge( (array)$model['themes'], $themes);
		foreach( $themes as $f){
			self::log_write( "Adding Theme files to zip", $log_file );
			$fdir = WP_CONTENT_DIR .'/themes';
			$fdir_full =  $fdir . "/" .$f;
			if( !file_exists($fdir_full) ) continue;
			self::zip_dir( $fdir_full, $zip, $fdir, 'wp-content/themes');	
			
			$meta['themes'][] = $f;
			self::log_write( "Finished Adding Theme files to zip", $log_file );
		}
		$zip->addfile( WP_CONTENT_DIR .'/themes/index.php', 'wp-content/themes/index.php');		
	
		//----------- Add Other wp-content files ---------------
		if( is_array($model['wp-content-files']) && !empty( $model['wp-content-files'] ) ){
			self::log_write( "Adding wp-conten-files to zip", $log_file );
			foreach( $model['wp-content-files'] as $f ){
				$fdir_full = WP_CONTENT_DIR .'/' . $f;
				if( !file_exists($fdir_full) ) continue;
				if( is_file($fdir_full) ) $zip->addfile( $fdir_full, 'wp-content/' . $f);
				else self::zip_dir( $fdir_full, $zip, WP_CONTENT_DIR, 'wp-content');
				
				$meta['wp-content-files'][] = $f;
			}	
			self::log_write( "Finished Adding wp-content-iles to zip", $log_file );
		}
		$zip->addfile( WP_CONTENT_DIR.'/index.php', 'wp-content/index.php');
			
		$meta['description'] = $model['description'];
		$meta['datetime'] = date('Y-m-d H:i:s');
		$meta_json = json_encode($meta);
		$zip->addFromString('meta.json', $meta_json );
		self::log_write( "Finished Export file:", $log_file );
		$zip->addfile( $log_file, 'log.txt' );
		$zip->close();	
		@unlink( $dump_file ); @unlink( $user_file ); @unlink( $log_file );
		
		do_action('cmcmg_export', $param);
		
		$response['success'] =  true; $response['message'] =  'export successful';
		return $response;
	}
	
	/**
     *  Add folder to zip file recursively
     */
	public static function zip_dir($dir, &$zip, $relative, $pre_dir = '', $exclude = array() ){
		$dir = rtrim($dir, '/\\');
		foreach(glob($dir.'/*') as $file){
			if( is_dir($file) ){
				if( in_array($file, $exclude) ) continue;
				self::zip_dir($file, $zip, $relative, $pre_dir, $exclude );
			}
			else{
				$f = preg_replace('/^' . preg_quote($relative, '/') . '/', '', $file);
				$f = $pre_dir . $f; $zip->addfile($file, $f);
			}
		}
	}
		
	/**
     *  Save Settings of cmc migrate
     */
	protected static function delete_blog( $blog_id ){	
		$export_dir = CMCMG_EXPORT_DIR . $blog_id;
		self::recursiveDelete( $export_dir );
	}
	
	
	/**
     *  Save Settings of cmc migrate
     */
	protected static function save_settings(){
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
		if( !self::get_setting('version') ){
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
		
		do_action('cmcmg_settings_save', $data);
		
		$response['success'] = true; $response['message'] = "Saved Successfully";
		return $response;
	}
	
	/**
     *  Save Network Settings of cmc migrate
	 * 	@since 0.0.2
     */
	protected static function save_net_settings(){
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
		
		$data = apply_filters( 'cmcmg_network_settings_data_save', $data);
        if( $data === false )return false;

        update_site_option(CMCMG_NET_SETTINGS, $data);
		
		do_action('cmcmg_network_settings_save', $data);
		
		$response['success'] = true; $response['message'] = "Saved Successfully";
		return $response;
	}
	
	
	/**
	 * Recursively move files from one directory to another
	 * 
	 * @param String $src - Source of files being moved
	 * @param String $dest - Destination of files being moved
	 */
	public static function movedir($src, $dest){

		// If source is not a directory stop processing
		if(!is_dir($src)) return false;

		// If the destination directory does not exist create it
		if(!is_dir($dest)) { 
			if(!mkdir($dest)) {
				// If the destination directory could not be created stop processing
				return false;
			}    
		}

		// Open the source directory to read in files
		$i = new DirectoryIterator($src);
		foreach($i as $f) {
			if($f->isFile()) {
				rename($f->getRealPath(), "$dest/" . $f->getFilename());
			}else if(!$f->isDot() && $f->isDir()) {
				self::movedir($f->getRealPath(), "$dest/$f");
				@unlink($f->getRealPath());
			}
		}
		@rmdir($src);
	}
	
	/**
	 * 	Recursively delete files from a directory
	 * 
	 * 	@param String $str Directory path
	 */
	public static function recursiveDelete( $str ) {
		if (is_file($str)) {
			return @unlink($str);
		}
		elseif (is_dir($str)) {
			$scan = glob(rtrim($str,'/').'/*');
			foreach($scan as $index=>$path) {
				self::recursiveDelete($path);
			}
			return @rmdir($str);
		}
	}
	
	/**
     *  Get File sie
	 *
	 * 	@param string $path path of the file
     */
	public static function filesize($path){
		$size = filesize($path);
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
		$power = $size > 0 ? floor(log($size, 1024)) : 0;
		return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
	}
	
	/**
     *  Download file
	 *
	 *  @param binary $out content of file
     * 	@param string $fname Name of file when downloaded
	 *	@param string $type content type of the downloaded file default is 'application/octet-stream'
     */
    public static function output_file( $out, $fname, $type = 'application/octet-stream' ){
        $type = (empty($type))? 'application/octet-stream' : $type;	
        header('Content-Description: File Transfer');		
        header('Content-Type: '.$type);
        header('Content-Disposition: attachment; filename='.$fname);
        header('Expires: 0');		
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        echo $out;
        exit();				
    }
		
	/**
     *  Get Appropriate Blog Id
	 * 	@since 0.0.2
     * 
     * 	@param string $name name of the settings
     * 	@param string $default default value if name doesnt exist
     */
    public static function get_blog_id(){
        $bid = ( !empty( $_REQUEST['blog_id'] ) ) ? intval($_REQUEST['blog_id']) : get_current_blog_id();
		return $bid;
    }
	
	/**
     *  Get Appropriate Current Blog Id
     * 	@since 0.0.2
	 *
     * 	@param string $name name of the settings
     * 	@param string $default default value if name doesnt exist
     */
    public static function get_current_blog_id(){
        $bid = isset($_REQUEST['blog_id']) && $_REQUEST['blog_id'] < 1 ? intval($_REQUEST['blog_id']) : get_current_blog_id();
		return $bid;
    }
		
	/**
     *  Get current blog tables
	 * 	@since 0.0.2
     * 
     */
    public static function get_current_blog_tables(){
		global $wpdb; $pre = $wpdb->prefix; $prefix = str_replace( '_', '\_', $pre ); 
		$b_id = self::get_blog_id();//get_current_blog_id();
		if( is_main_site() && $b_id > 0  ){

		$sql = 	"SHOW TABLES FROM ". DB_NAME .
				" WHERE Tables_in_". DB_NAME .
				" LIKE '$prefix%' AND Tables_in_".DB_NAME.
				" NOT REGEXP 'wp_[0-9]+' ";	
		}else{
			$sql = "SHOW TABLES like '$prefix%'";
		}
		$tables = $wpdb->get_col($sql);
		return $tables;
	}
		
	 /**
     *  Get value of one cmc migrate Settings
     * 
     * 	@param string $name name of the settings
     * 	@param string $default default value if name doesnt exist
     */
    public static function get_setting( $name, $default = ""){
        global $cmcmg_settings_default;
        $opt = get_option( CMCMG_SETTINGS, $cmcmg_settings_default );
        return isset($opt[$name])? $opt[$name]: $default;
    }
	
	/**
     *  Get value of one cmc migrate Network Settings
	 * 	@since 0.0.2
     * 
     * 	@param string $name name of the settings
     * 	@param string $default default value if name doesnt exist
     */
    public static function get_net_setting( $name, $default = ""){
        global $cmcmg_network_settings_default;
        $opt = get_site_option( CMCMG_NET_SETTINGS, $cmcmg_network_settings_default );
        return isset($opt[$name])? $opt[$name]: $default;
    }
	
	/**
     *  Writes to log file
	 * 	@since 0.0.2
     * 
     * 	@param string $data log to write
     * 	@param string $file file address
     */
	public static function log_write( $log, $file ){
		$timestamp = date('Y M j H:i:s'); // RSR
		file_put_contents( $file, "$timestamp $log\n", FILE_APPEND); // RSR
	}
	
	/**
     *  Activation function runs on plugin activation
     */
    public static function plugin_activate( $network_wide ){		
		if( !get_option( CMCMG_SETTINGS ) ){
			global $cmcmg_settings_default;
			update_option( CMCMG_SETTINGS , $cmcmg_settings_default);
		}
		if( $network_wide && !get_site_option( CMCMG_NET_SETTINGS ) ){
			global $cmcmg_network_settings_default;
			update_site_option( CMCMG_NET_SETTINGS , $cmcmg_network_settings_default);
		}
    }
    
    /**
     *  Deactivation function runs on plugin deactivation
    */
    public static function plugin_deactivate( $network_wide ){

    }
	
	/**
     *  uninstall function runs on plugin deactivation
    */
    public static function plugin_uninstall( $network_wide ){
        global $wpdb;

        if( self::get_setting('del_opt_uninstall', false) ){
            delete_option( CMCMG_SETTINGS );
        }
		
		if( $network_wide && self::get_net_setting('del_opt_uninstall', false) ){
            delete_site_option( CMCMG_NET_SETTINGS );
        } 
		
		//if( !is_network() && self::get_setting('del_folder_uninstall', false) ){
        //    @unlink( CMCMG_WP_CONTENT_DIR );
        //} 
    }
       
	/**
     *  Get Current Url
	 *	
	 *	@param	string|array url parameters to add to current url
     */
    public static function current_url( $args = false ){
        $url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
		if( empty( $args ) )
			return $url;
		
		if( is_string($args) ) parse_str( $args, $args );
		return add_query_arg( $args, $url );	
    }
	
	/**
     * 	Include required core files used in admin and on the frontend.
     */
    protected static function includes(){
        require_once( CMCMG_DIR_INCLUDE ."default_values.php");
		require_once( CMCMG_DIR_INCLUDE ."functions.php");
    }
		
	/**
     * 	Define cmc_migrate Constants.
     */
    protected static function constants(){
        global $wpdb;
        define('CMCMG_VERSION', '0.0.2');
        define('CMCMG_FOLDER', basename( dirname( __FILE__ ) ).'/' );
		define('CMCMG_BASENAME', plugin_basename( __FILE__ ) );
        define('CMCMG_DIR', plugin_dir_path( __FILE__ ).'/' );
		define('CMCMG_DIR_INCLUDE', CMCMG_DIR . 'include/' );
        define('CMCMG_DIR_URL',  plugin_dir_url( __FILE__ ) );
		define('CMCMG_PAGE_DIR',  CMCMG_DIR.'page/' );
		define('CMCMG_PAGE_SECTION_DIR',  CMCMG_PAGE_DIR.'sections/' );
		define('CMCMG_JS_URL',  CMCMG_DIR_URL.'assets/js/' );
		define('CMCMG_CSS_URL',  CMCMG_DIR_URL.'assets/css/' );
		define('CMCMG_SETTINGS',  'cmc_migrate_settings' );	 	
		define('CMCMG_NET_SETTINGS',  'cmc_migrate_net_settings' );
		define('CMCMG_WP_CONTENT_DIR',  WP_CONTENT_DIR .'/cmc-migrate/' );
		define('CMCMG_WP_CONTENT_URL',  WP_CONTENT_URL .'/cmc-migrate/' );		
		define('CMCMG_EXPORT_DIR',  CMCMG_WP_CONTENT_DIR."export/" );
		define('CMCMG_EXPORT_URL',  CMCMG_WP_CONTENT_URL."export/" );
		define('CMCMG_IMPORT_DIR',  CMCMG_WP_CONTENT_DIR."import/" );
		define('CMCMG_IMPORT_URL',  CMCMG_WP_CONTENT_URL."import/" );
		define('CMCMG_LOG_DIR',  CMCMG_IMPORT_DIR."log/" );
		define('CMCMG_TEMP_DIR',  CMCMG_WP_CONTENT_DIR."temp/" );
		define('CMCMG_DIR_SEPARATE', DIRECTORY_SEPARATOR);
    }
}

/**
 * Main instance of cmc_migrate.
 *
 * Returns the main instance of cmcmg to prevent the need to use globals.
 *
 * @return cmc_migrate
 */
function cmcmg() {
	return cmc_migrate::instance();
}
cmcmg();
?>
