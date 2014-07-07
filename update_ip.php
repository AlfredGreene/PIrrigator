<?php
include_once 'globals.php';
include_once 'ini_write.php';
include_once 'mutex.php';

function GetExternalIP() {
	$sGetIp = "http://ip4.me/";
	$sAns = file($sGetIp);
	if ($sAns == false) {
		echo NOW() . $sGetIP . " is not accessible" . PHP_EOL;
		return;
	}
	
	$sAns = rtrim(implode($sAns, ""));
	if (preg_match("#(\d+\.\d+\.\d+\.\d+)#i", $sAns, $aMatch)) 
	{ 
		$nIP = $aMatch[1]; 
	//	echo "|$nIP|" . PHP_EOL;
	//	echo ip2long($nIP) . PHP_EOL;

		if (ip2long($nIP) != 0) {
			return $nIP;
		} else {
			echo NOW() . "Invalid IP address: $nIP" . PHP_EOL;
		}	
	} else {
		echo NOW() . "IP address not found in: $sAns" . PHP_EOL;
	}	
}

function CheckIfIpChanged($sIniFilename) {
	$mutex = new Mutex($sIniFilename);
	$params = parse_ini_file($sIniFilename, true);
	unset($mutex);
	
	//print_r($params);
	$IP = GetExternalIP();
	if (!empty($IP)) {		
		if ($IP != $params["IP"]["LastIP"]) {
			echo NOW() . "IP Changed, External IP is: $IP, Last IP is: " . $params['IP']['LastIP'] . PHP_EOL;
			$params["IP"]["LastIP"] = $IP;
			ini_write($params, $sIniFilename, true);		
			return true;
		}
	}
	return false;
}

function update_ip() {
	$sDnsUpdate = "http://freedns.afraid.org/dynamic/update.php?WkNicER4UGprSUxEVGl3eW1VY0Q6OTY4MzI5Mw==";

	if (CheckIfIpChanged(DEFAULTPATH . 'LastIP.ini')) {
		$ans = file($sDnsUpdate);
		$ans = rtrim(implode($ans, ""));
		echo NOW() . 'Dynamic DNS answer - ' . $ans . PHP_EOL;
	}
}
