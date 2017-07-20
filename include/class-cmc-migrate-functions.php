<?php

/**
 * functions
 *
 * @author 		Evans Edem Ladzagla
 * @file		functions.php
 * @category 	Core
 * @since 		0.0.3
 * @package 	cmc-migrate/include
 */

class cmcmg{
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
     * Development Debug
     *
     * @var bool
     */
    public static $dev_debug = true;

	
	/**
     * On Admin Menu load this function run
	 * 	@since 0.0.2
     */
	public static function menu_render( $slug, $section, $menus ){
		$menu = $menus[$slug];
		if( !empty($menu['sections']) ){
			cmcmg::menu_section_render( $slug, $section, $menus );
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
     * On Admin Menu load this function run
	 * 	@since 0.0.2
     */
	public static function url_message( $resp ){
		$msg = ''; $msg = urlencode($resp['message']);
		if( !empty( $resp['message'] ) ){
			$msg = "&cmcmg_msg=$msg".( $resp['success']?"&cmcmg_msg_success=1":""  );
		}
		return $msg;
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
     *  Writes to log file
	 * 	@since 0.0.2
     * 
     * 	@param string $data log to write
     * 	@param string $file file address
     */
	public static function log_write( $log, $file, $args = array() ){
		$timestamp = date('Y M j H:i:s'); $content = "$timestamp $log\n";
		file_put_contents( $file, $content, FILE_APPEND); // RSR		
		if( $args['echo'] ){
			//$content = str_replace("\n", "<br/>", $content);
			show_message($content);
		}
	}
	
	/**
     *  Get active theme of all blogs on the network
	 * 	@since 0.0.3
     * 
     */
    public static function get_network_blog_active_themes(){
		$sites = get_sites(); $themes = array();
		foreach( $sites as $site ){
			$style = get_blog_option( $site->blog_id, 'stylesheet');
			$themes[] = $style;	$theme = wp_get_theme( $style );
			if( !empty($theme->template) && $theme->template != $style ){
				$themes[] = $theme->template;
			}			
		}
		$themes = array_unique($themes);
		return $themes;
	}
	
	/**
     *  Get active plugins of all blogs on the network
	 * 	@since 0.0.3
     */
    public static function get_network_blog_active_plugins(){
		$sites = get_sites(); $plugins = array();
		foreach( $sites as $site ){
			$plugin = (array)get_blog_option($site->blog_id, 'active_plugins');
			$plugins = array_merge( $plugins,  $plugin );
		}
		$plugins = array_unique( $plugins );
		return $plugins;
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
		if( is_multisite() && is_main_site() && $b_id > 0  ){
			$sql = 	"SHOW TABLES FROM ". DB_NAME .
				" WHERE Tables_in_". DB_NAME .
				" LIKE '$prefix%' AND Tables_in_".DB_NAME.
				" NOT REGEXP 'wp_[0-9]+' ";	
		}else{
			$sql = "SHOW TABLES like '$prefix%'";
		}
		$tables = $wpdb->get_col($sql);		
		
		if( $b_id > 0 ){
			$ms_tables = $wpdb->tables('global');
			$tables =  array_diff( $tables, $ms_tables);	
		}			
		
		$tables = array_unique($tables);
		return $tables;
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
		return cmcmg::bytesize( $size );
	}
	
	/**
     *  Format bytes into appropriate size
	 *
	 * 	@param int $byte bytes in integers
     */
	public static function bytesize( $byte ){
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
		$power = $byte > 0 ? floor(log($byte, 1024)) : 0;
		return number_format($byte / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
	}
	
	/**
     *  Gets database size
	 *
	 * 	@param array $tables list to tables to get size in database
     */
	public static function database_size( $tables = array() ) {
		global $wpdb;
		$sql = 'SELECT sum(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = "' . DB_NAME . '"';
		if( !empty($tables) ){
			$tables = array_map( function( $item ){return "'$item'";}, $tables);
			$sql .= " AND TABLE_NAME IN(".implode(', ', $tables).")";
		}
		$size = $wpdb->get_var( $sql );
		return cmcmg::bytesize( $size );
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
     *  Download file
	 *
	 *  @param binary $out content of file
     * 	@param string $fname Name of file when downloaded
	 *	@param string $type content type of the downloaded file default is 'application/octet-stream'
     */
    public static function output_file( $out, $fname, $type = 'application/octet-stream', $fsize = '' ){
        $type = (empty($type))? 'application/octet-stream' : $type;	
        header('Content-Description: File Transfer');		
        header('Content-Type: '.$type);
		if( !empty($fsize) ){
			header("Content-Length: " .$fsize);
		}
        header('Content-Disposition: attachment; filename='.$fname);
        header('Expires: 0');		
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        echo $out;
        exit();				
    }	
}