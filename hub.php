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
	// ! Server path settings (chdir) and define "HUB"                            //
	// ===========================================================================//

	// Variables and functions related to server path
	require_once('core/path.config.php');			

	// ===========================================================================// 
	// ! Dependencies and helper classes 	                                      //
	// ===========================================================================//

	// Include all required core files
	require_once('core/includes.core.php');			   

	// Set the controller to load
	$controller = strtolower($argv[1]);
	
	// Load controller
	new load($controller);

	

