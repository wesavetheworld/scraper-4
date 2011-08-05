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

class client 
{    
	
	function __construct()
	{
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

				// Update all hourly keywords
				$this->run("client", "rankings 100 google hourly");				
				
				// Update domain stats
				$this->domainStats();
			}
			// If not the first hour and its the first min
			elseif(date("H") != "00" && date("i") == "00" || date("i") == "18")
			{
				// Update hourly keyword rankings
				$this->run("client", "rankings 100 google hourly");									
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
			
			// Update all hourly keywords
			$this->run("client", "rankings 100 google new");				
			
			// Run cron tasks
			$this->run("tasks");	

			// Wait 1 min then loop again
			sleep(60);	
		}
	}	

	// ===========================================================================// 
	// ! Supporting functions                                                     //
	// ===========================================================================//	

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
	private function domainStats()
	{
		// Update all domain's pagerank
		$this->run("client/stats", "pr");
		
		// Update all domain's backlinks			
		$this->run("client/stats", "backlinks");

		// Update all domain's alexa rankings
		$this->run("client/stats", "alexa");		
	}	

	// Execute bash command that detaches from daemon
	private function run($controller, $options = false)
	{
		// Build the command to execute
		$command = "php hub.php $controller $options >> /home/ec2-user/core/$controller.log &";

		// Execute command given
		exec($command);

		utilities::notate("command: $command", "clientd.log");		  		   	 				
	}
}	