#!/usr/bin/php
<?php
	include_once 'globals.php';
	$LOG = DEFAULTPATH . 'daemon.log';
	
	exec("sudo chmod 777 /dev/i2c* >> $LOG 2>&1");
	exec("/var/www/html/daemon_main.php >> $LOG 2>&1");
 
