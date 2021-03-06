<?php
/**
 * This example gets all images. To upload an image, run UploadImage.php.
 *
 * Tags: MediaService.get
 *
 * PHP version 5
 *
 * Copyright 2011, Google Inc. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @package    GoogleApiAdsAdWords
 * @subpackage v201109
 * @category   WebServices
 * @copyright  2011, Google Inc. All Rights Reserved.
 * @license    http://www.apache.org/licenses/LICENSE-2.0 Apache License,
 *             Version 2.0
 * @author     Eric Koleda <api.ekoleda@gmail.com>
 */

error_reporting(E_STRICT | E_ALL);

// You can set the include path to src directory or reference
// AdWordsUser.php directly via require_once.
// $path = '/path/to/aw_api_php_lib/src';
$path = dirname(__FILE__) . '/../../src';
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

require_once 'Google/Api/Ads/AdWords/Lib/AdWordsUser.php';
require_once 'Google/Api/Ads/Common/Util/MediaUtils.php';
require_once 'Google/Api/Ads/Common/Util/MapUtils.php';

try {
  // Get AdWordsUser from credentials in "../auth.ini"
  // relative to the AdWordsUser.php file's directory.
  $user = new AdWordsUser();

  // Log SOAP XML request and response.
  $user->LogDefaults();

  // Get the MediaService.
  $mediaService = $user->GetService('MediaService', 'v201109');

  // Create selector.
  $selector = new Selector();
  $selector->fields = array('MediaId', 'Width', 'Height', 'MimeType');
  $selector->ordering = array(new OrderBy('MediaId', 'ASCENDING'));

  // Create predicates.
  $typePredicate = new Predicate('Type', 'IN', array('IMAGE'));
  $selector->predicates = array($typePredicate);

  // Get all images.
  $page = $mediaService->get($selector);

  // Display images.
  if (isset($page->entries)) {
    foreach ($page->entries as $image) {
      $dimensions = MapUtils::GetMap($image->dimensions);
      printf("Image with id '%s', dimensions '%dx%d', and MIME type '%s' was "
          . "found.\n",
          $image->mediaId, $dimensions['FULL']->width,
          $dimensions['FULL']->height, $image->mimeType);
    }
  } else {
    print "No images were found.\n";
  }
} catch (Exception $e) {
  print $e->getMessage();
}
