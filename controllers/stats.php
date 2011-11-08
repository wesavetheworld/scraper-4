<?php  if(!defined('HUB')) exit('No direct script access allowed\n');
 
// ******************************* INFORMATION *******************************//

// ***************************************************************************//
//  
// ** RANKINGS - Scrapes search engines for rankings. Required settings can be 
// ** set in config/rankings.php 
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-06-21
// ** @access	private
// ** @param	
// ** @return	constants for application 
//  	
// ***************************************************************************//

// ********************************** START **********************************//

class stats 
{  
	// ===========================================================================// 
	// ! Dependencies                                                             //
	// ===========================================================================// 
	
	function __construct()
	{           
		// Include proxy data model
		require_once('models/proxies.model.php'); 
		
		// Include job queue model		 	
		require_once('models/queue.model.php'); 		 	
	}
	
	// ===========================================================================// 
	// ! Main rankings method                                                     //
	// ===========================================================================//	
	
	public function stats()
	{   

		$this->workerStats();

		$this->proxyStats();
	}

	public function workerStats()
	{
		$this->queue = new queue();

		$types = $this->queue->getWorkerTypes();

		foreach($types as $type)
		{
			$stats = $this->queue->checkWorkers($type);

			echo "Total $type: ".$stats['total']."\n";

			foreach($stats['workers'] as $worker)
			{
				echo "\t$worker\n";
			}


			echo "\n";	
		}

	}


	// Check the status of the proxies
	public function proxyStats()
	{
		// Instantiate new proxies object
		$this->proxies = new proxies($this->engine);

		echo "Total proxies: ".$this->proxies->checkTotal('master')."\n";		

		foreach($this->proxies->sources as $source)
		{
			echo "\t$source Available: ".$this->proxies->checkAvailable($source);		
			echo " | Resting : ".$this->proxies->checkResting($source);		
			echo " | Blocked : ".$this->proxies->checkBlocked($source);		
			echo " | In use: ".$this->proxies->checkInUse($source);		
			echo " | All unblocked at: ".$this->proxies->checkBlockTime($source)."\n";
		}	

		echo "\n";
	}	
}	    






