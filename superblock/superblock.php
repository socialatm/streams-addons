<?php

use Zotlabs\Extend\Route;
use Zotlabs\Extend\Hook;
use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\LibBlock;


/**
 * Name: superblock
 * Description: block people
 * Version: 2.0
 * Author: Mike Macgirvin
 * Maintainer: Mike Macgirvin <mike@macgirvin.com> 
 */

function superblock_load() {

	Route::register('addon/superblock/Mod_Superblock.php','superblock');

	Hook::register('conversation_start', 'addon/superblock/superblock.php', 'superblock_conversation_start');
	Hook::register('thread_author_menu', 'addon/superblock/superblock.php', 'superblock_item_photo_menu');
	Hook::register('enotify_store', 'addon/superblock/superblock.php', 'superblock_enotify_store');
	Hook::register('enotify_format', 'addon/superblock/superblock.php', 'superblock_enotify_format');
	Hook::register('item_store', 'addon/superblock/superblock.php', 'superblock_item_store');
	Hook::register('directory_item', 'addon/superblock/superblock.php', 'superblock_directory_item');
	Hook::register('api_format_items', 'addon/superblock/superblock.php', 'superblock_api_format_items');
	Hook::register('conv_sort', 'addon/superblock/superblock.php', 'superblock_conv_sort');
	Hook::register('activity_widget', 'addon/superblock/superblock.php', 'superblock_activity_widget');

}


function superblock_unload() {

	Route::unregister('addon/superblock/Mod_Superblock.php','superblock');
	Hook::unregister_by_file('addon/superblock/superblock.php');

}



class Superblock {

	private $list = [];

	function __construct($channel_id) {
		$l = LibBlock::fetch($channel_id,BLOCKTYPE_CHANNEL);
		if ($l) {
			$this->list = ids_to_array($l,'block_entity');
		}
	}

	function get_list() {
		return $this->list;
	}

	function match($n) {
		if (! $this->list) {
			return false;
		}
		foreach ($this->list as $l) {
			if (trim($n) === trim($l)) {
				return true;
			}
		}
		return false;
	}

}


function superblock_conv_sort(&$b) {

	if (! ( local_channel() && Apps::addon_app_installed(local_channel(),'superblock'))) { 
		return;
	}

	$sb = new Superblock(local_channel());

	$ret = [];

	if ($b['items']) {
		foreach ($b['items'] as $item) {
			if ($sb->match($item['author_xchan']) || $sb->match($item['owner_xchan'])) {
				continue;
			}
			
			$matches = null;
			$found = false;
			$cnt = preg_match_all("/\[share(.*?)portable_id='(.*?)'(.*?)\]/ism", $item['body'], $matches, PREG_SET_ORDER);
			if ($cnt) {
				foreach ($matches as $match) {
					if ($sb->match($match[2])) {
						$found = true;
						break;
					}
				}
			}

			if ($found) {
				continue;
			}

			$matches = null;
			$found = false;
			$cnt = preg_match_all("/\[share(.*?)profile='(.*?)'(.*?)\]/ism", $b['item']['body'], $matches, PREG_SET_ORDER);
			if ($cnt) {
				foreach ($matches as $match) {
					$r = q("select hubloc_hash from hubloc where hubloc_id_url = '%s'",
						dbesc($match[2])
					);
					if ($r) {
						if ($sb->match($r[0]['hubloc_hash'])) {
							$found = true;
						}
					}
				}
			}

			if ($found) {
				continue;
			}

			$ret[] = $item;
		}
	}
	$b['items'] = $ret;

}

function superblock_item_store(&$b) {

	if (! Apps::addon_app_installed($b['uid'],'superblock')) { 
		return;
	}

	$sb = new Superblock($b['uid']);

	$found = false;

	if ($sb->match($b['owner_xchan'])) {
		$found = true;
	}
	elseif ($sb->match($b['author_xchan'])) {
		$found = true;
	}

	if ($found) {
		$b['cancel'] = true;
	}
	return;
}

