# FLOATING-CARD.md

Reuse guide for the floating side card pattern in the Jhon Brix Brion portfolio.

The current implementation is the **theme panel**. The same pattern can be adapted for:
filters, quick settings, account tools, contextual help, notifications, debug controls,
and floating action menus.

---

## What This Component Is

The floating card is a compact UI panel that:

- stays snapped to the right edge of the page
- shows only a small handle when closed
- keeps the handle vertically centered against the visible card
- slides in as a compact panel when opened
- keeps its bottom edge fixed while growing upward as content is added
- caps its height to respect the top and bottom viewport margin
- scrolls internally when content exceeds that max height
- keeps the main page layout untouched
- works well for small controls that should stay globally available without taking permanent page space

---

## Where It Lives in This Repo

| Part | File |
|---|---|
| Runtime module | `public/js/theme.js` |
| Shell and panel CSS | `public/css/main.css` |

---

## Architecture

The pattern has three parts.

### 1. Bootstrap (inline script per HTML page)

A small inline script sets the initial theme before the page paints, preventing a flash.
This bootstrap is theme-specific. The floating card shell itself does not depend on it.

### 2. Runtime Module (`public/js/theme.js`)

- Injects the floating card markup into the page on load
- Manages open and closed state
- Updates ARIA state, button labels, and focus
- Syncs with `prefers-color-scheme` when no saved override exists

### 3. Shell CSS (`public/css/main.css`)

- Positions the card fixed to the right edge
- Hides the panel behind the viewport in the closed state (only the handle is visible)
- Controls the slide animation
- Sizes the panel, handle, and card body

---

## CSS Structure

Copy this shape and rename the selectors for your feature.

```css
/* Fixed overlay — does not block the page by default */
.theme-panel {
  position: fixed;
  inset: 0;
  z-index: 1300;
  pointer-events: none;
}

/* Slide-in container */
.theme-card {
  --theme-handle-width: 2.35rem;
  --theme-panel-width: min(36.5vw, 19.45rem);
  --theme-panel-offset: max(10vh, 1.5rem);
  position: absolute;
  right: 0;
  bottom: var(--theme-panel-offset);
  display: flex;
  align-items: center;
  width: calc(var(--theme-panel-width) + var(--theme-handle-width));
  overflow: hidden;
  pointer-events: auto;
  transform: translateX(var(--theme-panel-width));
  transition: transform 0.24s ease;
}

/* Open state — moves the whole card into view */
.theme-panel.is-open .theme-card {
  transform: translateX(0);
}

/* Visible handle snapped to the right edge */
.theme-handle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 var(--theme-handle-width);
  width: var(--theme-handle-width);
  height: var(--theme-handle-width);
  border: 1px solid var(--card-border);
  border-right: 0;
  border-radius: 12px 0 0 12px;
  background: var(--surface-strong);
  color: var(--text);
  cursor: pointer;
}

/* Panel interior — width, max height, internal scroll */
.theme-card-body {
  align-self: flex-end;
  display: grid;
  gap: 0.9rem;
  width: var(--theme-panel-width);
  max-width: calc(100vw - var(--theme-handle-width));
  max-height: calc(100vh - (var(--theme-panel-offset) * 2));
  overflow-y: auto;
  scrollbar-gutter: stable;
  padding: 1rem 1rem 1.08rem;
  border: 1px solid var(--card-border);
  border-right: 0;
  border-radius: 12px 0 0 12px;
  background: var(--surface-elevated);
}

/* Disable interaction while closed */
.theme-card-body[aria-hidden="true"] {
  pointer-events: none;
}

.theme-card-title {
  margin: 0;
  font-size: 1.06rem;
}

.theme-mode-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 2.45rem;
  width: 100%;
  padding: 0.65rem 0.8rem;
  border: 1px solid var(--line);
  border-radius: 8px;
  background: var(--surface-strong);
  color: var(--text);
}
```

### What Each CSS Block Does

