<?php
/**
 * Created by PhpStorm.
 * User: anhvnit
 * Date: 9/14/18
 * Time: 21:54
 */
use Automattic\WooCommerce\Utilities\OrderUtil;
defined( 'ABSPATH' ) || exit;
class OP_Woo{
    private $settings_api;
    private $_core;
    private $_session;
    private $_enable_hpos;

    public function __construct()
    {
        $this->_session = new OP_Session();
        $this->settings_api = new OP_Settings();
        $this->_core = new Openpos_Core();
        
    }
    function plugins_loaded()
    {
        $this->_enable_hpos = $this->_core->enable_hpos();
    }
    public function init(){
        add_action('plugins_loaded', array($this,'plugins_loaded'));
        add_filter( 'posts_where', array($this,'title_filter'), 10, 2 );
        add_filter( 'get_meta_sql', array($this,'get_meta_sql'), 10, 2 );
        add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', array($this,'woocommerce_order_data_store_cpt_get_orders_query'), 10, 2 );
        add_filter('woocommerce_payment_complete_reduce_order_stock',array($this,'op_payment_complete_reduce_order_stock'),10,2);
        //add_action( 'op_add_order_after', array($this,'op_maybe_reduce_stock_levels'),10,1 );
        add_action( 'op_add_order_after', array($this,'op_update_local_date'),11,2 );
        add_action( 'op_add_transaction_after', array($this,'op_transaction_update_local_date'),11,3 );

        add_action( 'op_add_order_final_after', array($this,'op_maybe_reduce_stock_levels'),10,1 );
        add_action( 'op_woocommerce_cancelled_order', array($this,'op_maybe_increase_stock_levels'),10,2 );
        add_action( 'parse_query', array( $this, 'order_table_custom_fields' ) );
        add_action( 'woocommerce_order_refunded', array( $this, 'woocommerce_order_refunded' ),10,2 );
        add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'woocommerce_hidden_order_itemmeta' ),10,1 );
        add_filter( 'woocommerce_available_payment_gateways', array( $this, 'woocommerce_available_payment_gateways' ),10,1 );
        add_filter( 'woocommerce_order_get_payment_method_title', array( $this, 'woocommerce_order_get_payment_method_title' ),10,2 );

        add_action( 'woocommerce_product_options_sku', array( $this, 'woocommerce_product_options_sku_after' ),100);
        add_action( 'woocommerce_variation_options_dimensions', array( $this, 'woocommerce_variation_options_dimensions_after' ),100,3);
        add_action('woocommerce_save_product_variation',array($this,'woocommerce_save_product_variation'),10,2);

        //save product hook
        //add_action('woocommerce_admin_process_product_object',array($this,'woocommerce_admin_process_product_object'),10,1);
        add_action('woocommerce_process_product_meta_simple',array($this,'woocommerce_admin_process_product_object'),10,1);
        add_action('woocommerce_process_product_meta_variable',array($this,'woocommerce_admin_process_product_object'),10,1);
        add_action('woocommerce_process_product_meta_bundle',array($this,'woocommerce_admin_process_product_object'),10,1);

        add_action('woocommerce_after_order_itemmeta',array($this,'woocommerce_after_order_itemmeta'),10,3);

        add_filter('woocommerce_email_recipient_customer_completed_order',array($this,'woocommerce_email_recipient_customer_completed_order'),10,2);
        add_filter('woocommerce_email_recipient_customer_processing_order',array($this,'woocommerce_email_recipient_customer_completed_order'),10,2);
        add_filter('woocommerce_email_recipient_customer_invoice',array($this,'woocommerce_email_recipient_customer_completed_order'),10,2);


        add_action('woocommerce_admin_order_data_after_shipping_address',array($this,'woocommerce_admin_order_data_after_shipping_address'),10,1);


        add_filter('manage_edit-shop_order_columns', array($this,'order_columns_head'),10,1);
        add_filter('wc_get_template', array($this,'wc_get_template'),100,5);

        
        add_filter('woocommerce_shop_order_list_table_columns', array($this,'order_columns_head'), 10, 2);

        add_action('woocommerce_shop_order_list_table_custom_column', array($this,'woocommerce_shop_order_list_table_op_source'), 10, 2);
        add_action('manage_shop_order_posts_custom_column', array($this,'manage_shop_order_posts_custom_column'), 10, 2);
        

        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 30 );

        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            add_action( 'restrict_manage_posts', array( $this, 'filter_orders_by_source' ) );
            add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( $this, 'filter_orders_by_source_hpos' ) );
            add_filter( 'parse_query', array( $this,'filter_request_query') , 10,1);
            add_filter( 'woocommerce_order_query_args', array( $this,'woocommerce_order_query_args') , 10,1);
            

            add_filter('woocommerce_shop_order_search_fields', function($fields){
                $fields[] = '_op_order_number_format';
                return $fields;
            },10,1);
            add_filter('woocommerce_order_table_search_query_meta_keys',function($fields){
                $fields[] = '_op_order_number_format';
                return $fields;
            },10,1);

        }
        
       

        add_filter('product_type_options', array($this,'product_type_options'),10,1);


        add_action( 'woocommerce_new_product', array( $this, 'woocommerce_new_product' ), 30,2 );
        add_action( 'woocommerce_update_product', array( $this, 'woocommerce_update_product' ), 30 ,2);

        add_filter( 'woocommerce_order_item_display_meta_key', array( $this, 'woocommerce_order_item_display_meta_key' ), 30 ,2);


        add_filter( 'pre_option_woocommerce_registration_generate_password', array( $this, 'pre_option_woocommerce_openpos' ), 30 ,3);
        add_filter( 'pre_option_woocommerce_registration_generate_username', array( $this, 'pre_option_woocommerce_openpos' ), 30 ,3);


        add_filter( 'woocommerce_quantity_input_step', array( $this, 'woocommerce_quantity_input_step' ), 30 ,2);
        add_filter( 'woocommerce_quantity_input_step_admin', array( $this, 'woocommerce_quantity_input_step_admin' ), 30 ,3);
        

       
        
        add_action( 'init', array($this, 'wp_init') );
        
        add_action( 'woocommerce_loaded', array( $this, 'woocommerce_loaded' ));
        add_action( 'is_protected_meta', array( $this, 'is_protected_meta' ),30,3);

        
    }

    function wp_init(){
        
        $labels = array(
            'name' => _x( 'Custom Notes', 'openpos' ),
            'singular_name' => _x( 'Custom Note', 'openpos' ),
            'search_items' =>  __( 'Search Custom Notes' , 'openpos' ),
            'popular_items' => __( 'Popular Custom Notes' , 'openpos' ),
            'all_items' => __( 'All Custom Notes' , 'openpos' ),
            'parent_item' => null,
            'parent_item_colon' => null,
            'edit_item' => __( 'Edit Custom Notes' , 'openpos' ), 
            'update_item' => __( 'Update Custom Note' , 'openpos' ),
            'add_new_item' => __( 'Add New Custom Note' , 'openpos' ),
            'new_item_name' => __( 'New Custom Note Name' ),
            'separate_items_with_commas' => __( 'Quick item note for OpenPOS. Separate topics with commas' , 'openpos' ),
            'add_or_remove_items' => __( 'Add or remove custom note' , 'openpos' ),
            'choose_from_most_used' => __( 'Choose from the most used notes' , 'openpos' ),
            'menu_name' => __( 'Custom Notes' , 'openpos' ),
        ); 
        register_taxonomy('op_product_notes','product',array(
            'hierarchical' => false,
            'labels' => $labels,
            'show_ui' => true,
            'show_in_rest' => false,
            'show_admin_column' => false,
            'show_in_menu' => false,
            'show_in_nav_menus' => false,
            'query_var' => false,
          ));

        $args = array();
        $wc_queue = WC_Queue::instance();

        $hook_name = 'openpos_daily_event';
        $next_scheduled_date = $wc_queue->get_next( $hook_name, $args );
        if ( is_null( $next_scheduled_date ) ) {
            $wc_queue->cancel_all($hook_name, $args ); // Ensure all the actions are cancelled before adding a new one.
            $time = strtotime( 'midnight tomorrow' );
            $interval = 86400;
            $wc_queue->schedule_recurring($time, $interval, $hook_name, $args);
        }
        add_filter('woocommerce_debug_tools',array($this,'woocommerce_debug_tools'),10,1);
    }
    public function woocommerce_debug_tools($tools){
        $tools['op_clear_cache'] = array(
            'name'   => __( 'Clear OpenPOS Cache', 'openpos' ),
            'button' => __( 'Clear Cache', 'openpos' ),
            'desc'   => sprintf(
                __( 'manual clear OpenPOS transient cache.', 'openpos' )
            ),
            'callback' => array( $this, 'clear_all_transient_cache' ),
        );
        $tools['op_clear_expired_cache'] = array(
            'name'   => __( 'Clear OpenPOS Expired Cache', 'openpos' ),
            'button' => __( 'Clear Expired Cache', 'openpos' ),
            'desc'   => sprintf(
                __( 'manual clear OpenPOS expired transient cache.', 'openpos' )
            ),
            'callback' => array( $this, 'clear_expired_transient_cache' ),
        );
        
        $tools['op_clear_product_cache'] = array(
            'name'   => __( 'Clear OpenPOS product Cache', 'openpos' ),
            'button' => __( 'Clear Cache', 'openpos' ),
            'desc'   => sprintf(
                __( 'manual clear OpenPOS product cache.', 'openpos' )
            ),
            'callback' => array( $this, 'clear_all_product_cache' ),
        );
       
        return $tools;
    }

    
    public function pre_option_woocommerce_openpos($value , $option, $default){
        global $op_session_data;
        if($op_session_data && isset($op_session_data['user_id']) && $op_session_data['user_id'])
        {
            if($option == 'woocommerce_registration_generate_password'){
                $value = 'yes';
            }
            if($option == 'woocommerce_registration_generate_username'){
                $value = 'yes';
            }
        }
        
        return $value;
    }

    public function order_columns_head($defaults){

        $result = array();
        foreach($defaults as $key => $value)
        {
            $result[$key] = $value;
            if($key == 'cb')
            {
                $result['op_source']  = __('Source','openpos');

            }
        }
        return $result;
    }
   
    public function woocommerce_shop_order_list_table_op_source( $column, $order ){
        if ( 'op_source' !== $column ) {
            return;
        }
        $source = $order->get_meta('_op_order_source',true);
        
        if($source == 'openpos')
        {
            echo '<img style="width: 16px;" alt="from openpos" src="'.OPENPOS_URL.'/assets/images/shop.png">';
        }else{
            echo '<img style="width: 16px;" alt="from online website" src="'.OPENPOS_URL.'/assets/images/woocommerce.png">';
        }
        
    }
    public function manage_shop_order_posts_custom_column($column, $post_id){
        if ( 'op_source' !== $column ) {
            return;
        }
        $order = wc_get_order($post_id);
        if($order)
        {
            $source = $order->get_meta('_op_order_source',true);
        
            if($source == 'openpos')
            {
                echo '<img style="width: 16px;" alt="from openpos" src="'.OPENPOS_URL.'/assets/images/shop.png">';
            }else{
                echo '<img style="width: 16px;" alt="from online website" src="'.OPENPOS_URL.'/assets/images/woocommerce.png">';
            }
        }
        
    }

    public function woocommerce_hidden_order_itemmeta($meta){
        $meta[] = '_op_local_id';
        $meta[] = '_op_seller_id';
        $meta[] = '_op_reduced_stock';
        $meta[] = '_op_cost_price';
        $meta[] = '_op_item_options';
        $meta[] = '_op_item_bundles';
        $meta[] = '_op_item_variations';
        return $meta;
    }
    public function woocommerce_order_refunded($order_id,$refund_id)
    {
        global $op_refund_restock;
        global $op_warehouse;
        global $op_session_data;
        
        if($this->_enable_hpos)
        {
            $order = wc_get_order($order_id);
            $warehouse_id = $order->get_meta('_pos_order_warehouse');
        }else{
            $warehouse_id = get_post_meta($order_id,'_pos_order_warehouse',true);
        }
        
        if($op_session_data && isset($op_session_data['login_warehouse_id']))
        {
            $warehouse_id = $op_session_data['login_warehouse_id'] ;
        }

        if($op_refund_restock == 'no')
        {
            return;
        }
        

        if($warehouse_id > 0)
        {
                $refund = new WC_Order_Refund($refund_id );
                $line_items = $refund->get_items();
                foreach($line_items as $item)
                {
                    $product_id = $item->get_product_id();
                    $variation_id = $item->get_variation_id();

                    $post_product_id = $product_id;
                    if($variation_id  > 0)
                    {
                        $post_product_id = $variation_id;
                    }
                    $refund_qty = $item->get_quantity();
                    $current_qty = $op_warehouse->get_qty($warehouse_id,$post_product_id);
                    
                    $new_qty = apply_filters( 'op_outlet_order_item_refund_quantity',($current_qty - $refund_qty),$warehouse_id,$post_product_id,$item);
                    $op_warehouse->set_qty($warehouse_id,$post_product_id,$new_qty);

                    
                }
        }

    }
    public function order_pos_payment($order){
        global $op_warehouse;
        global $op_register;
        $id = $order->get_id();
        $source =  $order->get_meta('_op_order_source');
        
        if($source == 'openpos')
        {
            $tipping = null;
            $warehouse_meta_key = $op_warehouse->get_order_meta_key();
            $register_meta_key = $op_register->get_order_meta_key();
           
            $payment_methods = $order->get_meta('_op_payment_methods');
            $_op_order_addition_information = apply_filters( 'order_pos_payment:order_addition_information',$order->get_meta('_op_order_addition_information'),$id,$order);
            $_op_sale_by_person_id =  $order->get_meta('_op_sale_by_person_id');
            $pos_created_at =  $order->get_meta('pos_created_at');

            $_pos_order_id =  $order->get_meta('_pos_order_id');
            $order_number = $_pos_order_id;

            $_op_wc_custom_order_number_formatted =  $order->get_meta('_op_wc_custom_order_number_formatted');
            if($_op_wc_custom_order_number_formatted)
            {
                $order_number = $_op_wc_custom_order_number_formatted;
            }

            $warehouse_id =  $order->get_meta($warehouse_meta_key);
            $register_id =  $order->get_meta($register_meta_key);

            if($tmp_tip =  $order->get_meta('_op_tip'))
            {
                if(isset($tmp_tip['total']) && $tmp_tip['total'])
                {
                    $tipping = wc_price($tmp_tip['total']);
                }
            }

           
            
            $warehouse = $op_warehouse->get($warehouse_id);
            $register = $op_register->get($register_id);
            
            ?>
            <?php if($_op_sale_by_person_id): $person = get_user_by('id',$_op_sale_by_person_id);  ?>

                <p class="form-field form-field-wide">
                    <label><?php esc_html_e( 'POS Order Number:', 'openpos' ); ?> <b><?php echo esc_html($order_number)?></b></label>
                </p>
            <?php endif; ?>
           
            <?php if(!empty( $pos_created_at)): ?>
            <p class="form-field form-field-wide">
                <label><?php esc_html_e( 'Local Time:', 'openpos' ); ?> <b><?php echo esc_html($pos_created_at )?></b></label>
            </p>
            <?php endif; ?>
            <?php if($_op_sale_by_person_id): $person = get_user_by('id',$_op_sale_by_person_id);  ?>
            <p class="form-field form-field-wide">
                <label><?php esc_html_e( 'Shop Agent:', 'openpos' ); ?> <b><?php echo $person ? esc_html($person->get('display_name') ):  __('Unknown','openpos'); ?></b></label>
            </p>
            <?php endif; ?>
            <?php if(!empty($warehouse)): ?>
            <p class="form-field form-field-wide">
                <label><?php esc_html_e( 'Outlet:', 'openpos' ); ?> <b><?php echo esc_html($warehouse['name'] )?></b></label>
            </p>
            <?php endif; ?>
            <?php if(!empty($warehouse)): ?>
            <p class="form-field form-field-wide">
                <label><?php esc_html_e( 'Register:', 'openpos' ); ?> <b><?php echo isset($register['name']) ?  esc_html($register['name'] ) : __('Unknown','openpos'); ?></b></label>
            </p>
            <?php endif; ?>
            <?php if($payment_methods): ?>
            <hr/>
            <p class="form-field form-field-wide">
                <label><?php esc_html_e( 'POS Payment method:', 'openpos' ); ?></label>
                <ul>
                <?php foreach($payment_methods as $method): ?>
                    <li><?php echo esc_html($method['name']); ?>: <?php echo wc_price($method['paid']); ?> <?php echo $method['ref'] ? '('.esc_html($method['ref']).')':''; ?></li>
                <?php endforeach; ?>
                </ul>
            </p>
            <?php endif; ?>

            <?php if($tipping != null): ?>
            <hr/>
            <p class="form-field form-field-wide" style="background-color: yellow;">
                <label><?php esc_html_e( 'TIP:', 'openpos' ); ?> <b><?php echo $tipping; ?></b></label>
                
            </p>
            <?php endif; ?>

            <?php if(!empty( $_op_order_addition_information)): ?>
            <hr/>
            <p class="form-field form-field-wide">
                <label><?php esc_html_e( 'Additional information:', 'openpos' ); ?></label>
                <ul>
                <?php foreach($_op_order_addition_information as $key => $info): ?>
                    <li><?php echo esc_html($info['label']); ?>: <?php echo is_array($info['value']) ?  implode(',',$info['value']) : $info['value'] ; ?></li>
                <?php endforeach; ?>
                </ul>
            </p>
            <?php endif; ?>

           
            <?php
        }

    }
    public function get_cashiers(){
        $args = array(
            'meta_key' => '_op_allow_pos',
            'meta_value' => '1',
            'fields' => array('ID', 'display_name','user_email','user_login','user_status'),
            'number' => -1
        );
        $cashiers =  get_users( $args);
        $result = array();
        foreach($cashiers as $cashier)
        {
            $result[] = $cashier;
        }
        return $result;
    }
    public function op_payment_complete_reduce_order_stock($result,$order_id){
        global $op_warehouse;
        global $op_session_data;

        if( $op_session_data )
        {
            $result = false;
        }
        return $result;
    }
    public function op_maybe_reduce_stock_levels($order_data)
    {
        if(!isset($order_data['order_id']))
        {
            return;
        }
        
        $order_id = $order_data['order_id'];
        $stock_manager = $this->settings_api->get_option('pos_stock_manage','openpos_general');
        global $_op_warehouse_id;
        global $op_warehouse;
        if ( is_a( $order_id, 'WC_Order' ) ) {
            $order    = $order_id;
            $order_id = $order->get_id();
            
        } else {
            $order = wc_get_order( $order_id );
            
        }
        if(!$order)
        {
            return;
        }
        $allow_laybuy = $order->get_meta('_op_allow_laybuy');
        $ignore_status = $this->_core->getIgnoreDeductStockOrderStatus(); // array('pending');

        $order_status = $order->get_status();
       
        // reduct once this order are laybuy
        if(!$ignore_status)
        {
            $ignore_status = array();
        }
        
        if($allow_laybuy != 'yes')
        {
            if(in_array($order_status,$ignore_status))
            {
                return;
            }
        }
        

        $warehouse_id = $_op_warehouse_id;
        $stock_reduced  = $order->get_data_store()->get_stock_reduced( $order_id );
        $trigger_reduce = apply_filters( 'op_woocommerce_payment_complete_reduce_order_stock', ! $stock_reduced, $order_id );
        if ( ! $trigger_reduce ) {
            return;
        }
        
        if($warehouse_id > 0)
        {
            $product_stock_update = array();
            foreach ( $order->get_items() as $item ) {
                if (!$item ) {
                    continue;
                }

                $product = $item->get_product();

                $item_stock_reduced = $item->get_meta( '_op_reduced_stock', true );
                $item_stock_weight = $item->get_meta( '_op_item_weight', true );
                if ($item_stock_reduced || ! $product) {
                    continue;
                }


                $item_data = $item->get_data();
                $variation_id = isset($item_data['variation_id']) ? $item_data['variation_id'] : 0;

                if ( $product ) {
                    $product_id = $product->get_id();
                    if($variation_id > 0)
                    {
                        $product_id = $variation_id;
                    }
                    $product_stock_update[] = $product_id;
                    $_qty = $item->get_quantity();
                    $is_weight = $this->is_weight_base_pricing($product_id);
                    if($is_weight && $item_stock_weight)
                    {
                        $_qty = $item_stock_weight;
                    }
                    
                    $qty       = apply_filters( 'woocommerce_order_item_quantity',  $_qty , $order, $item );
                    $current_qty = $op_warehouse->get_qty($warehouse_id,$product_id);
                    if(!$current_qty)
                    {
                        $current_qty = 0;
                    }
                    $new_qty = apply_filters( 'op_outlet_order_item_quantity',($current_qty - $qty),$warehouse_id,$product_id,$item);

                    $op_warehouse->set_qty($warehouse_id,$product_id,$new_qty);
                    
                    $item->update_meta_data( '_reduced_stock', $qty );
                    $item->add_meta_data( '_op_reduced_stock', $qty, true );
		            $item->save();

                }
            }

            $product_stock_update = array_unique($product_stock_update);
            $_allow_send_stock_notification =  $stock_manager == 'no' ? false : true;
            $allow_send_stock_notification = apply_filters('openpos_allow_send_stock_notification',$_allow_send_stock_notification,$_op_warehouse_id,$product_stock_update);

            if( $allow_send_stock_notification  && !empty($product_stock_update))
            {
                foreach($product_stock_update as $product_id){
                    $op_warehouse->low_stock($warehouse_id,$product_id);
                    $op_warehouse->no_stock($warehouse_id,$product_id);
                }
            }

        }else{
            $changes = array();

            foreach ( $order->get_items() as $item ) {
                if ( ! $item->is_type( 'line_item' ) ) {
                    continue;
                }
                $product            = $item->get_product();
                $item_stock_reduced = $item->get_meta( '_reduced_stock', true );
                $item_stock_weight = $item->get_meta( '_op_item_weight', true );
                if ($item_stock_reduced || ! $product || ! $product->managing_stock() ) {
                    continue;
                }
                //$pos_item_details = $item->get_meta('_op_item_data_details',true);
                
                $_qty = $item->get_quantity();
               
                if ( $product ) {
                    $product_id = $product->get_id();
                    $is_weight = $this->is_weight_base_pricing($product_id);
                    if($is_weight && $item_stock_weight)
                    {
                        $_qty = $item_stock_weight;
                    }
                    $this->_core->addProductChange($product_id,0);
                }
                $qty       = apply_filters( 'woocommerce_order_item_quantity', $_qty, $order, $item );
                $new_stock = wc_update_product_stock( $product, $qty, 'decrease' );

                $item->add_meta_data( '_reduced_stock', $qty, true );
		        $item->save();

                $changes[] = array(
                    'product' => $product,
                    'from'    => $new_stock + $qty,
                    'to'      => $new_stock,
                );
                
                
            }
            if(!empty($changes))
            {
                wc_trigger_stock_change_notifications( $order, $changes );
            }
        }
        $order->get_data_store()->set_stock_reduced( $order_id, true );
    }

    public function op_maybe_increase_stock_levels($order_id,$status){
        global $_op_warehouse_id;
        global $op_warehouse;
        if ( is_a( $order_id, 'WC_Order' ) ) {
            $order    = $order_id;
            $order_id = $order->get_id();
        } else {
            $order = wc_get_order( $order_id );
        }
        $warehouse_id = $_op_warehouse_id;
        $allow_increase_stock = ($status == 'cancelled') ? true : false;
        if($allow_increase_stock)
        {
            if($warehouse_id > 0)
            {
                foreach ( $order->get_items() as $item ) {
                    if ( ! $item->is_type( 'line_item' ) ) {
                        continue;
                    }
                

                    $product = $item->get_product();
                    $item_data = $item->get_data();
                    $variation_id = isset($item_data['variation_id']) ? $item_data['variation_id'] : 0;

                    if ( $product ) {
                        $product_id = $product->get_id();
                        if($variation_id > 0)
                        {
                            $product_id = $variation_id;
                        }

                        $qty       = apply_filters( 'woocommerce_order_item_quantity', $item->get_quantity(), $order, $item );
                        $current_qty = $op_warehouse->get_qty($warehouse_id,$product_id);
                        if(!$current_qty)
                        {
                            $current_qty = 0;
                        }
                        $new_qty = $current_qty + $qty;
                        $op_warehouse->set_qty($warehouse_id,$product_id,$new_qty);

                        $item->update_meta_data( '_reduced_stock', 0 );
                        $item->update_meta_data( '_op_reduced_stock', 0);

                    }
                }

            }else{
                $changes = array();
                
                foreach ( $order->get_items() as $item ) {
                    if ( ! $item->is_type( 'line_item' ) ) {
                        continue;
                    }
                    $product            = $item->get_product();
                    $item_stock_reduced = $item->get_meta( '_reduced_stock', true );
                    
                    if (!$item_stock_reduced || ! $product || ! $product->managing_stock() ) {
                        continue;
                    }
                    
                    
                    $qty       = apply_filters( 'woocommerce_order_item_quantity_increase', $item_stock_reduced, $order, $item );
                    $new_stock = wc_update_product_stock( $product, $qty, 'increase' );

                    $item->add_meta_data( '_reduced_stock', 0, true );
                    $item->save();
                    
                    if ( $product ) {
                        $product_id = $product->get_id();
                        $this->_core->addProductChange($product_id,0);
                    }

                    $changes[] = array(
                        'product' => $product,
                        'from'    => $new_stock - $qty,
                        'to'      => $new_stock,
                    );
                    
                    
                }
                if(!empty($changes))
                {
                    wc_trigger_stock_change_notifications( $order, $changes );
                }
            }
        }
       
    }

    public function order_table_custom_fields($wp){
        global $pagenow;
        $post_type = isset($wp->query_vars['post_type']) ? $wp->query_vars['post_type'] : '';
        if ( 'edit.php' !== $pagenow  || 'shop_order' !== $post_type  ) { // WPCS: input var ok.
            return;
        }
        if(isset( $_GET['warehouse'] ))
        {
            $query_vars = $wp->query_vars;
            $query_vars['meta_key'] = '_pos_order_warehouse';
            $query_vars['meta_value'] = (int)$_GET['warehouse'];
            $wp->query_vars = $query_vars;
            return;
        }
        if(isset( $_GET['register'] ))
        {
            $query_vars = $wp->query_vars;
            $query_vars['meta_key'] = '_pos_order_cashdrawer';
            $query_vars['meta_value'] = (int)$_GET['register'];
            $wp->query_vars = $query_vars;
            return;
        }

    }
   

    public function get_available_variations($variation,$warehouse_id = 0) {
        global $op_warehouse;
        $show_out_of_stock_setting = $this->settings_api->get_option('pos_display_outofstock','openpos_pos');
        $variation_ids        = $variation->get_children();
        if ( is_callable( '_prime_post_caches' ) ) {
			_prime_post_caches( $variation_ids );
        }
        $tmp_class = new WC_Product_Variable();
        $_available_variations = array();
        foreach ( $variation_ids as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( ! $variation || ! $variation->exists()  ) {
				//continue;
            }
            if($warehouse_id == 0 && ( $show_out_of_stock_setting != 'yes' && ! $variation->is_in_stock() ))
            {
                //continue;
            }

			//$_available_variations[] =  $tmp_class->get_available_variation( $variation );
            $_available_variations[] = array(
                'attributes' => $variation->get_variation_attributes(),
                'variation_id'          => $variation->get_id(),
            );
		}
        $item_variations = array_values( array_filter( $_available_variations) );
        
        $available_variations = array();
        foreach ( $item_variations as $v ) {
            $tmp_v = $v;
            $product_id = $v['variation_id'];
            if( !$op_warehouse->is_instore($warehouse_id,$v['variation_id']))
            {
               // continue;
            }
            $current_qty = 1 * $op_warehouse->get_qty($warehouse_id,$product_id);
            $tmp_v['stock_quantity'] = $current_qty;
            $tmp_v['stock_status'] = $current_qty > 0 ? 'instock': 'outofstock';
            $available_variations[] = $tmp_v;
        }
        return $available_variations;
    }

    public function get_variations($product_id,$warehouse_id = 0,$incl_tax = false,$currency = null,$show_out_of_stock = true){
        try{
            $cache_key = 'op_variations_product_cache_'.$warehouse_id.'_'.$product_id;
            $cache_group = 'products';

            $cached_data = wp_cache_get( $cache_key, $cache_group );
            if ( false !== $cached_data )
            {
                //update qty
                return $cached_data;
            }else{
                global $op_warehouse;
                $core = $this->_core;
                $variation = new WC_Product_Variable($product_id);
                if(!$variation)
                {
                    return false;
                }

                if(!$currency || $currency == null)
                {
                    $currency_pos = get_option( 'woocommerce_currency_pos' );
                    $currency  = array(
                        'decimal' => wc_get_price_decimals(),
                        'decimal_separator' => wc_get_price_decimal_separator(),
                        'thousand_separator' => wc_get_price_thousand_separator(),
                        'currency_pos' => $currency_pos,
                        'code' => get_woocommerce_currency(), 
                        'symbol' => html_entity_decode(get_woocommerce_currency_symbol()), 
                        'rate' => 1
                    );
                }

                $item_variations = $this->get_available_variations($variation,$warehouse_id);
                $variant_products_with_attribute = array();
                $variation_attributes   = $variation->get_variation_attributes();
        
                $price_list = array();
                $regular_price_list = array();
                $variations = array();
                $qty_list = array();
                $child_products = array();
                $child_keywords = array();
                $manage_stocks = array();
                foreach($item_variations as $a_p)
                {
                    $variation_id = $a_p['variation_id'];
                    if( !$op_warehouse->is_instore($warehouse_id,$variation_id))
                    {
                        continue;
                    }

                    $qty = 1 * $op_warehouse->get_qty($warehouse_id,$variation_id);
                    if(!$show_out_of_stock && $qty <= 0)
                    {
                       // continue;
                    }

                    
                    $variant_product = wc_get_product($variation_id);
                    $taxes = $this->_tax($variant_product,$warehouse_id);
                    //$a_p_price =  wc_get_price_including_tax($variant_product);
                    $a_p_price =  $taxes['price_incl_tax'];
                    
                    $child_keywords[] = $variant_product->get_name();
                    
                    //end update price
        
                    $variant_products_with_attribute[] = array(
                        'value_id' => $variation_id,
                        'price' => $a_p_price,
                        'attributes' => $a_p['attributes']
                    );
                    $qty = $variant_product->get_stock_quantity();
                    if($warehouse_id > 0)
                    {
                        $qty = $op_warehouse->get_qty($warehouse_id,$variation_id);
                    }
                    if(!$qty)
                    {
                        $qty = 0;
                    }
                    $manage_stock = $this->_manage_stock($variant_product,$warehouse_id);
                    $manage_stocks[$variation_id] = $manage_stock ? 'yes' : 'no';
                    
                    $barcode = $core->getBarcode($variation_id);
                    
                    $child_products[$variation_id] = array(
                        'id' => $variation_id,
                        'barcode' => $barcode,
                        'qty' => $qty,
                        'price_included_tax' => $this->_price_included_tax($variant_product,$warehouse_id),
                        'final_price' => $this->_core->rounding_price($taxes['price_excl_tax'],$currency['decimal']),
                        'final_price_incl_tax' => $this->_core->rounding_price($taxes['price_incl_tax'],$currency['decimal']),
                        'regular_price_with_tax' => $this->_core->rounding_price($taxes['regular_price_with_tax'],$currency['decimal']),
                        'regular_price_excl_tax' => $this->_core->rounding_price($taxes['regular_price_excl_tax'],$currency['decimal']),
                        'tax_amount' => $this->_core->rounding_price($taxes['tax_amount'],$currency['decimal']),
                        'manage_stock' => $manage_stock,
                    );
        
                }
                foreach($variation_attributes as $key => $variants)
                {
                    $variants = $this->sortAttributeOptions($key,$variants);
                    $label = $key;
                    if(strpos($key,'pa_') === false)
                    {
                        $key = strtolower(esc_attr(sanitize_title($key)));
                    }else{
                        $key = urlencode($key);
                    }
                    
                    $options = array();
                    
                    foreach($variants as $v)
                    {
                        $option_label = $v;
                        $values = array();
                        $id_values = array();
                        $values_all = array();
                        $values_price = array();
                        
                        $stock_manager = true;
                        foreach($variant_products_with_attribute as $vp)
                        {
                            $attribute_key_1 = strtolower('attribute_'.$key); // sanitize_title
                            $is_all = false;
                            
                            if(isset($vp['attributes'][$attribute_key_1]) && ($vp['attributes'][$attribute_key_1] === $v || $vp['attributes'][$attribute_key_1] === ''))
                            {
                                if($vp['attributes'][$attribute_key_1] === ''){
                                    $is_all = true;
                                }
                                if($vp['value_id'])
                                {
        
                                    $taxonomy = $key;
                                    $term = get_term_by('slug', $v, $taxonomy);
                                    if($term)
                                    {
                                        $option_label = $term->name;
                                        
                                    }
                                    $child_id = $vp['value_id'];
                                    $child_data = isset($child_products[$child_id]) ? $child_products[$child_id] : array();

                                    $barcode = isset($child_data['barcode']) ? $child_data['barcode'] : $core->getBarcode($vp['value_id']);
                                    if($barcode)
                                    {
                                        
                                        if(!empty($child_data))
                                        {
                                            
                                            $values_price[$barcode] = $child_data['final_price'];
                                            $values[] = $barcode;
                                            
                                            if($is_all){
                                                $values_all[] = $barcode;
                                            }else{
                                                $id_values[] = $child_id;
                                            }
                                            if($incl_tax)
                                            {
                                                $price_list[] =  $child_data['final_price_incl_tax'];
                                                if($child_data['regular_price_with_tax'])
                                                {
                                                    $regular_price_list[] =  $child_data['regular_price_with_tax'];
                                                }
                                                
                                            }else{
                                                $price_list[] =  $child_data['final_price'];
                                                if($child_data['regular_price_excl_tax'])
                                                {
                                                    $regular_price_list[] =  $child_data['regular_price_excl_tax'];
                                                }
                                            }
                                            
                                            $qty_list[$barcode] = 1 * $child_data['qty'];
            
                                            
            
                                            if($child_data && !$child_data['manage_stock'])
                                            {
                                                $stock_manager = false;
                                            }
                                        }
                                    }
        
                                }
        
                            }
                        }
                        if(!empty($values_all))
                        {
                            $values_all = array_unique($values_all);
                        }
                        if(!empty($values))
                        {
                            $values = array_unique($values);
                            $diff = array_diff($values,$values_all);
                            if(!empty($diff))
                            {
                                $values = $diff;
                            }
                        }
                        $option_label = rawurldecode( $option_label);
                        
                        
                        
                        $option_tmp = array(
                            'title' => esc_html($option_label),
                            'slug' => $v,
                            'values' => array_values($values),
                            'id_values' => array_unique($id_values),
                            'prices' => $values_price,
                        );
                        
                        if($stock_manager)
                        {
                            $stock_status = '';
                            $value_qty = 0;
                            foreach($values as $barcode)
                            {
                                if(isset($qty_list[$barcode]))
                                {
                                    $value_qty += $qty_list[$barcode];
                                }
                            }
                            $option_tmp['stock_status'] = $stock_status;
                            $option_tmp['stock_qty'] = $value_qty;
                        }
        
                        $option_tmp = apply_filters('op_product_variation_attribute_option_data',$option_tmp);
                        $options[] = $option_tmp;
                        
                    }
        
                    $variant = array(
                        'title' => wc_attribute_label( $label ),
                        'slug' => sanitize_title($key),
                        'options' => $options
                    );
                    $variations[] = apply_filters('op_product_variant',$variant,$product_id,$warehouse_id);
                    
                }
        
                /*
                $variations = array(
                    0 => array(
                        'title' => 'Variation Color',
                        'slug' => 'color',
                        'options' => array(
                            0 => array(
                                'title' => 'Red',
                                'slug' => 'red',
                                'values' => array(100,101),
                                'prices' => array()
                            ),
                            1 => array(
                                'title' => 'Blue',
                                'slug' => 'blue',
                                'values' => array(102,103),
                                'prices' => array()
                            )
                        )
                    )
                );
                */
                
                $result = array(
                    'manage_stocks' => $manage_stocks,
                    'variations' => $variations,
                    'price_list' => $price_list,
                    'regular_price_list' => $regular_price_list,
                    'qty_list' => $qty_list,
                    'child_keywords' => array_unique($child_keywords)
                );
                wp_cache_set( $cache_key, $result, $cache_group );
            }

            
            return $result;
        }catch( Exception $e){
            return false;
        }
    }
    private function _manage_stock($product,$warehouse_id){
        $manage_stock = $product->get_manage_stock();
        $backorders = $product->get_backorders();
        if($manage_stock && $backorders == 'yes')
        {
                $manage_stock = false;
        }
        return $manage_stock;
    }
    private function _price_included_tax($product,$warehouse_id)
    {
        $price_included_tax = false;
        if(wc_tax_enabled()  )
        {
            $price_included_tax = wc_prices_include_tax();
        }
        return $price_included_tax;
    }
    private function _tax($product,$warehouse_id)
    {
        global $op_warehouse;
        $product_id = $product->get_id();

        $cache_key = 'op_tax_product_cache_'.$warehouse_id.'_'.$product_id;
        $cache_group = 'products';

        $cached_data = wp_cache_get( $cache_key, $cache_group );

        if ( false !== $cached_data )
        {
            $data =  $cached_data;
        }else{
            $tmp_tax_rates = array();

            $price_included_tax = $this->_price_included_tax($product,$warehouse_id);
    
            
    
            $regular_price = $product->get_regular_price();
            
            $price_without_tax = wc_get_price_excluding_tax($product);
            $price = $product->get_price(); //price entered
            if(!$price)
            {
                $price = 0;
            }
            if(!$regular_price)
            {
                $regular_price = 0;
            }
            $regular_price_without_tax = $regular_price;
            $regular_price_with_tax = $regular_price;
    
            $has_regular_price = false;
            if($regular_price && $regular_price > 0 && $regular_price != $price)
            {
                $has_regular_price = true;
            }
    
            $final_price = $price; //price with tax
            $final_price_excl_tax = $price; //price excl tax
    
            $tax_amount = 0;
            $regular_tax_amount = 0;
            
            if(wc_tax_enabled()  )
            {
                $_product = get_post($product_id);
                $setting_tax_class =  apply_filters('get_product_formatted_data:pos_tax_class', $this->settings_api->get_option('pos_tax_class','openpos_general'),$_product,$warehouse_id );
                $setting_tax_rate_id = apply_filters('get_product_formatted_data:pos_tax_rate_id', $this->settings_api->get_option('pos_tax_rate_id','openpos_general'),$_product,$warehouse_id );
    
                if( $setting_tax_class != 'op_notax')
                {
                    
    
                    if($setting_tax_class == 'op_productax' && $product->is_taxable())
                    {
                        $tax_class = $product->get_tax_class( 'unfiltered' );
                        $base_tax_rates = WC_Tax::get_base_tax_rates( $tax_class );
    
                        
                        if($warehouse_id > 0)
                        {
                            $warehouse_details = $op_warehouse->getStorePickupAddress($warehouse_id);
                            if($warehouse_details['country'] || $warehouse_details['state'] || $warehouse_details['postcode'] || $warehouse_details['city'] )
                            {
                                $base_tax_rates = $this->getLocationTaxRates($tax_class,$warehouse_details);
                            }
                        }
                        $tax_rates = $this->getTaxRates( $product->get_tax_class() );
                        if(!empty($base_tax_rates))
                        {
                            $tax_rates = $base_tax_rates;
                        }else{
                            $keys = array_keys($tax_rates);
                            if(is_array($keys) && !empty($keys))
                            {
                                $rate_id = max($keys);
                                $rate = $tax_rates[$rate_id];
                                $tax_rates = array($rate );
                            }
                            
                        }
                        
                        if(!empty($tax_rates))
                        {
                            $total_rate = 0;
                            foreach($tax_rates as $rate_id => $rate)
                            {
                                $tax_rate = array(
                                        'code' => 'openpos', // in percentage
                                        'rate' => 0, // in percentage
                                        'shipping' => 'no',
                                        'compound' => 'no',
                                        'rate_id' => 0,
                                        'label' => __('Tax on POS','openpos')
                                );
                               
                                $tax_rate['code'] = $product->get_tax_class() ? $tax_class.'_'.$rate_id : 'standard_'.$rate_id;
                                $tax_rate['rate_id'] = $rate_id;
                                if($rate['label'])
                                {
                                    $tax_rate['label'] = $rate['label'];
                                }
                                if(isset($rate['shipping']))
                                {
                                    $tax_rate['shipping'] = $rate['shipping'];
                                }
                                if(isset($rate['compound']))
                                {
                                    $tax_rate['compound'] = $rate['compound'];
                                }
                                if(isset($rate['rate']))
                                {
                                    $tax_rate['rate'] = $rate['rate'];
                                    $total_rate += 1 * $rate['rate'];
                                }
    
                                $tmp_tax_rates[] = $tax_rate;
                            }
                            if($total_rate > 0)
                            {
                                $ex_tax_rate = array(
                                        'code' => 'openpos', // in percentage
                                        'rate' => $total_rate, // in percentage
                                        'shipping' => 'no',
                                        'compound' => 'no',
                                        'rate_id' => 0,
                                        'label' => __('Tax on POS','openpos')
                                );
                               
                                
                                if($price_included_tax)
                                {
                                    $test_tax = @WC_Tax::calc_inclusive_tax($price, array(0 => $ex_tax_rate));
                                    $tax_amount = array_sum($test_tax);
                                    $tax_amount = wc_round_tax_total($tax_amount);
                                    $final_price_excl_tax -= $tax_amount;
                                    if($regular_price)
                                    {
                                        $test_tax_re = @WC_Tax::calc_inclusive_tax($regular_price, array(0 => $ex_tax_rate));
                                        $regular_tax_amount = array_sum($test_tax_re);
                                        $regular_tax_amount = wc_round_tax_total($regular_tax_amount);
                                        $regular_price_without_tax -= $regular_tax_amount;
                                    }
                                    
                                }else{
                                    $test_tax = @WC_Tax::calc_exclusive_tax($price, array(0 => $ex_tax_rate));
                                    $tax_amount = array_sum($test_tax);
                                    $tax_amount = wc_round_tax_total($tax_amount);
                                    $final_price += $tax_amount;
                                    if($regular_price)
                                    {
                                        $test_tax_re = @WC_Tax::calc_inclusive_tax($regular_price, array(0 => $ex_tax_rate));
                                        $regular_tax_amount = array_sum($test_tax_re);
                                        $regular_tax_amount = wc_round_tax_total($regular_tax_amount);
                                        $regular_price_with_tax += $regular_tax_amount;
                                    }
                                    
                                }
                            }
                            
                        }
                        
                        
                    }else{
    
                        $tax_rate = array(
                            'code' => 'openpos', // in percentage
                            'rate' => 0, // in percentage
                            'shipping' => 'no',
                            'compound' => 'no',
                            'rate_id' => 0,
                            'label' => __('Tax on POS','openpos')
                        );
    
                        $tax_rates = $this->getTaxRates( $setting_tax_class );
                        
                        if(!empty($tax_rates))
                        {
                            if(!$regular_price_without_tax)
                            {
                               $regular_price_without_tax = 0;
                            }
                            if(!$price_without_tax)
                            {
                               $price_without_tax = 0;
                            }
                            //format number : 
                            $price_without_tax = 1 * $price_without_tax;
                            $keys = array_keys($tax_rates);
                            $rate_id = max($keys);
    
                            
                            if($setting_tax_rate_id && isset($tax_rates[$setting_tax_rate_id]))
                            {
                                $rate_id = $setting_tax_rate_id;
                            }
                            $rate = $tax_rates[$rate_id];
    
                            
                            
                            
                            $tax_amount = array_sum(@WC_Tax::calc_tax( $price_without_tax, array($rate_id => $rate), false));
                            $tax_amount = wc_round_tax_total($tax_amount);
                            $final_price = $price_without_tax + $tax_amount;
                            $final_price_excl_tax = $price_without_tax;
    
                            if($has_regular_price)
                            {
                                $regular_price_without_tax = 1 * $regular_price_without_tax;
                                $regular_tax_amount = array_sum(@WC_Tax::calc_tax( $regular_price_without_tax, array($rate_id => $rate), false));
                                $regular_price_with_tax += $regular_tax_amount;
                            }
    
                            $tax_rate['code'] = $setting_tax_class ? $setting_tax_class.'_'.$rate_id : 'standard'.'_'.$rate_id;
                            $tax_rate['rate_id'] = $rate_id;
                            if($rate['label'])
                            {
                                $tax_rate['label'] = $rate['label'];
                            }
                            if(isset($rate['shipping']))
                            {
                                $tax_rate['shipping'] = $rate['shipping'];
                            }
                            if(isset($rate['compound']))
                            {
                                $tax_rate['compound'] = $rate['compound'];
                            }
                            if(isset($rate['rate']))
                            {
                                $tax_rate['rate'] = $rate['rate'];
                            }
                            
    
                        }
                        // custom tax
                        $tmp_tax_rates[] = $tax_rate;
                    }
                }
            }
            $data = array(
                'taxes' => $tmp_tax_rates,
                'tax_amount' => $tax_amount,
                'regular_tax_amount' => $regular_tax_amount,
                'regular_price_with_tax' => $regular_price_with_tax,
                'regular_price_excl_tax' => $regular_price_without_tax,
                'price_incl_tax' => $final_price,
                'price_excl_tax' => $final_price_excl_tax,
                'has_regular_price' =>  $has_regular_price 
            );
            wp_cache_set( $cache_key, $data, $cache_group );
        }
        
        
        return $data;
    }
    
    public function get_product_formatted_data($_product,$warehouse_id = 0,$ignore_variable = false,$is_search = false,$currency = null,$cache_group = '',$show_out_of_stock = true){
        global $op_warehouse;
        global $op_session_data;
        
        $product_id = $_product->ID;
        $product = wc_get_product($product_id);
        
        if(!$product)
        {
            return false;
        }

        $cache_key = 'op_product_cache_'.$warehouse_id.'_'.$product_id;
        $cached_data = false;
        if($cache_group)
        {
            $cached_data = wp_cache_get( $cache_key, $cache_group );
        }

		if ( false !== $cached_data  ) {
            $tmp = $cached_data;
		}else{
            $show_out_of_stock_setting = $this->settings_api->get_option('pos_display_outofstock','openpos_pos');
            $parent_product = null;
            $product_variation = null;
            if(!$currency || $currency == null)
            {
                $currency_pos = get_option( 'woocommerce_currency_pos' );
                $currency  = array(
                    'decimal' => wc_get_price_decimals(),
                    'decimal_separator' => wc_get_price_decimal_separator(),
                    'thousand_separator' => wc_get_price_thousand_separator(),
                    'currency_pos' => $currency_pos,
                    'code' => get_woocommerce_currency(), 
                    'symbol' => html_entity_decode(get_woocommerce_currency_symbol()), 
                    'rate' => 1
                );
            }
            $openpos_type = $this->settings_api->get_option('openpos_type','openpos_pos');
            $setting_tax_class =  apply_filters('get_product_formatted_data:pos_tax_class', $this->settings_api->get_option('pos_tax_class','openpos_general'),$_product,$warehouse_id );
            $display_price_with_tax =  apply_filters('pos_display_price_with_tax',('incl' === get_option('woocommerce_tax_display_shop')) ? true : false,$product,$warehouse_id);;
            //$type = $product->get_type();
            $product_type = $product->get_type();
            $post_type = get_post_type($product->get_id());    
            $lang = $this->settings_api->get_option('pos_language','openpos_pos');
            $child_products = array();
            $price_display_html = $product->get_price_html();
            $v_price_display_html = '';
            $group = array();
            $options = array();
            $bundles = array();
            $variations = array();
            $child_product_keywords = array();
            $is_orphaned = $this->is_orphaned($product);
            if($product_type == 'grouped')
            {
                return false;
            }
            if($is_orphaned)
            {
                return false;
            }
            $image =  wc_placeholder_img_src() ;
            if ( has_post_thumbnail( $product->get_id() ) ) {
                $attachment_id =  get_post_thumbnail_id( $product->get_id() );
                $size = 'shop_thumbnail';
                $custom_width = $this->settings_api->get_option('pos_image_width','openpos_pos');
                $custom_height = $this->settings_api->get_option('pos_image_height','openpos_pos');
                if($custom_width && $custom_height)
                {
                    $size = array($custom_width,$custom_height);
                }
                $image_attr = wp_get_attachment_image_src($attachment_id, $size);
    
                if(is_array($image_attr))
                {
                    $image = $image_attr[0];
                }
            }
            
            $manage_stock = $this->_manage_stock($product,$warehouse_id);
            $price_included_tax = $this->_price_included_tax($product,$warehouse_id);
            //end
            
            $decimal_qty = $this->enable_decimal_qty($product) ? 'yes' : 'no';
    
            $allow_decal = $this->enable_print_decal($product) ? 'yes' : 'no';
            $unit_type = '';
            $type_unit = '';
            if( $tmp_type = $this->_is_weight_base_pricing($product->get_id()))
            {
              
                $unit_type = 'weight_base';
                if($tmp_type == 'weight_base'){
                    $type_unit    = get_option( 'woocommerce_weight_unit' );
                    $decimal_qty = 'yes';
                }
                if($tmp_type == 'length_base'){
                    $type_unit = get_option( 'woocommerce_dimension_unit' );
                    $decimal_qty = 'yes';
                }
            }else{
                if( $tmp_type = $this->_is_price_base_pricing($product_id))
                {
                    $unit_type = 'price_base';
                    $decimal_qty = 'yes';
                }
            }
            $barcode = strtolower(trim($this->_core->getBarcode($product_id)));
            $custom_notes = $this->getProductCustomNotes($product_id);
            
            $categories = $this->get_product_categories($product_id);
            if(!$categories)
            {
                $categories = array();
            }
            
            $parent_id = wp_get_post_parent_id($product_id);
            
            $tmp = array(
                'name' => $product->get_name(),
                'id' => $product_id,
                'parent_id' => $parent_id,
                'sku' => $product->get_sku(),
                'barcode' => $barcode,
                'image' => $image,
                'special_price' => $product->get_sale_price() ?  $this->_core->rounding_price(1 *floatval($product->get_sale_price()),$currency['decimal']) :  $this->_core->rounding_price($product->get_sale_price(),$currency['decimal']),
                'regular_price' =>  $product->get_regular_price() ?  $this->_core->rounding_price(1 * floatval($product->get_regular_price()),$currency['decimal']) :  $this->_core->rounding_price($product->get_regular_price(),$currency['decimal']),
                'sale_from' => $product->get_date_on_sale_from(),
                'sale_to' => $product->get_date_on_sale_to(),
                'status' => $product->get_status(),
                'categories' => array_unique($categories),
                'price_included_tax' => 1 * $price_included_tax,
                'display_special_price' => false,
                'allow_change_price' => false,
                'product_type' => $product_type,
                'type' => $unit_type,
                'decimal_qty' => $decimal_qty,
                'custom_notes' => $custom_notes,
                'allow_decal' => $allow_decal,
            );
            
            if($unit_type)
            {
                $tmp['type_unit'] = $type_unit;
            }
            
            if($lang == 'vi' || !isset( $tmp['search_keyword']))
            {
                $tmp['search_keyword'] = $this->custom_vnsearch_slug($tmp['name']);
            }

            
            if($this->settings_api->get_option('pos_change_price','openpos_pos') == 'yes')
            {
                $tmp['allow_change_price'] = true;
            }
            if($op_session_data && isset($op_session_data['login_cashdrawer_mode']))
            {
                if($op_session_data['login_cashdrawer_mode'] == 'customer')
                {
                    $tmp['allow_change_price'] = false;
                }
            }


            //$manage_stock =  $this->_manage_stock($product,$warehouse_id);
            $parent_id = isset($tmp['parent_id']) ? $tmp['parent_id'] : wp_get_post_parent_id($product_id);
            if($product_type == 'grouped' && !$ignore_variable)
            {
                $group = $product->get_children();
            }
            $qty = $op_warehouse->get_qty($warehouse_id,$product_id);
            $stock_status = $product->get_stock_status('edit');
            if($warehouse_id > 0  )
            {
                $wqty =  $op_warehouse->get_qty($warehouse_id,$product_id);
                if(!$wqty)
                {
                    $wqty = 0;
                }
                $qty = 1 *  $wqty;
            }
            $display_pos = true;
            if($post_type== 'product_variation')
            {
                $display_pos = false;
            }

            if(!$ignore_variable)
            {
                switch ($product_type)
                {

                    case 'grouped':
                        $group = $product->get_children();
                        break;
                    case 'variable':
                        if($post_type == 'product')
                        {
                            if($is_search)
                            {
                                $child_ids = $product->get_children();
                                //$_parent_product = $this->get_parent_product_formatted_data($product,$warehouse_id ,true,$is_search);
                                foreach($child_ids as $cid)
                                {   
                                    $child_product_pos = get_post($cid);
                                    $child_product_data = $this->get_product_formatted_data($child_product_pos,$warehouse_id,true);
                                    $child_name = $product->get_name();
                                    $attribute_label = wc_get_formatted_variation( $child_product_pos, true, false );
                                    if($attribute_label)
                                    {
                                        $child_name.= ' ';
                                        $child_name.= $attribute_label;
                                    }
                                    $child_product_data['name'] = $child_name;
                                    if(isset($child_product_data['barcode']))
                                    {
                                        $cbarcode = $child_product_data['barcode'];
                                        $child_products[$cbarcode] = $child_product_data;
                                    }
                                }
                            }
                            
                            $variations_result = $this->get_variations($product->get_id(),$warehouse_id,$display_price_with_tax,$currency,$show_out_of_stock);
                            
                            
                            $child_product_keywords  = $variations_result ?  $variations_result['child_keywords'] : array();
                            $variations = $variations_result ?  $variations_result['variations'] : array();
                            $price_list = $variations_result ?  $variations_result['price_list'] : array();
                            $regular_price_list = $variations_result ? $variations_result['regular_price_list'] : array();
                            $qty_list = $variations_result ? $variations_result['qty_list'] : array();
                            $manage_stocks = $variations_result ? $variations_result['manage_stocks'] : array();
                            $_stock_manager =  $this->_manage_stock($product,$warehouse_id); 
                            $backorders = $product->get_backorders();
                            $qty = 0;
                            if($_stock_manager)
                            {
                                if($warehouse_id == 0)
                                {
                                    $qty = $product->get_stock_quantity();
                                }else{
                                    foreach($qty_list as $_qty)
                                    {
                                        if($_qty > 0)
                                        {
                                            $qty += 1 * $_qty;
                                        }
                                    }
                                }
                                if($qty <= 0 && $backorders == 'no')
                                {
                                    $stock_status = 'outofstock';
                                }
                            }else{
                                
                                foreach($qty_list as $_qty)
                                {
                                    if($_qty > 0)
                                    {
                                        $qty += 1 * $_qty;
                                    }
                                }
                                if(is_array($manage_stocks) && in_array('no',$manage_stocks))
                                {
                                    $manage_stock = false;
                                }else{
                                    $manage_stock = true;
                                }
                            }
                            
                            if(!empty($price_list))
                            {
                                $price_list_min = min($price_list);
                                $price_list_max = max($price_list);


                                if($price_list_min != $price_list_max)
                                {
                                    $price_list_min = wc_price($price_list_min);
                                    $price_list_max = wc_price($price_list_max);
                                    $v_price_display_html = wc_format_price_range($price_list_min,$price_list_max);
                                }else{

                                    $v_price_display_html = wc_price($price_list_min);
                                    if(!empty($regular_price_list))
                                    {
                                        $regular_max = max($regular_price_list);
                                        if($price_list_min < $regular_max)
                                        {
                                            $v_price_display_html = wc_format_sale_price($regular_max,$price_list_min);
                                        }
                                    }
                                }
                            }

                        }
                        break;
                    default:
                        if($setting_tax_class != 'op_productax')
                        {
                            $price_display_html = wc_price(wc_get_price_excluding_tax($product));
                        }
                        break;
                }
            }
            if($price_display_html == null)
            {
                $price_display_html = $v_price_display_html;
            }
            
            $taxes = $this->_tax($product,$warehouse_id);
            
            $tax_amount = $taxes['tax_amount'];
            $has_regular_price = $taxes['has_regular_price'];

            $price_without_tax = $taxes['price_excl_tax'];
            $price_incl_tax = $taxes['price_incl_tax'];

            $regular_price_without_tax = $taxes['regular_price_excl_tax'];
            $regular_price_with_tax = $taxes['regular_price_with_tax'];
            if(!$price_without_tax)
            {
                $price_without_tax = 0;
            }
            if(!$price_incl_tax)
            {
                $price_incl_tax = 0;
            }
            
            if($product_type == 'variable')
            {
                $price_display_html = $v_price_display_html;
            }else{

                if($display_price_with_tax)
                {
                    $price_display_html = wc_price($price_incl_tax);
                    if( wc_price($price_incl_tax) !=  wc_price($regular_price_with_tax) && (float)$regular_price_with_tax > 0)
                    {
                        
                        $price_display_html = wc_format_sale_price($regular_price_with_tax,$price_incl_tax);
                    }
                }else{
                    $price_display_html = wc_price($price_without_tax);
                    if( wc_format_decimal($price_without_tax) !=  wc_format_decimal($regular_price_without_tax) && (float)$regular_price_with_tax > 0)
                    {
                        $price_display_html = wc_format_sale_price($regular_price_without_tax,$price_without_tax);
                    }
                }
            }
            
            

            $price = 1 * floatval($price_without_tax);
            $price_incl_tax = 1 * floatval($price_incl_tax);

            if($has_regular_price)
            {
                $price = 1 * $regular_price_without_tax;
                $price_incl_tax = 1 * $regular_price_with_tax;
            }
            $final_price = 1 * floatval($price_without_tax);

            
        
            
            
            
            
            if($warehouse_id > 0 && $manage_stock)
            {
                $stock_status = ($qty > 0) ? 'instock' : 'outofstock';
            }

            if($display_pos && $show_out_of_stock_setting != 'yes')
            {
                if($manage_stock)
                {
                    if($qty <= 0 )
                    {
                        $display_pos = false;
                    }
                }elseif($stock_status == 'outofstock'){
                    $display_pos = false;
                }
            }
            
            

            if($price_display_html == 'null' || $price_display_html == null)
            {
                $price_display_html = ' ';
            }
            
            if($product_id != $parent_id)
            {
                $parent_post = get_post($parent_id);
                if($parent_post && $parent_post->post_type == 'product' && $post_type == 'product_variation')
                {
                    $parent_product = $this->get_parent_product_formatted_data($parent_post,$warehouse_id ,true,$is_search,$display_price_with_tax);
                    
                    if(!empty($parent_product['variations']))
                    {
                        $product_variation_options = array();
                        $product_variation_value_label = array();
                        foreach($parent_product['variations'] as $v)
                        {
                            $slug = $v['slug'];
                            $title = $v['title'];
                            $value = array(''.$barcode);
                            $value_label = $product->get_attribute( $slug );

                            foreach($v['options'] as $o)
                            {
                                if(sanitize_title($value_label) == sanitize_title($o['title']))
                                {
                                    $value = $o['values'];
                                }
                            }

                            $product_variation_options[$slug] = array(
                                'title' => $title,
                                'slug' => $slug,
                                'value' => $value,
                                'value_label' => $value_label,
                            );
                            $product_variation_value_label[$slug] = $value_label;
                        }

                        $product_variation = array(
                            'options' => $product_variation_options,
                            'value' => ''.$barcode,
                            'price' => $final_price,
                            'value_label' => $product_variation_value_label,
                        );
                    }
                    $tmp['parent_product'] = $parent_product;
                }
                
                
            }
            if($qty == '' || $qty == null)
            {
                $qty = 0;
            }

            if($openpos_type == 'restaurant')
            {
                if($price_display_html)
                {
                    $price_takeaway_html = $price_display_html;
                    $price_table_html = $price_display_html;
                }else{
                    $price_takeaway_html = wc_price($final_price);
                    $price_table_html = wc_price($final_price);
                }
                $tmp['price_takeaway_html'] = $price_takeaway_html;
                $tmp['price_table_html'] = $price_table_html;

                $all_area = $this->getListRestaurantArea();
                $parent_id = isset($tmp['parent_id']) ? $tmp['parent_id'] : 0;
                if(!$parent_id)
                {
                    $parent_id = $tmp['id'];
                }
                $area = array();
                foreach($all_area as $akey => $a){
                    $a_key = '_op_'.$akey;
                    $_op_value = get_post_meta($parent_id,$a_key,true);
                    if($_op_value == 'yes')
                    {
                        $area[] = $akey;
                    }
                }

                $tmp['kitchen_area'] = $area;
            }
            $tmp['qty'] = $qty;
            $tmp['manage_stock'] = $manage_stock;
            $tmp['display'] = $display_pos;
            $tmp['stock_status'] = $stock_status;
            $tmp['price'] = $this->_core->rounding_price($price,$currency['decimal']);
            $tmp['price_incl_tax'] = $this->_core->rounding_price($price_incl_tax,$currency['decimal']);
            $tmp['final_price'] = $this->_core->rounding_price($final_price,$currency['decimal']);
            $tmp['tax'] = $taxes['taxes'];
            $tmp['tax_amount'] =  $this->_core->rounding_price(1 * $tax_amount,$currency['decimal']);
            
            $tmp['price_display_html'] = $price_display_html;
            $tmp['product_variation'] = $product_variation;
            

            $tmp['group_items'] = $group;
            $tmp['variations'] = $variations;
            $tmp['options'] = $options;
            $tmp['bundles'] = $bundles;


            if(!empty($child_products ))
            {
                $tmp['child_products'] = $child_products ;
            }

            $meta_key = '_openpos_product_version_'.$warehouse_id;
            $db_version =  get_post_meta( $product_id, $meta_key, true );
            if(!$db_version)
            {
                $db_version = 0;
            }

            $tmp['version'] = $db_version;
            $tmp['search_keyword'] .= ' '.implode(' ',array_unique($child_product_keywords));

            


            wp_cache_set( $cache_key, $tmp, $cache_group,HOUR_IN_SECONDS );
        
        }
        
            
       
        $product_data = apply_filters('op_product_data',$tmp,$_product,$warehouse_id);
        
       
        return $product_data;

    }

    public function get_parent_product_formatted_data($parent_post,$warehouse_id ,$ignore_variable = false,$is_search = false,$display_price_with_tax= false){
        $product_id = $parent_post->ID;

        $cache_key = 'op_parent_product_cache_'.$warehouse_id.'_'.$product_id;
        $cache_group = 'products';

        $cached_data = wp_cache_get( $cache_key, $cache_group );

        if ( false !== $cached_data )
        {
            $tmp_v = $this->get_variations($product_id,$warehouse_id,$display_price_with_tax);
            $variations = $tmp_v && isset($tmp_v['variations']) ? $tmp_v['variations'] : array();
            $cached_data['variations'] = $variations;
            $data = $cached_data;
        }else{
            $tmp_v = $this->get_variations($product_id,$warehouse_id,$display_price_with_tax);
            $variations = $tmp_v && isset($tmp_v['variations']) ? $tmp_v['variations'] : array();
            $child_products = array();
            $data = array(
                'id' => $product_id,
                'barcode' => strtolower(trim($this->_core->getBarcode($product_id))),
                'variations' => $variations,
                //'child_products' => $child_products,
            );
            wp_cache_set( $cache_key, $data, $cache_group );
        }
        
        return $data;
    }
    public function getTaxRates($tax_class){
        global $wpdb;
        $criteria = array();
        $criteria[] = $wpdb->prepare( 'tax_rate_class = %s', sanitize_title( $tax_class ) );
        $found_rates = $wpdb->get_results( "
			SELECT tax_rates.*
			FROM {$wpdb->prefix}woocommerce_tax_rates as tax_rates
			WHERE 1=1 AND " . implode( ' AND ', $criteria ) . "
			GROUP BY tax_rates.tax_rate_id
			ORDER BY tax_rates.tax_rate_priority
		");

        $matched_tax_rates = array();

        foreach ( $found_rates as $found_rate ) {

            $matched_tax_rates[ $found_rate->tax_rate_id ] = array(
                'rate'     => (float) $found_rate->tax_rate,
                'label'    => $found_rate->tax_rate_name,
                'shipping' => $found_rate->tax_rate_shipping ? 'yes' : 'no',
                'compound' => $found_rate->tax_rate_compound ? 'yes' : 'no',
            );
        }
        return $matched_tax_rates;
    }
    public function getLocationTaxRates($tax_class,$location = array()){
  
        if(isset($location['country']) && $location['country'])
        {
            $calculate_tax_for['country'] =$location['country'];
        }
        if(isset($location['state']) && $location['state'])
        {
            $calculate_tax_for['state'] = $location['state'];
        }
        if(isset($location['city']) && $location['city'])
        {
            $calculate_tax_for['city'] = $location['city'];
        }
        if(isset($location['postcode']) && $location['postcode'])
        {
            $calculate_tax_for['postcode'] = $location['postcode'];
        }

        $calculate_tax_for['tax_class'] = $tax_class;
        $found_rates = WC_Tax::find_rates( $calculate_tax_for );
       
        return  $found_rates;
    }
    public function stripe_charge($amount,$source){
        global $OPENPOS_SETTING;
        $stripe_secret_key = $OPENPOS_SETTING->get_option('stripe_secret_key','openpos_payment');
        if($stripe_secret_key)
        {
            \Stripe\Stripe::setApiKey($stripe_secret_key);
            $currency = get_woocommerce_currency();
            $charge = \Stripe\Charge::create(['amount' => $amount, 'currency' => strtolower($currency), 'source' => $source]);
            return $charge->toArray(true);
        }else{
            return array();
        }
    }

    public function stripe_refund($charge_id){
        global $OPENPOS_SETTING;
        $stripe_secret_key = $OPENPOS_SETTING->get_option('stripe_secret_key','openpos_payment');
        if($stripe_secret_key)
        {
            \Stripe\Stripe::setApiKey($stripe_secret_key);

            $refund = \Stripe\Refund::create([
                'charge' => $charge_id,
            ]);
            return $refund->toArray(true);
        }else{
            return array();
        }
    }

    public function get_pos_categories($register_id = 0){
        global $OPENPOS_SETTING;
        $result = array();
        $category_ids = apply_filters( 'pos_setting_categories',$OPENPOS_SETTING->get_option('pos_categories','openpos_pos'),$register_id);

        if(is_array($category_ids))
        {

            foreach($category_ids as $cat_id)
            {
                $term = get_term_by( 'id', $cat_id, 'product_cat', 'ARRAY_A' );
                if($term && !empty($term))
                {
                    $parent_id =  $term['parent'];
                    if(!in_array($parent_id,$category_ids))
                    {
                        $parent_id = 0;
                    }
                    $tmp  = array(
                        'id' => $cat_id,
                        'name' => $term['name'],
                        'image' => OPENPOS_URL.'/assets/images/category_placehoder.png',
                        'description' => '',
                        'parent_id' => $parent_id,
                        'child' => array()
                    );

                    $thumbnail_id = get_term_meta( $cat_id, 'thumbnail_id', true );
                    $image = wp_get_attachment_url( $thumbnail_id );
                    if ( $image ) {
                         $tmp['image'] = $image;
                    }

                    $result[] = apply_filters('op_category_data',$tmp,$category_ids);
                }
            }
        }
        if(!empty($result))
        {
            $tree = $this->buildTree($result);
        }else{
            $tree = [];
        }


        return apply_filters('op_category_tree_data',$tree,$result);
    }


    function buildTree($items) {
        $childs = array();
        foreach($items as &$item) $childs[$item['parent_id']][] = &$item;
        unset($item);
        foreach($items as &$item) if (isset($childs[$item['id']]))
            $item['child'] = $childs[$item['id']];
        return $childs[0];
    }

    public function get_product_categories($product_id){
        global $OPENPOS_SETTING;
        global $op_session_data;
        $product = wc_get_product($product_id);
        $categories = $product->get_category_ids();

        $login_cashdrawer_id = $op_session_data && isset($op_session_data['login_cashdrawer_id']) ?  $op_session_data['login_cashdrawer_id'] : 0;
        if($login_cashdrawer_id > 0)
        {
            $category_ids = apply_filters( 'pos_setting_categories',$OPENPOS_SETTING->get_option('pos_categories','openpos_pos'),$login_cashdrawer_id);
        }else{
            $category_ids = $OPENPOS_SETTING->get_option('pos_categories','openpos_pos');
        }
        foreach($categories as $cat_id)
        {
            $tmp = $this->_cat_parent_ids($cat_id);
            $categories = array_merge($categories,$tmp);
        }
        $categories = array_unique($categories);
        if(!is_array($category_ids))
        {
            $cats = array();
        }else{
            $cats = array_intersect($category_ids,$categories);
        }

        if(!empty($cats))
        {
            $rest_cats = array_values($cats);
            return $rest_cats;
        }
        return $cats;
    }
    private function _cat_parent_ids($cat_id){
        $term = get_term_by( 'id', $cat_id, 'product_cat', 'ARRAY_A' );

        $result = array();
        if($term && $term['parent'] > 0 && $term['parent'] != $cat_id)
        {
            $result[] = $term['parent'];
            $tmp = $this->_cat_parent_ids($term['parent']);
            $result = array_merge($result,$tmp);
        }
        return $result;
    }

    public function get_setting_shipping_methods(){
        $shipping_methods = array();// WC()->shipping()->get_shipping_methods();
        

        $zone = WC_Shipping_Zones::get_zone(0);
        $shipping_methods = array_merge($shipping_methods,$zone->get_shipping_methods( false, 'admin' ));

        $delivery_zones = WC_Shipping_Zones::get_zones();
        
        foreach($delivery_zones as $zone)
        {
            $shipping_methods = array_merge($shipping_methods,$zone['shipping_methods']);
        }  
        
        return $shipping_methods;
    }

    public function get_shipping_method_by_code($code){
        $shipping_methods = WC()->shipping()->get_shipping_methods();
        $result = array(
                'code' => 'openpos',
                'title' => __('Custom Shipping','openpos')
        );
        foreach ($shipping_methods as $shipping_method)
        {
            $shipping_code = $shipping_method->id;
            if($code == $shipping_code)
            {
                $title = $shipping_method->method_title;
                if(!$title)
                {
                    $title = $code;
                }
                $result = array(
                    'code' =>$code,
                    'title' => $title
                );
            }

        }
        return $result;
    }

    public function woocommerce_available_payment_gateways($payment_methods){
        $order_id = absint( get_query_var( 'order-pay' ) );
        if($order_id > 0)
        {
            $pos_payment = get_post_meta($order_id,'pos_payment',true);
            if($pos_payment && is_array($pos_payment) && isset($pos_payment['code']))
            {
                $payment_code = $pos_payment['code'];
                if(isset($payment_methods[$payment_code]))
                {
                    $new_payment_method = array();
                    $new_payment_method[$payment_code] = $payment_methods[$payment_code];

                    return apply_filters( 'openpos_woocommerce_available_payment_gateways',$new_payment_method, $payment_methods );

                }

            }

        }
        return $payment_methods;
    }
    public function woocommerce_order_get_payment_method_title($value, $object){
        $payment_code = $object->get_payment_method();
        if($payment_code == 'pos_multi')
        {
            $methods = get_post_meta($object->get_id(), '_op_payment_methods', true);
            $method_values = array();
            if(!is_array($methods))
            {
                $methods = array();
            }
            foreach($methods as $code => $method)
            {
                $paid = isset($method['paid']) ? $method['paid'] : 0;
                if($paid > 0 && isset($method['name']))
                {
                    $return_paid = isset($method['return']) ? $method['return'] : 0;
                    $ref = isset($method['ref']) ? trim($method['ref']) : '';
                    if($return_paid > 0)
                    {
                        $paid = $paid - $return_paid;

                    }
                    if($ref)
                    {
                        $method_values[] = $method['name'].': '.strip_tags(wc_price($paid)).'('.$ref.')';
                    }else{
                        $method_values[] = $method['name'].': '.strip_tags(wc_price($paid));
                    }

                }

            }
            if(!empty($method_values))
            {
                return implode(', ',$method_values);
            }

        }
        return $value;
    }
    public function woocommerce_admin_order_data_after_shipping_address($order){
        $is_pos = get_post_meta($order->get_id(),'_op_order_source',true);

        if($is_pos == 'openpos' )
        {
            $_pos_shipping_phone = get_post_meta($order->get_id(),'_pos_shipping_phone',true);
            if($_pos_shipping_phone)
            {
                echo sprintf('<p><label>%s</label> : <span>%s</span></p>',__('Shipping Phone'),$_pos_shipping_phone);
            }
        }
    }
    // get formatted customer shipping address
    public function getCustomerShippingAddress($cutomer_id){
            $result = array();

            $customer = new WC_Customer($cutomer_id);
            $first_name = $customer->get_shipping_first_name();
            $last_name = $customer->get_shipping_last_name();
            if(!$first_name && !$last_name)
            {
                $first_name = $customer->get_first_name();
                $last_name = $customer->get_last_name();
            }
            $address_1 = $customer->get_shipping_address_1();
            $address_2 = $customer->get_shipping_address_2();
            $address = $address_1;
            if($address_1 && !$address)
            {
                $address = $address_1;

            }
            if($address_2 && !$address)
            {
                $address = $address_2;

            }
            if(!$address){
               // $address = $customer->get_address();
            }
            $phone = $customer->get_billing_phone();
            $shipping_address = array(
                'id' => 1,
                'title' => __('Shipping: ','openpos').$address,
                'name' => implode(' ',array($first_name,$last_name)),
                'address' => $address,
                'address_2' => $customer->get_shipping_address_2(),
                'state' => $customer->get_shipping_state(),
                'postcode' => $customer->get_shipping_postcode(),
                'city' => $customer->get_shipping_city(),
                'country' => $customer->get_shipping_country(),
                'phone' => $phone,
            );
            $billing_first_name = $customer->get_billing_first_name();
            $billing_last_name = $customer->get_billing_last_name();
            $billing_address = array(
                'id' => 2,
                'title' => __('Billing: ','openpos').$customer->get_billing_address_1(),
                'name' => implode(' ',array($billing_first_name,$billing_last_name)),
                'address' => $customer->get_billing_address_1(),
                'address_2' => $customer->get_billing_address_2(),
                'state' => $customer->get_billing_state(),
                'postcode' => $customer->get_billing_postcode(),
                'city' => $customer->get_billing_city(),
                'country' => $customer->get_billing_country(),
                'phone' => $phone,
            );
            $result[] = $billing_address;
            $result[] = $shipping_address;
            return $result;
    }
    public function woocommerce_product_options_sku_after(){
        global $post;
        global $product_object;
        $barcode_field = $this->settings_api->get_option('barcode_meta_key','openpos_label');
        $allow = false;
        if(!$barcode_field)
        {
            $barcode_field = '_op_barcode';
        }
        if($barcode_field == '_op_barcode' )
        {
            $allow = true;
        }

        if($allow && $product_object && $product_object != null) {
            $value = '';
            $product_id = $product_object->get_id();
            if($product_id)
            {
                $value = get_post_meta($product_id,$barcode_field,true);

            }
            echo '<div class="options_group hide_if_variable hide_if_grouped">';
            woocommerce_wp_text_input(
                array(
                    'id' => '_op_barcode',
                    'value' => $value,
                    'label' => '<abbr title="' . esc_attr__('OP Barcode', 'openpos') . '">' . esc_html__('Barcode', 'openpos') . '</abbr>',
                    'desc_tip' => true,
                    'description' => __('Barcode refers to use in POS panel.', 'openpos'),
                )
            );
            echo '</div>';
        }
    }

    public function woocommerce_variation_options_dimensions_after($loop, $variation_data, $variation){

            $barcode_field = $this->settings_api->get_option('barcode_meta_key','openpos_label');
            $allow = false;
            if(!$barcode_field)
            {
                $barcode_field = '_op_barcode';
            }
            if($barcode_field == '_op_barcode' )
            {
                $allow = true;
            }

            if($allow)
            {

                $value = '';
                if($variation && isset($variation->ID))
                {
                   $variation_id = $variation->ID;
                   $value = get_post_meta($variation_id,$barcode_field,true);

                }

                woocommerce_wp_text_input(
                    array(
                        'id'                => "_op_barcode{$loop}",
                        'name'              => "_op_barcode[{$loop}]",
                        'label'       => '<abbr title="' . esc_attr__( 'POS Barcode', 'openpos' ) . '">' . esc_html__( 'OP Barcode', 'openpos' ) . '</abbr>',
                        'desc_tip'    => true,
                        'value' => $value,
                        'description' => __( 'Barcode refers to use in POS panel.', 'openpos' ),
                        'wrapper_class' => 'form-row form-row-full',
                    )
                );
            }

    }
    public function woocommerce_save_product_variation($variation_id, $i){
        global $op_warehouse;
        $barcode = isset( $_POST['_op_barcode'][ $i ] ) ? sanitize_text_field($_POST['_op_barcode'][ $i ]) : '';
        $_op_cost_price = isset( $_POST['_op_cost_price'][ $i ] ) ? sanitize_text_field($_POST['_op_cost_price'][ $i ]) : '';

        update_post_meta($variation_id,'_op_barcode',$barcode);
        update_post_meta($variation_id,'_op_cost_price',$_op_cost_price);
        $op_warehouse_stock = isset( $_POST['_op_stock']) ? $_POST['_op_stock'] : array();
        $op_warehouse_instore = isset( $_POST['_op_instore']) ? $_POST['_op_instore'] : array();
        foreach($op_warehouse_stock as $warehouse_id => $qty_varation)
        {
            if(is_numeric($qty_varation[ $i ]))
            {
                
                $qty = isset($qty_varation[ $i ]) ? 1* $qty_varation[ $i ] : 0;
                $op_warehouse->set_qty($warehouse_id,$variation_id, 1*$qty );
                
            }
            if(isset($op_warehouse_instore[$warehouse_id][$i]) && $op_warehouse_instore[$warehouse_id][$i] =='yes')
            {
                $op_warehouse->add_instore($warehouse_id,$variation_id);
            }else{
                $op_warehouse->remove_instore($warehouse_id,$variation_id);
            }
            
        }

    }
    public function woocommerce_admin_process_product_object($product_id){
        global $op_warehouse;
        $barcode = isset( $_POST['_op_barcode'] ) ? wc_clean( wp_unslash( $_POST['_op_barcode'] ) ) : '';
        $_op_cost_price = isset( $_POST['_op_cost_price'] ) ? wc_clean( wp_unslash( $_POST['_op_cost_price'] ) ) : '';
        $_op_weight_base_pricing = isset( $_POST['_op_weight_base_pricing'] ) ? wc_clean( wp_unslash( $_POST['_op_weight_base_pricing'] ) ) : 'no';
        $_op_qty_decimal = isset( $_POST['_op_qty_decimal'] ) ? wc_clean( wp_unslash( $_POST['_op_qty_decimal'] ) ) : 'no';
        $_op_allow_decal = isset( $_POST['_op_allow_decal'] ) ? wc_clean( wp_unslash( $_POST['_op_allow_decal'] ) ) : 'no';
        $_op_quick_notes = isset( $_POST['_op_quick_notes'] ) ? array_map( 'stripslashes', (array) wp_unslash( $_POST['_op_quick_notes'] ) ) : array();

        $openpos_type = $this->settings_api->get_option('openpos_type','openpos_pos');
        if(isset($_POST['product-type']))
        {
           
           
            $product_type = empty( $_POST['product-type'] ) ? WC_Product_Factory::get_product_type( $product_id ) : sanitize_title( wp_unslash( $_POST['product-type'] ) );
            if($product_type == 'variable')
            {
    
                $barcode = '';
            }
            update_post_meta($product_id,'_op_barcode',$barcode);


            $new_op_cost_price = ( '' === $_op_cost_price ) ? '' : wc_format_decimal( $_op_cost_price );

            update_post_meta($product_id,'_op_cost_price',$new_op_cost_price);

            update_post_meta($product_id,'_op_weight_base_pricing',$_op_weight_base_pricing); //weight

            update_post_meta($product_id,'_op_qty_decimal',$_op_qty_decimal); // decimal qty
            update_post_meta($product_id,'_op_quick_notes',$_op_quick_notes); // decimal qty
    
            
            if($openpos_type == 'restaurant')
            {
                update_post_meta($product_id,'_op_allow_decal',$_op_allow_decal); // decal print
            }

            if($product_type != 'variable')
            {
                $op_warehouse_stock = isset( $_POST['_op_stock']) ? $_POST['_op_stock'] : array();
                $op_warehouse_instore = isset( $_POST['_op_instore']) ? $_POST['_op_instore'] : array();
                
                if(is_array($op_warehouse_stock) && !empty($op_warehouse_stock))
                {
                    
                    foreach($op_warehouse_stock as $warehouse_id => $qty)
                    {
                        if(is_array($qty))
                        {
                            $qty = array_sum($qty);
                        }
                        if($qty)
                        {
                            $qty = 1 * $qty;
                            if(is_numeric($qty))
                            {
                                $op_warehouse->set_qty($warehouse_id,$product_id, 1*$qty );
                            }
                        }
                        if(isset($op_warehouse_instore[$warehouse_id]) && $op_warehouse_instore[$warehouse_id] == 'yes')
                        {
                            $op_warehouse->add_instore($warehouse_id,$product_id);
                        }else{
                            $op_warehouse->remove_instore($warehouse_id,$product_id);
                        }
                    }
                }
    
            }
        }
        


    }

    public function filter_orders_by_source(){
        global $typenow;
        if ( 'shop_order' === $typenow ) {
            $current = isset($_GET['_op_order_source']) ? esc_attr($_GET['_op_order_source']) : '';
             ?>
                <select name="_op_order_source" id="dropdown_order_source">
                    <option value="">
                        <?php esc_html_e( 'Filter by Source', 'openpos' ); ?>
                    </option>
                    <option <?php echo ($current == 'online') ? 'selected':''; ?> value="online"><?php esc_html_e( 'Online Order', 'openpos' ); ?></option>
                    <option <?php echo ($current == 'pos') ? 'selected':''; ?> value="pos"><?php esc_html_e( ' POS Orders', 'openpos' ); ?></option>

                </select>
            <?php
        }
    }
    public function filter_orders_by_source_hpos(){
        $current = isset($_GET['_op_order_source']) ? esc_attr($_GET['_op_order_source']) : '';
        ?>
           <select name="_op_order_source" id="dropdown_order_source">
               <option value="">
                   <?php esc_html_e( 'Filter by Source', 'openpos' ); ?>
               </option>
               <option <?php echo ($current == 'online') ? 'selected':''; ?> value="online"><?php esc_html_e( 'Online Order', 'openpos' ); ?></option>
               <option <?php echo ($current == 'pos') ? 'selected':''; ?> value="pos"><?php esc_html_e( ' POS Orders', 'openpos' ); ?></option>

           </select>
       <?php
    }
    public function add_order_filterable_where($where, $wp_query){
        global $typenow, $wpdb;

        if ( 'shop_order' === $typenow && isset( $_GET['_op_order_source'] ) && ! empty( $_GET['_op_order_source'] ) ) {
            // Main WHERE query part
            $source = isset($_GET['_op_order_source']) ? esc_attr($_GET['_op_order_source']) : '';
            if($source == 'online')
            {
                $where .= " AND $wpdb->postmeta.meta_value <> 'openpos'";
                //$where .= $wpdb->prepare( " AND woi.order_item_type='coupon' AND woi.order_item_name='%s'", wc_clean( $_GET['_coupons_used'] ) );
            }else{
                $where .= " AND $wpdb->postmeta.meta_value = 'openpos'";
            }

        }
        return $where;
    }
    public function filter_request_query($query){
        global $typenow, $wpdb;
        if(!$this->_enable_hpos)
        {
            if ( 'shop_order' === $typenow && isset( $_GET['_op_order_source'] ) && ! empty( $_GET['_op_order_source'] ) ) {
                $source = $_GET['_op_order_source'];
                $meta_query = $query->meta_query;
    
                if($source == 'online')
                {
                    $meta_arr = array(
                        'field' => '_op_order_source',
                        'compare' => 'NOT EXISTS'
                    );
                    $query->query_vars['meta_key'] = $meta_arr['field'];
                    $query->query_vars['meta_compare'] = $meta_arr['compare'];
                }else{
                    $meta_arr = array(
                        'field' => '_op_order_source',
                        'value' => 'openpos',
                        'compare' => '='
                    );
                    $query->query_vars['meta_key'] = $meta_arr['field'];
                    $query->query_vars['meta_value'] = $meta_arr['value'];
                    $query->query_vars['meta_compare'] = $meta_arr['compare'];
                }
            }
        }
        
      

        return $query;
    }
    public function woocommerce_order_query_args($query_vars){
        if ( $this->_enable_hpos &&  isset( $_GET['_op_order_source'] ) && ! empty( $_GET['_op_order_source'] ) ) {
            $source = isset($_REQUEST['_op_order_source']) ? sanitize_text_field( wp_unslash( $_REQUEST[ '_op_order_source' ] ?? '' ) ) : '';
            
            if($source)
            {
                if($source == 'online')
                {
                    $meta_arr = array(
                        'key' => '_op_order_source',
                        'compare' => 'NOT EXISTS'
                    );
                    
                    $query_vars['meta_query'][] = $meta_arr;
                   
                }else{
                    $meta_arr = array(
                        'key' => '_op_order_source',
                        'value' => 'openpos',
                        'compare' => '='
                    );
                    $query_vars['meta_query'][] = $meta_arr;
                    
                }
                
            }
            
        }
        return $query_vars;
    }
    
    public function woocommerce_after_order_itemmeta( $item_id, $item, $product){

        $seller_id =  $item->get_meta( '_op_seller_id');
        $_op_local_id =  $item->get_meta( '_op_local_id');
        if($_op_local_id)
        {
            $has_seller = false;
            if($seller_id)
            {
                $user = get_user_by('id',$seller_id);
                if($user)
                {
                    echo '<p>'.__('Seller: ','openpos').'<strong>'.$user->display_name.'</strong></p>';
                    $has_seller = true;
                }

            }
            if(!$has_seller)
            {
                echo '<p>'.__('Sold By Shop Agent','openpos').'</p>';
            }
        }

    }
    public function getProductChanged($local_ver,$warehouse_id = 0,$page = 1,$per_page = 30){
        global $wpdb;
        global $op_warehouse;
        $meta_key = '_openpos_product_version_'.$warehouse_id;
        if(!$local_ver || $local_ver == null || !is_numeric($local_ver))
        {
            $local_ver = 0;
        }
        $from = ($page - 1) * $per_page;
        $sql = "SELECT SQL_CALC_FOUND_ROWS {$wpdb->postmeta}.post_id,{$wpdb->postmeta}.meta_value FROM {$wpdb->postmeta} WHERE meta_key = '".$meta_key."' AND meta_value >".($local_ver - 1)." ORDER BY meta_value ASC LIMIT  %d,".$per_page;
        $final_sql = $wpdb->prepare( $sql,$from);
        $rows = $wpdb->get_results(  $final_sql, ARRAY_A);

        $result = array(
                'current_version' => $local_ver,
                'data' => array(),
                'found_posts' => 0
        );
        $db_version = $this->_core->getProductDbVersion($warehouse_id);
        $found_posts = (int) $wpdb->get_var( $wpdb->prepare("SELECT FOUND_ROWS()") );
        if($found_posts  < $per_page )
        {
            $result['current_version'] = $db_version;
        }
        $result['found_posts'] = $found_posts;
        foreach ($rows as $row)
        {
            $product_id = $row['post_id'];
            $product_verion = $row['meta_value'];
            $qty = $op_warehouse->get_qty($warehouse_id,$product_id);
            if($product_verion > $result['current_version'])
            {
                $result['current_version'] = $product_verion;
            }
            $barcode = $product_id; //$this->_core->getBarcode($product_id);
            $result['data'][$barcode] = $qty;
        }
        if($db_version < $result['current_version'])
        {
            $this->_core->setProductDbVersion($warehouse_id,$result['current_version']);
            
        }
        
        return $result;
    }
    public function title_filter( $where, $wp_query )
    {
        global $wpdb;
        if ( $search_term = $wp_query->get( 'search_prod_title' ) ) {
            $tax_query = $wp_query->get('tax_query');
           
            if( $post_status = $wp_query->get( 'post_status' ) )
            {
                if($post_status && !is_array($post_status))
                {
                    $post_status = array($post_status);
                }
                $villes = array_map(function($v) {
                    return "'" . esc_sql($v) . "'";
                }, $post_status);
                $villes = implode(',', $villes);
                $where .= ' OR (' . $wpdb->posts . '.post_title LIKE \'%' . esc_sql(  $search_term ) . '%\' AND ' . $wpdb->posts . '.post_status IN ('.$villes.') ) ';
            }else{
                $where .= ' OR ' . $wpdb->posts . '.post_title LIKE \'%' . esc_sql(  $search_term ) . '%\'';
            }
            
        }
       
        
       
        return $where;
    }
    public function get_meta_sql($data){
        global $op_search_prod_title_like;
        if($op_search_prod_title_like)
        {
            //print_r($data);
           // print_r($op_search_prod_title_like.' - ');
            //$op_search_prod_title_like = '';
        }
        
        return $data;
    }
    public function searchProductsByTerm($term,$limit=10){
        $args = array(
            'posts_per_page'   => $limit,
            'search_prod_title' => $term,
            'post_type'        => $this->_core->getPosPostType(),
            'post_status'      => 'publish',
            'suppress_filters' => false,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key'     => '_sku',
                    'value'   =>  trim($term) ,
                    'compare' => 'LIKE'
                )

            ),
        );
        $query = new WP_Query($args);
        $posts = $query->get_posts();

        return $posts;

    }
    public function getDefaultContry(){
        $store_country_state = get_option( 'woocommerce_default_country', '' );
        $store_country = '';
        $store_country_tmp = explode(':',$store_country_state);
        if($store_country_state && count($store_country_tmp) > 1)
        {
            $store_country = $store_country_tmp[0];
        }
        return $store_country;
    }
    public function getDefaultState(){
        $store_country_state = get_option( 'woocommerce_default_country', '' );
        $store_state = '';
        $store_country_tmp = explode(':',$store_country_state);
        if($store_country_state && count($store_country_tmp) == 2)
        {
            $store_state = $store_country_tmp[1];
        }
        return $store_state;
    }
    public function getCustomerAdditionFields(){

        $address_field = array(
            'code' => 'address',
            'type' => 'text',
            'label' =>  __('Address','openpos'),
            'options' => array(),
            'placeholder' => __('Address','openpos'),
            'description' => '',
            'onchange_load' => false,
            'allow_shipping' => 'yes',
            'required' => 'no',
            'searchable' => 'no'
        );

        $address_2_field = array(
            'code' => 'address_2',
            'type' => 'text',
            'label' =>  __('Address 2','openpos'),
            'options' => array(),
            'placeholder' => __('Address 2','openpos'),
            'description' => '',
            'onchange_load' => false,
            'allow_shipping' => 'yes',
            
        );


        $postcode_field = array(
            'code' => 'postcode',
            'type' => 'text',
            'label' =>  __('PostCode / Zip','openpos'),
            'options' => array(),
            'placeholder' => __('PostCode / Zip','openpos'),
            'description' => '',
            'onchange_load' => false,
            'allow_shipping' => 'yes',
        );

        $city_field = array(
            'code' => 'city',
            'type' => 'text',
            'label' =>  __('City','openpos'),
            'options' => array(),
            'placeholder' => __('City','openpos'),
            'description' => '',
            'onchange_load' => false,
            'allow_shipping' => 'yes',
        );

        $state_field = array(
            'code' => 'state',
            'type' => 'text',
            'label' =>  __('State','openpos'),
            'options' => array(),
            'placeholder' => __('State','openpos'),
            'description' => '',
            'onchange_load' => false,
            'allow_shipping' => 'yes',
        );

        $store_country = $this->getDefaultContry();
        $store_state  = $this->getDefaultState();
        $states = array();
        if($store_country)
        {
            $tmp_states     = WC()->countries->get_states( $store_country );
            foreach($tmp_states as $key => $val)
            {
                $_tmp_state = array(
                        'value' => $key,
                        'label' => $val
                );
                $states[] = $_tmp_state;
            }
        }
        if(!empty($states))
        {
            $state_field = array(
                'code' => 'state',
                'type' => 'select',
                'label' =>  __('State','openpos'),
                'options' => $states,
                'placeholder' => __('State','openpos'),
                'description' => '',
                'onchange_load' => false,
                'allow_shipping' => 'yes',
                'default' => $store_state
            );
        }
        $countries_obj   = new WC_Countries();
        //$countries   = $countries_obj->__get('countries');
        $country_options = array();
        $countries   = WC()->countries->get_allowed_countries();
        foreach($countries as $key => $country)
        {
            $country_options[] = ['value' => $key,'label' => $country];
        }
        $select_contry = array(
            'code' => 'country',
            'type' => 'select',
            'label' =>  __('Country','openpos'),
            'options' => $country_options,
            'placeholder' => __('Choose Country','openpos'),
            'description' => '',
            'default' => $store_country,
            'allow_shipping' => 'yes',
            'onchange_load' => true
        );

        $fields = array(
                $address_field,
                $address_2_field,
                $city_field,
                $postcode_field,
                $state_field,
                $select_contry
        );

        return apply_filters( 'op_customer_addition_fields',$fields );
    }
    public function woocommerce_email_recipient_customer_completed_order($recipient,$_order){
        global $_g_send_to;
        
        if($_order)
        {
            $order_id = $_order->get_id();
            if($order_id)
            {
                $is_pos = $_order->get_meta('_op_order_source');// get_post_meta($order_id,'_op_order_source',true);
                if($is_pos == 'openpos')
                {
                    $_op_receipient = '';
                    $_op_email_receipt = $_order->get_meta('_op_email_receipt'); //get_post_meta($order_id,'_op_email_receipt',true);
                    $op_send_email_receipt = apply_filters('op_send_email_receipt',$_op_email_receipt,$_order,$recipient);
                    $allow_send_woo_email = apply_filters('op_allow_send_woo_email',false);
                    
                    $is_manual = isset($_REQUEST['wc_order_action']) && $_REQUEST['wc_order_action'] == 'send_order_details' ? true : false;
                    if( $is_manual || $op_send_email_receipt || $allow_send_woo_email)
                    {
                        $_op_receipient = $recipient ;
                    }
                    if($_g_send_to)
                    {
                        $_op_receipient = $_g_send_to;
                    }
                    return $_op_receipient;
                }
            }
        }
        return $recipient;
    }
    public function formatCustomer($customer_id){

        $customer = new WC_Customer( $customer_id);
        $name = $customer->get_first_name().' '.$customer->get_last_name();
        $email = $customer->get_email();
        $phone = $customer->get_billing_phone();
        $billing_address = $customer->get_billing();
        if(strlen($name) == 1)
        {
            $name = $customer->get_billing_first_name().' '.$customer->get_billing_last_name();
        }
        $name = trim($name);

        if(strlen($name) < 1 && $phone){
            $name = $customer->get_billing_phone();
        }
        if(strlen($name) < 1)
        {
            $name = $email;
        }

        $customer_data = array(
            'id' => $customer_id,
            'avatar' => $customer->get_avatar_url(),
            'name' => $name,
            'firstname' => $customer->get_first_name() != 'null' ? $customer->get_first_name() : '',
            'lastname' => $customer->get_last_name()  != 'null' ? $customer->get_last_name() : '',
            'address' => $customer->get_billing_address_1() != 'null' ? $customer->get_billing_address_1() : '',
            'address_2' => $customer->get_billing_address_2() != 'null' ? $customer->get_billing_address_2() : '',
            'state' => $customer->get_billing_state()  != 'null' ? $customer->get_billing_state() : '',
            'city' => $customer->get_billing_city()  != 'null' ? $customer->get_billing_city() : '',
            'country' => $customer->get_billing_country()  != 'null' ? $customer->get_billing_country() : '',
            'postcode' => $customer->get_billing_postcode()  != 'null' ? $customer->get_billing_postcode() : '',
            'phone' => $customer->get_billing_phone()  != 'null' ? $customer->get_billing_phone() : '',
            'email' => $email,
            'billing_address' => $billing_address,
            'point' => 0,
            'discount' => 0,
            'badge' => '',
            'summary_html' => '',
            'auto_add' => 'no',
            'shipping_address' => $this->getCustomerShippingAddress($customer_id)
        );

        $final_result = apply_filters('op_customer_data',$customer_data);
        return $final_result;
    }

    // format order to work with POS
    public function formatWooOrder($order_id){
        global $op_register;

        $order_key_format = array(
            'tax_amount',
            'sub_total',
            'sub_total_incl_tax',
            'shipping_cost',
            'shipping_tax',
            'refund_total',
            'grand_total',
            'final_items_discount_amount',
            'final_discount_amount',
            'discount_tax_amount',
            'discount_excl_tax',
            'discount_code_amount',
            'discount_code_tax_amount',
            'discount_code_excl_tax',
            'discount_amount',
            'discount_final_amount',
            'custom_tax_rate',
            'customer_total_paid',
            'remain_paid',
            'total_fee'
        );
        $tip = null;
        $pos_order = false;
        if ( is_a( $order_id, 'WC_Order' ) ) 
        {
            $order = $order_id;
            $order_id = $order->get_id();
        }else{
            $order = wc_get_order($order_id);
        }
        
        if(!$order || !is_a( $order, 'WC_Order' ))
        {
            return array();
        }
        if($this->_enable_hpos)
        {
            $_pos_order_id = $order->get_meta('_pos_order_id');
        }else{
            $_pos_order_id = get_post_meta($order_id,'_pos_order_id',true);
        }
        $grand_total = $order->get_total('ar');

        $billing_address = $order->get_address( 'billing' );
        $customer_id  = $order->get_customer_id();
        
        $customer_data = array(
                'id' => $customer_id,
                'group_id' => 0,
                'name' => implode(' ',array($billing_address['first_name'],$billing_address['last_name'])),
                'address' => $billing_address['address_1'],
                'firstname' => $billing_address['first_name'],
                'lastname' => $billing_address['last_name'],
                'email' => $billing_address['email'],
                'phone' => $billing_address['phone'],
                'point' => 0,
                'discount' => 0,
                'badge' => '',
                'summary_html' => '',
        );
        if($customer_data['id'] > 0)
        {
            $customer = new WC_Customer( $customer_data['id']);
            if($customer)
            {
                $customer_data = $this->formatCustomer($customer_data['id']);
            }
        }

        //$customer_base_data = 

        $customer_data = array_merge($customer_data,$billing_address);
       
        $item_ids = $order->get_items();
        $order_status = $order->get_status();
        $payment_status = $order_status;
        if($order_status == 'processing' || $order_status == 'completed')
        {
            $payment_status = 'paid';
        }

        $items = array();
        $qty_allow_refund = true; // more logic
        $cart_discount_item = array();
        $cart_tax_details = array();
        $final_items_discount_amount = 0;
        $refund_total = 0;
        $refund_total = 0;
        $total_qty = 0;
        $has_changed = false;
        $valid_items = array();
        foreach($item_ids as $item_id => $item)
        {
            
            $item = $this->formatOrderItem($order,$item);
            if(!$item || empty($item))
            {
                continue;
            }
            $total_qty += $item['qty'];
            if($item && $item['item_type'] != 'cart_discount')
            {
                $items[] = $item;
                $_op_local_id = wc_get_order_item_meta($item_id, '_op_local_id',true);
                if($_op_local_id)
                {
                    $valid_items[$_op_local_id] = 1 * $item['qty'];
                }else{
                    $has_changed = true;
                }
                if(isset($item['final_discount_amount']))
                {
                    $final_items_discount_amount += $item['final_discount_amount'];
                }
            }
            if($item && $item['item_type'] == 'cart_discount')
            {
                $cart_discount_item  = $item;
            }
            $item_tax_details = isset($item['tax_details']) ? $item['tax_details'] : array();
            foreach($item_tax_details as $_t)
            {
                $t_code = $_t['code'];
                $t_total = $_t['total'];
                if(isset($cart_tax_details[$t_code]))
                {
                    $cart_tax_details[$t_code]['total'] += $t_total;
                }else{
                    $cart_tax_details[$t_code] = $_t;
                }
            }
            
            $refund_total += isset($item['refund_total']) ? (1 * $item['refund_total']) : 0;
        }
        
        $user_id = $order->get_meta('_op_sale_by_person_id');
        $sale_person_name = '';
        $sale_person = 0;
        $register = array('id'=> 0, 'name' => 'online','outlet' => 0 );
        if($user_id)
        {
            $userdata = get_user_by('id', $user_id);
            if($userdata)
            {
                $sale_person_name = $userdata->data->display_name;
                $sale_person = $user_id;
            }

        }
        if(!$sale_person_name && !$_pos_order_id)
        {
            $sale_person_name = __('Done via website','openpos');
        }else{
            
            if($this->_enable_hpos)
            {
                $pos_order = $order->get_meta('_op_order');
            }else{
                $pos_order = get_post_meta($order_id,'_op_order',true);
            }
            if($pos_order && !empty($pos_order))
            {
                if(isset($pos_order['register']))
                {
                    $register = $pos_order['register'];
                }
                
            }
        }
        $sub_total = $order->get_subtotal();
        
        

        
        $shipping_cost_excl_tax = (float)$order->get_shipping_total();
        $shipping_tax = (float)$order->get_shipping_tax();
        $shipping_cost = $shipping_cost_excl_tax + $shipping_tax;

        $final_discount_amount = 0;
        $tax_totals = $order->get_tax_totals();
        $tax_amount = 0;
        foreach($tax_totals as $tax)
        {
            $tax_amount += $tax->amount;
        }
        $allow_pickup = $this->allowPickup($order_id);
        $allow_refund = $this->allowRefundOrder($order_id);
        if($grand_total <= 0)
        {
            $allow_refund = false;
        }
        $payments = array();
        if($_pos_order_id)
        {
           
            $payments = $order->get_meta('_op_payment_methods');
            
        }else{
            $method_title = $order->get_payment_method_title();
            $method_paid = $order->is_paid() ? $grand_total : 0;

            $payments[] = array(
                'name' => $method_title,
                'paid' => $method_paid,
                'return' => 0,
                'ref' => '',
            );
        }
        
        if($allow_refund && !$qty_allow_refund)
        {
            $allow_refund = false;
        }
        $order_status = $order->get_status();
        $setting_pos_continue_checkout_order_status = $this->settings_api->get_option('pos_continue_checkout_order_status','openpos_general');
        
        $allow_checkout = false;
        $allow_close = false;
        if($setting_pos_continue_checkout_order_status && is_array($setting_pos_continue_checkout_order_status)){
            foreach($setting_pos_continue_checkout_order_status as $setting_status)
            {
                $new_status = 'wc-' === substr( $setting_status, 0, 3 ) ? substr( $setting_status, 3 ) : $setting_status;
                if($new_status == $order_status)
                {
                    $allow_checkout = true;
                    $allow_close = true;
                }
            }
        }
        if($payment_status != 'paid')
        {
            $allow_refund = false;
            $allow_pickup = false;
        }
        $continue_pay_url = $order->get_checkout_payment_url(false);

        $order_number = $order_id;
        if($this->_enable_hpos)
        {
           
            if($tmp = $order->get_meta('_op_wc_custom_order_number'))
            {
                if((int)$tmp)
                {
                    $order_number = (int)$tmp;
                }
            }
        }else{
            if($tmp = get_post_meta( $order_id, '_op_wc_custom_order_number', true ))
            {
                if((int)$tmp)
                {
                    $order_number = (int)$tmp;
                }
            }
        }
        
        $discount_amount = 0;
        $discount_tax_amount = 0;
        $discount_excl_tax = 0;
        $discount_tax_details = array();
        
        if(empty($cart_discount_item))
        {
            $fees = $order->get_fees();
            foreach($fees as $fee_id => $fee)
            {
                $_pos_item_type = $fee->get_meta('_pos_item_type',true);
                if($_pos_item_type  == 'cart_discount')
                {
                    $cart_discount_item = $this->formatOrderFeeItem($order,$fee_id);
                   
                    
                }
                
            }
        }
       


        if(!empty($cart_discount_item))
        {
            if(isset($pos_order['discount_final_amount']))
			{
				$discount_amount = $pos_order['discount_final_amount'];
				$discount_tax_amount = $pos_order['discount_tax_amount'];
				$discount_excl_tax = $pos_order['discount_excl_tax'];
				$discount_tax_details = $pos_order['discount_tax_details'];
				$final_discount_amount += $pos_order['final_discount_amount'];
			}else{
                $discount_amount += $cart_discount_item['total_incl_tax'];
                $discount_tax_amount += $cart_discount_item['total_tax'];
                $discount_excl_tax += $cart_discount_item['total'];
                $final_discount_amount += $cart_discount_item['total_incl_tax'];
                $discount_tax_details = $cart_discount_item['tax_details'];
            }
            //
        }
        //coupon
        $discount_code_amount = 0;
        $discount_code_tax_amount = 0;
        $discount_code_excl_tax = 0;
        $discount_codes = array();
        $discount_codes_details = array();
        $coupons = apply_filters('op_order_coupons',$order->get_coupons(),$order);
        foreach($coupons as $c)
        {
            $coupon_data = $c->get_data();
            
            if(isset($coupon_data['discount']) && $coupon_data['code'])
            {
                $discount_codes[] = $coupon_data['code'];
                $discount_code_tax_amount += $coupon_data['discount_tax'];
                $discount_code_amount += 1 * $coupon_data['discount'];
                $discount_code_excl_tax += 1 * ($coupon_data['discount'] - $coupon_data['discount_tax']);

                $final_discount_amount += 1 * $coupon_data['discount'];
                $discount_codes_details[$coupon_data['code']] = array(
                    'applied_items' => array(),
                    'code' => $coupon_data['code'],
                    'conditions' => '',
                    'description' => '',
                    'discount_amount' => '',
                    'discount_amount_type' => '',
                    'discount_type' => '',
                    'tax' => $coupon_data['discount_tax'],
                    'taxes' => array(),
                    'total' =>  1 * $coupon_data['discount'],
                );
            }
        }
        

        $source = '#'.$order->get_order_number();
        $source_type = 'online';
        if($this->_enable_hpos)
        {
            $tmp_source_type = $order->get_meta('_op_order_source');
        }else{
            $tmp_source_type = get_post_meta($order_id,'_op_order_source',true);
        }
        

        $note = $order->get_customer_note();
        
        $shipping_information = array();
        $addition_information = array();
        
        
        $add_shipping = false;
        $cart_rule_discount = array();
        $discount_source = '';
        
        if($tmp_source_type == 'openpos')
        {
            $source_type = 'openpos';
            $cashdrawer_meta_key = $op_register->get_order_meta_key();
            if($this->_enable_hpos)
            {
                $tmp_source = $order->get_meta($cashdrawer_meta_key);
            }else{
                $tmp_source = get_post_meta($order_id,$cashdrawer_meta_key,true);
            }
            
            $cashdrawer = $op_register->get($tmp_source);
            if($cashdrawer && !empty($cashdrawer))
            {
                $source = $cashdrawer['name'];
            }
            if($pos_order && !empty($pos_order))
            {
                if(isset($pos_order['source']))
                {
                    $source = $pos_order['source'];
                }
                if(isset($pos_order['source_type']))
                {
                    $source_type = $pos_order['source_type'];
                }
                if(isset($pos_order['addition_information']))
                {
                    $addition_information = $pos_order['addition_information'];
                }
                $pos_items = isset($pos_order['items']) ? $pos_order['items'] : array();
                if(!$has_changed)
                {
                    foreach($pos_items as $pos_item)
                    {
                        $pos_item_id = $pos_item['id'];
                        if(!isset($valid_items[$pos_item_id]) || $valid_items[$pos_item_id] != $pos_item['qty'])
                        {
                            $has_changed = true;
                        }
                        
                    }
                }
                
            }
        }
        
        $created_at = date('Y-m-d H:i:s');
        $created_at_time = time();
        $date_created_at = $order->get_date_created();
        if($date_created_at != null)
        {
            $order_datetime = $order->get_date_created();

            $created_at = wc_format_datetime($order_datetime);
            $created_at_time = strtotime($order_datetime) * 1000;
        }
        $allow_laybuy = 'no';
        $point_discount = array();
        $pickup_time = '';
        
        if($pos_order && is_array($pos_order) && isset($pos_order['created_at']))
        {
            $created_at = $pos_order['created_at'];
            $created_at_time = $pos_order['created_at_time'];
            $shipping_information = $pos_order['shipping_information'];
            $add_shipping = isset($pos_order['add_shipping']) ? $pos_order['add_shipping'] : false;
            $allow_laybuy = isset($pos_order['allow_laybuy']) ? $pos_order['allow_laybuy'] : 'no';
            $point_discount  = isset($pos_order['point_discount']) ? $pos_order['point_discount'] : array();
            $tip  = isset($pos_order['tip']) ? $pos_order['tip'] : null;
            $pickup_time  = isset($pos_order['pickup_time']) ? $pos_order['pickup_time'] : '';
            $cart_rule_discount = isset($pos_order['cart_rule_discount']) ? $pos_order['cart_rule_discount'] : array();
            $discount_source = isset($pos_order['discount_source']) ? $pos_order['discount_source'] : '';;
        }else{
            $shipping_method = $order->get_shipping_method();
            if( $shipping_method)
            {
                $add_shipping = true;
                $shipping_information = array(
                    'name' =>  $order->get_shipping_first_name().' '.$order->get_shipping_last_name(),
                    'email' =>   $order->get_billing_email(),
                    'address' =>   $order->get_shipping_address_1(),
                    'phone' =>   $order->get_billing_phone(),
                    'note' =>  '',
                    'shipping_method_details' =>  array('title' => $shipping_method,'label' => $shipping_method,'code'=> esc_attr($shipping_method)),//{title: "Store Pickup", label: "Store Pickup", code: "store_pickup", cost_type: "local", cost: 0, …},
                    'tax_details' =>  array(),
                    'address_2' =>   $order->get_shipping_address_2(),
                    'city' =>   $order->get_shipping_city(),
                    'postcode' =>  $order->get_shipping_postcode(),
                    'state' =>  $order->get_shipping_state(),
                    'country' =>  $order->get_shipping_country()
                );
            }
            
            
            
        }
        $status = $order->get_status();
        $status_label = wc_get_order_status_name($status );
        if($this->_enable_hpos)
        {
            $total_paid = $order->get_meta('_op_order_total_paid');
        }else{
            $total_paid = get_post_meta($order_id,'_op_order_total_paid',true);
        }
        

        if($has_changed)
        {
            $total_paid = 0;
        }

        if(!$total_paid)
        {
            $total_paid = $grand_total;
        }
        $customer_total_paid = 0;

        $payments = array();
        if($allow_laybuy == 'yes')
        {
            //$op_order_data = $order->get_meta('_op_order',true);
           
            $transactions = $this->getOrderTransactions($order->get_id(),array( 'order'));
            
            foreach($transactions as $t)
            {
                $transaction_source_type = isset($t['source_type']) ? $t['source_type'] : '';
                $customer_total_paid += 1  * $t['in_amount'];
                $customer_total_paid -= 1 * $t['out_amount'];
                if($transaction_source_type == 'order')
                {
                    $payments[] = array(
                        'code' => $t['payment_code'],
                        'name' => $t['payment_name'],
                        'ref' => $t['payment_ref'],
                        'description' => $t['ref'],
                        'paid_currency_formatted' => $this->op_price($t['in_amount']),
                        'paid' => 1  * $t['in_amount'],
                        'return_currency_formatted' => $this->op_price($t['out_amount']),
                        'return' => 1  * $t['out_amount'],
                        'paid_point' => 0,
                        'type' => 'offline',
                        'online_type' => '',
                        'partial' => '',
                        'status_url' => '',
                        'offline_transaction' => 'yes',
                        'offline_order' => 'yes',
                        'allow_refund' => 'offline',
                        'callback_data' => array()
                    );
                }
            }
            $allow_checkout = false;
            if($customer_total_paid == 0)
            {
                $allow_close = true;
                
            }
        }else{
            
            $no_pay_status = apply_filters('op_online_order_nopaid_status',array('pending','on-hold','failed','refunded','cancelled'),$order);
            $paid_status = apply_filters('op_online_order_paid_status', array('completed'),$order);
            if(!in_array($payment_status,$no_pay_status))
            {
                $customer_total_paid = $total_paid;
            }
            if($tmp_source_type == 'openpos')
            {
                $payments = isset($pos_order['payment_method']) ? $pos_order['payment_method'] : array();
            }else{
            
                if(in_array($status,$paid_status)){
                    $method_code = $order->get_payment_method();
                    $method_title = $order->get_payment_method_title();
                    $ref =  $order->get_transaction_id();
                    
                    $payments[] = array(
                        'code' => $method_code,
                        'name' =>  $method_title,
                        'ref' => $ref,
                        'description' => '',
                        'paid_currency_formatted' => $this->op_price($grand_total),
                        'paid' => 1  * $grand_total,
                        'return_currency_formatted' => $this->op_price($grand_total),
                        'return' => 0,
                        'paid_point' => 0,
                        'type' => 'offline',
                        'online_type' => '',
                        'partial' => '',
                        'status_url' => '',
                        'offline_transaction' => 'yes',
                        'offline_order' => 'yes',
                        'allow_refund' => 'offline',
                        'callback_data' => array()
                    );
                }
            }
            
        }
        $allow_exchange = 'no';
        $allow_exchange_setting = $this->settings_api->get_option('allow_exchange','openpos_general');
        if($status =='completed')
        {
            if($allow_exchange_setting == 'alway')
            {
                $allow_exchange = 'yes';
            }
        }
        if($status =='refunded'){
            $allow_exchange = 'no';
            $allow_refund = false;
            $allow_pickup = false;
            $allow_checkout = false;
            $allow_close = false;
          
        }
        $total_fee = 0;
        $fee_items = array();
        $order_fee_items = $order->get_items('fee');
        foreach($order_fee_items as $fee_item_id => $order_fee_item)
        {
            $_op_fee_type = $order_fee_item->get_meta('_pos_item_type',true);
            if($_op_fee_type  == 'cart_discount')
            {
                continue;
            }
            if($_op_fee_type == 'cart_fee')
            {

                $fee_item = $order_fee_item->get_meta('_op_item_data_details',true);
                if($fee_item)
                {
                    
                    $fee_item['total_currency_formatted'] = $this->op_price($fee_item['total']);
                    $refunded = 1 * $order->get_total_refunded_for_item( $fee_item_id , 'fee' );
                    $fee_item['refund_total'] = $refunded;
                    $fee_item['refund_total_incl_tax'] = $refunded;

                    $total_fee += $fee_item['total'] - $refunded;

                    $fee_items[] = $fee_item;
                }
            }else{
                // default fee
                $fee_item = $order_fee_item;
                $line_total  = $order->get_line_total( $fee_item );
                $line_tax  = $order->get_line_tax( $fee_item );
                $fee_items[] = array(
                    'id' => $fee_item_id,
                    'name' => $fee_item['name'],
                    'amount_type' => 'fixed',
                    'amount' => $line_total,
                    'tax' => $line_tax,
                    'tax_details' => array(),//$fee_line['taxes'],
                    'total' => $line_total,
                    'total_currency_formatted' => $this->op_price($line_total),
                    'total_incl_tax' => ( $line_total + $line_tax ) ,
                    'source' => '',
                    'allow_refund' => false,
                    'refund_total' => 0,
                    'refund_total_incl_tax' => 0,
                );
                //end
                $total_fee += $line_total;
            }

        }
        
        $cart_discount_amount = (float)$discount_amount;
        $cart_discount_type = 'fixed';
        $cart_discount_final_amount = (float)$discount_amount;
        
        $refunds = array();
        $order_refunds= $order->get_refunds();
        if($order_refunds && is_array($order_refunds))
        {
            foreach($order_refunds as $refund)
            {
                $refund_id = $refund->get_id();
                $refund_amount = $refund->get_total();
                $refund_reason = $refund->get_reason();
                $refund_date = $refund->get_date_created();
                $refund_items = $refund->get_items();
                
                if($refund_date != null)
                {
                    $created_at = wc_format_datetime($refund_date);
                    $created_at_time = strtotime($refund_date) * 1000;
                }
                $_refund_items = array();
                $order_refund_total = 0;
                $order_refund_total_tax = 0;
                foreach($refund_items as $_item)
                {
                    if ( ! $_item->is_type( 'line_item' ) ) {
                        continue;
                    }
                    $refund_qty = (0 - $_item->get_quantity());
                    $_refund_total_tax = (0 - $_item->get_total_tax());
                    $_refund_total = (0 - $_item->get_total());
                    $order_refund_total += $_refund_total;
                    $order_refund_total_tax += $_refund_total_tax;
                    $_refund_items[] = array(
                        'id' => $_item->get_id(),
                        'tax_details' => array(),//$fee_line['taxes'],
                        'allow_partial' => true,
                        'item' => array(
                            'name' => $_item->get_name(),
                            'id' =>  $_item->get_id(),
                        ),
                        'item_type' => '',
                        'price' => $refund_qty != 0 ? ($_refund_total /$refund_qty) : $_refund_total ,
                        'qty' => $refund_qty,
                        'refund_total' => $_refund_total,
                        'refund_total_incl_tax' => $_refund_total + $_refund_total_tax,
                        'tax' =>  $refund_qty != 0 ? ($_refund_total_tax /$refund_qty) : 0 ,
                        'tax_total' => $_refund_total_tax,

                    );
                }
                $refund_number = $refund->get_meta('_op_local_id',true) ? $refund->get_meta('_op_local_id',true) : $refund->get_id();
                $refunds[] = array(
                    'id' => $refund_id,
                    'reason' => $refund_reason,
                    'created_at' => $created_at,
                    'created_at_time' => $created_at_time,
                    'created_by_id' => 0,
                    'refund_number' => $refund_number ,
                    'refund_number_format' => $refund_number,
                    'refund_point' => 0,
                    'refund_total' =>  ($order_refund_total + $order_refund_total_tax),
                    'refund_total_excl_tax' => $order_refund_total ,
                    'refund_total_tax' => $order_refund_total_tax,
                    'restock' => '',
                    'session' => '',
                    'source' => $order_number,
                    'items' => $_refund_items
                );
            }
        }
        $callback_data = array();
        $order_number_format = $order->get_order_number();
        if($tmp_order_number_format = $order->get_meta('_op_order_number_format'))
        {
            $order_number_format = $tmp_order_number_format;
        }
        if($tmp_order_number = $order->get_meta('_op_order_number'))
        {
            $order_number = $tmp_order_number;
        }
        $result = array(
            'id' => $order_id,
            'order_id' => $order_id,
            'system_order_id' => $order_id,
            'pos_order_id' => $_pos_order_id,
            'order_number' => $order_number,
            'order_number_format' => $order_number_format,
            'order_number_details' => array('order_id'=> $order_id, 'order_number' => $order_number,'order_number_format' => $order_number_format ),
            'register' => $register,
            'title' => '',
            'addition_information' => $addition_information,
            'items' => $items,
            'fee_items' => $fee_items,
            'customer' => $customer_data,
            'sub_total' => $sub_total, //excl tax
            'sub_total_incl_tax' => ($sub_total + $tax_amount), // incl tax
            'tax_amount' => $tax_amount,
            'discount_amount' => $cart_discount_amount,
            'discount_type' => $cart_discount_type,
            'discount_final_amount' => $cart_discount_final_amount,
            'final_items_discount_amount' => $final_items_discount_amount,
            'final_discount_amount' => (float)$final_discount_amount,
            'discount_tax_amount' => $discount_tax_amount,
            'discount_excl_tax' => $discount_excl_tax,
            'grand_total' => 1 * $grand_total,
            'total_paid' => 1 * $total_paid,
            'total_fee' => 1 * $total_fee,
            'customer_total_paid' => 1 * $customer_total_paid,
            'discount_code' => implode(',',$discount_codes),
            'discount_codes' => $discount_codes_details,
            'discount_code_amount' => $discount_code_amount , //incl tax
            'discount_code_tax_amount' => $discount_code_tax_amount,
            'discount_code_excl_tax' => $discount_code_excl_tax,
            'refunds' => $refunds,
            'refund_total' => $refund_total,
            'payment_method' => $payments, //ref , paid , return
            'shipping_cost' => $shipping_cost,
            'shipping_cost_excl_tax' => $shipping_cost_excl_tax,
            'shipping_tax' => $shipping_tax,
            'shipping_rate_id' => '',
            'shipping_information' => $shipping_information,
            'sale_person' => $sale_person,
            'sale_person_name' => $sale_person_name,
            'note' => $note,
            'created_at' => $created_at,
            'created_at_time' => $created_at_time,
            'state' => ($payment_status == 'paid') ? 'completed' : 'pending_payment',
            'online_payment' => false,
            'print_invoice' => true,
            'point_discount' => $point_discount,
            'add_discount' => ($final_discount_amount > 0),
            'add_shipping' => $add_shipping,
            'add_tax' => true,
            'custom_tax_rate' => '',
            'custom_tax_rates' => array(),
            'tax_details' => array_values($cart_tax_details),
            'discount_tax_details' => $discount_tax_details,
            'source_type' => $source_type,
            'source' => $source,
            'available_shipping_methods' => array(),
            'mode' => '',
            'is_takeaway' => false,
            'checkout_url' => $continue_pay_url,
            'payment_status' => $payment_status,
            'status' =>  $status,
            'status_label' => $status_label,
            'allow_refund' => $allow_refund,
            'allow_pickup' => $allow_pickup,
            'allow_checkout' => $allow_checkout,
            'allow_close' => $allow_close,
            'allow_laybuy' => $allow_laybuy,
            'allow_exchange' => $allow_exchange,
            'remain_paid' => ($status == 'pending') ? (1*$total_paid - 1*$customer_total_paid) : 0,
            'tip' => $tip,
            'pickup_time' => $pickup_time,
            'total_qty' => $total_qty,
            'cart_rule_discount' => $cart_rule_discount,
            'discount_source' => $discount_source,
            'callback_data' => $callback_data,
            //'extra_html' => '<a href="#">button1<a><a href="#">button2<a>'
        );

        if($pos_order)
        {
            if(isset($pos_order['gift_receipt']))
            {
                $result['gift_receipt'] = $pos_order['gift_receipt'];
            }
            if(isset($pos_order['add_shipping']))
            {
                $result['add_shipping'] = $pos_order['add_shipping'];
            }
        }
        $result = $this->formatCurrencyOrder($result);
        
        
        return apply_filters('op_get_online_order_data',$result,$order);
    }
    public function formatCurrencyOrder($order){
        $order_key_format = array(
            'tax_amount',
            'sub_total',
            'sub_total_incl_tax',
            'shipping_cost',
            'shipping_tax',
            'refund_total',
            'grand_total',
            'final_items_discount_amount',
            'final_discount_amount',
            'discount_tax_amount',
            'discount_excl_tax',
            'discount_code_amount',
            'discount_code_tax_amount',
            'discount_code_excl_tax',
            'discount_amount',
            'discount_final_amount',
            'custom_tax_rate',
            'customer_total_paid',
            'remain_paid',
            'total_fee'
        );
        foreach($order as $result_key => $result_value)
        {
            $new_key = $result_key.'_currency_formatted';
            if(in_array($result_key,$order_key_format) && !isset($order[$new_key]))
            {
                
                $order[$new_key] = $this->stripePriceTag($result_value,wc_price($result_value));
            }
        }
        return $order;

    }

    public function formatOrderFeeItem($order,$item_id)
    {
        $item = $order->get_item($item_id);
        $order_item_key_format = array(
            'total_tax',
            'total',
            'total_incl_tax',
            'tax_amount',
            'refund_total',
            'refund_total_incl_tax',
            'price',
            'price_incl_tax',
            'final_price',
            'final_price_incl_tax',
            'final_discount_amount',
            'discount_amount'
        );
        $items_data = $item->get_data();
        $tax_details_data = array();
        if ( wc_tax_enabled() ) {
            $order_taxes      = $order->get_taxes();

            
            foreach($order_taxes as $otax)
            {
                $o_tax_data = $otax->get_data();
                $tmp_tax = array(
                    'code' => $o_tax_data['rate_code'],
                    'compound' => $o_tax_data['compound'],
                    'label' => $o_tax_data['label'],
                    'rate' => $o_tax_data['rate_percent'],
                    'rate_id' => $o_tax_data['rate_id'],
                    'shipping' => false,
                    'total' => 0
                );
                $tax_details_data[] = $tmp_tax;
            }

        }

        $tax_details = array();
        $item_tax_data = $items_data['taxes'];
        foreach($item_tax_data['total'] as $id => $value)
        {
            foreach($tax_details_data as $t_value)
            {
                if($t_value['rate_id'] == $id)
                {
                    $tmp = $t_value;
                    if(!$value)
                    {
                        $value = 0;
                    }
                    if($value < 0 )
                    {
                        $value = 0 -  $value;
                    }
                    $tmp['total'] = 1 * $value;
                    
                    $tax_details[] = $tmp;
                }
            }
        }
        $total_tax = $items_data['total_tax'] > 0 ? $items_data['total_tax'] : -1 * $items_data['total_tax'];
        $total = $items_data['total'] > 0 ? $items_data['total'] : -1 * $items_data['total'];
        $total_incl_tax = ($items_data['total'] + $items_data['total_tax']) > 0 ? ($items_data['total'] + $items_data['total_tax']) :  -1* ($items_data['total'] + $items_data['total_tax']);

        $item_formatted_data = array(
            'total_tax'=> $total_tax,
            'total'=>  $total,
            'total_incl_tax'=>  $total_incl_tax,
            'tax_details'=> $tax_details,
        );
        

        foreach($item_formatted_data as $result_key => $result_value)
        {
            if(in_array($result_key,$order_item_key_format))
            {
                $new_key = $result_key.'_currency_formatted';
                $item_formatted_data[$new_key] = $this->stripePriceTag($result_value,wc_price($result_value));
            }
        }
        return apply_filters('op_get_online_order_fee_item_data',$item_formatted_data,$order);
    }

    public function formatOrderItem($order,$item){


        $order_item_key_format = array(
            'total_tax',
            'total',
            'total_incl_tax',
            'tax_amount',
            'refund_total',
            'price',
            'price_incl_tax',
            'final_price',
            'final_price_incl_tax',
            'final_discount_amount',
            'discount_amount'
        );
        

        $item_id = $item->get_id();

      
        $pos_item = $item->get_meta('_op_item_data_details',true);

        $order_created_date = $order->get_date_created();
    
        $items_data = $item->get_data();
        
        $product_data = array();
        $product_id = isset($items_data['product_id']) ? $items_data['product_id'] : 0;
        $variation_id = isset($items_data['variation_id']) ? $items_data['variation_id'] : 0;
        if($variation_id > 0)
        {
            $product_id = $variation_id;
        }
        $_product = get_post($product_id);
        if($_product)
        {
            
            $product_data =  $this->get_product_formatted_data($_product,0);
        }
        
        $refund_qty = $order->get_qty_refunded_for_item( $items_data['id'] );
        if($refund_qty < 0)
        {
            $refund_qty = 0 - $refund_qty;
        }

        $refund_total = $order->get_total_refunded_for_item($items_data['id']);
        $refund_tax = 0;
        $taxes  = $item->get_taxes();
        
        if(isset($taxes['total']))
        {
            foreach($taxes['total'] as $tax_id => $tax_value)
            {
                $_rate_id = $tax_id;
                $_refund_tax = $order->get_tax_refunded_for_item($items_data['id'],$_rate_id);
                $refund_tax += $_refund_tax;
            }
        }
        if($refund_tax < 0)
        {
            $refund_tax = 0 - $refund_tax;
        }
        $refund_total  += $refund_tax;
        

        

        $items_data['options'] = array();
        //print_r($items_data);
        $subtotal = $items_data['subtotal'];
        $total = $items_data['total'];


        $total_tax = $items_data['total_tax'];

        
        $subtotal_tax = $items_data['subtotal_tax'];
        $tax_details_data = array();
        if ( wc_tax_enabled() ) {
            $order_taxes      = $order->get_taxes();

            
            foreach($order_taxes as $otax)
            {
                $o_tax_data = $otax->get_data();
                $tmp_tax = array(
                    'code' => $o_tax_data['rate_code'],
                    'compound' => $o_tax_data['compound'],
                    'label' => $o_tax_data['label'],
                    'rate' => $o_tax_data['rate_percent'],
                    'rate_id' => $o_tax_data['rate_id'],
                    'shipping' => false,
                    'total' => 0
                );
                $tax_details_data[] = $tmp_tax;
            }

        }
        
        
        $discount = ($subtotal   - $total) > 0 ? ($subtotal   - $total) : 0;
        $discount_tax = 0;
        if($discount)
        {
            $discount_tax = $items_data['subtotal_tax'] - $items_data['total_tax'];
        }
        

        $item_price = $items_data['quantity'] > 0 ? ($subtotal / $items_data['quantity']) : $subtotal;

        $item_tax_amount = ($total_tax != 0 && $items_data['quantity'] > 0 ) ? ($total_tax / $items_data['quantity']) : 0 ;

        $item_subtax_amount = ($subtotal_tax != 0 && $items_data['quantity'] > 0 ) ? ($subtotal_tax / $items_data['quantity']) : 0 ;

        $item_price = $this->op_price_number($item_price);
        
        //start meta
        $sub_name = '';

        $hidden_order_itemmeta = apply_filters(
            'woocommerce_hidden_order_itemmeta', array(
                '_qty',
                '_tax_class',
                '_product_id',
                '_variation_id',
                '_line_subtotal',
                '_line_subtotal_tax',
                '_line_total',
                '_line_tax',
                'method_id',
                'cost',
                '_reduced_stock',
                '_op_cost_price',
                'op_item_details',
                '_op_item_data_details',
                '_restock_refunded_items'
            )
        );
        if(!$hidden_order_itemmeta)
        {
            $hidden_order_itemmeta = array();
        }
        $is_cart_discount = false;
        if ( $meta_data = $item->get_formatted_meta_data( '' ) ){
            foreach ( $meta_data as $meta_id => $meta ){
                if ( in_array( $meta->key, $hidden_order_itemmeta, true ) ) {
                    continue;
                }
                if($meta->key == '_pos_item_type' && $meta->value == 'cart_discount' )
                {
                    $is_cart_discount = true;
                }
                $tmp_sub = wp_kses_post( $meta->display_key ).': '.wp_kses_post( force_balance_tags( $meta->display_value ) );
                $sub_name .= '<li id="'.esc_attr($meta->key).'">'.$tmp_sub.'</li>';
            }
        }
        
        if($sub_name )
        {
            $sub_name = '<ul class="item-options-label">'.$sub_name.'</ul>';
        }

        $tax_details = array();
        $item_tax_data = $items_data['taxes'];
        foreach($item_tax_data['total'] as $id => $value)
        {
            foreach($tax_details_data as $t_value)
            {
                if($t_value['rate_id'] == $id)
                {
                    $tmp = $t_value;
                    if(!$value)
                    {
                        $value = 0;
                    }
                    $tmp['total'] = 1 * $value;
                    if($is_cart_discount)
                    {
                        $tmp['total'] = -1 * $value;
                    }
                    $tax_details[] = $tmp;
                }
            }
        }
        $item_type = '';
        if($is_cart_discount)
        {
            $item_type = 'cart_discount';
            $item_price *= -1 ; 
            $item_tax_amount *= -1 ; 
            $item_subtax_amount *= -1 ; 
            $discount *= -1 ; 
            $discount_tax *= -1 ; 
            $total_tax *= -1 ; 
            $items_data['total'] *= -1 ; 
            $items_data['total_tax'] *= -1 ; 
        }
        //end meta
        $barcode = '';
        if(isset($product_data['barcode']))
        {
            $barcode = $product_data['barcode'];
        }
        if($order_created_date == null)
        {
            $created_time = get_post_time('U', false, $order->get_id(), true );
        }else{
            $created_time = $order_created_date->getTimestamp();
        }
        $created_time =  $created_time  * 1000;
        $options = array();
        $bundles = array();
        $variations = array();
        if( $_options = $item->get_meta('_op_item_options',true)){
            $options = $_options;
        }
        if( $_bundles = $item->get_meta('_op_item_bundles',true)){
            $bundles = $_options;
        }
        $product_price = $this->op_price_number($item_price);
        $product_data_price = $product_price;
        $product_price_incl_tax = $this->op_price_number($item_price + $item_subtax_amount);
        if(isset($product_data['price']))
        {
            $product_data_price = $this->op_price_number($product_data['price']);
        }
        if($product_price != $product_data_price)
        {
            $product_data['final_price'] = $product_price;
            $product_data['price'] = $product_price;
            $product_data['price_incl_tax'] = $product_price_incl_tax;
            $product_data['tax_amount'] = $this->op_price_number($item_subtax_amount);
            
        }
        $seller_id = 0;
        if( $_seller_id = $item->get_meta('_op_seller_id',true)){
            $seller_id = $_seller_id;
        }
        
        $seller_name = '';
        if( !$seller_id)
        {
            $seller_id =  $order->get_meta('_op_sale_by_person_id');
        }
        if($seller_id)
        {
            $userdata = get_user_by('id', $seller_id);
            if($userdata)
            {
                $seller_name = $userdata->data->display_name;
            }
        }
        
       
       
        $item_formatted_data = array(
            'id' => $items_data['id'],
            'item_id' => $item_id, // woocommerce item id
            'name' => $items_data['name'],
            'barcode' => $barcode,
            'sub_name' => $sub_name,
            'dining' => '',
            'price' =>  $product_price,
            'price_incl_tax' =>  $product_price_incl_tax, //
            'product_id' =>  $product_id,
            'final_price' =>  $item_price,
            'final_price_incl_tax' =>  $this->op_price_number($item_price + $item_subtax_amount), //
            'options' => $options,
            'bundles' =>  $bundles,
            'variations' => $variations,
            'discount_amount' =>  ($discount + $discount_tax),
            'discount_type' => 'fixed',
            'final_discount_amount' =>  $discount,
            'final_discount_amount_incl_tax' =>  ($discount + $discount_tax),
            'qty' =>  $items_data['quantity'],
            'refund_qty' =>  $refund_qty,
            'exchange_qty' =>  0,
            'tax_amount' =>  $item_subtax_amount,
            'refund_total' =>  $refund_total,
            'total_tax'=> $total_tax,
            'total'=>  $items_data['total'],
            'total_incl_tax'=>  ($items_data['total'] + $items_data['total_tax']), //
            'product'=> $product_data,
            'option_pass' =>  true,
            'option_total' =>  0,
            'bundle_total' =>  0,
            'note' => $sub_name,
            'parent_id' => 0,
            'seller_id' => $seller_id,
            'seller_name' => $seller_name,
            'discount_source' => '',
            'final_price_source' => '',
            'item_type'=> $item_type,
            'has_custom_discount'=> false,
            'disable_qty_change'=> true,
            'read_only'=> false,
            'promotion_added'=> false,
            'tax_details'=> $tax_details,
            'is_exchange'=> false,
            'update_time' => 0, 
            'order_time' => $created_time
        );
        
        
        foreach($item_formatted_data as $result_key => $result_value)
        {
            if(in_array($result_key,$order_item_key_format))
            {
                $new_key = $result_key.'_currency_formatted';
                $item_formatted_data[$new_key] = $this->stripePriceTag($result_value,wc_price($result_value));
                
            }
        }

        if($pos_item && ($refund_qty == 0) && ($pos_item['qty'] == $item['qty']))
        {
            $pos_item['item_id'] = $item_id;
            $item_formatted_data = $pos_item;
        }
        
        return apply_filters('op_get_online_order_item_data',$item_formatted_data,$order,$item);

    }


    public function allowRefundOrder($order_id){
        $allow_refund_duration = $this->settings_api->get_option('pos_allow_refund','openpos_general');
        
        if($allow_refund_duration == 'yes')
        {
            return true;
        }
        if($allow_refund_duration == 'no')
        {
            return false;
        }
        $refund_duration = $this->settings_api->get_option('pos_refund_duration','openpos_general');
        $post = get_post($order_id);
        $order = wc_get_order($order_id);
        if(!$order)
        {
            return false;
        }
        $_pos_order_id = $order->get_meta('_pos_order_id');
        if(!$_pos_order_id)
        {
            return false;
        }
        $created = date_create($post->post_date)->getTimestamp();
        $today = time();
        $diff_time = $today - $created;
        $refund_duration = (float)$refund_duration;
        return ($diff_time < (86400 * $refund_duration));
    }
    public function allowPickup($order_id){
        $order = wc_get_order($order_id);
        $status = $order->get_status();
        $allow = false;
        if($status == 'processing')
        {
            $allow =  true;
        }
        return apply_filters('op_allow_order_pickup',$allow,$order_id);
    }
    public function inclTaxMode(){
        $pos_tax_class = $this->settings_api->get_option('pos_tax_class','openpos_general');

        return ( $pos_tax_class == 'op_productax'  && 'yes' === get_option( 'woocommerce_prices_include_tax' ) )  ? 'yes' : 'no';
    }
  
    public function getCustomerUserRoles(){
        global $wp_roles;
        $all_roles = $wp_roles->roles;
        $roles =  array_keys($all_roles);
        return apply_filters('op_customer_roles',$roles);
    }

    public function add_meta_boxes(){
        global $post;
        global $theorder;
        if(!$post)
        {
            OrderUtil::init_theorder_object( $post );
        }
        if($theorder){
            $order = $theorder;
            $source =   $order->get_meta('_op_order_source');
        }else{
            $source =   get_post_meta($post->ID,'_op_order_source',true);
        }
        
        if($source == 'openpos')
        {
            $screen_id = 'shop_order';
          
            $screen_id_2 = 'woocommerce_page_wc-orders';
            
            

            add_meta_box( 'look-openpos-order-setting',__('POS Information','openpos'), array($this,'add_order_boxes'),array($screen_id,$screen_id_2) , 'side', 'default' );
            add_meta_box( 'openpos-order-transactions',__('POS Transactions','openpos'), array($this,'add_order_transactions'), array($screen_id,$screen_id_2) , 'normal', 'default' );
        }

    }
    public function add_order_boxes(){
        global $post;
        
        global $theorder;
        if(!$post)
        {
            OrderUtil::init_theorder_object( $post );
        }
        
        if($theorder)
        {
            $order = $theorder;
            
        }else{
            $order = wc_get_order($post->ID);
           
        }
        $pos_order =   $order->get_meta('_op_order');
        $_op_session_id = $order->get_meta('_op_session_id');
        ?>

        <div class="openpos-order-meta-setting">
            <?php if($pos_order):  ?>
            <div style="width: 100%; float: left;">
                <a href="<?php echo admin_url('admin-ajax.php?action=print_receipt&s='.esc_attr($_op_session_id).'&id='.(int)$order->get_id()); ?>" target="_blank" style="background: transparent;padding: 0; float: right;border: none;"><image style="width: 28px;" src="<?php echo OPENPOS_URL.'/assets/images/print.png'; ?>" /></a>
            </div>
            <?php endif; ?>
            <?php
                $this->order_pos_payment($order);
            ?>

        </div>

        <?php
    }

    public function add_order_transactions(){
        global $post;
        global $theorder;
        if(!$post)
        {
            OrderUtil::init_theorder_object( $post );
        }
        if($theorder)
        {
           
            $order = $theorder;
            
        }else{
            $order = wc_get_order($post->ID);
           
        }
        $pos_order =   $order->get_meta('_op_order');
        $order_number =   $order->get_meta('_op_wc_custom_order_number');
        
        if(!$order_number &&  $order)
        {
            $order_number = $order->get_order_number();
        }
        $transactions = $this->getOrderTransactions($order->get_id());
        if(empty($transactions))
        {
            $transactions = $this->getOrderTransactions($order_number);
        }
       
        ?>
        <table width="100%" class="order-transactions-table">
            <thead>
            <tr>
                <th><?php echo __('ID','openpos'); ?></th>
                <th><?php echo __('IN','openpos'); ?></th>
                <th><?php echo __('OUT','openpos'); ?></th>
                <th><?php echo __('AT','openpos'); ?></th>
                <th><?php echo __('BY','openpos'); ?></th>
                <th><?php echo __('Reason / Note','openpos'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($transactions as $transaction):  ?>
            <?php
                $currency = get_post_meta($transaction['sys_id'],'_currency',true);
                $currency_code = '';
                $in_amount = $transaction['in_amount'];
                $out_amount = $transaction['out_amount'];
                $in_amount_format = wc_price($in_amount);
                $out_amount_format = wc_price($out_amount);
                if($currency && isset($currency['code']))
                {
                    $currency_code = $currency['code'];
                    $in_amount_format = wc_price($in_amount,array(
                        'currency' => $currency['code'],
                        'decimal_separator' => $currency['decimal_separator'],
                        'thousand_separator' => $currency['thousand_separator'],
                        'decimals' => $currency['decimal'],
                    ));
                    $out_amount_format = wc_price($out_amount,array(
                        'currency' => $currency['code'],
                        'decimal_separator' => $currency['decimal_separator'],
                        'thousand_separator' => $currency['thousand_separator'],
                        'decimals' => $currency['decimal'],
                    ));
                }
            ?>
            <tr>
                <td>
                    <strong>#<?php echo $transaction['sys_id']; ?></strong>
                    <p><?php echo $transaction['id']; ?></p>
                </td>
                <td><?php echo $in_amount_format; ?></td>
                <td><?php echo $out_amount_format; ?></td>
                <td><?php echo $transaction['created_at']; ?></td>
                <td>
                    <?php echo $transaction['created_by']; ?>
                    <p>
                        <i><?php echo __('via','openpos'); ?></i>&nbsp;
                        <b>
                            <?php echo $transaction['payment_name']; ?>
                            <?php echo $currency_code ? '('.$currency_code.')' : ''; ?>
                            <?php echo (isset($transaction['payment_ref']) && $transaction['payment_ref'] ) ? '('.$transaction['payment_ref'].')' :''; ?>
                        </b>
                    </p>
                </td>
                <td><?php echo $transaction['ref']; ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($transactions)): ?>
            <tr>
                <td colspan="6"><?php echo __('No POS transaction found','openpos'); ?></td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    public function getOrderTransactions($order_id,$source_type = array( 'order','refund_order')){
         global $op_transaction;
         return $op_transaction->getOrderTransactions($order_id,$source_type);
    }

    public function get_countries_and_states() {
        $countries = WC()->countries->get_countries();
        if ( ! $countries ) {
            return array();
        }
        $output = array();
        foreach ( $countries as $key => $value ) {
            $states = WC()->countries->get_states( $key );

            if ( $states ) {
                foreach ( $states as $state_key => $state_value ) {
                    $output[ $key . ':' . $state_key ] = $value . ' - ' . $state_value;
                }
            } else {
                $output[ $key ] = $value;
            }
        }
        return $output;
    }

    public function getListRestaurantArea($outlet_id = 0){
        $result = array(
                'cook' => array(
                        'code' => 'cook',
                        'label' => __('Kitchen Cook', 'openpos'),
                        'description' => __('Display on Kitchen View', 'openpos'),
                        'default' => 'yes' //yes or no
                ),
                'drink' => array(
                    'code' => 'drink',
                    'label' => __('Bar Drink', 'openpos' ),
                    'description' => __('Display on Bar View', 'openpos')
                ),

        );
        return apply_filters('op_list_restaurant_area',$result);
    }

    public function product_type_options($options)
    {
        global $post;
        $openpos_type = $this->settings_api->get_option('openpos_type','openpos_pos');
        if($openpos_type == 'restaurant')
        {
            $type_options = $this->getListRestaurantArea();

            foreach($type_options as $akey => $aop)
            {
                $a_key = '_op_'.$akey;
                $default_value = isset($aop['default']) ? $aop['default'] : 'no';

                if($post && $post->ID)
                {
                    $_op_value = get_post_meta($post->ID,$a_key,true);

                    if($_op_value )
                    {
                        if( $_op_value == 'no')
                        {
                            $default_value = 'no';
                        }
                        if( $_op_value == 'yes')
                        {
                            $default_value = 'yes';
                        }
                    }

                }

                $options[$a_key] = array(
                    'id'            => $a_key,
                    'wrapper_class' => '',
                    'label'         => $aop['label'],
                    'description'   => $aop['description'],
                    'default'       => $default_value,
                );
            }
        }
        return $options;
    }
    public function woocommerce_new_product($product_id,$product){
        $openpos_type = $this->settings_api->get_option('openpos_type','openpos_pos');
        
        if($product_id)
        {
            $product_ids = array($product_id);
            $child_ids = $product->get_children();
            $product_ids = array_merge( $product_ids,$child_ids);

            //remove cache
            $this->clear_product_cache($product_ids);
            //end

            if($openpos_type == 'restaurant')
            {

                $type_options = $this->getListRestaurantArea();
                foreach($type_options as $akey => $aop) {
                    $a_key = '_op_' . $akey;
                    $cook = 'no';
                    if(isset($_REQUEST[$a_key]))
                    {
                        $op_cook = esc_attr($_REQUEST[$a_key]);
                        if($op_cook == 'on')
                        {
                            $cook = 'yes';
                        }
                    }
                    foreach($product_ids as $id)
                    {
                        update_post_meta($id,$a_key,$cook);
                    }
                    
                }

            }
            foreach($product_ids as $id)
            {
                $this->_core->addProductChange($id,0);
            }
            
        }
    }

    public function woocommerce_update_product($product_id,$product){
        $action = isset($_REQUEST['action']) ? esc_attr($_REQUEST['action']) : '';
        $openpos_type = $this->settings_api->get_option('openpos_type','openpos_pos');
        
        if($product_id)
        {
            $product_ids = array($product_id);
            $child_ids = $product->get_children();
            $product_ids = array_merge( $product_ids,$child_ids);

            //remove cache
            $this->clear_product_cache($product_ids);
            //end

            if($openpos_type == 'restaurant' && $product_id && $action == 'editpost')
            {

                $type_options = $this->getListRestaurantArea();
                foreach($type_options as $akey => $aop) {
                    $a_key = '_op_' . $akey;
                    $cook = 'no';
                    if(isset($_REQUEST[$a_key]))
                    {
                        $op_cook = esc_attr($_REQUEST[$a_key]);
                        if($op_cook == 'on')
                        {
                            $cook = 'yes';
                        }
                    }
                    foreach($product_ids as $id)
                    {
                        update_post_meta($id,$a_key,$cook);
                    }
                }
            }
            foreach($product_ids as $id)
            {
                $this->_core->addProductChange($id,0);
            }
        }
    }
    public function check_product_kitchen_op_type($kitchen_type,$product_id,$item = null){
        $result = false;
        $key = '_op_'.esc_attr($kitchen_type);
        $post = get_post($product_id);
        if($post)
        {
            if($post->post_parent && $post->post_parent > 0)
            {
                $product_id = $post->post_parent;
            }
            $_op_type = get_post_meta($product_id,$key,true);
            if($_op_type == 'yes')
            {
                $result = true;
            }
        }
        if(!$result && $item != null)
        {
            $bundles = isset($item['bundles']) ? $item['bundles'] : array();
            if(!empty($bundles))
            {
                foreach($bundles as $b)
                {
                    $bundle_value = isset($b['value']) ? $b['value'] : '';
                    if($bundle_value && $bundle_value != null)
                    {
                        $product_id = 1 * $bundle_value;
                        $post = get_post($product_id);
                        if($post)
                        {
                            if($post->post_parent && $post->post_parent > 0)
                            {
                                $product_id = $post->post_parent;
                            }
                            $_op_type = get_post_meta($product_id,$key,true);
                            if($_op_type == 'yes')
                            {
                                $result = true;
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }
    public function sortAttributeOptions($attribute_code,$options){
        if(strpos($attribute_code,'pa_') !== false)
        {
            $result = array();
            $tmp_result = array();
            foreach($options as $op_name)
            {
            	 $term = get_term_by('name',$op_name, $attribute_code);
            	 if(!$term){
            	     $term = get_term_by('slug',$op_name, $attribute_code);
            	 }
            	 if($term && is_object($term) )
                 {
                    $tmp_result[] = $term->slug;
                 }
            }
            
            $terms = get_terms( $attribute_code, array(
                'hide_empty' => false,
            ) );

            foreach($terms as $term)
            {
                if($term && is_object($term) && in_array($term->slug,$tmp_result))
                {
                    $result[] = $term->slug;
                }
            }
            return $result;
        }
        return $options;
    }
    public  function custom_vnsearch_slug($str) {
        $str = trim(mb_strtolower($str));
        $str = preg_replace('/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ|ä)/', 'a', $str);
        $str = preg_replace('/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/', 'e', $str);
        $str = preg_replace('/(ì|í|ị|ỉ|ĩ)/', 'i', $str);
        $str = preg_replace('/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ|ö)/', 'o', $str);
        $str = preg_replace('/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ|ü)/', 'u', $str);
        $str = preg_replace('/(ỳ|ý|ỵ|ỷ|ỹ)/', 'y', $str);
        $str = preg_replace('/(đ)/', 'd', $str);
        $str = preg_replace('/[^a-z0-9-\s]/', '', $str);
        $str = preg_replace('/([\s]+)/', ' ', $str);
        $str = str_replace('  ',' ',$str);
        return $str;
    }
    public function woocommerce_order_item_display_meta_key($display_key, $meta){
        if($meta->key && $meta->key == 'op_item_details')
        {
            $display_key = __('Item Details','openpos');
        }
        return $display_key;
    }
    public function get_cost_price($product_id,$view = false){
        $price = false;
        $tmp_price = get_post_meta($product_id,'_op_cost_price',true);
        if($tmp_price !== false && $tmp_price != '')
        {
            $price = $tmp_price;
        }
       // wc_format_decimal
        return  apply_filters( 'op_get_cost_price', $price,$product_id,$view);
    }
    public function _is_weight_base_pricing($product_id){
        $result = false;
        $post = get_post($product_id);
        $setting_product_id = $product_id;
        if($post && $post->post_parent)
        {
            $setting_product_id = $post->post_parent;
        }
        $tmp_price = get_post_meta($setting_product_id,'_op_weight_base_pricing',true);

        if($tmp_price == 'yes')
        {
            $tmp_price = 'weight_base';
        }
        if(in_array($tmp_price,array('weight_base','length_base')))
        {
            return $tmp_price;
        }

        return $result;
    }
    public function _is_price_base_pricing($product_id){
        $result = false;
        $post = get_post($product_id);
        $setting_product_id = $product_id;
        if($post && $post->post_parent)
        {
            $setting_product_id = $post->post_parent;
        }
        $tmp_price = get_post_meta($setting_product_id,'_op_weight_base_pricing',true);
        if(in_array($tmp_price,array('price_base')))
        {
            return true;
        }

        return $result;
    }
    public function is_weight_base_pricing($product_id){
        $result = false;
        $post = get_post($product_id);
        $setting_product_id = $product_id;
        if($post && $post->post_parent)
        {
            $setting_product_id = $post->post_parent;
        }
        $tmp_price = $this->_is_weight_base_pricing($setting_product_id);

        if($tmp_price)
        {
            $product = wc_get_product($product_id);
            $weight =  $product->get_weight('pos');

            if($weight && (float)$weight > 0)
            {
                $weight = (float)$weight;
                $price = $product->get_price();
                $result = ($price/$weight);
            }
        }
        return apply_filters( 'op_is_weight_base_pricing', $result );
    }
    public function stripePriceTag($price,$price_html = ''){
        if(!$price)
        {
          $price = 0;
        }
	    $negative          = $price < 0;
        $args = array(
            'ex_tax_label'       => false,
            'currency'           => '',
            'decimal_separator'  => wc_get_price_decimal_separator(),
            'thousand_separator' => wc_get_price_thousand_separator(),
            'decimals'           => wc_get_price_decimals(),
            'price_format'       => get_woocommerce_price_format(),
        );
        $tmp_price = $negative ? ($price * -1) : $price ;
        $price             = apply_filters( 'op_raw_woocommerce_price', floatval($tmp_price) );
	    $price             = apply_filters( 'op_formatted_woocommerce_price', number_format( $price, $args['decimals'], $args['decimal_separator'], $args['thousand_separator'] ), $price, $args['decimals'], $args['decimal_separator'], $args['thousand_separator'] );

        return $price;
    }
    public function woocommerce_loaded(){
        include_once OPENPOS_DIR . 'lib/data-stores/class-wc-product-data-store-cpt.php';
        // Removes the WooCommerce filter, that is validating the quantity to be an int
        remove_filter('woocommerce_stock_amount', 'intval');
        // Add a filter, that validates the quantity to be a float
        add_filter('woocommerce_stock_amount', 'floatval'); 

        add_filter('woocommerce_quantity_input_pattern',array($this,'custom_woocommerce_quantity_input_pattern'),10,1);
        add_filter('woocommerce_quantity_input_inputmode',array($this,'custom_woocommerce_quantity_input_inputmode'),10,1);
    }
    function custom_woocommerce_quantity_input_pattern(){
        return (has_filter( 'woocommerce_stock_amount', 'intval' ) || has_filter( 'woocommerce_stock_amount', 'floatval' ))  ? '[0-9]*' : '' ;
    }
    function custom_woocommerce_quantity_input_inputmode(){
        return  (has_filter( 'woocommerce_stock_amount', 'intval' ) || has_filter( 'woocommerce_stock_amount', 'floatval' )) ? 'numeric' : '';
    }
    public function getStoreShippingMethods($warehouse_id,$setting){
        global $op_warehouse;
        $result = array();
        $shipping_methods = isset($setting['shipping_methods']) ? $setting['shipping_methods'] : array();
        
        $warehouse_details = $op_warehouse->getStorePickupAddress($warehouse_id);
        foreach($shipping_methods as $shipping_method)
        {
            
            $method_code = $shipping_method['code'];
            $tax_method_setting_code = 'shipping_tax_class_'.esc_attr($method_code);
            $tax_method_setting = $this->settings_api->get_option($tax_method_setting_code,'openpos_shipment');
            $shipping_taxes = $shipping_method['tax_details'];
       
            if($tax_method_setting != 'op_notax')
            {
                $shipping_taxes = array();
                if($warehouse_details['country'] || $warehouse_details['state'] || $warehouse_details['postcode'] || $warehouse_details['city'] )
                {
                    $base_tax_rates = $this->getLocationTaxRates($tax_method_setting,$warehouse_details);
                    foreach($base_tax_rates as $rate_id => $base_tax_rate)
                    {
                        if(isset($base_tax_rate['shipping']) && $base_tax_rate['shipping'] == 'yes')
                        {
                            $tax_rate_code = $tax_method_setting ? $tax_method_setting.'_'.$rate_id : 'standard'.'_'.$rate_id;
                            $shipping_tax = array(
                                    'code' => $tax_rate_code, // in percentage
                                    'rate' => $base_tax_rate['rate'], // in percentage
                                    'shipping' => 'yes',
                                    'compound' => 'no',
                                    'rate_id' => $rate_id,
                                    'label' => $base_tax_rate['label']
                            );
                            $shipping_taxes[] = $shipping_tax;
                        }
                    }
                    
                }
                //sample
            }
            $shipping_method['tax_details'] = $shipping_taxes;
            $result[] =  $shipping_method;
        }
        return $result;
    }
    public function getStoreProductTotal($warehouse_id){
        
    }
    public function getNotifications($from_time = 0,$session_data = array()){
        $from_time = ceil($from_time / 1000); //server time
        $notifications = array();
        
        $wc_date = new WC_DateTime();
        $wc_date->setTimestamp($from_time);
        $date_string = $wc_date->date("Y-m-d H:i:s");
            
        $notifications['date_string'] =  $date_string;
        $orders = array();
        if($this->_enable_hpos)
        {
            
            // if ( get_option( 'timezone_string' ) ) {
            //     $wc_date->setTimezone( new DateTimeZone( wc_timezone_string() ) );
            // } else {
            //     $wc_date->set_utc_offset( wc_timezone_offset() );
            // }
            
            $post_type = 'shop_order';
            $args = array(
                'date_query' => array(
                    'after'  =>  $date_string,
                    'column' => 'date_created_gmt',
                ),
                'post_type' => $post_type,
                'post_status' => array(
                    'wc-processing',
                    'wc-pending',
                    'wc-completed',
                    'wc-refunded',
                    'wc-on-hold',
                ),
                'posts_per_page' => 50,
                'orderby' => 'date',
                'order' => 'DESC'
            );
            
            $args = apply_filters('op_notification_order_query_args',$args);
            $data_store = WC_Data_Store::load( 'order' );
            $posts = $data_store->query( $args );

        }else{
            $post_type = 'shop_order';
            $args = array(
                'date_query' => array(
                    'after'  =>  $date_string,
                    'column' => 'post_date_gmt',
                ),
                'post_type' => $post_type,
                'post_status' => array(
                    'wc-processing',
                    'wc-pending',
                    'wc-completed',
                    'wc-refunded',
                    'wc-on-hold',
                ),
                'posts_per_page' => 50,
                'orderby' => 'date',
                'order' => 'DESC'
            );
            
            $args = apply_filters('op_notification_order_query_args',$args);
            $args['date_query']['column'] = 'post_date_gmt';
            $query = new WP_Query($args);
           
            $posts = $query->get_posts();
           
        }
        foreach($posts as $p)
        {

            if ( $p instanceof WC_Order )
            {
                $order_id = $p->get_id();
                
            }else{
                $order_id = $p->ID;
                
            }
            $formatted_order = $this->formatWooOrder($order_id);
            
            if($formatted_order)
            {
                $source_type = $formatted_order['source_type'];
                if($source_type != 'online')
                {
                    continue;
                }
                $order_number = $formatted_order['order_number'];
                $orders[] = array(
                    'type' => 'orders',
                    'id'=> $order_number
                );
            }
           
        }
        
        $orders = apply_filters('op_notice_orders_result',$orders,$posts,$from_time,$session_data);
        
        if(!empty($orders))
        {
            $notifications['message'] = sprintf( __('You have %d new orders from website','openpos'),count($orders));
            
            $notifications['items'] =  $orders;
        }

        
        return $notifications;
    }
    public function wc_get_template($template, $template_name, $args, $template_path, $default_path){
        if(isset($args['order']) )
        {
            if($template_name == 'emails/customer-completed-order.php' || $template_name == 'emails/plain/customer-completed-order.php')
            {
                $order = $args['order'];
                if($order)
                {
                    
                    $order_id = $order->get_id();
                    if($order_id)
                    {
                        $source = $order->get_meta('_op_order_source');
                        if($source == 'openpos')
                        {
                            $op_template_path = apply_filters('op_template_path',OPENPOS_DIR.'templates/',$order_id,$source);
                            $template = $op_template_path.$template_name;
                        }
                    }
                }
                
            }
        }
        
        return $template;
    }
    public function _add_customer($request,$session_data = array())
    {
        $name = isset($request['name']) ? $request['name'] : '';
        if($name)
        {
            $name = trim($name);
            $tmp = explode(' ',$name);
            $firstname = $tmp[0];
            $lastname = trim(substr($name,(strlen($firstname))));
        }else{
            $firstname = isset($request['firstname']) ? $request['firstname'] : '';
            $lastname = isset($request['lastname']) ? $request['lastname'] : '';
        }
        $email = isset($request['email']) &&  $request['email'] != 'null' ? $request['email'] : '';
        $phone = isset($request['phone']) &&  $request['phone'] != 'null'  ? $request['phone'] : '';
        $address = isset($request['address']) &&  $request['address'] != 'null'  ? $request['address'] : '';

        $password = (isset($request['password']) &&  $request['password'] != 'null')  ? $request['password'] : false;
        $confirm_password = (isset($request['confirm_password']) &&  $request['confirm_password'] != 'null')  ? $request['confirm_password'] : false;
       
        $result = array('status' => 0, 'message' => '','data' => array());
        
        $username = isset($request['username']) &&  $request['username'] != 'null'  ? $request['username'] : '';
        if(!$username && function_exists('wc_create_new_customer_username'))
        {
            
            $username = wc_create_new_customer_username( $email, array(
                'first_name' => $firstname,
                'last_name' => $lastname,
            ) );
            
        }
        if(!$username)
        {
            $username = 'user_'.time();
            
        }
    
        $username = apply_filters('op_customer_username',sanitize_title($username),$request);
        if(!$email)
        {
            $demo_domain_email = apply_filters('op_customer_demo_domain','@wpos.app');
            $email = $username.$demo_domain_email;
        }
        $require_phone = apply_filters('op_customer_require_phone',true,$phone);
        if(!$phone && $require_phone)
        {
            $result['message'] = __('Please enter phone number','openpos' );
        }
        if($password !== false)
        {
            if($password != $confirm_password)
            {
                $result['message'] = __('Your password not match. Please check again','openpos' );
            }
        }

        
        if(!$result['message'])
        {
            try{
                do_action('op_add_customer_before',$request,$session_data );
                
                if($password !== false)
                {
                    $id = wc_create_new_customer($email,$username,$password);
                }else{
                    $password  = wp_generate_password();
                    
                    $id = wc_create_new_customer($email,$username,$password);
                }
                if ( is_wp_error( $id ) ) {
                    $errors = $id->get_error_messages();
                    if(!empty($errors))
                    {
                        throw new Exception($errors[0]);
                    }
                }
                do_action('op_create_new_customer_after',$id,$password,$request,$session_data );
                
                if($name)
                {
                    wp_update_user( array( 'ID' => $id, 'display_name' => $name ) );
                }
                
                $customer = new WC_Customer($id);
                
                //$customer->set_username($username);

                if($firstname)
                {
                    $customer->set_first_name($firstname);
                    $customer->set_billing_first_name($firstname);
                    $customer->set_shipping_first_name($firstname);
                }
                if($lastname)
                {
                    $customer->set_last_name($lastname);
                    $customer->set_billing_last_name($lastname);
                    $customer->set_shipping_last_name($lastname);
                }

                if(isset($request['state']) && $request['state'] && $request['state'] != null && $request['state'] != 'null')
                {
                   
                    $customer->set_billing_state($request['state']);
                    $customer->set_shipping_state($request['state']);
                }
                if(isset($request['city']) && $request['city'] && $request['city'] != null && $request['city'] != 'null')
                {
                    
                    $customer->set_billing_city($request['city']);
                    $customer->set_shipping_city($request['city']);
                }

                if($address)
                {
                    $customer->set_billing_address_1($address);
                    $customer->set_shipping_address_1($address);
                }
                

                if(isset($request['address_2']) && $request['address_2'] && $request['address_2'] != null && $request['address_2'] != 'null')
                {
                    $customer->set_billing_address_2($request['address_2']);
                    $customer->set_shipping_address_2($request['address_2']);
                }

                // default contry
                $default_country = $this->getDefaultContry();
                $country = '';
                if(isset($request['country']) && $request['country'] != null && $request['country'] != 'null')
                {
                    $country = $request['country'];

                }
                if(!$country)
                {
                    $country = $default_country;
                }
                if($country)
                {
                    
                    $customer->set_billing_country($country);
                    $customer->set_shipping_country($country);
                }
                //end default country

                if(isset($request['postcode']) && $request['postcode'] && $request['postcode'] != null && $request['postcode'] != 'null')
                {
                    
                    $customer->set_billing_postcode($request['postcode']);
                    $customer->set_shipping_postcode($request['postcode']);
                }
                if($phone)
                {
                    $customer->set_billing_phone($phone);
                    $customer->set_shipping_phone($phone);
                }
                
                if($address)
                {
                    $customer->set_billing_address($address);
                    $customer->set_shipping_address($address);

                }
                if($email)
                {
                    $customer->set_billing_email($email);
                }
                
                
                $customer->save();
            
                if($id)
                {
                    $user_obj = get_userdata( $id);
                    clean_user_cache( $user_obj );
                   
                    $cashdrawer_id = isset($session_data['login_cashdrawer_id']) ? $session_data['login_cashdrawer_id'] : 0;
                    $warehouse_id = isset($session_data['login_warehouse_id']) ? $session_data['login_warehouse_id'] : 0;

                    $customer->add_meta_data('_op_cashdrawer_id',$cashdrawer_id);
                    $customer->add_meta_data('_op_warehouse_id',$warehouse_id);
                    if($name)
                    {
                        $customer->add_meta_data('_op_full_name',$name);
                    }
                    
                    
                    
                    $result['status'] = 1;
                    $result['data'] = $id;
                }

            }catch (Exception $e)
            {
                $result['status'] = 0;
                $result['message'] = $e->getMessage();
            }

        }
        return $result;
    }
    public function _update_customer_shipping($customer_id,$shipping_information){
        //pending update defaut shipping address when customer have no shipping in first time
    }
    public function product_data_visibility(){
        $visibility_options = array();
        $current_visibility = 'sdfsdfds';//$product_object->get_catalog_visibility();
        ?>
        <div class="misc-pub-section" id="pos-visibility">
			<?php esc_html_e( 'POS visibility:', 'openpos' ); ?>
			<strong id="pos-visibility-display">
				<?php

				// echo isset( $visibility_options[ $current_visibility ] ) ? esc_html( $visibility_options[ $current_visibility ] ) : esc_html( $current_visibility );

				
				?>
			</strong>

			<a href="#pos-visibility" class="edit-pos-visibility hide-if-no-js"><?php esc_html_e( 'Edit', 'openpos' ); ?></a>

			<div id="pos-visibility-select" class="hide-if-js">

				<input type="hidden" name="current_pos_visibility" id="current_pos_visibility" value="<?php echo esc_attr( $current_visibility ); ?>" />
				

				<?php
				echo '<p>' . esc_html__( 'This setting determines which products will be listed on.', 'openpos' ) . '</p>';

                    foreach ( $visibility_options as $name => $label ) {
                        echo '<input type="radio" name="_pos_visibility" id="_pos_visibility_' . esc_attr( $name ) . '" value="' . esc_attr( $name ) . '" ' . checked( $current_visibility, $name, false ) . ' data-label="' . esc_attr( $label ) . '" /> <label for="_visibility_' . esc_attr( $name ) . '" class="selectit">' . esc_html( $label ) . '</label><br />';
                    }

				    
				?>
				<p>
					<a href="#pos-visibility" class="save-post-visibility hide-if-no-js button"><?php esc_html_e( 'OK', 'openpos' ); ?></a>
					<a href="#pos-visibility" class="cancel-post-visibility hide-if-no-js"><?php esc_html_e( 'Cancel', 'openpos' ); ?></a>
				</p>
			</div>
		</div>
        <?php
    }

    public function getAvailableTaxes($warehouse_id = 0){
        global $op_warehouse;
        $result = array();
       
        $warehouse_details = $op_warehouse->getStorePickupAddress($warehouse_id);
        if(!empty($warehouse_details))
        {
           
            $tax_classes = wc_get_product_tax_class_options();
            
            foreach($tax_classes as $tax_class => $tax_class_name)
            {
                $base_tax_rates = WC_Tax::get_base_tax_rates( $tax_class );
               
                if($warehouse_details['country'] || $warehouse_details['state'] || $warehouse_details['postcode'] || $warehouse_details['city'] )
                {
                    $base_tax_rates = $this->getLocationTaxRates($tax_class,$warehouse_details);
                }
                        
                $tax_rates = $base_tax_rates;

                if(!empty($tax_rates))
                {
                    $formated_rates = array();
                    $tax_class_code = $tax_class;
                    if($tax_class =='')
                    {
                        $tax_class_code  = 'standard';
                    }
                    foreach($tax_rates as $rate_id => $rate)
                    {
                            $tax_rate = array();
                            $tax_rate['code'] = $tax_class ? $tax_class.'_'.$rate_id : 'standard'.'_'.$rate_id;
                            $tax_rate['rate_id'] = $rate_id;
                            $tax_rate['tax_class'] = $tax_class_code;
                            if($rate['label'])
                            {
                                $tax_rate['label'] = $rate['label'];
                            }
                            if(isset($rate['shipping']))
                            {
                                $tax_rate['shipping'] = $rate['shipping'];
                            }
                            if(isset($rate['compound']))
                            {
                                $tax_rate['compound'] = $rate['compound'];
                            }
                            if(isset($rate['rate']))
                            {
                                $tax_rate['rate'] = $rate['rate'];
                            }
                            $formated_rates[] = $tax_rate;
                    }
                    
                    $result[] = array(
                        'tax_class' => $tax_class_code,
                        'tax_class_name' => $tax_class_name,
                        'rates' => $formated_rates
                    );
                }
            }
            
        }
       
        return $result;
    }
    public function op_price( $price, $args = array() ) {
        $args = apply_filters(
            'wc_price_args',
            wp_parse_args(
                $args,
                array(
                    'ex_tax_label'       => false,
                    'currency'           => '',
                    'decimal_separator'  => wc_get_price_decimal_separator(),
                    'thousand_separator' => wc_get_price_thousand_separator(),
                    'decimals'           => wc_get_price_decimals(),
                    'price_format'       => get_woocommerce_price_format(),
                )
            )
        );
    
        $unformatted_price = $price;
        $negative          = $price < 0;
        $price             = apply_filters( 'raw_woocommerce_price', floatval( $negative ? $price * -1 : $price ) );
        $price             = apply_filters( 'formatted_woocommerce_price', number_format( $price, $args['decimals'], $args['decimal_separator'], $args['thousand_separator'] ), $price, $args['decimals'], $args['decimal_separator'], $args['thousand_separator'] );
    
        if ( apply_filters( 'woocommerce_price_trim_zeros', false ) && $args['decimals'] > 0 ) {
            $price = wc_trim_zeros( $price );
        }
        return $negative ? '-'.$price : $price;
    }

    public function op_price_number( $price, $args = array() ) {
        $price = 1 * floatval($price);
        $formatted_number = $this->op_price($price,$args);
        if(!$formatted_number)
        {
            return 0;
        }
        $formatted_number = wc_trim_zeros( $formatted_number );
        $number_format = str_replace(wc_get_price_thousand_separator(),'',$formatted_number);
        $number_format = str_replace(wc_get_price_decimal_separator(),'.',$number_format);
        return 1 * $number_format;
    }

    public function getProductCustomNotes($product_id){
        $notes = array();
        $parent_id = wp_get_post_parent_id($product_id);
        if($parent_id)
        {
            $product_id = $parent_id;
        }
        $terms = get_the_terms($product_id,'op_product_notes');
        if($terms && !empty($terms))
        {
            foreach($terms as $term)
            {
                if($term->name)
                {
                    $notes[] = $term->name;
                }
                
            }
            
        }
        return $notes;
    }
    public function op_update_local_date($order,$order_data){
        global $op_session_data;
        $created_at_local_time = isset($order_data['created_at_time']) ? $order_data['created_at_time'] : 0;
        $client_time_offset = isset($order_data['client_time_offset']) ? $op_session_data['client_time_offset'] : 0;
        $tmp_order_data_id = isset($order_data['order_id']) ? $order_data['order_id'] : 0;
        $utc_date = isset($order_data['created_at_utc']) ? $order_data['created_at_utc'] : '';
        if($utc_date)
        {
            $local_date = get_date_from_gmt($utc_date );
            $gmt_date = get_gmt_from_date($local_date);
            $order_id = $order->get_id();
            wp_update_post(
                array (
                    'ID'            => $order_id, // ID of the post to update
                    'post_date'     => $local_date,
                    'post_date_gmt' => $gmt_date
                )
            );
        }else{
            if($created_at_local_time && $tmp_order_data_id  == 0)
            {
                
                $utc_time =  $this->_core->get_hosting_time($created_at_local_time,$client_time_offset);
                if($utc_time)
                {
                    $format = 'Y-m-d H:i:s';
                    $post_date = gmdate($format,$utc_time); // 
                    $post_date_gmt = get_gmt_from_date( $post_date );
                    $order_id = $order->get_id();
                    wp_update_post(
                        array (
                            'ID'            => $order_id, // ID of the post to update
                            'post_date'     => $post_date,
                            'post_date_gmt' => $post_date_gmt
                        )
                    );
    
                }
            }
        }
        
    }
    public function op_transaction_update_local_date($id,$session_data,$transaction_data){
        $utc_date = isset($transaction_data['created_at_utc']) ? $transaction_data['created_at_utc'] : '';
        if($utc_date){
            $local_date = get_date_from_gmt($utc_date );
            $gmt_date = get_gmt_from_date($local_date);
            wp_update_post(
                array (
                    'ID'            => $id, // ID of the post to update
                    'post_date'     => $local_date,
                    'post_date_gmt' => $gmt_date
                )
            );
        }

    }
    public function woocommerce_order_data_store_cpt_get_orders_query($wp_query_args, $query_vars){
        
        if(isset($query_vars['_query_src']) && $query_vars['_query_src'] == 'op_order_query')
        {
            $query_meta = isset($query_vars['meta_query']) ? $query_vars['meta_query'] : array();
            if(!empty($query_meta))
            {
                if(!isset($wp_query_args['meta_query']))
                {
                    $wp_query_args['meta_query'] = array();
                }
                foreach($query_meta as $k => $v)
                {
                    if($k != 'relation')
                    {
                        $wp_query_args['meta_query'][] = $v;
                    }
                    
                }
            }
        }
        return $wp_query_args;
    }
    public function enable_decimal_qty($product){
        $result = false;
        $parent_id = $product->get_parent_id();
        if($parent_id)
        {
            $product = wc_get_product( $parent_id );
        }
        
        if($product)
        {
            $is_decimal_qty = $product->get_meta('_op_qty_decimal');
            if($is_decimal_qty == 'yes')
            {
                $result = true;
            }
        }
        return $result;
    }
    public function enable_print_decal($product){
        $result = false;
        $parent_id = $product->get_parent_id();
        if($parent_id)
        {
            $product = wc_get_product( $parent_id );
        }
        
        if($product)
        {
            $is_decimal_qty = $product->get_meta('_op_allow_decal');
            if($is_decimal_qty == 'yes')
            {
                $result = true;
            }
        }
        return apply_filters( 'op_enable_print_decal', $result );
    }
    public function woocommerce_quantity_input_step($step,$product){
        if($product)
        {
            if($this->enable_decimal_qty($product))
            {
                // if weight base / lenge base
                $decimal =  wc_get_price_decimals();
                if(!$decimal)
                {
                    $decimal = 0;
                }
    
                $decimal = 1 * $decimal ;
                $pow = pow(10,$decimal);
    
                $step = number_format((1/$pow),$decimal);
            }
        }
        return $step;
    }
    public function woocommerce_quantity_input_step_admin($step,$product,$type){
        if($product)
        {
            if($this->enable_decimal_qty($product))
            {
                // if weight base / lenge base
                $decimal =  wc_get_price_decimals();
                if(!$decimal)
                {
                    $decimal = 0;
                }
    
                $decimal = 1 * $decimal ;
                $pow = pow(10,$decimal);
    
                $step = number_format((1/$pow),$decimal);
            }
        }

        return $step;
    }
    public function pin_authenticate($pin,$unique_session = false){
        global $wpdb;
        $pin = trim($pin);
        //$sql = "SELECT * FROM `{$wpdb->usermeta}`  WHERE meta_key= '_op_pin' AND meta_value='".$pin."'";
        $sql = $wpdb->prepare("SELECT * FROM `{$wpdb->usermeta}`  WHERE meta_key= '_op_pin' AND meta_value='%s'",$pin);
        $rows = $wpdb->get_results( $sql );
        $allow_login = apply_filters( 'op_pin_authenticate_allow', true,$pin,$unique_session );
        if($allow_login)
        {
            if(!empty($rows))
            {
                if(count($rows) == 1)
                {
                    $row = end($rows);
                    $user_id = $row->user_id;
                    $user = get_user_by('id',$user_id);
                    if(!$user)
                    {
                        $user = new WP_Error( 'authentication_failed', __( '<strong>Error:</strong> Invalid PIN.','openpos' ) );
                    }elseif($unique_session){
                        $prefix = 'op-'.sanitize_title($user->user_login).'-';
                        $sessions  = $this->_session->getSessionByPrefix($prefix);
                        if(!empty($sessions))
                        {
                            $user = new WP_Error( 'authentication_failed', __( '<strong>Error:</strong> This user already login by other session.','openpos' ) );
                        }
                        
                    }
                }else{
                    $user = new WP_Error( 'authentication_failed', __( '<strong>Error:</strong> Have multi user has same PIN.','openpos' ) );
                }
               
            }else{
                $user = new WP_Error( 'authentication_failed', __( '<strong>Error:</strong> Invalid PIN.','openpos' ) );
    
            }
        }else{
            $user = new WP_Error( 'authentication_failed', __( '<strong>Error:</strong>Do not allow Login.','openpos' ) );
        }
        
        return $user;
    }
    public function is_protected_meta($protected, $meta_key, $meta_type){
        //$protected = true;
        $protected_keys = array(
            'sale_person_name',
            'pos_created_at_time',
            'pos_created_at',
        );
        if(in_array($meta_key,$protected_keys))
        {
            $protected = true;
        }
        return $protected;
    }
    public function is_orphaned($product){
        $result = false;
        
        return $result;
    }
   
    public function clear_product_cache($product_ids = array()){
       $this->_core->clear_product_cache($product_ids);
        //end
    }
    public function clear_all_product_cache(){
        return $this->_core->clear_all_product_cache();
    }
    public function clear_expired_transient_cache(){
        $this->_session->clear_expired_transient_cache();
    }
    public function clear_all_transient_cache(){
        $this->_session->clear_all_transient_cache();
    }
    public function getTaxDetails($tax_class,$rate_id = 0){
        
        $result = array('rate'=> 0,'compound' => '0','rate_id' => $rate_id,'shipping' => 'no','label' => 'Tax');
        if($tax_class != 'op_productax' && $tax_class != 'op_notax')
        {
            $tax_rate = 0;

            if(wc_tax_enabled() )
            {

                    $tax_rates = WC_Tax::get_rates( $tax_class );
                    $rates = $this->getTaxRates($tax_class);
                    if(!empty($tax_rates))
                    {
                        $rate = end($tax_rates);
                        $result = $rate;
                    }
                    if($rate_id && isset($rates[$rate_id]))
                    {
                        $result = $rates[$rate_id];
                    }

                    $result['code'] = $tax_class ? $tax_class.'_'.$rate_id : 'standard_'.$rate_id;
                    $result['rate_id'] = $rate_id;
            }

        }

        return $result;
    }
    public function _formatSetting($setting)
    {
        global $op_woo;
        //$setting['pos_search_display_product'] = 'yes';
        $setting['pos_money'] = $this->_core->_formatCash($setting['pos_money']);
        $setting['pos_custom_cart_discount_amount'] = $this->_core->_formatDiscountAmount($setting['pos_custom_cart_discount_amount']);
        $setting['pos_custom_item_discount_amount'] = $this->_core->_formatDiscountAmount($setting['pos_custom_item_discount_amount']);

        if(isset($setting['pos_allow_tip']) && $setting['pos_allow_tip'] == 'yes' && isset($setting['pos_tip_amount']))
        {
            $setting['pos_tip_amount'] = $this->_core->_formatTipAmount($setting['pos_tip_amount']);
        }

        if(isset($setting['pos_tax_rate_id']))
        {
            $setting['pos_tax_details'] = $this->getTaxDetails($setting['pos_tax_class'],$setting['pos_tax_rate_id']);
        }

        if($setting['pos_tax_class'] == 'op_productax' && isset($setting['pos_discount_tax_rate']))
        {
            $setting['pos_discount_tax'] = $this->getTaxDetails($setting['pos_discount_tax_class'],$setting['pos_discount_tax_rate']);
        }

        if($setting['pos_tax_class'] == 'op_notax')
        {
            $setting['pos_tax_details'] = $this->getTaxDetails('',0);
        }else{
            if ( 'yes' === get_option( 'woocommerce_tax_round_at_subtotal' ) ) {
                $setting['pos_tax_on_item_total'] = 'yes';
            } 
        }

        


        $setting['openpos_customer_addition_fields'] = $this->getCustomerAdditionFields();
        
        $setting['pos_incl_tax_mode'] = ( $setting['pos_tax_class'] != 'op_notax'  && 'yes' === get_option( 'woocommerce_prices_include_tax' ) )  ? 'yes' : 'no'; //check
        $setting['pos_prices_include_tax'] = ( 'yes' === get_option( 'woocommerce_prices_include_tax' ) )  ? 'yes' : 'no';

        if(!isset($setting['pos_tax_included_discount']))
        {
            $setting['pos_tax_included_discount'] = 'no';
        }

        $setting['pos_tax_included_discount'] = ( $setting['pos_tax_class'] != 'op_productax'  || 'yes' === get_option( 'woocommerce_prices_include_tax' ) )  ? 'yes' : $setting['pos_tax_included_discount'];
        
        if($setting['pos_incl_tax_mode'] == 'yes')
        {
            $setting['pos_item_incl_tax_mode'] = 'yes';

        }
        if($setting['pos_sequential_number_enable'] == 'no')
        {
            if(isset($setting['pos_sequential_number']))
            {
                unset($setting['pos_sequential_number']);
            }
            if(isset($setting['pos_sequential_number_prefix']))
            {
                unset($setting['pos_sequential_number_prefix']);
            }

        }

        if($setting['pos_tax_class'] == '')
        {
            $setting['pos_tax_class'] = 'standard';
        }
        if($setting['allow_exchange'] == 'alway')
        {
            $setting['allow_exchange'] = 'yes';
        }

        if($setting['pos_tax_class'] == 'op_notax' )
        {
            $setting['price_included_tax'] = false; 
        }
        if($setting['pos_tax_class'] == 'op_notax' || $setting['pos_tax_class'] == 'op_productax' )
        {
          
           $setting_pos_tax_details = $this->getTaxDetails('',0);
           $setting_pos_tax_details['rate'] = 0;
           $setting['pos_tax_details'] = $setting_pos_tax_details;
           
        }

        

        //remove none use

        if($setting['barcode_label_template'] )
        {
            unset($setting['barcode_label_template']);
        }
        if($setting['pos_custom_css'] )
        {
            unset($setting['pos_custom_css']);
        }
       

        if(isset($setting['pos_product_grid']) && !empty($setting['pos_product_grid'])){
           
            $pos_product_grid_column = 4;
            $pos_product_grid_row = 4;
            if(isset($setting['pos_product_grid']['col'])){
                $col = (int)$setting['pos_product_grid']['col'];
                if($col > 0)
                {
                    $pos_product_grid_column = $col;
                }
            }
            if(isset($setting['pos_product_grid']['row'])){
                $row = (int)$setting['pos_product_grid']['row'];
                if($row > 0)
                {
                    $pos_product_grid_row = $row;
                }
            }
            $setting['pos_product_grid_column'] = $pos_product_grid_column;
            $setting['pos_product_grid_row'] = $pos_product_grid_row;

            $setting['pos_product_grid_column_sm'] = $pos_product_grid_column;
            $setting['pos_product_grid_row_sm'] = $pos_product_grid_row;

            $setting['pos_product_grid_column_xs'] = $pos_product_grid_column;
            $setting['pos_product_grid_row_xs'] = $pos_product_grid_row; 

            unset($setting['pos_product_grid']);
        }
        if(isset($setting['pos_category_grid']) && !empty($setting['pos_category_grid'])){

            $pos_cat_grid_column = 2;
            $pos_cat_grid_row = 4;


            if(isset($setting['pos_category_grid']['col'])){
                $col = (int)$setting['pos_category_grid']['col'];
                if($col > 0)
                {
                    $pos_cat_grid_column = $col;
                }
            }
            if(isset($setting['pos_category_grid']['row'])){
                $row = (int)$setting['pos_category_grid']['row'];
                if($row > 0)
                {
                    $pos_cat_grid_row = $row;
                }
            }

            $setting['pos_cat_grid_column'] = $pos_cat_grid_column;
            $setting['pos_cat_grid_row'] = $pos_cat_grid_row;

            $setting['pos_cat_grid_column_sm'] = $pos_cat_grid_column;
            $setting['pos_cat_grid_row_sm'] = $pos_cat_grid_row;

            $setting['pos_cat_grid_column_xs'] = $pos_cat_grid_column;
            $setting['pos_cat_grid_row_xs'] = $pos_cat_grid_row;

            unset($setting['pos_category_grid']);
        }

        return $setting;
    }
    function get_customer_by($by,$term){
        global $wpdb;
        $roles = $this->getCustomerUserRoles();

        $users_found = array();
        if($by == 'id'){
           
            if( $customer = get_user_by('id', $term))
            {
                $users_found[] = $customer;
            }
        }else{
            if($by == 'email')
            {
                $args = array(
                    'search'         => '*'.esc_attr( trim($term) ).'*',
                    'search_columns' => array(
                        'user_email'
                    ),
                    'number' => 5
                );
                $args = apply_filters('op_get_customer_by_email_args',$args);
                $users = new WP_User_Query( $args );
                $users_found = $users->get_results();
            }else{
                $sql = $wpdb->prepare("SELECT * FROM  `{$wpdb->usermeta}` AS user_meta  WHERE  user_meta.meta_key = 'billing_phone' AND  user_meta.meta_value LIKE '%s' LIMIT 0,5",'%'.esc_sql($term).'%');
                
                
                $users_found_query = $wpdb->get_results( $sql );

                foreach($users_found_query as $u)
                {
                    $_user = get_user_by('id', $u->user_id);
                    if($_user)
                    {
                        $users_found[] = $_user;
                    }
                    
                }

                
            }

           
            $users_found = apply_filters('op_get_customer_by_'.$by.'_result',$users_found,$term,$this);   
           
        }
        $customers = array();
       
        foreach($users_found as $user)
        {
            $user_roles = $user->roles;
        
            if(!empty(array_intersect($roles,$user_roles)))
            {

                $customer_data = $this->formatCustomer($user->ID);
                if($customer_data != null)
                {
                    $customers[] = $customer_data;
                }
                
            }
        }
        return $customers;
    }
    function get_customers($term = '',$current_page = 1,$per_page = 10){
        global $wpdb;
        $roles = $this->getCustomerUserRoles();
        $total_page = 1;
        
        $offset = 0;
        if($current_page > 1)
        {
            $offset = ($current_page - 1) * $per_page;
        }
        $customers = array();
        if($term)
        {
            $term = trim($term);
            if(is_email($term))
            {
                $customers = $this->get_customer_by('email',$term);
                
            }else{
                $customers = $this->get_customer_by('phone', $term);
                
            }
        }
        if(count($customers)  < 1 )
        {
            $users_found  = array();
            $users_per_page = apply_filters('op_search_customer_result_per_page',5,$term);
            $args = array(
                'number'  => $users_per_page,
                'offset'  => $offset,
                'orderby' => 'ID',
                'order'   => 'DESC',
                'fields'  => 'all',
            );
            if(!empty($roles)){
                $args['role__in'] = $roles;
            }

            if($term)
            {
                $args['search'] =  $term  ;
                if (function_exists('wp_is_large_network') && wp_is_large_network( 'users' ) ) {
                    $args['search'] = ltrim( $args['search'], '*' );
                } elseif ( '' !== $args['search'] ) {
                    $args['search'] = trim( $args['search'], '*' );
                    $args['search'] = '*' . $args['search'] . '*';
                }
            }
            $args = apply_filters('op_search_customer_args',$args,$term);
            
        
            $users = new WP_User_Query($args );
            
            if(method_exists($wpdb,'remove_placeholder_escape'))
            {
                $sql = $wpdb->remove_placeholder_escape($users->request);
                
                $users_found = $wpdb->get_results($sql);
            }else{
                $users_found = $users->get_results();
            }
            
            $users_found_name = array();
            if(count($users_found) < $users_per_page)
            {
                $limit = $users_per_page - count($users_found);
                if($term)
                {
                    $users_found_name = $this->_core->search_customer_name($term, $limit);
                }
            }
            foreach($users_found as $user)
            {
                $user_id = $user->ID;
                $customer_data = $this->formatCustomer($user_id);
                if($customer_data != null)
                {
                    $customers[$user_id] = $customer_data;
                }
                
            }
            foreach($users_found_name as $user)
            {
                $user_id = $user->ID;
                $user_roles = $user->roles;
                
                if(!empty(array_intersect($roles,$user_roles)) || empty($roles))
                {
                    $customer_data = $this->formatCustomer($user_id);
                    if($customer_data != null)
                    {
                        $customer_id = $customer_data['id'];
                        if($customer_id && $customer_id != null)
                        {
                            $customers[$user_id] = $customer_data;
                        }
                    }
                }
                
            }
        }
        return apply_filters('op_search_customer_result',$customers,$term,$this);  
    }

    

}
