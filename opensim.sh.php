#!/usr/bin/php -q
<?php
error_reporting (E_ALL);
define('HOME_DIR',trim(`echo ~`));
define('BASE_DIR',HOME_DIR.'/MIOS/Instances/');
define('BASE_CONFIGS',BASE_DIR.'.config/');
define('CONFIGS_DIR',BASE_DIR.'%s/Configs/');
define('LOGS_DIR',BASE_DIR.'%s/Logs/');
define('SCRIPTS_DIR',BASE_DIR.'%s/ScriptEngines/');
define('OUT_CONF_DIR',BASE_DIR.'%s/ConfigOut/');
define('OS_ROOT_DIR',HOME_DIR.'/opensim/');
define('BIN_DIR',HOME_DIR.'/opensim/bin/');
define('INC_DIR',HOME_DIR.'/MIOS/php_inc/');
define('OS_RUNNER',INC_DIR.'os_runner.sh.php');
define('OS_RUNNER_LOG_DIR',HOME_DIR.'/MIOS/Logs/');
define('OS_RUNNER_LOG',OS_RUNNER_LOG_DIR.'os_runner.log');
define('OS_EXEC',INC_DIR.'os_exec.sh');

if(file_exists(BASE_CONFIGS.'config.inc.php')) include(BASE_CONFIGS.'config.inc.php');
else die("You must create a config file '".BASE_CONFIGS."config.inc.php' before you can use this script!\nPlease use '".BASE_CONFIGS."config.inc.php.example' as a template .\n");
include('php_inc/dbfunctions.inc.php');
include('php_inc/functions.inc.php');
include('php_inc/os_functions.inc.php');

ob_implicit_flush ();
define("DEBUG",0);
//script specific debug defines
define('SHOWINFO',1);
define('SHOWCMDS',2);
define('SHOWCMDERRORS',4);
define('LOGCMDERRORS',8);

set_time_limit (0);

// signal handling
declare(ticks=1); $must_exit=0;
pcntl_signal(SIGTERM, "signal_handler");
pcntl_signal(SIGINT, "signal_handler");

$argc=$_SERVER["argc"];
$argv=$_SERVER["argv"]; //$argv is an array
if($argc==0) error(usage());
$args=parse_args($argc,$argv);
if(isset($args['h']) or isset($args['help'])) error(usage());
if(isset($args['d']) or isset($args['debug'])) $debug=1; else $debug=0;

$dependencies=array('/usr/bin/git','/usr/bin/tmux','/usr/bin/mono','/usr/bin/xbuild');
foreach($dependencies as $d) if(!file_exists($d)) die("You are missing a dependency that is needed by MIOS! Please install package containing '$d'\n");

