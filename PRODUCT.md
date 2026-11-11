# Product

## Register

brand

## Users

**Primary: IGLs, coaches, and managers running competitive CS2 teams.** Global audience across amateur, semi-pro, and academy tiers, often juggling a day job with two-to-four practice sessions a week. They open CsStrats between practices: building the stratbook before a server boots, reviewing the demo after the team logs off, sharing a callout overlay on Discord at midnight. They already use FACEIT, HLTV, and Leetify daily, so they recognize generic design instantly and trust it less.

**Secondary: serious individual players** who came to analyze their own demos before promotion games. They're a single click away from upgrading the team plan when their squad asks.

The job is the same for both: turn the chaos of a 30-round match into something a team can actually act on by Tuesday's practice. Find the tactic, study the opponent, write the call, ship the plan.

## Product Purpose

CsStrats is the working surface where a CS2 team prepares for matches and learns from them. Three tools, one room:

1. **Tactics library.** A curated, weekly-updated catalog of Tier 1/2 pro tactics by map. Browse, copy, adapt.
2. **Stratbook + Board.** Team-owned collections of tactics with a collaborative 2D board for diagramming utility, executes, mid-round reads.
3. **Demoviewer.** Upload a .dem file, get a parsed match with 2D/3D playback, round-by-round annotations, and team callouts attached to the geometry.

Success is measured the same way the user measures their team: less time gathering, more time playing the right rounds. Stratbook built faster. Demo reviewed without three people having to sit through every kill. A new tactic moved from "saw on YouTube" to "ran in scrim" the same week.

The landing page exists to convince a busy IGL in under thirty seconds that this is the tool they already wished existed, then get them into a free account before they switch tabs.

## Brand Personality

**Composed. Exacting. Competitive.**

The voice of a coach who has watched a lot of demos and is not impressed easily. Short sentences. Verbs over adjectives. Numbers when numbers exist. No exclamation points. No "unlock your potential." No "elite players." The audience already considers themselves serious; the brand respects that by not over-explaining.

The visual posture: a warm light page (the daylight bench) where the dark product artifacts (board diagrams, parsed rounds, the live demoviewer) sit framed like printed match prep. The product itself stays dark for the midnight session; the marketing page presents it in daylight. The energy comes from data density and typographic confidence, not from glow effects or neon.

If the brand were a person: a Nordic analyst who used to play Tier 2, now coaches, talks half as much as they listen, and lays out a stratbook in a single afternoon that the team uses for six months.

## Anti-references

The landing must not look like any of the following. Each is named because the category-reflex pull is strong and every one of them has flooded the audience's eye.

- **refrag.gg.** Explicit. Do not borrow patterns. Do not echo their hierarchy, their hero, their feature presentation, their palette logic.
- **Generic gaming SaaS.** Neon cyan/magenta on near-black, hexagonal mesh patterns, glow text headlines, "next-gen" copy, Discord-clone aesthetics. The first reflex when the brief says "gaming." Reject it.
- **Esports org websites** (Astralis, NaVi, G2, Faze, etc.). Pure black with a single neon accent, sharp angled cards, jersey-style metal textures, roster-page energy. CsStrats is a working tool, not a roster.
- **Discord / Twitch.** Indigo or purple, bubbly rounded corners, friendly mascot energy. Wrong audience tone.
- **B2B SaaS Linear-clone.** Indigo gradient hero, glassmorphic 3-up feature grid, hero-metric template, gradient text on every heading. The default greenfield-launch shape.
- **Editorial-typographic lane.** Display serif (Fraunces/Cormorant) with italic headlines, ruled separators, broadsheet grid, lowercase tracked metadata. CsStrats is not a magazine.

## Design Principles

1. **Show the work, not the wrapper.** Real tactic diagrams, real demo timelines, real round breakdowns. The visual material of the brand is the actual artifact a user makes inside the product. Decorative geometry is not a substitute.

2. **Pro players don't shout.** The audience is already serious. Match their register: calm voice, no superlatives, no urgency manufactured from nothing. A premium tool earns trust through restraint, not energy.

3. **Team-led, individual-aware.** Lead the page with the team workflow because that's what the product is and what the pricing reflects. Keep enough depth that a Leetify-trained solo player who landed on the page recognizes themselves and stays.

4. **The page IS the proof.** A landing page that looks generic is the first piece of evidence the tool is generic. The IGL evaluates the design as part of evaluating the product. Sloppy here cancels everything else.

5. **Data is the imagery.** Numbers, heatmaps, round timelines, win/loss columns, tactic diagrams. Use them as the brand's visual material the way a food brand uses photography. The product's actual outputs are the most distinctive thing the brand has.

## Accessibility & Inclusion

- **WCAG 2.1 AA minimum** on all marketing surfaces. Contrast ratios verified against the dark palette before shipping.
- **Reduced motion respected.** All scroll-triggered reveals and entrance choreography gated by `prefers-reduced-motion: reduce`. Where motion carries meaning (data animations), provide a static equivalent.
- **Keyboard reachable, focus visible.** Every interactive element receives a clear focus ring that survives a dark background (not a default browser outline lost on `#0a0a0a`).
- **Heading hierarchy is real.** One `h1`, descending without skipping, used for structure not styling.
- **Text scaling tolerated.** Layout survives 200% zoom and `text-size-adjust` without horizontal scroll on mobile.
- **Global English copy.** Audience is worldwide; English is the working language. Tone is calm, technical, and reads naturally across English-speaking regions. Avoid US-marketing idioms ("level up", "crush the competition", "no-brainer").
- **Late-night context.** The user often opens this at midnight after a five-hour session. Avoid pure white surfaces, hard luminance jumps, and motion that demands attention without purpose.
