#!/usr/bin/php -q
<?php
error_reporting (E_ALL);

$mydir=__DIR__;
$mydir=substr($mydir,0,strpos($mydir,'MIOS')-1);
define('HOME_DIR',$mydir);
define('MIOS_DIR',HOME_DIR.'/MIOS/');
define('INC_DIR',MIOS_DIR.'php_inc/');
include(INC_DIR.'os_defines.inc.php');

if(file_exists(BASE_CONFIGS.'config.inc.php')) include(BASE_CONFIGS.'config.inc.php');
else die("You must create a config file '".BASE_CONFIGS."config.inc.php' before you can use this script!\nPlease use '".BASE_CONFIGS."config.inc.php.example' as a template .\n");
include(INC_DIR.'dbfunctions.inc.php');
include(INC_DIR.'functions.inc.php');
include(INC_DIR.'os_functions.inc.php');
include(INC_DIR.'os_config_defines.inc.php');

ob_implicit_flush ();
set_time_limit (0);

// signal handling
declare(ticks=1); $must_exit=0;
pcntl_signal(SIGTERM, "signal_handler");
pcntl_signal(SIGINT, "signal_handler");

$argc=$_SERVER["argc"];
$argv=$_SERVER["argv"]; //$argv is an array
if($argc<=1) error(usage());
$args=parse_args($argc,$argv);
if(isset($args['h']) or isset($args['help'])) error(usage());
$debug=0;
if(isset($args['d'])) $debug=$args['d'];
if(isset($args['debug'])) $debug=$args['debug'];

$dependencies=array('/usr/bin/git','/usr/bin/tmux','/usr/bin/mono','/usr/bin/xbuild','/usr/bin/wget');
foreach($dependencies as $d) if(!file_exists($d)) die("You are missing a dependency that is needed by MIOS! Please install package containing '$d'\n");

