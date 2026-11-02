---
paths:
  - composer.json
---

# Composer Json

## Approved stack: Laravel 13, PHP 8.5, Blade + Tailwind CSS 4, PHPUnit 12
Pin the stack to the approved versions: laravel/framework ^13.17 on PHP 8.5, with explicit return types and parameter type hints on every method. Frontend is Blade templates styled with Tailwind CSS 4 only (no Inertia, Livewire, Vue/React): utilities via @tailwindcss/vite, logical properties (ps-4, text-start) over directional ones. Tests run on PHPUnit 12 via php artisan test. composer.json is the source of truth for every version.
