<?php  if(!defined('HUB')) exit('No direct script access allowed\n');

class utilities
{

	// ===========================================================================// 
	// ! Functions                                                                //
	// ===========================================================================//
    
   	// Connect to database
	public static function databaseConnect($host, $user, $pass, $db)
	{	
		// Establish MySQL connection & select database
		$connection = mysql_connect($host, $user, $pass, true) or die ('Error connecting to mysql');
		
		// Select database
		mysql_select_db($db, $connection); 

		// Return connection
		return $connection;
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
	public static function benchmark($description = false, $log = false, $last = false, $reset = false, $return = false)
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
            
			// If a description is provided but it's not a timeout check
			if($description && $description != 'checkTimeOut')
			{
				// If returning data instead of printing it
				if($return)
				{
					return $description.$duration."\n";
				}

				print $description.$duration."\n";
			}  

			// If a log file has been defined
			if($log)
			{
				// Write to log file
				utilities::log($description, $log);			
			}				
			
			// If a request to check timeout status is found
			if($description == 'checkTimeOut')
			{
				// Check if script has exceded max execution time yet
				if(defined("MAX_EXECUTION_TIME") && !$last && $time - $firstTime >= MAX_EXECUTION_TIME)
				{     
					// Script has exceeded max time
					return true;       
				}  
			}	
		}
	}  
	 
	// Return notation for the current part of the script
	public static function notate($description, $log = false)
	{   
		print $description."\n";
		return true;

		// If notation is turned on
		if(NOTATION)
		{   
			// Print description to screen
			print $description."\n";

			// If a log file has been defined
			if($log)
			{
				// Write to log file
				utilities::log($description, $log);			
			}	
		}			
	}

	// Log status to log file
	public static function log($data, $logFile)
	{	
		// If a log file has been defined
		if($logFile)
		{
			// Open the log file for writing
			file_put_contents(LOG_DIRECTORY.$logFile, $data."\n", FILE_APPEND);					
		}	
	}
	
	// Convert large bytes into readable versions
	public static function byteConvert($bytes)
	{
		// byte, kilobyte (kB), megabyte (MB), etc
		$unit = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');

		return round($bytes / pow(1024, ($i=floor(log($bytes, 1024)))),2).' '.$unit[$i];
	} 
	
	// Convert seconds into hour/minutes/seconds
	function convertSeconds($sec, $padHours = false) 
	{
		// start with a blank string
		$hms = "";

		// do the hours first: there are 3600 seconds in an hour, so if we divide
		// the total number of seconds by 3600 and throw away the remainder, we're
		// left with the number of hours in those seconds
		$hours = intval(intval($sec) / 3600); 

		// add hours to $hms (with a leading 0 if asked for)
		$hms .= ($padHours) 
		? str_pad($hours, 2, "0", STR_PAD_LEFT). ":"
		: $hours. ":";

		// dividing the total seconds by 60 will give us the number of minutes
		// in total, but we're interested in *minutes past the hour* and to get
		// this, we have to divide by 60 again and then use the remainder
		$minutes = intval(($sec / 60) % 60); 

		// add minutes to $hms (with a leading 0 if needed)
		$hms .= str_pad($minutes, 2, "0", STR_PAD_LEFT). ":";

		// seconds past the minute are found by dividing the total number of seconds
		// by 60 and using the remainder
		$seconds = intval($sec % 60); 

		// add seconds to $hms (with a leading 0 if needed)
		$hms .= str_pad($seconds, 2, "0", STR_PAD_LEFT);

		// done!
		return $hms;
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
			utilities::log($error, 'client.log');			  
			
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