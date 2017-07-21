<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('get_active_shipping_methods')) {
    function get_active_shipping_methods()
    {
        global $woocommerce;
        $active_methods   = array();
        $shipping_methods = $woocommerce->shipping->get_shipping_methods();
        foreach ($shipping_methods as $id => $shipping_method) {
            if (isset($shipping_method->enabled) && 'yes' === $shipping_method->enabled) {
                $active_methods[$id] = $shipping_method->method_title;
            }
        }

        return $active_methods;
    }
}
/**
 * Settings for PayApi Gateway.
 */
$this->form_fields = array(

    'enabled'                  => array(
        'title'   => __('Enable/Disable', 'payapi-gateway'),
        'type'    => 'checkbox',
        'label'   => __('Enable PayApi Gateway', 'payapi-gateway'),
        'default' => 'yes',
    ),
    'payapi_public_id'         => array(
        'title'       => __('PayApi Public Id', 'payapi-gateway'),
        'type'        => 'text',
        'description' => __('Get your API credentials from PayApi Backoffice.', 'payapi-gateway'),
        'default'     => '',
        'desc_tip'    => true,
    ),
    'payapi_api_key'           => array(
        'title'       => __('PayApi Api Key', 'payapi-gateway'),
        'type'        => 'password',
        'description' => __('Get your API credentials from PayApi Backoffice.', 'payapi-gateway'),
        'default'     => '',
        'desc_tip'    => true,
    ),
    'staging'                  => array(
        'title'   => __('Staging', 'payapi-gateway'),
        'type'    => 'checkbox',
        'label'   => __('Enable PayApi staging mode', 'payapi-gateway'),
        'default' => 'no',
    ),
    'instant_buy'              => array(
        'title'   => __('Instant Buy', 'payapi-gateway'),
        'type'    => 'checkbox',
        'label'   => __('Enable PayApi Instant Buy', 'payapi-gateway'),
        'default' => 'yes',
    ),
    'default_instant_shipping' => array(
        'title'   => __('Default shipping method for Instant Buy', 'payapi-gateway'),
        'type'    => 'select',
        'class'   => 'wc-enhanced-select',
        'options' => get_active_shipping_methods(),
    ),
    'payapi_selector_product_container_listings'         => array(
        'title'       => __('jQuery Selector for product container in listings', 'payapi-gateway'),
        'type'        => 'text',
        'default'     => ''
    ),
    'payapi_selector_main_page_container'         => array(
        'title'       => __('jQuery Selector for product container in listings', 'payapi-gateway'),
        'type'        => 'text',
        'default'     => ''
    )

);
