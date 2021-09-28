<?php
    $return_data = array(
        'code' => '0',
        'orderId' => $_GET['orderId']
    );
    echo json_encode( $return_data );
?>
