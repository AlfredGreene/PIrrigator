<?php
include_once 'ini_write.php';
include_once 'mutex.php';

function GetExternalIP() {
	$sGetIp = "http://ip4.me/";
	$sAns = rtrim(implode(file($sGetIp), ""));
	if (preg_match("#(\d+\.\d+\.\d+\.\d+)#i", $sAns, $aMatch)) 
	{ 
		$nIP = $aMatch[1]; 
		//echo "$nIP\n";	

		if (ip2long($nIP) >= 0) {
			return $nIP;
		} else {
			echo (new DateTime())->format(Valve::DATEFORMAT) . "Invalid IP address: $nIP" . PHP_EOL;
		}	
	} else {
		echo (new DateTime())->format(Valve::DATEFORMAT) . "IP address not found in: $sAns" . PHP_EOL;
	}	
}

function CheckIfIpChanged($sIniFilename) {
	$mutex = new Mutex($sIniFilename);
	$params = parse_ini_file($sIniFilename, true);
	//print_r($params);
	$IP = GetExternalIP();
	if (!empty($IP)) {		
		if ($IP != $params["IP"]["LastIP"]) {
			$now = (new DateTime())->format(Valve::DATEFORMAT);
			echo $now . ": IP Changed, External IP is: $IP, Last IP is: " . $params['IP']['LastIP'] . "\n";
			$params["IP"]["LastIP"] = $IP;
			ini_write($params, $sIniFilename, true);		
			return true;
		}
	}
	return false;
}