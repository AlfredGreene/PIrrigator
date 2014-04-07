#!/usr/bin/php
<?php
	include_once 'valve.php';
	include_once 'ValveHW.php';
	$LOG = Valve::DEFAULTPATH . 'daemon.log';
	$sIniFilename = Valve::DEFAULTPATH . 'daemon.ini';
	
	fclose(STDIN);
	fclose(STDOUT);
	fclose(STDERR);
	$STDIN = fopen('/dev/null', 'r');
	$STDOUT = fopen($LOG, 'a');
	$STDERR = fopen($LOG, 'a');
	
	echo (new DateTime())->format(Valve::DATEFORMAT) . ': Starting daemon' . PHP_EOL;

	include_once 'update_ip.php';
	
	$HW = new ValveHW;
	$HW->CloseAll();	// Close all values

	while (true) { 
		set_time_limit(0); // Reset PHP time-out
		
		$pid=pcntl_fork(); 
		if ($pid == -1) {
			echo (new DateTime())->format(Valve::DATEFORMAT) . ': Error could not fork, exiting.' . PHP_EOL;
			break;
		} elseif ($pid) { 
			// we are the parent
			sleep(10);  // give the child up to this time to finish
			if (posix_kill($pid, 0)) {
				pcntl_wait($status, WNOHANG); // Protect against Zombie children
				if (pcntl_wifexited($status)) {
					// Child finished normally, continue
					continue;
				}
				
				echo (new DateTime())->format(Valve::DATEFORMAT) . ': Child is still running, killing it.' . PHP_EOL;			
				posix_kill($pid, SIGKILL); 
				
				if (posix_kill($pid, 0)) {
					echo (new DateTime())->format(Valve::DATEFORMAT) . ': Child is not dead, exiting.' . PHP_EOL;			
					break;
				} else {
					echo (new DateTime())->format(Valve::DATEFORMAT) . ': Child is dead.' . PHP_EOL;			
				}
			}
		} else {
			// we are the child, do daemon work
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
					} else { 
	//					echo (new DateTime())->format(Valve::DATEFORMAT) . ': ' . $Valve->params['General']['Name'] . ' Is Closed' . PHP_EOL;
						if ($Valve->ShouldOpen() && $Valve->CanOpen()) {
							$Valve->DoOpen();
							echo (new DateTime())->format(Valve::DATEFORMAT) . ': Opening ' . $Valve->params['General']['Name'] . PHP_EOL;
						}
					}
				}
			}
			// Update daemon status in ini file
			$params["Daemon"]["UpdateIP"] = (new DateTime())->format(Valve::DATEFORMAT);
			ini_write($params, $sIniFilename, true);		

			update_ip();
			
			// Update daemon status in ini file
			$params["Daemon"]["Finish"] = (new DateTime())->format(Valve::DATEFORMAT);
			ini_write($params, $sIniFilename, true);		
			
			// The child finished it's job, kill it.
			die;
		}
	}
	echo (new DateTime())->format(Valve::DATEFORMAT) . ': Stopping daemon' . PHP_EOL;
