<?php
/**
 * Dependency checks and admin notices.
 *
 * @package ZHD\ElementorBlocks
 */

declare(strict_types=1);

namespace ZHD\ElementorBlocks;

final class Dependencies
{
    public function register_hooks(): void
    {
        add_action('admin_notices', array($this, 'render_admin_notices'));
    }

    public function render_admin_notices(): void
    {
        if (! current_user_can('activate_plugins')) {
            return;
        }

        foreach ($this->get_admin_notices() as $notice) {
            printf(
                '<div class="notice notice-%1$s"><p>%2$s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }
    }

    /**
     * @return array<int, array{type: string, message: string}>
     */
    public function get_admin_notices(): array
    {
        $notices = array();

        if (! $this->is_elementor_installed()) {
            $notices[] = array(
                'type'    => 'warning',
                'message' => __('ZHD Elementor Blocks is installed, but Elementor is not active. The AI copilot workspace and Elementor-library compiler are paused until Elementor is available.', ZHD_EB_TEXT_DOMAIN),
            );
        } elseif (! $this->is_elementor_version_supported()) {
            $notices[] = array(
                'type'    => 'error',
                'message' => sprintf(
                    /* translators: %s: minimum Elementor version */
                    __('ZHD Elementor Blocks requires Elementor %s or newer.', ZHD_EB_TEXT_DOMAIN),
                    ZHD_EB_MINIMUM_ELEMENTOR_VERSION
                ),
            );
        }

        return $notices;
    }

    public function is_elementor_installed(): bool
    {
        return class_exists('\Elementor\Plugin');
    }

    public function is_elementor_ready(): bool
    {
        return $this->is_elementor_installed() && $this->is_elementor_version_supported();
    }

    public function is_elementor_version_supported(): bool
    {
        if (! defined('ELEMENTOR_VERSION')) {
            return false;
        }

        return version_compare(ELEMENTOR_VERSION, ZHD_EB_MINIMUM_ELEMENTOR_VERSION, '>=');
    }

    public function is_woocommerce_ready(): bool
    {
        if (! class_exists('\WooCommerce') || ! defined('WC_VERSION')) {
            return false;
        }

        return version_compare(WC_VERSION, ZHD_EB_MINIMUM_WOOCOMMERCE_VERSION, '>=');
    }

    /**
     * @param array<string, bool> $requirements
     */
    public function are_widget_requirements_met(array $requirements): bool
    {
        if (($requirements['elementor'] ?? true) && ! $this->is_elementor_ready()) {
            return false;
        }

        if (($requirements['woocommerce'] ?? false) && ! $this->is_woocommerce_ready()) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    public function get_dependency_status(): array
    {
        return array(
            'wordpress'   => $this->version_label(get_bloginfo('version'), ZHD_EB_MINIMUM_WP_VERSION),
            'php'         => $this->version_label(PHP_VERSION, ZHD_EB_MINIMUM_PHP_VERSION),
            'elementor'   => $this->is_elementor_installed()
                ? $this->version_label((string) (defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '0'), ZHD_EB_MINIMUM_ELEMENTOR_VERSION)
                : __('Missing', ZHD_EB_TEXT_DOMAIN),
        );
    }

    private function version_label(string $installed, string $minimum): string
    {
        return sprintf(
            /* translators: 1: installed version, 2: minimum version */
            __('%1$s (min %2$s)', ZHD_EB_TEXT_DOMAIN),
            $installed,
            $minimum
        );
    }
}
