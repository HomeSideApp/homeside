---
version: alpha
name: HomeSide
description: Warm, domestic and family-centered open-source design system for HomeSide. shadcn-vue is the canonical UI component foundation.
colors:
  background: "#FBFCFA"
  foreground: "#153C3A"
  card: "#FFFFFF"
  card-foreground: "#153C3A"
  popover: "#FFFFFF"
  popover-foreground: "#153C3A"
  primary: "#1E6B63"
  primary-hover: "#185851"
  primary-active: "#124A45"
  primary-foreground: "#FFFFFF"
  secondary: "#F4F1E8"
  secondary-foreground: "#1E6B63"
  muted: "#E6ECEF"
  muted-foreground: "#5B6770"
  accent: "#D9E8B6"
  accent-foreground: "#1E6B63"
  brand-seaglass: "#2FA090"
  brand-sage: "#7CCBA2"
  brand-moss: "#D9E8B6"
  brand-sand: "#F4F1E8"
  brand-mist: "#E6ECEF"
  brand-slate: "#5B6770"
  destructive: "#B42318"
  destructive-foreground: "#FFFFFF"
  success: "#1E6B63"
  success-foreground: "#FFFFFF"
  warning: "#FFF2C7"
  warning-foreground: "#654A00"
  info: "#E9F2FF"
  info-foreground: "#184E88"
  border: "#E6ECEF"
  input: "#E6ECEF"
  ring: "#2FA090"
  background-dark: "#0B2F2F"
  foreground-dark: "#F4F1E8"
  card-dark: "#103B39"
  card-foreground-dark: "#F4F1E8"
  popover-dark: "#103B39"
  popover-foreground-dark: "#F4F1E8"
  primary-dark: "#7CCBA2"
  primary-foreground-dark: "#0B2F2F"
  secondary-dark: "#164641"
  secondary-foreground-dark: "#F4F1E8"
  muted-dark: "#173F3D"
  muted-foreground-dark: "#B8C9C6"
  accent-dark: "#1E574F"
  accent-foreground-dark: "#D9E8B6"
  destructive-dark: "#EF6A60"
  destructive-foreground-dark: "#0B2F2F"
  border-dark: "#2B5A57"
  input-dark: "#2B5A57"
  ring-dark: "#7CCBA2"
typography:
  display-lg:
    fontFamily: Nunito Sans
    fontSize: 48px
    fontWeight: 800
    lineHeight: 1.1
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Nunito Sans
    fontSize: 36px
    fontWeight: 800
    lineHeight: 1.2
    letterSpacing: -0.015em
  headline-md:
    fontFamily: Nunito Sans
    fontSize: 30px
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Nunito Sans
    fontSize: 24px
    fontWeight: 700
    lineHeight: 1.3
  title-md:
    fontFamily: Nunito Sans
    fontSize: 20px
    fontWeight: 700
    lineHeight: 1.35
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: 400
    lineHeight: 1.6
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: 400
    lineHeight: 1.6
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: 400
    lineHeight: 1.5
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: 600
    lineHeight: 1.2
  caption:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: 400
    lineHeight: 1.4
  code:
    fontFamily: JetBrains Mono
    fontSize: 13px
    fontWeight: 400
    lineHeight: 1.5
rounded:
  xs: 4px
  sm: 6px
  md: 8px
  lg: 12px
  xl: 16px
  2xl: 24px
  full: 9999px
