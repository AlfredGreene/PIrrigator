<?php
class Mutex {
	const TIMEOUT = 10; // Time-out in seconds
    var $lockName = '';
    var $fileHandle = null;

    function __construct($lockName, $lock=1) {
        $this->lockName = $lockName . '.mutex';
		for ($i = 0; $i <= self::TIMEOUT; $i++) {
			if ($this->getLock()) return true;
			sleep(1);  // wait for the mutex to be free
		}
    }

	function __destruct() {
		$this->releaseLock();
//		unlink($this->lockName);
	}

    function getFileHandle() {
        if ($this->fileHandle == null) {
            $this->fileHandle = fopen($this->lockName, 'c');
        }
        return $this->fileHandle;
    }

    function getLock() {
		return flock($this->getFileHandle(), LOCK_EX | LOCK_NB);
    }

    function releaseLock() {
		if ($this->fileHandle != null) {
			$success = flock($this->fileHandle, LOCK_UN | LOCK_NB);
			fclose($this->getFileHandle());
			$this->fileHandle = null;
		}
        return $success;
    }
}
