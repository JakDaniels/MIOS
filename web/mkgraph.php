<?php
error_reporting (E_ALL);

if(isset($_GET['tid'])) {
	$tid=$_GET['tid'];
	if(!preg_match("/^[A-Z0-9\_\-]+$/i",$tid)) imgerror();
}
if(isset($_GET['sid'])) {
	$sid=$_GET['sid'];
	if(!preg_match("/^[0-9]+$/",$sid)) imgerror();
}
if(isset($_GET['sgc'])) {
	$sgc=$_GET['sgc'];
	if(!preg_match("/^[0-9]+$/",$sgc)) imgerror();
}
if(isset($_GET['rrd'])) {
	$rrd=$_GET['rrd'];
	if(!in_array($rrd,array('MIN','5MIN','HOUR','DAY'))) imgerror();
}
if(isset($_GET['debug'])) $debug=1; else $debug=0;

include('php_inc/os_defines.inc.php');

if(file_exists(BASE_CONFIGS.'config.inc.php')) include(BASE_CONFIGS.'config.inc.php');
else die("You must create a config file '".BASE_CONFIGS."config.inc.php' before you can use this script!\nPlease use '".BASE_CONFIGS."config.inc.php.example' as a template .\n");
include('php_inc/functions.inc.php');

$gcol=array('#000000','#000080','#008000','#800000','#008080','#800080','#808000','#c08000','#80c000','#0080c0');

ob_implicit_flush ();
set_time_limit (0);

//graph definitions ini file
$graph_path=STATS_CONFIGS.'.Graphs.ini';
$ini=parse_ini($graph_path, true, INI_SCANNER_RAW) or array();

if(!isset($ini["${tid}:${sid}"])) imgerror();

//$graph=$ini["${tid}:${sid}"];
$graph=str_replace('"','',$ini["${tid}:${sid}"]);

if(!isset($graph["Graph${sgc}DataFile"])) imgerror();
if(!file_exists($graph["Graph${sgc}DataFile"])) imgerror();
$rrdsrcfile=$graph["Graph${sgc}DataFile"];
$t=explode("/",$rrdsrcfile);
//$rrdimgfile='/tmp/'.substr($t[count($t)-1],0,-4).'.png';
$rrdimgfile=GRAPH_DIR.substr($t[count($t)-1],0,-4).'.png';

$rrdtitle=$graph["Graph${sgc}Heading"];
$rrdunits=$graph["Graph${sgc}DataUnits"];

if($rrd=='MIN') $rrddef=RRD_RRA_MIN;
if($rrd=='5MIN') $rrddef=RRD_RRA_5MIN;
if($rrd=='HOUR') $rrddef=RRD_RRA_HOUR;
if($rrd=='DAY') $rrddef=RRD_RRA_DAY;

$t=explode(":",$rrddef);
$rrdend=$graph["Graph${sgc}LastTimeStamp"]+RRD_STAT_UPDATE_INTERVAL;
$rrdstart=$rrdend-($t[0]*RRD_STAT_UPDATE_INTERVAL*$t[1]);

$ds=1;
while(1) {
	if(isset($graph["Graph${sgc}DataSource${ds}"])) {
		$plots[$ds]="DEF:fn${ds}=${rrdsrcfile}:".$graph["Graph${sgc}DataSource${ds}"].":MAX";
		$lines[$ds]="LINE1:fn${ds}".$gcol[$ds].":".$graph["Graph${sgc}DataLabel${ds}"];
		$xport[$ds]="XPORT:fn${ds}:\"".$graph["Graph${sgc}DataLabel${ds}"]."\"";
		$ds++;
	} else break;
}
$ds--;

$rrdoptions=array('--start',$rrdstart,'--end',$rrdend,'--title',$rrdtitle,
									'--width',RRD_GRAPH_WIDTH,'--height',RRD_GRAPH_HEIGHT,'--imgformat','PNG',
									'--interlaced','--lower-limit',0,'--alt-autoscale-max');

if($rrdunits!='') {
	$rrdoptions[]='--vertical-label';
	$rrdoptions[]=$rrdunits;
}
$rrdoptions=array_merge($rrdoptions,$plots,$lines);

if($debug) print "<pre>$rrdimgfile\n".print_r($rrdoptions,1)."</pre>\n";

$g=rrd_graph($rrdimgfile, $rrdoptions);

if($debug) {
	print "<pre>".print_r($g,1)."</pre>\n";


	$rrdoptions=array_merge(array('--start',$rrdstart,'--end',$rrdend),$plots,$xport);
	print "<pre>".print_r($rrdoptions,1)."</pre>\n";
	print "<pre>".print_r(rrd_error(),1)."</pre>\n";
	$rrd=rrd_xport($rrdoptions);
	print "<pre>".print_r($rrd,1)."</pre>\n";
} else {
	output($rrdimgfile);

}
/*	Graph1DataFile = /home/opensim/MIOS/Instances/UbOde256a/Stats/22b732cc-1362-9e61-9342-c148ccbb299a_Scene_Agents.rrd
	Graph1Heading = "Scene: Agents"
	Graph1DataUnits = ""
	Graph1Instance = UbOde256a
	Graph1LastTimeStamp = 1476531901
	Graph1Region = JDTestODE256b
	Graph1RegionUUID = 22b732cc-1362-9e61-9342-c148ccbb299a
	Graph1DataSource1 = DS1
	Graph1DataLabel1 = "Root Agents"
	Graph1DataSource2 = DS2
	Graph1DataLabel2 = "Child Agents"
*/

function output($fname) {
	if($fp=@fopen($fname, 'rb')) {
		header("Content-Type: image/png");
		header("Content-Length: " . filesize($fname));
		fpassthru($fp);
	}
	exit();
}

function imgerror() {
	$fname='img_cache/error.png';
	output($fname);
}

?>
