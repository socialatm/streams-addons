<?php

/**
 * Name: Qrator
 * Description: QR generator
 * Version: 1.0
 * Author: Macgirvin
 * Maintainer: none
 */

use Code\Extend\Hook;
use Code\Extend\Route;

function qrator_load() {
	Hook::register('photo_mod_init','addon/qrator/qrator.php','qrator_photo_mod_init');
	Hook::register('bbcode','addon/qrator/qrator.php','qrator_bbcode');
	Route::register('addon/qrator/Mod_qrator.php','qrator');
}
function qrator_unload() {
	Hook::unregister_by_file('addon/qrator/qrator.php');
	Route::unregister('addon/qrator/Mod_qrator.php','qrator');
}

function qrator_photo_mod_init(&$x) {

	if(argc() > 1 && argv(1) === 'qr') {
		$t = $_GET['qr'];
		require_once("addon/qrator/phpqrcode/qrlib.php");
		header("Content-type: image/png");
		QRcode::png(($t) ? $t : '.');
		killme();
	}

}


/**
 * @brief Returns an QR-code image from a value given in $match[1].
 *
 * @param array $match
 * @return string HTML img with QR-code of $match[1]
 */
function qrator_bb_qr($match) {
	return '<img class="zrl" src="' . z_root() . '/photo/qr?f=&qr=' . urlencode($match[1]) . '" alt="' . t('QR code') . '" title="' . htmlspecialchars($match[1],ENT_QUOTES,'UTF-8') . '" />';
}


function qrator_bbcode(&$x) {

	if(isset($x['options']['export']) && intval($x['options']['export'])) {
		return;
	}
	
	if(isset($x['options']['activitypub']) && intval($x['options']['activitypub'])) {
		return;
	}

	if (strpos($x['text'],'[/qr]') !== false) {
		$x['text'] = preg_replace_callback("/\[qr\](.*?)\[\/qr\]/ism", 'qrator_bb_qr', $x['text']);
	}

}


