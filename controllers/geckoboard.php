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
		$this->proxyStats();
	}

	public function proxyStats()
	{
		// Instantiate new proxies object
		$this->proxies = new proxies($this->engine);
		
		echo "Total proxies: ".$this->proxies->checkTotal()."\n";		
		echo "\tWorking proxies: ".$this->proxies->checkWorking()."\n";		
		echo "\tBlocked proxies: ".$this->proxies->checkBlocked()."\n";		

		echo "All proxies will be work next at: ".$this->proxies->checkBlockTime()."\n";
	}

}	