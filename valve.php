<?php
include_once 'ini_write.php';
include_once 'mutex.php';
include_once 'ValveHW.php';

class Valve
{
	const DATEDATEFORMAT = 'Y-m-d';
	const DATETIMEFORMAT = 'H:i:s';
	const DATETIMEFORMAT_NOSECONDS = 'H:i';
	const DATEFORMAT = 'Y-m-d H:i:s';

	const DURATIONFORMAT = '%r%H:%I';
	const DURATIONLONGFORMAT = '%r%a days %H:%I:%S';
	const DURATIONFORMAT_WITHSECS = '%r%H:%I:%S';
	const DURATIONLONGFORMAT_PARSE = '%d days %d:%d:%d';
	
	public $params = [
		"General" => [
			"Name" => "Name",
			"Image" => "http://lorempixel.com/64/64",
			"HWID" => 0,
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
			"Start" => "",
			],
		"History" => [
			"Dates" => [],
			"Durations" => [],
			],
		];
		
	public $filename;
	
	private $HW;

	function __construct($filename) {
		$this->HW = new ValveHW;
		$this->filename = $filename;
		$this->ReadINI();
	}
	
	function ReadINI() {
		$mutex = new Mutex($this->filename);
		$params = parse_ini_file($this->filename, true);
		// Overrides defaults with values from the ini file.
		$this->params = array_replace_recursive($this->params, $params);
	}

	function WriteINI() {
		ini_write($this->params, $this->filename, true);		
	}
	
	function IsOpen() {
		return $this->HW->IsOpen($this->params["General"]["HWID"]);
	}
	
	function DoOpen() {
		if (!$this->IsOpen() && $this->CanOpen()) {
			$this->params['Status']['Start'] = (new DateTime())->format(self::DATEFORMAT);
			$this->WriteINI();
			$this->HW->Open($this->params["General"]["HWID"]);
						
			if ($this->params["Status"]["Manual"]) {
				$title = $this->params["General"]["Name"] . " Manually for " . $this->FormatManualDuration();
			} elseif ($this->params["Status"]["Auto"]) {
				$title = $this->params["General"]["Name"] . " Automatically for " . $this->FormatAutoDuration();
			} else {
				$title = $this->params["General"]["Name"] . " For unknown reason";
			}
			exec("echo ' ' | mail -s 'PI Open $title' micronen@gmail.com");
		}
	}

	function DoClose() {
		if ($this->IsOpen()) {
			$start = DateTime::createFromFormat(self::DATEFORMAT, $this->params["Status"]["Start"]);
			$duration = $start->diff(new DateTime());
			$this->params['History']['Dates'][] = $this->params['Status']['Start'];
			$this->params['History']['Durations'][] = $duration->format(self::DURATIONLONGFORMAT);			
			$this->params['Status']['Manual'] = false;
			$this->WriteINI();
			$this->HW->Close($this->params["General"]["HWID"]);

			if ($duration->days == 0) {
				$duration = $duration->format(self::DURATIONFORMAT_WITHSECS);
			} else {
				$duration = $duration->format(self::DURATIONLONGFORMAT);
			}			
			
			exec("echo ' ' | mail -s 'PI Close " . $this->params["General"]["Name"] . " after $duration' micronen@gmail.com");
		}
	}

	function ShouldOpen() {
		$TimeToOpen = $this->TimeToOpen();
		if (is_null($TimeToOpen)) {
			return false;
		} else {
			return !$this->IsOpen() && $this->TimeToOpen()->invert;
		}
	}
	
	function CanOpen() {
		return $this->HW->CanOpen($this->params["General"]["HWID"]);
	}

	function ShouldClose() {
		return $this->IsOpen() && $this->TimeToClose()->invert;
	}
	
	function FormatAutoTime() {
		$at = DateTime::createFromFormat(self::DATETIMEFORMAT, $this->params["Auto"]["At"]);
		if (DateTime::getLastErrors()["error_count"] > 0) {
			$at = DateTime::createFromFormat(self::DATETIMEFORMAT_NOSECONDS, $this->params["Auto"]["At"]);
		}
		return $at->format(self::DATETIMEFORMAT);
	}

	function FormatAutoInterval() {
		return (new DateInterval($this->params["Auto"]['Interval']))->format("%d days");
	}

	function FormatAutoDuration() {
		return (new DateInterval($this->params["Auto"]["Duration"]))->format(self::DURATIONFORMAT);
	}
	
	function FormatManualDuration() {
		return (new DateInterval($this->params["Manual"]["Duration"]))->format(self::DURATIONFORMAT);
	}

	function FormatManualAt() {
		return DateTime::createFromFormat(self::DATEFORMAT, $this->params["Manual"]["At"])->format(self::DATEFORMAT);
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
			$at = DateTime::createFromFormat(self::DATETIMEFORMAT, $this->params["Auto"]["At"]);
			if (DateTime::getLastErrors()["error_count"] > 0) {
				$at = DateTime::createFromFormat(self::DATETIMEFORMAT_NOSECONDS, $this->params["Auto"]["At"]);
			}
			$last = end($this->params["History"]["Dates"]);
			if (empty($last)) {
				if ($at < new DateTime()) $at->add(new DateInterval("P1D"));
			} else {
				$wait = DateTime::createFromFormat(self::DATEFORMAT, $last)->add(new DateInterval($this->params["Auto"]['Interval']));
				for ($i = 0; $i < 100; $i++) {
					if ($at < $wait) { 
						$at->add(new DateInterval("P1D"));
					} else {
						$at->sub(new DateInterval("P1D"));
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
   
	function ManualOpenNow() {
		$this->params['Status']['Manual'] = 1;
		$this->params['Manual']['At'] = (new DateTime())->format(self::DATEFORMAT);
//		$this->params['Manual']['Duration'] = 'PT1H0M';
		$this->DoOpen();
		$this->WriteINI();
	}
}

function GetValvesList($class_name = 'Valve', $path = DEFAULTPATH) {
	$files = glob($path . '*.vlv');
	if (empty($files)) {
		return null;
	} else { 
		foreach($files as $file) {	
			$valves[] = new $class_name($file);
		}
		return $valves;
	}
}
