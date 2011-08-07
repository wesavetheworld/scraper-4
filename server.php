<?php 

	// ******************************* INFORMATION ******************************//

	// **************************************************************************//
	//  
	// ** HUB - The main hub of SEscout data collection. All process requests are 
	// ** routed through this file 	 
	// ** 
	// ** @author	Joshua Heiland <thezenman@gmail.com>
	// ** @date	 2011-06-17
	// ** @access	private
	// ** @param	
	// ** @return	Main controller router     
	//  	
	// ***************************************************************************//

	// ********************************** START **********************************// 

	// ===========================================================================// 
	// ! Dependencies and helper classes 	                                      //
	// ===========================================================================//

	// Include all required core files
	require_once('core/includes.core.php');
	
	// ===========================================================================// 
	// ! Configure server for use                                                 //
	// ===========================================================================// 	   	

	if($argv[1] == 'bootstrap') 
	{
   		// Configure server for use 
   		new bootstrap();	
   	}	

	// ===========================================================================// 
	// ! Route instance to correct core daemon                                    //
	// ===========================================================================// 

	if($argv[1] == 'run') 
	{
		// Define core to use
		$core = INSTANCE_TYPE;

		// Define core class
		$class = $core."Core";

		if($argv[2])
		{
			define("JOB_NAME", $argv[2]);
		}

		if($argv[3])
		{
			define("JOB_FUNCTION", $argv[3]);
		}		

		// Include main router
		include("core/$core.core.php");	

		// Instantiate core
		new $class();
	}	