=== Link Support by thisismyurl.com ===
Contributors: thisismyurl
Donate link: https://thisismyurl.com/link-support-thisismyurl/#register
Author: thisismyurl
Author URI: https://thisismyurl.com/
Tags: links, external links, nofollow, seo, target blank, link support
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.26010222
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
GitHub Plugin URI: https://github.com/thisismyurl/thisismyurl-link-support
Primary Branch: main

Globally manage external link behavior, including nofollow and target attributes, utilizing the TIMU Shared Core Library. 

== Description ==

**Link Support** is a lightweight, professional-grade utility designed to manage how your website interacts with external destinations. It provides a global way to manage external links by automatically adding attributes like `target="_blank"`, `rel="nofollow"`, or security-focused tags like `noopener noreferrer`.

Managing external links is a fundamental skill for any webmaster or developer concerned with "Link Equity" and site authority.  This plugin provides the technical tools required to master your outgoing traffic without an "Integration Tax," empowering you to direct your site's authority exactly where it belongs?into your own content.

This version is built upon the **TIMU Shared Core Library**, ensuring:
* **Clean Code & Security:** Centralized sanitization and updater logic.
* **Non-destructive Processing:** Links are modified "on the fly" using WordPress filters, ensuring your database remains clean and your content is never permanently altered.
* **Standardized UI:** A professional administrative interface that is consistent across all thisismyurl.com tools.

== Installation ==

1. Upload the `thisismyurl-link-support` folder to the `/wp-content/plugins/` directory. 
2. Activate the plugin through the 'Plugins' menu in WordPress. 
3. Navigate to **Tools > Link Support** to configure your settings. 

== Frequently Asked Questions ==

= Does this change my posts in the database? =
No. The plugin uses the `the_content` filter to modify the output of your posts as they are loaded in the browser. If you deactivate the plugin, all links return to their original state. 

= Is this compatible with other thisismyurl.com plugins? 
Yes. Since this plugin utilizes the Shared Core Library, it shares resources and a consistent UI with our WebP, SVG, and HEIC support tools.

== Changelog ==

= 1.26010222 =
* Core hierarchy updated via link-support-thisismyurl\link-support-thisismyurl.php

= 1.26010222 =
* Core hierarchy updated via link-support-thisismyurl\README.md

= 1.26010222 =
* Core hierarchy updated via link-support-thisismyurl\core\icons

= 1.26010222 =
* Core hierarchy updated via link-support-thisismyurl\readme.txt

= 1.260101 =
