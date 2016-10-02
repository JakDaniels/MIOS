# MIOS
MIOS is a set of control scripts that let you easily run Multiple Instances of Open Simulator.

**Notice** MIOS runs on Linux only!

Tested only on Centos 6 distro and mysql or mariadb as the backend database for opensim.

This is still very much work in progress, but so far it very quickly lets you spin up Instances
of Open Simulator and add new regions to those Instances.
It also monitors the Instances and respawns them if necessary after a crash, or marks them as broken
if the respawn happens too often and too many times.

It does need some initial simple configuration:

Copy the file ~/MIOS/Instances/.config/config.inc.php.example to ~/MIOS/Instances/.config/config.inc.php
and edit accordingly. Most stuff should be set up already, but you will need to set the database usernames
and passwords etc.

Run ./opensim.sh.php --help for more instructions on manipulating Instances once the initial config is done.


In general, on a new system where you want to run MIOS and opensim do the following:

1) As root, create a user that will run the opensim instances: 
		# adduser opensim

2) become that user and install MIOS from github:
		# su - opensim
		# git clone https://github.com/JakDaniels/MIOS.git
		# cd MIOS

3) Copy the example config and edit to fill in some values:
		# cp Instances/.config/config.inc.php.example Instances/.config/config.inc.php
		# nano Instances/.config/config.inc.php

You definitely need to set the passwords that will be used for database access, both by MIOS and 
Opensim. Don't worry about setting up any actual databases or users in Mysql now, MIOS can do that for you
later, but it will need Mysql root privileges to do this!

4) Use MIOS to setup the database server and the user accounts it needs:
		# ./opensim.sh.php --init-mysql
		We are about to add two users to mysql, one for administering databases, and one that opensim will use for database access.
		You will need to provide the mysql root password to do this.
		Enter password:

5) Use MIOS to download and build the latest Opensim from git:
		# ./opensim.sh.php --os-update

MIOS will grab the opensim build tree from git and place it at ~/opensim. It will also build it in release mode.

