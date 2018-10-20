<?php
namespace Zotlabs\Module;

use Zotlabs\Web\Controller;
use Zotlabs\Lib\System;

class Nodeinfo extends Controller {

	function init() {

		$hidden = get_config('system','hide_in_statistics');

		if($hidden) {
logger("GETDATA");
			$lastrun = get_config('system','hide_in_stats_lastrun');
			$lastrun = (isset($lastrun) && (intval($lastrun) > 0)) ? intval($lastrun) : (time() - (60 * 60 * 24 * 30));
logger("LASTRUN: ".$lastrun);
			set_config('system','hide_in_stats_lastrun',time());
			$timedelta = time() - intval($lastrun);
logger("time: ".time());
logger("timedelta: ".$timedelta);
logger("month: ".intval(60*60*24*30));
			$timeproportion = floatval($timedelta / intval(60 * 60 * 24 * 30));
logger("timeproportion: ".$timeproportion);
			
			$prevusers = get_config('system','hide_in_stats_prevusers');
			$prevusers = (isset($prevusers) && (intval($prevusers) > 0)) ? $prevusers : rand(1,50);
			$maxusers = $prevusers + intval(200 * $timeproportion);
logger("users: prev / max: ".$prevusers. " / " . $maxusers);

			$users = rand($prevusers,$maxusers);
			set_config('system','hide_in_stats_prevusers',$users);

			$prevActiveMonth = get_config('system','hide_in_stats_prevactivemonth');
			$prevActiveMonth = (isset($prevActiveMonth) && (intval($prevActiveMonth) > 0)) ? $prevActiveMonth : $users;
			$activeMonth = $prevActiveMonth + intval(rand(1,($users-$prevActiveMonth+1))*$timeproportion);
			set_config('system','hide_in_stats_prevactivemonth',$activeMonth);

			$prevActiveHalfyear = get_config('system','hide_in_stats_prevactivehy');
			$prevActiveHalfyear = (isset($prevActiveHalfyear) && (intval($prevActiveHalfyear) > 0)) ? $prevActiveHalfyear : $users;
			$activeHalfyear = $prevActiveHalfyear + intval(rand(1,($users-$prevActiveHalfyear+1))*$timeproportion);
			set_config('system','hide_in_stats_prevactivehy',$activeHalfyear);

			$prevPosts = get_config('system','hide_in_stats_prevposts');
			$prevPosts = (isset($prevPosts) && intval($prevPosts) > 0) ? $prevPosts : rand($users,$users * 15);
			$localPosts = intval($prevPosts + ($activeMonth * rand(1,30) * $timeproportion));
			set_config('system','hide_in_stats_prevposts',$localPosts);
			$newPosts = $localPosts - $prevPosts;

			$prevComments = get_config('system','hide_in_stats_prevcomments');
			$prevComments = (isset($prevComments) && intval($prevComments) > 0) ? $prevComments : rand($localPosts,$localPosts * 3);
			$localComments = intval($prevComments + ($newPosts * rand(1,10) * $timeproportion));
			set_config('system','hide_in_stats_prevcomments',$localComments);
			$prevComments = $localComments;


			if(argc() > 1 && argv(1) === '2.0') {
				$arr = [
					'version' => '2.0',
					'software' => [ 'name' => strtolower(System::get_platform_name()),'version' => System::get_project_version()],
					'protocols' => [ 'zot' ],
					'services' => [],
					'openRegistrations' => false,
					'usage' => [ 'users' => [ 'total' => $users, 'activeHalfyear' => $activeHalfyear, 'activeMonth' => $activeMonth ],
						'localPosts' => $localPosts,
						'localComments' => $localComments
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

