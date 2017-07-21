<?php
/**
Plugin Name: PayApi Online Secure Payment
Plugin URI: https://input.payapi.io
Description: PayApi Online Secure Payment includes a new payment gateway for the checkout process through the PayApi Secure-Form
Version: 1.0.1
Author: PayApi
Author URI: https://payapi.io
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Gateway_Payapi init
 */

add_action('plugins_loaded', 'woocommerce_payapi_init', 0);

function woocommerce_payapi_init()
{

    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    load_plugin_textdomain('payapi-gateway', false, dirname(plugin_basename(__FILE__)) . '/lang/');

    include_once 'includes/WC_Gateway_Payapi.php';

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_payapi_gateway($methods)
    {
        $methods[] = 'WC_Gateway_Payapi';
        return $methods;
    }

    function generate_fngr_config()
    {
        $payapigateway = new WC_Gateway_Payapi();        
        echo '<script type="text/javascript"> ' .
        'var payapi_selector_product_container_listings ="'.$payapigateway->getProductContainerListingsSelector().'"; '
        .'var payapi_selector_main_container ="'.$payapigateway->getMainContainerSelector().'"; '
        .'var instantbuy_string = "'.__("Instant Buy", 'payapi-gateway') .'"; '
        .'if (typeof _payapi == "undefined") { '
        .' var _payapi = {}; '
        .'}; '
        .'_payapi.addtocart = function _addtocart(url){ '
        .'    $dataContainer = $("[fngrsharesdk-product-container=\""+url+"\"]").find("[data-product-id]"); '
        .'    if($dataContainer.length > 0){ '
        .'        var productId = $dataContainer.attr("data-product-id"); '
        .'        var addCartUrl = window.location.href + ((window.location.href.indexOf("?")<0)?"?":"&") +"add-to-cart="+productId; '
        .'        window.location = addCartUrl; '
        .'    }else if ($("button[name=\"add-to-cart\"]").length > 0){ '
        .'        $("button[name=\"add-to-cart\"]").first().click(); '
        .'    }else{ '
            .'    $dataContainer = $("[fngrsharesdk-product-container=\""+url+"\"]").find(".ajax_add_to_cart"); '
            .'    if($dataContainer.length > 0){ '
            .'        $dataContainer.click(); '
            .'    }else{ '       
            .'        alert("Product was not added to the cart"); '
            .    '}'
        .'    } '
        .'  }; '.
        'var _fngrshareConfig = {' .
        'isStaging: function _isStaging(){' .
        'return ' . $payapigateway->get_is_staging() . ';' .
        '},' .
        'enablePurchases: function _enablePurchases(){' .
        'return ' . $payapigateway->get_is_instant_buy() . ';' .
        '},' .
        'shortenerClientId: function _shortenerClientId(){' .
        'return "' . $payapigateway->get_payapi_public_id() . '";' .
            '}' .
            '};' .
            '</script>';
    } 

    function generate_mini_cart_button(){
        //echo '<script type="text/javascript">console.log("INCLUDE MINI CART BUTTON");</script>'
        echo '<a rel="nofollow" class="cart-instantbuy-button checkout button">' .__("Instant Buy", 'payapi-gateway') . '</a>';
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_payapi_gateway');

    /**
     * Add scripts
     **/
    $payapigateway = new WC_Gateway_Payapi();
    if ($payapigateway->is_available()) {
        add_action('woocommerce_before_main_content', 'generate_fngr_config');
        add_action('woocommerce_before_cart', 'generate_fngr_config');
        add_action('woocommerce_after_mini_cart','generate_mini_cart_button');
        add_filter('wp_enqueue_scripts', 'incjs_payapi_checkout');
    }

    function incjs_payapi_checkout()
    {
        if (is_checkout()) {
            $urlJs = plugin_dir_url(__FILE__) . 'assets/js/payapi_checkout.js';
            WC_Gateway_Payapi::log("URL: " . $urlJs);
            wp_enqueue_script('payapi-checkout-js', $urlJs);
        }

        wp_enqueue_style('fngrsharesdk-css', plugin_dir_url(__FILE__) . 'assets/fngrsharesdk-min/sdk.min.css');
        wp_enqueue_script('fngrsharesdk-touch-js', plugin_dir_url(__FILE__) . 'assets/fngrsharesdk-min/touch.min.js');
        wp_enqueue_script('fngrsharesdk-ui-js', plugin_dir_url(__FILE__) . 'assets/fngrsharesdk-min/sdk-ui.min.js');
        wp_enqueue_script('fngrsharesdk-controller-js', plugin_dir_url(__FILE__) . 'assets/fngrsharesdk-min/sdk-controller.min.js');
        
        wp_enqueue_script('payapi-generic-js', plugin_dir_url(__FILE__) . 'assets/js/payapi_generic.js');

        if (is_product()) {
            WC_Gateway_Payapi::log("Is product page!!!!!!!");
            include_once 'includes/product-page.php';
        }

        if (is_cart()) {
            WC_Gateway_Payapi::log("Is cart page!!!!!!!");
            include_once 'includes/cart-page.php';
        }

        wp_enqueue_script('payapi-cartpage-js', plugin_dir_url(__FILE__) . 'assets/js/payapi_cart.js');
    }

/**
 * This function is where we register our routes for our example endpoint.
 */
    function register_payapi_routes()
    {
        register_rest_route('payapi-gateway/v1', '/secureformgenerator', array(
            'methods'  => 'POST',
            'callback' => 'register_secureformgenerator_cb',
        ));

        register_rest_route('payapi-gateway/v1', '/callback', array(
            'methods'  => 'POST',
            'callback' => 'register_callback_cb',
        ));
    }

    function register_secureformgenerator_cb($request)
    {
        WC_Gateway_Payapi::log("Runnning secureform ");
        include_once 'includes/WC_SecureFormGenerator.php';
        include_once 'includes/WC_Payapi_Callback.php';
        include_once 'includes/JWT.php';


        $shipping_method = false;
        $address = false;
        $payapigateway    = new WC_Gateway_Payapi();
        $secureFormHelper = new WC_SecureFormGenerator($payapigateway);
        if (isset($request['payapidata'])) {
            //From checkout
            WC_Gateway_Payapi::log("secureform data" . json_encode($request['payapidata']));
            $shipping_method = $request['payapidata']['shipping_method[0'];
            if (!isset($request['payapidata']['ship_to_different_address'])) {
                $address = [
                    'first_name'     => $request['payapidata']['billing_first_name'],
                    'last_name'      => $request['payapidata']['billing_last_name'],
                    'company'        => $request['payapidata']['billing_company'],
                    'email'          => $request['payapidata']['billing_email'],
                    'phone'          => $request['payapidata']['billing_phone'],
                    'address_1'      => $request['payapidata']['billing_address_1'],
                    'address_2'      => $request['payapidata']['billing_address_2'],
                    'city'           => $request['payapidata']['billing_city'],
                    'state'          => $request['payapidata']['billing_state'],
                    'postcode'       => $request['payapidata']['billing_postcode'],
                    'country'        => $request['payapidata']['billing_country'],
                    'order_comments' => $request['payapidata']['order_comments'],
                ];
            } else {
                $address = [
                    'first_name'     => $request['payapidata']['shipping_first_name'],
                    'last_name'      => $request['payapidata']['shipping_last_name'],
                    'company'        => $request['payapidata']['shipping_company'],
                    'email'          => $request['payapidata']['billing_email'],
                    'phone'          => $request['payapidata']['billing_phone'],
                    'address_1'      => $request['payapidata']['shipping_address_1'],
                    'address_2'      => $request['payapidata']['shipping_address_2'],
                    'city'           => $request['payapidata']['shipping_city'],
                    'state'          => $request['payapidata']['shipping_state'],
                    'postcode'       => $request['payapidata']['shipping_postcode'],
                    'country'        => $request['payapidata']['shipping_country'],
                    'order_comments' => $request['payapidata']['order_comments'],
                ];
            }

        }

        $payapiApiKey = $payapigateway->get_option('payapi_api_key');
        $payapiObject = $secureFormHelper->postSecureForm($shipping_method, $address);
        try {
            $strSigned = JWT::encode($payapiObject, $payapiApiKey);
            return new WP_REST_Response(["url" => $secureFormHelper->get_request_url(), "data" => $strSigned], 200);
        } catch (Exception $err) {
            return new WP_REST_Response(["msg" => "Wrong data decoding. Please review PayApi data in your backoffice", "platform" => "woocommerce", "status" => "wrong_signature"], 400);
        }

    }

    function register_callback_cb($request)
    {
        WC_Gateway_Payapi::log("Running callback");
        include_once 'includes/JWT.php';
        include_once 'includes/WC_Payapi_Callback.php';
        $data = $request['data'];
        if (isset($data)) {
            WC_Gateway_Payapi::log("Data: " . $data);
            $payapigateway = new WC_Gateway_Payapi();
            $payapiApiKey  = $payapigateway->get_payapi_api_key();
            try {
                $decoded = JWT::decode($data, $payapiApiKey, ['HS256']);
                return WC_Payapi_Callback::process(json_decode($decoded));
            } catch (Exception $err) {
                return new WP_REST_Response(["msg" => "Wrong data decoding. Please review PayApi data in your backoffice", "platform" => "woocommerce", "status" => "wrong_signature"], 400);
            }
        }
        return new WP_REST_Response(["msg" => "Missing or wrong PayApi parameters", "platform" => "woocommerce", "status" => "bad_request"], 400);
    }

    function create_redirect_pages()
    {
        $pages        = get_pages();
        $contact_page = array('slug' => 'payapi-redirect', 'title' => 'Redirect page');
        $found        = false;
        foreach ($pages as $page) {
            $apage = $page->post_name;
            switch ($apage) {
                case 'payapi-redirect':$found = true;
                    break;
                default:$no_page;
            }
        }

        if (!$found) {
            $page_id = wp_insert_post(array(
                'post_title'   => $contact_page['title'],
                'post_type'    => 'page',
                'post_name'    => $contact_page['slug'],
                'post_status'  => 'publish',
                'post_excerpt' => 'User profile and author page details page ! ',
            ));
            add_post_meta($page_id, '_wp_page_template', plugin_dir_path(__FILE__) . 'pages/redirect.php');
        }
    }

    function wcpt_locate_template($template_name)
    {

        $default_path = plugin_dir_path(__FILE__) . 'templates/'; // Path to the template folder
        $template     = $default_path . $template_name;

        return apply_filters('wcpt_locate_template', $template, $template_name, '', $default_path);
    }

    function wcpt_template_loader($template)
    {
        global $post;
        if ($post->post_name == 'payapi-redirect') {
            WC_Gateway_Payapi::log("IS PAYAPI REDIRECT");
            $file = 'redirect.php';
            if (file_exists(wcpt_locate_template($file))) {
                $template = wcpt_locate_template($file);
                WC_Gateway_Payapi::log("IS PAYAPI inside" . $post->post_name);
                return $template;
            }
        }

        return $template;
    }

    add_filter('template_include', 'wcpt_template_loader');
    add_action('admin_init', 'create_redirect_pages');

    add_action('rest_api_init', 'register_payapi_routes');
}
