=== Assistify for WooCommerce ===
Contributors: shameemreza
Tags: woocommerce, ai, chatbot, assistant, customer support
Requires at least: 6.4
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A unified, native WooCommerce AI Assistant that provides dual-interface chat for both store owners and customers.

== Description ==

Assistify for WooCommerce is a powerful AI assistant that deeply integrates with your WooCommerce store to provide intelligent support for both store owners and customers.

= For Store Owners =

* **Natural Language Queries** - Ask questions about your store in plain English.
* **Order Management** - Look up orders, process refunds, update statuses.
* **Product Management** - Update products, check inventory, generate content.
* **Analytics Insights** - Get sales reports and performance analysis.
* **Store Health Monitoring** - Diagnose issues and get recommendations.
* **Content Generation** - Create product descriptions, titles, and more.
* **AI Image Generation** - Generate product images from text descriptions.

= For Customers =

* **24/7 Support** - Instant answers to common questions.
* **Order Tracking** - Check order status without waiting.
* **Product Questions** - Get detailed product information.
* **Self-Service Actions** - Manage subscriptions, request refunds.

= Key Features =

* **Multi-Provider Support** - Works with OpenAI, Anthropic, Google, xAI, and DeepSeek.
* **BYOK (Bring Your Own Key)** - Use your own API keys for full control.
* **Privacy First** - Your data stays in WordPress.
* **WooCommerce Native** - Deep integration with WooCommerce extensions.
* **HPOS Compatible** - Works with High-Performance Order Storage.
* **Accessible** - WCAG 2.1 AA compliant.

= Supported AI Providers =

* **OpenAI** - GPT-5, GPT-5-mini, gpt-image-1 for images.
* **Anthropic** - Claude 4.5 Sonnet, Claude 4.5 Haiku.
* **Google** - Gemini 3 Pro, Gemini 2.5 Flash, Imagen 4.0.
* **xAI** - Grok-4 Fast.
* **DeepSeek** - V3.2 Chat.

= WooCommerce Extension Support =

* WooCommerce Subscriptions.
* WooCommerce Bookings.
* WooCommerce Memberships.
* Product Bundles.
* And more...

== Installation ==

= Minimum Requirements =

* WordPress 6.4 or greater.
* WooCommerce 8.0 or greater.
* PHP version 8.0 or greater.
* MySQL version 5.7 or greater OR MariaDB version 10.3 or greater.

= Automatic Installation =

1. Log in to your WordPress admin panel.
2. Navigate to Plugins > Add New.
3. Search for "Assistify for WooCommerce".
4. Click "Install Now" and then "Activate".
5. Go to WooCommerce > Settings > Assistify to configure.

= Manual Installation =

1. Download the plugin zip file.
2. Log in to your WordPress admin panel.
3. Navigate to Plugins > Add New > Upload Plugin.
4. Choose the downloaded file and click "Install Now".
5. Activate the plugin.
6. Go to WooCommerce > Settings > Assistify to configure.

= Configuration =

1. Navigate to WooCommerce > Settings > Assistify.
2. Enter your AI provider API key.
3. Configure the admin and customer chat options.
4. Save your settings.

== Frequently Asked Questions ==

= Do I need an API key? =

Yes, you need an API key from one of our supported AI providers (OpenAI, Anthropic, Google, xAI, or DeepSeek). The plugin uses a BYOK (Bring Your Own Key) model, giving you full control over costs and usage.

= How much does it cost to use? =

The plugin itself is free. You only pay for the API usage directly to your chosen AI provider. Typical costs range from $0.001 to $0.02 per conversation depending on the model used.

= Is my data safe? =

Yes. Your store data stays in WordPress. Only the conversation context needed for AI responses is sent to the AI provider, and this is encrypted in transit. No sensitive data like credit card numbers is ever sent.

= Does it work with WooCommerce Subscriptions? =

Yes! Assistify has deep integration with WooCommerce Subscriptions, allowing customers to manage their subscriptions through chat and giving store owners subscription analytics.

= Is it GDPR compliant? =

Yes. The plugin includes consent management, data export, and data erasure capabilities to help you comply with GDPR requirements.

= Can I customize the chat widget appearance? =

Yes, you can customize colors, position, welcome messages, and quick questions through the settings page.

= Does it support multiple languages? =

Yes, the AI can respond in the language the customer uses. The plugin interface is translation-ready.

== Screenshots ==

1. Admin chat interface - Query your store data naturally.
2. Customer chat widget - Self-service support for customers.
3. Settings page - Easy configuration with multiple AI providers.
4. Store health dashboard - Monitor and diagnose store issues.
5. Content generation - Create product descriptions with AI.
6. AI image generation - Generate product images from text.

== Changelog ==

= 1.0.0 - 2025-12-10 =
* Initial release.
* Admin chat interface with natural language queries.
* Customer chat widget with self-service capabilities.
* Support for OpenAI, Anthropic, Google, xAI, and DeepSeek.
* Content generation for products.
* AI image generation.
* Store health diagnostics.
* WooCommerce Subscriptions integration.
* WooCommerce Bookings integration.
* HPOS compatibility.
* GDPR compliance features.