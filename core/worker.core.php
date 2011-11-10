<?php if(!defined('CORE')) exit("Go away. Pass commands through router.php\n");

// ******************************* INFORMATION ******************************//
// **************************************************************************//
//  
// ** WORKER - Waits for work to be pushed to it, then performs the work
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-11-7
// ** @access	private
// ** @param	
// ** @return	Loops indefinitely and executes new processes when needed     
//  	
// ***************************************************************************//
// ********************************** START **********************************// 

class workerCore 
{    
	// When script starts
	function __construct()
	{
		// Instantiate a new job queue object
		$this->queue = new queue();			

		// Set the current worker's name
		$this->queue->name = INSTANCE_NAME.":".WORKER_ID;	

		// Set the channel to listen to jobs on
		$this->queue->channel = "worker:".$this->queue->name;
	
		// Set redis worker type key
		$this->queue->workerGroup = 'workers:'.SOURCE;

		// Notify redis that this worker is alive
		$this->queue->status('0');				

		// Subscribe to job channel and wait for work
		$this->listen();
	}
	
	// When script is ended
	function __destruct() 
	{
		// Give up and go home
		$this->queue->status('quit');	
	}	

	// ===========================================================================// 
	// ! Wait for work to be published                                            //
	// ===========================================================================//		
	
	// Register types of jobs available
	private function listen()
	{			
		// Daemonize it
		while(TRUE)
		{
			echo "ready...\n";	

			$job = $this->queue->getWork();			

			// If a job was received (read_reply only waits for so long then the loop repeats)
			if($job)
			{
				// Perform the task
				$this->work($job[2]);

				// Notify redis that this worker is alive
				$this->queue->status('0');	
			}	
		}		
	}	

	// ===========================================================================// 
	// ! Worker job functions                                                     //
	// ===========================================================================//

	private function work($job)
	{
		// Instantiate new worker	
		$worker = new worker();

		// Do the work!
		$worker->work($job);
	}	
}	

// ********************************** END **********************************// 
