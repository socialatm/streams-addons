<?php

namespace Zotlabs\Module;

function getStatisticsAsHTML() {

	$r = getStatistics();

	if (!$r) {
		return "";
	}

	$html = "<table>";
	$html .= "<tr>"
			. "<th>channel</th>"
			. "<th>finder</th>"
			. "<th>recognized</th>"
			. "<th>verified</th>"
			. "<th>unkown</th>"
			. "<th>ignored</th>"
			. "</tr>";

	foreach ($r as $row) {
		$html .= "<tr>"
				. "<td>" . $row[0] . "</td>"
				. "<td>" . $row[1] . "</td>"
				. "<td>" . $row[2][0]['verified'] . "</td>"
				. "<td>" . $row[2][0]['recognized'] . "</td>"
				. "<td>" . $row[2][0]['unkown'] . "</td>"
				. "<td>" . $row[2][0]['ignored'] . "</td>"
				. "</tr>";
	}

	$html .= "</table>";

	return $html;
}

function getStatistics() {

	$r = q("SELECT faces_encoding.channel_id, faces_encoding.finder, channel.channel_address FROM faces_encoding JOIN channel ON faces_encoding.channel_id = channel.channel_id GROUP BY faces_encoding.channel_id, faces_encoding.finder");

	if (!$r) {
		return false;
	}

	$stats = array();

	foreach ($r as $row) {
		$stat = getStatisticsFinderChannel($row['finder'], $row['channel_id']);
		if ($stat) {
			$stats[] = [$row['channel_address'], $row['finder'], $stat];
		}
	}

	return $stats;
}

function getStatisticsChannelAsHTML($channel_id) {

	$r = getStatisticsChannel($channel_id);

	if (!$r) {
		return "";
	}

	$html = "<table>";
	$html .= "<tr>"
			. "<th>finder</th>"
			. "<th>recognized</th>"
			. "<th>verified</th>"
			. "<th>unkown</th>"
			. "<th>ignored</th>"
			. "</tr>";

	foreach ($r as $row) {
		$html .= "<tr>"
				. "<td>" . $row[0] . "</td>"
				. "<td>" . $row[1][0]['verified'] . "</td>"
				. "<td>" . $row[1][0]['recognized'] . "</td>"
				. "<td>" . $row[1][0]['unkown'] . "</td>"
				. "<td>" . $row[1][0]['ignored'] . "</td>"
				. "</tr>";
	}

	$html .= "</table>";

	return $html;
}

function getStatisticsChannel($channel_id) {

	$r = q("SELECT finder FROM faces_encoding GROUP BY finder");

	if (!$r) {
		return false;
	}

	$stats = [];

	foreach ($r as $row) {
		$stat = getStatisticsFinderChannel($row['finder'], $channel_id);
		if ($stat) {
			$stats[] = [$row['finder'], $stat];
		}
	}

	return $stats;
}

function getStatisticsFinderChannel($finder, $channel_id) {

	$r = q("SELECT "
			. "SUM( "
			. "    CASE WHEN person_recognized != 0 THEN 1 ELSE 0 "
			. "END "
			. ") AS 'recognized', "
			. "SUM( "
			. "    CASE WHEN person_verified != 0 THEN 1 ELSE 0 "
			. "END "
			. ") AS 'verified', "
			. "SUM( "
			. "    CASE WHEN person_marked_unknown != 0 THEN 1 ELSE 0 "
			. "END "
			. ") AS 'unkown', "
			. "SUM( "
			. "    CASE WHEN marked_ignore != 0 THEN 1 ELSE 0 "
			. "END "
			. ") AS 'ignored' "
			. "FROM "
			. "    faces_encoding "
			. "WHERE "
			. "    finder = %d AND channel_id = %d ", //
			intval($finder), //
			intval($channel_id) //
	);

	if (!$r) {
		return false;
	}

	return $r;
}
