---
name: PM One
description: A monochrome, token-driven admin system where hierarchy comes from type and hairlines, never from color.
colors:
  ink: "#000000"
  paper: "#ffffff"
  graphite: "oklch(0.141 0.005 285.823)"
  slate-body: "oklch(0.37 0.013 285.805)"
  slate-quiet: "oklch(0.442 0.017 285.786)"
  surface-quiet: "oklch(0.967 0.001 286.375)"
  hairline: "oklch(0.92 0.004 286.32)"
  night-card: "oklch(0.1744 0.0061 285.74)"
  night-hairline: "oklch(0.274 0.006 286.033)"
  signal-info: "oklch(54.6% 0.245 262.881)"
  signal-success: "oklch(62.7% 0.194 149.214)"
  signal-warning: "oklch(68.1% 0.162 75.834)"
  signal-destructive: "oklch(57.7% 0.245 27.325)"
typography:
  display:
    fontFamily: "MinusOne, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 500
    lineHeight: 1.2
    letterSpacing: "-0.05em"
  title:
    fontFamily: "MinusOne, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 500
    lineHeight: 1.375
    letterSpacing: "-0.05em"
  body:
    fontFamily: "MinusOne, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: "-0.025em"
  label:
    fontFamily: "MinusOne, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 500
    lineHeight: 1
    letterSpacing: "-0.025em"
  control:
    fontFamily: "MinusOne, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 500
    letterSpacing: "-0.025em"
  field:
    fontFamily: "MinusOne, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    letterSpacing: "-0.025em"
rounded:
  sm: "6px"
  md: "8px"
  lg: "10px"
  xl: "14px"
  full: "9999px"
spacing:
  field-stack: "8px"
  form-column-gap: "8px"
  group: "16px"
  section: "24px"
  frame-gap: "32px"
components:
  button-default:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.paper}"
    typography: "{typography.control}"
    rounded: "{rounded.lg}"
    padding: "0 10px"
    height: "32px"
  button-default-hover:
    backgroundColor: "oklch(0 0 0 / 0.9)"
    textColor: "{colors.paper}"
  button-outline:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.graphite}"
    typography: "{typography.control}"
    rounded: "{rounded.lg}"
    padding: "0 10px"
    height: "32px"
  button-outline-hover:
    backgroundColor: "{colors.surface-quiet}"
    textColor: "{colors.graphite}"
  button-destructive:
    backgroundColor: "{colors.signal-destructive}"
    textColor: "{colors.paper}"
    typography: "{typography.control}"
    rounded: "{rounded.lg}"
    padding: "0 10px"
    height: "32px"
  input-field:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.graphite}"
    typography: "{typography.field}"
    rounded: "{rounded.lg}"
    padding: "4px 10px"
    height: "32px"
  card-surface:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.slate-body}"
    typography: "{typography.body}"
    rounded: "{rounded.xl}"
    padding: "16px"
  frame-panel:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.slate-body}"
    rounded: "{rounded.xl}"
    padding: "24px 12px"
  badge-pill:
    backgroundColor: "transparent"
    textColor: "{colors.graphite}"
    typography: "{typography.body}"
    rounded: "{rounded.full}"
    padding: "4px 8px"
---

# Design System: PM One

## Overview

**Creative North Star: "The Ink and Paper Ledger"**

PM One looks like a well-kept ledger: black ink on white paper, ruled by hairlines, with nothing on the page that isn't a record or a way to change one. Structure comes from type size, weight, and thin rules, never from colored panels or decorative blocks. A page can hold a great deal of information and still feel calm, because every element sits on the same flat plane and earns its space by carrying data.

The system is **restrained and exact**. Surfaces are flat, borders are one pixel, motion happens only when state actually changes. Color is a signal reserved for status, not a way to decorate chrome; the primary action is simply the darkest thing on the page. That restraint is what lets 244 screens feel like one product and lets an untrained exhibitor and a staff member who opens the same screen fifty times a day both read it quickly. Light mode is the default and dark mode is fully supported; neither is a stylistic afterthought.

The reference points are Linear and the Vercel dashboard, not Notion or Stripe marketing. This is a tool, and the interface earns trust by being predictable and out of the way, never by being charming.

Everything visual is expressed through tokens, never literal values. The application ships nine runtime style packs (`mono` is the default) plus user-selectable palette, radius, and font, so any hardcoded height, hex, or padding silently opts a component out of the system. **The tokens and the rules below are normative; the concrete values recorded here are `mono` + the `native` palette, the defaults everything is measured against.**

