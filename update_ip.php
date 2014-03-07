<?php
include_once 'GetExternalIP.php';
include_once 'valve.php';

function update_ip() {
	$sDnsUpdate = "http://freedns.afraid.org/dynamic/update.php?WkNicER4UGprSUxEVGl3eW1VY0Q6OTY4MzI5Mw==";

	if (CheckIfIpChanged(Valve::DEFAULTPATH . 'LastIP')) {
		$ans = file($sDnsUpdate);
		$ans = rtrim(implode($ans, ""));
		echo "$ans\n";
	}
}
