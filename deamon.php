<?php
	include_once 'valve.php';

	$now = (new DateTime())->format(Valve::DATEFORMAT);
	echo $now . ': Starting deamon' . PHP_EOL;

	while (true) { 
		foreach (GetValvesList() as $valve) {
			if ($valve->IsOpen()) {
				if ($valve->ShouldClose()) {
					$valve->DoClose();
					$now = (new DateTime())->format(Valve::DATEFORMAT);
					echo $now . ': Closing ' . $valve->params['General']['Name'] . PHP_EOL;
				}
			} else { 
				if ($valve->ShouldOpen() && $valve->CanOpen()) {
					$valve->DoOpen();
					$now = (new DateTime())->format(Valve::DATEFORMAT);
					echo $now . ': Opening ' . $valve->params['General']['Name'] . PHP_EOL;
				}
			}
		}
		
		sleep(1);
	}
	