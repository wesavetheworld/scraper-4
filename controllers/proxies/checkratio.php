<?php  if(!defined('HUB')) exit('No direct script access allowed\n');
	
	/*
		This script should be run every 30 mins
	*/

	// ===========================================================================// 
	// ! Configuration                                                            //
	// ===========================================================================//
	
	// 30 scrapes per minute
	$maxScrapesAllowedPerMin = 30;
	
	// Alert us when a proxy has been blocked 72 consecutive times (if cron runs every hour, this would make it 3 days)
	$alertWhenProxyBlockedCount = 72;
	
	// Email alert settings
	$emailTo = "thezenman@gmail.com";
	
	// ===========================================================================// 
	// ! Get database data                                                        //
	// ===========================================================================//
	
	// Connect to database
	utilities::databaseConnect();
	
	// ===========================================================================// 
	// ! Notifo                                                                   //
	// ===========================================================================//
	
	// start notifo class
	$notifo = new Notifo_API(NOTIFO_API_USERNAME, NOTIFO_API_SECRET);
	
	// usernames to which notifications will go
	if(defined('NOTIFO_NOTIFY_USERNAMES'))
	{
		$notifoNotifyUsernames = unserialize(NOTIFO_NOTIFY_USERNAMES);
	}
	
	// ===========================================================================// 
	// ! Twilio                                                                   //
	// ===========================================================================//
	
	// start twilio class
	// $twilio = new TwilioRestClient(TWILIO_API_ACCOUNT_SID, TWILIO_API_AUTH_TOKEN);
	// 
	// // mobiles to which notifications will go
	// $twilioNotifyMobiles = array();
	// if(defined('TWILIO_API_NOTIFY_MOBILE'))
	// {
	// 	$twilioNotifyMobiles = unserialize(TWILIO_API_NOTIFY_MOBILE);
	// }     
	
	// ===========================================================================// 
	// ! Check proxy ratio                                                        //
	// ===========================================================================//
	
	// Google blocks seem to last 70 mins total
	// about 20,500 scrapes per proxy
	// I did 278 per minute over 7 minutes
	// 50k keywords * 4 pages = 200,000 pages we scraped every hour
	// 200,000 / 500 = ( 400 pages per proxy hour )
	// 400 pages / 60 minutes = each proxy has to scrape 6.6 pages per minute
	
	// 30 scrapes per minute

	// each hour each ip has to scrape 400 pages from google
	
	// Get counts
	$query = "
	SELECT
	(SELECT COUNT(*) FROM proxies) as proxy_count,
	(SELECT COUNT(*) FROM proxies WHERE blocked_google > 0) as blocked_google_total,
	(SELECT COUNT(*) FROM proxies WHERE blocked_bing > 0) as blocked_bing_total,
	(SELECT COUNT(*) FROM keywords) as keywords_total
	";
	$result = mysql_query($query) or die(mysql_error());
	$stats = mysql_fetch_array($result, MYSQL_ASSOC);
	
	// total keywords * 4 pages
	$scrapesNeededPerHour = $stats['keywords_total'] * 4;
	
	// proxies not blocked
	$proxiesNotBlocked = $stats['proxy_count'] - $stats['blocked_google_total'];
	
	// scrapes needed per hour / 60 mins
	$scrapesNeededPerMin = $scrapesNeededPerHour / 60;
	
	// max scrapes allowed by our good proxies
	$proxiesAllowedMaxPerMin = $proxiesNotBlocked * $maxScrapesAllowedPerMin;
	
	// Log line
	$statsLog = "
	Total Keywords: {$stats['keywords_total']}
	
	Total Proxies: {$stats['proxy_count']}
		Not Blocked: {$proxiesNotBlocked}
		Blocked by Google: {$stats['blocked_google_total']}
		Blocked by Bing: {$stats['blocked_bing_total']}
	
	Scrapes needed per hour: {$scrapesNeededPerHour}
	Scrapes needed per min: {$scrapesNeededPerMin}
	
	Max Scrapes per minute (unblocked proxies): {$proxiesAllowedMaxPerMin}
	
	";
	

	// Output status
	echo $statsLog;
		
	// Notify us (If ratio is low)
	if($scrapesNeededPerMin > $proxiesAllowedMaxPerMin)
	{
		// Send notifo messages 
		if(!empty($notifoNotifyUsernames))
		{
			echo "Sending Notifo low proxies ratio alerts\n";

			foreach($notifoNotifyUsernames as $notifoUsername)
			{
				$notifo->send_notification(array(
							"to"    =>  $notifoUsername,
							"title" =>  "Proxy ratio low",
							"msg"   =>  $statsLog,
							"uri"   =>  ""
					));
			}
		}
		
		// Send twilio text messages
		if(!empty($twilioNotifyMobiles))
		{
			echo "Sending Twilio low proxies ratio text alerts\n";
			
			foreach($twilioNotifyMobiles as $mobileNumber)
			{
				$response = $twilio->request("/".TWILIO_API_VERSION."/Accounts/".TWILIO_API_ACCOUNT_SID."/SMS/Messages", 
							"POST", array(
							"To" => $mobileNumber,
							"From" => TWILIO_API_NUMBER,
							"Body" => "Proxy ratio low. see log file."
						));
			}
		}
	}
	
	// ===========================================================================// 
	// ! Check possible dead proxies                                              //
	// ===========================================================================//
	
	// Check for blocked proxies that have been blocked consecutive
	$query = "
	SELECT
		*
	
	FROM
		proxies
	
	WHERE
		blocked_google >= {$alertWhenProxyBlockedCount}
	";
	$result = mysql_query($query) or die(mysql_error());
	$deadProxies = array();
	while($blockedProxy = mysql_fetch_array($result, MYSQL_ASSOC))
	{
		$deadProxies[] = $blockedProxy;
	}
	
	// Notify us (If proxies seem dead)
	if(!empty($deadProxies))
	{
		$message = "The following proxies might be dead:\n\n\n".print_r($deadProxies, true);
		
		// Output status
		echo $message;
		
		// Send notifo messages (If ratio is low)
		if(!empty($notifoNotifyUsernames))
		{
			echo "Sending Notifo dead proxy alerts\n";

			foreach($notifoNotifyUsernames as $notifoUsername)
			{
				$notifo->send_notification(array(
							"to"    =>  $notifoUsername,
							"title" =>  "Possible dead proxies",
							"msg"   =>  substr($message, 0, 300),
							"uri"   =>  ""
					));
			}
		}
		
		// Send twilio text messages
		if(!empty($twilioNotifyMobiles))
		{
			echo "Sending Twilio low proxies ratio text alerts\n";
			
			foreach($twilioNotifyMobiles as $mobileNumber)
			{
				$response = $twilio->request("/".TWILIO_API_VERSION."/Accounts/".TWILIO_API_ACCOUNT_SID."/SMS/Messages", 
							"POST", array(
							"To" => $mobileNumber,
							"From" => TWILIO_API_NUMBER,
							"Body" => "Possible dead proxies. see log file."
						));
			}
		}
	}


?>