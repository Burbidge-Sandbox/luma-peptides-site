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

**Revised 2026-09-13.** The first pass moved the ground about one percent of
lightness, which is imperceptible. These are the values to implement; the
change has to be felt, not measured.

- **Dark is the primary ground**, not an accent surface. The entry gate, hero,
  testing and process sections all sit on it. Light paper is for catalogue,
  reading and forms. This single decision is what separates a supplier from a
  wellness brand.
  - `--dark: #0E1113` · `--dark-2: #171B1E` · `--line-dark: #2A2F33`
  - `--on-dark: #ECEDE9` · `--on-dark-muted: #99A2A6`
- **Light ground moves properly off cream** — cool, slightly green-grey paper:
  - `--ground: #E8E9E4` · `--surface: #F7F8F5` · `--line: #D3D6D0`
  - `--ink: #111416` · `--ink-2: #3A4043` · `--muted: #656D71`
- **Accent:** one colour, CTAs only, under 5% coverage.
  - `--accent: #C04A2E` (deepened terracotta, holds the brand tie)
  - `--accent-on-dark: #E2694A` (use on dark grounds; the base is too dark to
    read there)
  - Status only: `--ok: #2F6B4F` · `--warn: #9A6B12`. Never decorative.
- **Type — this is what will actually be noticed:**
  - Display: **Archivo** 700/800, tight tracking (`-0.02em`), used large and
    for section heads. Replaces Cormorant Garamond in all UI.
  - Body: **Newsreader** 400 for reading text.
  - Mono: **IBM Plex Mono** 400/500 for every lot number, purity figure, spec
    label, eyebrow and price. `font-variant-numeric: tabular-nums` everywhere
    digits stack.
  - **Exception:** the `luma peptides co.` wordmark keeps its current serif.
    It is the brand mark; everything around it changes.
- **Restraint:** OneSkin is the model. One CTA style, no badge wall, no
  ticker, no popup. Decoration reads as compensation.

## 1. Homepage running order

Rebuild `index.html` to this sequence. Each item names what it replaces.

| # | Section | Content | Notes |
|---|---|---|---|
| 01 | Announcement bar | One shipping or documentation fact, or the starter-kit price | No urgency framed around results |
| 02 | Hero | Lab or product photography, oversized display type, neutral identity line. Two CTAs: filled **Shop compounds**, outlined **See the testing** | Dual CTA from NOVOS: buyer and skeptic in one row |
| 03 | Fact strip | Four items, ~four words each. Use only facts that are currently true — as of 2026-09-13: Third-party tested · COA per lot · Lot-numbered vials · Ships in 1 business day | Alloy's trust bar, filled with operational facts. No cold-pack language: that was removed and must not come back. Re-check these four against reality before every publish |
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
3. Price ladder — **quantity breaks, not subscription.** Per-vial price at 1,
   3 and 5 vials, shown as one table with the per-unit figure falling as
   quantity rises, so the larger pack is visibly the better deal. This keeps
   the anchoring mechanic while staying clear of rule 10: a recurring
   auto-shipment implies a personal consumption cadence, a quantity discount
   is ordinary lab-supply pricing. No subscription, no "subscribe & save",
   no delivery-frequency selector anywhere on the site.
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

---

## 7. Step 2 — the first screen

Scope: `V2/index.html` plus the entry gate, nothing below the fact strip.
This is the whole step. Do not rebuild the rest of the homepage yet.

Rationale: the entry gate — not the hero — is what every first-time visitor
actually reads. It currently renders as a white cookie-banner box dimming the
page behind it, with "Leave" weighted almost equally to the primary action.
Every design decision below it is seen second.

### 7.1 Entry gate

Treat it as the most deliberate screen on the site. It is the positioning
statement: this is a supplier of research material, and it screens who enters.

- Full-bleed `--dark` ground, opaque. No dimmed-page-behind treatment — the
  page underneath should not be visible or scrollable while it is open.
- Centred column, max 560px. Mono eyebrow (`BEFORE YOU CONTINUE`), display
  headline, RUO statement set in body serif at reading size — a real document,
  not fine print. Keep the existing copy; it is good.
- The acknowledgement checkbox is required and unchecked by default. The
  primary button is disabled until it is checked, and its label states what is
  being agreed to.
- **Button hierarchy:** one filled primary, full width. "Leave" becomes a
  plain text link beneath it, not a second button of equal weight.
- **Remember the acknowledgement** in `localStorage` for 30 days
  (`luma.ruo.ack`, storing an ISO timestamp and the terms version). Re-showing
  it every visit trains people to click through without reading, which defeats
  the point of having a record. Wrap reads and writes in try/catch — private
  windows throw.
- Focus trapped inside the gate; Escape does **not** dismiss it; background
  scroll locked; visible focus rings; honours `prefers-reduced-motion`.
- No imagery of people. No product beauty shot. The gate is text and ground.

### 7.2 Hero

- Continues on `--dark`, so entering the site reads as one continuous move
  rather than a modal lifting off a different page.
- Headline in Archivo at display scale, naming what the catalogue is in
  neutral terms. It must not describe an effect, a benefit, or a "perspective"
  — replace the current line. A plain identity statement is stronger here.
- Mono sub-line carrying a concrete fact about the catalogue (compound count,
  purity standard, lab).
- `.cta-pair`: filled **Shop compounds** + outlined **See the testing**.
- Right side: lab or vial photography on the dark ground. One image, no
  carousel, no autoplay video.

### 7.3 Fact strip

- Sits directly beneath the hero on `--dark-2`, the seam between dark and
  paper.
- Four items, current true facts only: Third-party tested · COA per lot ·
  Lot-numbered vials · Ships in 1 business day.
- Mono labels, 4 → 2×2 → 1 column. No icons unless they carry meaning.

### 7.4 Done means

- The §5 checklist passes on the gate and the hero.
- The gate is not bypassable by scrolling, tabbing, or Escape, and does not
  re-appear for 30 days after acknowledgement.
- Side-by-side against the live site, the difference is obvious at a glance —
  if it is not, the step failed regardless of what changed in the CSS.
- Screenshots of both states (gate, and hero after entry) at 1440px and 375px.
