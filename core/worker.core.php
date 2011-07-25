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

	# Create our worker object.
	$gmworker= new GearmanWorker();

	# Add default server (localhost).
	$gmworker->addServer(JOB_SERVER_IP); 

	# Register function "reverse" with the server. Change the worker function to
	$gmworker->addFunction("rankings", "rankings"); 
	
	print "Waiting for jobs...\n"; 

	while($gmworker->work())
	{   
		// If job failed
		if ($gmworker->returnCode() != GEARMAN_SUCCESS)
		{
			echo "return_code: " . $gmworker->returnCode() . "\n";
			break;
		} 
		// If job was completed successfully 
		else
		{
			echo "job completed.\n"; 				
		} 
	}

	// ===========================================================================// 
	// ! Register job types                                                       //
	// ===========================================================================//	

	// Collect keyword rankings
	function rankings($job)
	{
		// Set controller argument
		$argv[1] = 'workers/rankings';

		// Include main router
		include('hub.php');
	}