<?php


class HTTPSig {

	// See RFC5843

	static function generate_digest($body,$set = true) {
		$digest = base64_encode(hash('sha256',$body,true));

		if($set) {
			header('Digest: SHA-256=' . $digest);
		}
		return $digest;
	}

	// See draft-cavage-http-signatures-07

	static function verify($data,$key) {

		$body = $data;
		$headers = null;

		// decide if $data arrived via controller submission or curl
		if($data['header']) {
			if(! $data['success'])
				return false;
			$headers = $data['header'];
			$body = $data['body'];



		}
		else {
			$headers = [];
			$headers['(request-target)'] = 
				$_SERVER['REQUEST_METHOD'] . ' ' .
				$_SERVER['REQUEST_URI']    . ' ' . 
				$_SERVER['SERVER_PROTOCOL'];
			foreach($_SERVER as $k => $v) {
				if(strpos($k,'HTTP_') === 0) {
					$field = str_replace('_','-',strtolower(substr($k,5)));
					$headers[$field] = $v;
				}
			}
					

		}




	}


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

	static function parse_sigheader($header) {
		$ret = [];
		$matches = [];
		if(preg_match('keyId="(.*?)"/ism',$header,$matches))
			$ret['keyId'] = $matches[1];
		if(preg_match('algorithm="(.*?)"/ism',$header,$matches))
			$ret['algorithm'] = $matches[1];
		if(preg_match('headers="(.*?)"/ism',$header,$matches))
			$ret['headers'] = explode(' ', $matches[1]);
		if(! $ret['headers'])
			$ret['headers'] = [ 'date' ];
		if(preg_match('signature="(.*?)"/ism',$header,$matches))
			$ret['signature'] = base64_decode(preg_replace('/\s+/','',$matches[1]));

 		return $ret;
	}


}


