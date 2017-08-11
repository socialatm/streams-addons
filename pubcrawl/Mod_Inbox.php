<?php
namespace Zotlabs\Module;


class Inbox extends \Zotlabs\Web\Controller {

	function post() {
		if(argc() <= 1)
			return;

		$is_public = false;

		if(argv(1) === '[public]') {
			$channel = get_sys_channel();
			$is_public = true;
		}
		else {
			$channel = channelx_by_nick(argv(1));
		}

		if(! $channel)
			return;

		$data = file_get_contents('php://input');
		if(! $data)
			return;

		logger('inbox_activity: ' . jindent($data), LOGGER_DATA);

		$AS = new \ActivityStreams($data);

		if(! $AS->is_valid())
			return;

		if(is_array($AS->actor) && array_key_exists('id',$AS->actor))
			as_actor_store($AS->actor['id'],$AS->actor);


		$saved_recips = [];
		foreach( [ 'to', 'cc', 'audience' ] as $x ) {
			if(array_key_exists($x,$AS->data)) {
				$saved_recips[$x] = $AS->data[$x];
			}
		}

		switch($AS->type) {
			case 'Follow':
				if($AS->obj & $AS->obj['type'] === 'Person') {
					// do follow activity
					as_follow($channel,$AS);
					http_status_exit(200,'OK');
					return;

				}
				break;
			default:
				break;

		}

		$observer_hash = $AS->actor['id'];
		if(! $observer_hash)
			return;


		if($is_public) {


		}
		else {

		}

		// Look up actor
		// These activities require permissions		

		switch($AS->type) {
			case 'Create':
				as_create_action($channel,$observer_hash,$AS);
				http_status_exit(200,'OK');
			case 'Like':
			case 'Dislike':
				as_like_action($channel,$observer_hash,$AS);
				http_status_exit(200,'OK');
			case 'Update':
			case 'Delete':
			case 'Add':
			case 'Remove':
			case 'Announce':
			case 'Undo':
				break;
			default:
				break;

		}

	}

	function get() {

	}

}



