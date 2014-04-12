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
		// Unable to get mutex in time, force the mutex to free
		releaseLock();
		unlink($this->lockName);
    }

	function __destruct() {
		$this->releaseLock();
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
        $success = flock($this->getFileHandle(), LOCK_UN | LOCK_NB);
        fclose($this->getFileHandle());
		$this->fileHandle = null;
        return $success;
    }
}
