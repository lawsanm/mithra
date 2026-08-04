# Color Palette — "Soft Trust Blue & Amber"

Color system for a community-based, money-free lending and borrowing platform built on trust, credibility, and warm human connection.

## Design intent

- **Feeling to create:** safe, stable, established, credible — but warm and human, not cold.
- **The one rule that governs every choice:** this must read as *warm-trust*, **not** as a bank, fintech, or government service. Blue supplies the trust shortcut for free; the warm neutrals and the amber accent are what keep it from cooling into an institutional/corporate look.
- **Style:** flat and clean. Solid fills, generous warm-white space, thin borders, rounded corners. Gradients are optional and used sparingly (see below), never on small UI or text.

---

## 1. Core brand colors

| Role | Name | Hex | Usage |
|------|------|-----|-------|
| Primary | Soft denim blue | `#2D6CA3` | Primary buttons, links, active nav, key interactive elements. White text passes AA. |
| Secondary | Deep slate blue | `#274B6D` | Footer and dark sections only. Carries the most "corporate weight" — use sparingly. |
| Accent | Honey amber | `#E9A23B` | The warmth carrier: featured tags, highlights, illustration accents, decorative icons. Always dark text on it, never white. |
| Background | Warm off-white | `#F7F5F1` | Page background. **Must stay warm** — do not substitute a cool gray; the warmth is what counters the banking read. |
| Surface / Card | White | `#FFFFFF` | Cards, panels, modals. Lifts gently off the warm background. |
| Text (primary) | Charcoal | `#1C2733` | Body and headings. AAA contrast on background and surface. |
| Text (muted) | Slate gray | `#5E6A74` | Secondary text, metadata, captions. |
| Border / divider | Warm neutral | `#E5E3DD` | Card borders, dividers, input outlines. Thin (0.5px–1px). |

---

## 2. Semantic / status colors

Deployed only as small elements: pills, badges, icons, inline text, alert components. Never as large fills. The **base** value is for icons and solid fills; the **tint** is the pill/alert background; the **on-tint text** is the text or icon color sitting on that tint (and the right value to use for status text on white).

| State | Base | Tint (background) | On-tint text |
|-------|------|-------------------|--------------|
| Success | `#2F9E44` | `#E4F3EA` | `#1E6B3E` |
| Warning | `#DD8A14` | `#FBEED4` | `#7A4D06` |
| Error | `#D64545` | `#FBEAEA` | `#9A2A2A` |
| Info | `#2D6CA3` | `#EAF1F8` | `#1B4D7A` |

> **Note:** Warning is a deeper honey-orange, deliberately separated from the honey-amber brand accent so a caution still reads as a *signal* and not as decoration. Info reuses the primary denim blue.

---

## 3. Extended color scales (for states & fills)

Functional light→dark ramps. These are what hover, active, disabled, selected, and tinted-fill states should pull from. No decorative gradients required — these scales do the work.

### Primary (denim blue)

| Step | Hex | Typical use |
|------|-----|-------------|
| 50 | `#ECF2F8` | Selected/hover row backgrounds, info tint |
| 100 | `#D2E1EF` | Light fills, chips |
| 200 | `#AFC9E1` | Disabled button background |
| 300 | `#82A9CF` | Borders on tinted areas |
| 400 | `#5388BD` | Secondary emphasis |
| 500 | `#2D6CA3` | **Base — primary** |
| 600 | `#28608F` | Button hover |
| 700 | `#274B6D` | Button active/pressed (= Secondary) |
| 800 | `#1F3A55` | Dark section background |
| 900 | `#16293C` | Deepest contrast text on light tints |

### Accent (honey amber)

| Step | Hex | Typical use |
|------|-----|-------------|
| Tint | `#FCF1DC` | Featured/badge background |
| Light | `#F8E1B6` | Soft fills, highlight bands |
| Base | `#E9A23B` | **Base — accent** (icons, tags, illustration) |
| Dark | `#D08C28` | Accent hover |
| Deep | `#A66E1C` | Strong accent on light backgrounds |
| Text | `#7A4D06` | Text/icon color on amber tint |

### Neutrals (warm)

| Step | Hex | Typical use |
|------|-----|-------------|
| Surface | `#FFFFFF` | Cards, surfaces |
| Warm 50 | `#F7F5F1` | **Page background** |
| Warm 100 | `#EFEDE7` | Alternate section background, subtle fill |
| Warm 200 | `#E5E3DD` | **Border / divider** |
| Warm 300 | `#D2D0C9` | Stronger border, disabled surface |
| Gray 400 | `#A6ABB0` | Disabled text and icons |
| Gray 500 | `#5E6A74` | **Muted text** |
| Gray 700 | `#34404B` | Secondary headings |
| Gray 900 | `#1C2733` | **Primary text** |