//****************************************************************************************************UPDATE/INSTALL OPENSIM****
if(isset($args['os-update'])) {
	if(!file_exists(BIN_DIR)) { //no opensim is installed. Do a fresh git clone.
		$cmd='cd '.HOME_DIR.'; git clone '.OPENSIM_GIT_REPOSITORY.' && cd '.OS_ROOT_DIR.' && ./runprebuild.sh autoclean && ./runprebuild.sh vs2010 && xbuild /p:Configuration=Release';
		if($debug) printf("Running: %s\n",$cmd);
		passthru($cmd);
	} else {
		$cmd='cd '.OS_ROOT_DIR.'; mv bin/OpenSim.exe.config bin/OpenSim.exe.config.tmp; git pull && xbuild /t:clean && ./runprebuild.sh autoclean && ./runprebuild.sh vs2010 && xbuild /p:Configuration=Release; mv bin/OpenSim.exe.config.tmp bin/OpenSim.exe.config';
		if($debug) printf("Running: %s\n",$cmd);
		passthru($cmd);
	}
	exit(0);
}
//****************************************************************************************************UPDATE/INSTALL OPENSIM****
if(isset($args['rebuild'])) {
	if(file_exists(BIN_DIR)) {
		$cmd='cd '.OS_ROOT_DIR.'; ./runprebuild.sh autoclean && ./runprebuild.sh vs2010 && xbuild /p:Configuration=Release;';
		if($debug) printf("Running: %s\n",$cmd);
		passthru($cmd);
	}
	exit(0);
}
//****************************************************************************************************UPDATE/INSTALL OPENSIM****
if(isset($args['recompile'])) {
	if(file_exists(BIN_DIR)) {
		$cmd='cd '.OS_ROOT_DIR.'; xbuild /p:Configuration=Release;';
		if($debug) printf("Running: %s\n",$cmd);
		passthru($cmd);
	}
	exit(0);
}
//**************************************************************************************UPDATE/INSTALL OPENSIM GRID CONFIGS****
if(isset($args['os-config'])) {
	if($args['os-config']==1) $osconfs=array_keys($osconfigs); //use all config sets if none specified
	else $osconfs=explode(",", $args['os-config']);
	foreach($osconfs as $c) {
		print "Starting retrieval of config set '$c'...\n";
		if(isset($osconfigs[$c])) {
			@mkdir(CONFIG_SETS);
			@mkdir(CONFIG_SETS."$c/");
			@mkdir(CONFIG_SETS."$c/config-include/");
			foreach($osconfigs[$c] as $k=>$v) {
				if(substr($v,0,7)=="http://") $cmd="wget -O ".CONFIG_SETS."$c/$k $v";
				else $cmd="/bin/cp -fv $v ".CONFIG_SETS."$c/$k";
				if($debug) printf("Running: %s\n",$cmd);
				passthru($cmd);
			}
		} else die("Could not find a section in the main config that describes the source for opensim configs of type '$c'.\n");
	}
}
//****************************************************************************************************SETUP MYSQL DB USERS****
if(isset($args['init-mysql'])) {
	if(!file_exists('/var/run/mysqld/mysqld.pid')) die("Mysql Server is not running!\nPlease make sure that Mysql is installed and configured to always run at boot time.\n");
	print "We are about to add two users to mysql, one for administering databases, and one that opensim will use for database access.\n";

	if($mysql['RegionDBServer']['server']==$mysql['EstateDBServer']['server']) {
		print "You will need to provide the mysql root password to do this.\n";
		$sql=sprintf("grant CREATE,DROP,ALTER,RELOAD on *.* to '%s'@'localhost' identified by '%s';\ngrant all on `%s%%`.* to '%s'@'localhost' identified by '%s';\ngrant all on %s.* to '%s'@'localhost';\nflush privileges;\n",
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
	//at this point the users should be available in Mysql and we can switch to using native database calls to do MIOS stuff!

	//$dbs=dbsql2array3("SHOW DATABASES");
	//if(!is_array($dbs) or !in_array(ESTATE_DB,$dbs)) { //pre-create the Estate database so we can add Regions to it
	//	dbquery(sprintf("CREATE DATABASE IF NOT EXISTS `%s` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci",ESTATE_DB));
	//}
	//$dbs=dbsql2array3("SHOW DATABASES");
	//if(!is_array($dbs) or !in_array(ESTATE_DB,$dbs)) die(sprintf("Could not create the Estate database named '%s'	!\n",ESTATE_DB));
	//
	//$tables=dbsql2array3(sprintf("SHOW TABLES FROM `%s`",ESTATE_DB));

	exit(0);
}

//get a list of instance configs
$instances=enum_instances();
$runlist=RUN_LIST;
$pidfile=PID_FILE;
$tmuxfile=TMUX_FILE;

//****************************************************************************************************ADD INSTANCE**************
if(isset($args['add-instance'])) {
	$inst=$args['add-instance'];
	if($inst===1) die("You must specify an instance name to add!\n");
	if(!preg_match("/^[A-Z0-9\_\-]+$/i",$inst)) die("Instance names can only contain letters, numbers, underscore and hyphen!\n");
	if(in_array($inst,$instances)) die("An Instance of that name already exists!\n");

	if(isset($args['config-set'])) $cs=$args['config-set'];
	else $cs=DEFAULT_CONFIG_SET;
	if(!in_array($cs,array_keys($osconfigs))) {
		die(sprintf("A config set '%s' does not exist! Valid sets are: %s",$cs,implode(",",array_keys(osconfigs))));
	}
	@mkdir(BASE_DIR.$inst);
	@mkdir(sprintf(CONFIGS_DIR,$inst));
	@mkdir(sprintf(CONFIGS_DIR.'Overrides',$inst));
	@mkdir(sprintf(CONFIGS_DIR.'Regions',$inst));
	set_instance_config_set($inst,$cs);
	@mkdir(sprintf(OUT_CONF_DIR,$inst));
	@mkdir(sprintf(OUT_CONF_DIR.'Regions/',$inst));
	@mkdir(sprintf(SCRIPTS_DIR,$inst));
	@mkdir(sprintf(LOGS_DIR,$inst));
	print "The Instance configs were generated successfully! Use --add-region now to add regions to this instance.\n";
	exit(0);
}
//******************************************************************************************************************************
//everything below here requires some instances to be configured!
if(count($instances)<1) die("There are no Opensim Instances Configured! Use --add-instance to start.\n");

//****************************************************************************************************DEL INSTANCE**************
if(isset($args['del-instance'])) {
	$inst=$args['del-instance'];
	if($inst===1) die("You must specify an instance name to delete!\n");
	if(!in_array($inst,$instances)) die("Couldn't find an Instance with that name! Use --list to get a list of instances.\n");
	$used_ports=array();
	$used_uuids=array();
	$base_port=array();
	$regions_list=array();
	$rl=read_text_file_to_array_with_lock($runlist,LOCK_EX);
	$config_set='';
	$info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list,$config_set);
	$cstatus=get_instance_status($rl,$inst,$base_port[$inst]);

	if($cstatus!='disabled') die(sprintf("The instance named '%s' must be disabled before it can be deleted!\n",$inst));
	printf("Removing Instance: %s",$inst);

	for($i=0;$i<count($rl);$i++) {
		if(substr($rl[$i],0,strlen($inst))==$inst) unset($rl[$i]);
	}
	sort($rl);
	write_text_file_from_array_with_lock($runlist,$rl,LOCK_EX);
	$cmd='rm -Rf '.BASE_DIR.$inst;
	if($debug) printf("Running: %s\n",$cmd);
	`$cmd`;

	$rs=str_replace(" ","_",$inst); //replace spaces with _ in instance names for when we create the database and tmux windows
	select_db_server('RegionDBServer');
	dbquery(sprintf("drop database if exists %s%s",INSTANCE_DB_PREFIX,$rs));

	//so the instance configs have changed. Signal to os_runner.sh.php if running to reload the .runlist
	// make sure that the os_runner daemon is running
	//if(file_exists($pidfile)) {
	//	$pid=trim(file_get_contents($pidfile));
	//	if(file_exists("/proc/${pid}")) posix_kill($pid, SIGHUP);
	//}

	print "\r\t\t\t\t\t\t[  OK  ]\n";
}
//****************************************************************************************************LIST INSTANCES*************
if(isset($args['inst'])) {
	print "Configured Instances:\n";
	foreach($instances as $li) printf("'%s' using base config set for '%s'\n",$li,get_instance_config_set($li));
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
	$config_set='';
	foreach($list as $inst) $info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list,$config_set);
	$config_set='';
	foreach($list as $inst) {
		$cstatus=get_instance_status($rl,$inst,$base_port[$inst]);
		if(!count($regions_list[$inst])) $cstatus.='/unconfigured';
		$cs=get_instance_config_set($inst);
		print "\n".pad_clip_string(sprintf("+-Instance: %s---",$inst),61,'-','-');
		print pad_clip_string(sprintf("+-Config Set: %s-",$cs),25,'-','-')."+  *".$cstatus."\n";

		print pad_clip_string('+',61,'-','-').pad_clip_string('+',37,'-','-')."+\n|";
		$info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list,$config_set);
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
	$config_set='';

	if(isset($args['instance'])) {
		$instance=$args['instance'];
		if(!in_array($instance,$instances)) die("You must specify a valid instance name that this region should be added to!\n");
		$rl=read_text_file_to_array_with_lock($runlist,LOCK_EX);
		$info=enum_instance($instance,$used_ports,$used_uuids,$base_port,$regions_list,$config_set);
		$cstatus=get_instance_status($rl,$instance,$base_port[$instance]);
		if($cstatus!='stopped') die("This instance must be stopped before you can add a region!\n");
	} else die("You must specify an instance name using --instance that this region should be added to!\n");

	$instance_config_set=get_instance_config_set($instance);
	$config_set='';
	foreach($instances as $inst) {
		$info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list,$config_set);
		if($config_set==$instance_config_set) { //locations can be the same on different standalones/grids
			foreach($info as $i) {
				if($location==str_replace(array(' ','"'),'',$i['Location'])) die("That Grid Location is already in use by another region!\n");
				if($region==$i['RegionName']) die("A region by that name already exists in this or another instance!\n");
			}
		}
	}

	if(isset($args['port'])) {
		$port=0;
		if(!try_parse_port($args['port'],$port)) die("Port must be a number!\n");
		if(in_array($port,$used_ports)) die("That port is already in use by another region!\n");
	} else {
		$port=INSTANCE_MIN_PORT;
		while(in_array($port,$used_ports) and $port<INSTANCE_MAX_PORT) $port++;
		if($port>INSTANCE_MAX_PORT) die(sprintf("Could not allocate a port to instance %s, region %s!\n",$instance,$region));
		if($debug) printf("- Allocated new port %d to instance %s, region %s\n",$port,$instance,$region);
	}

	if(isset($args['uuid'])) {
		$uuid='';
		if(!try_parse_uuid($args['uuid'],$uuid)) die("UUID must be in the format xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx!\n");
		if(in_array($uuid,$used_uuids)) die("That UUID is already in use by another region!\n");
	} else {
		$uuid=create_uuid();
		while(in_array($uuid,$used_uuids)) $uuid=create_uuid();
		if($debug) printf("- Allocated new UUID %s to instance %s, region %s\n",$uuid,$instance,$region);
	}

	$size=256;
	if(isset($args['size'])) {
		if(!try_parse_size($args['size'],$size)) die("Size must be a multiple of 256!\n");
	} else {
		if($debug) print "- Setting region size to 256 (256x256m)\n";
	}

	// now create (or add to) the basic region config
	make_instance_directories($instance);
	$rconfig=sprintf(CONFIGS_DIR,$instance).'Regions/Regions.ini';
	if($debug) printf("- Reading config file: %s\n",$rconfig);
	$rini=parse_ini($rconfig, true, INI_SCANNER_RAW) or array();
	$rini[$region]=array('Location'=>'"'.$location.'"','RegionUUID'=>$uuid,'InternalPort'=>$port,'SizeX'=>$size,'SizeY'=>$size);
	if(!write_ini($rconfig,$rini)) die("ERROR: Could not write ini file $rconfig !\n");
	if($debug) printf("* Updated Region config %s.\n",$rconfig);
	printf("Region %s was created in Instance %s with UUID %s on port %d.\n",$region,$instance,$uuid,$port);


	//so the instance configs have changed. Signal to os_runner.sh.php if running to reload the .runlist
	// make sure that the os_runner daemon is running
	//if(file_exists($pidfile)) {
	//	$pid=trim(file_get_contents($pidfile));
	//	if(file_exists("/proc/${pid}")) posix_kill($pid, SIGHUP);
	//}


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
	$config_set='';
	foreach($instances as $inst) {
		$info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list,$config_set);
		if($found=find_region_by_uuid($info,$uuid)) break;
	}
	if(!$found)	die("That region UUID could not be found in any instance!\n");
	$rl=read_text_file_to_array_with_lock($runlist,LOCK_EX);
	$cstatus=get_instance_status($rl,$inst,$base_port[$inst]);
	if($cstatus!='stopped' and $cstatus!='disabled' and $cstatus!='broken') die("This instance must be stopped or disabled before you can rename a region!\n");
	if($found['RegionName']==$rregion) die("The new region name is the same as the old one!\n");
	$rconfig=sprintf(CONFIGS_DIR,$inst).'Regions/Regions.ini';
	if($debug) printf("- Reading config file: %s\n",$rconfig);
	$rini=parse_ini($rconfig, true, INI_SCANNER_RAW) or array();
	$rini[$rregion]=$rini[$found['RegionName']];
	unset($rini[$found['RegionName']]);
	//print_r($rini);
	if(!write_ini($rconfig,$rini)) die("ERROR: Could not write ini file $rconfig !\n");
	printf("* Updated Region config %s.\n",$rconfig);
}


