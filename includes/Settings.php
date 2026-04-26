<?php
/**
 * Plugin settings and design tokens.
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
            'zhd_eb_design_system',
            ZHD_EB_OPTION_TOKENS,
            array(
                'type'              => 'array',
                'sanitize_callback' => array($this, 'sanitize_tokens'),
                'default'           => $this->get_default_tokens(),
            )
        );

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
    public function sanitize_tokens(array $tokens): array
    {
        $defaults = $this->get_default_tokens();

        return array(
            'primary_color'     => sanitize_hex_color($tokens['primary_color'] ?? $defaults['primary_color']) ?: $defaults['primary_color'],
            'secondary_color'   => sanitize_hex_color($tokens['secondary_color'] ?? $defaults['secondary_color']) ?: $defaults['secondary_color'],
            'surface_color'     => sanitize_hex_color($tokens['surface_color'] ?? $defaults['surface_color']) ?: $defaults['surface_color'],
            'text_color'        => sanitize_hex_color($tokens['text_color'] ?? $defaults['text_color']) ?: $defaults['text_color'],
            'muted_text_color'  => sanitize_hex_color($tokens['muted_text_color'] ?? $defaults['muted_text_color']) ?: $defaults['muted_text_color'],
            'accent_color'      => sanitize_hex_color($tokens['accent_color'] ?? $defaults['accent_color']) ?: $defaults['accent_color'],
            'font_heading'      => sanitize_text_field($tokens['font_heading'] ?? $defaults['font_heading']),
            'font_body'         => sanitize_text_field($tokens['font_body'] ?? $defaults['font_body']),
            'space_sm'          => absint($tokens['space_sm'] ?? $defaults['space_sm']),
            'space_md'          => absint($tokens['space_md'] ?? $defaults['space_md']),
            'space_lg'          => absint($tokens['space_lg'] ?? $defaults['space_lg']),
            'radius_md'         => absint($tokens['radius_md'] ?? $defaults['radius_md']),
            'radius_lg'         => absint($tokens['radius_lg'] ?? $defaults['radius_lg']),
            'shadow_soft'       => sanitize_text_field($tokens['shadow_soft'] ?? $defaults['shadow_soft']),
            'button_radius'     => absint($tokens['button_radius'] ?? $defaults['button_radius']),
            'button_padding_y'  => absint($tokens['button_padding_y'] ?? $defaults['button_padding_y']),
            'button_padding_x'  => absint($tokens['button_padding_x'] ?? $defaults['button_padding_x']),
            'badge_radius'      => absint($tokens['badge_radius'] ?? $defaults['badge_radius']),
            'badge_bg'          => sanitize_hex_color($tokens['badge_bg'] ?? $defaults['badge_bg']) ?: $defaults['badge_bg'],
            'badge_text'        => sanitize_hex_color($tokens['badge_text'] ?? $defaults['badge_text']) ?: $defaults['badge_text'],
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
    public function get_tokens(): array
    {
        return wp_parse_args((array) get_option(ZHD_EB_OPTION_TOKENS, array()), $this->get_default_tokens());
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

    public function get_tokens_css(): string
    {
        $tokens = $this->get_tokens();

        return sprintf(
            ':root{--zhd-eb-color-primary:%1$s;--zhd-eb-color-secondary:%2$s;--zhd-eb-color-surface:%3$s;--zhd-eb-color-text:%4$s;--zhd-eb-color-text-muted:%5$s;--zhd-eb-color-accent:%6$s;--zhd-eb-font-heading:%7$s;--zhd-eb-font-body:%8$s;--zhd-eb-space-sm:%9$spx;--zhd-eb-space-md:%10$spx;--zhd-eb-space-lg:%11$spx;--zhd-eb-radius-md:%12$spx;--zhd-eb-radius-lg:%13$spx;--zhd-eb-shadow-soft:%14$s;--zhd-eb-button-radius:%15$spx;--zhd-eb-button-padding-y:%16$spx;--zhd-eb-button-padding-x:%17$spx;--zhd-eb-badge-radius:%18$spx;--zhd-eb-badge-bg:%19$s;--zhd-eb-badge-text:%20$s;}',
            esc_html($tokens['primary_color']),
            esc_html($tokens['secondary_color']),
            esc_html($tokens['surface_color']),
            esc_html($tokens['text_color']),
            esc_html($tokens['muted_text_color']),
            esc_html($tokens['accent_color']),
            wp_json_encode($tokens['font_heading']),
            wp_json_encode($tokens['font_body']),
            absint($tokens['space_sm']),
            absint($tokens['space_md']),
            absint($tokens['space_lg']),
            absint($tokens['radius_md']),
            absint($tokens['radius_lg']),
            esc_html($tokens['shadow_soft']),
            absint($tokens['button_radius']),
            absint($tokens['button_padding_y']),
            absint($tokens['button_padding_x']),
            absint($tokens['badge_radius']),
            esc_html($tokens['badge_bg']),
            esc_html($tokens['badge_text'])
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function get_default_tokens(): array
    {
        return array(
            'primary_color'    => '#0f172a',
            'secondary_color'  => '#1d4ed8',
            'surface_color'    => '#ffffff',
            'text_color'       => '#0f172a',
            'muted_text_color' => '#475569',
            'accent_color'     => '#f97316',
            'font_heading'     => 'Manrope, sans-serif',
            'font_body'        => 'Inter, sans-serif',
            'space_sm'         => 12,
            'space_md'         => 20,
            'space_lg'         => 36,
            'radius_md'        => 16,
            'radius_lg'        => 28,
            'shadow_soft'      => '0 20px 60px rgba(15, 23, 42, 0.10)',
            'button_radius'    => 999,
            'button_padding_y' => 14,
            'button_padding_x' => 24,
            'badge_radius'     => 999,
            'badge_bg'         => '#eff6ff',
            'badge_text'       => '#1d4ed8',
        );
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
