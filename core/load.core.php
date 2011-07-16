<?php  if(!defined('HUB')) exit('No direct script access allowed\n');

// ******************************* INFORMATION ******************************//

// **************************************************************************//
//  
// ** BOOTSTRAP - Each server runs this first to identify itself and it's own 
// ** meaningless purpose in life.
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-06-17
// ** @access	private
// ** @param	
// ** @return	Main controller router     
//  	
// ***************************************************************************//

// ********************************** START **********************************// 

class load 
{    
	
	function __construct()
	{
		
	}	
	
	// Load and instantiate a controller class
	public function load($controller)
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
}			