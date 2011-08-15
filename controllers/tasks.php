<?php  if(!defined('HUB')) exit('No direct script access allowed\n');
 
// ******************************* INFORMATION *******************************//

// ***************************************************************************//
//  
// ** CRON - All of the tasks that need to be performed regularly for the health
// ** of the aplication.
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-06-24
// ** @access	private
// ** @param	
// ** @return	database and file updates
//  	
// ***************************************************************************//

// ********************************** START **********************************//

class tasks 
{  
 	function __construct()
	{    
		// Include settings for ranking collecting
		include('config/rankings.config.php'); 

		// Include proxy data model
		require_once('models/proxies.model.php'); 		
		
	  	// Initiate benchmarking
		utilities::benchmark();		  
	}
	  
	// ===========================================================================// 
	// ! Main routing method                                                      //
	// ===========================================================================//	
	
	public function tasks()
	{   
		// Log current state
		utilities::notate("Cron started at: ".TIME_CURRENT); 
    
		// If cron is passed an argument through CLI
		if($method = $_SERVER['argv'][2])
		{  
			// Call the method
			$this->$method();   		
		}
		// Else run normal cron tasks
		else
		{		
			// If it's 4am (00 is too soon for most daily tasks)
			if(date("H:i") == "04:00")
			{                  
				// Run all daily tasks
				$this->daily();
			} 
			
			// If it's the 30min mark
			if(date("i") == '30')
			{   
				// Run all hourly tasks
				$this->halfHour();
			}			
		
			// If it's the last minute of the hour
			if(date("i") == '59')
			{   
				// Run all hourly tasks
				$this->hourly();
			}
		
			// If it's the last minute of the day
			if(date("H:i") == "23:59")
			{                  
				// Run all daily tasks
				$this->lastMinute();
			}		
		}
		
		// Log current state
		utilities::notate("Cron complete");
		
		// Finish execution
		utilities::complete();		   
	}
	
	// ===========================================================================// 
	// ! Schedule type methods                                                    //
	// ===========================================================================//	 
	
	// Any tasks that should be run hourly
	private function halfHour()
	{ 
		// Connect to the database
		utilities::databaseConnect();
		                       
		// Check in any old keywords
		$this->keywordCheckIn();
	}			
	
	// Any tasks that should be run hourly
	private function hourly()
	{  
		// Connect to the database
		utilities::databaseConnect();
		
		// Reset all of the proxy stats
		$this->proxyReset();
		
		// Check that all keywords are following their schedules
		$this->checkSchedules();				
	}
	
	// Any tasks that should be run daily
	private function daily()
	{
		// Remove old saved search files
		$this->cleanSearchDirectory();
	}
	
	// Any tasks that should be run at the end of the day
	private function lastMinute()
	{   
		// Clean up the server logs
 	    $this->cleanLogs(); 
	}	 
	
	// ===========================================================================// 
	// ! Private Methods                                                          //
	// ===========================================================================//	   
	
	// Resets all stats for proxies (use/blocked/status)
	private function proxyReset()
	{
		// Instantiate a new proxy object
		$proxies = new proxies();

		// Rest proxy stats
		$proxies->reset();
		
		// Log current state
		utilities::notate("\tProxy stats reset"); 
	}
	
	// Remove the log files from the day
	private function cleanLogs()
	{ 
		// Loop through all status files
		foreach(glob(STATUS_DIRECTORY.'*.txt') as $status)
		{   
			// Remove status file
			unlink($status);
		}   
    
		// Loop through all log files
		foreach(glob(LOG_DIRECTORY.'*.log') as $log)
		{   
			// Remove log file
			unlink($log);
		} 
		
		// Log current state
		utilities::notate("\tLog files cleaned up");
	}
	
	// Remove old saved searches
	private function cleanSearchDirectory()
	{ 
		// Loop through all log files
		foreach(glob(SAVED_SEARCH_DIR.'*.html') as $search)
		{    
			// If search file is not from today
			if(date("Y-m-d-G", filemtime($search)) != date("Y-m-d-G"))
			{
				// Remove search file
				unlink($search);
			}	
		}
		
		// Log current state
		utilities::notate("\tSearch directory cleaned");
	} 
	
	// Check back in any keywords left checked out for some reason
	private function keywordCheckIn()
	{   
		// Checkouts from last hour
		$old = date("Y-m-d G:00");  

	 	// Build update query
		$query = "	UPDATE 
						keywords 
					SET 
						check_out = '0'
					WHERE 
					 	google_status < '$old'
					AND 
						check_out = 1";
							                                            
		// Execute update query
		mysql_query($query) or utilities::reportErrors("ERROR ON cron keyword check in: ".mysql_error());
		
		// Log current state
		utilities::notate("\tOld keywords checked in");		   
	}
	
	// Check that all keywords are following their schedules
	private function checkSchedules()
	{        		
		// Construct query
		$query =   "SELECT 
						schedule,
						google_status,
						date
					FROM 
						keywords
					WHERE
						status != 'suspended'";  
																										
		// Execute query and return results			
	    $result = mysql_query($query) or utilities::reportErrors("ERROR ON SELECTING: ".mysql_error());
        
		// If keywords are returned
		if(mysql_num_rows($result) > 0)
		{   
			// Loop through results
			while($keyword = mysql_fetch_object($result))
			{  
				if($keyword->schedule == "hourly")
				{
					$hourly++;
					
					if($keyword->google_status < date("Y-m-d H"))
					{
						$hourlyLate++;
					}
				}
				elseif($keyword->schedule == "daily")
				{
					$daily++; 
					
					if($keyword->google_status < date("Y-m-d")) 
					{
						$dailyLate++;
					}
				}				
				
				$total++;
			}
			
			if($hourlyLate > 0)
			{
				utilities::reportErrors("$hourlyLate of $hourly keywords not updated this hour");
			}
			
			if($dailyLate > 0)
			{
				utilities::reportErrors("$dailyLate of $daily daily keywords not updated today");
			}			 
						
			// Return the keyword array
   		}
		
		// Log current state
		utilities::notate("\tSchedule check complete");	   
	}      
	
	// Turn on/off the kill switch                                                    
	private function killswitch()
	{    
		// If kill argument passed
		if($_SERVER['argv'][3] == 'kill')
		{   
			// Write killswitch file
			file_put_contents(KILL_SWITCH_FILE, "kill");  

			// Log current state
			utilities::notate("\tKillswitch flicked");	
		}
		else
		{    
			// If killswitch is present
			if(file_exists(KILL_SWITCH_FILE))			
			{
				// Delete killswitch
				unlink(KILL_SWITCH_FILE);
			}   
			
			// Log current state
			utilities::notate("\tIt's alive!!!");			   
		}
	}

	// Set a system status message (pause,kill)
	private function system()
	{
		// Set the system status
		$status = $_SERVER['argv'][3];

		// Write status file
		file_put_contents(SYSTEM_STATUS, $status);	
		
		// Log current state
		utilities::notate("\tSystem: $status");			
	}


	
}	