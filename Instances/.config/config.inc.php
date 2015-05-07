<?php
	// where are the opensim databases kept for regions and estate?
	// the username and password here is so that the script can create databases
	// ready for opensim instances, so the user needs create privileges
	$mysql= array(
		'RegionDBServer'	=>array('server'=>'localhost',
															'user'	=>'opensim_admin',
															'pwd'		=>'0p3n51m4dm1n'),
		'EstateDBServer'	=>array('server'=>'localhost',
															'user'	=>'opensim_admin',
															'pwd'		=>'0p3n51m4dm1n')
	);

	// where are the opensim databases kept for regions and estate?
	// the username and password here is what opensim instances will use to access
	// the region and estate databases
	$opensim=array(
		'RegionDBServer'	=>array('server'=>'localhost',
															'user'	=>'opensim',
															'pwd'		=>'0p3n51m$'),
		'EstateDBServer'	=>array('server'=>'localhost',
															'user'	=>'opensim',
															'pwd'		=>'0p3n51m$')
	);


	define('INSTANCE_MIN_PORT',9016);
	define('INSTANCE_MAX_PORT',9024);
	define('INSTANCE_DB_PREFIX','Opensim_');
	define('ESTATE_DB','Estate');

	//used only if you enable remote admin http server and configure it for https
	define('SSL_PORT_OFFSET',500); //offset from http base port

	define('TIMEOUT',20); //timeout in seconds when starting and stopping instances
	define('MAX_RESTART_COUNT',3); //how many times do we attempt a restart until mark as broken?
	define('MAX_RESTART_TIME_INTERVAL',30); //how long do we consider an instance being up means it's not broken
	define('TMUX_SESSION_NAME','OS'); //name for the session that tmux will use for its instance windows
	define('TMUX_START_TIMEOUT',5);
	define('TMUX_STOP_TIMEOUT',10);
	define('TMUX_CONTROL_PREFIX','C-a'); //what control key combo do we use for tmux? CTRL+A
	define('TMUX_SCROLL_BUFFER',10000);  //how much scrollback do we want?

	define('BULLET_IN_OWN_THREAD',1); //run bulletsim in it's own thread

	define('REGION_NAMES_REGEX','/^[a-z0-9\ \_\-\*\(\)\!]+$/i'); //what are legal characeters in region names

	date_default_timezone_set('Europe/London');

?>
