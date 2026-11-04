=== ProWebLook Phone, WhatsApp & Email Validator ===
Contributors: proweblook
Tags: phone validation, whatsapp, email verification, woocommerce, contact form 7
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Validate phone numbers, block fake COD orders via silent WhatsApp checks, and verify email deliverability across WooCommerce & WordPress forms.

== Description ==

**ProWebLook Phone, WhatsApp & Email Validator** protects your WooCommerce store and WordPress lead forms from fake phone numbers, burner VoIP lines, undeliverable or disposable 10-minute emails, and costly Cash-on-Delivery (COD) Return-to-Origin (RTO) orders.

Powered by [ProWebLook](https://proweblook.com) real-time telecom & deliverability APIs (`<200ms` global average response), every check happens **silently in the background** — no SMS OTP friction, no ringing phones, and zero lost conversions.

= What's New in 2.0 =

* **Silent WhatsApp COD RTO Guard for WooCommerce** — Automatically require an active WhatsApp number specifically when a shopper chooses **Cash on Delivery (`cod`)** (or on all checkouts), without sending any OTP or message. Slashes fake COD orders while saving credits on prepaid Stripe/PayPal/Razorpay orders.
* **WooCommerce Checkout Blocks & HPOS Native Support** — Full compatibility with High-Performance Order Storage (`custom_order_tables`) and the modern React-based WooCommerce Cart & Checkout Blocks (`Store API`), plus Classic Checkout.
* **WooCommerce Order Trust Signals** — Attaches carrier name, line type (`Mobile`, `Fixed Line`, `VoIP`), WhatsApp status (`Personal` vs `Business`), and email deliverability verdict directly to Order Meta, Merchant Order Notes, and the Admin Order Details panel.
* **Email Deliverability & Disposable Email Blocker** — Verify MX records and mailbox existence in real time and block temporary/throwaway email domains across forms, WooCommerce Checkout, and WordPress user signups.
* **VoIP & Burner Phone Blocking** — Optionally reject virtual/VoIP phone numbers so sales teams only receive real mobile and landline leads.
* **Elementor Pro Forms + User Registration Guard** — Native server-side validation for Elementor Pro Forms (`tel` and `email` fields) and WordPress / WooCommerce account registration, alongside Contact Form 7, WPForms, Gravity Forms, and Ninja Forms.
* **Smart 12-Hour Transient Caching & Fail-Open Reliability** — Identical lookups are cached in WordPress transients for 12 hours so repeat form edits never double-bill credits. If a network timeout ever occurs, validation fails open gracefully so legitimate customers are never blocked.

= Supported Plugins & Integrations =

* **WooCommerce** (Classic Checkout, Cart & Checkout Blocks / Store API, HPOS, My Account Billing Edit, Order Trust Signals)
* **Elementor Pro Forms** (`tel` and `email` fields)
* **Contact Form 7** (`[tel]`, `[tel*]`, `[email]`, `[email*]`)
* **WPForms** (`Phone` and `Email` fields)
* **Gravity Forms** (`Phone` and `Email` fields)
* **Ninja Forms** (`Phone` and `Email` fields)
* **WordPress & WooCommerce User Registration** (`wp-login.php?action=register` and My Account signups)
* **Custom HTML / 3rd-Party Forms** — Call `PROP_activate_third_party();` in your theme and add `class="propp-phone"` to any phone input field.

= Free Credits Included =

Sign up free at [https://proweblook.com/auth/signup](https://proweblook.com/auth/signup) (no credit card required) and receive **50 Phone Validation + 50 WhatsApp Verification + 50 Email Verification free credits** that never expire. Pay-as-you-go credit packs and monthly/annual plans are available at [https://proweblook.com/pricing](https://proweblook.com/pricing).

== External services ==

This plugin connects to the **ProWebLook API** (`https://lookup.proweblook.com` and `https://proweblook.com`) to perform real-time phone carrier validation, silent WhatsApp presence detection, email deliverability verification, and account credit balance lookups.

* **What data is sent and when:**
  * When a visitor submits an enabled form or WooCommerce checkout (or when a site administrator runs a test in **Settings → ProWebLook Validator**), the plugin sends the configured ProWebLook `api_key` along with the submitted `phone_number` (and optional 2-letter ISO `country_code`) to `https://lookup.proweblook.com/api/v1/phonevalidation` and/or `https://lookup.proweblook.com/api/v1/wavalidation`, and/or sends the submitted `email` address to `https://lookup.proweblook.com/api/v1/emailvalidation`.
  * When an administrator opens **Settings → ProWebLook Validator**, the plugin sends the `api_key` to `https://proweblook.com/api/v1/zapier/get_balance` (with fallback to `https://proweblook.com/api/v1/checkbalance`) to display remaining credit balances.
* **No tracking without configuration:** No external requests are made until a site administrator enters a ProWebLook API key and enables at least one integration.
* **Service Provider Policies:**
  * ProWebLook Website: [https://proweblook.com](https://proweblook.com)
  * ProWebLook Privacy Policy: [https://proweblook.com/privacy-policy](https://proweblook.com/privacy-policy)
  * ProWebLook Terms & Conditions: [https://proweblook.com/generic/terms_conditions](https://proweblook.com/generic/terms_conditions)

== Installation ==

1. Install **ProWebLook Phone, WhatsApp & Email Validator** from **Plugins → Add New** in WordPress, or upload the `proweblook-phone-validator` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Create a free account at [https://proweblook.com/auth/signup](https://proweblook.com/auth/signup) and copy your API key from [https://proweblook.com/user/my_profile](https://proweblook.com/user/my_profile).
4. Navigate to **Settings → ProWebLook Validator**, paste your API key, enable your desired WooCommerce / form integrations, and click **Save Validator Settings**.
5. Use the built-in **Live Test Validator** card on the settings page to test Phone, WhatsApp, and Email checks immediately.

== Frequently Asked Questions ==

= Does Silent WhatsApp validation send an OTP or message to my customer? =

No. ProWebLook checks WhatsApp registration and WhatsApp Business status silently in real time without sending any message, notification, or OTP code to the customer's phone.

= Can I require WhatsApp verification only for Cash on Delivery (COD) orders? =

Yes! Under **Settings → ProWebLook Validator → WooCommerce WhatsApp COD Guard**, select **Cash on Delivery (COD) Orders Only**. Prepaid customers paying via Stripe, PayPal, Razorpay, or cards check out normally without consuming WhatsApp credits, while COD shoppers must enter an active WhatsApp number.

= Does this work with WooCommerce Checkout Blocks and HPOS? =

Yes. Version 2.0 declares official compatibility with both WooCommerce High-Performance Order Storage (`custom_order_tables`) and WooCommerce Cart & Checkout Blocks (`cart_checkout_blocks`), validating via the Store API as well as Classic Checkout.

= What happens if my API credits run out or there is a network timeout? =

The plugin is engineered to **fail open** on network timeouts or depleted credits so you never lose a legitimate sale or lead, and displays an administrator notice in WP Admin alerting you to top up.

= Will repeat form submissions burn extra credits? =

No. Completed Phone, WhatsApp, and Email verification verdicts are cached in WordPress transients for 12 hours (customizable via the `prop_cache_ttl` filter).

== Screenshots ==

1. WordPress Admin Settings — Live Phone, WhatsApp & Email credit balances, VoIP & COD WhatsApp RTO Guard toggles, and interactive Live Test Validator.
2. WooCommerce Checkout & HPOS Order Details — Silent WhatsApp Cash-on-Delivery protection and verified Carrier / WhatsApp / Email Trust Signals attached to orders.

== Changelog ==

= 2.0.0 =
* Added Silent WhatsApp Presence & Business Account verification (`lookup.proweblook.com/api/v1/wavalidation`) with dedicated WooCommerce **Cash on Delivery (COD) RTO Guard** mode.
* Added real-time Email Deliverability & Disposable Email Blocking (`lookup.proweblook.com/api/v1/emailvalidation`) across WooCommerce, forms, and user registration.
* Added VoIP / virtual burner phone blocking option (`propp_block_voip`).
* Added full WooCommerce High-Performance Order Storage (HPOS) and Cart & Checkout Blocks (`Store API`) compatibility, plus HPOS Order Meta, Order Notes, and Admin Order Trust Signals badge.
* Added native Elementor Pro Forms (`tel` and `email`) and WordPress / WooCommerce User Registration integrations.
* Upgraded Contact Form 7, WPForms, Gravity Forms, and Ninja Forms handlers to support custom inline validation messages, WhatsApp checks, and Email checks.
* Added 12-hour persistent WordPress transient caching (`prop_v2_*`) to prevent duplicate credit consumption, plus per-IP rate limiting on public AJAX checks.
* Redesigned **Settings → ProWebLook Validator** dashboard with unified Phone / WhatsApp / Email credit balance cards, settings sanitization callback, cache clearing, and a 3-service Live Test Validator.
* Tested up to WordPress 7.1 and WooCommerce 9.8.

= 1.4.1 =
* Initial WordPress.org release with Phone Validation for WooCommerce, Contact Form 7, WPForms, Gravity Forms, and Ninja Forms.

== Upgrade Notice ==

= 2.0.0 =
Major upgrade adding Silent WhatsApp COD RTO Guard, Email & Disposable Email Verification, VoIP blocking, Elementor Forms, and WooCommerce Block Checkout + HPOS support. 100% backward compatible with existing v1.4.1 settings.
