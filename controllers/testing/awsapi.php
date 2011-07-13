<?php  if(!defined('HUB')) exit('No direct script access allowed\n');
 
// ******************************* INFORMATION *******************************//

// ***************************************************************************//
//  
// ** RANKINGS - Scrapes search engines for rankings. Required settings can be 
// ** set in config/rankings.php 
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-06-21
// ** @access	private
// ** @param	
// ** @return	constants for application 
//  	
// ***************************************************************************//

// ********************************** START **********************************//

class awsapi 
{   
	
	function __construct()
	{
   		// Include the amazon SDK
		require_once 'classes/amazon/sdk.class.php';
	}
    

	public function awsapi()
	{
		// Save a file to e3
		$this->createS3Object();

		//Get a list of e3 buckets
		//$this->getE3Buckets();

		// List all instances in account
		//$this->listInstances();
		
		// Stop the supplied instance
		//$this->stopInstance('i-2ce3fb68');

		// Start the supplied instance
		//$this->startInstance('i-2ce3fb68');
	
	} 

	// Turn off a server instance
	public function startInstance($id)
	{
		// Instantiate a new amazon object
		$ec2 = new AmazonEC2();

		// Set the region to access instances
		$ec2->set_region('us-west-1');

		// Stop the instance with provided id
		$response = $ec2->start_instances($id);

		// Success?
		var_dump($response->isOK());
	}			

	// Turn off a server instance
	public function stopInstance($id)
	{
		// Instantiate a new amazon object
		$ec2 = new AmazonEC2();

		// Set the region to access instances
		$ec2->set_region('us-west-1');

		// Stop the instance with provided id
		$response = $ec2->stop_instances($id);

		// Success?
		var_dump($response->isOK());
	}

	public function listInstances()
	{
		$ec2 = new AmazonEC2();

		// Set the region to access instances
		$ec2->set_region('us-west-1');		

		$response = $ec2->describe_instances(array(
		    'Filter' => array(
				array('Name' => 'root-device-type', 'Value' => 'ebs')
		    )
		));		

		if($response->isOK())
		{
			return $response;
		}
		else
		{
			echo "error: ".$response->isOK()."\n";
		}	
	}

	// Get a list of all E3 buckets
	public function getE3Buckets()
	{
		$s3 = new AmazonS3();

		$response = $s3->list_buckets();

		if($response->isOK())
		{
			print_r($response);
		}
		else
		{
			echo "error: ".$response->isOK()."\n";
		}			
	}

	// Get a list of all E3 buckets
	public function createS3Object()
	{
		$s3 = new AmazonS3();

		$i = 0;
		while($i != 100)
		{
			$response = $s3->create_object('searches', 'search-'.$i.'.html', array(
				'body' => 'This is my body text.'
			));

			if(!$response->isOK())
			{
				echo "error";
				break;
			}
			
			$i++;
		}			
	

	}	
	
}