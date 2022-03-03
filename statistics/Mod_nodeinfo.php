<?php
namespace Code\Module;

use Code\Web\Controller;
use Code\Lib\System;
use Code\Extend\Hook;
use Code\Lib\Config;
    
class Nodeinfo extends Controller {

	function init() {

		$hidden = Config::Get('system', 'hide_in_statistics');

		$arr = [
			'version'           => '2.0',
			'software'          =>  [
				'name' => System::get_project_name(),
				'version' => (($hidden) ? EMPTY_STR : System::get_project_version())
			],
			'protocols'         => [ 'nomad', 'zot' ],
			'services'          => [],
			'openRegistrations' => ((intval(Config::Get('system', 'register_policy')) === REGISTER_OPEN) ? true : false),

			'usage' => [
				'users' => [
					'total' => (($hidden) ? 0 : intval(Config::Get('system', 'channels_total_stat'))),
					'activeHalfyear' => (($hidden) ? 0 : intval(Config::Get('system', 'channels_active_halfyear_stat'))),
					'activeMonth' => (($hidden) ? 0 : intval(Config::Get('system', 'channels_active_monthly_stat'))),
				],
				'localPosts' => (($hidden) ? 0 : intval(Config::Get('system', 'local_posts_stat'))),
				'localComments' => (($hidden) ? 0 : intval(Config::Get('system', 'local_comments_stat'))),
			],
			'metadata' => [ 'nodeName' => Config::Get('system', 'sitename') ]
		];

		if (Config::Get('system', 'activitypub', ACTIVITYPUB_ENABLED)) {
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

		Hook::call('nodeinfo', $arr);

		json_return_and_die($arr);
	}
}

