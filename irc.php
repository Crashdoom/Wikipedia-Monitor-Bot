<?php

error_reporting(E_ALL ^ E_NOTICE);

//So the bot doesnt stop.
set_time_limit(0);

// Open PID file
$tmpfilename = "/root/phpwikipedia/ircbot.pid";
if (!($tmpfile = @fopen($tmpfilename,"w")))
{
// Script already running - abort
echo "NekoBot is still active.";
return 0;
}

// Obtain an exlcusive lock on file
// (If script is running this will fail)
if (!@flock( $tmpfile, LOCK_EX | LOCK_NB, &$wouldblock) || $wouldblock)
{
// Script already running - abort
@fclose($tmpfile);
echo "NekoBot is still active.";
return 0;
}

include_once("database.php");

ini_set('display_errors', 'on');

	//Example connection stuff.
	include("config.php");

class IRCBot {

	//This is going to hold our TCP/IP connection
	var $socket;
	var $intersock;

	//This is going to hold all of the messages both server and client
	var $ex    = array();
	var $inter = array();

	/*
	 Construct item, opens the server connection, logs the bot in

	 @param array
	*/
	function __construct($config, $interirc)
	{
		$this->socket = fsockopen($config['server'], $config['port']);		
		$this->login($config);
		$this->main(0, $interirc, 0, $config);
	}

	/*
	 Logs the bot in on the server
	 @param array
	*/

	function login($config)
	{
		$this->send_data('USER', $config['nick'].' lynx.crashcraft.co.uk '.$config['nick'].' :'.$config['name']);
		$this->send_data('NICK', $config['nick']);
	}

	/*
	 This is the workhorse function, grabs the data from the server 
and displays on the browser
	*/
	function main($nextcheck = 0, $interirc = 0, $time_start = 0, $config)
	{
		$faux = array(); $read=array($this->socket);
		if(!stream_select($read, $faux, $faux, 0, 200000)){
			if ($nextcheck < time() && $interirc == 1) {
				include("bot.php");
			} else {
				echo "Nothing..\n";
				mysql_ping();
				sleep(5);
			}
			return $this->main($nextcheck, $interirc, $time_start, $config);
		}
		$data = fgets($this->socket, 128);
		echo nl2br($data);
		flush();
		$this->ex = explode(' ', $data);

		// Lets get the username, and hostname
		$startsplit = explode(':', $data);
		$getname = explode('!', $startsplit[1]);
		$gethost = explode('@', $startsplit[1]);
		$gethost = explode(' ', $gethost[1]);
 		$ircnickname = $getname[0]; // ta-da the username!
		$irchostname = $gethost[0]; // ta-da the host!

		if (strpos($data, ":End of /MOTD command.") !== false) {
			$this->send_data('PRIVMSG NickServ', ':identify '.$config['nickpass']);
			$this->send_data('MODE '.$config['nick'], '-x');
			$this->send_data('JOIN', $config['channel']);
			$interirc = 1;
		}

		if($this->ex[0] == 'PING')
		{
			$this->send_data('PONG', $this->ex[1]); //Plays ping-pong with the server to stay connected.
		}

		$command = str_replace(array(chr(10), chr(13)), '', $this->ex[3]);

		switch($command) //List of commands the bot responds to from a user.
		{
			case ':!join':
				$this->join_channel($this->ex[4]);
				break;

			case ':!quit':
				$query = mysql_query("SELECT * FROM `staff` WHERE `username` = '$ircnickname' AND `hostname` = '$irchostname'");
                                if (mysql_num_rows($query) == 0) {
                                        $this->send_data('NOTICE '.$ircnickname, 'Access denied.');
                                } else {
					$this->send_data('QUIT', 'Shutting Down Neko Services');
				}
				break;

			case ':!fcheck':
				$query = mysql_query("SELECT * FROM `staff` WHERE `username` = '$ircnickname' AND `hostname` = '$irchostname'");
				if (mysql_num_rows($query) == 0) {
					$this->send_data('NOTICE '.$ircnickname, ':Access denied.');
				} else {
					include("bot.php");
				}
				break;

			case ':!check':
				$data = explode(":!check ", $data);
				$tosearch = rtrim(ltrim($data[1]));
				$query = mysql_query("SELECT * FROM `3rrusers` WHERE `username` = '$tosearch'");
				if (mysql_num_rows($query) == 0) {
					$this->send_data('PRIVMSG '.$config['channel'], ':...Meow.. '.$tosearch.' isn\'t in my database.');
				} else {
					$getstuff = mysql_fetch_array($query);
					$count = $getstuff['count'];
					$status = $getstuff['status'];
					if ($status == 0) { $status = "Decision Pending."; }
					$this->send_data('PRIVMSG '.$config['channel'], ':...Meow! '.$tosearch.': Updated '.$count.' times, with latest status: '.$status.'!');
				}
				break;

			case ':!run':
				$query = mysql_query("SELECT * FROM `staff` WHERE `username` = '$ircnickname' AND `hostname` = '$irchostname'");
				if (mysql_num_rows($query) == 0) {
                                        $this->send_data('NOTICE '.$ircnickname, ':Access denied.');
                                } else {
					$data = explode(":!run ", $data);
					$this->send_data($data[1]);
				}
				break;

			case ':!raw':
				$query = mysql_query("SELECT * FROM `staff` WHERE `username` = '$ircnickname' AND `hostname` = '$irchostname'");
				if (mysql_num_rows($query) == 0) {
                                        $this->send_data('NOTICE '.$ircnickname, ':Access denied.');
                                } else {
					$data = explode(":!raw ", $data);
					$this->send_raw($data[1]);
				}
				break;
			
		}

		$this->main($nextcheck, $interirc, $time_start, $config);
	}

	function send_data($cmd, $msg = null) //displays stuff to the broswer and sends data to the server.
	{
		if($msg == null)
		{
			fputs($this->socket, $cmd."\r\n");
			echo '<strong>'.$cmd.'</strong><br />';
		} else {
			fputs($this->socket, $cmd.' '.$msg."\r\n");
			echo '<strong>'.$cmd.' '.$msg.'</strong><br />';
		}
	}

	function join_channel($channel) //Joins a channel, used in the join function.
	{
		if(is_array($channel))
		{
			foreach($channel as $chan)
			{
				$this->send_data('JOIN', $chan);
			}
		} else {
			$this->send_data('JOIN', $channel);
		}
	}

}
	$bot = new IRCBot($config, $interirc);
?>
