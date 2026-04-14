<?php
/**
 * Created by PhpStorm.
 * User: anhvnit
 * Date: 4/10/19
 * Time: 13:33
 */
use Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore;
defined( 'ABSPATH' ) || exit;
if(!class_exists('OP_Woo_Order'))
{
    class OP_Woo_Order{
        private $settings_api;
        private $_core;
        private $_session;
        private $_enable_hpos;
        public $_base_path;
        public $_order_path;
        public $_filesystem;
        public function __construct()
        {
            if(!class_exists('WP_Filesystem_Direct'))
            {
                require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php');
                require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php');
            }
            $upload_dir   = wp_upload_dir();
            $this->_filesystem = new WP_Filesystem_Direct(false);

            $this->_session = new OP_Session();
            $this->settings_api = new OP_Settings();
            $this->_core = new Openpos_Core();
            $this->_base_path =  $upload_dir['basedir'].'/openpos';
            $this->_order_path =  $this->_base_path.'/orders'; //processing order

            add_action('plugins_loaded', array($this,'plugins_loaded'));

            add_action('op_add_order_item_meta',array($this,'op_add_order_item_meta'),10,2);


            add_filter( 'woocommerce_order_number', array( $this, 'display_order_number' ), 10, 2 );
            add_filter( 'op_upload_desk_after', array( $this, 'op_upload_desk_after' ), 10, 4 );

            //customer my account
            add_action( 'woocommerce_account_dashboard', array( $this, 'woocommerce_account_dashboard' ), 10 );
            add_filter( 'woocommerce_my_account_my_orders_query', array( $this, 'woocommerce_my_account_my_orders_query' ), 10, 1 );
            
            add_filter('woocommerce_my_account_my_orders_actions',array($this,'woocommerce_my_account_my_orders_actions') , 10, 2);
            add_action('woocommerce_order_details_after_order_table',array($this,'woocommerce_order_details_after_order_table'),10,1);
            
           
            //add_filter( 'woocommerce_webhook_topic_hooks', array( $this, 'woocommerce_webhook_topic_hooks' ), 10, 2 );
            //add_filter('pre_option_op_wc_custom_order_number',  array( $this,'bypass_cache_custom_options_key'), 1, 2);
            
            

            $this->init();

        }
        function plugins_loaded()
        {
            $this->_enable_hpos = $this->_core->enable_hpos();
            
        }
        
        public function init(){
            $chmod_dir = ( 0755 & ~ umask() );
            if (  defined( 'FS_CHMOD_DIR' ) ) {

                $chmod_dir = FS_CHMOD_DIR;
            }

            // create openpos data directory
            if(!file_exists($this->_base_path))
            {
                $this->_filesystem->mkdir($this->_base_path,$chmod_dir);
            }
            if(!file_exists($this->_order_path))
            {
                $this->_filesystem->mkdir($this->_order_path,$chmod_dir);
            }
        }
        public function add_processing($order_id,$order_data = array()){
            $file_path = $this->_order_path.'/'.$order_id.'.json';
        }
        public function remove_processing($order_id)
        {
            $file_path = $this->_order_path.'/'.$order_id.'.json';
            unlink($file_path);
        }
        public function is_processing($order_id){
            $file_path = $this->_order_path.'/'.$order_id.'.json';
            return file_exists($file_path);
        }

        public function getOrderNotes($order_id){
            $result = array();

            $order = wc_get_order($order_id);
            if($order)
            {
                $date_created = $order->get_date_created();
                
                $order_created_at = '--/--/--';
                if($date_created != null)
                {
                    $order_created_at = esc_html( sprintf( __( '%1$s at %2$s', 'openpos' ), $order->get_date_created()->date_i18n( wc_date_format() ), $order->get_date_created()->date_i18n( wc_time_format() ) ) );
                }
                

                
                    
                $result[] = array(
                    'content' =>   esc_html( sprintf( __( 'Created Order  %1$s', 'openpos' ),$order->get_order_number())),
                    'created_at' => $order_created_at 
                );
                $notes = wc_get_order_notes( array( 'order_id' => $order_id ) );
                foreach ($notes as $note)
                {
                    $created_at = '--/--/--';
                    if($note->date_created != null)
                    {
                        $created_at = esc_html( sprintf( __( '%1$s at %2$s', 'openpos' ), $note->date_created->date_i18n( wc_date_format() ), $note->date_created->date_i18n( wc_time_format() ) ) );
                    }
                    
                    $content = $note->content;
                    if($note->customer_note)
                    {
                        $content.= ' - '.$note->customer_note;
                    }
                    $result[] = array(
                        'content' => $content,
                        'created_at' => $created_at
                    );
                }

            }

            return $result;
        }
        public function addOrderNote($order_id,$note){
            $order = wc_get_order($order_id);
            if($order && $note)
            {
                $is_customer_node = apply_filters('op_order_note_is_customer_node',false);
                wc_create_order_note($order_id,$note,$is_customer_node);
            }
        }
        public function addOrderStatusNote($order_id,$note,$status){
            $order = wc_get_order($order_id);
            if($order && $note)
            {
                $order->set_status($status, $note);
                $order->save();
                
            }
        }

        public function formatOrderNumber($order_number,$pos_sequential_number_prefix = '',$order_parse_data = array()){
            global $op_session_data;
            $pos_sequential_number_enable = $this->settings_api->get_option('pos_sequential_number_enable','openpos_general');
            if($pos_sequential_number_enable == 'yes')
            {
                if($pos_sequential_number_prefix == '')
                {
                    $pos_sequential_number_prefix = $this->settings_api->get_option('pos_sequential_number_prefix','openpos_general');
                }


                $order_number    = apply_filters(
                    'op_wc_custom_order_numbers',
                    sprintf( '%s%s', $pos_sequential_number_prefix, $order_number ),
                    'value',
                    $order_number
                );
                return (string) apply_filters( 'op_display_woocommerce_order_number_formatted', $order_number,$pos_sequential_number_prefix);
            }else{
                return $order_number;
            }
        }

        public function display_order_number($order_number, $order ){
            $is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
            $order_id              = ( $is_wc_version_below_3 ? $order->id : $order->get_id() );
            $pos_sequential_number_enable = $this->settings_api->get_option('pos_sequential_number_enable','openpos_general');
            
            $_op_wc_custom_order_number_formatted  = $order->get_meta('_op_wc_custom_order_number_formatted');

            if($_op_wc_custom_order_number_formatted)
            {
                return $_op_wc_custom_order_number_formatted;
            }
            if($pos_sequential_number_enable == 'yes')
            {
                $order_number_meta     =  $order->get_meta('_op_wc_custom_order_number');
                $_op_order     =  $order->get_meta('_op_order');
                $order_number = $order_id;
                if($order_number_meta)
                {
                    $order_number = (int)$order_number_meta;


                    $pos_sequential_number_prefix = $this->settings_api->get_option('pos_sequential_number_prefix','openpos_general');

                    $shorcodes = array('[year]','[month]','[day]','[register_id]','[outlet_id]','[cashier_id]');
                    $year = '';
                    $month = '';
                    $day = '';
                    $register_id = '';
                    $outlet_id = '';
                    $cashier_id = '';
                    foreach($shorcodes as $s)
                    {
                        // replace shortcode with value 
                        $v = '';
                        switch($s)
                        {
                            case '[year]':
                                $v = $year;
                                break;
                            case '[month]':
                                $v = $month;
                                break;
                            case '[day]':
                                $v = $day;
                                break;
                            case '[register_id]':
                                $v = $register_id;
                                break;
                            case '[outlet_id]':
                                $v = $outlet_id;
                                break;
                            case '[cashier_id]':
                                $v = $cashier_id;
                                break;
                        }
                        $pos_sequential_number_prefix = str_replace($s,$v,$pos_sequential_number_prefix);
                    }

                    $order_number    = apply_filters(
                        'op_wc_custom_order_numbers',
                        sprintf( '%s%s', $pos_sequential_number_prefix, $order_number ),
                        'value',
                        $order_number
                    );

                }else{
                   if($_op_order){
                       return $_op_order['order_number_format'];
                   }
                   
               }
                return (string) apply_filters( 'op_display_woocommerce_order_number', $order_number, $order );
            }else{
                return $order_number;
            }


        }

        public function bypass_cache_custom_options_key($val, $opt){
            $bypass_keys = array(
                '_op_wc_custom_order_number'
            );
            
            if(in_array($opt,$bypass_keys))
            {
                try {
                    global $wpdb;
                    $query = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->options WHERE " . $wpdb->options . ".option_name = '%s'",$opt));
                    return $query[ 0 ]->option_value;
                } catch (Exception $e) {
            
                }
            }
            
            return $val;
        }

        public function update_max_order_number(){
                global $wpdb;

                $option = '_op_wc_custom_order_number';

                wp_cache_delete($option,'options');
                
                $current_order_number = get_option($option,0);// this can be cached 

                $row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option ) );
				if ( is_object( $row ) ) {
                    $current_order_number = $row->option_value;
                }
                if(!$current_order_number)
                {
                    $current_order_number = 0;
                }
                $next_order_number = $current_order_number+1;

                
                $serialized_value = maybe_serialize( $next_order_number );
                $update_args = array(
                    'option_value' => $serialized_value,
                    'autoload' => 'yes'
                );
                $result = $wpdb->update( $wpdb->options, $update_args, array( 'option_name' => $option ) );
                if(!$result)
                {
                    $update_args['option_name'] = $option;
                    $insert_result = $wpdb->insert( $wpdb->options, $update_args);
                    if($insert_result === false)
                    {
                        update_option($option,$serialized_value);
                    }
                }

                return $next_order_number;
        }

        public function update_order_number($order_id,$is_hpos = false)
        {
            $pos_sequential_number_enable = apply_filters('pos_sequential_number_enable',$this->settings_api->get_option('pos_sequential_number_enable','openpos_general'));

            if($pos_sequential_number_enable == 'yes')
            {
                $next_number = $this->update_max_order_number();
                if($is_hpos)
                {
                    global $wpdb;
                    $meta = array();
                    $meta[] = array(
                        'meta_key' => '_op_wc_custom_order_number',
                        'meta_value' => $next_number
                    );
                    $meta[] = array(
                        'meta_key' => '_op_wc_custom_order_number_formatted',
                        'meta_value' => $this->formatOrderNumber($next_number ) 
                    );
                    $order_meta_table = OrdersTableDataStore::get_meta_table_name();
                    foreach($meta as $m)
                    {
                        $sql = $wpdb->prepare(
                            'INSERT INTO ' . $order_meta_table. ' (
                                order_id,
                                meta_key,
                                meta_value
                                )
                                VALUES
                                ( %d,%s, %s)',
                                $order_id,
                                $m['meta_key'],
                                $m['meta_value']
                            );
                        $wpdb->query($sql);
                    }
                    
                }else{
                    update_post_meta( $order_id, '_op_wc_custom_order_number', $next_number );
                    update_post_meta( $order_id, '_op_wc_custom_order_number_formatted', $this->formatOrderNumber($next_number ));
                }
                return $next_number;
            }else{
                return $order_id;
            }

        }
        public function get_order_id_from_number($order_number){
            global $wpdb;
            $order_id = 0;
            if($this->_enable_hpos)
            {
                $wp_post_meta_table  = OrdersTableDataStore::get_meta_table_name();
                $result_select    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' .$wp_post_meta_table. '` WHERE meta_key = "_op_wc_custom_order_number" AND meta_value=%s', $order_number) ); //phpcs:ignore
               
                if($result_select && $post_id = $result_select->order_id)
                {
                    $order_id = $post_id;
                }
                if(!$order_id)
                {
                    $result_select    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' .$wp_post_meta_table. '` WHERE meta_key = "_op_order_number" AND meta_value=%s', $order_number) ); //phpcs:ignore
                
                    if($result_select && $post_id = $result_select->order_id)
                    {
                        $order_id = $post_id;
                    }
                }
                if(!$order_id)
                {
                    $result_select    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' .$wp_post_meta_table. '` WHERE meta_key = "_op_wc_custom_order_number_formatted" AND meta_value=%s', $order_number) ); //phpcs:ignore
                
                    if($result_select && $post_id = $result_select->order_id)
                    {
                        $order_id = $post_id;
                    }
                }


            }else{
                $wp_post_meta_table = $wpdb->postmeta;
                $result_select    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' .$wp_post_meta_table. '` WHERE meta_key = "_op_wc_custom_order_number" AND meta_value=%s', $order_number) ); //phpcs:ignore
                
                if($result_select && $post_id = $result_select->post_id)
                {
                    $order_id = $post_id;
                }
                if(!$order_id)
                {
                    $result_select    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' .$wp_post_meta_table. '` WHERE meta_key = "_op_order_number" AND meta_value=%s', $order_number) ); //phpcs:ignore
                
                    if($result_select && $post_id = $result_select->post_id)
                    {
                        $order_id = $post_id;
                    }
                }
                if(!$order_id)
                {
                    if( is_numeric($order_number) &&  $post = get_post($order_number))
                    {
                        $order_id =  $post->ID;
                    }
                }
            }
            
            
            return apply_filters('op_get_order_id_from_number',$order_id,$order_number);
        }
        public function get_order_id_from_local_id($order_number){
            global $wpdb;
            if($this->_enable_hpos)
            {
                $wp_post_meta_table  = OrdersTableDataStore::get_meta_table_name();
                $result_select    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' .$wp_post_meta_table. '` WHERE meta_key = "_op_local_id" AND meta_value=%s', $order_number) ); //phpcs:ignore
                if($result_select && $post_id = $result_select->order_id)
                {
                    return $post_id;
                }
            }else{
                $wp_post_meta_table = $wpdb->postmeta;
                $result_select    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' .$wp_post_meta_table. '` WHERE meta_key = "_op_local_id" AND meta_value=%s', $order_number) ); //phpcs:ignore
                if($result_select && $post_id = $result_select->post_id)
                {
                    return $post_id;
                }
            }
            
            return 0;
        }
        public function get_order_id_from_order_number_format($order_number){
            global $wpdb;
            if($this->_enable_hpos)
            {
                $wp_post_meta_table  = OrdersTableDataStore::get_meta_table_name();
                $result_select    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' .$wp_post_meta_table. '` WHERE meta_key = "_op_order_number_format" AND meta_value=%s', $order_number) ); //phpcs:ignore
                if($result_select && $post_id = $result_select->order_id)
                {
                    return $post_id;
                }
                $result_select    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' .$wp_post_meta_table. '` WHERE meta_key = "_op_wc_custom_order_number_formatted" AND meta_value=%s', $order_number) ); //phpcs:ignore
                if($result_select && $post_id = $result_select->order_id)
                {
                    return $post_id;
                }
            }else{
                $wp_post_meta_table = $wpdb->postmeta;
                $order_number = strtolower(trim($order_number,'#'));
                $result_select    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' .$wp_post_meta_table. '` WHERE meta_key = "_op_order_number_format" AND LOWER(meta_value)=%s', $order_number) ); //phpcs:ignore
                if($result_select && $post_id = $result_select->post_id)
                {
                    return $post_id;
                }
                $result_select    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' .$wp_post_meta_table. '` WHERE meta_key = "_op_wc_custom_order_number_formatted" AND LOWER(meta_value)=%s', $order_number) ); //phpcs:ignore
                
                if($result_select && $post_id = $result_select->post_id)
                {
                    return $post_id;
                }
            }
            
            return 0;
        }

        public function reset_order_number($order_number_data){
            global $wpdb;
            $order_number = 0;
            if(is_numeric($order_number_data))
            {
                $order_number = $order_number_data;
            }else{
                if(isset($order_number_data['order_number']) && $order_number_data['order_number'])
                {
                    $order_number = $order_number_data['order_number'];
                }
                if(isset($order_number_data['order_id']) && $order_number_data['order_id'])
                {
                    $order_id = $order_number_data['order_id'];
                    if($this->_enable_hpos)
                    {
                        $wp_post_meta_table  = OrdersTableDataStore::get_meta_table_name();
                        $wpdb->query( "DELETE  FROM {$wp_post_meta_table} WHERE meta_key = '_op_wc_custom_order_number'" );
                        $wpdb->query( "DELETE  FROM {$wp_post_meta_table} WHERE meta_key = '_op_wc_custom_order_number_formatted'" );
                    }else{
                        delete_metadata( 'post', $order_id, '_op_wc_custom_order_number', false );
                        delete_metadata( 'post', $order_id, '_op_wc_custom_order_number_formatted', false );
                    }
                    
                }
                
                
            }
            $current_order_number = get_option('_op_wc_custom_order_number',0);
            if(is_numeric($current_order_number))
            {
                $current_order_number = 1 * $current_order_number;
                $order_number = 1 * $order_number;
                if(($current_order_number - $order_number) == 0 && $order_number > 0)
                {
                    update_option('_op_wc_custom_order_number',($order_number - 1));
                }
                
            }
        }
       
        public function op_add_order_item_meta($order_item,$_item_data){
            $product_id = $_item_data['product_id']; 
            $tmp_price = get_post_meta($product_id,'_op_weight_base_pricing',true);
            if($tmp_price == 'yes')
            {
                $options = $_item_data['options'];
                if(!empty($options))
                {
                    $weight = 0;
                    foreach($options as $option)
                    {
                        if(isset($option['option_id']) && $option['option_id'] == 'op_weight')
                        {
                            $weight = array_sum($option['value_id']);
                        }
                    }
                    if($weight > 0)
                    {
                        $order_item->add_meta_data('_op_item_weight' , $weight);

                        $product = wc_get_product($product_id);
                        $product_weight = $product->get_weight();
                        if(floatval($product_weight))
                        {
                            //$new_weight = floatval($product_weight) - $weight;
                            //$product->set_weight($new_weight);
                            //$product->save();
                        }
                    }

                }
                //$tmp_price = 'no';
            }
            
        }
        public function remove_order_items($order,$silent = false){
            
            $source = $order->get_meta('_op_order_source');
            $order_id = $order->get_id();
            $tmp_items = $order->get_items();
            // revert reducted item
            $changes = array();
            
            foreach($tmp_items as $item)
            {
                if($item)
                {
                    if ( ! $item->is_type( 'line_item' ) ) {
                        continue;
                    }
                    $product            = $item->get_product();
                    $item_stock_reduced = $item->get_meta( '_reduced_stock', true );

                    if($source == 'openpos')
                    {
                        //pending outlet order
                    }else{
                      
                        if ( !$item_stock_reduced || ! $product || ! $product->managing_stock() ) {
                            continue;
                        }
                        
                        if($item_stock_reduced)
                        {
                            $qty = 1 * $item_stock_reduced;
                            $new_stock = wc_update_product_stock( $product, $qty, 'increase' ); //revert stock

                            $changes[] = array(
                                'product' => $product,
                                'from'    => $new_stock - $qty,
                                'to'      => $new_stock,
                            );

                            $item->delete_meta_data( '_reduced_stock' );
		                    $item->save();
                        }
                    }
                    

                    
                    
                }
                
            }
            
           
            if(!empty($changes) && !$silent)
            {
                wc_trigger_stock_change_notifications( $order, $changes );
            }
            

            //end
            $order->remove_order_items();
            $order->get_data_store()->set_stock_reduced( $order_id, false );
            return $order;
        }
        public function reGenerateDraftOrder($order_id,$new_order_number = 0,$new_order_format = '',$order_parse_data = array()){   
            global $wpdb;
            $use_hpos = $this->_enable_hpos;
            if($use_hpos)
            {
                $default_args = array(
                    'status'        => 'auto-draft',
                    'customer_id'   => null,
                    'customer_note' => null,
                    'parent'        => null,
                    'created_via'   => null,
                    'cart_hash'     => null,
                    'order_id'      => 0,
                );
                if($order_id)
                {
                    $default_args['order_id'] = $order_id; 
                }
                $order = wc_create_order($default_args);
                if(is_wp_error($order))
                {
                    $default_args['order_id'] = 0;
                    $order = wc_create_order($default_args);
                }
                if($new_order_number){
                    $order->update_meta_data( '_op_wc_custom_order_number', $new_order_number );
                }
                if($new_order_format){
                    $order->update_meta_data( '_op_wc_custom_order_number_formatted', $this->formatOrderNumber($new_order_format,'',$order_parse_data  ) );
                }
                $order->save();
                return $order;
            }else{
                $data = array(
                    'post_status'           => 'auto-draft',
                    'post_type'             => 'shop_order'
                );
                if($order_id)
                {
                    $data['ID'] = $order_id; 
                }
                $table = $wpdb->posts;
                if ( false === $wpdb->insert( $table, $data ) ) {
                    return false;
                }else{
                    $post_id = (int) $wpdb->insert_id;
                  
                    if($new_order_number)
                    {
                        update_post_meta( $post_id, '_op_wc_custom_order_number', $new_order_number );
                    }
                    if($new_order_format)
                    {
                        update_post_meta( $post_id, '_op_wc_custom_order_number_formatted', $new_order_format );
                    }
                    return  get_post($post_id);
                }
            }
        }
        public function remove_draft_cart($cart_id)
        {
            $post = get_post($cart_id);
           
            if($post && $post->post_status == 'auto-draft')
            {
                wp_delete_post($cart_id,true);
            }
        }
        public function default_get_order_number( $session_data ,$update_number = true){
            $result = array('status' => 0, 'message' => '','data' => array());
            try{
                // lock order number
                $post_type = 'shop_order';
                $arg = array(
                    'post_type' => $post_type,
                    'post_status'   => 'auto-draft'
                );
                $next_order_id = wp_insert_post( $arg );
                $next_order_number = $next_order_id;
                update_post_meta($next_order_id,'_op_pos_session',$session_data['session']);
                if($update_number)
                {
                    $next_order_number = $this->update_order_number($next_order_id);
                } 
                if(!$next_order_number)
                {
                    $next_order_number = $next_order_id;
                }
                $setting = isset($session_data['setting']) ? $session_data['setting'] : array();
                $pos_sequential_number_prefix = isset($setting['pos_sequential_number_prefix']) ? ''.$setting['pos_sequential_number_prefix'] : '';
    
                $order_number_info = array(
                    'order_id' => $next_order_id,
                    'order_number' => $next_order_number,
                    'order_number_formatted' => $this->formatOrderNumber($next_order_number,$pos_sequential_number_prefix)
                );
                $result['data'] = apply_filters('op_get_next_order_number_info',$order_number_info);
                $result['status'] = 1;
            }catch (Exception $e)
            {
                $result['status'] = 0;
                $result['message'] = $e->getMessage();
            }
            return $result;
        }
        public function hpos_get_order_number($update_number = true)
        {
            global $op_session_data;
            $result = array('status' => 0, 'message' => '','data' => array());
            try{
            
                $session_data = $op_session_data;
                $use_sql = true;
                $setting = isset($session_data['setting']) ? $session_data['setting'] : array();
                $pos_sequential_number_prefix = isset($setting['pos_sequential_number_prefix']) ? $setting['pos_sequential_number_prefix'] : '';
                if($use_sql)
                {
                    global $wpdb;
                    $post_type = 'shop_order';
                    $arg = array(
                        'post_type' => $post_type,
                        'post_status'   => 'auto-draft'
                    );

                    $next_order_id = wp_insert_post( $arg );
                    $order_table = OrdersTableDataStore::get_orders_table_name();
                    $order_meta_table = OrdersTableDataStore::get_meta_table_name();
                    
                    
                    $sql = $wpdb->prepare(
                        'INSERT INTO ' . $order_table. ' (
                            id,
                            status,
                            type,
                            currency
                            )
                            VALUES
                            ( %d,%s, %s,%s)',
                            $next_order_id,
                            'auto-draft',
                        'shop_order',
                        get_woocommerce_currency()
                        );
                    $wpdb->query($sql);
                    $next_order_number = 0;
                    $session_id = isset($session_data['session']) ? $session_data['session'] : '';
                    $sql = $wpdb->prepare(
                        'INSERT INTO ' . $order_meta_table. ' (
                            order_id,
                            meta_key,
                            meta_value
                            )
                            VALUES
                            ( %d,%s, %s)',
                            $next_order_id,
                            '_op_pos_session',
                            $session_id
                        );
                    $wpdb->query($sql);
                    
                    if($update_number)
                    {
                        $next_order_number = $this->update_order_number($next_order_id,true);
                    }
                    if(!$next_order_number)
                    {
                        $next_order_number = $next_order_id;
                    }

                    $order_number_info = array(
                        'order_id' => $next_order_id,
                        'order_number' => $next_order_number,
                        'order_number_formatted' => $this->formatOrderNumber($next_order_number,$pos_sequential_number_prefix)
                    );
                }else{
                    $default_args = array(
                        'status'        => 'auto-draft',
                        'customer_id'   => null,
                        'customer_note' => null,
                        'parent'        => null,
                        'created_via'   => null,
                        'cart_hash'     => null,
                        'order_id'      => 0,
                    );
    
                    $order = wc_create_order($default_args);
    
                    
                    
                    // lock order number
                    
                    $next_order_id = $order->get_id();
                    $next_order_number = 0;
                    $order->update_meta_data('_op_pos_session',$session_data['session']);
                    $order->save();
                    if($update_number)
                    {
                        $next_order_number = $this->update_order_number($next_order_id,true);
                    }
                    if(!$next_order_number)
                    {
                        $next_order_number = $order->get_order_number();
                    }
                    if(!$next_order_number)
                    {
                        $next_order_number = $next_order_id;
                    }
    
                    $order_number_info = array(
                        'order_id' => $next_order_id,
                        'order_number' => $next_order_number,
                        'order_number_formatted' => $this->formatOrderNumber($next_order_number,$pos_sequential_number_prefix)
                    );
                }
                $result['status'] = 1;
                $result['data'] = apply_filters('op_get_next_order_number_info',$order_number_info);
    
            }catch (Exception $e)
            {
                $result['status'] = 0;
                $result['message'] = $e->getMessage();
            }
            return $result;
        }
        public function woocommerce_webhook_topic_hooks($topic_hooks, $current){
            $topic_hooks['order.created'][] = 'op_add_order_after';
            return $topic_hooks;
        }

        public function op_upload_desk_after($_tables,$op_table,$tables,$_old_tables){
            global $op_session_data;
            $action_source = isset($_REQUEST['source'])  ? $_REQUEST['source'] : '';
            $seller_name = $op_session_data &&  isset($op_session_data['name']) ? $op_session_data['name'] : '';
            if(!$seller_name)
            {
                $seller_name = $op_session_data &&  isset($op_session_data['username']) ? $op_session_data['username'] : '';
            }
            
            foreach($_tables as $key => $table)
            {
                if($action_source == 'clear_desk')
                {
                    $old_table = isset($_old_tables[$key]) ? $_old_tables[$key] : array();
                    $source_type = isset($old_table['source_type']) ? $old_table['source_type'] : '';
                    $source_details = isset($old_table['source_details']) ? $old_table['source_details'] : '';
                    if($source_type == 'order_takeaway' || $source_type == 'order_desk'){
                        $order_id = isset($source_details['order_id']) ? $source_details['order_id'] : 0;
                        if($order_id){
                            $order = wc_get_order($order_id);
                            if($order)
                            {
                                $order->add_order_note(sprintf(__('Order has been remove out of kitchen by %s','openpos'),$seller_name));
                                $order->delete_meta_data('_op_kitchen_state');
                                $order->save();
                            }
                        }
                    }
                }else{
                    $source_type = isset($table['source_type']) ? $table['source_type'] : '';
                    $source_details = isset($table['source_details']) ? $table['source_details'] : '';
                    
                    if($source_type == 'order_takeaway' || $source_type == 'order_desk'){
                        $order_id = isset($source_details['order_id']) ? $source_details['order_id'] : 0;
                        if($order_id){
                            $order = wc_get_order($order_id);
                            if($order)
                            {
                                $_op_kitchen_state = $order->get_meta('_op_kitchen_state');
                                if(!$_op_kitchen_state)
                                {
                                    
                                    $order->add_order_note(sprintf(__('Order has been sent to kitchen by %s','openpos'),$seller_name));
                                    $order->add_meta_data('_op_kitchen_state','sent_kitchen');
                                    $order->save();
                                }
                            }
                        }
                    }
                }
                

            }
        }

        public function op_get_customer_debit_total($user_id) {
            $laybuy_order_status = $this->_core->getPosLayBuyOrderStatus();
            $args = array(
                'customer_id' => $user_id, // hoặc bỏ nếu muốn lấy tất cả khách
                'status'      => array( $laybuy_order_status), // tuỳ ý
                'limit'       => -1,
                'return'      => 'ids',
                'meta_query'  => array(
                    array(
                        'key'   => '_op_allow_laybuy',
                        'value' => 'yes',
                    ),
                ),
            );
            $orders = wc_get_orders($args);
            $total = 0;
            foreach ($orders as $order_id) {
                $order = wc_get_order($order_id);
                $remain =  $order->get_meta('_op_remain_paid', true);
                if($remain)
                {
                    $total += $remain ? floatval($remain) : 0;
                }
                
            }
            return $total;
        }

        public function woocommerce_account_dashboard(){
            $user_id = get_current_user_id();
            if (!$user_id) return;

            $allow_laybuy = $this->settings_api->get_option('pos_laybuy','openpos_pos') == 'yes' ? true : false;

            if(!$allow_laybuy)
            {
                return;
            }

            // Example: Get total debit from user meta or your custom logic
            // Replace this with your actual logic to get the debit total
            $debit_total = $this->op_get_customer_debit_total($user_id);// get_user_meta($user_id, '_op_debit_total', true);
            if ($debit_total === '') $debit_total = 0;

            // Format as currency
            $debit_total_formatted = wc_price($debit_total);

            echo '<div class="woocommerce-MyAccount-debit-total" style="margin:20px 0;padding:15px;background:#f8f8f8;border:1px solid #eee;border-radius:6px;">';
            echo '<strong>' . esc_html__('Your Debit Total:', 'openpos') . '</strong> ';
            if($debit_total > 0)
            {
                $orders_url = add_query_arg(
                    array('order-type' => 'debit'), 
                    wc_get_account_endpoint_url('orders')
                );
                echo  '<a href="' . esc_url( $orders_url ) . '" style="color:#d32f2f;font-weight:bold;">' . '<span style="color:#d32f2f;font-weight:bold;">' . $debit_total_formatted . '</span>' . '</a>';  
            }else{
                echo '<span style="color:#d32f2f;font-weight:bold;">' . $debit_total_formatted . '</span>';
            }
            
            echo '</div>';
        }
        public function woocommerce_my_account_my_orders_query($args){
            $order_type = isset($_REQUEST['order-type']) ? $_REQUEST['order-type'] : '';
            if($order_type == 'debit')
            {
                $laybuy_order_status = $this->_core->getPosLayBuyOrderStatus();
                $args['status'] = array($laybuy_order_status);
                $args['meta_query']  = array(
                    array(
                        'key'   => '_op_allow_laybuy',
                        'value' => 'yes',
                    ),
                );
            }
            return $args;
        }
        public function woocommerce_my_account_my_orders_actions($actions, $order){
            
            $laybuy_order_status = $this->_core->getPosLayBuyOrderStatus();
            if($order->get_status() == $laybuy_order_status)
            {
                $_op_allow_laybuy = $order->get_meta('_op_allow_laybuy', true);
                if($_op_allow_laybuy == 'yes')
                {
                    
                    unset($actions['cancel']);
                    unset($actions['pay']);
                    return $actions;
                }
                
            }
            
            return $actions;
        }
        public function woocommerce_order_details_after_order_table($order){
            
            $remain =  $order->get_meta('_op_remain_paid', true);
            if(!$remain || $remain <= 0)
            {
                return;
            }
            ?>
            <table class="woocommerce-table shop_table order_details">
            <tr>
                <th><?php echo __('Debit amount'); ?></th>
                <td><span style="color:#d32f2f;font-weight:bold;"><?php echo wc_price($remain); ?></span></td>
            </tr>
            </table>
            <?php
        }
        public function get_order_number($session_data ,$allow_hpos ,$update_number = true){
            if($allow_hpos){
                return $this->hpos_get_order_number();
            }else{
                return $this->default_get_order_number($session_data,$update_number);
            }
        }
        private function batch_load_products($product_ids) {
            global $op_woo;
            $product_ids = array_filter(array_unique($product_ids));
            
            if (empty($product_ids)) {
                return array();
            }
            
            // Prime post cache
            _prime_post_caches($product_ids, false, true);
            
            // Load products into cache
            $products = array();
            foreach ($product_ids as $pid) {
                if(!$pid){
                    continue;
                }
                $post = get_post($pid);
                if ($post) {
                    
                    $tmp = array(
                        'post' => $post,
                        'product' => wc_get_product($pid)
                    );
                    $current_post_price = $op_woo->get_cost_price($pid);
                    if($current_post_price !== false)
                    {
                        $tmp['cost_price'] = $current_post_price;
                       
                    }
                    $products[$pid] = $tmp;
                }
            }
            
            return $products;
        }
        private function _process_order_items($order,$order_parse_data,$session_data){
            global $op_woo;
            $order_local_id = isset($order_parse_data['id']) ? $order_parse_data['id'] : '';
            $lock_group = "order_lock_{$order_local_id}";
            $sale_person_id = isset($order_parse_data['sale_person']) ? intval($order_parse_data['sale_person']) : 0;
            $discount_code = isset($order_parse_data['discount_code']) ? $order_parse_data['discount_code'] : '';
           
            $discount_codes = isset($order_parse_data['discount_codes']) ? $order_parse_data['discount_codes'] : array();
            $items = isset($order_parse_data['items']) ? $order_parse_data['items'] : array();
            $product_ids = array_column($items, 'product_id');
            $products_cache = $this->batch_load_products($product_ids);
            $order_id = $order->get_id();
            foreach($items as $_itm)
            {
                $_item = apply_filters('op_order_item_data_before',$_itm,$order_parse_data);
                $item_seller_id = isset($_item['seller_id']) ? $_item['seller_id'] : $sale_person_id;
                $item_local_id = isset($_item['id']) ? $_item['id'] : 0;
                $disable_qty_change = isset($_item['disable_qty_change']) ? $_item['disable_qty_change'] : false;

                $lock_key = "order_item_lock_{$item_local_id}";
                
                $item = new WC_Order_Item_Product();
                if($disable_qty_change && $item_local_id)
                {
                    $_tmp_item_order_id = wc_get_order_id_by_order_item_id($item_local_id);
                    if($_tmp_item_order_id == $order_id)
                    {
                        $item->set_id($item_local_id);
                    }
                }
                
                $item_options = isset($_item['options']) ? $_item['options'] : array();
                $item_bundles = isset($_item['bundles']) ? $_item['bundles'] : array();
                $item_variations = isset($_item['variations']) ? $_item['variations'] : array();

                $item_note = (isset($_item['note']) && $_item['note'] != null && strlen($_item['note']) > 0 )  ? $_item['note'] : '';
                $item_sub_name = (isset($_item['sub_name']) && $_item['sub_name'] != null && strlen($_item['sub_name']) > 0 )  ? $_item['sub_name'] : '';
                do_action('op_add_order_item_meta',$item,$_item);
                $v_product_id = $_item['product_id'];
            
                if(isset($_item['product_id']) && $_item['product_id'])
                {
                    $product_id = $_item['product_id'];
                    $product_data = $products_cache[$product_id] ?? null;
                    if (!$product_data)
                    {
                        $v_product_id = 0;
                    }else{
                        $post = $product_data['post'];
                        //$product = $product_data['product'];
                        if($post && $post->post_type == 'product_variation')
                        {
                            $product_id = 0;
                            if( $post->post_parent)
                            {
                                $product_id = $post->post_parent;
                                $item->set_variation_id($_item['product_id']);

                                $variation_product = $product_data['product'];

                                $_item_variations = $item_variations;

                                if(isset($_item_variations['options']) && !empty($_item_variations['options']))
                                {
                                    $_item_variation_options = $_item_variations['options'];

                                    foreach($_item_variation_options as $vcode => $v_val)
                                    {
                                        $v_name = str_replace( 'attribute_', '', $vcode );
                                        $label = isset($v_val['value_label']) ? $v_val['value_label'] : '';
                                        if($label)
                                        {
                                            $item->add_meta_data($v_name,$label);
                                        }
                                    }
                                }else{
                                    $v_attributes = $variation_product->get_variation_attributes();
                                    if($v_attributes && is_array($v_attributes))
                                    {
                                        foreach($v_attributes as $vcode => $v_val)
                                        {
                                            $v_name = str_replace( 'attribute_', '', $vcode );
                                            $item->add_meta_data($v_name,$v_val);
                                        }
                                    }
                                }
                            }
                        }
                        if($post && $product_id)
                        {
                            $item->set_product_id($product_id);
                        }
                    }
                    
                }
                $item->set_name($_item['name']);
                $item->set_quantity($_item['qty']);

                $item_product = false;
                if(isset($_item['product']))
                {
                    $item_product = $_item['product'];
                }

                $final_price = $_item['final_price'];
                $final_price_incl_tax = $_item['final_price_incl_tax'];
                $item_total_tax = $_item['total_tax'];
                //new
                $_item_final_discount_amount = $_item['final_discount_amount'];

                //$item->set_total_tax($item_total_tax);
                $item_tax_amount = $final_price_incl_tax - $final_price;
                
                $item->set_props(
                    array(
                        'price' => $final_price,
                        'custom_price' => $final_price,
                        'discount_amount' => $_item_final_discount_amount,
                        'final_discount_amount' => $_item_final_discount_amount,
                        'discount_type' => $_item['discount_type'],
                        'total_tax' => $item_total_tax,
                        'tax_amount' => $item_tax_amount,
                    )
                );

                if($v_product_id)
                {
                    //set current cost price
                    $current_post_price =  isset($products_cache[$v_product_id]) && isset($products_cache[$v_product_id]['cost_price']) ?  $products_cache[$v_product_id]['cost_price'] : false;
                    // $op_woo->get_cost_price($v_product_id);
                    if($current_post_price !== false)
                    {
                        $item->add_meta_data( '_op_cost_price', $current_post_price);
                    }
                }
                if($item_sub_name)
                {
                    $item->add_meta_data( 'op_item_details', $item_sub_name);
                }

                $item->add_meta_data( '_op_local_id', $_item['id']);

                $item->add_meta_data( '_op_seller_id', $item_seller_id);

                if(!empty($item_options))
                {
                    $item->add_meta_data( '_op_item_options', $item_options);
                }
                if(!empty($item_bundles))
                {
                    $item->add_meta_data( '_op_item_bundles', $item_bundles);
                }
                if(!empty($item_variations))
                {
                    $item->add_meta_data( '_op_item_variations', $item_variations);
                }

                

                foreach($item_options as $op)
                {
                    $meta_key = $op['title'];
                    $meta_value = implode(',',$op['value_id']);
                    if($op['cost'])
                    {
                        $meta_value .= ' ('.wc_price($op['cost']).')';
                    }

                    $item->add_meta_data($meta_key , $meta_value);
                }
                if($item_note)
                {
                    $item->add_meta_data('note' , $item_note);
                }

                $item_sub_total = $_item['qty'] * $_item['final_price'];

                $item->set_total_tax($item_total_tax);

                $item_total_before_discount = $_item['final_price'] * (1 * $_item['qty']);
                
                $item_total = $_item['total'];
                //coupon
                if($discount_code)
                {
                    foreach($discount_codes as $_discount_code)
                    {
                        if(isset($_discount_code['applied_items']) && isset($_discount_code['applied_items'][$item_local_id]))
                        $item_discount_code_amount = 1 * $_discount_code['applied_items'][$item_local_id];
                        if($item_discount_code_amount)
                        {
                            $item_total -= $item_discount_code_amount;
                        }
                    }
                    
                }

                $item->set_subtotal($item_total_before_discount);

                $item->set_total($item_total);

                if(isset($_item['subtotal']))
                {
                    $item->set_subtotal($_item['subtotal']);
                }

                $item->add_meta_data( '_op_item_data_details', $_item);
                // item tax
                $item_taxes = array();
                if(isset($_item['tax_details']) && !empty($_item['tax_details']))
                {
                    foreach($_item['tax_details'] as $item_tax_detail)
                    {
                        $item_tax_class = '';
                        $tax_class_code = $item_tax_detail['code'];
                        $tax_rate_id = isset($item_tax_detail['rate_id']) ? $item_tax_detail['rate_id'] : -1;
                        $tmp_code = explode('_',$tax_class_code);
                        if(count($tmp_code) == 2)
                        {
                            $tmp_tax_class = $tmp_code[0];
                            if($tax_rate_id < 0)
                            {
                                $tax_rate_id = $tmp_code[1];
                            }
                            if($tmp_tax_class != 'standard')
                            {
                                $item_tax_class = $tmp_tax_class;
                            }
                        }
                    
                        $item->set_total_tax($item_tax_detail['total']);
                        

                        $item->set_tax_class($item_tax_class);
                        
                        if($tax_rate_id >= 0 )
                        {
                            $item_taxes['total'][$tax_rate_id] = $item_tax_detail['total'];
                            $item_taxes['subtotal'][$tax_rate_id] = $item_tax_detail['total'];//$item_total_tax_before_discount;
                        }

                    }
                }
            
                if(!empty($item_taxes))
                {
                    $item->set_taxes($item_taxes);
                }
                //end item tax
                $final_item = apply_filters('op_order_item_data',$item,$_item,$order);

                $found = false;
                $cached_item = wp_cache_get( $lock_key, $lock_group, false, $found );
                if ( ! $found ) {
                    $item->save();
                    $order->add_item($final_item);
                    wp_cache_set($lock_key, $item, $lock_group, 120);
                    do_action('op_add_order_item_after',$order,$item,$_item,$session_data);
                }else{
                    $clone_item = clone $cached_item;
                    $order->add_item($clone_item);
                    do_action('op_add_order_item_after',$order,$clone_item,$_item,$session_data);
                }
            }
            return $order;
        }
        private function _process_order_fees($order,$order_parse_data,$session_data){
            $fee_items = isset($order_parse_data['fee_items']) ? $order_parse_data['fee_items'] : array();
            if(!empty($fee_items))
            {
                foreach($fee_items as $_fee_item)
                {

                    $fee_amount = 1 * $_fee_item['total'];
                    $fee_item = new WC_Order_Item_Fee();
                    $fee_item->set_name($_fee_item['name']);
                    $fee_item->set_total($fee_amount);
                    $fee_item->set_amount($fee_amount);

                    $item_taxes = array();
                    if(isset($_fee_item['tax_details']) && !empty($_fee_item['tax_details']))
                    {
                        foreach($_fee_item['tax_details'] as $item_tax_detail)
                        {
                            $item_tax_class = '';
                            $tax_class_code = $item_tax_detail['code'];
                            $tmp_code = explode('_',$tax_class_code);
                            if(count($tmp_code) == 2)
                            {
                                $tmp_tax_class = $tmp_code[0];
                                if($tmp_tax_class != 'standard')
                                {
                                    $item_tax_class = $tmp_tax_class;
                                }
                            }
                        
                            $fee_item->set_total_tax($item_tax_detail['total']);
                            

                            $fee_item->set_tax_class($item_tax_class);
                            
                            if(isset($item_tax_detail['rate_id']) && $item_tax_detail['rate_id'] != '' && $item_tax_detail['rate_id'] >= 0 )
                            {
                                $item_taxes['total'][$item_tax_detail['rate_id']] = $item_tax_detail['total'];
                                $item_taxes['subtotal'][$item_tax_detail['rate_id']] = $item_tax_detail['total'];
                            }
                        

                        }
                    }
                    if(!empty($item_taxes))
                    {
                        $fee_item->set_taxes($item_taxes);
                    }

                    
                    #$fee_item->set_total_tax(0);
                    
                    $fee_item->add_meta_data( '_op_local_id',$_fee_item['id']);
                    $fee_item->add_meta_data( '_pos_item_type','cart_fee');
                    $fee_item->add_meta_data( '_op_item_data_details', $_fee_item);
                    
                    $order->add_item($fee_item);
                }
            }
            return $order;
        }
        private function _process_order_coupons($order,$order_parse_data,$session_data){
            $discount_code = isset($order_parse_data['discount_code']) ? $order_parse_data['discount_code'] : '';
            $discount_code_amount = isset($order_parse_data['discount_code_amount']) ? floatval($order_parse_data['discount_code_amount']) : 0;
            $discount_code_tax_amount = isset($order_parse_data['discount_code_tax_amount']) ? floatval($order_parse_data['discount_code_tax_amount']) : 0;
            $discount_code_excl_tax = isset($order_parse_data['discount_code_excl_tax']) ? floatval($order_parse_data['discount_code_excl_tax']) : ( $discount_code_amount - $discount_code_tax_amount);
            $discount_codes = isset($order_parse_data['discount_codes']) ? $order_parse_data['discount_codes'] : array();
            if($discount_code)
            {
                foreach($discount_codes as $_discount_code)
                {
                    $_discount_code_total = $_discount_code['total'];
                    $_discount_code_tax = $_discount_code['tax'];
                    $coupon_item = new WC_Order_Item_Coupon();
                    $coupon_item->set_code($_discount_code['code']);
                    $coupon_item->set_discount($_discount_code_total);
                    $coupon_item->set_discount_tax($_discount_code_tax);
                    $order->add_item($coupon_item);
                }
                do_action('op_add_order_coupon_after',$order,$order_parse_data,$discount_code,$discount_code_amount);

            }
            return $order;
        }
        private function _process_order_customer($order,$order_parse_data,$session_data){
            global $op_woo;
            global $op_warehouse;
            $use_hpos = $this->_core->enable_hpos();
            $customer_id = 0;
            $has_shipping = false;
            $login_warehouse_id = isset($session_data['login_warehouse_id']) ? $session_data['login_warehouse_id'] : 0;
            if(isset($order_parse_data['add_shipping']) && $order_parse_data['add_shipping'] == true)
            {
                $has_shipping = true;
            }    
            $customer = isset($order_parse_data['customer']) ? $order_parse_data['customer'] : array();
            $customer_email = isset($customer['email']) ? $customer['email'] : '';
            $customer_firstname = isset($customer['firstname']) ? $customer['firstname'] : '';
            $customer_lastname = isset($customer['lastname']) ? $customer['lastname'] : '';
            $customer_name = isset($customer['name']) ? $customer['name'] : '';
            $shipping_information = isset($order_parse_data['shipping_information']) ? $order_parse_data['shipping_information'] : array();

            $shipping_first_name  = '';
            $shipping_last_name = '';

            if(!$customer_firstname && !$customer_lastname && $customer_name)
            {
                $name = trim($customer_name);
                $tmp = explode(' ',$name);
                if(count($tmp) > 0)
                {
                    $customer_firstname = $tmp[0];
                    $customer_lastname = substr($name,strlen($customer_firstname));
                }
            }
            if(!empty($customer) && isset($customer['id']))
            {
                $customer_id = $customer['id'];
                
                if($customer_id == 0 || !$customer_id)
                {
                    if($customer_email && $customer_email != null)
                    {
                        $customer_user = get_user_by('email',$customer_email);
                        if($customer_user)
                        {
                            $customer_id = $customer_user->get('ID');
                        }
                    }
                    if($customer_id == 0 && isset($customer['create_customer']) && $customer['create_customer'] == 1)
                    {
                        $tmp_create_customer = $op_woo->_add_customer($customer,$session_data);
                        $customer_id =  $tmp_create_customer['data'];
                        //create new customer
                    }
                }
            }
            if(isset($customer['addition_data']) && is_array($customer['addition_data']))
            {
                foreach($customer['addition_data'] as $addition_data_key => $addition_data_value)
                {
                    $customer[$addition_data_key] = $addition_data_value;
                }
            }
            $default_country = $op_woo->getDefaultContry();
            if($customer_firstname)
            {
                $order->set_billing_first_name($customer_firstname);
            }
            if($customer_lastname)
            {
                $order->set_billing_last_name($customer_lastname);
            }
            if(isset($customer['company']) && $customer['company'])
            {
                $order->set_billing_company($customer['company']);
            }
            if(isset($customer['address']) && $customer['address'])
            {
                $order->set_billing_address_1($customer['address']);
            }
            if(isset($customer['email']) && $customer['email'])
            {
                $order->set_billing_email($customer['email']);
            }
            if(isset($customer['phone']) && $customer['phone'])
            {
                $order->set_billing_phone($customer['phone']);
            }

            if(isset($customer['address_2']) && $customer['address_2'] != null)
            {
                $order->set_billing_address_2($customer['address_2']);
            }
            if(isset($customer['state']) && $customer['state'] != null)
            {
                $order->set_billing_state($customer['state']);
            }
            if(isset($customer['city']) && $customer['city'] != null)
            {
                $order->set_billing_city($customer['city']);
            }
            if(isset($customer['postcode']) && $customer['postcode'] != null)
            {
                $order->set_billing_postcode($customer['postcode']);
            }
            // country
            $billing_country = '';
            if(isset($customer['country']) && $customer['country'] != null)
            {
                $billing_country = $customer['country'];

            }
            if(!$billing_country)
            {
                $billing_country = $default_country;
            }
            if($billing_country)
            {
                $order->set_billing_country($billing_country);
            }

            if($has_shipping)
            {
                $order->set_shipping_first_name($shipping_first_name);
                $order->set_shipping_last_name($shipping_last_name);


                if(isset($shipping_information['address']))
                {
                    $order->set_shipping_address_1($shipping_information['address']);
                }

                if(isset($shipping_information['company']) && $shipping_information['company'])
                {
                    $order->set_shipping_company($shipping_information['company']);
                }

                if(isset($shipping_information['address_2']))
                {
                    $order->set_shipping_address_2($shipping_information['address_2']);
                }
                if(isset($shipping_information['city']))
                {
                    $order->set_shipping_city($shipping_information['city']);
                }
                // default contry
                $shipping_country = '';
                if(isset($shipping_information['country']) && $shipping_information['country'] != null)
                {
                    $shipping_country = $shipping_information['country'];

                }
                if(!$shipping_country)
                {
                    $shipping_country = $default_country;
                }
                if($shipping_country)
                {
                    $order->set_shipping_country($shipping_country);
                }
                //end default country

                if(isset($shipping_information['state']))
                {
                    $order->set_shipping_state($shipping_information['state']);
                }
                if(isset($shipping_information['postcode']))
                {
                    $order->set_shipping_postcode($shipping_information['postcode']);
                }

                if(isset($shipping_information['phone']))
                {
                    

                    if($use_hpos)
                    {
                        $order->update_meta_data( '_pos_shipping_phone', $shipping_information['phone'] );
                        $order->update_meta_data( 'shipping_phone', $shipping_information['phone'] );
                        
                    }else{
                        update_post_meta($order->get_id(),'_pos_shipping_phone',$shipping_information['phone']);
                        update_post_meta($order->get_id(),'shipping_phone',$shipping_information['phone']);
                    }  
                    

                    $order->set_shipping_phone($shipping_information['phone']);
                }
            }else{
                $use_store_address =  apply_filters('op_order_use_store_shipping_address',true,$order_parse_data);
                if($use_store_address)
                {
                    $store_address = $op_warehouse->getStorePickupAddress($login_warehouse_id);
                    $order->set_shipping_first_name($shipping_first_name);
                    $order->set_shipping_last_name($shipping_last_name);


                    if(isset($store_address['address_1']))
                    {
                        $order->set_shipping_address_1($store_address['address_1']);
                    }

                    if(isset($store_address['address_2']))
                    {
                        $order->set_shipping_address_2($store_address['address_2']);
                    }
                    if(isset($store_address['city']))
                    {
                        $order->set_shipping_city($store_address['city']);
                    }

                    if(isset($store_address['state']))
                    {
                        $order->set_shipping_state($store_address['state']);
                    }
                    if(isset($store_address['postcode']))
                    {
                        $order->set_shipping_postcode($store_address['postcode']);
                    }
                    if(isset($store_address['country']))
                    {
                        $order->set_shipping_country($store_address['country']);
                    }
                }
                

            }
            

            return $order;
        }
        private function _process_order_meta($order,$order_parse_data,$session_data,$addition_meta= array()){
            global $op_warehouse,$op_register;
            $use_hpos = $this->_core->enable_hpos();
            $login_cashdrawer_id = isset($session_data['login_cashdrawer_id']) ? $session_data['login_cashdrawer_id'] : 0;
            $login_warehouse_id = isset($session_data['login_warehouse_id']) ? $session_data['login_warehouse_id'] : 0;
            if(!$login_cashdrawer_id)
            {
                if(isset($order_parse_data['register']['id']))
                {
                    $login_cashdrawer_id = $order_parse_data['register']['id'];
                }
            }
            $cashier_id = $session_data['user_id'];
            $_get_session_id = isset($_REQUEST['session']) ? trim($_REQUEST['session']) : '';
            $session_id = isset($order_parse_data['session']) ? $order_parse_data['session'] : $_get_session_id ;
            $order_id = isset($order_parse_data['order_id']) ? $order_parse_data['order_id'] : 0;
            $order_local_id = isset($order_parse_data['id']) ? $order_parse_data['id'] : '';
            $order_number_format = isset($order_parse_data['order_number_format']) ? $order_parse_data['order_number_format'] : '';
            $order_number_details = isset($order_parse_data['order_number_details']) ? $order_parse_data['order_number_details'] : array();
            if(isset($order_number_details['order_id']) && $order_number_details['order_id'] &&  $order_id != $order_number_details['order_id']  )
            {
                $order_id = $order_number_details['order_id'];
            }
            $sale_person_id = isset($order_parse_data['sale_person']) ? intval($order_parse_data['sale_person']) : 0;
            $sale_person_name = isset($order_parse_data['sale_person_name']) ? $order_parse_data['sale_person_name'] : '';
            

            $created_at = isset($order_parse_data['created_at']) ? $order_parse_data['created_at'] : current_time( 'timestamp', true );
            $created_at_time = isset($order_parse_data['created_at_time']) ? $order_parse_data['created_at_time'] : time() * 1000;
            $store_id = isset($order_parse_data['store_id']) ? intval($order_parse_data['store_id']) : 0;
            $tip = isset($order_parse_data['tip']) ? $order_parse_data['tip'] : array();
            $source = isset($order_parse_data['source']) ? $order_parse_data['source'] : '';
            $source_type = isset($order_parse_data['source_type']) ? $order_parse_data['source_type'] : '';
            $guest_number = 0;
            if($source_type == 'desk'){
                $guest_number = isset($source['guest_count']) ? intval($source['guest_count']) : 1;
            }
            $point_discount = isset($order_parse_data['point_discount']) ? $order_parse_data['point_discount'] : array();
            $email_receipt = isset($order_parse_data['email_receipt']) ? $order_parse_data['email_receipt'] : 'no';
           

            $order_meta = $addition_meta;
            if($order_id)
            {
                $order_meta[] = array(
                    'key'   => '_pos_order_id',
                    'value' => $order_id,
                );
                
            }
            $order_meta[] = array(
                'key'   => 'sale_person_name',
                'value' => $sale_person_name,
            ) ;
            $order_meta[] = array(
                'key'   => 'pos_created_at',
                'value' => $created_at,
            );
            $order_meta[] = array(
                'key'   => 'pos_created_at_time',
                'value' => $created_at_time
            );
            if($guest_number > 0)
            {
                $order_meta[] =array(
                    'key'   => '_op_guest_number',
                    'value' => $guest_number
                );
            }
            if(isset($order_parse_data['addition_information']))
            {
                
                $order_meta[] = array(
                    'key'   => '_op_order_addition_information',
                    'value' => $order_parse_data['addition_information'],
                );
            }
            
            $order_meta[] = array(
                'key'   => '_op_order',
                'value' => $order_parse_data,
            );
            $order_meta[] = array(
                'key'   => '_op_order_source',
                'value' => 'openpos',
            );
            $order_meta[] = array(
                'key'   => '_wc_order_attribution_source_type',
                'value' => 'utm',
            );
            $order_meta[] = array(
                'key'   => '_wc_order_attribution_utm_source',
                'value' => 'openpos',
            );
            $order_meta[] =array(
                'key'   => '_op_local_id',
                'value' => $order_local_id,
            );
            $order_meta[] =array(
                'key'   => '_op_sale_by_person_id',
                'value' => $sale_person_id,
            );
           
            $order_meta[] = array(
                'key'   => '_op_wc_custom_order_number_formatted',
                'value' => $order_number_format,
            );
            $order_meta[] = array(
                'key'   => '_op_point_discount',
                'value' => $point_discount,
            );
            $order_meta[] =array(
                'key'   => '_op_sale_by_cashier_id',
                'value' => $cashier_id,
            );

            $warehouse_meta_key = $op_warehouse->get_order_meta_key();
            $cashdrawer_meta_key = $op_register->get_order_meta_key();

            $order_meta[] = array(
                'key'   => '_op_sale_by_store_id',
                'value' => $store_id,
            );
            $order_meta[] = array(
                'key'   => '_op_email_receipt',
                'value' => $email_receipt,
            );
            $order_meta[] =array(
                'key'   => $warehouse_meta_key,
                'value' => $login_warehouse_id,
            );
            $order_meta[] = array(
                'key'   => $cashdrawer_meta_key,
                'value' => $login_cashdrawer_id,
            );
            if($session_id )
            {
                $order_meta[] =array(
                    'key'   => '_op_session_id',
                    'value' => $session_id,
                );
                
            }
            if($order_number_format)
            {
                $order_number_format = trim($order_number_format,'#');
                $order_number_format = trim( $order_number_format);

                $order_meta[] = array(
                    'key'   => '_op_order_number_format',
                    'value' => $order_number_format,
                );
            
            }
            $order_meta[] =array(
                'key'   => '_op_source_type',
                'value' => $source_type,
            );
            $order_meta[] = array(
                'key'   => '_op_source',
                'value' => $source,
            );
            

            if($tip && isset($tip['total']) && $tip['total'])
            {
                $order_meta[] = array(
                    'key'   => '_op_tip',
                    'value' => $tip,
                );
                
            }

            if($use_hpos)
            {
                foreach($order_meta as $meta)
                {
                    $order->update_meta_data( $meta['key'], $meta['value'] );
                }
            }else{
                foreach($order_meta as $meta)
                {
                    update_post_meta($order->get_id(), $meta['key'], $meta['value'] );
                }
            }
            return $order;
        }

        private function _find_existing_order($order_parse_data,$session_data,$is_clear = false,$order_source=''){
           
            $use_hpos = $this->_core->enable_hpos();
            $continue_order_status = $this->settings_api->get_option('pos_continue_checkout_order_status','openpos_general');
           
            $login_cashdrawer_id = isset($session_data['login_cashdrawer_id']) ? $session_data['login_cashdrawer_id'] : 0;
            if(!$login_cashdrawer_id)
            {
                if(isset($order_parse_data['register']['id']))
                {
                    $login_cashdrawer_id = $order_parse_data['register']['id'];
                }
            }
            $order_id = isset($order_parse_data['order_id']) ? $order_parse_data['order_id'] : 0;
            $order_local_id = isset($order_parse_data['id']) ? $order_parse_data['id'] : '';
            $order_number_details = isset($order_parse_data['order_number_details']) ? $order_parse_data['order_number_details'] : array();
            if(isset($order_number_details['order_id']) && $order_number_details['order_id'] &&  $order_id != $order_number_details['order_id']  )
            {
                $order_id = $order_number_details['order_id'];
            }
            $orders = array();
            if($order_id)
            {
                
                if($order_id == $order_local_id)
                {
                    $_tmp_order = wc_get_order($order_id);
                    if($_tmp_order){
                        $orders[] = $_tmp_order;
                    }
                }
                if(empty($orders))
                {
                    $post_type = 'shop_order';
                    //start check order exist
                    $args = array(
                        'post_type' => $post_type,
                        'post_status' => 'any',
                        'meta_query' => array(
                            array(
                                'key' => '_pos_order_id',
                                'value' => $order_id,
                                'compare' => '=',
                            )
                        )
                    );
                    if($use_hpos)
                    {
                        $args['_query_src'] = 'op_order_query';
                        $data_store = WC_Data_Store::load( 'order' );
                        $orders = $data_store->query( $args );
                        // hpos here
                    }else{
                        $query = new WP_Query($args);
                        $orders = $query->get_posts();
                    }
                }
            }
            if(empty($orders))
            {
                $post_type = 'shop_order';
                //start check order exist
                $args = array(
                    'post_type' => $post_type,
                    'post_status' => 'any',
                    'meta_query' => array(
                        array(
                            'key' => '_op_local_id',
                            'value' => $order_local_id,
                            'compare' => '=',
                        )
                    )
                );
                if($use_hpos)
                {
                    $args['_query_src'] = 'op_order_query';
                    $data_store = WC_Data_Store::load( 'order' );
                    $orders = $data_store->query( $args );
                }else{
                    $query = new WP_Query($args);
                    $orders = $query->get_posts();
                }
                
            }
            
            foreach($orders as $o)
            {
                $is_abandoned = false;
                if ( $o instanceof WC_Order )
                {
                    $post_status = $o->get_status();
                    $from_sesison = $o->get_meta('_op_pos_session');
                    $from_source = $o->get_meta('_op_order_source');
                    if($from_sesison && $from_source != 'openpos' )
                    {
                        $is_abandoned = true;
                    }
                }else{
                    $post_status = $o->post_status;
                    $from_sesison = get_post_meta($o->ID,'_op_pos_session',true);
                    $from_source = get_post_meta($o->ID,'_op_order_source',true);
                    if($from_sesison && $from_source != 'openpos' )
                    {
                        $is_abandoned = true;
                    }
                }
                if(is_array($continue_order_status) && !empty($continue_order_status)){
                    if(strpos($post_status,'wc-') === false)
                    {
                    $post_status = 'wc-'.$post_status;
                    }
                    if(in_array($post_status , $continue_order_status))
                    {
                        $is_clear = true; 
                    }
                }
                if($is_abandoned)
                {
                    $is_clear = true; 
                }

            }
            $orders = apply_filters('op_check_order_data',$orders,$order_parse_data,$session_data);
            if(empty($orders) || $is_clear )
            {
                return false;
            }else{
                $post_order = end($orders);
                if($post_order instanceof WC_Order)
                {
                    return $post_order;   
                }
                return wc_get_order($post_order->ID);
            }
            
        }
        private function _process_order_cart_discount($order,$order_parse_data,$session_data){
            $discount_excl_tax = isset($order_parse_data['discount_excl_tax']) ? floatval($order_parse_data['discount_excl_tax']) : 0;
            $discount_final_amount = isset($order_parse_data['discount_final_amount']) ? floatval($order_parse_data['discount_final_amount']) : 0;
            $discount_tax_details = isset($order_parse_data['discount_tax_details']) ? $order_parse_data['discount_tax_details'] : array();
            if($discount_final_amount > 0)
            {
                $cart_discount_item = new WC_Order_Item_Fee();

                $cart_discount_item->set_name(__('POS Cart Discount','openpos'));
                $cart_discount_item->set_amount(0 - $discount_excl_tax);
                $cart_discount_item_taxes_total = array();
                $cart_discount_item_taxes_subtotal = array();
                $discount_taxes = array();
                $item_total_tax = 0;
                foreach($discount_tax_details as $discount_tax_detail){
                    $tax_class_code = $discount_tax_detail['code'];
                    $item_total_tax += $discount_tax_detail['total'];
                    if(isset($discount_taxes[$tax_class_code])){
                        $discount_taxes[$tax_class_code] += $discount_tax_detail['total'];
                    }else{
                        $discount_taxes[$tax_class_code] = $discount_tax_detail['total'];
                    }
                }
                $cart_discount_item->set_total_tax( 0 - $item_total_tax);
                foreach($discount_taxes as $tax_class_code => $total_tax){
                    
                    $item_tax_class = '';
                    $item_tax_rate_id = 0;
                    $tax_class_code = $tax_class_code;
                    $tmp_code = explode('_',$tax_class_code);
                    if(count($tmp_code) == 2)
                    {
                        $tmp_tax_class = $tmp_code[0];
                        $item_tax_rate_id = $tmp_code[1];
                        if($tmp_tax_class != 'standard')
                        {
                            $item_tax_class = $tmp_tax_class;
                        }
                    }
                    $cart_discount_item->set_tax_class($item_tax_class);
                    $cart_discount_item_taxes_total[$item_tax_rate_id] = (0 - $total_tax);
                    $cart_discount_item_taxes_subtotal[$item_tax_rate_id] = (0 - $total_tax);

                }
                $cart_discount_item->set_taxes(
                    array(
                        'total'    => $cart_discount_item_taxes_total,
                        'subtotal' => $cart_discount_item_taxes_subtotal,
                    )
                );
                $cart_discount_item->set_total(0 - $discount_excl_tax);
                $cart_discount_item->add_meta_data('_pos_item_type','cart_discount');
            
                $order->add_item($cart_discount_item);
            }
            return $order;
        }
        private function _process_order_tax($order,$tax_amount,$tax_details,$order_parse_data,$session_data){
            global  $op_woo;
            if(empty($tax_details))
            {
                $setting_tax_class =  apply_filters('add_order:pos_tax_class', $this->settings_api->get_option('pos_tax_class','openpos_general') );
                $setting_tax_rate_id = apply_filters('add_order:pos_tax_rate_id', $this->settings_api->get_option('pos_tax_rate_id','openpos_general'));
                $tax_item = new WC_Order_Item_Tax();
                $label =  __('Tax on POS','openpos');

                $tax_rates = $op_woo->getTaxRates($setting_tax_class);
                if($setting_tax_rate_id && isset($tax_rates[$setting_tax_rate_id]))
                {
                    $setting_tax_rate = $tax_rates[$setting_tax_rate_id];
                    if(isset($setting_tax_rate['label']))
                    {
                        $label = $setting_tax_rate['label'];
                    }
                }
                $tax_item->set_label($label);
                $tax_item->set_name( $label);
                $tax_item->set_tax_total($tax_amount);
                if($setting_tax_rate_id)
                {
                    $tax_item->set_rate_id($setting_tax_rate_id);
                }
                if($tax_amount > 0)
                {
                    $order->add_item($tax_item);
                }

            }else{
                
                foreach($tax_details as $tax_detail)
                {
                    $tax_item = new WC_Order_Item_Tax();
                    $label = $tax_detail['label'];
                    $setting_tax_rate_id = $tax_detail['rate_id'];
                
                    $tax_item->set_tax_total($tax_detail['total']);
                    if($setting_tax_rate_id)
                    {
                        $tax_item->set_rate($setting_tax_rate_id);
                    }else{
                        $tax_item->set_label($label);
                        $tax_item->set_compound(false);
                    }
                     
                    
                    $tax_item->set_shipping_tax_total(0);

                    
                    $tax_item->set_name(strtoupper( $label.'-'.$setting_tax_rate_id));

                    $order->add_item($tax_item);

                }
            }
            return $order;
        }
        private function _process_order_shipping($order,$shipping_information,$order_parse_data,$session_data){
             global $op_woo;
             //shipping item
             $shipping_cost = isset($order_parse_data['shipping_cost']) ? $order_parse_data['shipping_cost'] : 0;
             $shipping_tax = isset($order_parse_data['shipping_tax']) ? $order_parse_data['shipping_tax'] : 0;
             $shipping_item = new WC_Order_Item_Shipping();
             $shipping_note = isset($shipping_information['note']) ? $shipping_information['note'] : '';
             if(isset($shipping_information['shipping_method']) && $shipping_information['shipping_method'])
             {
                 $shipping_method_details = isset($shipping_information['shipping_method_details']) ? $shipping_information['shipping_method_details'] : array();
                 $shipping_tax_details = isset($shipping_information['tax_details']) ? $shipping_information['tax_details'] : array();
                 if(!empty($shipping_method_details) && isset($shipping_method_details['code']))
                 {
                     $title = $shipping_method_details['label'];
                     $code = $shipping_method_details['code'];
                     $tmp = explode(':',$code);
                     if(count($tmp) == 2){
                         $tmp_code = $tmp[0];
                         $tmp_instance_id = $tmp[1];
                         $shipping_item->set_method_id($tmp_code);
                         $shipping_item->set_instance_id($tmp_instance_id);
                     }else{

                         $shipping_item->set_method_id($code);
                     }
                     $shipping_item->set_method_title($title);
                 }else{
                     $order_shipping = $op_woo->get_shipping_method_by_code($shipping_information['shipping_method']);
                     $title = $order_shipping['title'];
                     $code = $order_shipping['code'];

                     $shipping_item->set_method_title($title);

                     $shipping_item->set_method_id($code);
                 }
                 $shipping_item->set_total($shipping_cost - $shipping_tax);
                 if(!empty($shipping_tax_details))
                 {
                     $shipping_taxes_total = array();
                     foreach($shipping_tax_details as $shipping_tax_data)
                     {
                         $shipping_taxes_total[$shipping_tax_data['rate_id']] = $shipping_tax_data['total'];
                     }
                     if(!empty($shipping_taxes_total))
                     {
                         $shipping_taxes = array(
                             'total' => $shipping_taxes_total
                         );
                         $shipping_item->set_taxes($shipping_taxes);
                     }
                     
                 }
                 $order->add_item($shipping_item);
             }else{
                 $shipping_item->set_method_title(__('POS Customer Pickup','openpos'));
                 $shipping_item->set_total($shipping_cost - $shipping_tax);
                 $shipping_item->set_method_id('openpos');
                 $order->add_item($shipping_item);
             }

             if($shipping_note)
             {
                 $order->set_customer_note($shipping_note);
             }
            return $order;
        }

        public function add_order($order_parse_data,$session_data,$is_clear = false,$order_source = 'sync'){
            global $op_register;
            global $op_woo;
            $use_hpos = $this->_core->enable_hpos();
            $is_product_tax = false;
            try{
                $session_setting = isset($session_data['setting']) ? $session_data['setting'] : array();
                $login_cashdrawer_id = isset($session_data['login_cashdrawer_id']) ? $session_data['login_cashdrawer_id'] : 0;
                if(!$login_cashdrawer_id)
                {
                    if(isset($order_parse_data['register']['id']))
                    {
                        $login_cashdrawer_id = $order_parse_data['register']['id'];
                    }
                }
                $has_shipping = false;
                if(isset($order_parse_data['add_shipping']) && $order_parse_data['add_shipping'] == true)
                {
                    $has_shipping = true;
                }
                $order_number = isset($order_parse_data['order_number']) ? $order_parse_data['order_number'] : 0;
                $new_order_number = $order_number;
                $order_id = isset($order_parse_data['order_id']) ? $order_parse_data['order_id'] : 0;
                $order_local_id = isset($order_parse_data['id']) ? $order_parse_data['id'] : '';
                $order_number_format = isset($order_parse_data['order_number_format']) ? $order_parse_data['order_number_format'] : '';
                $order_number_details = isset($order_parse_data['order_number_details']) ? $order_parse_data['order_number_details'] : array();
                if(isset($order_number_details['order_id']) && $order_number_details['order_id'] &&  $order_id != $order_number_details['order_id']  )
                {
                    $order_id = $order_number_details['order_id'];
                }

                do_action('op_add_order_data_before',$order_parse_data,$session_data);

                $items = isset($order_parse_data['items']) ? $order_parse_data['items'] : array();
                if(empty($items))
                {
                    throw new Exception('Item not found.');
                }
                $customer_id = 0;
                
                $customer = isset($order_parse_data['customer']) ? $order_parse_data['customer'] : array();
                $customer_email = isset($customer['email']) ? $customer['email'] : '';
                if(!empty($customer) && isset($customer['id']))
                {
                    $customer_id = $customer['id'];
                    
                    if($customer_id == 0 || !$customer_id)
                    {
                        if($customer_email && $customer_email != null)
                        {
                            $customer_user = get_user_by('email',$customer_email);
                            if($customer_user)
                            {
                                $customer_id = $customer_user->get('ID');
                            }
                        }
                        if($customer_id == 0 && isset($customer['create_customer']) && $customer['create_customer'] == 1)
                        {
                            $tmp_create_customer = $op_woo->_add_customer($customer,$session_data);
                            $customer_id =  $tmp_create_customer['data'];
                            //create new customer
                        }
                    }
                }
                if(isset($customer['addition_data']) && is_array($customer['addition_data']))
                {
                    foreach($customer['addition_data'] as $addition_data_key => $addition_data_value)
                    {
                        $customer[$addition_data_key] = $addition_data_value;
                    }
                }

                $tax_amount = isset($order_parse_data['tax_amount']) ? floatval($order_parse_data['tax_amount']) : 0;
                $tax_details = isset($order_parse_data['tax_details']) ? $order_parse_data['tax_details'] : array();
                $fee_tax_details = isset($order_parse_data['fee_tax_details']) ? $order_parse_data['fee_tax_details'] : array();
                    
                $final_items_discount_amount = 0;
                $final_items_discount_tax = 0;

                $grand_total = isset($order_parse_data['grand_total']) ? floatval($order_parse_data['grand_total']) : 0;

                $discount_code = isset($order_parse_data['discount_code']) ? $order_parse_data['discount_code'] : '';
                $discount_code_amount = isset($order_parse_data['discount_code_amount']) ? floatval($order_parse_data['discount_code_amount']) : 0;
                $discount_code_tax_amount = isset($order_parse_data['discount_code_tax_amount']) ? floatval($order_parse_data['discount_code_tax_amount']) : 0;
                $discount_code_excl_tax = isset($order_parse_data['discount_code_excl_tax']) ? floatval($order_parse_data['discount_code_excl_tax']) : ( $discount_code_amount - $discount_code_tax_amount);


                $payment_method = isset($order_parse_data['payment_method']) ? $order_parse_data['payment_method'] : array();
                $shipping_information = isset($order_parse_data['shipping_information']) ? $order_parse_data['shipping_information'] : array();
                $created_at_time = isset($order_parse_data['created_at_time']) ? $order_parse_data['created_at_time'] : time() * 1000;

                $is_online_payment = ($order_parse_data['online_payment'] == 'true') ? true : false;
                $order_state = isset($order_parse_data['state']) ? $order_parse_data['state'] : 'completed';
                $tmp_setting_order_status = $this->settings_api->get_option('pos_order_status','openpos_general');
                $setting_order_status =  apply_filters('op_new_order_status',$tmp_setting_order_status,$order_parse_data);
                if($order_state == 'pending_payment')
                {
                    $is_online_payment = true;
                }
                $shipping_cost = isset($order_parse_data['shipping_cost']) ? $order_parse_data['shipping_cost'] : 0;
                $shipping_tax = isset($order_parse_data['shipping_tax']) ? $order_parse_data['shipping_tax'] : 0;

                //total paid
                $customer_total_paid = isset($order_parse_data['customer_total_paid']) ? $order_parse_data['customer_total_paid'] : 0;
                $total_paid = isset($order_parse_data['total_paid']) ? $order_parse_data['total_paid'] : 0; //amount should pay
                $allow_laybuy = isset($order_parse_data['allow_laybuy']) ? $order_parse_data['allow_laybuy'] : 'no';
                if(!$total_paid)
                {
                    $total_paid = $grand_total;
                }
                if($customer_total_paid == $total_paid)
                {
                    $allow_laybuy = 'no';
                }
                $note = isset($order_parse_data['note']) ? $order_parse_data['note'] : '';
               
                
                if($_order = $this->_find_existing_order($order_parse_data,$session_data,$is_clear,$order_source))
                {
                    return $_order;
                }
                $arg = array(
                    'status'        => null,
                    'customer_id'   => $customer_id,
                    'customer_note' => $note
                );
                if($order_number > 0)
                {
                    $order_post = false;
                    if($order_id > 0)
                    {
                        if($use_hpos)
                        {
                            $order_post = wc_get_order($order_id);
                        }else{
                            $order_post = get_post($order_id);
                        }
                    }
                    
                    if(!$order_post)
                    {
                        $pos_use_offline_order_number = (isset($session_setting['pos_use_offline_order_number']) && $session_setting['pos_use_offline_order_number'] == 'yes') ? true : false;
                        if(!$pos_use_offline_order_number && $order_source == 'direct')
                        {
                            $is_update_order_number = apply_filters('op_order_data_order_is_update_order_number',true,$order_post,$order_parse_data);
                            
                            $next_order_json = $this->get_order_number($session_data,$use_hpos,$is_update_order_number );
                            
                            
                            $next_order = isset($next_order_json) ? $next_order_json['data'] : array();
                            
                            $order_id = $next_order['order_id'];
                            $order_post = apply_filters('op_order_data_order_post_obj',get_post($order_id),$order_parse_data);

                            $new_order_number = apply_filters('op_order_data_order_post_order_number',$next_order['order_number'],$order_post,$order_parse_data);
                            $order_number_format = apply_filters('op_order_data_order_post_order_number_format',$next_order['order_number_formatted'],$order_post,$order_parse_data);
                            
                        
                        }else{
                            $_order_post = $this->reGenerateDraftOrder($order_id,$new_order_number,$order_number_format,$order_parse_data); // hpos implemented
                            $order_post = apply_filters('op_order_data_order_post_obj',$_order_post,$order_parse_data);
                        }
                        
                    }
                    if($order_post)
                    {
                        if ( $order_post instanceof WC_Order )
                        {
                            $order_id = $order_post->get_id();
                            $order_post->update_status('wc-pending');
                            //$order_post->save();
                        }else{
                            $order_id = $order_post->ID;
                            $hidden_order = array(
                                'ID'           => $order_id,
                                'post_status'   => 'wc-pending'
                            );
                            wp_update_post( $hidden_order );
                        }
                        
                    }    
                }
                if($order_id)
                {
                    $arg['order_id'] = $order_id;
                }
                
                $arg['cart_hash'] = md5(json_encode($order_parse_data));
                $arg['created_via'] = 'openpos';
                $lock_group = "order_lock_{$order_local_id}";
                $order = wc_create_order($arg);
                
                if ( is_wp_error( $order ) ) {
                    $logger = wc_get_logger();
                    $context = array( 'source' => 'woocommerce-openpos-failure' );
                    $request_data = json_encode($_REQUEST);
                    $logger->debug( $request_data, $context );
                    $logger->debug(  print_r($order->get_error_messages(),true), $context );
                }

                
                $tmp_items = $order->get_items();    

                if(!empty($tmp_items) || $is_clear)
                {
                    $order = $this->remove_order_items($order,true);
                }
                $order->set_order_key( wc_generate_order_key() );

                do_action('op_add_order_before',$order,$order_parse_data,$session_data);

                $created_time_unix = round($created_at_time / 1000);
                $current_time_unix = time();
                if($created_time_unix > $current_time_unix)
                {
                    $created_time_unix = $current_time_unix;
                }
                $date_time = new WC_DateTime();
                $date_time->setTimestamp($created_time_unix);
                $order->set_date_created($date_time);
                

                $order->set_customer_note($note);
                
                //product list
                foreach($items as $_itm)
                {
                    $_item = apply_filters('op_order_item_data_before',$_itm,$order_parse_data);
                    if(isset($_item['final_discount_amount_incl_tax']))
                    {
                        $final_items_discount_tax +=  ($_item['final_discount_amount_incl_tax'] - $_item['final_discount_amount']);
                        $final_items_discount_amount += $_item['final_discount_amount'];
                    }
                }
                $order = $this->_process_order_items($order,$order_parse_data,$session_data);
                //fee
                $order = $this->_process_order_fees($order,$order_parse_data,$session_data);
                //coupon
                if($discount_code)
                {
                    $order = $this->_process_order_coupons($order,$order_parse_data,$session_data);
                    $final_items_discount_amount += $discount_code_excl_tax;
                    $final_items_discount_tax += $discount_code_tax_amount;
                }
                
                //cart discount item as fee

                $order = $this->_process_order_cart_discount($order,$order_parse_data,$session_data);
                
                //end cart discount item
                if($final_items_discount_amount)
                {
                    $order->set_discount_total(1 * $final_items_discount_amount);
                    $order->set_discount_tax(1 * $final_items_discount_tax);
                }else{
                    $order->set_discount_total(0);
                    $order->set_discount_tax(0);
                }
                //billing information
                $order = $this->_process_order_customer($order,$order_parse_data,$session_data);
                //shipping
                $shipping_total = wc_format_decimal($shipping_cost - $shipping_tax );
                $order->set_shipping_total($shipping_total);
                $order->set_shipping_tax(wc_format_decimal($shipping_tax)); //optional


                if($has_shipping)
                {
                    $order = $this->_process_order_shipping($order,$shipping_information,$order_parse_data,$session_data);
                }
                
                foreach($fee_tax_details as $fee_tax)
                {
                    $tax_amount += $fee_tax['total'];
                    $has_tax = false;
                    foreach($tax_details as $index => $t)
                    {
                        if($fee_tax['code'] == $t['code'])
                        {
                            $tax_details[$index]['total'] += $fee_tax['total'];
                            $has_tax = true;
                        }
                    }
                    if(!$has_tax)
                    {
                        $tax_details[] = $fee_tax;
                    }
                }
                
                if($tax_amount >= 0 && !$is_product_tax)
                {
                    $order = $this->_process_order_tax($order,$tax_amount,$tax_details,$order_parse_data,$session_data);
                    
                }
                $order->set_cart_tax($tax_amount);
                // payment method

                $payment_method_code = 'pos_payment';
                $payment_method_title = __('Pay On POS','openpos');
                
                if(count($payment_method) > 1)
                {

                    $payment_method_code = 'pos_multi';
                    $payment_method_title = __('Multi Methods','openpos');
                    $sub_str = array();
                    foreach($payment_method as $p)
                    {
                        $paid = wc_price($p['paid']);
                        $sub_str[] = implode(': ',array($p['name'],strip_tags($paid)));
                    }
                    if(!empty($sub_str))
                    {
                        $payment_method_title .= '( '.implode(' & ',$sub_str).' ) ';
                    }
                }else{
                    $method = end($payment_method);
                    if(is_array($method) && $method['code'])
                    {
                        $payment_method_code = $method['code'];
                        $payment_method_title = $method['name'];
                    }
                }
                if(!$is_online_payment)
                {
                    $order->set_payment_method($payment_method_code);
                    $order->set_payment_method_title($payment_method_title);
                }
                
                // order total
                $order->set_total( $grand_total );

                
                if($note)
                {
                    $order->add_order_note($note);
                }

                do_action('op_add_order_before_change_payment',$order,$order_parse_data);


                $addition_order_meta = array();
                $addition_order_meta[] = array(
                    'key'   => '_op_order_number',
                    'value' => $new_order_number,
                );
                $addition_order_meta[] =array(
                    'key'   => '_op_order_total_paid',
                    'value' => $total_paid,
                );
                if(!$is_online_payment) {
                    $addition_order_meta[] = array(
                        'key'   => '_op_payment_methods',
                        'value' => $payment_method,
                    );
                    
                }
                $addition_order_meta[] = array(
                    'key'   => '_op_allow_laybuy',
                    'value' => $allow_laybuy,
                );
    
                
                if($order_number)
                {
                    $addition_order_meta[] = array(
                        'key'   => '_op_wc_custom_order_number',
                        'value' => $order_number,
                    );
                    
                }

                


                if($allow_laybuy == 'yes')
                {
                    $laybuy_order_status = $this->_core->getPosLayBuyOrderStatus();
                
                    $order->set_status($laybuy_order_status , __('Create via OpenPos', 'openpos'));
                    if($customer_total_paid < $total_paid)
                    {
                        $op_remain_amount = ($total_paid - $customer_total_paid);
                        $op_register->addDebitBalance($login_cashdrawer_id, $op_remain_amount);
                        
                        $addition_order_meta[] = array(
                            'key'   => '_op_remain_paid',
                            'value' => $op_remain_amount,
                        );
                    }
                    $order = $this->_process_order_meta($order,$order_parse_data,$session_data,$addition_order_meta);
                    $order->save();
                }else{
                    $order = $this->_process_order_meta($order,$order_parse_data,$session_data,$addition_order_meta);
                    if(!$is_online_payment) {
                        $_allow_payment_complete = apply_filters('op_allow_payment_complete',true,$order_parse_data,$session_data);;
                        if($_allow_payment_complete)
                        {
                            $order->set_date_paid($date_time);
                            $order->set_status($setting_order_status, __('Done via OpenPos', 'openpos'));
                            $order->payment_complete();
                            $order->save();
                        }else{
                            $order->set_transaction_id( 'pay via openpos' );
                            //$order->set_date_paid( time() );
                            $order->set_date_paid($date_time);
                            $order->set_status($setting_order_status, __('Done via OpenPos', 'openpos'));
                            $order->save();
                        }
                    }else{
                        $order->save();
                    }
                }
                
                
                wp_cache_flush_group($lock_group);
                //shop_order
                if($customer_id && $has_shipping)
                {
                    // update shipping information
                    $op_woo->_update_customer_shipping($customer_id,$shipping_information);
                }
                //take look when use hpos
                do_action('op_add_order_after',$order,$order_parse_data);
                return $order;
                
            }catch (Exception $e)
            {
                return $this->handle_order_error($e, $order_parse_data, $session_data);
            }
        }

        private function handle_order_error($exception, $order_parse_data, $session_data) {
            $logger = wc_get_logger();
            $context = array('source' => 'openpos-order-error');
            
            $logger->error(sprintf(
                'Order creation failed: %s | Order Local ID: %s | User: %d',
                $exception->getMessage(),
                $order_parse_data['id'] ?? 'unknown',
                $session_data['user_id'] ?? 0
            ), $context);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $logger->debug('Order Data: ' . print_r($order_parse_data, true), $context);
            }
            
            return new WP_Error(
                'op_order_creation_failed',
                $exception->getMessage(),
                array('order_local_id' => $order_parse_data['id'] ?? '')
            );
        }

    }
}
