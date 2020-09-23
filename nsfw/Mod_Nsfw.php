<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libsync;

class Nsfw extends \Zotlabs\Web\Controller {


	function post() {

		if(! ( local_channel() && Apps::addon_app_installed(local_channel(),'nsfw'))) { 
			return;
		}

		if($_POST['nsfw-submit']) {
			set_pconfig(local_channel(),'nsfw','words',trim($_POST['nsfw-words']));
			set_pconfig(local_channel(),'nsfw','collapse_all',intval($_POST['nsfw-collapse']));
			info( t('NSFW Settings saved.') . EOL);
		}

		Libsync::build_sync_packet();

	}

	function get() {


		$desc = t('This addon app looks in posts for the words/text you specify below, and collapses any content containing those keywords so it is not displayed at inappropriate times, such as sexual innuendo that may be improper in a work setting. It is polite and recommended to tag any content containing nudity with #NSFW.  This filter can also match any other word/text you specify, and can thereby be used as a general purpose content filter.'); 

		$text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

		if(! ( local_channel() && Apps::addon_app_installed(local_channel(),'nsfw'))) { 
			return $text;
		}
		$words = get_pconfig(local_channel(),'nsfw','words','nsfw,contentwarning');

		$sc = $text;

		$sc .= replace_macros(get_markup_template('field_input.tpl'), array(
			'$field'	=> array('nsfw-words', t('Comma separated list of keywords to hide'), $words, t('Word, /regular-expression/, lang=xx, lang!=xx'))
		));

//		$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
//			'$field'	=> array('nsfw-collapse', t('Collapse entire conversation if a match is found'), get_pconfig(local_channel(),'nsfw','collapse_all',true), '' )
//		));

		$s = replace_macros(get_markup_template('generic_app_settings.tpl'), array(
			'$addon' 	=> array('nsfw', t('Not Safe For Work Settings'), t('General Purpose Content Filter'), t('Submit')),
			'$content'	=> $sc
		));

		return $s;

	}

}