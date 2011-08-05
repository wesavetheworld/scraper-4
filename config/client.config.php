<?php  if(!defined('HUB')) exit('No direct script access allowed\n');

	// ******************************* INFORMATION ******************************//
	
	// **************************************************************************//
	//  
	// ** SETTINGS - All of the settings that are generally constant and only 
	// ** need to be changed if relocating server or changing database structure
	// ** 
	// ** @author	Joshua Heiland <thezenman@gmail.com>
	// ** @date	 2011-04-22
	// ** @access	private
	// ** @param	
	// ** @return	constants for application 
	//  	
	// ***************************************************************************//
	
	// ********************************** START **********************************//   

	// ===========================================================================// 
	// ! Passed arguments through CLI ($argv[1] used for controller)              //
	// ===========================================================================//  
	
	// Get passed server arguments
	$argv = $_SERVER['argv'];

	// What tesks to create for workers
	define("TASK", $argv[2]);

	// Amount of keywords/domains per job
	define("JOB_SIZE", $argv[3]);	

	// Constants for keyword rankings
	if(in_array(TASK, array("rankings", "rankingsNew", "rankingsCalibrate")))
	{
		// Data model to use
		define("MODEL", "keywords");
		
		// Data model to use
		define("ENGINE", $argv[4]);	
		
		// Data model to use
		define("SCHEDULE", $argv[5]);
		
		// Data model to use
		define("ONLY_USER", $argv[6]);									
	}
	else
	{
		// Data model to use
		define("MODEL", "domains");		
	}
	
	// ===========================================================================// 
	// ! General settings                                                         //
	// ===========================================================================//		 
	
	// Build array of required arguments
	$requiredArgs = array('engine'=>ENGINE, 'schedule'=>SCHEDULE, 'keyword amount'=>KEYWORD_AMOUNT);
    
    // Json array of rquired arguments (only way to save array as a constant)                                              
	define("REQUIRED_ARGS", json_encode($requiredArgs));

