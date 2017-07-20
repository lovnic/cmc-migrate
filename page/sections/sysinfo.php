<?php 
/*
package: cmc_migrate
file: admin/sysinfo.php 
*/

if( !defined('ABSPATH') ){ 
    header('HTTP/1.0 403 Forbidden');
    exit;
}
if( !cmcmg::is_user_allowed() ){
	echo 'You Do have sufficient Permission To View This Page'; 
	return;
}
global $cmcmg_settings_default;
$model = get_option(CMCMG_SETTINGS, $cmcmg_settings_default);
if( !is_array( $model ) ){
	$model = $cmcmg_settings_default;
}
$bid = cmcmg::$url_blog_id;
global $wpdb;
$upload_dir = wp_upload_dir(); $upload = $upload_dir['basedir'];

$size = array();
//$size['plugins'] = cmcmg::bytesize( recurse_dirsize( WP_PLUGIN_DIR ) );
//$size['themes'] = cmcmg::bytesize( recurse_dirsize( WP_CONTENT_DIR.'/themes' ) );
//$size['uploads'] = cmcmg::bytesize( recurse_dirsize( $upload ) );
?>
<div class="cmcmg_section_sysinfo_inner">
	<style>
		#cmcmg_section_sysinfo_form .cmcmg-help-tip{
			float:right;
		}
	</style>
    <h3> <?php echo __('System Information', 'cmcmg'); ?></h3>
    <form id="cmcmg_section_settings_form" method="post" action="?page=cmcmg&tab=sysinfo<?php echo $bid; ?>" >
        <?php wp_nonce_field( 'cmcmg-sysinfo-save-nonce','_wpnonce', true, true ); ?>
		<?php if( cmcmg::$dev_debug ){ ?>
        <input name="XDEBUG_SESSION_START" type="hidden" />
		<?php } ?>
        <table id="cmcmg_section_sysinfo_table" class="cmcmg_section_sysinfo_table cmcmg-admin-page-info-table form-table">
            <tr>
                <th>
                    <?php echo __('Version', 'cmcmg'); ?>
                </th>
                <td>		
					<label>
						<?php echo CMCMG_VERSION; ?>
					</label>
                </td>
            </tr>
			<tr>
                <th>
                    <?php echo __('Wordpress version', 'cmcmg'); ?>
                </th>
                <td>
					<label>
						<?php echo get_bloginfo('version'); ?>
					</label>
                </td>
            </tr>
			<tr>
                <th>
                    <?php echo __('Php version', 'cmcmg'); ?>
                </th>
                <td>		
					<label>
						<?php echo phpversion(); ?>
					</label>
                </td>
            </tr>			
			<tr>
                <th>
                    <?php echo __('Mysql version', 'cmcmg'); ?>
                </th>
                <td>		 
					<label>
						<?php echo $wpdb->db_version(); ?>
					</label>
                </td>
            </tr>			
			<tr>
                <th>
                    <?php echo __('Php time limit', 'cmcmg'); ?>
                </th>
                <td>		
					<label>
						<?php echo ini_get('max_execution_time'); ?>
					</label>
                </td>
            </tr> 
			<tr>
                <th>
                    <?php echo __('Php Memory', 'cmcmg'); ?>
                </th>
                <td>		
					<label>
						<?php echo ini_get('memory_limit'); ?>
					</label>
                </td>
            </tr>
			<tr>
                <th>
                    <?php echo __('Database Size', 'cmcmg'); ?>
                </th>
                <td>		
					<label>
						<?php echo cmcmg::database_size(); ?>
					</label>
                </td>
            </tr>
			<tr>
                <th>
                    <?php echo __('Root Directory', 'cmcmg'); ?>
                </th>
                <td>		
					<label>
						<?php echo ABSPATH; ?>
					</label>
                </td>
            </tr>
			<tr>
                <th>
                    <?php echo __('Content Directory', 'cmcmg'); ?>
                </th>
                <td>		
					<label>
						<?php echo WP_CONTENT_DIR; ?>
					</label>
                </td>
            </tr>
			<tr>
                <th>
                    <?php echo __('Plugins Directory', 'cmcmg'); ?>
                </th>
                <td>		
					<label>						
						<?php echo WP_PLUGIN_DIR; ?>
						<?php 
							echo !empty( $size['plugins'] )? " ($size[plugins])":'';					
						?>
					</label>
                </td>
            </tr>
			<tr>
                <th>
                    <?php echo __('Themes Directory', 'cmcmg'); ?>
                </th>
                <td>		
					<label>
						<?php echo WP_CONTENT_DIR.'/themes'; ?>
						<?php 
							echo !empty( $size['themes'] )? " ($size[themes])":"";							
						?>
					</label>
                </td>
            </tr>			
			<tr>
                <th>
                    <?php echo __('Uploads Directory', 'cmcmg'); ?>
                </th>
                <td>		
					<label>
						<?php echo $upload; ?>
						<?php 
							echo !empty( $size['uploads'] )? " ($size[uploads])":"";							
						?>
					</label>
                </td>
            </tr>
			<tr>
                <th>
                    <?php echo __('CMCMG Directory', 'cmcmg'); ?>
                </th>
                <td>		
					<label>
						<?php echo CMCMG_WP_CONTENT_DIR; ?>
					</label>
                </td>
            </tr>			
        </table>
        <?php
            do_action('cmcmg_admin_page_sysinfo_list');
        ?>
    </form>   
</div>