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
	
	function __construct()
	{
		// Check repo for any new revisions
		$this->updateApp();

		// Include the amazon SDK
		require_once('classes/amazon/sdk.class.php');

		// Create a new amazon object
		$this->ec2 = new AmazonEC2();

		// Set the region to access instances
		$this->ec2->set_region('us-west-1');	
		
		// do it
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

		// Log status
		utilities::notate("Instance type: ".$this->instanceType);	

		// Save all server settings to config files
		$this->saveType();	  		
		
    	// Mount client servers data folder locally
    	$this->mountDataFolder();	 				

		// If this is the job server
		if($this->instanceType == "jobServer")
		{
			// Assign the jobServer elastic ip to this instance
			$this->assignIp(JOB_SERVER_IP);	

			// Run gearman daemon
			$this->runGearman();
		}
		// All othere instance types
		else
		{
			// Set up which core daemon supervisord will controll
			$this->editSupervisord(); 
						
			// Check for jobServer before continuing 
			$this->getJobServer();
					
			// If this is a client instance
			if($this->instanceType == "client")
			{
				// Assign the client elastic ip to this instance
				$this->assignIp(CLIENT_IP);			
			}
			// If this is a worker instance
			elseif($this->instanceName == "worker1")
			{
				// Assign the worker elastic ip to this instance
				$this->assignIp(WORKER_IP);	
			}
		}	
	}

	// ===========================================================================// 
	// ! Instance identification methods                                          //
	// ===========================================================================//
	
	// Load the current instances id
	private function getInstanceId()
	{
		// Get the instance id of the currently running instance
		$this->instanceId = exec("wget -q -O - http://169.254.169.254/latest/meta-data/instance-id");

		// If no instance id
		if(!$this->instanceId)
		{
			// Send admin error message
			utilities::reportErrors("Can't load instance id"); 
			
	  		// Finish execution
			utilities::complete();				
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
		}	
	}

	// Get the local ip of the jobServer
    private function getJobServer()
    {
    	// While job server is not running
    	while($jobServerStatus != "running")
    	{
	    	// Get EC2 job server info
			$jobServer = $this->getInstances(array('Filter' => array(array('Name' => 'tag-value', 'Value' => 'jobServer'))));

			// Set the status of the jobServer
			$jobServerStatus = $jobServer->item->instancesSet->item->instanceState->name;

			// If server status is offline
			if($jobServerStatus != "running")
			{	
				// Send admin error message
				utilities::reportErrors("Job server is not online.", TRUE);

				// Sleep for 1 minute and try again
				sleep(60);
			}
		}	

		// Set the jobServer ip constant for use in client and worker
		define('JOB_SERVER', $jobServer->item->instancesSet->item->privateIpAddress);		
    }

	// Get list of EC2 instance info
	private function getInstances($opt)
	{		
		// Get info on all worker instances
		$this->response = $this->ec2->describe_instances($opt);		

		// If request failed
		if(!$this->response->isOK())
		{
			// Send admin error message
			utilities::reportErrors("Can't load instance data"); 
			
	  		// Finish execution
			utilities::complete();
		}	

		// Return instance objects
		return $this->response->body->reservationSet;
	}

	// ===========================================================================// 
	// ! SVN repo methods                                                         //
	// ===========================================================================//

	// Update the app to the latest code revision
	public function updateApp()
	{
		// Updated code to latest revision in repo
		$changes = shell_exec("svn update /home/ec2-user/");

		// If new revision downloaded
		if(strpos($changes, "Updated"))
		{
			// Create a new bootstrap for new code
			exec('php /home/ec2-user/server.php bootstrap &');

			// Kill current bootstrap
			exit('new code. restarting...');
		}
	}
    
	// ===========================================================================// 
	// ! Boot methods                                                             //
	// ===========================================================================//    		
	
	// Mount the shared data folder
	private function mountDataFolder()
	{
		// Incase already mounted, unmount first
		exec("umount ".DATA_DIRECTORY);

   		// Mount the shared data drive 
		exec("mount -t glusterfs ".DATA_SERVER." ".DATA_DIRECTORY);		

		// // While data server is not running
  //   	while($dataStatus != "mounted")
  //   	{
  //   		// Mount the shared data drive (returns false if success)
		// 	$dataStatus = shell_exec("mount -t glusterfs ".DATA_DIRECTORY.":/gluster-data /home/ec2-user/support/data");

		// 	// If response is not blank (failed)
		// 	if($dataStatus)
		// 	{	
		// 		// Send admin error message
		// 		utilities::reportErrors("Can't mount data directory", TRUE);

		// 		// Sleep for 1 minute and try again
		// 		sleep(60);
		// 	}
		// 	// Response was blank (success)
		// 	else
		// 	{
		// 		echo "passed";
		// 		$dataStatus = "mounted";
		// 	}			
		// }		
	}

	// Run gearman job server
	private function runGearman()
	{
		exec("/usr/local/sbin/gearmand -d");
	}

	// Associate an elastic ip with an instance
	private function assignIp($ip)
	{
		// Attach the elastic ip provided to this instance
		$this->ec2->associate_address($this->instanceId, $ip);
		
		// If request failed
		if(!$this->response->isOK())
		{
			// Send admin error message
			utilities::reportErrors("Can't attach elastic ip"); 
			
	  		// Finish execution
			utilities::complete();
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
			$supervisord = "[program:theApp]\n";
			$supervisord.= "command=php /home/ec2-user/server.php client\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/data/logs/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=1\n"; 
			$supervisord.= "process_name=clientCore\n"; 						
		}
		// All other instance types
		elseif($this->instanceType == "jobServer")
		{	
			// Add instance specific daemon info
			$supervisord = "[program:theApp]\n";
			$supervisord.= "command=/usr/local/sbin/gearmand -d\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/data/logs/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=1\n"; 
			$supervisord.= "process_name=%(process_num)s\n\n"; 										
		}
		// If this instance is for bing
		elseif($this->instanceType == "bing")
		{
			// Add workers for ranking updates
			$supervisord = "[program:Bing]\n";
			$supervisord.= "command=php /home/ec2-user/server.php worker rankingsBing rankings\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/data/logs/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=10\n"; 
			$supervisord.= "process_name=%(process_num)s\n";
			
			// Check system status 
			$supervisord.= "[program:monitorSystem]\n";
			$supervisord.= "command=php /home/ec2-user/hub.php tasks monitorSystem bing\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/data/logs/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=1\n"; 
			$supervisord.= "process_name=%(process_num)s\n";						
		}	
		// If this instance is for bing
		elseif($this->instanceType == "domains")
		{
			// Add workers for domain pagerank
			$supervisord = "[program:pageRank]\n";
			$supervisord.= "command=php /home/ec2-user/server.php worker pr pr\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/data/logs/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=5\n"; 
			$supervisord.= "process_name=%(process_num)s\n"; 	
			
			// Add workers for domain pagerank
			$supervisord.= "[program:backlinks]\n";
			$supervisord.= "command=php /home/ec2-user/server.php worker backlinks backlinks\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/data/logs/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=5\n"; 
			$supervisord.= "process_name=%(process_num)s\n"; 	
			
			// Add workers for domain pagerank
			$supervisord.= "[program:alexa]\n";
			$supervisord.= "command=php /home/ec2-user/server.php worker alexa alexa\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/data/logs/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=5\n"; 
			$supervisord.= "process_name=%(process_num)s\n"; 
			
			// Check system status 
			$supervisord.= "[program:monitorSystem]\n";
			$supervisord.= "command=php /home/ec2-user/hub.php tasks monitorSystem domains\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/data/logs/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=1\n"; 
			$supervisord.= "process_name=%(process_num)s\n";					
		}				
		// All other instance types
		elseif($this->instanceType == "worker")
		{	
			// Add workers for ranking updates
			$supervisord = "[program:Google]\n";
			$supervisord.= "command=php /home/ec2-user/server.php worker rankingsGoogle rankings\n";
			$supervisord.= "stdout_logfile=/home/ec2-user/".$this->instanceType.".log\n";
			$supervisord.= "autostart=true\n";
			$supervisord.= "autorestart=true\n";
			$supervisord.= "numprocs=8\n"; 
			$supervisord.= "process_name=%(program_name)s_%(process_num)02d\n\n"; 

			// // Add workers for ranking updates
			// $supervisord.= "[program:GoogleNew]\n";
			// $supervisord.= "command=php /home/ec2-user/server.php worker rankingsNewGoogle rankings\n";
			// $supervisord.= "stdout_logfile=/home/ec2-user/data/logs/".$this->instanceType.".log\n";
			// $supervisord.= "autostart=true\n";
			// $supervisord.= "autorestart=true\n";
			// $supervisord.= "numprocs=1\n"; 
			// $supervisord.= "process_name=%(program_name)s_%(process_num)02d\n\n"; 
			
			// // Add workers for ranking updates
			// $supervisord.= "[program:BingNew]\n";
			// $supervisord.= "command=php /home/ec2-user/server.php worker rankingsNewBing rankings\n";
			// $supervisord.= "stdout_logfile=/home/ec2-user/data/logs/".$this->instanceType.".log\n";
			// $supervisord.= "autostart=true\n";
			// $supervisord.= "autorestart=true\n";
			// $supervisord.= "numprocs=1\n"; 
			// $supervisord.= "process_name=%(program_name)s_%(process_num)02d\n\n";
			
			// // Check system status 
			// $supervisord.= "[program:monitorSystem]\n";
			// $supervisord.= "command=php /home/ec2-user/hub.php tasks monitorSystem worker\n";
			// $supervisord.= "stdout_logfile=/home/ec2-user/data/logs/".$this->instanceType.".log\n";
			// $supervisord.= "autostart=true\n";
			// $supervisord.= "autorestart=true\n";
			// $supervisord.= "numprocs=1\n"; 
			// $supervisord.= "process_name=%(program_name)s_%(process_num)02d\n\n";																							
		}	

		// Write new supervisord config file
		file_put_contents("core/supervisord.core.conf", $supervisord);

		// Run supervisord daemon
		//exec("/usr/bin/supervisord &");
		exec("supervisord");
	}	
}			