---

## 4. Interactive state mapping

| Element | State | Color |
|---------|-------|-------|
| Primary button | Default | bg `#2D6CA3`, text `#FFFFFF` |
| Primary button | Hover | bg `#28608F` |
| Primary button | Active/pressed | bg `#274B6D` |
| Primary button | Disabled | bg `#AFC9E1`, text `#FFFFFF` (or bg `#E5E3DD`, text `#A6ABB0`) |
| Secondary / ghost button | Default | transparent bg, border `#2D6CA3`, text `#2D6CA3` |
| Secondary / ghost button | Hover | bg `#ECF2F8` |
| Link | Default | `#2D6CA3` |
| Link | Hover | `#28608F` (underline) |
| Focus ring | All focusable elements | `#2D6CA3` at ~40% opacity, ~3px ring |
| Input border | Default / focus | default `#E5E3DD`, focus `#2D6CA3` |

---

## 5. Gradients (optional — use sparingly)

The brand is flat-first. Gradients are not required anywhere. If a gentle gradient is wanted for a large area only (hero, section banner), keep it low-contrast and barely perceptible. Never apply gradients to buttons, small UI, or text.

| Gradient | Direction | From → To | Use |
|----------|-----------|-----------|-----|
| Hero (subtle) | Top-left → bottom-right (diagonal) | `#2D6CA3` → `#274B6D` | Large hero background behind white/amber content only |
| Warm wash (very subtle) | Top → bottom (vertical) | `#F7F5F1` → `#FCF1DC` | Soft section transition; should be nearly invisible |

Avoid: amber-heavy gradients (drift toward gold/luxury), multi-stop or vivid gradients (read as trendy/consumer-tech), and any gradient that reduces text contrast.

---

## 6. Accessibility rules

- **Body and heading text** (`#1C2733`) on background (`#F7F5F1`) and surface (`#FFFFFF`): AAA.
- **Muted text** (`#5E6A74`) on background/surface: meets AA for normal text.
- **Primary button** (`#2D6CA3` + white text): meets AA (~4.6:1).
- **Secondary** (`#274B6D` + white text): AAA.
- **Amber accent** (`#E9A23B`): use dark text (`#1C2733`) only — white text fails contrast. Prefer amber for non-text accents, icons, and large text.
- **Status colors:** use the base value for icons/fills; use the on-tint text value for any text (on a tint pill or on white).
- **Targets:** 4.5:1 minimum for normal text, 3:1 for large text and UI components/icons.
- Never rely on color alone for status — pair every status color with an icon or label.

---

## 7. Usage discipline (keeps it warm-trust, not banking)

- Keep the background **warm** (`#F7F5F1`). A cool gray is the single fastest way to make this look like a bank.
- Lead with the lighter denim (`#2D6CA3`) and lots of warm-white space. Confine the deep slate (`#274B6D`) to the footer and dark sections.
- Let the amber **show up** as visible warmth (featured tags, highlights, illustration) — don't hide it. But cap it at roughly 10–15% of any screen and never use it as a large solid fill (that tips toward gold/luxury).
- Use rounded corners and real photography of items and the people lending them. Color sets the baseline; faces, verification badges, and successful-lend counts do the heavy trust-lifting.
- Stay flat. Treat gradients as optional and subtle.

---

## Quick reference — all hex values

Primary `#2D6CA3` · Secondary `#274B6D` · Accent `#E9A23B` · Background `#F7F5F1` · Surface `#FFFFFF` · Text `#1C2733` · Muted text `#5E6A74` · Border `#E5E3DD`

Success `#2F9E44` · Warning `#DD8A14` · Error `#D64545` · Info `#2D6CA3`

Primary scale: `#ECF2F8` `#D2E1EF` `#AFC9E1` `#82A9CF` `#5388BD` `#2D6CA3` `#28608F` `#274B6D` `#1F3A55` `#16293C`

Amber scale: `#FCF1DC` `#F8E1B6` `#E9A23B` `#D08C28` `#A66E1C` `#7A4D06`

Neutrals: `#FFFFFF` `#F7F5F1` `#EFEDE7` `#E5E3DD` `#D2D0C9` `#A6ABB0` `#5E6A74` `#34404B` `#1C2733`
