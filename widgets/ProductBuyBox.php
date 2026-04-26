<?php
/**
 * Product Buy Box widget.
 *
 * @package ZHD\ElementorBlocks
 */

declare(strict_types=1);

namespace ZHD\ElementorBlocks\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use ZHD\ElementorBlocks\Widgets\Traits\SupportsProductContext;

final class ProductBuyBox extends BaseWidget
{
    use SupportsProductContext;

    /**
     * @return array<string, mixed>
     */
    protected static function metadata(): array
    {
        return array(
            'slug'          => 'zhd_product_buy_box',
            'title'         => 'Product Buy Box',
            'description'   => 'WooCommerce-aware purchase module that resolves the current product context and keeps conversion details compact.',
            'icon'          => 'eicon-product-add-to-cart',
            'keywords'      => array('buy', 'product', 'cart', 'woocommerce'),
            'categories'    => array('zhd-cro-blocks'),
            'style_handles' => array('zhd-eb-widget-product-buy-box'),
            'script_handles'=> array(),
            'requirements'  => array(
                'elementor'   => true,
                'woocommerce' => true,
            ),
        );
    }

    protected function register_content_controls(): void
    {
        $this->start_controls_section(
            'section_product',
            array(
                'label' => __('Product context', ZHD_EB_TEXT_DOMAIN),
            )
        );

        $this->register_product_context_controls();

        $this->add_control(
            'override_title',
            array(
                'label'       => __('Override title', ZHD_EB_TEXT_DOMAIN),
                'type'        => Controls_Manager::TEXT,
                'description' => __('Leave blank to use the current product title.', ZHD_EB_TEXT_DOMAIN),
                'label_block' => true,
            )
        );

        $this->add_control(
            'guarantee_text',
            array(
                'label'       => __('Guarantee text', ZHD_EB_TEXT_DOMAIN),
                'type'        => Controls_Manager::TEXT,
                'default'     => __('30-day satisfaction guarantee', ZHD_EB_TEXT_DOMAIN),
                'label_block' => true,
            )
        );

        $this->add_control(
            'stock_label',
            array(
                'label'       => __('Stock prefix', ZHD_EB_TEXT_DOMAIN),
                'type'        => Controls_Manager::TEXT,
                'default'     => __('Availability', ZHD_EB_TEXT_DOMAIN),
                'label_block' => true,
            )
        );

        $this->add_control(
            'show_description',
            array(
                'label'        => __('Show short description', ZHD_EB_TEXT_DOMAIN),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->add_control(
            'trust_badges',
            array(
                'label'       => __('Trust badges', ZHD_EB_TEXT_DOMAIN),
                'type'        => Controls_Manager::TEXTAREA,
                'default'     => __("Third-party tested\nFast shipping\nDoctor-formulated", ZHD_EB_TEXT_DOMAIN),
                'description' => __('One badge per line.', ZHD_EB_TEXT_DOMAIN),
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
            'card_background',
            array(
                'label'     => __('Card background', ZHD_EB_TEXT_DOMAIN),
                'type'      => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .zhd-eb-buy-box__card' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'price_color',
            array(
                'label'     => __('Price color', ZHD_EB_TEXT_DOMAIN),
                'type'      => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .zhd-eb-buy-box__price' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name'     => 'title_typography',
                'selector' => '{{WRAPPER}} .zhd-eb-buy-box__title',
            )
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $product = $this->resolve_product($settings);

        if (! $product instanceof \WC_Product) {
            $this->start_widget_shell('zhd-eb-buy-box');
            $this->render_editor_placeholder(__('No WooCommerce product was resolved. Use this widget inside a product template or select a manual product override.', ZHD_EB_TEXT_DOMAIN));
            $this->end_widget_shell();
            return;
        }

        $title = ! empty($settings['override_title']) ? (string) $settings['override_title'] : $product->get_name();
        $description = wp_strip_all_tags((string) $product->get_short_description());
        $stock_text = $product->is_in_stock() ? __('In stock', ZHD_EB_TEXT_DOMAIN) : __('Out of stock', ZHD_EB_TEXT_DOMAIN);
        $badges = preg_split('/\r\n|\r|\n/', (string) ($settings['trust_badges'] ?? '')) ?: array();
        $image_id = $product->get_image_id();

        $this->start_widget_shell('zhd-eb-buy-box');
        echo '<div class="zhd-eb-buy-box__card">';

        if ($image_id) {
            echo '<div class="zhd-eb-buy-box__media">' . wp_kses_post(wp_get_attachment_image($image_id, 'large')) . '</div>';
        }

        echo '<div class="zhd-eb-buy-box__content">';
        echo '<h2 class="zhd-eb-buy-box__title">' . esc_html($title) . '</h2>';
        echo '<div class="zhd-eb-buy-box__price">' . wp_kses_post($product->get_price_html()) . '</div>';

        if ('yes' === ($settings['show_description'] ?? 'yes') && '' !== $description) {
            echo '<p class="zhd-eb-buy-box__description">' . esc_html($description) . '</p>';
        }

        echo '<p class="zhd-eb-buy-box__stock"><strong>' . esc_html((string) $settings['stock_label']) . ':</strong> ' . esc_html($stock_text) . '</p>';

        printf(
            '<a class="zhd-eb-button zhd-eb-buy-box__button" href="%1$s">%2$s</a>',
            esc_url($product->add_to_cart_url()),
            esc_html($product->add_to_cart_text())
        );

        if (! empty($settings['guarantee_text'])) {
            echo '<p class="zhd-eb-buy-box__guarantee">' . esc_html((string) $settings['guarantee_text']) . '</p>';
        }

        if (! empty($badges)) {
            echo '<ul class="zhd-eb-buy-box__badges">';
            foreach ($badges as $badge) {
                if ('' === trim($badge)) {
                    continue;
                }

                echo '<li>' . esc_html($badge) . '</li>';
            }
            echo '</ul>';
        }

        echo '</div>';
        echo '</div>';
        $this->end_widget_shell();
    }
}

