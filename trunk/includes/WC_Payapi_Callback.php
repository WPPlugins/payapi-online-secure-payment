<?php

class WC_Payapi_Callback
{
    public static function process($jsonData)
    {
        $helper = new WC_Payapi_Callback();
        if ($jsonData->payment->status == 'processing') {
            //Processing async payment
            $order_id = $helper->createOrder($jsonData);
        } else {
            $order_id = $helper->getOrderId($jsonData);
            if ($order_id > 0) {
                if ($jsonData->payment->status == 'successful') {
                    //Payment success
                    $order_id = $helper->addPayment($order_id);
                } elseif ($jsonData->payment->status == 'failed') {
                    //Payment failure
                    $order = new WC_Order($order_id);
                    $order->cancel_order();
                    $order->update_status('failed', 'Payment cancelled via PayApi FAILURE callback.');
                } elseif ($jsonData->payment->status == 'cancelled') {
                    //Payment failure
                    $order = new WC_Order($order_id);
                    $order->add_order_note('Payment cancelled via PayApi CANCELLED callback.');
                    $order->cancel_order();
                } elseif ($jsonData->payment->status == 'chargeback') {
                    /*$order_id = $this->helper->changeStatus(
                $order_id,
                "payment_review",
                "payment_review",
                "chargeback"
                );*/
                }
            } else {
                return new WP_REST_Response(["order_id" => $order_id, "platform" => "woocommerce", "status" => "Order not found"], 404);
            }
        }
        if ($order_id > 0) {
            return new WP_REST_Response(["order_id" => $order_id, "platform" => "woocommerce", "status" => "success"], 200);
        } else {
            return new WP_REST_Response(["order_id" => $order_id, "platform" => "woocommerce", "status" => "Order cannot be created"], 400);
        }
    }

    public function createOrder($jsonData)
    {
     
        $preorder = $this->extractPreorder($jsonData->products[0]->extraData);

        if (!$preorder || !isset($preorder->order_key)) {
            return 0;
        }

        $orderKey = $preorder->order_key;
        $order    = wc_create_order();
        $order->set_order_key($orderKey);

        // add products from cart to order
        $address            = $preorder->address;
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $payapi_gateway     = $available_gateways['payapisecureform'];
        
        $shipping         = $preorder->shipping;
        $items            = $preorder->items;
        $items_in_package = array();
        foreach ($items as $item) {
            $product_id = $item->product_id;
            $product    = wc_get_product($product_id);
            $var_id     = $item->variation_id;
            
            $quantity = (int) $item->quantity;
            if ($var_id > 0) {
                $variationsArray = ["variation" => $item->variation];
                $var_product     = new WC_Product_Variation($var_id);
                $order->add_product($var_product, $quantity, $item);
            } else {
                $order->add_product($product, $quantity, $item);
            }

            $items_in_package[] = $product->get_title() . ' &times; ' . $item->quantity;
        }

        $methodId = $shipping->method_id;

        if (strpos($methodId, ':') !== false) {
            $methodId = strstr($methodId, ':', true);
        }

        // Add shipping costs
        $rate = new WC_Shipping_Rate($methodId, isset($shipping->method_title) ? $shipping->method_title : '', isset($shipping->total) ? floatval($shipping->total) : 0, isset($shipping->taxes) ? $shipping->taxes : [], $methodId);

        $rate->add_meta_data(__('Items', 'payapi-gateway'), implode(', ', $items_in_package));
        $order->add_shipping($rate);

        //set_customer
        //set payment method
        $order->set_payment_method($payapi_gateway);

        if ($address->first_name == 'xxxxx') {
            $address->first_name = $jsonData->shippingAddress->recipientName; //address Details
            $address->last_name  = '.';
            $address->address_1  = $jsonData->shippingAddress->streetAddress;
            $address->address_2  = $jsonData->shippingAddress->streetAddress2;
            $address->city       = $jsonData->shippingAddress->city;
            $address->state      = $jsonData->shippingAddress->stateOrProvince;
            $address->postcode   = $jsonData->shippingAddress->postalCode;
            $order->set_billing_email($jsonData->consumer->email);
            $order->set_billing_phone($jsonData->consumer->mobilePhoneNumber);
        }
        $order->add_order_note("PayApi customer address: \n" .
            $jsonData->shippingAddress->recipientName . "\n" .
            $jsonData->shippingAddress->streetAddress . " " . $jsonData->shippingAddress->streetAddress2 . "\n" .
            $jsonData->shippingAddress->postalCode . ", " . $jsonData->shippingAddress->city . "\n " .
            $jsonData->shippingAddress->stateOrProvince . ", " . $jsonData->shippingAddress->countryCode);

        $order->set_customer_ip_address($preorder->consumerIp);
        $order->set_address($address, 'billing');
        $order->set_address($address, 'shipping');
        if (isset($preorder->coupons)) {
            foreach ($preorder->coupons as $coupon) {
                $this->set_coupon($order, $coupon);
            }
        }

        $order->save();

        $order->calculate_totals();

        //Apply discounts to line_items and recalculate totals
        if (isset($preorder->coupons)) {
            $orderItems = array_values($order->get_items());
            for ($i=0; $i < count($orderItems); $i++) { 
                $orderItems[$i]->set_total($items[$i]->line_total);            
                $orderItems[$i]->set_total_tax($items[$i]->line_total_tax);
                $orderItems[$i]->save();                
            }
            $order->save();
            $order->calculate_totals();
        }

        return $order->get_id();
    }

    public function getOrderId($payapiObject)
    {
        $arr = $this->extractPreorder($payapiObject->products[0]->extraData);
        if (isset($arr) && isset($arr->order_key)) {
            $orderKey = $arr->order_key;
            return wc_get_order_id_by_order_key($orderKey);
        }
        return 0;
    }

    public function addPayment($orderId)
    {
        $order = wc_get_order($orderId);
        $order->add_order_note("Adding payment");
        $order->payment_complete($orderId);
        return $orderId;
    }

    public function set_coupon(&$order, $coupon)
    {

        // coupon amount must be positive float
        if (isset($coupon->amount) && floatval($coupon->amount) < 0) {
            WC_Gateway_Payapi::log("Coupon discount total must be a positive amount.");
            throw new WC_API_Exception('woocommerce_invalid_coupon_total', __('Coupon discount total must be a positive amount.', 'woocommerce'), 400);
        }

        // coupon code is required
        if (empty($coupon->code)) {
            WC_Gateway_Payapi::log("Coupon code is required.");
            throw new WC_API_Exception('woocommerce_invalid_coupon_coupon', __('Coupon code is required.', 'woocommerce'), 400);
        }

        $item = new WC_Order_Item_Coupon();
        $item->set_props(array(
            'code'         => $coupon->code,
            'discount'     => isset($coupon->amount) ? floatval($coupon->amount) : 0,
            'discount_tax' => isset($coupon->tax) ? floatval($coupon->tax) : 0,
        ));

        $item->save();

        do_action('woocommerce_checkout_create_order_coupon_item', $item, $coupon->code, $coupon, $order);

        // Add item to order and save.
        $order->add_item($item);
        $order->save();

    }

    public function extractPreorder($extraData)
    {
        include_once 'JWT.php';
        include_once 'WC_Gateway_Payapi.php';
        try {
            $gateway = new WC_Gateway_Payapi();
            parse_str($extraData, $data);
            $preorder = JWT::decode($data['data'], $gateway->get_payapi_api_key(), ['HS256']);            
            return $preorder;
        } catch (Exception $err) {
            WC_Gateway_Payapi::log("Preorder decode Exception ");
            return false;
        }

    }

}
