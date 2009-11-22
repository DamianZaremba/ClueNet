<?php
$ds = ldap_connect("ldap.cluenet.org");
if ($ds) {
	if (!ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3)) {
		fatal_error("Could not set version");
	}
	if (!ldap_start_tls($ds)) {
		fatal_error("TLS failed");
	}
	$bth = ldap_bind($ds);
	if($bth){
		$result = ldap_list($ds, "ou=servers, dc=cluenet, dc=org", "cn=*");
		$data = ldap_get_entries($ds, $result);
		$total_hddsize = 0;
		$total_ramsize = 0;
		$total_cpuspeed = 0;
		$total_servers = $data["count"];

			echo('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"><html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en"><head><meta http-equiv="Content-Type" content="text/html; charset=US-ASCII" /><title>Hamlin wants a title</title></head><body><table border="1" width="100%"><tr><td align="center"><b>Server</b></td><td align="center"><b>Owner</b></td><td align="center"><b>Purpose</b></td><td align="center"><b>SSH Port</b></td><td align="center"><b>User Accessible</b></td><td align="center"><b>Official</b></td><td align="center"><b>Active</b></td></tr>');
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
				<tr><td align="center">'.$data[$i]['cn'][0].'</td><td align="center"><a href="http://search.cluenet.org/?q='.$owner[0].'">'.$owner[0].'</a></td><td align="center">'.$data[$i]['purpose'][0].'</td><td align="center">'.$data[$i]['sshport'][0].'</td><td align="center">'.$useaccessible.'</td><td align="center">'.$official.'</td><td align="center">'.$active.'</td></tr>
			');
			}
		}
			echo('</table>
			<table border="1" width="400px">
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
			</table></body></html>
			');
	}else{
		fatal_error("Failed to bind");
	}
}
?>
