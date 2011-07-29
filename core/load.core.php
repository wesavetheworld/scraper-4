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
	// ! Main controller loading method                                           //
	// ===========================================================================// 

class load 
{    	
	// Complete loading and instantiation of a class
	function __construct($controller, $data = false)
	{
		// Define the class name (account for folders)
		$class = array_pop(explode("/", $controller));	

		// Load the controller's config file
		$this->loadConfig($class);

		// Load requestd controller
		return $this->loadController($controller, $class, $data);
	} 
    
    // ===========================================================================// 
	// ! Controller loading methods                                               //
	// ===========================================================================//  

	// Load the controllers config file
    private function loadConfig($class)
    {     
		// The config file name for the controller
	 	$config = "config/".$class.".config.php"; 

		// Check if controller's config file exists
		if(file_exists($config))
		{
			// Load the controllers config file
			require_once($config); 
		}
	}		 	

	// The main routing function
	private function loadController($controller, $class, $data = false)
	{ 			
		// The requested controller location
		$controllerFile = 'controllers/'.$controller.".php";
		
		// Check if controller exists
		if(file_exists($controllerFile))
		{                                  
			// Include the requested controller
		 	require_once($controllerFile);	 
		    
			// Check if assumed class exists 
			if(class_exists($class))
			{
				// Instantiate requested class
				$controller = new $class();
				
				// If a method with the same name as the class exists
				if(method_exists($class, $class))
				{   
					// Run the first function
					return $controller->$class($data); 	
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
}	
