<?php  if(!defined('HUB')) exit('No direct script access allowed\n');

class scraper
{
	// ===========================================================================// 
	// ! Configuration                                                            //
	// ===========================================================================//
	
	// Array of urls to scrape 
	public $urls = array(); 

	// Which search engine to scrape
	public $engine;
	 
	// Use proxies for scraping?
	public $proxy_use = PROXY_USE;
	
	// If proxy use is on, this will contain an array of proxies
	public $proxies = array();
	
	// Follow any 301, 302 redirects, will follow only once
	public $redirectFollow = TRUE;
	
	// Browser referer must be supplied as array to randomize
	// array("http://www.bing.com/")
	public $referer = array();
	
	// User agent must be supplied as array to randomize
	// array("Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.8) Gecko/2009032609 Firefox/3.0.8")
	public $agent = array();
	
	// ===========================================================================// 
	// ! All variables used in class                                              //
	// ===========================================================================//	
	
 	// Will contain any blocked proxies
	private $proxiesBlocked = array(); 
	
	// Will contain any proxies that returned 407 auth errors
	private $proxiesDenied = array();  

	// Will contain any proxies that timeout
	private $proxiesTimeout = array(); 
	
	// Will contain any proxies that timeout
	private $proxiesDead = array(); 
		
	// The amount of scrapes that fail
	private $scrapesBad = 0; 
	
	// The amount of scrapes that succeed
	private $scrapesGood = 0;

	// The total amount of new scrapes performed
	private $scrapesTotal = 0;	
	
	// CURL handle
	private $mh;
	
	// Curl options
	private $ch = array();
	
	// Current proxy
	private $proxy;
	
	// Proxies array key
	private $proxyKey = 0;
	
	// Log all proxy errors
	private $proxyError;
	
	// Final scraped content
	public $results = array();  

	function __construct()
	{      
		// Include keywords data model
	 	//require_once('models/'.MODEL.'.model.php'); 

	}	

	// ===========================================================================// 
	// ! Public Methods                                                           //
	// ===========================================================================//
	
	// ===========================================================================// 
	// ! Public Methods                                                           //
	// ===========================================================================//
	 
	// The main scraping method
	public function curlExecute()
	{    
	    // If no urls provided
	 	if(count($this->urls) == 0)
		{   
			// Don't continue
			return false;
		}
		
		// If proxy use is turned on
		if($this->proxy_use)
		{
			// Get a list of proxies equal to urls in array
			$this->proxies = $this->proxyDatabaseSelect(count($this->urls));
		}
		
		// Reset the proxy array back to the beginning
		$this->proxyKey = 0;
		
		// Initialize curl
		$this->mh = curl_multi_init();	
		
		// Build curl request
		$this->curlRequestBuild(); 
								
		// Start performing the request
		do
		{
			$execReturnValue = curl_multi_exec($this->mh, $runningHandles);
		}
		while($execReturnValue == CURLM_CALL_MULTI_PERFORM);
		
		// Loop and continue processing the request
		while($runningHandles && $execReturnValue == CURLM_OK) 
		{
			// Wait forever for network
			$numberReady = curl_multi_select($this->mh);
			if($numberReady != -1) 
			{
				// Pull in any new data, or at least handle timeouts
				do 
				{
					$execReturnValue = curl_multi_exec($this->mh, $runningHandles);
				}
				while ($execReturnValue == CURLM_CALL_MULTI_PERFORM);
			}
		}
		
		// Check for any errors
		if($execReturnValue != CURLM_OK) 
		{
			trigger_error("Curl multi read error $execReturnValue\n", E_USER_WARNING);
		}

		// Extract the content fromt the curl requests
		$this->curlContentExtract(); 
		
		// If errors
		if($this->scrapesBad > 0)
		{
	 		// Log current state
			utilities::notate("Bad scrapes: ".$this->scrapesBad." of ".$this->scrapesTotal." total scrapes", "scrape.log");		 
		}	
		
		// Clean up the curl_multi handle
		curl_multi_close($this->mh);
		
		// Update each proxyies status that was uses
		if($this->engine != "bing")
		{
			$this->updateProxies();
		}	
	}
	
	// ===========================================================================// 
	// ! Private Methods                                                          //
	// ===========================================================================// 
	
