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

	// Create gearmn worker object.
	$gm = new GearmanWorker();

	// Add jobServer's ip for checking in
	$gm->addServer(JOB_SERVER_IP); 

	// Register job function with jobServer
	$gm->addFunction(JOB_NAME, JOB_FUNCTION); 
	
	// Continuous loop waiting for jobs
	while($this->gm->work())
	{   
		// // If job failed
		// if($this->gm->returnCode() != GEARMAN_SUCCESS)
		// {
		// 	// Log current status
		// 	utilities::notate("return_code: ".$this->gm->returnCode());
		// 	break;
		// } 
	}					

	// ===========================================================================// 
	// ! Worker job types                                                         //
	// ===========================================================================//	

	// Collect keyword rankings
	function rankings($job)
	{	
		 // Build job data array
		// $job = array('model'=>'keywords', 'jobData'=>$job->workload());
		 
		 // Instantiate new worker	
		 //$worker = new load('worker', $job);
		sleep(3);	
		 // return false;
		$job->sendFail();


		 //return false;

		 // Finalize job (success/failure)
		 //return $job->complete;
	}

	// Collect domain pagerank
	function pr($job)
	{	
		 // Build job data array
		 $job = array('model'=>'domains', 'jobData'=>$job->workload());
		 
		 // Instantiate new worker	
		 $job = new load('worker', $job);	

		 return $job->results;
	}	

	// Collect domain backlinks
	function backlinks($job)
	{	
		 // Build job data array
		 $job = array('model'=>'domains', 'jobData'=>$job->workload());
		 
		 // Instantiate new worker	
		 $job = new load('worker', $job);	

		 return $job->results;
	}	

	// Collect domain alexa rank
	function alexa($job)
	{	
		 // Build job data array
		 $job = array('model'=>'domains', 'jobData'=>$job->workload());
		 
		 // Instantiate new worker	
		 $job = new load('worker', $job);	

		 return $job->results;
	}	