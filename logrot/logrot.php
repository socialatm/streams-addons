<?php

/**
 * Name: Logrot
 * Description: Logfile Rotator
 * Version: 1.0
 * Author: Mike Macgirvin
 * Maintainer: Mike Macgirvin <mike@macgirvin.com>
 */

use Code\Extend\Hook;
use Code\Render\Theme;
    
function logrot_load() {
		Hook::register('logger', 'addon/logrot/logrot.php','logrot_logger');
}

function logrot_unload() {
		Hook::unregister('logger', 'addon/logrot/logrot.php','logrot_logger');
}


function logrot_plugin_admin(&$o) {
	$t = Theme::get_template("admin.tpl", "addon/logrot/");
	$logrotpath = get_config('logrot', 'logrotpath');
	if(! $logrotpath)
		$logrotpath = '';
	$logrotsize = get_config('logrot', 'logrotsize');
	if($logrotsize === false)
		$logrotsize = 0;

	$logretained = get_config('logrot','logretained');
	if($logretained === false)
		$logretained = 20;

	$o = replace_macros($t, array(
			'$submit' => t('Submit'),
			'$logrotpath'  => array('logrotpath', t('Logfile archive directory'), $logrotpath, t('Directory to store rotated logs')),
			'$logrotsize'  => array('logrotsize', t('Logfile size in bytes before rotating, example 10M'), $logrotsize, ''),
			'$logretained' => array('logretained', t('Number of logfiles to retain'), $logretained, ''),
	));
}

function logrot_plugin_admin_post(&$a) {
	$logrotpath  = ((x($_POST, 'logrotpath')) ? notags(trim($_POST['logrotpath'])) : '');
	$logrotsize  = ((x($_POST, 'logrotsize')) ? trim(escape_tags($_POST['logrotsize'])) : 0);
	$logretained = ((x($_POST, 'logretained')) ? intval($_POST['logretained']) : 0);

	if($logrotpath)
		@mkdir($logrotpath);

	set_config('logrot', 'logrotpath', $logrotpath);
	set_config('logrot', 'logrotsize', $logrotsize);
	set_config('logrot', 'logretained', $logretained);

	info( t('Settings updated.') . EOL);
}


function logrot_logger(&$b) {

	$logrotpath  = get_config('logrot', 'logrotpath');
	$logrotsize  = get_config('logrot', 'logrotsize');
	$logretained = get_config('logrot','logretained');

	if(! $logrotsize)
		return;

	$bytes = engr_units_to_bytes($logrotsize);

	$x = @filesize($b['filename']);

	if(($x === false) || ($x < $bytes))
		return;

	@rename($b['filename'],$logrotpath . '/logfile-' . datetime_convert('UTC','UTC','now','Y-m-d_H:i') . '.out');

	$d = glob($logrotpath . '/logfile-*.out');
	if(count($d) > $logretained) {
		sort($d);
		$diff = count($d) - $logretained;
		for($x = 0 ; $x < $diff; $x ++)
			@unlink($d[$x]);	
	}
}