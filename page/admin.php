<?php
/*
package: cmc_migrate
file: admin/page.php 
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
$menus = array(
    'migrations'=>array('text'=>__('Migrations', 'cmcmg'), 'href'=>"?page=cmcmg$bid", 
        'sections' => array(
            'migrations'=>array('page'=>function(){ 
                echo "<div id='cmcmg_admin_page_section_migrations' class='cmcmg_tab'>";
                echo '<h3>'. __('All Migrations', 'cmcmg').'</h3>';
                cmc_migrate::$migrations->prepare_items();
				echo cmc_migrate::$migrations->show_messages();
                cmc_migrate::$migrations->views();
                echo "<form method='post' action='?page=cmcmg'>";
                echo "<input type='hidden' name='XDEBUG_SESSION_START' />";                
                cmc_migrate::$migrations->display();
                echo "</form>";
                echo "<div>";
            })
        ), 'default'=>'migrations'), 	
    'export'=>array('text'=>__('Export Site', 'cmcmg'), 'href'=>"?page=cmcmg&tab=export$bid",
        'sections' => array(
            'export'=>array('page'=> function(){
                echo "<div id='cmcmg_admin_page_section_export' class='cmcmg_section'>";
                require("sections/export.php");
                echo "<div>";
            })
		), 'default'=>'export'),
	'restore'=>array('text'=>__('Restore Site'), 'href'=>"?page=cmcmg&tab=restore$bid",
		'sections' => array(
            'restore'=>array('page'=> function(){
                echo "<div id='cmcmg_admin_page_section_restore' class='cmcmg_section'>";
                require("sections/restore.php");
                echo "<div>";
            })
		), 'default'=>'restore'),
    'settings'=>array('text'=>__('Settings', 'cmcmg'), 'href'=> "?page=cmcmg&tab=settings$bid",
		'sections' => array(
            'settings'=>array('page'=>function(){ 
                echo "<div id='cmcmg_admin_page_section_settings' class='cmcmg_section'>";
				require("sections/settings.php");  
				echo "<div>";
            })
        ), 'default'=>'settings'),
	'sysinfo'=>array('text'=>__('System Info', 'cmcmg'), 'href'=> "?page=cmcmg&tab=sysinfo$bid",
	'sections' => array(
		'sysinfo'=>array('page'=>function(){ 
			echo "<div id='cmcmg_admin_page_section_sysinfo' class='cmcmg_section'>";
			require("sections/sysinfo.php");  
			echo "<div>";
		})
	), 'default'=>'sysinfo'),
);
$sel_page = empty($_REQUEST['tab']) ? 'migrations': $_REQUEST['tab'];

//$bid = cmc_migrate::$url_blog_id;

?>
<div class="wrap cmcmg_admin_page">
	<style>
		#cmcmg_admin_page_net_toolbar{ border: 1px solid #e5e5e5; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 10px; padding: 5px; overflow: hidden; background: #fbfbfb; }
		.cmcmg-admin-page-info-table th{ background-color:#ccc; border-right: 1px solid black;}
		.cmcmg-admin-page-info-table td:hover{ background-color:#ccc;}
		.cmcmg-admin-page-info-table td, .cmcmg-admin-page-info-table th{
			padding:5px;
			border-bottom:1px solid black;
		}
		.cmcmg-input-selection span{ cursor: pointer; color: blue; text-decoration:underline;}
		.cmcmg-input-selection span:nth-child(2){  color: red; margin-left:5px;}
	</style>
    <h1>
        <?php 
			echo __('CMC Migrate', 'cmcmg'); 
			do_action('cmcmg_admin_page_title');
		?>		
		<button type="button" id="cmcmg-form-migration-impport-btn" class="page-title-action cmcmg-help-tip" data-tip="Import migration file" onclick="jQuery('form#cmcmg-form-migration-import').slideToggle('fast').find(':file').focus();" >                
            <?php echo __('Import', 'cmcmg'); ?>
        </button>  
		<?php do_action('cmcmg_admin_page_title_buttons'); ?>
    </h1>	
	<div style="width:400px;">
        <form id="cmcmg-form-migration-import" method="post" enctype="multipart/form-data" class="" style="display:none;" action="?page=cmcmg<?php echo $bid; ?>" >
            <p>
                <?php wp_nonce_field( 'cmcmg-import-nonce','_wpnonce', true, true ); ?>
                <input name="cmcmg_action" type="hidden" value="import" />
                <input name="XDEBUG_SESSION_START" type="hidden" value="xdebug" />
				<label>File: </label><input type="file" name="cmcmg_file_import" />
                <button type="submit" class="button button-primary" style="width:15%;"><?php echo __('Import', 'cmcmg'); ?></button>
            </p>
        </form>
		<?php do_action('cmcmg_admin_page_title_button_panel'); ?>
	</div>	
	<div>
		<?php if( is_network_admin() ){ ?>
			<div>
				<div id="cmcmg_admin_page_net_toolbar" style="">
					<form action="?page=cmcmg" method="post">
						<strong>Select Site: </strong>
						<?php 
							$subsitesraw = get_sites();  $subsites = array();
							foreach( $subsitesraw as $subsite ){
								$subsite_id = get_object_vars($subsite)["blog_id"];
								$subsite_name = get_blog_details($subsite_id)->blogname;
								$subsites[] = array('id'=>$subsite_id, 'name'=>$subsite_name);
							}
						?>
						<select name="blog_id">
							<?php
								$subsites = apply_filters('cmcmg_network_subsites_select', $subsites);
								foreach( $subsites as $subsite ){
									printf("<option value='%s' %s >%s</option>", $subsite['id'], selected( $subsite['id'], $_REQUEST['blog_id'], false), $subsite['name']." ($subsite[id]) " );
								}
							?>
						</select>
						<button type="submit" class="button button-secondary">Select</button>
					</form>
				</div>
				<div>
					<p>Site Url: <?php echo site_url(); ?></p>
				</div>
			</div>
		<?php } ?>
		<h2 id="cmcmg_admin_page_menu" class="nav-tab-wrapper wp-clearfix">        
			<?php             
				$menus = apply_filters('cmcmg_admin_page_menu', $menus, $sel_page );
				foreach($menus as $k => $m){
					if( $m['active'] === false) continue;
					$s = ($sel_page == $k)? "nav-tab-active":""; $m['class'] = is_array($m['class'])? implode(' ', $m['class']):$m['class'];
					echo sprintf('<a href="%s" class="nav-tab %s %s" %s > %s </a>', $m['href'], $m['class'], $s, $m['atts'], $m['text'] );
				}
			?> 		
		</h2>
		<div id="cmcmg_admin_page_body" class="cmcmg_admin_page_<?php echo $sel_page; ?>"> 
			<?php
				cmcmg::menu_render( $sel_page, $_REQUEST['section'], $menus );
			?>
		</div>
	</div>
	
	<script>
		var cmcmg = cmcmg || {};
		(function($, cmcmg){
			cmcmg.page_load = function( $wrap ){
				if( !$wrap )return;
				var tiptip_args = {
					'attribute': 'data-tip',
					'fadeIn': 50,
					'fadeOut': 50,
					'delay': 200
				};	
			
				$wrap.find( '.cmcmg-help-tip' ).tipTip( tiptip_args ).css( 'cursor', 'help' );
			}
			
			cmcmg.selectall = function( select, all ){
				if( !select ) return;
				$(select).find('option').prop( 'selected', all );
			}
			
			$(function(){
				cmcmg.page_load( $(document) );
			});
		
		})(jQuery, cmcmg);
	</script>
</div>