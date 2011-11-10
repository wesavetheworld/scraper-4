<?php if(!defined('CORE')) exit("Go away. Pass commands through router.php\n");

// ******************************* INFORMATION ******************************//

// **************************************************************************//
//  
// ** BOSS - Monitors redis for work to be done and assigns it to available workers
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

		// Run the Boss
		$this->theBoss();				
	}	

	// ===========================================================================// 
	// ! Infinite daemon loop                                                     //
	// ===========================================================================//
	
	// The main loop that acts as a daemon
	public function theBoss()
	{	
		// Declare job types explicitly to avoid issues where workers are off line (like bing)
		$workerTypes = array('google', 'bing', 'pr', 'backlinks', 'alexa');

		// Loop forever if not development client
		while(TRUE)
		{	
			// Loop through each worker type		
			foreach($workerTypes as $source)
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
}	

// ********************************** END **********************************// 
