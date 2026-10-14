# Changelog

## 1.4.1 — 2026-10-06
- **Attribution no longer dies on the `www` host.** The referral cookie is host-scoped (`COOKIE_DOMAIN` is undefined on most installs, so PHP writes a host-only cookie). A visit to `www.shop.com/?ref=CODE` set the cookie on the **www** host, WordPress then canonical-redirected the visitor to `shop.com`, and the browser never sent that cookie again: the click was recorded, the cookie evaporated on the next request, and the order was attributed to nobody — the partner lost a sale that looked organic. `?ref=` is now moved to the canonical host **before** it is consumed, so the cookie is only ever written where the customer will actually shop. Measured end to end: before the fix the cookie was bound to `www.…` and absent from requests to the bare host; after it is bound to the canonical host and sent.
- **Coupon orders created outside the checkout now reach the partner.** Attribution only ran on the two frontend checkout hooks, so an order opened from wp-admin, a POS, a phone order or an import carried the partner's coupon and still earned them nothing. A late pass now runs on payment/processing/completed, before commissions are recorded. It attributes **from the coupon only** — never from the cookie, because in that context the cookie belongs to whoever is processing the order (a staff member), not to the customer; that guard is verified by mutation.

## 1.4.0 — 2026-09-23
- **A coupon can no longer discount more than the store budgeted for it.** Group shares differ by category (15% default, 8% or 13% for some groups). A partner with a 15% coupon selling from an 8% group used to have the full discount applied while the commission came out as `max(0, 8 − 15) = 0`: the store paid nearly double the discount it had budgeted and the partner earned nothing. Lines whose share is smaller than the coupon discount are now **outside the coupon** — no discount is applied there and the partner earns the link rate on them instead. Where discount equals the share, the coupon still applies (same cost to the store) and the commission is zero, which is the partner's own choice.
- **Brand scope is actually enforced.** `SSA_Coupon::brand_scope_check()` was referenced in comments but had never been written, so a "selected brands" coupon discounted the *entire* store while commission was only counted on that brand. Scope is now decided in one place — `SSA_Calculator::covers()` — and WooCommerce reads the same decision through `woocommerce_coupon_is_valid_for_product` (`SSA_Coupon::scope_check`). What the customer is charged and what the partner earns cannot disagree.
- **The customer is told what the coupon covers.** On a mixed basket, the cart and checkout now say how many items the coupon applied to and which ones it did not, instead of leaving the shopper to work out why the discount looked small. A rejected coupon explains itself: below the minimum it says how much more is needed; out of scope it says so in plain words.
- **The minimum basket is visible.** It was one small grey line at the bottom of the coupon form. It is now a badge at the top of the form, a badge on every coupon in the list, and a `{min_order}` placeholder for the content-kit captions.
- **The coupon creator sees the fallout as they set the discount.** The static "heads up" note is now a live box: move the discount slider and it lists exactly which categories drop out of the coupon at that rate, or confirms that none do.
- **Orders say where they came from.** The order list has a column with the partner, the code used (coupon or link) and the commission; the order screen's box is titled with the program name and sits at the top of the sidebar.

## 1.3.0 — 2026-09-09
- **Brand scope for partner coupons.** Alongside whole store / products / categories, a coupon can now target selected **brands** (`product_brand`). WooCommerce has no native brand restriction, so it is enforced through `woocommerce_coupon_is_valid_for_product`; the commission calculator applies the same rule, so what the customer is charged and what the partner earns can no longer disagree.
- **Exclusions on whole-store coupons.** A store-wide coupon can now exclude individual products, categories or brands. Program-wide excluded categories and the partner's own exclusions are merged. New `exclude_ids` column (`ssa_db_version` 1.3.0).
- **Clearer "no commission" reason.** A single `zero` reason told partners nothing. The most common case — the coupon discount exceeding the line's share group (8% share, 10% discount → 0) — now has its own `discount_over_share` reason and reads "Kazanç yok" instead of "İptal", which is a different thing and was misleading.
- Partner panel and admin: scope labels list brands; coupon form gains brand and exclusion pickers; short explanations added across coupons, earnings, links, kit and sales pages so each figure says where it comes from.

