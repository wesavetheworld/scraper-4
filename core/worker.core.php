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
	// ! Main rankings method                                                     //
	// ===========================================================================//
	
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