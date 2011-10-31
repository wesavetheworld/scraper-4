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
	// ! Server path and argument settings	                                      //
	// ===========================================================================//
	
	// Checked for in all othere files to prevent direct access   
	define('HUB', TRUE);

	// Get passed server arguments
	$argv = $_SERVER['argv'];	

   	// Set working directory for correct file includes etc 
	chdir(dirname($argv[0]));		
	
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

		// If this is a worker instance
		if($core == "worker")
		{
			if($argv[2])
			{			
				// Set the data model to be used	
				define("MODEL", $argv[2]);
			}

			if($argv[3])
			{
				// Set the job type
				define("SOURCE", $argv[3]);
			}	

			if($argv[4])
			{
				// Set the job type
				define("SCHEDULE", $argv[4]);
			}							

			if($argv[5])
			{
				// Set the job type
				define("NEW", $argv[5]);
			}				
		}

		// Include main router
		include("core/$core.core.php");	

		// Instantiate core
		$type = new $class();

		// Run the instance daemon
		$type->daemon();				
	}	