//*********************************************************************************************START RESTART STOP STATUS VIEW VISITORS****
$start=0; $restart=0; $stop=0; $status=0; $view=0; $config=0; $visitors=0; $manual=0; $disable=0; $enable=0;
if(isset($args['start'])) {
	if($args['start']==1) $start=$instances; else $start=explode(',',str_replace('"','',trim($args['start'])));
	if(isset($args['manual'])) $manual=1;
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
if(isset($args['disable'])) {
	if($args['disable']==1) $disable=$instances; else $disable=explode(',',str_replace('"','',trim($args['disable'])));
}
if(isset($args['enable'])) {
	if($args['enable']==1) $enable=$instances; else $enable=explode(',',str_replace('"','',trim($args['enable'])));
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
	$config_set='';
	foreach($instances as $inst) {
		$info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list,$config_set);
	}

	// make sure that the os_runner daemon is running
	if(file_exists($pidfile)) {
		$pid=trim(file_get_contents($pidfile));
		if(!file_exists("/proc/${pid}")) @unlink($pidfile);
		else posix_kill($pid, SIGHUP); //trigger .runlist reload
	}
	if(!file_exists($pidfile)) {
		@mkdir(OS_RUNNER_LOG_DIR);
		$cmd=OS_RUNNER.' -d 2>/dev/null >>'.OS_RUNNER_LOG.' &';
		if($debug) printf("Running %s\n",$cmd);
		`exec $cmd`;
	}

	$stopped_instances=array(); //keep a list of which instances we successfully stopped in case we're restarting
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
				$stopped_instances[]=$inst;
				break;
			}
			if($cstatus=='started') {
				if(isset($args['f']) or isset($args['force'])) $rl[$i]=sprintf("%s\t%s\t%s\t%s",$inst,$rs,$base_port[$inst],'kill');
				else $rl[$i]=sprintf("%s\t%s\t%s\t%s",$inst,$rs,$base_port[$inst],'stop');
				sort($rl);
				write_text_file_from_array_with_lock($runlist,$rl,LOCK_EX);
			}
			if($cstatus=='start') {
				if(isset($args['r']) or isset($args['reset'])) {
					$cstatus='stopped';
					$rl[$i]=sprintf("%s\t%s\t%s\t%s",$inst,$rs,$base_port[$inst],'stopped');
					sort($rl);
					write_text_file_from_array_with_lock($runlist,$rl,LOCK_EX);
					print "\r\t\t\t\t\t\t[OK] Instance was reset to stopped state!\n";
					break;
				} else {
					print "\r\t\t\t\t\t\t[FAILED] Instance is still starting!\n";
					break;
				}
			}
			if($cstatus=='disabled') {
				print "\r\t\t\t\t\t\t[FAILED] Instance is disabled! Use --enable to re-enable this instance.\n";
				break;
			}
			if($cstatus=='stop' or $cstatus=='broken') {
				if(isset($args['r']) or isset($args['reset'])) {
					$cstatus='stopped';
					$rl[$i]=sprintf("%s\t%s\t%s\t%s",$inst,$rs,$base_port[$inst],'stopped');
					sort($rl);
					write_text_file_from_array_with_lock($runlist,$rl,LOCK_EX);
					print "\r\t\t\t\t\t\t[OK] Instance was reset to stopped state!\n";
					break;
				}
			}
			sleep(2);
			$timer+=2;
			print ".";
			if($timer>=TIMEOUT) {
				if(isset($args['f']) or isset($args['force'])) { //asked for a forced stop so bang it hard now
					$pidpath=sprintf(LOGS_DIR,$inst).'OpenSim.pid';
					if(file_exists($pidpath)) {
						$instpid=trim(file_get_contents($pidpath));
						if(file_exists("/proc/${instpid}")) {
							if($debug) printf("Sending process %s a hard kill signal...\n",$instpid);
							posix_kill($instpid, SIGKILL); //hard kill the process
							sleep(1);
							$cstatus='stopped';
							$rl[$i]=sprintf("%s\t%s\t%s\t%s",$inst,$rs,$base_port[$inst],'stopped');
							sort($rl);
							write_text_file_from_array_with_lock($runlist,$rl,LOCK_EX);
						} else {
							printf("\r\t\t\t\t\t\t[FAILED] Instance did not stop within %s seconds!\n",TIMEOUT);
							break;
						}
					} else {
						printf("\r\t\t\t\t\t\t[FAILED] Instance did not stop within %s seconds!\n",TIMEOUT);
						break;
					}
				} else {
					printf("\r\t\t\t\t\t\t[FAILED] Instance did not stop within %s seconds!\n",TIMEOUT);
					break;
				}
			}
		}
	}

	if($restart) {
		$start=$stopped_instances; 	//only restart stopped instances
		sleep(5);										//and wait a few seconds between stopping and starting
	}
}
//****************************************************************************************************CLEAN UP****
//check and see if all instances are stopped, if so we can kill the os_runner daemon
//we do this even if part of a --restart as os_runner.sh.php might have been updated
//and we want always be using latest code.
if(!$manual) {
	$rl=read_text_file_to_array_with_lock($runlist,LOCK_EX);
	$stopped=0;
	for($i=0;$i<count($rl);$i++) {
		if(substr($rl[$i],-7)=='stopped' or substr($rl[$i],-8)=='disabled') $stopped++;
	}
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
	$config_set='';
	foreach($instances as $inst) {
		$info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list,$config_set);
	}

	// make sure that the os_runner daemon is running if we're not running in manual mode
	if(!$manual) {
		if(file_exists($pidfile)) {
			$pid=trim(file_get_contents($pidfile));
			if(!file_exists("/proc/${pid}")) @unlink($pidfile);
			else posix_kill($pid, SIGHUP); //trigger .runlist reload
		}
		if(!file_exists($pidfile)) {
			@mkdir(OS_RUNNER_LOG_DIR);
			$cmd=OS_RUNNER.' -d 2>/dev/null >>'.OS_RUNNER_LOG.' &';
			if($debug) printf("Running %s\n",$cmd);
			`exec $cmd`;
		}
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

		//$inifiles=array(BASE_CONFIGS."OpenSimDefaults.ini",
		//								BASE_CONFIGS."OpenSim.ini",
		//								BASE_CONFIGS."config-include/GridHypergrid.ini",
		//								BASE_CONFIGS."config-include/GridCommon.ini",
		//								BASE_CONFIGS."config-include/FlotsamCache.ini");

		$cs=get_instance_config_set($inst);
		foreach($osconfigs[$cs] as $config_dst=>$config_src) {
			$inifiles[]=CONFIG_SETS."${cs}/${config_dst}";
		}

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

		$dbs=dbsql2array3("show databases");
		if(!is_array($dbs) or !in_array(INSTANCE_DB_PREFIX.$rs,$dbs)) {
			// Make sure we have a database to work in!
			if($debug) printf("- Creating (if not exists) a region database: %s%s\n",INSTANCE_DB_PREFIX,$rs);
			dbquery(sprintf("create database if not exists %s%s default character set utf8 collate utf8_unicode_ci",INSTANCE_DB_PREFIX,$rs));
			$manual=1; //first run do manually as we will need to add the instances region(s) to an estate
		}


		// Make sure some files and paths are present!
		if(!file_exists($ini['Startup']['shutdown_console_commands_file'])) write_text_file($ini['Startup']['shutdown_console_commands_file'],"; Lines that start with ; are comments.");
		if(!file_exists($ini['Startup']['startup_console_commands_file'])) write_text_file($ini['Startup']['startup_console_commands_file'],"; Lines that start with ; are comments.");
		if(!file_exists($ini['XEngine']['ScriptEnginesPath'])) mkdir($ini['XEngine']['ScriptEnginesPath']);

		// move old logs to a dated file?

		// Now tell the runner to launch the instance

		if($errors===0) {

			if($manual) {

				printf("Manually Starting Instance: %s\n",$inst);
				$inipath=sprintf(OUT_CONF_DIR,$inst);
				$pidpath=sprintf(LOGS_DIR,$inst).'OpenSim.pid';
				$oscmd=sprintf("%s \"%s\"\n",OS_EXEC,$inipath);
				print "\nI am about to run this command in a tmux window:\n$oscmd\n\n";
				$tsn="Running";
				$tn=$inst." in manual mode. Type shutdown to exit";
				write_text_file($tmuxfile,"set-option -g prefix ".TMUX_CONTROL_PREFIX."\nset-option -g history-limit ".TMUX_SCROLL_BUFFER."\n");

				$c=5;
				while($c) {
					print "$c...";
					$c--;
					sleep(1);
				}
				print "\n";

				$cmd="tmux new-session -s '".$tsn."' -n '".$tn."' -x 132 -y 50 '".$oscmd."'";
				`$cmd`;
				printf("Manually Ended Instance: %s\n",$inst);

			} else {

				printf("Starting Instance: %s",$inst);
				$entry=sprintf("%s\t%s\t%s",$inst,$rs,$base_port[$inst]);
				$inipath=sprintf(OUT_CONF_DIR,$inst);
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
							printf("\tor to try a manual unmanaged start you can invoke the instance with 'mios --start %s --manual' or  %s \"%s\"\n",$inst,OS_EXEC,$inipath);
							break;
						}
					}
					if($cstatus=='disabled') {
						print "\r\t\t\t\t\t\t[FAILED] Instance is disabled! Use --enable to re-enable this instance.\n";
						break;
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
						printf("\tTo try a manual unmanaged start you can invoke the instance with 'mios --start %s --manual' or: %s \"%s\"\n",$inst,OS_EXEC,$inipath);
						break;
					}
					sleep(2);
					$timer+=2;
					print ".";
					if($timer>=TIMEOUT) {
						printf("\r\t\t\t\t\t\t[FAILED] Instance did not start within %s seconds!\n",TIMEOUT);
						printf("\tTo try a manual unmanaged start you can invoke the instance with 'mios --start %s --manual' or %s \"%s\"\n",$inst,OS_EXEC,$inipath);
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
	$config_set='';
	foreach($instances as $inst) {
		$info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list,$config_set);
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
	$config_set='';
	foreach($instances as $inst) {
		$info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list,$config_set);
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
		$config_set='';
		foreach($instances as $inst) {
			$info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list,$config_set);
		}
		$index=$base_port[$instances[0]];
	} else $index=0;

	`tmux select-window -t $tsn:$index; tmux attach-session -t $tsn`;
}
//****************************************************************************************************SHOW VISITORS TO INSTANCE(S)****
if(is_array($visitors)) {
	foreach($visitors as $vi) if(!in_array($vi,$instances)) die(sprintf("An instance named '%s' was not found! Use --inst to show possible instance names.\n",$vi));
	$instances=$visitors;

	if(isset($args['csv'])) {
		$csv=1;
		print "DateTime,Instance,Region,Avatar,UUID,Presence\n";
	} else $csv=0;

	foreach($instances as $inst) {
		$logpath=sprintf(LOGS_DIR,$inst).'OpenSim.log';
		if($debug) printf("Checking log: %s\n",$logpath);
		if(!$csv) printf("******** Visitor Log for Instance: '%s' ********\n",$inst);
		$visits=array();

		/* old way, prone to failure if log format changes
		$cmd=sprintf('cat %s |awk \'/\\[SCENE\\]: Found presence/{print $1 " " $2 " " $17 " " $11 " " $12 " " $13}\' >/tmp/visitors.txt',$logpath);
		if($debug) printf("Running: %s\n",$cmd);
		`$cmd`;
		$cmd=sprintf('cat %s |awk \'/\\[SCENE\\]: Region .+ authenticated and authorized incoming root agent/{print $1 " " $2 " " $10 " " $17 " " $18 " " $19}\' >>/tmp/visitors.txt',$logpath);
		if($debug) printf("Running: %s\n",$cmd);
		`$cmd`;
		$cmd="cat /tmp/visitors.txt |sort";
		passthru($cmd);
		print "\n"; */

		//new way uses preg_match
		if($fp=@fopen($logpath,'r')) {
			while(!feof($fp)) {
				$line=trim(fgets($fp));
				//match rule #1
				if(preg_match("/([0-9\-]{10}\ [0-9:,]{12}).*?\[SCENE\]:\ Region\ (.*?)\ authenticated\ .*?(root|child)\ agent\ ([^\ ]+)\ ([^\ ]+)\ ([0-9a-f\-]{36})/",$line,$m)) {
					$visits[$m[1]]=array('Instance'=>$inst,'Region'=>$m[2],'Presence'=>$m[3],'AvatarFirst'=>$m[4],'AvatarLast'=>$m[5],
															 'Avatar'=>$m[4].' '.str_replace(' ','',$m[5]),'UUID'=>$m[6],'Rule'=>'#1');
				}
				//match rule #2
				//if(preg_match("/([0-9\-]{10}\ [0-9:,]{12}).*?\[SCENE\]:\ Found\ presence\ ([^\ ]+)\ ([^\ ]+)\ ([0-9a-f\-]{36})\ as\ root\ in\ (.*?)\ after/",$line,$m)) {
				//	$visits[$m[1]]=array('Region'=>$m[5],'AvatarFirst'=>$m[2],'AvatarLast'=>$m[3],'UUID'=>$m[4],'Rule'=>'#2');
				//}
			}
			@fclose($fp);
			ksort($visits);
			foreach($visits as $k=>$v) {
				//printf("%s\t%s\t%s\t%s\t%s\t%s\n",$k,$v['Region'],$v['AvatarFirst'],$v['AvatarLast'],$v['UUID'],$v['Rule']);
				if($csv) printf("%s,%s,%s,%s,%s,%s\n",$k,$v['Instance'],$v['Region'],$v['Avatar'],$v['UUID'],$v['Presence']);
				else printf("%s %s %s %s %s\n",pad_clip_string($k,23),pad_clip_string($v['Region'],17),pad_clip_string($v['Avatar'],48),pad_clip_string($v['UUID'],36),$v['Presence']);
			}
		}
	}
}
//****************************************************************************************************DISABLE INSTANCE(S)****
if(is_array($disable)) {
	foreach($disable as $di) if(!in_array($di,$instances)) die(sprintf("An instance named '%s' was not found! Use --inst to show possible instance names.\n",$di));
	$instances=$disable;
	$used_ports=array();
	$used_uuids=array();
	$base_port=array();
	$regions_list=array();
	$config_set='';
	foreach($instances as $inst) {
		$info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list,$config_set);
	}
	foreach($instances as $inst) {
		$rs=str_replace(" ","_",$inst); //replace spaces with _ in instance names for when we create the database and tmux windows
		printf("Disabling Instance: %s",$inst);
		$entry=sprintf("%s\t%s\t%s\t",$inst,$rs,$base_port[$inst]);
		$cstatus='stopped'; //default if no entry found

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
				print "\r\t\t\t\t\t\t[FAILED] Instance appears to be broken! Use -f to force it to disabled.\n";
			}
		}
		if($cstatus=='disabled') {
			print "\r\t\t\t\t\t\t[FAILED] Instance is already disabled! Use --enable to re-enable this instance.\n";
		}
		if($cstatus=='started') {
			print "\r\t\t\t\t\t\t[FAILED] Instance cannot be disabled while it is running! please --stop the instance first.\n";
		}
		if($cstatus=='stopped') {
			$rl[$i]=sprintf("%s\t%s\t%s\t%s",$inst,$rs,$base_port[$inst],'disabled');
			sort($rl);
			write_text_file_from_array_with_lock($runlist,$rl,LOCK_EX);
			print "\r\t\t\t\t\t\t[  OK  ]\n";
		}
	}
}
//****************************************************************************************************ENABLE INSTANCE(S)****
if(is_array($enable)) {
	foreach($enable as $en) if(!in_array($en,$instances)) die(sprintf("An instance named '%s' was not found! Use --inst to show possible instance names.\n",$di));
	$instances=$enable;
	$used_ports=array();
	$used_uuids=array();
	$base_port=array();
	$regions_list=array();
	$config_set='';
	foreach($instances as $inst) {
		$info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list,$config_set);
	}
	foreach($instances as $inst) {
		$rs=str_replace(" ","_",$inst); //replace spaces with _ in instance names for when we create the database and tmux windows
		printf("Enabling Instance: %s",$inst);
		$entry=sprintf("%s\t%s\t%s\t",$inst,$rs,$base_port[$inst]);
		$cstatus='stopped'; //default if no entry found

		$rl=read_text_file_to_array_with_lock($runlist,LOCK_EX);
		for($i=0;$i<count($rl);$i++) {
			if(substr($rl[$i],0,strlen($entry))==$entry) {
				$cstatus=trim(substr($rl[$i],strlen($entry)));
				if($cstatus=='') $cstatus='stopped';
				break;
			}
		}
		if($cstatus=='disabled') {
			$rl[$i]=sprintf("%s\t%s\t%s\t%s",$inst,$rs,$base_port[$inst],'stopped');
			sort($rl);
			write_text_file_from_array_with_lock($runlist,$rl,LOCK_EX);
			print "\r\t\t\t\t\t\t[  OK  ]\n";
		}	else {
			print "\r\t\t\t\t\t\t[FAILED] Instance is already enabled!\n";
		}
	}
}

