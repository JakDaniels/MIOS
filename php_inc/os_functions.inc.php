<?php

function create_uuid() {
	$uuid=md5("Opensim".microtime_float());
	$uuid=sprintf('%s-%s-%s-%s-%s',substr($uuid,0,8),substr($uuid,8,4),substr($uuid,12,4),substr($uuid,16,4),substr($uuid,20,12));
	return $uuid;
}

function try_parse_uuid($uuid,&$out) {
	if(preg_match("/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i",$uuid,$m)) {
		$out=$m[1];
		return 1;
	}
	return 0;
}

function try_parse_port($port,&$out) {
	if(preg_match("/([0-9]+)/i",$port,$m)) {
		if($m[1]>=0 and $m[1]<65536) {
			$out=$m[1];
			return 1;
		}
	}
	return 0;
}

function try_parse_size($size, &$out) {
	$sizes=array(256,512,768,1024,1280,1536,1792,2048,2304,2560,2816,3072,3328,3584,3840,4096);
	if(in_array($size,$sizes)) {
		$out=$size;
		return 1;
	}
	return 0;
}

function get_instance_status($rl,$inst,$base_port) {
	$rs=str_replace(" ","_",$inst); //replace spaces with _ in instance names for when we create the database and tmux windows
	$entry=sprintf("%s\t%s\t%s",$inst,$rs,$base_port);
	$cstatus='stopped'; //default if no entry found
	for($i=0;$i<count($rl);$i++) {
		if(substr($rl[$i],0,strlen($entry))==$entry) {
			$cstatus=trim(substr($rl[$i],strlen($entry)));
			if($cstatus=='') $cstatus='stopped';
			if($cstatus=='stop') $cstatus='stopping';
			if($cstatus=='start') $cstatus='starting';
			break;
		}
	}
	return $cstatus;
}

function get_instance_config_set($inst) {
	$cset=@file(sprintf("%s%s/.config_set",BASE_DIR,$inst));
	$cs=trim($cset[0]);
	if($cs=='') {
		$cs=DEFAULT_CONFIG_SET;
		set_instance_config_set($inst,$cs);
	}
	return $cs;
}

function set_instance_config_set($inst,$set) {
	if($fp=fopen(sprintf("%s%s/.config_set",BASE_DIR,$inst),'wb')) {
		fwrite($fp,$set);
		fclose($fp);
		return 1;
	}
	return 0;
}

function find_region_by_uuid($info,$uuid) {
	foreach($info as $region) {
		$ruuid=str_replace(array('"',' '),'',$region['RegionUUID']);
		if($ruuid==$uuid) {
			return $region;
		}
	}
	return 0;
}

//xbuild /t:clean ; ./runprebuild.sh autoclean ; ./runprebuild.sh vs2010 ; xbuild /p:Configuration=Release

function make_instance_directories($inst) {
	@mkdir(sprintf(OUT_CONF_DIR,$inst));
	@mkdir(sprintf(OUT_CONF_DIR.'Regions/',$inst));
	@mkdir(sprintf(SCRIPTS_DIR,$inst));
	@mkdir(sprintf(LOGS_DIR,$inst));
	@mkdir(sprintf(STATS_DIR,$inst));
	@mkdir(sprintf(CONFIGS_DIR,$inst));
	@mkdir(sprintf(CONFIGS_DIR.'Regions/',$inst));
	@mkdir(sprintf(CONFIGS_DIR.'Overrides/',$inst));

	if($fp=@fopen(sprintf(CONFIGS_DIR.'Regions/README.txt',$inst))) {
		fwrite($fp,"Place .ini files here that contain named Region specific settings");
		@fclose($fp);
	}
	if($fp=@fopen(sprintf(CONFIGS_DIR.'Overrides/README.txt',$inst))) {
		fwrite($fp,"Place .ini files here that should override the main .ini setting for this instance only.");
		@fclose($fp);
	}
}

function enum_instances() {
	$configs=BASE_DIR;	//get a list of instance configs
	$l=trim(`ls -1 "${configs}"|grep -v "\."`);
	if($l!='') $instances=explode("\n",$l);
	else $instances=array();
	return $instances;
}

