<?php
class I2C
{	
	private $addr; 	// the i2c bus ( 0 on first gen rpi's, 1 on subsequnet rpi's ) 
	                // and address of the unit being communicated with ( set when instantiated )

	private $hwsim = null; // Simulate a device if no HW exists.
	
	function __construct($addr, $bus = 1) {		
		$this->addr = $bus . ' ' . $addr;
		if (!$this->hw_exists()) {
			$this->hwsim = '/var/www-data/valves/i2c_hwsim' . $bus . '_' . $addr . '.txt';
			$f = fopen($this->hwsim, 'r');
			if ($f === false) {
				$f = fopen($this->hwsim, 'w');
				fwrite($f, '0');
			}
			fclose($f);
		}
	}

	public function hw_exists() {
		for ($i = 0; $i <= 10; $i++) {
			$ans = trim(shell_exec('/usr/sbin/i2cget -y ' . $this->addr . ' 2>&1'));
			if (strcmp($ans, 'Error: Read failed')) {
				return TRUE;
			}
		}
		return FALSE;
	}
	
	public function read_register($register = '') {
		if (is_null($this->hwsim)) {
//			echo '/usr/sbin/i2cget -y ' . $this->addr . ' ' . $register . PHP_EOL;
			$ans = shell_exec('/usr/sbin/i2cget -y ' . $this->addr . ' ' . $register);
//			echo '|' . trim($ans) . '|' . PHP_EOL;
			return intval(trim($ans), 16);
		} else {
			$f = fopen($this->hwsim, 'r');
			$value = intval(fgets($f));
			fclose($f);
			return $value;
		}			
	}
			
	public function write_register($value, $register = '') {
		if (is_null($this->hwsim)) {
//			echo '/usr/sbin/i2cset -y ' . $this->addr . ' ' . $register . ' ' . $value . PHP_EOL;
			for ($i = 0; $i <= 10; $i++) {
				shell_exec('/usr/sbin/i2cset -y ' . $this->addr . ' ' . $register . ' ' . $value );
				if ($this->read_register($register) == $value) {
					break;
				}
			}
			if ($i >= 10) {
				error_log('Error writing value ' . $value . ' read: ' . $this->read_register($register) . PHP_EOL);
			} elseif ($i > 0) {
				error_log('Warning value ' . $value . ' was written after ' . $i . ' tries.' . PHP_EOL);
			}
		} else {
			$f = fopen($this->hwsim, 'w');
			fwrite($f, $value);
			fclose($f);
		}			
	}
}
