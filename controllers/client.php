<?php  if(!defined('HUB')) exit('No direct script access allowed\n');
 
// ******************************* INFORMATION *******************************//

// ***************************************************************************//
//  
// ** CLIENT - Used to build data update jobs for keywords and domains
// ** 
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-06-21
// ** @access	private
// ** @param	
// ** @return	constants for application 
//  	
// ***************************************************************************//

// ********************************** START **********************************//

class client 
{  
	// ===========================================================================// 
	// ! Dependencies                                                             //
	// ===========================================================================// 
	
	function __construct()
	{      
		// Include keywords data model
	 	require_once('models/'.MODEL.'.model.php'); 
	
	  	// Initiate benchmarking
		utilities::benchmark();		
	}
	
	// ===========================================================================// 
	// ! Main rankings method                                                     //
	// ===========================================================================//	
	
	public function client()
	{
		// Create our gearman client
		$gmclient = new GearmanClient(); 

		// add the default job server
		$gmclient->addServer(JOB_SERVER_IP);   

   		// Set the class to instantiate
   		$class = MODEL;
		
		// Select all items from db to update
		$items = new $class(); 

		define("TOTAL", $items->total);
	   		
		echo "items selected: ".$items->total."\n"; 
	           
 		// If items returned
		if($items->$class)
		{
			// Call processing time
			utilities::benchmark('items selected: ', "client.log");
	   
		 	// Keep track of item loops
			$i = 0;
				
			// Loop through all items		
			foreach($items->$class as &$item)
			{       
				// If first item in new batch
				if($i == 0) 
				{
				 	// Create new items object
					$batch = new $class(true); 					
				} 
				
				// Add keyword to job batch
				$batch->$class->{$item->uniqueId} = $item;

				// Until data models are changed to just "id" for item ids
				if(MODEL == "keywords")
				{
					$id = "keyword_id";
				}
				else
				{
					$id = "domain_id";
				}

				// Singlular model item
				$obj = substr(MODEL, 0, -1); 
				
				// Add item id to checkout list                                                                           	
				$batch->{$obj."Ids"}[$item->uniqueId] = $item->$id;														 
			
				// Keep track of keywords in batch
				$i++;				
			
				// If item batch amount has reached job size limit
				if($i == JOB_SIZE || $i == $items->total)
				{   
					// Set item count object
					$batch->total = $i;
					
					// Reset count
					$i = 0;  

					// Create job data array
					$data = array();									

					// Define the engine used for the job (google,bing,yahoo)
					$data['engine'] = ENGINE;						

					// Define the type of job to create
					$task = $this->getTask();

					// Define the task for the worker
					$data['task'] = $task;					
					
					// Serialize items and add to job data
					$data[MODEL] = $batch;
					
					// Serialize job data for transport
					$data = serialize($data);
					
					// Define a new high priority job for current batch
				   	$gmclient->addTaskBackground($task, $data, null, $task."_".$job++."_".time());						  		

					// Create the jobs
		    		$gmclient->runTasks();	
		    		
		    		// Throttle the speed at which jobs are created
		    		$this->throttle($items->total, JOB_SIZE, 50);				
				} 			   		
			}
		
			// Call processing time
			utilities::benchmark("$job jobs defined: ", "client.log");		 
   
			// Call processing time
			utilities::benchmark('All jobs completed: ', "client.log"); 
		}	   	   	
        		        
	  	// Finish execution
		utilities::complete();
	} 

	// ===========================================================================// 
	// ! Supporting methods                                                       //
	// ===========================================================================//	

	// Throttle the job creation based on total jobs across 1 hour(50mins)
	function throttle($items, $itemsPerJob, $duration)
	{
		static $set = 0;
		static $jobsPerMinute = 0;
		static $first = true;

		// If the first call of the function
		if($first)
		{
			// Get amount of total jobs
			$jobs = setJobsTotal($items, $itemsPerJob);

			// Get the amount of jobs to create per hour
			$jobsPerMinute = $this->setJobsPerMin($jobs, $duration);
			$first = false;
		}

		//If enough jobs have been created this minute
		if($set == $jobsPerMinute)
		{			
			// Reset set count
			$set = 0;
			
			// The throttle part
			sleep(60);	
		}

		// Increment set count
		$set++;
	}
	
	// Calculate total jobs required for amount of items
	function setJobsTotal($items, $itemsPerJob)
	{
		// If enough items to divide by batch size
		if($items > $itemsPerJob)
		{
			// Return amount of batches needed for items
			return round($items / $itemsPerJob);
		}
		// Only enough items for one job
		else
		{
			return 1;	
		}			
	}	

	// Determine how many jobs to create per minute
	function setJobsPerMin($jobs, $duration)
	{
		// If more jobs than minutes in the duration
		if($jobs > $duration)
		{
			// Divide jobs across the duration
			return round($jobs / $duration);
		}
		// Too few jobs to throttle
		else
		{
			return $jobs;	
		}	
	}

	// Creat the task name for created jobs
	private function getTask()
	{
		// If engine matches task (domain stats)
		if(ENGINE == TASK)
		{
			return TASK;	 		
		}
		// else its for rankings
		else
		{	
			// Creates format like: taskEngine
			return TASK.ucfirst(ENGINE);
		}	 	
	}

	// ===========================================================================// 
	// ! Gearman methods                                                          //
	// ===========================================================================//	

	// Runs as jobs are checked back in
 //  	public static function jobComplete($task) 
	// { 
	// 	$time = intval(round(trim($task->data())));

	// 	// Show task completion message
	// 	print "Task ".$task->unique()." completed in $time seconds\n";

	// 	static $rates = array();
	// 	static $jobs = 0;

	// 	$jobs++;
	// 	$items = JOB_SIZE * $jobs;
	// 	$itemsLeft = TOTAL - $items;

	// 	$workers = 48;

	// 	// 16 workers @ 100 keywords each at the returned time
	// 	$rate = round((3600 / $time) * ($workers * JOB_SIZE));

	// 	$rates[] = $rate;
	// 	$avg =  number_format(round(array_sum($rates) / count($rates)));

	// 	// 16 workers @ 100 keywords each at the returned time
	// 	$rate = number_format($rate);		
		
	// 	// Get total time so far
	// 	utilities::benchmark("\ttime so far: ", true); 		

	// 	print "\tupdate rate: $rate items per hour\n";
	// 	print "\taverage rate: $avg items per hour\n";
	// 	print "\titems updated so far: $items\n";
	// 	print "\titems left: $itemsLeft\n";
	// }	

}	    





