<?php
/**
 * Bundled template manifest.
 *
 * @package ZHD\ElementorBlocks
 */

return array(
    'home-hero' => array(
        'label'             => 'Home Hero',
        'description'       => 'Starter landing-page hero using the Hero CRO widget.',
        'preview_image'     => '',
        'compatible'        => '^1.0',
        'path'              => 'templates/home-hero.json',
        'required_widgets'  => array('zhd_hero_cro', 'zhd_trust_badges'),
    ),
    'product-single-biotech' => array(
        'label'             => 'Product Single Biotech',
        'description'       => 'Product detail starter layout using the Product Buy Box widget in a WooCommerce context.',
        'preview_image'     => '',
        'compatible'        => '^1.0',
        'path'              => 'templates/product-single-biotech.json',
        'required_widgets'  => array('zhd_product_buy_box', 'zhd_trust_badges'),
    ),
);

