<?php
include_once 'diff.function.php'; /* The diff function. */
include_once 'wikibot.classes.php'; /* The wikipedia classes. */

$data = "";

$user = $config['wikiuser'];
$pass = $config['wikipass'];

$wpapi  = new wikipediaapi;
$wpq    = new wikipediaquery;
$wpi    = new wikipediaindex;
        
$edits = 0;

var_export($wpapi->login($user,$pass));

$data = $wpq->getpage("Wikipedia:Administrators' noticeboard/Edit warring");
#$data = $wpq->getpage("User:NekoBot/Sandbox");
$pos = strpos($data, "{{admin backlog|bot=NekoBot}}");
$countreport = substr_count($data, '(Result: )');
if (strpos(preg_replace('/<noinclude\b[^>]*>(.*?)<\/noinclude>/', '', $wpq->getpage('User:'.$user.'/Run')), 'yes') === false) $noedit = 1;

if ($pos !== false) {
	if ($countreport > 3) {
		echo "Still backlogged. Exiting.";
		$this->send_data('PRIVMSG '.$config['channel'], ':ALERT: Edit Warring noticeboard is backlogged! ('.$countreport.' pending reports.)');
		$dataedit = $data;
	} else {
		$dataedit = str_replace("{{admin backlog|bot=NekoBot}}", "", $data);
		$summary = "Backlog Cleared. Removing {{admin backlog}}";
		$this->send_data('PRIVMSG '.$config['channel'], ':INFO: Edit Warring backlog cleared. Removing {{admin backlog}} ('.$countreport.' pending reports.)');
		$loginfo = "Removed backlog tag ";
		$modnote = 1;
	}
} else {
	if ($countreport < 4) {
		echo "Noticeboard is not backlogged. Exiting.";
		$this->send_data('PRIVMSG '.$config['channel'], ':INFO: Edit Warring noticeboard is not backlogged. ('.$countreport.' pending reports.)');
		$dataedit = $data;
	} else {
		$dataedit = '{{admin backlog|bot=NekoBot}}'.$data;
		$summary = "Noticeboard Backlogged. Alerting Admins via {{admin backlog}}";
		$this->send_data('PRIVMSG '.$config['channel'], ':ALERT: Edit Warring noticeboard is backlogged! ('.$countreport.' pending reports.)');
		$loginfo = "Adding backlog tag ";
		$modnote = 1;
	}
}

#$summary = 'NekoTraining: '.$summary;

$query = mysql_query("SELECT * FROM `core`");
$info = mysql_fetch_array($query);
#if ($info['edits'] >= 10) $noedit = 1;

if ($noedit != 1) {
	$length = strlen($data);
	$dlength = strlen($dataedit);
	$diff = $dlength - $length;
	$check = $wpq->getpage("Wikipedia:Administrators' noticeboard/Edit warring");

	if ($length == strlen($check)) {
		if ($dlength == $length) {
			$reason = 'No data changed ('.$dlength.' matches '.$length.')';
		} else {
			$positive = 1; // Allow submission
		}
	} else {
		$reason = 'Edit Conflict';
	}

	if ($modnote != 1) {
		$this->send_data('PRIVMSG '.$config['channel'], ':INFO: Modifying WP:AN/EW not required. [Reason: '.$reason.']');
	} elseif ($positive != 1) {
		$this->send_data('PRIVMSG '.$config['channel'], ':FATAL: String Validation FAILED ['.strlen($check).', expected: '.$length.'] [Diff: '.$diff.']');
	} else {
		$wpapi->edit("Wikipedia:Administrators' noticeboard/Edit warring", $dataedit, $summary, '', false);
		$this->send_data('PRIVMSG '.$config['channel'], ':INFO: Modifying WP:AN/EW: '.$summary.'.');		
		echo "Edit Complete: $summary";
		$edits = $edits + 1;
	}
} else {
	$whodidit = $wpapi->revisions('User:'.$user.'/Run');
	$user = $whodidit[0]['user'];
	$comment = $whodidit[0]['comment'];
	$this->send_data('PRIVMSG '.$config['channel'], ':INFO: NekoBot was disabled by: '.$user.' for '.$comment.'!');
}

echo "Mission Accomplished!";

