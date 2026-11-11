---
name: CsStrats
description: Tactical CS2 demo analysis and stratbook workbench for competitive teams.
colors:
  saffron-strike: "oklch(52% 0.15 53)"
  forge-rust: "oklch(45% 0.14 27)"
  cold-steel: "oklch(48% 0.045 240)"
  concrete: "oklch(95.5% 0.005 53)"
  slate: "oklch(98.5% 0.002 53)"
  smoke: "oklch(91% 0.006 53)"
  ash: "oklch(82% 0.008 53)"
  bone: "oklch(45% 0.012 53)"
  chalk: "oklch(22% 0.01 53)"
  round-won: "oklch(72% 0.12 145)"
  round-lost: "oklch(58% 0.18 22)"
typography:
  display:
    fontFamily: "'Schibsted Grotesk', 'Helvetica Neue', Arial, sans-serif"
    fontSize: "clamp(3rem, 7vw, 6rem)"
    fontWeight: 700
    lineHeight: 1.02
    letterSpacing: "-0.025em"
  headline:
    fontFamily: "'Schibsted Grotesk', 'Helvetica Neue', Arial, sans-serif"
    fontSize: "clamp(2rem, 4vw, 3.5rem)"
    fontWeight: 600
    lineHeight: 1.08
    letterSpacing: "-0.02em"
  title:
    fontFamily: "'Schibsted Grotesk', 'Helvetica Neue', Arial, sans-serif"
    fontSize: "clamp(1.375rem, 2vw, 1.75rem)"
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: "-0.01em"
  body:
    fontFamily: "'Schibsted Grotesk', 'Helvetica Neue', Arial, sans-serif"
    fontSize: "1.0625rem"
    fontWeight: 400
    lineHeight: 1.6
    letterSpacing: "normal"
  label:
    fontFamily: "'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace"
    fontSize: "0.75rem"
    fontWeight: 500
    lineHeight: 1.1
    letterSpacing: "0.08em"
  data:
    fontFamily: "'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace"
    fontSize: "0.9375rem"
    fontWeight: 500
    lineHeight: 1.3
    letterSpacing: "normal"
rounded:
  none: "0"
  xs: "2px"
  sm: "4px"
  md: "6px"
  lg: "10px"
  full: "9999px"
spacing:
  "1": "4px"
  "2": "8px"
  "3": "12px"
  "4": "16px"
  "5": "24px"
  "6": "32px"
  "7": "48px"
  "8": "72px"
  "9": "96px"
  "10": "128px"
components:
  button-primary:
    backgroundColor: "{colors.saffron-strike}"
    textColor: "{colors.concrete}"
    typography: "{typography.label}"
    rounded: "{rounded.sm}"
    padding: "14px 24px"
  button-primary-hover:
    backgroundColor: "{colors.forge-rust}"
    textColor: "{colors.chalk}"
  button-secondary:
    backgroundColor: "{colors.slate}"
    textColor: "{colors.chalk}"
    typography: "{typography.label}"
    rounded: "{rounded.sm}"
    padding: "14px 24px"
  button-secondary-hover:
    backgroundColor: "{colors.smoke}"
    textColor: "{colors.chalk}"
  button-ghost:
    backgroundColor: "transparent"
    textColor: "{colors.bone}"
    typography: "{typography.label}"
    rounded: "{rounded.sm}"
    padding: "14px 16px"
  button-ghost-hover:
    textColor: "{colors.chalk}"
    backgroundColor: "{colors.slate}"
  input-default:
    backgroundColor: "{colors.slate}"
    textColor: "{colors.chalk}"
    typography: "{typography.body}"
    rounded: "{rounded.sm}"
    padding: "12px 16px"
  input-focus:
    backgroundColor: "{colors.slate}"
    textColor: "{colors.chalk}"
  panel:
    backgroundColor: "{colors.slate}"
    textColor: "{colors.chalk}"
    rounded: "{rounded.md}"
    padding: "32px"
  chip-label:
    backgroundColor: "transparent"
    textColor: "{colors.bone}"
    typography: "{typography.label}"
    rounded: "{rounded.xs}"
    padding: "6px 10px"
  nav-link:
    backgroundColor: "transparent"
    textColor: "{colors.bone}"
    typography: "{typography.label}"
    rounded: "{rounded.xs}"
    padding: "8px 12px"
  nav-link-active:
    textColor: "{colors.chalk}"
    backgroundColor: "transparent"
