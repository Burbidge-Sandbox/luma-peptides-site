# Luma storefront — WooCommerce migration spec

Written 2026-09-14. Read `../CLAUDE.md` first; its compliance guardrails
outrank everything here. This document replaces the "Going live for real"
section of `README.md` and supersedes the static-site build order in
`UX-SPEC.md` §6. The design intent in `UX-SPEC.md` §0–§5 still applies.

## 0. Decision and why

The storefront moves from static HTML + Google Apps Script to **WooCommerce
on self-hosted WordPress**. Every operational feature Zack listed — inventory,
order tracking, customer accounts, order notifications, email/SMS updates,
owner alerts — is stock WooCommerce plus a short list of plugins. Rebuilding
those on Cloudflare Workers is months of bespoke code for a store that needs
nothing bespoke.

Rejected: **Shopify** (Shopify Payments runs on Stripe; both treat research
peptides as prohibited and terminate stores without warning). **Headless
(Swell / Supabase)** is viable — it is what aurumpeptidelabs.com runs — but has
a thinner ecosystem for high-risk gateways, shipping and lot tracking, so more
glue code. WooCommerce has the broadest processor support in this category and
is what competitors run.

Also retired with this move: the Apps Script "backend". Its shared secret sits
in client-side `site.js` and doubles as the order-lookup key, so anyone can read
any order. Do not port that pattern.

## 1. Hosting (already provisioned)

| Item | Value |
|---|---|
| Host | Cloudways Flexible, project "Luma Peptides" |
| Server | `luma-prod` — DigitalOcean 2 GB Premium, San Francisco, IP `64.23.217.248` |
| Stack | Lightning (Nginx). **No `.htaccess`** — redirects go in Cloudways → Web Rules or in PHP |
| Database | MariaDB 10.11 |
| Application | `luma-store`, Cloudways app id `6671900` |
| Temp URL | `https://woocommerce-1671014-6671900.cloudwaysapps.com` |
| Admin | `…/wp-admin/` — credentials in Cloudways → Access Details, never in the repo |
| Git deploy | SSH key generated (Cloudways → Deployment via GIT). Public key must be added as a **deploy key** on the GitHub repo |

`lumaresearchco.com` still points at the Cloudflare Pages static site. Nothing
here touches DNS until §9.

2 GB is for the build phase. Cloudways scales vertically in one click; go to
4 GB before launch marketing.

## 2. Repository layout

The repo becomes a **wp-content overlay**, not a full WordPress checkout.
WordPress core and third-party plugins are managed by Cloudways/WP admin and
are not committed.

**Revised 2026-09-14 (step 1 done).** The static site stays at the repo root
for now: Cloudflare Pages builds `lumaresearchco.com` from this repo's root,
so moving files to `legacy/` would take the live site down on the next push.
The move happens at cutover (§9), after the Pages project is retired.

```
luma-peptides-site/
├── CLAUDE.md                  ← guardrails + stack rules
├── docs/UX-SPEC.md, docs/WOO-MIGRATION.md
├── wp-content/
│   ├── themes/luma/           ← custom theme (§3)
│   └── plugins/
│       ├── luma-core/         ← custom plugin (§4)
│       └── luma-bootstrap/    ← one-file shim, installed by hand once (below)
├── tools/                     ← deploy notes, seed script
└── (static site files at root — frozen; moves to legacy/ at cutover)
```

**Deploy path and the bootstrap shim.** Cloudways git deploy clones into
`public_html/wp-content/luma-src/` (configured). There is no SSH from the
coding environment, so instead of symlinks a tiny plugin, `luma-bootstrap`,
is uploaded once through WP admin. It calls `register_theme_directory()` on
`luma-src/wp-content/themes` and `require`s `luma-core` from `luma-src`. After
that, a deploy is: push to `main`, click **Pull** in Cloudways. The static
site also lands in `luma-src/` and is web-reachable at
`/wp-content/luma-src/index.html` on the temp URL; add a Web Rule denying
`/wp-content/luma-src/*.html` before cutover, or move to `legacy/` then.

Local development: **Local (localwp.com)** or `wp-env`, with the same
bootstrap plugin pointing at a checkout of the repo.

## 3. Theme — `wp-content/themes/luma`

A lean **classic theme** (PHP templates, not a block/FSE theme). Reason: the
compliance rules need every pixel to come from templates the agent controls;
the block editor's visual freedom is a liability here. No page builder, no
parent theme from a marketplace, no Storefront child theme.

