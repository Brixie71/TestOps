{{-- Floating theme card (light/dark switcher).
     Styles live in resources/css/app.css ("Theme floating card" section,
     pattern: .ai/templates/FLOATING-CARD.md) and are keyed off html.dark,
     so this component works on any page. Chrome selects the icon; the card
     lets the user pick and persists the choice in localStorage ('testops-theme'). --}}

<div class="theme-panel" data-theme-panel>
    <aside class="theme-card">
        <button
            type="button"
            class="theme-handle"
            data-theme-handle
            aria-controls="theme-card-body"
            aria-expanded="false"
            aria-label="Open theme panel"
        >
            <svg class="theme-icon-sun h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="4" />
                <path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
            </svg>
            <svg class="theme-icon-moon h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
            </svg>
        </button>

        <div class="theme-card-body" id="theme-card-body" aria-hidden="true" data-theme-body>
            <h2 class="theme-card-title">Theme</h2>
            <p class="theme-card-hint">
                Choose how TestOps looks on this device. Your choice is saved locally.
            </p>

            <div class="grid gap-2.5">
                <button type="button" class="theme-mode-button" data-theme-action="light" aria-pressed="true">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="4" />
                        <path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
                    </svg>
                    Light
                </button>
                <button type="button" class="theme-mode-button" data-theme-action="dark" aria-pressed="false">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
                    </svg>
                    Dark
                </button>
            </div>
        </div>
    </aside>
</div>

{{-- Theme card behaviour — plain JavaScript, no framework required --}}
<script>
    (function () {
        var STORAGE_KEY = 'testops-theme';
        var DARK_QUERY = '(prefers-color-scheme: dark)';

        // ---- Theme state -------------------------------------------------
        function getStoredTheme() {
            try {
                return localStorage.getItem(STORAGE_KEY);
            } catch (error) {
                return null;
            }
        }

        function persistTheme(theme) {
            try {
                localStorage.setItem(STORAGE_KEY, theme);
            } catch (error) {}
        }

        function systemTheme() {
            return window.matchMedia(DARK_QUERY).matches ? 'dark' : 'light';
        }

        function applyTheme(theme) {
            document.documentElement.classList.toggle('dark', theme === 'dark');
        }

        // Restore the theme again here so it works even if the inline
        // bootstrap did not run (e.g. script disabled mid-page load).
        applyTheme(getStoredTheme() || systemTheme());

        // ---- Theme floating card ----------------------------------------
        var panel = document.querySelector('[data-theme-panel]');

        if (!panel) {
            return;
        }

        var handle = panel.querySelector('[data-theme-handle]');
        var body = panel.querySelector('[data-theme-body]');
        var modeButtons = {
            light: panel.querySelector('[data-theme-action="light"]'),
            dark: panel.querySelector('[data-theme-action="dark"]')
        };

        function updateModeButtons() {
            var active = document.documentElement.classList.contains('dark') ? 'dark' : 'light';

            modeButtons.light.setAttribute('aria-pressed', active === 'light' ? 'true' : 'false');
            modeButtons.dark.setAttribute('aria-pressed', active === 'dark' ? 'true' : 'false');
        }

        function setPanelOpen(panel, isOpen, shouldFocus) {
            if (shouldFocus === undefined) {
                shouldFocus = true;
            }

            panel.classList.toggle('is-open', isOpen);
            handle.setAttribute('aria-expanded', String(isOpen));
            handle.setAttribute('aria-label', isOpen ? 'Close theme panel' : 'Open theme panel');
            body.setAttribute('aria-hidden', String(!isOpen));

            Object.keys(modeButtons).forEach(function (key) {
                modeButtons[key].tabIndex = isOpen ? 0 : -1;
            });

            // Focus moves into the card when it opens, and returns
            // to the handle when it closes (skip on initial setup).
            if (!shouldFocus) {
                return;
            }

            if (isOpen) {
                var active = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
                modeButtons[active].focus();
                return;
            }

            handle.focus();
        }

        function chooseMode(theme) {
            applyTheme(theme);
            persistTheme(theme);
            updateModeButtons();
        }

        setPanelOpen(panel, false, false);
        Object.keys(modeButtons).forEach(function (key) {
            modeButtons[key].addEventListener('click', function () {
                chooseMode(key);
            });
        });

        handle.addEventListener('click', function () {
            setPanelOpen(panel, !panel.classList.contains('is-open'));
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && panel.classList.contains('is-open')) {
                setPanelOpen(panel, false);
            }
        });

        // Follow system preference changes while no override is saved.
        var darkQuery = window.matchMedia(DARK_QUERY);

        function onSystemChange(event) {
            if (getStoredTheme()) {
                return;
            }

            applyTheme(event.matches ? 'dark' : 'light');
            updateModeButtons();
        }

        if (typeof darkQuery.addEventListener === 'function') {
            darkQuery.addEventListener('change', onSystemChange);
        } else if (typeof darkQuery.addListener === 'function') {
            darkQuery.addListener(onSystemChange);
        }

        updateModeButtons();
    })();
</script>
