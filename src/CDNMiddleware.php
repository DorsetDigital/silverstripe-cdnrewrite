<?php
namespace DorsetDigital\CDNRewrite;

use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Control\HTTPRequest;

class CDNMiddleware implements HTTPMiddleware
{

 public function process(HTTPRequest $request, callable $delegate)
 {

  $response = $delegate($request);
  $response->addHeader('X-CDN', 'Enabled');
  
  return $response;
 }
}
