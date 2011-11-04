#Introduction
This is a complete php data mining application that utilizes multiple simultaneous cURL connections (it's fast).

#Job queueing 
The application utilizes a custom job queue built with redis to distribute tasks.

#Database
Redis is used for all exchange of data. It runs the job queue, messaging system between servers, and stores all data collected by the app.

#Custom redis job queue
Newly added items(keywords/domains) should be updated as soon as possible. So when they get added to a sorted set, they are given a score of "0", which means that when client.core is selecting keywords to be updated, they will be at the top of the list. Since hourly and daily keyword sorted sets are both checked with the same frequency, there is no reason to distinguish new items with their own sorted set for priority.
