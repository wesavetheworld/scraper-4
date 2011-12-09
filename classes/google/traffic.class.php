<?php


// error_reporting(E_STRICT | E_ALL);

// // You can set the include path to src directory or reference
// // AdWordsUser.php directly via require_once.
// // $path = '/path/to/aw_api_php_lib/src';
// $path = dirname(__FILE__) . '/src';
// set_include_path(get_include_path() . PATH_SEPARATOR . $path);

// require_once 'Google/Api/Ads/AdWords/Lib/AdWordsUser.php';

// try {
//   // Get AdWordsUser from credentials in "../auth.ini"
//   // relative to the AdWordsUser.php file's directory.
//   $user = new AdWordsUser();

//   // Log SOAP XML request and response.
//   $user->LogDefaults();

//   // Get the TrafficEstimatorService.
//   $targetingIdeaService = $user->GetService('TargetingIdeaService', 'v201109');

//   // Create keywords. Up to 2000 keywords can be passed in a single request.
//   $keywords = array();
//   $keywords[] = new Keyword('cruise', 'EXACT');
  
//   // $paging = new Paging(); 
//   // $paging->startIndex = 0; 
//   // $paging->numberResults = 10; 
//   // $selector->paging = $paging; 

//   $relatedToKeywordSearchParameter = new RelatedToKeywordSearchParameter(); 
//   $relatedToKeywordSearchParameter->keywords = $keywords; 

//   $keywordMatchTypeSearchParameter = new KeywordMatchTypeSearchParameter(array('EXACT')); 

//   // Set targeting criteria. Only locations and languages are supported.
//   $country = new Location();
//   // US
//   $country->locationName = 'GB';

//   $language = new Language();
//   // english
//   $language->code = 'en';

//   $selector = new TargetingIdeaSelector(); 
//   $selector->requestType = 'STATS'; 
//   $selector->ideaType = 'KEYWORD'; 
//   $selector->requestedAttributeTypes = array('KEYWORD', 'TARGETED_MONTHLY_SEARCHES');

//   $selector->searchParameters = array($relatedToKeywordSearchParameter,
//                                       $country, 
//                                       $language, 
//                                       $keywordMatchTypeSearchParameter); 

//   // Get traffic estimates.
//   $result = $targetingIdeaService->get($selector);

//   // Display traffic estimates.
//   if(isset($result)) 
//   {

//   } 
//   else 
//   {
//     print "No traffic estimates were returned.\n";
//   }
// } catch (Exception $e) {
//   print $e->getMessage();
// }

error_reporting(E_STRICT | E_ALL);

// You can set the include path to src directory or reference
// AdWordsUser.php directly via require_once.
// $path = '/path/to/aw_api_php_lib/src';
$path = dirname(__FILE__) . '/src';
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

require_once 'Google/Api/Ads/AdWords/Lib/AdWordsUser.php';
require_once 'Google/Api/Ads/Common/Util/MapUtils.php';

try {
  // Get AdWordsUser from credentials in "../auth.ini"
  // relative to the AdWordsUser.php file's directory.
  $user = new AdWordsUser();

  // Log SOAP XML request and response.
  $user->LogDefaults();

  // Get the TargetingIdeaService.
  $targetingIdeaService = $user->GetService('TargetingIdeaService', 'v201008');

  // Create seed keyword.
  $keywords[] = new Keyword('crappy pants', 'EXACT');
  $keywords[] = new Keyword('sex', 'EXACT');
  $keywords[] = new Keyword('seo', 'EXACT');

  // Create selector.
  $selector = new TargetingIdeaSelector();
  $selector->requestType = 'STATS';
  $selector->ideaType = 'KEYWORD';
  $selector->requestedAttributeTypes = array('KEYWORD', 'AVERAGE_TARGETED_MONTHLY_SEARCHES');

  // Set selector paging (required for targeting idea service).
  $paging = new Paging();
  $paging->startIndex = 0;
  $paging->numberResults = 10;
  $selector->paging = $paging;

  // Create related to keyword search parameter.
  $relatedToKeywordSearchParameter = new RelatedToKeywordSearchParameter();
  $relatedToKeywordSearchParameter->keywords = $keywords;

  // Create keyword match type search parameter to ensure unique results.
  $keywordMatchTypeSearchParameter = new KeywordMatchTypeSearchParameter();
  $keywordMatchTypeSearchParameter->keywordMatchTypes = array('EXACT');

  $selector->searchParameters =
  array($relatedToKeywordSearchParameter, $keywordMatchTypeSearchParameter);

  // Get related keywords.
  $page = $targetingIdeaService->get($selector);

  // Display related keywords.
  if (isset($page->entries)) {
    foreach ($page->entries as $targetingIdea) {
      $data = MapUtils::GetMap($targetingIdea->data);
      $keyword = $data['KEYWORD']->value;
      $averageMonthlySearches =
          isset($data['AVERAGE_TARGETED_MONTHLY_SEARCHES']->value)
          ? $data['AVERAGE_TARGETED_MONTHLY_SEARCHES']->value : 0;
      printf("Keyword with text '%s', match type '%s', and average monthly "
          . "search volume '%s' was found.\n", $keyword->text,
          $keyword->matchType, $averageMonthlySearches);
    }
  } else {
    print "No related keywords were found.\n";
  }
} catch (Exception $e) {
  print $e->getMessage();
}