6) Use MIOS to grab a basic default working set of opensim configuration files. MIOS uses the default files that
   come with Opensim as a starting point for standalone instances, or files provided by a grid operator like OSGrid. (It
   downloads them)
		# ./opensim.sh.php --os-config
		Starting retrieval of config set 'standalone'...
		`/home/opensim/opensim/bin/OpenSimDefaults.ini' -> `/home/opensim/MIOS/Instances/.config/ConfigSets/standalone/OpenSimDefaults.ini'
		`/home/opensim/opensim/bin/OpenSim.ini.example' -> `/home/opensim/MIOS/Instances/.config/ConfigSets/standalone/OpenSim.ini'
		`/home/opensim/opensim/bin/config-include/Standalone.ini' -> `/home/opensim/MIOS/Instances/.config/ConfigSets/standalone/config-include/Standalone.ini'
		`/home/opensim/opensim/bin/config-include/StandaloneCommon.ini.example' -> `/home/opensim/MIOS/Instances/.config/ConfigSets/standalone/config-include/StandaloneCommon.ini'
		`/home/opensim/opensim/bin/config-include/FlotsamCache.ini.example' -> `/home/opensim/MIOS/Instances/.config/ConfigSets/standalone/config-include/FlotsamCache.ini'
		`/home/opensim/opensim/bin/config-include/osslEnable.ini' -> `/home/opensim/MIOS/Instances/.config/ConfigSets/standalone/config-include/osslEnable.ini'
		Starting retrieval of config set 'osgrid'...
		`/home/opensim/opensim/bin/OpenSimDefaults.ini' -> `/home/opensim/MIOS/Instances/.config/ConfigSets/osgrid/OpenSimDefaults.ini'
		--2016-10-02 20:48:59--  http://download.osgrid.org/OpenSim.ini.txt
		Resolving download.osgrid.org... 64.31.16.122
		Connecting to download.osgrid.org|64.31.16.122|:80... connected.
		HTTP request sent, awaiting response... 200 OK
		Length: 52857 (52K) [text/plain]
		Saving to: “/home/opensim/MIOS/Instances/.config/ConfigSets/osgrid/OpenSim.ini”
		
		100%[============================================================================================================>] 52,857       194K/s   in 0.3s
		
		2016-10-02 20:49:00 (194 KB/s) - “/home/opensim/MIOS/Instances/.config/ConfigSets/osgrid/OpenSim.ini” saved [52857/52857]
		
		`/home/opensim/opensim/bin/config-include/GridHypergrid.ini' -> `/home/opensim/MIOS/Instances/.config/ConfigSets/osgrid/config-include/GridHypergrid.ini'
		--2016-10-02 20:49:00--  http://download.osgrid.org/GridCommon.ini.txt
		Resolving download.osgrid.org... 64.31.16.122
		Connecting to download.osgrid.org|64.31.16.122|:80... connected.
		HTTP request sent, awaiting response... 200 OK
		Length: 5819 (5.7K) [text/plain]
		Saving to: “/home/opensim/MIOS/Instances/.config/ConfigSets/osgrid/config-include/GridCommon.ini”
		
		100%[============================================================================================================>] 5,819       --.-K/s   in 0.002s
		
		2016-10-02 20:49:01 (2.63 MB/s) - “/home/opensim/MIOS/Instances/.config/ConfigSets/osgrid/config-include/GridCommon.ini” saved [5819/5819]
		
		--2016-10-02 20:49:01--  http://download.osgrid.org/FlotsamCache.ini.txt
		Resolving download.osgrid.org... 64.31.16.122
		Connecting to download.osgrid.org|64.31.16.122|:80... connected.
		HTTP request sent, awaiting response... 200 OK
		Length: 1865 (1.8K) [text/plain]
		Saving to: “/home/opensim/MIOS/Instances/.config/ConfigSets/osgrid/config-include/FlotsamCache.ini”
		
		100%[============================================================================================================>] 1,865       --.-K/s   in 0.001s
		
		2016-10-02 20:49:01 (2.04 MB/s) - “/home/opensim/MIOS/Instances/.config/ConfigSets/osgrid/config-include/FlotsamCache.ini” saved [1865/1865]
		
7) Create your first Opensim Instance!
		# ./opensim.sh.php --add-instance Test1
		The Instance configs were generated successfully! Use --add-region now to add regions to this instance.
	
8) Add a 512x512 var region to your new instance:
		# ./opensim.sh.php --add-region TestRegion1 --instance Test1 --location 10000,10000 --size 512
		Region TestRegion1 was created in Instance Test1 with UUID 8172c646-c147-efec-8476-d5e5b71ec62d on port 9005.
	
9) The first time a new region starts it will ask you questions about which estate it should be part of, so for the first
   time we fire up the instance manually and answer the questions:
		# ./opensim.sh.php --start Test1 --manual
		Run this command: /home/opensim/MIOS/php_inc/os_exec.sh "/home/opensim/MIOS/Instances/Test1/ConfigOut/empty.ini"
		"/home/opensim/MIOS/Instances/Test1/ConfigOut/combined.ini" "/home/opensim/MIOS/Instances/Test1/Logs/OpenSim.pid"

   Yes, this bit is a bit clumsy.... you have to run the command that is returned above. Hopefully I'll fix this.
   Run the returned command and join the region to the estate. Then shutdown the instance (type shutdown on the opensim console)
   
10) You can now list the instances and regions you have created:
		# ./opensim.sh.php --list
		
		+-Instance: Test1--------------------------------------------+-Config Set: osgrid-----+  *stopped
		+------------------------------------------------------------+------------------------------------+
		| RegionName          | RegionUUID                           | GridLocation   | Port  | Size      |
		+-------------------------------------------------------------------------------------------------+
		| TestRegion1         | 8172c646-c147-efec-8476-d5e5b71ec62d | 50,50          | 9005  | 512x512   |
		+-------------------------------------------------------------------------------------------------+
		
11) And start all the instances:
		# ./opensim.sh.php --start
		Starting Instance: Test1...               [  OK  ]

That's about it. Look at ./opensim.sh.php --help for more stuff you can do.

