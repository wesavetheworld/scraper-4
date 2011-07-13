<?php  if(!defined('HUB')) exit('No direct script access allowed\n');

// **************************************************************************//

	//PROBABLY BEST TO MOVE THIS TO THE FRONT END (DAYMAN.SESCOUT.COM)

// **************************************************************************//    













	// ===========================================================================// 
	// ! Configuration                                                            //
	// ===========================================================================//
	
	/*
	108.62.10.175:17240:websiteguru:ETYMEhuXy:Anonymous-proxies
	*/
		
	// ===========================================================================// 
	// ! Dependencies                                                             //
	// ===========================================================================//
	
	// Include settings (constants)
	include('../settings.php');
	
	// Include utilities class
	include('../utilities.class.php');
		
	// ===========================================================================// 
	// ! Add proxies                                                              //
	// ===========================================================================//
	
	// Connect to database
	utilities::databaseConnect();
		

	if(!empty($_POST['proxies']))
	{
		$proxies = $_POST['proxies'];
		$date = "000-00-00";
		$use = "0";

		$proxies = str_replace("\n\r", "\n", $proxies);
		$proxies = explode("\r\n", trim($proxies)); 

		$duplicates = 0;
		$added = 0;

		shuffle($proxies);
		
		$proxyList = array();
		
		foreach($proxies as $proxy)
		{
			$validProxy = true;
			
			// Format proxy input
			$proxy = explode(":", $proxy);
			
			// count argument
			if(count($proxy) < 4)
			{
				$proxy[0] .= " (Invalid Proxy)";
				$validProxy = FALSE;
			}
			
			// Split proxy input
			$port = trim($proxy[1]);
			$username = trim($proxy[2]);
			$password = trim($proxy[3]);
			$source = trim($proxy[4]);
			
			$proxy = trim($proxy[0]);
			
			// For display after submission
			$proxyList[] = $proxy;

			if($validProxy)
			{
				// Check for duplicates
				$query = mysql_query("SELECT proxy FROM proxies WHERE proxy='$proxy'");
				$dupeFound = mysql_num_rows($query);

				if($dupeFound > 0)
				{
					// Add total dupes
					$duplicates++;
				}
				else
				{
					// Add to database
					$query = "
					INSERT INTO
						proxies

					SET
						proxy = '{$proxy}',
						port = '{$port}',
						username = '{$username}',
						password = '{$password}',
						source = '{$source}',
						24hruse = '{$use}',
						date = '{$date}'
					";
					mysql_query($query) or die(mysql_error());

					// Add total added
					$added++;
				}
			}
		}
		
		// Display output
		echo "<br />$added Proxies added";
		echo "<br />$duplicates Duplicates found";
		echo "<hr size=1>";
		
		foreach($proxyList as $proxy)
		{
			echo "{$proxy}<br/>\n";
		}
		echo "<hr size=1>";
	}
	
?>
<style>
BODY {
	font-family: arial;
}
textarea {
  width: 800px;
  height: 70%;
  overflow: auto;
  text-indent:0;
}
</style>

</strong><form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>"/ >
	<h2>Proxy Import</h2>
	<strong>Format: </strong> proxy : port : username : password : source<br />
	<textarea name="proxies"></textarea>
	<br />
	<br />
	
	<input type="submit" value="add proxies" />

</form>