spacing:
  1: 4px
  2: 8px
  3: 12px
  4: 16px
  5: 20px
  6: 24px
  8: 32px
  10: 40px
  12: 48px
  16: 64px
  page-gutter-mobile: 16px
  page-gutter-tablet: 24px
  page-gutter-desktop: 32px
  content-max: 1280px
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.primary-foreground}"
    typography: "{typography.label-md}"
    rounded: "{rounded.md}"
    padding: 12px
    height: 40px
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
    textColor: "{colors.primary-foreground}"
  button-primary-active:
    backgroundColor: "{colors.primary-active}"
    textColor: "{colors.primary-foreground}"
  button-secondary:
    backgroundColor: "{colors.secondary}"
    textColor: "{colors.secondary-foreground}"
    typography: "{typography.label-md}"
    rounded: "{rounded.md}"
    padding: 12px
    height: 40px
  button-destructive:
    backgroundColor: "{colors.destructive}"
    textColor: "{colors.destructive-foreground}"
    typography: "{typography.label-md}"
    rounded: "{rounded.md}"
    padding: 12px
    height: 40px
  button-primary-dark:
    backgroundColor: "{colors.primary-dark}"
    textColor: "{colors.primary-foreground-dark}"
    typography: "{typography.label-md}"
    rounded: "{rounded.md}"
    padding: 12px
    height: 40px
  card-default:
    backgroundColor: "{colors.card}"
    textColor: "{colors.card-foreground}"
    rounded: "{rounded.lg}"
    padding: 24px
  card-dark:
    backgroundColor: "{colors.card-dark}"
    textColor: "{colors.card-foreground-dark}"
    rounded: "{rounded.lg}"
    padding: 24px
  popover-default:
    backgroundColor: "{colors.popover}"
    textColor: "{colors.popover-foreground}"
    rounded: "{rounded.md}"
    padding: 8px
  popover-dark:
    backgroundColor: "{colors.popover-dark}"
    textColor: "{colors.popover-foreground-dark}"
    rounded: "{rounded.md}"
    padding: 8px
  input-default:
    backgroundColor: "{colors.card}"
    textColor: "{colors.foreground}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.md}"
    padding: 12px
    height: 40px
  input-dark:
    backgroundColor: "{colors.card-dark}"
    textColor: "{colors.foreground-dark}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.md}"
    padding: 12px
    height: 40px
  badge-default:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.accent-foreground}"
    typography: "{typography.caption}"
    rounded: "{rounded.full}"
    padding: 8px
  badge-muted:
    backgroundColor: "{colors.muted}"
    textColor: "{colors.muted-foreground}"
    typography: "{typography.caption}"
    rounded: "{rounded.full}"
    padding: 8px
  alert-success:
    backgroundColor: "{colors.success}"
    textColor: "{colors.success-foreground}"
    rounded: "{rounded.md}"
    padding: 16px
  alert-warning:
    backgroundColor: "{colors.warning}"
    textColor: "{colors.warning-foreground}"
    rounded: "{rounded.md}"
    padding: 16px
  alert-info:
    backgroundColor: "{colors.info}"
    textColor: "{colors.info-foreground}"
    rounded: "{rounded.md}"
    padding: 16px
  surface-dark:
    backgroundColor: "{colors.background-dark}"
    textColor: "{colors.foreground-dark}"
  secondary-dark:
    backgroundColor: "{colors.secondary-dark}"
    textColor: "{colors.secondary-foreground-dark}"
  muted-dark:
    backgroundColor: "{colors.muted-dark}"
    textColor: "{colors.muted-foreground-dark}"
  accent-dark:
    backgroundColor: "{colors.accent-dark}"
    textColor: "{colors.accent-foreground-dark}"
  destructive-dark:
    backgroundColor: "{colors.destructive-dark}"
    textColor: "{colors.destructive-foreground-dark}"
---

# HomeSide Design System

## Overview

HomeSide is an **open-source home management application**. Its identity should feel like the home itself: warm, calm, familiar, organized, inclusive, and safe. The product is intended for individuals, couples, families, shared households, and homeowners, so the interface must never assume a specific family structure or living arrangement.

The visual personality is **warm domestic software**, not futuristic smart-home software. Technology should feel useful and quietly present rather than dominant. Avoid cyber, neon, glassmorphism, sci-fi, overly corporate SaaS styling, or excessive dashboard density.

The four brand pillars are:

