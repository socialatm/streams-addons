<?php

use Zotlabs\Extend\Route;

/**
 * Name: Articles
 * Description: provide a personal blog which accepts comments but does not federate
 * Version: 1
 */



function articles_load() {
	Route::register('addon/articles/Mod_Articles.php','articles');
	Route::register('addon/articles/Mod_Article_edit.php','article_edit');
}

function articles_unload() {
	Route::unregister('addon/articles/Mod_Articles.php','articles');
	Route::unregister('addon/articles/Mod_Article_edit.php','article_edit');
}