**Key Characteristics:**
- Monochrome chrome, colored signals only
- Hairline borders and flat surfaces instead of shadows
- Tight tracking that tightens further as type grows
- Semibold ceiling; contrast comes from size and color, not weight
- Fixed control sizing on every screen width, with one deliberate exception for typed fields
- Token contract over literal values, because the visual layer is swappable at runtime

## Colors

A zinc-based monochrome ramp carries the entire interface; four semantic hues exist only to report status.

### Primary
- **Ink** (`#000000`): The primary action and the focus ring in light mode. It is the darkest value available, which is exactly why it needs no accent color to read as primary. In dark mode the role inverts to Paper, so "primary" always means maximum contrast against the current background.

### Neutral
- **Paper** (`#ffffff`): Page background, card, and popover surfaces in light mode.
- **Graphite** (`oklch(0.141 0.005 285.823)`): Foreground text in light mode; the page background in dark mode.
- **Slate Body** (`oklch(0.37 0.013 285.805)`): Long-form body copy and card content, one step softer than the foreground so headings still lead.
- **Slate Quiet** (`oklch(0.442 0.017 285.786)`): Secondary text, helper text, captions, and labels sitting beside a value.
- **Surface Quiet** (`oklch(0.967 0.001 286.375)`): Secondary surfaces, hover backgrounds, icon containers, and soft badges.
- **Hairline** (`oklch(0.92 0.004 286.32)`): Every border and input stroke in light mode. The single most-used non-text color in the product.
- **Night Card** (`oklch(0.1744 0.0061 285.74)`): The one literal value on the dark ramp, sitting between two stops so cards lift off the dark background without a shadow.
- **Night Hairline** (`oklch(0.274 0.006 286.033)`): Borders and input strokes in dark mode.

### Tertiary (status signals)
- **Signal Info** (`oklch(54.6% 0.245 262.881)`): Informational badges and neutral states.
- **Signal Success** (`oklch(62.7% 0.194 149.214)`): Confirmed, paid, checked-in, active.
- **Signal Warning** (`oklch(68.1% 0.162 75.834)`): Pending, expiring, partial, near-limit.
- **Signal Destructive** (`oklch(57.7% 0.245 27.325)`): Destructive actions, failures, and validation errors. Soft form is the same hue at 10% over a matching foreground.

### Named Rules

**The Token Contract Rule.** Write `bg-muted`, `text-muted-foreground`, `border-border`. Never a literal palette class (`bg-green-500`, `border-gray-200`), never `bg-white` / `bg-black`, never a raw hex. Literals survive a palette swap and a style swap; the product does not.

**The Signal Scarcity Rule.** Hue means status. Nothing structural (headers, cards, nav, tabs, empty states) is ever colored. If a screen shows color, a record is telling you something about its state.

**The Accent Trap Rule.** `--accent` in this project is the primary ink, not a tint. Class lists copied from external shadcn sources must translate `bg-accent` to `bg-muted`, or the element turns solid black. Do not "fix" the token itself: the `mira`, `sera`, and `lyra` styles use that black highlight block deliberately.

## Typography

**Body Font:** MinusOne (variable, 400-1000), falling back to `ui-sans-serif, system-ui, sans-serif`
**Optional Faces:** ten curated families (Geist, Inter, DM Sans, Manrope, Space Grotesk, Outfit, Geist Mono, JetBrains Mono, Playfair Display, Lora) are user-selectable through Appearance; the system must look correct in all of them, so never design against a single face's metrics.

**Character:** One neutral variable sans doing all the work, set tight and never shouting. Distinction between levels comes from size and color, with weight held on a short leash.

### Hierarchy
- **Display** (500, 1.25rem, 1.2): Page titles only, via the `page-title` utility. Never hand-styled.
- **Title** (500, 1rem, 1.375): Card and frame section titles. Frame titles carry the same size at `font-medium`.
- **Body** (400, 0.875rem, 1.5): Table cells, descriptions, paragraph copy. `text-base` is available for genuinely long reading passages.
- **Label** (500, 0.875rem, 1): Form labels, via `<Label>`. Required markers are generated by CSS from the `required` attribute, never typed by hand.
- **Control** (500, 0.875rem): Buttons, tabs, menu items, selects. Fixed at every width.
- **Field** (400, 1rem, dropping to 0.875rem on mouse devices): Typed inputs, textareas, native selects, OTP slots, command inputs, and the select trigger that sits beside them.
- **Fine print** (400, 0.75rem rising to 0.875rem at `sm`): Helper text under inputs and dense metadata.

