<?php
/*
package: cmc_migrate
file: admin/migrations.php 
*/
if(!defined('ABSPATH')) { 
    header('HTTP/1.0 403 Forbidden');
    exit;
}
if( !cmcmg::is_user_allowed() ){
	echo 'You Do have sufficient Permission To View This Page'; 
	return;
}
$bid = cmcmg::$url_blog_id;

?>
<?php
	if( $_REQUEST['cmcmg_action'] == 'remote_import' ){		
		show_message( "<p>Remote Import Process Started</p>" );
		$response = cmcmg_action::remote_import( array('echo_log'=> true) ); 
		if( is_array($response) && !empty($response['message']) ){
			show_message( $response['message']."\n" );
		}
		show_message( "<p>Remote Import Process Completed. Thank you.</p>" );
	}else{
		?>
	<h3><?php echo __('All Migrations', 'cmcmg'); ?></h3>
		<?php
			cmc_migrate::$migrations->prepare_items();
			echo cmc_migrate::$migrations->show_messages();
			cmc_migrate::$migrations->views();
		?>
    <form method="post" action="?page=cmcmg">
		<?php if( cmcmg::$dev_debug ){ ?>
		<input type="hidden" name="XDEBUG_SESSION_START" />   
		<?php } ?>
        <?php cmc_migrate::$migrations->display(); ?>
    </form>
	
	<?php } ?>
	
	