<?php 

	// ******************************* INFORMATION ******************************//

	// **************************************************************************//
	//  
	// ** SERVER - When a server boots up it runs this file (called from rc.local) 
	// ** to bootstrap itself, and then again (from supervisord) to run the core 
	// ** daemon associated with it's purpose.
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
	// ! Server path settings (chdir) and define "HUB"                            //
	// ===========================================================================//

	// Variables and functions related to server path
	require_once($argv[0].'/core/path.config.php');			
	
	// ===========================================================================// 
	// ! Configure server for use                                                 //
	// ===========================================================================// 	   	

	// If the first argument is to bootstrap the server
	if($argv[1] == 'bootstrap') 
	{
		// Class for configuring server
   		include('core/bootstrap.core.php');
   			
   		// Configure server for use 
   		new bootstrap();	
   	}	

	// ===========================================================================// 
	// ! Route instance to correct core daemon                                    //
	// ===========================================================================// 

	// Otherwise get the correct core daemon to run for the server
   	else
   	{
		// Include all required core files (Dependencies and helper classes)
		require_once('core/includes.core.php');   		
		
		// Define core to use
		$core = $argv[1];

		// Define core class
		$class = $core."Core";

		if($argv[2])
		{
			define("JOB_NAME", $argv[2]);


			if($argv[3])
			{
				define("JOB_FUNCTION", $argv[3]);
			}				
		}

		// Include main router
		include("core/$core.core.php");	

		// Instantiate core
		$type = new $class();

		// Run the instance daemon
		$type->daemon();				
	}	