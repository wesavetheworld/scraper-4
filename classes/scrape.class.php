<?php if(!defined('CORE')) exit("Go away. Pass commands through router.php\n");

// ******************************* INFORMATION *******************************//

// ***************************************************************************//
//  
// ** SCRAPER - The class used for scraping with cURL 
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-06-21
// ** @access	private
// ** @param	
// ** @return	constants for application 
//  	
// ***************************************************************************//

// ********************************** START **********************************//

class scraper
{
	// ===========================================================================// 
	// ! Configuration                                                            //
	// ===========================================================================//
	
	// Array of urls to scrape 
	public $urls = array(); 
	 
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
	public $proxiesBlocked = array(); 
	
	// Will contain any proxies that returned 407 auth errors
	public $proxiesDenied = array();  

	// Will contain any proxies that timeout
	public $proxiesTimeout = array(); 
	
	// Will contain any proxies that timeout
	public $proxiesDead = array(); 

	// Will contain any proxies that have unknown errors
	public $proxiesOther = array(); 	
		
	// The amount of scrapes that fail
	public $scrapesBad = 0; 
	
	// The amount of scrapes that succeed
	public $scrapesGood = 0;

	// The total amount of new scrapes performed
	public $scrapesTotal = 0;	
	
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
		// Get array of user agents to use
		$this->agent = json_decode(USER_AGENTS);

