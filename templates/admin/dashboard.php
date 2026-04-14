<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
$op_nonce = wp_create_nonce( 'op_nonce' );
?>
<?php
/**
 * Created by PhpStorm.
 * User: anhvnit
 * Date: 12/4/16
 * Time: 23:40
 */

?>
<div class="op-admin-wrap wrap">
<h1 class="wp-heading-inline"><?php echo __( 'POS Dashboard', 'openpos' ); ?></h1>
<script type="text/javascript">
    (function($) {
        $('body').on('click','#reset-balance',function () {
            if(confirm('<?php echo __('This function to reset cash balance on your all cashdrawers to 0. Are you sure ?','openpos'); ?>'))
            {
                $.ajax({
                    url: openpos_admin.ajax_url,
                    type: 'post',
                    dataType: 'json',
                    data:{action:'admin_openpos_reset_balance',op_nonce: '<?php echo $op_nonce?>'},
                    success:function(data){
                        $('#openpos-cash-balance').text(0);
                    }
                })
            }
        });
        $('body').on('click','#reset-debit-balance',function () {
            if(confirm('<?php echo __('This function to reset debit balance on your all cashdrawers to 0. Are you sure ?','openpos'); ?>'))
            {
                $.ajax({
                    url: openpos_admin.ajax_url,
                    type: 'post',
                    dataType: 'json',
                    data:{action:'admin_openpos_reset_debit_balance',op_nonce: '<?php echo $op_nonce?>'},
                    success:function(data){
                        $('#openpos-debit-balance').text(0);
                    }
                })
            }
        });

        

        $(document).on('ready',function(){
            

        
            <?php
                $label = array();
                $sale_data = array();
                $transaction_data = array();
                $commision_data = array();
                foreach($chart_data as $index =>  $c)
                {
                    if($index == 0)
                    {
                        continue;
                    }
                    $label[] = $c[0];
                    $sale_data[] = round($c[1],wc_get_price_decimals());;
                    $transaction_data[] = $c[2];
                    $commision_data[] = round($c[3],wc_get_price_decimals());
                }
            ?>
            var ctx = document.getElementById("myChart").getContext("2d");
            var   sale_data = <?php echo json_encode($sale_data) ?>;
            var   commission_data = <?php echo json_encode($commision_data) ?>;

            var   transaction_data = <?php echo json_encode($transaction_data) ?>;;
            var labels =  <?php echo json_encode($label) ?>;
            
            var myChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                    {
                        label: '<?php echo __('Sales','openpos'); ?>',
                        data: sale_data,
                    },
                    {
                        label: '<?php echo __('Profit','openpos'); ?>',
                        data: commission_data,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    }
                    
                ]
                },
                options: {
                        title: {
                            display: true,
                            text: '<?php echo __('All Sales','openpos'); ?>'
                        }
                    }
            });
            <?php  $pie_type = 'register'; ?>
            var data = {
                datasets: [{
                    data: [],
                    backgroundColor: [],
                }],
                labels: []
            };

            var ctx_pie = document.getElementById("myChart-pie").getContext("2d");
            var myPieChart = new Chart(ctx_pie, {
                type: 'pie',
                data: data,
                options: {
                    title: {
                        display: true,
                        text: '<?php echo ($pie_type == 'register' ) ? __('Sale by Register','openpos') : __('Sale by Outlet','openpos'); ?>'
                    }
                }
            });
        
            var ctx_seller = document.getElementById("myChart-seller").getContext("2d");
            var mySellerChart = new Chart(ctx_seller, {
                type: 'horizontalBar',
                data: {
                    labels: [],
                    datasets: []
                },
                options: {
                        title: {
                            display: true,
                            text: '<?php echo __('Sales by Seller','openpos'); ?>'
                        }
                    }
            });
        
            
            
            var ctx_payment = document.getElementById("myChart-payment").getContext("2d");
            var myPaymentChart = new Chart(ctx_payment, {
                type: 'pie',
                data: data,
                options: {
                        title: {
                            display: true,
                            text: '<?php echo __('Sales by Payment','openpos'); ?>'
                        }
                    }
            });
            
            function loadChart(duration){
                    $.ajax({
                            url: openpos_admin.ajax_url,
                            type: 'post',
                            dataType: 'json',
                            data: {action: 'op_dashboard', op_nonce: '<?php echo $op_nonce; ?>',duration:duration},
                            beforeSend:function(){
                                $('.op-widget-ajax-data').addClass('loading');
                            },
                            success:function(response){
                                
                                var sale_data = response['sale_data'];
                                var register_data = response['register_data'];
                                var payment_data = response['payment_data'];
                                var seller_data = response['seller_data'];
                                //sale chart
                                myChart.data.labels = sale_data.label;
                                myChart.data.datasets[0]['data'] = sale_data.data;
                                myChart.data.datasets[1]['data'] = sale_data.commission_data;
                                myChart.update();
                                //register chart

                                myPieChart.data = register_data.data;
                                myPieChart.options.title.text = register_data.label;
                                myPieChart.update();
                                //seller chart

                                mySellerChart.data = seller_data;
                                mySellerChart.update();

                                //payment chart

                                myPaymentChart.data = payment_data;
                                myPaymentChart.update();

                                $('.op-widget-ajax-data').removeClass('loading');
                            }
                    });
            }
        

            loadChart('<?php echo $duration; ?>');

            $(document).on('click','.duration-option',function(){
                $('.duration-option').removeClass('btn-success');
                $(this).addClass('btn-success');
                var duration = $(this).data('duration');
                loadChart(duration);
            });
            
        });

    }(jQuery));
</script>

