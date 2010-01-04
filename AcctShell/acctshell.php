#!/usr/bin/php
<?php

include "countrycodes.php";

$attrarray = array(
		"cn" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "Your real name"),
		"givenname" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "Your first name"),
		"sn" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "Your last name"),
		"c" => array("multi" => FALSE, "defaultaccess" => "anon", "desc" => "Your country code", "allowedvalues" => $countrycodes),
		"st" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "Your state or province of residence"),
		"l" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "Your city or locality of residence"),
		"telephonenumber" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "Your primary telephone number"),
		"postaladdress" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "Your street address"),
		"spokenlanguage" => array("multi" => TRUE, "defaultaccess" => "none", "desc" => "A language that you speak (can have multiple)"),
		"cluesshprivkey" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "The user's SSH private key"),
		"cluesshpubkey" => array("multi" => TRUE, "defaultaccess" => "anon", "desc" => "The user's SSH public keys (can have multiple)"),
		"url" => array("multi" => FALSE, "defaultaccess" => "anon", "desc" => "The user's personal web site"),
		"cluevoipuri" => array("multi" => TRUE, "defaultaccess" => "anon", "desc" => "The user URI to a VOIP connection (can have multiple)"),
		"cluegeneralcontact" => array("multi" => TRUE, "defaultaccess" => "none", "desc" => "Various other contact methods for the user (can have multiple)"),
		"cluegender" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "The user's gender", "allowedvalues" => array("male" => "Male", "female" => "Female")),
		"cluebirthyear" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "The user's year of birth"),
		"altemail" => array("multi" => TRUE, "defaultaccess" => "none", "desc" => "Alternate email addresses for the user"),
		"pgpkeyid" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "The user's PGP key ID"),
		"aimsn" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "The user's AIM screen name"),
		"xmppuri" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "The URI for the user's XMPP"),
		"msnsn" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "MSN Screen name"),
		"scheduleinfo" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "Schedule/availability information for the user"),
		"twitteruser" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "Twitter username"),
		"digguser" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "The user's digg username"),
		"slashdotuser" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "Slashdot username"),
		"googlecodeuser" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "Google code username"),
		"githubuser" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "GitHub username"),
		"freshmeatuser" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "Freshmeat username"),
		"occupation" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "The user's occupation"),
		"timezone" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "The user's timezone"),
		"school" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "The user's current school name"),
		"skill" => array("multi" => TRUE, "defaultaccess" => "anon", "desc" => "A skill that the user has (can have multiple)"),
		"wikipediauser" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "Wikipedia username"),
		"cluenotes" => array("multi" => FALSE, "defaultaccess" => "none", "desc" => "Various notes from the user"),
		"loginshell" => array("multi" => FALSE, "defaultaccess" => "anon", "desc" => "Default shell for login", "allowedvalues" => array("/bin/bash" => "Bash", "/bin/sh" => "Sh")),
		"gecos" => array("multi" => FALSE, "defaultaccess" => "anon", "desc" => "The user's gecos, ie, real name")
	);

$invalidcmdtext = "acctshell: Invalid command.  Use \"help\" for a list of valid commands.\n";

$argstack = array();

function myescapeshellarg($arg) {
	if(!isset($arg) || $arg === FALSE || $arg === "") return "''";
	return escapeshellarg($arg);
}

function ldapfilterencode($s) {
	$t = $s;
	$t = str_replace("*", "\\2a", $t);
	$t = str_replace("(", "\\28", $t);
	$t = str_replace(")", "\\29", $t);
	$t = str_replace("\\", "\\5c", $t);
	$t = str_replace("\0", "\\00", $t);
	return $t;
}

function ldapescape($s) {
	$ret = '';
	for ($i = 0; $i < strlen($s); $i++) {
		$cchar = $s{$i};
		$n = ord($cchar);
		if($n < 33 || $n > 126 || strpos('"+,;<>\\=*()\'', $cchar) !== FALSE) $cchar = '\\' . str_pad(dechex($n), 2, '0');
		$ret .= $cchar;
	}
	return $ret;
}

function getline($prompt) {
	global $argstack;
	echo $prompt;
	fflush(STDOUT);
	if(sizeof($argstack) > 0) {
		$l = array_pop($argstack);
		echo $l . "\n";
	} else {
		$l = fgets(STDIN);
	}
	if($l === FALSE) return FALSE;
	$lsegs = explode("\n", $l);
	return $lsegs[0];
}

function getpassword($prompt) {
	passthru('/bin/stty -echo');
	$pw = getline($prompt);
	passthru('/bin/stty echo');
	echo "\n";
	return $pw;
}

function getboolean($prompt) {
	while(TRUE) {
		$l = getline($prompt);
		if(strcasecmp($l, "yes") == 0) return TRUE;
		if(strcasecmp($l, "y") == 0) return TRUE;
		if(strcasecmp($l, "true") == 0) return TRUE;
		if(strcasecmp($l, "no") == 0) return FALSE;
		if(strcasecmp($l, "n") == 0) return FALSE;
		if(strcasecmp($l, "false") == 0) return FALSE;
		echo "Please enter yes or no.\n";
	}
}

function errorexit($error) {
	echo "Error: " . $error . "\n";
	echo "Press ENTER to exit.\n";
	getline('');
	exit(0);
}

function verifynickserv(&$nick, &$nickservpw) {
	$nick = getline("Your IRC Nick: ");
	$nickservpw = getpassword("NickServ Password (It won't show up): ");
	exec('/usr/bin/remctl irc.api.cluenet.org irc checknickservpass ' . myescapeshellarg($nick) . ' ' . myescapeshellarg($nickservpw), $cmdout, $rv);
	if($rv != 0) {
			echo "Incorrect password.\n";
			return FALSE;
	}
	echo "Correct.\n";
	return TRUE;
}

function donewaccount() {
	echo "The requirements for a new account are:\n";
	echo "  - A vouch code from an existing user with the ability to vouch.\n";
	echo "  - A registered nick on the ClueIRC network.\n";
	echo "  - 150 CluePoints.\n";
	echo "  - A verified email address.\n";
	getline("Press ENTER to continue.\n");
	system('kinit -kt /home/acctshell/acctshell.keytab actshl/rhombus.cluenet.org@CLUENET.ORG');
	echo "First, we'll need to verify your nickserv account.\n";
	$hasnickserv = FALSE;
	while(!$hasnickserv) {
		$hasnickserv = getboolean("Do you have a nickserv account? (yes/no): ");
		if(!$hasnickserv) {
			echo "Please register with nickserv on the ClueIRC network, then return here.\n";
			getline("Press ENTER to continue.\n");
		}
	}
	$nsverified = verifynickserv($nick, $nickservpw);
	if(!$nsverified) errorexit("Unfulfilled requirement");
	echo "Checking to see if you have enough CluePoints ...\n";
	$outline = exec('/usr/bin/remctl irc.api.cluenet.org irc cluebot shortpoints ' . myescapeshellarg($nick), $cmdout, $rv);
	if($rv != 0) errorexit("Error checking CluePoints");
	if(!is_numeric($outline)) errorexit("Invalid output while checking CluePoints");
	$cluepoints = $outline;
	if($cluepoints < 150) {
		echo "You only have " . $cluepoints . " CluePoints.  You need 150 CluePoints.  Please come back when you have enough.\n";
		errorexit("Unfulfilled requirement");
	}
	echo "You have " . $cluepoints . " CluePoints.  That is enough to get an account.\n";
	$hasvouchcode = FALSE;
	while(!$hasvouchcode) {
		$hasvouchcode = getboolean("Do you have a vouch code? (yes/no): ");
		if(!$hasvouchcode) {
			echo "You must ask a user on the ClueIRC network to vouch for you to get an account.  When they vouch for you, they will give you a vouch code, which you will have to enter here.\n";
			getline("Press ENTER to continue.\n");
		}
	}
	$vouchcode = getline("Enter your vouch code: ");
	exec('/usr/bin/remctl useradm.api.cluenet.org useradm checkvouchcode ' . myescapeshellarg($nick) . ' ' . myescapeshellarg($vouchcode), $cmdout, $rv);
	if($rv != 0) {
		echo "Incorrect vouch code.\n";
		errorexit("Unfulfilled requirement");
	}
	echo "Verified.\n";
	$email = getline("Enter your email address: ");
	$hasemailverify = getboolean("Do you have an email verification code? (yes/no): ");
	if(!$hasemailverify) {
		echo "Sending email verification code to " . $email . " ...\n";
		system('/usr/bin/remctl useradm.api.cluenet.org useradm sendemailverify ' . myescapeshellarg($email), $rv);
		if($rv == 0) echo "Sent.\n";
	}
	$emailverify = getline("Enter your email verification code: ");
	echo "Checking email verification code ...\n";
	exec('/usr/bin/remctl useradm.api.cluenet.org useradm checkemailverification ' . myescapeshellarg($email) . ' ' . myescapeshellarg($emailverify), $cmdout, $rv);
	if($rv != 0) {
		echo "Incorrect email verification code.\n";
		errorexit("Unfulfilled requirement");
	}
	echo "Verified.\n";
	echo "It looks like you have all the requirements to get an account.\n";
	echo "Now you must select a username.\n";
	while(TRUE) {
		$newuname = getline("Enter username: ");
		system('/usr/bin/remctl useradm.api.cluenet.org useradm checkunameavailability ' . myescapeshellarg($newuname), $rv);
		if($rv == 0) break;
	}
	echo "We now have all the information we should need to create your account.  Press ENTER to start.";
	getline('');
	system('/usr/bin/remctl useradm.api.cluenet.org useradm newacct ' . myescapeshellarg($newuname) . ' ' . myescapeshellarg($email) . ' ' . myescapeshellarg($nick) . ' ' . myescapeshellarg($emailverify) . ' ' . myescapeshellarg($nickservpw) . ' ' . myescapeshellarg($vouchcode), $rv);
	if($rv != 0) errorexit("Error creating account");
	echo "\nCongratulations.  Your account has been created.  An email will be sent to your provided address with your initial login credentials.  You may log in to this account shell to change your password and initialize your account.  Thank you.\n";
	echo "Press ENTER to quit.";
	getline("");
	exit(0);
}

function command_news() {
	echo "- New acctshell up and running\n";
	echo "- Support for administrative actions planned\n";
	echo "- 'getme' command to retrieve source code works\n";
	echo "- 'reconnect' command to reconnect after an LDAP error added\n";
	echo "- An automatic reconnect after 60 seconds added\n";
	echo "- 'news' command added\n";
	echo "- Fixed Ctrl-D bug\n";
	return TRUE;
}

