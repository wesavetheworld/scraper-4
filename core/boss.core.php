<?php if(!defined('CORE')) exit("Go away. Pass commands through router.php\n");

// ******************************* INFORMATION ******************************//

// **************************************************************************//
//  
// ** BOSS - Monitors redis for work to be done and assigns it to available workers
// ** also, specific tasks can be passed to it to perform like data migration
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-11-7
// ** @access	private
// ** @param	
// ** @return	Loops indefinitely and executes new processes when needed     
//  	
// ***************************************************************************//

// ********************************** START **********************************// 

class bossCore 
{    
	// Include dependencies on instantiation
	function __construct()
	{
		// Instantiate a new job queue object
		$this->queue = new queue();	

		// If a specific task was provided
		if(TASK)
		{
			// Perform the task.
			$this->runTask(TASK);		
		}
		// Default behavior - BE THE BOSS!!
		else
		{
			// Run the Boss
			$this->theBoss();		
		}
	}	

	// ===========================================================================// 
	// ! Infinite daemon loop                                                     //
	// ===========================================================================//
	
	// The main loop that acts as a daemon
	private function theBoss()
	{	
		// Loop forever if not development client
		while(TRUE)
		{	
			// Loop through each worker type		
			foreach($this->queue->sources as $source)
			{
				// Loop for as long as this type of worker is available and there are jobs
				while(($worker = $this->queue->hire($source)) && ($job = $this->queue->checkForJobs($source)))
				{
					// Assign the job to the worker
					$this->queue->assignWork($source, $worker, $job);
				}
			}

			echo "check complete\n";
			sleep(3);
		}
	}

	// ===========================================================================// 
	// ! Boss tasks                                                               //
	// ===========================================================================//	

	private function runTask($task)
	{	
		// Instantiate a new tasks object
		$do = new tasks();

		// Perform the task
		$do->$task();
	}
}	

// ********************************** END **********************************// 
