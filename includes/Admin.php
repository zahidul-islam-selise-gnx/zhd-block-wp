<?php
/**
 * Admin UI.
 *
 * @package ZHD\ElementorBlocks
 */

declare(strict_types=1);

namespace ZHD\ElementorBlocks;

use WP_Error;

final class Admin
{
    public function __construct(
        private readonly Dependencies $dependencies,
        private readonly WidgetsLoader $widgets,
        private readonly Settings $settings,
        private readonly Updater $updater,
        private readonly AI $ai,
        private readonly ElementorCompiler $compiler
    ) {
    }

    public function register_hooks(): void
    {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_post_zhd_eb_clear_caches', array($this, 'handle_clear_caches'));
        add_action('admin_post_zhd_eb_save_ai_preset', array($this, 'handle_save_ai_preset'));
        add_action('admin_post_zhd_eb_generate_ai_preset', array($this, 'handle_generate_ai_preset'));
        add_action('admin_post_zhd_eb_compile_ai_preset', array($this, 'handle_compile_ai_preset'));
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('ZHD Blocks', ZHD_EB_TEXT_DOMAIN),
            __('ZHD Blocks', ZHD_EB_TEXT_DOMAIN),
            'manage_options',
            'zhd-elementor-blocks',
            array($this, 'render_page'),
            'dashicons-layout'
        );
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $tab = sanitize_key((string) ($_GET['tab'] ?? 'overview'));
        $tabs = array(
            'overview'  => __('Overview', ZHD_EB_TEXT_DOMAIN),
            'settings'  => __('Settings', ZHD_EB_TEXT_DOMAIN),
            'ai'        => __('AI Studio', ZHD_EB_TEXT_DOMAIN),
            'updates'   => __('Updates', ZHD_EB_TEXT_DOMAIN),
            'tools'     => __('Tools', ZHD_EB_TEXT_DOMAIN),
        );

        if (! isset($tabs[$tab])) {
            $tab = 'overview';
        }

        echo '<div class="wrap zhd-eb-admin">';
        echo '<h1>' . esc_html__('ZHD Elementor Blocks', ZHD_EB_TEXT_DOMAIN) . '</h1>';
        echo '<nav class="nav-tab-wrapper">';

        foreach ($tabs as $slug => $label) {
            $url = add_query_arg(
                array(
                    'page' => 'zhd-elementor-blocks',
                    'tab'  => $slug,
                ),
                admin_url('admin.php')
            );

            printf(
                '<a class="nav-tab %1$s" href="%2$s">%3$s</a>',
                $slug === $tab ? 'nav-tab-active' : '',
                esc_url($url),
                esc_html($label)
            );
        }

        echo '</nav>';

        $this->render_flash_notice();

        echo '<div class="zhd-eb-card">';

        switch ($tab) {
            case 'settings':
                $this->render_settings_tab();
                break;
            case 'updates':
                $this->render_updates_tab();
                break;
            case 'ai':
                $this->render_ai_tab();
                break;
            case 'tools':
                $this->render_tools_tab();
                break;
            case 'overview':
            default:
                $this->render_overview_tab();
                break;
        }

