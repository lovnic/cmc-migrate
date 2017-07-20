<?php 
/**
 * CMC Migrate Uninstall
 *
 * @version     0.0.3
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
global $wpdb;

if( is_multisite() ){
	$sites = get_sites();
	foreach ( $sites as $site ){
		switch_to_blog( $site->blog_id );
		if( cmc_migrate::get_net_setting('del_opt_uninstall', false) ){
			delete_option( CMCMG_SETTINGS );
		}
	}
	restore_current_blog();

	if( cmc_migrate::get_net_setting('del_opt_uninstall', false) ){
		delete_site_option( CMCMG_NET_SETTINGS );
	} 
	if( cmc_migrate::get_net_setting('del_folder_uninstall', false) ){
	    @unlink( CMCMG_WP_CONTENT_DIR );
	}
}else{
	if( cmc_migrate::get_setting('del_opt_uninstall', false) ){
		delete_option( CMCMG_SETTINGS );
	}
	if( cmc_migrate::get_setting('del_folder_uninstall', false) ){
	    @unlink( CMCMG_WP_CONTENT_DIR );
	} 
}


//if( self::get_setting('del_folder_uninstall', false) ){
//    @unlink( CMCMG_WP_CONTENT_DIR );
//} 

?>
