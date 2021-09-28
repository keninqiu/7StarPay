<?php
if (! defined ( 'ABSPATH' ))
    exit (); // Exit if accessed directly

class WC_7StarPay_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = C_WC_7STARPAY_ID;
        $this->icon =C_WC_7STARPAY_URL. '/images/7star-pay.png';
        $this->has_fields = false;
        
        $this->method_title = 'Payment Gateway for 7StarPay';
        $this->method_description='7StarPay provided by <a href="http://www.7starpay.com" target="_blank">7StarPay Inc.</a>';

        $this->init_form_fields ();
        
        $this->title = $this->get_option ( 'title' );
        $this->description = $this->get_option ( 'description' );
        $this->merchantId = $this->get_option ( 'merchantId' );
        
        add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) ); // WC <= 1.6.6
        add_action( 'woocommerce_update_options_payment_gateways_'.C_WC_7STARPAY_ID, array( $this, 'process_admin_options' ) ); // WC >= 2.0
        add_action( 'woocommerce_api_wc_7starpay_notify', array( $this, 'wc_7starpay_notify' ) );
        add_action( 'woocommerce_receipt_'.C_WC_7STARPAY_ID, array($this, 'receipt_page'));
        add_action( 'woocommerce_thankyou', array( $this, 'thankyou_page' ) );
    }

    //heartbeat call this method to check order status
    public function is_order_completed($order_id){
        
        global $woocommerce;
        $order = new WC_Order( $order_id );
        $isCompleted = false;
        $orderStatus = $order->get_status();
        //call wordpress first, if not complated call snappay api
        if($orderStatus == 'completed' || $orderStatus == 'processing' || $orderStatus == 'refunded'){
            $isCompleted = true;
        } else {
            if($this->starpay_query_order_status($order_id) === 'SUCCESS'){
                $isCompleted = true;
                //change order status
                if ( $order->get_status() != 'completed' || $order->get_status() != 'processing' || $order->get_status() != 'refunded') {
                    $order->payment_complete();
                    // clear cart
                    $woocommerce->cart->empty_cart();
                }
            }
        }
        return $isCompleted;
    }

    function starpay_query_order_status($order_id){
      $order = new WC_Order ( $order_id );
      $starpayOrderId = get_post_meta( $order->get_id(), 'starpayOrderId', true );

      $url = C_WC_7STARPAY_OPENAPI_HOST.'7star-payment/orderquery/'.($this->merchantId).'/'.$starpayOrderId;
    
      $json = $this->do_get_request(esc_url($url));
      
      $ret = json_decode($json['body'], true);
      if($ret['ok']) {
        $data = $ret['_body'];
        if($data[3] == 1) {
          return 'SUCCESS';
        } 
      }

      return 'FAILED';
    }

    public function thankyou_page($order_id) {
        $this->is_order_completed($order_id);
        $order = new WC_Order( $order_id );
    }

    function redirect($url){
        header('Location: '.$url);
        exit();
    }
