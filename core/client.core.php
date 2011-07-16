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
			run("rankings all");

			// Update all domain and keyword stats
			//$this->statsAll();
		}
		// The first minute of the hour (except the first hour)
		elseif(date("i") == 57)
		{
			// Update hourly keyword rankings
			run("rankings hourly");	
		}
		
		echo "sleeping...";

		// Wait 1 min then loop again
		sleep(60);	
	}

	// Execute bash command that detaches from daemon
	function run($command)
	{
		echo "execute: ";
		// Execute command given
		//echo shell_exec("php hub.php client/$command &> /dev/null &");
		echo shell_exec("php hub.php client/$command");
	}