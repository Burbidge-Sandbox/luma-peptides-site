# Luma storefront — UX rebuild spec

Source: a teardown of ten consumer-health sites selling to women 45–60
(Midi, Alloy, Bonafide, Noom, Calibrate, Ritual, OneSkin, NOVOS, Tally Health),
filtered so only claim-free structure survives. Read `../CLAUDE.md` first —
its guardrails outrank anything here.

The audience insight stays true; the expression changes. She is researching,
skeptical, and has been disappointed before. What persuades her here is not a
promise — it is evidence of rigour: testing, documentation, process, and a
price she can see without giving up her email.

---

## 0. Design direction

Current tokens (`css/styles.css` `:root`) are cream `#F7F2EC` + terracotta
`#B4432C` + Cormorant/Inter. That combination is the default "AI wellness
brand" look and reads soft, not analytical. Move the ground, keep one accent.

- **Ground:** cool paper `#F4F5F3` / near-white `#FCFCFB`, plus a true dark
  surface `#121416` for the testing and process sections. Dark = instrument
  panel; it is the cheapest credibility signal on the page (Tally Health).
- **Accent:** keep one saturated colour, used almost entirely on CTAs and
  nothing else. Terracotta is fine if it is the brand; drop its usage to <5%
  of the page.
- **Type:** keep a serif or condensed display for headings, but pair it with a
  monospace utility face (IBM Plex Mono or similar) for lot numbers, purity
  figures, spec labels and eyebrows. Data set in mono is the whole visual
  argument. `font-variant-numeric: tabular-nums` wherever figures stack.
- **Restraint:** OneSkin is the model. One CTA style, no badge wall, no
  ticker, no popup. Decoration reads as compensation.

## 1. Homepage running order

Rebuild `index.html` to this sequence. Each item names what it replaces.

| # | Section | Content | Notes |
|---|---|---|---|
| 01 | Announcement bar | One shipping or documentation fact, or the starter-kit price | No urgency framed around results |
| 02 | Hero | Lab or product photography, oversized display type, neutral identity line. Two CTAs: filled **Shop compounds**, outlined **See the testing** | Dual CTA from NOVOS: buyer and skeptic in one row |
| 03 | Fact strip | Four items, ~four words each: Third-party tested · COA per lot · Cold-shipped · Ships from Utah in 2 days | Alloy's trust bar, filled with operational facts |
| 04 | Testing preview | One figure (e.g. assay purity) as a tile with a link into the real document | Calibrate's evidence tile; figure must be analytical |
| 05 | Catalogue | Compound cards: name, purity, net content, price-from, format. Sorted by compound | Never by goal or outcome |
| 06 | Process strip | Received → sampled → tested → stored → shipped, 5 steps, photography of the real operation | This replaces the social-proof block entirely |
| 07 | Documentation | Links to COA library, storage & handling, RUO terms | Ritual's "Our Standards" as a first-class destination |
| 08 | Footer | RUO statement, terms, contact | Plain language, not fine print |

Remove from the current homepage: anything resembling benefit copy,
lifestyle imagery of people, "best sellers" framed by popularity in a use
context, and any section whose only job is reassurance-by-vibe.

## 2. The testing page — the flagship

`verify.html` is currently a lot-lookup utility. Promote it to the most
designed page on the site and link it from the hero.

- Lot lookup by number, with a real result card: compound, lot, testing lab
  (Freedom Diagnostics), method, assay purity %, identity confirmed, net
  content, vial photo, test date, and the PDF itself.
- A browsable COA library below the lookup — every lot, newest first, as a
  dense table. Mono figures, tabular numerals, sortable by column.
- A short plain-language explanation of what each test measures and what it
  does not tell you. Honesty here is the conversion mechanism.
- Dark ground. This is the instrument-panel page.

## 3. Product page (`product.html`)

Structure, borrowed from the Ritual PDP, with the content column swapped from
benefits to specifications:

1. Title = compound name + quantity. No descriptor that implies effect.
2. **Specification block above the price** — compound, CAS, purity, net
   content, format, lot, storage (−20 °C desiccated), test date. Mono,
   checkmarked rows, one line of detail each.
3. Price ladder: subscription price anchored against list, with the one-time
   price shown on the same screen and visibly the worse deal.
4. Verification seals inside the image carousel, not the footer.
5. **RUO statement inside the buy box**, above the add-to-cart button.
6. Below the fold: link to this lot's COA, storage & handling, shipping.

No reviews section unless it is scoped to fulfilment and packaging only.

## 4. Components to build or fix

- `.fact-strip` — four-item band, stacks 2×2 then 1× at mobile.
- `.spec-table` — label/value rows, mono values, tabular numerals, used on
  PDP, verify results and COA library.
- `.evidence-tile` — one figure, one caption, one link to the source document.
- `.cta-pair` — filled + outlined buttons in a row, wraps at mobile.
- `.process-steps` — five numbered steps; numbering is real sequence, so the
  numerals are legitimate here.
- `.ruo-notice` — the RUO statement styled as a component, reusable in buy
  box, checkout, and footer.

## 5. Acceptance checklist

Run this against every page before calling it done. Any "no" blocks the page.

- [ ] No sentence claims or implies an effect in a person.
- [ ] No photograph shows a person in a use, symptom, or outcome context.
- [ ] No dosing, protocol, stack, or calculator anywhere, including FAQ.
- [ ] No testimonial, rating, or review about effects; no clinician endorsement.
- [ ] Nav, filters, collection names and URLs reference compounds and formats,
      not goals.
- [ ] `<title>`, meta description and image alt text pass the same four checks.
- [ ] RUO statement present in the buy box; acknowledgement required at checkout.
- [ ] Price visible before any email capture.
- [ ] Works at 375px, keyboard navigable, visible focus states, honours
      `prefers-reduced-motion`.
- [ ] Opens correctly from `file://` with no console errors.

## 6. Suggested build order

One page per session, verified against §5 before moving on:

1. Tokens and components (`css/styles.css`, §0 and §4) — no page changes yet.
2. `index.html` to the §1 running order.
3. `verify.html` to the §2 flagship.
4. `product.html` to the §3 structure.
5. `shop.html` — re-sort by compound, strip goal-based filters.
6. Sweep `faq.html`, `about.html`, `terms.html`, `js/products.js` copy and all
   meta tags against §5.
