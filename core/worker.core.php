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

class workerCore 
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
	
	// When script is ended
	function __destruct() 
	{
		// Unregister job types with jobServer
		$this->gm->unregisterAll();
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
		// Register job function with jobServer
		$this->gm->addFunction(JOB_NAME, "workerCore::".JOB_FUNCTION); 				
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
	public static function rankings($job)
	{	
		 // Build job data array
		 $job = array('model'=>'keywords', 'jobData'=>$job->workload());
		 
		 // Instantiate new worker	
		 $job = new load('worker', $job);	

		 return $job->results;
	}

	// Collect domain pagerank
	public static function pr($job)
	{	
		 // Build job data array
		 $job = array('model'=>'domains', 'stat'=>'pr', 'jobData'=>$job->workload());
		 
		 // Instantiate new worker	
		 $job = new load('worker', $job);	

		 return $job->results;
	}	

	// Collect domain backlinks
	public static function backLinks($job)
	{	
		// Load the controller and get job results
		return new load('workers/rankings', $job->workload());	
	}	

	// Collect domain alexa rank
	public static function alexa($job)
	{	
		// Load the controller and get job results
		return new load('workers/rankings', $job->workload());	
	}	
}	