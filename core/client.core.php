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

		// Boot up bing workers
		//$this->bootBing();
		
		// Update all daily keywords for bing
		$this->run("client", "rankings 100 bing daily");	
		
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

	// // boot bing instances
	// private function bootBing()
	// {
	// 	// Create a new amazon object
	// 	$ec2 = new AmazonEC2();

	// 	// Set the region to access instances
	// 	$ec2->set_region('us-west-1');	
	// }

	// // boot bing instances
	// private function shutDownBing()
	// {
	// 	// Create a new amazon object
	// 	$ec2 = new AmazonEC2();

	// 	// Set the region to access instances
	// 	$ec2->set_region('us-west-1');	
	// }	

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
		
		$sleep = date("i") + 1;

		$sleep = date("H:$sleep");
		
		utilities::notate("sleeping until $sleep", "clientd.log");		  		   	 				

		$sleep = strtotime($sleep);

		// Wait for the remaining seconds in the minute
		time_sleep_until($sleep);		

		utilities::notate("starting at ".date("i"), "clientd.log");		  		   	 				
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