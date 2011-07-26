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

class rankings 
{  
	// ===========================================================================// 
	// ! Dependencies                                                             //
	// ===========================================================================// 
	
	function __construct()
	{      
		// Include keywords data model
	 	require_once('models/keywords.model.php'); 
	
	  	// Initiate benchmarking
		utilities::benchmark();		
		
		// Connect to database
		utilities::databaseConnect();
	}
	
	// ===========================================================================// 
	// ! Main rankings method                                                     //
	// ===========================================================================//	
	
	public function rankings()
	{
		// Create our gearman client
		$gmclient = new GearmanClient(); 

		// add the default job server
		$gmclient->addServer(JOB_SERVER_IP);   
		
		// Set the function to be used when jobs are complete
   		$gmclient->setCompleteCallback("rankings::jobComplete");
		
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
				// If first keyword in new batch
				if($i == 0) 
				{
				 	// Create new keywords object
					$keywordBatch = new keywords(true); 					
				} 
				
				// Add keyword to job batch
				$keywordBatch->keywords->{$keyword->uniqueId} = $keyword;
				
				// Add keywords id to checkout list                                                                           	
				$keywordBatch->keywordIds[$keyword->uniqueId] = $keyword->keyword_id;														 
			
				// Keep track of keywords in batch
				$i++;				
			
				// If keyword batch amount has been reached
				if($i == KEYWORD_AMOUNT || $i == $keywords->total)
				{   
					// Set keyword count object
					$keywordBatch->total = $i;
					
					// Reset count
					$i = 0;  

					// Create job data array
					$data = array();
					 
					// Serialize keyword and add to job data
					$data['keywords'] = $keywordBatch;

					// Add engine to job data
					$data['engine'] = ENGINE;

					// Serialize job data for transport
					$data = serialize($data);
	
					// Define a new job for current batch
				   	$gmclient->addTask("rankings", $data, null, $job++);  		
				} 			   		
			}
		
			// Call processing time
			utilities::benchmark("$job jobs defined: ");		
		    
			// Create the jobs
		    $gmclient->runTasks(); 
   
			// Call processing time
			utilities::benchmark('All jobs completed: '); 
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
		print "COMPLETE: " . $task->unique() . ", " . unserialize($task->data()) . "\n data:"; 
		print_r($task->data());
	}


	
	 
}	    






