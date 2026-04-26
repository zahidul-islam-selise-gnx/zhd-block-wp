<?php
/**
 * Plugin Name: ZHD Elementor Blocks
 * Plugin URI:  https://github.com/zahidul-islam-selise-gnx/zhd-block-wp
 * Description: CRO-focused Elementor widgets, bundled templates, design tokens, and GitHub-powered updates for client delivery.
 * Version:     1.0.0
 * Author:      Z. Islam
 * Author URI:  https://zahidui.com
 * Text Domain: zhd-elementor-blocks
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Update URI: https://github.com/zahidul-islam-selise-gnx/zhd-block-wp
 *
 * @package ZHD\ElementorBlocks
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('ZHD_EB_VERSION', '1.0.0');
define('ZHD_EB_SCHEMA_VERSION', '1');
define('ZHD_EB_PLUGIN_SLUG', 'zhd-elementor-blocks');
define('ZHD_EB_PLUGIN_FILE', __FILE__);
define('ZHD_EB_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('ZHD_EB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ZHD_EB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZHD_EB_TEXT_DOMAIN', 'zhd-elementor-blocks');
define('ZHD_EB_OPTION_TOKENS', 'zhd_eb_design_tokens');
define('ZHD_EB_OPTION_SETTINGS', 'zhd_eb_plugin_settings');
define('ZHD_EB_OPTION_AI_PRESETS', 'zhd_eb_ai_presets');
define('ZHD_EB_OPTION_SCHEMA_VERSION', 'zhd_eb_schema_version');
define('ZHD_EB_MINIMUM_WP_VERSION', '6.5');
define('ZHD_EB_MINIMUM_PHP_VERSION', '8.1');
define('ZHD_EB_MINIMUM_ELEMENTOR_VERSION', '3.20.0');
define('ZHD_EB_MINIMUM_WOOCOMMERCE_VERSION', '8.0.0');

spl_autoload_register(
    static function (string $class): void {
        $prefix = 'ZHD\\ElementorBlocks\\';

        if (! str_starts_with($class, $prefix)) {
            return;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $candidate_paths = array(
            ZHD_EB_PLUGIN_PATH . 'includes/' . $relative . '.php',
        );

        if (str_starts_with($relative, 'Widgets/')) {
            $candidate_paths[] = ZHD_EB_PLUGIN_PATH . 'widgets/' . basename($relative) . '.php';
        }

        foreach ($candidate_paths as $path) {
            if (is_readable($path)) {
                require_once $path;
                return;
            }
        }
    }
);

register_activation_hook(
    __FILE__,
    static function (): void {
        \ZHD\ElementorBlocks\Plugin::activate();
    }
);

add_action(
    'plugins_loaded',
    static function (): void {
        \ZHD\ElementorBlocks\Plugin::instance()->boot();
    }
);
