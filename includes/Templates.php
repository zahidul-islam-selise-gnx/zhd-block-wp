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
        $manifest_path = ZHD_EB_PLUGIN_PATH . 'templates/manifest.php';

        if (! is_readable($manifest_path)) {
            return array();
        }

        $manifest = require $manifest_path;

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
            return new WP_Error('zhd_elementor_missing', __('Elementor must be active before templates can be imported.', ZHD_EB_TEXT_DOMAIN));
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

        $result = $this->import_template_contents($contents, basename($path));

        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'template' => $template,
            'result'   => $result,
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public function import_template_payload(array $payload, string $file_name = 'zhd-generated-template.json')
    {
        $contents = wp_json_encode($payload, JSON_UNESCAPED_SLASHES);

        if (! is_string($contents) || '' === $contents) {
            return new WP_Error('zhd_template_encode_failed', __('The generated Elementor template could not be encoded as JSON.', ZHD_EB_TEXT_DOMAIN));
        }

        return $this->import_template_contents($contents, $file_name);
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function import_template_contents(string $contents, string $file_name)
    {
        if (! $this->dependencies->is_elementor_ready()) {
            return new WP_Error('zhd_elementor_missing', __('Elementor must be active before templates can be imported.', ZHD_EB_TEXT_DOMAIN));
        }

        if ('' === $contents) {
            return new WP_Error('zhd_template_empty', __('The template payload is empty.', ZHD_EB_TEXT_DOMAIN));
        }

        json_decode($contents, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            return new WP_Error('zhd_template_invalid_json', __('The template payload is not valid JSON.', ZHD_EB_TEXT_DOMAIN));
        }

        try {
            $result = \Elementor\Plugin::instance()->templates_manager->import_template(
                array(
                    'fileData' => base64_encode($contents),
                    'fileName' => sanitize_file_name($file_name),
                )
            );
        } catch (\Throwable $throwable) {
            return new WP_Error('zhd_template_import_failed', $throwable->getMessage());
        }

        return is_array($result) ? $result : array(
            'raw_result' => $result,
        );
    }
}
