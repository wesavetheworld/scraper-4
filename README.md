#Introduction
This is a complete php data mining application that utilizes multiple simultaneous cURL connections (it's fast). It was written to run on EC2 across multiple instances. Each task (boss, worker, databse, etc..) is designed to exist on a separate instance.

#Application design

###How to use it
Every aspect of the app is controlled from command line by passing commands to "router.php", like so:

 ````
 php router.php bootstrap
 php router.php boss
 php router.php worker google
 etc...
 ````
---------------------------------------

###Bootstrapping
 Each instance runs the app on boot, issuing the command "php path/to/app/router.php bootstrap" from server location "/etc/rc.local". If the location of the app on the server changes, the command in rc.local needs to be updated as well as the path used in the bootstrap core file. When the bootstrap core runs, it will use the aws api to determine what type of server it is, based on instance tags, and run the correct core daemon for it's type (redis,client,worker:google,worker:bing etc).

####Self updating
The first function run during bootstrap pulls the latest revision from git. If newer code was pulled, a new bootstrap process is started and the current one ended. So the easiest way to update the multi-server application is to simply reboot all of the instances.

####Self awareness
In the AWS control panel, you can assign "tags" to an instance. During the bootstrap process, the app will retrieve it's tags and save them to a config file.

####Doing stuff
Based on the tags retrieved aboved, it will write to the supervisord config file, setting which tasks will be deamonized and how many processes of each type should be run in parallel.

####Supervisord
The last step of bootstrapping is to start [supervisord](http://supervisord.org/). Supervisord will load it's config file, which now contains the commands specific for the current instance type, and run those processess.  If for any reason those processes die, supervisord will relaunch them,

---------------------------------------
###Core Daemons
For every type of action there is a core daemmon that will be run. As the name suggests, they are never ending daemonized scripts that typically just loop on forever firing off events based on [pub/sub](http://redis.io/topics/pubsub) or time based events. Everything else is basically about supporting these, as they are the "core" of the application.

####boss.core
The boss
####worker.core
the worker
####monitor.core
waits for messages
####cron.core
manages all time-based events
####manual.core
all events manually fired by either the command line or cron

#Redis
[Redis](http://redis.io) is used for all exchange of data. It runs the job queue, messaging system between servers, and stores all data collected by the app.

###Database details
The following redis databases are needed to run the application. If running on AWS, each database would be on it's own instance, but locally they can all be in the same redis install.

####Job queue
This database manages all of the workers and jobs and is run on the Boss instance on AWS or as db 0 locally. 

*Workers check in to this db on launch and check out on close*
	name: workers:$type (i.e. workers:google)
	type: sorted set

*All items in the serps database have their ids added to these sets. One set for each combination of source and schedule. Each items score is updated to reflect the next time it should be updated.*	
	name: $source:$schedule (i.e. google:hourly)
	type:sorted set

####Serps
This database contains hashes of each domain and keyword's data. It's a duplicate of whats stored in MySQL for faster access for job creation. It runs on it's own instance or db 1 locally.

*Keyword objects*
````
	name: k:$id (i.e. k:3957)
	type: hash
	fields:
		user_id
		keyword
		domain_id
		g_country
		schedule
````
*Keyword Stats objects. One for each of last 30 days*
````
	name: k:$id:$date (i.e. k:59911:2012-06-07)
	type: hash	
````		

*Domain objects*
````
	name: d:$id (i.e. d:1924)
	type: hash	
````	

*Domain Stats objects. One for each of last 30 days*
````
	name: d:$id:$date (i.e. d:59911:2012-06-07)
	type: hash		
````	

####Proxies
This database manages all of the proxies used for data collecting. It runs on it's own instance or db 2 locally.

*Proxy objects*
````
	name: p:$ip (i.e. p:64.87.58.10)
	type: hash
	fields:
		ip
		port
		username
		password
		source
		tunnel
````		

####Dashboard cache
This database caches a user's dashboard array of domains and keywords on the first load from MySQL	

*Dashboard key*	
````
	name: $user_id:$group_id (i.e. 3:45)
	type: string
````

###Sever monitoring messaging system
 Boostratp will launch a server monitoring daemon.  The monitoring daemon subscribes to redis channels for all servers, it's type of server, and it's specific channel. All servers can be sent commands in real time via the redis prompt. Available commands are:

*Kills and restarts supervisord*

    publish monitor:[type] reset

*Kills supervisord*
   
    publish monitor:[type] stop

*Kill supervisord then reboot instance*

    publish monitor:[type] reboot

*Kill supervisord then shutdown instance*

    publish monitor:[type] shutdown

---------------------------------------
###Job queue 
The application utilizes a custom job queue built with redis to distribute tasks.

####Job priority
Newly added items(keywords/domains) should be updated as soon as possible. So when they get added to a sorted set, they are given a score of "0", which means that when client.core is selecting keywords to be updated, new items will be at the top of the list. Since hourly and daily keyword sorted sets are both checked with the same frequency, there is no reason to distinguish new items with their own sorted set for priority.

---------------------------------------
###Database design
The end user will refresh their dashboard far more than they will add/remove items, so the idea is to place the "expensive" transactions on the writing and not on the reading. To accomplish this, redundancy is ok and encouraged.

#Local development
The application was designed to run across multiple AWS instances, but can be set up to run on a local machine (in this case a mac).

###Dependencies
redis
MySQL

###Step 1: Start redis
Redis needs to be running in order to manage the job queue.

*From the command line, navigate into the redis folder and run*

    src/redis-server

###Step 2: Instance config
config/instance.config.php determines the script name. Modify to match the boss.

*Modify following lines to mock AWS instance*
````
    define("INSTANCE_TYPE", "boss");
    define("INSTANCE_NAME", "theBoss"); 
````       

###Step 3: The Boss
*Navigate to the scraper directory, and start the boss*

	php router.php boss

###Step 4: Instance config(again)
*config/instance.config.php determines the script name. Modify to match the worker*

*Modify following lines to mock AWS instance*
````
	define("INSTANCE_TYPE", "google");
	define("INSTANCE_NAME", "google");  	
````

###Step 5: The worker
*Navigate to the scraper directory, and start the worker (last integer is process number. Increment for multiple simultaneous workers)*

    php router.php worker google 1


#Troubleshooting
Here are some ways to help troubleshoot any problems you may encounter.

###Update stats
The boss instance can report on the system's current update status. It will show amount of keywords/domains updated/unupdated for the hour/day.

*From the scraper directory*

    php router.php boss checkQueueSchedules manual

###Proxy stats
From the redis command line you can view proxy usage stats for each hour. The total number of proxy connections as well as good and bad/blocked scrapes. This will give you a quick overview of the health of the proxies.

*Works for all data sources google/bing/alexa etc*

    hgetall usage:google

###Worker logs
Each worker process logs it's output. This is very useful for getting more specific information about data collection. To view these logs you must access a worker instance.

*Integer in log name represents specific worker process*

    tail -f scraper/logs/google.1.log  