function command_help() {
	echo "== Your Account Management Commands ==\n";
	echo "passwd\n   Changes your account password\n";
	echo "modattr OR m\n   Change various account properties\n";
	echo "changenick\n   Change your registered IRC nick\n";
	echo "changeemail\n   Change your primary registered email address\n";
	echo "\n== Voting and Vouching ==\n";
	echo "initvouch OR iv\n   Vouch for a user\n";
	echo "votetodo OR vt\n   Get a list of responses that you should vote on\n";
	echo "vote OR v\n   View other users' responses and vote on them\n";
	echo "votenext OR n\n   Vote on the next response in your TODO list\n";
	echo "\n== Requirements, Privileges, And Services ==\n";
	echo "listmiscprivileges OR lmp\n   List all miscellaneous privileges, not associated with any server\n";
	echo "listserverprivileges OR lsp\n   List all the privileges specific to a single server\n";
	echo "listallprivileges OR lap\n   List all privileges\n";
	echo "listservices\n   List all the services granted by a specific privilege\n";
	echo "listrequirements OR lr\n   List the requirements associated with a particular privilege\n";
	echo "listallrequirements OR lar\n   List all the available requirements\n";
	echo "\n== Your Responses/Answers, Scores, and Privileges ==\n";
	echo "respond OR r\n   Create a response to a requirement\n";
	echo "deactivateresponse OR deact\n   Freeze a response's current score and votes\n";
	echo "activateresponse OR act\n   Un-freeze a response's current score and votes\n";
	echo "viewmyresponse OR vmr\n   View your past response to a requirement\n";
	echo "checkscore OR cs\n   Check your current score on a requirement\n";
	echo "votestatus OR vs\n   Check the current status of a vote - how many votes, current score, etc.\n";
	echo "allvotestatus OR avs\n   Check your vote status on each of your responses\n";
	echo "privilegerequirementstatus OR prs\n   Check the status of each requirement for a privilege, to see if you can get that privilege\n";
	echo "getprivilege OR gp\n   Use this command to get a privilege once you have the necessary requirements\n";
	echo "\n== Misc Commands ==\n";
	echo "news\n   Shows recent news pertaining to the acctshell\n";
	echo "whoami\n   Shows your username\n";
	echo "reconnect\n   Reconnects to LDAP\n";
	echo "exit\n   Exits the shell\n";
	echo "adminhelp\n   Prints out help for administrative commands\n";
	echo "help OR ?\n   Prints out this help message\n";
	return TRUE;
}

function command_adminhelp() {
	echo "== Service Packs ==\n";
	echo "addservicepack\n   Adds a new service pack to a server\n";
	echo "editservicepack\n   Edits an existing service pack on a server\n";
	echo "delservicepack\n   Deletes a service pack from a server\n";
	return TRUE;
}

function command_reconnect() {
	global $ldap, $ccfile, $username;
	echo "This command reinitializes your kerberos ticket and reconnects to the LDAP server.  It does NOT refresh the script.\n";
	echo "Getting Kerberos ticket ...\n";
	passthru('KRB5CCNAME=' . $ccfile . ' /usr/bin/kinit ' . $username . '@CLUENET.ORG', $rv);
	if($rv != 0) { echo "Error: Could not authenticate user.\n"; return FALSE; }
	putenv('KRB5CCNAME=' . $ccfile);
	echo "Initializing LDAP ...\n";
	$ldap = ldap_connect('ldap://ldap.cluenet.org');
	if(!$ldap) errorexit("Could not connect to LDAP server");
	echo "Setting protocol options ...\n";
	if(!ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3)) errorexit("Could not set LDAP protocol to version 3");
	if(!ldap_start_tls($ldap)) errorexit("Could not start TLS on LDAP");
	echo "Connecting and binding ...\n";
	if(!ldap_sasl_bind($ldap)) errorexit("Could not bind to LDAP");
	echo "Done.\n";
	return TRUE;
}

function check_connect_ldap() {
	global $ldap, $lastconnecttime;
	$reconnectperiod = 60;
	$ctime = time();
	if(!isset($lastconnecttime)) $lastconnecttime = 0;
	if($ctime - $lastconnecttime > 60) {
		$ldap = ldap_connect('ldap://ldap.cluenet.org');
		if(!$ldap) errorexit("Could not connect to LDAP server");
		if(!ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3)) errorexit("Could not set LDAP protocol to version 3");
		if(!ldap_start_tls($ldap)) errorexit("Could not start TLS on LDAP");
		if(!ldap_sasl_bind($ldap)) errorexit("Could not bind to LDAP");
		$lastconnecttime = $ctime;
	}
	return TRUE;
}

function command_passwd() {
	$curpass = getpassword("Current password: ");
	$newpass = getpassword("New password: ");
	$confirm = getpassword("Confirm new password: ");
	if($newpass != $confirm) { echo "Passwords do not match, Aborting.\n"; return FALSE; }
	if(strlen($newpass) < 6) { echo "New password too short, Aborting.\n"; return FALSE; }
	echo "Changing password ...\n";
	exec('/usr/bin/remctl useradm.api.cluenet.org useradm passwd ' . myescapeshellarg($GLOBALS['username'] . '@CLUENET.ORG') . ' ' . myescapeshellarg($curpass) . ' ' . myescapeshellarg($newpass), $cmdout, $rv);
	if($rv != 0) { echo "Error.\n"; return FALSE; }
	echo "Password changed.\n";
	return TRUE;
}

function command_modattr() {
	global $username, $ldap;
	$modattrs = $GLOBALS['attrarray'];
	$selectedattr = FALSE;
	while(!$selectedattr) {
		$attr = getline("Which attribute would you like to edit (Type \"list\" to get a list): ");
		$attr = strtolower($attr);
		if($attr == "list") {
			foreach($GLOBALS['attrarray'] as $cattrname => $cattr) {
				echo $cattrname . ': ' . $cattr['desc'] . "\n";
			}
		} else {
			$selectedattr = TRUE;
			if(!isset($GLOBALS['attrarray'][$attr])) {
				echo "No such attribute.\n";
				break;
			}
			$ismulti = $GLOBALS['attrarray'][$attr]['multi'];
			while(TRUE) {
				$attrcmd = getline('Attribute_' . $attr . '> ');
				if($attrcmd == "") continue;
				if($attrcmd == "done" || $attrcmd == "exit") break;
				if($attrcmd == "help" || $attrcmd == "h" || $attrcmd == "?") {
					if($ismulti) {
						echo "Available commands for this attribute: add, del, delall, show, showaccess, changeaccess, help, done\n";
					} else {
						echo "Available commands for this attribute: modify, del, show, showaccess, changeaccess, help, done\n";
					}
					continue;
				}
				if($attrcmd == "del" && !$ismulti) $attrcmd = "delall";
				if($attrcmd == "show") {
					check_connect_ldap();
					$r = ldap_search($ldap, 'ou=people,dc=cluenet,dc=org', 'uid=' . $username, array($attr));
					if($r == FALSE) { echo "Error.\n"; continue; }
					$lentries = ldap_get_entries($ldap, $r);
					if($lentries == FALSE) { echo "Error.\n"; continue; }
					if(!isset($lentries[0][$attr][0])) { echo "That attribute is not currently set.\n"; continue; }
					if($ismulti) {
						echo "Values:\n";
						for($i = 0; $i < $lentries[0][$attr]["count"]; $i++) {
							echo ($i + 1) . '. ' . $lentries[0][$attr][$i] . "\n";
						}
					} else {
						echo "Value: " . $lentries[0][$attr][0] . "\n";
					}
					continue;
				}
				if($attrcmd == "showaccess") {
					check_connect_ldap();
					$r = ldap_search($ldap, 'ou=people,dc=cluenet,dc=org', 'uid=' . $username, array('acs' . $attr));
					if($r == FALSE) { echo "Error.\n"; continue; }
					$lentries = ldap_get_entries($ldap, $r);
					if($lentries == FALSE) { echo "Error.\n"; continue; }
					if(!isset($lentries[0]['acs' . $attr][0])) { echo "You have not yet set access control for this attribute.  The default is: " . $GLOBALS['attrarray'][$attr]['defaultaccess'] . "\n"; continue; }
					echo "The current access for this attribute is: " . $lentries[0]["acs" . $attr][0] . "\n";
					continue;
				}
				if($attrcmd == "changeaccess") {
					echo "The valid access levels are:\n   none - Nobody (except admins) can access the attribute.\n   user - Cluenet users can read the attribute.\n   anon - Anyone can read the attribute.\n   default - Leave the access level at the default.\n";
					$newval = getline("New access level for " . $attr . ": ");
					if($newval != "none" && $newval != "user" && $newval != "anon" && $newval != "default") { echo "Invalid access level.\n"; continue; }
					$acsattr = "acs" . $attr;
					check_connect_ldap();
					if($newval == "default") {
						$r = ldap_mod_del($ldap, 'uid=' . $username . ',ou=people,dc=cluenet,dc=org', array($acsattr => array()));
					} else {
						$r = ldap_mod_replace($ldap, 'uid=' . $username . ',ou=people,dc=cluenet,dc=org', array($acsattr => $newval));
					}
					if($r != TRUE) {
						echo "Error: " . ldap_error($ldap) . "\n";
					} else {
						echo "Access level modified.\n";
					}
					continue;
				}
				if($attrcmd == "modify" && !$ismulti) {
					if(isset($GLOBALS['attrarray'][$attr]['allowedvalues'])) {
						echo "Allowed values for this attribute:\n";
						foreach($GLOBALS['attrarray'][$attr]['allowedvalues'] as $aval => $avaldesc) {
							echo $aval . ' - ' . $avaldesc . "\n";
						}
					}
					$newval = getline("New value of " . $attr . ": ");
					if(isset($GLOBALS['attrarray'][$attr]['allowedvalues'])) if(!isset($GLOBALS['attrarray'][$attr]['allowedvalues'][$newval])) {
						echo "Invalid value for that attribute.\n";
						continue;
					}
					check_connect_ldap();
					$r = ldap_mod_replace($ldap, 'uid=' . $username . ',ou=people,dc=cluenet,dc=org', array($attr => $newval));
					if($r != TRUE) {
						echo "Error: " . ldap_error($ldap) . "\n";
					} else {
						echo "Attribute modified.\n";
					}
					continue;
				}
				if($attrcmd == "add" && $ismulti) {
					if(isset($GLOBALS['attrarray'][$attr]['allowedvalues'])) {
						echo "Allowed values for this attribute:\n";
						foreach($GLOBALS['attrarray'][$attr]['allowedvalues'] as $aval => $avaldesc) {
							echo $aval . ' - ' . $avaldesc . "\n";
						}
					}
					$newval = getline("New value of " . $attr . ": ");
					if(isset($GLOBALS['attrarray'][$attr]['allowedvalues'])) if(!isset($GLOBALS['attrarray'][$attr]['allowedvalues'][$newval])) {
						echo "Invalid value for that attribute.\n";
						continue;
					}
					check_connect_ldap();
					$r = ldap_mod_add($ldap, 'uid=' . $username . ',ou=people,dc=cluenet,dc=org', array($attr => $newval));
					if($r != TRUE) {
						echo "Error: " . ldap_error($ldap) . "\n";
					} else {
						echo "Attribute added.\n";
					}
					continue;
				}
				if($attrcmd == "del" && $ismulti) {
					$newval = getline("Value to delete for " . $attr . ": ");
					check_connect_ldap();
					$r = ldap_mod_del($ldap, 'uid=' . $username . ',ou=people,dc=cluenet,dc=org', array($attr => $newval));
					if($r != TRUE) {
						echo "Error: " . ldap_error($ldap) . "\n";
					} else {
						echo "Attribute deleted.\n";
					}
					continue;
				}
				if($attrcmd == "delall") {
					check_connect_ldap();
					$r = ldap_mod_del($ldap, 'uid=' . $username . ',ou=people,dc=cluenet,dc=org', array($attr => array()));
					if($r != TRUE) {
						echo "Error: " . ldap_error($ldap) . "\n";
					} else {
						echo "Attribute deleted.\n";
					}
					continue;
				}
				echo "Invalid attr command - type \"help\" for a list\n";
			}
		}
	}
}

