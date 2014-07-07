<?php

include_once 'globals.php';

class I2C
{	
	private $addr; 	 		// and address of the unit being communicated with ( set when instantiated )
	private $hwsim = null; 	// Simulate a device if no HW exists.
	const TRIES = 10;		// Number of tries before giving up
	
	function __construct($addr, $bus = 1) {	 // the i2c bus ( 0 on PI Ver. A, 1 on PI Ver B )	
		$this->addr = $bus . ' ' . $addr;
		if (!$this->check_hw()) {
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
		return is_null($this->hwsim);
	}
		
	private function check_hw() {
		for ($i = 0; $i <= $this::TRIES; $i++) {
			$ans = trim(shell_exec('/usr/sbin/i2cget -y ' . $this->addr . ' 2>&1'));
			if (strcmp($ans, 'Error: Read failed')) {
				return TRUE;
			}
		}
		return FALSE;
	}
	
	public function read_register($register = '') {
		if (is_null($this->hwsim)) {
			for ($i = 0; $i <= $this::TRIES; $i++) {
				$ans = trim(shell_exec('/usr/sbin/i2cget -y ' . $this->addr . ' ' . $register . ' 2>&1'));
				if (strcmp($ans, 'Error: Read failed') != 0) {
					if ($i > 0) {
						error_log(NOW() . 'Warning reading from ' . $this->addr . ' ' . $register . ' took ' . $i . ' tries' . PHP_EOL);
					}
					return intval($ans, 16);
				}
			}
			error_log(NOW() . 'Error reading from ' . $this->addr . ' ' . $register . PHP_EOL);
			return 0;
		} else {
			$f = fopen($this->hwsim, 'r');
			$value = intval(fgets($f));
			fclose($f);
			return $value;
		}			
	}
			
	public function write_register($value, $register = '') {
		if (is_null($this->hwsim)) {
			for ($i = 0; $i <= $this::TRIES; $i++) {
				shell_exec('/usr/sbin/i2cset -y ' . $this->addr . ' ' . $register . ' ' . $value . ' 2>&1' );
				if ($this->read_register($register) == $value) {
					if ($i > 0) {
						error_log(NOW() . 'Warning writing value ' . $value . ' to ' . $this->addr . ' ' . $register . ' took ' . $i . ' tries' . PHP_EOL);
					}
					return;
				}
			}
			error_log(NOW() . 'Error writing value ' . $value . ' to ' . $this->addr . ' ' . $register . ' read: ' . $this->read_register($register) . PHP_EOL);
		} else {
			$f = fopen($this->hwsim, 'w');
			fwrite($f, $value);
			fclose($f);
		}			
	}
}