Port map from the static site:

| Static file | Theme file | Data source |
|---|---|---|
| `css/styles.css` | `style.css` + `assets/css/` | tokens carried over verbatim |
| `js/site.js` header/footer | `header.php`, `footer.php`, `inc/nav.php` | WP menus (Compounds, Testing, Documentation, Contact) |
| `index.html` | `front-page.php` | §1 running order of UX-SPEC |
| `shop.html` | `archive-product.php` | Woo product loop, sorted by title; category = compound family |
| `product.html` | `single-product.php` + `woocommerce/single-product/*.php` overrides | spec block above price, quantity-break ladder, `.ruo-notice` in the buy box |
| `verify.html` | `page-testing.php` (template "Testing") | lot lookup + COA library from luma-core (§4.2) |
| `cart.html`, `checkout.html` | Woo block/shortcode pages with template overrides for layout only | — |
| `confirmation.html` | `woocommerce/checkout/thankyou.php` | shows order number + status link |
| `status.html` | retired — replaced by Woo "Track order" + My Account | — |
| `account.html` | `woocommerce/myaccount/*.php` overrides | Woo accounts |
| `faq/about/terms/privacy/shipping-returns/contact` | pages in WP, content in `inc/content/*.php` **not** the block editor | copy stays in git |
| `404.html` | `404.php` | — |

Rules:
- Theme CSS uses the `:root` tokens; page-level CSS adds tokens, never raw hex.
- All Woo template overrides live under `woocommerce/` in the theme with a
  one-line comment naming the Woo version they were copied from.
- Remove/dequeue everything Woo adds that the guardrails forbid: reviews tab
  (`woocommerce_product_tabs` filter), related/upsell/cross-sell loops, "sale"
  badges framed as urgency, star ratings.
- `woocommerce_product_tabs`: keep Description (spec language only) and add
  a "Documentation" tab (COA link, storage & handling, RUO terms).

**Design direction to resolve before theme work starts.** Commit `ad82f9c`
removed the V2 (dark ground / Archivo / Plex Mono) build and kept the original
cream/terracotta design. UX-SPEC §0 still describes V2 as the target. Pick one
before porting tokens; do not port both.

## 4. Custom plugin — `wp-content/plugins/luma-core`

Everything specific to Luma that is not presentation. One plugin, namespaced
`Luma\Core`, activated on every environment. Modules:

### 4.1 RUO acknowledgement gate
- Checkout: required checkbox `luma_ruo_ack` rendered via
  `woocommerce_review_order_before_submit`; validated server-side on
  `woocommerce_checkout_process` (reject if missing); stored as order meta
  `_luma_ruo_ack` = ISO timestamp + terms version; shown on the admin order
  screen and in the customer's order email.
- Works with both classic and block checkout (register a checkout block
  field as well as the classic hook).
- Independent of payment method. Never tie it to the gateway.
- Site entry gate (UX-SPEC §7.1) stays client-side in the theme; the checkout
  checkbox is the one that is enforced.

### 4.2 Lots and COAs
- Custom post type `luma_lot`, non-public, REST-enabled read-only.
  Fields: lot number (`LP-YYMM-CODE`, see `Lot Code Key - INTERNAL.md`),
  linked product/variation, testing lab, test date, method, assay purity,
  identity result, net content, endotoxin, status, expiry, COA PDF
  (media attachment), vial photo.
- Product/variation meta `_luma_current_lot` → the lot shipping now. Admin
  UI: a lot selector on the product edit screen; a "Lots" column on the
  products list.
- Shortcode / template tag `luma_lot_lookup()` for the Testing page: input →
  result card (fields above + PDF link). Lookups are by exact lot number, no
  enumeration endpoint, rate-limited (10/min/IP via transient).
- COA library: paginated table of all lots newest first, sortable, mono
  figures.
- Order line items record the lot shipped (`_luma_lot` line-item meta) when
  the order is marked completed — this is the traceability record.
- Migration: `tools/seed.sh` imports `LUMA_LOTS` from `legacy/js/products.js`
  (4 lots) and the folder `Lab Testing/` on Zack's Mac holds the PDFs.

### 4.3 Catalogue rules (enforced, not just documented)
- Disable reviews site-wide (`comments_open` → false for products; remove
  the Reviews tab; hide the ratings widget).
- Remove related/upsell/cross-sell output.
- Product categories are compound families only. Seed: Metabolic, Tissue,
  Dermal, Longevity → **rename before launch**; "longevity" and "dermal" are
  goal-adjacent. Prefer "Growth-hormone axis", "Copper peptides", or plain
  alphabetical with no categories at all. Decide in the sweep (§7 step 8).
