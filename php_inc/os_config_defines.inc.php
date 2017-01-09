	<?php

	//OpenSim Configs: here we list all the places that we get configs from. These may be standalone or grid
	//configurations, each instance will use the named config set as its base. These base configs
	//can then be overridden, per instance, to make custom configurations. Remember that each group of instances
	//that uses a config set MUST HAVE ITS OWN build folder bin/ directory as things like asset-cache CANNOT
	//be shared between grids or a grided instance and a standalone instance.
	//THE ORDER of the ini files MATTERS, as this will be the order they are loaded when starting an instance.
	//Files are assumed to be in or relative to the bin/ directory of the instances selected build folder

	$osconfigs['standalone'] = array( //default standalone setup that comes with Opensim
		'OpenSimDefaults.ini' => BIN_DIR.'OpenSimDefaults.ini',
		'OpenSim.ini'				=> BIN_DIR.'OpenSim.ini.example',
		'config-include/Standalone.ini'		=> BIN_DIR.'config-include/Standalone.ini',
		'config-include/StandaloneCommon.ini'		=> BIN_DIR.'config-include/StandaloneCommon.ini.example',
		'config-include/FlotsamCache.ini'	=> BIN_DIR.'config-include/FlotsamCache.ini.example',
		'config-include/osslEnable.ini'	=> BIN_DIR.'config-include/osslEnable.ini'
	);

	$osconfigs['osgrid'] = array(	//default configs that come ready for OSGrid
		'OpenSimDefaults.ini' => BIN_DIR.'OpenSimDefaults.ini',
		'OpenSim.ini'				=> 'http://download.osgrid.org/OpenSim.ini.txt',
		'config-include/GridHypergrid.ini'=> BIN_DIR.'config-include/GridHypergrid.ini',
		'config-include/GridCommon.ini'		=> 'http://download.osgrid.org/GridCommon.ini.txt',
		'config-include/FlotsamCache.ini'	=> 'http://download.osgrid.org/FlotsamCache.ini.txt'
	);

?>