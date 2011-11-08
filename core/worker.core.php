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

class workerCore 
{    
	// The redis key for worker types
	public $key;

	// The name of the current worker
	public $name;

	// The channel to listen to work on
	public $channel;
	
	// When script starts
	function __construct()
	{
		include('controllers/worker.php');
		include('config/worker.config.php');

		// Instantiate a new job queue object
		$this->queue = new queue();			

		// Set the current worker's name
		$this->name = INSTANCE_NAME.":".WORKER_ID;	

		// Set the channel to listen to jobs on
		$this->channel = "worker:".$this->name;
	
		// Set redis worker type key
		$this->workerList = 'workers:'.SOURCE;

		// Notify redis that this worker is alive
		$this->queue->status($this->name, $this->workerList, '0');				

		// Subscribe to job channel and wait for work
		$this->listen();
	}
	
	// When script is ended
	function __destruct() 
	{
		// Give up and go home
		$this->queue->status($this->name, $this->workerList, 'quit');	
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

			$job = $this->queue->getWork($this->channel);			

			// If a job was received (read_reply only waits for so long then the loop repeats)
			if($job)
			{
				// Perform the task
				$this->work($job[2]);

				// Notify redis that this worker is alive
				$this->queue->status($this->name, $this->workerList, '0');	
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