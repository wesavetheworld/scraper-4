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

class async 
{   
	private $data = false;
	
	function __construct()
	{
   
	}
    

	public function async()
	{     
		echo "\nStarting...\n"; 
		
		$this->longRequest();
		
		while($this->data == FALSE)
		{
			echo "\t\t...";
			sleep(1);
		}
		
		echo "finished\n";

	} 
	
	public function longRequest()
	{    
		sleep(5);
		
		$this->data = TRUE;
	}

}