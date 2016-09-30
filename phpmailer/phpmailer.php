<?php

/**
 * Name: phpmailer
 * Description: use phpmailer instead of built-in mail() function
 * Version: 1.0
 * Author: Mike Macgirvin <mike@macgirvin.com>
 * Maintainer: Mike Macgirvin <mike@macgirvin.com>
 */



function phpmailer_install() {
	\Zotlabs\Extend\Hook::register('enotify_send','addon/phpmailer/phpmailer.php','phpmailer_enotify_send');
}


function phpmailer_uninstall() {
	\Zotlabs\Extend\Hook::unregister_by_file('addon/phpmailer/phpmailer.php');
}



function phpmailer_enotify_send(&$x) {

	logger('test');

	/**
	 * @brief Send a multipart/alternative message with Text and HTML versions.
	 *
	 * @param array $params an assoziative array with:
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

	$mail->isSendmail();

	$mail->setFrom($x['fromEmail'],$x['fromName']);
	$mail->addReplyTo($x['replyTo']);
	$mail->addAddress($x['toEmail']);
	$mail->isHTML(true);
	$mail->Subject = $x['messageSubject'];
	$mail->Body = $x['htmlVersion'];
	$mail->AltBody = $x['textVersion'];

	$result = $mail->send();

	if(! $result)
		logger('phpmailer: ' . $mail->ErrorInfo);

	$x['sent'] = true;
	$x['result'] = $result;



}


