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

class workerstatus 
{  
	// ===========================================================================// 
	// ! Dependencies                                                             //
	// ===========================================================================// 
	
	function __construct()
	{           
		// Include keywords data model
	 	require_once('classes/gearman.class.php');

	 	// If this is dev
	 	$this->dev = $_SERVER['argv'][2];
	}
	
	// ===========================================================================// 
	// ! Main rankings method                                                     //
	// ===========================================================================//	
	
	public function workerstatus()
	{   
		echo "started:";

		// If this is a dev instance
		if($this->dev)
		{
			// Instantiate new dev gearman call
			$jobServer = new jobServerStatus(JOB_SERVER_IP_DEV);	
		}
		// Then it's production
		else
		{
			// Instantiate new gearman call
			$jobServer = new jobServerStatus(JOB_SERVER_IP);	
		}			
		
		$status = $jobServer->getStatus();

		print_r($status['operations']);

		$this->checkJobQueue('rankings');
	}

	// Check for oustanding jobs stilled queued
	private function checkJobQueue($type)
	{
		echo "\nQueued $type jobs: ";

		// If this is a dev instance
		if($this->dev)
		{
			// Instantiate new dev gearman call
			$jobServer = new jobServerStatus(JOB_SERVER_IP_DEV);	
		}
		// Then it's production
		else
		{
			// Instantiate new gearman call
			$jobServer = new jobServerStatus(JOB_SERVER_IP);	
		}			

		// Retrieve list of current jobs in queue
		$status = $jobServer->getStatus();	
		
		// Return specified job type job queue total
		echo $status['operations'][$type]['total']."\n";	
	}	

}	    