| Selector | Responsibility |
|---|---|
| `.theme-panel` | Fixed full-screen layer; `pointer-events: none` keeps it from blocking the page |
| `.theme-card` | Slide-in container; owns the fixed bottom offset and handle alignment |
| `.theme-panel.is-open .theme-card` | Moves the whole card into view via `translateX(0)` |
| `.theme-handle` | Visible snapped control on the right edge |
| `.theme-card-body` | Panel width, max height, bottom alignment, upward growth, internal scrolling |
| `.theme-card-body[aria-hidden="true"]` | Disables interaction while the panel is closed |
| `.theme-card-title` | Styles the internal heading |
| `.theme-mode-button` | Styles the primary action button inside the panel |

---

## JavaScript Structure (`theme.js`)

The current module mixes floating-card shell behavior with theme-specific behavior.

```js
// Theme state
const STORAGE_KEY = "portfolio-theme";
const DARK_QUERY  = "(prefers-color-scheme: dark)";

function getSystemTheme()      {}  // reads browser color preference
function getStoredTheme()      {}  // reads saved theme from localStorage
function getActiveTheme()      {}  // reads currently applied theme from document
function getThemeLabel(theme)  {}  // converts theme state into button text
function applyTheme(theme)     {}  // applies theme to document.documentElement
function persistTheme(theme)   {}  // saves the chosen theme

// Floating card shell
function createThemePanel()           {}  // injects markup into the page
function setPanelOpen(panel, isOpen)  {}  // toggles open/closed: class, ARIA, focus, arrow
function updateThemeUi(panel)         {}  // updates button label for next action
function bindSystemThemeSync(panel)   {}  // keeps UI in sync with system changes

// Entry point
export function initTheme() {}  // wires everything together, attaches event handlers
```

### Reusable Shell Functions

These belong to the floating-card pattern, not to the theme feature:

- `createThemePanel()` — injects the shell markup
- `setPanelOpen()` — open/close state, ARIA, and focus handoff
- `Escape` key close listener
- Handle state updates (arrow direction, `aria-expanded`, `aria-label`)

### Theme-Specific Functions

These belong to the theme feature and should not be in a generic floating-card module:

- `STORAGE_KEY`, `getSystemTheme()`, `getStoredTheme()`, `applyTheme()`, `persistTheme()`, `updateThemeUi()`

If you extract this into a generic module, move the shell functions to `floating-card.js`
and leave the theme functions in `theme.js`.

---

## Recommended Generic Split

If you need more than one floating panel, split into two modules:

### `floating-card.js` (generic shell)

```js
function createFloatingCard()              {}  // renders the shell
function setFloatingCardOpen(card, isOpen) {}  // manages ARIA state, open/close, focus
function bindFloatingCardKeyboard(card)    {}  // Escape close, keyboard navigation
```

Knows: how to render the shell, open and close it, manage ARIA state, and move focus.
Does not know: what the panel is for, what its title says, or what its button does.

### Feature module (e.g. `theme-panel.js`)

```js
function updateFeatureUi(card)  {}  // updates title and button label
function runFeatureAction()     {}  // the actual feature behavior
export function initFeatureCard() {}
```

Knows: the title, button label, feature action, and what data it reads and writes.
Does not know: how the shell slides in or manages ARIA.

---

## Reuse: Option 1 — Copy and Rename

Best when you want the exact same interaction and a fast starting point.

1. Copy the CSS shell above into your stylesheet. Rename every selector for your feature.
2. Copy the JavaScript shell below into your module.
3. Replace the default panel content (title and button) with your feature content.
4. Replace the placeholder callback with the real feature action.
5. Rename every selector and `data-*` attribute so CSS, markup, and JavaScript all match.

**Generic JavaScript shell:**

