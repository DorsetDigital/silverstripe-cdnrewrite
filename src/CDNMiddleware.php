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
  * should themes also be rewritten?
  * @var bool
  */
 private static $rewrite_themes = false;

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

  if ($this->config()->get('rewrite_assets') === true) {
   $this->rewriteAssets($body, $response);
  }

  if ($this->config()->get('rewrite_resources') === true) {
   $this->rewriteResources($body, $response);
  }

  if ($this->config()->get('rewrite_themes') === true) {
   $this->rewriteThemes($body, $response);
  }

  if ($this->config()->get('add_prefetch') === true) {
   $this->addPrefetch($body, $response);
  }
 }

 private function rewriteAssets(&$body, &$response)
 {

  $cdn = $this->config()->get('cdn_domain');
  $subDir = $this->getSubdirectory();

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
 }

 private function rewriteThemes(&$body, &$response)
 {

  $cdn = $this->config()->get('cdn_domain');
  $subDir = $this->getSubdirectory();

  $search = [
      'src="' . $subDir . 'themes/',
      'src="/' . $subDir . 'themes/',
      'src=\"/' . $subDir . 'themes/',
      'href="/' . $subDir . 'themes/',
      Director::absoluteBaseURL() . 'themes/'
  ];

  $replace = [
      'src="' . $cdn . '/' . $subDir . 'themes/',
      'src="' . $cdn . '/' . $subDir . 'themes/',
      'src=\"' . $cdn . '/' . $subDir . 'themes/',
      'href="' . $cdn . '/' . $subDir . 'themes/',
      $cdn . '/' . $subDir . 'themes/'
  ];

  $body = str_replace($search, $replace, $body);

  if ($this->config()->get('add_debug_headers') == true) {
   $response->addHeader('X-CDN-Themes', 'Enabled');
  }
 }

 private function rewriteResources(&$body, &$response)
 {

  $cdn = $this->config()->get('cdn_domain');
  $subDir = $this->getSubdirectory();

  $search = [
      'src="/' . $subDir . 'resources/',
      'src="' . Director::absoluteBaseURL() . $subDir . 'resources/',
      'href="/' . $subDir . 'resources/',
      'href="' . Director::absoluteBaseURL() . $subDir . 'resources/'
  ];

  $replace = [
      'src="' . $cdn . '/' . $subDir . 'resources/',
      'src="' . $cdn . '/' . $subDir . 'resources/',
      'href="' . $cdn . '/' . $subDir . 'resources/',
      'href="' . $cdn . '/' . $subDir . 'resources/'
  ];

  $body = str_replace($search, $replace, $body);

  if ($this->config()->get('add_debug_headers') == true) {
   $response->addHeader('X-CDN-Resources', 'Enabled');
  }
 }

 private function addPrefetch(&$body, &$response)
 {
  $prefetchTag = $this->getPrefetchTag();
  $body = str_replace('<head>', "<head>" . $prefetchTag, $body);
  if ($this->config()->get('add_debug_headers') == true) {
   $response->addHeader('X-CDN-Prefetch', 'Enabled');
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
  * (shamelessly based on https://github.com/silverstripe/silverstripe-subsites)
  *
  * @param  HTTPRequest $request
  * @return bool
  */
 private function getIsAdmin(HTTPRequest $request)
 {
  $adminPath = AdminRootController::admin_url();
  $currentPath = rtrim($request->getURL(), '/') . '/';
   if (substr($currentPath, 0, strlen($adminPath)) === $adminPath) {
    return true;
   }
  return false;
 }
}
