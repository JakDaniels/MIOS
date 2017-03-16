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
include('php_inc/functions.inc.php');


ob_implicit_flush ();
set_time_limit (0);

//graph definitions ini file
$graph_path=STATS_CONFIGS.'.Graphs.ini';
$ini=parse_ini($graph_path, true, INI_SCANNER_RAW) or array();

//print "<pre>".print_r($ini,1)."</pre>\n";

foreach($ini as $h=>$d) {
	$t=explode(":",$h);
	$inst=$t[0];
	$gc=$t[1];
	$graph[$inst][$gc]=str_replace('"','',$d);
	$categories[$inst]=sprintf("Instance: %s",$inst);
}

$tid=$inst;
$sid=0;

if(isset($_GET['set'])) {
	$set=$_GET['set'];
	if(preg_match("/^[A-Z0-9\_\-]+\:[0-9]+$/i",$set)) {
		$t=explode(":",$set);
		if(in_array($t[0],array_keys($categories))) $tid=$t[0]; else $tid=$inst;
		$sid=$t[1];
	}
}



?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">
<HTML><HEAD><TITLE>MIOS OpenSimulator Statistics</TITLE>
<META content="text/html; charset=iso-8859-1" http-equiv=Content-Type>
<LINK REL="Stylesheet" HREF="styles.css" TYPE="text/css">
<script type="text/javascript" src="jquery_1.4.1.js"></script>
<script type="text/javascript" src="functions.js"></script>
</HEAD>
<BODY class="page_body">

<?php
//print "${tid} ${sid}";

print "<input type=\"hidden\" id=\"tabmem1\" name=\"tid\" value=\"$tid\">\n";
print "<ul class=\"tabs1\">\n";
foreach($categories as $tab_id=>$tab_name) {
	if(isset($tid) and $tab_id==$tid) $tab_class='class="active1"'; else $tab_class='';
	print "<li ${tab_class}><a name=\"tab${tab_id}\" href=\"#tab${tab_id}\">${tab_name}</a></li>\n";
}
print "</ul>\n";

print "<div class=\"tab_container\">\n";
foreach($categories as $tab_id=>$tab_name) {
	if(isset($tid) and $tab_id==$tid) $tab_class='class="tab1_content active1"'; else $tab_class='class="tab1_content"';
	print "<div id=\"tab${tab_id}\" ${tab_class}>\n";

	$sgc=1;
	while(1) {
		if(isset($graph[$tab_id][$sid]["Graph${sgc}Heading"])) {
			$content[$sgc]['heading']='&nbsp;';
			if(isset($graph[$tab_id][$sid]["Graph${sgc}Region"])) $content[$sgc]['heading']=sprintf("Region: %s",$graph[$tab_id][$sid]["Graph${sgc}Region"]);
			if(isset($graph[$tab_id][$sid]["Graph${sgc}Interface"])) $content[$sgc]['heading']=sprintf("Interface: %s",$graph[$tab_id][$sid]["Graph${sgc}Interface"]);
			$c="<table border=0 cellspacing=0 cellpadding=5>";
			$c.=sprintf("<tr><td><img class=\"graph\" src=\"mkgraph.php?tid=%s&amp;sid=%s&amp;sgc=%s&amp;rrd=%s\"></td></tr>",$tab_id,$sid,$sgc,'MIN');
			$c.=sprintf("<tr><td><img class=\"graph\" src=\"mkgraph.php?tid=%s&amp;sid=%s&amp;sgc=%s&amp;rrd=%s\"></td></tr>",$tab_id,$sid,$sgc,'5MIN');
			$c.=sprintf("<tr><td><img class=\"graph\" src=\"mkgraph.php?tid=%s&amp;sid=%s&amp;sgc=%s&amp;rrd=%s\"></td></tr>",$tab_id,$sid,$sgc,'HOUR');
			$c.=sprintf("<tr><td><img class=\"graph\" src=\"mkgraph.php?tid=%s&amp;sid=%s&amp;sgc=%s&amp;rrd=%s\"></td></tr>",$tab_id,$sid,$sgc,'DAY');
			$c.="</table>\n";
			$content[$sgc]['graphs']=$c;
		} else break;
		$sgc++;
	}
	$sgc--;

	print "<table border=1 cellspacing=0 cellpadding=3 class=\"stats_display\">\n";
	$gc=count($graph[$tab_id]);
	for($i=0;$i<$gc;$i++) {
		printf("<tr><td class=\"%s\"><a href=\"?set=%s:%s\">%s</a></td>",($i==$sid?'hilite':'normal'),$tab_id,$i,$graph[$tab_id][$i]['Graph1Heading']);
		if($i==0) {
			for($j=1;$j<=$sgc;$j++) printf("<td rowspan=%s class=\"graph_container\"><h3>%s</h3>%s</td>",$gc,$content[$j]['heading'],$content[$j]['graphs']);
		}
		print "</tr>\n";
	}

	print "</table>\n";
	print "</div>\n";
}
print "</div>\n";


//print "<br><pre>".print_r($graph,1)."</pre>\n";

print "</body></html>\n";

?>