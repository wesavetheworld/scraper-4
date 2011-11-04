#Introduction
This is a complete php data mining application that utilizes multiple simultaneous cURL connections (it's fast).

#Server configuration
The applicaton assumes it exists on ec2 in a multi server environment. It wasn't build to be run from a single server. Each instance runs the app on boot from "/etc/rc.local". If the location of the app on the server changes, the command in rc.local needs to be updated as well as the path used in the bootstrap core. 

###Bootstrapping
When the bootstrap core runs, it will use the aws api to determine what type of server it is, based on instance tags, and run the correct controllers for it's type.

#Redis
Redis is used for all exchange of data. It runs the job queue, messaging system between servers, and stores all data collected by the app.

###Sever monitoring messaging system
Every server that runs this application will launch a monitoring script on boot.  The system monitor subscribes to a redis channels for all servers, it's type of server, and it's specific channel. All servers be sent commands in real time via the redis prompt (publish workers:all reboot)

###Job queue 
The application utilizes a custom job queue built with redis to distribute tasks.

####Job priority
The app uses a custom job queue built with redis. 
Newly added items(keywords/domains) should be updated as soon as possible. So when they get added to a sorted set, they are given a score of "0", which means that when client.core is selecting keywords to be updated, they will be at the top of the list. Since hourly and daily keyword sorted sets are both checked with the same frequency, there is no reason to distinguish new items with their own sorted set for priority.
