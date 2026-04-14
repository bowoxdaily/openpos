<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Queue</title>
    <style>
        .processing .order-item[data-progress]::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: attr(data-progress %);
            background: linear-gradient(90deg, #007bff 0%, #00c3ff 100%);
            opacity: 0.3;
            z-index: 0;
            transition: width 0.4s;
        }
        
    </style>
     <?php
    $handes = array(
        'openpos.queue.style'
    );
    wp_print_styles($handes);
    ?>
</head>
<body>
    <script type="text/html" id="tmpl-ready-order">
        <div class="order-item-container"><div class="order-item">{{data.label}}</div></div>
    </script>
    <script type="text/html" id="tmpl-pending-order">
    <div class="order-item-container"><div class="order-item" data-progress="{{data.percentage}}">{{data.label}}</div></div>
    </script>

    <div class="container">
        <div class="area ready">
            <h2><?php echo __('Ready to Pick','openpos');?></h2>
            <div class="orders" id="ready-orders">
                <div class="no-orders"><?php echo __('No orders in queue','openpos');?></div>
                
            </div>
        </div>
        <div class="area processing">
            <h2><?php echo __('Processing','openpos');?></h2>
            <div class="orders" id="processing-orders">
                <div class="no-orders"><?php echo __('No orders in queue','openpos');?></div>
               
            </div>
        </div>
    </div>
<?php
    $handes = array(
        'openpos.queue.script'
    );
    wp_print_scripts($handes);
?>
</body>
</html>