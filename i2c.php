<?php
class I2C
{	
	private $addr; 	// the i2c bus ( 0 on first gen rpi's, 1 on subsequnet rpi's ) 
	                // and address of the unit being communicated with ( set when instantiated )

	private $hwsim = null; // Simulate a device if no HW exists.
	
	function __construct($addr, $bus = 1) {		
		$this->addr = $bus . ' ' . $addr;
		if (!$this->hw_exists()) {
			echo '<h2 style="text-align: center">NO HW FOUND - Simulation mode</h2>' . PHP_EOL;
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
		$ans = trim(shell_exec('/usr/sbin/i2cget -y ' . $this->addr . ' 2>&1'));
		return strcmp($ans, 'Error: Read failed');
	}
	
	public function read_register($register = '') {
		if (is_null($this->hwsim)) {
			return intval(trim(shell_exec('/usr/sbin/i2cget -y ' . $this->addr . ' ' . $register)));
		} else {
			$f = fopen($this->hwsim, 'r');
			$value = intval(fgets($f));
			fclose($f);
			return $value;
		}			
	}
			
	public function write_register($value, $register = '') {
		if (is_null($this->hwsim)) {
			shell_exec('/usr/sbin/i2cset -y ' . ' ' . $register . ' ' . $value );
		} else {
			$f = fopen($this->hwsim, 'w');
			fwrite($f, $value);
			fclose($f);
		}			
	}
}
