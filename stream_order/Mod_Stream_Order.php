<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libsync;

class Stream_order extends \Zotlabs\Web\Controller {


	function get() {


		$desc = t('This addon app provides a selector on your network stream page allowing you to change the sort order of the page between \'recently commented\' (default), \'posted order\', or \'unthreaded\' which displays single activities as received.');

		$text = '<div class="section-content-info-wrapper">' . $desc . '</div>';


		return $text;

	}

}