        echo '</div>';
        echo '</div>';
    }

    public function handle_clear_caches(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to clear caches.', ZHD_EB_TEXT_DOMAIN));
        }

        check_admin_referer('zhd_eb_clear_caches');

        $this->updater->clear_cache();

        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::instance()->files_manager->clear_cache();
        }

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'        => 'zhd-elementor-blocks',
                    'tab'         => 'tools',
                    'zhd_notice'  => 'success',
                    'zhd_message' => __('Plugin transients and Elementor generated files cache were cleared.', ZHD_EB_TEXT_DOMAIN),
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }

    public function handle_save_ai_preset(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to save AI presets.', ZHD_EB_TEXT_DOMAIN));
        }

        check_admin_referer('zhd_eb_save_ai_preset');

        $raw_payload = wp_unslash((string) ($_POST['ai_payload'] ?? ''));
        $decoded = json_decode($raw_payload, true);

        $args = array(
            'page' => 'zhd-elementor-blocks',
            'tab'  => 'ai',
        );

        if (! is_array($decoded)) {
            $args['zhd_notice'] = 'error';
            $args['zhd_message'] = __('AI preset validation failed: the submitted JSON could not be parsed.', ZHD_EB_TEXT_DOMAIN);
            wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
            exit;
        }

        $result = $this->ai->save_preset($decoded);

        if (is_wp_error($result)) {
            $args['zhd_notice'] = 'error';
            $args['zhd_message'] = $result->get_error_message();
        } else {
            $args['zhd_notice'] = 'success';
            $args['zhd_message'] = sprintf(
                /* translators: %s: draft title */
                __('AI draft saved: %s', ZHD_EB_TEXT_DOMAIN),
                (string) $result['title']
            );
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public function handle_generate_ai_preset(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to generate AI presets.', ZHD_EB_TEXT_DOMAIN));
        }

        check_admin_referer('zhd_eb_generate_ai_preset');

        $result = $this->ai->generate_preset_from_brief(
            array(
                'brief'            => wp_unslash((string) ($_POST['brief'] ?? '')),
                'business_context' => wp_unslash((string) ($_POST['business_context'] ?? '')),
                'cta_goal'         => wp_unslash((string) ($_POST['cta_goal'] ?? '')),
                'visual_direction' => wp_unslash((string) ($_POST['visual_direction'] ?? '')),
                'generation_model' => wp_unslash((string) ($_POST['generation_model'] ?? '')),
            ),
            $_FILES
        );

        $args = array(
            'page' => 'zhd-elementor-blocks',
            'tab'  => 'ai',
        );

        if (is_wp_error($result)) {
            $args['zhd_notice'] = 'error';
            $args['zhd_message'] = $result->get_error_message();
        } else {
            $args['zhd_notice'] = 'success';
            $args['zhd_message'] = sprintf(
                /* translators: %s: draft title */
                __('AI draft generated and saved: %s', ZHD_EB_TEXT_DOMAIN),
                (string) $result['title']
            );
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public function handle_compile_ai_preset(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to compile AI presets.', ZHD_EB_TEXT_DOMAIN));
        }

        check_admin_referer('zhd_eb_compile_ai_preset');

        $preset_slug = sanitize_title((string) ($_POST['preset_slug'] ?? ''));
        $result = $this->compiler->compile_preset_to_template($preset_slug);

        $args = array(
            'page' => 'zhd-elementor-blocks',
            'tab'  => 'ai',
        );

        if (is_wp_error($result)) {
            $args['zhd_notice'] = 'error';
            $args['zhd_message'] = $result->get_error_message();
        } else {
            $args['zhd_notice'] = 'success';
            $args['zhd_message'] = sprintf(
                /* translators: %s: preset title */
                __('AI draft saved to the Elementor library: %s', ZHD_EB_TEXT_DOMAIN),
                (string) ($result['preset']['title'] ?? $preset_slug)
            );
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private function render_flash_notice(): void
    {
        $message = sanitize_text_field((string) ($_GET['zhd_message'] ?? ''));
        $notice  = sanitize_key((string) ($_GET['zhd_notice'] ?? ''));

        if ('' === $message) {
            return;
        }

        if (! in_array($notice, array('success', 'warning', 'error'), true)) {
            $notice = 'info';
        }

        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr($notice),
            esc_html($message)
        );
    }

    private function render_overview_tab(): void
    {
        echo '<h2>' . esc_html__('Platform Overview', ZHD_EB_TEXT_DOMAIN) . '</h2>';
        echo '<p>' . esc_html__('This plugin is now an AI copilot for Elementor: generate visual directions from briefs and screenshots, keep drafts inside the plugin, and move approved results into the Elementor library.', ZHD_EB_TEXT_DOMAIN) . '</p>';

        echo '<div class="zhd-eb-grid">';
        echo '<div>';
        echo '<h3>' . esc_html__('Version & schema', ZHD_EB_TEXT_DOMAIN) . '</h3>';
        echo '<table class="widefat striped"><tbody>';
        echo '<tr><td>' . esc_html__('Plugin version', ZHD_EB_TEXT_DOMAIN) . '</td><td>' . esc_html(ZHD_EB_VERSION) . '</td></tr>';
        echo '<tr><td>' . esc_html__('Schema version', ZHD_EB_TEXT_DOMAIN) . '</td><td>' . esc_html((string) get_option(ZHD_EB_OPTION_SCHEMA_VERSION, ZHD_EB_SCHEMA_VERSION)) . '</td></tr>';
        echo '<tr><td>' . esc_html__('Plugin file', ZHD_EB_TEXT_DOMAIN) . '</td><td><code>' . esc_html(ZHD_EB_PLUGIN_BASENAME) . '</code></td></tr>';
        echo '</tbody></table>';
        echo '</div>';

        echo '<div>';
        echo '<h3>' . esc_html__('Dependency status', ZHD_EB_TEXT_DOMAIN) . '</h3>';
        echo '<table class="widefat striped"><tbody>';
        foreach ($this->dependencies->get_dependency_status() as $label => $value) {
            echo '<tr><td>' . esc_html(ucfirst($label)) . '</td><td>' . esc_html($value) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';

        echo '<h3>' . esc_html__('Installed widgets', ZHD_EB_TEXT_DOMAIN) . '</h3>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Widget', ZHD_EB_TEXT_DOMAIN) . '</th><th>' . esc_html__('Description', ZHD_EB_TEXT_DOMAIN) . '</th><th>' . esc_html__('Availability', ZHD_EB_TEXT_DOMAIN) . '</th></tr></thead><tbody>';
        foreach ($this->widgets->get_widget_manifest() as $widget) {
            printf(
                '<tr><td><strong>%1$s</strong><br><code>%2$s</code></td><td>%3$s</td><td>%4$s</td></tr>',
                esc_html((string) $widget['title']),
                esc_html((string) $widget['slug']),
                esc_html((string) $widget['description']),
                esc_html($widget['available'] ? __('Ready', ZHD_EB_TEXT_DOMAIN) : __('Waiting on dependency', ZHD_EB_TEXT_DOMAIN))
            );
        }
        echo '</tbody></table>';
    }

    private function render_settings_tab(): void
    {
        $plugin_settings = $this->settings->get_plugin_settings();
        $model_options = $this->ai->get_available_models();
        $selected_model = (string) $plugin_settings['openai_model'];

        echo '<h2>' . esc_html__('Plugin Settings', ZHD_EB_TEXT_DOMAIN) . '</h2>';
        echo '<p>' . esc_html__('Visual defaults are now inherited from Elementor global styles and the active theme. Use Elementor itself as the design system. The settings below are for plugin behavior only.', ZHD_EB_TEXT_DOMAIN) . '</p>';
        echo '<form method="post" action="options.php">';
        settings_fields('zhd_eb_plugin_settings');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row">' . esc_html__('OpenAI proxy URL', ZHD_EB_TEXT_DOMAIN) . '</th><td><input type="url" class="regular-text" name="' . esc_attr(ZHD_EB_OPTION_SETTINGS . '[openai_proxy_url]') . '" value="' . esc_attr((string) $plugin_settings['openai_proxy_url']) . '"><p class="description">' . esc_html__('Optional endpoint for AI-generated layout requests if you want to route calls through your own service.', ZHD_EB_TEXT_DOMAIN) . '</p></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('OpenAI API key', ZHD_EB_TEXT_DOMAIN) . '</th><td><input type="password" class="regular-text" name="' . esc_attr(ZHD_EB_OPTION_SETTINGS . '[openai_api_key]') . '" value=""><p class="description">' . esc_html($this->settings->has_managed_openai_api_key() ? __('A managed API key is already saved. Leave this blank to keep the current value, or paste a new key to replace it. You can also define ZHD_EB_OPENAI_API_KEY in wp-config.php or set OPENAI_API_KEY in the environment.', ZHD_EB_TEXT_DOMAIN) : __('Optional if you are using the direct OpenAI API. You can also define ZHD_EB_OPENAI_API_KEY in wp-config.php or set OPENAI_API_KEY in the environment.', ZHD_EB_TEXT_DOMAIN)) . '</p></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Preferred model', ZHD_EB_TEXT_DOMAIN) . '</th><td><select name="' . esc_attr(ZHD_EB_OPTION_SETTINGS . '[openai_model]') . '" class="regular-text">';
        foreach ($model_options as $model_id => $model_label) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr($model_id),
                selected($selected_model, $model_id, false),
                esc_html($model_label)
            );
        }
        echo '</select><p class="description">' . esc_html__('This is the default model for AI Studio. GPT-5.5 is the latest flagship model currently listed in OpenAI’s official docs.', ZHD_EB_TEXT_DOMAIN) . '</p></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Include prereleases', ZHD_EB_TEXT_DOMAIN) . '</th><td><label><input type="checkbox" name="' . esc_attr(ZHD_EB_OPTION_SETTINGS . '[allow_prereleases]') . '" value="1" ' . checked(! empty($plugin_settings['allow_prereleases']), true, false) . '> ' . esc_html__('Allow prerelease GitHub versions in update checks', ZHD_EB_TEXT_DOMAIN) . '</label></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('AI debug logging', ZHD_EB_TEXT_DOMAIN) . '</th><td><label><input type="checkbox" name="' . esc_attr(ZHD_EB_OPTION_SETTINGS . '[template_debug_log]') . '" value="1" ' . checked(! empty($plugin_settings['template_debug_log']), true, false) . '> ' . esc_html__('Keep room for future generation and compiler diagnostics', ZHD_EB_TEXT_DOMAIN) . '</label></td></tr>';
        echo '</tbody></table>';
        submit_button(__('Save plugin settings', ZHD_EB_TEXT_DOMAIN), 'secondary');
        echo '</form>';
    }

    private function render_updates_tab(): void
    {
        $release = $this->updater->get_release_snapshot();

        echo '<h2>' . esc_html__('GitHub Release Updates', ZHD_EB_TEXT_DOMAIN) . '</h2>';
        echo '<p>' . esc_html__('WordPress update data is driven by public GitHub Releases. Publish a tagged release with a ZIP asset for the cleanest update experience.', ZHD_EB_TEXT_DOMAIN) . '</p>';

        echo '<table class="widefat striped"><tbody>';
        echo '<tr><td>' . esc_html__('Current version', ZHD_EB_TEXT_DOMAIN) . '</td><td>' . esc_html(ZHD_EB_VERSION) . '</td></tr>';
        echo '<tr><td>' . esc_html__('Repository', ZHD_EB_TEXT_DOMAIN) . '</td><td><code>' . esc_html('https://github.com/zahidul-islam-selise-gnx/zhd-block-wp') . '</code></td></tr>';

        if ($release instanceof WP_Error) {
            echo '<tr><td>' . esc_html__('Latest release', ZHD_EB_TEXT_DOMAIN) . '</td><td>' . esc_html($release->get_error_message()) . '</td></tr>';
        } else {
            echo '<tr><td>' . esc_html__('Latest release', ZHD_EB_TEXT_DOMAIN) . '</td><td>' . esc_html((string) $release['tag_name']) . '</td></tr>';
            echo '<tr><td>' . esc_html__('Published', ZHD_EB_TEXT_DOMAIN) . '</td><td>' . esc_html((string) $release['published_at']) . '</td></tr>';
            echo '<tr><td>' . esc_html__('Download package', ZHD_EB_TEXT_DOMAIN) . '</td><td><a href="' . esc_url((string) $release['package']) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Open release package', ZHD_EB_TEXT_DOMAIN) . '</a></td></tr>';
            echo '<tr><td>' . esc_html__('Release notes', ZHD_EB_TEXT_DOMAIN) . '</td><td>' . wp_kses_post(wpautop((string) $release['body'])) . '</td></tr>';
        }

        echo '</tbody></table>';
    }

    private function render_ai_tab(): void
    {
        $presets = $this->ai->get_presets();
        $status_label = $this->ai->get_generation_configuration_label();
        $model_options = $this->ai->get_available_models();
        $preferred_model = $this->ai->get_preferred_model();

        echo '<section class="zhd-eb-ai-studio">';
        echo '<div class="zhd-eb-ai-hero">';
        echo '<div class="zhd-eb-ai-hero__content">';
        echo '<span class="zhd-eb-ai-kicker">' . esc_html__('ZHD AI Studio', ZHD_EB_TEXT_DOMAIN) . '</span>';
        echo '<h2>' . esc_html__('Future-ready layout generation, safely constrained.', ZHD_EB_TEXT_DOMAIN) . '</h2>';
        echo '<p>' . esc_html__('Generate AI design drafts from a plain-language brief, uploaded section screenshots, or both, while keeping output aligned to a compiler-safe Elementor layout schema instead of raw Elementor internals.', ZHD_EB_TEXT_DOMAIN) . '</p>';
        echo '<div class="zhd-eb-ai-meta">';
        echo '<span class="zhd-eb-ai-chip">' . esc_html__('White-mode studio UI', ZHD_EB_TEXT_DOMAIN) . '</span>';
        echo '<span class="zhd-eb-ai-chip">' . esc_html__('Structured outputs', ZHD_EB_TEXT_DOMAIN) . '</span>';
        echo '<span class="zhd-eb-ai-chip zhd-eb-ai-chip--status">' . esc_html__('Status: ', ZHD_EB_TEXT_DOMAIN) . esc_html($status_label) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="zhd-eb-ai-orb" aria-hidden="true"><span></span><span></span><span></span></div>';
        echo '</div>';

        echo '<div class="zhd-eb-ai-layout">';
        echo '<div class="zhd-eb-ai-main">';
        echo '<section class="zhd-eb-ai-panel zhd-eb-ai-panel--form">';
        echo '<div class="zhd-eb-ai-panel__head"><h3>' . esc_html__('Generate Drafts From Brief + Screenshots', ZHD_EB_TEXT_DOMAIN) . '</h3><p>' . esc_html__('Use campaign context, upload one or more section screenshots, and let the copilot create structured Elementor-ready design drafts that stay saved inside the plugin until you approve them.', ZHD_EB_TEXT_DOMAIN) . '</p></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="zhd-eb-ai-form" enctype="multipart/form-data">';
        wp_nonce_field('zhd_eb_generate_ai_preset');
        echo '<input type="hidden" name="action" value="zhd_eb_generate_ai_preset">';
        echo '<div class="zhd-eb-ai-field">';
        echo '<label for="zhd-eb-ai-brief">' . esc_html__('Design brief', ZHD_EB_TEXT_DOMAIN) . '</label>';
        echo '<textarea id="zhd-eb-ai-brief" class="large-text" name="brief" rows="7" placeholder="' . esc_attr__('Example: Use the uploaded references to create a cleaner, more premium supplement landing flow with stronger hierarchy and a more obvious CTA.', ZHD_EB_TEXT_DOMAIN) . '"></textarea>';
        echo '</div>';
        echo '<div class="zhd-eb-ai-field">';
        echo '<label for="zhd-eb-ai-screenshots">' . esc_html__('Reference screenshots', ZHD_EB_TEXT_DOMAIN) . '</label>';
        echo '<input id="zhd-eb-ai-screenshots" type="file" name="reference_screenshots[]" accept="image/png,image/jpeg,image/webp" multiple>';
        echo '<p>' . esc_html($this->ai->get_reference_limits_description()) . '</p>';
        echo '</div>';
        echo '<div class="zhd-eb-ai-form__grid">';
        echo '<div class="zhd-eb-ai-field">';
        echo '<label for="zhd-eb-ai-context">' . esc_html__('Business context', ZHD_EB_TEXT_DOMAIN) . '</label>';
        echo '<textarea id="zhd-eb-ai-context" class="large-text" name="business_context" rows="5" placeholder="' . esc_attr__('Audience, offer, tone, differentiators, compliance sensitivity, product context, or funnel stage.', ZHD_EB_TEXT_DOMAIN) . '"></textarea>';
        echo '</div>';
        echo '<div class="zhd-eb-ai-field">';
        echo '<label for="zhd-eb-ai-goal">' . esc_html__('Primary CTA goal', ZHD_EB_TEXT_DOMAIN) . '</label>';
        echo '<input id="zhd-eb-ai-goal" type="text" class="regular-text" name="cta_goal" value="" placeholder="' . esc_attr__('Example: Drive visitors to start a subscription', ZHD_EB_TEXT_DOMAIN) . '">';
        echo '<p>' . esc_html__('The generated preset is saved only after it matches the safe ZHD schema.', ZHD_EB_TEXT_DOMAIN) . '</p>';
        echo '</div>';
        echo '</div>';
        echo '<div class="zhd-eb-ai-field">';
        echo '<label for="zhd-eb-ai-model">' . esc_html__('Model for this generation', ZHD_EB_TEXT_DOMAIN) . '</label>';
        echo '<select id="zhd-eb-ai-model" name="generation_model" class="regular-text zhd-eb-ai-select">';
        foreach ($model_options as $model_id => $model_label) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr($model_id),
                selected($preferred_model, $model_id, false),
                esc_html($model_label)
            );
        }
        echo '</select>';
        echo '<p>' . esc_html__('This only affects the current draft generation. Your saved preferred model remains the default for future runs.', ZHD_EB_TEXT_DOMAIN) . '</p>';
        echo '</div>';
        echo '<div class="zhd-eb-ai-field">';
        echo '<label for="zhd-eb-ai-visual-direction">' . esc_html__('Visual direction', ZHD_EB_TEXT_DOMAIN) . '</label>';
        echo '<textarea id="zhd-eb-ai-visual-direction" class="large-text" name="visual_direction" rows="4" placeholder="' . esc_attr__('Optional: describe what to preserve or change from the screenshots, like spacing, hierarchy, trust treatment, or CTA emphasis.', ZHD_EB_TEXT_DOMAIN) . '"></textarea>';
        echo '</div>';
        submit_button(__('Generate and save draft', ZHD_EB_TEXT_DOMAIN), 'primary zhd-eb-button-primary');
        echo '</form>';
        echo '</section>';

        echo '<div class="zhd-eb-ai-code-grid">';
        echo '<section class="zhd-eb-ai-panel">';
        echo '<div class="zhd-eb-ai-panel__head"><h3>' . esc_html__('Compiler Schema', ZHD_EB_TEXT_DOMAIN) . '</h3><p>' . esc_html__('This is the exact structured shape the model is allowed to return before the compiler turns it into a native Elementor template.', ZHD_EB_TEXT_DOMAIN) . '</p></div>';
        echo '<textarea class="large-text code" rows="18" readonly>' . esc_textarea($this->ai->get_schema_json()) . '</textarea>';
        echo '</section>';

        echo '<section class="zhd-eb-ai-panel">';
        echo '<div class="zhd-eb-ai-panel__head"><h3>' . esc_html__('Example Draft Payload', ZHD_EB_TEXT_DOMAIN) . '</h3><p>' . esc_html__('Use this to understand the saved draft format or to seed manual edits.', ZHD_EB_TEXT_DOMAIN) . '</p></div>';
        echo '<textarea class="large-text code" rows="18" readonly>' . esc_textarea($this->ai->get_example_payload_json()) . '</textarea>';
        echo '</section>';
        echo '</div>';

        echo '<section class="zhd-eb-ai-panel">';
        echo '<div class="zhd-eb-ai-panel__head"><h3>' . esc_html__('Save A Structured Draft', ZHD_EB_TEXT_DOMAIN) . '</h3><p>' . esc_html__('Paste a structured payload manually if you want to validate and store a draft without generating a new one.', ZHD_EB_TEXT_DOMAIN) . '</p></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="zhd-eb-ai-form">';
        wp_nonce_field('zhd_eb_save_ai_preset');
        echo '<input type="hidden" name="action" value="zhd_eb_save_ai_preset">';
        echo '<textarea class="large-text code" name="ai_payload" rows="16">' . esc_textarea($this->ai->get_example_payload_json()) . '</textarea>';
        submit_button(__('Validate and save draft', ZHD_EB_TEXT_DOMAIN), 'secondary zhd-eb-button-secondary');
        echo '</form>';
        echo '</section>';
        echo '</div>';

        echo '<aside class="zhd-eb-ai-side">';
        echo '<section class="zhd-eb-ai-panel zhd-eb-ai-panel--sticky">';
        echo '<div class="zhd-eb-ai-panel__head"><h3>' . esc_html__('Studio Notes', ZHD_EB_TEXT_DOMAIN) . '</h3></div>';
        echo '<ul class="zhd-eb-ai-list">';
        echo '<li>' . esc_html__('The model can only return supported Elementor-friendly element types.', ZHD_EB_TEXT_DOMAIN) . '</li>';
        echo '<li>' . esc_html__('Multiple screenshots are interpreted as a top-to-bottom section sequence.', ZHD_EB_TEXT_DOMAIN) . '</li>';
        echo '<li>' . esc_html__('Raw Elementor JSON is intentionally blocked.', ZHD_EB_TEXT_DOMAIN) . '</li>';
        echo '<li>' . esc_html__('Drafts are sanitized before storage.', ZHD_EB_TEXT_DOMAIN) . '</li>';
        echo '<li>' . esc_html__('Approved drafts can be saved to the Elementor library for insertion.', ZHD_EB_TEXT_DOMAIN) . '</li>';
        echo '<li>' . esc_html__('Use a proxy later if you want central cloud control.', ZHD_EB_TEXT_DOMAIN) . '</li>';
        echo '</ul>';
        echo '</section>';
        echo '</aside>';
        echo '</div>';

        echo '<section class="zhd-eb-ai-panel zhd-eb-ai-panel--table">';
        echo '<div class="zhd-eb-ai-panel__head"><h3>' . esc_html__('Saved AI Drafts', ZHD_EB_TEXT_DOMAIN) . '</h3><p>' . esc_html__('Generated and manually validated drafts stay inside the plugin until you decide to save one to the Elementor library.', ZHD_EB_TEXT_DOMAIN) . '</p></div>';

        if (array() === $presets) {
            echo '<div class="zhd-eb-ai-empty">';
            echo '<strong>' . esc_html__('No presets yet', ZHD_EB_TEXT_DOMAIN) . '</strong>';
            echo '<p>' . esc_html__('Generate your first AI draft and it will appear here.', ZHD_EB_TEXT_DOMAIN) . '</p>';
            echo '</div>';
            echo '</section>';
            echo '</section>';
            return;
        }

        echo '<table class="widefat striped zhd-eb-ai-table"><thead><tr><th>' . esc_html__('Draft', ZHD_EB_TEXT_DOMAIN) . '</th><th>' . esc_html__('Source', ZHD_EB_TEXT_DOMAIN) . '</th><th>' . esc_html__('Sections', ZHD_EB_TEXT_DOMAIN) . '</th><th>' . esc_html__('State', ZHD_EB_TEXT_DOMAIN) . '</th><th>' . esc_html__('Created', ZHD_EB_TEXT_DOMAIN) . '</th><th>' . esc_html__('Action', ZHD_EB_TEXT_DOMAIN) . '</th></tr></thead><tbody>';
        foreach ($presets as $preset) {
            echo '<tr>';
            echo '<td><strong>' . esc_html((string) ($preset['title'] ?? '')) . '</strong><br><code>' . esc_html((string) ($preset['slug'] ?? '')) . '</code></td>';
            echo '<td>' . esc_html((string) ($preset['source'] ?? 'manual')) . '</td>';
            echo '<td>' . esc_html((string) count((array) ($preset['sections'] ?? array()))) . '</td>';
            echo '<td>' . esc_html__('Draft', ZHD_EB_TEXT_DOMAIN) . '</td>';
            echo '<td>' . esc_html((string) ($preset['created_at'] ?? '')) . '</td>';
            echo '<td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('zhd_eb_compile_ai_preset');
            echo '<input type="hidden" name="action" value="zhd_eb_compile_ai_preset">';
            echo '<input type="hidden" name="preset_slug" value="' . esc_attr((string) ($preset['slug'] ?? '')) . '">';
            submit_button(__('Save to Elementor Library', ZHD_EB_TEXT_DOMAIN), 'secondary zhd-eb-button-secondary', 'submit', false);
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</section>';
        echo '</section>';
    }

    private function render_tools_tab(): void
    {
        echo '<h2>' . esc_html__('Operations & Debug', ZHD_EB_TEXT_DOMAIN) . '</h2>';
        echo '<p>' . esc_html__('Use these tools when validating releases, flushing caches, or handing useful environment data to support or another developer.', ZHD_EB_TEXT_DOMAIN) . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('zhd_eb_clear_caches');
        echo '<input type="hidden" name="action" value="zhd_eb_clear_caches">';
        submit_button(__('Clear updater + Elementor caches', ZHD_EB_TEXT_DOMAIN), 'secondary');
        echo '</form>';

        echo '<h3>' . esc_html__('Debug snapshot', ZHD_EB_TEXT_DOMAIN) . '</h3>';
        echo '<textarea class="large-text code" rows="12" readonly>';
        echo esc_textarea(
            wp_json_encode(
                array(
                    'plugin_version' => ZHD_EB_VERSION,
                    'schema_version' => get_option(ZHD_EB_OPTION_SCHEMA_VERSION, ZHD_EB_SCHEMA_VERSION),
                    'dependencies'   => $this->dependencies->get_dependency_status(),
                    'widgets'        => $this->widgets->get_widget_manifest(),
                    'ai_schema'      => $this->ai->get_schema(),
                    'ai_presets'     => $this->ai->get_presets(),
                ),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );
        echo '</textarea>';
    }
}
