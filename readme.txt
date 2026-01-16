=== Assistify for WooCommerce ===
Contributors: shameemreza
Donate link: https://ko-fi.com/shameemreza
Tags: woocommerce, ai, chatbot, assistant, customer support
Requires at least: 6.4
Tested up to: 6.9
Stable tag: 1.1.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An AI assistant that actually understands WooCommerce. Chat with your store data, help customers 24/7, and stop wasting time on repetitive tasks.

== Description ==

Running a WooCommerce store means drowning in support tickets, endless admin clicks, and questions you've answered a thousand times. Assistify gives you an AI assistant that speaks WooCommerce natively.

**The problem is simple:** You spend hours looking up orders, explaining shipping policies, and handling the same customer questions over and over. Meanwhile, tools like Shopify have Sidekick, and you're stuck with nothing.

**Here's what Assistify does:** It lets you manage your store through conversation. Ask "what sold today?" and get real numbers. Tell it to "create a 20% off coupon for Black Friday" and it's done. Your customers can check their orders, pause subscriptions, or reschedule bookings, without waiting for you.

= What Store Owners Actually Get? =

Forget fluffy promises. Here's what you can do:

**Ask questions in plain English:**

* Show me orders from last week that are still processing.
* Which products are running low on stock?
* What's my revenue this month compared to last month?
* Find all customers who ordered more than $500.

**Take action through chat:**

* Create coupons with specific rules and expiry dates.
* Process refunds without clicking through five screens.
* Update product prices and stock levels.
* Add notes to orders.

**Generate content when you're stuck:**

* Product titles that don't sound like "Product #4523".
* Descriptions that actually describe the product.
* Review responses that don't take 20 minutes to write.
* Email templates for common situations.

**Monitor your store's health:**

* Get alerts when something breaks (not three days later).
* See error logs without SSH access.
* Track failed payments before customers complain.
* Know when plugins need updating.

= What Your Customers Get? =

A chat widget that actually helps instead of saying "please contact support":

* **Order tracking**: "Where's my order?" gets a real answer, not a tracking link.
* **Subscription management**: Pause, skip, or cancel without hunting through account pages.
* **Booking changes**: Reschedule appointments through a quick chat.
* **Product questions**: Get specifications, availability, shipping info instantly.

No more "our team will get back to you within 24-48 hours."

= Works With Your Extensions =

Assistify isn't limited to core WooCommerce. It understands:

**[WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/):**

* Customers can pause, resume, or cancel their subscriptions.
* You get MRR, churn rate, and retention analytics.
* See at-risk subscriptions before they cancel.
* Track failed renewals and retry payments.

