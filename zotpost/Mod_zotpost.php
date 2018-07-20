<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Apps;


class Zotpost extends \Zotlabs\Web\Controller {

	function post() {

		if(! ( local_channel() && Apps::addon_app_installed(local_channel(),'zotpost'))) { 
			return;
		}

		$channel = \App::get_channel();

		// Don't let somebody post to their self channel. Since we aren't passing message-id this would be very very bad.

		if(! trim($_POST['zotpost_channel'])) {
			notice( t('Channel is required.') . EOL);
			return;
		}

		if($channel['channel_address'] === trim($_POST['zotpost_channel'])) {
			notice( t('Invalid channel.') . EOL);
			return;
		}

		set_pconfig(local_channel(), 'zotpost', 'baseapi',         trim($_POST['zotpost_baseapi']));
		set_pconfig(local_channel(), 'zotpost', 'password',        z_obscure(trim($_POST['zotpost_password'])));
		set_pconfig(local_channel(), 'zotpost', 'channel',         trim($_POST['zotpost_channel']));
		set_pconfig(local_channel(), 'zotpost', 'post',            intval($_POST['zotpost_enable']));
		set_pconfig(local_channel(), 'zotpost', 'post_by_default', intval($_POST['zotpost_default']));
        info( t('Zotpost Settings saved.') . EOL);


	}

	function get() {

		$desc = t('This addon app allows you to cross-post to other Zot services and channels. After installing the app, select it to configure the destination settings and preferences.');

		$text =  '<div class="section-content-info-wrapper">' . $desc . '</div>';


		if(! ( local_channel() && Apps::addon_app_installed(local_channel(),'zotpost'))) {
			return $text;
		}

		nav_set_selected(t('Zotpost'));

		$api     = get_pconfig(local_channel(), 'zotpost', 'baseapi');
		$password = z_unobscure(get_pconfig(local_channel(), 'zotpost', 'password' ));
		$channel = get_pconfig(local_channel(), 'zotpost', 'channel' );
		$defenabled = get_pconfig(local_channel(),'zotpost','post_by_default');
		$defchecked = (($defenabled) ? 1 : false);


		$sc = $text;

		$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
			'$field'	=> array('zotpost_default', t('Send public postings to Zot channel by default'), $defchecked, '', array(t('No'),t('Yes'))),
		));

		$sc .= replace_macros(get_markup_template('field_input.tpl'), array(
			'$field'	=> array('zotpost_baseapi', t('Zot API Path'), $api, t('https://{sitename}/api'))
		));

		$sc .= replace_macros(get_markup_template('field_input.tpl'), array(
			'$field'	=> array('zotpost_channel', t('Zot channel name'), $channel, t('Nickname'))
		));

		$sc .= replace_macros(get_markup_template('field_password.tpl'), array(
			'$field'	=> array('zotpost_password', t('Zot password'), $password, '')
		));

		return replace_macros(get_markup_template('generic_app_settings.tpl'), array(
			'$addon' 	=> array('zotpost', t('Zotpost Settings'), '', t('Submit')),
			'$content'	=> $sc
		));

	}

}