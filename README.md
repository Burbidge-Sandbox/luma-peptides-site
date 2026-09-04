# Luma Peptides Co. — Storefront

A fast, dependency-free e-commerce storefront. Plain HTML, CSS and JavaScript — no build step, no framework — so it loads instantly and deploys anywhere static files are hosted.

## Run locally
Open `index.html` in a browser, or serve the folder:

```bash
python3 -m http.server 8080
```

Then visit http://localhost:8080.

## Deploy (pick one)
- **Netlify:** drag the `site` folder onto app.netlify.com/drop. `netlify.toml` is already configured.
- **Vercel:** `npx vercel` inside this folder. `vercel.json` is included.
- **GitHub Pages:** push this folder to a repo and enable Pages on the root.
- **Any host:** upload the folder as-is.

Replace `YOUR-DOMAIN.com` in `robots.txt` and `sitemap.xml` with your real domain.

## Where to edit things
| What | File |
|---|---|
| Products, prices, copy, categories | `js/products.js` |
| Demo lot certificates (Verify page) | `js/products.js` → `LUMA_LOTS` |
| Free-shipping threshold, shipping rates, promo codes, tax | `js/site.js` → `CONFIG` |
| Navigation links, announcement bar, footer | `js/site.js` → `header()` / `footer()` |
| Colors, fonts, spacing | `css/styles.css` → `:root` |
| Homepage sections | `index.html` |

## Features
- Home, shop with category filters and sorting, product pages with Subscribe & Save vs one-time, slide-out cart, full cart page, checkout with validation, order confirmation, lot-verification (COA lookup), how-it-works, about, contact, FAQ, shipping/returns, privacy, terms, 404.
- Cart persists in `localStorage`. Promo codes: `WELCOME10`, `GLOW15`, `FREESHIP`.
- Free-shipping progress bar, sticky order summary, sticky product image, mobile menu, keyboard/screen-reader accessible, reduced-motion aware.

## Going live for real
1. **Payments:** the checkout is a demo. Replace `placeOrder()` in `js/checkout.js` with Stripe Checkout (or move the catalog to Shopify and keep this as the theme). Never handle raw card numbers on your own server.
2. **Lot lookup:** point `js/verify.js` at a real endpoint or a published JSON file of certificates.
3. **Email capture / contact form:** connect to Netlify Forms, Formspree, or Klaviyo.
4. **Analytics:** add your tag in the shared `<head>` (search `gen.py` or add to each page).
5. **Legal review:** confirm the trust claims on the homepage ("Doctor Prescribed", "U.S. Pharmacy") match how the business actually operates before launch.
