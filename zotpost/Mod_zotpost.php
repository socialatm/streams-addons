<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\PConfig;
use Zotlabs\Lib\Navbar;
use Zotlabs\Web\Controller;

class Zotpost extends Controller {

	function post() {

		if (! ( local_channel() && Apps::addon_app_installed(local_channel(),'zotpost'))) { 
			return;
		}

		$channel = App::get_channel();

		// Don't let somebody post to their self channel. Since we aren't passing message-id this would be very very bad.

		if (! trim($_POST['zotpost_channel'])) {
			notice( t('Channel is required.') . EOL);
			return;
		}

		if ($channel['channel_address'] === trim($_POST['zotpost_channel'])) {
			notice( t('Invalid channel.') . EOL);
			return;
		}

		PConfig::Set(local_channel(), 'zotpost', 'server',          trim($_POST['zotpost_server']));
		PConfig::Set(local_channel(), 'zotpost', 'password',        obscurify(trim($_POST['zotpost_password'])));
		PConfig::Set(local_channel(), 'zotpost', 'channel',         trim($_POST['zotpost_channel']));
		PConfig::Set(local_channel(), 'zotpost', 'post_by_default', intval($_POST['zotpost_default']));
        info( t('Zotpost Settings saved.') . EOL);

		Libsync::build_sync_packet();

	}

	function get() {

		$desc = t('This addon app allows you to cross-post to other Zot services and channels. After installing the app, select it to configure the destination settings and preferences.');

		$text =  '<div class="section-content-info-wrapper">' . $desc . '</div>';


		if(! ( local_channel() && Apps::addon_app_installed(local_channel(),'zotpost'))) {
			return $text;
		}

		Navbar::set_selected(t('ZotPost'));

		$api        = PConfig::Get(local_channel(), 'zotpost', 'server');
		$password   = unobscurify(PConfig::Get(local_channel(), 'zotpost', 'password' ));
		$channel    = PConfig::Get(local_channel(), 'zotpost', 'channel' );
		$defenabled = PConfig::Get(local_channel(), 'zotpost', 'post_by_default');
		$defchecked = (($defenabled) ? 1 : false);

		$sc = $text;

		$sc .= replace_macros(get_markup_template('field_input.tpl'), [
			'$field'	=>  [ 'zotpost_server', t('Zot server URL'), $api, t('https://example.com') ]
		]);

		$sc .= replace_macros(get_markup_template('field_input.tpl'), [
			'$field'	=>  [ 'zotpost_channel', t('Zot channel name'), $channel, t('Nickname') ]
		]);

		$sc .= replace_macros(get_markup_template('field_password.tpl'), [
			'$field'	=>  [ 'zotpost_password', t('Zot password'), $password, '' ]
		]);

		$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), [
			'$field'	=>  [ 'zotpost_default', t('Send public postings to Zot channel by default'), $defchecked, '', [ t('No'),t('Yes') ] ],
		]);

		return replace_macros(get_markup_template('generic_app_settings.tpl'), [
			'$addon' 	=> [ 'zotpost', t('Zotpost Settings'), '', t('Submit') ],
			'$content'	=> $sc
		]);

	}

}