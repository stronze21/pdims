---
name: PDIMS hospital theme
description: Application-wide clinical mint, hospital green, and surgical teal design reference.
colors:
  print-background: "#fff"
  print-content: "#111827"
  light-background: "oklch(96.5% 0.012 166)"
  light-surface: "oklch(99% 0.006 165)"
  light-text: "oklch(27% 0.035 205)"
  light-muted: "#566c68"
  light-primary: "oklch(52% 0.145 164)"
  light-primary-text: "oklch(98% 0.01 165)"
  light-border: "#c9d9d1"
  light-soft: "#eaf3ed"
  dark-background: "oklch(17% 0.025 207)"
  dark-surface: "oklch(20% 0.028 205)"
  dark-text: "oklch(92% 0.018 174)"
  dark-muted: "#a4bdb5"
  dark-primary: "oklch(72% 0.14 162)"
  dark-primary-text: "oklch(16% 0.035 170)"
  dark-border: "#36524e"
  dark-soft: "#193c37"
typography:
  body:
    fontFamily: "Instrument Sans, ui-sans-serif, sans-serif"
  display:
    fontFamily: "Instrument Sans, ui-sans-serif, sans-serif"
    fontSize: "clamp(44px, 4.8vw, 78px)"
    fontWeight: 600
    lineHeight: 1.06
    letterSpacing: "-0.04em"
rounded:
  input: "10px"
  card: "16px"
  brand-panel: "24px"
---

## Overview

The hospital theme applies to all shared PDIMS application layouts: pharmacy, dispensing, portal, guest/account, queue controller, and queue display. `resources/css/theme.css` supplies semantic DaisyUI tokens and shared surface treatments; `resources/css/app.css` supplies typography and login composition. Preserve purpose-specific clinical status colors and printable document layouts.

The visual direction inherits the theme pinned to `C:/laragon/www/emr2`: clinical mint white, hospital green, and surgical teal. Pair a dark hospital identity panel with a quiet credential card. Keep the hospital seal and pharmacy identity recognizable, with sign-in as the primary action.

## Colors

Preserve the exact light and dark OKLCH tokens above. Primary green carries actions, focus, and selected accents; muted colors carry supporting text; soft surfaces and borders separate controls without heavy decoration.

The identity panel stays dark in both modes, using the existing layered teal gradient and mint headline accent. Appearance is shared through the document's `data-theme` and `dark` class. The head partial restores it before styles paint. `pharmacyAppearance` and the native `appearance-toggle` component synchronize all controls and browser tabs. Keep compatibility with MaryUI's `mary-theme` and `mary-class` storage keys and migrate the earlier `pdims.login.theme` preference. Without a saved choice use the system preference. Storage failure must leave switching usable.

## Typography

Use the self-hosted Instrument Sans variable font (weights 400–700, `font-display: swap`), supplied with `public/fonts/InstrumentSans-OFL.txt`. Keep the system sans fallback. The large two-line identity statement uses the display treatment above; the credential heading uses 32px, weight 600, and tight tracking. Supporting form copy is 14px; labels are 13px and weight 600. Mobile inputs increase to 16px.

## Layout

Desktop uses a full-height (`100svh`) two-column composition in a 1.08:1 ratio with 20px outer spacing. The identity panel contains the hospital identity, headline, capability labels, and authorization footer. The sign-in column centers content within 440px; card padding is 34px.

- At 1500px and wider, increase outer spacing to 28px, content width to 460px, and card padding to 40px.
- At 1100px and narrower, reduce panel and card spacing, fix the brand headline at 48px, and allow supporting copy to wrap naturally.
- At 900px and narrower, use one column without outer padding, hide the large identity panel, show the compact hospital identity above the form, and hide the form emblem. Use 24px side padding.
- At 420px and narrower, use 18px side padding, 26px by 22px card padding, and a 29px credential heading.
- Above 900px wide and at most 820px tall, compress vertical spacing and hide the form emblem so the full sign-in path fits smaller laptop displays. Preserve ordinary scrolling when content expands.

## Elevation & Depth

Keep depth restrained: the form card uses a broad, soft shadow (`0 20px 60px -28px`), tinted teal in light mode and black in dark mode. The identity panel supplies visual richness through its gradient; inputs rely on borders and focus outlines rather than shadows.

## Shapes

Use the rounded values above consistently. Credential inputs and the primary action are 52px tall. Theme and password visibility controls have 44px square targets; the theme control is circular. Keep icons small and functional.

## Components

Preserve the existing email/password POST to `route('login')`, CSRF field, remembered email and checkbox state, autocomplete attributes, and conditional password-reset route. Appearance changes must not alter authentication behavior.

Keep visible labels, native required fields, keyboard focus outlines, accessible names for icon buttons, and the password visibility button's pressed state. Errors use an alert and field associations; session success and Caps Lock guidance use status announcements. Submission changes the action label to “Signing in…” and disables the button, with state reset on `pageshow`. The help text directs users to IT or Pharmacy Administration.

Motion is subtle: a 420ms, 8px arrival movement and short control transitions. Respect reduced motion by disabling animations and transitions throughout the login root. Do not add persistent decorative movement.

## Do's and Don'ts

**Shared theme rule.** Use semantic base, primary, secondary, and status tokens across PDIMS. Legacy neutral utilities adapt within `.pdims-theme`; preserve warning, error, success, and information meanings. Keep the login composition scoped to `.pharmacy-login`. Printed output always uses white surfaces and dark text.

**Clinical clarity rule.** Preserve the quiet form hierarchy, readable labels, hospital identity, and one obvious primary action. Avoid additional promotional cards, invented capabilities, or unsupported security claims.

**Native loop rule.** Use native HTML with DaisyUI/Tailwind styling inside Blade loops, including validation lists; do not introduce MaryUI components inside loops.

Desktop and mobile light-mode screenshots were reviewed in an isolated preview without material visual defects. This review does not establish backend authentication or database verification.

## Application surfaces

Use Instrument Sans throughout. Navigation uses quiet mint-tinted surfaces and green active items. Shared cards, dialogs, buttons, dropdowns, form controls, table headings, and row treatments inherit semantic tokens. Keep existing information density, route authorization, Livewire bindings, column widths, and responsive layout decisions. Touch controls retain at least 44px targets where existing size variants permit. Focus indicators use the primary color; reduced motion disables added transitions. Paper documents retain their existing dimensions and a forced light palette.
