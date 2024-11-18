# Silverstripe CDN Rewrite

Provides a simple method of rewriting the URLs of assets and resources to allow the use of a subdomain or external CDN service


[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/DorsetDigital/silverstripe-cdnrewrite/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/DorsetDigital/silverstripe-cdnrewrite/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/DorsetDigital/silverstripe-cdnrewrite/badges/build.png?b=master)](https://scrutinizer-ci.com/g/DorsetDigital/silverstripe-cdnrewrite/build-status/master)
[![License](https://img.shields.io/badge/License-BSD%203--Clause-blue.svg)](LICENSE.md)
[![Version](http://img.shields.io/packagist/v/dorsetdigital/silverstripe-cdnrewrite.svg?style=flat)](https://packagist.org/packages/dorsetdigital/silverstripe-cdnrewrite)

# V2
Please note, the V2 branch introduces a new configuration syntax.  If you are using V1 of the module, you will need to change this before it will work correctly.
See the configuration notes below for an example of how to set this module up.

# Requirements
*Silverstripe 4.x

# Installation
* Install the code with `composer require dorsetdigital/silverstripe-cdnrewrite "^2"`
* Run a `dev/build?flush` to update your project

# Usage

The module won't make any changes to your site unless you do a bit of configuration.  There are a few options you can set, done in a yml file:


```yaml
---
Name: cdnconfig
---

DorsetDigital\CDNRewrite\CDNMiddleware:
  cdn_rewrite: true
  cdn_domain: 'https://cdn.example.com'
  add_debug_headers: true
  enable_in_dev: true
  enable_in_preview: false
  subdirectory: ''
  add_prefetch: true
  rewrites:
    - '_resources'
    - 'client'
```

The options are hopefully fairly self explanatory:

* `cdn_rewrite` - globally enables and disables the module (default false - disabled)
* `cdn_domain` - the full domain name of the CDN (required to enable module)
* `add_debug_headers` - if enabled, adds extra HTML headers to show the various operations being applied (default false)
* `enable_in_dev` - enable the CDN in dev mode (default false)
* `enable_in_preview` - enable the rewrites in the CMS preview panel (default false)
* `subdirectory` - set this if your site is in a subdirectory (eg. for http://www.example.com/silverstripe - set this to 'silverstripe')
* `add_prefetch` - set this to true if you want the module to automatically add a `<link rel="dns-prefetch">` tag to your html head to improve performance
* `rewrites` - this is a list of the prefixes you wish to rewrite.  By default, the CMS exposes content in a _resources directory in the public structure, so you'll probably want that as a minimum.  You can add as many additional entires here as required.

# Notes

* The module is disabled in the CMS / admin system unless explicitly enabled with the setting above, so rewrites do not currently happen here
* When enabled, the module will always add an HTTP header of `X-CDN: Enabled` to show that it's working, even if none of the other rewrite operations are carried out.  If this is not present and you think it should be, ensure that you have set `cdn_rewrite` to true, that you have specified the `cdn_domain` in your config file and that you have `enable_in_dev` set to true if you are testing in dev mode.

# Need help?

- [Open an issue](https://github.com/DorsetDigital/silverstripe-cdnrewrite/issues) on the repository
- Ask in the [community Slack channel](https://silverstripe-users.slack.com/)
- See you at the next [StripeCon](https://stripecon.eu)!

# Credits
* Very much inspired by [Werner Krauss' silverstripe-cdnrewrite](https://github.com/wernerkrauss/silverstripe-cdnrewrite)
* As always, thanks to the core team for all their hard work.  
