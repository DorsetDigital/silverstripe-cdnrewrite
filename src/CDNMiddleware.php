<?php

namespace DorsetDigital\CDNRewrite;

use SilverStripe\Admin\AdminRootController;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\View\HTML;

class CDNMiddleware implements HTTPMiddleware
{

    use Injectable;
    use Configurable;
    use Extensible;

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
     * Enable in CMS preview
     * @var bool
     */
    private static $enable_in_preview = false;

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
        $response->addHeader('X-CDN-Module', 'Installed');

        if (($response !== null) && ($this->canRun($request, $response) === true)) {
            $response->addHeader('X-CDN-Rewrites', 'Enabled');

            $body = $response->getBody() ?: '';
            $this->rewriteTags($body, $response);
            $this->addPrefetch($body, $response);
            $response->setBody($body);

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
    private function canRun(HTTPRequest $request, HTTPResponse $response)
    {
        $confEnabled = $this->config()->get('cdn_rewrite');
        $devEnabled = ((!Director::isDev()) || ($this->config()->get('enable_in_dev')));
        $notAdmin = !$this->getIsAdmin($request);
        $previewOK = true;
        if ($request->getVar('CMSPreview') == 1) {
            if (!$this->config()->get('enable_in_preview')) {
                $previewOK = false;
            }
        }

        return ($confEnabled && $devEnabled && $previewOK && $notAdmin);
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

            $patterns = [
                '/src=(["\'])?' . preg_quote('/' . $subDir . $cleanPrefix, '/') . '/i', // Match src attribute with optional quotes
                '/href=(["\'])?' . preg_quote('/' . $subDir . $cleanPrefix, '/') . '/i', // Match href attribute with optional quotes
                '/background-image:\s*url\((["\'])?\/?' . preg_quote($subDir . $cleanPrefix, '/') . '/i', // Match background-image with optional quotes and leading slash
                '/' . preg_quote(Director::absoluteBaseURL() . $cleanPrefix, '/') . '/i' // Match absolute URL
            ];

            $replacements = [
                'src=$1' . $cdn . '/' . $subDir . $cleanPrefix, // Use backreference to preserve matching quote style
                'href=$1' . $cdn . '/' . $subDir . $cleanPrefix, // Use backreference to preserve matching quote style
                'background-image: url($1' . $cdn . '/' . $subDir . $cleanPrefix, // Use backreference for quotes
                $cdn . '/' . $subDir . $cleanPrefix // Replace absolute URL
            ];

            $this->extend('updateRewriteSearch', $patterns);
            $this->extend('updateRewriteReplacements', $replacements);

            $body = preg_replace($patterns, $replacements, $body);
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
