<?php  if(!defined('HUB')) exit('No direct script access allowed\n');


class bootstrap 
{    
	
	function __construct()
	{
		// Include the amazon SDK
		require_once 'classes/amazon/sdk.class.php';
	}
	
	public function bootstrap()
	{ 
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
		$jobServer = $this->getInstances(array('Name' => 'tag-value', 'Value' => 'jobServer'));

		return $jobServer->item->instancesSet->item->privateIpAddress;
    }

    public function syncData()
    {
    	// Get the client servers ip
		$clientServer = $this->getInstances(array('Name' => 'tag-value', 'Value' => 'client'));

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

	// Get list of EC2 instance info
	private function getInstances($filter)
	{
		// Create a new amazon object
		$ec2 = new AmazonEC2();

		// Set the region to access instances
		$ec2->set_region('us-west-1');		

		// Get info on all worker instances
		$this->response = $ec2->describe_instances(array(
		    'Filter' => array(
				$filter
		    )
		));		

		// If request failed
		if(!$this->response->isOK())
		{
			// End the script
			exit("error \n");
		}	

		// Return instance objects
		return $this->response->body->reservationSet;
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
			$sync .= 'sync{default.rsyncssh, source="/home/ec2-user/scraper/", host="ec2-user@'.$ip.'", targetdir="/home/ec2-user/scraper/", rsyncOps="-avz" exclude = { "/support/data" }}';

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