### Named Rules

**The Tighter-As-It-Grows Rule.** Everything is `tracking-tight`. At `text-xl` and above, or at `font-semibold`, it becomes `tracking-tighter`. Bigger or heavier means tighter, always. `tracking-wider`, `tracking-widest`, and `uppercase` are not part of this system.

**The Semibold Ceiling Rule.** `font-semibold` is the maximum weight in the product. If something needs more presence, raise its size or its color, never its weight. `font-bold` and `font-extrabold` do not exist here.

**The 16px Touch Rule.** Typed fields are `text-base pointer-fine:text-sm`, not `sm:text-sm`. iOS Safari zooms on focus below 16px, and it does so on iPads and landscape phones that are already past the `sm` breakpoint. `pointer-fine` is the only correct guard. Every other control keeps one fixed size at all widths.

**The Static-Small Rule.** Standalone `text-xs` on static text is a defect on large screens; write `text-xs sm:text-sm`. This does not apply to interactive controls, where `text-xs` on an extra-small button is correct by design.

**The Two-Case Rule.** Page titles, section titles, and card titles are Title Case. Everything else — helper text, descriptions, empty-state copy, toast bodies — is sentence case. Two cases, no third.

## Layout

Pages stack in a single column of sections separated by 24px, inside a container that is `mx-auto px-4` by default and widens in steps to 1600px for data-heavy pages (`container-wider`). Narrow form pages cap at `max-w-xl`; list pages open up to `max-w-4xl` on large screens and `max-w-6xl` above that. Full-height pages use `min-h-screen-offset`, which already subtracts the 3.5rem navbar.

The spacing rhythm is deliberately short: 8px between a label and its input, 16px between fields in a group, 24px between sections on a page, 32px between frames in a long form. Forms are one column by default; when two columns are justified they use `gap-x-2` (8px) and collapse to one column below `sm`. Wider column gaps break the reading of a paired field like first name / last name and are not used.

Breakpoints are `xs` 540px, `sm` 640px, `md` 768px, `lg` 1024px, `xl` 1280px, `2xl` 1500px, `3xl` 1600px. Two of them carry meaning beyond width: `sm` is where controls drop one 4px step to their desktop height, and `md` (768px) is where `ResponsiveDialog` switches from a drawer to a dialog. Padding inside a dialog body must switch at `md:`, never `sm:`, or a drawer gets dialog padding.

Density is high but never cramped: information is compressed by removing chrome and tightening space, not by shrinking type below its floor.

### Named Rules

**The gap-x-2 Rule.** Two-column form grids use `gap-x-2` and `gap-y-6`. Anything wider reads as two unrelated forms.

**The Mobile Step Rule.** Every control is one 4px step taller on touch than on desktop, and the desktop value is the one the style owns. Call sites write `h-(--cn-input-h)` when a button must align with a field; they never write `h-8` or add a `sm:h-*` of their own.

## Elevation & Depth

This system is flat. Depth is built from hairline borders, background steps on the neutral ramp, and spacing, not from shadows. Shadows exist only in three narrow roles, and none of them is decorative: a 1px lift under resting buttons and inputs, a soft card shadow, and a real elevation shadow for genuinely floating layers (tooltip, popover, dialog). Dark mode does not deepen shadows; it separates surfaces by lightness instead, which is why the card token is a literal value between two ramp stops.

### Shadow Vocabulary
- **Resting lift** (`box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05)`): Default buttons and focused inputs. Barely visible on purpose; it reads as a printed edge, not a float.
- **Card** (`box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)`): Small standalone cards that must separate from the page background.
- **Floating layer** (`box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)`): Tooltips, popovers, dialog content. The only shadow allowed to be noticed.

### Named Rules

**The Border-Before-Shadow Rule.** When something needs separation, add a border or change the surface one step on the ramp. Reach for a shadow only when the element genuinely floats above the page. `shadow-2xl` on ordinary components is a defect.

**The No Nested Cards Rule.** A card inside a card inside a panel is how flat systems turn to mush. Sections inside a form use `.frame`; content inside a frame is plain.

## Shapes

