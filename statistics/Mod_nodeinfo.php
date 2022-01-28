<?php
namespace Zotlabs\Module;

use Zotlabs\Web\Controller;
use Zotlabs\Lib\System;

class Nodeinfo extends Controller {

	function init() {

		$hidden = get_config('system','hide_in_statistics');

		$arr = [
			'version'           => '2.0',
			'software'          =>  [
				'name' => System::get_project_name()),
				'version' => (($hidden) ? EMPTY_STR : System::get_project_version())
			],
			'protocols'         => [ 'zot' ],
			'services'          => [],
			'openRegistrations' => ((intval(get_config('system','register_policy')) === REGISTER_OPEN) ? true : false),

			'usage' => [
				'users' => [
					'total' => (($hidden) ? 0 : intval(get_config('system','channels_total_stat'))),
					'activeHalfyear' => (($hidden) ? 0 : intval(get_config('system','channels_active_halfyear_stat'))),
					'activeMonth' => (($hidden) ? 0 : intval(get_config('system','channels_active_monthly_stat'))),
				],
				'localPosts' => (($hidden) ? 0 : intval(get_config('system','local_posts_stat'))),
				'localComments' => (($hidden) ? 0 : intval(get_config('system','local_comments_stat'))),
			],
			'metadata' => [ 'nodeName' => get_config('system','sitename') ]
		];

		if (get_config('system','activitypub', ACTIVITYPUB_ENABLED)) {
			$arr['protocols'][] = 'activitypub';
		}

		if (! $arr['software']['version']) {
			$arr['software']['version'] = 'N/A';
		}

		$services = [ 'atom1.0' ];

		if (isset($services)) {
			$arr['services']['outbound'] = $services;
		}
		if (isset($iservices)) {
			$arr['services']['inbound'] = $iservices;
		}

		call_hooks('nodeinfo',$arr);

		json_return_and_die($arr);
	}
}

