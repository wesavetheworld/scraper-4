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
echo svn_update(realpath('working-copy'));
die('end');

		// Include the amazon SDK
		require_once 'classes/amazon/sdk.class.php';

		// Create a new amazon object
		$this->ec2 = new AmazonEC2();

		// Load the current instances id
		$this->getInstanceId();

		// Load the current instances description (client/worker)
		$this->getInstanceType();

		echo "type: ".$this->instanceType."\n";
	}
	
	// Run boot functions based on instance identity
	public function bootstrap()
	{ 
		// Get the latest code
		$this->checkoutApp();

		die('the end');

		// If this is the job server
		if($this->instanceType == "jobServer")
		{
			// Run gearman daemon
			$this->runGearman();
		}
		// All othere instance types
		else
		{
			// Check for jobServer before continuing 
			$this->getJobServer();
					
			// If this is a client instance
			if($this->instanceType == "client")
			{
				// Assign the client elastic ip to this instance
				$this->assignIp(CLIENT_IP);

				// Start NFS file sharing for data folder
				//$this->startNfs();

				// Sync all worker instances
				$this->syncInstances();			
			}
			// If this is a worker instance
			elseif($this->instanceType == "worker")
			{
		    	// Mount client servers data folder locally
		    	$this->mountDataFolder();			
			}
		}	

		// Return the correct controller to load next
		return $this->instanceType;			
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
		}	
	}

	// Get list of EC2 instance info
	private function getInstances($opt)
	{
		// Set the region to access instances
		$this->ec2->set_region('us-west-1');		

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

	private function checkoutApp()
	{
		// Update scraper app
		$changes = shell_exec("svn update /home/ec2-user/");

		echo $chages;
	}

	// ===========================================================================// 
	// ! File syncing methods                                                     //
	// ===========================================================================//

	// Start NFS file sharing for data folder	
	private function startNfs()
	{
		// Bind ports for nfs
		exec("/etc/init.d/rpcbind start");
		
		// Turn on nfs folder sharing for the data folder 
		exec("/etc/init.d/nfs start");	
	}

	// Sync all worker instances
	private function syncInstances()
	{
		// Get array of all ec2 instances currently running
		$this->getInstances(array('Filter' => array(array('Name' => 'tag-value', 'Value' => 'scraper'))));

		// Loop through each instance returned
		foreach($this->response->body->reservationSet->item as $inst)
		{
			// Dont add client ip(self) to sync list (obviously)
			if($inst->instancesSet->item->tagSet->item->value != "client")
			{
				// Define insance private ip
				$ip = $inst->instancesSet->item->privateIpAddress;

				// Add instance private ip to sync data 
				$sync .= 'sync{default.rsyncssh, source="/home/ec2-user/scraper/", host="ec2-user@'.$ip.'", targetdir="/home/ec2-user/scraper/", rsyncOps="-avz", exclude = {"/support/data"}}';

				// Add instance to client export file for data sync exclusion
				$export .= "/home/ec2-user/scraper/support/data/ $ip(rw,no_root_squash)\n";
			}	
		}

		// Write to the lyxync exclude file
		file_put_contents(LSYNC_EXCLUDE, $export);

		// Re export new locations added to exports file
		exec("exportfs -ra");

		// Load the Lsynce configuration file
		$config = file_get_contents(LSYNC_CONFIG);

		// Isolate the host part of the file
		$config = explode("settings", $config);

		// Add the new hosts to the config file
		$config = $sync."\nsettings".$config[1];

		// Create new config file with new hosts
		file_put_contents(LSYNC_CONFIG, $config);

		// Restart Lsync with new settings
		exec("/etc/init.d/lsyncd restart");
	} 	

	// Mount the client server's data folder locally for read/writes
    public function mountDataFolder()
    {
    	// Get client server info
		$clientServer = $this->getInstances(array('Filter' => array(array('Name' => 'tag-value', 'Value' => 'client'))));
		
		// Extract the client server's ip
		$ip = $clientServer->item->instancesSet->item->privateIpAddress;

		// Unmount directory incase its already mounted
		exec("umount -l /home/ec2-user/scraper/support/data");

		// Execute mounting of client data directory
		exec("mount -t nfs -o rw $ip:/home/ec2-user/scraper/support/data /home/ec2-user/scraper/support/data");
    }
    
	// ===========================================================================// 
	// ! Boot methods                                                             //
	// ===========================================================================//    		
	
	// Run gearman job server
	private function runGearman()
	{
		exec("/usr/local/sbin/gearmand -d");
	}

	// ===========================================================================// 
	// ! Public methods                                                           //
	// ===========================================================================//
	    
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
}			