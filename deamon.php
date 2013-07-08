#!/usr/bin/php
<?php
	include_once 'valve.php';
	const LOG = '/var/www-data/valves/deamon.log';

	fclose(STDIN);
	fclose(STDOUT);
	fclose(STDERR);
	$STDIN = fopen('/dev/null', 'r');
	$STDOUT = fopen(LOG, 'a');
	$STDERR = fopen(LOG, 'a');

	$now = (new DateTime())->format(Valve::DATEFORMAT);
	echo $now . ': Starting deamon' . PHP_EOL;

	while (true) { 
		$Valves = GetValvesList();
		if (!empty($Valves)) {
			foreach ($Valves as $Valve) {
				if ($Valve->IsOpen()) {
					if ($Valve->ShouldClose()) {
						$Valve->DoClose();
						$now = (new DateTime())->format(Valve::DATEFORMAT);
						echo $now . ': Closing ' . $Valve->params['General']['Name'] . PHP_EOL;
					}
				} else { 
					if ($Valve->ShouldOpen() && $Valve->CanOpen()) {
						$Valve->DoOpen();
						$now = (new DateTime())->format(Valve::DATEFORMAT);
						echo $now . ': Opening ' . $Valve->params['General']['Name'] . PHP_EOL;
					}
				}
			}
		}
		sleep(1);
	}
	