- No subscription products. If the WooCommerce Subscriptions plugin is ever
  installed, this module deactivates itself and logs an admin notice.
- Quantity breaks (1 / 3 / 5 vials) via native per-quantity pricing rules in
  this plugin (`woocommerce_product_get_price` on cart quantity), rendered as
  the price ladder from UX-SPEC §3. No third-party "dynamic pricing" plugin —
  they bring bundle/BOGO UI that reads as promotion.

### 4.4 Operations
- **Inventory:** stock managed at variation level (Woo native). Low-stock
  threshold per variation; `woocommerce_low_stock` and `woocommerce_no_stock`
  hooked to owner alerts (§4.5). Lot quantity tracked on `luma_lot`
  (`_qty_received`, `_qty_remaining`, decremented on order completion).
- **Order status flow:** Pending payment → Processing → Completed, plus a
  custom **"Awaiting payment (Venmo)"** status only while Venmo remains the
  rail; removed when the processor lands.
- **Shipping:** Woo flat rates from `legacy/js/site.js` CONFIG (Standard $8,
  Express $24, free over $150) until ShipStation is connected. Tracking number
  on the order → customer email + My Account (Woo Shipment Tracking or
  ShipStation's own).
- **Tax:** Woo native tax tables seeded from CONFIG `taxRates` for the build;
  replace with TaxJar/Avalara before launch — the static table is approximate.

### 4.5 Notifications and alerts
- Customer email: Woo's transactional emails, restyled with the theme tokens
  via `woocommerce_email_styles`. Sent through **Postmark** (or SendGrid) via
  their SMTP plugin — never PHP `mail()`. Copy uses spec vocabulary only.
- Customer SMS: **Twilio** via a small module in luma-core (opt-in checkbox
  at checkout, stored as `_luma_sms_optin`; events: paid, shipped w/ tracking).
- Owner alerts: new order, payment received, low stock, failed payment,
  order flagged by fraud rules → email to `info@lumaresearchco.com` and
  optional SMS. One function `Luma\Core\Alerts::send()` so channels can change
  without touching hooks.
- Marketing email: **not in scope** for the migration. Check the provider's
  AUP first (Klaviyo restricts this category).

### 4.6 Analytics and capture
- GA4 + Meta pixel IDs in plugin settings, output in `wp_head` only when set
  (same behaviour as CONFIG today). First-touch/UTM capture from `site.js`
  becomes order meta (`_luma_first_utm_*`, `_luma_first_landing`) set on
  checkout from a first-party cookie — this replaces the "events" sheet.

## 5. Plugins (install list — keep it this short)

| Need | Plugin | Notes |
|---|---|---|
| Commerce | WooCommerce | pre-installed by Cloudways |
| Payments | *TBD* — high-risk gateway plugin (AllayPay via Authorize.net/NMI, PeptiPay, or Corepay) + **Coinbase Commerce** as second rail | **Blocked on processor underwriting. Apply now; 2–6 weeks.** Until then: Venmo custom gateway in luma-core, clearly labelled, see CLAUDE.md payments rule |
| Shipping | ShipStation Integration for WooCommerce | labels, tracking sync |
| Tracking display | Woo Shipment Tracking (or ShipStation's) | one, not both |
| Email delivery | Postmark for WordPress (or SendGrid) | transactional only |
| SMTP fallback | none — if Postmark is down, emails queue in Action Scheduler | |
| Security | Cloudways Bot Protection (server-level) + Two-Factor (WP core plugin) | no Wordfence-style bloat on Lightning |
| Backups | Cloudways daily backups (enable, 1-week retention) | plus `wp db export` in `tools/` before deploys |
| Caching | Cloudways Breeze (pre-installed) | exclude cart/checkout/my-account/testing lookup |

Explicitly **not** installed: page builders (Elementor, Divi, WPBakery),
review plugins, "related products" plugins, dynamic-pricing/bundle plugins,
WooCommerce Subscriptions, popup/exit-intent plugins, SEO plugins that
auto-generate meta from product copy (write meta by hand in luma-core).

## 6. Data migration

From `legacy/js/products.js` → WooCommerce, via `tools/seed.sh` (WP-CLI):

- Each `LUMA_PRODUCTS` entry → variable product (variants → variations by
  strength) or simple product when no variants. SKU from `variants[].sku` or
  generated `LUMA-<ID>-<MG>`. Price = `once`. Stock status from `stock`.
- `tagline` → short description; `description` → description; `inside` and
  `specs` → product meta rendered by the spec block (not into the description
  body, so they cannot be edited into claims by accident).
- `category` → product_cat (see §4.3 rename note).
- `subscribe`, `badge`, `bestSeller`-type fields → **dropped**.
- Product images from `assets/products/` → media library, alt text set from
  the compound name only.
- `LUMA_LOTS` → `luma_lot` posts; attach PDFs from `Lab Testing/<lot>/`.
- Promo codes (`WELCOME10`, `GLOW15`, `FREESHIP`) → **do not migrate** as-is.
  "GLOW" is outcome-adjacent. Recreate as neutral codes only if promos are
  wanted at all.

Orders in the Google Sheet are not migrated; export the sheet to CSV and
archive it in the Luma Peptides folder. The Apps Script web app is disabled
the day the new checkout goes live.

## 7. Build order (one step per session, §8 checklist before moving on)

1. **Repo scaffold** ✅ 2026-09-14 — theme + plugin skeletons, bootstrap shim,
   `tools/`. Deploy path on Cloudways. Luma theme renders on the temp URL.
2. **luma-core: catalogue rules + lots** (§4.2, §4.3). Seed script. Products
   and lots visible in admin.
3. **Theme: tokens, header, footer, front page** (resolve §3 design decision
   first).
4. **Theme: product archive + single product** with spec block, quantity
   ladder, RUO notice in buy box.
5. **Testing page** — lot lookup + COA library. Flagship; give it its own
   session.
6. **Checkout** — RUO gate (§4.1), Venmo interim gateway, order emails via
   Postmark, thank-you page, My Account overrides.
7. **Operations** — inventory thresholds, alerts, Twilio, ShipStation, tax.
8. **Content sweep** — FAQ, about, terms, privacy, shipping/returns, contact,
   all meta/alt text, category names. Run §8 on every URL.
9. **Cutover** (§9).

Payment gateway integration happens whenever underwriting clears; it slots in
at step 6 or after cutover without blocking anything else.

## 8. Acceptance checklist (WordPress additions to UX-SPEC §5)

Everything in UX-SPEC §5, plus:

- [ ] Reviews, ratings, related/upsell/cross-sell are absent on every product
      URL — check the HTML, not just the screen.
- [ ] No plugin has injected copy, badges, popups or widgets the guardrails
      forbid (view source of front page, archive, product, checkout).
- [ ] `_luma_ruo_ack` is present on a test order placed through checkout and
      a test order without the checkbox is rejected server-side.
- [ ] Lot lookup returns the seeded lots and nothing for a fabricated number;
      rate limit triggers.
- [ ] Order emails render with theme styling and spec vocabulary; from-address
      is `info@lumaresearchco.com` via Postmark, SPF/DKIM passing.
- [ ] Stock decrements on order; low-stock alert fires at threshold.
- [ ] Breeze cache excludes cart, checkout, my-account, testing lookup.
- [ ] Admin login has 2FA; `wp-admin` is not reachable over plain HTTP.
- [ ] Lighthouse mobile ≥ 90 performance on front page and a product page.

## 9. Cutover plan

1. Point `lumaresearchco.com` at Cloudways in **Domain Management** (add the
   domain as primary), install Let's Encrypt via **SSL Certificate**.
2. In Cloudflare DNS: change the `A`/`CNAME` for the apex and `www` to
   `64.23.217.248` (proxied on). Set SSL/TLS to **Full (strict)**.
3. Keep the Cloudflare Pages project alive but unreferenced for two weeks
   (instant rollback = flip DNS back).
4. Redirect map: old static URLs (`/product.html?id=bpc-157`, `/verify.html`,
   `/shop.html`) → new permalinks via Cloudways Web Rules (Nginx), since there
   is no `.htaccess`.
5. Disable the Apps Script deployment. Archive the orders sheet.
6. Update `sitemap.xml`/`robots.txt` (WP generates them).
7. Re-run §8 on the live domain; confirm `CONFIG.promises` facts (ships in 1
   business day etc.) are still true on the day of publish.

## 10. Open decisions (Zack)

- Payment processor: which one(s) to apply to this week.
- Design direction: original (current `main`) or V2 (UX-SPEC §0).
- Category naming (§4.3).
- Whether promo codes exist at all at launch.
- Tax service before launch (TaxJar vs Avalara vs Woo native tables).
