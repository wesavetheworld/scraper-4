<?php  if(!defined('HUB')) exit('No direct script access allowed\n');

// ******************************* INFORMATION ******************************//

// **************************************************************************//
//  
// ** CLIENT - Acts like cron. Fires off actions based on the current time.
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-07-12
// ** @access	private
// ** @param	
// ** @return	Loops indefinitely and executes new processes when needed     
//  	
// ***************************************************************************//

// ********************************** START **********************************// 

class clientCore 
{    

	// Bing instance status
	private $bingStatus = false;
	
	function __construct()
	{
		// Include gearman class for job status updates
	 	require_once('classes/gearman.class.php');
	 	
		// Include the amazon SDK
		require_once('classes/amazon/sdk.class.php');
	 			
		// The main loop
		$this->daemon();
	}	
	// ===========================================================================// 
	// ! Infinite daemon loop                                                     //
	// ===========================================================================//
	
	// The main loop that acts as a daemon
	private function daemon()
	{
		// Loop forever
		while(TRUE)
		{
			// Check system status
			utilities::checkStatus();

			// If first hour of the day
			if(date("H:i") == "00:00")
			{
				// Run all daily tasks
				$this->daily();			
			}

			// The first min of every hour but the first
			if(date("i") == "00")
			{
				// Run all hourly tasks
				$this->hourly();
			}

			// Every 2 minutes
			if(intval(ltrim(date("i"), "0")) % 2 == 0)
			{
				// Run all every other minute tasks
				$this->twoMinutes();			
			}

			// Run all minute tasks 
			$this->minute();	

			// Wait for next loop
			$this->meditate();	
		}
	}
	
	// ===========================================================================// 
	// ! Time based functions                                                     //
	// ===========================================================================//
	
	// Tasks that should be run daily
	private function daily()
	{
		// Update all daily keywords for google
		$this->run("client", "rankings 100 google daily");	
		
		// Update all daily keywords for bing
		$this->run("client", "rankings 100 bing daily");	

		// Turn on bing servers
		$this->bing('start');		
		
		// Update domain stats
		$this->domainStats();			
	}	
	
	// Tasks that should be run hourly
	private function hourly()
	{
		// Get current job Queue total
		$queue = $this->checkJobQueue('rankingsGoogle');
		
		// If job queue is empty
		if(!$queue)
		{				
			// Update hourly keyword rankings for google
			$this->run("client", "rankings 100 google hourly");															
		}	
		// Jobs have not finished from last hour
		else
		{
			// Notify admin of overlap
			utilities::reportErrors("Hourly scraping overlap: $queue jobs remaining");	
			
			// Log overlap notice				
			utilities::notate("Job queue overlap. $queue jobs remaining. Skipped updates this hour", "clientd.log");		  		   	
		}	
		
		// If bing instances are on
		if($this->bingStatus)	
		{
			// Get number of bing jobs
			$queue = $this->checkJobQueue('rankingsBing');			
			
			// If job queue is empty
			if(!$queue)
			{				
				// Update hourly keyword rankings for google
				$this->bing('stop');															
			}	
		}				
	}	
	
	// Tasks that should be run every 2 minutes
	private function twoMinutes()
	{
		// Update domain stats
		$this->domainStats('new');	

		// Check for new keywords to update for google
		$this->run("client", "rankingsNew 100 google");
		
		// Check for new keywords to update for bing
		$this->run("client", "rankingsNew 100 bing");			
	}
	
	// Tasks that should be run every minute
	private function minute()
	{
		// Run cron tasks
		$this->run("tasks");		
	}		

	// ===========================================================================// 
	// ! Supporting functions                                                     //
	// ===========================================================================//	

