<?php if(!defined('CORE')) exit("Go away. Pass commands through router.php\n");
 
// ******************************* INFORMATION *******************************//

// ***************************************************************************//
//  
// ** SEARCHES - Manage the saved searches
// ** 
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-11-15
// ** @access	private
// ** @param	
// ** @return	constants for application 
//  	
// ***************************************************************************//

// ********************************** START **********************************//

class searches 
{   

	// ===========================================================================// 
	// ! Opening and closing functions                                            //
	// ===========================================================================//

	// Run on class instantiation
	function __construct()
	{  	
		// Connect to the boss server
		$this->redis = new redis(REDIS_SEARCHES_IP, REDIS_SEARCHES_PORT);	
	}

	// Run when script ends
	function __destruct()
	{
			
	}

	// ===========================================================================// 
	// ! Saved search functions                                                   //
	// ===========================================================================//	

	public function save($id, $content)
	{
		$this->redis->send_command("set", "s:$id", $content);
	}
}	