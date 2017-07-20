<?php
/**
Plugin Name: cmc-migrate
Description: Migrate sites from installation to another
Version: 0.0.4
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
		cmcmg::$blog_id = cmcmg::get_blog_id();
		cmcmg::$url_blog_id = is_network_admin()? '&blog_id='.cmcmg::get_blog_id():'';
		add_action( 'plugins_loaded', array( $this, 'init'));		
		register_activation_hook( __FILE__, array( __CLASS__, 'plugin_activate' ) );
        register_deactivation_hook( __FILE__, array( __CLASS__, 'plugin_deactivate' ) ); 
		if( is_multisite() ){
			add_action( 'wpmu_new_blog', array( $this, 'create_blog' ), 10, 6 );
			add_action( 'delete_blog', array( $this, 'delete_blog' ) ); 			
		}	
	}
		
	/**
     * Init cmc-migrage when WordPress Initialises
     */
	public function init(){
		do_action( 'before_cmcmg_init' );
		
		if( cmcmg::is_network_activated() && !is_network_admin() ){
			$allowed_sites = (array)cmcmg::get_net_setting('allowed_sites', array());
			$allowed_sites = (array)apply_filters('cmcmg_allowed_sites', $allowed_sites );
			if( !in_array( get_current_blog_id(), $allowed_sites ) ){
				return;
			}
		}
		
		if( cmcmg::is_network_activated() && is_network_admin() && $_REQUEST['blog_id'] != -1 ){
			switch_to_blog( cmcmg::$blog_id );
		}
		if( !empty( $_REQUEST['cmcmg_action'] ) || !empty($_REQUEST['cmcmg']) ){	
			require_once( CMCMG_DIR_INCLUDE ."class-cmc-migrate-action.php");
		}
		if( is_admin() ){	
			if( cmcmg::is_user_allowed() ){	
				if( !empty( $_REQUEST['cmcmg_action'] ) ){					
					switch( $_REQUEST['cmcmg_action'] ){
						case 'import': cmcmg_action::import(); break;
						//case 'remote_import': cmcmg_action::remote_import(); break;			
						case 'download': cmcmg_action::download(); break;	
						case 'delete': cmcmg_action::delete(); break;	
						case 'save_settings': cmcmg_action::save_settings(); break;	
					}
					if( is_super_admin() && cmcmg::is_network_activated() && is_network_admin() ){
						switch( $_REQUEST['cmcmg_action'] ){
							case 'save_net_settings': cmcmg_action::save_net_settings(); break;	
						}
					}
				}				
				
				add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		
				if( is_super_admin() && is_plugin_active_for_network( CMCMG_BASENAME ) && is_network_admin() ){
					self::network_admin_menu();
				}

				add_filter( 'set-screen-option', function($status, $option, $value ){return $value;}, 10, 3 );
			}

			if( !empty($_REQUEST['cmcmg_msg']) ){
				$admin_notice = ( is_network_admin()? 'network_':'').'admin_notices';
				add_action( $admin_notice, function(){
					$status = $_REQUEST['cmcmg_msg_success'] ? 'success':'error'; 
					printf( "<div class='notice notice-%s'><p>%s</p></div>", $status, $_REQUEST['cmcmg_msg'] );
				});
			}
			
			if( defined( 'DOING_AJAX' ) && DOING_AJAX ){
				if( $_REQUEST['cmcmg'] == 'migrations' ){
					cmcmg_action::remote_migration_list();
				}
				if( $_REQUEST['cmcmg'] == 'migration_file1' ){
					cmcmg_action::remote_import_migration_file();	
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
        add_action( "load-$hook", array( __CLASS__, "menu_load"));		
    }
	
	/**
     * On Admin Menu load this function run
	 * 	@since 0.0.2
     */
	private static function network_admin_menu(){
		add_action( 'network_admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_filter( 'cmcmg_admin_page_menu', function( $menu ){
			$bid = cmcmg::$url_blog_id;
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
     *  Fires when blog is deleted
     */
	protected static function delete_blog( $blog_id ){	
		$export_dir = CMCMG_EXPORT_DIR . $blog_id;
		self::recursiveDelete( $export_dir );
	} 
	
	/**
     *  Fires when new blog is created
     */
	protected static function create_blog( $blog_id ){	
		switch_to_blog( $blog_id );
		self::plugin_activate_single( false );
		restore_current_blog();
	}
	
	/**
     *  Activation function runs on plugin activation
     */
    public static function plugin_activate( $network_wide ){		
		if( is_multisite() && $network_wide ){
			$sites = get_sites();
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				self::plugin_activate_single( $network_wide );
			}
			restore_current_blog();
			if( $network_wide && !get_site_option( CMCMG_NET_SETTINGS ) ){
				global $cmcmg_network_settings_default;
				update_site_option( CMCMG_NET_SETTINGS , $cmcmg_network_settings_default);
			}
		}else{
			self::plugin_activate_single( $network_wide );
		}
    }
	
	/**
     *  Activation function runs on plugin activation for single site
     */
    public static function plugin_activate_single( $network_wide ){		
		if( !get_option( CMCMG_SETTINGS ) ){
			global $cmcmg_settings_default;
			update_option( CMCMG_SETTINGS , $cmcmg_settings_default);
		}
		if( !file_exists( CMCMG_EXPORT_DIR ) )  mkdir( CMCMG_EXPORT_DIR, 0777, true );
		if( !file_exists( CMCMG_EXPORT_DIR.'/.htaccess' ) )  file_put_contents( CMCMG_EXPORT_DIR.'/.htaccess', "deny from all" );
    }
    
    /**
     *  Deactivation function runs on plugin deactivation
     */
    public static function plugin_deactivate( $network_wide ){

    }
       
	/**
     * 	Include required core files used in admin and on the frontend.
     */
    protected static function includes(){
		require_once( CMCMG_DIR_INCLUDE ."class-cmc-migrate-functions.php");
        require_once( CMCMG_DIR_INCLUDE ."default_values.php");	
    }
		
	/**
     * 	Define cmc_migrate Constants.
     */
    protected static function constants(){
        global $wpdb;
        define('CMCMG_VERSION', '0.0.3');
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
function cmcmgc() {
	return cmc_migrate::instance();
}
cmcmgc();
?>