function command_changenick() {
	$r = verifynickserv($nick, $nspw);
	if($r == FALSE) return FALSE;
	exec('remctl useradm.api.cluenet.org useradm changenick ' . myescapeshellarg($nick) . ' ' . myescapeshellarg($nspw), $cmdout, $rv);
	if($rv != 0) {
		echo "Error changing nick.\n";
		return FALSE;
	}
	echo "Nick changed.\n";
	return TRUE;
}

function command_changeemail() {
	$email = getline("New email: ");
	echo "Sending verification email ...\n";
	exec('remctl useradm.api.cluenet.org useradm sendemailverify ' . myescapeshellarg($email), $cmdout, $rv);
	if($rv != 0) { echo "Error sending verification email.\n"; return FALSE; }
	$vcode = getline("Enter verification code: ");
	exec('remctl useradm.api.cluenet.org useradm checkemailverification ' . myescapeshellarg($email) . ' ' . myescapeshellarg($vcode), $cmdout, $rv);
	if($rv != 0) { echo "Incorrect verification code.\n"; return FALSE; }
	exec('remctl useradm.api.cluenet.org useradm changeemail ' . myescapeshellarg($email) . ' ' . myescapeshellarg($vcode), $cmdout, $rv);
	if($rv != 0) { echo "Error changing email.\n"; return FALSE; }
	echo "Email changed.\n";
	return TRUE;
}

function command_initvouch() {
	$nick = getline("IRC Nick of the user to vouch for: ");
	$vcode = exec('remctl useradm.api.cluenet.org useradm initialvouch ' . myescapeshellarg($nick), $cmdout, $rv);
	if($rv != 0) { echo "Error getting vouch code: " . implode('  ', $cmdout) . "\n"; return FALSE; }
	echo "Vouch code for " . $nick . " is: " . $vcode . "\n";
	return TRUE;
}

function getresponsedata($reqid, $respuid) {
	$ll = exec('remctl privapp.api.cluenet.org privapp getresponse ' . myescapeshellarg($reqid) . ' ' . myescapeshellarg($respuid), $cmdout, $rv);
	if($rv != 0) {
		//if($ll != "Error: No response data for that response") echo "Error getting response data: " . $ll . "\n";
		return FALSE;
	}
	return implode("\n", $cmdout);
}

function command_votetodo($retfirst = FALSE) {
	$threemon = gmdate('YmdHis\Z', time() - 3 * 30 * 24 * 60 * 60);
	$onemon = gmdate('YmdHis\Z', time() - 1 * 30 * 24 * 60 * 60);
	exec('remctl privapp.api.cluenet.org privapp getallmyvoteinfo ' . $threemon . ' - ' . $onemon . ' -', $cmdout, $rv);
	if($rv != 0) { echo "Error getting vote information.\n"; return FALSE; }
	if($retfirst != TRUE) echo "Responses for you to vote on:\n";
	$numdisp = 0;
	foreach($cmdout as $line) {
		$linesegs = explode(":", $line);
		$reqid = $linesegs[0];
		$respuid = $linesegs[1];
		$createtime = $linesegs[2];
		$modtime = $linesegs[3];
		$isactive = $linesegs[4];
		$votescore = $linesegs[5];
		$votetime = $linesegs[6];
		$fbtime = $linesegs[7];
		if(strlen($votescore) > 0) if(strlen($votetime) > 0) if(strlen($modtime) > 0) if($modtime < $votetime) continue;
		if($retfirst == TRUE) {
			if(strlen($votescore) > 0) return $reqid . ':' . $respuid . ':UPDATED';
			return $reqid . ':' . $respuid . ':';
		}
		$numdisp++;
		echo "Requirement: " . $reqid . "  User: " . $respuid;
		if(strlen($votescore) > 0) echo "   UPDATED SINCE YOUR LAST VOTE";
		echo "\n";
	}
	if($numdisp < 1) echo "No pending votes for you.\n";
	return TRUE;
}

function view_essay($reqid, $respuid, $reqdata, $respdata) {
	if($reqdata == FALSE) { echo "Invalid requirement.\n"; return FALSE; }
	if($respdata == FALSE) { echo "No response data.\n"; return FALSE; }
	echo "That is an essay requirement.\n";
	getline("Press ENTER to view the essay question.");
	echo "\n";
	echo "Essay question:\n" . $reqdata . "\n";
	getline("Press ENTER to view " . $respuid . "'s response.");
	echo "\n";
	echo "Response:\n" . $respdata . "\n";
	getline("Press ENTER to continue.");
	return TRUE;
}

function view_shortanswerseries($reqid, $respuid, $reqdata, $respdata) {
	if($reqdata == FALSE) { echo "Invalid requirement.\n"; return FALSE; }
	if($respdata == FALSE) { echo "No response data.\n"; return FALSE; }
	echo "That is a short answer series requirement.\n";
	$questions = explode("\n", $reqdata);
	$answers = explode("\n", $respdata);
	for($i = 0; $i < sizeof($questions); $i++) {
		if(strlen($questions[$i]) < 1) unset($questions[$i]);
	}
	if(sizeof($questions) == 0) {
		echo "There are no questions in this series.\n";
		getline("Press ENTER to continue.");
		return FALSE;
	}
	echo "There are " . sizeof($questions) . " short answer questions in this series.\n";
	getline("Press ENTER to view the first question and " . $respuid . "'s response.");
	for($cq = 0; $cq < sizeof($questions); $cq++) {
		echo "\n";
		echo ($cq + 1) . '. ' . $questions[$cq] . "\n";
		echo "Answer: " . $answers[$cq] . "\n\n";
		if($cq + 1 < sizeof($questions)) {
			getline("Press ENTER to view the next question and response.");
		}
	}
	echo "\n";
	echo "\n";
	getline("Press ENTER to continue.");
	return TRUE;
}

function view_multiplechoicequestions($reqid, $respuid, $reqdata, $respdata) {
	if($reqdata == FALSE) { echo "Invalid requirement.\n"; return FALSE; }
	if($respdata == FALSE) { echo "No response data.\n"; return FALSE; }
	echo "That is a multiple choice requirement.\n";
	$questions = explode("\n\n", $reqdata);
	$answers = explode("\n", $respdata);
	for($i = 0; $i < sizeof($questions); $i++) {
		$questions[$i] = str_replace("\\n", "\n", $questions[$i]);
		if(strlen($questions[$i]) < 1) unset($questions[$i]);
	}
	if(sizeof($questions) == 0) {
		echo "There are no questions in this set.\n";
		getline("Press ENTER to continue.");
		return FALSE;
	}
	echo "There are " . sizeof($questions) . " multiple choice questions in this series.\n";
	getline("Press ENTER to view the first question and " . $respuid . "'s answer.");
	for($cq = 0; $cq < sizeof($questions); $cq++) {
		echo "\n";
		echo ($cq + 1) . '. ' . str_replace("\\n", "\n", $questions[$cq]) . "\n";
		echo "Answer: " . $answers[$cq] . "\n\n";
		if($cq + 1 < sizeof($questions)) {
			getline("Press ENTER to view the next question and response.");
		}
	}
	echo "\n";
	echo "\n";
	getline("Press ENTER to continue.");
	return TRUE;
}

function view_rrdata($reqid, $respuid, $reqdata, $respdata) {
	getline("Press ENTER to view the requirement data.");
	echo $reqdata . "\n";
	getline("Press ENTER to view " . $respuid . "'s response data.");
	echo $respdata . "\n";
	getline("Press ENTER to continue.");
	return TRUE;
}

