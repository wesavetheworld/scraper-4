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

class write 
{   
	
	function __construct()
	{
   		// Include the amazon SDK
		require_once 'classes/amazon/sdk.class.php';
	}
    

	public function write()
	{
		echo "hello";

		$dir = '10.170.91.139/';

		if ($handle = opendir('.')) { 
    while (false !== ($file = readdir($handle))) { 
        if ($file != "." && $file != "..") { 
            echo "$file\n"; 
        } 
    } 
    closedir($handle); 
} 
	} 


	
}
php -r "<?php if($handle = opendir('.')){while (false !== ($file = readdir($handle))){if($file != "." && $file != ".."){echo \"$file\n\";}}closedir($handle);} ?>";

php -r 'echo is_dir("./")."\n"; '


php -r 'echo is_dir("10.170.91.139/home/ec2-user/scraper")."\n"; '