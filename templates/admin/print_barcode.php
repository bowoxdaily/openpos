<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<?php
global $OPENPOS_SETTING;
global $op_warehouse;
$sheet_width = $OPENPOS_SETTING->get_option('sheet_width','openpos_label',8.5);
$sheet_height = $OPENPOS_SETTING->get_option('sheet_height','openpos_label',11);
$sheet_padding_top = $OPENPOS_SETTING->get_option('sheet_margin_top','openpos_label',0.5);
$sheet_padding_right = $OPENPOS_SETTING->get_option('sheet_margin_right','openpos_label',0.188);
$sheet_padding_bottom = $OPENPOS_SETTING->get_option('sheet_margin_bottom','openpos_label',0.5);
$sheet_padding_left = $OPENPOS_SETTING->get_option('sheet_margin_left','openpos_label',0.188);
$vertical_space = $OPENPOS_SETTING->get_option('sheet_vertical_space','openpos_label',0);
$horizontal_space = $OPENPOS_SETTING->get_option('sheet_horizontal_space','openpos_label',0.125);
$label_width = $OPENPOS_SETTING->get_option('barcode_label_width','openpos_label',2.625);
$label_height = $OPENPOS_SETTING->get_option('barcode_label_height','openpos_label',1);

$label_padding_top = $OPENPOS_SETTING->get_option('barcode_label_padding_top','openpos_label',0.1);
$label_padding_right = $OPENPOS_SETTING->get_option('barcode_label_padding_right','openpos_label',0.1);
$label_padding_bottom = $OPENPOS_SETTING->get_option('barcode_label_padding_bottom','openpos_label',0.1);
$label_padding_left = $OPENPOS_SETTING->get_option('barcode_label_padding_left','openpos_label',0.1);

$barcode_width = $OPENPOS_SETTING->get_option('barcode_width','openpos_label',2);
$barcode_height = $OPENPOS_SETTING->get_option('barcode_height','openpos_label',0.5);
$barcode_label_template = $OPENPOS_SETTING->get_option('barcode_label_template','openpos_label','<p style="padding: 0; margin: 0;">[op_product attribute="name"]</p><p style="padding: 0; margin: 0;">[barcode]</p><p style="padding: 0; margin: 0;">[op_product attribute="barcode"]</p>');
$unit = $OPENPOS_SETTING->get_option('unit','openpos_label','in');
$default_qty = 30;
$product_id = isset($_GET['id']) ? 1*$_GET['id'] : 0;
if($product_id)
{
    $total_qty = $op_warehouse->get_total_qty($product_id);
    if($total_qty && $total_qty > 0)
    {
        $default_qty = $total_qty;
    }
}

