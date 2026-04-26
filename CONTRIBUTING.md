# Contributing

## Branching

- `main` is the stable branch.
- `dev` is the active development branch.

## Expectations

- Keep everything namespaced and prefixed with `zhd_`.
- Escape frontend output and sanitize stored settings.
- Prefer shared widget helpers over duplicating control/render logic.
- Keep widget markup lean and asset loading conditional.

## Before opening a release PR

- Run PHP syntax checks across the plugin.
- Test activation with Elementor active and inactive.
- Test WooCommerce-aware widgets with and without product context.
- Verify template import from the plugin admin page.
- Confirm updater behavior against a tagged GitHub Release when possible.

