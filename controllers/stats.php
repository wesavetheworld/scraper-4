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
	
	public function stats()
	{   
		echo "started: ".JOB_SERVER_IP;		
		
		// Instantiate new gearman call
		$jobServer = new jobServerStatus(JOB_SERVER_IP);
					
		$status = $jobServer->getStatus();

		print_r($status['operations']);

		$this->checkJobQueue('rankings');

		$this->proxyStats();
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

		// Array of sources 
		$sources = array('google', 'bing', 'alexa', 'backlinks');

		foreach($sources as $source)
		{
			echo "\t$source Available: ".$this->proxies->checkAvailable($source);		
			echo " | Resting : ".$this->proxies->checkResting($source);		
			echo " | Blocked : ".$this->proxies->checkBlocked($source);		
			echo " | In use: ".$this->proxies->checkInUse($source);		
			echo " | All unblocked at: ".$this->proxies->checkBlockTime($source)."\n";
		}	
	}	
}	    






