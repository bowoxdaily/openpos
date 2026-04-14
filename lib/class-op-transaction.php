<?php
defined( 'ABSPATH' ) || exit;
if(!class_exists('OP_Transaction'))
{
    class OP_Transaction{
        public $post_type = 'op_transaction';
        public $_core;
        private $_enable_hpos;
        public function __construct()
        {
            $this->_core = new Openpos_Core();
            add_action( 'init', array($this, 'init') );
            add_action('plugins_loaded', array($this,'plugins_loaded'));
        }
        function plugins_loaded()
        {
            $this->_enable_hpos = $this->_core->enable_hpos();
        }
        public function init(){
            register_post_type( $this->post_type,
                    array(
                        'labels'              => array(
                            'name'                  => __( 'Transactions', 'openpos' ),
                            'singular_name'         => __( 'Transaction', 'openpos' )
                        ),
                        'description'         => __( 'This is where you can add new transaction that customers can use in your store.', 'openpos' ),
                        'public'              => false,
                        'show_ui'             => false,
                        'capability_type'     => 'op_transaction',
                        'map_meta_cap'        => true,
                        'publicly_queryable'  => false,
                        'exclude_from_search' => true,
                        'show_in_menu'        => false,
                        'hierarchical'        => false,
                        'rewrite'             => false,
                        'query_var'           => false,
                        'supports'            => array( 'title','author' ),
                        'show_in_nav_menus'   => false,
                        'show_in_admin_bar'   => false
                    )
            );
        }
        public function add($transaction){
            $in_amount = isset($transaction['in_amount']) ? floatval($transaction['in_amount']) : 0;
            $out_amount = isset($transaction['out_amount']) ? floatval($transaction['out_amount']) : 0;
            $store_id = isset($transaction['store_id']) ? intval($transaction['store_id']) : 0;
            $ref = isset($transaction['ref']) ? $transaction['ref'] : date('d-m-Y H:i:s');
            $payment_code = isset($transaction['payment_code']) ? $transaction['payment_code'] : 'cash';
            $payment_name = isset($transaction['payment_name']) ? $transaction['payment_name'] : 'Cash';
            $payment_ref = isset($transaction['payment_ref']) ? $transaction['payment_ref'] : '';
            $created_at = isset($transaction['created_at']) ? $transaction['created_at'] : '';
            $created_at_utc = isset($transaction['created_at_utc']) ? $transaction['created_at_utc'] : '';
            $created_at_time = isset($transaction['created_at_time']) ? $transaction['created_at_time'] : current_time( 'timestamp', true );
            $user_id = isset($transaction['user_id']) ? $transaction['user_id'] : 0;
            $source_type = isset($transaction['source_type']) ? $transaction['source_type'] : '';
            $source = isset($transaction['source']) ? $transaction['source'] : '';
            $source_data = isset($transaction['source_data']) ? $transaction['source_data'] : array();
            $transaction_data = isset($transaction['transaction_data']) ? $transaction['transaction_data'] : array();
            $session_id = isset($transaction['session']) ? $transaction['session'] : '' ;
            $cashdrawer_id = isset($transaction['login_cashdrawer_id']) ? $transaction['login_cashdrawer_id'] : 0;
            $warehouse_id = isset($transaction['login_warehouse_id']) ? $transaction['login_warehouse_id'] : 0;
            $transaction_id = isset($transaction['id']) ? $transaction['id'] : 0 ; //offline id
            $currency = isset($transaction['currency']) ? $transaction['currency'] : null ; //offline id
            $id = wp_insert_post(
                array(
                    'post_title'=> $ref,
                    'post_type'=> $this->post_type,
                    'post_author'=> $user_id,
                    'post_status' => 'publish'
                ));
            if($id)
            {
                add_post_meta($id,'_in_amount',$in_amount);
                add_post_meta($id,'_out_amount',$out_amount);
                add_post_meta($id,'_created_at',$created_at);
                add_post_meta($id,'_created_at_utc',$created_at_utc);
                add_post_meta($id,'_created_at_time',$created_at_time);
                add_post_meta($id,'_user_id',$user_id);
                add_post_meta($id,'_store_id',$store_id);
                add_post_meta($id,'_transaction_id',$transaction_id);
                add_post_meta($id,'_payment_code',$payment_code);
                add_post_meta($id,'_payment_name',$payment_name);
                if(!empty($source_data))
                {
                    if(isset($source_data['order_id']) && $source_data['order_id'] ){
                        add_post_meta($id,'_source_data_order_id',$source_data['order_id']);
                    }
                    if(isset($source_data['order_local_id']) && $source_data['order_local_id'] ){
                        add_post_meta($id,'_source_data_order_local_id',$source_data['order_local_id']);
                    }
                    if(isset($source_data['order_number']) && $source_data['order_number'] ){
                        add_post_meta($id,'_source_data_order_number',$source_data['order_number']);
                    }
                    add_post_meta($id,'_source_data',$source_data);
                }
                
                if($payment_ref)
                {
                    add_post_meta($id,'_payment_ref',$payment_ref);
                }
                if($source_type)
                {
                    add_post_meta($id,'_source_type',$source_type);
                }
                if($source)
                {
                    add_post_meta($id,'_source',$source);
                }
                if($session_id )
                {
                    update_post_meta($id,'_op_trans_session_id',$session_id);
                }
                if(!empty($transaction_data))
                {
                    add_post_meta($id,'_transaction_details',$transaction_data);
                }else{
                    add_post_meta($id,'_transaction_details',$transaction);
                }
                if($currency != null)
                {
                    add_post_meta($id,'_currency',$currency);
                }
                $warehouse_key = '_pos_transaction_warehouse';
                $cashdrawer_key = '_pos_transaction_cashdrawer';
                add_post_meta($id,$cashdrawer_key,$cashdrawer_id);
                add_post_meta($id,$warehouse_key,$warehouse_id);
            }
            return apply_filters( 'op_add_transaction_item_after', $id,$transaction ); 
        }
        public function get($transaction_id){
            $transaction_data = array(
                'id'     => $transaction_id,
                'sys_id'     => '',
                'in_amount'     => '',
                'out_amount'     => '',
                'currency' => null,
                'ref'     => '',
                'source_type'     => '',
                'source'     => '',
                'source_data'     => '',
                'created_at'     => '',
                'created_at_time'     => '',
                'created_by_id'     => '',
                'created_by'     => '',
                'sync_status'     => '',
                'session'     => '',
                'payment_code'     => '',
                'payment_name'     => '',
                'payment_ref'     => '',
            );

            $transaction = get_post_meta($transaction_id,'_transaction_details',true);
            $_user_id = get_post_meta($transaction_id,'_user_id',true);
            $transaction_id =  $transaction_id;
            $transaction['sys_id'] = $transaction_id;
            $local_id = isset($transaction['id']) ? $transaction['id'] : $transaction_id;
        
            $user_id = isset($transaction['created_by_id']) ? $transaction['created_by_id'] : $_user_id;
        
            $transaction['created_by'] = __('Unknown','openpos');
            if($user_email = get_the_author_meta('user_email', $user_id))
            {
                $transaction['created_by'] = $user_email;
            }
            if($user_nicename = get_the_author_meta('user_nicename', $user_id))
            {
                $transaction['created_by'] = $user_nicename;
            }
            foreach($transaction as $key => $value)
            {
               
                if(isset($transaction_data[$key]))
                {
                    $transaction_data[$key] =  $value;
                }
            }

            return apply_filters( 'op_get_transaction_item', $transaction_data, $transaction_id );
        }
        public function get_by_local_id($local_transaction_id){
            $post_type = $this->post_type;
            //start check transaction exist
            $args = array(
                'post_type' => $post_type,
                'meta_query' => array(
                    array(
                        'key' => '_transaction_id',
                        'value' => $local_transaction_id,
                        'compare' => '=',
                    )
                )
            );
            $query = new WP_Query($args);
            $transactions = $query->get_posts();
            if(empty($transactions))
            {
                return false;
            }else{
                $transaction = end($transactions);
                $id = $transaction->ID;
                return $this->get($id);
            }
        }
        
        public function getOrderTransactions($order_id,$source_types = array(),$is_local = false,$payment_code = ''){
            
            if(empty($source_types))
            {
                $source_types = array( 'order','refund_order');
            }
            $transactions = array();
            $order_source = '';
            $_op_local_id = '';
            
            $order = wc_get_order($order_id);
            if($order)
            {
                $order_source = $order->get_meta('_op_order_source');
                $_op_local_id = $order->get_meta('_op_local_id');
                
            }
            $meta_query = array(
                'relation' => 'AND',
                'source_clause' => array(
                    'key' => '_source_data_order_local_id',
                    'value' => $_op_local_id,
                    'compare' => '=',
                ),
                'source_type_clause' => array(
                    'key' => '_source_type',
                    'value'   => $source_types,
                    'compare' => 'IN',
                ),
                    
            );
            if($payment_code)
            {
                $meta_query['_payment_code'] = array(
                    'key' => '_payment_code',
                    'value' => $payment_code,
                    'compare' => '=',
                );
                
            }
                
            $args_source_data = array(
                    'meta_query' => $meta_query,
                    'post_type'         => $this->post_type,
                    'posts_per_page'    => '-1'
            );

            
            $query = new WP_Query($args_source_data);
            $posts_transactions = $query->get_posts();

            $args_source_data = array(
                    'meta_query' => array(
                        'relation' => 'AND',
                        'source_clause' => array(
                            'key' => '_source_data_order_id',
                            'value' => $order_id,
                            'compare' => '=',
                        ),
                        'source_type_clause' => array(
                            'key' => '_source_type',
                            'value'   => $source_types,
                            'compare' => 'IN',
                        ),
                            
                    ),
                    'post_type'         => $this->post_type,
                    'posts_per_page'    => '-1'
            );
            
            $query = new WP_Query($args_source_data);
            $posts_orders = $query->get_posts();
            $posts_transactions = array_merge($posts_transactions,$posts_orders);

            
           
            $args = array(
                    'meta_query' => array(
                        'relation' => 'AND',
                        'source_clause' => array(
                            'key' => '_source',
                            'value' => $order_id,
                            'compare' => '=',
                        ),
                        'source_type_clause' => array(
                            'key' => '_source_type',
                            'value'   => $source_types,
                            'compare' => 'IN',
                        ),
                            
                    ),
                    'post_type'         => $this->post_type,
                    'posts_per_page'    => '-1'
            );
            
            $query = new WP_Query($args);
            $posts_orders = $query->get_posts();
            $posts_transactions = array_merge($posts_transactions,$posts_orders);


           # print_r($posts_transactions);die;
            
            foreach($posts_transactions as $post)
            {
                $transaction = $this->get($post->ID);
                $local_id = isset($transaction['id']) ? $transaction['id'] : $post->ID;
                $transactions[$local_id] = $transaction;
            }
            
            if($_op_local_id && empty($transactions) && !$is_local)
            {
                $transactions = $this->getOrderTransactions($_op_local_id,$source_types,true);
            }
            

            if($order_source == 'openpos')
            {
               
            }else{
                $order = wc_get_order($order_id);
                if($order)
                {
                    $payment_method = $order->get_payment_method();
                    $payment_method_title = $order->get_payment_method_title();
                    #$payment_method_title = $order->payment;
                    $payment_ref =  $order->get_transaction_id();;
                    $order_status = $order->get_status();
                    if($payment_method && !in_array($order_status, array('pending','on-hold','failed','refunded','cancelled')))
                    {
                        $paid_amount = $order->get_total();
                        $transaction = array();
                        $transaction['id'] = $order_id;
                        $transaction['sys_id'] = $order_id;
                        $transaction['created_by'] = 0;
                        $transaction['created_at'] = wc_format_datetime( $order->get_date_created(), get_option( 'date_format' ) . ', ' . get_option( 'time_format' ) );
                        $transaction['in_amount'] = $paid_amount;
                        $transaction['out_amount'] = 0;
                        $transaction['payment_code'] = $payment_method;
                        $transaction['payment_name'] = $payment_method_title;
                        $transaction['payment_ref'] = $payment_ref;
                        $transaction['session'] = '';
                        $transaction['currency'] = null;
                        $transaction['created_by_id'] = 0;
                        $transaction['sync_status'] = 1;
                        $transaction['source'] =  $order_id;
                        $transaction['source_data'] = array();
                        $transaction['source_type'] = 'website_order';
                        $transaction['ref'] = __('Website Order','openpos');
                        $transactions[$order_id] = $transaction;
                    }
                }
            }
           
            return apply_filters( 'op_get_order_transactions', array_values($transactions),$order_id ); 
            
        }
        public function formatDataFromJson($transaction,$session_data){
            $transaction_id =  $transaction['id'];
            $currency = isset($transaction['currency']) ? $transaction['currency'] : null;
            $in_amount = isset($transaction['in_amount']) ? floatval($transaction['in_amount']) : 0;
            $out_amount = isset($transaction['out_amount']) ? floatval($transaction['out_amount']) : 0;
            $store_id = isset($transaction['store_id']) ? intval($transaction['store_id']) : 0;
            $ref = isset($transaction['ref']) ? $transaction['ref'] : date('d-m-Y H:i:s');
            $payment_code = isset($transaction['payment_code']) ? $transaction['payment_code'] : 'cash';
            $payment_name = isset($transaction['payment_name']) ? $transaction['payment_name'] : 'Cash';
            $payment_ref = isset($transaction['payment_ref']) ? $transaction['payment_ref'] : '';
            $created_at = isset($transaction['created_at']) ? $transaction['created_at'] : '';
            $created_at_utc = isset($transaction['created_at_utc']) ? $transaction['created_at_utc'] : '';
            $created_at_time = isset($transaction['created_at_time']) ? $transaction['created_at_time'] : current_time( 'timestamp', true );
            $created_by_id = isset($transaction['created_by_id']) ? $transaction['created_by_id'] : 0;

            $user_id = isset($session_data['user_id']) ? $session_data['user_id'] : '';
           
            $source_type = isset($transaction['source_type']) ? $transaction['source_type'] : '';
            $source = isset($transaction['source']) ? $transaction['source'] : '';
            $source_data = isset($transaction['source_data']) ? $transaction['source_data'] : array();
            $_get_session_id = isset($_REQUEST['session']) ? trim($_REQUEST['session']) : '';
            $session_id = isset($transaction['session']) ? $transaction['session'] : $_get_session_id ;

            $cashdrawer_id = isset($session_data['login_cashdrawer_id']) ? $session_data['login_cashdrawer_id'] : 0;
            $warehouse_id = isset($session_data['login_warehouse_id']) ? $session_data['login_warehouse_id'] : 0;

            $currency_rate = 1;
            if($currency != null)
            {
                if(isset($currency['rate']))
                {
                    $currency_rate = 1* $currency['rate'];
                }
            }
            $transaction_data = array(
                'id' => $transaction_id,
                'session' => $session_id,
                'source_data' => $source_data,
                'source' => $source,
                'source_type' => $source_type,
                'user_id' => $user_id,
                'created_by_id' => $created_by_id,
                'created_at' => $created_at,
                'created_at_utc' => $created_at_utc,
                'created_at_time' => $created_at_time,
                'payment_ref' => $payment_ref,
                'payment_name' => $payment_name,
                'payment_code' => $payment_code,
                'ref' => $ref,
                'store_id' => $store_id,
                'out_amount' => $out_amount,
                'in_amount' => $in_amount,
                'currency' => $currency,
                'login_cashdrawer_id' => $cashdrawer_id,
                'login_warehouse_id' => $warehouse_id,
                'transaction_data' => $transaction,
            );
            return $transaction_data;
        }
    }

}