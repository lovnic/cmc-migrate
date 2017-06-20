<?php
/* 
 * package: cmc-migrage
 * file: default_values.php
 */
if(!defined('ABSPATH')) { 
    header('HTTP/1.0 403 Forbidden');
    exit;
}

$GLOBALS['cmcmg_settings_default'] = array(
	'version'=> CMCMG_VERSION,
	'allowed_roles' =>'',
    'del_opt_uninstall' => 0,
	'del_folder_uninstall' => 0,
	'replace_dir_theme' => 0,
	'replace_dir_plugin' => 0,
	'replace_dir_wpcontent' => 0,	
	'allow_remote_connection'=>0,
	'remote_connection_token'=>'',
	'remote_migration_url'=>'',
	'remote_migration_token'=>'',
);

$GLOBALS['cmcmg_network_settings_default'] = array(
	'version'=> CMCMG_VERSION,
	'allowed_roles' =>'',
	'allowed_sites'=>array(),
	'del_opt_uninstall' => 0,
);