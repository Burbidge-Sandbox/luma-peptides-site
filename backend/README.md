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
| `checkout_email` / `checkout_contact` | email or phone typed at checkout, before submit | for abandoned-checkout follow-up |

Every event also carries: a persistent `visitor` id, a `session` id, the A/B `variant`, current UTM parameters, first-touch attribution (landing page, referrer, UTMs from the visitor's very first visit), device/language/screen, and a snapshot of the cart. That's the data a retargeting audience or an email flow needs later.

## Set up the Google Sheet backend (~5 minutes)
The script is already pointed at the Luma orders sheet. Follow the numbered SETUP steps at the top of `Code.gs`. Result: tabs `events`, `orders`, `contacts`; an email to info@lumaresearchco.com for every order and contact form; an automatic branded confirmation email (with Venmo link and QR code) to the customer; and, when you set a row's status to `paid` or `shipped`, an automatic email to the customer.

The site sends a shared key with every event (`captureKey` in `js/site.js`, `SECRET` in `Code.gs`). Keep them identical.

## Alternative: Formspree
Create a form at formspree.io, set `captureEndpoint` to its URL. You get an email per event and a dashboard, but no per-order sheet. Free tier is 50 submissions/month.

## Privacy
Update the Privacy Policy if you add analytics or ad pixels. The capture layer stores only what the visitor typed plus standard web attribution; no card data is ever collected.

## Handling resources in the order email
The customer order email includes an optional "Laboratory handling resources" section: a third-party reconstitution video and links to alcohol prep pads and syringes, marked as unaffiliated. These appear only in the email, never on the website. Edit or blank the `VIDEO_URL`, `PADS_URL`, `SYRINGE_URL` values at the top of `Code.gs` to change or remove them.
