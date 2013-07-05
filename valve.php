<?php
include_once 'ini_write.php';

class Valve
{
	const DIR = '/var/www-data/valves/';

	const DATEDATEFORMAT = 'Y-m-d';
	const DATETIMEFORMAT = 'H:i:s';
	const DATEFORMAT = 'Y-m-d H:i:s';

	const DURATIONFORMAT = '%r%H:%I';
	const DURATIONLONGFORMAT = '%r%a days %H:%I';
	const DURATIONDATEFORMAT = 'H:i';
	
	public $params = [
		"General" => [
			"Name" => "Name",
			"Image" => "http://lorempixel.com/64/64",
			],
		"Auto" => [
			"Duration" => "PT1H0M",
			"At" => "4:30",
			"Interval" => "P3D",
			],
		"Manual" => [
			"Duration" => "PT1H0M",
			"At" => "2013-01-01 12:00:00",
			],
		"Status" => [
			"Manual" => false,
			"Auto" => true,
			"Open" => false,
			"Start" => "",
			],
		"History" => [
			"Dates" => [],
			"Durations" => [],
			],
		];
		
	public $filename;

	function __construct($filename) {
		$this->filename = $filename;
		$this->ReadINI();
	}
	
	function ReadINI() {
		$params = parse_ini_file(self::DIR . $this->filename, true);
		// Overrides defaults with values from the ini file.
		$this->params = array_replace_recursive($this->params, $params);
	}

	function WriteINI() {
		ini_write($this->params, self::DIR . $this->filename, true);		
	}
	
	function IsOpen() {
		return $this->params["Status"]["Open"];
	}
	
	function DoOpen() {
		if (!$this->params['Status']['Open']) {
			echo 'Opening ' . $this->params['General']['Name'] . "\n";
			$this->params['Status']['Open'] = true;
			$this->params['Status']['Start'] = (new DateTime())->format(self::DATEFORMAT);
		}
	}

	function DoClose() {
		if ($this->params['Status']['Open']) {
			echo 'Closeing ' . $this->params['General']['Name'] . "\n";
			$this->params['Status']['Open'] = false;
			$start = DateTime::createFromFormat(self::DATEFORMAT, $this->params["Status"]["Start"]);
			$duration = $start->diff(new DateTime());
			$this->params['History']['Dates'][] = $this->params['Status']['Start'];
			$this->params['History']['Durations'][] = $duration->format(self::DURATIONLONGFORMAT);
			
			$this->params['Status']['Manual'] = false;
		}
	}

	function ShouldOpen() {
//		echo 'TimeToOpen ' . $this->params['General']['Name'] . "\n";
//		print_r($this->TimeToOpen());
		$TimeToOpen = $this->TimeToOpen();
		if (is_null($TimeToOpen)) {
			return false;
		} else {
			return $this->TimeToOpen()->invert;
		}
	}

	function ShouldClose() {
//		echo 'TimeToClose ' . $this->params['General']['Name'] . "\n";
//		print_r($this->TimeToClose());
		return $this->TimeToClose()->invert;
	}
	
	function FormatAutoTime() {
		return DateTime::createFromFormat(self::DURATIONDATEFORMAT, $this->params["Auto"]["At"])->format(self::DATETIMEFORMAT);
	}

	function FormatAutoDuration() {
		return (new DateInterval($this->params["Auto"]["Duration"]))->format(self::DURATIONFORMAT);
	}
	
	function FormatManualDuration() {
		return (new DateInterval($this->params["Manual"]["Duration"]))->format(self::DURATIONFORMAT);
	}

	function FormatManualDate() {
		return DateTime::createFromFormat(self::DATEFORMAT, $this->params["Manual"]["At"])->format(self::DATEDATEFORMAT);
	}

	function FormatManualTime() {
		return DateTime::createFromFormat(self::DATEFORMAT, $this->params["Manual"]["At"])->format(self::DATETIMEFORMAT);
	}
	
	function OpenDuration() {
		if ($this->params["Status"]["Manual"]) {
			$duration = new DateInterval($this->params["Manual"]["Duration"]);
		} else {
			$duration = new DateInterval($this->params["Auto"]["Duration"]);
		}
		return $duration;
	}
	
