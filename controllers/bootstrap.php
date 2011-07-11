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
		//$ec2 = new AmazonEC2();
		$this->listInstances();

		// Sync all worker instances
		$this->syncWorkers();

		// Show completion status
		echo "Bootstrapping complete. \n";
	}

	// Get list of EC2 instance info
	private function listInstances()
	{
		// Create a new amazon object
		$ec2 = new AmazonEC2();

		// Set the region to access instances
		$ec2->set_region('us-west-1');		

		// Get info on all worker instances
		$this->response = $ec2->describe_instances(array(
		    'Filter' => array(
				array('Name' => 'tag-value', 'Value' => 'worker')
		    )
		));		

		// If request failed
		if(!$this->response->isOK())
		{
			// End the script
			exit("error \n");
		}	
	}
	
	// Sync all worker instances
	private function syncWorkers()
	{
		// Loop through each instance returned
		foreach($this->response->body->reservationSet->item as $inst)
		{
			// Define insance private ip
			$ip = $inst->instancesSet->item->privateIpAddress;

			// Sync external worker server
			echo exec("rsync -avz /home/ec2-user/ ec2-user@$ip:/home/ec2-user/");

			// Show syncing status 
			echo $inst->instancesSet->item->instanceId." : synced \n";
		}
		
	} 	
}			