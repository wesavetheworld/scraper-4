<?php  if(!defined('HUB')) exit('No direct script access allowed\n');
 
// ******************************* INFORMATION *******************************//

// ***************************************************************************//
//  
// ** QUEUE - Manage the job queue
// ** 
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-11-04
// ** @access	private
// ** @param	
// ** @return	constants for application 
//  	
// ***************************************************************************//

// ********************************** START **********************************//

class queue 
{   
	// ===========================================================================// 
	// ! Opening and closing functions                                            //
	// ===========================================================================//

	// Run on class instantiation
	function __construct($engine = false)
	{  	
		// Include redis class
		require_once('classes/redis.php');

		// Instantiate new redis object
		//$this->redis = new redis(BOSS_IP, BOSS_PORT);	

		echo "here".BOSS_IP.BOSS_PORT;

		die();
	}

	// Run when script ends
	function __destruct()
	{
			
	}

	// ===========================================================================// 
	// ! Redis proxy select and update                                            //
	// ===========================================================================//

	public function getWorkerTypes()
	{
		return $this->redis->send_command("keys", "workers:*");
	}

	// Get the status of workers
	public function checkWorkers($type)
	{
		$total = $this->redis->zCard($type);
		$list = $this->redis->zRangeByScore($type, "0", "1", "WITHSCORES");

		$zebra = 0;

		// Loop through redis response
		foreach($list as $item)
		{
			// Worker name
			if($zebra %2 == 0)
			{
				echo "\t$item | ";
			}
			// Worker status
			else
			{
				if(!$item)
				{
					$item = "available";
				}
				else
				{
					$item = "working";
				}

				echo "$item\n";
			}

			$zebra++;
		}
		return array('total' => $total, 'list' => $list);

		
	}
	

}	