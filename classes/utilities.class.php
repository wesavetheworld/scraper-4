<?php  if(!defined('HUB')) exit('No direct script access allowed\n');

class utilities
{

	// ===========================================================================// 
	// ! Functions                                                                //
	// ===========================================================================//
    
   	// Connect to database
	public static function databaseConnect()
	{	
		// Establish MySQL connection & select database
		mysql_connect(DB_HOST, DB_SERP_USER, DB_SERPS_PASS) or die ('Error connecting to mysql');
		mysql_select_db(DB_NAME_SERPS);
	}
	
	// Check for needed constants
	public static function argumentCheck($arguments = array())
	{                                                         
		// Loop through required arguments supplied
		foreach($arguments as $arg => $value)
		{      			
		    // If argument is empty(missing)
		 	if(empty($value))
			{
				// Show error and quit
				echo "$arg is not defined.\n";
				die();
			}  
		}     	   			
    }    
	
	// Checks for system status file
	public static function checkStatus()
	{   
		// If system status exists
		if(file_exists(SYSTEM_STATUS))
		{    
			// Get file contents
			$status = file_get_contents(SYSTEM_STATUS);
		
			// If the file contains data
			if($status)
			{   
				// If system status says to kill yourself
				if($status == "kill")
				{				
					// Log current state
					utilities::notate("Kill switch flicked"); 
				
				  	// Finish execution
					utilities::complete();		
				}
				elseif($status == "pause")
				{
					// Log current state
					utilities::notate("System paused..."); 
										
					// Wait for 60
					sleep(60);

					// Check status again
					utilities::checkStatus();
				}
			}
		}
	}  
	
	// Fork application into a new process
	public static function fork()
	{
		// Fork current process
		$pid = pcntl_fork();
		
		// If forking returned an error
		if($pid == -1) 
		{
		     die('could not fork');
		} 
		// If parent
		elseif($pid) 
		{
			// Return successful forking 
			return true;
		}	
	}  
    
    
      
	
	// When called, keeps track of execution time since last call
	public static function benchmark($description = false, $last = false, $reset = false)
	{
		// Check benchmarking is turned on in the settings
		if(BENCHMARK)
		{
			static $firstTime =  null;
			static $lastTime = null;
			
			// If reset param passed
			if($reset)
			{   
				// Reset the first time
				$firstTime = null;
				$lastTime = null;
			}

			$time = microtime(true); 
			
			if($lastTime == null)
			{
				$firstTime = $time;
				$duration = "starting";
			}
			elseif($last)
			{
				$duration = $time - $firstTime;
			}	
			else
			{	                                                                                        
				// Using sprintf to parse E notation
				$duration  = sprintf('%.16f', $time - $lastTime)." seconds ";
								
				$duration .= "(memory: ".utilities::byteConvert(memory_get_usage(true)).")";                  				
			}	

			$lastTime = $time;
            
			// If a description is provided
			if($description)
			{
				print $description.$duration."\n";
			}  
			
			// Check if script has exceded max execution time yet
			if(!$last && $time - $firstTime >= MAX_EXECUTION_TIME)
			{     
				// Send any error notifications
			 	utilities::reportErrors("Scraper max execution time exceeded.");			

				// Stop the script 
			  	utilities::complete();            
			}  
		}
	}  
	 
	// Return notation for the current part of the script
	public static function notate($description)
	{   
		// If notation is turned on
		if(NOTATION)
		{   
			// Print description to screen
			print $description."\n";

			// Write to log file
			utilities::log($description);			
		}			
	}

	// Log status to log file
	public static function log($log)
	{
		// If a log file is defined in controller's config file
		if(defined("LOG_FILE"))
		{
			// Open the log file for writing
			file_put_contents(LOG_FILE, $log."\n", FILE_APPEND);					
		}			
	}
	
	// Convert large bytes into readable versions
	public static function byteConvert($bytes)
	{
		// byte, kilobyte (kB), megabyte (MB), etc
		$unit = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');

		return round($bytes / pow(1024, ($i=floor(log($bytes, 1024)))),2).' '.$unit[$i];
	} 
	    
	// Function used to notify admin of any errors that occur
	public static function reportErrors($error = false, $sendAllErrors = false)
	{                
		// Declare function static variables
		static $errorCount = 0;             
		static $errors = "";

		// If an error message was passed
		if($error)
		{
			// Display errors
			echo $error."\n";
			
			// Write to log file
			utilities::log($error);			  
			
			// Add new error to error list
			$errors .= $error;			  	

			// Increment error count
			$errorCount++;			
		}
		
		// If the erros should be sent now
		if($sendAllErrors)  
		{  	  		    
			// If notifo reporting is turned on in the settings
			if(NOTIFO && $errors)
			{                                     
				// Instantiate notifo only once
				$notifo = new Notifo_API;   
			             			
				// Build notifo notification
				$params['to'] = NOTIFO_NOTIFY_USERNAME;
				$params['msg'] = $errors;
				$params['title'] = "scraper error";
			
				// Send notifo error
				$notifo->sendNotification($params);	
				
				// Reset errors 
				$errors = "";
				$errorCount = 0;		
			} 
		} 
	}
	
	// Called to end the execution of the script and
	public static function complete()
	{                   
		
	  	// Log current state
		utilities::notate("Instance ".INSTANCE. " complete\n\n"); 

		// Send any error notifications
	 	utilities::reportErrors(false, true); 
	    
		// Final benchmark
		utilities::benchmark('total execution: ', true); 
		
		exit();
	}

	
	
	
}


?>