- **Warm:** HomeSide should feel welcoming and human rather than clinical.
- **Organized:** Information should be easy to scan, group, retrieve, and act on.
- **Family:** The product is designed around people sharing a home, without assuming what a family looks like.
- **Open Source:** The visual language should communicate transparency, practicality, accessibility, and community ownership.

The canonical tagline is **“Open-source home management”**.

### Brand mark

The primary HomeSide symbol is the approved **family-inside-a-house** mark: a rounded house outline containing three abstract human figures. The shapes communicate a home shared by people, while the central lower negative/positive shape subtly suggests care and connection.

Rules for brand assets:

- Always use the canonical SVG/PNG brand assets supplied with the repository once available. **Never redraw, reinterpret, approximate, or replace the HomeSide mark with a generic house icon.**
- The full horizontal lockup is the preferred logo: symbol on the left, `HomeSide` wordmark on the right.
- The icon-only mark is used for favicon, app icon, avatar, compact navigation, PWA metadata, and other constrained square spaces.
- The wordmark is exactly **HomeSide**, preserving the uppercase `H` and `S`.
- Do not translate or localize the brand name.
- The canonical logo may contain its own controlled tonal treatment/gradient in exported artwork. Do not recreate that gradient in CSS. Use the supplied asset as-is.
- UI surfaces should remain predominantly flat; gradients are not a general-purpose UI decoration.

### Logo clear space and size

For the horizontal logo, maintain clear space on all sides at least equal to the cap height of the `H` in the HomeSide wordmark. No text, border, icon, illustration, or container edge should enter this clear area.

- Full horizontal logo: **120px minimum digital width**.
- Full horizontal logo: **25mm minimum print width**.
- Icon-only mark: may be used below the full-logo minimum size.
- Favicon/icon-only mark: optimize exported assets specifically for 16px, 32px, 48px, 96px, 192px, and 512px rather than shrinking the full lockup.

### Imagery

When photography or illustration is introduced, prefer real domestic contexts: lived-in but orderly homes, shared activities, everyday objects, natural materials, soft daylight, plants, kitchens, living spaces, storage, maintenance, and household routines.

People should appear naturally and inclusively. Avoid staged stock-photo stereotypes, luxury-only homes, sterile architecture, surveillance imagery, humanoid robots, or visuals that imply HomeSide is primarily an IoT control panel.

### Iconography

Use **Lucide** icons through the icon library used by shadcn-vue. Icons are supporting information, not decoration.

- Default UI icon size: 16px.
- Prominent action/empty-state icon size: 20–24px.
- Use a consistent stroke weight within each surface.
- Prefer one clear icon over clusters of decorative icons.
- Never use a generic Lucide house icon as a substitute for the HomeSide brand mark.

## Colors

HomeSide uses a teal/green family inspired by home, nature, calm, trust, and continuity. Warm neutrals prevent the interface from becoming cold or medical.

### Core brand palette

- **Forest — `#1E6B63`:** primary HomeSide brand color. Use for primary actions, key emphasis, brand framing, and the dominant logo tone.
- **Seaglass — `#2FA090`:** energetic supporting teal. Use for focus, secondary visual accents, progress, charts, and selected details.
- **Sage — `#7CCBA2`:** friendly light green. Use for soft highlights and dark-theme primary actions.
- **Moss — `#D9E8B6`:** warm pale green. Use for positive or gentle accent surfaces, never as body text.
- **Sand — `#F4F1E8`:** warm neutral surface. Use for secondary controls, grouped content, and domestic warmth.
- **Mist — `#E6ECEF`:** cool-light neutral used for dividers, muted surfaces, and input/border structure.
- **Slate — `#5B6770`:** secondary copy, metadata, and subdued UI text.

### Semantic color behavior

- `primary` is Forest in light mode and Sage in dark mode.
- `foreground` is a very dark teal rather than pure black.
- `background` is an almost-white warm-neutral surface rather than a tinted green canvas.
- Use white cards selectively; not every content group needs a card.
- `secondary`, `muted`, and `accent` are intentionally subtle so that the primary action remains obvious.
- Destructive actions use red and must never be recolored green simply to fit the brand.
- Warnings use amber/yellow; informational states use blue. Functional semantics override palette purity.

