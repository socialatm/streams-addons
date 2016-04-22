<?php

/**
 * Name: Calculator App
 * Description: Simple Calculator Application
 * Version: 1.0
 * Author: Mike Macgirvin <mike@zothub.com>
 * MinVersion: 1.4.2
 * Maintainer: none
 */

require_once('addon/calc/Mod_Calc.php');

function calc_load() {
	Zotlabs\Extend\Hook::register('app_menu', 'addon/calc/calc.php', 'calc_app_menu');
}

function calc_unload() {
	Zotlabs\Extend\Hook::unregister('app_menu', 'addon/calc/calc.php', 'calc_app_menu');

}

function calc_app_menu(&$b) {
	$b['app_menu'][] = '<div class="app-title"><a href="calc">Calculator</a></div>'; 
}

