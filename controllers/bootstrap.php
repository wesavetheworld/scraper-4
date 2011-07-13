<?php  if(!defined('HUB')) exit('No direct script access allowed\n');


class bootstrap 
{    
	
	function __construct()
	{
		// Include the amazon SDK
		require_once 'classes/amazon/sdk.class.php';

		// Create a new amazon object
		$this->ec2 = new AmazonEC2();		

		// Load the current instances id
		$this->getInstanceId();

		// Load the current instances description (client/worker)
		$this->getInstanceType();
	}
	
	public function bootstrap()
	{ 

		// If this is a client instance
		if($this->instanceType == "client")
		{
			// Assign the client elastic ip to this instance
			$this->assignIp(CLIENT_IP);

			// Start NFS file sharing for data folder
			$this->startNfs();

			// Sync all worker instances
			$this->syncWorkers();			
		}
		// If this is a worker instance
		elseif($this->instanceType == "worker")
		{
	    	// Sync data folder with client
	    	$this->syncData();			
		}

		$this->getInstances(array('Name' => 'tag-value', 'Value' => 'client'));

		// Instantiate a new amazon object
		$this->getInstances(array('Name' => 'tag-value', 'Value' => 'worker'));

		// Sync all worker instances
		$this->syncWorkers();

		// Show completion status
		echo "Bootstrapping complete. \n";
	}

	// ===========================================================================// 
	// ! Public methods                                                          //
	// ===========================================================================//
	    
    public function getJobServer()
    {
    	// Get EC2 job server info
		$jobServer = $this->getInstances(array('Filter' => array(array('Name' => 'tag-value', 'Value' => 'jobServer'))));

		return $jobServer->item->instancesSet->item->privateIpAddress;
    }

    public function syncData()
    {
    	// Get the client servers ip
		$clientServer = $this->getInstances(array('Filter' => array(array('Name' => 'tag-value', 'Value' => 'client'))));
		$ip = $clientServer->item->instancesSet->item->privateIpAddress;

		// Unmount directory incase its already mounted
		exec("sudo umount -l /home/ec2-user/scraper/support/data");
		
		// Execute mounting of client data directory
		exec("sudo mount -t nfs -o rw $ip:/home/ec2-user/scraper/support/data /home/ec2-user/scraper/support/data");


		return true;
    }

	// ===========================================================================// 
	// ! Private methods                                                          //
	// ===========================================================================//
	
	// Load the current instances id
	private function getInstanceId()
	{
		// Get the instance id of the currently running instance
		$this->instanceId = exec("sudo wget -q -O - http://169.254.169.254/latest/meta-data/instance-id");

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
		
		// Set the instance type from the return data
		$this->instanceType = $this->response->body->reservationSet->item->instancesSet->item->tagSet->item->value;		
	}

	// Get list of EC2 instance info
	private function getInstances($opt)
	{
		// Set the region to access instances
		$this->ec2->set_region('us-west-1');		

		// Get info on all worker instances
		$this->response = $ec2->describe_instances($opt);		

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
	
	// Start NFS file sharing for data folder	
	private function startNfs()
	{
		// Bind ports for nfs
		exec("sudo /etc/init.d/rpcbind start");
		
		// Turn on nfs folder sharing for the data folder 
		exec("sudo /etc/init.d/nfs start");	
	}

	// Sync all worker instances
	private function syncWorkers()
	{
		// Loop through each instance returned
		foreach($this->response->body->reservationSet->item as $inst)
		{
			// Define insance private ip
			$ip = $inst->instancesSet->item->privateIpAddress;

			// Add instance private ip to sync data 
			$sync .= 'sync{default.rsyncssh, source="/home/ec2-user/scraper/", host="ec2-user@'.$ip.'", targetdir="/home/ec2-user/scraper/", rsyncOps="-avz", exclude = {"/support/data"}}';

			// Add instance to client export file for data sync exclusion
			$export .= "/home/ec2-user/scraper/support/data/ $ip(rw,no_root_squash)\n";
		}

		// Write to the lyxync exclude file
		file_put_contents(LSYNC_EXCLUDE, $export);

		// Re export new locations added to exports file
		exec("sudo exportfs -ra");

		// Load the Lsynce configuration file
		$config = file_get_contents(LSYNC_CONFIG);

		// Isolate the host part of the file
		$config = explode("settings", $config);

		// Add the new hosts to the config file
		$config = $sync."\nsettings".$config[1];

		// Create new config file with new hosts
		file_put_contents(LSYNC_CONFIG, $config);

		// Restart Lsync with new settings
		exec("sudo /etc/init.d/lsyncd restart");
	} 	
}			