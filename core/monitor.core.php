<?php if(!defined('CORE')) exit("Go away. Pass commands through router.php\n");

// ******************************* INFORMATION ******************************//

// **************************************************************************//
//  
// ** MONITOR - Listen for published messages from Boss server and act upon them
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-11-9
// ** @access	private
// ** @param	
// ** @return	Loops indefinitely and executes new processes when needed     
//  	
// ***************************************************************************//

// ********************************** START **********************************// 

class monitorCore 
{  
	// Include dependencies on instantiation
	function __construct()
	{	
		// Connect to the job server
		$this->queue = new queue();
				
		// Run the Boss
		$this->monitor();				
	}		

	// ===========================================================================// 
	// ! Main listening method                                                    //
	// ===========================================================================//	

	// Subscribe to the worker channel and listen for instructions
	private function monitor()
	{
		while(TRUE)
		{
			echo "listening...\n";

			// Wait for instructions
			$instructions = $this->queue->monitor();		

			// If instructions received
			if($instructions)
			{
				// Follow the instructions received
				$this->obey($instructions[2]);
			}
		}	
	}
	
	// ===========================================================================// 
	// ! System monitoring methods                                                //
	// ===========================================================================//		

	// Obey the instructions received from monitor()
	private function obey($instruction)
	{
		if($instruction == 'reset')
		{		
			// Change this workers status in the job queue
			//$this->queue->status(INSTANCE_NAME.":".WORKER_ID, $this->workerList, '0');	

			// Restart the application
			$this->restartSupervisord();
		}
		elseif($instruction == "stop")
		{
			// Kill all scripts
			$this->killSupervisord();												
		}							
		elseif($instruction == "reboot")
		{
			// Kill all scripts
			$this->killSupervisord();												
			
			// Shutdown the server
			exec("reboot");
		}					
		elseif($instruction == "shutdown")
		{
			// Kill all scripts
			$this->killSupervisord();													
			
			// Shutdown the server
			exec("shutdown now");
		}	
		else
		{
			echo "no actions found for \"$instruction\"\n";
		}	
	} 
		
	// ===========================================================================// 
	// ! Supervisord daemon methods                                               //
	// ===========================================================================//	

	// Get a list of all current system processes 
	private function killSupervisord()
	{
		// If supervisord is running
		if(file_exists('/tmp/supervisord.pid'))
		{
			// Get supervisord's pid from system file
			$pid = file_get_contents('/tmp/supervisord.pid');

			// Kill supervisord and all of its processes (client/worker/etc)
			exec("kill $pid");

			// While supervisord is still running, wait
			while(file_exists('/tmp/supervisord.pid'))
			{
				// Log current state
				utilities::notate("Supervisord is still running, waiting...", "tasks.log");		

				// Wait 10 seconds
				sleep(10);
			}						
			
			// Log current state
			utilities::notate("Killed supervisord and all sub processes", "tasks.log");
		}	
		else
		{
			// Log current state
			utilities::notate("Supervisord is not running", "tasks.log");				
		}
	}

	// Restart supervisord and all of its processes
	private function restartSupervisord()
	{
		// If supervisord is running
		if(file_exists('/tmp/supervisord.pid'))
		{
			// Get supervisord's pid from system file
			$pid = file_get_contents('/tmp/supervisord.pid');

			// Kill supervisord and all of its processes (client/worker/etc)
			exec("kill -1 $pid");
						
			// Log current state
			utilities::notate("Supervisord is restarting", "tasks.log");	
		}
		// Supervisord is not running, start it
		else
		{	
			// Start supervisord
			exec("supervisord &");
					
			// Log current state
			utilities::notate("Supervisord is not running, starting....", "tasks.log");				
		}		
	}
}	

// ********************************** END **********************************// 
	