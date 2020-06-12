<?php

namespace Zotlabs\Module;

use Zotlabs\Access\AccessControl;

/**
 * separate ZAP and Hubzilla
 */
class ZapHubSpecific {

	function setACL($aclArr, $channel) {

		$acl = new AccessControl($channel); // this on is specific to ZAP

		$acl->set_from_array($aclArr);

		$x = $acl->get();

		return $x;
	}
}