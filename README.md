#Introduction
This is a complete php data mining application that utilizes multiple simultaneous cURL connections (it's fast). It was written to run on EC2 across multiple instances. Each task (boss, worker, databse, etc..) is designed to exist on a separate instance.

#Application design

###How to use it
Every aspect of the app is controlled from command line by passing commands to the "router.php", like so:

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
For every type of action there is a core daemmon that will be run. As the name suggests, they are never ending daemonized scripts that typically just loop on forever firing off events based on a pub/sub event or time based event. Everything else is basically about supporting these, as they are the "core" of the application.

#Redis
Redis is used for all exchange of data. It runs the job queue, messaging system between servers, and stores all data collected by the app.

###Sever monitoring messaging system
Every server that runs this application will launch a monitoring script on boot.  The system monitor subscribes to redis channels for all servers, it's type of server, and it's specific channel. All servers can be sent commands in real time via the redis prompt. Available commands are:

 // Kills and restarts supervisord
    publish monitor:[type] reset

 // Kills supervisord
    publish monitor:[type] stop

 // Kill supervisord then reboot instance
    publish monitor:[type] reboot

 // Kill supervisord the shutdown instance
    publish monitor:[type] shutdown

---------------------------------------
###Job queue 
The application utilizes a custom job queue built with redis to distribute tasks.

####Job priority
Newly added items(keywords/domains) should be updated as soon as possible. So when they get added to a sorted set, they are given a score of "0", which means that when client.core is selecting keywords to be updated, new items will be at the top of the list. Since hourly and daily keyword sorted sets are both checked with the same frequency, there is no reason to distinguish new items with their own sorted set for priority.

---------------------------------------
### Database design
The end user will refresh their dashboard far more than they will add/remove items, so the idea is to place the "expensive" transactions on the writing and not on the reading. To accomplish this, redundancy is ok and encouraged.

