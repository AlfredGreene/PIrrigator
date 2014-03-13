#!/usr/bin/php
<?php
	include_once 'valve.php';
	include_once 'ValveHW.php';
	$LOG = Valve::DEFAULTPATH . 'daemon.log';
	
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
		$Valves = GetValvesList();
		if (!empty($Valves)) {
			foreach ($Valves as $Valve) {
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
		update_ip();
		sleep(10);
	}
	echo (new DateTime())->format(Valve::DATEFORMAT) . ': Stopping daemon' . PHP_EOL;
