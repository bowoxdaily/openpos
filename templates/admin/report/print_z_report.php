<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

?>
<html>
<head>
    <title><?php echo __('z-report','openpos');?></title>
    <style type="text/css">
        #invoice-POS{
            box-shadow: 0 0 1in -0.25in rgba(0, 0, 0, 0.5);
            padding:2mm;
            margin: 0 auto;
            width: 100%;
            background: #FFF;
        }
            
            
            ::selection {background: #f31544; color: #FFF;}
            ::moz-selection {background: #f31544; color: #FFF;}
            h1{
                font-size: 1.5em;
                color: #000;
            }
            h2{font-size: .9em;}
            h3{
            font-size: 1.2em;
            font-weight: 300;
            line-height: 2em;
            }
            p{
            font-size: 1em;
            color: #000;
            line-height: 1.2em;
            }
            
            #top, #mid,#bot{ /* Targets all id with 'col-' */
                border-bottom: 1px solid #EEE;
            }

            #top{min-height: 100px;}
            #mid{min-height: 80px;} 
            #bot{ min-height: 50px;}

            
            .info{
            display: block;
            //float:left;
            margin-left: 0;
            }
            .title{
            float: right;
            }
            .title p{text-align: right;} 
            table{
                width: 100%;
                border-collapse: collapse;
                border-bottom: dotted 1px #000;
            }
            td{
            //padding: 5px 0 5px 15px;
            //border: 1px solid #EEE
            }
            .tabletitle{
                font-weight: bold;
            }
            .service{border-bottom: 1px solid #EEE;}
       
            .itemtext{font-size: 1em;}

            #legalcopy{
                margin-top: 5mm;
            }
            }
    </style>
</head>
<body style="margin:0;">

<div id="invoice-POS">
    
    <div id="top" style="tex-align:center;">
      <div class="logo"></div>
      <div class="info"> 
      <h2><?php echo __('Z Reading Report','openpos');?></h2>
      </div><!--End Info-->
    </div><!--End InvoiceTop-->
    
    <div id="mid">
      <div class="info">
        <ul> 
           
            <li><?php echo __('Register','openpos');?> : <?php echo $register_name ; ?></li>
            <li><?php echo __('Print date','openpos');?> : <?php echo date('Y-m-d h:i:s') ; ?></li>
            
        </ul>
      </div>
    </div><!--End Invoice Mid-->
    
    <div id="bot">
             
                    
                    
					<div id="table">
                        <table>
                            <?php  foreach($table_data as $tkey => $data):?>
                                    <?php foreach($table_label as $key => $label):?>
                                    <tr class="service  <?php echo $key; ?>">
                                        <td class="tableitem" colspan="2"><p class="tabletitle"><?php echo $label; ?></p></td>
                                        <td class="tableitem"><p class="itemtext"><?php echo $data[$key]; ?></p></td>
                                    </tr>
                                    <?php endforeach; ?>
                            <?php endforeach;  ?>
                        </table>
                        
                    </div><!--End Table-->
                     <h2><?php echo __('Payment Info','openpos');?></h2>                   
                    <div id="table-payment">
						<table>
							<tr class="tabletitle">
								<td class="item"><h2><?php echo __('Method','openpos');?></h2></td>
								<td class="Rate"><h2><?php echo __('Total','openpos');?></h2></td>
                            </tr>
                            <?php foreach($sale_payments as $item): ?>
                                <tr class="service">
                                    <td class="tableitem"><p class="itemtext"><?php echo $item['payment_name']; ?></p></td>
                                    <td class="tableitem"><p class="itemtext"><?php echo wc_price($item['total']); ?></p></td>
                                </tr>
                            <?php endforeach; ?>

						</table>
					</div><!--End Table-->
                    <h2><?php echo __('Tax Detail','openpos');?></h2>
                    <div id="table-payment">
						<table>
							<tr class="tabletitle">
								<td class="item"><h2><?php echo __('Tax','openpos');?></h2></td>
								<td class="Rate"><h2><?php echo __('Total','openpos');?></h2></td>
                            </tr>
                            <?php foreach($taxes as $item): ?>
                                <tr class="service">
                                    <td class="tableitem"><p class="itemtext"><?php echo $item['label']; ?></p></td>
                                    <td class="tableitem"><p class="itemtext"><?php echo wc_price($item['total']); ?></p></td>
                                </tr>
                            <?php endforeach; ?>
                           
                                <tr class="tabletitle">
                                    <th><?php echo __('Total','openpos');?></th>
                                    <td class="Rate"><h2><?php echo wc_price($tax_total); ?></h2></td>
                                </tr>  
							

						</table>
					</div><!--End Table-->
	</div><!--End InvoiceBot-->
</div><!--End Invoice-->
</body>
<script type="text/javascript">
   window.print();
</script>
</html>