## 1.2.2 — 2026-08-28
- Application form: optional "Platforms & followers" field (used by custom landing-page forms; shown on the application card).

## 1.2.1 — 2026-08-28
- Partners can edit a coupon (campaign name, discount, scope, end date) from "My coupons"; the code itself stays fixed. Admins get the same edit form in the partner profile.

## 1.2.0 — 2026-08-28
- **Partner coupons.** Partners create their own campaign coupons from My Account ("My coupons"): code, optional campaign name, discount (between the configured min/max, taken out of the share), scope (whole store / selected products / selected categories), optional end date. Each is a real WooCommerce coupon (individual use, per-customer limit, minimum basket, excluded categories, scope restrictions). Partners can pause, resume and delete; admins manage them from the partner profile.
- **Commission model.** Per line item: covered by the coupon → `share − discount`; not covered, or no coupon (referral link) → `min(link rate, share)`. New settings: link commission %, min/max coupon discount, coupon code length. Removed: default split, split-change interval, code rotation/grace.
- Removed the single partner code + "Split my share" page; the partner keeps an auto-generated **link code** for `?ref=` only (admin-editable).
- Content kit captions pick a coupon to fill `{code}` / `{discount}`; dashboard hero shows the referral link and live coupons.
- Admin: partners list shows live coupons and best code; profile has a Coupons tab; overview/report donut shows live coupons by discount; anomaly report per coupon code; order box shows the coupon.
- Migration (`ssa_db_version` 1.2.0): every existing partner code becomes a "Main code" whole-store coupon with the old discount; partners get a new link code; old codes keep working in `?ref=` links. Endpoint `split` → `coupons`.
- Default options no longer call `__()` at load time (WP 6.7 JIT translation); translated defaults are stored at activation.

## 1.1.1 — 2026-08-28
- Fix: column charts are now HTML/CSS — axis labels stay crisp at every width (SVG scaling made them tiny and blurry)
- Fix: e-mail titles were translated before `init` (WordPress 6.7+ just-in-time loading) and showed up in English; hook registration no longer translates
- Fix: commission void reasons (`order cancelled`, `below minimum basket`, …) are now translated in admin and partner panel
- Fix: partner list — status badge no longer overlaps the tier name; split legend wraps instead of overflowing; overview cards align to top
- Fix: default tier names, program name and legal notice are translatable
- Partner panel: spacing after the legal notice on the content kit page

## 1.1.0 — 2026-08-28
- Admin: Overview tab with KPI cards (vs previous period), inline SVG charts (monthly revenue/commission, new vs returning, split donut, share groups), date-range presets, needs-attention list
- Admin: partner list with avatars, code chips, split bars and sparklines; partner profile with KPIs and tabs; application cards; commission/payout summary strips and filters; CSV export for commissions
- Partner panel: hero with code + split bar, confirmed vs estimated earnings, daily traffic chart, monthly commission chart, live split example, product search in link generator, payout progress meter
- Cache-busting asset versions; partner-safe product search endpoint

## 1.0.0 — 2026-08-27
- Initial release: applications & approval, partner codes as WooCommerce coupons, referral links with click tracking
- Split slider (commission vs. follower discount) with change interval
- Commission calculator with product-group shares, excluded categories, minimum basket, cap, returning-customer factor, campaign periods and product boosters
- Hold period, cancellation/refund handling, post-payout refund adjustments
- Monthly payouts with minimum and carry-over, CSV export, paid notifications
- Partner tiers, code rotation, leak-detection report
- My Account partner panel (dashboard, sales, earnings, split, links, content kit)
- Admin screens: partners, applications, commissions, payouts, reports; order meta box
- Six transactional e-mails; Turkish translation
