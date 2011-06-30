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

	// Get passed server arguments
	$argv = $_SERVER['argv'];
 
	// Check for the controller argument
	if(!isset($argv[1]))
	{         
		echo "No controller provided \n"; 
		die();
	} 
	
	// The requested controller
	$controller = $argv[1]; 

	// ===========================================================================// 
	// ! Dependencies and helper classes 	                                      //
	// ===========================================================================//
	
	// Checked for in all othere files to prevent direct access   
	define('HUB', TRUE);
	
	// Environment settings and DB credentials
	include('config/environment.php');	
	
	// All of the settings required for all controllers
	include('config/settings.php'); 
	
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
	$controller = 'controllers/'.$controller.".php";
	
	// Check if controller exists
	if(file_exists($controller))
	{                                  
		// Include the requested controller
	 	include($controller);	 
	    
		// Define the class name (account for folders)
		$class = array_pop(explode("/", $argv[1]));
	    
		// Check if assumed class exists 
		if(class_exists($class))
		{
			// Instantiate requested class
			$controller = new $class();
			
			// If a method with the same name as the class exists
			if(method_exists($class, $class))
			{   
				// Run the first function
				$controller->$class(); 	
			}  
		} 
		// Class was not found
		else
		{
			// Show error
			echo "That class does not exist.\n";
		}		
	}
	// The requested controller doesn't exist   
	else
	{   
		// Show error
		echo "\nThat conroller does not exist\n";
	}

?>