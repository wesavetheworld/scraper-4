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

	// Constants for domain stats
	if(in_array(TASK, array("pr", "backlinks", "alexa")))
	{
		// Data model to use
		define("MODEL", "domains");	

		// Data model to use
		define("ENGINE", TASK);			

		define("ONLY_NEW", $argv[4]);	
															
		define("ONLY_USER", $argv[5]);								
	}
	//Constants for keywords
	else
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

	// What ranking to switch scraping from 10/100 results
	define("NUM_SWITCH_THRESHHOLD", 29);	
	
	// ===========================================================================// 
	// ! General settings                                                         //
	// ===========================================================================//		 
	
	// Build array of required arguments
	$requiredArgs = array('engine'=>ENGINE, 'schedule'=>SCHEDULE, 'keyword amount'=>KEYWORD_AMOUNT);
    
    // Json array of rquired arguments (only way to save array as a constant)                                              
	define("REQUIRED_ARGS", json_encode($requiredArgs));

