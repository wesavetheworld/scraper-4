<?php  if(!defined('CORE')) exit('No direct script access allowed\n');
 
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
		// //Include settings for ranking collecting
		// require_once('config/rankings.config.php'); 

		// // Include keywords data model
		// require_once('models/keywords.model.php'); 		

		// // Include proxy data model
		// require_once('models/proxies.model.php'); 
		
		// // Include the amazon SDK
		// require_once('classes/amazon/sdk.class.php');	
		
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
	// ! Clean up type methods                                                    //
	// ===========================================================================//	
	
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
	// ! Stats monitoring methods                                                 //
	// ===========================================================================//	
		
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

	// ===========================================================================// 
	// ! Data migration methods                                                   //
	// ===========================================================================//
	
	public function migrate()
	{	
		// Instantiate new proxies object
		$this->migration = new migration();	

		// Define the migrate function to call	
		$function = "migrate".ucwords($_SERVER['argv'][3]);
		
		// Call function for requested data source
		$this->$function();	
	}

	// Migrate proxies from MySQL to redis
	private function migrateProxies()
	{
		// Copy proxies from MySQL to redis
		$this->migration->proxies();

		// Log current state
		utilities::notate("\tProxies migrated to redis", "tasks.log"); 		
	}   
	
	// Migrate keywords from MySQL to redis
	private function migrateSerps()
	{
		// Only new keywords should be provided if command found
		$new = $_SERVER['argv'][4];

		// Copy serps from MySQL to redis
		$this->migration->serps($new);		

		// Log current state
		utilities::notate("\Serps migrated to redis", "tasks.log"); 		
	}
	
	// ===========================================================================// 
	// ! Application stats methods                                                //
	// ===========================================================================//
	
	// Route to a stats related method
	public function stats()
	{	
		// Define the stats function to call	
		$function = "stats".ucwords($_SERVER['argv'][3]);
		
		// Call function for requested data source
		$this->$function();	
	}
	
	// Check that status of the workers
	public function statsWorkers()
	{
		$this->queue = new queue();

		foreach($this->queue->sources as $type)
		{
			$stats = $this->queue->checkWorkers("workers:".$type);

			echo "Total $type: ".$stats['total']."\n";

			foreach($stats['workers'] as $worker)
			{
				echo "\t$worker\n";
			}

			echo "\n";	
		}
	}	
	
	// Check the status of the proxies
	public function statsProxies()
	{
		// Instantiate new proxies object
		$this->proxies = new proxies($this->engine);

		echo "Total proxies: ".$this->proxies->checkTotal('master')."\n";		

		foreach($this->proxies->sources as $source)
		{
			echo "\t".ucwords($source).": ".$this->proxies->checkTotal($source)." total";
			echo " | ".$this->proxies->checkAvailable($source)." ready";		
			echo " | ".$this->proxies->checkResting($source)." resting";		
			echo " | ".$this->proxies->checkBlocked($source)." blocked";		
			echo " | all ready in ".$this->proxies->checkBlockTime($source)." mins\n";
		}	

		echo "\n";
	}	

	public function test()
	{
		$this->queue = new queue();
		//$this->queue->fakeAdd();
		//$this->queue->fakeCheckOut();
		//$this->queue->fakeUpdate();
		$this->queue->fakeOld();
		//$this->queue->fakeCheck();
	}

	// Check that queue item update schedules are on track
	public function checkQueueSchedules()
	{
		$this->queue = new queue();

		// Count unupdated items
		$alert = $this->queue->checkUpdateSchedules();

		// If manual argument passed (checking from command line)
		if($_SERVER['argv'][3])
		{
			echo $alert;
		}
		else
		{
			// If notifo is turned on and there is an alert
			if($alert && NOTIFO)
			{
				// Send alert
				utilities::sendAlert($alert);
			}
		}	
	}	
	
	public function keywordStats()
	{
		$this->queue = new queue();

		foreach($this->queue->sources as $source)
		{
			print_r($this->queue->checkForJobsNew($source));
		}
	}		
}	