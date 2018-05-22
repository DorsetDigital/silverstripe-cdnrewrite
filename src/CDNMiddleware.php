<?php
namespace DorsetDigital\CDNRewrite;

use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Control\Director;

class CDNMiddleware implements HTTPMiddleware
{

 use Injectable;
 use Configurable;

 /**
  * @config
  *
  * Enable rewriting
  * @var bool
  */
 private static $cdn_rewrite = false;

 /**
  * @config
  *
  * The cdn domain incl. protocol
  * @var string
  */
 private static $cdn_domain = '';

 /**
  * @config
  *
  * Enable rewrite in dev mode
  * @var bool
  */
 private static $enable_in_dev = false;

 /**
  * @config
  *
  * should assets be rewritten?
  * @var bool
  */
 private static $rewrite_assets = false;

 /**
  * @config
  *
  * should resources also be rewritten?
  * @var bool
  */
 private static $rewrite_resources = false;

 /**
  * @config
  *
  * Add debug headers for each operation
  * @var bool
  */
 private static $add_debug_headers = false;

 /**
  * Process the request
  * @param HTTPRequest $request
  * @param $delegate
  * @return
  */
 public function process(HTTPRequest $request, callable $delegate)
 {

  $response = $delegate($request);

  if ($this->canRun() === true) {
   $response->addHeader('X-CDN', 'Enabled');

   if (substr($request->getURL(), 0, 6) !== 'admin/') {
    $body = $response->getBody();
    $this->updateBody($body, $response);
    $response->setBody($body);
   }

   if ($this->config()->get('add_debug_headers') == true) {
    $response->addHeader('X-CDN-Domain', $this->config()->get('cdn_domain'));
   }
  }

  return $response;
 }

 private function canRun()
 {
  $confEnabled = $this->config()->get('cdn_rewrite');
  $devEnabled = ((!Director::isDev()) || ($this->config()->get('enable_in_dev')));

  return ($confEnabled && $devEnabled);
 }

 private function updateBody(&$body, &$response)
 {
  $cdn = $this->config()->get('cdn_domain');

  if ($this->config()->get('rewrite_assets') === true) {

   $search = [
       'src="assets/',
       'src="/assets/',
       'src=\"/assets/',
       'href="/assets/',
       Director::absoluteBaseURL() . 'assets/'
   ];

   $replace = [
       'src="' . $cdn . '/assets/',
       'src="' . $cdn . '/assets/',
       'src=\"' . $cdn . '/assets/',
       'href="' . $cdn . '/assets/',
       $cdn . '/assets/'
   ];

   $body = str_replace($search, $replace, $body);

   if ($this->config()->get('add_debug_headers') == true) {
    $response->addHeader('X-CDN-Assets', 'Enabled');
   }
  }

  if ($this->config()->get('rewrite_resources') === true) {

   $search = [
       'src="/resources/',
       'src="' . Director::absoluteBaseURL() . 'resources/',
       'href="/resources/',
       'href="' . Director::absoluteBaseURL() . 'resources/'
   ];

   $replace = [
       'src="' . $cdn . '/resources/',
       'src="' . $cdn . '/resources/',
       'href="' . $cdn . '/resources/',
       'href="' . $cdn . '/resources/'
   ];

   $body = str_replace($search, $replace, $body);

   if ($this->config()->get('add_debug_headers') == true) {
    $response->addHeader('X-CDN-Resources', 'Enabled');
   }
  }
 }
}