	private function getInstances()
	{
		// Create a new amazon object
		$ec2 = new AmazonEC2();

		// Set the region to access instances
		$ec2->set_region('us-west-1');
				
		// Get info on all worker instances
		$response = $ec2->describe_instances();		

		// If request failed
		if(!$response->isOK())
		{
			// Send admin error message
			utilities::reportErrors("Can't load instance data"); 
			
	  		// Finish execution
			utilities::complete();
		}	

		// Return instance objects
		return $response->body->reservationSet;		
	}

	// Manage bing servers
	private function bing($action)
	{
		return false;
		
		// Filter instances to only bing
		$opt = array(
				    'Filter' => array(
				        array('Name' => 'tag-value', 'Value' => 'bing')
				    )
				);

		// Get a list of all bing instances
		$instances = $this->getInstances($opt);

		// Loop through selected instances
		foreach($instances->item as $items)
		{	
			foreach($items->instancesSet->item as $instance)
			{
				// Add instance id to array
				$id = (array)$instance->instanceId[0];
				$instanceIds[] = $id[0];
			}
		}	

		// If instance ids are returned
		if(count($instanceIds) > 0)
		{
			// Modify bing instance statuses by instanceIds
			$this->manageInstance($instanceIds, $action);	
			
			// Log overlap notice				
			utilities::notate("Bing instances modified: $action", "clientd.log");	
		}	
		
		// If starting bing instances
		if($action == "start")
		{
			// Set bing status to on
			$this->bingStatus = true; 
		}	
		// If stopping bing instances
		elseif($action == "stop")
		{
			// Set bing status to off
			$this->bingStatus = false; 			
		}
	}

	// Check for oustanding jobs stilled queued
	private function checkJobQueue($type)
	{
		// Instantiate new gearman call
		$jobServer = new jobServerStatus(JOB_SERVER_IP);	

		// Retrieve list of current jobs in queue
		$status = $jobServer->getStatus();	
		
		// Return specified job type job queue total
		return $status['operations'][$type]['total'];	
	}

	// Manage ec2 instance states (start,stop)
	private function manageInstance($instanceId, $function)
	{
		// Create a new amazon object
		$ec2 = new AmazonEC2();

		// Set the region to access instances
		$ec2->set_region('us-west-1');	

		// Create function's function name
		$function = $function."_instances";

		// Perform requested action
		if($ec2->$function($instanceId))
		{
			// The process was a success
			return true;
		}
	}

	// Check for any newly added keywords/domains
	private function checkNew($type)
	{
		// Check if item status file exists
		if(file_exists($type))
		{
			// Load file containing new items(keywords or domains) count
			$type = file_get_contents($type);
			
			// If there are new items
			if($type)
			{
				return true;
			}	
		}	
	}

	// Update all domain's stats
	private function domainStats($new = false)
	{
		// Update all domain's pagerank
		$this->run("client", "pr 100 $new");
		
		// Update all domain's backlinks			
		$this->run("client", "backlinks 100 $new");

		// Update all domain's alexa rankings
		$this->run("client", "alexa 100 $new");		
	}	
	
	// ===========================================================================// 
	// ! Main daemon functions                                                    //
	// ===========================================================================//	

	// Determine amount of time to wait before daemon loops again
	private function meditate()
	{
		// Get remaining seconds in current minute
		//$sleep = intval(60 - intval(ltrim(date("i"), "0")));
		
		// $sleep = date("i") + 1;

		// $sleep = date("H:$sleep");
		// utilities::notate("sleeping until $sleep", "clientd.log");		  		   	 				

		// $sleep = strtotime($sleep);

		// // Wait for the remaining seconds in the minute
		// time_sleep_until($sleep);		
		sleep(60);
		// utilities::notate("starting at ".date("i"), "clientd.log");		  		   	 				
	}

	// Execute bash command that detaches from daemon
	private function run($controller, $options = false)
	{
		// Build the command to execute
		$command = "php hub.php $controller $options > /dev/null 2>/dev/null &";

		// Execute command given
		exec($command);

		utilities::notate("command: $controller $options", "clientd.log");		  		   	 				
	}
}	