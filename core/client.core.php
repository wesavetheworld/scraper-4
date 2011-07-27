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
			//run("client/rankings", "google daily 1000");

			echo "\nWould update daily here\n";

			// Update all domain and keyword stats
			//$this->statsAll();
		}
		// If not the first hour and its the first min
		elseif(date("H") != 00 && date("i") == 00)
		{
			// Update hourly keyword rankings
			//run("client/rankings", "google hourly 1000");
			echo "\nWould update hourly here\n";
		}	
		
		// Run cron tasks
		run("tasks");	

		// Wait 1 min then loop again
		sleep(60);	
	}

	// ===========================================================================// 
	// ! Supporting functions                                                     //
	// ===========================================================================//	

	// Execute bash command that detaches from daemon
	function run($controller, $options = false)
	{
		// Build the command to execute
		$command = "php hub.php $controller $options >> ".LOG_DIRECTORY."$controller.log &";

		// Execute command given
		exec($command);

		echo "command executed: $command ";		
	}