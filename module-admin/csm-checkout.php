<?php
/**
 * Rearrange checkout field and modify the data before saving
 */
class CSM_Checkout {
  function __construct() {
    add_action('woocommerce_checkout_update_user_meta', [$this, 'update_user_meta'], 99, 2);
    add_action('woocommerce_checkout_update_order_meta', [$this, 'update_order_meta'], 99, 2);

    add_filter('woocommerce_cart_shipping_packages', [$this, 'parse_shipping_package']);
  }
}
