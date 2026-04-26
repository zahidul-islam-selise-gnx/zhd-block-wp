<?php
/**
 * Plugin settings.
 *
 * @package ZHD\ElementorBlocks
 */

declare(strict_types=1);

namespace ZHD\ElementorBlocks;

final class Settings
{
    public function register_hooks(): void
    {
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings(): void
    {
        register_setting(
            'zhd_eb_plugin_settings',
            ZHD_EB_OPTION_SETTINGS,
            array(
                'type'              => 'array',
                'sanitize_callback' => array($this, 'sanitize_plugin_settings'),
                'default'           => $this->get_default_plugin_settings(),
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitize_plugin_settings(array $settings): array
    {
        $defaults = $this->get_default_plugin_settings();
        $existing = $this->get_plugin_settings();
        $api_key  = sanitize_text_field($settings['openai_api_key'] ?? '');

        if ('' === $api_key && ! empty($existing['openai_api_key'])) {
            $api_key = (string) $existing['openai_api_key'];
        }

        return array(
            'allow_prereleases'  => ! empty($settings['allow_prereleases']),
            'openai_api_key'     => $api_key,
            'openai_proxy_url'   => esc_url_raw($settings['openai_proxy_url'] ?? $defaults['openai_proxy_url']),
            'openai_model'       => sanitize_text_field($settings['openai_model'] ?? $defaults['openai_model']),
            'template_debug_log' => ! empty($settings['template_debug_log']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function get_plugin_settings(): array
    {
        return wp_parse_args((array) get_option(ZHD_EB_OPTION_SETTINGS, array()), $this->get_default_plugin_settings());
    }

    public function get_openai_api_key(): string
    {
        if (defined('ZHD_EB_OPENAI_API_KEY') && is_string(ZHD_EB_OPENAI_API_KEY) && '' !== ZHD_EB_OPENAI_API_KEY) {
            return ZHD_EB_OPENAI_API_KEY;
        }

        $env_key = getenv('OPENAI_API_KEY');
        if (is_string($env_key) && '' !== $env_key) {
            return $env_key;
        }

        $settings = $this->get_plugin_settings();
        return (string) ($settings['openai_api_key'] ?? '');
    }

    public function has_managed_openai_api_key(): bool
    {
        $settings = $this->get_plugin_settings();

        return ! empty($settings['openai_api_key']);
    }

    /**
     * @return array<string, mixed>
     */
    public function get_default_plugin_settings(): array
    {
        return array(
            'allow_prereleases'  => false,
            'openai_api_key'     => '',
            'openai_proxy_url'   => '',
            'openai_model'       => 'gpt-5.5',
            'template_debug_log' => false,
        );
    }
}
