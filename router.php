<?php 

// ******************************* INFORMATION ******************************//

// **************************************************************************//
//  
// ** ROUTER - When a server boots up, it runs this file (called from rc.local) 
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
define('ROUTER', TRUE);	

// Define core file to use to use
define("CORE", $_SERVER['argv'][1]);	

// If no core provided, bitch
if(!CORE)
{
	echo "No core specified\n";
}

	// Set working directory for correct file includes etc 
chdir(dirname($_SERVER['argv'][0]));
	
// ===========================================================================// 
// ! Configure server for use                                                 //
// ===========================================================================// 	   	

// If the first argument is to bootstrap the server
if(CORE == 'bootstrap') 
{
	// Class for configuring server
		include('core/bootstrap.core.php');
			
		// Configure server for use 
		new bootstrap();	
	}	

// ===========================================================================// 
// ! Route instance to correct core daemon                                    //
// ===========================================================================// 

	else
	{
	// Include all required core files (Dependencies and helper classes)
	require_once('core/includes.core.php');   		

	// Define the core class to instantiate
	$class = CORE."Core";

	// Instantiate core
	$type = new $class();				
}	

// ********************************** END **********************************// 
