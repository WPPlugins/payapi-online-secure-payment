<?php

if (!defined('ABSPATH')) {
    exit;
}

//Add product page instant buy button
wp_enqueue_script('payapi-productpage-js', plugin_dir_url(__FILE__) . '../assets/js/payapi_productpage.js');

add_action('woocommerce_before_main_content', 'generate_meta_instant_buy');
add_action('woocommerce_after_add_to_cart_button', 'add_instant_buy_button');

function add_instant_buy_button()
{
    include_once 'WC_Gateway_Payapi.php';
    $payapigateway = new WC_Gateway_Payapi();
    $baseUrl       = $payapigateway->get_request_url(false);
    echo '<script type="text/javascript">var baseUrl = "' . $baseUrl . '"; </script>';    
    echo '<button type="button" class="alt button" id="payapi-button">' . __("Instant Buy", 'payapi-gateway') . '</button>';
}

function generateOptionsFromQuery($productId)
{
    $qty = 1;
    if (isset($_GET['quantity'])) {
        $qty = intval($_GET['quantity']);
    }

    $_pf = new WC_Product_Factory();
    $prd = $_pf->get_product($productId);

    $variation_id = 0;
    $variation    = [];
    $params       = $_GET;
    if ($prd->is_type('variable')) {
        $available_variations = $prd->get_available_variations();
        foreach ($available_variations as $value) {
            if (array_intersect_assoc($value['attributes'], $params) == $value['attributes']) {
                WC_Gateway_Payapi::log("FOUND VARIATION!");
                return ['qty' => $qty, 'variation_id' => $value['id'], 'variation' => $value['attributes']];
            }
        }
    }
    return ['qty' => $qty];
}

function generate_meta_instant_buy()
{
    if (isset($_GET['consumerIp'])) {
        global $post;
        include_once 'WC_Gateway_Payapi.php';
        include_once 'WC_SecureFormGenerator.php';
        $productId        = $post->ID;
        $opts             = generateOptionsFromQuery($productId);
        $payapigateway    = new WC_Gateway_Payapi();
        $secureFormHelper = new WC_SecureFormGenerator($payapigateway);
        $secureFormData   = $secureFormHelper->getInstantBuySecureForm($productId, $opts, $_GET['consumerIp']);
        
        WC_Gateway_Payapi::log("currencyy!!!!!!!!!!!!!!!!".json_encode($secureFormData['order']));

        echo '<meta name="io.payapi.webshop" content="'.$payapigateway->get_payapi_public_id().'">';        
        echo '<meta name="order.currency" content="'.$secureFormData['order']['currency'].'">';
        echo '<meta name="order.shippingHandlingFeeInCentsExcVat" content="'.$secureFormData['products'][1]['priceInCentsExcVat'].'">';
        echo '<meta name="order.shippingHandlingFeeInCentsIncVat" content="'.$secureFormData['products'][1]['priceInCentsIncVat'].'">';
        echo '<meta name="order.tosUrl" content="'.$secureFormData['order']['tosUrl'].'">';
        echo '<meta name="product.id" content="'.$secureFormData['products'][0]['id'].'">';
        echo '<meta name="product.quantity" content="'.$secureFormData['products'][0]['quantity'].'">';
        echo '<meta name="product.title" content="'.$secureFormData['products'][0]['title'].'">';
        echo '<meta name="product.imageUrl" content="'.$secureFormData['products'][0]['imageUrl'].'">';
        echo '<meta name="product.priceInCentsIncVat" content="'.$secureFormData['products'][0]['priceInCentsIncVat'].'">';
        echo '<meta name="product.priceInCentsExcVat" content="'.$secureFormData['products'][0]['priceInCentsExcVat'].'">';
        echo '<meta name="product.vatInCents" content="'.$secureFormData['products'][0]['vatInCents'].'">';
        echo '<meta name="product.vatPercentage" content="'.$secureFormData['products'][0]['vatPercentage'].'">';
        echo '<meta name="product.hasMandatoryFields" content="' . 0 .'">';
        echo '<meta name="consumer.email" content="'.$secureFormData['consumer']['email'].'">';
        echo '<meta name="consumer.locale" content="'.$secureFormData['consumer']['locale'].'">';
        echo '<meta name="callbacks.processing" content="'.$secureFormData['callbacks']['processing'].'">';
        echo '<meta name="callbacks.success" content="'.$secureFormData['callbacks']['success'].'">';
        echo '<meta name="callbacks.failed" content="'.$secureFormData['callbacks']['failed'].'">';
        echo '<meta name="callbacks.chargeback" content="'.$secureFormData['callbacks']['chargeback'].'">';
        echo '<meta name="returnUrls.success" content="'.$secureFormData['returnUrls']['success'].'">';
        echo '<meta name="returnUrls.cancel" content="'.$secureFormData['returnUrls']['cancel'].'">';
        echo '<meta name="returnUrls.failed" content="'.$secureFormData['returnUrls']['failed'].'">';
        echo '<meta name="product.extraData" content="'.$secureFormData['products'][0]['extraData'].'">';
     
    }
}
