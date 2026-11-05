# ProWebLook Phone, WhatsApp & Email Validator — WooCommerce COD & Lead Guard

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/proweblook-phone-validator?label=WordPress.org)](https://wordpress.org/plugins/proweblook-phone-validator/)
[![Tested up to WordPress 7.1](https://img.shields.io/badge/WordPress-5.8%20--%207.1-21759b)](https://wordpress.org/plugins/proweblook-phone-validator/)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4)](https://wordpress.org/plugins/proweblook-phone-validator/)
[![License: GPLv2+](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Official WordPress & WooCommerce plugin for **[ProWebLook](https://proweblook.com)** — real-time **Phone Carrier Validation**, **Silent WhatsApp COD RTO Guard**, **Caller ID (CNAM) Name-Match**, **Email Deliverability & Disposable Blocking**, **Corporate Domain DNSBL**, **Edge GeoIP Country-Mismatch / VPN Detection**, and **0–100 WooCommerce Fraud Auto-Hold**.

- **WordPress.org Plugin Directory:** https://wordpress.org/plugins/proweblook-phone-validator/
- **API Documentation:** https://docs.proweblook.com/
- **Free Starter Credits + 20,000/mo Edge GeoIP:** https://proweblook.com/auth/signup

---

## Key Capabilities (`v2.1.0`)

1. **Silent WhatsApp COD RTO Guard for WooCommerce**
   - Require an active WhatsApp phone number **only when Cash on Delivery (`cod`) is selected** (or across all checkouts) — zero SMS OTP friction, `<200ms` lookup, slashes fake COD orders and RTO losses while saving credits on prepaid orders.
2. **Caller ID (CNAM) Subscriber Name-Match Guard (`v2.1.0`)**
   - Resolves the registered telecom subscriber/business name (`lookup.proweblook.com/api/v1/calleridvalidation`) and fuzzy-matches it against the shopper's Billing Name & Company (`MATCH`, `PARTIAL`, `MISMATCH`).
3. **Edge GeoIP Country-Mismatch & VPN/Datacenter Detection (`v2.1.0` — 20,000/mo Free)**
   - Checks shopper IP via `ipapi.proweblook.com` to flag Billing-vs-IP country mismatches and cloud/VPN/proxy ASNs while auto-skipping local/private IPs (`127.0.0.1`).
4. **Email Deliverability, Disposable Blocker & Corporate Domain DNSBL (`v2.1.0`)**
   - Verifies MX records + mailbox existence, blocks 10-minute throwaway domains, and screens B2B/corporate domains against 50+ DNSBL blocklists (automatically skipping consumer webmail like `gmail.com`, `yahoo.com`, `outlook.com`, `icloud.com` to preserve credits).
5. **Composite `0–100` Fraud Trust Score & WooCommerce Auto-Hold (`v2.1.0`)**
   - Calculates a `0–100` Trust Score (`Trusted`, `Review`, `High Risk`), renders color-coded pills inside the **WooCommerce → Orders** table (HPOS + Legacy), and automatically transitions risky COD or review-flagged orders to **`On Hold`** (`on-hold`) for manual review.
6. **Phone Carrier & VoIP Burner Filter**
   - Validates global phone numbers (`E.164` or local format with ISO country hint), identifies carrier & line type (`Mobile`, `Fixed Line`, `VoIP`, `Toll-Free`), and optionally blocks virtual/VoIP numbers.
7. **7 Native WordPress Integrations (HPOS & Checkout Blocks Ready)**
   - **WooCommerce** (Classic Checkout, Cart & Checkout Blocks / Store API, HPOS, Fraud Auto-Hold, Orders Trust Column, My Account)
   - **Elementor Pro Forms** (`tel` & `email`)
   - **Contact Form 7** (`[tel]`, `[tel*]`, `[email]`, `[email*]`)
   - **WPForms** (`Phone` & `Email`)
   - **Gravity Forms** (`Phone` & `Email`)
   - **Ninja Forms** (`Phone` & `Email`)
   - **WordPress & WooCommerce User Registration** (`registration_errors`)
   - Plus any custom HTML form via `PROP_activate_third_party()` + `class="propp-phone"`

---

## Quick Start

1. Install from **WordPress Admin → Plugins → Add New** (search `ProWebLook`) or upload this repository to `/wp-content/plugins/proweblook-phone-validator/`.
2. Activate the plugin and open **Settings → ProWebLook Validator**.
3. Sign up at [https://proweblook.com/auth/signup](https://proweblook.com/auth/signup) and copy your API key from [https://proweblook.com/user/my_profile](https://proweblook.com/user/my_profile).
4. Paste your API key, toggle your desired WooCommerce / Form Guard settings, and test all 6 signals right inside the **Live Test Validator** card.

## Running Contract & Integration Tests

```bash
php tests/run_tests.php
```

## License

GPLv2 or later — see [LICENSE](LICENSE).
