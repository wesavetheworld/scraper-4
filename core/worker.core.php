<?php 

	// ******************************* INFORMATION ******************************//
	// **************************************************************************//
	//  
	// ** CLIENT - Acts like cron. Fires off actions based on the current time.
	// ** 
	// ** @author	Joshua Heiland <thezenman@gmail.com>
	// ** @date	 2011-07-12
	// ** @access	private
	// ** @param	
	// ** @return	Loops indefinitely and executes new processes when needed     
	//  	
	// ***************************************************************************//
	// ********************************** START **********************************// 

class worker 
{    
	
	function __construct()
	{
		// Checkin with gearman jobServer
		$this->checkin();

		// Register job types with jobServer
		$this->registerJobs();

		// The main worker loop
		$this->daemon();
	}

	// ===========================================================================// 
	// ! Gearman worker checkin and job type descriptions                         //
	// ===========================================================================//	
	
	// Checkin with gearman job server
	private function checkin()
	{
		// Create gearmn worker object.
		$this->gm = new GearmanWorker();

		// Add jobServer's ip for checking in
		$this->gm->addServer(JOB_SERVER_IP); 		
	}
	
	// Register types of jobs available
	private function registerJobs()
	{
		// Register rankings function with gearman server
		$this->gm->addFunction("rankings", "worker::rankings"); 

		// Register pagerank function with gearman server
		$this->gm->addFunction("pageRank", "worker::pageRank"); 
		
		// Register backlinks function with gearman server
		$this->gm->addFunction("backLinks", "worker::backLinks"); 
		
		// Register alexa function with gearman server
		$this->gm->addFunction("alexa", "worker::alexa"); 	
	}	
	
	// ===========================================================================// 
	// ! Infinite daemon loop                                                     //
	// ===========================================================================//		

	private function daemon()
	{
		// Log current status
		utilities::notate("Waiting for jobs..."); 

		// Continuous loop waiting for jobs
		while($this->gm->work())
		{   
			// If job failed
			if($this->gm->returnCode() != GEARMAN_SUCCESS)
			{
				// Log current status
				utilities::notate("return_code: ".$this->gm->returnCode());
				break;
			} 
			// If job was completed successfully 
			else
			{
				// Log current status
				utilities::notate("job completed"); 				
			} 
		}
	}	

	// ===========================================================================// 
	// ! Worker job types                                                         //
	// ===========================================================================//	

	// Collect keyword rankings
	private static function rankings($job)
	{	
		// Load the controller and get job results
		return  new load('workers/rankings', $job->workload());	
	}

	// Collect domain pagerank
	private static function pageRank($job)
	{	
		// Load the controller and get job results
		return  new load('workers/rankings', $job->workload());	
	}	

	// Collect domain backlinks
	private static function backLinks($job)
	{	
		// Load the controller and get job results
		return new load('workers/rankings', $job->workload());	
	}	

	// Collect domain alexa rank
	private static function alexa($job)
	{	
		// Load the controller and get job results
		return new load('workers/rankings', $job->workload());	
	}	
}	