<?php


use Zotlabs\Extend\Route;


/**
 * Name: Stream Order
 * Description: Provides an app to switch the post ordering
 * Version: 1.0
 * Author: Mike Macgirvin <http://macgirvin.com/profile/mike>
 * Maintainer: Mike Macgirvin <mike@macgirvin.com> 
 */

function stream_order_install() {
	Route::register('addon/stream_order/Mod_Stream_Order.php','stream_order');
}


function stream_order_uninstall() {
	Route::unregister('addon/stream_order/Mod_Stream_Order.php','stream_order');
}


