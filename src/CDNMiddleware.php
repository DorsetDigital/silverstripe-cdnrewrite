<?php
namespace DorsetDigital\CDNRewrite;

use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Control\Director;
use SilverStripe\Admin\AdminRootController;
use SilverStripe\View\HTML;

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
  * @config
  *
  * Subdirectory name for the site
  * @var string
  */
 private static $subdirectory = '';

 /**
  * @config
  * 
  * Add dns-prefetch links to the html head
  * @var boolean
  */
 private static $add_prefetch = false;
 
 
 /**
  * Process the request
  * @param HTTPRequest $request
  * @param $delegate
  * @return
  */
 public function process(HTTPRequest $request, callable $delegate)
 {

  $response = $delegate($request);

  if (($this->canRun() === true) && ($response !== null)) {
   $response->addHeader('X-CDN', 'Enabled');

   if ($this->getIsAdmin($request) === false) {
    $body = $response->getBody();
    $this->updateBody($body, $response);
    $response->setBody($body);
   }

   if ($this->config()->get('add_debug_headers') == true) {
    $response->addHeader('X-CDN-Domain', $this->config()->get('cdn_domain'));
    $response->addHeader('X-CDN-Dir', $this->getSubdirectory());
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
  $subDir = $this->getSubdirectory();

  if ($this->config()->get('rewrite_assets') === true) {

   $search = [
       'src="' . $subDir . 'assets/',
       'src="/' . $subDir . 'assets/',
       'src=\"/' . $subDir . 'assets/',
       'href="/' . $subDir . 'assets/',
       Director::absoluteBaseURL() . 'assets/'
   ];

   $replace = [
       'src="' . $cdn . '/' . $subDir . 'assets/',
       'src="' . $cdn . '/' . $subDir . 'assets/',
       'src=\"' . $cdn . '/' . $subDir . 'assets/',
       'href="' . $cdn . '/' . $subDir . 'assets/',
       $cdn . '/' . $subDir . 'assets/'
   ];

   $body = str_replace($search, $replace, $body);

   if ($this->config()->get('add_debug_headers') == true) {
    $response->addHeader('X-CDN-Assets', 'Enabled');
   }
   
   if ($this->config()->get('add_prefetch') === true) {
     $prefetchTag = $this->getPrefetchTag();
     $body = str_replace('<head>', "<head>" . $prefetchTag, $body);
     if ($this->config()->get('add_debug_headers') == true) {
       $response->addHeader('X-CDN-Prefetch', 'Enabled');
     }       
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

 private function getSubdirectory()
 {
  $subDir = trim($this->config()->get('subdirectory'), '/');
  if ($subDir != "") {
   $subDir = $subDir . '/';
  }
  return $subDir;
 }
 
 private function getPrefetchTag() 
 {
   $atts = [
     'rel' => 'dns-prefetch',
     'href' => $this->config()->get('cdn_domain')
   ];
   $pfTag = "\n" . HTML::createTag('link', $atts);
        
   return $pfTag;
 }

 /**
  * Determine whether the website is being viewed from an admin protected area or not
  * (shamelessly stolen from https://github.com/silverstripe/silverstripe-subsites)
  *
  * @param  HTTPRequest $request
  * @return bool
  */
 private function getIsAdmin(HTTPRequest $request)
 {
  $adminPaths = static::config()->get('admin_url_paths');
  $adminPaths[ ] = AdminRootController::config()->get('url_base') . '/';
  $currentPath = rtrim($request->getURL(), '/') . '/';
  foreach ($adminPaths as $adminPath) {
   if (substr($currentPath, 0, strlen($adminPath)) === $adminPath) {
    return true;
   }
  }
  return false;
 }
}
