<?php if(!defined('CORE')) exit("Go away. Pass commands through router.php\n");

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
		$this->cron();
	}	

	// ===========================================================================// 
	// ! Infinite daemon loop (Only add time related functions here)              //
	// ===========================================================================//
	
	// The main loop that acts as a daemon
	public function cron()
	{	
		// Loop forever if not development client
		while(TRUE)
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

			// The last min of every hour
			if(date("i") == "59")
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
		// Migrate all items (to account for removals)
		//$this->migrate();		
	}	
	
	// Tasks that should be run hourly
	private function hourAll()
	{
		// Check update schedules
		$this->run("checkQueueSchedules");
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
		// Check for new items
		$this->migrate('new');
	}
	
	// ===========================================================================// 
	// ! Supporting functions                                                     //
	// ===========================================================================//
	
	// Check for new items in the db
	private function migrate($new = false)
	{
		// If only migrating new items
		if($new)
		{
			// Add a space for the command
			$new = " $new";
		}

		// Tell boss to load migrate tool for only new items
		$this->run("migrate serps$new");
	}		
	
	// ===========================================================================// 
	// ! Main daemon functions                                                    //
	// ===========================================================================//	

	// Execute bash command that detaches from daemon
	private function run($command)
	{
		// Build the command to execute
		$command = "php router.php boss $command > /dev/null 2>/dev/null &";

		// Execute command given
		exec($command);	

		// Log current command
		utilities::notate("command: $command", "clientd.log");		  		   	 				
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