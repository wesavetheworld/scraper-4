<?php  if(!defined('HUB')) exit('No direct script access allowed\n');

// ******************************* INFORMATION ******************************//

// **************************************************************************//
//  
// ** BOOTSTRAP - Each server runs this first to identify itself and it's own 
// ** meaningless purpose in life.
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-06-17
// ** @access	private
// ** @param	
// ** @return	Main controller router     
//  	
// ***************************************************************************//

// ********************************** START **********************************// 

class bootstrap 
{    
	// Will contain the ec2 instance id
	private $instanceId = false;
	
	// Will contain the ec2 instance name
	private $instanceName = false;

	// Will contain the ec2 instance type
	private $instanceType = false;	
	
	// Will be set to true for development instances
	private $instanceDev = false;

	// Runs on class instantiation
	function __construct()
	{
		// Check repo for any new revisions
		$this->updateApp();

		// Create a new amazon connection		
		$this->amazon();
		
		// Configure this server
		$this->bootstrap();		
	}

	// ===========================================================================// 
	// ! Bootstrap routing function                                               //
	// ===========================================================================//	
	
	// Run boot functions based on instance identity
	public function bootstrap()
	{ 
		// Load the current instances id
		$this->getInstanceId();

		// Load the current instances description (client/worker)
		$this->getInstanceType();

		// Save all server settings to config files
		$this->saveType();	 
		
		// Include all required core files (Dependencies and helper classes)
		require_once('core/includes.core.php');    		 				

		// If this is a redis server
		if($this->instanceType == "redis")
		{	
			if($this->instanceName == 'redisSerps')
			{
				// Assign the redis serps elastic ip to this instance
				$this->assignIp(REDIS_SERPS_IP);	
			}
			elseif($this->instanceName == 'redisProxies')
			{
				// Assign the redis proxy elastic ip to this instance
				$this->assignIp(REDIS_PROXY_IP);					
			}

			// Run redis database
			$this->runRedis();
		}		
		// All othere instance types
		else
		{
			// If this is a client instance
			if($this->instanceType == "client")
			{
				// Assign the client elastic ip to this instance
				$this->assignIp(CLIENT_IP);			
			}
			// If this is either production or development worker 1
			elseif($this->instanceName == "google1" || $this->instanceName == "google1Dev")
			{	
				// Assign the worker elastic ip to this instance
				$this->assignIp(WORKER_IP);					
			}

			// Set up which core daemon supervisord will controll
			$this->editSupervisord(); 		
		}	

		// Start system monitor and detach from script
		exec("php /home/ec2-user/scraper/hub.php tasks monitor &> /dev/null &");

		// Bootstrap complete
		exit("Server successfully configured\n");
	}

	// ===========================================================================// 
	// ! Instance identification methods                                          //
	// ===========================================================================//

	// Create a new amazon connection
	private function amazon()
	{
		// Include the amazon SDK
		require_once('classes/amazon/sdk.class.php');

		// Create a new amazon object
		$this->ec2 = new AmazonEC2();

		// Set the region to access instances
		$this->ec2->set_region('us-west-1');			
	}
	
	// Load the current instances id
	private function getInstanceId()
	{
		// Loop until the server's id is known (loop is a failsafe)
		while(!$this->instanceId)
		{
			// Get the instance id of the currently running instance
			$this->instanceId = exec("wget -q -O - http://169.254.169.254/latest/meta-data/instance-id");

			// If request failed
			if(!$this->instanceId)
			{
				echo "instance id problem.  sleeping...";

				// Wait 10 seconds before trying again.
				sleep(10);
			}	
		}	
	}

	// Load the current instances type tag description (worker/client/job)
	private function getInstanceType()
	{
		// Get current instances info
		$this->getInstances(array('InstanceId' => $this->instanceId));

		// Select all of the tags from the server response
		$tags = $this->response->body->reservationSet->item->instancesSet->item->tagSet->item;

		// Loop through tags
		foreach($tags as $tag)
		{
			// If current tag is the type tag
			if($tag->key == 'type')
			{
				// Set current instance type
				$this->instanceType = $tag->value;
			}
			// If current tag is the name tag
			elseif($tag->key == 'Name')
			{	
				// Set current instance name
				$this->instanceName = $tag->value;				
			}	

			// Check if this is a development server
			if($tag->key == 'dev')
			{
				// Set as a dev server
				$this->instanceDev = TRUE;
			}
		}	

		// If tags are missing
		if(!$this->instanceType || !$this->instanceName)
		{
			// Don't continue, just kill yourself
			exit("no instance type or name tag found. I give up...\n");
		}
	}

	// Get list of EC2 instance info
	private function getInstances($opt)
	{		
		$success = false;

		// Loop until the server's id is known (loop is a failsafe)
		while(!$success)
		{
			// Get info on all worker instances
			$this->response = $this->ec2->describe_instances($opt);		

			// If request was successful
			if($this->response->isOK())
			{
				// Break the while loop
				$success = true;
			}	
			// Request failed
			else
			{
				echo "instance description problem.  sleeping...";

				// Wait 10 seconds before trying again.
				sleep(10);							
			}
		}	

		// Return instance objects
		return $this->response->body->reservationSet;
	}

	// ===========================================================================// 
	// ! Github pull new code                                                     //
	// ===========================================================================//

	// Update the app to the latest code revision
	public function updateApp()
	{		
		// Updated code to latest revision in repo
		$changes = shell_exec("git pull git@github.com:iamjoshua/scraper.git Development");

		// If new revision downloaded
		if(strpos($changes, "Updating") !== FALSE)
		{
			// Create a new bootstrap for new code
			exec('php /home/ec2-user/scraper/server.php bootstrap &> /dev/null &');

			// Kill current bootstrap
			exit('new code. restarting...');
		}
	}

