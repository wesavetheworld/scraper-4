<?php  if(!defined('HUB')) exit('No direct script access allowed\n');

// ******************************* INFORMATION ******************************//

// **************************************************************************//
//  
// ** CRON - Acts like cron. Fires off actions based on the current time.
// ** 
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-11-8
// ** @access	private
// ** @param	
// ** @return	Loops indefinitely and executes new processes when needed     
//  	
// ***************************************************************************//

// ********************************** START **********************************// 

class cronCore 
{    
	// Include dependencies on instantiation
	function __construct()
	{
	}	

	// ===========================================================================// 
	// ! Infinite daemon loop (Only add time related functions here)              //
	// ===========================================================================//
	
	// The main loop that acts as a daemon
	public function daemon()
	{	
		// Loop forever if not development client
		while(TRUE && !defined("DEV"))
		{
			// Log time for current task loop
			utilities::notate("Actions at: ".date("H:i:s"), "clientd.log");

			// Every 10 minutes
			if(intval(ltrim(date("i"), "0")) % 10 == 0)
			{
				// Run all 10 minute tasks
				$this->minuteEveryTen();
			}				

			// If first hour of the day
			if(date("H:i") == "00:00")
			{
				// Run all daily tasks
				$this->hourOne();			
			}

			// The first min of every hour
			if(date("i") == "00")
			{
				// Run all hourly tasks
				$this->hourAll();
			}		

			// Every 2 minutes
			if(intval(ltrim(date("i"), "0")) % 2 == 0)
			{
				// Run all every other minute tasks
				$this->minuteEveryTwo();			
			}

			// Run all minute tasks 
			$this->minuteAll();			  		   	 				

			// Wait for next loop
			$this->meditate();	
		}
	}
	
	// ===========================================================================// 
	// ! Time based functions                                                     //
	// ===========================================================================//
	
	// Tasks that should be run daily
	private function hourOne()
	{	
		
	}	
	
	// Tasks that should be run hourly
	private function hourAll()
	{
	
	}
	
	// Taks that should be run every 10 minutes
	private function minuteEveryTen()
	{

	}	
	
	// Tasks that should be run every 2 minutes
	private function minuteEveryTwo()
	{
		
	}
	
	// Tasks that should be run every minute
	private function minuteAll()
	{
		
	}		


	
	// ===========================================================================// 
	// ! Main daemon functions                                                    //
	// ===========================================================================//	

	// Execute bash command that detaches from daemon
	private function run($controller, $options = false)
	{
		// Build the command to execute
		$command = "php hub.php $controller $options > /dev/null 2>/dev/null &";

		// Execute command given
		exec($command);	

		// Log current command
		utilities::notate("command: $controller $options", "clientd.log");		  		   	 				
	}

	// Determine amount of time to wait before daemon loops again
	private function meditate()
	{	
		// Get remaining seconds in current minute
		$sleep = intval(60 - intval(ltrim(date("s"), "0")));
		
		// Sleep until next minute
		sleep($sleep);		  		   	 					
	}	
}	