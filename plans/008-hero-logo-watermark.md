# Plan 008 — Hero watermark from the project logo

## Goal

Use `docs/images/turso-php-installer-logo.png` as a decorative background on the
left side of the landing page hero, behind the headline, and tune the surrounding
UI so the hero stays readable and balanced in both themes and on small screens.

## Current state (checked)

- `docs/index.html` hero is a two-column `.hero-grid`: copy on the left, CSS
  terminal frame on the right. No image in the hero.
- `docs/assets/css/landing.css` defines light/dark palettes sampled from the
  same logo; `--accent` is `#8a5a00` light and `#fcb61e` dark.
- Logo file is `1254 × 1254` RGB PNG. It is currently used only as the 20×20
  brand mark in the header.
- `scripts/check_docs.py` validates `<img src>` targets, so the watermark should
  be a real `<img>` tag with `aria-hidden="true"` rather than a CSS-only
  background URL that the gate cannot see.
- Jekyll template expressions are available in `docs/index.html`, so the src
  must keep `{{ site.baseurl }}` for base-path correctness.

## Steps

### Step 1 — Add the decorative image

- Insert an `<img class="hero__watermark">` as the first child of `.hero`,
  before `.hero-grid`.
- Keep `alt=""`, `aria-hidden="true"`, `draggable="false"`, `decoding="async"`
  and intrinsic width/height. It must not be focusable or announced.

### Step 2 — Position and tune it

- `.hero` becomes a positioning context with `overflow: hidden` so the oversized
  mark cannot create horizontal scroll.
- Position the mark on the left, vertically centred on the copy, oversized and
  rotated slightly; use a radial `mask-image` (with `-webkit-` prefix) so it
  fades out instead of ending in a hard square.
- Raise `.hero-grid` above the watermark with `z-index`.
- Theme variables control opacity: faint on the cream light theme, slightly
  stronger on the dark theme. No filter or blend-mode tricks unless the
  screenshot shows they are needed.
- Tablet and mobile breakpoints shrink, reposition and further reduce the
  opacity so the headline remains the primary focal point.

### Step 3 — Verify

- `python3 scripts/check_docs.py` → three `OK:` lines.
- `bash -n`, Pest, `composer validate`, `git diff --check` still pass.
- Render a local static preview in headless Chrome at 1440×775 and 390×844,
  light and dark, and inspect the screenshots before committing.
- Jekyll build on `main` must pass after merge.
- No code, version, tag or release changes.

## Out of scope

- Replacing the existing terminal-frame visual.
- Changing copy, navigation, typography or the colour system.
- Animating the watermark.
