=== LearnDash WooCommerce Product Enrollment ===
Contributors: robertstaddon
Tags: learndash, woocommerce, enrollment, lms, course
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a WooCommerce Product enrollment mode to LearnDash courses.

== Description ==

This plugin adds a **WooCommerce Product** option to the LearnDash course **Enrollment** settings (alongside Open, Closed, Pay Now, etc.). When selected:

* You choose one WooCommerce product that grants enrollment (via your existing LearnDash + WooCommerce setup).
* The course page shows the product price in the infobar and a standard LearnDash enroll button.
* Clicking enroll sends visitors to checkout with the product added (`checkout/?add-to-cart={product_id}`).

The plugin does not handle post-purchase enrollment; configure that on the WooCommerce product using LearnDash's normal integration.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/learndash-woocommerce-enrollment/` or install from your repository.
2. Activate **LearnDash LMS** (`sfwd-lms`), **WooCommerce**, then this plugin.

LearnDash is required but is not listed in the `Requires Plugins` header because that feature only supports plugins on WordPress.org (LearnDash is premium).
3. Edit a course → **Settings** → **Enrollment** → set **WooCommerce Product** and select a product.

== Frequently Asked Questions ==

= Can I use this with LearnDash WooCommerce Credit/Audit? =

Yes. This plugin uses price type `wc_product`; Credit/Audit uses `wcca`. Both can be active on the same site.

= Does the enroll button show the product price? =

No. Price appears in the course infobar (and Course Grid ribbon). The button uses the standard LearnDash "Take this Course" label.

== Changelog ==

= 1.0.0 =
* Initial release: WooCommerce Product enrollment mode with product selector and checkout add-to-cart link.
* Price display: injects WooCommerce `get_price_html()` via `learndash_get_course_price` for the course infobar (LearnDash only shows custom types when `course_price` is set or type is closed/free).

== Manual test checklist ==

1. Activate LearnDash, WooCommerce, and this plugin. Confirm no dependency notice appears.
2. Edit a course → Enrollment → select **WooCommerce Product** → choose a simple, purchasable product → Save.
3. Confirm only the product selector appears (no manual Course Price field) when this mode is selected.
4. View the course while logged out: infobar shows WooCommerce price HTML; button shows standard enroll text.
5. Click enroll → checkout loads with the product in the cart (`?add-to-cart=` on the checkout URL).
6. Course Grid / listing: price ribbon shows product price (HTML allowed).
7. With Credit/Audit plugin active: both **WooCommerce Product** and **WooCommerce Credit/Audit** appear as separate enrollment types.
8. Clear the product or choose an invalid ID → course shows closed message; no broken enroll link.
