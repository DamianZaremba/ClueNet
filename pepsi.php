<?php
set_time_limit(0);

$joinchans = array('#damian', '#bots', '#clueirc'); // Channel
$admins = array(
	'damian'	=> 'damianzaremba.co.uk',
	); // Admins
$server = "10.156.12.4"; // Server
$port = 6667; // Port
$nick = "Pepsi"; // Nick

$nickservpass = ""; // Password for NickServ

$phpservuser = "Pepsi"; // Username for PHPSERV
$phpservpass = ""; // Password for PHPSERV

$socket = fsockopen("$server", $port);
fputs($socket,"USER $nick pepsi.damianzaremba.co.uk $nick $nick :$nick\n");
fputs($socket,"NICK $nick\n");

function ivuser($user){
	$user = trim($user);
	$pid = pcntl_fork();
	if ($pid == -1) {
		return "Fork Error";
	}elseif($pid){
		pcntl_wait($status);
	}else{
		$data = trim(system('/usr/bin/remctl useradm.api.cluenet.org useradm initialvouch ' . $user));
		return "The vouch code for '$user' is $data";
	}
}

function getNowPlaying(){
	$pid = pcntl_fork();
	if ($pid == -1) {
		return "Fork Error";
	}elseif($pid){
		pcntl_wait($status);
	}else {
		$connection = @fsockopen('ws.audioscrobbler.com', 80, $error_number, $error_string, 30);
		if($error_number == 0 && $connection == false) {
			return "Error connecting to API server";
		}else{
			$header  = "GET /2.0/?method=user.getrecenttracks&user=damianzaremba4&api_key=b25b959554ed76058ac220b7b2e0a026 HTTP/1.0\r\n";
			$header .= "Host: ws.audioscrobbler.com\r\n";
			$header .= "Connection: Close\r\n";
			$header .= "\r\n";				
			fputs($connection, $header);
	
			$raw_result = "";
			while(!feof($connection)) {
				$raw_result .= @fgets($connection, 128);
			}
			$result = explode("\r\n\r\n", $raw_result);
			$xml = new SimpleXMLElement($result[1]);
	
			if(count($xml->recenttracks->track) > 0){
				foreach($xml->recenttracks->track as $track){
					if(isset($track['nowplaying'])){
						return $track->artist . ' - ' . $track->name;
					}
				}
				return "No Track Playing Currently";
			}else{
				return "No Track Playing Currently";
			}
		}
	// Kill teh fork
	die();
	}
}

function getLastTweet(){
	$pid = pcntl_fork();
	if ($pid == -1) {
		return "Fork Error";
	}elseif($pid){
		pcntl_wait($status);
	}else {
		$connection = @fsockopen('twitter.com', 80, $error_number, $error_string, 30);
		if($error_number == 0 && $connection == false) {
			return "Error connecting to API server";
		}else{
			$header  = "GET /statuses/user_timeline/24356039.xml HTTP/1.0\r\n";
			$header .= "Host: twitter.com\r\n";
			$header .= "Connection: Close\r\n";
			$header .= "\r\n";				
			fputs($connection, $header);
	
			$raw_result = "";
			while(!feof($connection)) {
				$raw_result .= @fgets($connection, 128);
			}
			$result = explode("\r\n\r\n", $raw_result);
			$xml = new SimpleXMLElement($result[1]);
			if(count($xml->status) > 0){
				return $xml->status->{0}->text;
			}else{
				return "No Tweet";
			}
		}
	// Kill teh fork
	die();
	}
}

function updateStats($channel){
	$url_link = 'http://scalar.cluenet.org/~damian/irc/' . urlencode(strtolower($channel));

	system('mkdir -p /home/damian/public_html/irc/' . strtolower($channel));
	system('/home/damian/stats/pisg -ma pepsi -ch \'' . strtolower($channel) . '\' -l /home/damian/stats/logs/' . strtolower($channel) . '.log -o /home/damian/public_html/irc/' . strtolower($channel) . '/index.php -f xchat -ne ClueIRC --silent');
	return 'Updating the stats @ ' . $url_link;
}