---

# Design System: CsStrats

## 1. Overview

**Creative North Star: "The Analyst's Bench."**

CsStrats is the workbench where a CS2 coach lays out the evidence of a match and decides what to do about it. The visual system carries that same posture: methodical, precise, low light, no decoration that isn't doing a job. A page should read like the moment a serious person sits down to work, not like a product announcement.

The register is brand (the landing page is a first impression that converts), but the brand draws its texture from the product. Tactic diagrams, demo timelines, round breakdowns, callout overlays: these are the imagery. Decorative geometry, glow halos, hexagonal mesh, gradient-text headlines all read as the cliche the audience already actively distrusts.

Dark by scene, not by reflex. The user opens this at midnight on a 27 inch monitor after a session, energized but composed, and the surface should not assault them with luminance. The energy comes from data density and typographic confidence, not from neon.

**Key Characteristics:**
- Dark surface tinted warm toward the brand hue; never pure black.
- One committed accent (saffron-strike orange), one quiet counter (cold-steel blue), no gradient text anywhere.
- Sharp small radii (4-6px). Borders are visible 1px. No glass, no blur as decoration.
- Display type at scale, body type capped at 65-75ch. Mono carries data, never decoration.
- Asymmetric layouts. Vary spacing for rhythm. Centered stacks with icon-title-subtitle cards are out of register.
- Motion is purposeful (scroll-reveal sections, data choreography), reduced-motion respected without compromise.

## 2. Colors: The Saffron-On-Concrete Palette

A committed orange leading on tinted-warm near-black, with a single muted steel-blue counter for cool contrast. Greens and reds appear only where the product itself uses them (round outcomes, demo states). Every neutral is tinted toward the brand hue at chroma 0.005-0.012 so the surface reads warm even at the deepest steps.

### Primary

