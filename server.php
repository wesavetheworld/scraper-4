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
	include('core/includes.core.php');
	
	// ===========================================================================// 
	// ! Configure server for use                                                 //
	// ===========================================================================// 	   	

   	// Configure server for use 
   	$instanceType = new bootstrap();	

   	//echo 	$instanceType;

   	print_r(	$instanceType);

   	die('done');
   	
   	//$instanceType = "client";

	// ===========================================================================// 
	// ! Route instance to correct core daemon                                    //
	// ===========================================================================// 

	// Include main router
	include('core/'.$instanceType.'.core.php');	