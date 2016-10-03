<?php
define('HOME_DIR',trim(`echo ~`));
define('BASE_DIR',HOME_DIR.'/MIOS/Instances/');
define('BASE_CONFIGS',BASE_DIR.'.config/');
define('STATS_CONFIGS',BASE_DIR.'.config/Stats/');
define('CONFIG_SETS',BASE_DIR.'.config/ConfigSets/');
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

//some of these get used in dbfunctions
define("DEBUG",0);
define('SHOWINFO',1);
define('SHOWCMDS',2);
define('SHOWCMDERRORS',4);
define('LOGCMDERRORS',8);
?>
