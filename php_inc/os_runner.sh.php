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
define('BIN_DIR',HOME_DIR.'/opensim/bin/');
define('INC_DIR',HOME_DIR.'/MIOS/php_inc/');
define('OS_EXEC',INC_DIR.'os_exec.sh');

include(BASE_CONFIGS.'config.inc.php');
include(INC_DIR.'functions.inc.php');
include(INC_DIR.'os_functions.inc.php');

ob_implicit_flush ();
define("DEBUG",0);
//script specific debug defines
define('SHOWINFO',1);
define('SHOWCMDS',2);
define('SHOWCMDERRORS',4);
define('LOGCMDERRORS',8);

set_time_limit (0);

// signal handling
declare(ticks=1); $signalled=0;
pcntl_signal(SIGTERM, "signals");
pcntl_signal(SIGINT, "signals");
pcntl_signal(SIGHUP, "signals");

$argc=$_SERVER["argc"];
$argv=$_SERVER["argv"]; //$argv is an array
if($argc<1) error(usage());
$args=parse_args($argc,$argv);
if(isset($args['h']) or isset($args['help'])) error(usage());
if(isset($args['d']) or isset($args['debug'])) $debug=1; else $debug=0;

$runlist=BASE_CONFIGS.'.runlist';
$pidfile=BASE_CONFIGS.'.pidfile';
$tmuxfile=HOME_DIR.'/.tmux.conf';

$tsn=TMUX_SESSION_NAME;
write_text_file($tmuxfile,"set-option -g prefix ".TMUX_CONTROL_PREFIX."\nset-option -g history-limit ".TMUX_SCROLL_BUFFER."\n");

`tmux new-session -d -s $tsn -n Top -x 132 -y 50 'top' || tmux new-window -d -n Top -t $tsn:0 'top' 2>/dev/null`;

$pid=getmypid();
if($debug) date_log(sprintf("Writing %s to pidfile %s\n",$pid,$pidfile));
write_text_file($pidfile,"$pid\n");

$signalled='SIGHUP';
while(1) {

	if($signalled==='SIGHUP') { //we were kicked so update
		//get a list of instance configs
		$instances=enum_instances();
		$signalled=0;
	}

	foreach($instances as $inst) {
		$rs=str_replace(" ","_",$inst); //replace spaces with _ in instance names for when we create the database and tmux windows

		$entry=sprintf("%s\t%s\t",$inst,$rs);
		$action=''; //default if no entry found

		$rl=read_text_file_to_array_with_lock($runlist,LOCK_EX);
		for($i=0;$i<count($rl);$i++) {
			if(substr($rl[$i],0,strlen($entry))==$entry) {
				$data=explode("\t",trim(substr($rl[$i],strlen($entry))));
				$index=$data[0];
				$action=$data[1];
				break;
			}
		}
		$status=tmux_window_state($tsn,$rs);

		//attempt to restart an instance that's stopped, but only a limited number of times if the last start time was within MAX_RESTART_TIME_INTERVAL secs
		if($action=='started' and $status=='stopped') {
			if($last_started[$inst]+MAX_RESTART_TIME_INTERVAL>time() and $started_count[$inst]>MAX_RESTART_COUNT) {
				if($debug) date_log(sprintf("Instance: %s was restarted %s times and stopped again within %s seconds every time. It must be broken!\n",$inst,$started_count[$inst],MAX_RESTART_TIME_INTERVAL));
				$started_count[$inst]=0;
				$action='break';
				$status='broken';
			} else {
				if($debug) date_log(sprintf("Restarting Instance: %s\n",$inst));
				$action='start'; //restart instance if failed
			}
		}

		if($action=='start') {
			if($debug) date_log(sprintf("Starting %s...\n",$inst));
			$c_inipath=sprintf(OUT_CONF_DIR,$inst).'combined.ini';
			$e_inipath=sprintf(OUT_CONF_DIR,$inst).'empty.ini';
			if(!file_exists($c_inipath) or !file_exists($e_inipath)) $status='stopped';
			else {
				//$cmd=sprintf("tmux new-window -d -n %s -t %s:%s 'cd ~/opensim/bin; mono OpenSim.exe -inimaster=\"%s\" -inifile=\"%s\"'",$rs,$tsn,$index,$e_inipath,$c_inipath);
				$cmd=sprintf("tmux new-window -d -n %s -t %s:%s '%s \"%s\" \"%s\"'",$rs,$tsn,$index,OS_EXEC,$e_inipath,$c_inipath);
				if($debug) date_log(sprintf("Running: %s\n",$cmd));
				`$cmd`;
				for($timer=0;$timer<TMUX_START_TIMEOUT;$timer++) {
					sleep(1);
					$status=tmux_window_state($tsn,$rs);
					if($status=='started') {
						$last_started[$inst]=time();
						if(!isset($started_count[$inst])) $started_count[$inst]=1; else $started_count[$inst]++;
						$cmd=sprintf("tmux select-window -t %s:%s",$tsn,$index);
						if($debug) date_log(sprintf("Running: %s\n",$cmd));
						`$cmd`;
						break;
					}
				}
			}
		}

		if($action=='stop') {
			if($debug) date_log(sprintf("Stopping %s...\n",$inst));
			$cmd=sprintf("tmux send-keys -t %s:%s 'shutdown' Enter",$tsn,$index);
			if($debug) date_log(sprintf("Running: %s\n",$cmd));
			`$cmd`;
			for($timer=0;$timer<TMUX_STOP_TIMEOUT;$timer++) {
				sleep(1);
				$status=tmux_window_state($tsn,$rs);
				if($status=='stopped') break;
			}
			$started_count[$inst]=0;
		}

		if($action=='start' or $action=='stop' or $action=='break') {
			$rl=read_text_file_to_array_with_lock($runlist,LOCK_EX);
			for($i=0;$i<count($rl);$i++) {
				if(substr($rl[$i],0,strlen($entry))==$entry) {
					$data=explode("\t",trim(substr($rl[$i],strlen($entry))));
					$index=$data[0];
					break;
				}
			}
			//print "Writing $status\n";
			$rl[$i]=sprintf("%s\t%s\t%s\t%s",$inst,$rs,$index,$status);
			sort($rl);
			write_text_file_from_array_with_lock($runlist,$rl,LOCK_EX);
		}
	}
	sleep(3);
	if($signalled==='SIGTERM' or $signalled==='SIGINT') break;
}

// need to terminate
if($debug) date_log(sprintf("Terminating and removing pidfile %s\n",$pidfile));
`tmux kill-window -t $tsn:0`; //kill the top window
@unlink($pidfile);


// tmux new-session -d -s OpenSim -n OpenSimConsole1 -x 132 -y 50 'mono OpenSim.exe'
// tmux new-window -d -n Window1 -t OpenSim:1
// tmux new-window -d -n Window2 -t OpenSim:2
// tmux list-windows -t OpenSim
// tmux kill-window -t OpenSim:2
// tmux send-keys -t OpenSim:2 'shutdown' Enter

function usage() {
	return "
os_runner.sh.php [ -d ]
";
}
?>
