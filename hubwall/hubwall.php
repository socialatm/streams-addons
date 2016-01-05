<?php

/**
 *
 * Name: Hubwall
 * Description: Send admin email message to all account holders
 * Version: 1.0
 * Author: Mike Macgirvin
 * Maintainer: none
 */

require_once('include/enotify.php');

function hubwall_module() {}



function hubwall_post(&$a) {
	if(! is_site_admin())
		return;

	$text = trim($_REQUEST['text']);
	if(! $text)
		return;

	$sender_name = t('Hub Administrator');
	$sender_email = 'sys@' . $a->get_hostname();

	$subject = $_REQUEST['subject'];


	$textversion = strip_tags(html_entity_decode(bbcode(stripslashes(str_replace(array("\\r", "\\n"),array( "", "\n"), $text))),ENT_QUOTES,'UTF-8'));

	$htmlversion = bbcode(stripslashes(str_replace(array("\\r","\\n"), array("","<br />\n"),$text)));


	$recips = q("select account_email from account where account_flags = %d",
		intval(ACCOUNT_OK)
	);

	if(! $recips) {
		notice( t('No recipients found.') . EOL);
		return;
	}
	
	foreach($recips as $recip) {


		enotify::send(array(
			'fromName'             => $sender_name,
			'fromEmail'            => $sender_email,
			'replyTo'              => $sender_email,
			'toEmail'              => $recip['account_email'],
			'messageSubject'       => $subject,
			'htmlVersion'          => $htmlversion,
			'textVersion'          => $textversion
		));
	}

}

function hubwall_content(&$a) {
	if(! is_site_admin())
		return;

	$title = t('Send email to all hub members.');

	$o = replace_macros(get_markup_template('hubwall_form.tpl','addon/hubwall/'),array(
		'$title' => $title,
		'$subject' => array('subject',t('Message subject'),'',''),
		'$submit' => t('Submit')
	));

	return $o;

}