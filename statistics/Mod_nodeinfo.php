<?php
namespace Zotlabs\Module;

use Zotlabs\Web\Controller;
use Zotlabs\Lib\System;

class Nodeinfo extends Controller {

	function init() {

		$hidden = get_config('system','hide_in_statistics');

		if($hidden) {

			if(argc() > 1 && argv(1) === '2.0') {
				$arr = [
					'version' => '2.0',
					'software' => [ 'name' => strtolower(System::get_platform_name()),'version' => System::get_project_version()],
					'protocols' => [ 'zot' ],
					'services' => [],
					'openRegistrations' => false,
					'usage' => [ 'users' => [ 'total' => 1, 'activeHalfyear' => 1, 'activeMonth' => 1 ],
						'localPosts' => 1,
						'localComments' => 1
					],
					'metadata' => [ 'nodeName' => get_config('system','sitename') ]
				];
			}

		}
		else {
			$arr = [
				'version'           => '2.0',
				'software'          =>  [ 'name' => strtolower(System::get_platform_name()),'version' =>System::get_project_version() ],
				'protocols'         => [ 'zot' ],
				'services'          => [],
				'openRegistrations' => ((intval(get_config('system','register_policy')) === REGISTER_OPEN) ? true : false),

				'usage' => [
					'users' => [
						'total' => intval(get_config('system','channels_total_stat')),
						'activeHalfyear' => intval(get_config('system','channels_active_halfyear_stat')),
						'activeMonth' => intval(get_config('system','channels_active_monthly_stat')),
					],
					'localPosts' => intval(get_config('system','local_posts_stat')),
					'localComments' => intval(get_config('system','local_comments_stat')),
				],
				'metadata' => [ 'nodeName' => get_config('system','sitename') ]
			];

			if(! defined('NOMADIC')) {
				$arr['protocols'][] = 'activitypub';
			}

			$services = [ 'atom1.0' ];
			$iservices = [ 'atom1.0', 'rss2.0' ];

			if($services)
				$arr['services']['outbound'] = $services;
			if($iservices)
				$arr['services']['inbound'] = $iservices;

		}


		header('Content-type: application/json');
		echo json_encode($arr);
		killme();


	}
}

