<?php

/**
 * Name: phpmailer
 * Description: use phpmailer instead of built-in mail() function
 * Version: 1.0
 * Author: Mike Macgirvin <mike@macgirvin.com>
 * Maintainer: Mike Macgirvin <mike@macgirvin.com>
 */



function phpmailer_load() {
	\Zotlabs\Extend\Hook::register('email_send','addon/phpmailer/phpmailer.php','phpmailer_email_send');
}


function phpmailer_unload() {
	\Zotlabs\Extend\Hook::unregister_by_file('addon/phpmailer/phpmailer.php');
}



function phpmailer_email_send(&$x) {


	/**
	 * @brief Send a multipart/alternative message with Text and HTML versions.
	 *
	 * @param array $params an associative array with:
	 *  * \e string \b fromName        name of the sender
	 *  * \e string \b fromEmail       email of the sender
	 *  * \e string \b replyTo         replyTo address to direct responses
	 *  * \e string \b toEmail         destination email address
	 *  * \e string \b messageSubject  subject of the message
	 *  * \e string \b htmlVersion     html version of the message
	 *  * \e string \b textVersion     text only version of the message
	 *  * \e string \b additionalMailHeader  additions to the smtp mail header
	 */

	require('addon/phpmailer/PHPMailerAutoload.php');

	$mail = new PHPMailer;

	$s = intval(get_config('phpmailer','smtpdebug'));
	if($s) {
		// 1: debug client
		// 2: debug server (most useful setting)
		// 3: debug connection (useful for STARTTLS issues)
		// 4: debug lowlevel (very verbose)

		$mail->SMTPDebug = intval($s);
		$mail->Debugoutput = function($str,$level) { logger('phpmailer: ' . $str); };	
	}


	$mail->Hostname = \App::get_hostname();

	if(get_config('phpmailer','mailer') === 'smtp') {
		$mail->IsSMTP();
		$mail->Mailer = "smtp";

		$s = get_config('phpmailer','host');
		if($s) 
			$mail->Host = $s;
		else
			$mail->Host = 'localhost';

		$s = get_config('phpmailer','port');
		if($s) 
			$mail->Port = $s;
		else
			$mail->Port = '25';

		$s = get_config('phpmailer','smtpsecure');
		if($s) 
			$mail->SMTPSecure = $s;

		$s = get_config('phpmailer','smtpauth');
		if($s) 
			$mail->SMTPAuth = (boolean) $s;

		$s = get_config('phpmailer','username');
		if($s) 
			$mail->Username = $s;

		$s = get_config('phpmailer','password');
		if($s) 
			$mail->Password = $s;


		$s = intval(get_config('phpmailer','noverify'));
		if($s) {
			$mail->SMTPOptions = [ 'ssl' => [ 
				'verify_peer' => false, 
				'verify_peer_name' => false, 
				'allow_self_signed' => true ]
			];
		}

	}
	else {    

		$mail->isSendmail();

		$s = intval(get_config('phpmailer','usesendmailoptions'));
		if($s)
			$mail->UseSendmailOptions = (boolean) $s;



	}


	$mail->setFrom($x['fromEmail'],$x['fromName']);
	$mail->addReplyTo($x['replyTo']);
	$mail->addAddress($x['toEmail']);
	$mail->Subject = $x['messageSubject'];

	if($x['htmlVersion']) {
		$mail->isHTML(true);
		$mail->Body = $x['htmlVersion'];
		$mail->AltBody = $x['textVersion'];
	}
	else {
		$mail->isHTML(false);
		$mail->Body = $x['textVersion'];
	}

	$result = $mail->send();

	if(! $result)
		logger('phpmailer: ' . $mail->ErrorInfo);

	$x['sent'] = true;
	$x['result'] = $result;



}


