<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

class Fuzzloc extends Controller {

	function post() {
		if (! local_channel()) {
			return;
		}

		if (! Apps::addon_app_installed(local_channel(),'fuzzloc')) {
			return;
		}

		check_form_security_token_redirectOnErr('/fuzzloc', 'fuzzloc');

		set_pconfig(local_channel(),'fuzzloc','minfuzz',intval($_POST['minfuzz']));
		set_pconfig(local_channel(),'fuzzloc','maxfuzz',intval($_POST['maxfuzz']));
		info( t('Fuzzloc Settings updated.') . EOL);
	}

	function get() {

		if (! local_channel()) {
			return;
		}

		if (! Apps::addon_app_installed(local_channel(), 'fuzzloc')) {
			// Do not display any associated widgets at this point
			App::$pdl = '';

			$o = '<b>' . t('Fuzzy Location App') . ' (' . t('Not Installed') . '):</b><br>';
			$o .= t('Blur your precise location if your channel uses browser location mapping');
			return $o;
		}

		$sc = replace_macros(get_markup_template('field_input.tpl'), [
			'$field'	=> [ 'minfuzz', t('Minimum offset in meters'), get_pconfig(local_channel(),'fuzzloc','minfuzz') ],
		]);

		$sc .= replace_macros(get_markup_template('field_input.tpl'), [
			'$field'	=> [ 'maxfuzz', t('Maximum offset in meters'), get_pconfig(local_channel(),'fuzzloc','maxfuzz') ],
		]);

		$o = replace_macros(get_markup_template('settings_addon.tpl'), [
			'$action_url'          => 'fuzzloc',
			'$form_security_token' => get_form_security_token("fuzzloc"),
			'$title'               => t('Fuzzy Location'),
			'$content'             => $sc,
			'$baseurl'             => z_root(),
			'$submit'              => t('Submit'),
		]);

		return $o;
	}

}
