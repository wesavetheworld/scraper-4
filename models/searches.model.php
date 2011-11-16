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
		//$this->redis = new redis(REDIS_SEARCHES_IP, REDIS_SEARCHES_PORT);

		// Create a new amazon object
		$this->S3 = new AmazonS3();

		
		
		// $search = $this->S3->get_object("savedsearches", "test.html");			

		// echo $search->body;
					
			

		// $this->S3->create_bucket("searches-bad", "us-west-1");
		// $this->S3->create_bucket("searches-good", "us-west-1");
		
		// die();			


		// if($return = $this->S3->delete_bucket("searches", TRUE)){
		// 	echo "it worked\n";
		// }
		// else
		// {
		// 	echo "it failed\n";
		// }

		// print_r($return);
		

		//$this->S3->create_bucket("searches2", "us-west-1");			
		//print_r($this->S3->list_buckets());			
		//print_r($this->S3->get_object("searches2", "test.html"));			
	}

	// Run when script ends
	function __destruct()
	{
			
	}

	// ===========================================================================// 
	// ! Saved search functions                                                   //
	// ===========================================================================//	

	public function save($type, $name, $content)
	{

		$this->S3->create_object("searches-".$type, $name.".html", array("body"=>$content));

		//$this->redis->send_command("set", "$id", $content);
	}
}	