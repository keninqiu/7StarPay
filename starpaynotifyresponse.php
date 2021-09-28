<?php
    $return_data = array(
        'code' => '0',
        'orderId' => sanitize_text_field($_GET['orderId'])
    );
    echo json_encode( $return_data );
?>