 	// Select proxies for scraping from the database
	private function proxyDatabaseSelect($totalProxies = 1, $blockedProxies = false)
	{
		// If executing locally
		if(ENVIRONMENT == 'local')
		{   
			// Exclude proxies requiring ip authentication
			$excludeIpAuth = "AND ip_auth != 1";
		}

		// Grab proxies with lowest 24 hour use counts
		$sql = "SELECT 
					* 
				FROM 
					proxies 
				WHERE 
					status='active'
				AND 
					blocked_".$this->engine." = 0 
					{$excludeIpAuth} 
			   	ORDER BY 
					hr_use, 
					RAND()
				LIMIT 
					{$totalProxies}";
					
		$result = mysql_query($sql) or utilities::reportErrors("ERROR ON proxy select: ".mysql_error());

		// Check if proxies exist
		if(mysql_num_rows($result) == 0)
		{    
			// Send any error notifications
		 	utilities::reportErrors("No proxies to select");			

			// No proxies found, so stop 
		  	utilities::complete();			
		}	

		// Build proxy and SQL array
		while($proxy = mysql_fetch_array($result, MYSQL_ASSOC))
		{
			// for SQL
			$currentProxies[] = $proxy['proxy'];

			// Proxy array
			$proxies[] = $proxy;
		}
		 
		// 
		if(!$this->proxyStrain($proxies, $totalProxies))
		{
 			// Send any error notifications
		 	utilities::reportErrors("Too few proxies. Aborting to save lives.");			

			// No proxies found, so stop 
		  	utilities::complete();   		
		}

		// Glue the ips together for the update query below
		$currentProxies = implode("','", $currentProxies);

		// Update proxies use count
		// this update process should be called outside this function
		$sql = "UPDATE proxies SET hr_use = hr_use + 1 WHERE proxy IN ('$currentProxies')";
		mysql_query($sql) or utilities::reportErrors("ERROR ON proxy update: ".mysql_error());;   

		// Make sure proxy array count matches $totalProxies 
		$keyIndex = 0;
		while($totalProxies > count($proxies))
		{
			$proxies[] = $proxies[$keyIndex];
			$keyIndex++;
		}

		return $proxies;
	} 
	
	// Make sure there are enough proxies for a request not to get proxies blocked
	private function proxyStrain($proxies, $uses)
	{   
		// Get the amount of proxies being used
		$proxyTotal = count($proxies);
		
		return true;
	} 
	
	// *****************************************************************************
	// ** proxySelect
	// ** Grab a least used proxy from the database
	// **
	// ** @return		Nothing
	// *****************************************************************************
	private function proxySelect()
	{
		// Set current proxy
		$this->proxy = $this->proxies[$this->proxyKey];   
		
		// Increment key
		$this->proxyKey++;
	}
	
	// *****************************************************************************
	// ** curlAgentRandom
	// ** Pick a random array element from $this->agent array
	// **
	// ** @return		string      user-agent picked
	// *****************************************************************************
	private function curlAgentRandom()
	{
		$arrayIndex = rand(0, count($this->agent) - 1);
		return $this->agent[$arrayIndex];
	}
	
	// *****************************************************************************
	// ** curlRefererRandom
	// ** Pick a random array element from $this->agent array
	// **
	// ** @return		string      user-agent picked
	// *****************************************************************************
	private function curlRefererRandom()
	{
		$arrayIndex = rand(0, count($this->referer) - 1);
		return $this->referer[$arrayIndex];
	}
	
