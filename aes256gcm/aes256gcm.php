<?php

/**
 * Name: AES256GCM
 * Description: Authenticated encryption for Zot pro
 * Version: 1.0
 * ServerRoles: pro
 *
 */


function aes256gcm_load() {
	\Zotlabs\Extend\Hook::register('crypto_methods','addon/aes256gcm/aes256gcm.php','aes256gcm_crypto_methods');
	\Zotlabs\Extend\Hook::register('other_encapsulate','addon/aes256gcm/aes256gcm.php','aes256gcm_other_encapsulate');
	\Zotlabs\Extend\Hook::register('other_unencapsulate','addon/aes256gcm/aes256gcm.php','aes256gcm_other_unencapsulate');
}

function aes256gcm_unload() {
	\Zotlabs\Extend\Hook::unregister_by_file('addon/aes256gcm/aes256gcm.php');
}


function aes256gcm_crypto_methods(&$x) {
	array_unshift( $x , 'aes256gcm' );
}

function aes256gcm_other_encapsulate(&$a) {

		if($a['alg'] !== 'aes256gcm')
			return;

 		$key = openssl_random_pseudo_bytes(256);
		$iv  = openssl_random_pseudo_bytes(256);

		$result = [];

		$result['data'] = base64url_encode(AES256CGM_encrypt($a['data'],$key,$iv),true);
		// log the offending call so we can track it down
		if(! openssl_public_encrypt($key,$k,$a['pubkey'])) {
			$x = debug_backtrace();
			logger('RSA failed. ' . print_r($x[0],true));
		}

		$result['alg'] = $a['alg'];
		$result['key'] = base64url_encode($k,true);
		openssl_public_encrypt($iv,$i,$a['pubkey']);
		$result['iv'] = base64url_encode($i,true);
		$a['result'] = $result;

}

function aes256gcm_other_unencapsulate(&$a) {

	if($a['alg'] !== 'aes256gcm')
		return;

	openssl_private_decrypt(base64url_decode($a['data']['key']),$k,$a['prvkey']);
	openssl_private_decrypt(base64url_decode($a['data']['iv']),$i,$a['prvkey']);

	$a['result'] = AES256GCM_decrypt(base64url_decode($a['data']['data']),$k,$i);

}



function AES256GCM_encrypt($data,$key,$iv) {
    $key = substr($key,0,32);
    $iv  = substr($iv,0,16);
    return openssl_encrypt($data,'aes-256-gcm',str_pad($key,32,"\0"),OPENSSL_RAW_DATA,str_pad($iv,16,"\0"));
}

function AES256GCM_decrypt($data,$key,$iv) {
    $key = substr($key,0,32);
    $iv  = substr($iv,0,16);
    return openssl_decrypt($data,'aes-256-gcm',str_pad($key,32,"\0"),OPENSSL_RAW_DATA,str_pad($iv,16,"\0"));
}