- **Saffron Strike** (`oklch(73% 0.17 53)`, approx #ff8c00): The brand carries here. CTAs, active states, the wordmark accent, key data emphasis. Used confidently but never as decoration; saffron means action or weight, never just "color."
- **Forge Rust** (`oklch(53% 0.15 27)`, approx #bd3d3d): The deepening of saffron under pressure. Hover state on primary CTAs, the second stop in any saffron-to-rust transition, the color of a missed round in match data.

### Secondary

- **Cold Steel** (`oklch(60% 0.045 240)`, approx #7c8da3): The CT-side counter. Used sparingly: callout labels in tactic diagrams, secondary chip text, the occasional line in a chart. Never neon, never cyan, never glowing. The job is to interrupt the warmth, not compete with it.

### Neutral (warm-tinted dark ramp)

- **Concrete** (`oklch(15% 0.005 53)`, approx #0e0b07): Page background. Not black; black is for products that don't trust themselves.
- **Slate** (`oklch(20% 0.006 53)`, approx #18130e): Elevated surfaces. Panels, cards, the working areas inside Concrete.
- **Smoke** (`oklch(27% 0.008 53)`, approx #251f18): Mid-tone surface for hover states on secondary buttons, inactive tab backgrounds, second-layer panels.
- **Ash** (`oklch(38% 0.009 53)`, approx #3a3128): Borders and dividers. Visible at 1px on Concrete and Slate without shouting.
- **Bone** (`oklch(68% 0.012 53)`, approx #aaa094): Mid-tier text. Body copy in paragraphs, labels at small sizes, the workhorse text weight.
- **Chalk** (`oklch(95% 0.005 53)`, approx #f1ede7): Top-tier text. Headlines, primary numbers, the line you want a reader to track first. Never #fff.

### Tertiary (data-state only)

- **Round Won** (`oklch(72% 0.12 145)`, approx #6db77a): Calm sage green. Appears only in round outcomes, win-state indicators, positive metrics. Not for buttons, not for decoration.
- **Round Lost** (`oklch(58% 0.18 22)`, approx #c0533a): A separate red from Forge Rust, used only for loss-state indicators and destructive confirmations. Distinct hue so a loss never reads as a brand accent.

### Named Rules

**The One Voice Rule.** Saffron-strike covers 8-15% of any given screen. Above that and the brand reads aggressive; below that and it reads timid. The CTA is saffron. The active nav link is saffron. The wordmark accent is saffron. Almost nothing else is.

**The No-Neon Rule.** No color in the system has chroma above 0.18 except saffron-strike itself. Anything brighter reads as gaming-template neon. The audience has seen that exact reflex on every esports site they have already discarded.

**The Warm-Dark Rule.** Every neutral, including Concrete, carries chroma 0.005-0.012 toward hue 53. Pure-neutral grays read clinical and unowned; warm-tinted darks read like CsStrats and nothing else.

## 3. Typography

**Display + Body Font:** Schibsted Grotesk (with Helvetica Neue, Arial fallback).
**Label / Data Font:** JetBrains Mono (with SF Mono, Menlo, ui-monospace fallback).

**Character:** Schibsted is a Norwegian newsroom typeface, designed for media in the same region the audience lives in. Sharp terminals, confident weight contrast from 400 to 900, no editorial-italic affectation. It carries authority without performing it. JetBrains Mono handles every number, score, timestamp, and label: data should look like data, not like decoration trying to look like data.

### Hierarchy

- **Display** (Schibsted 700, `clamp(3rem, 7vw, 6rem)`, line-height 1.02, tracking -0.025em): Single-screen hero statements. One per page maximum.
- **Headline** (Schibsted 600, `clamp(2rem, 4vw, 3.5rem)`, line-height 1.08, tracking -0.02em): Section openers. The first thing a reader sees when they enter a new fold.
- **Title** (Schibsted 600, `clamp(1.375rem, 2vw, 1.75rem)`, line-height 1.2, tracking -0.01em): Sub-sections, card headings, feature names.
- **Body** (Schibsted 400, `1.0625rem` / 17px, line-height 1.6, tracking normal): Paragraphs. Capped at 65-75ch; line length over 80ch is a bug.
- **Label** (JetBrains Mono 500, `0.75rem` / 12px, line-height 1.1, tracking 0.08em, uppercase): Kickers, section eyebrow labels, chip text, small UI labels. Mono carries the analytics identity here; never on prose.
- **Data** (JetBrains Mono 500, `0.9375rem` / 15px, line-height 1.3, tracking normal): Numbers in metrics, scores, timestamps, technical readouts. Tabular figures (`font-variant-numeric: tabular-nums`) when stacked in columns.

### Named Rules

**The Display-Once Rule.** Display tier appears exactly once per page, in the hero. Every subsequent section opens with Headline. If two sections claim Display, the page reads desperate.

**The Mono-For-Data Rule.** Monospace is reserved for numbers and short technical labels. Body copy never. "Aesthetic mono" body is the lazy gaming-template signal and is banned.

**The Reading-Width Rule.** Body copy is capped at 65-75ch. Beyond that, the line break is doing more work than the typography is.

**The Caps Rule.** All-caps is reserved for Label tier. Headlines, body, and Display are mixed-case. Track all-caps labels at 0.08em minimum; otherwise the rhythm collapses.

## 4. Elevation

The system is flat by default. Surfaces stack through tone (Concrete, Slate, Smoke), not through shadows. The depth a reader perceives comes from typography hierarchy and tonal contrast, not from elevation cues. Shadow appears only in two cases: (1) a dropdown or popover that genuinely floats above the document flow, (2) the saffron CTA's ambient glow on hover, which earns its weight by being rare.

### Shadow Vocabulary

- **Floating Surface** (`box-shadow: 0 1px 0 oklch(40% 0.01 53 / 0.4), 0 12px 40px oklch(0% 0 0 / 0.5)`): Dropdowns, popovers, command menus. The two-stop shadow gives a soft cast that survives the dark background.
- **CTA Ambient Glow** (`box-shadow: 0 0 32px oklch(73% 0.17 53 / 0.22)`): Applied only to `button-primary:hover` and only when motion is allowed. Never on rest, never on secondary surfaces.

### Named Rules

**The Flat-At-Rest Rule.** A surface at rest has no shadow. Shadow is a response to state (floating, hovered, focused), not a baseline treatment. If every card has the same shadow, none of them have shadow.

**The Tonal-Layering Rule.** Depth is signalled by stepping from Concrete to Slate to Smoke, not by adding box-shadows. A panel sitting on the page background should be Slate or Smoke, with an Ash 1px border if separation needs sharpening.

## 5. Components

Every component is precise and reserved. Sharp small radii. Confident 1px borders that you can actually see on dark. No glass blur as decoration. Generous internal padding so the surface breathes between dense data and headline weight.

### Buttons

- **Shape:** Sharp small radius (4px) on rectangles. Pills (full radius) reserved for chip-only contexts; never on primary CTAs.
- **Primary:** Saffron-strike background, Concrete text, Label typography, 14px y / 24px x padding. The text is dark on saffron, not white, because Chalk-on-saffron softens the contrast and the surface feels less confident.
- **Primary hover:** Background shifts to Forge Rust, text shifts to Chalk, CTA Ambient Glow fades in over 240ms (cubic-bezier(.22,1,.36,1)).
- **Secondary:** Slate background, Chalk text. Hover: background steps to Smoke. No saffron, no border.
- **Ghost:** Transparent background, Bone text. Hover: background Slate, text Chalk. Used for tertiary actions and dense toolbars.
- **Focus state (all):** 2px Saffron-strike outline, 2px offset, never replacing the border but supplementing it. Survives keyboard navigation on every surface tone.

### Panels and Cards

- **Corner Style:** 6px (md). Sharper than the SaaS-default 12-16px; softer than the terminal-default 0px.
- **Background:** Slate by default. Hover-state on interactive panels: Smoke.
- **Border:** Ash, 1px, always present. Tonal contrast alone isn't enough on dark; the border earns the separation.
- **Shadow:** None at rest. Tonal layering only.
- **Internal Padding:** 32px (spacing 6) is the default. Tight panels use 24px (spacing 5). Hero-scale panels use 48px (spacing 7).

### Chips and Eyebrow Labels

- **Style:** Transparent background or transparent-on-Slate, Bone text, Label typography (mono, uppercase, tracked 0.08em).
- **Border:** None for inline kickers; 1px Ash for filter chips that need an outline.
- **Selected state:** Background Slate, text Chalk. Saffron appears only on the "active filter" state, never on an inactive resting chip.

### Inputs

- **Style:** Slate background, Ash 1px border, 4px radius, 12px y / 16px x padding, Body typography.
- **Focus:** Border shifts to Saffron-strike 1px, plus the 2px Saffron focus outline at 2px offset.
- **Error:** Border shifts to Round-Lost, helper text Round-Lost. No background change.
- **Disabled:** Background steps to Concrete, text steps to Ash. No saffron.

### Navigation

- **Style:** Top fixed bar, transparent at top of page, becomes `slate / backdrop-blur-md / border-bottom 1px Ash` on scroll past 24px.
- **Links:** Label typography (mono, uppercase, tracked). Bone at rest, Chalk on hover, Chalk on active route.
- **Active state:** Chalk text, plus a 2px Saffron underline sitting flush with the bottom of the link, never an underline by default. No saffron background, no pill.
- **Mobile:** Hamburger reveals a full-viewport overlay. Same Slate surface, same Label type, links spaced at spacing-5 (24px). Slide-and-fade entrance, never a bounce.

### Section Eyebrow + Headline Pattern

A signature pattern: an uppercase mono Label kicker (e.g. `01 · DEMOVIEWER`) sits exactly `spacing-3` (12px) above the Headline. The number is the section index (one-padded), the dot is a literal `·` (middle dot, U+00B7), the word is the section name in uppercase. The Headline below is mixed-case, weight 600, never repeating the kicker's words.

## 6. Do's and Don'ts

Concrete guardrails. Every anti-reference in PRODUCT.md shows up here as a Don't with the same language.

### Do:

- **Do** use Saffron Strike on 8-15% of any screen. Above that or below that, recalibrate.
- **Do** tint every neutral toward hue 53 at chroma 0.005-0.012. The warm-dark is the brand.
- **Do** lead each section with a mono Label kicker numbered one-padded (`01 · TACTICS`, `02 · DEMOVIEWER`).
- **Do** use Display tier exactly once per page. The hero owns it.
- **Do** vary spacing for rhythm: tight groupings inside cards, generous separations between sections. spacing-9 to spacing-10 between major folds.
- **Do** show real product imagery: tactic diagrams, demo timelines, callout overlays, parsed match data. Data is the imagery.
- **Do** cap body line length at 65-75ch.
- **Do** respect `prefers-reduced-motion: reduce`. All scroll-triggered reveals and entrance choreography gate behind the query, with static equivalents.
- **Do** treat `font-variant-numeric: tabular-nums` as default on every column of stacked numbers.
- **Do** use OKLCH for every color declaration. Hex is a fallback approximation in this document; the OKLCH values are canonical.

### Don't:

- **Don't** use `#000` or `#fff`. Concrete and Chalk only.
- **Don't** apply `background-clip: text` to a gradient. Gradient text is banned in this system.
- **Don't** use `border-left` or `border-right` greater than 1px as a colored accent on cards, callouts, or alerts. Side-stripe borders are banned.
- **Don't** wrap content in a glass-blur card by reflex. Backdrop blur appears once (the scrolled nav). Anywhere else is a bug.
- **Don't** ship identical 3-up feature card grids with icon + heading + paragraph. The B2B SaaS Linear-clone shape is explicitly out of register.
- **Don't** ship a hero-metric template (big number + small label + supporting stats + gradient accent). SaaS cliche, banned.
- **Don't** echo refrag.gg in any pattern, hierarchy, or palette logic. Explicit anti-reference.
- **Don't** use neon cyan, neon magenta, hexagonal patterns, or glow-text headlines. Generic gaming SaaS is the first reflex; reject it.
- **Don't** ship Discord-purple, indigo gradients, or rounded-bubbly aesthetics. Wrong audience tone.
- **Don't** ship esports-org-website energy: pure black, sharp angled cards, jersey-style metal textures. CsStrats is a tool, not a roster.
- **Don't** ship the editorial-typographic lane: display serif with italic, ruled separators, broadsheet grid, lowercase tracked metadata. CsStrats is not a magazine.
- **Don't** use Inter, Space Grotesk, DM Sans, Plus Jakarta, or any reflex-reject typeface from the impeccable brand reference.
- **Don't** use US-marketing idioms ("level up", "unlock your potential", "elite players", "crush the competition", "no-brainer"). Global audience, calm voice.
- **Don't** use em dashes in copy. Commas, colons, semicolons, periods, parentheses only.
- **Don't** animate CSS layout properties. Transform and opacity only; ease-out-quart / quint / expo for state, never bounce or elastic.
