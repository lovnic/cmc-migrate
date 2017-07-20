<?php
/*
package: cmc_migrate
file: admin/restore.php 
*/
if(!defined('ABSPATH')) { 
    header('HTTP/1.0 403 Forbidden');
    exit;
}
if( !cmcmg::is_user_allowed() ){
	echo 'You Do have sufficient Permission To View This Page'; 
	return;
}

if( empty( $_REQUEST['id'] ) ){
	echo "<p>No Exported file Selected</p>";
	return;
}

$file = $_REQUEST['id']; $base = dirname($file); if( $base != '.' ) return;
$f_path = CMCMG_EXPORT_DIR . cmcmg::get_current_blog_id() .'/'.$file; 
$meta = file_get_contents('zip://'.$f_path.'#meta.json' );
$meta = json_decode( $meta, true ); 

$bid = cmcmg::$url_blog_id;
$form_url = "?page=cmcmg&tab=restore&id=".esc_attr($_REQUEST['id']).$bid;
$form_url = apply_filters('cmcmg_admin_page_restore_url', $form_url);
?>
	
<div id="cmcmg-admin-page-restore-container" >
	<?php 
		if( $_REQUEST['cmcmg_action'] == 'restore' ){
			show_message( "<p>Restore Process Started</p>" );
			$response = cmcmg_action::restore( array('echo_log'=> true) ); $param = is_array($response) ? $response['param']: array();
			if( is_array($response) && !empty($response['message']) ){
				show_message( $response['message']."\n" );
			}
			if( $param && $param['log_file'] ){
				$log_file = str_replace( WP_CONTENT_DIR, content_url(), $param['log_file']);
				show_message( "\n<a href='$log_file' target='_blank'>Log file</a>\n" );
			}
			show_message( "<p>Restore Process Completed. Thank you.</p>" );
		}else{
	?>
	<form id="cmcmg-admin-page-restore-form" method="post" action="<?php echo $form_url; ?>" style="margin-top:10px;">				
		<style>
			#cmcmg-admin-page-restore-replacetext table tbody tr:first-child button:nth-child(2){
				display:none;
			}
		</style>
		<?php do_action('cmcmg_admin_page_restore_before_accordion'); ?>	
		<div id="cmcmg-admin-page-restore-form-accordion">
			<h3>Tables</h3>
			<div id="cmcmg-admin-page-restore-db-tables" class="">
				<select multiple="true" name="tables[]" disabled="true" style="height:200px" >
				<?php						
						foreach( $meta['tables'] as $item ){
							echo '<option>'.$item.'</option>';
						}
					?>
				</select>
				<p><label>User Table: </label> <?php echo $meta['users_table']; ?></p>
				<p><label>User Meta Table: </label> <?php echo $meta['usermeta_table']; ?></p>
			</div>	
			<h3>Plugins</h3>
			<div id="cmcmg-admin-page-restore-plugins">
				<select name="plugins[]" multiple="true" disabled="true" style="height:150px;">
					<?php 
						foreach($meta['plugins'] as $p){
							echo "<option>".$p."</option>";
						}
					?>
				</select>
			</div>	
			<h3>Theme</h3>
			<div id="cmcmg-admin-page-restore-themes">	
				<select name="themes[]" multiple="true" disabled="true" style="height:150px;">
					<?php 
						foreach($meta['themes'] as $s => $t){
							echo "<option value='$s' >".$t."</option>";
						}
					?>
				</select>
			</div>		
			<h3>Other WP-Content Files</h3>
			<div id="cmcmg-admin-page-restore-wpcontentfiles">	
				<p><label>Plus Other wp-content files: </label></p>				
				<select name="wp-content-files[]" multiple="true" disabled="true" style="height:150px;">
					<?php 
						foreach((array)$meta['wp-content-files'] as $file){							
							echo "<option value='$f' >".$file."</option>";
						}
					?>
				</select>
			</div>
			<h3>Log File</h3>
			<div id="cmcmg-admin-page-restore-logfile">	
				<p><label>logfile: </label></p>			
				<?php 
					$log_file = @file_get_contents('zip://'.$f_path.'#log.txt');
				?>
				<textarea class="widefat" style="height:100%;" readonly="true"><?php echo $log_file; unset($log_file); ?></textarea>
			</div>
			<h3>Replace text</h3>
			<div id="cmcmg-admin-page-restore-replacetext">
				<table>	
					<thead>
						<tr>
							<th>No.</th>
							<th>Text</th>
							<th>New text</th>
							<th>Actions</th>
						</tr>	
					</thead>
					<tbody>
						<tr>
							<td>1</td>
							<td><input type="text" name="replacetext[0][text]" class="" /></td>
							<td><input type="text" name="replacetext[0][replace]" class="" /></td>
							<td>
								<button type="button" onclick="cmcmg.replacetext(this, 'add');" >+</button>
								<button type="button" onclick="cmcmg.replacetext(this, 'remove');" >X</button>								
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			
			<?php do_action('cmcmg_admin_page_restore_accordion'); ?>	
			
			<h3>Restore</h3>
			<div id="cmcmg-admin-page-restore-info" class="">
				<?php 
					$upload_dir = wp_upload_dir();  
				?>
				<?php $upload_dir = wp_upload_dir(); ?>	
				<table id="cmcmg-admin-page-info-table" class="cmcmg-admin-page-info-table form-table">
					<tr>
						<th><label>Blog Name: </label></th>
						<td><?php echo $meta['name'];   ?></td>
					</tr>
					<tr>
						<th><label>Blog Description: </label></th>
						<td><?php echo $meta['blog_description'];  ?></td>
					</tr>
					<tr>
						<th><label>Admin Email:  </label></th>
						<td><?php echo $meta['admin_email'];  ?></td>
					</tr>
					<tr>
						<th><label>Site Url:  </label></th>
						<td><?php echo $meta['siteurl'];  ?></td>
					</tr>
					<tr>
						<th><label>Home Url:  </label></th>
						<td><?php echo $meta['homeurl'];  ?></td>
					</tr>
					<tr>
						<th><label>ABSPATH:  </label></th>
						<td><?php echo $meta['ABSPATH'];  ?></td>
					</tr>
					<tr>
						<th><label>Database Prefix: </label></th>
						<td><?php echo $meta['table_prefix']; ?></td>
					</tr>
					<tr>
						<th><label>Date Time: </label></th>
						<td><?php echo $meta['datetime']; ?></td>
					</tr>
					<tr>
						<th><label>From Multisite: </label></th>
						<td><?php echo isset($meta['from_multisite'])? ($meta['from_multisite']?'Yes':'No'):'unkown'; ?></td>
					</tr>
					<tr>
						<th><label>Is Network of Sites: </label></th>
						<td><?php echo isset($meta['is_network'])? ($meta['is_network']?'Yes':'No'):'unkown'; ?></td>
					</tr>
					<tr>
						<th><label>Wordpress Version: </label></th>
						<td><?php echo isset($meta['wp_version'])? $meta['wp_version']:'unkown'; ?></td>
					</tr>
					
				</table>
				<input type="hidden" name="XDEBUG_SESSION_START" />
				<!-- <input type="hidden" name="id" value="<?php echo $_REQUEST['id']; ?>" /> -->
				<?php wp_nonce_field( 'cmcmg_restore_migration','_wpnonce', true, true ); ?>
				<p>
					<button onclick="return cmcmg.restore();" class="button button-primary" type="submit"name="cmcmg_action" value="restore" >Restore</button>
				</p>
			</div>	
		</div>
		<?php do_action('cmcmg_admin_page_restore'); ?>		
	</form>
	<script>
		var cmcmg = cmcmg || {};
		(function($, cmcmg){			
			cmcmg.restore = function(){
				var msg = "Your current site will be replaced with this migration.\n"+
					"Make sure you make a backup of your databases and files.\n"+
					"The process is irreversible.\n"+
					"Do you want to continue";
				if( confirm(msg) ){
					return true
				}
				return false;
			}
			
			cmcmg.replacetext = function( me, action ){
				$me = $(me); $tr = $me.closest('tr'); $table = $me.closest('table');
				if( action == 'add' ){
					$trnew = $tr.clone(); 
					$trnew.find(':text:nth-child(1)').val(''); 
					$tr.find(':text:nth-child(2)').val('');
					$trnew.insertAfter( $tr );
				}else if( action == 'remove' ){
					var index = $tr.index();
					if( $tr.index() != 0 ){ $tr.remove(); }
				}
				
				$table.find('tbody tr').each(function(i, v){
					var $tr = $(this); var index = i+1;
					$tr.find('td:nth-child(1)').text(index); $input = $tr.find(':text');
					if( $input.size() > 0){
						$($input[0]).prop('name', 'replacetext['+i+'][text]');
						$($input[1]).prop('name', 'replacetext['+i+'][replace]');
					}					
				});
			}			
			
			$(function(){
				$('#cmcmg-admin-page-restore-form-accordion').accordion({collapsible: true, active: false});
			});					
		})(jQuery, cmcmg);
	</script>
	
	<?php 
		}
	?>
</div>