```js
function createFeaturePanel() {
  const existing = document.querySelector("[data-feature-panel]");
  if (existing) return existing;

  const panel = document.createElement("div");
  panel.className = "feature-panel";
  panel.dataset.featurePanel = "";
  panel.innerHTML = `
    <aside class="feature-card" data-feature-card>
      <button
        class="feature-handle"
        type="button"
        aria-controls="feature-card-body"
        aria-expanded="false"
        aria-label="Open panel"
        data-feature-handle
      >
        <span aria-hidden="true" data-feature-arrow>&lt;</span>
      </button>
      <div
        class="feature-card-body"
        id="feature-card-body"
        aria-hidden="true"
        data-feature-body
      >
        <h2 class="feature-card-title">Panel Title</h2>
        <button class="feature-action-button" type="button" data-feature-action>
          Primary action
        </button>
      </div>
    </aside>
  `;

  document.body.append(panel);
  return panel;
}

function setFeaturePanelOpen(panel, isOpen) {
  const handle       = panel.querySelector("[data-feature-handle]");
  const body         = panel.querySelector("[data-feature-body]");
  const actionButton = panel.querySelector("[data-feature-action]");
  const arrow        = panel.querySelector("[data-feature-arrow]");

  panel.classList.toggle("is-open", isOpen);
  handle.setAttribute("aria-expanded", String(isOpen));
  handle.setAttribute("aria-label", isOpen ? "Close panel" : "Open panel");
  body.setAttribute("aria-hidden", String(!isOpen));
  actionButton.tabIndex = isOpen ? 0 : -1;
  arrow.textContent = isOpen ? ">" : "<";

  if (isOpen) { actionButton.focus(); return; }
  handle.focus();
}

export function initFeaturePanel() {
  const panel        = createFeaturePanel();
  const handle       = panel.querySelector("[data-feature-handle]");
  const actionButton = panel.querySelector("[data-feature-action]");

  setFeaturePanelOpen(panel, false);

  handle.addEventListener("click", () => {
    setFeaturePanelOpen(panel, !panel.classList.contains("is-open"));
  });

  actionButton.addEventListener("click", () => {
    // Replace with the real feature action.
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && panel.classList.contains("is-open")) {
      setFeaturePanelOpen(panel, false);
    }
  });
}
```

---

## Reuse: Option 2 — Generic Module Extract

Best when more than one panel will exist in the same project.

Refactor into:

- `floating-card.css` — renamed selector tokens, shared shell styles
- `floating-card.js` — shell behavior only
- `theme-panel.js` — theme feature logic, imports shell
- `help-panel.js`, `filter-panel.js`, etc. — other feature modules as needed

---

## Accessibility Requirements

Keep all of these in any reused version:

- Handle is keyboard reachable (in tab order, receives focus)
- Handle uses `aria-expanded` (true when open, false when closed)
- Handle label changes between open and closed states
- Panel body uses `aria-hidden` (true when closed)
- Focus moves into the card when it opens
- Focus returns to the handle when it closes
- `Escape` closes the card from anywhere on the page

Do not rely on the arrow symbol alone to communicate open/closed state.

---

## Animation Rules

- Animate only the outer panel position (`translateX` on `.theme-card`).
- Keep duration short: `0.2s` to `0.3s`.
- Do not animate body opacity separately unless you coordinate it precisely with the slide duration.
  If body opacity and slide finish at different times, the panel will appear to vanish before it closes.
- Respect `@media (prefers-reduced-motion: reduce)` — remove the transition when motion is reduced.
- The outer container owns the slide. The body owns internal scrolling. Never mix them.

---

## Integration Checklist

Use this when adding the pattern to a new project or a new panel to this one:

- [ ] Copy the panel CSS shell
- [ ] Copy or extract the panel JS shell
- [ ] Rename every selector and `data-*` attribute consistently across CSS, HTML, and JS
- [ ] Replace the default title and button label
- [ ] Replace the placeholder action callback
- [ ] Verify closed state shows only the handle
- [ ] Verify open state slides in cleanly
- [ ] Verify the bottom edge stays fixed while content is added
- [ ] Verify the card stops growing at the viewport limit
- [ ] Verify overflow scrolls inside the card body
- [ ] Verify `Escape` closes the card
- [ ] Verify focus moves into the card on open
- [ ] Verify focus returns to the handle on close
- [ ] Verify reduced motion removes the slide transition

---

## JavaScript Adaptation Checklist

When reusing the JS for another feature:

- [ ] Rename selector tokens and `data-*` attributes
- [ ] Rename button labels
- [ ] Replace the action callback
- [ ] Keep the open/close toggle logic
- [ ] Keep the `Escape` close behavior
- [ ] Keep the focus handoff logic
- [ ] Keep `aria-expanded` and `aria-hidden` management

---

## Summary

Use this floating card when you need a persistent, low-noise control that stays globally available
without occupying permanent page space.

Copy the current pattern for quick reuse with a new feature name.
Extract it into a generic module if more than one panel will exist in the same project.