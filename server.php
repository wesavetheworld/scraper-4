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
		utilities::reportErrors('runnin bootstrap', true);

   		// Configure server for use 
   		$server = new bootstrap();	
   	}	

	// ===========================================================================// 
	// ! Route instance to correct core daemon                                    //
	// ===========================================================================// 

	if($argv[1] == 'run') 
	{
		utilities::reportErrors('it worked!!!!', true);

		// Include main router
		include('core/'.INSTANCE_TYPE.'.core.php');	
	}	