//****************************************************************************************************STATS****

if(isset($args['init-stats'])) {
	//set up crontab for the user
	$cronlines =sprintf("PATH=/sbin:/bin:/usr/sbin:/usr/bin:%s\n",MIOS_DIR);
	$cronlines.=sprintf("MAILTO=%s\n",MAIL_TO);
	$cronlines.=sprintf("HOME=%s\n",HOME_DIR);
	$cronlines.=sprintf("* * * * * %smios.sh.php --get-stats 2>&1 >>%smios_cron.log\n",MIOS_DIR,OS_RUNNER_LOG_DIR);
	if(defined('AUTO_RESTART_INSTANCES')) {
		$cronlines.=sprintf("%s %smios.sh.php --restart --force 2>&1 >>%smios_cron.log\n",AUTO_RESTART_INSTANCES,MIOS_DIR,OS_RUNNER_LOG_DIR);
	}
	$crontab=BASE_CONFIGS.'mios_crontab.txt';
	if($fp=@fopen($crontab,'wb')) {
		fwrite($fp,$cronlines);
		fclose($fp);
	}
	`crontab -r`;
	`crontab $crontab`;
	$args['get-stats']=1;
}

if(isset($args['clear-stats'])) {
	if($args['clear-stats']==1) $inst=$instances; else $inst=explode(',',str_replace('"','',trim($args['clear-stats'])));
	foreach($inst as $i) if(!in_array($i,$instances)) die(sprintf("An instance named '%s' was not found! Use --inst to show possible instance names.\n",$i));
	foreach($inst as $i) {
		$rrd_base=sprintf(STATS_DIR,$i);
		printf("%s Removing all Statistics for Instance: '%s'\t\t",date('Y-m-d H:i:s'),$i);
		$cmd="cd ${rrd_base} 2>/dev/null && rm -f *.rrd 2>/dev/null";
		`$cmd`;
		print "[  OK  ]\n";
	}
	$args['get-stats']=$args['clear-stats'];
}

