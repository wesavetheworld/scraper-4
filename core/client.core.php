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
	// ! Infinite daemon loop                                                     //
	// ===========================================================================//
	
	// Loop forever
	while(TRUE)
	{
		// Check system status
		utilities::checkStatus();

		// If first hour of the day
		if(date("H:i") == "00:00")
		{
			// Update all keyword rankings
			//run("rankings", "google daily 1000");

			// Update all domain and keyword stats
			//$this->statsAll();
		}
		// The first minute of the hour (except the first hour)
		elseif(date("i") == 00)
		{
			// Update hourly keyword rankings
			//run("rankings hourly");	
		}
		
		// Update hourly keyword rankings
		run("rankings", "google daily 1000");

		// Wait 1 min then loop again
		sleep(60);	
	}

	// ===========================================================================// 
	// ! Supporting functions                                                     //
	// ===========================================================================//	

	// Execute bash command that detaches from daemon
	function run($controller, $options)
	{
		// Build the command to execute
		$command = "php hub.php client/$controller $options >> ".LOG_DIRECTORY."$controller.log";

		// Execute command given
		exec($command);

		echo "command executed: $command ";	
		die();	
	}