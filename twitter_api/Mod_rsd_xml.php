<?php
namespace Code\Module;

use Code\Lib\System;
use Code\Render\Theme;
use Code\Web\Controller;

class Rsd_xml extends Controller {

	function init() {
		header ("Content-Type: text/xml");
		echo replace_macros(Theme::get_template('rsd.tpl','addon/twitter_api'),array(
			'$project' => System::get_platform_name(),
			'$baseurl' => z_root(),
			'$apipath' => z_root() . '/api/'
		));
		killme();
	}

}

