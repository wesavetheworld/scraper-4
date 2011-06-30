<?php  if(!defined('HUB')) exit('No direct script access allowed\n');

	// ===========================================================================// 
	// ! Configuration                                                            //
	// ===========================================================================//
	
	// HTTP referer to use
	$referer = "http://www.bing.com/";
	
	// HTTP User agent to use
	$agent = "Mozilla/5.0 (Windows NT 5.1; rv:2.0) Gecko/20100101 Firefox/4.0";
		
	// ===========================================================================// 
	// ! Check for variables                                                      //
	// ===========================================================================//

	// Set arguments
	$action = '';
	$urlCount = '';
	$proxyIp = '';
	
	if(is_array($_SERVER["argv"]))
	{
		if(count($_SERVER["argv"]) > 2)
		{
			// Action switch - can be single or multiple
			if(!empty($_SERVER["argv"][1])) $action = $_SERVER["argv"][1];

			// URL count (this is the total number of urls that will be created regardless
			// of available proxies
			if(!empty($_SERVER["argv"][2])) $urlCount = $_SERVER["argv"][2];
			
			// Proxy to test with
			if(!empty($_SERVER["argv"][3])) $proxyIp = $_SERVER["argv"][3];
		}
	}
	
	// Check for required arguments
	$showArgumentError = false;
	if(empty($action) || !isset($urlCount))
	{
		$showArgumentError = true;
	}
	
	// Check for blocked arguments
	if($action == 'block' && empty($proxyIp))
	{
		$showArgumentError = true;
	}
	
	if($showArgumentError)
	{
		echo "************************************************************\n";
		echo "*** Check Status\n";
		echo "*** Error: Missing arguments\n";
		echo "*** \n";
		echo "*** checkstatus.php [COMMAND] [URL_COUNT] [PROXY_IP_ADDRESS]\n";
		echo "*** \n";
		echo "*** Example: run single:\n";
		echo "*** \$ checkstatus.php single 8\n";
		echo "*** \n";
		echo "*** Example: run single all proxies:\n";
		echo "*** \$ checkstatus.php single 0\n";
		echo "*** \n";
		echo "*** Example: run multiple:\n";
		echo "*** \$ checkstatus.php multiple 7\n";
		echo "*** \n";
		echo "*** Example: run multiple all proxies:\n";
		echo "*** \$ checkstatus.php multiple 0\n";
		echo "*** \n";
		echo "*** Example: send 50 random queries to google, repeat until we are blocked:\n";
		echo "*** \$ checkstatus.php block 50 666.666.666.666\n";
		echo "************************************************************\n";
		
		
		exit;
	}

	// ===========================================================================// 
	// ! Dependencies                                                             //
	// ===========================================================================//
	
	// Include scraping class
	include('classes/scraper.class.php');

	// ===========================================================================// 
	// ! Get database data                                                        //
	// ===========================================================================//
	
	// Connect to database
	utilities::databaseConnect();

	// ===========================================================================// 
	// ! Proxy stats                                                              //
	// ===========================================================================//
	
	// Total proxy count
	$proxyTotal = 0;
	
	// Total working proxies
	$proxyWorking = 0;
	
	// Total dead proxies
	$proxyDead = 0;
	
	// Total google blocked proxies
	$googleBlock = 0;
	
	// Output message - header
	if($action == 'single' || $action == 'multiple')
	{
		echo "\n";
		echo "================================================================================================================================================================\n";
		echo displayOutput("Proxy", 15);
		echo displayOutput("IP", 15);
		echo displayOutput("Status", 10);
		echo displayOutput("Google", 10);
		echo displayOutput("Code", 10);
		echo displayOutput("Download Speed", 30);
		echo displayOutput("Source", 30);
		echo displayOutput("Error Page", 10);
		echo "\n";
		echo "================================================================================================================================================================\n";
	}


	// ===========================================================================// 
	// ! Get URLs and proxies                                                     //
	// ===========================================================================//

	// Get proxy array - include blocked proxies
	$proxies = utilities::proxyDatabaseSelect($urlCount, TRUE, '', TRUE);
	//$proxies = utilities::proxyDatabaseSelect($urlCount, TRUE, '', FALSE);
	//$proxies = utilities::proxyDatabaseSelect($urlCount, true, '173.192.206.41');
	//print_r($proxies);
	
	// URL count
	if(empty($urlCount))
	{
		$urlCount = count($proxies);
	}
	
	// Create URL array
	$urls = array();
	$count = 1;
	while($count <= $urlCount)
	{
		$urls[] = "http://50.23.212.129/tools/manage/showip.php";
		$count++;
	}
	
	// ===========================================================================// 
	// ! Start Checking                                                           //
	// ===========================================================================//
	          
	// Create new scraper object
	$scrape = new scraper;	
	
	// Parse response headers
	$scrape->headersParse = TRUE;

	// For each proxy run tests
	switch($action)
	{
	   
	    // ===========================================================================// 
		// ! Check status of a proxy one at a time                                    //
		// ===========================================================================//
		
		case 'single':
		
			foreach($proxies as $proxy)
			{
				$responseGoogleOutput = "";

				// Output message
				echo displayOutput($proxy['proxy'], 15);

				// Use proxies for scraping?
				$scrape->proxies = array($proxy);

				// Array of urls to scrape 
				$scrape->urls = array("http://50.23.212.129/tools/manage/showip.php");

				// Execute the scraping
				$scrape->curlExecute();

				// Execute cURL
				$responseOutput = $scrape->results;

				// Check if there was a connection to our proxy

				// Check if there was a connection to our IP address status page

				// Output IP address only
				$showErrorPage = FALSE;
				if(ipIsValid($responseOutput[0]['output']))
				{
					echo displayOutput($responseOutput[0]['output'], 15);
				}
				else
				{
					// For showing error page later
					$showErrorPage = TRUE;

					// Output message
					echo displayOutput(" ", 15);
				}

				// Check if the proxy outgoing IP is the same as the proxies claimed IP address
				if(trim($responseOutput[0]['output']) != $proxy['proxy'])
				{
					// Output message - this proxy is whacked
					echo displayOutput("bad", 10);

					// Output message - we do not need to test google
					echo displayOutput("n/a", 10);

					// Output message - HTTP response code
					echo displayOutput($results[0]['httpInfo']['http_code'], 10);

					// Count this proxy as one of the dead ones
					$proxyDead++;
				}
				else
				{		
					// Output message
					echo displayOutput("ok", 10);

					// Google test

					// Parse response headers
					$scrape->headersParse = TRUE;
					
					// Generate random keyword
					$randomKeyword = md5(microtime());
					$randomKeyword = substr($randomKeyword, 0, rand(3, 8));

					// Array of urls to scrape 
					$scrape->urls = array("http://www.google.com/search?q=".$randomKeyword);

					// Execute the scraping
					$scrape->curlExecute();	

					// Execute cURL
					$responseGoogleOutput = $scrape->results;

					if(!preg_match("/Results/i", $responseGoogleOutput[0]['output']))
					{
						// maybe right here preg_match the results and see what the google error was

						// Output message
						echo displayOutput("blocked", 10);
						echo displayOutput($responseGoogleOutput[0]['httpInfo']['http_code'], 10);
						echo displaySpeed(" ", 30);
						
						// Update blocked proxies 
						utilities::proxyDatabaseUpdate(array($proxy), 'google', 'blocked');
						
						// Set the flag to show the blocked / error page
						$showErrorPage = TRUE;
						$responseOutput[0]['output'] = $responseGoogleOutput[0]['output'];

						// Count total google blocked
						$googleBlock++;
					}	
					else
					{
						// Set working proxy count
						$proxyWorking++;
					
						// Output message
						echo displayOutput("ok", 10);
						echo displayOutput($responseGoogleOutput[0]['httpInfo']['http_code'], 10);
						echo displaySpeed($responseGoogleOutput[0]['httpInfo'], 30);
						
						// Unblock proxy from google
						if($proxy['blocked_google'] == 1)
						{
							// Update blocked proxies 
							utilities::proxyDatabaseUpdate(array($proxy), 'google', 'unblock');
						}
					}
				}

				// Output message
				echo displayOutput($proxy['source'], 30);

				// Show error page
				if($showErrorPage)
				{
					$responseOutput[0]['output'] = str_replace("\r\n", " ", $responseOutput[0]['output']);
					$responseOutput[0]['output'] = str_replace("\n", " ", $responseOutput[0]['output']);
					echo "\t".$responseOutput[0]['output'];
				}

				// Output message
				echo "\n";

				// Flush the output
				flush();

				// Increment proxy count
				$proxyTotal++;

			} // foreach
		
		
			break 1;
			
		// ===========================================================================// 
		// ! Check status of multiple proxies at once (faster than single             //
		// ===========================================================================//
		case 'multiple':

			$scrape->proxies = $proxies;

			// Array of urls to scrape 
			$scrape->urls = $urls;

			// Execute the scraping
			$scrape->curlExecute();

			// Display data
			foreach($scrape->results as $result)
			{
				$proxyTotal++;

				// Output message - proxy
				echo displayOutput($result['proxy_info']['proxy'], 15);

				// Output IP address only
				$showErrorPage = FALSE;
				if(ipIsValid($result['output']))
				{
					echo displayOutput($result['output'], 15);
				}
				else
				{
					// For showing error page later
					$showErrorPage = TRUE;

					// Output message
					echo displayOutput(" ", 15);
				}

				// Check if the proxy outgoing IP is the same as the proxies claimed IP address
				if(trim($result['output']) != $result['proxy_info']['proxy'])
				{
					// Output message - this proxy is whacked
					echo displayOutput("bad", 10);

					// Output message - we do not need to test google
					echo displayOutput("n/a", 10);

					// Output message - HTTP response code
					echo displayOutput($result['httpInfo']['http_code'], 10);

					echo displayOutput(" ", 30);

					echo displayOutput($result['proxy_info']['source'], 30);

					// Count this proxy as one of the dead ones
					$proxyDead++;
				}
				else
				{
					// Set working proxy count
					$proxyWorking++;

					// Output message
					echo displayOutput("ok", 10);

					echo displayOutput("n/a", 10);
					echo displayOutput($result['httpInfo']['http_code'], 10);

					echo displaySpeed($result['httpInfo'], 30);

					echo displayOutput($result['proxy_info']['source'], 30);

					// No google test
				}

				echo "\n";
			}

			break 1;
			
		// ===========================================================================// 
		// ! Check status of multiple proxies at once (faster than single             //
		// ===========================================================================//
		case 'block':
		
			// Set time now
			$seconds_start = time();
			$seconds_end = 0;
		
			// Set proxy count
			$proxyCrawl = 0;
		
			// Set proxy array
			$proxies = utilities::proxyDatabaseSelect($urlCount, false, $proxyIp);
			
			// Loop until we are blocked
			while(1)
			{
				// Add totals
				$proxyCrawl += $urlCount;
				
				// Output message
				echo "\nScraping {$urlCount} random keywords from google with proxy {$proxyIp}\n";

				// Set random keywords for google testing
				$urls = array();
				$keywordN = 0;
				while($keywordN < $urlCount)
				{
					$randomKeyword = md5(microtime());
					$randomKeyword = substr($randomKeyword, 0, rand(3, 8));

					$urls[] = "http://www.google.com/search?q=".$randomKeyword;

					$keywordN++;
				}

				$scrape->proxies = $proxies;

				// Array of urls to scrape 
				$scrape->urls = $urls;

				// Execute the scraping
				@$scrape->curlExecute();

				// Blocked count
				$blockedCount = 0;

				foreach($scrape->results as $result)
				{
					if($result['httpInfo']['http_code'] == 302)
					{
						// Record now as the time we got blocked
						if($seconds_end == 0)
						{
							// Stop time
							$seconds_end = time();
						}
					
						$blockedCount++;
					}
				}

				if($blockedCount > 0)
				{
					// Total time blocked
					$seconds_total = $seconds_end - $seconds_start;
					
					echo "\nGoogle blocked {$proxyIp} {$blockedCount} time(s) after {$proxyCrawl} scrapes ({$seconds_total} seconds) (Start time: ".date("n/j/y h:i:s a", $seconds_start).") (End time: ".date("n/j/y h:i:s a", time()).")\n\n";
					break 1;
				}
				
				// Time now
				$timenow = time();
				
				// Not blocked yet
				echo date("h:i:s", $timenow)." - Not blocked after {$proxyCrawl} scrapes - ".($timenow - $seconds_start)." secs\n";
			}
			
			// end script
			exit;
		
			break 1;
	}
	
	// ===========================================================================// 
	// ! Display summary                                                          //
	// ===========================================================================//
	
	// Output message - footer
	echo "\n";
	echo "\n";
	echo "================================================================================================================================================================\n";
	echo displayOutput("Tested", 15);
	echo displayOutput("Working", 15);
	echo displayOutput("Dead", 10);
	echo displayOutput("Google Blocked", 10);
	echo displayOutput(" ", 40);
	echo "\n";
	echo "================================================================================================================================================================\n";
	echo displayOutput($proxyTotal, 15);
	echo displayOutput($proxyWorking, 15);
	echo displayOutput($proxyDead, 10);
	echo displayOutput($googleBlock, 10);
	echo displayOutput(" ", 40);
	echo "\n";
	echo "\n";
	
	// ===========================================================================// 
	// ! Functions                                                                //
	// ===========================================================================//

	function ipIsValid($ipAddress)
	{
		if(strlen($ipAddress) > 15)
		{
			return FALSE;
		}
		
		return TRUE;
	}
	
	function displayOutput($string, $length)
	{
		return "\t".str_pad($string, $length, " ", STR_PAD_RIGHT);
	}
	
	function displaySpeed($httpInfo, $length)
	{
		$google_speed = '';
		
		if(is_array($httpInfo))
		{
			// mbps
			$mbps = number_format($httpInfo['speed_download'] * 8 / 1024 / 1024, 2);

			// Display the speed it took to grab google
			$google_speed = "";
			$google_speed = "{$mbps}mbps {$httpInfo['size_download']} bytes {$httpInfo['total_time']} secs";
		}
		
		return displayOutput($google_speed, $length);
	}

?>