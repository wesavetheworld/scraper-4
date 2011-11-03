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

		// Connect to redis
		$this->redis = new redis(REDIS_SERPS_IP, REDIS_SERPS_PORT);	

		// Set the current worker's name
		$this->name = INSTANCE_NAME;	

		// Set the channel to listen to jobs on
		$this->channel = "workers:".INSTANCE_NAME;
	
		// Set redis worker type key
		$this->key = 'workers:'.SOURCE;

		// Notify redis that this worker is alive
		$this->status('0');		

		// Subscribe to job channel and wait for work
		$this->listen();
	}
	
	// When script is ended
	function __destruct() 
	{
		// Give up and go home
		$this->status('quit');
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
			
			$this->redis->subscribe($this->channel);

			echo "subscribed!\n";

			// Wait for a job to be published
			$job = $this->redis->read_reply();

			// Redis commands are ignored if still subscribed to a channel
			$this->redis->unsubscribe($this->channel);				

			// If a job was received (read_reply only waits for so long then the loop repeats)
			if($job)
			{
				// Perform the task
				$this->work($job[2]);

				// Notify redis that this worker is alive
				$this->status('0');	
			}	
		}		
	}	

	// ===========================================================================// 
	// ! Manage the worker stats (available, busy, quit)                          //
	// ===========================================================================//	
	
	// Set worker status (0 = ready, 1 = working, quit = offline)
	private function status($status)
	{	
		// If worker is shutting down
		if($status == "quit")
		{
			// Remove this worker from the worker list
			$this->redis->zRem($this->key, "$this->name");
		}
		else
		{
			// Update the worker's status
			$this->redis->zAdd($this->key, $status, "$this->name") ."\n";	
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