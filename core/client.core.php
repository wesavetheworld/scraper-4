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
				// Update all daily keywords
				$this->run("client", "rankings 100 google daily");	
				
				// Update domain stats
				$this->domainStats();				
			}

			// The first min of every hour but the first
			if(date("i") == "00")
			{
				// Get current job Queue total
				$queue = $this->checkJobQueue('rankings');
				
				// If job queue is empty
				if(!$queue)
				{				
					// Update hourly keyword rankings
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

			// Every 2 minutes
			if(intval(ltrim(date("i"), "0")) % 2 == 0)
			{
				// Update domain stats
				$this->domainStats('new');	
			}

			// Check for any new domains
			// elseif($this->checkNew(NEW_DOMAINS_FILE))
			// {
			// 	// Update domain stats
			// 	//$this->domainStats();		
			// }	

			// // Check for any new keywords
			// if($this->checkNew(NEW_KEYWORDS_FILE))
			// {
			// 	// Update hourly keyword rankings
			// 	$this->run("client", "google all 100 new");			
			// }
			
			// Check for new keywords to update
			$this->run("client", "rankingsNew 100 google");
			
			// Check for keywords needing calibration
			//$this->run("client", "rankingsCalibrate 100 google");							
			
			// Run cron tasks
			$this->run("tasks");	

			// Wait 1 min then loop again
			sleep(60);	
		}
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