	function TimeToClose() {
		$duration = $this->OpenDuration();
		$finish = DateTime::createFromFormat(self::DATEFORMAT, $this->params["Status"]["Start"])->add($duration);
		$left = (new DateTime())->diff($finish);
		return $left;
	}
		
	function TimeToOpen() {
		if ($this->params["Status"]["Manual"]) {
			$at = DateTime::createFromFormat(self::DATEFORMAT, $this->params["Manual"]["At"]);
		} elseif ($this->params["Status"]["Auto"]) {
			$at = DateTime::createFromFormat(self::DURATIONDATEFORMAT, $this->params["Auto"]["At"]);
			$last = end($this->params["History"]["Dates"]);
			if (empty($last)) {
				if ($at < new DateTime()) $at->add(new DateInterval("P1D"));
			} else {
				$wait = DateTime::createFromFormat(self::DATEFORMAT, $last)->add(new DateInterval($this->params["Auto"]['Interval']));
				for ($i = 0; $i < 100; $i++) {
					if ($at < $wait) { 
						$at->add(new DateInterval("P1D"));
					} else {
						break;
					}
				}
			}
		} else {
			return NULL;
		}
		$diff = (new DateTime())->diff($at);
		return $diff;
	}
   
	function GenerateMainMenuItem() {
		$g = $this->params["General"];
		$a = $this->params["Auto"];
		$s = $this->params["Status"];
		$m = $this->params["Manual"];

		echo '<table style="width:100%; table-layout:fixed"><tr>';
		echo "<td class=valve_image style=width:72px><img src=\"$g[Image]\"/></td>";
		echo '<td class=valve_text>';
		echo "<h2>$g[Name]</h2>";
		
		if ($s["Auto"]) {
			$interval = (new DateInterval($a['Interval']))->format("%d days");
			$duration = (new DateInterval($a['Duration']))->format("%H:%M");
			echo "<h5>Opens for $duration at $a[At] every $interval</h5>";
		} else {
			echo '<h5 style="color:red">Scheduled opetation is disabled</h5>';		
		}
		
		if ($s["Manual"]) {
			$OpType = 'Manual';
			$ClassType = 'manual';
		} elseif ($s["Auto"]) {
			$OpType = 'Scheduled';
			$ClassType = 'auto';
		} else {
			$OpType = 'Disabled';
			$ClassType = 'disabled';
		}

		if ($this->IsOpen()) {
			$start = DateTime::createFromFormat(self::DATEFORMAT, $s["Start"]);
			$left = $this->TimeToClose();
			if ($left->days != 0) {
				$left = $left->format(self::DURATIONLONGFORMAT); 
			} else {
				$left = $left->format(self::DURATIONFORMAT); 
			}
			echo "<div class={$ClassType}_opened>$OpType open, $left time left<br>started at $s[Start]</div>"; 
		} else {
			if ($s["Manual"] || $s["Auto"]) {
				$duration = $this->OpenDuration()->format(self::DURATIONFORMAT);
				$TimeToOpen = $this->TimeToOpen()->format(self::DURATIONLONGFORMAT);
				$WillOpen = "<br>$OpType open in $TimeToOpen for $duration";
			} else {
				$WillOpen = "";
			}
			$last = end($this->params["History"]["Dates"]);
			if (empty($last)) {
				echo "<div class={$ClassType}_closed>Valve was never opened$WillOpen</div>";
			} else {
				$LastDate = DateTime::createFromFormat(self::DATEFORMAT, $last);
				$TimeSinceOpen = $LastDate->diff(new DateTime())->format(self::DURATIONLONGFORMAT);
				echo "<div class={$ClassType}_closed>Valve was last opened at $last<br>$TimeSinceOpen time ago$WillOpen</div>";
			}
		}
		echo '</td></tr></table>';
	}
}

function GetValvesList() {
	$files = array_diff(scandir(Valve::DIR), array('..', '.'));
	foreach($files as $file) {	
		$valves[] = new Valve($file);
	}
	return $valves;
}

function UpdateValves() {
	foreach(GetValvesList() as $valve){
		if ($valve->IsOpen()) {
			if ($valve->ShouldClose()) $valve->DoClose();
		} else { 
			if ($valve->ShouldOpen()) $valve->DoOpen();
		}
		$state[] = $valve->IsOpen();
		$valve->WriteINI();
	}
	print_r($state);
	// Update valves with state vector.
}