$data = $wpq->getpage("Wikipedia:Administrators' noticeboard/Edit warring");
$toberemoved = array("<!-- Place name of the user you are reporting here -->");
$data = str_replace($toberemoved, "", $data);
#$data = $wpq->getpage("User:NekoBot/Sandbox");
$test = explode("== ", $data);
$count = 0;
$count = count($test);
echo ("\n|".$count."|\n");
$start = 1;
for ($start = 1; $start <= $count; $start += 1) {
	$test = explode("== ", $data);
	$testa = explode(" reported by", $test[$start]);
	#echo $testa[0]; // Username
	#echo " - ";
	$status = explode("(Result: ", $test[$start]);
	$status = explode(")", $status[1]);
	#echo $status[0]; // Status
	#echo "\n";
	// Format link
	$te = explode(" ==", $test[$start]);
	echo $te[0];
	$replace = array("(", ")", " ", "[", "]");
	$replaced = array(".28", ".29", "_", "", "");
	$link = str_replace($replace, $replaced, $te[0]);
	
	// Report Template: {{User:NekoBot/3RRAttn}}
	$user = $testa[0];
	$user = rtrim(ltrim(str_replace(array("[[User:", "]]"), "", $user)));
	$status = $status[0];

	$mdata = $wpq->getpage("Wikipedia:Administrators' noticeboard/Edit warring", $start);
	$toberemoved = array("<!-- Place name of the user you are reporting here -->");
	$mdata = str_replace($toberemoved, "", $mdata);
	
	if ($user == "" || $user == NULL || !$user || $noedit == 1 || strpos($testa[0], 'User:') === false || strpos($te[0], ' reported by') === false) {
		if (strpos($mdata, '~ [[User:NekoBot|NekoBot]]') === false && $user != "" && $user != NULL) {
			if ((strpos($testa[0], 'User:') === false || strpos($data, ' reported by') === false) && $te[0] != "") {
				echo("\nALERT: ".$te[0]." is malformed.");
				$malformed = $mdata . "\n\n:[[File:Pictogram_voting_info.svg|20px|link=]] '''Malformed''' &ndash; The report is formatted in a way that is unreadable by the automated processing system. Please ensure the report header and body follow the guidelines. Refer to the [[User:NekoBot/FAQ#Malformed|FAQ]] for more information. ~~~~";
				$wpapi->edit("Wikipedia:Administrators' noticeboard/Edit warring", $malformed, 'Malformed Report', '', false, $start);
				$this->send_data('PRIVMSG '.$config['channel'], ':ALERT: '.$te[0].' (Section: '.$start.') is malformed.');
			} else {
				$malformed = $mdata . "\n\n:[[File:Pictogram_voting_delete.svg|20px|link=]] '''Malformed''' &ndash; The report is misformatted, or does not contain the information required by the report template. Please edit the report and remove any <nowiki><!-- --></nowiki> tags and enter any missing data. Refer to the [[User:NekoBot/FAQ#Malformed|FAQ]] for more information. ~~~~";
				$wpapi->edit("Wikipedia:Administrators' noticeboard/Edit warring", $malformed, 'Malformed Report', '', false, $start);
				$this->send_data('PRIVMSG '.$config['channel'], ':ALERT: '.$te[0].' (Section: '.$start.') is malformed/missing info.');
			}
			$loginfo .= "Logging malformed report (ID: ".$start.") ";
		}
	} else {
	#$this->send_data('PRIVMSG #nekopedia', ':Run '.$start);
	#$this->send_data('PRIVMSG #nekopedia', $user);
	if (strpos($testa[0], '/') !== false || strpos($testa[0], ' and ') !== false || strpos($testa[0], ',') !== false) {
		$user = $testa[0];
		$user = str_replace(array("/", " and ", "&", ","), "+=+", $user);
		$split = explode("+=+", $user);
		$ncount = count($split);
		$i = 0;
		while ($ncount > $i) {
			if (isset($split)) {
				if (strpos($split[$i], 'User:') !== false) {
					$user = rtrim(ltrim(str_replace(array("[[User:", "]]"), "", $split[$i])));
					echo $user;
					sleep(3);
				}
			}
			if ($status == NULL || !isset($status)) { $status = "0"; }
			$query = mysql_query("SELECT * FROM `3rrusers` WHERE `username` = '".$user."' AND `status` = '".$status."'") or $this->send_data('QUIT', 'There was an error with MySQL:'.mysql_error());
			if(mysql_num_rows($query) == 0) {
				$insert = mysql_query("INSERT INTO `3rrusers` (id, username, status, count) VALUES ('', '$user', '$status', 1)") or $this->send_data('QUIT', 'MySQL Error: '.mysql_error());
				$info = $wpq->getpage('User Talk:'.$user);
				$link = "WP:AN/EW#".$link;
				$info = "{{subst:User:NekoBot/3RRAttn|".$link."}}";
				$summary = "Notification of [[WP:AN/EW]] report";
				if ($status == "0") { // Has the user got a verdict yet?
					$wpapi->edit('User Talk:'.$user, $info, $summary, '', false, 'new'); // Nope, lets tell them they've been reported
					$this->send_data('PRIVMSG '.$config['channel'], ':'.$user.': Adding to Database, Adding notice to talkpage');
					#$this->send_data('PRIVMSG #nekopedia', $link);
					$loginfo .= "Notifying ".$user.". ";
					$edits = $edits + 1;
				} else {
					$this->send_data('PRIVMSG '.$config['channel'], ':'.$user.': Adding to Database, User already has verdict: '.$status);
				}
			} else {
				$theinfo = mysql_fetch_array($query) or $this->send_data('QUIT', 'There was an error with MySQL:'.mysql_error());
				if ($status == NULL || !isset($status)) { $status = "0"; }
				if ($theinfo['status'] != $status) {
					$count = $theinfo['count'] + 1;
					$update = mysql_query("UPDATE `3rrusers` SET `status` = '$status', `count` = '$count' WHERE `username` = '$user'");
					$this->send_data('PRIVMSG '.$config['channel'], ':'.$user.': Updating in Database, User has verdict: '.$status);
				}
			}
		$i++;
		}
	} else {
		if ($status == NULL || !isset($status)) { $status = "0"; }
		$query = mysql_query("SELECT * FROM `3rrusers` WHERE `username` = '".$user."' AND `status` = '".$status."'") or $this->send_data('QUIT', 'There was an error with MySQL:'.mysql_error());
		if(mysql_num_rows($query) == 0) {
			$insert = mysql_query("INSERT INTO `3rrusers` (id, username, status, count) VALUES ('', '$user', '$status', 1)") or $this->send_data('QUIT', 'MySQL Error: '.mysql_error());
			$info = $wpq->getpage('User Talk:'.$user);
			$link = "WP:AN/EW#".$link;
			$info = "{{subst:User:NekoBot/3RRAttn|".$link."}}";
			$summary = "Notification of [[WP:AN/EW]] report";
			if ($status == "0") { // Has the user got a verdict yet?
				$wpapi->edit('User Talk:'.$user, $info, $summary, '', false, 'new'); // Nope, lets tell them they've been reported
				$this->send_data('PRIVMSG '.$config['channel'], ':'.$user.': Adding to Database, Adding notice to talkpage');
				#$this->send_data('PRIVMSG #nekopedia', $link);
				$loginfo .= "Notifying ".$user.". ";
				$edits = $edits + 1;
			} else {
				$this->send_data('PRIVMSG '.$config['channel'], ':'.$user.': Adding to Database, User already has verdict: '.$status);
			}
		} else {
			if ($status == NULL || !isset($status)) { $status = "0"; }
			$query = mysql_query("SELECT * FROM `3rrusers` WHERE `username` = '$user' AND `status` = '$status' LIMIT 1");
			$theinfo = mysql_fetch_array($query) or $this->send_data('QUIT', 'There was an error with MySQL:'.mysql_error());
				if ($theinfo['status'] != $status) {
					$count = $theinfo['count'] + 1;
					$update = mysql_query("UPDATE `3rrusers` SET `status` = '$status', `count` = '$count' WHERE `username` = '$user'");
					$this->send_data('PRIVMSG '.$config['channel'], ':'.$user.': Updating in Database, User has verdict: '.$status);
				}
			}
		}
	}
}
	$start = $start - 1;
	$this->send_data('PRIVMSG '.$config['channel'], ':Completed '.$start.' out of '.$count.' cycles');
	#echo "Completed.";
if (isset($loginfo) && $noedit != 1) {
        $summary = "Updating NekoBot Log";
	$user = "NekoBot";
        $getdata = $wpq->getpage("User:NekoBot/Log");
        $logdata = $getdata.'\n\n'.$loginfo.' ~~~~';
        $wpapi->edit('User:'.$user.'/Log', $logdata, $summary, '', false);
	$query = mysql_query("SELECT * FROM `core`");
	$info = mysql_fetch_array($query);
	$tedits = $edits + $info['edits'];
	$query = mysql_query("UPDATE `core` SET `edits` = '$tedits'");
}

$nextcheck = time()+600;
$this->send_data('PRIVMSG '.$config['channel'], ':DONE: Completed Check. Next check at: '.$nextcheck.'.');
