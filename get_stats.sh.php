#!/usr/bin/php -q
<?php
error_reporting (E_ALL);
include('php_inc/os_defines.inc.php');

if(file_exists(BASE_CONFIGS.'config.inc.php')) include(BASE_CONFIGS.'config.inc.php');
else die("You must create a config file '".BASE_CONFIGS."config.inc.php' before you can use this script!\nPlease use '".BASE_CONFIGS."config.inc.php.example' as a template .\n");
include('php_inc/dbfunctions.inc.php');
include('php_inc/functions.inc.php');
include('php_inc/os_functions.inc.php');

ob_implicit_flush ();
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

$instances=enum_instances();

if(isset($args['instance'])) {
	if($args['instance']==1) $inst=$instances; else $inst=explode(',',str_replace('"','',trim($args['instances'])));

	foreach($inst as $i) if(!in_array($i,$instances)) die(sprintf("An instance named '%s' was not found! Use --inst to show possible instance names.\n",$i));
	// start by enumerating all the existing region names, uuids and ports,
	// each instance can run one or more regions and if regions are defined without ports and uuids
	// then we add them to Regions.ini now so there are no conflicts later
	$instances=$inst;
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
		if(isset($ini['Startup']['ManagedStatsRemoteFetchURI'])) {
			$url=sprintf('http://%s:%s/%s','127.0.0.1',$base_port[$inst],$ini['Startup']['ManagedStatsRemoteFetchURI']);
			if($fp=@fopen($url,'rb')) {
				$json_stats=fread($fp);
				print "$json_stats\n";
				@fclose($fp);
			}
		} else printf("Instance '%s' is not configured to provide stats! Please set ManagedStatsRemoteFetchURI in [Startup] section of .ini\n");
	}
}
?>
