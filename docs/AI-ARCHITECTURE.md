# AI Extension Notes

ZHD Elementor Blocks can support OpenAI-powered layout generation without turning the plugin into an unsafe raw-JSON generator.

## Recommended model

- Keep the WordPress plugin as the runtime, renderer, and save target.
- Use OpenAI to generate structured configuration for known ZHD widgets.
- Convert that structured output into widget settings or bundled-template style JSON.

## Safe v1.5 path

- Add a service such as `includes/AI.php`.
- Send prompts plus the ZHD widget schema to an OpenAI-backed endpoint.
- Return validated JSON for widgets like `zhd_hero_cro`, `zhd_product_buy_box`, and `zhd_trust_badges`.
- Save the result as a plugin-managed preset or imported Elementor template.

## Safe-now status in this repo

- The plugin now includes a safe schema service in [includes/AI.php](/Volumes/CORSAIR/Plugins/zhd-block-wp/includes/AI.php).
- Admin users can inspect the schema and save validated structured presets from `ZHD Blocks -> AI Studio`.
- The plugin can now call the OpenAI Responses API directly, or an optional proxy endpoint, to generate a safe structured preset from a design brief.
- Generated output is validated and sanitized before it is saved.
- It still does not allow arbitrary raw Elementor JSON generation.

## Cloud infrastructure

- API layer: Laravel, NestJS, or a focused WordPress REST service.
- Database: Postgres or MySQL for users, licenses, templates, and usage history.
- Storage: S3 or Cloudflare R2 for template packs, previews, and export files.
- Auth/licensing: site tokens or license keys mapped to installs.
- Queue workers: background jobs for image generation, long-running imports, or sync.
- Observability: request logging, prompt tracing, job monitoring, and webhook alerts.
