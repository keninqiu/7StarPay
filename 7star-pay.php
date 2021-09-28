<?php

/**
 * Plugin Name:7StarPay Payment Gateway for crypto currencies (七星支付，虚拟货币支付)
 * Plugin URI: https://www.7starpay.com/
 * Description: Easily accept payment with digital currency.
 * Version: 2.3.2
 * Tested up to: 5.8.1
 * Author: 7Star Group.
 * Author URI: http://www.7starpay.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: 7starpay
 */

    if (! defined ( 'ABSPATH' )){
        exit (); // Exit if accessed directly
    }

    function wc_7starpay_log($message) {
        if (WP_DEBUG === true) {
            if (is_array($message) || is_object($message)) {
                error_log(print_r($message, true));
            } else {
                error_log($message);
            }
        }
    }

    define('C_WC_7STARPAY_ID','wc7starpaygateway');
    define('C_WC_7STARPAY_DIR',rtrim(plugin_dir_path(__FILE__),'/'));
    define('C_WC_7STARPAY_URL',rtrim(plugin_dir_url(__FILE__),'/'));

    define('C_WC_7STARPAY_OPENAPI_HOST','https://api.blockchaingate.com/v2/');
    define('C_WC_7STARPAY_WEB', 'https://www.madearn.com/wallet/bindpay');

    add_action( 'plugins_loaded', 'init_7star_pay_gateway_class' );

    function init_7star_pay_gateway_class() {
        wc_7starpay_log('init_7star_pay_gateway_class getting started');
        if( !class_exists('WC_Payment_Gateway') ){
            return;
        }
        require_once( plugin_basename( 'class-wc-7star-pay-gateway.php' ) );    
    }

    add_filter( 'woocommerce_payment_gateways', 'add_7star_pay_gateway_class' );
    function add_7star_pay_gateway_class( $methods ) {
        $methods[] = 'WC_7StarPay_Gateway'; 
        return $methods;
    }


    //Set hearbeat to check order status for WeChat pay
    add_action( 'init', 'wc_7starpay_init_heartbeat' );
    function wc_7starpay_init_heartbeat(){
        wp_enqueue_script('heartbeat');
    }

    add_filter( 'heartbeat_settings', 'wc_7starpay_setting_heartbeat' );
    function wc_7starpay_setting_heartbeat( $settings ) {
        $settings['interval'] = 5;
        return $settings;
    }

    add_filter('heartbeat_received', 'wc_7starpay_heartbeat_received', 10, 2);
    add_filter('heartbeat_nopriv_received', 'wc_7starpay_heartbeat_received', 10, 2 );
    function wc_7starpay_heartbeat_received($response, $data){
        if(!isset($data['orderId'])){
            return;
        }

        $gateway = new WC_Snappay_Gateway();
        $isCompleted = $gateway->is_order_completed($data['orderId']);

        if($isCompleted){
            $response['status'] = 'SUCCESS';
        }

        return $response;
    }

    function wc_7starpay_widget_enqueue_script() {   
        $qrcode = 'haha';
        wp_enqueue_script( 'qrcode_script', plugin_dir_url( __FILE__ ) . 'js/qrcode.min.js' );
        $codeScript = 'var qrcode = new QRCode(document.getElementById("code"), {width : 200,height : 200});'.'qrcode.makeCode(' . $qrcode . ')';
        wp_add_inline_script( 'qrcode_script', $codeScript );
    }
    add_action('wp_footer', 'wc_7starpay_widget_enqueue_script');

?>
