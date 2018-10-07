<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Apps;
use Zotlabs\Web\HTTPSig;
use Zotlabs\Web\Controller;

class File_import extends Controller {


	function post() {

		if(! local_channel())
			return;

		$channel = App::get_channel();

		$hz_server = $_REQUEST['hz_server'];

		// The API will convert these to UTC.

		$since = datetime_convert(date_default_timezone_get(),date_default_timezone_get(),$_REQUEST['since']);
		$until = datetime_convert(date_default_timezone_get(),date_default_timezone_get(),$_REQUEST['until']);

	
		$headers = [ 
			'X-API-Token' => random_string(),
			'X-API-Request' => $hz_server . '/api/z/1.0/files?f=&since=' . urlencode($since) . '&until=' . urlencode($until),
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

//				logger('json data: ' . print_r($jj,true));

				proc_run('php','addon/file_import/file_import_helper.php',$jj['hash'], $channel['channel_address'], urlencode($hz_server));
				sleep($poll_interval);

			}

			goaway(z_root() . '/cloud/' . $channel['channel_address']);
		}
	}


	function get() {


		$desc = t('This addon app copies existing file storage to a cloned/copied channel. Once the app is installed, visit the newly installed app. This will allow you to set the location of your original channel and an optional date range of files to copy.'); 

		$text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

		if(! ( local_channel() && Apps::addon_app_installed(local_channel(),'file_import'))) { 
			return $text;
		}

		if(! local_channel()) {
			return login();
		}

		$o = replace_macros(get_markup_template('file_import.tpl','addon/file_import'),array( 
			'$header' => t('File Storage Import'),
			'$desc' => t('This will import all your cloud files from a cloned channel on another server. This may take a while if you have lots of files.'),
			'$fr_server' => array('hz_server', t('Original Server base URL'),'',''),
			'$since' => array('since', t('Since modified date yyyy-mm-dd'),'0001-01-01',''),
			'$until' => array('until', t('Until modified date yyyy-mm-dd'),datetime_convert('UTC',date_default_timezone_get(),'now','Y-m-d'),''),
			'$submit' => t('Submit'),
		));
		return $o;
	}
}
