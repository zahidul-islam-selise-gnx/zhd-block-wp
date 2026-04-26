<?php
/**
 * Bundled Elementor templates.
 *
 * @package ZHD\ElementorBlocks
 */

declare(strict_types=1);

namespace ZHD\ElementorBlocks;

use WP_Error;

final class Templates
{
    public function __construct(private readonly Dependencies $dependencies)
    {
    }

    public function register_hooks(): void
    {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function get_manifest(): array
    {
        $manifest = require ZHD_EB_PLUGIN_PATH . 'templates/manifest.php';

        return (array) apply_filters('zhd_eb_template_manifest', $manifest);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_template(string $slug): ?array
    {
        $manifest = $this->get_manifest();

        return $manifest[$slug] ?? null;
    }

    public function import_template(string $slug)
    {
        if (! $this->dependencies->is_elementor_ready()) {
            return new WP_Error('zhd_elementor_missing', __('Elementor must be active before bundled templates can be imported.', ZHD_EB_TEXT_DOMAIN));
        }

        $template = $this->get_template($slug);

        if (null === $template) {
            return new WP_Error('zhd_template_missing', __('The requested template could not be found in the bundled library.', ZHD_EB_TEXT_DOMAIN));
        }

        $path = ZHD_EB_PLUGIN_PATH . ltrim((string) $template['path'], '/');

        if (! is_readable($path)) {
            return new WP_Error('zhd_template_unreadable', __('The template file is missing or unreadable.', ZHD_EB_TEXT_DOMAIN));
        }

        $contents = (string) file_get_contents($path);

        if ('' === $contents) {
            return new WP_Error('zhd_template_empty', __('The bundled template file is empty.', ZHD_EB_TEXT_DOMAIN));
        }

        json_decode($contents, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            return new WP_Error('zhd_template_invalid_json', __('The bundled template file is not valid JSON.', ZHD_EB_TEXT_DOMAIN));
        }

        try {
            $result = \Elementor\Plugin::instance()->templates_manager->import_template(
                array(
                    'fileData' => base64_encode($contents),
                    'fileName' => basename($path),
                )
            );
        } catch (\Throwable $throwable) {
            return new WP_Error('zhd_template_import_failed', $throwable->getMessage());
        }

        return array(
            'template' => $template,
            'result'   => $result,
        );
    }
}

