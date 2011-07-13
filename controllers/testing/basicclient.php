<?php  if(!defined('HUB')) exit('No direct script access allowed\n');
 
// ******************************* INFORMATION *******************************//

// ***************************************************************************//
//  
// ** RANKINGS - Scrapes search engines for rankings. Required settings can be 
// ** set in config/rankings.php 
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-06-21
// ** @access	private
// ** @param	
// ** @return	constants for application 
//  	
// ***************************************************************************//

// ********************************** START **********************************//
         
		// Include keywords data model
		include('models/keywords.model.php');

		# Create our gearman client
		$gmclient = new GearmanClient(); 

		# add the default job server
		$gmclient->addServer('10.170.102.159');  
		
		# register some callbacks
		$gmclient->setCreatedCallback("reverse_created");
		$gmclient->setDataCallback("reverse_data");
		$gmclient->setStatusCallback("reverse_status");
		$gmclient->setCompleteCallback("reverse_complete");
		$gmclient->setFailCallback("reverse_fail");
		
		// Select all keywords from db to update
		$keywords = new keywords();  
	   		
		echo "keywords selected: ".$keywords->total."\n";   			

 		// If keywords selected
		if($keywords->keywords)
		{
			// Call processing time
			utilities::benchmark('keywords selected: ');
	   
		 	// Keep track of keyword loops
			$i = 0;
		
			// Loop through all keywords		
			foreach($keywords->keywords as &$keyword)
			{ 
				// Add keyword to job batch
				$keywordBatch[$keyword->keyword_id] = $keyword;   
			
				// Keep track of keywords in batch
				$i++;			
						
			    // Every 1000 keywords
				if($i % KEYWORD_AMOUNT == 0 || $i == $keywords->total )
				{    				
					// Define a new job for current batch
				   	$gmclient->addTask("rankings", json_encode($keywordBatch), null, $job++);
				
					// Clear batch array
					unset($keywordBatch);			
				} 
			}
		
			// Call processing time
			utilities::benchmark("$job jobs defined: ");  
			
			
			 


			// Create the jobs
		    $gmclient->runTasks(); 
   
			// Call processing time
			utilities::benchmark('All jobs created: '); 
		}  
		
		  
		
		function reverse_created($task)
		{
		    echo "CREATED: " . $task->jobHandle() . "\n";
		}

		function reverse_status($task)
		{
		    echo "STATUS: " . $task->jobHandle() . " - " . $task->taskNumerator() . 
		         "/" . $task->taskDenominator() . "\n";
		}

		function reverse_complete($task)
		{
		    echo "COMPLETE: " . $task->jobHandle() . ", " . $task->data() . "\n";
		}

		function reverse_fail($task)
		{
		    echo "FAILED: " . $task->jobHandle() . "\n";
		}

		function reverse_data($task)
		{
		    echo "DATA: " . $task->data() . "\n";
		}




