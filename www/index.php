<?php

require_once("database.inc.php");

function get_var($varname) {
	if (isset($_REQUEST[$varname]) && !empty($_REQUEST[$varname])) {
		return intval($_REQUEST[$varname]);
	}
	return false;
}

$maillist = "";

$link_state = "showignored=0";
$showignored = false;
$showall = false;

if (get_var('showignored') === 1) {
	$showignored = true;
	$link_state = "showignored=1";
}
if (get_var('showall') === 1) {
	$showall = true;
	$showignored = true;
	$link_state = "showall=1";
}

$pg = pg_connect("host=$host port=$port user=$user password=$pass dbname=$db");

$result = pg_prepare('do_unignore', 'UPDATE beratung SET ignore=FALSE WHERE id=$1;');
$result = pg_prepare('do_ignore', 'UPDATE beratung SET ignore=TRUE WHERE id=$1;');
if ($showall) {
	$result = pg_prepare('all_mails', 'SELECT id,subject,sender,maildate,ignore,mailid,replyid FROM beratung WHERE isreply is FALSE ORDER BY maildate DESC;');
} else {
	$result = pg_prepare('all_mails', 'SELECT id,subject,sender,maildate,ignore,mailid,replyid FROM beratung WHERE replyid is NULL AND isreply is FALSE ORDER BY maildate DESC;');
}
$result = pg_prepare('sum_mails', "SELECT COUNT(*) as num FROM beratung WHERE (maildate + INTERVAL '4 weeks') > now();");

$query = (get_var('unignore') !== false) ? pg_execute('do_unignore', array(get_var('unignore'))) : true;
$query = (get_var('ignore') !== false) ? pg_execute('do_ignore', array(get_var('ignore'))) : true;

if ($showall) {
	$maillist .=  '<a href="index.php?showignored=0">Show open mails</a> '."\n";
	$maillist .=  '<a href="index.php?showignored=1">Show open and ignored mails</a> '."\n";
	$maillist .=  'Show all mails'."\n";
} elseif ($showignored) {
	$maillist .=  '<a href="index.php?showignored=0">Show open mails</a> '."\n";
	$maillist .=  'Show open and ignored mails '."\n";
	$maillist .=  '<a href="index.php?showall=1">Show all mails</a>'."\n";
} else {
	$maillist .=  'Show open mails '."\n";
	$maillist .=  '<a href="index.php?showignored=1">Show open and ignored mails</a> '."\n";
	$maillist .=  '<a href="index.php?showall=1">Show all mails</a>'."\n";
}

$query = pg_execute('all_mails', array());
$num_mails = pg_execute('sum_mails', array());

$total_mails_last_4_weeks = 0;
if (($sum_mails = pg_fetch_object($num_mails)) != null) {
	$total_mails_last_4_weeks = $sum_mails->num;
}

$curlvl = 0;
$levels = array(
	0 => array('until'=>0, 'class'=>'openmail-future', 'text'=>'In the future'),
	1 => array('until'=>2*24*3600, 'class'=>'openmail-2d', 'text'=>'Last two days'),
	2 => array('until'=>7*24*3600, 'class'=>'openmail-1w', 'text'=>'Last week'),
	3 => array('until'=>2*7*24*3600, 'class'=>'openmail-2w', 'text'=>'Last two weeks'),
	4 => array('until'=>4*7*24*3600, 'class'=>'openmail-4w', 'text'=>'Last four weeks'),
	4 => array('until'=>8*7*24*3600, 'class'=>'openmail-8w', 'text'=>'Last eight weeks'),
	4 => array('until'=>365*24*3600, 'class'=>'openmail-1y', 'text'=>'Before our time'),
);

$curtable = "";

$total_unanswered_mails = 0;
$curts = time();

while (($res = pg_fetch_object($query)) != null) {
	$timestamp = strtotime($res->maildate);
	while (($curlvl < 4) && ($curts - $levels[$curlvl]['until'] > $timestamp)) {
		$curlvl++;
		if ($curtable != "") {
			$maillist .= "<table border='1'>" . $curtable . "</table>\n";
			$curtable = "";
		}
		$maillist .=  "<h1>".$levels[$curlvl]['text']."</h1>\n";
	}
	if ($res->ignore !== 't' && $res->replyid === null) {
		$total_unanswered_mails++;
		$curtable .= "<tr class='openmail ".$levels[$curlvl]['class']."'>\n";
		$curtable .=  "<td class='mtools'><a href='index.php?".$link_state."&ignore=".$res->id."' title='mark as resolved'>✔</a></td>\n";
		$curtable .=  "<td class='mdate'>".date('Y-m-d H:i', $timestamp)."</td>\n";
		$curtable .=  "<td class='msubject'>".htmlspecialchars(iconv_mime_decode($res->subject))."</td>\n";
		$curtable .=  "<td class='msender'>".htmlspecialchars(iconv_mime_decode($res->sender))."</td>\n";
		$curtable .= "</tr>\n";
	} elseif ($showignored && $res->replyid === null) {
		$curtable .= "<tr class='openmail openmail-ignored'>\n";
		$curtable .=  "<td class='mtools'><a href='index.php?".$link_state."&unignore=".$res->id."' title='mark as unresolved'>✔</a></td>\n";
		$curtable .=  "<td class='mdate'>".date('Y-m-d H:i', $timestamp)."</td>\n";
		$curtable .=  "<td class='msubject'>".htmlspecialchars(iconv_mime_decode($res->subject))."</td>\n";
		$curtable .=  "<td class='msender'>".htmlspecialchars(iconv_mime_decode($res->sender))."</td>\n";
		$curtable .= "</tr>\n";
	} elseif ($showall) {
		$curtable .= "<tr class='openmail openmail-any'>\n";
		$curtable .=  "<td class='mtools'>✔</td>\n";
		$curtable .=  "<td class='mdate'>".date('Y-m-d H:i', $timestamp)."</td>\n";
		$curtable .=  "<td class='msubject'>".htmlspecialchars(iconv_mime_decode($res->subject))."</td>\n";
		$curtable .=  "<td class='msender'>".htmlspecialchars(iconv_mime_decode($res->sender))."</td>\n";
		$curtable .= "</tr>\n";
	}
}

if ($curtable != "") {
	$maillist .= "<table border='1'>" . $curtable . "</table>\n";
}

echo "<!DOCTYPE html>
<html><head>
<title>unanswered mails: $total_unanswered_mails</title>
<link rel='stylesheet' type='text/css' href='style.css' />
</head><body>
<p>Total mails in the last four weeks: <em>$total_mails_last_4_weeks</em>, number of unanswered mails: <em>$total_unanswered_mails</em></p>
$maillist
</body></html>";

