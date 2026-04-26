<?php
/**
 * Hero CRO widget.
 *
 * @package ZHD\ElementorBlocks
 */

declare(strict_types=1);

namespace ZHD\ElementorBlocks\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Utils;

final class HeroCRO extends BaseWidget
{
    /**
     * @return array<string, mixed>
     */
    protected static function metadata(): array
    {
        return array(
            'slug'          => 'zhd_hero_cro',
            'title'         => 'Hero CRO',
            'description'   => 'High-converting hero section with background media, focused copy, and a primary CTA.',
            'icon'          => 'eicon-banner',
            'keywords'      => array('hero', 'landing', 'conversion', 'cro'),
            'categories'    => array('zhd-cro-blocks'),
            'style_handles' => array('zhd-eb-widget-hero-cro'),
            'script_handles'=> array(),
            'requirements'  => array(
                'elementor'   => true,
                'woocommerce' => false,
            ),
        );
    }

    protected function register_content_controls(): void
    {
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __('Content', ZHD_EB_TEXT_DOMAIN),
            )
        );

        $this->add_control(
            'eyebrow',
            array(
                'label'       => __('Eyebrow', ZHD_EB_TEXT_DOMAIN),
                'type'        => Controls_Manager::TEXT,
                'default'     => __('Clinically grounded biotech conversion system', ZHD_EB_TEXT_DOMAIN),
                'label_block' => true,
            )
        );

        $this->add_control(
            'heading',
            array(
                'label'       => __('Heading', ZHD_EB_TEXT_DOMAIN),
                'type'        => Controls_Manager::TEXTAREA,
                'default'     => __('Build higher-converting Elementor pages without rebuilding the stack every time.', ZHD_EB_TEXT_DOMAIN),
                'label_block' => true,
            )
        );

        $this->add_control(
            'subheading',
            array(
                'label'       => __('Subheading', ZHD_EB_TEXT_DOMAIN),
                'type'        => Controls_Manager::TEXTAREA,
                'default'     => __('Launch reusable CRO blocks across multiple client sites with faster delivery, stronger consistency, and less design drift.', ZHD_EB_TEXT_DOMAIN),
                'label_block' => true,
            )
        );

        $this->add_control(
            'button_text',
            array(
                'label'   => __('Button text', ZHD_EB_TEXT_DOMAIN),
                'type'    => Controls_Manager::TEXT,
                'default' => __('Start with the core blocks', ZHD_EB_TEXT_DOMAIN),
            )
        );

        $this->add_control(
            'button_url',
            array(
                'label'       => __('Button URL', ZHD_EB_TEXT_DOMAIN),
                'type'        => Controls_Manager::URL,
                'placeholder' => 'https://',
                'default'     => array(
                    'url' => '#',
                ),
            )
        );

        $this->add_control(
            'background_image',
            array(
                'label'   => __('Background image', ZHD_EB_TEXT_DOMAIN),
                'type'    => Controls_Manager::MEDIA,
                'default' => array(
                    'url' => Utils::get_placeholder_image_src(),
                ),
            )
        );

        $this->add_control(
            'overlay_color',
            array(
                'label'   => __('Overlay color', ZHD_EB_TEXT_DOMAIN),
                'type'    => Controls_Manager::COLOR,
                'default' => 'rgba(15, 23, 42, 0.78)',
            )
        );

        $this->add_responsive_control(
            'content_max_width',
            array(
                'label'      => __('Content width', ZHD_EB_TEXT_DOMAIN),
                'type'       => Controls_Manager::SLIDER,
                'range'      => array(
                    'px' => array(
                        'min' => 320,
                        'max' => 900,
                    ),
                ),
                'default'    => array(
                    'size' => 680,
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .zhd-eb-hero__inner' => 'max-width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    protected function register_style_controls(): void
    {
        $this->start_controls_section(
            'section_style',
            array(
                'label' => __('Style', ZHD_EB_TEXT_DOMAIN),
                'tab'   => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'heading_color',
            array(
                'label'     => __('Heading color', ZHD_EB_TEXT_DOMAIN),
                'type'      => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .zhd-eb-hero__heading' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'subheading_color',
            array(
                'label'     => __('Subheading color', ZHD_EB_TEXT_DOMAIN),
                'type'      => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .zhd-eb-hero__subheading' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_background',
            array(
                'label'     => __('Button background', ZHD_EB_TEXT_DOMAIN),
                'type'      => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .zhd-eb-button' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name'     => 'heading_typography',
                'selector' => '{{WRAPPER}} .zhd-eb-hero__heading',
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name'     => 'body_typography',
                'selector' => '{{WRAPPER}} .zhd-eb-hero__subheading',
            )
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $background_url = ! empty($settings['background_image']['url']) ? esc_url((string) $settings['background_image']['url']) : '';
        $overlay_color  = sanitize_text_field((string) ($settings['overlay_color'] ?? 'rgba(15, 23, 42, 0.78)'));
        $button_url     = $settings['button_url']['url'] ?? '#';

        $style = sprintf(
            '--zhd-eb-hero-overlay:%1$s;%2$s',
            esc_attr($overlay_color),
            '' !== $background_url ? 'background-image:url(' . esc_url($background_url) . ');' : ''
        );

        $this->start_widget_shell('zhd-eb-hero');

        echo '<div class="zhd-eb-hero__backdrop" style="' . esc_attr($style) . '">';
        echo '<div class="zhd-eb-hero__inner">';

        if (! empty($settings['eyebrow'])) {
            echo '<p class="zhd-eb-hero__eyebrow">' . esc_html((string) $settings['eyebrow']) . '</p>';
        }

        echo '<h2 class="zhd-eb-hero__heading">' . esc_html((string) $settings['heading']) . '</h2>';
        echo '<p class="zhd-eb-hero__subheading">' . esc_html((string) $settings['subheading']) . '</p>';

        if (! empty($settings['button_text'])) {
            printf(
                '<a class="zhd-eb-button" href="%1$s">%2$s</a>',
                esc_url((string) $button_url),
                esc_html((string) $settings['button_text'])
            );
        }

        echo '</div>';
        echo '</div>';

        $this->end_widget_shell();
    }
}

