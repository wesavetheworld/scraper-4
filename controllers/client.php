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

class client 
{  
	// ===========================================================================// 
	// ! Dependencies                                                             //
	// ===========================================================================// 
	
	function __construct()
	{           
		// Include keywords data model
	 	include('models/keywords.model.php'); 
	    //include('models/keywords.mongo.php');
	
	  	// Initiate benchmarking
		utilities::benchmark();		
	}
	
	// ===========================================================================// 
	// ! Main rankings method                                                     //
	// ===========================================================================//	
	
	public function client()
	{   
		# Create our gearman client
		$gmclient = new GearmanClient(); 

		# add the default job server
		$gmclient->addServer('10.170.102.159');
		
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
		
			// Set the function to be used when jobs are complete
			$gmclient->setCompleteCallback("$this->complete"); 

			// Create the jobs
			$gmclient->runTasks(); 
		
			// Call processing time
			utilities::benchmark('All jobs finished: '); 
		}	   	   	
        		        
	  	// Finish execution
		utilities::complete();
	} 
	
	// ===========================================================================// 
	// ! Supporting methods                                                       //
	// ===========================================================================//	
    
	// Runs when all jobs have checked back in
  	function complete($task) 
	{ 
	  print "COMPLETE: " . $task->unique() . ", " . $task->data() . "\n"; 
	}


	
	 
}	    