$qty = isset($_GET['qty']) ? 1 * $_GET['qty'] : apply_filters( 'op_print_label_default_qty', $default_qty);
$sample_template_url = OPENPOS_URL.'/default/barcode_label_template_sample.txt';
$op_nonce = wp_create_nonce( 'op_nonce' );
?>
<div class="op-admin-wrap wrap">
    <div id="wrap-loading">
        <div class="lds-ellipsis"><div></div><div></div><div></div><div></div></div>
    </div>
    
    <p  class="page-label"> <?php echo __( 'Barcode Label Composer', 'openpos' ); ?></p>
    <div class="label-container">
    <div class="label-setting">
        <form method="post" id="label-setting-frm">
            <input type="hidden" name="product_id" value="<?php echo $product_id; ?>" />
            <input type="hidden" name="op_nonce" value="<?php echo $op_nonce; ?>" />

            <div class="form-row" style="width: 100%;margin: 0 auto;">
                <table style="width: 100%;">
                    <tr>
                        <th><?php echo __( 'Unit:', 'openpos' ); ?></th>
                        <td>
                            <select name="unit">
                                <option value="in" <?php echo ($unit == 'in')? 'selected':''; ?>><?php echo __( 'Inch', 'openpos' ); ?></option>
                                <option value="mm" <?php echo ($unit == 'mm')? 'selected':''; ?>><?php echo __( 'Millimeter', 'openpos' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo __( 'Sheet Width x Height:', 'openpos' ); ?></th>
                        <td><input type="text" value="<?php echo $sheet_width; ?>" name="sheet_width"> x <input name="sheet_height" type="text" value="<?php echo $sheet_height; ?>"></td>
                    </tr>
                    <tr>
                        <th><?php echo __( 'Vertical Spacing:', 'openpos' ); ?></th>
                        <td><input type="text" name="sheet_vertical_space" value="<?php echo $vertical_space; ?>"></td>
                    </tr>
                    <tr>
                        <th><?php echo __( 'Horizontal Spacing:', 'openpos' ); ?></th>
                        <td><input type="text" name="sheet_horizontal_space"  value="<?php echo $horizontal_space; ?>"></td>
                    </tr>


                    <tr>
                        <th><?php echo __( 'Sheet Margin (top x right x bottom x left):', 'openpos' ); ?></th>
                        <td>
                            <input type="text" name="sheet_margin_top"   value="<?php echo $sheet_padding_top; ?>"> x
                            <input type="text" name="sheet_margin_right" value="<?php echo $sheet_padding_right; ?>"> x
                            <input type="text" name="sheet_margin_bottom" value="<?php echo $sheet_padding_bottom; ?>"> x
                            <input type="text" name="sheet_margin_left" value="<?php echo $sheet_padding_left; ?>">
                        </td>
                    </tr>

                    <tr>
                        <th><?php echo __( 'Label Size (w x h):', 'openpos' ); ?></th>
                        <td>
                            <input type="text" name="barcode_label_width" value="<?php echo $label_width; ?>"> x <input name="barcode_label_height" type="text" value="<?php echo $label_height; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo __( 'Label Padding (top x right x bottom x left):', 'openpos' ); ?></th>
                        <td>
                            <input type="text" name="barcode_label_padding_top"   value="<?php echo $label_padding_top; ?>"> x
                            <input type="text" name="barcode_label_padding_right" value="<?php echo $label_padding_right; ?>"> x
                            <input type="text" name="barcode_label_padding_bottom" value="<?php echo $label_padding_bottom; ?>"> x
                            <input type="text" name="barcode_label_padding_left" value="<?php echo $label_padding_left; ?>">
                        </td>
                    </tr>

                    <tr>
                        <th><?php echo __( 'Barcode Image Size ( w x h ):', 'openpos' ); ?></th>
                        <td>
                            <input type="text" name="barcode_width" value="<?php echo $barcode_width; ?>"> x <input name="barcode_height" type="text" value="<?php echo $barcode_height; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo __( 'Template:', 'openpos' ); ?></th>
                        <td>
                            <textarea style="width: 100%;min-height:150px;" name="barcode_label_template"><?php echo $barcode_label_template; ?></textarea>
                            <p class="help">
                                <?php echo __( 'use [barcode with="" height=""] to adjust barcode image, [op_product attribute="attribute_name"] with attribute name: <b>name, price ,regular_price, sale_price, width, height,length,weight</b> and accept html,inline style css string', 'openpos' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo __( 'Number Of Label:', 'openpos' ); ?></th>
                        <td><input type="number" name="total" value="<?php echo $qty; ?>"></td>
                    </tr>
                    <tr>
                        <th></th>
                        <td>
                            <a href="javascript:void(0)" id="load-sample" data-sample="<?php echo esc_url($sample_template_url); ?>"><?php echo __( 'Load sample', 'openpos' ); ?></a>
                            <span style="margin: 0 5px;color: #ccc;">|</span>
                            <a href="javascript:void(0)" id="load-guide" data-sample="<?php echo esc_url($sample_template_url); ?>"><?php echo __( 'Quick Guide', 'openpos' ); ?></a>
                           
                            
                        </td>
                    </tr>
                    <tr>
                        <th></th>
                        <td>
                           
                            <button type="button"  id="preview-label-btn"><?php echo __( 'Save And Preview', 'openpos' ); ?></button>
                            <button type="button" id="print-label-btn" name="print" ><?php echo __( 'Print', 'openpos' ); ?></button>
                            
                        </td>
                    </tr>
                </table>

            </div>
        </form>
    </div>
    <div class="preview-live">
        <iframe id="preview-frame" style="width:calc(100% - 1px);height:100%;min-height:490px;    background: #fff;
        border: none;" src=""><?php echo __( 'Preview', 'openpos' ); ?></iframe>
    </div>
    </div>


</div>


<!-- Modal HTML cho Quick Guide -->
<div id="op-guide-modal" class="op-modal" style="display:none;">
    <div class="op-modal-overlay"></div>
    <div class="op-modal-content">
        <span class="op-modal-close">&times;</span>
        <h2><?php echo __('Barcode Label Quick Guide', 'openpos'); ?></h2>
        <div class="op-modal-body">
            <img src="<?php echo OPENPOS_URL; ?>/assets/images/label-guide.png" alt="<?php esc_attr_e('Guide', 'openpos'); ?>" class="op-guide-image" onerror="this.style.display='none'" />
            
            <div class="op-guide-text">
                
                <h3><?php echo __('Sample Template:', 'openpos'); ?></h3>
                <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px;">
                    &lt;div style="text-align: center; padding: 5px;"&gt;
                        &lt;p style="margin: 0; font-weight: bold; font-size: 14px;"&gt;[op_product attribute="name"]&lt;/p&gt;
                        &lt;p style="margin: 5px 0;"&gt;[barcode]&lt;/p&gt;
                        &lt;p style="margin: 0; font-size: 12px;"&gt;[op_product attribute="barcode"]&lt;/p&gt;
                        &lt;p style="margin: 0; font-size: 10px;"&gt;Price: [op_product attribute="price"]&lt;/p&gt;
                    &lt;/div&gt;
                </pre>

                
            </div>
        </div>
    </div>
</div>


<style>
    #print-label-btn{
        border: solid 1px #000;
        padding:  5px 7px;
        text-transform: uppercase;
        margin-top: 15px;
        background:  #000;
        color: #fff;
    }
    #preview-label-btn{
        border: solid 1px blue;
        padding:  5px 7px;
        text-transform: uppercase;
        margin-top: 15px;
        background:  blue;
        color: #fff;
    }
    form input{
        width: 100px;
        text-align: right;
        padding: 5px 2px;
    }
    .form-row tr:nth-child(odd){
        background: #e6e6e6;
    }
    .form-row tr td{
        padding: 5px;
    }
    .form-row th{
        text-align: left;
        font-size:10px;
        padding-left: 5px;
    }
    .label-setting{
        width: calc(50% - 2px);
        float: left;
        overflow: auto;
        background: #ccc;
        padding: 5px ;
        min-height: 500px;
        border:solid 1px #ccc;
    }
    .preview-live{
        float: left;
        height: fit-content;
        display: block;
        width: calc(50% - 2px);
        overflow: auto;
        min-height: 500px;
        border:solid 1px #00BCD4;
        padding: 5px 0;
        background: #00BCD4;
    }
    .page-label{
        font-size: 20px;
        font-weight: bold;
        padding: 20px 0;
    }
    .label-setting input{
        width: 60px!important;
    }

    /* Modal Styles */
    .op-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 999999;
    }
    
    .op-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        animation: op-fadeIn 0.3s ease-in-out;
    }
    
    .op-modal-content {
        position: relative;
        background: #fff;
        max-width: 900px;
        max-height: 90vh;
        margin: 2% auto;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        overflow-y: auto;
        z-index: 1000000;
        animation: op-slideDown 0.3s ease-out;
    }
    
    @keyframes op-fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes op-slideDown {
        from { 
            transform: translateY(-50px);
            opacity: 0;
        }
        to { 
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    .op-modal-close {
        position: absolute;
        top: 10px;
        right: 20px;
        font-size: 32px;
        font-weight: bold;
        color: #999;
        cursor: pointer;
        line-height: 1;
        transition: color 0.2s;
        z-index: 10;
    }
    
    .op-modal-close:hover {
        color: #333;
    }
    
    .op-modal-content h2 {
        margin: 0 0 20px 0;
        padding: 0 0 15px 0;
        font-size: 24px;
        color: #333;
        border-bottom: 3px solid #00BCD4;
    }
    
    .op-modal-body {
        padding: 10px 0;
    }
    
    .op-guide-image {
        max-width: 100%;
        height: auto;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-bottom: 20px;
        display: block;
    }
    
    .op-guide-text h3 {
        color: #333;
        margin: 25px 0 15px 0;
        font-size: 18px;
        border-left: 4px solid #00BCD4;
        padding-left: 12px;
        font-weight: 600;
    }
    
    .op-guide-text ul {
        list-style: none;
        padding: 0;
        margin: 10px 0 20px 0;
    }
    
    .op-guide-text ul li {
        padding: 10px;
        border-bottom: 1px solid #f0f0f0;
        line-height: 1.6;
    }
    
    .op-guide-text ul li:last-child {
        border-bottom: none;
    }
    
    .op-guide-text ul li:hover {
        background-color: #f9f9f9;
    }
    
    .op-guide-text strong {
        color: #00BCD4;
        font-family: 'Courier New', monospace;
        background: #f5f5f5;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 13px;
    }
    
    .op-guide-text p {
        line-height: 1.8;
        color: #555;
        margin: 10px 0;
    }
    
    .op-guide-text pre {
        background: #f5f5f5;
        padding: 15px;
        border-radius: 4px;
        overflow-x: auto;
        font-size: 13px;
        border: 1px solid #e0e0e0;
        line-height: 1.5;
    }
    
    body.op-modal-open {
        overflow: hidden;
    }
    
    #load-guide, #load-sample {
        color: #0073aa;
        text-decoration: none;
        font-weight: 500;
        cursor: pointer;
    }
    
    #load-guide:hover, #load-sample:hover {
        color: #005177;
        text-decoration: underline;
    }
    
    @media (max-width: 768px) {
        .op-modal-content {
            margin: 10px;
            padding: 20px;
            max-width: calc(100% - 20px);
            max-height: calc(100vh - 20px);
        }
        
        .op-modal-content h2 {
            font-size: 20px;
        }
        
        .op-guide-text h3 {
            font-size: 16px;
        }
        
        .op-guide-text pre {
            font-size: 11px;
            padding: 10px;
        }
    }
