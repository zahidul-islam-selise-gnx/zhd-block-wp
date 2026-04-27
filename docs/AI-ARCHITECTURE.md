# AI Extension Notes

ZHD Elementor Blocks can support OpenAI-powered layout generation without turning the plugin into an unsafe raw-JSON generator.

## Recommended model

- Keep the WordPress plugin as the runtime, renderer, and save target.
- Use OpenAI to generate a structured Elementor-friendly layout schema.
- Compile that structured output into native Elementor library templates.

## Current copilot path

- Add a service such as `includes/AI.php`.
- Send prompts plus screenshot references to an OpenAI-backed endpoint.
- Return validated JSON for sections, columns, and supported Elementor-friendly elements.
- Save the result as a plugin-managed AI draft or compile it into the Elementor library.

## Safe-now status in this repo

- The plugin now includes a safe schema service in [includes/AI.php](/Volumes/CORSAIR/Plugins/zhd-block-wp/includes/AI.php).
- Admin users can inspect the schema and save validated structured drafts from `ZHD Blocks -> AI Studio`.
- The plugin can now call the OpenAI Responses API directly, or an optional proxy endpoint, to generate a structured draft from a design brief and uploaded screenshots.
- Generated output is validated and sanitized before it is saved or compiled.
- It still does not allow arbitrary raw Elementor JSON generation.

## Cloud infrastructure

- API layer: Laravel, NestJS, or a focused WordPress REST service.
- Database: Postgres or MySQL for users, licenses, templates, and usage history.
- Storage: S3 or Cloudflare R2 for template packs, previews, and export files.
- Auth/licensing: site tokens or license keys mapped to installs.
- Queue workers: background jobs for image generation, long-running imports, or sync.
- Observability: request logging, prompt tracing, job monitoring, and webhook alerts.
