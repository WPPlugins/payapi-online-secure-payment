<?php

if (!defined('ABSPATH')) {
    exit;
}

//Add product page instant buy button

add_action('woocommerce_proceed_to_checkout', 'add_instant_buy_button');

function add_instant_buy_button()
{
    echo '<a href="#" style="margin-bottom:10px;width:100%" id="cart-instantbuy-button" class="cart-instantbuy-button checkout-button button">' . __("Instant Buy", "payapi-gateway") . '</a>';
}
