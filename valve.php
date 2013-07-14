<?php
include_once 'ini_write.php';
include_once 'mutex.php';
include_once 'ValveHW.php';

class Valve
{
	const DEFAULTPATH = '/var/www-data/valves/';
	const DATEDATEFORMAT = 'Y-m-d';
	const DATETIMEFORMAT = 'H:i';
	const DATEFORMAT = 'Y-m-d H:i:s';

	const DURATIONFORMAT = '%r%H:%I';
	const DURATIONLONGFORMAT = '%r%a days %H:%I';
	const DURATIONDATEFORMAT = 'H:i';
	
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
		$mutex = new Mutex($this->filename);
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
   
	function ManualOpenNow() {
		$this->params['Status']['Manual'] = 1;
		$this->params['Manual']['At'] = (new DateTime())->format(self::DATEFORMAT);
		$this->params['Manual']['Duration'] = 'PT1H0M';
		$this->DoOpen();
		$this->WriteINI();
	}
}

function GetValvesList($path = Valve::DEFAULTPATH) {
	$files = glob($path . '*.ini');
	foreach($files as $file) {	
		$valves[] = new Valve($file);
	}
	return $valves;
}
