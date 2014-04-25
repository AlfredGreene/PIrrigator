#!/usr/bin/php
<?php
	include_once 'valve.php';
	include_once 'ValveHW.php';
	include_once 'update_ip.php';
	$sIniFilename = Valve::DEFAULTPATH . 'daemon.ini';
		
	echo (new DateTime())->format(Valve::DATEFORMAT) . ': Starting daemon' . PHP_EOL;

	$HW = new ValveHW;
	$HW->CloseAll();	// Close all values

	$iCounter = 0;
	while (true) { 
		$iCounter++; 
		set_time_limit(0); // Reset PHP time-out
		
		$pid=pcntl_fork(); 
		if ($pid == -1) {
			echo (new DateTime())->format(Valve::DATEFORMAT) . ': Error could not fork, exiting.' . PHP_EOL;
			break;
		} elseif ($pid) { 
			// we are the parent
			sleep(10);  // give the child up to this time to finish
			if (WaitForChild()) {
				continue;
			} else {
				echo (new DateTime())->format(Valve::DATEFORMAT) . ': Error could not kill child, exiting.' . PHP_EOL;
				DumpIni($sIniFilename);
			}
		} else {
			// we are the child, do daemon work
			if (!UpdateValves($sIniFilename)) {
				if ($iCounter > 10) {
					UpdateIP($sIniFilename);
					$iCounter = 0;
				}
			}
			// The child finished it's job, kill it.
			die;
		}
	}
	echo (new DateTime())->format(Valve::DATEFORMAT) . ': Closing all valves' . PHP_EOL;
	$HW = new ValveHW;
	$HW->CloseAll();	// Close all values
	
	echo (new DateTime())->format(Valve::DATEFORMAT) . ': Stopping daemon' . PHP_EOL;

	exec("mail -s 'PI daemon is down' micronen@gmail.com < $sIniFilename");
	
/////////////////////////////////////////////////////////////////////////////////////////
	
function WaitForChild() {
	pcntl_wait($status, WNOHANG); // Protect against Zombie children
	if (pcntl_wifexited($status)) {
		// Child finished normally, continue
		return true;
	}
	
	echo (new DateTime())->format(Valve::DATEFORMAT) . ': Child is still running, killing it.' . PHP_EOL;			
	posix_kill($pid, SIGKILL); 

	sleep(10);  // give the child up to this time to finish
	pcntl_wait($status, WNOHANG); // Protect against Zombie children
	if (pcntl_wifexited($status)) {
		// Child finished normally, continue
		return true;
	}
	
	if (posix_kill($pid, 0)) {
		echo (new DateTime())->format(Valve::DATEFORMAT) . ': Child is not dead, exiting.' . PHP_EOL;			
		return false;
	} else {
		echo (new DateTime())->format(Valve::DATEFORMAT) . ': Child is dead.' . PHP_EOL;			
		return true;
	}
}

function UpdateValves($sIniFilename) {
	$bValveIsOpenedOrShouldOpen = false;
	// Update daemon status in ini file
	$params = parse_ini_file($sIniFilename, true);
	
	$params["Daemon"]["Start"] = (new DateTime())->format(Valve::DATEFORMAT);
	ini_write($params, $sIniFilename, true);					

	$Valves = GetValvesList();
	$params["Daemon"]["GetValvesList"] = (new DateTime())->format(Valve::DATEFORMAT);
	if (!empty($Valves)) {
		foreach($Valves as $Valve) {	
			$params["Daemon"][$Valve->filename] = (new DateTime())->format(Valve::DATEFORMAT);
			ini_write($params, $sIniFilename, true);	
			
			if ($Valve->IsOpen()) {
//					echo (new DateTime())->format(Valve::DATEFORMAT) . ': ' . $Valve->params['General']['Name'] . ' Is Opened' . PHP_EOL;
				if ($Valve->ShouldClose()) {
					$Valve->DoClose();
					echo (new DateTime())->format(Valve::DATEFORMAT) . ': Closing ' . $Valve->params['General']['Name'] . PHP_EOL;
				}
				$bValveIsOpenedOrShouldOpen = true;
			} else { 
//					echo (new DateTime())->format(Valve::DATEFORMAT) . ': ' . $Valve->params['General']['Name'] . ' Is Closed' . PHP_EOL;
				if ($Valve->ShouldOpen()) {
					$bValveIsOpenedOrShouldOpen = true;
					if ($Valve->CanOpen()) {
						$Valve->DoOpen();
						echo (new DateTime())->format(Valve::DATEFORMAT) . ': Opening ' . $Valve->params['General']['Name'] . PHP_EOL;
					}
				}
			}
		}
	}
	
	// Update daemon status in ini file
	$params["Daemon"]["Finish"] = (new DateTime())->format(Valve::DATEFORMAT);
	ini_write($params, $sIniFilename, true);		
	
	return $bValveIsOpenedOrShouldOpen;
}

function UpdateIP($sIniFilename) {
	// Update daemon status in ini file
	$params = parse_ini_file($sIniFilename, true);
	
	// Update daemon status in ini file
	$params["Daemon"]["UpdateIP"] = (new DateTime())->format(Valve::DATEFORMAT);
	ini_write($params, $sIniFilename, true);		

	update_ip();
	
	// Update daemon status in ini file
	$params["Daemon"]["FinishUpdateIP"] = (new DateTime())->format(Valve::DATEFORMAT);
	ini_write($params, $sIniFilename, true);		
}	

function DumpIni($sIniFilename) {
	// Dump child status to log
	$pid=pcntl_fork(); 
	if ($pid == -1) {
		echo (new DateTime())->format(Valve::DATEFORMAT) . ': Error could not fork, exiting.' . PHP_EOL;
		break;
	} elseif ($pid) { 
		// we are the parent
		sleep(10);  // give the child up to this time to finish
		echo (new DateTime())->format(Valve::DATEFORMAT) . ': Finished dumping, exiting.' . PHP_EOL;
		break; 
	} else {
		// we are the child, dump status to log
		$params = parse_ini_file($sIniFilename, true);
		echo print_r($params) . PHP_EOL;
		die;
	}
}