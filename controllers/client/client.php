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
		
		// Connect to database
		utilities::databaseConnect();
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
		
		// Set the function to be used when jobs are complete
   		$gmclient->setCompleteCallback("client::jobComplete");

   		// Set the class to instantiate
   		$class = MODEL;
		
		// Select all items from db to update
		$items = new $class();  

		print_r($items);
		die();

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
				
				// Add item id to checkout list                                                                           	
				$batch->{MODEL."Ids"}[$item->uniqueId] = $item->$id;														 
			
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
					 
					// Serialize items and add to job data
					$data[MODEL] = $batch;

					if(MODEL == "keywords")
					{
						// Add engine to job data
						$data['engine'] = ENGINE;
					}
					else
					{
						// Add engine to job data
						$data['engine'] = ENGINE;
					}

					// Serialize job data for transport
					$data = serialize($data);
	
					// Define a new job for current batch
				   	///$gmclient->addTask(TASK, $data, null, $job++);  		
				} 			   		
			}
		
			// Call processing time
			utilities::benchmark("$job jobs defined: ", "client.log");		
		    
			// Create the jobs
		    $gmclient->runTasks(); 
   
			// Call processing time
			utilities::benchmark('All jobs completed: ', "client.log"); 
		}	   	   	
        		        
	  	// Finish execution
		utilities::complete();
	} 
	
	// ===========================================================================// 
	// ! Gearman methods                                                          //
	// ===========================================================================//	

	// Runs as jobs are checked back in
  	public static function jobComplete($task) 
	{ 
		$time = intval(round(trim($task->data())));

		// Show task completion message
		print "Task ".$task->unique()." completed in $time seconds\n";

		static $rates = array();
		static $jobs = 0;

		$jobs++;
		$items = JOB_SIZE * $jobs;
		$itemsLeft = TOTAL - $items;

		$workers = 48;

		// 16 workers @ 100 keywords each at the returned time
		$rate = round((3600 / $time) * ($workers * JOB_SIZE));

		$rates[] = $rate;
		$avg =  number_format(round(array_sum($rates) / count($rates)));

		// 16 workers @ 100 keywords each at the returned time
		$rate = number_format($rate);		
		
		// Get total time so far
		utilities::benchmark("\ttime so far: ", true); 		

		print "\tupdate rate: $rate items per hour\n";
		print "\taverage rate: $avg items per hour\n";
		print "\titems updated so far: $items\n";
		print "\titems left: $itemsLeft\n";
	}
	 
}	    





