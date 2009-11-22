<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8" />
	<title>ClueNet Server List</title>
	<meta http-equiv="X-UA-Compatible" content="IE=8" />
	<meta name="author" content="Damian Zaremba" />
	<style type="text/css">
body {
	font-family: "Segoe UI", Arial, sans-serif;
	background: #000;
	background: url(images/bg.jpg) fixed no-repeat #000;
	color: #fff;
	max-width: 650px;
	margin-left: auto;
	margin-right: auto;
}
.error { color: #f33; }
a:link, a:visited {
	color: #fff;
	text-decoration: none;
	border-bottom: 1px dotted #fff;
}
a:hover, a:focus, input:hover, input:focus {
	color: #3f3;
	border-color: #3f3;
}
#title {
	opacity: 0.8;
	margin-top: 0.1em;
	margin-bottom: 0.7em;
}
#title h1 {
	margin-top: 0em;
	margin-bottom: 0.1em;
	font-weight: normal;
}
#title #realname {
	margin-top: 0.1em;
	margin-bottom: 0em;
}
#searchbar {
	width: 100%;
	text-align: left;
	font-family: "Segoe UI", Arial, sans-serif
	margin-top: 0.2em;
	margin-bottom: 0.3em;
}
input#q {
	width: 33%;
}
td { padding: 0.2em; }
.box {
	color: #fff;
	background: #000;
	border: 1px solid #000;
	opacity: 0.7;
	-moz-border-radius: 5px;
	padding: 0.2em;
	margin-top: 0.5em;
	margin-bottom: 0.5em;
}
.box > * {
	margin: 0.2em;
}
.box h2 {
	margin-top: 0em;
}
p {
	margin-top: 0.5em;
	margin-bottom: 0.5em;
}
.footer {
	font-size: small;
	font-style: italic;
	text-align: right;
	color: black;
}
input {
	background: #000;
	color: #fff;
	border: 1px solid #bbb;
	-moz-border-radius: 3px;
	font-family: "Segoe UI", Arial, sans-serif;
}
	</style>
</head>
<body>
<div class="box">
<h1>Search</h1>
<form method="get" action="index.php">
	<div>
		<input type="text" name="server" id="server" value="<?php if(isset($_GET['server'])){ echo $_GET['server']; } ?>" />
		<input type="submit" value="search" />
	</div>
</form>
<h1>Advanced Search</h1>
<form method="get" action="index.php">
	<div>
		<p>Here you can search using LDAP filters</p>
		<input type="text" name="query" id="query" value="<?php if(isset($_GET['query'])){ echo $_GET['query']; } ?>" />
		<input type="submit" value="search" />
	</div>
