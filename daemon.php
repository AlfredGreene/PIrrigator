#!/usr/bin/php
<?php
	include_once 'globals.php';
	$LOG = DEFAULTPATH . 'daemon.log';
	
	exec("/var/www/html/daemon_main.php >> $LOG 2>&1");
 