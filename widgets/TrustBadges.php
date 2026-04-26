<?php
/**
 * Trust badges widget.
 *
 * @package ZHD\ElementorBlocks
 */

declare(strict_types=1);

namespace ZHD\ElementorBlocks\Widgets;

use Elementor\Controls_Manager;
use Elementor\Icons_Manager;
use Elementor\Repeater;

final class TrustBadges extends BaseWidget
{
    /**
     * @return array<string, mixed>
     */
    protected static function metadata(): array
    {
        return array(
            'slug'          => 'zhd_trust_badges',
            'title'         => 'Trust Badges',
            'description'   => 'Compact credibility grid for guarantees, certifications, and reassurance messaging.',
            'icon'          => 'eicon-check-circle',
            'keywords'      => array('trust', 'badges', 'guarantee', 'icons'),
            'categories'    => array('zhd-cro-blocks'),
            'style_handles' => array('zhd-eb-widget-trust-badges'),
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
                'label' => __('Badges', ZHD_EB_TEXT_DOMAIN),
            )
        );

        $repeater = new Repeater();

        $repeater->add_control(
            'icon',
            array(
                'label'   => __('Icon', ZHD_EB_TEXT_DOMAIN),
                'type'    => Controls_Manager::ICONS,
                'default' => array(
                    'value'   => 'fas fa-check-circle',
                    'library' => 'fa-solid',
                ),
            )
        );

        $repeater->add_control(
            'label',
            array(
                'label'       => __('Label', ZHD_EB_TEXT_DOMAIN),
                'type'        => Controls_Manager::TEXT,
                'default'     => __('Verified quality', ZHD_EB_TEXT_DOMAIN),
                'label_block' => true,
            )
        );

        $this->add_control(
            'items',
            array(
                'label'       => __('Badge items', ZHD_EB_TEXT_DOMAIN),
                'type'        => Controls_Manager::REPEATER,
                'fields'      => $repeater->get_controls(),
                'title_field' => '{{{ label }}}',
                'default'     => array(
                    array(
                        'label' => __('Third-party tested', ZHD_EB_TEXT_DOMAIN),
                    ),
                    array(
                        'label' => __('Secure checkout', ZHD_EB_TEXT_DOMAIN),
                    ),
                    array(
                        'label' => __('Fast dispatch', ZHD_EB_TEXT_DOMAIN),
                    ),
                ),
            )
        );

        $this->add_responsive_control(
            'columns',
            array(
                'label'     => __('Columns', ZHD_EB_TEXT_DOMAIN),
                'type'      => Controls_Manager::SELECT,
                'default'   => '3',
                'options'   => array(
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                ),
                'selectors' => array(
                    '{{WRAPPER}} .zhd-eb-badges__grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
                ),
            )
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $items = $settings['items'] ?? array();

        $this->start_widget_shell('zhd-eb-badges');
        echo '<div class="zhd-eb-badges__grid">';

        foreach ($items as $item) {
            echo '<div class="zhd-eb-badges__item">';

            if (! empty($item['icon']['value'])) {
                echo '<span class="zhd-eb-badges__icon">';
                Icons_Manager::render_icon($item['icon'], array('aria-hidden' => 'true'));
                echo '</span>';
            }

            echo '<span class="zhd-eb-badges__label">' . esc_html((string) ($item['label'] ?? '')) . '</span>';
            echo '</div>';
        }

        echo '</div>';
        $this->end_widget_shell();
    }
}
