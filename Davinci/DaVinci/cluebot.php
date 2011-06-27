<?PHP
	include 'cb_config.php';
	include 'cb_functions.php';

	mysqlconn($config['mysqluser'],$config['mysqlpass'],$config['mysqlhost'],$config['mysqlport'],$config['mysqldb']);
	$locked = false;
	$users = get_db();
	
	$socket = stream_socket_client('tcp://'.$config['server'].':'.$config['port'],$errno,$errstr,30);

	if (!$socket) {
		echo "$errstr ($errno)\n";
	} else {
		fwrite($socket,'USER '.$config['user'].' "1" "1" :'.$config['gecos']."\n");
		fwrite($socket,'NICK '.$config['nick']."\n");

		while (!feof($socket)) {
			$line = str_replace(array("\n","\r"),'',fgets($socket,512));
			$tmp = explode(' ',$line);
			$cmd = $tmp[0];

			if (strtolower($cmd) == 'ping') {
				fwrite($socket,'PONG '.$tmp[1]."\n");
			} else {
				switch (strtolower($tmp[1])) {
					case '422':
					case '376':
						fwrite($socket,'JOIN '.implode(',',$config['channels'])."\n");
						break;
					case 'privmsg':
						$nick = explode('!',substr($tmp[0],1));
						$nick = strtolower($nick[0]);
						$source = $nick;
						$target = $tmp[2];
						$message = explode(' ',$line,4);
						$message = substr($message[3],1);

						if (substr($target,0,1) != '#') {
							loguser($source,'Bot PM -50');
							chgpts($source,-50);
						} else {
							if (substr($message,0,1) == $config['trigger']) {
								$tmp0 = explode(' ',$message);
								$cmd = strtolower(substr($tmp0[0],1));
								$bottom = false;
								$ignore = true;

								switch ($cmd) {
									case 'verbose':
										if ($users[$source]['verbose'] == true) {
											$users[$source]['verbose'] = false;
											$users[$source]['vdedo'] = false;
											$users[$source]['vlog'] = false;
											fwrite($socket,'NOTICE '.$source.' :No longer noticing you every change in points.'."\n");
										} else {
											$users[$source]['verbose'] = true;
											fwrite($socket,'NOTICE '.$source.' :Now noticing you every change in points.'."\n");
										}
										break;
									case 'vdeductions':
										if ($users[$source]['verbose'] == true) {
											if ($users[$source]['vlog'] == false) {
												if ($users[$source]['vdedo'] == true) {
													$users[$source]['vdedo'] = false;
													fwrite($socket,'NOTICE '.$source.' :Now noticing you every change in points.'."\n");
												} else {
													$users[$source]['vdedo'] = true;
													fwrite($socket,'NOTICE '.$source.' :Now noticing you only negative changes in points.'."\n");
												}
											} else {
												fwrite($socket,'NOTICE '.$source.' :Verbose deduction-only is incompatible with verbose log.'."\n");
											}
										} else {
											fwrite($socket,'NOTICE '.$source.' :Verbose must be on before this option is available.'."\n");
										}
										break;
									case 'vlog':
										if ($users[$source]['verbose'] == true) {
											if ($users[$source]['vdedo'] == false) {
												if ($users[$source]['vlog'] == true) {
													$users[$source]['vlog'] = false;
													fwrite($socket,'NOTICE '.$source.' :Now noticing you every change in points.'."\n");
												} else {
													$users[$source]['vlog'] = true;
													fwrite($socket,'NOTICE '.$source.' :Now noticing you log entries relating to you.'."\n");
												}
											} else {
												fwrite($socket,'NOTICE '.$source.' :Verbose deduction-only is incompatible with verbose log.'."\n");
											}
										} else {
											fwrite($socket,'NOTICE '.$source.' :Verbose must be on before this option is available.'."\n");
										}
										break;
									case 'points':
										if ($tmp0[1]) {
											$who = $tmp0[1];
										} else {
											$who = $source;
										}
										fwrite($socket,'NOTICE '.$source.' :'.$who.' has '.getpts(strtolower($who)).' points.'."\n");
										break;
									case 'bottom':
									case 'lamers':
										$bottom = true;
									case 'top':
										$top = gettop($bottom);
										foreach ($top as $nick => $pts) {
											fwrite($socket,' NOTICE '.$source.' :'.$nick.' has '.$pts.' points.'."\n");
										}
										break;
									case 'stats':
										if ($tmp0[1]) {
											$who = $tmp0[1];
										} else {
											$who = $source;
										}																																										
										fwrite($socket,'NOTICE '.$source.' :'.$who.'\'s stats:'."\n");
										fwrite($socket,'NOTICE '.$source.' :'.getstats(strtolower($who))."\n");
										break;
									case 'unignore':
										$ignore = false;
									case 'ignore':
										if (isadmin(strtolower($source))) {
											setignore(strtolower($tmp0[1]),$ignore);
											if ($ignore == false) { $ignore = ' not'; }
											else { $ignore = ''; }
											fwrite($socket,'NOTICE '.$source.' :'.$tmp0[1].' is now'.$ignore.' being ignored.'."\n");
										} else {
											fwrite($socket,'NOTICE '.$source.' :You must be an administrator to use this feature!'."\n");
										}
										break;
									case 'lock':
										if (isadmin(strtolower($source))) {
											if ($locked == true) {
												$locked = false;
												fwrite($socket,'NOTICE '.$source.' :The database is now in read-write mode.'."\n");
											} else {
												$locked = true;
												fwrite($socket,'NOTICE '.$source.' :The database is now in read-only mode.'."\n");
											}
										} else {
											fwrite($socket,'NOTICE '.$source.' :Only administrators may lock the database.'."\n");
										}
										break;
									case 'reload':
										if (isadmin(strtolower($source))) {
											$users = get_db();
											fwrite($socket,'NOTICE '.$source.' :Internal database reloaded according to the MySQL database.'."\n");
										} else {
											fwrite($socket,'NOTICE '.$source.' :Only administrators may reload the database.'."\n");
										}
										break;
									case 'chgpts':
										if (isadmin(strtolower($source))) {
											loguser(strtolower($tmp0[1]),'Administratively changed');
											chgpts(strtolower($tmp0[1]),$tmp0[2]);
											fwrite($socket,'NOTICE '.$source.' :Points changed.'."\n");
										} else {
											fwrite($socket,'NOTICE '.$source.' :Only administrators may manipulate users\' points.'."\n");
										}
										break;
									case 'reset':
										if (isadmin(strtolower($source))) {
											unset($users[strtolower($tmp0[1])]);
											fwrite($socket,'NOTICE '.$source.' :User reset.'."\n");
										} else {
											fwrite($socket,'NOTICE '.$source.' :Only administrators may manipulate users.'."\n");
										}
										break;
									case 'whoami':
										$tmp0[1] = $source;
									case 'whois':
										$who = $tmp0[1];
										$pts = getpts(strtolower($who));
										if ($pts < -1500) { $rating = 'Lamer'; }
										elseif ($pts < -1000) { $rating = 'Not clueful'; }
										elseif ($pts < -500) { $rating = 'Needs alot of work'; }
										elseif ($pts < -10) { $rating = 'Needs work'; }
										elseif ($pts < 10) { $rating = 'Neutral'; }
										elseif ($pts < 30) { $rating = 'Clueful'; }
										elseif ($pts < 60) { $rating = 'Very clueful'; }
										elseif ($pts < 100) { $rating = 'Extremely clueful'; }
										elseif ($pts < 500) { $rating = 'Super clueful'; }
										elseif ($pts >= 500) { $rating = 'Clueful elite'; }
										fwrite($socket,'NOTICE '.$source.' :'.$who.' holds the rank of: '.$rating.'.'."\n");
										fwrite($socket,'NOTICE '.$source.' :'.$who.' has '.$pts.' points.'."\n");
										fwrite($socket,'NOTICE '.$source.' :'.$who.'\'s track record: '.getstats(strtolower($who)).'.'."\n");
										if (isadmin(strtolower($who))) {
											fwrite($socket,'NOTICE '.$source.' :'.$who.' is an administrator.'."\n");
										}
										if ($users[strtolower($who)]['ignore']) {
											fwrite($socket,'NOTICE '.$source.' :'.$who.' is ignored.'."\n");
										}
										break;
								}
							} else {
								$tmppts = 0;
								$smilies = '((>|\})?(:|;|8)(-|\')?(\)|[Dd]|[Pp]|\(|[Oo]|[Xx]|\\|\/)';
								$smilies.= '|(\)|[Dd]|[Pp]|\(|[Oo]|[Xx]|\\|\/)(-|\')?(:|;|8)(>|\})?)';
								if ((!preg_match('/^'.$smilies.'$/i',$message))
								and (!preg_match('/^(uh+|um+|uhm+|er+|ok|ah+|er+m+)(\.+)?$/i',$message))
								and (!preg_match('/^[^A-Za-z].*$/',$message))
								and (!preg_match('/^s(.).+\1.+\1i?g?$/',$message))
								and (!preg_match('/(brb|bbl|lol|rofl|heh|wt[hf]|hah|lmao|bbiab|grr|hmm|hrm|https?:|grep|\||vtun|ifconfig|\$|mm|gtg|wb)/i',$message))
								) {
									if (preg_match('/^([^ ]+(:|,| -) .|[^a-z]).*(\?|\.(`|\'|")?|!|:|'.$smilies.')( '.$smilies.')?$/',$message)) {
										loguser($source,'Clueful sentence +2');
										$tmppts+=2;
									} elseif (preg_match('/^([^ ]+(:|,| -) .|[^a-z]).*$/',$message)) {
										loguser($source,'Normal sentence +1');
										$tmppts++;
									} else {
										loguser($source,'Abnormal sentence -1');
										$tmppts--;
									}
									if (preg_match('/[^\x0a\x0d\x20-\x7e\x99\x9c\xa9\xb8]/',$message)) {
										loguser($source,'Use of non-printable ascii characters -5');
										$tmppts -= 5;
									}
									if (preg_match('/(^| )i( |$)/',$message)) {
										loguser($source,'Lower-case personal pronoun -5');
										$tmppts -= 5;
									}
									if (preg_match('/^[^a-z]{8,}$/',$message)) {
										loguser($source,'All caps -20');
										$tmppts -= 20;
									}
									if (preg_match('/\<censored\>/',$message)) {
										loguser($source,'Use of profanity -20');
										$tmppts -= 20;
									}
									if (preg_match('/(^| )lawl( |$)/',$message)) {
										loguser($source,'Use of non-clueful variation of "lol" -20');
										$tmppts -= 20;
									}
									if (preg_match('/(^| )rawr( |$)/',$message)) {
										loguser($source,'Use of non-clueful expression -20');
										$tmppts -= 20;
									}
									if (preg_match('/^[^aeiouy]{5,}$/i',$message)) {
										loguser($source,'No vowels -30');
										$tmppts -= 30;
									}
									if (preg_match('/(^| )[rRuU]( |$)/',$message)) {
										loguser($source,'Use of r, R, u, or U -40');
										$tmppts -= 40;
									}

									$tmppts = ceil(((strlen($message) < $lineaverage) ? 1 : (strlen($message)/$lineaverage)) * $tmppts);
									$lineaverage = $lineaverage + ((strlen($message) - $lineaverage) / 5000);
									savelineaverage();
									chgpts($source,$tmppts);
								}
							}
						}
						break;
				}
			}
		}
	}
?>
