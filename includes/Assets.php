<?php
/**
 * Asset registration.
 *
 * @package ZHD\ElementorBlocks
 */

declare(strict_types=1);

namespace ZHD\ElementorBlocks;

final class Assets
{
    public function register_hooks(): void
    {
        add_action('wp_enqueue_scripts', array($this, 'register_frontend_assets'));
        add_action('elementor/frontend/after_register_styles', array($this, 'register_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    public function register_frontend_assets(): void
    {
        wp_register_style(
            'zhd-eb-frontend-base',
            ZHD_EB_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            ZHD_EB_VERSION
        );

        wp_register_style(
            'zhd-eb-widget-hero-cro',
            ZHD_EB_PLUGIN_URL . 'assets/css/widget-hero-cro.css',
            array('zhd-eb-frontend-base'),
            ZHD_EB_VERSION
        );

        wp_register_style(
            'zhd-eb-widget-product-buy-box',
            ZHD_EB_PLUGIN_URL . 'assets/css/widget-product-buy-box.css',
            array('zhd-eb-frontend-base'),
            ZHD_EB_VERSION
        );

        wp_register_style(
            'zhd-eb-widget-trust-badges',
            ZHD_EB_PLUGIN_URL . 'assets/css/widget-trust-badges.css',
            array('zhd-eb-frontend-base'),
            ZHD_EB_VERSION
        );

        wp_register_script(
            'zhd-eb-frontend',
            ZHD_EB_PLUGIN_URL . 'assets/js/frontend.js',
            array(),
            ZHD_EB_VERSION,
            true
        );
    }

    public function enqueue_admin_assets(string $hook_suffix): void
    {
        if (! str_contains($hook_suffix, 'zhd-elementor-blocks')) {
            return;
        }

        wp_enqueue_style(
            'zhd-eb-admin',
            ZHD_EB_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ZHD_EB_VERSION
        );
    }
}
