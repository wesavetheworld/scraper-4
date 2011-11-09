<?php if(!defined('ROUTER')) exit("Go away. Pass commands through router.php\n");

class parse
{  
	
	// Will contain an array of elements parsed
	public $elements = array();

	// The search engine results being parsed
	public $engine;
   
	// ===========================================================================// 
	// ! Parsing methods                                                          //
	// ===========================================================================//

    // Find all of the desired matching elements in a string
	public function findElements($find, $html)
	{   		
		// Find all of the matches 
		preg_match_all($find, $html, $results); 
       
		// Add matches to element array
		$this->elements = $results[1]; 
		
		// For method chaining return object
		return $this;
	}

    // Loop through elements array
	public function findInElements($find)
	{		
		// Make raw domain safe for preg_match 
		$find = $this->pregMatchSafe($find);		
		
		// Loop through each each element
		foreach($this->elements as $position => $element)
		{				
			// If current element matches element searched for
			if(preg_match("/$find/i", $element)) 
			{    
				// The matching element found
			   	$this->found = $element;     
			    
				// The found position in array 
				$this->position = $position + 1;

				// Match found so end loop
				break;
			}
		}
	}
	
	// Parse the pagerank response and return just the rank
	public function pageRank($pr)
	{      
		$pos = strpos($pr, "Rank_");
		
		if($pos === false)
		{
			$pr = 0;
		} 
		else
		{
		    $pr = trim(substr($pr, $pos + 9));
		}
	
		return $pr;  
	}
	
	// Process Alexa response xml
	public function alexa($xml)
	{   
		// Convert alexa response into an xml object
		$xml =  simplexml_load_string($xml);			
		
		// If anything is wrong with the xml response
		if(!is_object($xml) || !isset($xml->SD[1]) || !is_object($xml->SD[1]))
		{   
			// No alexa data for this domain            
			return "0";
		} 
		
		// Set alexa rank for domain
		return trim($xml->SD[1]->POPULARITY['TEXT']);
	} 
	
	// ===========================================================================// 
	// ! Supporting functions                                                     //
	// ===========================================================================//	
	
	// Make a string safe for preg_match 
	private function pregMatchSafe($str)
	{		
		// Convert to all lowercase
		$str = strtolower($str);
		
		// Escape regular expression characters
		$str = preg_quote($str, '/');
		
		// Looks for "." after www or "/" after http:// to rule out similar domain matches
		$str = "(\.|\/)".$str;

		return $str;
	}
}


?>