**[WooCommerce Bookings](https://woocommerce.com/products/woocommerce-bookings/):**

* View today's schedule, upcoming appointments, available slots.
* Customers can check availability and cancel bookings.
* Booking revenue and cancellation rate tracking.
* Resource utilization reporting.

**[WooCommerce Memberships](https://woocommerce.com/products/woocommerce-memberships/):**

* Manage member access and plan assignments.
* Track expiring memberships for retention outreach.
* Customers can view their benefits and membership status.
* Member analytics by plan.

**[Hotel Booking for WooCommerce](https://wordpress.org/plugins/hotel-booking-for-woocommerce/):**

* Search available rooms by dates, guests, and capacity.
* Check room availability and get instant pricing.
* View today's check-ins and check-outs at a glance.
* Hotel analytics: occupancy rates, revenue, popular rooms.
* Customers can browse rooms, check availability, and view their reservations.
* AI-powered alternative suggestions when dates are unavailable.

= Pick Your AI Provider =

You're not locked into one service. Bring your own API key from:

* **OpenAI**: GPT-4o, GPT-4o-mini, GPT-4.1, o1, o3-mini.
* **Anthropic**: Claude Sonnet 4, Claude Opus 4, Claude 3.5 Sonnet/Haiku.
* **Google**: Gemini 2.5 Pro, Gemini 2.5/2.0 Flash.
* **xAI**: Grok 3, Grok 3 Mini, Grok 2.
* **DeepSeek**: DeepSeek-V3, DeepSeek-R1 (budget-friendly option).

Switch providers anytime. Your data stays in WordPress, only the conversation goes to the AI.

= Image Generation =

Need product images? Generate them from text descriptions using:

* OpenAI gpt-image-1
* Google Imagen 4.0
* xAI Grok 2 Image

Plus background removal via Remove.bg integration.

= Safety First =

**For store actions:**

* Destructive actions (refunds, cancellations) require confirmation.
* High-risk actions need you to type a confirmation code.
* Everything gets logged, see exactly what the AI did and when.

**For privacy:**

* Credit card numbers are automatically filtered (never sent to AI).
* Customer data stays in WordPress.
* GDPR consent built in.
* Export and delete conversation data on request.

= Technical Details =

* **HPOS compatible**: Works with WooCommerce's High-Performance Order Storage.
* **WCAG 2.1 AA**: Accessible interface with keyboard navigation.
* **Translation ready**: Full i18n support with POT file included.
* **Clean code**: PHPStan Level 2 compliant, WordPress coding standards.

== Installation ==

= Requirements =

* WordPress 6.4+
* WooCommerce 8.0+
* PHP 8.0+
* MySQL 5.7+ or MariaDB 10.3+

= Quick Start =

1. Install and activate the plugin.
2. Go to WooCommerce > Settings > Assistify.
3. Add your API key (OpenAI, Anthropic, Google, xAI, or DeepSeek).
4. Pick a model for text and image generation.
5. Enable the admin chat, customer chat, or both.

That's it. Start chatting with your store.

== Frequently Asked Questions ==

= Do I need an API key? =

Yes. Assistify uses a BYOK (Bring Your Own Key) model. You pay your AI provider directly — no middleman markup from us.

= How much will this cost me? =

The plugin is free. AI costs depend on usage and provider. Typical conversations cost $0.001 to $0.02 each. A small store might spend $5-20/month total.

= Is my store data sent to the AI? =

Only conversation context. The AI doesn't get your entire database. Sensitive data (card numbers, passwords) is filtered out automatically. Everything is encrypted in transit.

= Will it work with my WooCommerce extensions? =

Core WooCommerce works out of the box. We've built specific integrations for Subscriptions, Bookings, Memberships, and Hotel Booking for WooCommerce. More coming.

= Can the AI actually change things in my store? =

Yes, but with safeguards. The admin assistant can process refunds, create coupons, update products, etc. Destructive actions require confirmation. Everything is logged.

= What if the AI makes a mistake? =

All actions are logged with timestamps and details. You can see exactly what happened. For dangerous actions, you'll need to confirm before anything executes.

= Is it GDPR compliant? =

Yes. Consent management, data export, data deletion, it's all built in.

= Can customers access other customers' data? =

No. Customers can only see their own orders, subscriptions, and bookings. Permission checks happen on every request.

= How do I get help? =

[GitHub Issues](https://github.com/shameemreza/assistify-for-woocommerce/issues) for bugs and feature requests. And [forum](https://wordpress.org/support/plugin/assistify-for-woocommerce/) for support.

== Screenshots ==

1. Chat overview.
2. Admin chat: Ask questions about your store in plain English.
3. Customer widget: Self-service support that actually works.
4. Settings: Configure AI providers and chat options.
5. Store Health: Monitor errors, updates, and performance.
6. Store health Email notifications.
7. Store health widget.
8. Content generation: Write product descriptions without staring at a blank screen.
9. Image generation: Create product images from text descriptions.

== Changelog ==

= 1.1.0 - 2026-01-16 =
**New: Hotel Booking for WooCommerce Integration**

* Added 15 new AI abilities for hotel/accommodation management.
* Admin: List rooms, check availability, view reservations.
* Admin: Today's check-ins/check-outs dashboard.
* Admin: Upcoming reservations and hotel analytics.
* Admin: Occupancy rate, revenue tracking, popular rooms.
* Customer: Search available rooms by dates and guests.
* Customer: Check room availability and get pricing.
* Customer: View personal reservations.
* Customer: AI-powered alternative suggestions when dates unavailable.
* Natural language queries: "Do you have a room for 2 adults next weekend?"

= 1.0.0 - 2025-12-13 =
**Initial release**

Admin Chat:

* 103+ AI tools for store management.
* Order lookup, refunds, status updates.
* Product management and inventory.
* Customer search and insights.
* Revenue and sales analytics.
* Coupon creation and management.

Customer Chat:

* Order tracking and status.
* Self-service account management.
* Product information and recommendations.

AI Providers:

* OpenAI (GPT-4o, GPT-4o-mini, GPT-4.1, o1, o3-mini).
* Anthropic (Claude Sonnet 4, Claude Opus 4, Claude 3.5).
* Google (Gemini 2.5 Pro, Gemini Flash).
* xAI (Grok 3, Grok 2).
* DeepSeek (V3, R1).

Image Generation:

* OpenAI gpt-image-1.
* Google Imagen 4.0
* xAI Grok 2 Image.
* Remove.bg background removal.

Extension Integrations:

* WooCommerce Subscriptions: 16 abilities (admin + customer).
* WooCommerce Bookings: 14 abilities (admin + customer).
* WooCommerce Memberships: 13 abilities (admin + customer).

Content Generation:

* Product titles, descriptions, tags.
* Review responses.
* Email templates.
* Category descriptions.

Store Health:

* Health score dashboard.
* Email alerts for critical issues.
* Error and update monitoring.
* One-click fixes for common problems.

Safety & Compliance:

* Audit logging for all AI actions.
* Confirmation modals for destructive actions.
* GDPR compliant with consent management.
* HPOS compatible.

== External Services ==

This plugin connects to external AI services to provide its functionality. All connections require user-provided API keys and only occur when you actively use AI features. **No data is sent without your explicit action.**

= OpenAI =

When configured with OpenAI as your AI provider, this plugin sends requests to:

**https://api.openai.com/v1**

**What data is sent:**

* Your chat messages and prompts.
* Product/order context needed for AI responses (titles, descriptions, prices).
* Image generation prompts.

**When data is sent:**

* When you send a message in admin or customer chat.
* When you generate product content (titles, descriptions).
* When you generate images.

**Service links:**

* [OpenAI Terms of Use](https://openai.com/policies/terms-of-use/).
* [OpenAI Privacy Policy](https://openai.com/policies/privacy-policy/).

= Anthropic (Claude) =

When configured with Anthropic as your AI provider, this plugin sends requests to:
**https://api.anthropic.com/v1**

**What data is sent:**

* Your chat messages and prompts.
* Product/order context needed for AI responses.

**When data is sent:**

* When you send a message in admin or customer chat.
* When you generate product content.

**Service links:**

* [Anthropic Terms of Service](https://www.anthropic.com/legal/consumer-terms).
* [Anthropic Privacy Policy](https://www.anthropic.com/legal/privacy).

= Google (Gemini) =

When configured with Google as your AI provider, this plugin sends requests to:
**https://generativelanguage.googleapis.com/v1beta**

**What data is sent:**

* Your chat messages and prompts.
* Product/order context needed for AI responses.
* Image generation prompts (when using Imagen models).

**When data is sent:**

* When you send a message in admin or customer chat.
* When you generate product content.
* When you generate images (Imagen models).

**Service links:**

* [Google Cloud Terms of Service](https://cloud.google.com/terms).
* [Google Privacy Policy](https://policies.google.com/privacy).

= xAI (Grok) =

When configured with xAI as your AI provider, this plugin sends requests to:
**https://api.x.ai/v1**

**What data is sent:**

* Your chat messages and prompts.
* Product/order context needed for AI responses.
* Image generation prompts (when using Grok image models).

**When data is sent:**

* When you send a message in admin or customer chat.
* When you generate product content.
* When you generate images.

**Service links:**

* [xAI Terms of Service](https://x.ai/legal/terms-of-service/).
* [xAI Privacy Policy](https://x.ai/legal/privacy-policy/).

= DeepSeek =

When configured with DeepSeek as your AI provider, this plugin sends requests to:
**https://api.deepseek.com/v1**

**What data is sent:**

* Your chat messages and prompts.
* Product/order context needed for AI responses.

**When data is sent:**

* When you send a message in admin or customer chat.
* When you generate product content.

**Service links:**

* [DeepSeek Terms of Service](https://www.deepseek.com/terms).
* [DeepSeek Privacy Policy](https://www.deepseek.com/privacy).

= Remove.bg (Background Removal) =

When you use the background removal feature, this plugin sends requests to:
**https://api.remove.bg/v1.0**

**What data is sent:**

* Image data (the image you want to remove the background from).

**When data is sent:**

* Only when you explicitly click "Remove Background" on an image.

**Service links:**

* [Remove.bg Terms of Service](https://www.remove.bg/terms).
* [Remove.bg Privacy Policy](https://www.remove.bg/privacy).

= Data Privacy Notes =

* **Sensitive data filtering**: Credit card numbers, passwords, and other sensitive information are automatically filtered before sending to AI providers.
* **Your API keys**: All API keys are stored locally in your WordPress database and are never shared with third parties.
* **No automatic transmission**: Data is only sent when you actively use AI features (chat, content generation, image generation).
* **GDPR compliance**: Customer consent is required before using the customer chat widget.