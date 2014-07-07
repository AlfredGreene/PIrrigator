#!/usr/bin/php
<?php
	include_once 'globals.php';
	$LOG = DEFAULTPATH . 'daemon.log';
	
	exec("./daemon_main.php >> $LOG 2>&1");
 