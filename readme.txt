=== ProWebLook Phone & Email Validator — COD & Lead Guard ===
Contributors: proweblook
Tags: phone validation, email verification, woocommerce, contact form 7, fraud prevention
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Validate phones, emails, Caller ID & GeoIP fraud scores across WooCommerce, CF7, WPForms, Elementor & Gravity Forms.

== Description ==

**ProWebLook Phone & Email Validator — COD & Lead Guard** protects your WooCommerce store and WordPress lead forms from fake phone numbers, burner VoIP lines, disposable 10-minute emails, blacklisted spam domains, impersonated Caller IDs, VPN/country-mismatched checkouts, and costly Cash-on-Delivery (COD) Return-to-Origin (RTO) losses.

Powered by [ProWebLook](https://proweblook.com) real-time telecom, deliverability & edge intelligence APIs (`<200ms` global average response), every check happens **silently in the background** — no SMS OTP friction, no ringing phones, and zero lost conversions.

= What's New in 2.1 =

* **Caller ID (CNAM) Subscriber Name-Match Guard** — Look up the registered telecom subscriber/business name on Cash on Delivery (`cod`) or all WooCommerce checkouts and fuzzy-match it (`MATCH`, `PARTIAL`, `MISMATCH`) against the shopper's Billing Name & Company.
* **Edge GeoIP Billing-Country Mismatch & VPN/Datacenter Detection (20,000 / mo Free)** — Resolve shopper IP geolocation via `ipapi.proweblook.com` to flag orders where the IP country differs from the WooCommerce Billing Country or originates from a VPN, proxy, or cloud datacenter ISP. Automatically skips local/private IPs (`127.0.0.1`).
* **Corporate Domain DNSBL Blacklist Screening** — Screen B2B/custom email domains against 50+ real-time DNSBL/RBL spam & malware blocklists across WooCommerce, Elementor Forms, Contact Form 7, WPForms, Gravity Forms, Ninja Forms, and User Registration. Automatically skips consumer webmail (`gmail.com`, `yahoo.com`, `outlook.com`, `icloud.com`) so you never waste credits.
* **0–100 Composite Fraud Trust Score & Automatic WooCommerce `On Hold` Guard** — Combines up to 6 real-time ProWebLook signals into a single `0–100` Trust Score (`Trusted`, `Review`, `High Risk`) and can automatically place risky COD or review-flagged orders into WooCommerce **`On Hold`** (`on-hold`) status with a detailed merchant order note before fulfillment.
* **HPOS Orders Table `ProWebLook Trust` Column** — Color-coded `Trusted` / `Review` / `High Risk` score pills directly inside **WooCommerce → Orders** (both HPOS and legacy order tables), plus an enriched 6-signal Trust Signals card on single Order Details screens.
* **6-Signal Admin Dashboard & Live Test Validator** — View live credit balances across Phone, WhatsApp, Email, Caller ID, Domain DNSBL, and Edge GeoIP (`20,000/mo Free`), and test any phone, WhatsApp number, Caller ID, email, domain/IP blacklist, or IP address directly inside **Settings → ProWebLook Validator**.

= Core Capabilities (2.0+) =

* **Silent WhatsApp COD RTO Guard for WooCommerce** — Automatically require an active WhatsApp number specifically when a shopper chooses **Cash on Delivery (`cod`)** (or on all checkouts), without sending any OTP or message. Slashes fake COD orders while saving credits on prepaid Stripe/PayPal/Razorpay orders.
* **WooCommerce Checkout Blocks & HPOS Native Support** — Full compatibility with High-Performance Order Storage (`custom_order_tables`) and the modern React-based WooCommerce Cart & Checkout Blocks (`Store API`), plus Classic Checkout.
* **Email Deliverability & Disposable Email Blocker** — Verify MX records and mailbox existence in real time and block temporary/throwaway email domains across forms, WooCommerce Checkout, and WordPress user signups.
* **VoIP & Burner Phone Blocking** — Optionally reject virtual/VoIP phone numbers so sales teams only receive real mobile and landline leads.
* **Smart 12-Hour Transient Caching & Fail-Open Reliability** — Identical lookups are cached in WordPress transients for 12 hours so repeat form edits never double-bill credits. If a network timeout ever occurs, validation fails open gracefully so legitimate customers are never blocked.

= Supported Plugins & Integrations =

* **WooCommerce** (Classic Checkout, Cart & Checkout Blocks / Store API, HPOS, Fraud Auto-Hold, Orders Table Trust Column, My Account Billing Edit, Order Trust Signals)
* **Elementor Pro Forms** (`tel` and `email` fields + Disposable & Corporate DNSBL Guard)
* **Contact Form 7** (`[tel]`, `[tel*]`, `[email]`, `[email*]`)
* **WPForms** (`Phone` and `Email` fields)
* **Gravity Forms** (`Phone` and `Email` fields)
* **Ninja Forms** (`Phone` and `Email` fields)
* **WordPress & WooCommerce User Registration** (`wp-login.php?action=register` and My Account signups)
* **Custom HTML / 3rd-Party Forms** — Call `PROP_activate_third_party();` in your theme and add `class="propp-phone"` to any phone input field.

= Free Credits & 20,000/mo Free Edge GeoIP Included =

Sign up free at [https://proweblook.com/auth/signup](https://proweblook.com/auth/signup) (no credit card required) and receive **free starter credits across Phone Validation, WhatsApp Verification & Email Verification** plus **20,000 free Edge IP Geolocation lookups every month**. Pay-as-you-go credit packs and monthly/annual plans for Phone, WhatsApp, Email, Caller ID, and Domain DNSBL are available at [https://proweblook.com/pricing](https://proweblook.com/pricing).

== External services ==

This plugin connects to the **ProWebLook API** (`https://lookup.proweblook.com`, `https://ipapi.proweblook.com`, and `https://proweblook.com`) to perform real-time phone carrier validation, silent WhatsApp presence detection, Caller ID (CNAM) subscriber lookup, email deliverability verification, domain DNSBL blacklist screening, shopper IP geolocation, and account credit balance lookups.

* **What data is sent and when:**
  * **Phone Validation (`https://lookup.proweblook.com/api/v1/phonevalidation`):** Sent when a visitor submits an enabled form or WooCommerce checkout (or during an admin live test), transmitting `api_key`, `phone_number`, and optional 2-letter ISO `country_code`.
  * **Silent WhatsApp Check (`https://lookup.proweblook.com/api/v1/wavalidation`):** Sent when WhatsApp validation or WooCommerce WhatsApp COD Guard is enabled (or during an admin live test), transmitting `api_key`, `phone_number`, and optional `country_code`.
  * **Email Deliverability Check (`https://lookup.proweblook.com/api/v1/emailvalidation`):** Sent when Email validation is enabled on a form, registration, or checkout (or during an admin live test), transmitting `api_key` and `email`.
  * **Caller ID / CNAM Lookup (`https://lookup.proweblook.com/api/v1/calleridvalidation`):** Sent only when WooCommerce Caller ID (CNAM) Name-Match Guard is enabled (or during an admin live test), transmitting `api_key`, `phone_number`, and optional `country_code`. Customer billing names stay in WordPress and are compared locally.
  * **Domain DNSBL Blacklist Check (`https://lookup.proweblook.com/api/v1/blacklistchecker`):** Sent only when Corporate Domain DNSBL Check is enabled for non-free-webmail domains (or during an admin live test), transmitting `api_key`, `target` (domain or IP), and `target_type`.
  * **Edge IP Geolocation (`https://ipapi.proweblook.com/`):** Sent only when WooCommerce Edge GeoIP Country-Mismatch & VPN Check is enabled (or during an admin live test), transmitting `api_key` and public shopper `ip`.
  * **Credit Balance Check (`https://proweblook.com/api/v1/zapier/get_balance` and fallback `https://proweblook.com/api/v1/checkbalance`):** Sent when an administrator opens **Settings → ProWebLook Validator** or refreshes balances, transmitting `api_key`.
* **No tracking without configuration:** No external requests are made until a site administrator enters a ProWebLook API key and enables at least one integration.
* **Service Provider Policies:**
  * ProWebLook Website: [https://proweblook.com](https://proweblook.com)
  * ProWebLook Privacy Policy: [https://proweblook.com/privacy-policy](https://proweblook.com/privacy-policy)
  * ProWebLook Terms & Conditions: [https://proweblook.com/generic/terms_conditions](https://proweblook.com/generic/terms_conditions)

== Installation ==

1. Install **ProWebLook Phone, WhatsApp & Email Validator — COD & Lead Guard** from **Plugins → Add New** in WordPress, or upload the `proweblook-phone-validator` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Create a free account at [https://proweblook.com/auth/signup](https://proweblook.com/auth/signup) and copy your API key from [https://proweblook.com/user/my_profile](https://proweblook.com/user/my_profile).
4. Navigate to **Settings → ProWebLook Validator**, paste your API key, enable your desired WooCommerce COD / Fraud Auto-Hold / Form Guard integrations, and click **Save Validator Settings**.
5. Use the built-in **6-Signal Live Test Validator** card on the settings page to test Phone, WhatsApp, Caller ID, Email, Domain DNSBL, and Edge GeoIP immediately.

== Frequently Asked Questions ==

= Does Silent WhatsApp validation send an OTP or message to my customer? =

No. ProWebLook checks WhatsApp registration and WhatsApp Business status silently in real time without sending any message, notification, or OTP code to the customer's phone.

= Can I require WhatsApp or Caller ID checks only for Cash on Delivery (COD) orders? =

Yes! Under **Settings → ProWebLook Validator**, both **WooCommerce WhatsApp COD Guard** and **Caller ID (CNAM) Name-Match Guard** offer a **Cash on Delivery (COD) Orders Only** mode so prepaid Stripe/PayPal/Razorpay orders never consume extra credits.

= How does WooCommerce Fraud Auto-Hold work? =

When enabled, ProWebLook calculates a `0–100` composite Trust Score across your active signals (Phone Carrier/VoIP, WhatsApp presence, Email deliverability, Corporate Domain DNSBL, Caller ID subscriber name match, and Edge GeoIP country mismatch / VPN ISP). Orders scoring below your chosen threshold (`High Risk < 45` or `COD Review < 65`) are automatically placed into WooCommerce **`On Hold`** status with a detailed merchant order note instead of entering fulfillment immediately.

= Does Domain DNSBL burn credits on Gmail, Yahoo, or Outlook shoppers? =

No. Automatic checkout and form DNSBL checks skip known consumer webmail providers (`gmail.com`, `yahoo.com`, `outlook.com`, `icloud.com`, `proton.me`, etc.) so credits are reserved strictly for custom and corporate B2B domains.

= Does this work with WooCommerce Checkout Blocks and HPOS? =

Yes. The plugin declares official compatibility with both WooCommerce High-Performance Order Storage (`custom_order_tables`) and WooCommerce Cart & Checkout Blocks (`cart_checkout_blocks`), validating via the Store API as well as Classic Checkout.

= What happens if my API credits run out or there is a network timeout? =

The plugin is engineered to **fail open** on network timeouts or depleted credits so you never lose a legitimate sale or lead, and displays an administrator notice in WP Admin alerting you to top up.

== Screenshots ==

1. WordPress Admin Settings — Live 6-service credit balances, WhatsApp COD Guard, Caller ID CNAM, Edge GeoIP, Fraud Auto-Hold toggles, and 6-signal Live Test Validator.
2. WooCommerce Checkout, HPOS Orders Trust Column & Order Details — Composite 0–100 Trust Score pills and Carrier / WhatsApp / Caller ID / Email / DNSBL / GeoIP Trust Signals attached to orders.

== Changelog ==

= 2.1.1 =
* Updated plugin display name to **ProWebLook Phone & Email Validator — COD & Lead Guard** for compliance with WordPress.org Plugin Directory trademark naming guidelines (`GUIDELINES TRM`).

= 2.1.0 =
* Added **Caller ID (CNAM) Subscriber Name-Match Guard** (`lookup.proweblook.com/api/v1/calleridvalidation`) with local fuzzy name matching (`match`, `partial`, `mismatch`) against WooCommerce Billing Name & Company (`COD Only` or `All Checkouts`).
* Added **Edge GeoIP Country-Mismatch & VPN/Datacenter Detection** (`ipapi.proweblook.com`, 20,000 free lookups/mo) to flag Billing-vs-IP country mismatches and cloud/VPN proxy networks while auto-skipping local/private IPs.
* Added **Corporate Domain DNSBL Blacklist Screening** (`lookup.proweblook.com/api/v1/blacklistchecker`) across WooCommerce, Elementor Forms, Contact Form 7, WPForms, Gravity Forms, Ninja Forms, and User Registration (auto-skips free webmail providers like Gmail/Yahoo/Outlook to save credits).
* Added **Composite `0–100` Fraud Trust Score** and **WooCommerce Fraud Auto-Hold** (`high_risk`, `cod_review`, `all_review`) to automatically place suspicious orders into `on-hold` status with an explanatory merchant order note.
* Added color-coded **`ProWebLook Trust`** score column to WooCommerce Orders list tables (both HPOS and legacy CPT) and expanded the Admin Order Details Trust Signals card.
* Upgraded **Settings → ProWebLook Validator** with 6 balance cards (`Phone`, `WhatsApp`, `Email`, `Caller ID`, `Domain DNSBL`, and `Edge GeoIP: 20,000 / mo Free`) and a 6-signal interactive Live Test Validator.

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

= 2.1.0 =
Adds Caller ID (CNAM) Name-Match Guard, free 20,000/mo Edge GeoIP Country-Mismatch & VPN detection, Corporate Domain DNSBL screening, 0-100 Fraud Trust Scores, HPOS Orders Trust column, and automatic WooCommerce On-Hold protection. 100% backward compatible.

= 2.0.0 =
Major upgrade adding Silent WhatsApp COD RTO Guard, Email & Disposable Email Verification, VoIP blocking, Elementor Forms, and WooCommerce Block Checkout + HPOS support. 100% backward compatible with existing v1.4.1 settings.
