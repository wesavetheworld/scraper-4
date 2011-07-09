                                                                        <?php  if(!defined('HUB')) exit('No direct script access allowed\n');
 
// ******************************* INFORMATION *******************************//

// ***************************************************************************//
//  
// ** RANKINGS - Scrapes search engines for rankings. Required settings can be 
// ** set in config/rankings.php 
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-06-21
// ** @access	private
// ** @param	
// ** @return	constants for application 
//  	
// ***************************************************************************//

// ********************************** START **********************************//

class serial 
{  
	// ===========================================================================// 
	// ! Dependencies                                                             //
	// ===========================================================================// 
	function __construct()
	{           
    	echo "\n";
	}

	// ===========================================================================// 
	// ! Main rankings method                                                     //
	// ===========================================================================//	
	
	public function serial()
	{	
		// Create new object	
		$obj = new levelOne; 
		 
		// Define some variables
		$obj->one = new levelTwo; 
		
		$obj->one->two = new levelThree; 
		
		$obj->one->two->three = "look at me";
		$obj->one->two->four = "look at me";
		
	   // print_r($obj);
		
		$obj = serialize($obj);
		
		$obj = unserialize($obj);
		
		// Loop through object
		foreach($obj->one->two as $key => $val)
		{   
			// unset current key
			unset($obj->one->two->$key);
		}
		 
		// Show whats left in the object (should be nothing)
		print_r($obj);
		
		//-----------------------------------------
		
		
  	}  
	
	 
}	 

class levelOne
{ 
	public $one; 
} 

class levelTwo
{ 	
	public $two;  
} 

class levelThree
{ 	
	public $three;  
}  
  






