<?php  if(!defined('HUB')) exit('No direct script access allowed\n');
 
// ******************************* INFORMATION *******************************//

// ***************************************************************************//
//  
// ** GECKOBOARD - A stats api for the scraper to be accessed by geckoboard for
// ** a nice stats panel to keep track of everything
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-10-23
//  	
// ***************************************************************************//

// ********************************** START **********************************//

class geckoboard 
{  
	function __construct()
	{
		// Include proxy data model
		require_once('models/proxies.model.php'); 				
	}

	public function geckoboard()
	{
		echo "\n".date("r")."\n\n";

		$this->proxyStats();
	}

	public function proxyStats()
	{
		// Instantiate new proxies object
		$this->proxies = new proxies($this->engine);
		
		echo "Total proxies: ".$this->proxies->checkTotal()."\n";		
		echo "\tAvailable proxies: ".$this->proxies->checkAvailable()."\n";		
		echo "\tResting proxies: ".$this->proxies->checkResting()."\n";		
		echo "\tBlocked proxies: ".$this->proxies->checkBlocked()."\n";		
		echo "\tIn use proxies: ".$this->proxies->checkInUse()."\n";		

		echo "\tAll proxies unblocked at: ".$this->proxies->checkBlockTime()."\n";
	}

}	