<div class="op-dashboard-content container">

    <div class="row">
        <div class="col-md-9 col-sm-9 col-xs-9 col-lg-9">
            <a class="btn duration-option <?php  echo ($duration == 'today') ? 'btn-default btn-success':'btn-default' ?> " data-duration="today" href="javascript:void(0)" role="button"><?php echo __('Today','openpos'); ?></a>
            <a class="btn duration-option <?php  echo ($duration == 'yesterday') ? 'btn-default btn-success':'btn-default' ?> " data-duration="yesterday"  href="javascript:void(0)" role="button"><?php echo __('Yesterday','openpos'); ?></a>
            <a class="btn duration-option <?php  echo ($duration == 'this_week') ? 'btn-default btn-success':'btn-default' ?>"  data-duration="this_week" href="javascript:void(0)" role="button"><?php echo __('This Week','openpos'); ?></a>
            <a class="btn duration-option <?php  echo $duration == 'last_7_days' ? 'btn-default btn-success':'btn-default' ?>"  data-duration="last_7_days" href="javascript:void(0)" role="button"><?php echo __('Last 7 Days','openpos'); ?></a>
            <a class="btn duration-option <?php  echo ($duration == 'this_month') ? 'btn-default btn-success':'btn-default' ?>"  data-duration="this_month" href="javascript:void(0)" role="button"><?php echo __('This Month','openpos'); ?></a>
            <a class="btn duration-option <?php  echo $duration == 'last_30_days' ? 'btn-default btn-success':'btn-default' ?>"  data-duration="last_30_days" href="javascript:void(0)" role="button"><?php echo __('Last 30 days','openpos'); ?></a>
            
        </div>
        <div class="col-md-3 col-sm-3 col-xs-3 col-lg-3"><a href="<?php echo $pos_url; ?>"class="button-primary btn-default pull-right" target="_blank"><?php echo __('Goto POS','openpos'); ?></a></div>
    </div>
    <div class="row">
        
            <div class="col-md-8 col-sm-8 col-xs-12 col-lg-8 op-widget-container">
                <div class="op-widget-content op-widget-ajax-data">
                    <canvas id="myChart" height="250" width="800"></canvas>
                </div>
            </div>
            
            <div class="col-md-4 col-sm-4 col-xs-12 col-lg-4 op-widget-container">
                <div class="op-widget-content op-widget-ajax-data">
                    <canvas id="myChart-pie"></canvas>
                </div>
            </div>
        
    </div>
    <div class="row">
            <div class="col-md-8 col-sm-8 col-xs-12 col-lg-8 op-widget-container">
                <div class="op-widget-content op-widget-ajax-data">
                    <canvas id="myChart-seller"></canvas>
                </div>
            </div>
            
            <div class="col-md-4 col-sm-4 col-xs-12 col-lg-4 op-widget-container">
                <div class="op-widget-content op-widget-ajax-data">
                    <canvas id="myChart-payment"></canvas>
                </div>
            </div>
       
    </div>
    <div class=" row">
        <div class="last-orders col-md-8 col-sm-8 col-xs-12 col-lg-8 op-widget-container" >
            <div class="op-widget-content">
                <div class="title"><label><?php echo __('Last Orders','openpos'); ?></label></div>
                <div id="table_div_latest_orders">
                <table class="table table-bordered" style="width: 100%;" id="lastest-order">
                    <thead>
                        <tr>
                        <th><?php echo __('#','openpos'); ?></th>
                        <th><?php echo __('Customer','openpos'); ?></th>
                        <th><?php echo __('Grand Total','openpos'); ?></th>
                        <th><?php echo __('Sale By','openpos'); ?></th>
                        <th><?php echo __('Created At','openpos'); ?></th>
                        <th><?php echo __('Status','openpos'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($dashboard_data['order'] as $order): ?>
                        <tr>
                            <td><?php echo $order['view']; ?></td>
                            <td><?php echo $order['customer_name']; ?></td>
                            <td><?php echo $order['total']; ?></td>
                            <td><?php echo $order['cashier']; ?></td>
                            <td><?php echo $order['created_at']; ?></td>
                            <td class="order_status"><?php echo $order['status']; ?></td>
                        </tr>
                        <?php endforeach;   ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        <div class="total col-md-4 col-sm-4 col-xs-12 col-lg-4 op-widget-container">
            <div class="op-widget-content real-content-container">
                <div class="row ">
                    <div class="col-md-12 col-sm-12 col-lg-12 col-xs-12">
                        <div class="title"><label><?php echo __('Cash Balance','openpos'); ?></label></div>
                        <ul id="total-details">

                            <li>
                                <div class="field-title" style="text-align: center;">
                                <span id="openpos-cash-balance"><?php echo $dashboard_data['cash_balance']; ?></span>
                                    <a href="javascript:void(0);" id="reset-balance" style="outline: none;display: block;border:none;" title="Reset Balance">
                                        <img src="<?php echo OPENPOS_URL; ?>/assets/images/reset.png" height="34px" />
                                    </a>
                                </div>

                            </li>
                        </ul>
                    </div>
                </div>
                <?php if(isset($dashboard_data['debt_balance'])): ?>
                    <div class="row">
                        <div class="col-md-12 col-sm-12 col-lg-12 col-xs-12">
                            <div class="title"><label><?php echo __('Debit Balance','openpos'); ?></label></div>
                            <ul id="total-details">

                                <li>
                                    <div class="field-title" style="text-align: center;color:red;">
                                        <span id="openpos-debit-balance"><?php echo $dashboard_data['debt_balance']; ?></span>
                                        <a href="javascript:void(0);" id="reset-debit-balance" style="outline: none;display: block;border:none;" title="Reset Balance">
                                            <img src="<?php echo OPENPOS_URL; ?>/assets/images/reset.png" height="34px" />
                                        </a>
                                    </div>

                                </li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>
