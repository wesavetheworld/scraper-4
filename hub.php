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
	
	// Checked for in all othere files to prevent direct access   
	define('HUB', TRUE);
	
	// Environment settings and DB credentials
	include('config/environment.config.php');	
	
	// All of the settings required for all controllers
	include('config/settings.config.php'); 
	
	// Include all utility static functions
   	include('classes/utilities.class.php');	 
    
	// If notifo notifications are turned on
	if(NOTIFO)
	{
		// Include notifo api class
		include('classes/notifo_api.class.php');  
	}	
	
	// If Twilio notifications are turned on
	if(TWILIO)
	{
		// Include twilio api class
		include('classes/twilio_api.class.php');
	} 

	// ===========================================================================// 
	// ! Route connection to correct controller                                   //
	// ===========================================================================// 

	// Get passed server arguments
	$argv = $_SERVER['argv'];
 
	// Check for the controller argument
	if(!isset($argv[1]))
	{         
		echo "Bootstrap mode \n"; 

		// Bootstrap server instance and get controller
		$controller = load('bootstrap');
		
		// Load required controller from bootstrap
		load($controller);	
	}
	// A controller was provided from the CL
	else
	{
		// Load requested controller
		load($argv[1]);		
	} 

	// ===========================================================================// 
	// ! The controller loader                                                    //
	// ===========================================================================// 	

	// Load and instantiate a controller class
	function load($controller)
	{
		// Make everything lowercase for files
		$controller = strtolower($controller);

		// ===========================================================================// 
		// ! Include the config file for the controller                               //
		// ===========================================================================//   
	     
		// The config file name for the controller
	 	$config = "config/".$controller.".config.php"; 

		// Check if controller's config file exists
		if(file_exists($config))
		{
			// Load the controllers config file
			include($config); 
		}		   

		// ===========================================================================// 
		// ! Route the request to the correct controller                              //
		// ===========================================================================//   
		
		// The requested controller location
		$controllerFile = 'controllers/'.$controller.".php";
		
		// Check if controller exists
		if(file_exists($controllerFile))
		{                                  
			// Include the requested controller
		 	include($controllerFile);	 
		    
			// Define the class name (account for folders)
			$class = array_pop(explode("/", $controller));
		    
			// Check if assumed class exists 
			if(class_exists($class))
			{
				// Instantiate requested class
				$controller = new $class();
				
				// If a method with the same name as the class exists
				if(method_exists($class, $class))
				{   
					// Run the first function
					return $controller->$class(); 	
				}  
			} 
			// Class was not found
			else
			{
				// Show error
				echo "That class does not exist.\n";
				echo $controller;
			}		
		}
		// The requested controller doesn't exist   
		else
		{   
			// Show error
			echo "\nThat conroller does not exist\n";
		}
	}	

?>