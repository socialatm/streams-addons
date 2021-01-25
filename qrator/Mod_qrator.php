<?php
namespace Zotlabs\Module;

use Zotlabs\Web\Controller;

class Qrator extends Controller {

	function get() {

		$header = t('QR Generator (Qrator)');
		$prompt = t('Enter some text');

		$o .= replace_macros(get_markup_template('qrator.tpl','addon/qrator'), [
			'$header' => $header,
			'$qrtext' => [ 'qrtext', $prompt, '','', '', ' onkeyup="makeqr();" ' ]
		]);
		
		return $o;
	}
}
