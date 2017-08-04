<?php


class HTTPSig {

	// See RFC5843

	static function generate_digest($body) {
		$digest = base64_encode(hash('sha256',$body,true));
		header('Digest: SHA-256=' . $digest);
	}

	// See draft-cavage-http-signatures-07

	static function sign($head,$body,$prvkey,$keyid = 'key') {
		$fields = '';
		if(head) {
			foreach($head as $k => $v) {
				$headers = strtolower($k) . ': ' . trim($v) . "\n";
				if($fields)
					$fields .= ' ';
				$fields .= strtolower($k);
			}
			$headers = rtrim($headers);

		}
		$body = trim($body);

	}


}