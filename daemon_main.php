#!/usr/bin/php
<?php
	include_once 'globals.php';
	include_once 'valve.php';
	include_once 'ValveHW.php';
	include_once 'update_ip.php';
		
	echo NOW() . 'Starting daemon' . PHP_EOL;

	$HW = new ValveHW;
	$HW->CloseAll();	// Close all values

	$iCounter = 0;
	while (true) { 
		$iCounter++; 
		set_time_limit(0); // Reset PHP time-out
		
		$pid=pcntl_fork(); 
		if ($pid == -1) {
			echo NOW() . 'Error could not fork, exiting.' . PHP_EOL;
			break;
		} elseif ($pid) { 
			// we are the parent
			sleep(10);  // give the child up to this time to finish
			if (WaitForChild($pid)) {
				continue;
			} else {
				echo NOW() . 'Error could not kill child, exiting.' . PHP_EOL;
				DumpIni();
			}
		} else {
			// we are the child, do daemon work
			if (!UpdateValves()) {
				if ($iCounter > 10) {
					UpdateIP();
					$iCounter = 0;
				}
			}
			// The child finished it's job, kill it.
			die;
		}
	}
	echo NOW() . 'Closing all valves' . PHP_EOL;
	$HW = new ValveHW;
	$HW->CloseAll();	// Close all values
	
	echo NOW() . 'Stopping daemon' . PHP_EOL;

	exec("mail -s 'PI daemon is down' micronen@gmail.com < " . DAEMONINI);
	
/////////////////////////////////////////////////////////////////////////////////////////
	
function WaitForChild($pid) {
	pcntl_wait($status, WNOHANG); // Protect against Zombie children
	if (pcntl_wifexited($status)) {
		// Child finished normally, continue
		return true;
	}
	
	echo NOW() . 'Child is still running, killing it.' . PHP_EOL;			
	posix_kill($pid, SIGKILL); 

	sleep(10);  // give the child up to this time to finish
	pcntl_wait($status, WNOHANG); // Protect against Zombie children
	if (pcntl_wifexited($status)) {
		// Child finished normally, continue
		return true;
	}
	
	if (posix_kill($pid, 0)) {
		echo NOW() . 'Child is not dead, exiting.' . PHP_EOL;			
		return false;
	} else {
		echo NOW() . 'Child is dead.' . PHP_EOL;			
		return true;
	}
}

function UpdateValves() {
	$bValveIsOpenedOrShouldOpen = false;
	// Update daemon status in ini file
	$params = parse_ini_file(DAEMONINI, true);
	
	$params["Daemon"]["Start"] = (new DateTime())->format(LOGDATEFORMAT);
	ini_write($params, DAEMONINI, true);					

	$Valves = GetValvesList();
	$params["Daemon"]["GetValvesList"] = (new DateTime())->format(LOGDATEFORMAT);
	if (!empty($Valves)) {
		foreach($Valves as $Valve) {	
			$params["Daemon"][$Valve->filename] = (new DateTime())->format(LOGDATEFORMAT);
			ini_write($params, DAEMONINI, true);	
			
			if ($Valve->IsOpen()) {
//					echo NOW() . '' . $Valve->params['General']['Name'] . ' Is Opened' . PHP_EOL;
				if ($Valve->ShouldClose()) {
					$Valve->DoClose();
					echo NOW() . 'Closing ' . $Valve->params['General']['Name'] . PHP_EOL;
				}
				$bValveIsOpenedOrShouldOpen = true;
			} else { 
//					echo NOW() . '' . $Valve->params['General']['Name'] . ' Is Closed' . PHP_EOL;
				if ($Valve->ShouldOpen()) {
					$bValveIsOpenedOrShouldOpen = true;
					if ($Valve->CanOpen()) {
						$Valve->DoOpen();
						echo NOW() . 'Opening ' . $Valve->params['General']['Name'] . PHP_EOL;
					}
				}
			}
		}
	}
	
	// Update daemon status in ini file
	$params["Daemon"]["Finish"] = (new DateTime())->format(LOGDATEFORMAT);
	ini_write($params, DAEMONINI, true);		
	
	return $bValveIsOpenedOrShouldOpen;
}

function UpdateIP() {
	// Update daemon status in ini file
	$params = parse_ini_file(DAEMONINI, true);
	
	// Update daemon status in ini file
	$params["Daemon"]["UpdateIP"] = (new DateTime())->format(LOGDATEFORMAT);
	ini_write($params, DAEMONINI, true);		

	update_ip();
	
	// Update daemon status in ini file
	$params["Daemon"]["FinishUpdateIP"] = (new DateTime())->format(LOGDATEFORMAT);
	ini_write($params, DAEMONINI, true);		
}	

function DumpIni() {
	// Dump child status to log
	$pid=pcntl_fork(); 
	if ($pid == -1) {
		echo NOW() . 'Error could not fork, exiting.' . PHP_EOL;
		break;
	} elseif ($pid) { 
		// we are the parent
		sleep(10);  // give the child up to this time to finish
		echo NOW() . 'Finished dumping, exiting.' . PHP_EOL;
		break; 
	} else {
		// we are the child, dump status to log
		$params = parse_ini_file(DAEMONINI, true);
		echo print_r($params) . PHP_EOL;
		die;
	}
}