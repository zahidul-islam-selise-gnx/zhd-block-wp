<?php
/**
 * Shared base widget.
 *
 * @package ZHD\ElementorBlocks
 */

declare(strict_types=1);

namespace ZHD\ElementorBlocks\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

abstract class BaseWidget extends Widget_Base
{
    /**
     * @return array<string, mixed>
     */
    abstract protected static function metadata(): array;

    protected function register_controls(): void
    {
        $this->register_content_controls();
        $this->register_style_controls();
    }

    protected function register_content_controls(): void
    {
    }

    protected function register_style_controls(): void
    {
    }

    public function get_name(): string
    {
        return (string) static::metadata()['slug'];
    }

    public function get_title(): string
    {
        return (string) __(static::metadata()['title'], ZHD_EB_TEXT_DOMAIN);
    }

    public function get_icon(): string
    {
        return (string) (static::metadata()['icon'] ?? 'eicon-post');
    }

    /**
     * @return array<int, string>
     */
    public function get_categories(): array
    {
        return (array) (static::metadata()['categories'] ?? array('zhd-cro-blocks'));
    }

    /**
     * @return array<int, string>
     */
    public function get_keywords(): array
    {
        return (array) (static::metadata()['keywords'] ?? array());
    }

    /**
     * @return array<int, string>
     */
    public function get_style_depends(): array
    {
        $styles = (array) (static::metadata()['style_handles'] ?? array());

        if (! in_array('zhd-eb-frontend-base', $styles, true)) {
            array_unshift($styles, 'zhd-eb-frontend-base');
        }

        return array_values(array_unique($styles));
    }

    /**
     * @return array<int, string>
     */
    public function get_script_depends(): array
    {
        return (array) (static::metadata()['script_handles'] ?? array());
    }

    /**
     * @return array<string, mixed>
     */
    public static function admin_manifest(): array
    {
        return static::metadata();
    }

    protected function start_widget_shell(string $modifier = ''): void
    {
        $classes = trim('zhd-eb-widget ' . $modifier);

        $this->add_render_attribute('wrapper', 'class', $classes);
        echo '<section ' . $this->get_render_attribute_string('wrapper') . '>';
    }

    protected function end_widget_shell(): void
    {
        echo '</section>';
    }

    protected function render_editor_placeholder(string $message): void
    {
        echo '<div class="zhd-eb-editor-placeholder">';
        echo '<strong>' . esc_html($this->get_title()) . '</strong>';
        echo '<p>' . esc_html($message) . '</p>';
        echo '</div>';
    }

    protected function add_text_style_controls(string $selector): void
    {
        $this->add_control(
            'text_color',
            array(
                'label'     => __('Text Color', ZHD_EB_TEXT_DOMAIN),
                'type'      => Controls_Manager::COLOR,
                'selectors' => array(
                    $selector => 'color: {{VALUE}};',
                ),
            )
        );
    }
}

