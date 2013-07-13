<?php
include_once 'valve.php';

class ValveDisplay extends Valve
{
	public $DurationArray;
	public $YesNoArray = [true=>'Yes', false=>'No'];
	
	function __construct($filename) {
		parent::__construct($filename);
		for ($i=1;$i<=50;$i++) $this->DurationArray["P{$i}D"]=$i;
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
		
		<table id="form-header"><tr>
			<td style="border-right:2px solid #e3f2f9"><img src="<?=$this->params['General']['Image']?>"/></td>
			<td><h1><?=$this->params['General']['Name']?></h1></td>
			<td style="border-left:2px solid #e3f2f9">
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
		<?php
		$this->AddSelectHeaderLine('Use automatic scheduling?', 'Auto', $this->YesNoArray, $this->params['Status']['Auto']);	
		$this->AddFormTimeLine('Open at', 'AutoAt', $this->FormatAutoTime()); 
		$this->AddSelectLine('Days in between', 'AutoInterval', $this->DurationArray, $this->params['Auto']['Interval']);
		$this->AddFormTimeLine('Open for', 'AutoDuration', $this->FormatAutoDuration());

		$this->AddSelectHeaderLine('Use manual scheduling?', 'Manual', $this->YesNoArray, $this->params['Status']['Manual']);	
		$this->AddFormDateTimeLine('Open at', 'ManualAtDate', $this->FormatManualDate(), 'ManualAtTime', $this->FormatManualTime());
		$this->AddFormTimeLine('Open for', 'ManualDuration', $this->FormatManualDuration()); 

		$this->AddSeperatorLine();
		?>
		<tr><td colspan=3><div style="width:40%; margin:0px auto 10px auto">
			<input type="submit" name="Save" value="Save" class="status" style="width:100%;height:30px;font-size:16px">
			</div></td></tr>
		</table>
		</form>
		<?php
	}

	function AddFormDateLine($Text, $NameDate, $ValueDate) {
		$this->AddFormDateTimeLine($Text, $NameDate, $ValueDate, null, null);
	}

	function AddFormTimeLine($Text, $NameTime, $ValueTime) {
		$this->AddFormDateTimeLine($Text, null, null, $NameTime, $ValueTime);
	}
	
	function AddFormDateTimeLine($Text, $NameDate, $ValueDate, $NameTime, $ValueTime) {
		echo "<tr><td></td><td>$Text</td><td>";
		if (!empty($NameDate)) echo "<input type=\"date\" name=\"$NameDate\" value=\"$ValueDate\">";
		if (!empty($NameTime)) echo "<input type=\"time\" name=\"$NameTime\" value=\"$ValueTime\">";
		echo "</td></tr>";
	}
	
	function AddSelectLine($Text, $Name, $Array, $Value) {
		echo "<tr><td></td><td>$Text</td><td><select name=\"$Name\">";
		$this->SelectArray($Array, $Value); 
		echo "</select></td></tr>";
	}

	function AddSelectHeaderLine($Text, $Name, $Array, $Value) {		
		$this->AddSeperatorLine();
		echo '<tr><td colspan=2><div style="padding:0 0 0 10px">' . $Text . '</div></td><td><select name="' . $Name . '">';
		$this->SelectArray($this->YesNoArray, $this->params['Status']['Auto']);
		echo '</select></td></tr>';
	}

	function AddSeperatorLine() {		
		echo '<tr><td colspan=3><div style="margin:5px 0 5px 0px; border-top: 1px solid #eee;"></div></td></tr>';
	}
	
	function SelectArray($Arr, $Val) { 
		foreach($Arr as $Key=>$Value) {
			echo "<option value=\"$Key\"";
			if ($Key==$Val) echo ' selected';
			echo ">$Value</option>";
		}
	}
	
	function GenerateHistoryTable() {
		echo '<div class=history><table id=history>';
		echo '<tr><th>#</th><th>Date</th><th>Duration</th></tr>';
		$alt = false;
		foreach ($this->params['History']['Dates'] as $Key=>$Date) {
			$Duration = $this->params['History']['Durations'][$Key];
			echo "<tr"; if ($alt) " class=alt"; $alt = !$alt;
			echo "><td>$Key</td><td>$Date</td><td>$Duration</td></tr>";
		}
		echo '</table></div>';
	}

	function UpdateParamsFromForm() {
		$this->params["Status"]["Auto"] = $_POST["Auto"];
		$this->params["Status"]["Manual"] = $_POST["Manual"];
		list($h, $m) = sscanf($_POST["AutoDuration"], "%d:%d");
		$this->params["Auto"]["Duration"] = "PT{$h}H{$m}M";
		list($h, $m) = sscanf($_POST["AutoAt"], "%d:%d");
		$this->params["Auto"]["At"] = "{$h}:{$m}";
		$this->params["Auto"]["Interval"] = $_POST["AutoInterval"];
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
