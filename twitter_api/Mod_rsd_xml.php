<?php
namespace Code\Module;

class Rsd_xml extends \Code\Web\Controller {

	function init() {
		header ("Content-Type: text/xml");
		echo replace_macros(get_markup_template('rsd.tpl','addon/twitter_api'),array(
			'$project' => \Code\Lib\System::get_platform_name(),
			'$baseurl' => z_root(),
			'$apipath' => z_root() . '/api/'
		));
		killme();
	}

}

