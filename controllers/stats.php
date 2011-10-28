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
		// Include keywords data model
	 	require_once('classes/gearman.class.php');

		// Include proxy data model
		require_once('models/proxies.model.php'); 		 	

	 	// If this is dev
	 	$this->dev = $_SERVER['argv'][2];
	}
	
	// ===========================================================================// 
	// ! Main rankings method                                                     //
	// ===========================================================================//	
	
	public function workerstatus()
	{   
		echo "started: ".JOB_SERVER_IP;		
		
		// Instantiate new gearman call
		$jobServer = new jobServerStatus(JOB_SERVER_IP);
					
		$status = $jobServer->getStatus();

		print_r($status['operations']);

		$this->checkJobQueue('rankings');
	}

	// Check for oustanding jobs stilled queued
	private function checkJobQueue($type)
	{
		echo "\nQueued $type jobs: ";		

		// Instantiate new gearman call
		$jobServer = new jobServerStatus(JOB_SERVER_IP);

		// Retrieve list of current jobs in queue
		$status = $jobServer->getStatus();	
		
		// Return specified job type job queue total
		echo $status['operations'][$type]['total']."\n";	
	}	

	// Check the status of the proxies
	public function proxyStats()
	{
		// Instantiate new proxies object
		$this->proxies = new proxies($this->engine);
		
		echo "Total proxies: ".$this->proxies->checkTotal('master')."\n";		
		echo "\tAvailable proxies: ".$this->proxies->checkAvailable('google')."\n";		
		echo "\tResting proxies: ".$this->proxies->checkResting('google')."\n";		
		echo "\tBlocked proxies: ".$this->proxies->checkBlocked('google')."\n";		
		echo "\tIn use proxies: ".$this->proxies->checkInUse('google')."\n";		

		echo "\tAll proxies unblocked at: ".$this->proxies->checkBlockTime('google')."\n";
	}	
}	    