### shadcn-vue semantic mapping

The application should theme shadcn-vue using its semantic CSS-variable model. Agents should prefer these semantic variables/classes over hard-coded brand hex values inside components.

Light theme mapping:

```css
:root {
  --background: #fbfcfa;
  --foreground: #153c3a;
  --card: #ffffff;
  --card-foreground: #153c3a;
  --popover: #ffffff;
  --popover-foreground: #153c3a;
  --primary: #1e6b63;
  --primary-foreground: #ffffff;
  --secondary: #f4f1e8;
  --secondary-foreground: #1e6b63;
  --muted: #e6ecef;
  --muted-foreground: #5b6770;
  --accent: #d9e8b6;
  --accent-foreground: #1e6b63;
  --destructive: #b42318;
  --destructive-foreground: #ffffff;
  --border: #e6ecef;
  --input: #e6ecef;
  --ring: #2fa090;
  --radius: 0.5rem;
}
```

Dark theme mapping:

```css
.dark {
  --background: #0b2f2f;
  --foreground: #f4f1e8;
  --card: #103b39;
  --card-foreground: #f4f1e8;
  --popover: #103b39;
  --popover-foreground: #f4f1e8;
  --primary: #7ccba2;
  --primary-foreground: #0b2f2f;
  --secondary: #164641;
  --secondary-foreground: #f4f1e8;
  --muted: #173f3d;
  --muted-foreground: #b8c9c6;
  --accent: #1e574f;
  --accent-foreground: #d9e8b6;
  --destructive: #ef6a60;
  --destructive-foreground: #0b2f2f;
  --border: #2b5a57;
  --input: #2b5a57;
  --ring: #7ccba2;
}
```

When shadcn-vue or Tailwind generates a variable using a different color syntax such as `oklch()`, preserve the semantic role and visual/contrast result. Do not maintain a second competing component-color system unless a technical requirement demands it.

### Accessibility

- Normal text must meet **WCAG AA 4.5:1** contrast minimum.
- Large text must meet at least **3:1**.
- Focus states must remain visible in both themes.
- Never communicate status using color alone; pair status color with text and/or an icon.
- Do not use Seaglass, Sage, Moss, Sand, or Mist as small body text on white without verifying contrast.

## Typography

Typography is friendly and contemporary, with a clear distinction between brand expression, application UI, and technical content.

### Font families

- **Nunito Sans:** HomeSide brand voice, marketing headings, major application headings, empty-state titles, and prominent moments. Its rounded forms reinforce warmth and approachability.
- **Inter:** primary interface and content typeface. Use for forms, tables, navigation, body copy, labels, menus, dialogs, and data-heavy application views.
- **JetBrains Mono:** code, terminal output, identifiers, API examples, file paths, and technical values where monospace presentation adds meaning.

Use system fallbacks when fonts are not loaded. Do not block the application while a webfont loads.

Recommended stacks:

```css
--font-brand: "Nunito Sans", ui-rounded, system-ui, sans-serif;
--font-sans: "Inter", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
--font-mono: "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
```

### Hierarchy

- Large marketing/display text can use 48px Nunito Sans ExtraBold.
- Application page titles generally use 30–36px Nunito Sans Bold/ExtraBold.
- Section titles use 20–24px Nunito Sans Bold.
- Standard body and form content use 16px Inter Regular.
- Dense metadata may use 14px Inter; avoid going below 12px.
- Buttons and compact labels use 14px Inter SemiBold.
- Body text should remain at least 16px where reading, accessibility, or mobile ergonomics matter.

Avoid excessive weight changes. A normal product screen should usually need only Regular, SemiBold, and Bold/ExtraBold for the page title.

## Layout

HomeSide uses a **mobile-first fluid layout** with a constrained desktop content area. Interfaces should feel ordered but not cramped.

### Grid and containers

