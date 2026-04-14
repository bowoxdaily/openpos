<?php

require_once OPENPOS_DIR.'/bill/protect.php';

global $op_register;
$site_id = isset($_GET['site']) ? (int)$_GET['site'] : 0;
$id = esc_attr($_GET['id']);
$register = $op_register->get((int)$id);

$password = apply_filters('openpos_bill_screen_password', '');
if($password)
{
    Protect\with('form.php', $password);
}
?>
<?php if(!empty($register)):  ?>
<html lang="en" >
<head>
    <meta charset="utf-8">
    <title><?php echo __( 'Bill Screen', 'openpos' ); ?> - <?php echo $register['name']; ?></title>
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <script>
        var data_url = '<?php echo $op_register->bill_screen_file_url($register['id'],$site_id); ?>';
        var data_template= <?php echo json_encode(array('template' => $op_register->bill_template()));?>;
        
        var lang_obj = {
            'label_cashier': '<?php echo __('Cashier','openpos'); ?>',
            'label_products': '<?php echo __('Products','openpos'); ?>',
            'label_product': '<?php echo __('Product','openpos'); ?>',
            'label_price': '<?php echo __('Price','openpos'); ?>',
            'label_qty': '<?php echo __('Qty','openpos'); ?>',
            'label_total': '<?php echo __('Total','openpos'); ?>',
            'label_grand_total': '<?php echo __('Grand Total','openpos'); ?>'
        };
        var bill_frequency_time = 1000;
    </script>
    <?php
    $handes = array(
        'openpos.bill.style'
    );
    wp_print_styles($handes);
    ?>

</head>
<body>
<div  id="bill-content"></div>

<?php
$handes = array(
    'openpos.bill.script'
);
wp_print_scripts($handes);
?>

</body>
</html>
<?php else: ?>
    <h1> <?php echo __('Opppos !!!!','openpos'); ?></h1>
<?php endif; ?>


