<?php

/**
 * Name: Chord Generator
 * Description: Guitar Chord Generator Application
 * Version: 1.0
 * Author: Mike Macgirvin <mike@zothub.com>
 * Maintainer: none
 */


function chords_load() {
	register_hook('app_menu', 'addon/chords/chords.php', 'chords_app_menu');
}

function chords_unload() {
	unregister_hook('app_menu', 'addon/chords/chords.php', 'chords_app_menu');

}

function chords_app_menu($a,&$b) {
	$b['app_menu'][] = '<div class="app-title"><a href="chords">Guitar Chords</a></div>'; 
}


function chords_module() {}


function chords_content($a) {


$args = '';
$l = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
  if(isset($_POST['chord']) && strlen($_POST['chord']))
    $args .= escapeshellarg(ucfirst(trim($_POST['chord'])));
  if((strlen($args)) && (isset($_POST['tuning'])) && (strlen($_POST['tuning'])))
      $args .= ' '.escapeshellarg($_POST['tuning']);
  if((strlen($args)) && (isset($_POST['lefty'])))
      $args .= ' lefty';
}


	if((! $_POST['chord']) && argc() > 1) {
		$_REQUEST['chord'] = argv(1);
		$args = escapeshellarg(ucfirst(basename(argv(1))));
	}
 

	$tunings = [
		''       => 'Em11 [Standard] (EADGBE)',
		'openg'  => 'G/D [Drop D] (DGDGBD)',
		'opene'  => 'Open E (EBEG#BE)',
		'dadgad' => 'Dsus4 (DADGAD'
	];


	if(strlen($args)) {
	  $chords =  '<pre>';
	  $chords .= shell_exec("addon/chords/chord ".$args);
	  $chords .=  '</pre>';
	}
	else {

		$chords .=  <<< EOT

<p class="descriptive-text">
This is a fairly comprehensive and complete guitar chord dictionary which will list most of the available ways to play a certain chord, starting from the base of the fingerboard up to a few frets beyond the twelfth fret (beyond which everything repeats). A couple of non-standard tunings are provided for the benefit of slide players, etc. 
<p />
<p class="descriptive-text">
Chord names start with a root note (A-G) and may include sharps (#) and flats (b). This software will parse most of the standard naming conventions such as maj, min, dim, sus(2 or 4), aug, with optional repeating elements.
</p>
<p class="descriptive-text">
Valid examples include  A, A7, Am7, Amaj7, Amaj9, Ammaj7, Aadd4, Asus2Add4, E7b13b11 ...
</p>
Quick Reference:<br />

EOT;

$keys = array('A','Bb','B', 'C','Db','D','Eb','E','F','Gb','G','Ab');
$chords .=  '<table border="1">';
$chords .=  "<tr>";
foreach($keys as $k)
  $chords .=  "<td><a href=\"chords/$k\"> $k </a></td>";
$chords .=  "</tr><tr>";
foreach($keys as $k)
  $chords .=  "<td><a href=\"chords/{$k}m\"> {$k}m </a></td>";
$chords .=  "</tr><tr>";
foreach($keys as $k)
  $chords .=  "<td><a href=\"chords/{$k}7\"> {$k}7 </a></td>";
$chords .=  "</tr>";
$chords .=  "</table>";

}



	$o .= replace_macros(get_markup_template('chords.tpl','addon/chords'), [
		'$header' => t('Guitar Chords'),
		'$desc'   => t('The complete online chord dictionary'),
		'$chords' => $chords,
		'$tuning' => [ 'tuning', t('Tuning'), $_POST['tuning'], '', $tunings ],
		'$chord'  => [ 'chord', t('Chord name: example: Em7'), $_REQUEST['chord'], '' ],
		'$lefty'  => [ 'lefty', t('Show for left handed stringing'), $_POST['lefty'], '' ],
		'$submit' => t('Submit'),
		]
	);

	return $o;

}










