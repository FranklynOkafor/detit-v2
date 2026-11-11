=== DetIt AI Content Generator for WooCommerce ===
Contributors: emeka002
Tags: woocommerce, ai, product descriptions, product audit, content
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate and review WooCommerce product content with AI. Audit product completeness without changing products or using AI credits.

== Description ==

DetIt Free helps WooCommerce store owners improve product information while staying in control of every change.

* Generate selected product titles, descriptions, short descriptions, suggested tags, and meta-description text.
* Review the generated text before applying any change.
* Apply only selected fields and undo the latest DetIt application when safe.
* Save store-wide writing preferences in a reusable Brand Profile.
* Audit product completeness to identify missing information and assign content-health scores.
* Search, sort, and filter product audits, rescan changed products, and scan chosen products or batches.
* Run content-health audits locally: audits do not call an AI provider or alter WooCommerce product content.

DetIt does not guarantee search rankings, sales, or the accuracy of AI-generated descriptions. Review every suggestion before applying it. The content-health score reflects completeness checks, not SEO performance.

**Before using AI generation**

AI generation requires a configured, compatible WordPress AI Client and an AI provider with text-generation support. WordPress 7.0 and newer include the WordPress AI Client API. On older WordPress installations, a compatible AI Client integration must be installed separately; generation is unavailable if `wp_ai_client_prompt()` is missing. WooCommerce is also required.

A configured provider may require an API key, a provider account, and paid usage. DetIt does not include free AI credits. If no compatible provider is available, catalog auditing remains usable.

Example provider integration: [AI Provider for Google](https://wordpress.org/plugins/ai-provider-for-google/). You may choose other compatible providers configured through WordPress.

**External AI services and privacy**

DetIt makes an external AI request only when an authorized merchant explicitly starts product-content generation. The WordPress AI Client sends the request to the merchant's configured AI provider, not to a DetIt-owned server.

Generation prompts include the store's saved Brand Profile (such as store name, market, target audience, language, tone, and writing preferences); product facts (including product ID, title, SKU, descriptions, product type, categories, tags, attributes, weight, and dimensions); and any extra generation instructions entered by the user. Generated text is returned through the AI Client to the WordPress site. Do not include personal or confidential information in these fields unless you are comfortable sharing it with your configured AI provider.

The receiving provider, its terms, retention practices, privacy rules, model availability, and fees depend on the provider the store owner configures. Example for Google's Gemini API: [Gemini API terms](https://ai.google.dev/gemini-api/terms) and [Google privacy policy](https://policies.google.com/privacy). Check the terms of any other configured provider before generating content.

DetIt stores audit results and generation/apply history in the site's own WordPress database. Manual content-health audits do not send product data to an AI provider. The image-awareness checkbox in V2.0 is disabled; image-aware generation is not included in this release.

== Installation ==

1. Install and activate WooCommerce.
2. Install DetIt through Plugins > Add New > Upload Plugin, then activate it.
3. Go to Settings > DetIt Brand Profile and enter your store's writing preferences.
4. To use generation, configure a compatible WordPress AI Client and AI provider in your WordPress environment.
5. Edit an existing WooCommerce product and select Generate with DetIt.
6. Choose which fields to generate, review the preview, and apply only the fields you want.
7. Go to WooCommerce > DetIt Content Health to audit products and review missing content.

For new installations on WordPress 7.0 or newer, the WordPress AI Client API is part of WordPress core. Older versions require a separately installed compatible AI Client implementation.

**Upgrading from DetIt 1.0**

DetIt V2.0 changes AI generation from the plugin's built-in Gemini API-key setting to the WordPress AI Client. Your previous DetIt 1.0 Gemini API key does not automatically configure the new connection. After updating, set up a compatible AI provider through WordPress and verify that generation is available. Back up your WordPress site and database before updating. DetIt V2.0 requires PHP 8.1 or newer.

== Frequently Asked Questions ==

= Does DetIt charge me to audit products? =

No. Manual product content-health audits are local and do not use AI credits.

= Is AI generation free? =

The DetIt Free plugin is free. Your configured AI provider may charge for text generation and any repair request. You are responsible for the provider account and its costs.

= Can DetIt change my product without approval? =

The Generate button only prepares a preview. DetIt applies selected fields after you explicitly approve them. Auditing does not change WooCommerce product information.

= What does the content-health score measure? =

It identifies missing product information and completeness issues. It is not a search-engine ranking or conversion prediction.

= Are product images used by AI? =

No. Image-aware generation is disabled in DetIt Free V2.0.

= What happens if I uninstall DetIt? =

Product content already applied to WooCommerce remains in the product. DetIt's own settings, audit indexes, and generation history may be removed by uninstalling the plugin; make a backup first.

= Where do I report problems? =

Use the [DetIt GitHub issue tracker](https://github.com/FranklynOkafor/detit-v2/issues).

== Changelog ==

= 2.0.0 =
* Initial DetIt Free V2.0 release.
* Selective AI product generation with preview, apply, and safe undo.
* Store-wide Brand Profile with per-generation language and tone overrides.
* Product completeness scoring, issue detection, and source-change detection.
* Searchable Content Health dashboard and manual individual, selected, or batch audits.
* No image-aware generation, scheduled generation, or premium functions in this release.

= 1.0 =
* Original public release with built-in Google Gemini API-key configuration.
