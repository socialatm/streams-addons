<?php
namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Lib\System;
use Code\Render\Theme;                                                                                                                                            



class Opensearch extends Controller {

	function init() {

		// stock project icons are 64px which is required for this interface
		// custom site icons will normally be [ 300,80,48 ] px.
		// Detect which one of these is in play and rewrite custom icon urls
		// so to generate a 64px icon on demand
		
		$icon = System::get_site_icon();

		if (strpos($icon,z_root() . '/image') === false) {
			$icon = str_replace('/m/','/64/',$icon);
		}


		header("Content-type: application/opensearchdescription+xml");

		echo replace_macros(Theme::get_template('opensearch.tpl','addon/opensearch'), [ 
			'$project'        => t('$Projectname'),
			'$search_project' => t('Search $Projectname'),
			'$baseurl'        => z_root(),
			'$repo'           => System::get_project_srclink(),
			'$photo'          => $icon,
			'$favicon'        => System::get_site_favicon(),
			'$nodename'       => App::get_hostname(),
		]);
			
		killme();
	}
}