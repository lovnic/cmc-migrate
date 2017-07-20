<?php
/*
package: cmc_migrate
file: admin/settings.php 
*/
if(!defined('ABSPATH')) { 
    header('HTTP/1.0 403 Forbidden');
    exit;
}
if( !cmcmg::is_user_allowed() ){
	echo 'You Do have sufficient Permission To View This Page'; 
	return;
}
global $wpdb;
$bid = cmcmg::$url_blog_id;
$form_url = "?page=cmcmg&tab=export".$bid;
$form_url = apply_filters('cmcmg_admin_page_export_url', $form_url);

//----------  Tables ---------------
$pre = $wpdb->prefix; $b_id = cmcmg::get_blog_id();
$tables = cmcmg::get_current_blog_tables();

$sql = "SHOW VARIABLES LIKE 'basedir'"; $variables = $wpdb->get_results($sql, ARRAY_A);
$mysql = $variables[0]['Value'];

//-----------  Plugins  ------------------
if ( ! function_exists( 'get_plugins' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$plugins = get_plugins(); $plugins_active = (array)get_option('active_plugins');
$network_plugins_actived = array_keys((array)get_site_option( 'active_sitewide_plugins', array() ));

//-----------  Themes  --------------------
if ( ! function_exists( 'wp_get_themes' ) ) { 
	require_once ABSPATH . 'wp-admin/includes/theme.php';
}
$themes = wp_get_themes( array( 'allowed' => true ) );
$stylesheet = get_option( 'stylesheet' ); 					
$theme = wp_get_theme(); $template = (empty($theme->template) && $theme->template != $stylesheet)? $theme->template:'';
$table_size = cmcmg::database_size( $tables );
//unset( $themes[$stylesheet] ); unset( $themes[$template] );
?>
<div id="cmcmg-admin-page-export-container" >
	<?php
		if( $_REQUEST['cmcmg_action'] == 'export' ){
			show_message( "<p>Export Process Started</p>" );
			$response = cmcmg_action::export( array('echo_log'=> true) ); 
			if( is_array($response) && !empty($response['message']) ){
				show_message( $response['message']."\n" );
			}
			$param = is_array($response) ? $response['param']: array();
			if( $param && $param['log_file'] ){
				$log_file = str_replace( WP_CONTENT_DIR, content_url(), $param['log_file']);
				//show_message( "\n<a href='$log_file' target='_blank'>Log file</a>\n" );
			}
			show_message( "<p>Export Process Completed. Thank you.</p>" );
		}else{
	?>
	<form id="cmcmg-admin-page-export-form" method="post" action="<?php echo $form_url; ?>" style="margin-top:10px;">						
		
		<?php do_action('cmcmg_admin_page_export_before_accordion'); ?>
		<div id="cmcmg-admin-page-export-form-accordion">
			<h3>Tables <?php echo "(".count($tables).")";  ?></h3>
			<div id="cmcmg-admin-page-export-db-tables" class="">
				<?php ?>	
				<p><label>Base Dir: </label> <?php echo $mysql; ?></p>
				<p class="cmcmg-input-selection"><small><span onclick="cmcmg.selectall( '#cmcmg-input-tables', true);" >Select All</span> <span onclick="cmcmg.selectall( '#cmcmg-input-tables', false);">Deselect All</span></small></p>
				<select id="cmcmg-input-tables" multiple="true" name="tables[]" style="height:200px" >
				<?php				
					if( $tables ){			
						$user_table = array( $wpdb->usermeta, $wpdb->users); $i = 1;
						foreach( $tables as $item ){
							if( in_array( $item, $user_table) )continue;
							$preg = $pre.'[\d]+_';
							if( $b_id == 1 && preg_match( '/^'.$preg.'/', $item ) ) continue;
							echo "<option selected='true' value='$item'> $i) ".$item."</option>"; $i++;
						}
					}
					?>
				</select> 
				<p><label>Table size: </label> <?php echo $table_size; ?></p>
				<p><label>User Table: </label> <?php echo $wpdb->users; ?></p>
				<p><label>User Meta Table: </label> <?php echo $wpdb->usermeta; ?></p>
			</div>
			
			<h3>Plugins <?php echo "(".count($plugins).")"; ?></h3>
			<div id="cmcmg-admin-page-export-plugins">
				<?php ?>
				<p><label><strong>Plugins Path: </strong></label><?php echo WP_PLUGIN_DIR;  ?></p>
				<?php if( is_multisite() ){ ?>
				<p><label><strong>Network Activated Plugins: </strong></label> <?php echo implode( ', ', $network_plugins_actived ); ?></p>
				<?php } ?>
				<?php if( cmcmg::get_current_blog_id() > 0 ){ ?>
				<p><label><strong>Activated Plugins: </strong></label> <?php echo implode( ', ', $plugins_active ); ?></p>
				<?php } ?>
				<?php 
					if( cmcmg::get_current_blog_id() == -1 ){
						$net_plugins = cmcmg::get_network_blog_active_plugins();
						$net_plugins = array_intersect_key( $plugins, array_flip($net_plugins) );
						$net_plugins = array_column( $net_plugins, 'Name');
				?>
					<p><label><strong>All site Activated Plugins: </strong></label> <?php echo implode( ', ', $net_plugins ); ?></p>
				<?php } ?>
				
				<p>Plus:</p>
				<p class="cmcmg-input-selection"><small><span onclick="cmcmg.selectall( '#cmcmg-input-plugins', true);" >Select All</span> <span onclick="cmcmg.selectall( '#cmcmg-input-plugins', false);">Deselect All</span></small></p>
				<select id="cmcmg-input-plugins" name="plugins[]" multiple="true" style="height:150px;">
					<?php 
						$i = 1;
						foreach($plugins as $pk => $p){
							//if( in_array( $pk, $plugins_active ) )continue;
							$sel = !is_multisite() ?"selected='true'":'';
							echo sprintf("<option value='%s' %s >%s) %s</option>", $pk, $sel, $i, $p['Name']); $i++;
						}
					?>
				</select>
			</div>

			<h3>Theme <?php echo "(".count($themes).")"; ?></h3>
			<div id="cmcmg-admin-page-export-themes">	
				<?php ?>
				<p><label><strong>Themes: </strong> </label><?php echo get_theme_root();  ?></p>
				<?php if( cmcmg::get_current_blog_id() > 0 ){ ?>
				<p><label><strong>Activated Themes: </strong></label> <?php echo $stylesheet  ?></p>
				<?php if( !empty($template) ){ ?>
						<p><label><strong>Activeted Theme Parent: </strong><?php echo $template; ?></label></p>
				<?php } 
				}  
					if( cmcmg::get_current_blog_id() == -1 ){
						$net_themes = cmcmg::get_network_blog_active_themes();
				?>
					<p><label><strong>Activeted site Themes: </strong><?php echo implode( ', ', $net_themes ); ?></label></p>					
				<?php } ?>
				<p>Plus:</p>
				<p class="cmcmg-input-selection"><small><span onclick="cmcmg.selectall( '#cmcmg-input-themes', true);" >Select All</span> <span onclick="cmcmg.selectall( '#cmcmg-input-themes', false);">Deselect All</span></small></p>
				<select id="cmcmg-input-themes" name="themes[]" multiple="true" style="height:150px;">
					<?php 
						$i = 1;
						foreach($themes as $s => $t){
							$sel = !is_multisite() ? "selected='true'":'';
							echo sprintf( "<option value='%s' %s >%s) %s</option>", $s, $sel, $i, $t['Name']); $i++;
						}
					?>
				</select>
			</div>			
			
			<h3>Other WP-Content Files</h3>
			<div id="cmcmg-admin-page-export-wpcontentfiles">	
				<p><label>Plus Other wp-content files: </label></p>
				<p class="cmcmg-input-selection"><small><span onclick="cmcmg.selectall( '#cmcmg-input-files', true);" >Select All</span> <span onclick="cmcmg.selectall( '#cmcmg-input-files', false);">Deselect All</span></small></p>			
				<select id="cmcmg-input-files" name="wp-content-files[]" multiple="true" style="height:150px;">
					<?php 
						$i = 1;
						foreach(glob(WP_CONTENT_DIR.'/*') as $file){							
							$f = preg_replace('/^' . preg_quote(WP_CONTENT_DIR.'/', '/') . '/', '', $file);
							if( in_array( $f, array('plugins', 'themes', 'uploads') ) ) continue;
							$sel = !is_multisite() && $f != 'cmc-migrate' ? "selected='true'":'';
							echo sprintf("<option value='%s' %s >%s) %s</option>", $f, $sel, $i, $f); $i++;
						}
					?>
				</select>
			</div>
				
			<?php do_action('cmcmg_admin_page_export_accordion'); ?>
			
			<h3>Export</h3>
			<div id="cmcmg-admin-page-export-info">			
				<?php $upload_dir = wp_upload_dir(); ?>	
				<table id="cmcmg-admin-page-export-info-table" class="cmcmg-admin-page-info-table form-table">
					<tr>
						<th><label>Themes Dir: </label></th>
						<td><?php echo get_theme_root();  ?></td>
					</tr>
					<tr>
						<th><label>Plugins Dir: </label></th>
						<td><?php echo WP_PLUGIN_DIR;  ?></td>
					</tr>
					<tr>
						<th><label>Upload Dir:  </label></th>
						<td><?php echo $upload_dir['basedir']; ?></td>
					</tr>
					<tr>
						<th><label>Blog Name: </label></th>
						<td><?php echo get_bloginfo('name');   ?></td>
					</tr>
					<tr>
						<th><label>Blog Description: </label></th>
						<td><?php echo get_bloginfo('description');  ?></td>
					</tr>
					<tr>
						<th><label>Site Url: </label></th>
						<td><?php echo get_bloginfo('url');  ?></td>
					</tr>
					<tr>
						<th><label>Admin Email: </label></th>
						<td><?php echo get_bloginfo('admin_email');  ?></td>
					</tr>
					<tr>
						<th><label>Blog Id: </label></th>
						<td><?php echo get_current_blog_id(); ?></td>
					</tr>
					<tr>
						<th><label>Database Prefix: </label></th>
						<td><?php echo $wpdb->prefix; ?></td>
					</tr>
					<tr>
						<th><label>Wordpress Version </label></th>
						<td><?php echo get_bloginfo('version'); ?></td>
					</tr>
				</table>
				
				<input type="hidden" name="XDEBUG_SESSION_START" />
				
				<?php wp_nonce_field( 'cmcmg-export-nonce','_wpnonce', true, true ); ?>
				<p>
					<button class="button button-primary" type="submit"name="cmcmg_action" value="export">Create A Migration</button>
				</p>
			</div>		
		</div>
		<?php do_action('cmcmg_admin_page_export'); ?>
	</form>	
	<script>
		var cmcmg = cmcmg || {};
		(function($, cmcmg){
			$(function(){
				$('#cmcmg-admin-page-export-form-accordion').accordion({collapsible: true, active: false});
			});		
			
		})(jQuery, cmcmg);
	</script>
	<?php 
		}
	?>
</div>	
	