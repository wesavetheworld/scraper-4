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
		// Set the job type for this worker
		$jobName = $this->setJobName();	

		// Register job function with jobServer (600 is max execution in seconds before timeout)
		$this->gm->addFunction($jobName, "workerCore::work"); 				
	}		
	
	// ===========================================================================// 
	// ! Infinite daemon loop                                                     //
	// ===========================================================================//		

	public function daemon()
	{
		// Log current status
		utilities::notate("Waiting for jobs..."); 

		// Continuous loop waiting for jobs
		while($this->gm->work()){ }
	}	

	// ===========================================================================// 
	// ! Worker job functions                                                     //
	// ===========================================================================//
	
	// Set the job name for the worker from the cli arugments provided 
	private function setJobName()
	{
		$jobName = SOURCE."-".SCHEDULE;

		if(defined("NEW"))
		{
			$jobName = "$jobName-new";
		}

		return $jobName;
	}	

	// The function to be registered with gearman
	public static function work($job)
	{
		 // Build job data array
		$job = array('model'=>MODEL, 'jobData'=>$job->workload());
		 
		 // Instantiate new worker	
		 $worker = new load('worker', $job);

		 // Finalize job (success/failure)
		 return $job->complete;		
	}	
}	