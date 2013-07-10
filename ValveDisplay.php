<?php
include_once 'valve.php';

class ValveDisplay extends Valve
{
	function __construct($filename) {
		parent::__construct($filename);
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
			</td>
		</tr></table>
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
	
	function GenerateHistoryTable() {
		?>
			<table>
			<tr><th>#</th><th>Date</th><th>Duration</th></tr>
		<?php
		foreach ($this->params['History']['Dates'] as $Key=>$Date) {
			$Duration = $this->params['History']['Durations'][$Key];
			echo "<tr><td>$Key</td><td>$Date</td><td>$Duration</td></tr>";
		}
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
}

function GetValvesDisplayList($path = ValveDisplay::DEFAULTPATH) {
	$files = glob($path . '*.ini');
	foreach($files as $file) {	
		$valves[] = new ValveDisplay($file);
	}
	return $valves;
}
