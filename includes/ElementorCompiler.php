<?php
/**
 * Compile saved AI presets into native Elementor templates.
 *
 * @package ZHD\ElementorBlocks
 */

declare(strict_types=1);

namespace ZHD\ElementorBlocks;

use WP_Error;

final class ElementorCompiler
{
    public function __construct(
        private readonly Dependencies $dependencies,
        private readonly Templates $templates,
        private readonly AI $ai
    ) {
    }

    public function register_hooks(): void
    {
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function compile_preset_to_template(string $preset_slug)
    {
        if (! $this->dependencies->is_elementor_ready()) {
            return new WP_Error('zhd_elementor_missing', __('Elementor must be active before AI drafts can be saved to the library.', ZHD_EB_TEXT_DOMAIN));
        }

        $preset = $this->ai->get_presets()[$preset_slug] ?? null;

        if (! is_array($preset)) {
            return new WP_Error('zhd_preset_missing', __('The requested AI draft could not be found.', ZHD_EB_TEXT_DOMAIN));
        }

        $payload = $this->build_template_payload($preset);
        $file_name = sanitize_file_name(($preset['slug'] ?? 'zhd-ai-template') . '.json');
        $import_result = $this->templates->import_template_payload($payload, $file_name);

        if (is_wp_error($import_result)) {
            return $import_result;
        }

        return array(
            'preset'        => $preset,
            'payload'       => $payload,
            'import_result' => $import_result,
        );
    }

    /**
     * @param array<string, mixed> $preset
     * @return array<string, mixed>
     */
    private function build_template_payload(array $preset): array
    {
        $content = array();

        if (is_array($preset['sections'] ?? null)) {
            foreach ((array) $preset['sections'] as $section) {
                if (! is_array($section)) {
                    continue;
                }

                $compiled = $this->compile_structured_section($section);

                if (null !== $compiled) {
                    $content[] = $compiled;
                }
            }
        }

        if (array() === $content) {
            $content[] = $this->build_fallback_section(
                (string) ($preset['title'] ?? __('Generated template', ZHD_EB_TEXT_DOMAIN)),
                (string) ($preset['description'] ?? '')
            );
        }

        return array(
            'version'       => '0.4',
            'title'         => sanitize_text_field((string) ($preset['title'] ?? __('Generated template', ZHD_EB_TEXT_DOMAIN))),
            'type'          => 'section',
            'content'       => $content,
            'page_settings' => array(),
        );
    }

    /**
     * @param array<string, mixed> $section
     * @return array<string, mixed>|null
     */
    private function compile_structured_section(array $section): ?array
    {
        $columns = array_values(array_filter((array) ($section['columns'] ?? array()), 'is_array'));

        if (array() === $columns) {
            return null;
        }

        $widths = $this->get_layout_widths(
            sanitize_key((string) ($section['layout'] ?? 'one')),
            count($columns)
        );

        $compiled_columns = array();

        foreach ($columns as $index => $column) {
            $elements = $this->compile_structured_elements((array) ($column['elements'] ?? array()));

            if (array() === $elements) {
                continue;
            }

            $compiled_columns[] = $this->build_column(
                $elements,
                $widths[$index] ?? end($widths) ?: 100
            );
        }

        if (array() === $compiled_columns) {
            return null;
        }

        return $this->build_section($compiled_columns, count($compiled_columns));
    }

    /**
     * @param array<int, mixed> $elements
     * @return array<int, array<string, mixed>>
     */
    private function compile_structured_elements(array $elements): array
    {
        $compiled = array();

        foreach ($elements as $element) {
            if (! is_array($element)) {
                continue;
            }

            $widget = $this->compile_structured_element($element);

            if (null !== $widget) {
                $compiled[] = $widget;
            }
        }

        return $compiled;
    }

    /**
     * @param array<string, mixed> $element
     * @return array<string, mixed>|null
     */
    private function compile_structured_element(array $element): ?array
    {
        $type = sanitize_key((string) ($element['type'] ?? ''));

        return match ($type) {
            'heading' => $this->build_widget(
                'heading',
                array(
                    'title'       => sanitize_textarea_field((string) ($element['text'] ?? '')),
                    'header_size' => sanitize_key((string) ($element['level'] ?? 'h2')),
                    'size'        => 'default',
                )
            ),
            'text' => $this->build_widget(
                'text-editor',
                array(
                    'editor' => wpautop(esc_html(sanitize_textarea_field((string) ($element['text'] ?? '')))),
                )
            ),
            'button' => $this->build_widget(
                'button',
                array(
                    'text'        => sanitize_text_field((string) ($element['text'] ?? '')),
                    'align'       => sanitize_key((string) ($element['align'] ?? 'left')),
                    'button_type' => $this->map_button_style((string) ($element['style'] ?? 'primary')),
                    'size'        => 'md',
                    'link'        => array(
                        'url' => esc_url_raw((string) ($element['url'] ?? '')),
                    ),
                )
            ),
            'image' => $this->build_widget(
                'image',
                array(
                    'image' => array(
                        'url' => esc_url_raw((string) ($element['image_url'] ?? '')),
                    ),
                    'image_size' => 'large',
                )
            ),
            'icon_list' => $this->build_widget(
                'icon-list',
                array(
                    'icon_list' => $this->build_icon_list_items((array) ($element['items'] ?? array())),
                    'view'      => 'traditional',
                )
            ),
            'spacer' => $this->build_widget(
                'spacer',
                array(
                    'space' => $this->map_spacer_size((string) ($element['size'] ?? 'medium')),
                )
            ),
            'divider' => $this->build_widget('divider', array()),
            'faq' => $this->build_widget(
                'accordion',
                array(
                    'accordion' => $this->build_accordion_items((array) ($element['items'] ?? array())),
                )
            ),
            default => null,
        };
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, array<string, mixed>>
     */
    private function build_icon_list_items(array $items): array
    {
        $compiled = array();

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $text = sanitize_text_field((string) ($item['text'] ?? ''));

            if ('' === $text) {
                continue;
            }

            $compiled[] = array(
                '_id'           => $this->generate_id(),
                'text'          => $text,
                'selected_icon' => array(
                    'value'   => sanitize_text_field((string) ($item['icon'] ?? 'fas fa-check-circle')),
                    'library' => 'fa-solid',
                ),
                'link'          => array(
                    'url' => '',
                ),
            );
        }

        return $compiled;
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, array<string, mixed>>
     */
    private function build_accordion_items(array $items): array
    {
        $compiled = array();

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $question = sanitize_text_field((string) ($item['question'] ?? ''));
            $answer = sanitize_textarea_field((string) ($item['answer'] ?? ''));

            if ('' === $question || '' === $answer) {
                continue;
            }

            $compiled[] = array(
                '_id'         => $this->generate_id(),
                'tab_title'   => $question,
                'tab_content' => wpautop(esc_html($answer)),
            );
        }

        return $compiled;
    }

    /**
     * @return array<string, mixed>
     */
    private function build_fallback_section(string $title, string $description): array
    {
        return $this->build_section(
            array(
                $this->build_column(
                    array(
                        $this->build_widget(
                            'heading',
                            array(
                                'title'       => sanitize_text_field($title),
                                'header_size' => 'h2',
                                'size'        => 'default',
                            )
                        ),
                        $this->build_widget(
                            'text-editor',
                            array(
                                'editor' => wpautop(esc_html($description)),
                            )
                        ),
                    ),
                    100
                ),
            ),
            1
        );
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     * @return array<string, mixed>
     */
    private function build_section(array $columns, int $column_count): array
    {
        return array(
            'id'       => $this->generate_id(),
            'elType'   => 'section',
            'settings' => array(
                'structure' => $this->get_structure_key($column_count),
            ),
            'elements' => $columns,
            'isInner'  => false,
        );
    }

    /**
     * @param array<int, array<string, mixed>|null> $widgets
     * @return array<string, mixed>
     */
    private function build_column(array $widgets, float $size): array
    {
        return array(
            'id'       => $this->generate_id(),
            'elType'   => 'column',
            'settings' => array(
                '_column_size' => round($size, 2),
            ),
            'elements' => array_values(array_filter($widgets, 'is_array')),
            'isInner'  => false,
        );
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>|null
     */
    private function build_widget(string $widget_type, array $settings): ?array
    {
        if (
            in_array($widget_type, array('heading', 'text-editor', 'button'), true)
            && '' === trim(wp_strip_all_tags((string) ($settings['title'] ?? $settings['text'] ?? $settings['editor'] ?? '')))
        ) {
            return null;
        }

        if ('image' === $widget_type && '' === (string) ($settings['image']['url'] ?? '')) {
            return null;
        }

        if ('icon-list' === $widget_type && array() === (array) ($settings['icon_list'] ?? array())) {
            return null;
        }

        if ('accordion' === $widget_type && array() === (array) ($settings['accordion'] ?? array())) {
            return null;
        }

        return array(
            'id'         => $this->generate_id(),
            'elType'     => 'widget',
            'widgetType' => $widget_type,
            'settings'   => $settings,
            'elements'   => array(),
        );
    }

    /**
     * @return array<int, float>
     */
    private function get_layout_widths(string $layout, int $column_count): array
    {
        return match ($layout) {
            'two_equal' => array(50, 50),
            'two_left' => array(60, 40),
            'two_right' => array(40, 60),
            'three_equal' => array(33.33, 33.33, 33.34),
            default => 1 === $column_count ? array(100) : array_fill(0, $column_count, round(100 / $column_count, 2)),
        };
    }

    private function map_button_style(string $style): string
    {
        return match (sanitize_key($style)) {
            'secondary' => 'info',
            'link' => 'link',
            default => 'default',
        };
    }

    private function map_spacer_size(string $size): int
    {
        return match (sanitize_key($size)) {
            'small' => 24,
            'large' => 80,
            default => 48,
        };
    }

    private function get_structure_key(int $column_count): string
    {
        return match ($column_count) {
            2 => '50',
            3 => '33',
            4 => '25',
            default => '10',
        };
    }

    private function generate_id(): string
    {
        return substr(str_replace('-', '', wp_generate_uuid4()), 0, 8);
    }
}