//****************************************************************************************************UPDATE/INSTALL OPENSIM****
if(isset($args['os-update'])) {
	if(!file_exists(BIN_DIR)) { //no opensim is installed. Do a fresh git clone.
		$cmd='cd '.HOME_DIR.'; git clone https://github.com/opensim/opensim.git && ./runprebuild.sh autoclean && ./runprebuild.sh vs2010 && xbuild /p:Configuration=Release';
		if($debug) printf("Running: %s\n",$cmd);
		passthru($cmd);
	} else {
		$cmd='cd '.OS_ROOT_DIR.'; mv bin/OpenSim.exe.config bin/OpenSim.exe.config.tmp; git pull && xbuild /t:clean && ./runprebuild.sh autoclean && ./runprebuild.sh vs2010 && xbuild /p:Configuration=Release; mv bin/OpenSim.exe.config.tmp bin/OpenSim.exe.config';
		if($debug) printf("Running: %s\n",$cmd);
		passthru($cmd);
	}
	exit(0);
}
//**************************************************************************************UPDATE/INSTALL OPENSIM GRID CONFIGS****
if(isset($args['grid-config'])) {
	$grid=$args['grid-config'];
	if(isset($gridconfigs[$grid])) {
		foreach($gridconfigs[$grid] as $k=>$v) {
			$cmd="wget -O ".BASE_CONFIGS."$k $v";
			if($debug) printf("Running: %s\n",$cmd);
			passthru($cmd);
		}
	} else die("Could not find a section in the config for retrieving configs from '$grid'.\n");
}
//****************************************************************************************************SETUP MYSQL DB USERS****
if(isset($args['init-mysql'])) {
	if(!file_exists('/var/run/mysqld/mysqld.pid')) die("Mysql Server is not running!\nPlease make sure that Mysql is installed and configured to always run at boot time.\n");
	print "We are about to add two users to mysql, one for administering databases, and one that opensim will use for database access.\n";

	if($mysql['RegionDBServer']['server']==$mysql['EstateDBServer']['server']) {
		print "You will need to provide the mysql root password to do this.\n";
		$sql=sprintf("drop user '%s'@localhost;\ndrop user '%s'@localhost;\ngrant CREATE,DROP,ALTER,RELOAD on *.* to '%s'@'localhost' identified by '%s';\ngrant all on `%s%%`.* to '%s'@'localhost' identified by '%s';\ngrant all on %s.* to '%s'@'localhost';\nflush privileges;\n",
				$mysql['RegionDBServer']['user'],
				$opensim['RegionDBServer']['user'],
				$mysql['RegionDBServer']['user'],$mysql['RegionDBServer']['pwd'],
				INSTANCE_DB_PREFIX,$opensim['RegionDBServer']['user'],$opensim['RegionDBServer']['pwd'],
				ESTATE_DB,$opensim['RegionDBServer']['user']);
		if(write_text_file(BASE_CONFIGS.'tmp.sql',$sql)) {
			$cmd=sprintf("mysql -h %s -u root -p <%s", $mysql['RegionDBServer']['server'], BASE_CONFIGS.'tmp.sql');
			if($debug) printf("Running: %s\n",$cmd);
			`$cmd`;
			@unlink(BASE_CONFIGS.'tmp.sql');
		}
	} else {
		print "You will need to provide the mysql root password twice to do this.\n";
		printf("Configuring server %s:\n",$mysql['RegionDBServer']['server']);
		$sql=sprintf("grant CREATE,DROP,ALTER,RELOAD on *.* to '%s'@'localhost' identified by '%s';\ngrant all on `%s%%`.* to '%s'@'localhost' identified by '%s';\nflush privileges;\n",
				$mysql['RegionDBServer']['user'],$mysql['RegionDBServer']['pwd'],
				INSTANCE_DB_PREFIX,$opensim['RegionDBServer']['user'],$opensim['RegionDBServer']['pwd']);
		if(write_text_file(BASE_CONFIGS.'tmp.sql',$sql)) {
			$cmd=sprintf("mysql -h %s -u root -p <%s", $mysql['RegionDBServer']['server'], BASE_CONFIGS.'tmp.sql');
			if($debug) printf("Running: %s\n",$cmd);
			`$cmd`;
			@unlink(BASE_CONFIGS.'tmp.sql');
		}
		printf("Configuring server %s:\n",$mysql['EstateDBServer']['server']);
		$sql=sprintf("grant CREATE,DROP,ALTER,RELOAD on *.* to '%s'@'localhost' identified by '%s'\ngrant all on %s.* to '%s'@'localhost' identified by '%s'\nflush privileges;\n",
				$mysql['EstateDBServer']['user'],$mysql['EstateDBServer']['pwd'],
				ESTATE_DB,$opensim['EstateDBServer']['user'],$opensim['EstateDBServer']['pwd']);
		if(write_text_file(BASE_CONFIGS.'tmp.sql',$sql)) {
			$cmd=sprintf("mysql -h %s -u root -p <%s", $mysql['EstateDBServer']['server'], BASE_CONFIGS.'tmp.sql');
			if($debug) printf("Running: %s\n",$cmd);
			`$cmd`;
			@unlink(BASE_CONFIGS.'tmp.sql');
		}
	}
	exit(0);
}

//get a list of instance configs
$instances=enum_instances();
$runlist=BASE_CONFIGS.'.runlist';
$pidfile=BASE_CONFIGS.'.pidfile';
$tmuxfile=HOME_DIR.'/.tmux.conf';

//****************************************************************************************************ADD INSTANCE**************
if(isset($args['add-instance'])) {
	$inst=$args['add-instance'];
	if($inst===1) die("You must specify an instance name to add!\n");
	if(in_array($inst,$instances)) die("An Instance of that name already exists!\n");
	@mkdir(BASE_DIR.$inst);
	@mkdir(sprintf(OUT_CONF_DIR,$inst));
	@mkdir(sprintf(OUT_CONF_DIR.'Regions/',$inst));
	@mkdir(sprintf(SCRIPTS_DIR,$inst));
	@mkdir(sprintf(LOGS_DIR,$inst));
	print "The Instance configs were generated successfully! Use --add-region now to add regions to this instance.\n";
	exit(0);
}

//everything below here requires some instances to be configured!
if(count($instances)<1) die("There are no Opensim Instances Configured! Use --add-instance to start.\n");