		// Get array of referrers to use
		$this->referer = json_decode(REFERRERS);		
	  
	}	
	
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
			//utilities::notate("Bad scrapes: ".$this->scrapesBad." of ".$this->scrapesTotal." total scrapes", "scrape.log");		 
		}	
		
		// Clean up the curl_multi handle
		curl_multi_close($this->mh);
	}
	
	
	// *****************************************************************************
	// ** curlAgentRandom
	// ** Pick a random array element from $this->agent array
	// **
	// ** @return		string      user-agent picked
	// *****************************************************************************
	private function curlAgentRandom()
	{
		//return "";
		
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
		return "";
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
		// Open log file for
		//$this->fp = fopen('/home/ec2-user/data/logs/curl.log', "w");

		// Loop through urls and build multi curl request
		foreach($this->urls as $i => $url)
		{
							
			$this->ch[$i] = curl_init($url);
			
			// Include a cookie with the request
			curl_setopt($this->ch[$i], CURLOPT_COOKIEFILE, 'CURL');
			
			curl_setopt($this->ch[$i], CURLOPT_RETURNTRANSFER, TRUE);
			
			// Show response HTTP header along with the output ($headersParse Default is FALSE)
			curl_setopt($this->ch[$i], CURLOPT_HEADER, TRUE);
			
			// This option is required to show our on request HTTP headers in httpInfo array
			curl_setopt($this->ch[$i], CURLINFO_HEADER_OUT, TRUE);
			
			// Turn on curl error code debugging
			//curl_setopt($this->ch[$i], CURLOPT_VERBOSE, TRUE);
			
			// Write errors to log file
			//curl_setopt($this->ch[$i], CURLOPT_STDERR, $this->fp);
			
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

				// print_r($this->proxies);
				// echo "i: $i";
		
				curl_setopt($this->ch[$i], CURLOPT_PROXY, $this->proxies[$i]['ip']);
				curl_setopt($this->ch[$i], CURLOPT_PROXYPORT, $this->proxies[$i]['port']);
				curl_setopt($this->ch[$i], CURLOPT_PROXYUSERPWD, $this->proxies[$i]['username'].":".$this->proxies[$i]['password']);

				//echo "proxy used: ".$this->proxies[$i]['ip']."\n";
			}

			// Timeout settings in config/scraping.config.php
			curl_setopt($this->ch[$i], CURLOPT_CONNECTTIMEOUT, CURL_CONNECT_TIMEOUT);
			curl_setopt($this->ch[$i], CURLOPT_TIMEOUT, CURL_TIMEOUT);			
			
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
	    // Loop through url array and get already scraped content
		foreach($this->urls as $i => $url)
		{                                          			
			// Get HTTP information array (response code, content-type, etc)
			$this->results[$i]['httpInfo'] = curl_getinfo($this->ch[$i]);

			// Attach any cUrl errors to output
			$this->results[$i]['curlError'] = curl_error($this->ch[$i]);

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
			
			// Check headers for errors (302,407, blank response)
			$this->checkHeaders($i);
			
			// Remove current curl handle
			curl_multi_remove_handle($this->mh, $this->ch[$i]);
			
			// Close out current curl 
			curl_close($this->ch[$i]);
		}
	}
	
	// Sort proxy errors into correct arrays for later update
	private function checkHeaders($i)
	{   
		// Increment the amount of total scrapes
		$this->scrapesTotal++;

		// If curl returned an error 
		if($this->results[$i]['curlError'])
		{
			// If error code 302 block encountered
			if($this->results[$i]['httpInfo']['http_code'] == 302 || $this->results[$i]['httpInfo']['http_code'] == 503) 
			{   
				// If proxy use on
				if($this->proxy_use)
				{
					// For updating the redis proxy
					$this->results[$i]['blocked'] = true;

					// Add proxy to blocked list
					$this->proxiesBlocked[] = $this->proxies[$i]['ip'];

					//echo "blocked proxy: ".$this->proxies[$i]['ip']."\n";
				}	
			}  
			else
			{
				// Add proxy to timeout list
				$this->proxiesTimeout[] = $this->proxies[$i]['ip'];				
			}

			//echo "\nThere was an error: ".$this->results[$i]['curlError']."\n";
			// Set the content scrape as a failure
			$this->results[$i]['status'] = 'error';  			
			
			// Increment the amount of failed scrapes
			$this->scrapesBad++;

			return false;
		}
		   	
		   	
		// Scraped content has a 200 success code and size is greater than 5 bytes
		if($this->results[$i]['httpInfo']['http_code'] == 200 && $this->results[$i]['httpInfo']['size_download'] > 500 || $this->results[$i]['httpInfo']['http_code'] == 200 && $this->task != "rankings")
		{   
			// Set the content scrape as a success
			$this->results[$i]['status'] = 'success';
			
			// Add proxy used to good proxy array
			$this->proxiesGood[] = $this->proxies[$i]['ip'];			
			
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
						$this->proxiesTimeout[] = $this->proxies[$i]['ip'];
					}	
				}
				// Not a timeout response
				else
				{
					// Nothing returned  from proxy, just dead
					$this->proxiesDead[] = $this->proxies[$i]['ip'];
				}
			}			
			// If error code 302 block encountered
			elseif($this->results[$i]['httpInfo']['http_code'] == 302
					|| $this->results[$i]['httpInfo']['http_code'] == 503 
					|| $this->results[$i]['httpInfo']['http_code'] == 403 
					|| $this->results[$i]['httpInfo']['http_code'] == 200 && $this->results[$i]['httpInfo']['size_download'] < 500)
			{   
				// If proxy use on
				if($this->proxy_use)
				{
					// For updating the redis proxy
					$this->results[$i]['blocked'] = true;
										
					// Add proxy to blocked list
					$this->proxiesBlocked[] = $this->proxies[$i]['ip'];
				}	
			}  
			// If error code 407 auth encountered
			elseif($this->results[$i]['httpInfo']['http_code'] == 407)
			{   	
				// If proxy use on
				if($this->proxy_use)
				{
					// Add proxy to denied list
					$this->proxiesDenied[] = $this->proxies[$i]['ip'];
				}	
   			}
			// Not a timeout response
			else
			{
				echo "OTHER ERROR!!!!\n";
				// Add proxy to other problem list
				$this->proxiesOther[] = $this->proxies[$i]['ip'];

				// Nothing returned  from proxy, just dead
				//utilities::notate("\tsome other error", "scrape.log");					

			}   			
 
			// Set the content scrape as a failure
			$this->results[$i]['status'] = 'error';  
			
			// Increment the amount of failed scrapes
			$this->scrapesBad++;
		   
		 	// Log current state
			//utilities::notate("\tcode: ".$this->results[$i]['httpInfo']['http_code'], "scrape.log");					
			//utilities::notate("\tsize: ".$this->results[$i]['httpInfo']['size_download'], "scrape.log");
			//utilities::notate("\tproxy source: ".$this->proxies[$i]['source'], "scrape.log");			 
		} 	
	}
	
	
}

// ********************************** END **********************************// 
