<?php

namespace Zotlabs\Module;

use Zotlabs\Web\Controller;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libsync;

class Superblock extends Controller {

	function init() {
	
		if (! local_channel()) {
			return;
		}

		$words = get_pconfig(local_channel(),'system','blocked');
		$handled = false;
		$ignored = [];

		if (array_key_exists('block',$_GET) && $_GET['block']) {
			$handled = true;
			$r = q("select id from item where id = %d and author_xchan = '%s' limit 1",
				intval($_GET['item']),
				dbesc($_GET['block'])
			);
			if ($r) {
				if (strlen($words))
					$words .= ',';
				$words .= trim($_GET['block']);
			}
			$z = q("insert into xign ( 'uid', 'xchan') values ( %d , '%s' ) ",
				intval(local_channel()),
				dbesc($_GET['block'])
			);
			$ignored = [ 'uid' => local_channel(), 'xchan' => $_GET['block'] ];
		}

		if (array_key_exists('unblock',$_GET) && $_GET['unblock']) {
			$handled = true;
			if (check_form_security_token('superblock','sectok')) {
				$newlist = [];
				$list = explode(',',$words);
				if ($list) {
					foreach ($list as $li) {
						if ($li !== $_GET['unblock']) {
							$newlist[] = $li;
						}
					}
				}
				$words = implode(',',$newlist);
				$z = q("delete from xign where uid = %d  and xchan = '%s' ",
					intval(local_channel()),
					dbesc($_GET['block'])
				);

				$ignored = [ 'uid' => local_channel(), 'xchan' => $_GET['block'], 'deleted' => true ];
			}
		}

		if ($handled) {

			set_pconfig(local_channel(),'system','blocked',$words);
			Libsync::build_sync_packet(0, [ 'xign' => [ $ignored ] ] );

			info( t('superblock settings updated') . EOL );

			if ($_GET['unblock']) {
				return;
			}
		
			killme();
		}

	}

	function get() {
		$desc = t('This addon app allows you to block channels from appearing in your stream and basically makes them vanish from your life. You may occasionally encounter them on other websites but otherwise this blocking is extensive. To enable blocking, install this app if it is not already installed, then select the drop-down menu attached to the channel photo in a conversation and select \'Block Completely\'');

		$text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

		if (! ( local_channel() && Apps::addon_app_installed(local_channel(),'superblock'))) { 
			return $text;
		}

		$sc = $text;

		$cnf = get_pconfig(local_channel(),'system','blocked', EMPTY_STR);

		$list = explode(',',$cnf);
		stringify_array_elms($list,true);
		$query_str = implode(',',$list);
		if ($query_str) {
			$r = q("select * from xchan where xchan_hash in ( " . $query_str . " ) ");
		}
		else {
			$r = [];
		}
		if ($r) {
			for ($x = 0; $x < count($r); $x ++) {
				$r[$x]['encoded_hash'] = urlencode($r[$x]['xchan_hash']);
			}
		}

		$sc .= replace_macros(get_markup_template('superblock_list.tpl','addon/superblock'), [
			'$blocked' => t('Currently blocked'),
			'$entries' => $r,
			'$nothing' => (($r) ? '' : t('No channels currently blocked')),
			'$token'   => get_form_security_token('superblock'),
			'$remove'  => t('Remove')
		]);

		$s .= replace_macros(get_markup_template('generic_app_settings.tpl'), [
			'$addon' 	=> array('superblock', t('Superblock Settings'), '', t('Submit')),
			'$content'	=> $sc
		]);

		return $s;

	}
}