//****************************************************************************************************LIST INSTANCES*************
if(isset($args['inst'])) {
	print "Configured Instances:\n";
	foreach($instances as $li) print $li."\n";
	exit(0);
}
//****************************************************************************************************LIST INSTANCE(S) & REGIONS***
if(isset($args['list'])) {
	if($args['list']==1) $list=$instances; else $list=explode(',',str_replace('"','',trim($args['list'])));
	foreach($list as $li) if(!in_array($li,$instances)) die(sprintf("An instance named '%s' was not found! Use --inst to show possible instance names.\n",$li));
	$used_ports=array();
	$used_uuids=array();
	$base_port=array();
	$regions_list=array();
	$rl=read_text_file_to_array_with_lock($runlist,LOCK_EX);
	foreach($list as $inst) $info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list);
	foreach($list as $inst) {
		$cstatus=get_instance_status($rl,$inst,$base_port[$inst]);
		if(!count($regions_list[$inst])) $cstatus.='/unconfigured';
		print "\n".pad_clip_string(sprintf("+-Instance: %s---",$inst),61,'-','-')."+  *".$cstatus."\n";
		print pad_clip_string('+',61,'-','-').pad_clip_string('+',37,'-','-')."+\n|";
		$info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list);
		$h=array('RegionName'=>20,'RegionUUID'=>37,'GridLocation'=>15,'Port'=>6,'Size'=>10);
		foreach($h as $t=>$s) print " ".pad_clip_string($t,$s)."|";
		print "\n".pad_clip_string('+',98,'-','-')."+\n";
		if(count($info)) {
			foreach($info as $i) {
				print "| ".pad_clip_string($i['RegionName'],20)."| ";
				print pad_clip_string($i['RegionUUID'],37)."| ";
				print pad_clip_string(str_replace('"','',$i['Location']),15)."| ";
				print pad_clip_string($i['InternalPort'],6)."| ";
				print pad_clip_string($i['SizeX'].'x'.$i['SizeY'],10)."|\n";
			}
		} else print pad_clip_string("| No Regions!",98)."|\n";
		print pad_clip_string('+',98,'-','-')."+\n";
	}
	exit(0);
}
//****************************************************************************************************ADD REGION**************
if(isset($args['add-region'])) {
	$region=$args['add-region'];
	if($region===1) die("You must specify an region name to add!\n");
	if(!preg_match(REGION_NAMES_REGEX,$region)) die("The region name you specifed has illegal characters!\n");

	if(isset($args['location'])) {
		$location=$args['location'];
		if(!preg_match("/^([0-9]{1,5}),([0-9]{1,5})$/",$location,$m)) die("Location must in the format x,y!\n");
	} else die("I need a --location in the format x,y!\n");

	$used_ports=array();
	$used_uuids=array();
	$base_port=array();
	$regions_list=array();
	$locations=array();
	foreach($instances as $inst) {
		$info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list);
		foreach($info as $i) {
			if($location==str_replace(array(' ','"'),'',$i['Location'])) die("That Grid Location is already in use by another region!\n");
			if($region==$i['RegionName']) die("A region by that name already exists in this or another instance!\n");
		}
	}
	if(isset($args['instance'])) {
		$inst=$args['instance'];
		if(!in_array($inst,$instances)) die("You must specify a valid instance name that this region should be added to!\n");
		$rl=read_text_file_to_array_with_lock($runlist,LOCK_EX);
		$cstatus=get_instance_status($rl,$inst,$base_port[$inst]);
		if($cstatus!='stopped') die("This instance must be stopped before you can add a region!\n");
	} else die("You must specify an instance name using --instance that this region should be added to!\n");

	if(isset($args['port'])) {
		$port=0;
		if(!try_parse_port($args['port'],$port)) die("Port must be a number!\n");
		if(in_array($port,$used_ports)) die("That port is already in use by another region!\n");
	} else {
		$port=INSTANCE_MIN_PORT;
		while(in_array($port,$used_ports) and $port<INSTANCE_MAX_PORT) $port++;
		if($port>INSTANCE_MAX_PORT) die(sprintf("Could not allocate a port to instance %s, region %s!\n",$inst,$region));
		if($debug) printf("- Allocated new port %d to instance %s, region %s\n",$port,$inst,$region);
	}

	if(isset($args['uuid'])) {
		$uuid='';
		if(!try_parse_uuid($args['uuid'],$uuid)) die("UUID must be in the format xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx!\n");
		if(in_array($uuid,$used_uuids)) die("That UUID is already in use by another region!\n");
	} else {
		$uuid=create_uuid();
		while(in_array($uuid,$used_uuids)) $uuid=create_uuid();
		if($debug) printf("- Allocated new UUID %s to instance %s, region %s\n",$uuid,$inst,$region);
	}

	$size=256;
	if(isset($args['size'])) {
		if(!try_parse_size($args['size'],$size)) die("Size must be a multiple of 256!\n");
	} else {
		if($debug) print "- Setting region size to 256 (256x256m)\n";
	}

	// now create (or add to) the basic region config
	make_instance_directories($inst);
	$rconfig=sprintf(CONFIGS_DIR,$inst).'Regions/Regions.ini';
	if($debug) printf("- Reading config file: %s\n",$rconfig);
	$rini=parse_ini($rconfig, true, INI_SCANNER_RAW) or array();
	$rini[$region]=array('Location'=>'"'.$location.'"','RegionUUID'=>$uuid,'InternalPort'=>$port,'SizeX'=>$size,'SizeY'=>$size);
	if(!write_ini($rconfig,$rini)) die("ERROR: Could not write ini file $rconfig !\n");
	if($debug) printf("* Updated Region config %s.\n",$rconfig);
	printf("Region %s was created in Instance %s with UUID %s on port %d.\n",$region,$inst,$uuid,$port);

}
//****************************************************************************************************RENAME REGION***********
if(isset($args['rename-region'])) {
	$rregion=$args['rename-region'];
	if($rregion===1) die("Please specify the new region name!\n");
	if(!preg_match(REGION_NAMES_REGEX,$rregion)) die("The region name you specifed has illegal characters!\n");
	if(isset($args['uuid'])) {
		$uuid='';
		if(!try_parse_uuid($args['uuid'],$uuid)) die("UUID must be in the format xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx!\n");
	} else die("You must supply the region's UUID in the format --uuid=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx!\n");

	$used_ports=array();
	$used_uuids=array();
	$base_port=array();
	$regions_list=array();
	foreach($instances as $inst) {
		$info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list);
		if($found=find_region_by_uuid($info,$uuid)) break;
	}
	if(!$found)	die("That region UUID could not be found in any instance!\n");
	$rl=read_text_file_to_array_with_lock($runlist,LOCK_EX);
	$cstatus=get_instance_status($rl,$inst,$base_port[$inst]);
	if($cstatus!='stopped' and $cstatus!='broken') die("This instance must be stopped before you can rename a region!\n");
	if($found['RegionName']==$rregion) die("The new region name is the same as the old one!\n");
	$rconfig=sprintf(CONFIGS_DIR,$inst).'Regions/Regions.ini';
	if($debug) printf("- Reading config file: %s\n",$rconfig);
	$rini=parse_ini($rconfig, true, INI_SCANNER_RAW) or array();
	$rini[$rregion]=$rini[$found['RegionName']];
	unset($rini[$found['RegionName']]);
	print_r($rini);
	if(!write_ini($rconfig,$rini)) die("ERROR: Could not write ini file $rconfig !\n");
	if($debug) printf("* Updated Region config %s.\n",$rconfig);
}


