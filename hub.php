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
	// ! Server path and argument settings	                                      //
	// ===========================================================================//
	
	// Checked for in all othere files to prevent direct access   
	define('HUB', TRUE);

	// Get passed server arguments
	$argv = $_SERVER['argv'];	

   	// Set working directory for correct file includes etc 
	chdir(dirname($argv[0]));		

	// ===========================================================================// 
	// ! Dependencies and helper classes 	                                      //
	// ===========================================================================//

	// Include all required core files
	require_once('core/includes.core.php');			   

	// Set the controller to load
	$controller = strtolower($argv[1]);
	
	// Load controller
	new load($controller);

	