function command_vote($reqid = FALSE, $respuid = FALSE) {
	global $ldap;
	if($reqid == FALSE) {
		$reqid = getline("Requirement to vote on: ");
	}
	check_connect_ldap();
	$r = ldap_search($ldap, 'ou=requirements,dc=cluenet,dc=org', '(&(objectClass=Requirement)(requirementID=' . ldapescape($reqid) . '))', array("requirementtype", "requirementdata", "scorecheckmethod"));
	if($r == FALSE) { echo "Error getting requirement data: " . $ldap_error($ldap) . "\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries["count"] < 1) { echo "No such requirement.\n"; return FALSE; }
	if(!isset($lentries[0]['requirementtype'][0])) { echo "Requirement error.\n"; return FALSE; }
	if(!isset($lentries[0]['scorecheckmethod'][0])) { echo "Requirement error.\n"; return FALSE; }
	$reqtype = $lentries[0]['requirementtype'][0];
	$ssm = $lentries[0]['scorecheckmethod'][0];
	$ssmparts = explode(':', $ssm);
	if($ssmparts[0] != 'vote') { echo "That is not a voting requirement.\n"; return FALSE; }
	if(!isset($lentries[0]['requirementdata'][0])) $reqdata = FALSE; else $reqdata = $lentries[0]['requirementdata'][0];
	if($respuid == FALSE) {
		$respuid = getline("User to vote on: ");
	}
	$respdata = getresponsedata($reqid, $respuid);
	$viewed = FALSE;
	if($reqtype == "essay") {
		if(!view_essay($reqid, $respuid, $reqdata, $respdata)) return FALSE;
		$viewed = TRUE;
	}
	if($reqtype == "shortanswerseries") {
		if(!view_shortanswerseries($reqid, $respuid, $reqdata, $respdata)) return FALSE;
		$viewed = TRUE;
	}
	if($reqtype == "multiplechoicequestions") {
		if(!view_multiplechoicequestions($reqid, $respuid, $reqdata, $respdata)) return FALSE;
		$viewed = TRUE;
	}
	if($reqtype == "uservote") {
		echo "That is an arbitrary voting requirement.  There is no data or response for this requirement, you only vote on the user: " . $respuid . "\n";
		getline("Press ENTER to continue.");
		$viewed = TRUE;
	}
	if($reqtype == 'remctl') { echo "This is a remctl requirement.  You cannot respond to this requirement type.  Scoring information is taken from an external source.\n"; return FALSE; }
	if(!$viewed) {
		echo "Unknown requirement type: " . $reqtype . "\n";
		getline("Press ENTER to viwe raw requirement/response data.");
		if(!view_rrdata($reqid, $respuid, $reqdata, $respdata)) return FALSE;
	}
	unset($cmdout);
	exec('remctl privapp.api.cluenet.org privapp getmyvoteinfo ' . myescapeshellarg($reqid) . ' ' . myescapeshellarg($respuid), $cmdout, $rv);
	if($rv != 0) { echo "Error looking up existing vote data.\n"; return FALSE; }
	$existvote = FALSE;
	$existfb = FALSE;
	foreach($cmdout as $line) {
		$segs = explode(":", $line);
		if($segs[0] == 'vote') $existvote = $segs[2];
		if($segs[0] == 'feedback') $existfb = $segs[2];
	}
	if($existvote !== FALSE) {
		echo "You have already voted on this user's response.  Your new vote will replace your existing vote.\n";
		echo "Last time you voted: " . $existvote . "\n";
	}
	if($existfb !== FALSE) {
		echo "You have already left feedback on this user's response.  Your new feedback will replace your existing feedback.\n";
		echo "Last time your feedback was: " . $existfb . "\n";
	}
	$prompt = "Your score for this response: ";
	if($existvote !== FALSE) $prompt = "Your new score for this response (or -1 to delete your existing vote without leaving a new vote): ";
	$newscore = getline($prompt);
	system('remctl privapp.api.cluenet.org privapp vote ' . myescapeshellarg($reqid) . ' ' . myescapeshellarg($respuid) . ' ' . myescapeshellarg($newscore), $rv);
	if($rv != 0) { echo "Error.\n"; return FALSE; }
	$prompt = "Would you like to leave feedback for this user regarding this response? (yes/no): ";
	if($existfb !== FALSE) $prompt = "Would you like to change your feedback? (yes/no): ";
	if(getboolean($prompt) == TRUE) {
		$prompt = "Enter your feedback: ";
		if($existfb !== FALSE) $prompt = "Enter your new feedback, or just press ENTER to remove your feedback: ";
		$newfb = getline($prompt);
		system('remctl privapp.api.cluenet.org privapp givefeedback ' . myescapeshellarg($reqid) . ' ' . myescapeshellarg($respuid) . ' ' . myescapeshellarg($newfb), $rv);
		if($rv != 0) { echo "Error.\n"; return FALSE; }
	}
	getline("Press ENTER to continue.");
	return TRUE;
}

function command_votenext() {
	$rstr = command_votetodo(TRUE);
	if($rstr === TRUE) {
		echo "No more TODO votes for you.\n";
		return TRUE;
	}
	if($rstr === FALSE) {
		echo "Error.\n";
		return FALSE;
	}
	$segs = explode(":", $rstr);
	$reqid = $segs[0];
	$respuid = $segs[1];
	$updated = $segs[2];
	echo "Next vote to vote on:\n Requirement: " . $reqid . "\n User: " . $respuid . "\n";
	if($updated == "UPDATED") echo "This response has been updated since your last vote on this response.\n";
	getline("Press ENTER to continue.");
	return command_vote($reqid, $respuid);
}

function command_listmiscprivileges() {
	global $ldap;
	check_connect_ldap();
	$r = ldap_search($ldap, 'ou=miscservicereqs,dc=cluenet,dc=org', 'ou=miscservicereqs', array('servicerequirement'));
	if($r == FALSE) { echo "Error.\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries == FALSE) { echo "Error.\n"; return FALSE; }
	if($lentries["count"] != 1) { echo "Error.\n"; return FALSE; }
	if(!isset($lentries[0]['servicerequirement']["count"])) { echo "Error.\n"; return FALSE; }
	for($i = 0; $i < $lentries[0]['servicerequirement']["count"]; $i++) {
		$segs = explode(':', $lentries[0]['servicerequirement'][$i]);
		// echo "_misc:" . $segs[0] . "\n";
		echo $segs[0] . "\n";
	}
	return TRUE;
}

function command_listserverprivileges($server = FALSE) {
	global $ldap;
	if($server == FALSE) {
		$server = getline("Server: ");
	}
	$serversegs = explode(".", $server);
	$server = $serversegs[0];
	check_connect_ldap();
	$r = ldap_search($ldap, 'ou=servers,dc=cluenet,dc=org', 'cn=' . ldapescape($server), array('servicerequirement'));
	if($r == FALSE) { echo "Error.\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries == FALSE) { echo "Error.\n"; return FALSE; }
	if($lentries["count"] != 1) { echo "No such server.\n"; return FALSE; }
	if(!isset($lentries[0]['servicerequirement']["count"])) { echo "No service requirements for this server.\n"; return FALSE; }
	for($i = 0; $i < $lentries[0]['servicerequirement']["count"]; $i++) {
		$segs = explode(':', $lentries[0]['servicerequirement'][$i]);
		echo $server . ':' . $segs[0] . "\n";
	}
	return TRUE;
}

function command_listallprivileges() {
	global $ldap;
	check_connect_ldap();
	$r = ldap_search($ldap, 'ou=miscservicereqs,dc=cluenet,dc=org', 'ou=miscservicereqs', array('servicerequirement'));
	if($r == FALSE) { echo "Error.\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries == FALSE) { echo "Error.\n"; return FALSE; }
	if($lentries["count"] != 1) { echo "Error.\n"; return FALSE; }
	if(!isset($lentries[0]['servicerequirement']["count"])) { echo "Error.\n"; return FALSE; }
	for($i = 0; $i < $lentries[0]['servicerequirement']["count"]; $i++) {
		$segs = explode(':', $lentries[0]['servicerequirement'][$i]);
		echo "_misc:" . $segs[0] . "\n";
	}
	$r = ldap_search($ldap, 'ou=servers,dc=cluenet,dc=org', 'objectClass=Server', array('cn', 'servicerequirement'));
	if($r == FALSE) { echo "Error.\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries == FALSE) { echo "Error.\n"; return FALSE; }
	if($lentries["count"] < 1) { return TRUE; }
	for($j = 0; $j < $lentries["count"]; $j++) {
		if(!isset($lentries[$j]['servicerequirement']["count"])) continue;
		for($i = 0; $i < $lentries[$j]['servicerequirement']["count"]; $i++) {
			$segs = explode(':', $lentries[$j]['servicerequirement'][$i]);
			echo $lentries[$j]['cn'][0] . ':' . $segs[0] . "\n";
		}
	}
	return TRUE;
}

function command_listservices($privilege = FALSE) {
	global $ldap;
	if($privilege == FALSE) {
		$privilege = getline("Privilege to list services for: ");
	}
	$segs = explode(":", $privilege);
	if(sizeof($segs) > 2) { echo "Invalid privilege format.  Should be _misc:ServicePack or Server:ServicePack.\n"; return FALSE; }
	if(sizeof($segs) == 1) { $segs[1] = $segs[0]; $segs[0] = "_misc"; }
	$server = $segs[0];
	$spack = $segs[1];
	if($server == '_misc') {
		$sbase = 'ou=miscservicereqs,dc=cluenet,dc=org';
		$sfilter = 'ou=miscservicereqs';
	} else {
		$sbase = 'ou=servers,dc=cluenet,dc=org';
		$sfilter = 'cn=' . ldapescape($server);
	}
	check_connect_ldap();
	$r = ldap_search($ldap, $sbase, $sfilter, array('servicerequirement'));
	if($r == FALSE) { echo "Error.\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries == FALSE) { echo "Error.\n"; return FALSE; }
	if($lentries["count"] < 1) { echo "No such server.\n"; return FALSE; }
	if(!isset($lentries[0]['servicerequirement']["count"])) { echo "No such privilege.\n"; return FALSE; }
	for($i = 0; $i < $lentries[0]['servicerequirement']["count"]; $i++) {
		$segs = explode(':', $lentries[0]['servicerequirement'][$i]);
		if($segs[0] != $spack) continue;
		$services = explode(',', $segs[1]);
		foreach($services as $s) {
			echo $s . "\n";
		}
		break;
	}
	if($i == $lentries[0]['servicerequirement']["count"]) { echo "No such service pack.\n"; return FALSE; }
	return TRUE;
}

function command_listrequirements($privilege = FALSE) {
	global $ldap;
	if($privilege == FALSE) {
		$privilege = getline("Privilege to list services for: ");
	}
	$segs = explode(":", $privilege);
	if(sizeof($segs) > 2) { echo "Invalid privilege format.  Should be _misc:ServicePack or Server:ServicePack.\n"; return FALSE; }
	if(sizeof($segs) == 1) { $segs[1] = $segs[0]; $segs[0] = "_misc"; }
	$server = $segs[0];
	$spack = $segs[1];
	if($server == '_misc') {
		$sbase = 'ou=miscservicereqs,dc=cluenet,dc=org';
		$sfilter = 'ou=miscservicereqs';
	} else {
		$sbase = 'ou=servers,dc=cluenet,dc=org';
		$sfilter = 'cn=' . ldapescape($server);
	}
	check_connect_ldap();
	$r = ldap_search($ldap, $sbase, $sfilter, array('servicerequirement'));
	if($r == FALSE) { echo "Error.\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries == FALSE) { echo "Error.\n"; return FALSE; }
	if($lentries["count"] < 1) { echo "No such server.\n"; return FALSE; }
	if(!isset($lentries[0]['servicerequirement']["count"])) { echo "No such privilege.\n"; return FALSE; }
	for($i = 0; $i < $lentries[0]['servicerequirement']["count"]; $i++) {
		$segs = explode(':', $lentries[0]['servicerequirement'][$i]);
		if($segs[0] != $spack) continue;
		$reqs = explode(',', $segs[2]);
		foreach($reqs as $req) {
			$reqparts = explode('.', $req);
			echo "A score of at least " . $reqparts[1] . " on " . $reqparts[0] . ".\n";
		}
		break;
	}
	if($i == $lentries[0]['servicerequirement']["count"]) { echo "No such service pack.\n"; return FALSE; }
	return TRUE;
}

function command_listallrequirements() {
	global $ldap;
	check_connect_ldap();
	$r = ldap_search($ldap, 'ou=requirements,dc=cluenet,dc=org', 'objectClass=Requirement', array('requirementID', 'requirementOverview'));
	if($r == FALSE) { echo "Error.\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries == FALSE) { echo "Error.\n"; return FALSE; }
	if($lentries["count"] < 1) { echo "Error.\n"; return FALSE; }
	for($i = 0; $i < $lentries["count"]; $i++) {
		if(!isset($lentries[$i]['requirementid'][0])) continue;
		echo $lentries[$i]['requirementid'][0];
		if(isset($lentries[$i]['requirementoverview'][0])) echo ' - ' . $lentries[$i]['requirementoverview'][0];
		echo "\n";
	}
	return TRUE;
}

function command_viewmyresponse($reqid = FALSE) {
	global $ldap;
	if($reqid == FALSE) {
		$reqid = getline("Requirement: ");
	}
	check_connect_ldap();
	$r = ldap_search($ldap, 'ou=requirements,dc=cluenet,dc=org', '(&(objectClass=Requirement)(requirementID=' . ldapescape($reqid) . '))', array("requirementtype", "requirementdata"));
	if($r == FALSE) { echo "Error getting requirement data: " . $ldap_error($ldap) . "\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries["count"] < 1) { echo "No such requirement.\n"; return FALSE; }
	if(!isset($lentries[0]['requirementtype'][0])) { echo "Requirement error.\n"; return FALSE; }
	$reqtype = $lentries[0]['requirementtype'][0];
	if(!isset($lentries[0]['requirementdata'][0])) $reqdata = FALSE; else $reqdata = $lentries[0]['requirementdata'][0];
	$respuid = $GLOBALS['username'];
	$respdata = getresponsedata($reqid, $respuid);
	$viewed = FALSE;
	if($reqtype == "essay") {
		if(!view_essay($reqid, $respuid, $reqdata, $respdata)) return FALSE;
		$viewed = TRUE;
	}
	if($reqtype == "shortanswerseries") {
		if(!view_shortanswerseries($reqid, $respuid, $reqdata, $respdata)) return FALSE;
		$viewed = TRUE;
	}
	if($reqtype == "multiplechoicequestions") {
		if(!view_multiplechoicequestions($reqid, $respuid, $reqdata, $respdata)) return FALSE;
		$viewed = TRUE;
	}
	if($reqtype == "uservote") {
		echo "That is an arbitrary voting requirement.  There is no data or response for this requirement.\n";
		getline("Press ENTER to continue.");
		$viewed = TRUE;
	}
	if($reqtype == 'remctl') { echo "This is a remctl requirement.  You cannot respond to this requirement type.  Scoring information is taken from an external source.\n"; return FALSE; }
	if(!$viewed) {
		echo "Unknown requirement type: " . $reqtype . "\n";
		getline("Press ENTER to viwe raw requirement/response data.");
		if(!view_rrdata($reqid, $respuid, $reqdata, $respdata)) return FALSE;
	}
	return TRUE;
}

function command_checkscore($reqid = FALSE, $respuid = FALSE, $print = TRUE) {
	if($reqid == FALSE) {
		$reqid = getline("Requirement to check score on: ");
	}
	if($respuid == FALSE) $respuid = $GLOBALS['username'];
	$ll = exec('remctl privapp.api.cluenet.org privapp getscore ' . myescapeshellarg($reqid) . ' ' . myescapeshellarg($respuid), $cmdout, $rv);
	if($rv != 0) { echo "Error getting score.\n"; return FALSE; }
	if($print) {
		echo "Your score on your " . $reqid . " is: " . $ll . "\n";
		return TRUE;
	}
	return $ll;
}

function respond_essay($reqid, $reqdata) {
	global $ldap;
	global $username;
	$respuid = $username;
	check_connect_ldap();
	$r = ldap_search($ldap, 'requirementID=' . ldapescape($reqid) . ',ou=requirements,dc=cluenet,dc=org', '(&(objectClass=RequirementResponse)(responseUserID=' . ldapescape($respuid) . '))', array("responseData"));
	if($r == FALSE) { echo "Error getting response data: " . $ldap_error($ldap) . "\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries["count"] > 0) $respdata = getresponsedata($reqid, $respuid); else $respdata = FALSE;
	$alreadyresponded = FALSE;
	if($lentries["count"] > 0) {
		$alreadyresponded = TRUE;
		if(getboolean("You have already responded to this requirement.  Are you sure you want to rewrite it? (yes/no): ") == FALSE) return FALSE;
		if(getboolean("Would you like to view your last response before creating a new one? (yes/no): ") == TRUE) {
			if($respdata == FALSE) {
				echo "There is no response data for your previous response.\n";
			} else {
				if(view_essay($reqid, $respuid, $reqdata, $respdata) == FALSE) return FALSE;
			}
		}
		echo "Done viewing previous response.\n";
	}
	echo "\nThe essay question is: " . $reqdata . "\n\n";
	echo "For essay questions, you should have your essay written beforehand in a separate application, ready to paste into this window, beforehand.  This way, if an error occurs, you won't lose your essay.\n";
	while(TRUE) {
		$b = getboolean("Do you have your essay typed out and ready to paste? (yes/no): ");
		if($b == TRUE) break;
		echo "Ok, I'll wait ...\n\n";
	}
	echo "\n";
	echo "Paste your essay now.  When you're done pasting, type Done on a line by itself.\n";
	echo "PASTE HERE:\n";
	$newrespdata = "";
	while(TRUE) {
		$cline = getline('');
		if(strcasecmp($cline, "Done") == 0) break;
		$newrespdata .= $cline . "\n";
	}
	echo "\n";
	$words = explode(' ', $newrespdata);
	if(sizeof($words) < 300) echo "Warning!  This essay is less than 300 words.  If you submit it, people might not vote high scores for it.\n";
	if(getboolean("Would you like to submit this essay? (yes/no): ") == FALSE) {
		echo "Ok, I won't submit it ...\n";
		return FALSE;
	}
	$active = getboolean("Would you like to activate this response so that it can be voted on and scored? (yes/no): ");
	echo "Submitting response ...\n";
	if(!$alreadyresponded) {
		system('remctl privapp.api.cluenet.org privapp newresponse ' . myescapeshellarg($reqid), $rv);
		if($rv != 0) { echo "Error creating new response entry.\n"; return FALSE; }
	}
	$newentry = array();
	$newentry['responsedata'] = $newrespdata;
	$newentry['lastmodificationtime'] = gmdate('YmdHis\Z');
	if($active) $newentry['isactive'] = 'TRUE'; else $newentry['isactive'] = 'FALSE';
	check_connect_ldap();
	$r = ldap_mod_replace($ldap, 'responseUserID=' . ldapescape($respuid) . ',requirementID=' . ldapescape($reqid) . ',ou=requirements,dc=cluenet,dc=org', $newentry);
	if($r == FALSE) { echo "Error submitting response.\n"; return FALSE; }
	echo "Response submitted.\n";
	return TRUE;
}

function respond_shortanswerseries($reqid, $reqdata) {
	global $ldap;
	global $username;
	$respuid = $username;
	check_connect_ldap();
	$r = ldap_search($ldap, 'requirementID=' . ldapescape($reqid) . ',ou=requirements,dc=cluenet,dc=org', '(&(objectClass=RequirementResponse)(responseUserID=' . ldapescape($respuid) . '))', array("responseData"));
	if($r == FALSE) { echo "Error getting response data: " . $ldap_error($ldap) . "\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries["count"] > 0) $respdata = getresponsedata($reqid, $respuid); else $respdata = FALSE;
	$alreadyresponded = FALSE;
	if($lentries["count"] > 0) {
		$alreadyresponded = TRUE;
		if(getboolean("You have already responded to this requirement.  Are you sure you want to rewrite it? (yes/no): ") == FALSE) return FALSE;
		if(getboolean("Would you like to view your last response before creating a new one? (yes/no): ") == TRUE) {
			if($respdata == FALSE) {
				echo "There is no response data for your previous response.\n";
			} else {
				if(view_shortanswerseries($reqid, $respuid, $reqdata, $respdata) == FALSE) return FALSE;
			}
		}
		echo "Done viewing previous response.\n";
	}
	$questions = explode("\n", trim($reqdata));
	echo "There are " . sizeof($questions) . " questions in this series.\n";
	echo "Answer each question on a single line with concise, but accurate and informative, answers.  One to four sentences is approximately the right length.\n";
	getline("Press ENTER to start answering questions.");
	echo "\n";
	$newrespdata = "";
	for($i = 0; $i < sizeof($questions); $i++) {
		echo ($i + 1) . ". " . $questions[$i] . "\n";
		$canswer = getline("Answer: ");
		echo "\n";
		$newrespdata .= $canswer . "\n";
	}
	echo "Done answering questions.\n";
	if(getboolean("Would you like to submit these answers? (yes/no): ") == FALSE) {
		echo "Ok, I won't submit it ...\n";
		return FALSE;
	}
	$active = getboolean("Would you like to activate this response so that it can be voted on and scored? (yes/no): ");
	echo "Submitting response ...\n";
	if(!$alreadyresponded) {
		system('remctl privapp.api.cluenet.org privapp newresponse ' . myescapeshellarg($reqid), $rv);
		if($rv != 0) { echo "Error creating new response entry.\n"; return FALSE; }
	}
	$newentry = array();
	$newentry['responsedata'] = $newrespdata;
	$newentry['lastmodificationtime'] = gmdate('YmdHis\Z');
	if($active) $newentry['isactive'] = 'TRUE'; else $newentry['isactive'] = 'FALSE';
	check_connect_ldap();
	$r = ldap_mod_replace($ldap, 'responseUserID=' . ldapescape($respuid) . ',requirementID=' . ldapescape($reqid) . ',ou=requirements,dc=cluenet,dc=org', $newentry);
	if($r == FALSE) { echo "Error submitting response.\n"; return FALSE; }
	echo "Response submitted.\n";
	return TRUE;
}

function respond_multiplechoicequestions($reqid, $reqdata) {
	global $ldap;
	global $username;
	$respuid = $username;
	check_connect_ldap();
	$r = ldap_search($ldap, 'requirementID=' . ldapescape($reqid) . ',ou=requirements,dc=cluenet,dc=org', '(&(objectClass=RequirementResponse)(responseUserID=' . ldapescape($respuid) . '))', array("responseData"));
	if($r == FALSE) { echo "Error getting response data: " . $ldap_error($ldap) . "\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries["count"] > 0) $respdata = getresponsedata($reqid, $respuid); else $respdata = FALSE;
	$alreadyresponded = FALSE;
	if($lentries["count"] > 0) {
		$alreadyresponded = TRUE;
		if(getboolean("You have already responded to this requirement.  Are you sure you want to rewrite it? (yes/no): ") == FALSE) return FALSE;
		if(getboolean("Would you like to view your last response before creating a new one? (yes/no): ") == TRUE) {
			if($respdata == FALSE) {
				echo "There is no response data for your previous response.\n";
			} else {
				if(view_multiplechoicequestions($reqid, $respuid, $reqdata, $respdata) == FALSE) return FALSE;
			}
		}
		echo "Done viewing previous response.\n";
	}
	$questions = explode("\n\n", trim($reqdata));
	echo "There are " . sizeof($questions) . " questions in this set.\n";
	echo "Each question will be presented along with a series of answers.  Type the letter of the correct answer and press ENTER.\n";
	getline("Press ENTER to start answering questions.");
	echo "\n";
	$newrespdata = "";
	for($i = 0; $i < sizeof($questions); $i++) {
		echo ($i + 1) . ". " . str_replace("\\n", "\n", $questions[$i]) . "\n";
		$canswer = strtolower(getline("Answer: "));
		echo "\n";
		$newrespdata .= $canswer . "\n";
	}
	echo "Done answering questions.\n";
	if(getboolean("Would you like to submit these answers? (yes/no): ") == FALSE) {
		echo "Ok, I won't submit it ...\n";
		return FALSE;
	}
	$active = getboolean("Would you like to activate this response so that it can be scored? (yes/no): ");
	echo "Submitting response ...\n";
	if(!$alreadyresponded) {
		system('remctl privapp.api.cluenet.org privapp newresponse ' . myescapeshellarg($reqid), $rv);
		if($rv != 0) { echo "Error creating new response entry.\n"; return FALSE; }
	}
	$newentry = array();
	$newentry['responsedata'] = $newrespdata;
	$newentry['lastmodificationtime'] = gmdate('YmdHis\Z');
	if($active) $newentry['isactive'] = 'TRUE'; else $newentry['isactive'] = 'FALSE';
	check_connect_ldap();
	$r = ldap_mod_replace($ldap, 'responseUserID=' . ldapescape($respuid) . ',requirementID=' . ldapescape($reqid) . ',ou=requirements,dc=cluenet,dc=org', $newentry);
	if($r == FALSE) { echo "Error submitting response.\n"; return FALSE; }
	echo "Response submitted.\n";
	if(getboolean("Would you like to check your score on the questions you just answered? (yes/no): ") == TRUE) {
		$score = command_checkscore($reqid, $respuid, FALSE);
		if($score === FALSE || !is_numeric($score)) { echo "Error.\n"; return FALSE; }
		echo "You scored " . $score . "%.\n";
		getline("Press ENTER to continue.\n");
	}
	return TRUE;
}

function respond_uservote($reqid, $reqdata) {
	global $ldap;
	global $username;
	$respuid = $username;
	check_connect_ldap();
	$r = ldap_search($ldap, 'requirementID=' . ldapescape($reqid) . ',ou=requirements,dc=cluenet,dc=org', '(&(objectClass=RequirementResponse)(responseUserID=' . ldapescape($respuid) . '))', array("responseUserID"));
	if($r == FALSE) { echo "Error getting response data: " . $ldap_error($ldap) . "\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries["count"] > 0) {
		$alreadyresponded = TRUE;
		if(getboolean("You have already submitted your name for review for this requirement.  Would you like to prompt for a revote?  (Don't do this too often or it could annoy the admins) (yes/no): ") == FALSE) return FALSE;
	} else {
		echo "All you have to do for this requirement is to submit your name for voting and approval.\n";
		if(getboolean("Would you like to submit your name now? (yes/no): ") == FALSE) return FALSE;
	}
	echo "Submitting name ...\n";
	if(!$alreadyresponded) {
		system('remctl privapp.api.cluenet.org privapp newresponse ' . myescapeshellarg($reqid), $rv);
		if($rv != 0) { echo "Error creating new response entry.\n"; return FALSE; }
	}
	$newentry = array();
	$newentry['lastmodificationtime'] = gmdate('YmdHis\Z');
	$newentry['isactive'] = 'TRUE';
	check_connect_ldap();
	$r = ldap_mod_replace($ldap, 'responseUserID=' . ldapescape($respuid) . ',requirementID=' . ldapescape($reqid) . ',ou=requirements,dc=cluenet,dc=org', $newentry);
	if($r == FALSE) { echo "Error submitting response.\n"; return FALSE; }
	echo "Name submitted.\n";
	return TRUE;
}

function command_respond($reqid = FALSE) {
	global $ldap;
	global $username;
	if($reqid == FALSE) {
		$reqid = getline("Requirement to respond to: ");
	}
	check_connect_ldap();
	$r = ldap_search($ldap, 'ou=requirements,dc=cluenet,dc=org', '(&(objectClass=Requirement)(requirementID=' . ldapescape($reqid) . '))', array("requirementtype", "requirementoverview", "requirementdata", "scorecheckmethod"));
	if($r == FALSE) { echo "Error getting requirement data: " . $ldap_error($ldap) . "\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries["count"] < 1) { echo "No such requirement.\n"; return FALSE; }
	if(!isset($lentries[0]['requirementtype'][0])) { echo "Requirement error.\n"; return FALSE; }
	if(!isset($lentries[0]['scorecheckmethod'][0])) { echo "Requirement error.\n"; return FALSE; }
	$reqtype = $lentries[0]['requirementtype'][0];
	$ssm = $lentries[0]['scorecheckmethod'][0];
	if(!isset($lentries[0]['requirementdata'][0])) $reqdata = FALSE; else $reqdata = $lentries[0]['requirementdata'][0];
	if(!isset($lentries[0]['requirementoverview'][0])) $reqoverview = FALSE; else $reqoverview = $lentries[0]['requirementoverview'][0];
	echo '== ' . $reqid . ' ==' . "\n";
	if($reqoverview != FALSE) echo "- " . $reqoverview . "\n";
	echo "\n";
	if($reqtype == 'essay') echo "This is an essay requirement.\n";
	if($reqtype == 'shortanswerseries') echo "This is a short answer series requirement.\n";
	if($reqtype == 'multiplechoicequestions') echo "This is a multiple choice questions requirement.\n";
	if($reqtype == 'uservote') echo "This is a strictly voting requirement.\n";
	getline("Press ENTER to answer this requirement.");
	if($reqtype == 'essay') return respond_essay($reqid, $reqdata);
	if($reqtype == 'shortanswerseries') return respond_shortanswerseries($reqid, $reqdata);
	if($reqtype == 'multiplechoicequestions') return respond_multiplechoicequestions($reqid, $reqdata);
	if($reqtype == 'uservote') return respond_uservote($reqid, $reqdata);
	if($reqtype == 'remctl') { echo "This is a remctl requirement.  You cannot respond to this requirement type.  Scoring information is taken from an external source.\n"; return FALSE; }
	echo "Unknown requirement type.\n";
	return FALSE;
}

function command_activateresponse($reqid = FALSE) {
	global $ldap;
	global $username;
	$respuid = $username;
	if($reqid == FALSE) {
		$reqid = getline("Requirement to activate your response to: ");
	}
	check_connect_ldap();
	$r = ldap_search($ldap, 'ou=requirements,dc=cluenet,dc=org', '(&(objectClass=Requirement)(requirementID=' . ldapescape($reqid) . '))', array("requirementtype", "requirementoverview", "requirementdata", "scorecheckmethod"));
	if($r == FALSE) { echo "Error getting requirement data: " . $ldap_error($ldap) . "\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries["count"] < 1) { echo "No such requirement.\n"; return FALSE; }
	$r = ldap_search($ldap, 'requirementID=' . ldapescape($reqid) . ',ou=requirements,dc=cluenet,dc=org', '(&(objectClass=RequirementResponse)(responseUserID=' . ldapescape($respuid) . '))', array("isActive"));
	if($r == FALSE) { echo "Error getting response data: " . $ldap_error($ldap) . "\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries["count"] < 1) { echo "You have not yet responded to this requirement.\n"; return FALSE; }
	if(isset($lentries[0]['isactive'][0])) if($lentries[0]['isactive'][0] == 'TRUE') { echo "This response is already active.\n"; return TRUE; }
	$newentry = array();
	$newentry['isactive'] = 'TRUE';
	$r = ldap_mod_replace($ldap, 'responseUserID=' . ldapescape($respuid) . ',requirementID=' . ldapescape($reqid) . ',ou=requirements,dc=cluenet,dc=org', $newentry);
	if($r == FALSE) { echo "Error activating response.\n"; return FALSE; }
	echo "Response activated.\n";
	return TRUE;
}

function command_deactivateresponse($reqid = FALSE) {
	global $ldap;
	global $username;
	$respuid = $username;
	if($reqid == FALSE) {
		$reqid = getline("Requirement to activate your response to: ");
	}
	check_connect_ldap();
	$r = ldap_search($ldap, 'ou=requirements,dc=cluenet,dc=org', '(&(objectClass=Requirement)(requirementID=' . ldapescape($reqid) . '))', array("requirementtype"));
	if($r == FALSE) { echo "Error getting requirement data: " . $ldap_error($ldap) . "\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries["count"] < 1) { echo "No such requirement.\n"; return FALSE; }
	$r = ldap_search($ldap, 'requirementID=' . ldapescape($reqid) . ',ou=requirements,dc=cluenet,dc=org', '(&(objectClass=RequirementResponse)(responseUserID=' . ldapescape($respuid) . '))', array("isActive"));
	if($r == FALSE) { echo "Error getting response data: " . $ldap_error($ldap) . "\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries["count"] < 1) { echo "You have not yet responded to this requirement.\n"; return FALSE; }
	if(isset($lentries[0]['isactive'][0])) if($lentries[0]['isactive'][0] == 'FALSE') { echo "This response is already inactive.\n"; return TRUE; }
	$newentry = array();
	$newentry['isactive'] = 'FALSE';
	$r = ldap_mod_replace($ldap, 'responseUserID=' . ldapescape($respuid) . ',requirementID=' . ldapescape($reqid) . ',ou=requirements,dc=cluenet,dc=org', $newentry);
	if($r == FALSE) { echo "Error deactivating response.\n"; return FALSE; }
	echo "Response deactivated.\n";
	return TRUE;
}

function command_votestatus($reqid = FALSE, $erroronnvr = TRUE, $disptitle = FALSE) {
	global $ldap;
	global $username;
	$respuid = $username;
	if($reqid == FALSE) {
		$reqid = getline("Requirement to check vote status for: ");
	}
	check_connect_ldap();
	$r = ldap_search($ldap, 'ou=requirements,dc=cluenet,dc=org', '(&(objectClass=Requirement)(requirementID=' . ldapescape($reqid) . '))', array("requirementtype", "scorecheckmethod"));
	if($r == FALSE) { echo "Error getting requirement data: " . $ldap_error($ldap) . "\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries["count"] < 1) { echo "No such requirement.\n"; return FALSE; }
	$ssmparts = explode(":", $lentries[0]['scorecheckmethod'][0]);
	if($ssmparts[0] != 'vote') {
		if($erroronnvr) echo "That is not a voting requirement.\n";
		return FALSE;
	}
	$minvotes = $ssmparts[1];
	$r = ldap_search($ldap, 'requirementID=' . ldapescape($reqid) . ',ou=requirements,dc=cluenet,dc=org', '(&(objectClass=RequirementResponse)(responseUserID=' . ldapescape($respuid) . '))', array("isActive"));
	if($r == FALSE) { echo "Error getting response data: " . $ldap_error($ldap) . "\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries["count"] < 1) { echo "You have not yet responded to this requirement.\n"; return FALSE; }
	$r = exec('remctl privapp.api.cluenet.org privapp getvoteinfo ' . myescapeshellarg($reqid) . ' ' . myescapeshellarg($respuid), $cmdout, $rv);
	if($rv != 0) { echo "Error getting vote information: " . $r . "\n"; return FALSE; }
	$votes = array();
	$feedback = array();
	foreach($cmdout as $line) {
		$linesegs = explode(":", $line, 3);
		if($linesegs[0] == 'vote') $votes[] = $linesegs[2];
		if($linesegs[0] == 'feedback') $feedback[] = $linesegs[2];
	}
	if($disptitle != FALSE) echo $disptitle . "\n";
	echo "You currently have " . sizeof($votes) . " votes.\n";
	echo "These votes have the scores: " . implode(", ", $votes) . ".\n";
	$avg = 0;
	if(sizeof($votes) > 0) $avg = (floor(array_sum($votes) / sizeof($votes)));
	echo "The current average of these votes is " . $avg . ", however this may not be your actual score (use the checkscore command).\n";
	echo "The minimum number of votes that you need for this requirement is " . $minvotes . " before your score shows up as non-zero.\n";
	echo "\n";
	if(sizeof($feedback) < 1) {
		echo "Nobody has left you feedback yet.\n";
	} else {
		$plural = "user has";
		if(sizeof($feedback) > 1) $plural = "users have";
		echo sizeof($feedback) . " " . $plural . " left you feedback on this response.\n";
		echo "The feedback, in no particular order:\n";
		foreach($feedback as $f) echo "- " . $f . "\n";
	}
	return TRUE;
}

function command_allvotestatus() {
	global $ldap, $username;
	check_connect_ldap();
	$r = ldap_search($ldap, 'ou=requirements,dc=cluenet,dc=org', 'responseUserID=' . ldapescape($username), array('dn'));
	if($r == FALSE) { echo "Error searching LDAP\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries == FALSE) { echo "Error searching LDAP\n"; return FALSE; }
	for($i = 0; $i < $lentries["count"]; $i++) {
		$dn = $lentries[$i]["dn"];
		$r = preg_match('/^responseUserID=.*,requirementID=(.*),ou=requirements,dc=cluenet,dc=org$/', $dn, $matches);
		if($r != 1) { echo "Warning: Unexpected DN format\n"; continue; }
		$reqid = $matches[1];
		command_votestatus($reqid, FALSE, "\n\n== Status for response to requirement " . $reqid . " ==");
	}
	return TRUE;
}

function command_privilegerequirementstatus($privilege = FALSE, $print = TRUE) {
	if($privilege == FALSE) {
		$privilege = getline("Privilege to check requirement status for: ");
	}
	$segs = explode(":", $privilege);
	if(sizeof($segs) > 2) { echo "Invalid privilege format.  Should be _misc:ServicePack or Server:ServicePack.\n"; return FALSE; }
	if(sizeof($segs) == 1) { $segs[1] = $segs[0]; $segs[0] = "_misc"; }
	$server = $segs[0];
	$spack = $segs[1];
	$ll = exec('remctl privapp.api.cluenet.org privapp checkprivilegerequirements ' . myescapeshellarg($server) . ' ' . myescapeshellarg($spack), $cmdout, $rv);
	if($rv != 0) { echo "Error getting privilege requirement status: " . $ll . "\n"; return FALSE; }
	$hasno = FALSE;
	foreach($cmdout as $line) {
		$segs = explode(":", $line);
		if($print) {
			if($segs[2] == 'err') { echo "Error getting score for requirement " . $segs[0] . "\n"; continue; }
			if($segs[2] == 'noresponse') { echo "Requirement: " . $segs[0] . "   Minimum score: " . $segs[1] . "   You have not responded to this requirement yet\n"; continue; }
			echo "Requirement: " . $segs[0] . "   Minimum score: " . $segs[1] . "   Your current score: " . $segs[2] . "   Requirement fulfilled: " . $segs[3] . "\n";
		}
		if($segs[2] == 'err' || $segs[2] == 'noresponse' || $segs[3] == 'No') $hasno = TRUE;
	}
	if($print) return TRUE;
	if($hasno) return FALSE;
	return TRUE;
}

function command_getprivilege($privilege = FALSE) {
	if($privilege == FALSE) {
		$privilege = getline("Privilege to get: ");
	}
	$hasreq = command_privilegerequirementstatus($privilege, FALSE);
	if(!$hasreq) { echo "You don't have the requirements necessary to get this privilege.  Use the privilegerequirementstatus command to check your requirements.\n"; return FALSE; }
	$segs = explode(":", $privilege);
	if(sizeof($segs) > 2) { echo "Invalid privilege format.  Should be _misc:ServicePack or Server:ServicePack.\n"; return FALSE; }
	if(sizeof($segs) == 1) { $segs[1] = $segs[0]; $segs[0] = "_misc"; }
	$server = $segs[0];
	$spack = $segs[1];
	$ll = exec('remctl privapp.api.cluenet.org privapp addprivilege ' . myescapeshellarg($server) . ' ' . myescapeshellarg($spack), $cmdout, $rv);
	if($rv != 0) { echo "Error adding privilege: " . $ll . "\n"; return FALSE; }
	echo "Privilege added.\n";
	return TRUE;
}

function servicepackeditor($sptext) {
	$segs = explode(':', $sptext);
	$spname = $segs[0];
	$spservstext = $segs[1];
	$spreqstext = $segs[2];
	$spservs = explode(",", $spservstext);
	$spreqtextarray = explode(",", $spreqstext);
	$spreqs = array();
	foreach($spreqtextarray as $cspt) {
		$sptar = explode(".", $cspt);
		$spreqs[$sptar[0]] = $sptar[1];
	}
	while(TRUE) {
		$command = getline("ServicePackEditor> ");
		if($command == "done" || $command == "exit" || $command == "cancel" || $command === FALSE) break;
		if($command == "") continue;
		if($command == "help" || $command == "?") {
			echo "Commands: getname, listservices, addservice, delservice, listrequirements, listavailablerequirements, addrequirement, delrequirement, done, cancel\n";
			continue;
		}
		if($command == "getname" || $command == "gn") {
			echo $spname . "\n";
			continue;
		}
		if($command == "listservices" || $command == "ls") {
			foreach($spservs as $sps) echo $sps . "\n";
			continue;
		}
		if($command == "addservice" || $command == "as") {
			$sta = getline("Service to add: ");
			if(!in_array($sta, $spservs)) $spservs[] = $sta;
			continue;
		}
		if($command == "delservice" || $command == "ds") {
			$std = getline("Service to delete: ");
			if(!in_array($std, $spservs)) { echo "That service does not exist in this service pack.\n"; continue; }
			unset($spservs[array_search($std, $spservs)]);
			continue;
		}
		if($command == "listrequirements" || $command == "lr") {
			foreach($spreqs as $spr => $minscore) echo "A score of at least " . $minscore . " on " . $spr . "\n";
			continue;
		}
		if($command == "listavailablerequirements" || $command == "lar") {
			command_listallrequirements();
			continue;
		}
		if($command == "addrequirement" || $command == "ar") {
			$newreq = getline("Requirement name to add: ");
			$minscore = getline("Minimum score for " . $newreq . ": ");
			$spreqs[$newreq] = $minscore;
			continue;
		}
		if($command == "delrequirement" || $command == "dr") {
			$dr = getline("Requirement to delete: ");
			if(!isset($spreqs[$dr])) { echo "That requirement does not exist in this service pack.\n"; continue; }
			unset($spreqs[$dr]);
			continue;
		}
		if($command == "getspstring" || $command == "gsps") {
			$newreqtexts = array();
			foreach($spreqs as $rn => $minscore) $newreqtexts[] = $rn . '.' . $minscore;
			$newsptext = $spname . ':' . implode(',', $spservs) . ':' . implode(',', $newreqtexts);
			echo $newsptext . "\n";
			continue;
		}
		echo "Invalid command.  Use \"help\" for a list.\n";
	}
	if($command == "cancel") return $sptext;
	$newreqtexts = array();
	foreach($spreqs as $rn => $minscore) $newreqtexts[] = $rn . '.' . $minscore;
	$newsptext = $spname . ':' . implode(',', $spservs) . ':' . implode(',', $newreqtexts);
	return $newsptext;
}

function command_addservicepack() {
	global $ldap;
	$server = getline("Server to add service pack to: ");
	$serversegs = explode(".", $server);
	$server = $serversegs[0];
	check_connect_ldap();
	$r = ldap_search($ldap, 'ou=servers,dc=cluenet,dc=org', 'cn=' . ldapescape($server), array("servicerequirement"));
	if($r == FALSE) { echo "Error checking server.\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries == FALSE) { echo "Error checking server.\n"; return FALSE; }
	if($lentries["count"] < 1) { echo "No such server.\n"; return FALSE; }
	$spname = getline("Service pack name to add: ");
	if(isset($lentries[0]['servicerequirement'][0])) {
		for($i = 0; $i < $lentries[0]['servicerequirement']["count"]; $i++) {
			$spsegs = explode(":", $lentries[0]['servicerequirement'][$i]);
			if($spsegs[0] == $spname) { echo "That service pack already exists on that server.\n"; return FALSE; }
		}
	}
	$spservs = '';
	$spreqs = '';
	if($spname != "shellaccess") {
		if(strpos($spname, "shell") !== FALSE || strpos($spname, "ssh") !== FALSE) {
			echo "It looks like you might be trying to add a service pack for shell access without using the standard \"shellaccess\" name.\n";
			$b = getboolean("Would you like to use the standard \"shellaccess\" name instead? (yes/no): ");
			if($b == TRUE) $spname = "shellaccess";
		}
	}
	if($spname == "shellaccess") {
		$b = getboolean("Would you like to prefill the service list with the standard shell access services? (yes/no): ");
		if($b == TRUE) $spservs = 'ssh,sshd,su,sudo,atd,cron,passwd,login';
		$b = getboolean("Would you like to prefill the requirements list with the standard shell access requirements? (yes/no): ");
		if($b == TRUE) $spreqs = 'basicshellquestions.75,basicmiscquestions.75,aboutmeessay.75,shellusageessay.75,communitycontributeessay.50,blankspaceessay.40,tosquestions.100,cluepoints.750,netadminshellvote.65';
	}
	$editsp = TRUE;
	if(strlen($spservs) > 0 && strlen($spreqs) > 0) {
		$editsp = getboolean("Would you like to view or edit the service or requirement list for this service pack before adding it? (yes/no): ");
	}
	$sptext = $spname . ':' . $spservs . ':' . $spreqs;
	if($editsp) {
		$newsptext = servicepackeditor($sptext);
		if($newsptext != FALSE) $sptext = $newsptext;
	}
	$b = getboolean("Do you want to add the service pack " . $spname . "? (yes/no): ");
	if($b == FALSE) return FALSE;
	check_connect_ldap();
	$newentry = array();
	$newentry['servicerequirement'] = $sptext;
	$r = ldap_mod_add($ldap, 'cn=' . ldapescape($server) . ',ou=servers,dc=cluenet,dc=org', $newentry);
	if($r == FALSE) { echo "Error adding service requirement attribute.\n"; return FALSE; }
	echo "Service pack added.\n";
	return TRUE;
}

function command_editservicepack() {
	global $ldap;
	$server = getline("Server on which to edit service pack: ");
	$serversegs = explode(".", $server);
	$server = $serversegs[0];
	check_connect_ldap();
	$r = ldap_search($ldap, 'ou=servers,dc=cluenet,dc=org', 'cn=' . ldapescape($server), array("servicerequirement"));
	if($r == FALSE) { echo "Error checking server.\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries == FALSE) { echo "Error checking server.\n"; return FALSE; }
	if($lentries["count"] < 1) { echo "No such server.\n"; return FALSE; }
	$spname = getline("Service pack name to edit: ");
	$sptext = FALSE;
	if(isset($lentries[0]['servicerequirement'][0])) {
		for($i = 0; $i < $lentries[0]['servicerequirement']["count"]; $i++) {
			$spsegs = explode(":", $lentries[0]['servicerequirement'][$i]);
			if($spsegs[0] == $spname) $sptext = $lentries[0]['servicerequirement'][$i];
		}
	}
	if($sptext == FALSE) { echo "Service pack not found on that server.\n"; return FALSE; }
	$oldsptext = $sptext;
	$sptext = servicepackeditor($sptext);
	if($sptext == FALSE) return FALSE;
	$b = getboolean("Do you want to change the service pack " . $spname . "? (yes/no): ");
	if($b == FALSE) return FALSE;
	check_connect_ldap();
	$delentry = array('servicerequirement' => $oldsptext);
	$r = ldap_mod_del($ldap, 'cn=' . ldapescape($server) . ',ou=servers,dc=cluenet,dc=org', $delentry);
	if($r == FALSE) { echo "Error deleting old service requirement attribute.\n"; return FALSE; }
	$newentry = array();
	$newentry['servicerequirement'] = $sptext;
	$r = ldap_mod_add($ldap, 'cn=' . ldapescape($server) . ',ou=servers,dc=cluenet,dc=org', $newentry);
	if($r == FALSE) { echo "Error adding new service requirement attribute.\n"; return FALSE; }
	echo "Service pack changed.\n";
	return TRUE;
}

function command_delservicepack() {
	global $ldap;
	$server = getline("Server on which to delete service pack: ");
	$serversegs = explode(".", $server);
	$server = $serversegs[0];
	check_connect_ldap();
	$r = ldap_search($ldap, 'ou=servers,dc=cluenet,dc=org', 'cn=' . ldapescape($server), array("servicerequirement"));
	if($r == FALSE) { echo "Error checking server.\n"; return FALSE; }
	$lentries = ldap_get_entries($ldap, $r);
	if($lentries == FALSE) { echo "Error checking server.\n"; return FALSE; }
	if($lentries["count"] < 1) { echo "No such server.\n"; return FALSE; }
	$spname = getline("Service pack name to delete: ");
	$sptext = FALSE;
	if(isset($lentries[0]['servicerequirement'][0])) {
		for($i = 0; $i < $lentries[0]['servicerequirement']["count"]; $i++) {
			$spsegs = explode(":", $lentries[0]['servicerequirement'][$i]);
			if($spsegs[0] == $spname) $sptext = $lentries[0]['servicerequirement'][$i];
		}
	}
	if($sptext == FALSE) { echo "Service pack not found on that server.\n"; return FALSE; }
	$b = getboolean("Do you want to delete the service pack " . $spname . "? (yes/no): ");
	if($b == FALSE) return FALSE;
	check_connect_ldap();
	$delentry = array('servicerequirement' => $sptext);
	$r = ldap_mod_del($ldap, 'cn=' . ldapescape($server) . ',ou=servers,dc=cluenet,dc=org', $delentry);
	if($r == FALSE) { echo "Error deleting service requirement attribute.\n"; return FALSE; }
	echo "Service pack deleted.\n";
	return TRUE;
}

if(file_exists("secret.php")) {
	$havesecret = TRUE;
	include("secret.php");
} else $havesecret = FALSE;

$username = strtolower(getline('Enter username or "new": '));
if($username == "new") { donewaccount(); exit(0); }
$r = preg_match('/^[a-z][a-z0-9_]{1,24}[a-z0-9_]$/', $username);
if($r != 1) errorexit('Invalid username');

$ccfile = '/tmp/acctshell_krb5cc_' . $username;
passthru('KRB5CCNAME=' . $ccfile . ' /usr/bin/kinit ' . $username . '@CLUENET.ORG', $rv);
if($rv != 0) errorexit('Could not authenticate user');
putenv('KRB5CCNAME=' . $ccfile);
/*$ldap = ldap_connect('ldap://ldap.cluenet.org');
if(!$ldap) errorexit("Could not connect to LDAP server");
if(!ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3)) errorexit("Could not set LDAP protocol to version 3");
if(!ldap_start_tls($ldap)) errorexit("Could not start TLS on LDAP");
if(!ldap_sasl_bind($ldap)) errorexit("Could not bind to LDAP");*/
check_connect_ldap();

while(TRUE) {
	$argstack = array();
	$command = getline('acctshell> ');
	if($command === FALSE) break;
	$cmdsegs = explode(' ', $command);
	$command = $cmdsegs[0];
	$argstack = array();
	for($i = 1; $i < sizeof($cmdsegs); $i++) array_unshift($argstack, $cmdsegs[$i]);
	if($command == "") continue;
	if($command == "exit" || $command == "quit") break;
	if($command == "reconnect" || $command == "rc") { command_reconnect(); continue; }
	if($command == "getme") { passthru('cat /home/acctshell/acctshell.php'); continue; }
	if($command == "news") { command_news(); continue; }
	if($command == "whoami") { echo $username . "\n"; continue; }
	if($command == "help" || $command == "h" || $command == "?") { command_help(); continue; }
	if($command == "adminhelp") { command_adminhelp(); continue; }
	if($command == "passwd") { command_passwd(); continue; }
	if($command == "modattr" || $command == "m") { command_modattr(); continue; }
	if($command == "changenick") { command_changenick(); continue; }
	if($command == "changeemail") { command_changeemail(); continue; }
	if($command == "initvouch" || $command == "iv") { command_initvouch(); continue; }
	if($command == "votetodo" || $command == "vt") { command_votetodo(); continue; }
	if($command == "vote" || $command == "v") { command_vote(); continue; }
	if($command == "votenext" || $command == "vn" || $command == "n") { command_votenext(); continue; }
	if($command == "listmiscprivileges" || $command == "lmp") { command_listmiscprivileges(); continue; }
	if($command == "listserverprivileges" || $command == "lsp") { command_listserverprivileges(); continue; }
	if($command == "listallprivileges" || $command == "lap" || $command == "lp") { command_listallprivileges(); continue; }
	if($command == "listservices") { command_listservices(); continue; }
	if($command == "listrequirements" || $command == "lr") { command_listrequirements(); continue; }
	if($command == "listallrequirements" || $command == "lar") { command_listallrequirements(); continue; }
	if($command == "respond" || $command == "answer" || $command == "r") { command_respond(); continue; }
	if($command == "viewmyresponse" || $command == "vr" || $command == "vmr") { command_viewmyresponse(); continue; }
	if($command == "checkscore" || $command == "cs") { command_checkscore(); continue; }
	if($command == "activateresponse" || $command == "act") { command_activateresponse(); continue; }
	if($command == "deactivateresponse" || $command == "deact") { command_deactivateresponse(); continue; }
	if($command == "votestatus" || $command == "vs") { command_votestatus(); continue; }
	if($command == "allvotestatus" || $command == "avs") { command_allvotestatus(); continue; }
	if($command == "privilegerequirementstatus" || $command == "prs") { command_privilegerequirementstatus(); continue; }
	if($command == "getprivilege" || $command == "gp") { command_getprivilege(); continue; }
	if($command == "addservicepack") { command_addservicepack(); continue; }
	if($command == "editservicepack") { command_editservicepack(); continue; }
	if($command == "delservicepack") { command_delservicepack(); continue; }
	if($havesecret) if(processsecretcommand($command) == TRUE) continue;
	echo $invalidcmdtext;
}

echo "Goodbye.\n";
usleep(250000);
exit(0);