- Main application content maximum: **1280px** unless a data-heavy feature genuinely benefits from a wider viewport.
- Mobile horizontal gutter: 16px.
- Tablet horizontal gutter: 24px.
- Desktop horizontal gutter: 32px.
- Use full-width data tables carefully and provide responsive alternatives when the content cannot compress.
- Prefer responsive CSS Grid/Flexbox over fixed dimensions.

### Spacing rhythm

The base rhythm is 4px, with 8px as the dominant visual step.

- 4px: micro-spacing between tightly related elements.
- 8px: icon/label gaps and compact control relationships.
- 12px: compact component interiors.
- 16px: default control/card gaps and mobile padding.
- 24px: card/container padding and related section spacing.
- 32px: separation between major groups.
- 48–64px: major page sections and marketing composition.

Do not choose arbitrary values such as 13px, 19px, 27px, or 35px when a defined spacing token can express the intent.

### Information density

HomeSide contains inventories, finances, documents, household tasks, maintenance, and administrative data, so some screens will be information-dense. Density should be handled through hierarchy, filtering, tables, tabs, collapsibles, and progressive disclosure — not by shrinking text or removing whitespace indiscriminately.

### Responsive behavior

- Design for touch first where actions are likely to be used from a phone.
- Interactive targets should generally be at least 40px high; aim for 44px when space allows on touch-focused surfaces.
- Dialogs that become awkward on mobile should use the appropriate shadcn-vue responsive pattern, such as a Drawer where that is more usable.
- Side-by-side forms collapse to one column on narrow viewports unless fields have a strong semantic reason to remain paired.
- Never hide essential actions solely because the viewport is small.

## Elevation & Depth

HomeSide primarily uses **borders, tonal surfaces, spacing, and hierarchy** rather than strong shadows.

- Default cards and form containers use subtle borders.
- Popovers, menus, dialogs, command palettes, and floating elements may use shadcn-vue's subtle elevation treatment.
- Avoid large diffuse shadows on every card.
- Do not create floating layers for static information that can be expressed with spacing and a border.
- Dark mode should use tonal separation between `background`, `card`, `popover`, and borders rather than relying on shadows that become invisible.

Recommended shadow intent:

```text
sm  -> subtle interactive/floating hint
md  -> dropdown/popover
lg  -> dialog/command overlay when necessary
xl  -> exceptional marketing presentation only
```

Motion may reinforce depth, but should be brief and restrained. Prefer opacity/transform transitions around 150–200ms and respect `prefers-reduced-motion`.

## Shapes

The shape language is **soft geometric**: approachable without becoming childish.

- Standard controls: 8px radius.
- Cards: 12px radius.
- Larger feature containers: 16px radius when appropriate.
- Badges, avatars, and pills may use full rounding.
- Small utility elements can use 4–6px radius.
- Keep a consistent radius family on the same screen.

The HomeSide logo has rounded geometry, but that does not mean every UI element should become a pill. Pills are reserved for badges, tags, segmented states, compact filters, avatars, and explicitly pill-shaped controls.

Avoid ornamental blobs, wavy container boundaries, overly organic buttons, or different radius values invented per feature.

## Components

**shadcn-vue is the mandatory UI foundation for HomeSide.** It is not merely visual inspiration. If an appropriate shadcn-vue component exists, use/install that component before creating a custom primitive.

Canonical component documentation: `https://www.shadcn-vue.com/docs/components`

### Implementation rules for coding agents

1. Search the existing project for an installed shadcn-vue component before implementing UI.
2. If the required shadcn-vue component is not present, install/add it before writing a new equivalent component.
3. Extend or compose shadcn-vue components for HomeSide-specific behavior rather than duplicating primitives.
4. Preserve shadcn-vue/Reka UI keyboard behavior, focus management, ARIA semantics, disabled states, and accessibility behavior.
5. Style through the project theme, semantic CSS variables, Tailwind utilities, and component variants. Avoid one-off inline hex colors.
6. HomeSide domain components may be custom compositions — for example `HouseholdMemberPicker`, `AssetCard`, `ExpenseBreakdown`, or `DocumentUploader` — but their buttons, inputs, popovers, dialogs, badges, calendars, etc. should remain shadcn-vue primitives underneath.
7. Do not introduce a second general-purpose component library for controls that shadcn-vue already supplies.
8. Use Lucide icons consistently with the shadcn-vue installation.

