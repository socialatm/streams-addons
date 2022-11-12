<?php
namespace Code\Module;

use Code\Web\Controller;
use Code\Render\Theme;                                                                                                                                            


class Qrator extends Controller {

	function get() {

		$header = t('QR Generator (Qrator)');
		$prompt = t('Enter some text');

		$o .= replace_macros(Theme::get_template('qrator.tpl','addon/qrator'), [
			'$header' => $header,
			'$qrtext' => [ 'qrtext', $prompt, '','', '', ' onkeyup="makeqr();" ' ]
		]);
		
		return $o;
	}
}
