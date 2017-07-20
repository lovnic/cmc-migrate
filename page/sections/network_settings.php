<?php 
/*
package: cmc_hook
file: admin/network_settings.php 
*/

if( !defined('ABSPATH') ){ 
    header('HTTP/1.0 403 Forbidden');
    exit;
}
if( !is_super_admin() ){
	echo 'You Do have sufficient Permission To View This Page'; 
	return;
}
global $cmcmg_network_settings_default;
$model = get_site_option(CMCMG_NET_SETTINGS, $cmcmg_network_settings_default);
if( !is_array( $model ) ){
	$model = $cmcmg_network_settings_default;
}
$bid = cmcmg::$url_blog_id;
?>
<div class="cmcmg_admin_page_section_network_settings_inner">
	<style>
		#cmcmg_admin_page_section_network_settings_form .cmcmg-help-tip{
			float:right;
		}
	</style>
    <h3> <?php echo __('All Settings', 'cmcmg'); ?></h3>
    <form id="cmcmg_admin_page_section_network_settings_form" method="post" action="?page=cmcmg&tab=net_sets<?php echo $bid; ?>" >
        <?php wp_nonce_field( 'cmcmg-network-settings-save-nonce','_wpnonce', true, true ); ?>
        <input name="XDEBUG_SESSION_START" type="hidden" />
        <table id="cmcmg_admin_page_section_network_settings_table" class="cmcmg_section_network_settings_table form-table">
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
                    <?php echo __('Allow Site', 'cmcmg'); ?>
					<span class="cmcmg-help-tip" data-tip="<?php echo __( "Select Sites to use CMC Migrate" , 'cmcmg'); ?>">
						<i class="fa fa-question-circle"></i>
					</span>
                </th>
                <td>				
					<?php $subsites = get_sites(); ?>
					<select name="allowed_sites[]" multiple="true" style="height:200px;">
						<?php
							$sites = (array)$model['allowed_sites'];
							foreach( $subsites as $subsite ){
								$subsite_id = get_object_vars($subsite)["blog_id"];
								$subsite_name = get_blog_details($subsite_id)->blogname;
								printf("<option value='%s' %s >%s</option>", $subsite_id, selected( in_array($subsite_id, $sites), true, false), $subsite_name." ($subsite_id) " );
							}
						?>
					</select>
                </td>
            </tr>
        </table>
        <?php
            do_action('cmcmg_admin_page_network_settings_controls', $model);
        ?>
        <button type="submit" name="cmcmg_action" value="save_net_settings" class="button button-primary" ><?php echo __('Submit', 'cmcmg'); ?></button>
    </form>   
</div>