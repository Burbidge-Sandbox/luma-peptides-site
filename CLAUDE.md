# Luma Peptides Co. — storefront working rules

Read `docs/UX-SPEC.md` (design intent) and `docs/WOO-MIGRATION.md` (build
plan) before changing any page. This file is the standing
guardrail; the spec is the build brief.

## What this site is

A direct-to-consumer storefront for **research-use-only (RUO)** peptide
reference material. **As of 2026-09-14 it is being rebuilt on WooCommerce**
(self-hosted WordPress on Cloudways) — read `docs/WOO-MIGRATION.md` for the
plan and build order. The original static site lives on in `legacy/` until
cutover and is frozen except for the compliance sweeps below.

## Compliance guardrails — non-negotiable

Intended use is read from the *whole presentation*: headlines, photography,
nav labels, alt text, review content, quiz logic, meta descriptions, ad copy.
A disclaimer does not cure a page built to imply human use. Treat every item
below as a hard constraint, not a style preference.

Never add, and remove on sight:

1. Any statement or implication that the product treats, prevents, mitigates,
   or affects a disease, condition, symptom, or body composition in people.
2. Dosing, administration, reconstitution volumes, cycles, stacks, protocols,
   titration schedules, or calculators.
3. Testimonials, reviews, ratings, or user stories about effects in people.
   Reviews about shipping, packaging, or documentation are fine.
4. Clinician, doctor, scientist, or influencer endorsement — real or implied,
   including stock photos of people in lab coats presented as advisors.
5. Photography of people in a use, symptom, or transformation context; scales,
   tape measures, body silhouettes, before/after grids, syringes posed for use.
6. Eligibility or qualification framing ("see if you qualify", "find your
   plan", intake forms, quizzes that recommend a product to a person).
7. Outcome statistics, timelines, percentages, or "results" of any kind about
   people. Analytical figures only (purity, identity, net content).
8. Comparisons to prescription drugs, brand-name GLP-1s, or "alternative to"
   framing; SEO/meta text that targets those terms.
9. Navigation, collections, filters, or URLs organised by goal or outcome
   (weight, energy, anti-aging). Organise by compound, format, quantity.
10. Bundles, names, or imagery that only make sense as a human regimen —
    including subscriptions, auto-ship, "subscribe & save", and any
    delivery-frequency selector. Quantity-break pricing (1 / 3 / 5 vials) is
    the approved alternative and carries no regimen implication.
11. Any operational claim that is not currently true. Shipping speed, cold
    handling, stock and turnaround are facts with an expiry date — verify
    before publishing, and delete rather than soften. Cold-pack language is
    permanently out.

Always present:

- RUO statement in the buy box on every product page — not only the footer.
- The RUO acknowledgement checkbox at checkout, required to submit.
- Clear "not for human or veterinary use, not a drug or supplement" language
  in terms, FAQ, and product pages, in plain sentences.

Allowed vocabulary: compound name, CAS, purity %, identity confirmed, net
content, lot number, test date, testing lab, storage conditions, handling,
shipping, quantity, format, price. That is the persuasive material — use it
well rather than reaching past it.

If a requested change conflicts with the above, do not implement it. Say which
rule it hits and offer the compliant version of the same idea.

None of this is legal advice; it is the working standard for the build. An FDA
promotional-law attorney reviews before launch.

## Stack rules (WooCommerce — updated 2026-09-14)

- **Repo is a wp-content overlay:** `wp-content/themes/luma` (classic PHP
  theme) and `wp-content/plugins/luma-core` (all Luma-specific logic). WordPress
  core and third-party plugins are never committed. See WOO-MIGRATION §2.
- **No page builders, no marketplace themes, no theme frameworks.** No
  Elementor, Divi, WPBakery, Astra/Flatsome/Storefront child themes. Templates
  are hand-written PHP the agent controls end to end.
- **Plugins:** only those listed in WOO-MIGRATION §5. Never install a plugin to
  solve a problem luma-core can solve in under ~100 lines. Never edit a
  third-party plugin's files — hook it or replace it.
- **Anything a plugin or the admin can inject is in scope for the guardrails.**
  Reviews, ratings, related/upsell/cross-sell, popups, "customers also bought",
  sale badges, and auto-generated SEO meta count as content. luma-core disables
  the Woo defaults that violate the rules; view source after every plugin
  install or update and remove what slipped in.
- **No subscriptions, ever.** If WooCommerce Subscriptions or any auto-ship
  plugin appears, luma-core must refuse to run. Quantity breaks (1/3/5) are the
  approved mechanic.
- **Design tokens** live in the theme's `:root` (ported from `legacy/css/styles.css`).
  Add tokens, do not hardcode colors. Template CSS references tokens only.
- **Copy lives in git,** not the block editor: product spec fields are post meta
  rendered by templates; static pages (FAQ, terms, about…) render from
  `inc/content/*.php`. This keeps every sentence reviewable in a diff.
- **Server is Nginx (Cloudways Lightning).** There is no `.htaccess`; redirects
  and rules go in Cloudways → Web Rules or PHP.
- **Secrets never enter the repo:** no API keys, SMTP passwords, gateway
  credentials, or the WP salts. They live in Cloudways/WP settings or
  `wp-config.php` on the server. The Apps Script pattern (shared secret in
  client JS) is retired and must not be recreated.
- **Local first:** build and verify against a local WordPress (Local /
  `wp-env`) with the theme and plugin symlinked, run the acceptance checklists
  (UX-SPEC §5 + WOO-MIGRATION §8), then deploy. Use WP-CLI for seeding products,
  lots, users and test orders rather than clicking through admin.
- Keep every page keyboard accessible, reduced-motion aware, and working at
  ~375px width.
- **Payments:** Venmo (manual, "Awaiting payment" status) is a stopgap shipped
  as a clearly labelled custom gateway in luma-core. The plan of record is a
  high-risk card processor plus a second rail (crypto or eCheck); underwriting
  is in progress. Never handle raw card numbers, never present a demo card
  flow as working, and never write checkout logic that assumes a specific
  gateway. Whatever the rail, the RUO acknowledgement checkbox gates order
  submission server-side and is stored on the order.
- **Operational claims** (ship-in-1-day, stock, turnaround) are data with an
  expiry: pull them from a single settings screen in luma-core, and verify the
  values before every deploy.