	// ===========================================================================// 
	// ! Boot methods                                                             //
	// ===========================================================================//    		

	// Run redis database
	private function runRedis()
	{
		exec("/home/ec2-user/redis/src/redis-server /home/ec2-user/redis/redis.conf");
	}	

	// Associate an elastic ip with an instance
	private function assignIp($ip)
	{
		// Attach the elastic ip provided to this instance
		$this->ec2->associate_address($this->instanceId, $ip);
		
		// If request failed
		if(!$this->response->isOK())
		{
			echo "Can't attach elastic ip\n";
		}		
	}	

	// ===========================================================================// 
	// ! Finalize bootstrap                                                       //
	// ===========================================================================//10
	// Save all settings to config file for use
	private function saveType()
	{
		// Build config file
		$config = "<?php \n\n";
		$config.= "// This config file is auto generated on boot.\n\n";
		$config.= 'define("INSTANCE_TYPE", "'.$this->instanceType.'");'."\n";
		$config.= 'define("INSTANCE_NAME", "'.$this->instanceName.'");'."\n";

		// If bootstrapping the development server
		if($this->instanceDev)
		{
			$config.= 'define("DEV", TRUE);'."\n";
		}	

		// Write config file to config folder
		file_put_contents("config/instance.config.php", $config);
	}	

	// Modify supervisord for this specific instance
	private function editSupervisord()
	{
		// If this is a worker instance
		if($this->instanceType == "client")
		{
			// Add instance specific daemon info
			$supervisord = "[program:Client]\n";
			$supervisord.= "command=php /home/ec2-user/scraper/server.php client\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/scraper/data/logs/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=1\n"; 
			$supervisord.= "process_name=%(process_num)s\n";					
			
			// Add instance specific daemon info
			$supervisord.= "[program:Cron]\n";
			$supervisord.= "command=php /home/ec2-user/scraper/server.php cron\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/scraper/data/logs/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=1\n"; 
			$supervisord.= "process_name=%(process_num)s\n";					
		}
		// If this instance is for bing
		elseif($this->instanceType == "bing")
		{
			// Add workers for ranking updates
			$supervisord = "[program:Bing]\n";
			$supervisord.= "command=php /home/ec2-user/scraper/server.php worker keywords bing daily\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/scraper/data/logs/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=10\n"; 
			$supervisord.= "process_name=%(process_num)s\n";					
		}
		// All other instance types
		elseif($this->instanceType == "google")
		{	
			// Add workers for hourly google updates
			$supervisord = "[program:GoogleHourly]\n";
			$supervisord.= "command=php /home/ec2-user/scraper/server.php worker google %(process_num)s\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/scraper/data/logs/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=5\n"; 
			$supervisord.= "process_name=%(program_name)s_%(process_num)02d\n\n"; 																	
		}				
		// If this instance is for bing
		elseif($this->instanceType == "domains")
		{
			// Add workers for domain pagerank
			$supervisord = "[program:prDaily]\n";
			$supervisord.= "command=php /home/ec2-user/scraper/server.php worker domains pr daily\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/scraper/data/logs/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=5\n"; 
			$supervisord.= "process_name=%(process_num)s\n"; 	
			
			// Add workers for domain pagerank
			$supervisord.= "[program:backlinksDaily]\n";
			$supervisord.= "command=php /home/ec2-user/scraper/server.php worker domains backlinks daily\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/scraper/data/logs/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=5\n"; 
			$supervisord.= "process_name=%(process_num)s\n"; 	
			
			// Add workers for domain pagerank
			$supervisord.= "[program:alexaDaily]\n";
			$supervisord.= "command=php /home/ec2-user/scraper/server.php worker domains alexa daily\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/scraper/data/logs/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=5\n"; 
			$supervisord.= "process_name=%(process_num)s\n"; 				
		}	
		// All other instance types
		elseif($this->instanceType == "new")
		{	
			// Add workers for hourly google updates
			$supervisord = "[program:GoogleNew]\n";
			$supervisord.= "command=php /home/ec2-user/scraper/server.php worker keywords google new\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/scraper/data/logs/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=5\n"; 
			$supervisord.= "process_name=%(program_name)s_%(process_num)02d\n\n"; 

			// Add workers for daily google updates
			$supervisord.= "[program:BingNew]\n";
			$supervisord.= "command=php /home/ec2-user/scraper/server.php worker keywords bing new\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/scraper/data/logs/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=5\n"; 
			$supervisord.= "process_name=%(program_name)s_%(process_num)02d\n\n"; 
			
			// Add workers for domain pagerank
			$supervisord.= "[program:prNew]\n";
			$supervisord.= "command=php /home/ec2-user/scraper/server.php worker domains pr new\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/scraper/data/logs/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=5\n"; 
			$supervisord.= "process_name=%(process_num)s\n"; 	
			
			// Add workers for domain pagerank
			$supervisord.= "[program:backlinksNew]\n";
			$supervisord.= "command=php /home/ec2-user/scraper/server.php worker domains backlinks new\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/scraper/data/logs/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=5\n"; 
			$supervisord.= "process_name=%(process_num)s\n"; 	
			
			// Add workers for domain pagerank
			$supervisord.= "[program:alexaNew]\n";
			$supervisord.= "command=php /home/ec2-user/scraper/server.php worker domains alexa new\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/scraper/data/logs/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=5\n"; 
			$supervisord.= "process_name=%(process_num)s\n"; 					
		}
		
		// Write new supervisord config file
		file_put_contents("core/supervisord.core.conf", $supervisord);

		// Run supervisord daemon
		exec("/usr/bin/supervisord &> /dev/null &");
	}	
}			