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

   	// Configure server for use 
   	$server = new bootstrap();	

   	//$instanceType = "client";

	// ===========================================================================// 
	// ! Route instance to correct core daemon                                    //
	// ===========================================================================// 

	// Include main router
	include('core/'.$server->instanceType.'.core.php');	