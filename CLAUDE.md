# Luma Peptides Co. — storefront working rules

Read `docs/UX-SPEC.md` before changing any page. This file is the standing
guardrail; the spec is the build brief.

## What this site is

A direct-to-consumer storefront for **research-use-only (RUO)** peptide
reference material. Static HTML/CSS/JS, no build step, no framework, no
package manager. Every page must work when opened directly from disk.

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

## Stack rules

- Plain HTML/CSS/JS only. No React, no Tailwind, no CDN frameworks, no npm.
- Design tokens live in `css/styles.css` `:root`. Add tokens, do not hardcode
  colors in page CSS.
- Catalog, copy and lots live in `js/products.js`; site config, header, footer
  and nav in `js/site.js`. Change data there, not in markup.
- Keep every page keyboard accessible, reduced-motion aware, and working at
  ~375px width.
- **Payments (updated 2026-09-13):** Venmo is live; the card checkout is still
  a demo. Never handle raw card numbers, and never present the demo card flow
  as a working payment path. Whatever the rail, the RUO acknowledgement
  checkbox must gate order submission — it is not tied to the payment method.
- Venmo is a stopgap, not the plan of record. Treat a payments migration to a
  high-risk processor as pending work, and do not build checkout logic that
  assumes Venmo is permanent.
