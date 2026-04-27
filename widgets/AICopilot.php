<?php
/**
 * Elementor AI copilot widget.
 *
 * @package ZHD\ElementorBlocks
 */

declare(strict_types=1);

namespace ZHD\ElementorBlocks\Widgets;

use Elementor\Controls_Manager;

final class AICopilot extends BaseWidget
{
    /**
     * @return array<string, mixed>
     */
    protected static function metadata(): array
    {
        return array(
            'slug'           => 'zhd_ai_copilot',
            'title'          => 'ZHD AI Copilot',
            'description'    => 'Elementor workspace block for generating AI drafts, selecting saved concepts, and moving approved designs into the Elementor library.',
            'icon'           => 'eicon-editor-code',
            'keywords'       => array('ai', 'copilot', 'elementor', 'design'),
            'categories'     => array('zhd-ai-copilot'),
            'style_handles'  => array('zhd-eb-widget-ai-copilot'),
            'script_handles' => array(),
            'requirements'   => array(
                'elementor'   => true,
                'woocommerce' => false,
            ),
        );
    }

    protected function register_content_controls(): void
    {
        $this->start_controls_section(
            'section_workspace',
            array(
                'label' => __('Copilot Workspace', ZHD_EB_TEXT_DOMAIN),
            )
        );

        $this->add_control(
            'linked_preset',
            array(
                'label'   => __('Linked draft', ZHD_EB_TEXT_DOMAIN),
                'type'    => Controls_Manager::SELECT,
                'options' => $this->get_preset_options(),
                'default' => '',
            )
        );

        $this->add_control(
            'workspace_note',
            array(
                'label'       => __('Local note', ZHD_EB_TEXT_DOMAIN),
                'type'        => Controls_Manager::TEXTAREA,
                'label_block' => true,
                'placeholder' => __('Optional note for this AI workspace block, like “Use this area for hero exploration.”', ZHD_EB_TEXT_DOMAIN),
            )
        );

        $this->add_control(
            'workspace_help',
            array(
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => wp_kses_post(
                    sprintf(
                        __('Generate and manage drafts from <a href="%s" target="_blank" rel="noopener noreferrer">ZHD Blocks → AI Studio</a>, then return here and link the draft you want this workspace to represent.', ZHD_EB_TEXT_DOMAIN),
                        esc_url(admin_url('admin.php?page=zhd-elementor-blocks&tab=ai'))
                    )
                ),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            )
        );

        $this->end_controls_section();
    }

    /**
     * @return array<string, string>
     */
    private function get_preset_options(): array
    {
        $options = array(
            '' => __('No draft linked yet', ZHD_EB_TEXT_DOMAIN),
        );

        $presets = get_option(ZHD_EB_OPTION_AI_PRESETS, array());

        if (! is_array($presets)) {
            return $options;
        }

        foreach ($presets as $slug => $preset) {
            if (! is_array($preset)) {
                continue;
            }

            $options[(string) $slug] = sanitize_text_field((string) ($preset['title'] ?? $slug));
        }

        return $options;
    }

    protected function render(): void
    {
        if (! $this->is_editor_context()) {
            return;
        }

        $settings = $this->get_settings_for_display();
        $linked_preset = sanitize_title((string) ($settings['linked_preset'] ?? ''));
        $workspace_note = sanitize_textarea_field((string) ($settings['workspace_note'] ?? ''));
        $presets = get_option(ZHD_EB_OPTION_AI_PRESETS, array());
        $preset = is_array($presets) && isset($presets[$linked_preset]) && is_array($presets[$linked_preset]) ? $presets[$linked_preset] : null;
        $ai_studio_url = admin_url('admin.php?page=zhd-elementor-blocks&tab=ai');

        $this->start_widget_shell('zhd-eb-ai-copilot');

        echo '<div class="zhd-eb-ai-copilot__panel">';
        echo '<div class="zhd-eb-ai-copilot__eyebrow">' . esc_html__('ZHD AI Copilot Workspace', ZHD_EB_TEXT_DOMAIN) . '</div>';
        echo '<h3 class="zhd-eb-ai-copilot__title">' . esc_html__('Generate drafts in AI Studio, then link the one you want to develop here.', ZHD_EB_TEXT_DOMAIN) . '</h3>';
        echo '<p class="zhd-eb-ai-copilot__body">' . esc_html__('This block is an editor-side workspace marker. Drafts stay saved inside the plugin until you approve one and save it to the Elementor library.', ZHD_EB_TEXT_DOMAIN) . '</p>';

        if (is_array($preset)) {
            echo '<div class="zhd-eb-ai-copilot__draft">';
            echo '<strong>' . esc_html((string) ($preset['title'] ?? '')) . '</strong>';
            echo '<span>' . esc_html(
                sprintf(
                    /* translators: %s: section count */
                    __('%s sections in this saved draft', ZHD_EB_TEXT_DOMAIN),
                    (string) count((array) ($preset['sections'] ?? array()))
                )
            ) . '</span>';
            echo '</div>';
        } else {
            echo '<div class="zhd-eb-ai-copilot__draft zhd-eb-ai-copilot__draft--empty">';
            echo '<strong>' . esc_html__('No draft linked yet', ZHD_EB_TEXT_DOMAIN) . '</strong>';
            echo '<span>' . esc_html__('Open AI Studio to generate a few directions, then come back and select one here.', ZHD_EB_TEXT_DOMAIN) . '</span>';
            echo '</div>';
        }

        if ('' !== $workspace_note) {
            echo '<div class="zhd-eb-ai-copilot__note">';
            echo '<strong>' . esc_html__('Workspace note', ZHD_EB_TEXT_DOMAIN) . '</strong>';
            echo '<p>' . esc_html($workspace_note) . '</p>';
            echo '</div>';
        }

        printf(
            '<p class="zhd-eb-ai-copilot__action"><a class="zhd-eb-button" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></p>',
            esc_url($ai_studio_url),
            esc_html__('Open AI Studio', ZHD_EB_TEXT_DOMAIN)
        );

        echo '</div>';

        $this->end_widget_shell();
    }

    private function is_editor_context(): bool
    {
        return class_exists('\Elementor\Plugin')
            && method_exists(\Elementor\Plugin::instance()->editor, 'is_edit_mode')
            && \Elementor\Plugin::instance()->editor->is_edit_mode();
    }
}