</style>
<script type="text/javascript">
    (function($) {
        var form_values = 'product_id=<?php echo (int)$_GET['id']; ?>';// $('#label-setting-frm').serialize();
            let total = $('#label-setting-frm').find('input[name="total"]').first().val();
            total = 1 * total;
            if(!total)
            {
                total = 1;
            }
            form_values += '&total='+total;
            form_values += "&is_preview=1&is_print=0&action=print_barcode&op_nonce=<?php echo $op_nonce;?>" ;
            var frame_url = '<?php echo admin_url('admin-ajax.php'); ?>?'+form_values;
            $('#preview-frame').attr('src',frame_url);

        // Handle Quick Guide click
        $(document).on('click', '#load-guide', function(e) {
            e.preventDefault();
            $('#op-guide-modal').fadeIn(300);
            $('body').addClass('op-modal-open');
        });

        // Handle modal close
        $(document).on('click', '.op-modal-close, .op-modal-overlay', function() {
            $('#op-guide-modal').fadeOut(300);
            $('body').removeClass('op-modal-open');
        });

        // Close modal on ESC key
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27 && $('#op-guide-modal').is(':visible')) {
                $('#op-guide-modal').fadeOut(300);
                $('body').removeClass('op-modal-open');
            }
        });

        $('body').on('click','#preview-label-btn',function () {
                var _form_values_save = $('#label-setting-frm').serialize();
                var _form_values = 'product_id=<?php echo (int)$_GET['id']; ?>';
                let total = $('#label-setting-frm').find('input[name="total"]').first().val();
                total = 1 * total;
                if(!total)
                {
                    total = 1;
                }
                _form_values += '&total='+total;
                
                form_values = _form_values+"&is_preview=1&is_print=0&action=print_barcode&op_nonce=<?php echo $op_nonce;?>" ;

                $.ajax({
                        url: '<?php echo admin_url( 'admin-ajax.php?action=save_bacode_setting' ); ?>',
                        type: 'post',
                        dataType: 'json',
                        data: _form_values_save,
                        beforeSend:function(){
                            $('body').addClass('op_loading');
                        },
                        success:function(data){
                           
                            $('body').removeClass('op_loading');
                            var  t = new Date().getTime();
                            var frame_url = '<?php echo admin_url('admin-ajax.php'); ?>?t='+t+'&'+form_values;
                            $('#preview-frame').attr('src',frame_url);
                        }
                });


               
        })

        $('body').on('click','#print-label-btn',function () {
                let total = $('#label-setting-frm').find('input[name="total"]').first().val();
                //var form_values = $('#label-setting-frm').serialize();
                var form_values = 'product_id=<?php echo (int)$_GET['id']; ?>';
                total = 1 * total;
                if(!total)
                {
                    total = 1;
                }
                form_values += '&total='+total;
                form_values += "&is_preview=0&is_print=1&action=print_barcode&op_nonce=<?php echo $op_nonce;?>" ;
                var frame_url = '<?php echo admin_url('admin-ajax.php'); ?>?'+form_values;
                window.open(frame_url);
        })
        var form_height = $('#label-setting-frm').height();
        if(form_height > 400)
        {
            form_height -= 5;
            $('#preview-frame').css('height',form_height+'px');
        }

        function downloadObjectAsJson(exportObj, exportName){
        var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(exportObj));
        var downloadAnchorNode = document.createElement('a');
        downloadAnchorNode.setAttribute("href",     dataStr);
        downloadAnchorNode.setAttribute("download", exportName + ".json");
        document.body.appendChild(downloadAnchorNode); // required for firefox
        downloadAnchorNode.click();
        downloadAnchorNode.remove();
    }
        $(document).on('click','#load-sample',function(){
                var sample_url = $(this).data('sample');

                //var formData = $('#label-setting-frm').serializeArray();
                //downloadObjectAsJson(formData,'template');
                var form = $('#label-setting-frm');
                $.ajax({
                    url: sample_url,
                    type: 'get',
                    dataType: 'json',
                    beforeSend:function(){
                        $('body').addClass('op_loading');
                    },
                    success:function(data){
                        
                        for(let i=0;i<data.length;i++)
                        {
                            let field = data[i];
                            
                            form.find('input[name="'+field['name']+'"]').val(field['value']);
                            form.find('select[name="'+field['name']+'"]').val(field['value']);
                            form.find('textarea[name="'+field['name']+'"]').val(field['value']);
                        }
                       

                        
                       $('body').removeClass('op_loading');
                       
                        
                    },
                    error:function(){
                        $('body').removeClass('op_loading');
                    }
                });
            });
        console.log(form_height);
    }(jQuery));
</script>