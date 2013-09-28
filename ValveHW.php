<?php

include_once 'i2c.php';
include_once 'mutex.php';

class ValveHW
{	
	const mutex = '/var/www-data/valves/i2c';
	static private $i2c = null;
	
	function __construct() {
		if (is_null(self::$i2c)) {
			self::$i2c = new I2C(0x20, 1);
		}
	}

	public function hw_exists() {
		$mutex = new Mutex(self::mutex);
		return self::$i2c->hw_exists();
	}
	
	private function read() {
		$mutex = new Mutex(self::mutex);
		return self::$i2c->read_register();
	}
			
	private function write($value) {
		$mutex = new Mutex(self::mutex);
		return self::$i2c->write_register($value);
	}

	private function read_bit($bit) {
		$mutex = new Mutex(self::mutex);
		$reg = self::$i2c->read_register();
		return ($reg & (1 << $bit)) != 0;
	}
			
	private function write_bit($bit, $value) {
		$mutex = new Mutex(self::mutex);
		$reg = self::$i2c->read_register();
		$reg = ($reg & ~(1 << $bit)) | (($value == true) << $bit);
		return self::$i2c->write_register($reg);
	}
	
	public function Close($valve) {
		$this->write_bit($valve, true);
	}

	public function Open($valve) {
		$this->write_bit($valve, false);
	}
	
	public function IsOpen($valve) {
		return $this->read_bit($valve) == 0;
	}

	public function CanOpen($valve) {
		return $this->read() == 255;
	}

	public function CloseAll() {
		return $this->write(255);
	}
}