function logToFile($type, $chan, $data){
	if(file_exists('/home/damian/stats/logs/' . strtolower($chan) . '.log')){
		$file = fopen('/home/damian/stats/logs/' . strtolower($chan) . '.log', 'a');
		if(!$file){ return; }
	}else{
		$file = fopen('/home/damian/stats/logs/' . strtolower($chan) . '.log', 'w');
		logToFile('start', $chan, $data);
		if(!$file){ return; }
	}

	switch($type){
		case "start":
			fwrite($file, "**** BEGIN LOGGING AT " . date("D M d h:i:s Y", time()) . "\n");
		break;

		case "end":
			fwrite($file, "**** ENDING LOGGING AT " . date("D M d h:i:s Y", time()) . "\n");
		break;

		case "topicChange":
			fwrite($file, date("M d h:i:s", time()) . " *\t" . $data['nick'] . " has changed the topic to: " . $data['topic'] . "\n");
		break;

		case "topicSet":
			fwrite($file, date("M d h:i:s", time()) . " *\tTopic for " . $chan . " is: " . $data['topic'] . "\n");
		break;

		case "topicSetBy":
			fwrite($file, date("M d h:i:s", time()) . " *\tTopic for " . $chan . " set by " . $data['user'] . " at " . $data['time'] . "\n");
		break;

		case "privmsg":
			fwrite($file, date("M d h:i:s", time()) . " * " . $data['nick'] . "\t" . $data['msg'] . "\n");
		break;

		case "kick":
			fwrite($file, date("M d h:i:s", time()) . " *\t" . $data['source'] . " has kicked" . $data['target'] . " from " . $chan . " " . $msg . "\n");
		break;

		case "quit":
			fwrite($file, date("M d h:i:s", time()) . " *\t" . $data['nick'] . " has quit (" . $data['message'] . ")" . "\n");
		break;

		case "join":
			fwrite($file, date("M d h:i:s", time()) . " *\t" . $data['nick'] . " (" . $data['host'] . ") has joined " . $chan . "\n");
		break;

		case "part":
			fwrite($file, date("M d h:i:s", time()) . " * " . $data['nick'] . " (" . $data['host'] . ") has left " . $chan . "\n");
		break;

		case "mode":
			fwrite($file, date("M d h:i:s", time()) . " *\t" . $data['source'] . " sets mode " . $data['mode'] . " " . $chan . " " . $data['target'] . "\n");
		break;
	}
	fclose($file);
	return;
}

logToFile('start', '', '');