function enum_instance($inst,&$used_ports,&$used_uuids,&$base_port,&$regions_list,&$config_set) {
	global $debug;
	$dconfig=BASE_CONFIGS.'Regions/Regions.ini';
	if($debug) printf("- Reading config file: %s\n",$dconfig);
	$dini=parse_ini($dconfig, true, INI_SCANNER_RAW) or array();
	$rconfig=sprintf(CONFIGS_DIR,$inst).'Regions/Regions.ini';
	if($debug) printf("- Reading config file: %s\n",$rconfig);
	$rini=parse_ini($rconfig, true, INI_SCANNER_RAW) or array();
	$oconfig=sprintf(OUT_CONF_DIR,$inst).'Regions/Regions.ini';

	//also read any other non shared module specific inis that might be in the Regions dir
	$inis=array();
	$region_modspec_path=str_replace(' ','\ ',sprintf(CONFIGS_DIR,$inst).'Regions/*.ini');
	$region_modspec_inis=explode("\n", trim(`ls -1 {$region_modspec_path} 2>/dev/null`));
	foreach($region_modspec_inis as $bi) {
		if($bi!='' and $bi!=$rconfig) {
			if($debug) printf("- Reading config file: %s\n",$bi);
			$inis[]=parse_ini($bi, true, INI_SCANNER_RAW) or array();
		}
	}
	$rmini=ini_merge($inis);

	$info=array();

	$base_port[$inst]=65535;
	$regions=array_keys($rini);
	$regions_list[$inst]=$regions;
	foreach($regions as $region) {
		$uuid='';
		if(isset($rini[$region]['RegionUUID']) and try_parse_uuid($rini[$region]['RegionUUID'],$uuid)) $used_uuids[$inst.'/'.$region]=$uuid;
		$port=0;
		if(isset($rini[$region]['InternalPort']) and try_parse_port($rini[$region]['InternalPort'],$port)) {
			$used_ports[$inst.'/'.$region]=$port;
			//remember lowest region port number for the instance
			if($port<$base_port[$inst]) $base_port[$inst]=$port;
		}
	}
	$updated=0;
	foreach($regions as $region) {
		$uuid='';
		if(!isset($rini[$region]['RegionUUID']) or !try_parse_uuid($rini[$region]['RegionUUID'],$uuid)) {
			$uuid=create_uuid();
			while(in_array($uuid,$used_uuids)) $uuid=create_uuid();
			if($debug) printf("- Allocation new UUID %s to instance %s, region %s\n",$uuid,$inst,$region);
			$rini[$region]['RegionUUID']='"'.$uuid.'"';
			$used_uuids[$inst.'/'.$region]=$uuid;
			$updated=1;
		}
		$port=0;
		if(!isset($rini[$region]['InternalPort']) or !try_parse_port($rini[$region]['InternalPort'],$port)) {
			$port=INSTANCE_MIN_PORT;
			while(in_array($port,$used_ports) and $port<INSTANCE_MAX_PORT) $port++;
			if($port>INSTANCE_MAX_PORT) die(sprintf("Could not allocate a port to instance %s, region %s!\n",$inst,$region));
			if($debug) printf("- Allocation new port %d to instance %s, region %s\n",$port,$inst,$region);
			$rini[$region]['InternalPort']=$port;
			$used_ports[$inst.'/'.$region]=$port;
			//remember lowest region port number for the instance
			if($port<$base_port[$inst]) $base_port[$inst]=$port;
			$updated=1;
		}
		if($updated) {
			if(!write_ini($rconfig,$rini)) die("ERROR: Could not write ini file $rconfig !\n");
			if($debug) printf("* Updated Region config %s.\n",$rconfig);
		}

		//fill in any setting from default region config from if missing
		foreach($dini['Region Defaults'] as $k=>$v) {
			if(!isset($rini[$region][$k])) {
				$rini[$region][$k]=$v;
				if($debug>1) printf("- Added '%s = %s' to %s for region %s\n",$k,$v,$oconfig,$region);
			}
			if(!isset($rini[$region]['SizeX'])) $rini[$region]['SizeX']=256;
			if(!isset($rini[$region]['SizeY'])) $rini[$region]['SizeY']=256;
		}

		//also apply the region module specific ini data
		if(isset($rmini[$region]) and is_array($rmini[$region])) {
			foreach($rmini[$region] as $k=>$v) {
				if(!isset($rini[$region][$k])) {
					$rini[$region][$k]=$v;
					if($debug>1) printf("- Added '%s = %s' to %s for region %s\n",$k,$v,$oconfig,$region);
				}
			}
		}

		$info[]=array_merge(array('RegionName'=>$region),$rini[$region]);

		//write the output config Regions.ini file
		if(!write_ini($oconfig,$rini)) die("ERROR: Could not write ini file $oconfig !\n");
		if($debug) printf("* Wrote Region config %s.\n",$oconfig);

	}
	$config_set=get_instance_config_set($inst); //which config set does this instance use?
	return $info;
}

