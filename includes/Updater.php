<?php
/**
 * GitHub Releases-based plugin updater.
 *
 * @package ZHD\ElementorBlocks
 */

declare(strict_types=1);

namespace ZHD\ElementorBlocks;

use WP_Error;

final class Updater
{
    private const REPO_OWNER = 'zahidul-islam-selise-gnx';
    private const REPO_NAME = 'zhd-block-wp';
    private const CACHE_KEY = 'zhd_eb_github_release';
    private const FAILURE_CACHE_TTL = 300;
    private const SUCCESS_CACHE_TTL = 3600;

    public function __construct(private readonly Settings $settings)
    {
    }

    public function register_hooks(): void
    {
        add_filter('pre_set_site_transient_update_plugins', array($this, 'inject_plugin_update'));
        add_filter('plugins_api', array($this, 'plugins_api'), 10, 3);
    }

    public function clear_cache(): void
    {
        delete_transient(self::CACHE_KEY);
    }

    public function get_release_snapshot(bool $force = false)
    {
        if (! $force) {
            $cached = get_transient(self::CACHE_KEY);

            if (false !== $cached) {
                return $cached;
            }
        }

        $settings = $this->settings->get_plugin_settings();
        $allow_prereleases = ! empty($settings['allow_prereleases']);
        $url = 'https://api.github.com/repos/' . self::REPO_OWNER . '/' . self::REPO_NAME . '/releases';

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 12,
                'headers' => array(
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'ZHD-Elementor-Blocks/' . ZHD_EB_VERSION,
                ),
            )
        );

        if (is_wp_error($response)) {
            set_transient(self::CACHE_KEY, $response, self::FAILURE_CACHE_TTL);
            return $response;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if (! is_array($body)) {
            $error = new WP_Error('zhd_invalid_release_response', __('GitHub returned malformed release metadata.', ZHD_EB_TEXT_DOMAIN));
            set_transient(self::CACHE_KEY, $error, self::FAILURE_CACHE_TTL);
            return $error;
        }

        foreach ($body as $release) {
            if (! is_array($release)) {
                continue;
            }

            if (! $allow_prereleases && (! empty($release['draft']) || ! empty($release['prerelease']))) {
                continue;
            }

            $version = ltrim((string) ($release['tag_name'] ?? ''), 'v');

            if (! preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.\-]+)?$/', $version)) {
                continue;
            }

            $snapshot = array(
                'version'      => $version,
                'tag_name'     => (string) $release['tag_name'],
                'published_at' => (string) ($release['published_at'] ?? ''),
                'html_url'     => esc_url_raw((string) ($release['html_url'] ?? '')),
                'body'         => (string) ($release['body'] ?? ''),
                'package'      => $this->resolve_package_url($release),
            );

            set_transient(self::CACHE_KEY, $snapshot, self::SUCCESS_CACHE_TTL);
            return $snapshot;
        }

        $empty = new WP_Error('zhd_no_valid_releases', __('No valid public releases were found on GitHub.', ZHD_EB_TEXT_DOMAIN));
        set_transient(self::CACHE_KEY, $empty, self::FAILURE_CACHE_TTL);
        return $empty;
    }

    public function inject_plugin_update(object $transient): object
    {
        if (empty($transient->checked) || ! is_array($transient->checked)) {
            return $transient;
        }

        $release = $this->get_release_snapshot();

        if (is_wp_error($release)) {
            return $transient;
        }

        if (version_compare($release['version'], ZHD_EB_VERSION, '<=')) {
            return $transient;
        }

        $transient->response[ZHD_EB_PLUGIN_BASENAME] = (object) array(
            'slug'        => ZHD_EB_PLUGIN_SLUG,
            'plugin'      => ZHD_EB_PLUGIN_BASENAME,
            'new_version' => $release['version'],
            'package'     => $release['package'],
            'tested'      => get_bloginfo('version'),
            'requires'    => ZHD_EB_MINIMUM_WP_VERSION,
            'requires_php'=> ZHD_EB_MINIMUM_PHP_VERSION,
            'url'         => $release['html_url'],
        );

        return $transient;
    }

    public function plugins_api($result, string $action, object $args)
    {
        if ('plugin_information' !== $action || empty($args->slug) || ZHD_EB_PLUGIN_SLUG !== $args->slug) {
            return $result;
        }

        $release = $this->get_release_snapshot();

        if (is_wp_error($release)) {
            return $result;
        }

        return (object) array(
            'name'          => __('ZHD Elementor Blocks', ZHD_EB_TEXT_DOMAIN),
            'slug'          => ZHD_EB_PLUGIN_SLUG,
            'version'       => $release['version'],
            'author'        => '<a href="https://zahidui.com">Z. Islam</a>',
            'homepage'      => $release['html_url'],
            'download_link' => $release['package'],
            'requires'      => ZHD_EB_MINIMUM_WP_VERSION,
            'requires_php'  => ZHD_EB_MINIMUM_PHP_VERSION,
            'sections'      => array(
                'description' => __('CRO-focused Elementor widgets with bundled templates, design tokens, and GitHub-based updates.', ZHD_EB_TEXT_DOMAIN),
                'changelog'   => wp_kses_post(wpautop($release['body'])),
            ),
        );
    }

    /**
     * @param array<string, mixed> $release
     */
    private function resolve_package_url(array $release): string
    {
        $assets = $release['assets'] ?? array();
        $preferred_asset = ZHD_EB_PLUGIN_SLUG . '.zip';

        if (is_array($assets)) {
            foreach ($assets as $asset) {
                if (! is_array($asset)) {
                    continue;
                }

                $name = (string) ($asset['name'] ?? '');
                $url  = esc_url_raw((string) ($asset['browser_download_url'] ?? ''));

                if ('' !== $url && $preferred_asset === $name) {
                    return $url;
                }
            }

            foreach ($assets as $asset) {
                if (! is_array($asset)) {
                    continue;
                }

                $name = (string) ($asset['name'] ?? '');
                $url  = esc_url_raw((string) ($asset['browser_download_url'] ?? ''));

                if ('' !== $url && str_ends_with($name, '.zip')) {
                    return $url;
                }
            }
        }

        return esc_url_raw((string) ($release['zipball_url'] ?? ''));
    }
}
