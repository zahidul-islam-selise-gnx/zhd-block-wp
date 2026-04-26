<?php
/**
 * Product context resolution helpers.
 *
 * @package ZHD\ElementorBlocks
 */

declare(strict_types=1);

namespace ZHD\ElementorBlocks\Widgets\Traits;

use Elementor\Controls_Manager;

trait SupportsProductContext
{
    protected function register_product_context_controls(): void
    {
        $this->add_control(
            'product_source',
            array(
                'label'   => __('Product Source', ZHD_EB_TEXT_DOMAIN),
                'type'    => Controls_Manager::SELECT,
                'default' => 'context',
                'options' => array(
                    'context' => __('Current product context', ZHD_EB_TEXT_DOMAIN),
                    'manual'  => __('Manual product override', ZHD_EB_TEXT_DOMAIN),
                ),
            )
        );

        $this->add_control(
            'manual_product_id',
            array(
                'label'       => __('Manual Product', ZHD_EB_TEXT_DOMAIN),
                'type'        => Controls_Manager::SELECT2,
                'options'     => $this->get_available_products(),
                'condition'   => array(
                    'product_source' => 'manual',
                ),
                'label_block' => true,
            )
        );
    }

    /**
     * @return array<int|string, string>
     */
    protected function get_available_products(): array
    {
        if (! function_exists('wc_get_products')) {
            return array();
        }

        $products = wc_get_products(
            array(
                'limit'  => 50,
                'status' => 'publish',
                'return' => 'objects',
            )
        );

        $options = array();

        foreach ($products as $product) {
            if (! is_object($product) || ! method_exists($product, 'get_id')) {
                continue;
            }

            $options[$product->get_id()] = $product->get_name();
        }

        return $options;
    }

    protected function resolve_product(array $settings): ?\WC_Product
    {
        if (! function_exists('wc_get_product')) {
            return null;
        }

        if ('manual' === ($settings['product_source'] ?? 'context') && ! empty($settings['manual_product_id'])) {
            $manual = wc_get_product((int) $settings['manual_product_id']);
            return $manual instanceof \WC_Product ? $manual : null;
        }

        global $product;

        if ($product instanceof \WC_Product) {
            return $product;
        }

        $current_id = get_the_ID();

        if ($current_id && 'product' === get_post_type($current_id)) {
            $resolved = wc_get_product($current_id);
            return $resolved instanceof \WC_Product ? $resolved : null;
        }

        $queried = get_queried_object_id();

        if ($queried && 'product' === get_post_type($queried)) {
            $resolved = wc_get_product($queried);
            return $resolved instanceof \WC_Product ? $resolved : null;
        }

        return null;
    }
}