Corners are soft but not round: 6px on the smallest chips, 8px on inputs and small buttons, 10px on standard buttons and popover items, 14px on cards, panels, and frames. Radius scales from a single `--radius` root value (0.625rem), so the whole product re-shapes when a user picks a different radius, and two styles (`lyra`, `sera`) deliberately force it to zero.

Full rounding is reserved for avatars, status dots, badges, and small chips. App icons, brand marks, and project logos use the `squircle` utility (`corner-shape: squircle`) rather than a hand-built shape. Ad hoc radii like `rounded-3xl` on one card while its neighbors are `rounded-xl` break the ledger's ruled feel more than they look custom.

Borders are the system's real form language: 1px, `border-border`, on almost everything. The `.frame` primitive is the one place with a second ring, an outer hairline offset 5px from the panel that reads like a ruled margin around a section of the ledger.

## Components

### Buttons
- **Shape:** Softly rounded (10px at standard size, 8px at small and extra-small).
- **Default (primary):** Ink background, paper text, 10px horizontal padding, resting lift shadow, a 1px inner highlight, and a 1px downward translate on press.
- **Hover / Focus:** Primary darkens to 90% opacity; outline fills with Surface Quiet. Focus is a 2px ring with a 1px offset, inherited from the base layer.
- **Secondary / Outline / Ghost / Link:** Secondary is Surface Quiet with foreground text; outline is a hairline over the page surface (the toolbar default); ghost has no chrome until hover; link is text-only with underline on hover.
- **Destructive / Outline-destructive:** Destructive is the solid signal hue with white text. Outline-destructive is used for the cancel side of a delete confirmation.
- **Sizes:** `xs`, `sm`, `default`, `lg`, `xl`, plus icon-only variants. Sizes are chosen by role, not by pixel: `sm` for toolbars, table rows, and page headers; `default` for forms and panels. Each size is 4px taller on touch. All sizing is owned by the active style, never by the call site.
- **Loading:** the `loading` prop, which disables the button, hides the label without changing width, and centers a spinner in the variant's color. Never a hand-placed spinner child.

### Inputs / Fields
- **Style:** Page-surface background (muted in dark mode), hairline border, 10px radius, 10px horizontal padding, field typography.
- **Focus:** Border shifts to the ring color with a 2px ring at 30% opacity, plus the resting lift shadow.
- **Error:** `aria-invalid` drives a destructive border and ring; the message renders through `<InputErrorMessage>` in the destructive foreground.
- **Structure:** Every field is `<Label>` + control + optional helper text + `<InputErrorMessage>` inside a `space-y-2` wrapper, with the label's `for` matching the control's `id`.
- **Specialized fields are mandatory where they exist:** phone, link, password, OTP, date, time, month, year, file, image, and rich text all have dedicated primitives.

### Cards / Containers
- **Corner Style:** 14px.
- **Background:** Paper on light, Night Card on dark.
- **Border:** A 1px ring at 10% foreground rather than a hard border, so cards read softer than frames.
- **Shadow Strategy:** None by default; the Card shadow only when a card floats alone on the page background.
- **Internal Padding:** 16px standard, 12px in the small size; the footer picks up a muted background and a top border.

### Frames (signature component)
The form section primitive, and the clearest expression of the ledger: a muted half-tone container with a hairline border, an outer offset ring, a header of title plus description, and a paper-white panel inset by one pixel that holds the fields. Section titles live in the header, never inside the panel. Every multi-section form uses this instead of a custom card.

### Badges
- **Style:** A pill with a hairline border at 17% foreground, 8px horizontal padding, body typography, plus an automatic colored dot in the variant's hue. `plain` drops the pill entirely; `icon` replaces the dot with a Hugeicons glyph.
- **Variants:** default, info, success, warning, destructive, muted, outline, chosen by meaning.

### Dialogs
`ResponsiveDialog` is the only modal: a centered dialog at 768px and up, a bottom drawer below it, 400px wide by default. It ships with zero padding so a body can go full-bleed, and the first wrapper inside owns `px-4 pt-5 pb-8 md:px-6 md:py-5`. Titles are 1.125rem semibold with tighter tracking; footers are right-aligned with an 8px gap. Destructive confirmations pair an outline cancel with a destructive confirm. Dialog content is never wrapped in a `<form>` that submits the page behind it.

### Toasts
Sonner, through `toast.success / error / warning / info`. A short title, an optional one-line description, and nothing else: no custom corner notification, no stacked banners, no icons beyond the variant's own. Descriptions state what happened, not how the system feels about it.

