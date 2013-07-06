<?php
class Mutex {
    var $lockName = '';
    var $fileHandle = null;

    function __construct($lockName, $lock=1) {
        $this->lockName = $lockName . '.mutex';
		if ($lock) {
			while (!$this->getLock()) {
				sleep(.5);
			}
		}
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
        return flock($this->getFileHandle(), LOCK_EX);
    }

    function releaseLock() {
        $success = flock($this->getFileHandle(), LOCK_UN);
        fclose($this->getFileHandle());
		$this->fileHandle = null;
        return $success;
    }
}