while(1) {
	while($data = fgets($socket)){
		$data = trim($data);

		$raw = explode(' ', $data);
		$tmp1 = explode('@', $raw[0]);;
		$tmp2 = explode('!', $tmp1[0]);
		$tmp3 = explode(':', $tmp2[0]);
 
		$user['user'] = $tmp3[1];
		$user['host'] = $tmp1[1];

		if(($raw[1] == "376") || ($raw[1] == "422")){
			if($nickservpass != ''){
				fputs($socket, "PRIVMSG NickServ IDENTIFY ". $nickservpass ."\n");
				fputs($socket, "PRIVMSG PHPSERV IDENTIFY ". $phpservuser ." ". $phpservpass ."\n");
				fputs($socket,"MODE $nick +B\n");
			}
			foreach($joinchans as $chan){
				fputs($socket, "JOIN ".$chan."\n");
			}
		}

		// for those stupid anti spoofing things...
		if(strtolower($raw[0]) == "ping"){ fputs($socket, "PONG ".$raw[1]."\n"); }
		switch(strtolower($raw[1])){
			case "ping":
	        		fputs($socket, "PONG ".$raw[2]."\n");
			break;

			case "topicChange":
					$chan = $raw[2];
					$user = explode("!", $raw[0]);
					$user = explode(":", $user[0]);
					$user = $user[1];

					$msg = $raw;
					unset($msg[0]);
					unset($msg[1]);
					unset($msg[2]);

					$msg[3] = explode(":", $msg[3]);
					$msg[3] = $msg[3][1];
					$msg = implode(" ", $msg);

					$newtopic = $msg;

					$data = array(
						'nick'	=> $user,
						'topic'	=> $newtopic,
					);
					logToFile('topic', $chan, $data);
			break;

			case "332":
				$chan = $raw[3];

				$msg = $raw;
				unset($msg[0]);
				unset($msg[1]);
				unset($msg[2]);
				unset($msg[3]);

				$msg[4] = explode(":", $msg[4]);
				$msg[4] = $msg[4][1];
				$msg = implode(" ", $msg);

				logToFile('topicSet', $chan, array('topic'	=> $msg,));
			break;

			case "333":
				$chan = $raw[3];
				$user = $raw[4];
				$time = $raw[5];

				logToFile('topicSetBy', $chan, array('user'	=> $user,'time'	=> $time,));
			break;

			case "privmsg":
				if(substr($raw[2], 0, 1) == "#"){
					#channel privmsg
					$chan = $raw[2];
					$cmd = $raw[3];
					$cmd = explode(":", $cmd);
					$cmd = $cmd[1];

					$msg = $raw;
					unset($msg[0]);
					unset($msg[1]);
					unset($msg[2]);

					$msg[3] = explode(":", $msg[3]);
					$msg[3] = $msg[3][1];
					$msg = implode(" ", $msg);

					$data = array(
						'nick'	=> $user['user'],
						'msg'	=> $msg,
					);
					logToFile('privmsg', $chan, $data);
					// Admin only Commands
					if($admins[strtolower($user['user'])] == $user['host']){
						switch(strtolower($cmd)){
							case "%vomit":
								$shitz = explode(" ", $msg);
;								unset($shitz[0]);
								$msg = implode(" ",$shitz);
								fputs($socket, "PRIVMSG $chan :$msg\n");
							break;

							case "%join":
								fputs($socket, "JOIN $msg\n");
							break;

							case "%part":
								fputs($socket, "PART $msg Bye\n");
							break;
			
							case "%die":
								fputs($socket,"QUIT IM DIYYYEEEEIINNGG!!!!!!!!!!!!\n");
								break;
							break;

							case "%restart":
								fputs($socket,"QUIT IM DIYYYEEEEIINNGG!!!!!!!!!!!!\n");
								sleep(2);
								system('screen -dm /usr/bin/php /home/damian/bot.php');
								die();
							break;

							case "%stats":
								$stats = updateStats($chan);
								fputs($socket, "PRIVMSG $chan :$stats\n");
							break;

							case "%iv":
								$user = explode("%iv", $msg);
								$user = $user[1];
								$data = ivuser($user);
								fputs($socket, "PRIVMSG $chan :$data\n");
							break;
						}
					}

					// All user commands
					switch(strtolower($cmd)){
							case "%cc":
								$user = explode("%cc", $msg);
								$user = trim($user[1]);
								fputs($socket, "PRIVMSG $chan :Hi there " . $user . ", Please could you take a look at http://wiki.cluenet.org/Clueful_Chatting\n");
							break;

							case "%np":
								$np = getNowPlaying();
								fputs($socket, "PRIVMSG $chan :Now Playing: $np\n");
							break;


							case "%lt":
								$lt = getLastTweet();
								fputs($socket, "PRIVMSG $chan :Last Tweet: $lt\n");
							break;
					}
				}else{
					#bot privmsg
					$cmd = $raw[3];
					$cmd = explode(":", $cmd);
					$cmd = $cmd[1];
					$msg = $raw;
					unset($msg[0]);
					unset($msg[1]);
					unset($msg[2]);
					unset($msg[3]);
		
					$msg = implode(" ", $msg);
					if($admins[strtolower($user['user'])] == $user['host']){
						switch($cmd){
							case "say":
								$msg = explode(" ", $msg);
								$chan = $msg[0];
								unset($msg[0]);
								$msg = implode(" ", $msg);
		
								if(isset($msg) && $msg != ''){
									fputs($socket, "PRIVMSG $chan :$msg\n");
								}else{
									fputs($socket, "PRIVMSG ". $nick['nick'] ." :Invalid Syntax\n");
								}
							break;
			
			
							case "act":
								$msg = explode(" ", $msg);
								$chan = $msg[0];
								unset($msg[0]);
								$msg = implode(" ", $msg);
			
								if(isset($msg) && $msg != ''){
									fputs($socket, "PRIVMSG $chan \x01ACTION $msg\x01\r\x0D\x0A\n");
								}else{
									fputs($socket, "PRIVMSG ". $nick['nick'] ." :Invalid Syntax\n");
								}
							break;
			
							case "kick":
								$msg = explode(" ",$msg);
								$chan = $msg[0];
								$user = $msg[1];
								if(isset($chan) && $chan != '' && isset($user) && $user != ''){
									fputs($socket, "KICK $chan $user Bye!\n");
								}else{
									fputs($socket, "PRIVMSG ". $nick['nick'] ." :Invalid Syntax\n");
								}
							break;
			
							case "ban":
								$msg = explode(" ",$msg);
								$chan = $msg[0];
								$user = $msg[1];
								if(isset($chan) && $chan != '' && isset($user) && $user != ''){
									fputs($socket, "MODE $chan +b $user\n");
								}else{
									fputs($socket, "PRIVMSG ". $nick['nick'] ." :Invalid Syntax\n");
								}
							break;
			
							case "np":
								$msg = explode(" ",$msg);
								$np = getNowPlaying();
								$chan = $msg[0];
								if(isset($chan)){
									fputs($socket, "PRIVMSG $chan :Now Playing: $np\n");
								}else{
									fputs($socket, "PRIVMSG ". $nick['nick'] ." :Invalid Syntax\n");
								}
							break;

							case "lt":
								$msg = explode(" ",$msg);
								$lt = getLastTweet();
								$chan = $msg[0];
								if(isset($chan)){
									fputs($socket, "PRIVMSG $chan :Last Tweet: $lt\n");
								}else{
									fputs($socket, "PRIVMSG ". $nick['nick'] ." :Invalid Syntax\n");
								}
							break;

							case "iv":
								$msg = explode(" ",$msg);
								$chan = $msg[0];
								$user = $msg[1];
								if(isset($chan) && isset($user)){
									$data = ivuser($user['user']);
									fputs($socket, "PRIVMSG $chan :$data\n");
								}else{
									fputs($socket, "PRIVMSG ". $nick['nick'] ." :Invalid Syntax\n");
								}
							break;

							case "kickban":
								$msg = explode(" ",$msg);
								$chan = $msg[0];
								$user = $msg[1];
								if(isset($chan) && $chan != '' && isset($user) && $user != ''){
									fputs($socket, "MODE $chan +b $user\n");
									fputs($socket, "KICK $chan $user Bye!\n");
								}else{
									fputs($socket, "PRIVMSG ". $nick['nick'] ." :Invalid Syntax\n");
								}
							break;
						}
		
					}
		
				}
		}
	}
}

logToFile('end', '', '');
?>