//*********************************************************************************************START RESTART STOP STATUS VIEW VISITORS****
$start=0; $restart=0; $stop=0; $status=0; $view=0; $config=0; $visitors=0; $manual=0;
if(isset($args['start'])) {
	if($args['start']==1) $start=$instances; else $start=explode(',',str_replace('"','',trim($args['start'])));
	if($args['manual']==1) $manual=1;
}
if(isset($args['restart'])) {
	if($args['restart']==1) $restart=$instances; else $restart=explode(',',str_replace('"','',trim($args['restart'])));
}
if(isset($args['stop'])) {
	if($args['stop']==1) $stop=$instances; else $stop=explode(',',str_replace('"','',trim($args['stop'])));
}

if(($start and $restart) or ($start and $stop) or ($restart and $stop)) die("Options --start --restart or --stop cannot be used together!\n");

if(isset($args['config'])) {
	if($args['config']==1) $config=$instances; else $config=explode(',',str_replace('"','',trim($args['config'])));
}
if(isset($args['status'])) {
	if($args['status']==1) $status=$instances; else $status=explode(',',str_replace('"','',trim($args['status'])));
}
if(isset($args['view'])) {
	if($args['view']==1) $view=1; else $view=explode(',',str_replace('"','',trim($args['view'])));
}
if(isset($args['visitors'])) {
	if($args['visitors']==1) $visitors=$instances; else $visitors=explode(',',str_replace('"','',trim($args['visitors'])));
}


select_db_server('EstateDBServer');
dbquery("create database if not exists ".ESTATE_DB." default character set utf8 collate utf8_unicode_ci");

select_db_server('RegionDBServer');

//print_r($instances);
//print_r($start);
//print_r($restart);
//print_r($stop);
//exit(0);

