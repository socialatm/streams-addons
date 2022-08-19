<?php

namespace Code\Module;

use Code\Lib\Apps;
use Code\Lib\Libsync;

class Stream_order extends \Code\Web\Controller {


	function get() {


		$desc = t('This addon app provides a selector on your stream page allowing you to change the sort order of the page between \'recently commented\' (default), \'posted order\', or \'unthreaded\' which displays single activities as received.');

		$text = '<div class="section-content-info-wrapper">' . $desc . '</div>';


		return $text;

	}

}