</form>
<a href="servers2.php">Alt</a>
</div>
<div class="box">
<h1>ClueNet Server List V2</h1>
<p>Here is a list of all servers and what they do.</p>
<p>Click on a servers name to get more detail.</p>
</div>
<?php
if(isset($_GET['server'])){
$ds = ldap_connect("ldap.cluenet.org");
if ($ds) {
	if (!ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3)) {
		die("Could not set version");
	}
	if (!ldap_start_tls($ds)) {
		die("TLS failed");
	}
	$bth = ldap_bind($ds);
	if($bth){
		$result = ldap_list($ds, "ou=servers, dc=cluenet, dc=org", "cn=".$_GET['server']);
		$data = ldap_get_entries($ds, $result);
		$total_servers = $data["count"];
		if($total_servers >0){
		for ($i=0; $i<=$total_servers;$i++) {
			if($data[$i]['cn'][0] != ''){			
			$owner = explode("uid=", $data[$i]['owner'][0]); $owner = explode(",", $owner[1]);
			$admin_count = $data[$i]['authorizedadministrator']['count'];
			if($admin_count > 0){
			$admins = "<ul>";
			$ii=0;
			while($ii<$admin_count){
			$admin_tmp = explode("uid=", $data[$i]['authorizedadministrator'][$ii]); $admin_tmp = explode(",", $admin_tmp[1]);
			$admins .= "<li><a href=\"http://search.cluenet.org/?q=".$admin_tmp[0]."\">".$admin_tmp[0]."</a></li>";
			$ii++;
			}
			$admins .= "</ul>";
			}else{
			$admins = "No Authorized Administrators";
			}
			if($data[$i]['isofficial'][0] == 'TRUE'){ $official = '<img src="images/yes.png" alt="yes" />'; }else{ $official = '<img src="images/no.png" alt="no" />'; }
			if($data[$i]['isactive'][0] == 'TRUE'){ $active = '<img src="images/yes.png" alt="yes" />'; $count_active++; }else{ $active = '<img src="images/no.png" alt="no" />'; $count_inactive++; }
			if($data[$i]['useraccessible'][0] == 'TRUE'){ $useaccessible = '<img src="images/yes.png" alt="yes" />'; $count_useaccessible++; }else{ $useaccessible = '<img src="images/no.png" alt="no" />'; $count_notuseaccessible++; }
			if($data[$i]['serverrules'][0] == 'default'){ $server_rules = 'Standard ClueNet Rules'; }else{ $server_rules = $data[$i]['serverrules'][0]; }
			echo('
<div class="box">
<h1><a href="?server='.$data[$i]['cn'][0].'">'.$data[$i]['cn'][0].'</a></h1>
<p><strong>Owner:</strong> <a href="http://search.cluenet.org/?q='.$owner[0].'">'.$owner[0].'</a></p>
<p><strong>IP Address: </strong>'.$data[$i]['ipaddress'][0].'</p>
<p><strong>SSH Port:</strong> '.$data[$i]['sshport'][0].'</p>
<p><strong>Official:</strong> '.$official.'</p>
<p><strong>Active:</strong> '.$active.'</p>
<p><strong>User Accessible:</strong> '.$useaccessible.'</p>
<p><strong>Purpose:</strong></p>
<p>'.$data[$i]['purpose'][0].'</p>
<p><strong>Authorized Administrators:</strong></p>
<p>'.$admins.'</p>
<p><strong>Server Rules:</strong></p>
<p>'.$server_rules.'</p>
<p><strong>Network LAN:</strong></p>
<p>'.$data[$i]['networklan'][0].'</p>
<p><strong>Description:</strong></p>
<p>'.$data[$i]['description'][0].'</p>
<p><strong>HDD Size: </strong>'.$data[$i]['hddsize'][0].'GB</p>
<p><strong>CPU Type: </strong>'.$data[$i]['cputype'][0].'</p>
<p><strong>CPU Speed: </strong>'.$data[$i]['cpuspeed'][0].'</p>
<p><strong>RAM: </strong>'.$data[$i]['ramsize'][0].'MB</p>
<p><strong>OS: </strong>'.$data[$i]['operatingsystem'][0].'</p>
<p><strong>RX Network Speed: </strong>'.$data[$i]['networkrxspeed'][0].'</p>
<p><strong>TX Network Speed: </strong>'.$data[$i]['networktxspeed'][0].'</p>
<a href="index.php">View all</a>
</div>
			');
			}
		}
	}else{
echo('
<div class="box">
<h1>404</h1>
<p>Wooow hold up there. I can\'t find that server. Please make sure you entered a valid server.</p>
<a href="index.php">View all</a>
</div>
');
	}
	}else{
		die("Failed to bind");
	}
}


}else if(isset($_GET['query'])){
$ds = ldap_connect("ldap.cluenet.org");
if ($ds) {
	if (!ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3)) {
		die("Could not set version");
	}
	if (!ldap_start_tls($ds)) {
		die("TLS failed");
	}
	$bth = ldap_bind($ds);
	if($bth){
		$result = @ldap_list($ds, "ou=servers, dc=cluenet, dc=org", $_GET['query']);
		if($result){
			$data = ldap_get_entries($ds, $result);
			$total_servers = $data["count"];
			if($total_servers >0){
				for ($i=0; $i<=$total_servers;$i++) {
				if($data[$i]['cn'][0] != ''){			
			$owner = explode("uid=", $data[$i]['owner'][0]); $owner = explode(",", $owner[1]);
			$admin_count = $data[$i]['authorizedadministrator']['count'];
			if($admin_count > 0){
			$admins = "<ul>";
			$ii=0;
			while($ii<$admin_count){
			$admin_tmp = explode("uid=", $data[$i]['authorizedadministrator'][$ii]); $admin_tmp = explode(",", $admin_tmp[1]);
			$admins .= "<li><a href=\"http://search.cluenet.org/?q=".$admin_tmp[0]."\">".$admin_tmp[0]."</a></li>";
			$ii++;
			}
			$admins .= "</ul>";
			}else{
			$admins = "No Authorized Administrators";
			}
			if($data[$i]['isofficial'][0] == 'TRUE'){ $official = '<img src="images/yes.png" alt="yes" />'; }else{ $official = '<img src="images/no.png" alt="no" />'; }
			if($data[$i]['isactive'][0] == 'TRUE'){ $active = '<img src="images/yes.png" alt="yes" />'; $count_active++; }else{ $active = '<img src="images/no.png" alt="no" />'; $count_inactive++; }
			if($data[$i]['useraccessible'][0] == 'TRUE'){ $useaccessible = '<img src="images/yes.png" alt="yes" />'; $count_useaccessible++; }else{ $useaccessible = '<img src="images/no.png" alt="no" />'; $count_notuseaccessible++; }
			if($data[$i]['serverrules'][0] == 'default'){ $server_rules = 'Standard ClueNet Rules'; }else{ $server_rules = $data[$i]['serverrules'][0]; }
			echo('
<div class="box">
<h1><a href="?server='.$data[$i]['cn'][0].'">'.$data[$i]['cn'][0].'</a></h1>
<p><strong>Owner:</strong> <a href="http://search.cluenet.org/?q='.$owner[0].'">'.$owner[0].'</a></p>
<p><strong>IP Address: </strong>'.$data[$i]['ipaddress'][0].'</p>
<p><strong>SSH Port:</strong> '.$data[$i]['sshport'][0].'</p>
<p><strong>Official:</strong> '.$official.'</p>
<p><strong>Active:</strong> '.$active.'</p>
<p><strong>User Accessible:</strong> '.$useaccessible.'</p>
<p><strong>Purpose:</strong></p>
<p>'.$data[$i]['purpose'][0].'</p>
<p><strong>Authorized Administrators:</strong></p>
<p>'.$admins.'</p>
<p><strong>Server Rules:</strong></p>
<p>'.$server_rules.'</p>
<p><strong>Network LAN:</strong></p>
<p>'.$data[$i]['networklan'][0].'</p>
<p><strong>Description:</strong></p>
<p>'.$data[$i]['description'][0].'</p>
<p><strong>HDD Size: </strong>'.$data[$i]['hddsize'][0].'GB</p>
<p><strong>CPU Type: </strong>'.$data[$i]['cputype'][0].'</p>
<p><strong>CPU Speed: </strong>'.$data[$i]['cpuspeed'][0].'</p>
<p><strong>RAM: </strong>'.$data[$i]['ramsize'][0].'MB</p>
<p><strong>OS: </strong>'.$data[$i]['operatingsystem'][0].'</p>
<p><strong>RX Network Speed: </strong>'.$data[$i]['networkrxspeed'][0].'</p>
<p><strong>TX Network Speed: </strong>'.$data[$i]['networktxspeed'][0].'</p>
<a href="index.php">View all</a>
</div>
			');
			}
				}
			}else{
			echo('
				<div class="box">
				<h1>404</h1>
				<p>Wooow hold up there. I can\'t find any servers >:O</p>
				<a href="index.php">View all</a>
				</div>
			');
			}
		}else{
			echo('
				<div class="box">
				<h1>Woow....</h1>
				<p>That seems to be an invalid filter :(</p>
				<a href="index.php">View all</a>
				</div>
			');
		}
	}else{
		die("Failed to bind");
	}
}
}else{
$ds = ldap_connect("ldap.cluenet.org");
if ($ds) {
	if (!ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3)) {
		die("Could not set version");
	}
	if (!ldap_start_tls($ds)) {
		die("TLS failed");
	}
	$bth = ldap_bind($ds);
	if($bth){
		$result = ldap_list($ds, "ou=servers, dc=cluenet, dc=org", "cn=*");
		$data = ldap_get_entries($ds, $result);
		$total_hddsize = 0;
		$total_ramsize = 0;
		$total_cpuspeed = 0;
		$total_servers = $data["count"];
		if($total_servers >0){
		for ($i=0; $i<=$total_servers;$i++) {
			if($data[$i]['cn'][0] != ''){
			$total_hddsize = $total_hddsize+$data[$i]['hddsize'][0];
			$total_ramsize = $total_ramsize+$data[$i]['ramsize'][0];
			$total_cpuspeed = $total_cpuspeed+$data[$i]['cpuspeed'][0];
			
			$owner = explode("uid=", $data[$i]['owner'][0]); $owner = explode(",", $owner[1]);
			if($data[$i]['isofficial'][0] == 'TRUE'){ $official = '<img src="images/yes.png" alt="yes" />'; }else{ $official = '<img src="images/no.png" alt="no" />'; }
			if($data[$i]['isactive'][0] == 'TRUE'){ $active = '<img src="images/yes.png" alt="yes" />'; $count_active++; }else{ $active = '<img src="images/no.png" alt="no" />'; $count_inactive++; }
			if($data[$i]['useraccessible'][0] == 'TRUE'){ $useaccessible = '<img src="images/yes.png" alt="yes" />'; $count_useaccessible++; }else{ $useaccessible = '<img src="images/no.png" alt="no" />'; $count_notuseaccessible++; }

			echo('
<div class="box">
<h1><a href="?server='.$data[$i]['cn'][0].'">'.$data[$i]['cn'][0].'</a></h1>
<p><strong>Owner:</strong> <a href="http://search.cluenet.org/?q='.$owner[0].'">'.$owner[0].'</a></p>
<p><strong>Purpose:</strong> '.$data[$i]['purpose'][0].'</p>
<p><strong>SSH Port:</strong> '.$data[$i]['sshport'][0].'</p>
<p><strong>Official:</strong> '.$official.'</p>
<p><strong>Active:</strong> '.$active.'</p>
</div>
			');
			}
		}
			echo('
			<div class="box">
			<table border="0" width="80%">
			<tr><td align="center">Total HDD:</td><td align="center">'.$total_hddsize.' GB</td></tr>
			<tr><td align="center">Total Ram:</td><td align="center">'.$total_ramsize.' MB</td></tr>
			<tr><td align="center">Total CPU SPEED:</td><td align="center">'.$total_cpuspeed.' MHZ</td></tr>
			<tr><td align="center" colspan="2"></td></tr>
			<tr><td align="center">Servers User Accessible:</td><td align="center">'.$count_useaccessible.'</td></tr>
			<tr><td align="center">Servers Not User Accessible:</td><td align="center">'.$count_notuseaccessible.'</td></tr>
			<tr><td align="center" colspan="2"></td></tr>
			<tr><td align="center">Servers Active:</td><td align="center">'.$count_active.'</td></tr>
			<tr><td align="center">Servers Inactive:</td><td align="center">'.$count_inactive.'</td></tr>
			<tr><td align="center">Total Servers:</td><td align="center">'.$total_servers.'</td></tr>
			</table></div>
			');
	}else{
echo('
<div class="box">
<h1>404</h1>
<p>Wooow hold up there. I can\'t find any servers. Seems something is broke.</p>
</div>
');
	}
	}else{
		die("Failed to bind");
	}
}
}
?>
<p class="footer">Damian</p>

</body>
</html>
