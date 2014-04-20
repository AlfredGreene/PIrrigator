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

		echo "<table style='width:99%'><tr>";
		echo "<td rowspan=3 class=valve_image style=width:95><img src=\"$g[Image]\"/></td>";
		echo "<td><span style='font-size:18px; font-weight:bold;'>$g[Name]</span></td></tr>";

		echo "<tr><td><span style='font-size:12px; font-weight:bold;'>";
		if ($s["Auto"]) {
			echo "Opens for {$this->FormatAutoDuration()} at {$this->FormatAutoTime()} every {$this->FormatAutoInterval()}";
			if ($s["Manual"]) echo '<br>';
		}
		if ($s["Manual"]) {
			echo "Manual open for {$this->FormatManualDuration()} at {$this->FormatManualAt()}";
		}
		echo "</span>";
		if (!$s["Auto"] && !$s["Manual"]) {
			echo '<span style="color:red">Scheduled operation is disabled</span>';
		}
		echo "</td></tr>";

		echo "<tr><td>";
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
				$left = $left->format(self::DURATIONFORMAT_WITHSECS);
			}
			echo "<span class={$ClassType}_opened>$OpType open, $left time left<br>Started at $s[Start]</span>";
		} else {
			if ($s["Manual"] || $s["Auto"]) {
				$duration = $this->OpenDuration()->format(self::DURATIONFORMAT_WITHSECS);
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
				echo "<span class={$ClassType}_closed>Valve was never opened$WillOpen</span>";
			} else {
				$LastDate = DateTime::createFromFormat(self::DATEFORMAT, $last);
				$TimeSinceOpen = $LastDate->diff(new DateTime())->format(self::DURATIONLONGFORMAT);
				echo "<span class={$ClassType}_closed>Valve was last opened at $last<br>$TimeSinceOpen time ago$WillOpen</span>";
			}
		}
		echo '</td></tr></table>';
	}

	function GenerateParamsForm() {
		foreach ($_REQUEST as $Key=>$Value) {
			echo "<input type=\"hidden\" name=\"$Key\" value=\"$Value\">";
		}
		?>
		<table id="form-header"><tr>
			<td class=valve_image style="border-right:2px solid #e3f2f9"><img style="height:64px" src="<?=$this->params['General']['Image']?>"/></td>
			<td><h1><?=$this->params['General']['Name']?></h1></td>
			<td style="border-left:2px solid #e3f2f9">
				<?php if ($this->IsOpen()) { $Op = 'Close'; $Class = 'closed'; } else { $Op = 'Open'; $Class = 'opened'; } ?>
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
				<input type="submit" name="Save" value="Save" class="form_submit" style="width:100%;height:30px;font-size:16px">
				</div></td></tr>
		</table>
		<?php
	}

	public static function AddFormDateLine($Text, $NameDate, $ValueDate) {
		self::AddFormDateTimeLine($Text, $NameDate, $ValueDate, null, null);
	}

	public static function AddFormTimeLine($Text, $NameTime, $ValueTime) {
		self::AddFormDateTimeLine($Text, null, null, $NameTime, $ValueTime);
	}

	public static function AddFormDateTimeLine($Text, $NameDate, $ValueDate, $NameTime, $ValueTime) {
		echo "<tr><td></td><td>$Text</td><td>";
		if (!empty($NameDate)) echo "<input type=\"date\" name=\"$NameDate\" value=\"$ValueDate\">";
		if (!empty($NameTime)) echo "<input type=\"time\" name=\"$NameTime\" value=\"$ValueTime\">";
		echo "</td></tr>";
	}

	public static function AddSelectLine($Text, $Name, $Array, $Value) {
		echo "<tr><td></td><td>$Text</td><td><select name=\"$Name\">";
		self::SelectArray($Array, $Value);
		echo "</select></td></tr>";
	}

	public static function AddSelectHeaderLine($Text, $Name, $Array, $Value) {
		self::AddSeperatorLine();
		echo '<tr><td colspan=2><div style="padding:0 0 0 10px">' . $Text . '</div></td><td><select name="' . $Name . '">';
		self::SelectArray($Array, $Value);
		echo '</select></td></tr>';
	}

	public static function AddSeperatorLine() {
		echo '<tr><td colspan=3><div style="margin:5px 0 5px 0px; border-top: 1px solid #eee;"></div></td></tr>';
	}

	public static function SelectArray($Array, $Selected) {
		foreach($Array as $Key=>$Value)
			echo "<option value=\"$Key\"" . (($Key==$Selected) ? ' selected' : '') . ">$Value</option>";
	}

	function GenerateHistoryTable() {	
		echo '<div class=history><table id=history>';
		echo '<tr><th>#</th><th>Date</th><th>Duration</th><th>Time Difference</th></tr>';
		$alt = false;
		$prev = false;  //new DateTime();
		foreach ($this->params['History']['Dates'] as $Key=>$Date) {
			$Duration = $this->params['History']['Durations'][$Key];
			
			// Remove error entries
			$Dur = new DateInterval("PT1S");
			list($Dur->d, $Dur->h, $Dur->i, $Dur->s) = sscanf($Duration, self::DURATIONLONGFORMAT_PARSE);
			$DurGt1D = (new DateTime())->add($Dur) > (new DateTime())->add(new DateInterval("P1D"));
			$DurLt1S = (new DateTime())->add($Dur) <= (new DateTime())->add(new DateInterval("PT1S"));
			if ($DurGt1D || $DurLt1S)  {
				continue;
			} 
			
			// Remove 'days' text when the duration is less than a day
			if ($Dur->days == 0) {
				$Duration = $Dur->format(self::DURATIONFORMAT_WITHSECS);
			}			
			
			// Calculate time between opens
			$d = DateTime::createFromFormat(self::DATEFORMAT, $Date);
			if ($prev) {
				$diff = $prev->diff($d);
				$diff = $diff->format(self::DURATIONLONGFORMAT);
			} else {
				$diff = "";
			}
			$prev = $d;
						
			// Add the row to the table
			echo "<tr"; if ($alt) echo " class=alt"; $alt = !$alt; echo ">";
			echo "<td>$Key</td>";
			echo "<td>$Date</td>";
			echo "<td>$Duration</td>";
			echo "<td>$diff</td>";
			echo "</tr>";
		}
		echo '</table></div>';
	}

	function UpdateParamsFromForm() {
		$this->params["Status"]["Auto"] = $_REQUEST["Auto"];
		$this->params["Status"]["Manual"] = $_REQUEST["Manual"];
		list($h, $m) = sscanf($_REQUEST["AutoDuration"], "%d:%d");
		$this->params["Auto"]["Duration"] = "PT{$h}H{$m}M";
		list($h, $m) = sscanf($_REQUEST["AutoAt"], "%d:%d");
		$this->params["Auto"]["At"] = "{$h}:{$m}";
		$this->params["Auto"]["Interval"] = $_REQUEST["AutoInterval"];
		list($h, $m) = sscanf($_REQUEST["ManualDuration"], "%d:%d");
		$this->params["Manual"]["Duration"] = "PT{$h}H{$m}M";
		list($h, $m) = sscanf($_REQUEST["ManualAtTime"], "%d:%d");
		$this->params["Manual"]["At"] = $_REQUEST["ManualAtDate"] .  sprintf(' %02d:%02d:00', $h, $m);
		$this->WriteINI();
	}
}
