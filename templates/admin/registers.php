<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<?php
global $op_warehouse;
global $op_register;
global $op_woo;
global $OPENPOS_SETTING;
$op_nonce = wp_create_nonce( 'op_nonce' );
$warehouses = $op_warehouse->warehouses();
$cashiers = $op_woo->get_cashiers();
$registers = $op_register->registers();
$openpos_type = $OPENPOS_SETTING->get_option('openpos_type','openpos_pos');
$default = array(
    'id' => 0,
    'name' => '',
    'warehouse' => 0,
    'cashiers' => array(),
    'register_mode' => 'cashier',
    'status' => 'publish',
);
$is_new = true;
if(isset($_GET['id']) && $id = $_GET['id'])
{
    $current_register = $op_register->get($id);
    if(!empty($current_register))
    {
        $default = $current_register;
        $is_new = false;
    }
}
$modes = $op_register->get_modes();
?>
<style type="text/css">
    .register-name ul{
        list-style: none;
        display: block;
        margin:0;
        padding:0;
    }
    .register-name ul li{
        float:left;
        padding:3px;
        display: inline-block;
    }
    .register-frm{
        background-color: #ccccccb3;
    }
    .status-draft{
        color: red;
    }
    .status-publish{
        color: green;
    }
</style>
<div class="op-admin-wrap wrap">
    <div id="wrap-loading">
        <div class="lds-ellipsis"><div></div><div></div><div></div><div></div></div>
    </div>
    <h1 class="wp-heading-inline"><?php echo __( 'Registers', 'openpos' ); ?></h1>
    <br class="clear" />
    <div class="container-fluid">
        <div class="row">
            <div class="col-xs-12 col-sm-12 col-md-4 register-frm">
                <h4><?php echo ($is_new) ?  __( 'New Register', 'openpos' ) : __( 'Edit Register', 'openpos' ); ?></h4>
                <form class="form-horizontal" id="register-frm">
                    <input type="hidden" name="action" value="openpos_update_register">
                    <input type="hidden" name="op_nonce" value="<?php echo $op_nonce; ?>">
                    <input type="hidden" name="id" value="<?php echo $default['id']; ?>">
                    <div class="form-group">
                        <label for="inputEmail3" class="col-sm-2 control-label"><?php echo __( 'Name', 'openpos' ); ?></label>
                        <div class="col-sm-10">
                            <input type="text" class="form-control" name="name" value="<?php echo $default['name']; ?>" placeholder="<?php echo __( 'Register Name', 'openpos' ); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="inputEmail3" class="col-sm-2 control-label"><?php echo __( 'Outlet', 'openpos' ); ?></label>
                        <div class="col-sm-10">
                            <select class="form-control" name="warehouse">
                                <?php foreach ($warehouses as $w): ?>
                                <option <?php echo ($default['warehouse'] == $w['id'] ) ? 'selected':''; ?> value="<?php echo $w['id']; ?>"><?php echo $w['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small id="emailHelp" class="form-text text-muted"><?php echo __( 'Default online store = Online woocommerce website stock', 'openpos' ); ?></small>
                        </div>

                    </div>

                    <div class="form-group">
                        <label for="inputEmail3" class="col-sm-2 control-label"><?php echo __( 'Cashiers', 'openpos' ); ?></label>
                        <div class="col-sm-10">
                            <?php foreach($cashiers as $cashier):?>
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" <?php echo in_array( $cashier->ID,$default['cashiers']) ? 'checked':''; ?> name="cashiers[]" value="<?php echo $cashier->ID; ?>"><?php echo $cashier->display_name; ?>
                                </label>
                            </div>
                           <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label"><?php echo __( 'Mode', 'openpos' ); ?></label>
                        <div class="col-sm-8">
                            <select class="form-control" name="register_mode">
                                <?php foreach($modes as $code => $label): ?>
                                    <?php 
                                        if($openpos_type != 'restaurant' && $code == 'waiter'){
                                            continue;
                                        } 
                                    ?>
                                    <option <?php echo (!isset($default['register_mode']) || !$default['register_mode'] || $default['register_mode'] == $code  ) ? 'selected':''; ?> value="<?php echo $code; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                                
                                
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label"><?php echo __( 'Status', 'openpos' ); ?></label>
                        <div class="col-sm-4">
                            <select class="form-control" name="status">
                                    <option <?php echo ($default['status'] == 'publish') ? 'selected':''; ?> value="publish"><?php echo __('Active','openpos'); ?></option>
                                    <option <?php echo ($default['status'] == 'draft') ? 'selected':''; ?> value="draft"><?php echo __('Inactive','openpos'); ?></option>
                            </select>
                        </div>
                    </div>
                    <?php do_action('op_register_form_end',$default,$warehouses,$cashiers); ?>
                    <div class="form-group">
                        <div class="col-sm-offset-8 col-sm-4">
                            <button type="submit" class="btn btn-default"><?php echo __( 'Save', 'openpos' ); ?></button>
                        </div>
                    </div>
                </form>
                <?php do_action('op_register_form_after',$default,$warehouses,$cashiers); ?>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-8 op-main-content">
                <h4><?php echo __( 'All Registers', 'openpos' ); ?></h4>
                <div class="table-responsive">
                    <table class="table register-list">
                        <tr>
                            <th><?php echo __( 'Name', 'openpos' ); ?></th>
                            <th><?php echo __( 'Cashiers', 'openpos' ); ?></th>
                            <th><?php echo __( 'Outlet', 'openpos' ); ?></th>
                            <th><?php echo __( 'Balance', 'openpos' ); ?></th>
                            <th><?php echo __( 'Status', 'openpos' ); ?></th>
                        </tr>
                        <?php foreach($registers as $register): ?>
                        <?php
                            $register_cashiers = array();

                            $meta_cashiers = $register['cashiers'];
                            foreach($meta_cashiers as $user_id)
                            {
                                $user = get_userdata($user_id);
                                if($user)
                                {
                                    $register_cashiers[] = $user;
                                }
                            }
                            $outlet = $op_warehouse->get($register['warehouse']);
                            $all_menu  = $op_register->admin_register_menu($register,$openpos_type);
                        ?>
                        <tr>
                            <td class="register-name">
                                <p><span style="color: #fff;background: #009688;padding: 2px 6px;margin-right: 3px;"><?php echo $register['id']; ?></span><?php echo $register['name']; ?></p>
                                <ul>
                                    <?php foreach( $all_menu as $k => $menu ): $attributes = isset($menu['attributes']) ? $menu['attributes'] : array(); ?>
                                        <li>
                                            <a href="<?php echo $menu['url']; ?>" <?php foreach($attributes as $attr_key => $att_value): ?> <?php echo $attr_key.'="'.$att_value.'"'; ?> <?php endforeach;?> >
                                                <?php echo $menu['label']; ?>
                                            </a>
                                        </li>
                                        <?php if($k+1 < count($all_menu)): ?>
                                        <li>|</li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    
                                </ul>
                            </td>
                            <td class="cashiers">
                                <ul>
                                    <?php foreach($register_cashiers as $register_cashier): ?>
                                    <li><a href="<?php echo admin_url('user-edit.php?user_id='.$register_cashier->ID); ?>"><?php echo $register_cashier->display_name; ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <td>
                                <p><?php echo $outlet['name']; ?></p>
                            </td>
                            <td>
                                <p><?php echo wc_price($register['balance']); ?></p>
                            </td>
                            <td>
                                <span class="status-<?php echo esc_attr($register['status']); ?>"><?php echo $register['status'] == 'publish' ? 'Active' : 'Inactive'; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(count($registers) == 0): ?>
                            <tr>
                                <td colspan="4"><?php echo __('No register found','openpos'); ?></td>
                            </tr>
                        <?php endif; ?>

                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript">
    (function($) {
        "use strict";
        $(document).ready(function(){
            $('#register-frm').on('submit',function(){
               var data = $(this).serialize();
                $.ajax({
                    url: openpos_admin.ajax_url,
                    type: 'post',
                    dataType: 'json',
                    data: data,
                    beforeSend:function(){
                        $('body').addClass('op_loading');
                    },
                    success:function(data){
                        if(data.status == 1)
                        {
                            window.location.href = '<?php echo admin_url('admin.php?page=op-registers'); ?>';

                        }else {
                            alert(data.message);
                            $('body').removeClass('op_loading');
                        }
                    },
                    error:function(){
                        $('body').removeClass('op_loading');
                    }
                });
               console.log(data);
               return false;
            });

            $(document).on('click','.delete-register-btn',function(){
                var id = $(this).data('id');

                if(confirm('Are you sure ? '))
                {
                    $.ajax({
                        url: openpos_admin.ajax_url,
                        type: 'post',
                        dataType: 'json',
                        //data:$('form#op-product-list').serialize(),
                        data: {action: 'openpos_delete_register',id:id, op_nonce : "<?php echo $op_nonce; ?>"},
                        beforeSend:function(){
                            $('body').addClass('op_loading');
                        },
                        success:function(data){
                            if(data.status == 1)
                            {
                                location.reload();
                            }else {
                                alert(data.message);
                                $('body').removeClass('op_loading');
                            }
                        },
                        error:function(){
                            $('body').removeClass('op_loading');
                        }
                    });
                }
            });

        });



    })( jQuery );
</script>