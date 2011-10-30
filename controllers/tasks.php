<?php  if(!defined('HUB')) exit('No direct script access allowed\n');
 
// ******************************* INFORMATION *******************************//

// ***************************************************************************//
//  
// ** tasks - All of the tasks that need to be performed regularly for the health
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
	// Include dependencies on instantiation	
 	function __construct()
	{    
		//Include settings for ranking collecting
		require_once('config/rankings.config.php'); 

		// Include keywords data model
		require_once('models/keywords.model.php'); 		

		// Include proxy data model
		require_once('models/proxies.model.php'); 
		
		// Include the amazon SDK
		require_once('classes/amazon/sdk.class.php');						
		
	  	// Initiate benchmarking
		utilities::benchmark();		  
	}
	  
	// ===========================================================================// 
	// ! Main routing method                                                      //
	// ===========================================================================//	
	
	public function tasks()
	{   
		// Log current state
		utilities::notate("Cron started at: ".TIME_CURRENT, "tasks.log"); 
    
		// If cron is passed an argument through CLI
		if($method = $_SERVER['argv'][2])
		{  
			// Call the method
			$this->$method($_SERVER['argv'][3]);   		
		}
		// No method was provided
		else
		{
			utilities::notate("No method provided", "tasks.log"); 
		}
		
		// Log current state
		utilities::notate("Cron complete", "tasks.log");
		
		// Finish execution
		utilities::complete();		   
	} 
	
	// ===========================================================================// 
	// ! Clen up type methods                                                     //
	// ===========================================================================//	
	
	// Transfer proxies from MySQL to redis
	private function migrateProxies()
	{
		// Include proxy data model
		require_once('models/proxies.model.php'); 

		// Instantiate new proxies object
		$this->proxies = new proxies();

		// Copy proxies from MySQL to redis
		$this->proxies->migrateToRedis();

		// Log current state
		utilities::notate("\tProxies migrated to redis", "tasks.log"); 		
	}   

	//Transfer keywords from MySQL to redis
	private function migrateSerps()
	{
		// Include keywords data model
	 	require_once('models/keywords.model.php'); 	
	 	
	 	// Set constants needed for keyword model
	 	define('ENGINE', 'bing');
	 	define('MIGRATION', TRUE);


	 	// Select all items from db to update
		$keywords = new keywords(); 		
		
		// Migrate keywords from MySQL to redis
		$keywords->migrateToRedis();

		// Log current state
		utilities::notate("\tMigration complete", "tasks.log"); 		
	}

	
	// Resets all stats for proxies (use/blocked/status)
	private function proxyReset()
	{
		// Instantiate a new proxy object
		$proxies = new proxies();

		// Rest proxy stats
		$proxies->reset();
		
		// Log current state
		utilities::notate("\tProxy stats reset", "tasks.log"); 
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
		utilities::notate("\tLog files cleaned up", "tasks.log");
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
		utilities::notate("\tSearch directory cleaned", "tasks.log");
	} 
	
	// Check back in any keywords left checked out for some reason
	private function keywordCheckIn()
	{   
		// Instantiate a new keywords object
		$keywords = new keywords(true, true);

		// Check keywords back in
		$keywords->setCheckOut(0, true);
		
		// Log current state
		utilities::notate("\tOld keywords checked in", "tasks.log");		   
	}
	
	// Check that all keywords are following their schedules
	private function checkSchedules()
	{        	
		// Instantiate a new keywords object
		$keywords = new keywords(true, true);

		// Make sure keyword schedules are honored
		$keywords->checkSchedules();
		
		// Log current state
		utilities::notate("\tSchedule check complete", "tasks.log");	   
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
			utilities::notate("\tKillswitch flicked", "tasks.log");	
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
			utilities::notate("\tIt's alive!!!", "tasks.log");			   
		}
	}

	// ===========================================================================// 
	// ! ec2 related methods                                                      //
	// ===========================================================================//	
	
	// Manage bing servers
	private function bing($action)
	{		
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
			utilities::notate("Bing instances modified: $action", "tasks.log");	
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

	// Get inforation for ec2 instances
	private function getInstances($opt)
	{
		// Create a new amazon object
		$ec2 = new AmazonEC2();

		// Set the region to access instances
		$ec2->set_region('us-west-1');
				
		// Get info on all worker instances
		$response = $ec2->describe_instances($opt);		

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
		
	// ===========================================================================// 
	// ! System monitoring methods                                                //
	// ===========================================================================//	

	// Check the system for actions to take
	private function monitorSystem()
	{
		// What type of isntance is running
		$instanceType = $_SERVER['argv'][3];

		// Infinite loop
		while(TRUE)
		{
			// Check if the system status file exists
			if(file_exists(SYSTEM_STATUS))
			{
				// Open the system status file
				$system = file_get_contents(SYSTEM_STATUS);

				// If there is a system command
				if($system)
				{
					// Get each part of the system message
					$system = explode("_", $system);
					$action = $system[0];
					$who = $system[1];
					$time = $system[2];

					// If the command timestamp is not older than 40 seconds
					if($time > (time() - 40))
					{
						// If system command applies to this instance
						if(in_array($who, array($instanceType, 'all')))
						{
							// If there is a system message
							if($action == 'reset')
							{		
								// Restart the application
								$this->restartSupervisord();
							}
							elseif($action == "stop")
							{
								// Kill all scripts
								$this->killSupervisord();												
							}							
							elseif($action == "reboot")
							{
								// Kill all scripts
								$this->killSupervisord();												
								
								// Shutdown the server
								exec("reboot");
							}					
							elseif($action == "shutdown")
							{
								// Kill all scripts
								$this->killSupervisord();													
								
								// Shutdown the server
								exec("shutdown now");
							}

							echo "some command has been run";
						}
						else
						{
							echo "not for me";
						}
					}
					else
					{
						echo "command is old.";
					}	
				}
				else
				{
					echo "no commands to run";
				}		
			} 
			else
			{
				echo "file does not exist";
			}

			// Wait 30 seconds and check again
			sleep(30);
		}	
	}

	// Get a list of all current system processes 
	private function killSupervisord()
	{
		// If supervisord is running
		if(file_exists('/tmp/supervisord.pid'))
		{
			// Get supervisord's pid from system file
			$pid = file_get_contents('/tmp/supervisord.pid');

			// Kill supervisord and all of its processes (client/worker/etc)
			exec("kill $pid");

			// While supervisord is still running, wait
			while(file_exists('/tmp/supervisord.pid'))
			{
				// Log current state
				utilities::notate("Supervisord is still running, waiting...", "tasks.log");		

				// Wait 10 seconds
				sleep(10);
			}						
			
			// Log current state
			utilities::notate("Killed supervisord and all sub processes", "tasks.log");
		}	
		else
		{
			// Log current state
			utilities::notate("Supervisord is not running", "tasks.log");				
		}
	}

	// Restart supervisord and all of its processes
	private function restartSupervisord()
	{
		// If supervisord is running
		if(file_exists('/tmp/supervisord.pid'))
		{
			// Get supervisord's pid from system file
			$pid = file_get_contents('/tmp/supervisord.pid');

			// Kill supervisord and all of its processes (client/worker/etc)
			exec("kill -1 $pid");
						
			// Log current state
			utilities::notate("Supervisord is restarting", "tasks.log");	
		}
		// Supervisord is not running, start it
		else
		{	
			// Start supervisord
			exec("supervisord &");
					
			// Log current state
			utilities::notate("Supervisord is not running, starting....", "tasks.log");				
		}		
	}

	// Set a system status message (pause,kill)
	private function system()
	{
		// Set the system status + a timestamp
		$status = $_SERVER['argv'][3]."_".time();

		// Write status file
		file_put_contents(SYSTEM_STATUS, $status);	
		
		// Log current state
		utilities::notate("\tSystem: $status", "tasks.log");			
	}

	private function testSystem()
	{
		// Kill all scripts
		$this->killSupervisord();		

		// Restart the application
		$this->restartSupervisord();			
	}	
}	