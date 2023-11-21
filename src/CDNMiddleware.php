<?php

namespace DorsetDigital\CDNRewrite;

use SilverStripe\Admin\AdminRootController;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
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
     * @config
     *
     * Array of prefixes we wish to rewrite
     * @var array
     */
    private static $rewrites = [];

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
            $response->addHeader('X-CDN-Rewrites', 'Enabled');

            if ($this->getIsAdmin($request) === false) {
                $body = $response->getBody();
                $this->rewriteTags($body, $response);
                $this->addPrefetch($body, $response);
                $response->setBody($body);
            }

            if ($this->config()->get('add_debug_headers') == true) {
                $response->addHeader('X-CDN-Domain', $this->config()->get('cdn_domain'));
                $response->addHeader('X-CDN-Dir', $this->getSubdirectory());
            }

            if ($this->config()->get('cdn_rewrite') === true) {
                $response->addHeader('X-CDN-Module', 'Active');
            }
        }

        return $response;
    }

    /**
     * Check if we're OK to execute
     * @return bool
     */
    private function canRun()
    {
        $confEnabled = $this->config()->get('cdn_rewrite');
        $devEnabled = ((!Director::isDev()) || ($this->config()->get('enable_in_dev')));
        return ($confEnabled && $devEnabled);
    }


    /**
     * Rewrite all the tags we need
     * @param $body
     * @param $response
     */
    private function rewriteTags(&$body, &$response)
    {
        $cdn = $this->config()->get('cdn_domain');
        $subDir = $this->getSubdirectory();
        $prefixes = $this->config()->get('rewrites');

        foreach ($prefixes as $prefix) {
            $cleanPrefix = trim($prefix, '/');

            $search = [
                'src="' . $subDir . $cleanPrefix . '/',
                'src="/' . $subDir . $cleanPrefix . '/',
                'src=\"/' . $subDir . $cleanPrefix . '/',
                'href="/' . $subDir . $cleanPrefix . '/',
                'background-image: url(/' . $subDir . $cleanPrefix . '/',
                Director::absoluteBaseURL() . $cleanPrefix . '/'
            ];

            $replace = [
                'src="' . $cdn . '/' . $subDir . $cleanPrefix . '/',
                'src="' . $cdn . '/' . $subDir . $cleanPrefix . '/',
                'src=\"' . $cdn . '/' . $subDir . $cleanPrefix . '/',
                'href="' . $cdn . '/' . $subDir . $cleanPrefix . '/',
                'background-image: url(' . $cdn . '/' . $subDir . $cleanPrefix . '/',
                $cdn . '/' . $subDir . $cleanPrefix . '/'
            ];
            
            if ($body)
            {
                $body = str_replace($search, $replace, $body);
            }
        }
    }


    private function addPrefetch(&$body, &$response)
    {
        if ($this->config()->get('add_prefetch') === true) {
            $prefetchTag = $this->getPrefetchTag();
            $body = str_replace('<head>', "<head>" . $prefetchTag, $body);
            if ($this->config()->get('add_debug_headers') == true) {
                $response->addHeader('X-CDN-Prefetch', 'Enabled');
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
     * (shamelessly based on https://github.com/silverstripe/silverstripe-subsites)
     *
     * @param HTTPRequest $request
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