### shadcn-vue components to prefer

Use the canonical component that matches the interaction. Examples include:

- `Button` for actions.
- `ButtonGroup` when actions are intentionally grouped.
- `Input`, `Textarea`, `NumberField`, `InputGroup`, `InputOTP`, and `Field` for data entry.
- `Checkbox`, `RadioGroup`, `Switch`, and `Select` for choices.
- `Combobox` for searchable selection.
- `Form`/field patterns for validation-aware forms.
- `Card` for genuine contained content groups, not for every section.
- `Badge` for compact status/category metadata.
- `Alert` for persistent contextual feedback.
- `Toast`/sonner-style feedback where already established in the project for transient notifications.
- `Dialog` and `AlertDialog` for modal flows and destructive confirmation.
- `Drawer` for touch-oriented or responsive secondary flows.
- `DropdownMenu`, `ContextMenu`, `Popover`, `Tooltip`, and `HoverCard` according to their actual interaction semantics.
- `Tabs`, `Accordion`, `Collapsible`, and `NavigationMenu` for information architecture.
- `Table`/`DataTable` for structured data.
- `Pagination` for server-paginated result sets.
- `Calendar` and `DatePicker` for dates.
- `Skeleton` for loading placeholders where preserving layout is useful.
- `Empty` for intentionally empty datasets and first-run states.
- `Breadcrumb` when hierarchy needs explicit navigation context.

Do not substitute a `div` with click handlers for a button, menu item, checkbox, select, dialog, or other semantic control.

### Buttons

Follow shadcn-vue variants rather than inventing HomeSide-specific button families:

- **Default/Primary:** Forest background, white foreground. One dominant action per local decision area.
- **Secondary:** warm Sand surface with Forest text.
- **Outline:** transparent/light surface with standard border and Forest/foreground text.
- **Ghost:** no persistent container; used for low-emphasis utility actions.
- **Destructive:** semantic red, reserved for destructive actions.
- **Link:** only when an action should visually read as a text link.

Primary buttons should not be used repeatedly in the same toolbar. Hierarchy must remain obvious.

### Forms and Laravel Precognition states

Form styling must support normal, focus, validation-pending, valid, invalid, disabled, and submitting states without layout jumps.

- Labels remain visible; placeholders do not replace labels.
- Validation messages appear adjacent to the relevant field using shadcn-vue field/form patterns.
- Precognition validation must use the same visual error state as final server validation.
- Do not show success styling on every field merely because precognition passed; use success feedback only when it adds useful information.
- Required fields should be communicated accessibly, not only through color.
- Disabled and read-only are visually and semantically distinct.

### Cards

Cards are for meaningful containment. Prefer a simple page section with heading and spacing when a border/background adds no information.

When using `Card`:

- Default radius: 12px.
- Default interior spacing: 24px desktop, 16px when compact/mobile.
- Use `CardHeader`, `CardTitle`, `CardDescription`, `CardContent`, and `CardFooter` according to the component's structure rather than rebuilding them ad hoc.
- Avoid decorative shadows; a subtle border is the normal treatment.

### Badges and status

Badges are compact and short. Use them for state, category, role, or metadata such as `Active`, `Pending`, `Shared`, or `Warranty`.

Status colors have stable meanings across modules. Do not let individual features redefine green as error, red as normal, etc.

### Alerts, destructive actions, and confirmations

- Persistent inline guidance/error -> `Alert`.
- Destructive confirmation -> `AlertDialog`.
- Non-destructive editing flow -> `Dialog` or `Drawer` as appropriate.
- Use destructive red only where consequences justify it.
- Confirmation dialogs must name the affected entity and explain irreversible consequences.

