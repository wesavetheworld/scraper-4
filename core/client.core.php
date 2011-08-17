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
	
	// Include dependencies on instantiation
	function __construct()
	{
		// Include gearman class for job status updates
	 	require_once('classes/gearman.class.php');
	}	

	// ===========================================================================// 
	// ! Infinite daemon loop                                                     //
	// ===========================================================================//
	
	// The main loop that acts as a daemon
	public function daemon()
	{	
		// Loop forever 
		while(TRUE)
		{
			// Log time for current task loop
			utilities::notate("Actions at: ".date("H:i:s"), "clientd.log");
						
			// Check system status
			utilities::checkStatus();

			// If first hour of the day
			if(date("H:i") == "00:00")
			{
				// Run all daily tasks
				$this->hourOne();			
			}

			// The first min of every hour but the first
			if(date("i") == "00")
			{
				// Run all hourly tasks
				$this->hourAll();
			}

			// Every 2 minutes
			if(intval(ltrim(date("i"), "0")) % 2 == 0)
			{
				// Run all every other minute tasks
				$this->minuteEveryTwo();			
			}

			// Run all minute tasks 
			$this->minuteAll();			  		   	 				

			// Wait for next loop
			$this->meditate();	
		}
	}
	
	// ===========================================================================// 
	// ! Time based functions                                                     //
	// ===========================================================================//
	
	// Tasks that should be run daily
	private function hourOne()
	{
		// Remove old saved search files
		$this->run("tasks", "cleanSearchDirectory");	
		
		// Clean up the server logs
		$this->run("tasks", "cleanLogs");
				
		// Update all daily keywords for google
		$this->run("client", "rankings 100 google daily");	
		
		// Update all daily keywords for bing
		$this->run("client", "rankings 100 bing daily");			
		
		// Update domain stats
		$this->domainStats();			
	}	
	
	// Tasks that should be run hourly
	private function hourAll()
	{
		// Reset proxy stats in db
		$this->run("tasks", "proxyReset");	
		
		// Check that all keywords are following their schedules
		$this->run("tasks", "checkSchedules");	
		
		// Check in any old keywords		
		$this->run("tasks", "keywordCheckIn");	

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
				//$this->bing('stop');															
			}	
		}				
	}	
	
	// Tasks that should be run every 2 minutes
	private function minuteEveryTwo()
	{
		// Update domain stats
		$this->domainStats('new');	

		// Check for new keywords to update for google
		$this->run("client", "rankingsNew 100 google");
		
		// Check for new keywords to update for bing
		$this->run("client", "rankingsNew 100 bing");			
	}
	
	// Tasks that should be run every minute
	private function minuteAll()
	{
		
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

	// Execute bash command that detaches from daemon
	private function run($controller, $options = false)
	{
		// Build the command to execute
		$command = "php hub.php $controller $options > /dev/null 2>/dev/null &";

		// Execute command given
		exec($command);

		// Log current command
		utilities::notate("command: $controller $options", "clientd.log");		  		   	 				
	}

	// Determine amount of time to wait before daemon loops again
	private function meditate()
	{	
		// Get remaining seconds in current minute
		$sleep = intval(60 - intval(ltrim(date("s"), "0")));
		
		// Sleep until next minute
		sleep($sleep);		  		   	 					
	}	
}	