#!/usr/bin/php
<?php

// Add servers that are permanently down here.
$ignored_servers = array(
	"Cobi",
	"cobigw",
);

$ldap = ldap_connect("ldap.cluenet.org", 389);

if (!ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3))
	die("ldap_set_option");

if (!ldap_start_tls($ldap))
	die("start_tls");

if (!ldap_bind($ldap))
	die("bind");

$res = ldap_search($ldap, "ou=servers,dc=cluenet,dc=org", "(cn=*)");

$info = ldap_get_entries($ldap, $res);

$offline_servers = array();

echo "To regenerate this list, run [[ClueNet:Comparison_of_Servers/serverlist.php|serverlist.php]] and paste the output to this page.

{| class=\"wikitable sortable\" style=\"text-align: center; width: 100%;\"
|-
! Server
! Owner !! Purpose
! Accessible by users !! SSH port !! Status !! VPN !! Official\n";

unset($info["count"]);
function server_sort_callback($a, $b) {
	return strcasecmp($a["cn"][0], $b["cn"][0]);
}

usort($info, "server_sort_callback");


for ($i = 0; $i < count($info); $i++) {
	$server = $info[$i];

	if (in_array($server["cn"][0], $ignored_servers))
		continue;
	
	if (isset($server["isactive"]) and $server["isactive"][0] == "TRUE")
		print_server($server, true);
	else
		$offline_servers[] = $server;
}

echo "<!-- Server graveyard -->\n";

foreach ($offline_servers as $server)
	print_server($server, false);

echo "|}\n";

function print_server($server, $active) {
	list ($owner, $owner_nick) = get_user($server["owner"][0]);

	if (isset($server["useraccessible"]))
		$userAccessible = ($server["useraccessible"][0] == "TRUE");
	else
		$userAccessible = true;

	if (isset($server["sshport"]))
		unset ($server["sshport"]["count"]);
	else
		$server["sshport"] = array("22");

	$hostname = $server["cn"][0];
	unset ($server["purpose"]["count"]);

	echo "|-\n";
	echo "! [[Server:{$hostname}|]]\n";
	
	echo "| [[User:{$owner}|{$owner_nick}]]".
		" || ".(isset($server["purpose"])? $server["purpose"][0] : "''space waster''").
		"\n";

	echo "| ". ($userAccessible? "{{Yes}}" : "{{No}}").
		" || ".implode(", ", $server["sshport"]).
		" || ".($active? "{{Online}}" : "{{Offline}}").
		" || ".(isset($server["ipv6address"])? "Yes" : "No").
		" || ".(isset($server["internaladdress"])? "Yes" : "No").
		"\n";
}

$users = array();

function get_user($dn) {
	global $users;
	if (isset($users[$dn])) return $users[$dn];
	
	global $ldap;
	$res = ldap_search($ldap,
		$dn,
		"(objectClass=person)",
		array("uid", "clueircnick")
	);
	$entries = ldap_get_entries($ldap, $res);
	$users[$dn] = array(
		$entries[0]["uid"][0],
		$entries[0]["clueircnick"][0],
	);

	return $users[$dn];
}