### Tables and dense data

For inventories, assets, finances, audit-like lists, and administrative screens:

- Keep column labels concise.
- Right-align numeric/currency values where appropriate.
- Keep row actions in a consistent trailing position.
- Use a dropdown menu for secondary row actions when too many actions would create noise.
- Preserve keyboard access and visible focus.
- On mobile, choose a deliberate responsive representation rather than forcing an unreadable desktop table.

### Navigation

Navigation should feel calm and stable.

- Highlight the current location using semantic active styling, not color alone.
- Avoid multiple competing navigation systems on one screen.
- Keep global navigation separate from entity-level tabs/actions.
- Search is a distinct control and must not visually compete with the page's primary action.

### Empty, loading, and error states

Every data-driven screen should have designed states for:

- first-use empty state;
- filtered/no-results state;
- loading state;
- permission/access-denied state where relevant;
- recoverable error;
- non-recoverable/404 state where relevant.

Empty states should be helpful and concise. They may use a Lucide icon and a single clear action. Avoid large decorative illustrations unless the context warrants them.

### Dark mode

Dark mode is a first-class theme, not an inverted afterthought.

- Use the explicit dark semantic tokens.
- Avoid pure black as the primary app background.
- Use Sage as the dark-mode primary action color with dark teal foreground.
- Ensure borders remain visible but subdued.
- Brand artwork should use its approved dark-background asset when available.
- Never apply CSS filters to force the light logo into a dark-mode logo.

## Do's and Don'ts

### Do

- Do make HomeSide feel warm, useful, organized, calm, and human.
- Do use the approved HomeSide family-house brand assets.
- Do use shadcn-vue as the source of truth for UI primitives.
- Do install an existing shadcn-vue component before creating an equivalent custom primitive.
- Do compose domain-specific components from shadcn-vue primitives.
- Do use semantic theme variables instead of scattering brand hex values through Vue components.
- Do use Nunito Sans for brand/prominent headings and Inter for application UI/body text.
- Do keep body text legible and controls touch-friendly.
- Do maintain WCAG AA contrast and visible keyboard focus.
- Do support light and dark mode from the beginning of a new surface.
- Do use destructive red, warning amber, and informational blue according to semantic meaning.
- Do favor borders, spacing, and tonal hierarchy over heavy shadows.
- Do design mobile/responsive behavior intentionally.
- Do preserve standard HTML semantics and shadcn-vue/Reka accessibility behavior.

### Don't

- Don't recreate a shadcn-vue primitive from scratch because it looks simple.
- Don't introduce another generic UI library for components already covered by shadcn-vue.
- Don't use a generic house icon as the HomeSide logo.
- Don't distort, rotate, recolor arbitrarily, outline, shadow, or otherwise decorate the canonical HomeSide logo.
- Don't place the logo over busy imagery without an approved high-contrast treatment.
- Don't use the full horizontal logo where the icon-only mark is required by size.
- Don't make the interface look like a futuristic IoT dashboard, cybersecurity console, or neon smart-home control panel.
- Don't use gradients as generic buttons, cards, headers, or backgrounds. The logo asset is the exception when the canonical artwork contains a controlled tonal treatment.
- Don't overuse cards, pills, badges, borders, shadows, or icons.
- Don't make every action a primary green button.
- Don't hard-code colors that should come from shadcn-vue semantic theme variables.
- Don't use color alone to indicate status, validation, selection, or focus.
- Don't shrink typography to solve layout problems.
- Don't hide important functionality on mobile without providing an equivalent accessible path.
- Don't invent new spacing, radii, or status colors when an existing token fits.
- Don't create non-semantic clickable `div` or `span` controls.

When a design decision is not explicitly covered here, choose the option that best preserves this priority order: **accessibility and semantics → shadcn-vue consistency → clarity and organization → HomeSide warmth → decorative expression**.
