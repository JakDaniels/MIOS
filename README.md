# MIOS
MIOS is a set of control scripts that let you easily run Multiple Instances of Open Simulator.

**Notice:**

MIOS runs on Linux only, it's been tested on Centos 6 distro and requires MySQL or Mariadb as the backend database for OpenSim.

This is still very much work in progress, but so far it very quickly lets you spin up Instances
of Open Simulator and add new regions to those Instances.
It also monitors the Instances and respawns them if necessary after a crash, or marks them as broken
if the respawn happens too often and too many times.

On Centos 6 you will need the make sure the following Repos are enabled:

EPEL

	# yum install epel-release

and optionally for a newer version of the Mono packages for Centos 6, tested to work with OpenSim (currently 3.10, 3.12 and 4.4.2.11):

	# yum install http://el6.ateb.co.uk/ateb/x86_64/RPMS/ateb-release-6-1.noarch.rpm

Look at the Repo documentation at http://el6.ateb.co.uk/ for info on how to choose different Mono versions that work with OpenSim.

*Disclaimer:* This is my own Repo and these Mono RPMS are provided by me, I use them myself with MIOS. You don't have to use them. Centos 6 packages
Mono 2.10.8 in it's main Repo and this *should* work with OpenSim although I have not tested this older version :)
You can also build Mono yourself from http://download.mono-project.com/sources/mono/ if you want to try other versions.

Make sure these packages are installed:

	# yum install php php-mysql php-cli php-common php-pdo php-process php-pecl-rrd git tmux wget mono-complete mysql-server
 
NOTE: Make sure you've run the mysql_secure_installation script that comes with MySQL and have set a root password. You will
need this MySQL root password later when you set up MIOS! You also need to remember to tweak the MySQL config in /etc/my.cnf, adding the
max_allowed_packet=128M setting that is needed by Opensim. My file looks like this:

	[mysqld]
	datadir=/var/lib/mysql
	socket=/var/lib/mysql/mysql.sock
	user=mysql
	# Disabling symbolic-links is recommended to prevent assorted security risks
	symbolic-links=0
	max_allowed_packet=128M

	[mysqld_safe]
	log-error=/var/log/mysqld.log
	pid-file=/var/run/mysqld/mysqld.pid

Make sure the database server is running :)

	# service mysqld start

# Install and configure MIOS

In general, on a new system where you want to run MIOS and OpenSim do the following:

### 1) As root, create a user that will run the OpenSim instances:

	# adduser opensim
	# passwd opensim

Make sure you set a password for that account. By default on Centos 6 you will be able to login to that
account via SSH (your firewall rules permitting of course :). Of course having SSH access to your OpenSim consoles
will be very useful later...

### 2) As that user, install MIOS from GitHub:

	# su - opensim
	# git clone https://github.com/JakDaniels/MIOS.git
	# cd MIOS

### 3) Copy and edit the example config:

	# cp Instances/.config/config.inc.php.example Instances/.config/config.inc.php
	# vi Instances/.config/config.inc.php

You definitely need to change the passwords that will be used for database access, from 'xxxxx' to something else.
The MySQL user accounts and passwords in the config are used by both MIOS and OpenSim when creating and running OpenSim Instances..
Don't worry about setting up any actual users or databases in MySQL now, MIOS can do that for you!

You will need the MySQL root password to do this next bit. We create the user accounts in MySQL that MIOS and OpenSim will use.

### 4) Using MIOS to setup the database server:

	# ./opensim.sh.php --init-mysql
	We are about to add two users to mysql, one for administering databases, and one that opensim will use for
	database access.
	You will need to provide the mysql root password to do this.
	Enter password:

You could do this bit manually of course, creating two accounts in MySQL with the credentails from the MIOS config... but it's quicker this way :)

### 5) Use MIOS to download and build the latest OpenSim from git:

	# ./opensim.sh.php --os-update

MIOS will download or update the OpenSim source code that is stored on GitHub (dev-master branch). A subdirectory 'opensim' will be created in the opensim user's home directory.
It will also attempt to build the OpenSim code in release mode.