//插件选项
    function init_form_fields() {

        $title = array (
            'title' => __ ( 'Title 标题', C_WC_7STARPAY_ID ),
            'type' => 'text',
            'description' => __ ( 'This is the payment method title the user will see during checkout.', C_WC_7STARPAY_ID ),
            'default' => __ ( '7StarPay for crypto currency', C_WC_7STARPAY_ID ),
            'css' => 'width:400px'
        );

        $description = array (
            'title' => __ ( 'Description 描述', C_WC_7STARPAY_ID ),
            'type' => 'textarea',
            'description' => __ ( 'This controls the description the user sees during checkout.', C_WC_7STARPAY_ID ),
            'default' => __ ( 'Seven Star Global Wealth Club, with many years of experience in the development and investment of blockchain and digital currency, using sophisticated models and advanced technical means to give full play to the decentralized, safe and reliable characteristics of the blockchain, foresight, and design a set Ensure to keep up with the pace of digital currency development, optimize returns, avoid risks, and enable investors to take off with the digital revolution in the Seven-Star Digital Wealth System.', C_WC_7STARPAY_ID ),
            'css' => 'width:400px'
        );

        $merchantId = array (
            'title' => __ ( 'Merchant Wallet Address', C_WC_7STARPAY_ID ),
            'type' => 'text',
            'description' => __ ( 'Register your merchant account from <a href="https://www.madearn.com/merchant" target="_blank">here</a> with 7StarPay. You can find the Merchant Wallet Address in the wallet page.', C_WC_7STARPAY_ID ),
            'css' => 'width:400px',
            'default' => __ ( '', C_WC_7STARPAY_ID )
        );

        $this->form_fields = array();
        $this->form_fields['title'] = $title;
        $this->form_fields['description'] = $description;
        $this->form_fields['merchantId'] = $merchantId;
    }

    function getPayLink($qrcodejson) {
    return C_WC_7STARPAY_WEB.'?to='.$qrcodejson['to'].'&data='.$qrcodejson['data']; 
    }

    function process_payment( $order_id ) {
            $order = new WC_Order( $order_id );

            return array (
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url ( true )
            );
    }

    function generate_7starpay_order_id( $wp_order_id ){
        $milliseconds = number_format(round(microtime(true) * 1000), 0, '', '');
        return "$wp_order_id"."$milliseconds";
    }

    function get_wp_order_id( $sp_order_id ){
        $orderIdString = substr($sp_order_id, 2, 51);
        $orderId = (int)$orderIdString;
        return $orderId;
    }

    public function wc_7starpay_notify() {
        global $woocommerce;

        //get json request notify from snappay
        $json_data = file_get_contents("php://input");
        $json_obj = json_decode($json_data, true);
        $smartContractAdd = sanitize_text_field($json_obj['smartContractAdd']);
        $starpayOrderId = sanitize_text_field($json_obj['orderId']);
        $txid = sanitize_text_field($json_obj['txid']);
        $order_id = $this->get_wp_order_id($starpayOrderId);
        update_post_meta($order_id, 'txid', $txid);
        $order = new WC_Order( $order_id );
        //change order status
        if ( $order->get_status() != 'completed' || $order->get_status() != 'processing' ) {
            $order->payment_complete();
        // clear cart
            $woocommerce->cart->empty_cart();
            do_action( 'woocommerce_thankyou', $order_id );
        } 
        $this->redirect(C_WC_7STARPAY_URL.'/starpaynotifyresponse.php?orderId='.$order_id);
    }

    public function receipt_page($order_id) {
        $order = new WC_Order($order_id);
        $currency = get_woocommerce_currency();
        $orderTotal = $order->get_total();
        $USDTAmount = $orderTotal;
        if($currency != 'USD') {

            try {
                $url = 'https://api.frankfurter.app/latest?from=' . $currency . '&to=USD';
                $json = $this->do_get_request(esc_url($url));
                if($json && $json['body']) {
                    $ret = json_decode($json['body'], true);
                    if($ret['rates'] && $ret['rates']['USD']) {
                        $USDTAmount = $ret['rates']['USD'] * $orderTotal;
                        $USDTAmount = number_format($USDTAmount, 2, '.', '');
                    }
                } else {
?>
                    <p>Your currency is not supported with 7StarPay.</p>
<?php                    
                    return;
                }

            } catch (WP_Error $e) {
 ?>
                <p>Your currency is not supported with 7StarPay.</p>
 <?php           
                return;    
            }
        }

        $returnUrl = $this->get_return_url( $order );
        $starpayOrderId = $this->generate_7starpay_order_id($order_id);
        update_post_meta($order_id, 'starpayOrderId', $starpayOrderId);
        $post_data = array(
            'orderId' => sanitize_text_field($starpayOrderId),
            'merchantWalletAddress' => sanitize_text_field($this->merchantId),
            'coin' => 'USDT',
            'totalAmount' => $USDTAmount,
            'taxAmount' => 0,
            'notify_url' => get_site_url().'/?wc-api=wc_7starpay_notify',
        );
        $data_json =  json_encode($post_data);
        $url = C_WC_7STARPAY_OPENAPI_HOST.'7star-payment/qrcode';
        $json = $this->do_post_request(esc_url($url), $data_json);
        $ret = json_decode($json['body'], true);
        if($ret['ok']) {
            $qrcodejson = $ret['_body'];
            $qrcode = json_encode($qrcodejson);
            
            
    ?>
        <p>你需要支付<?= esc_textarea($USDTAmount) ?> USDT。 You need to pay <?= esc_textarea($USDTAmount) ?> USDT.</p>
        <p>请使用七星支付App扫描下方二维码进行支付。Please scan the QR code using the 7StarPay App to complete payment.</p>


                    <div>
                        <div style="display: inline-block; margin: 0;">

                            <div id="code" class="codestyle" value='<?= esc_attr($qrcode); ?>'></div>


                            <?php 
        function wc_7starpay_widget_enqueue_script() {
            wp_enqueue_script( 'qrcode_script', plugin_dir_url( __FILE__ ) . 'js/qrcode.min.js' );
            $codeScript = 'var qrcode = new QRCode(document.getElementById("code"), {width : 200,height : 200});'.'qrcode.makeCode(jQuery("#code").attr( "value" ))';
            wp_add_inline_script( 'qrcode_script', $codeScript );
        }
        add_action('wp_footer', 'wc_7starpay_widget_enqueue_script');

                            ?>

                        </div>
                    </div>
                    <p>或者通过<a href="<?= esc_url($this->getPayLink($qrcodejson));?>" target="_blank">七星支付Web</a>进行支付。Or Pay with the <a href="<?= esc_url($this->getPayLink($qrcodejson));?>" target="_blank">7StarPay Web</a> to complete payment.</p>

                    <script>
                    jQuery(document).ready(function() {

                            jQuery(document).on('heartbeat-send', function(event, data) {
                                data['orderId'] = '<?= esc_textarea($order_id) ?>'; 
                            });

                            jQuery(document).on('heartbeat-tick', function(event, data) {
                                if(data['status']){
                                    if(data['status'] === 'SUCCESS'){
                                        window.location.replace('<?= esc_url($returnUrl) ?>');
                                    }
                                }
                            });

                            // set the heartbeat interval
                            wp.heartbeat.interval( 'fast' );
                        });     
                    </script>

<?php


        }else{
                wc_add_notice( 'Payment error:'.$ret['error'], 'error' );
                $order->update_status('failed', $ret['error']);
                wp_safe_redirect( wc_get_page_permalink( 'checkout' ) );
            }
        }

        function do_post_request($url, $post_data){
            $result = wp_remote_post( $url, array( 
                'headers' => array("Content-type" => "application/json;charset=UTF-8"),
                'body' => $post_data ) );
            return $result;
        }

        function do_get_request($url){
            $result = wp_remote_get( $url );
            if( is_wp_error( $result ) ) {
                return false;
            }
            return $result;
        }
    }
?>
