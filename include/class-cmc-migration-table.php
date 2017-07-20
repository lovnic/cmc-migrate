<?php
/*
package: cmc_migration
file: admin/migration_table.php 
*/

if(!defined('ABSPATH')) { 
    header('HTTP/1.0 403 Forbidden');
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class cmc_migration_List extends WP_List_Table {
   
   private static $migrations = array();
   
   private static $msg = array('success'=>array(), 'error'=>array());
   
   private static $migrations_count_all = 0;
   
   public function __construct( $str = array() ) {	
		if( is_string($str) )return;
        parent::__construct( [
            'singular' => __( 'Migration', 'cmcmg' ), //singular name of the listed records
            'plural'   => __( 'Migrations', 'cmcmg' ), //plural name of the listed records
            'ajax'     => false, //should this table support ajax?
        ] );
    }
    
    public static function get_migrations( $per_page = 5, $page_number = 1 ) {
		$list = array(); $items = array();
		if( $_REQUEST['location'] == 'remote' ){
			$url = cmcmg::get_setting('remote_migration_url'); if( empty($url) ) return array();
			$url = rtrim( $url, '/\\').'/wp-admin/admin-ajax.php';
			$token = cmcmg::get_setting('remote_migration_token');
			$source = add_query_arg( array('paged'=>empty($_REQUEST['paged'])? 1: $_REQUEST['paged']), $url );
			//$result = file_get_contents( $url, null, null ); $result = json_decode( $result, true );
			$remote_post = wp_remote_post( $source, array(
				'method' => 'POST', 'timeout' => 45, 'redirection' => 5,
				'httpversion' => '1.0', 'blocking' => true, 'headers' => array(),
				'body' => array('cmcmg'=>'migrations', 'token'=>$token)
				)  
			); 
			if ( is_wp_error( $remote_post ) ) {
				self::$msg['error'][] = $remote_post->get_error_message();
			} else {
				$result = json_decode( $remote_post['body'], true );
			}
			
			if( is_array($result) && $result['success'] ){
				self::$migrations = $result['data']; self::$migrations_count_all = $result['count_all'];
			}
		}else{
			$bid = cmcmg::get_current_blog_id(); //get_current_blog_id();
			$export_dir = CMCMG_EXPORT_DIR.$bid; $migrations = array();
			$files = glob($export_dir.'/*.zip'); self::$migrations_count_all = count($files);
			$all = array_chunk($files, $per_page ); $page = $page_number - 1;
			$current_page_files = (array)$all[$page];
			
			foreach( $current_page_files as $file ){
				$item = array();
				$meta = @file_get_contents('zip://'.$file.'#meta.json');	 $meta = json_decode($meta, true);	
				$item['filename'] = basename($file); $item['site'] = $meta['name'];			
				$item['filesize'] = cmcmg::filesize($file);
				
				$pfiles = '<p><b>Plugins:</b> '. implode(', ', (array)$meta['plugins']). '</p>';
				$pfiles .= "\n<p><b>Themes:</b> ". implode(', ', (array)$meta['themes']). '</p>';
				$pfiles .= "\n<p><b>Wp-Content:</b> ". implode(', ', (array)$meta['wp-content-files']). '</p>';
				$item['files'] = $pfiles;				
				
				$item['plugins'] = $meta['plugins'];
				$item['themes'] = $meta['themes'];
				$item['wp-content-files'] = $meta['wp-content-files'];
				
				$item['date'] = $meta['datetime'];				
				$migrations[] = $item;
			}	
			
			self::$migrations = $migrations;
		}	

		return self::$migrations;
    }
   
    public static function record_count() {
		return self::$migrations_count_all;
    }

    public function cmcmg_get_counts(){
		return self::$migrations_count_all;
    }
	
    public function no_items() {
        _e( 'No Migrations avaliable.', 'cmchk' );
    }
    
    function column_filename( $item ) {
        $nonce = wp_create_nonce( 'cmcmg_delete_action' ); $fname = $item[filename];
        $title = "<strong>$fname</strong> ($item[filesize])";
		$fname_url = urlencode( $fname );
		$bid = cmcmg::$url_blog_id;
		
        $actions = []; 
		if( $_REQUEST['location'] == 'remote'){
			$actions['import'] = "<a href='?page=cmcmg&cmcmg_action=remote_import$bid&XDEBUG_SESSION_START&id=$fname_url&_wpnonce=$nonce' >Import</a></span>";
		}else{
			$actions['delete'] =  "<a onclick='return cmcmg.delete_export();' href='?page=cmcmg$bid&cmcmg_action=delete&XDEBUG_SESSION_START&id=$fname_url&_wpnonce=$nonce' style='color:red;' >Delete</a></span>";
			$actions['download'] = "<a href='?page=cmcmg$bid&cmcmg_action=download&XDEBUG_SESSION_START&id=$fname_url&_wpnonce=$nonce' target='_blank' > Download</a>";
			//$actions['restore'] = "<a onclick='return cmcmg.restore();' href='?page=cmcmg&cmcmg_action=restore&XDEBUG_SESSION_START&id=$fname_url&_wpnonce=$nonce' target='_blank' style='color:green;' > Restore</a></span>";
			//$url = CMCMG_EXPORT_URL . cmcmg::get_current_blog_id().'/'.$fname_url;
			//$actions['download'] = "<a href='$url' target='_blank' > Download</a>";
			$actions['restore'] = "<a href='?page=cmcmg$bid&tab=restore&id=$fname_url' style='color:green;' > Restore</a></span>";
		}
		
        $actions = apply_filters('cmcmg_table_actions', $actions);
        return $title . $this->row_actions( $actions );
    }
    
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'safe_mode':
                return $item[ $column_name ]? 'Yes':'No';
            default:
                return $item[ $column_name ]; // print_r( $item, true ); //Show the whole array for troubleshooting purposes
        }
    }
    
    function column_cb( $item ) {
        return sprintf(
          '<input type="checkbox" name="bulk-items[]" value="%s" />', basename($item['filename'])
        );
    }
    
    function get_columns() {
        $columns = [
            'cb'      => '<input type="checkbox" />',
            'filename'    => __( 'File Name', 'cmcmg' ),
            'site'  => __('Site Name', 'cmcmg'),
			'files'  => __('Files', 'cmcmg'),
            'date'=> __('Created', 'cmcmg'),            
        ];

        return $columns;
    }
    
    public function get_sortable_columns() {
        $sortable_columns = array(
          'site' => array( 'site', true ),
          'date' => array( 'date', true ),
        );

        return $sortable_columns;
    }
    
    public function get_bulk_actions() {
        $actions = [];
        if( $_REQUEST['location'] == 'remote' ){
		
        }else{
            $actions['bulk-trash'] = 'Trash'; 
        } 
        //return $actions;
		return array();
    }
    
    protected function get_views(){
        //$count = $this->cmchk_get_counts();
		$bid = cmcmg::$url_blog_id;
        $remote = $_REQUEST['location'] == 'remote'? 'current':'';
        $local = empty($_REQUEST['location'])? 'current':'';
		$remote_url = $_REQUEST['location'] == 'remote'? ': '.cmcmg::get_setting('remote_migration_url'):'';
		
        $views = array();
		$views['local'] = "<a href='?page=cmcmg$bid&XDEBUG_SESSION_START' class='$local'>Local</a>";			
		$views['remote'] = "<a href='?page=cmcmg$bid&location=remote&XDEBUG_SESSION_START' class='$remote'>Remote $remote_url</a>";

		return $views;
    }
	
	public static function show_messages(){
		$return = '';
		foreach( self::$msg['success'] as $msg){
			$return .= sprintf("<div class='notice notice-success'><p>%s</p></div>\n", $msg);
		}		
		foreach( self::$msg['error'] as $msg){
			$return .= sprintf("<div class='notice notice-error'><p>%s</p></div>\n", $msg);
		}		
		return $return;
	}
	
    public function prepare_items(){
        $this->_column_headers = $this->get_column_info();

        /** Process bulk action */
        //$this->process_bulk_action();

        $per_page     = $this->get_items_per_page( 'migrations_per_page', 5 );
        $current_page = $this->get_pagenum();
		$this->items = self::get_migrations( $per_page, $current_page );
        $total_items  = self::record_count();
		
        $this->set_pagination_args( [
          'total_items' => $total_items, //WE have to calculate the total number of items
          'per_page'    => $per_page //WE have to determine how many items to show on a page
        ] );
        
        //$this->search_box( 'Search', 'cmchk_hook_search' );
		$this->cmc_footer();
    }
	
	public function remote_prepare_items(){
		$per_page = $this->get_items_per_page( 'migrations_per_page', 5 );
		$current_page = $this->get_pagenum();
		$result = self::get_migrations( $per_page, $current_page );
		$count_all = self::record_count();
		return array( 'success'=>true, 'data'=>$result, 'count_all'=>$count_all);
	}
    
    public function process_bulk_action() {
		
	}
    
	public function cmc_footer(){ ?>
		<script>
			var cmcmg = cmcmg || {};
			(function($, cmcmg){				
				cmcmg.delete_export = function (){
					if( confirm("Do Yo Want to Delete The Item") ){
						return true
					}
					return false;
				}
			
			})(jQuery, cmcmg);
		</script>
	<?php }
}