	// *****************************************************************************
	// ** curlRequestBuild
	// ** Initiate and set curl options for the HTTP request
	// **
	// ** @return		Nothing
	// *****************************************************************************
	private function curlRequestBuild()
	{
		// Loop through urls and build multi curl request
		foreach($this->urls as $i => $url)
		{
			// Get a fresh proxy
			if($this->proxy_use)
			{
				$this->proxySelect();
			}
							
			$this->ch[$i] = curl_init($url);
			
			// Include a cookie with the request
			curl_setopt($this->ch[$i], CURLOPT_COOKIEFILE, 'CURL');
			
			curl_setopt($this->ch[$i], CURLOPT_RETURNTRANSFER, TRUE);
			
			// Show response HTTP header along with the output ($headersParse Default is FALSE)
			curl_setopt($this->ch[$i], CURLOPT_HEADER, TRUE);
			
			// This option is required to show our on request HTTP headers in httpInfo array
			curl_setopt($this->ch[$i], CURLINFO_HEADER_OUT, TRUE);
			
			// Turn on curl error code debugging
			curl_setopt($this->ch[$i], CURLOPT_VERBOSE, TRUE);
			
			// Follow any HTTP Redirects
			if($this->redirectFollow)
			{
				// automatically set the Referer: field in requests where it follows
				// a Location: redirect
				curl_setopt($this->ch[$i], CURLOPT_AUTOREFERER, TRUE);
				
				// to follow any "Location: " header that the server sends
				curl_setopt($this->ch[$i], CURLOPT_FOLLOWLOCATION, TRUE);
				
				// The maximum amount of HTTP redirections to follow (we do not want
				// to follow more than once)
				curl_setopt($this->ch[$i], CURLOPT_MAXREDIRS, 1);
			}
			
			// If this request is using a proxy
			if($this->proxy_use)
			{                 
				// If proxy requires tunneling
				if($this->proxy['tunnel'] ===  1)
				{
					curl_setopt($this->ch[$i], CURLOPT_HTTPPROXYTUNNEL, TRUE);
				}
		
				curl_setopt($this->ch[$i], CURLOPT_PROXY, $this->proxy['proxy']);
				curl_setopt($this->ch[$i], CURLOPT_PROXYPORT, $this->proxy['port']);
				curl_setopt($this->ch[$i], CURLOPT_PROXYUSERPWD, $this->proxy['username'].":".$this->proxy['password']);
			}
			
			curl_setopt($this->ch[$i], CURLOPT_CONNECTTIMEOUT, CURL_TIMEOUT);
			curl_setopt($this->ch[$i], CURLOPT_TIMEOUT, 30);
			
			// Set random referer if array is supplied
			if(!empty($this->referer))
			{
				curl_setopt($this->ch[$i], CURLOPT_REFERER, $this->curlRefererRandom());
			}
			
			// Set random user-agent if array is supplied
			if(!empty($this->agent))
			{
				curl_setopt($this->ch[$i], CURLOPT_USERAGENT, $this->curlAgentRandom());
			}
			
			curl_multi_add_handle($this->mh, $this->ch[$i]);	
		}
		
	}
	
	// *****************************************************************************
	// ** curlContentExtract
	// ** Extract the HTTP response content from the curl requests
	// **
	// ** @return		Nothing
	// *****************************************************************************
	private function curlContentExtract()
	{
		$loop = 0;
		
	    // Loop through url array and get already scraped content
		foreach($this->urls as $i => $url)
		{                                          			
			// Get HTTP information array (response code, content-type, etc)
			$this->results[$i]['httpInfo'] = curl_getinfo($this->ch[$i]);
			
			// Check for errors
			$curlError = curl_error($this->ch[$i]);

			// Set the output data
			$this->results[$i]['output'] = curl_multi_getcontent($this->ch[$i]); 
						
			// Separate the header info from the content
			$this->results[$i]['output'] = explode("\r\n\r\n", $this->results[$i]['output'], 2);
			$this->results[$i]['headers'] = $this->results[$i]['output'][0];
			$this->results[$i]['output'] = $this->results[$i]['output'][1];
			
			// Check if we pulled the correct headers, if we use proxy we get something like this:
			// HTTP/1.0 200 Connection established
			if(strpos($this->results[$i]['headers'], "\r\n") === FALSE)
			{
				$this->results[$i]['output'] = explode("\r\n\r\n", $this->results[$i]['output'], 2);
				$this->results[$i]['headers'] = $this->results[$i]['output'][0];
				$this->results[$i]['output'] = $this->results[$i]['output'][1];
			}   
			
			// If proxy use on
			if($this->proxy_use)
			{
				// Add proxy info to results array
				$this->results[$i]['proxy_info']['proxy'] = $this->proxies[$loop]['proxy'];
				$this->results[$i]['proxy_info']['port'] = $this->proxies[$loop]['port'];
				$this->results[$i]['proxy_info']['username'] = $this->proxies[$loop]['username'];
				$this->results[$i]['proxy_info']['password'] = $this->proxies[$loop]['password'];
				$this->results[$i]['proxy_info']['source'] = (!empty($this->proxies[$loop]['source'])) ? $this->proxies[$loop]['source'] : '';  
			}   
			
			// Check headers for errors (302,407, blank response)
			$this->checkHeaders($i);
			
			// Remove current curl handle
			curl_multi_remove_handle($this->mh, $this->ch[$i]);
			
			// Close out current curl 
			curl_close($this->ch[$i]);
			
			// Increment loop counter
			$loop++;
		}
	}
	