What's important to remember here is that the code is from the dev-master branch. It changes frequently, and very occasionally may not even compile properly!
The OpenSim code is fetched from a GitHub URL that is configurable in the MIOS config. At some point I want to support the loading of specific fixed release versions, but this is not yet supported. :(

### 6) Use MIOS to grab a basic default working set of opensim configuration files.

Each OpenSim Instance has to have a set of default configs. These may be the ones supplied by the OpenSim developers for running
in 'standalone' mode, or by a grid operator such as OSGrid to allow your regions to be a part of their grid. In the MIOS config
there are currently two configuration sets defined. One is for 'standalone' mode - (isolated, not connected to any other simulators)
and 'osgrid' (for placing regions into the OSGrid metaverse).

MIOS uses the default config files that come with OpenSim as a starting point for 'standalone' instances, and files provided by OSGrid for 'osgrid' instances.
The files needed for OSGrid are downloaded as needed from their website by MIOS.

You can later customise this default OpenSim configuration by having you own set of small .ini files that override the
defaults in a predictable way. By providing just the setting you want, in your own files, you *never* need to touch the default
.ini files or those provided by OSGrid. Any customisations you provide using .ini overrides will be overlayed on top of the defaults.

Hint: look in directory MIOS/Instances/.config/Overrides and  MIOS/Instances/[Instance Name]/Configs/Overrides
This is where your put own OpenSim .ini file settings.

So... here we go.... let's setup the defaults configs for OpenSim:
   
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

It is recommended that you run this after every time you do a --os-update. Features in OpenSim change quickly on the development branch
MIOS uses, OSGrid tracks any config changes that might occur as a result of code changes and regularly produces new base configs for the grid.
Sometimes the default configs supplied with OpenSim change too. New options are sometimes added. We need to make sure we use the latest 
default configs after a code update.

### 7) Create your first OpenSim Instance!

Each OpenSim Instance has to have a set of default configs. These may be the ones supplied by the OpenSim developers for running
in 'standalone' mode, or by a grid operator such as OSGrid to allow your regions to be a part of their grid.

By default if --config-set is not given then MIOS will use the downloaded 'osgrid' config files. This can be changed in the MIOS config file.

	# ./opensim.sh.php --add-instance Test1 --config-set osgrid
	The Instance configs were generated successfully! Use --add-region now to add regions to this instance.

### 8) Add a 512x512 var region to your new instance:

	# ./opensim.sh.php --add-region TestRegion1 --instance Test1 --location 10000,10000 --size 512
	Region TestRegion1 was created in Instance Test1 with UUID 8172c646-c147-efec-8476-d5e5b71ec62d on port 9005.
	
MIOS will not detect if the grid location is already occupied on the grid, so take care to find some coordinates that are
not already in use before doing this. It will however detect if another instance has used these coordinated for any of it's regions.

### 9) The first time a new region starts it will ask you questions about which estate it should be part of, so for the first
   time we fire up the instance manually and not under MIOS control so we can answer the interactive questions:
   
	# ./opensim.sh.php --start Test1 --manual
	Run this command: /home/opensim/MIOS/php_inc/os_exec.sh "/home/opensim/MIOS/Instances/Test1/ConfigOut/empty.ini" "/home/opensim/MIOS/Instances/Test1/ConfigOut/combined.ini" "/home/opensim/MIOS/Instances/Test1/Logs/OpenSim.pid"
	
   Yes, this bit is a bit clumsy.... you have to run the command that is returned above. Hopefully I'll fix this.
   Run the returned command and join the region to the estate. Then shutdown the instance (type shutdown on the opensim console).
   Now is a good time to find and debug any other issues the instance may have in starting (like using an already occupied grid location)
   before putting the instance under full MIOS control. Once the instance and its region(s) come up cleanly using the command  provided by the
   --manual parameter, you can proceed to let MIOS start, stop and monitor the instance.
   
### 10) List the instances and regions you have created:

	# ./opensim.sh.php --list

	+-Instance: Test1--------------------------------------------+-Config Set: osgrid-----+  *stopped
	+------------------------------------------------------------+------------------------------------+
	| RegionName          | RegionUUID                           | GridLocation   | Port  | Size      |
	+-------------------------------------------------------------------------------------------------+
	| TestRegion1         | 8172c646-c147-efec-8476-d5e5b71ec62d | 50,50          | 9005  | 512x512   |
	+-------------------------------------------------------------------------------------------------+

### 11) And start all the instances:

	# ./opensim.sh.php --start
	Starting Instance: Test1...               [  OK  ]

That's about it. Look at ./opensim.sh.php --help for more stuff you can do.

