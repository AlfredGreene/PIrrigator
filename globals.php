<?php
	define('LOGDATEFORMAT', 'Y-m-d H:i:s');
	define('DEFAULTPATH', '/var/www-data/valves/');
	define('DAEMONINI', DEFAULTPATH . 'daemon.ini');
	
	function NOW() {
		return (new DateTime())->format(LOGDATEFORMAT) . ': ';
	}
