<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Apps;
use Zotlabs\Web\HTTPSig;
use Zotlabs\Web\Controller;

class Content_import extends Controller {


	function post() {

		if(! local_channel())
			return;

		$channel = App::get_channel();

		$hz_server = $_REQUEST['hz_server'];

		// The API will convert these to UTC.

		$since = datetime_convert(date_default_timezone_get(),date_default_timezone_get(),$_REQUEST['since']);
		$until = datetime_convert(date_default_timezone_get(),date_default_timezone_get(),$_REQUEST['until']);

		$poll_interval = get_config('system','poll_interval',3);

		if(intval($_REQUEST['items'])) {
			$page = 0;

			while(1) {
				$headers = [ 
					'X-API-Token'      => random_string(),
					'X-API-Request'    => $hz_server . '/api/z/1.0/item_export_page?f=&since=' . urlencode($since) . '&until=' . urlencode($until) . '&page=' . $page ,
					'Host'             => \App::get_hostname(),
					'(request-target)' => '/api/z/1.0/item_export_page?f=&since=' . urlencode($since) . '&until=' . urlencode($until) . '&page=' . $page ,
				];

				$headers = HTTPSig::create_sig($headers,$channel['channel_prvkey'], channel_url($channel),true,'sha512');

				$x = z_fetch_url($hz_server . '/api/z/1.0/item_export_page?f=&since=' . urlencode($since) . '&until=' . urlencode($until . '&page=' . $page),false,$redirects,[ 'headers' => $headers ]);

				if(! $x['success']) {
					logger('no API response');
					break;
				}

				$j = json_decode($x['body'],true);

				if(! ($j['item'] || count($j['item'])))
					break;

				proc_run('php','addon/content_import/item_import_helper.php',$page,$start,$finish,$channel['channel_address'], urlencode($hz_server));
				sleep($poll_interval);

				$page ++;
				continue;
			}
			notice(t('Posts imported') . EOL);

		}

		if(intval($_REQUEST['files'])) {

			$headers = [ 
				'X-API-Token'      => random_string(),
				'X-API-Request'    => $hz_server . '/api/z/1.0/files?f=&since=' . urlencode($since) . '&until=' . urlencode($until),
				'Host'             => \App::get_hostname(),
				'(request-target)' => '/api/z/1.0/files?f=&since=' . urlencode($since) . '&until=' . urlencode($until),
			];

			$headers = HTTPSig::create_sig($headers,$channel['channel_prvkey'], channel_url($channel),true,'sha512');

			$x = z_fetch_url($hz_server . '/api/z/1.0/files?f=&since=' . urlencode($since) . '&until=' . urlencode($until),false,$redirects,[ 'headers' => $headers ]);

			if(! $x['success']) {
				logger('no API response');
				return;
			}

			$j = json_decode($x['body'],true);


			if(! $j['success']) 
				return;

			$poll_interval = get_config('system','poll_interval',3);

			if(count($j['results'])) {
				$todo = count($j['results']);
				logger('total to process: ' . $todo); 

				foreach($j['results'] as $jj) {

//					logger('json data: ' . print_r($jj,true));

					proc_run('php','addon/content_import/file_import_helper.php',$jj['hash'], $channel['channel_address'], urlencode($hz_server));
					sleep($poll_interval);

				}
			}

			notice(t('Files imported') . EOL);
		}

	}


	function get() {


		$desc = t('This addon app copies existing content and file storage to a cloned/copied channel. Once the app is installed, visit the newly installed app. This will allow you to set the location of your original channel and an optional date range of files/conversations to copy.'); 

		$text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

		if(! ( local_channel() && Apps::addon_app_installed(local_channel(),'content_import'))) { 
			return $text;
		}

		if(! local_channel()) {
			return login();
		}

		$o = replace_macros(get_markup_template('content_import.tpl','addon/content_import'),array( 
			'$header' => t('Content Import'),
			'$desc' => t('This will import all your conversations and cloud files from a cloned channel on another server. This may take a while if you have lots of posts and or files.'),
			'$items' => [ 'items', t('Include posts'), true, t('Conversations, Articles, Cards, and other posted content'), [ t('No'),t('Yes') ]],
			'$files' => [ 'files', t('Include files'), true, t('Files, Photos and other cloud storage'), [ t('No'),t('Yes') ]],
			'$fr_server' => array('hz_server', t('Original Server base URL'),'',''),
			'$since' => array('since', t('Since modified date yyyy-mm-dd'),'0001-01-01',''),
			'$until' => array('until', t('Until modified date yyyy-mm-dd'),datetime_convert('UTC',date_default_timezone_get(),'now','Y-m-d'),''),
			'$submit' => t('Submit'),
		));
		return $o;
	}
}