	// Sort proxy errors into correct arrays for later update
	private function checkHeaders($i)
	{   
		// Increment the amount of total scrapes
		$this->scrapesTotal++;
		   
		// Scraped content has a 200 success code and size is greater than 5 bytes
		if($this->results[$i]['httpInfo']['http_code'] == 200 && $this->results[$i]['httpInfo']['size_download'] > 500 || $this->results[$i]['httpInfo']['http_code'] == 200 && STAT == 'alexa' )
		{   
			// Set the content scrape as a success
			$this->results[$i]['status'] = 'success';
			
			// Increment the amount of successful scrapes
			$this->scrapesGood++;						
		}
		// Headers didn't validate
		else
		{    		
			// If blank http code (curl timeout?)
			if($this->results[$i]['httpInfo']['http_code'] == 0)
			{			
				// If proxy timeout error
				if($this->results[$i]['error'] == 'Connection time-out')
				{	
					// If proxy use on
					if($this->proxy_use)
					{			
						// Add proxy to timeout list
						$this->proxiesTimeout[] = $this->results[$i]['proxy_info']['proxy'];
					}	
				}
				// Not a timeout response
				else
				{
					// Nothing returned  from proxy, just dead
					$this->proxiesDead[] = $this->results[$i]['proxy_info']['proxy'];
				}
			}			
			// If error code 302 block encountered
			elseif($this->results[$i]['httpInfo']['http_code'] == 302 
					|| $this->results[$i]['httpInfo']['http_code'] == 200 && $this->results[$i]['httpInfo']['size_download'] < 500)
			{   
				// If proxy use on
				if($this->proxy_use)
				{
					// Add proxy to blocked list
					$this->proxiesBlocked[] = $this->results[$i]['proxy_info']['proxy'];
				}	
			}  
			// If error code 407 auth encountered
			elseif($this->results[$i]['httpInfo']['http_code'] == 407)
			{   	
				// If proxy use on
				if($this->proxy_use)
				{
					// Add proxy to denied list
					$this->proxiesDenied[] = $this->results[$i]['proxy_info']['proxy'];
				}	
   			}
			// Not a timeout response
			else
			{
				// Nothing returned  from proxy, just dead
				utilities::notate("\tsom other error", "scrape.log");					

			}   			
 
			// Set the content scrape as a failure
			$this->results[$i]['status'] = 'error';  
			
			// Increment the amount of failed scrapes
			$this->scrapesBad++;
		   
		 	// Log current state
			//utilities::notate("\tcode: ".$this->results[$i]['httpInfo']['http_code'], "scrape.log");					
			//utilities::notate("\tsize: ".$this->results[$i]['httpInfo']['size_download'], "scrape.log");
			//utilities::notate("\tproxy source: ".$this->results[$i]['proxy_info']['source'], "scrape.log");			 
		} 	
	}
	
	// Update proxies
	private function updateProxies()
	{  
		utilities::notate("updating proxy section", "scrape.log");			

		// Update blocked proxies
		if(count($this->proxiesBlocked) > 0)
		{
			$query = "UPDATE proxies SET blocked_".$this->engine." = 1 WHERE proxy IN('".implode("','", $this->proxiesBlocked)."')";
			mysql_query($query) or utilities::reportErrors("ERROR ON proxy update: ".mysql_error());

	 		// Log current state
			utilities::notate("Proxies blocked: ".count($this->proxiesBlocked), "scrape.log");			
		}
		
		// Update blocked proxies
		if(count($this->proxiesDenied) > 0)
		{
			$query = "UPDATE proxies SET status = 'disabled' WHERE proxy IN('".implode("','", $this->proxiesDenied)."')";
			mysql_query($query) or utilities::reportErrors("ERROR ON proxy update: ".mysql_error());

	 		// Log current state
			utilities::notate("Proxies denied: ".count($this->proxiesDenied), "scrape.log");				
		}
		
		// Update timed out proxies
		if(count($this->proxiesTimeout) > 0)
		{
			$query = "UPDATE proxies SET timeouts = timeouts + 1 WHERE proxy IN('".implode("','", $this->proxiesTimeout)."')";
			mysql_query($query) or utilities::reportErrors("ERROR ON proxy update: ".mysql_error());

	 		// Log current state
			utilities::notate("Proxies timedout: ".count($this->proxiesTimeout), "scrape.log");				
		}	
		
		// Update timed out proxies
		if(count($this->proxiesDead) > 0)
		{
			$query = "UPDATE proxies SET dead = dead + 1 WHERE proxy IN('".implode("','", $this->proxiesDead)."')";
			mysql_query($query) or utilities::reportErrors("ERROR ON proxy update: ".mysql_error());

	 		// Log current state
			utilities::notate("Proxies dead: ".count($this->proxiesDead), "scrape.log");				
		}						
	}
}


?>