function create_rrd_db($rrd_file,$rrd_date,$d) {
	global $debug;
	$ds=$d['DataSets'];
	if($debug) printf("Constructing RRD database file '%s' with %s datasets.\n",$rrd_file,$ds);
	$rrd_types=explode(',',RRD_TYPES);
	$rrd[]='--start';
	$rrd[]=$rrd_date;
	$rrd[]='--step';
	$rrd[]=RRD_STAT_UPDATE_INTERVAL;
	for($i=1;$i<=$ds;$i++) {
		if(isset($d["Data${i}"])) {
			if(isset($d["Data${i}Min"]) and preg_match("/^[0-9U]+$/",$d["Data${i}Min"])) $dataMin=strip_quoted_string($d["Data${i}Min"]); else $dataMin='0';
			if(isset($d["Data${i}Max"]) and preg_match("/^[0-9U]+$/",$d["Data${i}Min"])) $dataMax=strip_quoted_string($d["Data${i}Max"]); else $dataMax='U';
			if(isset($d["Data${i}Type"]) and in_array($d["Data${i}Type"],$rrd_types)) $dataType=strip_quoted_string($d["Data${i}Type"]); else $dataType='GAUGE';
			$dataDS="DS${i}";
			$rrd[]=sprintf('DS:%s:%s:%s:%s:%s',$dataDS,$dataType,RRD_STAT_UPDATE_INTERVAL,$dataMin,$dataMax);
		}
	}
	$rrd[]=sprintf('RRA:MAX:%s:%s',RRD_XFF,RRD_RRA_MIN);
	$rrd[]=sprintf('RRA:MAX:%s:%s',RRD_XFF,RRD_RRA_5MIN);
	$rrd[]=sprintf('RRA:MAX:%s:%s',RRD_XFF,RRD_RRA_HOUR);
	$rrd[]=sprintf('RRA:MAX:%s:%s',RRD_XFF,RRD_RRA_DAY);
	if($debug>1) print_r($rrd);
	if(!rrd_create($rrd_file,$rrd)) printf("Error: %s  creating RRD database '%s'\n",rrd_error(),$rrd_file);
	return $rrd;
}

function update_rrd_db($rrd_file,$rrd_date,$d,$stats,$config,$env) {
	global $debug;
	$ds=$d['DataSets'];
	if($debug) printf("Processing environment: %s\n",$env);
	eval($env);
	if($debug) printf("Updating RRD database file '%s' with %s datasets.\n",$rrd_file,$ds);
	$rrd_types=explode(',',RRD_TYPES);
	$template=array();
	for($i=1;$i<=$ds;$i++) $template[]="DS${i}";
	$rrd=array('--template',implode(":",$template));
	$data=array();
	for($i=1;$i<=$ds;$i++) {
		if(isset($d["Data${i}"])) {
			if(isset($d["Data${i}Min"]) and preg_match("/^[0-9U]+$/",$d["Data${i}Min"])) $dataMin=strip_quoted_string($d["Data${i}Min"]); else $dataMin='0';
			if(isset($d["Data${i}Max"]) and preg_match("/^[0-9U]+$/",$d["Data${i}Min"])) $dataMax=strip_quoted_string($d["Data${i}Max"]); else $dataMax='U';
			if(isset($d["Data${i}Type"]) and in_array($d["Data${i}Type"],$rrd_types)) $dataType=strip_quoted_string($d["Data${i}Type"]); else $dataType='GAUGE';
			$data_keys=explode("||",strip_quoted_string($d["Data${i}"]));
			$dataKey='';
			foreach($data_keys as $k) $dataKey.='["'.$k.'"]';
			$value=0;
			$eval_string=sprintf('$value=%s%s;','$stats',$dataKey);
			if($debug>1) printf("Evaluating %s",$eval_string);
			@eval($eval_string);
			if($debug>1) printf(" - Value is '%s'\n",$value);
			$data[]=sprintf('%s:%s',$rrd_date,$value);
		} else $data[]=sprintf('%s:%s',$rrd_date,0);
	}
	$rrd[]=implode(":",$data);
	if($debug>1) print_r($rrd);
	if(!rrd_update($rrd_file,$rrd)) printf("Error: %s\n",rrd_error());
}

function signals($signal) {
	global $signalled;
	switch($signal) {
		case SIGTERM:
			$signalled='SIGTERM';
			break;
		case SIGINT:
			$signalled='SIGINT';
			break;
		case SIGHUP:
			$signalled='SIGHUP';
			break;
	}
}

function enum_tmux_windows($session) {
	$winlist=explode("\n",trim(`tmux list-windows -t $session`));
	return $winlist;
}

function is_tmux_window_active($session,$window) {
	$windows=enum_tmux_windows($session);
	foreach($windows as $w) {
		if(preg_match("/^[0-9]{1,5}:\ ".$window."\ /",$w)) return 1;
	}
	return 0;
}

function tmux_window_state($session,$window) {
	if(is_tmux_window_active($session,$window)) return 'started';
	return 'stopped';
}

function tmux_session_start($session) {
	global $debug;
	$c=0;
	while($c++<5) {
		$seslist=explode("\n",trim(`tmux list-sessions`));
		foreach($seslist as $s) {
			$sn=explode(":",$s);
			if($session==$sn[0]) return 1;
		}
		$cmd="tmux new-session -d -s $session -n Top -x 132 -y 50 'top' || tmux new-window -d -n Top -t $tsn:0 'top' 2>/dev/null";
		if($debug) date_log(sprintf("Running: %s\n",$cmd));
		`$cmd`;
		sleep(1);
	}
	return 0;
}

?>
