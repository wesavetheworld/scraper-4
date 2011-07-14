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
	 	require_once('models/keywords.model.php'); 
	
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
		echo "client controller loaded successfully";

		// Loop forever JUST FOR TESTING
		while(true != false)
		{
			sleep(1);
		}
		
		// Should never get here
		die();		


		// Loop forever
		while(true != false)
		{
			// If first hour of the day
			if(date("H") == 00)
			{
				// Update all rankings
				$this->rankings('all');

				// Update all domain and keyword stats
				$this->statsAll();
			}
			// If not the first hour of the day
			elseif(date("H") != 00)
			{
				$this->rankings('hourly');
			}
			// No actions to run
			else
			{
				// Wait 1 min then loop again
				sleep(60);	
			}
		}
	}

	//private function rankings($schedule)
	// {
	// 	// Fork client into new process
	// 	$pid = pcntl_fork();		
	
	// 	// Only apply next step for child
	// 	if(!$pid) 
	// 	{
	// 		// Include rankings controller
	// 		include rankings('controllers/rankings.php');

	// 		$rankings = new rankings;

	// 		$rankings->rankings;			
	// 	}
	// }
	
	public function rankings($schedule)
	{
		// Create our gearman client
		$gmclient = new GearmanClient(); 

		// add the default job server
		$gmclient->addServer(JOB_SERVER;   
		
		// Set the function to be used when jobs are complete
   		$gmclient->setCompleteCallback("client::jobComplete");
		
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
					 
					// Serialize object for transport
					$keywordBatch = serialize($keywordBatch);
	
					// Define a new job for current batch
				   	$gmclient->addTask("rankings", $keywordBatch, null, $job++);  		
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

	// Runs when all jobs have checked back in
  	public static function jobComplete($task) 
	{ 
		print "COMPLETE: " . $task->unique() . ", " . $task->data() . "\n"; 
	}


	
	 
}	    






