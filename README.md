# LearnDash WooCommerce Product Enrollment

Adds a **WooCommerce Product** enrollment mode to LearnDash courses. Link a course to a WooCommerce product, show the product price on the course page, and send students to checkout with the product already in their cart.

## Features

- New **WooCommerce Product** option in LearnDash course **Enrollment** settings (alongside Open, Closed, Pay Now, etc.)
- Product selector to choose which WooCommerce product grants enrollment
- Course infobar displays the linked product’s WooCommerce price HTML
- Enroll button uses the standard LearnDash “Take this Course” label and links to checkout with `?add-to-cart={product_id}`
- Compatible with LearnDash Course Grid (price ribbon HTML allowed)
- Graceful fallback: missing or invalid product shows the closed-course message instead of a broken enroll link

## Requirements

| Requirement | Version |
| --- | --- |
| WordPress | 6.5+ |
| PHP | 7.4+ |
| WooCommerce | 7.0+ (tested up to 9.6) |
| LearnDash LMS | Active (`sfwd-lms`) |

LearnDash is required but cannot be listed in WordPress’s `Requires Plugins` header because that feature only supports plugins on WordPress.org.

## Installation

1. Download or clone this repository into `wp-content/plugins/learndash-woocommerce-enrollment/`.
2. Activate **LearnDash LMS**, then **WooCommerce**, then this plugin.
3. Edit a course → **Settings** → **Enrollment**.
4. Set enrollment to **WooCommerce Product** and select a product.
5. Save the course.

Activation is blocked (and an admin notice is shown) if LearnDash or WooCommerce is missing.

## How it works

1. You set the course enrollment type to **WooCommerce Product** and pick a product.
2. On the course page, visitors see the product price in the LearnDash infobar and a standard enroll button.
3. Clicking enroll opens checkout with the product added via `checkout/?add-to-cart={product_id}`.

This plugin does **not** handle post-purchase LearnDash enrollment. Configure course/group access on the WooCommerce product using LearnDash’s normal WooCommerce integration (or your existing enrollment workflow).

## Configuration notes

- When **WooCommerce Product** is selected, only the product selector is shown — there is no manual Course Price field. Price comes from WooCommerce’s `get_price_html()`.
- The enroll button label comes from LearnDash’s custom “Take this Course” label when available.
- Price appears in the course infobar and Course Grid ribbon; it is not duplicated on the enroll button itself.

## FAQ

### Does the enroll button show the product price?

No. Price appears in the course infobar (and Course Grid ribbon). The button uses the standard LearnDash enroll label.

### What happens if the product is missing or not purchasable?

The course shows the closed-course message and no broken enroll link is output.

## Development

```
learndash-woocommerce-enrollment/
├── learndash-woocommerce-enrollment.php   # Bootstrap, dependency checks
├── includes/
│   └── class-ldwc-enrollment.php          # Enrollment mode, settings, frontend UI
└── README.md
```

Price type slug: `wc_product`  
Course setting key: `course_price_type_wc_product_id`

## Changelog

### 1.0.1

- Added Update URI for custom update server.

### 1.0.0

- Initial release: WooCommerce Product enrollment mode with product selector and checkout add-to-cart link.
- Price display via `learndash_get_course_price` using WooCommerce `get_price_html()` for the course infobar.

## Manual test checklist

1. Activate LearnDash, WooCommerce, and this plugin. Confirm no dependency notice appears.
2. Edit a course → Enrollment → select **WooCommerce Product** → choose a simple, purchasable product → Save.
3. Confirm only the product selector appears (no manual Course Price field) when this mode is selected.
4. View the course while logged out: infobar shows WooCommerce price HTML; button shows standard enroll text.
5. Click enroll → checkout loads with the product in the cart (`?add-to-cart=` on the checkout URL).
6. Course Grid / listing: price ribbon shows product price (HTML allowed).
7. Clear the product or choose an invalid ID → course shows closed message; no broken enroll link.

## License

License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This plugin is free software; you can redistribute it and/or modify it under the terms of the [GNU General Public License](https://www.gnu.org/licenses/gpl-2.0.html) as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

## Author

[Abundant Designs](https://www.abundantdesigns.com)
