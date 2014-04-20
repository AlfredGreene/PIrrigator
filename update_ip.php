<?php
include_once 'ini_write.php';
include_once 'mutex.php';
include_once 'valve.php';

function GetExternalIP() {
	$sGetIp = "http://ip4.me/";
	$sAns = implode(file($sGetIp));	
	if (preg_match("#(\d+\.\d+\.\d+\.\d+)#i", $sAns, $aMatch)) 
	{ 
		$nIP = $aMatch[1]; 
		//echo "$nIP\n";	

		if (ip2long($nIP) >= 0) {
			return $nIP;
		} else {
			echo (new DateTime())->format(Valve::DATEFORMAT) . ": Invalid IP address: $nIP" . PHP_EOL;
		}	
	} else {
		echo (new DateTime())->format(Valve::DATEFORMAT) . ": IP address not found in: $sAns" . PHP_EOL;
	}	
	return "";
}

function CheckIfIpChanged($sIniFilename) {
	$mutex = new Mutex($sIniFilename);
	$params = parse_ini_file($sIniFilename, true);
	unset($mutex);
	
	//print_r($params);
	$IP = GetExternalIP();
	if ($IP != $params["IP"]["LastIP"]) {
		echo (new DateTime())->format(Valve::DATEFORMAT) . ": IP Changed, External IP is: $IP, Last IP is: " . $params['IP']['LastIP'] . PHP_EOL;
		$params["IP"]["LastIP"] = $IP;
		ini_write($params, $sIniFilename, true);		
		return true;
	}
	return false;
}

function update_ip() {
	$sDnsUpdate = "http://freedns.afraid.org/dynamic/update.php?WkNicER4UGprSUxEVGl3eW1VY0Q6OTY4MzI5Mw==";

	if (CheckIfIpChanged(Valve::DEFAULTPATH . 'LastIP.ini')) {
		$ans = file($sDnsUpdate);
		$ans = rtrim(implode($ans, ""));
		echo (new DateTime())->format(Valve::DATEFORMAT) . ': Dynamic DNS answer: ' . $ans . PHP_EOL;
	}
}
