<?php  if(!defined('HUB')) exit('No direct script access allowed\n');

//=================================================================================================================//
//=================================================================================================================//
//! When a keyword's date field shows that its never been updated, but there is actually tracking data in the table, 
//  the scraper will try to do an insert, but then fail.  This finds those cases and fixes them.  This is usually 
//  caused when something interrupts the scraper before it gets to update the keywords
//=================================================================================================================//
//=================================================================================================================//

class fix 
{    
	
	function __construct()
	{
		// Connect to database
		utilities::databaseConnect();  	

		// Having memory issues locally
		ini_set('memory_limit', '100512M'); 
	}
	
	public function fix()
	{   
		// This part checks if there are any keywords that dont show that they have been updated, but really have
		// In these cases a duplicate key error happens
		// The old scraper updated the keyword table all at once, so if somethin failed during tracking update, keyword table was left unupdated

		$date = date("Y-m-d");

		$query = "	SELECT 
						keywords.date as keywordDate,
						keywords.keyword_id as keyword_id,
						keywords.keyword,
						keywords.status,
						tracking.date as trackingDate
		 			FROM 
		 				keywords 
		 			LEFT JOIN 
		 				tracking ON tracking.keyword_id=keywords.keyword_id 
		 			WHERE 
		 				keywords.date != '$date'
		 			AND
		 				keywords.status !='suspended'";

		$result = mysql_query($query) or die(mysql_error()); 	

		$count = 0;
		while($row = mysql_fetch_array($result))
		{
			if($row['keywordDate'] != $date && $row['trackingDate'] == $date )
			{
				echo $row['keywordDate']." | ".$row['trackingDate']." | ".$row['keyword_id']."\n";
				
				$query2 = " DELETE FROM
								tracking
							WHERE
								keyword_id = {$row['keyword_id']}
							AND
								date = '$date'";	
						
				$result2 = mysql_query($query2) or die(mysql_error()); 		 
				$count++;   
			}
		}

		echo "\nTotal keywords fixed : $count\n";
	}
}		
		   

?>