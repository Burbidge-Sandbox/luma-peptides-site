# Order & data capture

The storefront is static (GitHub Pages), so it can't store anything itself. Instead every meaningful action on the site is sent as a JSON event to one URL, `CONFIG.captureEndpoint` in `js/site.js` (and `compliant/js/site.js`). Until that URL is set, events queue in the visitor's browser and are sent later.

## What is captured
| Event | When | Data |
|---|---|---|
| `page_view` | every page load | page, referrer, UTM params, first-touch attribution, device |
| `gate_accept` | visitor passes the age gate | |
| `add_to_cart` | product added | product, vial size, qty, price |
| `checkout_start` | checkout page opened | cart contents |
| `order_placed` | order submitted | full order: items, totals, name, org, email, phone, address, shipping, Venmo note |
| `waitlist_join` | out-of-stock waitlist | email, product |
| `newsletter_signup` | footer/newsletter form | email |
| `contact_form` | contact page | name, email, org, topic, message |
| `lot_lookup` | Verify a Lot search | lot code, found |

Every event also carries: a persistent `visitor` id, a `session` id, the A/B `variant`, current UTM parameters, first-touch attribution (landing page, referrer, UTMs from the visitor's very first visit), device/language/screen, and a snapshot of the cart. That's the data a retargeting audience or an email flow needs later.

## Set up the Google Sheet backend (recommended, free, ~5 minutes)
Follow the numbered steps at the top of `Code.gs`. Result: a Google Sheet with three tabs — `events` (everything), `orders` (one row per order, with shipping address), `contacts` (waitlist, newsletter, contact form) — and an email to info@lumaresearchco.com for every order.

## Alternative: Formspree
Create a form at formspree.io, set `captureEndpoint` to its URL. You get an email per event and a dashboard, but no per-order sheet. Free tier is 50 submissions/month.

## Privacy
Update the Privacy Policy if you add analytics or ad pixels. The capture layer stores only what the visitor typed plus standard web attribution; no card data is ever collected.
