<?php /* Template Name: Redirect */?>
<?php
if (!defined('ABSPATH')) {
    exit;
}
global $wp;
$orderkey = false;
$orderId  = false;
if (!isset($_GET['cancel'])) {
    if (isset($_GET['orderkey'])) {
        $orderkey = $_GET['orderkey'];
    }
    if ($orderkey && $orderkey != '') {
        $orderId = wc_get_order_id_by_order_key($orderkey);
    }
    if (!$orderId) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        get_template_part(404);exit();
    } else {
        global $woocommerce;
        $order_received_url = wc_get_endpoint_url('order-received', $orderId, wc_get_page_permalink('checkout'));

        if ('yes' === get_option('woocommerce_force_ssl_checkout') || is_ssl()) {
            $order_received_url = str_replace('http:', 'https:', $order_received_url);
        }

        $order_received_url = add_query_arg('key', $orderkey, $order_received_url);

        header('HTTP/1.1 200 OK');
        header('Location: ' . $order_received_url);
        exit();
    }
} else {
    get_header();
    do_action('woocommerce_before_main_content');    
   ?>

   <header class="entry-header">
      <h1 class="entry-title"><?php echo __('Payment Cancelled', 'payapi-gateway') ?></h1>     
    </header>
   <div class="entry-content">
   <h1 class="product_title entry-title"><?php echo __('Sorry, you cancelled the payment', 'payapi-gateway') ?></h1>
    <p>  <?php echo __('Your products are still on your shopping cart, if you do need any help do not hesitate to contact us.', 'payapi-gateway')?></p>
    
            <a href="<?php echo wc_get_page_permalink('shop') ?>">
              <?php echo __('Continue Shopping', 'payapi-gateway') ?>
            </a>
        </div>
    <?php 
    get_footer();    
}