//****************************************************************************************************RESTART INSTANCE(S)***********
if($restart) {
	$stop=$restart; //trigger stop action
	$start=$restart; //trigger start action
}
//****************************************************************************************************STOP INSTANCE(S)**************
if($stop) {
	foreach($stop as $si) if(!in_array($si,$instances)) die(sprintf("An instance named '%s' was not found! Use --inst to show possible instance names.\n",$si));
	// start by enumerating all the existing region names, uuids and ports,
	// each instance can run one or more regions and if regions are defined without ports and uuids
	// then we add them to Regions.ini now so there are no conflicts later
	$instances=$stop;
	$used_ports=array();
	$used_uuids=array();
	$base_port=array();
	$regions_list=array();
	foreach($instances as $inst) {
		$info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list);
	}

	// make sure that the os_runner daemon is running
	if(file_exists($pidfile)) {
		$pid=trim(file_get_contents($pidfile));
		if(!file_exists("/proc/${pid}")) @unlink($pidfile);
	}
	if(!file_exists($pidfile)) {
		@mkdir(OS_RUNNER_LOG_DIR);
		$cmd=OS_RUNNER.' -d 2>/dev/null >>'.OS_RUNNER_LOG.' &';
		if($debug) printf("Running %s\n",$cmd);
		`exec $cmd`;
	}

	foreach($instances as $inst) {

		if(count($regions_list[$inst])==0) {
			if($debug) printf("Skipping Instance %s as it has no regions!\n",$inst);
			continue;
		}
		$rs=str_replace(" ","_",$inst); //replace spaces with _ in instance names for when we create the database and tmux windows

		printf("Stopping Instance: %s",$inst);
		$entry=sprintf("%s\t%s\t%s\t",$inst,$rs,$base_port[$inst]);
		$cstatus='stopped'; //default if no entry found
		$timer=0;

		while(1) {
			$rl=read_text_file_to_array_with_lock($runlist,LOCK_EX);
			for($i=0;$i<count($rl);$i++) {
				if(substr($rl[$i],0,strlen($entry))==$entry) {
					$cstatus=trim(substr($rl[$i],strlen($entry)));
					if($cstatus=='') $cstatus='stopped';
					break;
				}
			}
			if($cstatus=='stopped') {
				print "\r\t\t\t\t\t\t[  OK  ]\n";
				break;
			}
			if($cstatus=='started') {
				$rl[$i]=sprintf("%s\t%s\t%s\t%s",$inst,$rs,$base_port[$inst],'stop');
				sort($rl);
				write_text_file_from_array_with_lock($runlist,$rl,LOCK_EX);
			}
			if($cstatus=='start') {
				print "\r\t\t\t\t\t\t[FAILED] Instance is still starting!\n";
				break;
			}
			sleep(2);
			$timer+=2;
			print ".";
			if($timer>=TIMEOUT) {
				printf("\r\t\t\t\t\t\t[FAILED] Instance did not stop within %s seconds!\n",TIMEOUT);
				break;
			}
		}
	}

	//now check and see if all are stopped, if so we can kill the os_runner daemon
	$rl=read_text_file_to_array_with_lock($runlist,LOCK_EX);
	$stopped=0;
	for($i=0;$i<count($rl);$i++) {
		if(substr($rl[$i],-7)=='stopped') $stopped++;
	}
	//print $stopped." ".count($rl);
	if($stopped==count($rl)) { //all stopped
		if($debug) print "All Stopped! Terminating os_runner.\n";
		if(file_exists($pidfile)) {
			$pid=trim(file_get_contents($pidfile));
			$cmd='kill -s 2 '.$pid;
			if($debug) print "Running $cmd\n";
			`$cmd`;
			@unlink($pidfile);
		}
	}
}
//****************************************************************************************************START INSTANCE(S)*************
if($start) {
	foreach($start as $si) if(!in_array($si,$instances)) die(sprintf("An instance named '%s' was not found! Use --inst to show possible instance names.\n",$si));
	// start by enumerating all the existing region names, uuids and ports,
	// each instance can run one or more regions and if regions are defined without ports and uuids
	// then we add them to Regions.ini now so there are no conflicts later
	$instances=$start;
	$used_ports=array();
	$used_uuids=array();
	$base_port=array();
	$regions_list=array();
	foreach($instances as $inst) {
		$info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list);
	}

	// make sure that the os_runner daemon is running
	if(file_exists($pidfile)) {
		$pid=trim(file_get_contents($pidfile));
		if(!file_exists("/proc/${pid}")) @unlink($pidfile);
	}
	if(!file_exists($pidfile)) {
		@mkdir(OS_RUNNER_LOG_DIR);
		$cmd=OS_RUNNER.' -d 2>/dev/null >>'.OS_RUNNER_LOG.' &';
		if($debug) printf("Running %s\n",$cmd);
		`exec $cmd`;
	}

	foreach($instances as $inst) {

		if(count($regions_list[$inst])==0) {
			if($debug) printf("Skipping Instance %s as it has no regions!\n",$inst);
			continue;
		}

		if($debug) {
			printf("Configuring an OpenSim instance: %s\n",$inst);
			printf("- Instance contains regions: %s\n",implode(', ',$regions_list[$inst]));
		}
		$rs=str_replace(" ","_",$inst); //replace spaces with _ in instance names for when we create the database and tmux windows
		make_instance_directories($inst);

		$inis[]=array('Const'=>	array('InstanceName'=>$inst,
																	'InstancePort'=>$base_port[$inst],
																	'InstanceDBName'=>INSTANCE_DB_PREFIX.$rs,
																	'EstateDBName'=>ESTATE_DB,
																	'ConfigBase'=>sprintf(CONFIGS_DIR,$inst)
																		));
		$inifiles=array(BASE_CONFIGS."OpenSimDefaults.ini",
										BASE_CONFIGS."OpenSim.ini",
										BASE_CONFIGS."config-include/GridHypergrid.ini",
										BASE_CONFIGS."config-include/GridCommon.ini",
										BASE_CONFIGS."config-include/FlotsamCache.ini");

		foreach($inifiles as $inifile) {
			if($debug) printf("- Reading config file: %s\n",$inifile);
			$inis[]=parse_ini($inifile, true, INI_SCANNER_RAW) or array();
		}

		//base config override ini files
		$base_overrides_path=str_replace(' ','\ ',BASE_CONFIGS.'Overrides/*.ini');
		$base_overrides_inis=explode("\n", trim(`ls -1 {$base_overrides_path} 2>/dev/null`));
		foreach($base_overrides_inis as $bi) {
			if($bi!='') {
				if($debug) printf("- Reading config file: %s\n",$bi);
				$inis[]=parse_ini($bi, true, INI_SCANNER_RAW) or array();
			}
		}

		//instance specific override ini files
		$region_overrides_path=str_replace(' ','\ ',sprintf(CONFIGS_DIR,$inst).'Overrides/*.ini');
		$region_overrides_inis=explode("\n", trim(`ls -1 {$region_overrides_path} 2>/dev/null`));
		foreach($region_overrides_inis as $bi) {
			if($bi!='') {
				if($debug) printf("- Reading config file: %s\n",$bi);
				$inis[]=parse_ini($bi, true, INI_SCANNER_RAW) or array();
			}
		}

		$ini=ini_merge($inis);

		unset($ini['Architecture']);
		unset($ini['Includes']);
		unset($ini['Modules']['Include-modules']);

		//set some dynamic values directly that configure some parts of opensim.
		//(we could use an override.ini and the ${Const|Key} method but it's easier like this)
		$ini['Startup']['ConsoleHistoryFile']=sprintf(LOGS_DIR,$inst).'OpenSimConsoleHistory.txt';
		$ini['Startup']['ConsoleHistoryFileEnabled']='true';
		$ini['Startup']['PIDFile']=sprintf(LOGS_DIR,$inst).'OpenSim.pid';
		$ini['Startup']['LogFile']=sprintf(LOGS_DIR,$inst).'OpenSim.log';
		$ini['Startup']['regionload_regionsdir']=sprintf(OUT_CONF_DIR,$inst).'Regions/';
		$ini['Startup']['shutdown_console_commands_file']=sprintf(CONFIGS_DIR,$inst).'shutdown_commands.txt';
		$ini['Startup']['startup_console_commands_file']=sprintf(CONFIGS_DIR,$inst).'startup_commands.txt';

		$ini['XEngine']['ScriptEnginesPath']=sprintf(SCRIPTS_DIR,$inst);

		$ini['Network']['http_listener_port']=$base_port[$inst];
		$ini['Network']['http_listener_sslport']=$base_port[$inst]+SSL_PORT_OFFSET;

		unset($ini['DatabaseService']['Include-Storage']); //always using mysql
		$ini['DatabaseService']['StorageProvider']='OpenSim.Data.MySQL.dll';
		$ini['DatabaseService']['ConnectionString']=sprintf("Data Source=%s;Database=%s;User ID=%s;Password=%s;Old Guids=true;",
																													$opensim['RegionDBServer']['server'],
																													INSTANCE_DB_PREFIX.$rs,
																													$opensim['RegionDBServer']['user'],
																													$opensim['RegionDBServer']['pwd']);
		$ini['DatabaseService']['EstateConnectionString']=sprintf("Data Source=%s;Database=%s;User ID=%s;Password=%s;Old Guids=true;",
																													$opensim['EstateDBServer']['server'],
																													ESTATE_DB,
																													$opensim['EstateDBServer']['user'],
																													$opensim['EstateDBServer']['pwd']);
		$ini['BulletSim']['UseSeparatePhysicsThread']=BULLET_IN_OWN_THREAD?'True':'False';

		$errors=0;
		$c_inipath=sprintf(OUT_CONF_DIR,$inst).'combined.ini';
		if(!write_ini($c_inipath,$ini)) {
			print "WARNING: Could not write ini file $c_inipath !\n";
			$errors++;
		}

		$e_inipath=sprintf(OUT_CONF_DIR,$inst).'empty.ini';
		if(!write_ini($e_inipath,array())) {
			print "WARNING: Could not write ini file $e_inipath !\n";
			$errors++;
		}

		// Make sure we have a database to work in!
		if($debug) printf("- Creating (if not exists) a region database: %s%s\n",INSTANCE_DB_PREFIX,$rs);
		dbquery(sprintf("create database if not exists %s%s default character set utf8 collate utf8_unicode_ci",INSTANCE_DB_PREFIX,$rs));

		// Make sure some files and paths are present!
		if(!file_exists($ini['Startup']['shutdown_console_commands_file'])) write_text_file($ini['Startup']['shutdown_console_commands_file'],"; Lines that start with ; are comments.");
		if(!file_exists($ini['Startup']['startup_console_commands_file'])) write_text_file($ini['Startup']['startup_console_commands_file'],"; Lines that start with ; are comments.");
		if(!file_exists($ini['XEngine']['ScriptEnginesPath'])) mkdir($ini['XEngine']['ScriptEnginesPath']);

		// move old logs to a dated file?

		// Now tell the runner to launch the instance

		if($errors===0) {

			if($manual) {

				//printf("Manually Starting Instance: %s",$inst);
				$c_inipath=sprintf(OUT_CONF_DIR,$inst).'combined.ini';
				$e_inipath=sprintf(OUT_CONF_DIR,$inst).'empty.ini';
				$pidpath=sprintf(LOGS_DIR,$inst).'OpenSim.pid';
				$cmd=sprintf("%s \"%s\" \"%s\" \"%s\"\n",OS_EXEC,$e_inipath,$c_inipath,$pidpath);
				print "Run this command: $cmd";
				//printf("Manually Ended Instance: %s",$inst);

			} else {

				printf("Starting Instance: %s",$inst);
				$entry=sprintf("%s\t%s\t%s",$inst,$rs,$base_port[$inst]);
				$pidpath=sprintf(LOGS_DIR,$inst).'OpenSim.pid';
				$cstatus='stopped'; //default if no entry found
				$timer=0;

				while(1) {
					$rl=read_text_file_to_array_with_lock($runlist,LOCK_EX);
					for($i=0;$i<count($rl);$i++) {
						if(substr($rl[$i],0,strlen($entry))==$entry) {
							$cstatus=trim(substr($rl[$i],strlen($entry)));
							if($cstatus=='') $cstatus='stopped';
							break;
						}
					}
					if($cstatus=='broken') {
						if(isset($args['f']) or isset($args['fixed'])) $cstatus='stopped';
						else {
							print "\r\t\t\t\t\t\t[FAILED] Instance appears to be broken! Use -f to force start.\n";
							printf("\tor to try a manual unmanaged start you can invoke the instance with: %s \"%s\" \"%s\" \"%s\"\n",OS_EXEC,$e_inipath,$c_inipath,$pidpath);
							break;
						}
					}
					if($cstatus=='started') {
						print "\r\t\t\t\t\t\t[  OK  ]\n";
						break;
					}
					if($cstatus=='stopped') {
						$rl[$i]=sprintf("%s\t%s\t%s\t%s",$inst,$rs,$base_port[$inst],'start');
						sort($rl);
						write_text_file_from_array_with_lock($runlist,$rl,LOCK_EX);
					}
					if($cstatus=='stop') {
						print "\r\t\t\t\t\t\t[FAILED] Instance is still stopping!\n";
						printf("\tTo try a manual unmanaged start you can invoke the instance with: %s \"%s\" \"%s\" \"%s\"\n",OS_EXEC,$e_inipath,$c_inipath,$pidpath);
						break;
					}
					sleep(2);
					$timer+=2;
					print ".";
					if($timer>=TIMEOUT) {
						printf("\r\t\t\t\t\t\t[FAILED] Instance did not start within %s seconds!\n",TIMEOUT);
						printf("\tTo try a manual unmanaged start you can invoke the instance with: %s \"%s\" \"%s\" \"%s\"\n",OS_EXEC,$e_inipath,$c_inipath,$pidpath);
						break;
					}
				}
			}
		}
	}
}
//****************************************************************************************************CONFIG OF INSTANCE(S)********
if($config) {
	if(isset($args['section'])) $section=explode(",",$args['section']); else $section=0;
	foreach($config as $ci) if(!in_array($ci,$instances)) die(sprintf("An instance named '%s' was not found! Use --inst to show possible instance names.\n",$ci));
	// start by enumerating all the existing region names, uuids and ports,
	// each instance can run one or more regions and if regions are defined without ports and uuids
	// then we add them to Regions.ini now so there are no conflicts later
	$instances=$config;
	$used_ports=array();
	$used_uuids=array();
	$base_port=array();
	$regions_list=array();
	foreach($instances as $inst) {
		$info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list);
	}
	foreach($instances as $inst) {
		$c_inipath=sprintf(OUT_CONF_DIR,$inst).'combined.ini';
		$ini=parse_ini($c_inipath, true, INI_SCANNER_RAW) or array();
		print "***********************************************************************************\n";
		printf("Instance: %s\n",$inst);
		if($section) {
			foreach($section as $s) {
				if(isset($ini[$s])) {
					printf("Section:\n[%s]\n",$s);
					foreach($ini[$s] as $k=>$v) printf("\t%s = %s\n",$k,$v);
				}
			}
		} else {
			foreach($ini as $s=>$data) {
				printf("\n[%s]\n",$s);
				foreach($data as $k=>$v) printf("\t%s = %s\n",$k,$v);
			}
		}
		print "***********************************************************************************\n";
	}

}
//****************************************************************************************************STATUS OF INSTANCE(S)********
if($status) {
	foreach($status as $si) if(!in_array($si,$instances)) die(sprintf("An instance named '%s' was not found! Use --inst to show possible instance names.\n",$si));
	// start by enumerating all the existing region names, uuids and ports,
	// each instance can run one or more regions and if regions are defined without ports and uuids
	// then we add them to Regions.ini now so there are no conflicts later
	$instances=$status;
	$used_ports=array();
	$used_uuids=array();
	$base_port=array();
	$regions_list=array();
	foreach($instances as $inst) {
		$info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list);
	}
	$runlist=BASE_CONFIGS.'.runlist';
	$rl=read_text_file_to_array_with_lock($runlist,LOCK_EX);
	foreach($instances as $inst) {
		printf("Instance %s is ",$inst);
		print get_instance_status($rl,$inst,$base_port[$inst])."\n";
	}
}
//****************************************************************************************************VIEW INSTANCE(S) CONSOLE(s)****
if($view) {
	$tsn=TMUX_SESSION_NAME;
	write_text_file($tmuxfile,"set-option -g prefix ".TMUX_CONTROL_PREFIX."\nset-option -g history-limit ".TMUX_SCROLL_BUFFER."\n");
	if(is_array($view)) {
		foreach($view as $si) if(!in_array($si,$instances)) die(sprintf("An instance named '%s' was not found! Use --inst to show possible instance names.\n",$si));
		// start by enumerating all the existing region names, uuids and ports,
		// each instance can run one or more regions and if regions are defined without ports and uuids
		// then we add them to Regions.ini now so there are no conflicts later
		$instances=$view;
		$used_ports=array();
		$used_uuids=array();
		$base_port=array();
		$regions_list=array();
		foreach($instances as $inst) {
			$info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list);
		}
		$index=$base_port[$instances[0]];
	} else $index=0;

	`tmux select-window -t $tsn:$index; tmux attach-session -t $tsn`;
}
//****************************************************************************************************SHOW VISITORS TO INSTANCE(S)****
if(is_array($visitors)) {
	foreach($visitors as $vi) if(!in_array($vi,$instances)) die(sprintf("An instance named '%s' was not found! Use --inst to show possible instance names.\n",$vi));
	$instances=$visitors;
	foreach($instances as $inst) {
		$logpath=sprintf(LOGS_DIR,$inst).'OpenSim.log';
		printf("******** Visitor Log for Instance: '%s' ********\n",$inst);
		$cmd=sprintf('cat %s |awk \'/\\[SCENE\\]: Found presence/{print $1 " " $2 " " $15 " " $9 " " $10 " " $11}\' >/tmp/visitors.txt',$logpath);
		`$cmd`;
		$cmd=sprintf('cat %s |awk \'/\\[SCENE\\]: Region .+ authenticated and authorized incoming root agent/{print $1 " " $2 " " $8 " " $15 " " $16 " " $17}\' >>/tmp/visitors.txt',$logpath);
		`$cmd`;
		$cmd="cat /tmp/visitors.txt |sort";
		passthru($cmd);
		print "\n";
	}
}
//****************************************************************************************************USAGE****
function usage() {
	return "
opensim.sh.php [--option|--option[=]value]

Creates and manages OpenSimulator instances and regions. It allows you to run
multiple instances of Opensim from one binary build of the OpenSim code.
Each Instance can run multiple regions if necessary.

The options that you give to this script determine the actions taken.
The values, if provided must be enclosed in quotes (\") if the value contains
spaces.

**--------------------------------**
**Install/Update and build options**
**--------------------------------**

--os-update
           Install or Update the OpenSim binaries using the latest dev code
           from git master. The version is built in release mode. This command
           is equivalent to:

           cd ~; git clone https://github.com/opensim/opensim.git &&
             ./runprebuild.sh autoclean && ./runprebuild.sh vs2010 &&
             xbuild /p:Configuration=Release

           for an initial install or:

           cd ~/opensim; git pull && xbuild /t:clean &&
             ./runprebuild.sh autoclean && ./runprebuild.sh vs2010 &&
             xbuild /p:Configuration=Release

           for an update.

--grid-config name
           Downloads configs from the web for a named grid, where the filenames
           and web urls are listed in the MIOS configuration. See the example
           config.ini.example file for OSGrid configs

--init-mysql
           Add the two users to Mysql that are required to manage and run
           Opensim Instances. The users are: 'opensim' which have full access
           to each Instance database and the Estate database, and
           'opensim_admin' which is used to create the Instance databases when
           an Instance is added using this script. The host, user and password
           details are taken from the config file:
           ".BASE_CONFIGS."config.inc.php

**--------------------------**
**Managing OpenSim Instances**
**--------------------------**

--inst     Displays a quick list of all the configured instances. Each instance
           has a unique name which is used to identify it.

--list     Displays a detailed table of all the configured instances and the
           Regions that each instance is running. It also shows each instance's
           status, e.g. running, stopped, unconfigured etc.

--list [InstanceName[,InstanceName]...]
           Displays a detailed table of the specified single instance or the
           comma separated list of instances. As above it shows the Regions
           that they are running. It also shows each instance's status, e.g.
           running, stopped, unconfigured etc.


--add-instance InstanceName
           Creates a new instance, that is it creates an new directory called
           'InstanceName' and fills that directory with the base configs that
           are required to control that instance. You will need to add at least
           one Region to the instance in order to be able to start and stop the
           instance.

--start [InstanceName[,InstanceName]...] [--manual]
           Attempt to start all or just the named instances. If an instance for
           some reason does not start in a timely manner, it will show [FAILED].
           If --manual is specified then the instance will be started manually
           from the current shell and will run in interactive mode until
           shutdown from the OpenSim console.

--stop [InstanceName[,InstanceName]...]
           Attempt to stop all or just the named instances. If an instance for
           some reason does not stop in a timely manner, it will show [FAILED].

--restart [InstanceName[,InstanceName]...]
           Attempt to restart all or just the named instances. If an instance
           for some reason does not stop or start in a timely manner, it will
           show as [FAILED].

--status [InstanceName[,InstanceName]...]
           Shows the running status of all or just the named instances.

--view [InstanceName]
           Switch to the console display and optionally select the window for
           the instance InstanceName. The console display(s) run in TMUX, and
           the usual TMUX keys are used to switch panes, scroll and exit etc.

--visitors [InstanceName[,InstanceName]...]
           Show the visitors that have arrived at the regions hosted by the
           named instances.

--config [InstanceName[,InstanceName]...]
           Show the running config of all or just the named instances. An
           instance must have been previously started for the config to exist.
  [--section Name[,Name]...] View only a particular section or sections of the
                             configuration.

If an instance crashes while running, it will be attempted to be restarted.
If it starts and then dies within MAX_RESTART_TIME_INTERVAL seconds then after
MAX_RESTART_COUNT times of trying, the instance will be marked as broken.

**-----------------------------------------**
**Managing Regions within OpenSim Instances**
**-----------------------------------------**

--add-region RegionName
           Required parameters:
  --instance InstanceName   The instance name that the Region will be added to.
  --location xxxxx,yyyyy    Position on the grid for the new Region

           Optional Parameters (values auto generated if not specified):
  [--uuid xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx]  UUID of the new Region
  [--port pppp]             Port number the Region listens on
  [--size xxx]              Size of the region in multiples of 256. Defaults to
                            256 (256x256m)

--rename-region NewRegionName
           Required parameters:
  --uuid=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx    The UUID of the Region you are
                                                 renaming.
";
}

?>