if(isset($args['get-stats'])) {

	if($args['get-stats']==1) $inst=$instances; else $inst=explode(',',str_replace('"','',trim($args['get-stats'])));

	foreach($inst as $i) if(!in_array($i,$instances)) die(sprintf("An instance named '%s' was not found! Use --inst to show possible instance names.\n",$i));

	$used_ports=array();
	$used_uuids=array();
	$base_port=array();
	$regions_list=array();
	$config_set='';
	foreach($instances as $inst) {
		$info=enum_instance($inst,$used_ports,$used_uuids,$base_port,$regions_list,$config_set);
	}
	$rl=read_text_file_to_array_with_lock($runlist,LOCK_EX);
	$interfaces=get_interfaces();
	$myip=$interfaces['eth0']['ipv4'];

	//stats definitions ini files
	$inis=array();
	$stats_path=STATS_CONFIGS.'*.ini';
	$stats_inis=explode("\n", trim(`ls -1 {$stats_path} 2>/dev/null`));
	foreach($stats_inis as $si) {
		if($si!='') {
			if($debug) printf("- Reading config file: %s\n",$si);
			$inis[]=parse_ini($si, true, INI_SCANNER_RAW) or array();
		}
	}
	$stats_ini=ini_merge($inis);

	$rrd_date=time();
	$rrd_data=array();

	foreach($instances as $inst) {
		$rs=str_replace(" ","_",$inst); //replace spaces with _ in instance names for when we create the database and tmux windows
		$entry=sprintf("%s\t%s\t%s\t",$inst,$rs,$base_port[$inst]);
		$cstatus='stopped';
		for($i=0;$i<count($rl);$i++) {
			if(substr($rl[$i],0,strlen($entry))==$entry) {
				$cstatus=trim(substr($rl[$i],strlen($entry)));
				if($cstatus=='') $cstatus='stopped';
				break;
			}
		}

		if($cstatus=='started') {
			$c_inipath=sprintf(OUT_CONF_DIR,$inst).'combined.ini';
			$ini=parse_ini($c_inipath, true, INI_SCANNER_RAW) or array();
			if(isset($ini['Startup']['ManagedStatsRemoteFetchURI'])) {
				$url=sprintf('http://%s:%s/%s/',$myip,$base_port[$inst],$ini['Startup']['ManagedStatsRemoteFetchURI']);
				printf("%s Getting Stats for Instance '%s'\t\t",date('Y-m-d H:i:s'),$inst);
				$json_stats=get_data_from_url($url);

				if(substr($json_stats,0,2)=='{"') {

					print "[  OK  ]\n";
					if(isset($args['show'])) {
						printf("Statistics for Instance: '%s' on url '%s'\n",$inst,$url);
						if(isset($args['json'])) print $json_stats."\n";
						else print array_visualise(json_decode($json_stats,true));
					} else {

						$stats=json_decode($json_stats,true);

						//create rrd database if needed and populate them with data
						@mkdir(sprintf(STATS_DIR,$inst));
						$rrd_base=sprintf(STATS_DIR,$inst);
						$gc=0;
						foreach($stats_ini as $h=>$d) {
							if(!isset($d['DataSets'])) {
								printf("Warning - Stats definition '%s' has no datasets defined!\n",$h);
								continue;
							}
							$ds=$d['DataSets'];
							$has_region_stats=0;
							$has_interface_stats=0;

							for($i=1;$i<=$ds;$i++) {
								if(isset($d["Data${i}"])) {
									if(strpos($d["Data${i}"],'${RegionName}')!==false) $has_region_stats=1;
									if(strpos($d["Data${i}"],'${Interface}')!==false) $has_interface_stats=1;
								}
							}
							$base_env=sprintf("\$BasePort='%s'; \$InventoryServerURI='%s';",$base_port[$inst],$ini['InventoryService']['InventoryServerURI']);
							$rrd_graph=array();
							if($has_region_stats) {
								$sgc=1;
								foreach($regions_list[$inst] as $region) {
									$rrd_file=sprintf("%s%s_%s.rrd",$rrd_base,$used_uuids[$inst.'/'.$region],preg_replace(array("/[^A-Z0-9\ ]/i","/[\ ]/"),array("","_"),$h));
									if(!file_exists($rrd_file) or isset($args['init-stats'])) {
										$data=create_rrd_db($rrd_file,$rrd_date,$d);
									} else {
										$env=$base_env.sprintf("\$RegionName='%s'; ",$region);
										$data=update_rrd_db($rrd_file,$rrd_date,$d,$stats,$env);
									}
									$rrd_graph["Graph${sgc}DataFile"]=$rrd_file;
									$rrd_graph["Graph${sgc}Heading"]=$h;
									$rrd_graph["Graph${sgc}DataUnits"]=$d['DataUnits'];
									$rrd_graph["Graph${sgc}Instance"]=$inst;
									$rrd_graph["Graph${sgc}LastTimeStamp"]=$rrd_date;
									$rrd_graph["Graph${sgc}Region"]=$region;
									$rrd_graph["Graph${sgc}RegionUUID"]=$used_uuids[$inst.'/'.$region];
									for($i=1;$i<=$ds;$i++) {
										$rrd_graph["Graph${sgc}DataSource${i}"]="DS${i}";
										$rrd_graph["Graph${sgc}DataLabel${i}"]=$d["Data${i}Label"];
										$rrd_graph["Graph${sgc}DataLastValue${i}"]=$data[$i];
									}
									$sgc++;
								}
							} elseif($has_interface_stats) {
								$sgc=1;
								foreach($interfaces as $interface=>$idata) {
									$rrd_file=sprintf("%s%s_%s.rrd",$rrd_base,$interface,preg_replace(array("/[^A-Z0-9\ ]/i","/[\ ]/"),array("","_"),$h));
									if(!file_exists($rrd_file) or isset($args['init-stats'])) {
										$data=create_rrd_db($rrd_file,$rrd_date,$d);
									} else {
										$env=$base_env.sprintf("\$Interface='%s'; ",$interface);
										$data=update_rrd_db($rrd_file,$rrd_date,$d,$stats,$env);
									}
									$rrd_graph["Graph${sgc}DataFile"]=$rrd_file;
									$rrd_graph["Graph${sgc}Heading"]=$h;
									$rrd_graph["Graph${sgc}DataUnits"]=$d['DataUnits'];
									$rrd_graph["Graph${sgc}Instance"]=$inst;
									$rrd_graph["Graph${sgc}LastTimeStamp"]=$rrd_date;
									$rrd_graph["Graph${sgc}Interface"]=$interface;
									for($i=1;$i<=$ds;$i++) {
										$rrd_graph["Graph${sgc}DataSource${i}"]="DS${i}";
										$rrd_graph["Graph${sgc}DataLabel${i}"]=$d["Data${i}Label"];
										$rrd_graph["Graph${sgc}DataLastValue${i}"]=$data[$i];
									}
									$sgc++;
								}
							} else {
								$rrd_file=sprintf("%s%s.rrd",$rrd_base,preg_replace(array("/[^A-Z0-9\ ]/i","/[\ ]/"),array("","_"),$h));
								if(!file_exists($rrd_file) or isset($args['init-stats'])) {
									$data=create_rrd_db($rrd_file,$rrd_date,$d);
								} else {
									$env=$base_env;
									$data=update_rrd_db($rrd_file,$rrd_date,$d,$stats,$env);
								}
								$rrd_graph["Graph1DataFile"]=$rrd_file;
								$rrd_graph["Graph1Heading"]=$h;
								$rrd_graph["Graph1DataUnits"]=$d['DataUnits'];
								$rrd_graph["Graph1Instance"]=$inst;
								$rrd_graph["Graph1LastTimeStamp"]=$rrd_date;
								for($i=1;$i<=$ds;$i++) {
									$rrd_graph["Graph1DataSource${i}"]="DS${i}";
									$rrd_graph["Graph1DataLabel${i}"]=$d["Data${i}Label"];
									$rrd_graph["Graph1DataLastValue${i}"]=$data[$i];
								}
							}
							$rrd_data["${inst}:${gc}"]=$rrd_graph;
							$gc++;
						}
					}
				} else print "[FAILED]\n";
			} else if($debug) printf("Instance '%s' is not configured to provide stats! Please set ManagedStatsRemoteFetchURI in [Startup] section of .ini\n",$inst);
		} else if($debug) printf("Instance '%s' must be started in order to provide stats!\n",$inst);
	}
	write_ini(STATS_CONFIGS.'.Graphs.ini',$rrd_data,'DO NOT EDIT THIS FILE! It is created automatically for the graphing tools.');
}
//****************************************************************************************************CLEAN UP****
//check and see if all instances are stopped, if so we can kill the os_runner daemon
if(!$manual) {
	$rl=read_text_file_to_array_with_lock($runlist,LOCK_EX);
	$stopped=0;
	for($i=0;$i<count($rl);$i++) {
		if(substr($rl[$i],-7)=='stopped' or substr($rl[$i],-8)=='disabled') $stopped++;
	}
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
//****************************************************************************************************USAGE****
function usage() {
	return "
mios [--option|--option[=]value]

Creates and manages OpenSimulator Instances and Regions. It allows you to run
multiple Instances of OpenSim from one binary build of the OpenSim code.
Each Instance can run multiple Regions if necessary.

The options that you give to this script determine the actions taken.
The values, if provided must be enclosed in quotes (\") if the value contains
spaces.

**--------------------------------**
**Install/Update and build options**
**--------------------------------**

--os-update
           Install or Update the OpenSim binaries using the latest dev code
           from git master. The version is built in release mode. For an initial
           install this command is equivalent to:

           cd ~; git clone ".OPENSIM_GIT_REPOSITORY." &&
             ./runprebuild.sh autoclean && ./runprebuild.sh vs2010 &&
             xbuild /p:Configuration=Release

           Or for an update:

           cd ~/opensim; git pull && xbuild /t:clean &&
             ./runprebuild.sh autoclean && ./runprebuild.sh vs2010 &&
             xbuild /p:Configuration=Release

--rebuild
           Same as above except don't attempt to update from git, just rebuild
           the project and recompile. Equivalent to:

           cd ~; ./runprebuild.sh autoclean && ./runprebuild.sh vs2010 &&
             xbuild /p:Configuration=Release

--recompile
           Same as above except don't attempt to update from git, just do the
           recompile step. Equivalent to:

           cd ~; xbuild /p:Configuration=Release;

--os-config [name[,name[,name]]]...
           Downloads configs from the web for a named grid, where the filenames
           and web urls are listed in the MIOS configuration, *OR* copy files
           from another directory. See the config.ini.example file for example
           OSGrid and standalone config source definitions.

--init-mysql
           Add the two users to Mysql that are required to manage and run
           Opensim Instances. The users are: 'opensim' which has full access
           to each Instance database and the Estate database, and
           'opensim_admin' which is used to create the Instance databases when
           an Instance is added using this script. The host, user and password
           details are taken from the config file:
           ".BASE_CONFIGS."config.inc.php

**--------------------------**
**Managing OpenSim Instances**
**--------------------------**

--inst     Displays a quick list of all the configured Instances. Each Instance
           has a unique name which is used to identify it.

--list     Displays a detailed table of all the configured Instances and the
           Regions that each Instance is running. It also shows each Instance's
           status, e.g. running, stopped, unconfigured etc.

--list [InstanceName[,InstanceName]...]
           Displays a detailed table of the specified single Instance or the
           comma separated list of Instances. As above it shows the Regions
           that they are running. It also shows each Instance's status, e.g.
           running, stopped, unconfigured etc.


--add-instance InstanceName
           Creates a new Instance, that is it creates an new directory called
           'InstanceName' and fills that directory with the base configs that
           are required to control that Instance. You will need to add at least
           one Region to the Instance in order to be able to start and stop the
           Instance.
     [--config-set ConfigSetName] Specifies which set of *base* configs we will
           use for this Instance. These can be grid or standalone config sets
           and the sources for those configs are defined in config.inc.php.

--del-instance InstanceName
           Deletes an Instance by removing all of its configs and it's
           associated database. All Regions on that Instance will be destroyed!
           An Instance can only be deleted if it is first stopped and also set
           to disabled.

--start [InstanceName[,InstanceName]...] [--manual]
           Attempt to start all or just the named Instances. If an Instance for
           some reason does not start in a timely manner, it will show [FAILED].
           If --manual is specified then the Instance will be started manually
           in a Tmux window and will run in interactive mode until shutdown
           from the OpenSim console.

--stop [InstanceName[,InstanceName]...]
           Attempt to stop all or just the named Instances. If an Instance for
           some reason does not stop in a timely manner, it will show [FAILED].
           If --force is specified too, then the Instance process will be killed
           if it fails to shutdown cleanly.

--restart [InstanceName[,InstanceName]...]
           Attempt to restart all or just the named Instances. If an Instance
           for some reason does not stop or start in a timely manner, it will
           show as [FAILED]. As above, if --force is specified too, then the
           Instance process will be killed if it fails to shutdown cleanly,
           before it is restarted.

--status [InstanceName[,InstanceName]...]
           Shows the running status of all or just the named Instances.

--view [InstanceName]
           Switch to the console display and optionally select the window for
           the Instance InstanceName. The console display(s) run in Tmux, and
           the usual Tmux keys, prefixed with CTRL-a are used to switch panes,
           scroll and exit etc.

--visitors [InstanceName[,InstanceName]...]
           Scan the logs and show the visitors that have arrived at the Regions
           hosted by the named Instances.
  [--csv]  Display the results in comma separated format for a spreadsheet.

--config [InstanceName[,InstanceName]...]
           Show the running config of all or just the named Instances. An
           Instance must have been previously started for the config to exist.
  [--section Name[,Name]...] View only a particular section or sections of the
                             configuration.

--disable [InstanceName[,InstanceName]...]
           Attempt to disable all or just the named Instances. An Instance
           must be stopped before you can disable it, otherwise the operation
           will show as [FAILED]. Disabled Instances will never be started by
           MIOS.

--enable [InstanceName[,InstanceName]...]
           Attempt to enable all or just the named Instances. An Instance
           must be disabled before you can enable it, otherwise the operation
           will show as [FAILED].

--init-stats [InstanceName[,InstanceName]...]
           Initiase MIOS to collect stats. A crontab entry will be created for
           the current user and MIOS will be called with the --get-stats option
           every minute. Data is stored in a number of RRD databases per
           Instance, which are created automatically.

--get-stats [InstanceName[,InstanceName]...]
           Normally called via a crontab entry every minute. Updates the RRD
           databases with OpenSim statistics.
  [--show [--json]] Instead of updating the RRD database, just show the data
                    collected in a readable format, or in a json format.

--clear-stats [InstanceName[,InstanceName]...]
           Remove and recreate the RRD database files for the named Instance(s).
           Effectively clears all the past statistics and starts again.

If an Instance crashes while running, it will be attempted to be restarted.
If it starts and then dies within ".MAX_RESTART_TIME_INTERVAL." seconds then after ".MAX_RESTART_COUNT." times of trying,
the Instance will be marked as broken.

**-----------------------------------------**
**Managing Regions within OpenSim Instances**
**-----------------------------------------**

--add-region RegionName
           Required parameters:
  --instance InstanceName   The Instance name that the Region will be added to.
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

MIOS is written by Jak Daniels - jak@ateb.co.uk
";
}

?>
