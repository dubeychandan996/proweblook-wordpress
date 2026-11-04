# ProWebLook Phone, WhatsApp & Email Validator for WordPress

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/proweblook-phone-validator?label=WordPress.org)](https://wordpress.org/plugins/proweblook-phone-validator/)
[![Tested up to WordPress 7.1](https://img.shields.io/badge/WordPress-5.8%20--%207.1-21759b)](https://wordpress.org/plugins/proweblook-phone-validator/)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4)](https://wordpress.org/plugins/proweblook-phone-validator/)
[![License: GPLv2+](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Official WordPress & WooCommerce plugin for **[ProWebLook](https://proweblook.com)** — real-time **Phone Carrier Validation**, **Silent WhatsApp COD RTO Guard**, and **Email Deliverability & Disposable Email Blocking**.

- **WordPress.org Plugin Directory:** https://wordpress.org/plugins/proweblook-phone-validator/
- **API Documentation:** https://docs.proweblook.com/
- **Get 50 Free Credits (Phone + WhatsApp + Email):** https://proweblook.com/auth/signup

---

## Key Capabilities (`v2.0.0`)

1. **Silent WhatsApp COD RTO Guard for WooCommerce**
   - Require an active WhatsApp phone number **only when Cash on Delivery (`cod`) is selected** (or across all checkouts) — zero SMS OTP friction, `<200ms` lookup, slashes fake COD orders and RTO losses while saving credits on prepaid orders.
2. **Phone Carrier & VoIP Burner Filter**
   - Validates global phone numbers (`E.164` or local format with ISO country hint), identifies carrier & line type (`Mobile`, `Fixed Line`, `VoIP`, `Toll-Free`), and optionally blocks virtual/VoIP numbers.
3. **Email Deliverability & Disposable Blocker**
   - Verifies MX records and mailbox existence in real time and rejects 10-minute / throwaway email domains across checkout, lead forms, and WordPress user signups.
4. **WooCommerce Block Checkout & HPOS Native**
   - Compatible with **High-Performance Order Storage (`custom_order_tables`)** and **Cart & Checkout Blocks (`Store API`)**, attaching carrier, line type, WhatsApp Personal/Business status, and email verdicts directly to Order Meta, Order Notes, and the Admin Order Details screen.
5. **7 Native WordPress Integrations**
   - WooCommerce (Classic + Block Checkout + My Account)
   - Elementor Pro Forms (`tel` & `email`)
   - Contact Form 7 (`[tel]`, `[tel*]`, `[email]`, `[email*]`)
   - WPForms (`Phone` & `Email`)
   - Gravity Forms (`Phone` & `Email`)
   - Ninja Forms (`Phone` & `Email`)
   - WordPress & WooCommerce User Registration (`registration_errors`)
   - Plus any custom HTML form via `PROP_activate_third_party()` + `class="propp-phone"`

---

## Quick Start

1. Install from **WordPress Admin → Plugins → Add New** (search `ProWebLook Phone Validator`) or upload this repository to `/wp-content/plugins/proweblook-phone-validator/`.
2. Activate the plugin and open **Settings → ProWebLook Validator**.
3. Sign up at [https://proweblook.com/auth/signup](https://proweblook.com/auth/signup) and copy your API key from [https://proweblook.com/user/my_profile](https://proweblook.com/user/my_profile).
4. Paste your API key, toggle your desired WooCommerce / form integrations, and test right inside the **Live Test Validator** card.

## Running Contract & Integration Tests

```bash
php tests/run_tests.php
```

## License

GPLv2 or later — see [LICENSE](LICENSE).