function superblock_enotify_store(&$b) { 	

	if (! Apps::addon_app_installed($b['uid'],'superblock')) { 
		return;
	}

	$sb = new Superblock($b['uid']); 	
	$found = false; 	
	
	if ($sb->match($b['sender_hash'])) {
		$found = true;
	}

	if (is_array($b['parent_item']) && (! $found)) {
		if ($sb->match($b['parent_item']['owner_xchan'])) {
			$found = true;
		}
		elseif ($sb->match($b['parent_item']['author_xchan'])) {
			$found = true;
 		}
	}
	
	if ($found) { 		
		$b['abort'] = true;
	}
}

function superblock_enotify_format(&$b) {

	if (! Apps::addon_app_installed($b['uid'],'superblock')) { 
		return;
	}

	$sb = new Superblock($b['uid']);

	$found = false;

	if ($sb->match($b['hash'])) {
		$found = true;
	}

	if ($found) {
		$b['display'] = false;
	}
}



function superblock_api_format_items(&$b) {

	if (! Apps::addon_app_installed($b['api_user'],'superblock')) { 
		return;
	}

	$sb = new Superblock($b['api_user']);
	$ret = [];

	for ($x = 0; $x < count($b['items']); $x ++) {

		$found = false;

		if ($sb->match($b['items'][$x]['owner_xchan'])) {
			$found = true;
		}
		elseif ($sb->match($b['items'][$x]['author_xchan'])) {
			$found = true;
		}

		if (! $found) {
			$ret[] = $b['items'][$x];
		}
	}

	$b['items'] = $ret;

}


function superblock_directory_item(&$b) {

	if (! ( local_channel() && Apps::addon_app_installed(local_channel(),'superblock'))) { 
		return;
	}

	$sb = new Superblock(local_channel());

	$found = false;

	if ($sb->match($b['entry']['hash'])) {
		$found = true;
	}

	if ($found) {
		unset($b['entry']);
	}
}


function superblock_activity_widget(&$b) {

	if (! ( local_channel() && Apps::addon_app_installed(local_channel(),'superblock'))) { 
		return;
	}
	
	$sb = new Superblock(local_channel());

	$found = false;

	if ($b['entries']) {
		$output = [];
		foreach ($b['entries'] as $x) {
			if (! $sb->match($x['author_xchan'])) {
				$output[] = $x;
			}
		}
		$b['entries'] = $output;
	}
}


function superblock_conversation_start(&$b) {

	if (! ( local_channel() && Apps::addon_app_installed(local_channel(),'superblock'))) { 
		return;
	}

	$l = LibBlock::fetch($channel_id,BLOCKTYPE_CHANNEL);
	if ($l) {
		App::$data['superblock'] = ids_to_array($l,'block_entity');
	}

	if (! array_key_exists('htmlhead', App::$page)) {
		App::$page['htmlhead'] = '';
	}

	App::$page['htmlhead'] .= <<< EOT
<script>
function superblockBlock(author,item) {
	$.get('superblock?f=&item=' + item + '&block=' + encodeURI(author), function(data) {
		location.reload(true);
	});
}
</script>
EOT;

}

function superblock_item_photo_menu(&$b) {

	if (! ( local_channel() && Apps::addon_app_installed(local_channel(),'superblock'))) { 
		return;
	}

	$blocked = false;
	$author  = $b['item']['author_xchan'];
	$item    = $b['item']['id'];

	if (App::$channel['channel_hash'] == $author)
		return;

	if (is_array(App::$data['superblock'])) {
		foreach (App::$data['superblock'] as $bloke) {
			if (link_compare($bloke,$author)) {
				$blocked = true;
				break;
			}
		}
	}

	if ($blocked) {
		return;
	}

	$b['menu'][] = [           
		'menu' => 'superblock',
		'title' => t('Block Completely'),
		'icon' => 'fw',
		'action' => 'superblockBlock(\'' . $author . '\',' . $item . '); return false;',
		'href' => '#'
	];
}



