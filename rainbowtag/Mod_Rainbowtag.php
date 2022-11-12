<?php

namespace Code\Module;

use App;
use Code\Lib\Apps;
use Code\Web\Controller;
use Code\Render\Theme;                                                                                                                                            


class Rainbowtag extends Controller {

	function get() {
		if(! local_channel())
			return;

		$desc = t('Add some colour to tag clouds');

		if(! Apps::addon_app_installed(local_channel(), 'rainbowtag')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';

			$o = '<b>Rainbow Tag App (Not Installed):</b><br>';
			$o .= $desc;
			return $o;
		}

		$content = '<b>Rainbow Tag App Installed:</b><br>';
		$content .= $desc;

		$tpl = Theme::get_template("settings_addon.tpl");

		$o = replace_macros($tpl, array(
			'$action_url' => '',
			'$form_security_token' => '',
			'$title' => t('Rainbow Tag'),
			'$content'  => $content,
			'$baseurl'   => z_root(),
			'$submit'    => '',
		));

		return $o;
	}
	
}
