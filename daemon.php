#!/usr/bin/php
<?php
	include_once 'valve.php';
	$LOG = Valve::DEFAULTPATH . 'daemon.log';
	
	exec("./daemon_main.php >> $LOG 2>&1");
 