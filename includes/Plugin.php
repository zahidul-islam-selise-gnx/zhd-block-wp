<?php
/**
 * Main plugin container.
 *
 * @package ZHD\ElementorBlocks
 */

declare(strict_types=1);

namespace ZHD\ElementorBlocks;

final class Plugin
{
    private static ?self $instance = null;

    /** @var array<string, object> */
    private array $services = array();

    private bool $booted = false;

    private function __construct()
    {
        $dependencies = new Dependencies();
        $settings     = new Settings();
        $ai           = new AI($settings);
        $assets       = new Assets();
        $templates    = new Templates($dependencies);
        $widgets      = new WidgetsLoader($dependencies);
        $updater      = new Updater($settings);
        $admin        = new Admin($dependencies, $widgets, $templates, $settings, $updater, $ai);

        $this->services = array(
            'dependencies' => $dependencies,
            'settings'     => $settings,
            'ai'           => $ai,
            'assets'       => $assets,
            'templates'    => $templates,
            'widgets'      => $widgets,
            'updater'      => $updater,
            'admin'        => $admin,
        );
    }

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate(): void
    {
        update_option(ZHD_EB_OPTION_SCHEMA_VERSION, ZHD_EB_SCHEMA_VERSION);
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        add_action('init', array($this, 'load_textdomain'));
        add_action('admin_init', array($this, 'maybe_upgrade'));

        foreach ($this->services as $service) {
            if (method_exists($service, 'register_hooks')) {
                $service->register_hooks();
            }
        }
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            ZHD_EB_TEXT_DOMAIN,
            false,
            dirname(ZHD_EB_PLUGIN_BASENAME) . '/languages'
        );
    }

    public function maybe_upgrade(): void
    {
        $stored_version = (string) get_option(ZHD_EB_OPTION_SCHEMA_VERSION, '0');

        if (version_compare($stored_version, ZHD_EB_SCHEMA_VERSION, '>=')) {
            return;
        }

        update_option(ZHD_EB_OPTION_SCHEMA_VERSION, ZHD_EB_SCHEMA_VERSION);
    }

    public function get(string $service): ?object
    {
        return $this->services[$service] ?? null;
    }
}
