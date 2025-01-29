<?php
/**
 * Plugin Name: WooCommerce Minimum Order Amount
 * Plugin URI: 
 * Description: Sets minimum order amount for WooCommerce
 * Version: 1.0
 * Author: Your Name
 * License: GPL2
 * Text Domain: wc-minimum-order
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load translations
add_action('init', 'wc_minimum_order_load_textdomain');
function wc_minimum_order_load_textdomain() {
    load_plugin_textdomain('wc-minimum-order', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

// Add menu item to WooCommerce settings
add_filter('woocommerce_settings_tabs_array', 'add_minimum_order_settings_tab', 50);
function add_minimum_order_settings_tab($settings_tabs) {
    $settings_tabs['minimum_order'] = __('Minimum Order', 'wc-minimum-order');
    return $settings_tabs;
}

// Add settings to the tab
add_action('woocommerce_settings_tabs_minimum_order', 'minimum_order_settings_tab');
function minimum_order_settings_tab() {
    woocommerce_admin_fields(get_minimum_order_settings());
}

// Save settings
add_action('woocommerce_update_options_minimum_order', 'update_minimum_order_settings');
function update_minimum_order_settings() {
    woocommerce_update_options(get_minimum_order_settings());
}

// Define settings
function get_minimum_order_settings() {
    $settings = array(
        'section_title' => array(
            'name'     => __('Minimum Order Settings', 'wc-minimum-order'),
            'type'     => 'title',
            'desc'     => '',
            'id'       => 'wc_minimum_order_section_title'
        ),
        'minimum_amount' => array(
            'name'     => __('Minimum Order Amount', 'wc-minimum-order'),
            'type'     => 'number',
            'desc'     => __('Set the minimum order amount', 'wc-minimum-order'),
            'id'       => 'wc_minimum_order_amount',
            'default'  => '0',
            'custom_attributes' => array(
                'min'   => '0',
                'step'  => '0.01'
            )
        ),
        'error_message' => array(
            'name'     => __('Error Message', 'wc-minimum-order'),
            'type'     => 'textarea',
            'desc'     => __('Message to show when order amount is below minimum', 'wc-minimum-order'),
            'id'       => 'wc_minimum_order_error_message',
            'default'  => __('Minimum order amount is {min_amount}', 'wc-minimum-order'),
            'css'      => 'min-width: 300px;'
        ),
        'section_end' => array(
            'type'     => 'sectionend',
            'id'       => 'wc_minimum_order_section_end'
        )
    );
    return $settings;
}

// Check cart total and show error if below minimum
add_action('woocommerce_check_cart_items', 'check_minimum_order_amount');
function check_minimum_order_amount() {
    // Get minimum amount from settings
    $minimum_amount = get_option('wc_minimum_order_amount', 0);
    
    if ($minimum_amount <= 0) {
        return;
    }

    // Get current cart total
    $cart_total = WC()->cart->get_subtotal();

    // Check if cart total is less than minimum
    if ($cart_total < $minimum_amount) {
        // Get error message from settings
        $error_message = get_option('wc_minimum_order_error_message', 
            __('Minimum order amount is {min_amount}', 'wc-minimum-order'));
        
        // Replace placeholder with actual amount
        $error_message = str_replace(
            '{min_amount}', 
            wc_price($minimum_amount), 
            $error_message
        );

        // Add error
        wc_add_notice($error_message, 'error');
    }
}

add_action('wp_head', 'add_minimum_order_styles');
function add_minimum_order_styles() {
    ?>
    <style>
    .checkout-button.disabled {
        opacity: 0.5 !important;
        pointer-events: none !important;
        cursor: not-allowed !important;
    }
    </style>
    <?php
}

// Добавляем скрипт для деактивации кнопки оформления заказа
add_action('wp_footer', 'add_minimum_order_script');
function add_minimum_order_script() {
    if (is_cart() || is_checkout()) {
        $minimum_amount = get_option('wc_minimum_order_amount', 0);
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            function checkMinimumOrder() {
                var cartTotal = parseFloat($('.cart-subtotal .amount').text().replace(/[^0-9,.]/g, '').replace(',', '.'));
                var minAmount = <?php echo $minimum_amount; ?>;
                
                if (cartTotal < minAmount) {
                    $('.checkout-button').addClass('disabled');
                    if ($('.checkout-button').is('a')) {
                        $('.checkout-button').attr('href', '#');
                    }
                    $('.checkout-button').prop('disabled', true);
                    $('#place_order').prop('disabled', true);
                } else {
                    $('.checkout-button').removeClass('disabled');
                    if ($('.checkout-button').is('a')) {
                        $('.checkout-button').attr('href', '<?php echo wc_get_checkout_url(); ?>');
                    }
                    $('.checkout-button').prop('disabled', false);
                    $('#place_order').prop('disabled', false);
                }
            }
            
            // Проверяем при загрузке и при изменении корзины
            checkMinimumOrder();
            $('body').on('updated_cart_totals', checkMinimumOrder);
            $(document.body).on('updated_checkout', checkMinimumOrder);
        });
        </script>
        <?php
    }
}

// Add validation on checkout
add_action('woocommerce_checkout_process', 'validate_minimum_order_amount_on_checkout');
function validate_minimum_order_amount_on_checkout() {
    $minimum_amount = get_option('wc_minimum_order_amount', 0);
    
    if ($minimum_amount <= 0) {
        return;
    }

    $cart_total = WC()->cart->get_subtotal();

    if ($cart_total < $minimum_amount) {
        $error_message = get_option('wc_minimum_order_error_message', 
            __('Minimum order amount is {min_amount}', 'wc-minimum-order'));
        
        $error_message = str_replace(
            '{min_amount}', 
            wc_price($minimum_amount), 
            $error_message
        );

        throw new Exception($error_message);
    }
}

// Add activation hook
register_activation_hook(__FILE__, 'minimum_order_plugin_activate');
function minimum_order_plugin_activate() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('This plugin requires WooCommerce to be installed and active.', 'wc-minimum-order'));
    }
}