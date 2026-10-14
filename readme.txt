=== SplitShare Affiliates for WooCommerce ===
Contributors: erayusta
Tags: woocommerce, affiliate, influencer, referral, commission
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Influencer affiliate program: partners create their own campaign coupons; the follower discount comes out of a fixed share and the rest is their commission.

== Description ==
A fixed share of every sale is set aside. Partners create as many campaign coupons as they like from My Account — each with its own discount (taken out of the share; the rest is commission), scope (whole store, selected products or categories) and optional end date. Orders through a referral link without a coupon earn a configurable link rate. Product-group shares, hold period, refund handling, monthly payouts with minimum and carry-over, admin reports and e-mails. HPOS compatible, translatable (Turkish included).

== Installation ==
1. Activate the plugin (requires WooCommerce).
2. Configure WooCommerce > Settings > Affiliates.
3. Create a page with the [ssa_apply] shortcode and select it as the application page.

== Changelog ==
= 1.4.1 =
* Fixed: referral attribution was lost when the affiliate link was opened on the `www` host (cookie stranded on the wrong host by the canonical redirect).
* Fixed: orders created outside the frontend checkout (admin, POS, phone, import) that use a partner coupon are now attributed to that partner.

= 1.4.0 =
* Coupons no longer discount more than the category's share: low-share categories fall outside the coupon and earn the link rate instead.
* Brand-scoped coupons are now actually restricted to those brands (they used to discount the whole store).
* Cart and checkout explain what a partner coupon covers, and why it was rejected.
* Minimum basket total shown prominently in the panel; `{min_order}` placeholder for kit captions.
* Orders list shows the partner, the code used and the commission.

= 1.3.0 =
* Coupons can target brands, and whole-store coupons can exclude products, categories or brands. Clearer reason when a coupon discount leaves no commission.

= 1.2.1 =
* Coupons can be edited (name, discount, scope, end date) from the partner panel and the admin profile.

= 1.2.0 =
* Partner-created campaign coupons (discount, product/category scope, end date) replace the single code + split; link orders earn a configurable rate. Automatic migration of existing codes.

= 1.1.1 =
* Fix: crisp HTML column charts, translation loading (e-mail titles), reason labels, partner table layout, translatable defaults.

= 1.1.0 =
* Admin overview/reports, partner panel with traffic and earnings.

= 1.0.0 =
* Initial release.
