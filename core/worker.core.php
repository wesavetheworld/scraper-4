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

	
	// ===========================================================================// 
	// ! Gearman worker checkin                                                   //
	// ===========================================================================//

	// Create gearmn worker object.
	$gmworker = new GearmanWorker();

	// Add jobServer's ip for checking in
	$gmworker->addServer(JOB_SERVER_IP); 

	// Register rankings function with gearman server
	$gmworker->addFunction("rankings", "rankings"); 
	
	// Log current status
	utilities::notate("Waiting for jobs..."); 

	// Continuous loop waiting for jobs
	while($gmworker->work())
	{   
		// If job failed
		if($gmworker->returnCode() != GEARMAN_SUCCESS)
		{
			// Log current status
			utilities::notate("return_code: ".$gmworker->returnCode());
			break;
		} 
		// If job was completed successfully 
		else
		{
			// Log current status
			utilities::notate("job completed"); 				
		} 
	}

	// ===========================================================================// 
	// ! Register job types                                                       //
	// ===========================================================================//	

	// Collect keyword rankings
	function rankings($job)
	{	
		// Load the controller and get job results
		$results = load('workers/rankings', $job->workload());	

		// If job was successful 
		// if(!empty($results))
		// {
		// 	// Return finished job to jobServer
		// 	return $results;
		// }
	}