### Tables
`TableData` owns every list surface that needs search, filter, sort, and pagination: it carries its own toolbar, filter button with a count badge, bulk-action pill, and mobile behavior. Row actions live in a ghost icon-button dropdown, and a delete entry there always opens a confirmation dialog.

### Empty States
The `Empty` family: a dashed border, a 48px round muted circle holding a 24px icon, a headline, one line of body copy, and at most one primary action. Padding is 24px, opening to 48px at `md`.

### Icons
Hugeicons is the default set; Lucide fills gaps. One family per page. Sizes step 12 / 16 / 20 / 24 / 32px by context, and icons inside controls are half a step larger on touch.

### Named Rules

**The Primitive-First Rule.** If a primitive exists, using anything else is a defect: `<Button>` not `<button>`, `<Badge>` not a hand-rolled pill, `<Empty>` not a centered div, `<Skeleton>` not a gray block, `<TabNav>` not a hand-built tab strip, `<TableData>` not an assembled list, `<ResponsiveDialog>` not `confirm()` / `alert()` / `prompt()`. The most frequent violation by far is the manual pill (`rounded-full` + `px-2 py-0.5` + `bg-*` + `text-xs`); it is always a `<Badge>`.

**The Optimistic Response Rule.** Toggles, quick-add, delete, and inline title edits update the interface immediately and reconcile with the server afterwards, rolling back with an error toast if it fails. A switch that waits for a round trip before moving reads as broken, and on a show floor it reads as a dead app.

**The Style-Owns-The-Box Rule.** Never attach `bg-*`, `border-*`, `h-*`, `rounded-*`, `px-*`, or `shadow-*` to an input-like element at the call site. Those belong to the `cn-*` rules in `assets/css/styles/style-<name>.css`. Hardcoding pins a field to one style pack and, in the case of `dark:bg-background`, dissolves it into the dialog behind it. Add the missing `cn-*` rule instead (9 style files across 3 repos), and run `bash frontend/scripts/check-input-hardcode.sh`.

**The Sized-Variant Rule.** Calling `buttonVariants()` without a `size` argument silently falls back to `size-default`, producing a button taller than its neighbors. Always pass the size.

## Do's and Don'ts

### Do:
- **Do** use semantic tokens for every color: `bg-muted`, `text-muted-foreground`, `border-border`, `bg-destructive`.
- **Do** set `tracking-tight` everywhere and `tracking-tighter` from `text-xl` or `font-semibold` up.
- **Do** use `text-xs sm:text-sm` for fine print, and leave interactive controls at one fixed size.
- **Do** use `pointer-fine:text-sm` on typed fields so iOS never zooms on focus.
- **Do** wrap form sections in `.frame` and fields in `space-y-2`, with `gap-x-2` in two-column grids.
- **Do** put the submit button in the page header, beside the title or back button, with the `loading` prop driving its state.
- **Do** reach for `h-(--cn-input-h)` when a button must line up with a field.
- **Do** animate state changes: open/close, selected, valid/invalid, loading/loaded.
- **Do** hide fields that don't matter to the current task; density earns its keep by carrying signal, not options.

### Don't:
- **Don't** use `font-bold`, `font-extrabold`, `uppercase`, `tracking-wider`, or `tracking-widest`.
- **Don't** use literal palette colors (`bg-green-*`, `bg-red-*`, `bg-blue-*`, `bg-yellow-*`), `bg-white`, `bg-black`, or raw hex.
- **Don't** use native `<button>`, `<input>`, `<select>`, `<textarea>`, or browser `confirm()`.
- **Don't** hand-build pills, cards, empty states, skeletons, or toolbar buttons that a primitive already covers.
- **Don't** add `hover:scale-*` or `group-hover:scale-*` to images and cards in new code. Hover affordance is a color or border change. (`active:scale-98` on a pressed button is click feedback and stays.)
- **Don't** treat existing motion in the repo as a finding. It was decided deliberately; change it only when asked.
- **Don't** nest cards, add accent stripes (`border-left: 3px solid`), gradient text, glassmorphism, or decorative sparklines.
- **Don't** use em dashes in interface copy, and don't hand-write required-field asterisks.
- **Don't** mix icon families on one page, and don't reach for `shadow-2xl` on ordinary components.
- **Don't** ship a placeholder illustration where a neutral muted gradient will do (`from-muted to-muted/40 bg-gradient-to-br`).
