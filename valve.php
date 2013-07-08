<?php
include_once 'ini_write.php';
include_once 'mutex.php';
include_once 'ValveHW.php';

function GetValvesList($path = '/var/www-data/valves/') {
	$files = glob($path . '*.ini');
	foreach($files as $file) {	
		$valves[] = new Valve($file);
	}
	return $valves;
}

class Valve
{
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
			echo "<div class={$ClassType}_opened>$OpType open, $left time left<br>Started at $s[Start]</div>"; 
		} else {
			if ($s["Manual"] || $s["Auto"]) {
				$duration = $this->OpenDuration()->format(self::DURATIONFORMAT);
				if ($this->ShouldOpen()) {
					$TimeToOpen = 'ASAP';
				} else {
					$TimeToOpen = 'in ' . $this->TimeToOpen()->format(self::DURATIONLONGFORMAT);
				}
				$WillOpen = "<br>$OpType open $TimeToOpen for $duration";
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
	
	function GenerateParamsForm() {
		?>
		<form action="" method="post" name="ValveConfig">
		<table class=center style="margin-top:10px; margin-bottom:0px"><tr>
			<td class=valve_image style=width:72px><img src="<?=$this->params['General']['Image']?>"/></td>
			<td style="vertical-align:middle; padding:0 10px 0 10px"><h1><?=$this->params['General']['Name']?></h1></td>
			<td style="vertical-align:middle; padding:0 10px 0 10px; border-left:2px solid #e3f2f9;">
				<?php if ($this->IsOpen()) {
					$Op = 'Close'; $Class = 'closed';
				} else {
					$Op = 'Open'; $Class = 'opened';
				}
				?>
				<input type="submit" name="<?=$Op?>" value="<?=$Op?>" class=manual_<?=$Class?> style="height:64px;font-size:20px; padding:0 10px 0 10px;">
		</tr></table>
			</td>
		<table class=center style="table-layout:fixed; padding:10px">
		<colgroup><col style="width:20px"><col style="width:200px"></colgroup>
		<tr><td colspan=3><div style="margin:5px 0 5px 0px; border-top: 1px solid #eee;"></div></td></tr>
		<tr><td colspan=2><div style="padding:0 0 0 10px">Use automatic scheduling?</div></td>
			<td><select name="Auto">
				<option value="1" <?php if ($this->params['Status']['Auto']) echo 'selected' ?> >Yes</option>
				<option value="0" <?php if (!$this->params['Status']['Auto']) echo 'selected' ?> >No</option>
			</select></td></tr>
		<tr><td></td>
			<td>Open at</td>
			<td><input type="time" name="AutoAt" value="<?=$this->FormatAutoTime()?>"></td></tr>
		<tr><td></td>
			<td>Days in between</td>
			<td><select name="AutoInterval">
				<?php for ($i=1;$i<=50;$i++) {
					echo "<option value=\"$i\"";
					if ($this->params['Auto']['Interval'] == "P{$i}D") { echo " selected"; }
					echo ">$i</option>";
				} ?>
			</select></td></tr>
		<tr><td></td>
			<td>Open for</td>
			<td><input type="time" name="AutoDuration" value="<?=$this->FormatAutoDuration()?>"></td></tr>
		<tr><td colspan=3><div style="margin:5px 0 5px 0px; border-top: 1px solid #eee;"></div></td></tr>
		<tr><td colspan=2><div style="padding:0 0 0 10px">Use manual scheduling?</div></td>
			<td><select name="Manual">
				<option value="1" <?php if ($this->params['Status']['Manual']) echo 'selected' ?> >Yes</option>
				<option value="0" <?php if (!$this->params['Status']['Manual']) echo 'selected' ?> >No</option>
			</select></td></tr>
		<tr><td></td>
			<td>Open at</td>
			<td><input type="date" name="ManualAtDate" value="<?=$this->FormatManualDate()?>">
				<input type="time" name="ManualAtTime" value="<?=$this->FormatManualTime()?>"></td></tr>
		<tr><td></td>
			<td>Open for</td>
			<td><input type="time" name="ManualDuration" value="<?=$this->FormatManualDuration()?>"></td></tr>
		<tr><td colspan=3><div style="margin:5px 0 5px 0px; border-top: 1px solid #eee;"></div></td></tr>
		<tr><td colspan=3><div class=center style="width:40%;margin-top:10px">
			<input type="submit" name="Save" value="Save" class="status" style="width:100%;height:30px;font-size:16px">
			</div></td></tr>
		</table>
		</form>
		<?php
	}

	function UpdateParamsFromForm() {
		$this->params["Status"]["Auto"] = $_POST["Auto"];
		$this->params["Status"]["Manual"] = $_POST["Manual"];
		list($h, $m) = sscanf($_POST["AutoDuration"], "%d:%d");
		$this->params["Auto"]["Duration"] = "PT{$h}H{$m}M";
		list($h, $m) = sscanf($_POST["AutoAt"], "%d:%d");
		$this->params["Auto"]["At"] = "{$h}:{$m}";
		$this->params["Auto"]["Interval"] = "P{$_POST["AutoInterval"]}D";
		list($h, $m) = sscanf($_POST["ManualDuration"], "%d:%d");
		$this->params["Manual"]["Duration"] = "PT{$h}H{$m}M";
		list($h, $m) = sscanf($_POST["ManualAtTime"], "%d:%d");
		$this->params["Manual"]["At"] = $_POST["ManualAtDate"] .  " {$h}:{$m}:00";
		$this->WriteINI();
	}
	
	function ManualOpenNow() {
		$this->params['Status']['Manual'] = 1;
		$this->params['Manual']['At'] = (new DateTime())->format(self::DATEFORMAT);
		$this->params['Manual']['Duration'] = 'PT1H0M';
		$this->WriteINI();
	}
}
