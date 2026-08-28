# Changelog

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
- Six transactional e-mails; Turkish translation (Lezzet Ortakları)
