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

= For Store Owners (67+ AI Tools) =

* **Natural Language Queries** - Ask questions about your store in plain English.
* **Order Management** - Look up orders, process refunds, update statuses, add notes.
* **Product Management** - Create, update products, check inventory, manage stock.
* **Customer Insights** - View customer details, order history, repeat buyers.
* **Analytics & Reporting** - Sales trends, revenue metrics, AOV, retention rates.
* **Store Health Monitoring** - Real-time monitoring with email alerts for issues.
* **Content Generation** - AI-powered product descriptions, titles, tags.
* **AI Image Generation** - Generate product images from text descriptions.
* **Coupon Management** - Create, update, delete coupons with full settings.
* **Shipping & Tax** - View zones, methods, rates, and settings.

= For Customers =

* **24/7 Support** - Instant answers to common questions.
* **Order Tracking** - Check order status, shipping info, payment details.
* **Product Questions** - Get detailed product information and recommendations.
* **Self-Service** - View downloads, addresses, cart contents.
* **Policy Information** - Shipping, returns, and store policies.

= Key Features =

* **Multi-Provider Support** - Works with OpenAI, Anthropic, Google, xAI, and DeepSeek.
* **67+ Admin Tools** - Comprehensive store management via AI chat.
* **Agentic AI** - Tool-calling architecture for accurate, real-time data.
* **Store Health Dashboard** - Dedicated monitoring page with health score.
* **Analytics Tracking** - Product views, cart behavior, traffic sources, conversions.
* **BYOK (Bring Your Own Key)** - Use your own API keys for full control.
* **Privacy First** - Your data stays in WordPress, GDPR compliant.
* **WooCommerce Native** - Deep integration following WooCommerce standards.
* **HPOS Compatible** - Works with High-Performance Order Storage.
* **Accessible** - WCAG 2.1 AA compliant interface.

= Store Health Monitoring =

Assistify includes a comprehensive health monitoring system:

* **Real-time Alerts** - Email notifications for critical issues.
* **Error Monitoring** - PHP errors, WooCommerce log analysis.
* **Update Tracking** - Plugin, theme, and WordPress core updates.
* **Order Monitoring** - Failed payments, processing issues.
* **Security Checks** - Debug mode, .git exposure, SSL status.
* **Inventory Alerts** - Low stock and out-of-stock products.
* **AI Recommendations** - Actionable insights based on store data.
* **One-Click Fixes** - Clear transients, sessions, optimize autoload.

= Analytics & Insights =

* **Revenue Trends** - Daily, weekly, monthly breakdowns.
* **Product Conversion** - Views to purchases tracking.
* **Traffic Sources** - UTM tracking and referrer attribution.
* **Customer Behavior** - Add-to-cart events, checkout abandonment.
* **Search Analytics** - What customers search for, no-results queries.
* **Regional Analysis** - Orders grouped by customer location.

= Supported AI Providers =

* **OpenAI** - GPT-4o, GPT-4o-mini, GPT-4.1, o1, o3-mini, gpt-image-1 for images.
* **Anthropic** - Claude Sonnet 4, Claude Opus 4, Claude 3.7/3.5 Sonnet, Claude 3.5 Haiku.
* **Google** - Gemini 2.5 Pro, Gemini 2.5/2.0 Flash, Gemini 1.5 Pro/Flash, Imagen 4.0.
* **xAI** - Grok 3, Grok 3 Mini, Grok 2, Grok 2 Vision, Grok 2 Image.
* **DeepSeek** - DeepSeek-V3, DeepSeek-R1, DeepSeek Coder.

= Image Generation =

* **Text-to-Image** - Generate images from descriptions.
* **Product Images** - Create product photos automatically.
* **Gallery Generation** - Multiple images for product galleries.
* **Background Removal** - Transparent PNG via Remove.bg integration.
* **Image Editing** - Edit existing images with AI prompts.
* **Variations** - Create style variants of existing images.

= WooCommerce Extension Support =

* WooCommerce Subscriptions
* WooCommerce Bookings
* WooCommerce Memberships
* WC Shipment Tracking
* Product Bundles
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
3. Select your preferred AI model for text and image generation.
4. Configure the admin and customer chat options.
5. Save your settings.

== Frequently Asked Questions ==

= Do I need an API key? =

Yes, you need an API key from one of our supported AI providers (OpenAI, Anthropic, Google, xAI, or DeepSeek). The plugin uses a BYOK (Bring Your Own Key) model, giving you full control over costs and usage.

= How much does it cost to use? =

The plugin itself is free. You only pay for the API usage directly to your chosen AI provider. Typical costs range from $0.001 to $0.02 per conversation depending on the model used.

= Is my data safe? =

Yes. Your store data stays in WordPress. Only the conversation context needed for AI responses is sent to the AI provider, and this is encrypted in transit. Sensitive data like credit card numbers is automatically filtered and never sent.

= Does it work with WooCommerce Subscriptions? =

Yes! Assistify has deep integration with WooCommerce Subscriptions, allowing customers to manage their subscriptions through chat and giving store owners subscription analytics.

= Is it GDPR compliant? =

Yes. The plugin includes consent management, data export, and data erasure capabilities. Customer consent is required before chat, and all data handling follows GDPR requirements.

= What is HPOS and is it supported? =

HPOS (High-Performance Order Storage) is WooCommerce's modern order storage system. Assistify fully supports HPOS and uses WooCommerce's native APIs for compatibility.

= Can I customize the chat widget appearance? =

Yes, you can customize colors, position, welcome messages, agent name, and quick questions through the settings page.

= Does it support multiple languages? =

Yes, the AI can respond in the language the customer uses. The plugin interface is translation-ready with full i18n support.

= What is the Store Health feature? =

Store Health is a monitoring dashboard that tracks your store's health including errors, updates, security, and performance. It provides a health score and sends email alerts for critical issues.

= Can the AI take actions on my store? =

Yes, the admin AI assistant can perform actions like updating orders, creating coupons, and managing products. All actions use WooCommerce's native APIs and follow security best practices.

= How do I report bugs or request features? =

Please report bugs and request features on our [GitHub Issues](https://github.com/shameemreza/assistify-for-woocommerce/issues) repo.

== Screenshots ==

1. Admin chat interface - Query your store data naturally.
2. Customer chat widget - Self-service support for customers.
3. Settings page - Easy configuration with multiple AI providers.
4. Store Health dashboard - Monitor and diagnose store issues.
5. Content generation - Create product descriptions with AI.
6. AI image generation - Generate product images from text.

== Changelog ==

= 1.0.0 - 2025-12-12 =
* Initial release.
* Admin chat interface with 67+ AI tools.
* Agentic AI with tool-calling for accurate data.
* Customer chat widget with self-service capabilities.
* Support for OpenAI, Anthropic, Google, xAI, and DeepSeek.
* Content generation for products (titles, descriptions, tags).
* AI image generation (OpenAI, Google Imagen, xAI).
* Background removal via Remove.bg integration.
* Store Health monitoring with email alerts.
* Store Health dashboard page.
* Analytics tracking (views, cart, traffic, behavior).
* Order management (refunds, status, notes).
* Coupon management (create, update, delete).
* Customer insights and analytics.
* WooCommerce Subscriptions integration.
* WooCommerce Bookings integration.
* HPOS compatibility.
* GDPR compliance features.
* Privacy consent management.
* Multi-language support.