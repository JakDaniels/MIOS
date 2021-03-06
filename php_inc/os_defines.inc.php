<?php
date_default_timezone_set('Europe/London');

define('BASE_DIR',MIOS_DIR.'Instances/');
define('BASE_CONFIGS',BASE_DIR.'.config/');
define('STATS_CONFIGS',BASE_DIR.'.config/Stats/');
define('CONFIG_SETS',BASE_DIR.'.config/ConfigSets/');
define('CONFIGS_DIR',BASE_DIR.'%s/Configs/');
define('LOGS_DIR',BASE_DIR.'%s/Logs/');
define('STATS_DIR',BASE_DIR.'%s/Stats/');
define('SCRIPTS_DIR',BASE_DIR.'%s/ScriptEngines/');
define('OUT_CONF_DIR',BASE_DIR.'%s/ConfigOut/');
define('OS_ROOT_DIR',HOME_DIR.'/opensim/');
define('BIN_DIR',HOME_DIR.'/opensim/bin/');
define('OS_RUNNER',INC_DIR.'os_runner.sh.php');
define('OS_RUNNER_LOG_DIR',MIOS_DIR.'Logs/');
define('OS_RUNNER_LOG',OS_RUNNER_LOG_DIR.'os_runner.log');
define('RUN_LIST',BASE_CONFIGS.'.runlist');
define('PID_FILE',BASE_CONFIGS.'.pidfile');
define('TMUX_FILE',HOME_DIR.'/.tmux.conf');
define('OS_EXEC',INC_DIR.'os_exec.sh');
define('GRAPH_DIR',MIOS_DIR.'web/img_cache/');

//some of these get used in dbfunctions
define("DEBUG",0);
define('SHOWINFO',1);
define('SHOWCMDS',2);
define('SHOWCMDERRORS',4);
define('LOGCMDERRORS',8);

// a list of the RRDTool datatypes we can use in stats.
define('RRD_TYPES','GAUGE,COUNTER,DERIVE,DCOUNTER,DDERIVE,ABSOLUTE,COMPUTE');
?>