<?php 
/*
package: cmc_migrate
file: admin/settings.php 
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
?>
<div class="cmcmg_section_settings_inner">
	<style>
		#cmcmg_section_settings_form .cmcmg-help-tip{
			float:right;
		}
	</style>
    <h3> <?php echo __('All Settings', 'cmcmg'); ?></h3>
    <form id="cmcmg_section_settings_form" method="post" action="?page=cmcmg&tab=settings<?php echo $bid; ?>" >
        <?php wp_nonce_field( 'cmcmg-settings-save-nonce','_wpnonce', true, true ); ?>
		<?php if( cmcmg::$dev_debug ){ ?>
        <input name="XDEBUG_SESSION_START" type="hidden" />
		<?php } ?>
        <table id="cmcmg_section_settings_table" class="cmcmg_section_settings_table form-table">
            <tr>
                <th>
                    <?php echo __('Version', 'cmcmg'); ?>
					<span class="cmcmg-help-tip" data-tip="<?php echo __( "Currently Installed Version" , 'cmcmg'); ?>">
						<i class="fa fa-question-circle"></i>
					</span>
                </th>
                <td>		
					<label>
						 <?php echo CMCMG_VERSION; ?>
					</label>
                </td>
            </tr>	
			<tr>
                <th>
                    <?php echo __('Remote Connection In', 'cmcmg'); ?>
					<span class="cmcmg-help-tip" data-tip="<?php echo __( "Allow Remote Connection To Migration List And set Security Token" , 'cmcmg'); ?>">
						<i class="fa fa-question-circle"></i>
					</span>
                </th>
                <td>				
					<label>
						 <?php echo __('Allow', 'cmcmg'); ?>
						 <input name="allow_remote_connection" type="checkbox" <?php checked( $model['allow_remote_connection'], 1); ?> />
					</label>
					<label>
						 <?php echo __('Security token', 'cmcmg'); ?>
						 <input name="remote_connection_token" type="text" value="<?php echo $model['remote_connection_token']; ?>" />
					</label>
                </td>
            </tr>			
			<tr>
                <th>
                    <?php echo __('Remote Migration Url', 'cmcmg'); ?>
					<span class="cmcmg-help-tip" data-tip="<?php echo __( "Enter the url of remote migrations and set Security Token" , 'cmcmg'); ?>">
						<i class="fa fa-question-circle"></i>
					</span>
                </th>
                <td>	
					<input name="remote_migration_url" type="text" class="widefat" style="width:50%" value="<?php echo $model['remote_migration_url']; ?>" placeholder="eg 'www.test.com/wp-admin/admin-ajax.php'" />
					<span class="cmcmg-help-tip" style="float:none;" data-tip="<?php echo __( "Site url as in options e.g. www.test.com" , 'cmcmg'); ?>">
						<i class="fa fa-question-circle"></i>
					</span>
					<label>Token</label>
					<input name="remote_migration_token" type="text" class="" value="<?php echo $model['remote_migration_token']; ?>" />
                </td>
            </tr>
            <tr>
                <th>
                    <?php echo __('Delete on Uninstall', 'cmcmg'); ?>
					<span class="cmcmg-help-tip" data-tip="<?php echo __( "On Deactivation of Plugin Select items to delete" , 'cmcmg'); ?>">
						<i class="fa fa-question-circle"></i>
					</span>
                </th>
                <td>				
					<label>
						<?php echo __('Settings', 'cmcmg'); ?>
						<input name="del_opt_uninstall" type="checkbox" <?php checked( $model['del_opt_uninstall'], 1); ?> />
					</label>
					<label>
						<?php echo __('CMC Migrate folder', 'cmcmg'); ?>
						<input name="del_folder_uninstall" type="checkbox" <?php checked( $model['del_folder_uninstall'], 1); ?> />
					</label>
                </td>
            </tr>
            <tr>
                <th>
                    <?php echo __('Roles', 'cmcmg'); ?>
					<span class="cmcmg-help-tip" data-tip="<?php echo __("Enter role per line to allow usage of the system", 'cmcmg'); ?>">
						<i class="fa fa-question-circle"></i>
					</span>
                </th>
                <td>
                    <textarea name="allowed_users" class="widefat" style="min-height:150px;" title="One Role Per Line"><?php echo $model['allowed_users']; ?></textarea>
                </td>
            </tr>
        </table>
        <?php
            do_action('cmcmg_admin_page_settings_controls', $model);
        ?>
        <button type="submit" name="cmcmg_action" value="save_settings" class="button button-primary" title="<?php echo __('Save Settings', 'cmcmg'); ?>" ><?php echo __('Save Settings', 'cmcmg'); ?></button>
    </form>   
</div>