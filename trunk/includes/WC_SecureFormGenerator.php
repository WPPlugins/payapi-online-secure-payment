<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generates secure form object for PayApi.
 */
class WC_SecureFormGenerator
{

    /**
     * Stores line items to send to PayApi.
     * @var array
     */
    protected $line_items = array();

    /**
     * Pointer to gateway making the request.
     * @var WC_Gateway_Payapi
     */
    protected $gateway;

    /**
     * Constructor.
     * @param WC_Gateway_Paypal $gateway
     */
    public function __construct($gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Get the Secure Form request URL.
     * @return string
     */
    public function get_request_url($isPostEndpoint = true)
    {
        return $this->gateway->get_request_url($isPostEndpoint);
    }

    public function getInstantBuySecureForm($productId, $opts = ['qty' => 1], $ipaddress = false)
    {
        WC_Gateway_Payapi::log("InstantBuy ");
        $newCart = $this->generateNewCartWithProduct($productId, $opts);
        
        if ($newCart) {
            $extraData = $this->generateExtraData($newCart, $ipaddress);
            return $this->generateSecureFormData($newCart, $extraData);
        }

        return null;
    }

    public function postSecureForm(
        $shippingMethod = false,
        $checkoutAddress = false,
        $ipaddress = false
    ) {

        global $woocommerce;
        if(!$ipaddress){
            $ipaddress = WC_Geolocation::get_ip_address();
        }
        $wc_cart   = $woocommerce->cart;
        $extraData = $this->generateExtraData($wc_cart, $ipaddress, $shippingMethod, $checkoutAddress);

        return $this->generateSecureFormData($wc_cart, $extraData);
    }

    private function generateNewCartWithProduct($productId, $opts)
    {
        $mynewCart = new WC_Cart();
        if ($mynewCart->is_empty()) {
            $quantity = 1;
            if (isset($opts['qty'])) {
                $quantity = $opts['qty'];
            }
            $variation_id = 0;
            $variation    = [];
            if (isset($opts['variation_id'])) {
                $variation_id = $opts['variation_id'];
            }

            if (isset($opts['variation'])) {
                $variation = $opts['variation'];
            }

            $mynewCart->add_to_cart($productId, $quantity, $variation_id, $variation);
        }
        return $mynewCart;
    }

    private function generateExtraData($wc_cart, $ipaddress = false, $shippingMethod = false, $checkoutAddress = false)
    {
        if (!$shippingMethod) {
            $shippingMethod = $this->gateway->default_instant_shipping;
        }

        //User logged in
        $userAddress = false;
        if (is_user_logged_in()) {
            $userAddress = $this->getUserAddress();
        }

        //Shipping Address
        //1. use checkout addr (Always in checkout)
        //2. use user addr if logged in (Instant buy or post without address)
        //3. use ip country
        //4. use store country
        if ($checkoutAddress) {
            $finalAddr = $checkoutAddress;
        } elseif ($userAddress) {
            WC_Gateway_Payapi::log("USER ADDRESS " . json_encode($userAddress));
            $finalAddr = $userAddress;
        } else {
            WC_Gateway_Payapi::log("IP Address " . $ipaddress);
            $finalAddr = $this->getShippingFromIp($ipaddress);
            if (!isset($finalAddr) || $finalAddr == null) {
                WC_Gateway_Payapi::log("STORE ADDRESS ");
                $countryCode = WC_Countries::get_base_country();
                $region      = WC_Countries::get_base_state();
                WC_Gateway_Payapi::log("BASE ADDRESS " . $countryCode . "--" . $region);
                $finalAddr = [
                    'first_name' => 'xxxxx',
                    'last_name'  => 'xxxxx',
                    'company'    => '',
                    'address_1'  => 'xxxxx',
                    'address_2'  => '',
                    'city'       => 'xxxxx',
                    'state'      => $region,
                    'postcode'   => '*',
                    'country'    => $countryCode,
                ];

            }
        }


        WC_Gateway_Payapi::log("after final address: " . json_encode($finalAddr));

        $extraData             = [];
        if($ipaddress){
            $extraData['consumerIp'] = $ipaddress;
        }
        $extraData['address'] = $finalAddr;
        $extraData['shipping'] = $this->getShippingMethod($wc_cart, $shippingMethod, $finalAddr);
        $extraData['items']    = $this->getItemsFromCart($wc_cart);

        $coupons = $this->getCouponsFromCart($wc_cart);
        if (!empty($coupons)) {
            $extraData['coupons'] = $coupons;
        }

        return $extraData;
    }

    private function getShippingMethod($wc_cart, $methodId, $finalAddr)
    {

        WC()->shipping->reset_shipping();
        WC()->customer->set_shipping_location($finalAddr['country'], $finalAddr['state'], $finalAddr['postcode']);
        WC()->customer->set_billing_location($finalAddr['country'], $finalAddr['state'], $finalAddr['postcode']);

        WC_Gateway_Payapi::log("ShippingMethod " . $methodId);
        if (strpos($methodId, ':') !== false) {
            $methodId = strstr($methodId, ':', true);
        }

        WC()->session->set('chosen_shipping_methods', array($methodId));
        $wc_cart->calculate_shipping();

        $shipping_total     = floatval($wc_cart->shipping_total);
        $shipping_tax_total = WC_Tax::get_tax_total($wc_cart->shipping_taxes);
        
        return ['method_id' => $methodId, 'total' => $shipping_total, 'total_taxes' => $shipping_tax_total, 'taxes' => ($shipping_tax_total > 0) ? $wc_cart->shipping_taxes : []];
    }

    private function getItemsFromCart($wc_cart)
    {
        $wc_cart->calculate_totals();
        $items        = $wc_cart->get_cart();
        $productsJson = [];
        foreach ($items as $item => $values) {    
            $productsJson[] =  $values;
        }
        return $productsJson;
    }

    private function getCouponsFromCart($wc_cart)
    {         
        $my_coupons = [];
        if ( ! empty( $wc_cart->applied_coupons ) ) {
            $applied_coupons = $wc_cart->get_applied_coupons();
            foreach ($applied_coupons as $couponCode) {
                $my_coupons[] = ['code' => $couponCode,
                'amount' => $wc_cart->get_coupon_discount_amount($couponCode), 
                'tax' => $wc_cart->get_coupon_discount_tax_amount($couponCode) 
                ];
            }
        }
        return $my_coupons;
    }

    private function generateSecureFormData($cart, $extraData)
    {
        $orderKey               = $this->generateOrderKey();
        $extraData['order_key'] = $orderKey;
        $items                  = $extraData['items'];
        $products               = [];
        if ($items) {
            $hasExtradata = false;
            foreach ($items as $item) {
                $attachedIds = wc_get_product($item['product_id'])->get_gallery_attachment_ids();
                if (!empty($attachedIds)) {
                    $imageUrl = wp_get_attachment_url($attachedIds[0]);
                } else {
                    $imageUrl = wc_placeholder_img_src();
                }

                $excVat  = floatval($item['line_total']);
                $taxes   = floatval($item['line_tax']);
                $incVat  = $excVat + $taxes;
                $qty     = intval($item['quantity']);
                $percent = 0;
                if ($excVat > 0) {
                    $percent = $taxes * 100.0 / $excVat;
                }

                $products[] = [
                    "id"                 => $item['product_id'],
                    "quantity"           => $item['quantity'],
                    "title"              => $item['data']->post->post_title,
                    "priceInCentsIncVat" => round($incVat * 100 / $qty),
                    "priceInCentsExcVat" => round($excVat * 100 / $qty),
                    "vatInCents"         => round($taxes * 100 / $qty),
                    "vatPercentage"      => $percent,
                    "extraData"          => (!$hasExtradata) ? "data=" . $this->getJWTSignedData($extraData) : "",
                    "imageUrl"           => $imageUrl,
                ];
                $hasExtradata = true;
            }
        }

        $shippingObj = $extraData['shipping'];
        $shipExcVat  = $shippingObj['total'];
        $shipTaxes   = $shippingObj['total_taxes'];
        $shipIncVat  = $shipExcVat + $shipTaxes;
        $shipPercent = 0;
        if ($shipExcVat > 0) {
            $shipPercent = $shipTaxes * 100.0 / $shipExcVat;
        }
        $products[] = [
            "id"                 => $shippingObj['method_id'],
            "quantity"           => 1,
            "title"              => __('Handling and Delivery', 'payapi-gateway'),
            "priceInCentsIncVat" => round($shipIncVat * 100),
            "priceInCentsExcVat" => round($shipExcVat * 100),
            "vatInCents"         => round($shipTaxes * 100),
            "vatPercentage"      => $shipPercent,
            "extraData"          => "",
            "imageUrl"           => wc_placeholder_img_src(),
        ];

        $baseExclTax  = $cart->subtotal_ex_tax;
        $taxAmount    = $cart->get_taxes_total();
        $discountExcVat = $cart->discount_cart;
        $discountTax = $cart->discount_cart_tax;
        $totalOrdered = $baseExclTax + $taxAmount + $shipIncVat - $discountExcVat;
        $order        = ["sumInCentsIncVat" => round($totalOrdered * 100),
            "sumInCentsExcVat"                  => round(($baseExclTax + $shipExcVat - $discountExcVat) * 100),
            "vatInCents"                        => round(($taxAmount + $shipTaxes) * 100),
            "currency"                          => get_woocommerce_currency(),
            "referenceId"                       => $orderKey,
            "tosUrl"                            => get_site_url()];

        //Consumer
        $locale = function_exists('get_user_locale') ? get_user_locale() : get_locale();
        $locale = apply_filters('plugin_locale', $locale, 'woocommerce');
        $locale = str_replace("_", "-", $locale);

        if (isset($extraData['address']['email'])) {
            $email    = $extraData['address']['email'];
            $consumer = ["locale" => $locale, "email" => $email];
        } else {
            $consumer = ["locale" => $locale, "email" => ""];
        }

        //Return URLs

        $returnUrls = [
            "success" => get_site_url() . "/payapi-redirect?orderkey=" . $orderKey,
            "cancel"  => get_site_url() . "/payapi-redirect?cancel=1",
            "failed"  => get_site_url() . "/payapi-redirect?orderkey=" . $orderKey,
        ];

        $callbackUrl   = get_site_url() . "/wp-json/payapi-gateway/v1/callback";
        $jsonCallbacks = [
            "processing" => $callbackUrl,
            "success"    => $callbackUrl,
            "failed"     => $callbackUrl,
            "chargeback" => $callbackUrl,
        ];

        $res = ["order" => $order,
            "products"      => $products,
            "consumer"      => $consumer,
            "returnUrls"    => $returnUrls,
            "callbacks"     => $jsonCallbacks];

        if (isset($payapiShipping) && !empty($payapiShipping)) {
            $res["shippingAddress"] = $payapiShipping;
        }

        WC_Gateway_Payapi::log("secureform Data " . json_encode($res));

        return $res;
    }

    public function getShippingFromIp($ip)
    {
        if (!$ip || $ip == "") {
            return null;
        } else {
            $visitorIp = $ip;
        }

        $url      = "https://input.payapi.io/v1/api/fraud/ipdata/" . $visitorIp;
        $response = wp_remote_get($url);

        $countryCode = null;
        $regionCode  = '';

        if ($response && isset($response['body'])) {
            $response = json_decode($response['body'], true);
        }

        if ($response && isset($response['countryCode'])) {
            $countryCode = $response['countryCode'];
            if (isset($response['regionCode'])) {
                $regionCode = $response['regionCode'];
            } else {
                $regionCode = "";
            }

            if (isset($response['postalCode'])) {
                $postalCode = $response['postalCode'];
            } else {
                $postalCode = "";
            }
        }

        if ($countryCode != null) {
            return [
                'first_name' => 'xxxxx',
                'last_name'  => 'xxxxx',
                'company'    => '',
                'address_1'  => 'xxxxx',
                'address_2'  => '',
                'city'       => 'xxxxx',
                'state'      => $regionCode,
                'postcode'   => $postalCode,
                'country'    => $countryCode,
            ];
        } else {
            WC_Gateway_Payapi::log("returns null getShippingFromIp");
            return null;
        }

    }

    private function getUserAddress()
    {
        $user = wp_get_current_user();

        return $user->getData()['shipping'];
    }

    public function getJWTSignedData($object)
    {
        include_once 'JWT.php';
        try {
            $strSigned = JWT::encode($object, $this->gateway->get_payapi_api_key());
            return $strSigned;
        } catch (Exception $err) {
            return "";
        }
    }

    private function generateOrderKey()
    {
        do {
            $orderKey = "wc-" . rand(1, getrandmax());
            $orderId  = wc_get_order_id_by_order_key($orderKey);
        } while ($orderId > 0);

        return $orderKey;
    }

}
