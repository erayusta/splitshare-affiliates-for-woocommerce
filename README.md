# SplitShare Affiliates for WooCommerce

An influencer / creator affiliate program for WooCommerce with one unusual rule: **the partner decides how to split their share.** A fixed percentage of every sale (default 15%) is set aside; the partner keeps part of it as commission and gives the rest to their followers as a discount. The store's cost is identical whatever they choose — no tiers, no negotiation, one number.

> Built for the Turkish grocery store Marketten Gelse's **Lezzet Ortakları** program, released as a brand-neutral plugin. The Turkish translation and default settings reproduce that program.

## How it works

1. Creators apply through a form (`[ssa_apply]`). You approve them from **WooCommerce → Affiliates**.
2. Each partner gets a **code** (a real WooCommerce coupon, single-use per customer, minimum basket, excluded categories) and a **link** (`?ref=CODE`, 15-day cookie).
3. On every attributed order the plugin computes the commission per line item: `min(partner commission %, group share − applied discount %) × amount paid`. Groups (e.g. wholesale, coffee) can have a smaller share; excluded categories earn nothing.
4. Commissions wait out the return window (21 days after completion), are voided by cancellations, recalculated on partial refunds and deducted from the next payout if refunded after being paid.
5. On the payout day (15th) approved balances at or above the minimum (₺500) become payouts; the rest carries over. You transfer the money, mark the payout paid (bank reference), the partner gets an e-mail.
6. Partners manage everything from **My Account**: dashboard, sales, earnings & bank details, split slider (once per 30 days), link generator with click stats, content kit.

## Features

- Split slider with presets; discount and commission always add up to the share
- Coupon **and** link attribution (coupon wins); self-orders are ignored
- Product-group shares by category (child categories inherit; lowest share wins), excluded categories
- Campaign periods (temporarily higher share) and product boosters ("earning extra this week")
- Returning-customer factor (default ½), per-order cap, minimum basket
- Hold period, partial-refund recalculation, post-payout refund adjustments
- Monthly payout batches, minimum payout with carry-over, CSV export for bank transfers
- Partner tiers by approved sales, periodic code rotation with grace period, leak-detection report
- Reports: new-customer rate, split distribution, effective cost rate, share groups, top partners, code usage
- 6 transactional e-mails (WooCommerce e-mail settings, template overrides)
- HPOS and Checkout Blocks compatible; everything translatable (`splitshare-affiliates`), Turkish included

## Installation

1. Upload/clone into `wp-content/plugins/splitshare-affiliates-for-woocommerce` and activate (WooCommerce 7+, PHP 7.4+).
2. **WooCommerce → Settings → Affiliates**: program name, share, groups, thresholds, payout rules, panel slugs, content kit.
3. Create a page with `[ssa_apply]` and select it as the application page.
4. Optional: adjust My Account icons/templates in your theme (`woocommerce/splitshare-affiliates/account/*.php`).

If you use a page cache, exclude requests containing `?ref=` (the plugin sends no-cache headers, but some caches ignore them).

## Rules at a glance (defaults)

| Rule | Default |
|---|---|
| Share | 15% (groups can override) |
| Default split | 10% commission + 5% discount |
| Minimum basket | 750 |
| Cap per order | 10,000 |
| Hold period | 21 days after completion |
| Payout | 15th of each month, minimum 500, carry-over below |
| Link validity | 15 days, last click wins |
| Code | 1 use per customer, individual use, rotated every 90 days (7-day grace) |
| Returning customer | ½ commission |

All of these are settings.

## Developer notes

- `SSA_Calculator` is pure PHP with unit tests (`composer install && composer test`) — the document's sample baskets are the fixtures.
- Hooks: `ssa_application_received`, `ssa_partner_approved`, `ssa_partner_rejected`, `ssa_split_changed`, `ssa_code_rotated`, `ssa_payout_created`, `ssa_payout_paid`, `ssa_daily_done`; filters `ssa_commission_context`, `ssa_email_paragraphs`.
- Tables: `ssa_partners`, `ssa_commissions`, `ssa_payouts`, `ssa_clicks`. Uninstall removes them only if `SSA_REMOVE_DATA` is `true` in `wp-config.php`.
- WP-Cron: `ssa_daily` (approvals, tiers, rotation) and `ssa_monthly` (payouts, runs on the payout day).

## Lezzet Ortakları (TR)

Bu eklenti Marketten Gelse'nin **Lezzet Ortakları** satış ortaklığı programı için yazıldı: her satışta %15 pay ayrılır, ortak bu payı komisyon ve takipçi indirimi arasında panelinden böler; 750 ₺ eşik, 10.000 ₺ tavan, 21 gün onay, ayın 15'i hakediş (min. 500 ₺), 15 gün link geçerliliği, toptan/kahve grubunda %8 pay. Türkçe çeviri ve varsayılan ayarlar bu programı birebir uygular.

## License

GPL v2 or later — see [LICENSE](LICENSE).
