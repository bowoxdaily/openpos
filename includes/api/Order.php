<?php
if(!class_exists('OP_REST_API_Order'))
{
    class OP_REST_API_Order extends OP_REST_API{
       
        
        public function register_routes() {
            
            register_rest_route( $this->namespace, '/order/orders', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'orders'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/order/search-orders', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'search_orders'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/order/customer-orders', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'customer_orders'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            
            register_rest_route( $this->namespace, '/order/get-by-order-number/(?P<order_number>\d+)', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'get_order_by_order_number'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/order/get-by-local-id/(?P<local_id>\d+)', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'get_order_by_local_id'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/order/get', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'get_order'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/order/send-receipt', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'send_receipt'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            
            register_rest_route( $this->namespace, '/order/get-notes', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'get_order_notes'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/order/add-note', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'add_note'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/order/generate-order-number', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'generate_order_number'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/order/create', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'save'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/order/update', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'update_order'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/order/pickup', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'pickup'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );

            register_rest_route( $this->namespace, '/order/close', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'close_order'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/order/check', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'check_order'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/order/refund', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'refund_order'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/order/pending-order', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'pending_order'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            

        }
        public function orders($request)
        {
            $result = array(
                'code' =>'unknown_error',
                'response' => array(
                    'status' => 0,
                    'data' => array(
                        'total_post' => 0,
                        'total_page' => 0,
                        'orders' => array()
                    ),
                    'message' => ''
                ),
                'api_message' => ''
            );
            
            try{
                $session_data = $this->session_data;
                $customer_id = $request->get_param('customer_id') ? (int)$request->get_param('customer_id') : 0;
                $page = $request->get_param('page') ? (int)$request->get_param('page') : 1;
                $list_type = $request->get_param('list_type') ? $request->get_param('list_type') : 'latest';
                $term = $request->get_param('term') ? $request->get_param('term') : '';
                $per_page = $request->get_param('per_page') ? $request->get_param('per_page') : 15;
                $per_page = apply_filters('op_latest_order_per_page',$per_page);
                
               
                $post_type = 'shop_order';
                $today = getdate();
                $per_page = 15;
                
                $post_statuses =  array(
                    'wc-processing',
                    'wc-pending',
                    'wc-completed',
                    'wc-refunded',
                    'wc-on-hold',
                );
                if($list_type == 'latest')
                {
                    $args = array(
                        'post_type' => $post_type,
                        'post_status' =>  $post_statuses,
                        'posts_per_page' => $per_page,
                        'paged' => $page
                    );
                }else{
                    
                    $time = time();
                    if(isset($session_data['logged_time']) && $session_data['logged_time'])
                    {
                        $time = strtotime($session_data['logged_time']);
                    }
                    $wc_date = new WC_DateTime();
                    if ( get_option( 'timezone_string' ) ) {
                        $wc_date->setTimezone( new DateTimeZone( wc_timezone_string() ) );
                    } else {
                        $wc_date->set_utc_offset( wc_timezone_offset() );
                    }
                    $wc_date->setTimestamp($time);
                    $date_string = $wc_date->date("Y-m-d 00:00:00");
                    
                    $args = array(
                        'date_query' => array(
                            'after'  =>  $date_string
                        ),
                        'post_type' => $post_type,
                        'post_status' =>  $post_statuses,
                        'posts_per_page' => $per_page,
                        'paged' => $page
                    );
                }
                
                $args['order'] = 'DESC';
                $args['orderby'] = 'ID';

                $args = apply_filters('op_latest_order_query_args',$args);

                if($this->core_class->enable_hpos())
                {
                    $args['_query_src'] = 'op_order_query';
                    $data_store = WC_Data_Store::load( 'order' );
                    $orders = $data_store->query( $args );
                    $total_order_numbs = 0;
                    
                    
                    
                    if($list_type == 'latest')
                    {
                        foreach($post_statuses as $status)
                        {
                            $total_order_numbs += $data_store->get_order_count($status);
                        }
                        $total_page  = ceil($total_order_numbs / $per_page);
                        if( ($total_page  * $per_page) < $total_order_numbs)
                        {
                            $total_page += 1;
                        }
                        $result['response']['data']['total_page']  =   $total_page;
                    }
                     $orders = apply_filters('op_latest_orders_result',$orders,$list_type,null);

                }else{
                    $query = new WP_Query($args);
                    $orders = $query->get_posts();
                    if($list_type == 'latest')
                    {
                        $result['response']['data']['total_page']  = $query->max_num_pages;
                    }
                    $orders = apply_filters('op_latest_orders_result',$orders,$list_type,$query);
                }
                


                
                
                if(count($orders) > 0)
                {
                    foreach($orders as $_order)
                    {

                        if($_order)
                        {
                            if($_order instanceof WC_Order )
                            {
                                $formatted_order = $this->woo_class->formatWooOrder($_order->get_id());
                             }else{
                                $formatted_order = $this->woo_class->formatWooOrder($_order->ID);
                             }
                            
                            if(!$formatted_order || empty($formatted_order))
                            {
                                continue;
                            }
                            $payment_status = $formatted_order['payment_status'];
                            $result['response']['data']['orders'][] = $formatted_order;
                        }
                       
                    }
                    $result['response']['status'] = 1;
                }else{
                    throw new Exception(__('No order found','openpos'));
                }
                
                $result['code'] = 200;
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['response']['message'] = $e->getMessage();
                
            }
            return $this->rest_ensure_response($result);
        }
        public function get_order($request)
        {
            $result = array(
                'code' =>'unknown_error',
                'response' => array(
                    'status' => 0,
                    'data' => array(),
                    'message' => ''
                ),
                'api_message' => ''
            );
            try{
                $order_id = $request->get_param('order_id');
                $order_number = $request->get_param('order_number');
                
                
                $order = false;
                if(!$order_id)
                {
                    $order_id = $this->order_class->get_order_id_from_number($order_number);
                }
                $order = wc_get_order($order_id);
                if($order)
                {

                    $result['response']['data'] = $this->woo_class->formatWooOrder($order_id);
                    $result['response']['status'] = 1;
                }else{
                    throw new Exception(__('Order not found.','openpos'));
                }
                $result['code'] = 200;
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['response']['message'] = $e->getMessage();
                
            }
            return $this->rest_ensure_response($result);
        }
        public function get_order_by_order_number($request)
        {
            $result = array(
                'code' =>'unknown_error',
                'data' => array(
                    'status' => 0,
                    'result' => array(),
                ),
                'message' => ''
            );
            try{
                $order_number = $request->get_param('order_number');
                if(!$order_number)
                {
                    throw new Exception(__('Order number is required','openpos'));
                }
                $order_id = $this->order_class->get_order_id_from_number($order_number);
                if(!$order_id)
                {
                    throw new Exception(__('Order not found','openpos'));
                }
                $order = wc_get_order($order_id);
                if(!$order)
                {
                    throw new Exception(__('Order not found','openpos'));
                }
                
                $formatted_order = $this->_formatApiOrder($order->get_id());
                if(!$formatted_order || empty($formatted_order))
                {
                    throw new Exception(__('Order not found','openpos'));
                }
                $result['data']['result'] = $formatted_order;
                $result['code'] = 200;
                $result['data']['status'] = 1;

            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['data']['status'] = 0;
                $result['message'] = $e->getMessage();
                
            }
            return $this->rest_ensure_response($result);
        }
        public function get_order_by_local_id($request)
        {
            $result = array(
                'code' =>'unknown_error',
                'data' => array(
                    'status' => 0,
                    'result' => array(),
                ),
                'message' => ''
            );
            try{
                $local_id = $request->get_param('local_id');
                if(!$local_id)
                {
                    throw new Exception(__('Local ID is required','openpos'));
                }
                $order_id = $this->order_class->get_order_id_from_local_id($local_id);
                
                if(!$order_id)
                {
                    throw new Exception(__('Order not found','openpos'));
                }
                $order = wc_get_order($order_id);
                if(!$order)
                {
                    throw new Exception(__('Order not found','openpos'));
                }
                
                $formatted_order = $this->_formatApiOrder($order->get_id());
                if(!$formatted_order || empty($formatted_order))
                {
                    throw new Exception(__('Order not found','openpos'));
                }
                
                $result['data']['result'] = $formatted_order;
                $result['code'] = 200;
                $result['data']['status'] = 1;

            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['data']['status'] = 0;
                $result['message'] = $e->getMessage();
                
            }
            return $this->rest_ensure_response($result);
        }
        public function generate_order_number($request)
        {
            $result = array(
                'code' =>'unknown_error',
                'response' => array(
                    'status' => 0,
                    'data' => array(),
                    'message' => ''
                ),
                'api_message' => ''
            );
            try{
                $allow_hpos = $this->core_class->enable_hpos();
                $order_number_json = array();
                $session_data = $this->session_data;
                if($allow_hpos){
                    $order_number_json = $this->order_class->hpos_get_order_number();
                }else{
                    $order_number_json = $this->order_class->default_get_order_number($session_data);
                }
                if(!$order_number_json['status'])
                {
                    throw new Exception($order_number_json['message']);
                }
                $result['response']['data'] = $order_number_json['data'];
                $result['code'] = 200;
                $result['response']['status'] = 1;

            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['response']['message'] = $e->getMessage();
                
            }
            return $this->rest_ensure_response($result);
        }
        public function get_order_notes($request)
        {
            $result = array(
                'code' =>'unknown_error',
                'response' => array(
                    'status' => 0,
                    'data' => array(),
                    'message' => ''
                ),
                'api_message' => ''
            );
            try{
                $order_id = $request->get_param('order_id');
                $order_number = $request->get_param('order_number');
                $local_order_id = $request->get_param('local_order_id');
                if(!$order_id)
                {
                    throw new Exception(__('Order Id is required','openpos'));
                }
                
            
                $order = wc_get_order($order_id);
                
                if(!$order)
                {
                    $order_number = $this->order_class->get_order_id_from_local_id($order_id);
                    $order = wc_get_order($order_number);
                }

                
                if(!$order)
                {
                    throw new Exception(__('Order not found','openpos'));
                }
                $notes = $this->order_class->getOrderNotes($order->get_id());

                $result['response']['data']['notes'] = $notes;
                $result['response']['data']['order_status'] = $order->get_status();
                $order_note_allow_status = array();

                //$order_note_allow_status[] = array('code' => 'completed','label' => 'Completed');
                
                $result['response']['data']['allow_status'] =  apply_filters('op_order_note_allow_status',$order_note_allow_status,$order,$this);
                $result['code'] = 200;
                $result['response']['status'] = 1;

            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['response']['message'] = $e->getMessage();
                
            }
            return $this->rest_ensure_response($result);
        }
        private function _add_order($order_data,$order_source,$is_clear = true){
            $result = array('status' => 0,'data' => array(),'message' => '');
            try{
                global $_op_warehouse_id;
                $use_hpos = $this->core_class->enable_hpos();
                $session_data = $this->session_data;
                $_op_warehouse_id = isset($session_data['login_warehouse_id']) ? $session_data['login_warehouse_id'] : 0;
                $login_cashdrawer_id = isset($session_data['login_cashdrawer_id']) ? $session_data['login_cashdrawer_id'] : 0;
                $order_parse_data = apply_filters('op_new_order_data',$order_data,$session_data);

               

                $order = $this->order_class->add_order($order_parse_data,$session_data,$is_clear,$order_source);

                $transactions = isset($order_parse_data['transactions']) ? $order_parse_data['transactions'] : array();
                $source = isset($order_parse_data['source']) ? $order_parse_data['source'] : '';
                $source_type = isset($order_parse_data['source_type']) ? $order_parse_data['source_type'] : '';
                $email_receipt = isset($order_parse_data['email_receipt']) ? $order_parse_data['email_receipt'] : 'no';
                $cashier_id = $session_data['user_id'];
                $customer = isset($order_parse_data['customer']) ? $order_parse_data['customer'] : array();
                $customer_email = isset($customer['email']) ? $customer['email'] : '';
                if($order)
                {
                    if(!empty($transactions))
                    {
                        foreach($transactions as $transaction){
                            //save transaction
                            $transaction_data = apply_filters('op_order_transaction_data',$transaction,$order,$order_parse_data);
                            $transaction_id =  isset($transaction_data['id']) ? $transaction_data['id'] : 0;
                            $done_transient_key = 'done_transaction_'.$transaction_id;
                            $transient_key = 'adding_transaction_'.$transaction_id;
                            if($transaction_id)
                            {
                               
                                $transaction_data = $this->session_class->get_transient($transient_key);
                                $done_transaction_data = $this->session_class->get_transient($done_transient_key);
                                

                                if ( false === $transaction_data && false === $done_transaction_data ) {
                                    $transaction_data = $this->transaction_class->formatDataFromJson($transaction,$session_data);
                                    $in_amount = isset($transaction_data['in_amount']) ? floatval($transaction_data['in_amount']) : 0;
                                    $out_amount = isset($transaction_data['out_amount']) ? floatval($transaction_data['out_amount']) : 0;
                                    $payment_code = isset($transaction_data['payment_code']) ? $transaction_data['payment_code'] : 'cash';
                                    $cashdrawer_id = isset($transaction_data['login_cashdrawer_id']) ? $transaction_data['login_cashdrawer_id'] : 0;
                                    $currency = isset($transaction_data['currency']) ? $transaction_data['currency'] : null;
                                    $currency_rate = 1;
                                    if($currency != null)
                                    {
                                        if(isset($currency['rate']))
                                        {
                                            $currency_rate = 1* $currency['rate'];
                                        }
                                    }

                                    $this->session_class->set_transient( $transient_key, $transaction_data, MINUTE_IN_SECONDS );
                                
                                    //start check transaction exist
                                    $exist_transaction = $this->transaction_class->get_by_local_id($transaction_id);
                                    
                                    $id = 0;
                                    $is_new = false;
                                    if(!$exist_transaction)
                                    {
                                        
                                        $id = $this->transaction_class->add($transaction_data);
                                        $is_new = true;

                                    }else{
                                        $transaction = $exist_transaction;
                                        $id = $transaction['id'];
                                    
                                    }
                                    
                                    //end
                                    if($id)
                                    {
                                        //add cash drawer balance
                                        $is_added_balance = get_post_meta($id,'_add_balance_amount',true);
                                        if( $is_new || !$is_added_balance )
                                        {
                                            if($payment_code == 'cash')
                                            {
                                                $balance = ($in_amount - $out_amount) / $currency_rate;

                                                $this->register_class->addCashBalance($cashdrawer_id,$balance);
                        
                                                add_post_meta($id,'_add_balance_amount',$balance);
                                            }
                                        }
                                        $this->session_class->set_transient( $done_transient_key, $id, DAY_IN_SECONDS );
                                        if($is_new)
                                        {
                                            do_action('op_add_transaction_after',$id,$session_data,$transaction_data);
                                        }
                                    }
                                    $this->session_class->delete_transient( $transient_key );
                                }
                            }
                            
                            //end save transaction
                        }
                    }
                    $arg = array(
                        'ID' => $order->get_id(),
                        'post_author' => $cashier_id,
                    );
                    if($source_type == "order_exchange" && $source){
                        
                        $paren_order_number = isset($source['order_number']) ? $source['order_number'] : 0;
                        
                        if($paren_order_number)
                        {
                            if($use_hpos){
                                $order->update_meta_data('_op_source_order_number',$paren_order_number);
                                $order->save();
                            }else{
                                update_post_meta($order->get_id(),'_op_source_order_number',$paren_order_number);
                            }
                        }
                        $parent_order_id = $this->order_class->get_order_id_from_number($paren_order_number);
                        if($parent_order_id)
                        {
                            $arg['post_parent'] = $parent_order_id;

                            $this->exchange_class->saveNewExchange($parent_order_id,$order,$session_data);
                        }
                    }
                    if(!$use_hpos)
                    {
                        wp_update_post( $arg );
                    }

                    if($source_type == "hold" && $source){
                        $cart_id = isset($source['order_id']) ? $source['order_id'] : 0;
                        $this->order_class->remove_draft_cart($cart_id);
                    }

                    //add send email
                    $allow_send_op_email_receipt = apply_filters('op_allow_send_op_email_receipt',$email_receipt);
                    if($allow_send_op_email_receipt == 'yes')
                    {
                        $email_result = $this->receipt_class->send_receipt($customer_email,$order_parse_data,$login_cashdrawer_id);
                        if($email_result['status'] == 0)
                        {
                            $result['message'].= $email_result['message'] ;
                        }
                    }
                    
                    $result['status'] = 1;
                    $result['data'] = $this->woo_class->formatWooOrder($order->get_id());
                    do_action('op_add_order_final_after',$result['data']);
                }else{
                    throw new Exception(__('Can not create order.','openpos'));
                }
                
            }catch(Exception $e)
            {
                $result['status'] = 0;
                $result['message'] = $e->getMessage();
            }
            return $result;
        }
        /**
         * Save order
         *
         * @param WP_REST_Request $request
         * @return WP_REST_Response
         */
        public function save($request)
        {
            $result = array(
                'code' =>'unknown_error',
                'response' => array(
                    'status' => 0,
                    'data' => array(),
                    'message' => ''
                ),
                'api_message' => ''
            );
            $lock_key = 'lock_order_';
            try{
                $use_hpos = $this->core_class->enable_hpos();
                $session_data = $this->session_data;
                global $_op_warehouse_id;
                $_op_warehouse_id = isset($session_data['login_warehouse_id']) ? $session_data['login_warehouse_id'] : 0;
                $order_data_json = $request->get_param('order');
                $order_data = json_decode($order_data_json,true);
                $is_clear = false;
            
                $order_source = $request->get_param('source') ? $request->get_param('source') : 'sync';
                $lock_key .= $order_data['id'];
                $timeout = 60; // lock for 60 seconds
                $lock_value = time() + $timeout;
                if($order_source != 'sync')
                {
                    delete_transient($lock_key);
                }
                $existing_lock = get_transient($lock_key);
                do_action('op_api_before_add_order',$order_data,$session_data,$order_source);
                if($existing_lock)
                {
                    throw new Exception(__('This order is being processed. Please try again later.','openpos'));
                }else{
                    set_transient($lock_key, $lock_value, $timeout);
                }
                $order_result = $this->_add_order($order_data,$order_source,$is_clear);
                if(!$order_result['status'])
                {
                    throw new Exception($order_result['message']);
                }
                delete_transient($lock_key);
                do_action('op_api_after_add_order',$order_data,$session_data,$order_source,$order_result);
                $result['response']['data'] = $order_result['data'];
                $result['response']['status'] = 1;
                $result['code'] = 200;
                
            }catch(Exception $e)
            {
                delete_transient($lock_key);
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['message'] = $e->getMessage();
                
            }
            return $this->rest_ensure_response($result);
        }
        public function update_order($request)
        {
            $result = array(
                'code' =>'unknown_error',
                'response' => array(
                    'status' => 0,
                    'data' => array(),
                    'message' => ''
                ),
                'api_message' => ''
            );
            try{
                $session_data = $this->session_data;
                $order_post_data = json_decode($request->get_param('order'),true);
                $is_refund = false;
                $is_exchange = false;
                $login_warehouse_id = isset($session_data['login_warehouse_id']) ? $session_data['login_warehouse_id'] : 0;
                $order_id = $order_post_data['order_id'];
                $order_number = isset($order_post_data['order_number']) ? $order_post_data['order_number'] : 0;
                
                $order = wc_get_order($order_id);
                if($order_number && !$order )
                {
                    $tmp_order_id = $this->order_class->get_order_id_from_number($order_number);
                    if($tmp_order_id)
                    {
                        $order_id = $tmp_order_id;
                        $order = wc_get_order($order_id);
                    }
                }
                if(!$order){
                    $tmp_time_test = date('Y',round($order_id/1000));
                    $today_year = date('Y');
                    $is_local = (!$order_number  || ($today_year - $tmp_time_test) < 10) ? true : false;
                    if(  $is_local && isset($order_post_data['id']))
                    {
                        $_id = $order_post_data['id'];
                        $tmp_order_id = $this->order_class->get_order_id_from_local_id($_id);
                      
                        if($tmp_order_id)
                        {
                            $order_id = $tmp_order_id;
                            $order = wc_get_order($order_id);
                        }
                    }
                    
                }
                if(isset($order_post_data['refunds']) && !empty($order_post_data['refunds'])){
                    $is_refund = true;
                }
                if(isset($order_post_data['exchanges']) && !empty($order_post_data['exchanges'])){
                    $is_exchange = true;
                }
                $_order = false;
                $order_data = array();
                if((!$is_exchange && !$is_refund) || !$order_id)
                {
                    $order_result = $this->_add_order($order_post_data,'update',false);
                    if(!$order_result['status'])
                    {
                        throw new Exception($order_result['message']);
                    }
                    $order_data = $order_result['data'];
                    $_order = wc_get_order($order_data['order_id']);
                }else{
                   
                    $_order = wc_get_order($order_id);
                    if($_order)
                    {
                        $_order->update_meta_data( '_op_order', $order_post_data );
                        $_order->save();
                    }
                    
                }
                
                if($_order)
                {
                    $order_data = $this->woo_class->formatWooOrder($order_id);
                    if(isset($order_post_data['refunds']) && !empty($order_post_data['refunds']))
                    {
                        $order_refunds = $order->get_refunds();
                        $post_refunds = $order_post_data['refunds'];
    
                        $order_items = $order->get_items();
                        $order_fee_items = $order->get_items('fee');
                        
                        $warehouse_id = $order->get_meta('_op_sale_by_store_id');

                        foreach($post_refunds as $_refund)
                        {
    
                            $refund_reason = isset($_refund['reason']) ? $_refund['reason'] : '';
                            $refund_restock = isset($_refund['restock']) ? $_refund['restock'] : 'yes';
                            $allow_add = true;
                            foreach($order_refunds as $order_refund)
                            {
                                $order_refund_id = $order_refund->get_id();
                                $local_id = $order_refund->get_meta('_op_local_id',true); // get_post_meta($order_refund_id,'_op_local_id',true);
                                if($local_id == $_refund['id'])
                                {
                                    $allow_add = false;
                                }
                            }
                            if($allow_add)
                            {
                                $line_items = array();
                                
                                foreach($_refund['items'] as $refund_item)
                                {
                                    $item_local_id = $refund_item['id'];
                                    $tax_details = $refund_item['tax_details'];
                                    $item_type = $refund_item['item_type'] ? $refund_item['item_type'] : '';
                                    $check_order_id = wc_get_order_id_by_order_item_id($item_local_id);
                                    $item_id = $item_local_id;
                                    if($check_order_id != $order_id)
                                    {
                                        if($item_type == 'fee')
                                        {
                                            foreach($order_fee_items as $order_item)
                                            {
                                                $order_local_item_id = $order_item->get_meta('_op_local_id');
                                                if($order_local_item_id == $item_local_id)
                                                {
                                                    $item_id = $order_item->get_id();
                                                }
                                            }
                                        }else{
                                            foreach($order_items as $order_item)
                                            {
                                                $order_local_item_id = $order_item->get_meta('_op_local_id');
                                                if($order_local_item_id == $item_local_id)
                                                {
                                                    $item_id = $order_item->get_id();
                                                }
                                            }
                                        }
                                    }
                                    $refund_tax = array();
                                    foreach($tax_details as $k => $v)
                                    {
                                        $rate_id = $v['rate_id'];
                                        $refund_tax[$rate_id] = $v['total'];
                                    }
                                   
                                    $line_items[ $item_id ] = array(
                                        'qty'          => 1 * $refund_item['qty'],
                                        'refund_total' => 1 * $refund_item['refund_total'],
                                        'refund_tax'   => $refund_tax,
                                    );
                                }
                                $refund_amount = $_refund['refund_total'];
                                $remaining_refund_amount     = $order->get_remaining_refund_amount();
                                if($refund_amount > $remaining_refund_amount)
								{
									$refund_amount = $remaining_refund_amount;
								}
                                if($refund_amount > 0)
                                {
                                    global $op_refund_restock;
                                    $op_refund_restock = $refund_restock == 'no' ? 'no' : 'yes';
                                    $restock_items = $refund_restock == 'no' ? false : true;
    
                                    if($login_warehouse_id > 0)
                                    {
                                        $restock_items = false;
                                    }
     
                                    $refund_arg = apply_filters('update_order:refund_arg',array(
                                        'amount'         => $refund_amount,
                                        'reason'         => $refund_reason,
                                        'order_id'       => $order_id,
                                        'line_items'     => $line_items,
                                        'refund_payment' => false,
                                        'restock_items'  => $restock_items,
                                    ));
                                   
                                    $refund = wc_create_refund($refund_arg);
    
                                    if( $refund instanceof WP_Error)
                                    {
                                        throw new Exception($refund->get_error_message().' - max:'.$remaining_refund_amount);
                                    }else{
                                        
                                        $refund->update_meta_data('_op_local_id',$_refund['id']);
                                        $refund->update_meta_data('_op_refund_total',$_refund['refund_total']);
                                        $refund->save();
                                    }
                                }
                            }
                        }
                    }
                    //exchange
                    if(isset($order_post_data['exchanges']) && !empty($order_post_data['exchanges']) && false) // disable exchange old
                    {
                        $pos_exchange_partial_refund = $this->settings_api->get_option('pos_exchange_partial_refund','openpos_general');
                        
                       
                        
                        $order_refunds = $order->get_refunds();
                        $post_exchanges = $order_post_data['exchanges'];
    
                        $order_items = $order->get_items();
                        $warehouse_id = $order->get_meta('_op_sale_by_store_id');
                        
                        foreach($post_exchanges as $_exchange)
                        {
                            $refund_reason = isset($_exchange['reason']) ? $_exchange['reason'] : '';
                            $allow_add = true;
                            foreach($order_refunds as $order_refund)
                            {
                                $order_refund_id = $order_refund->get_id();
                                $local_id = get_post_meta($order_refund_id,'_op_local_id',true);
                                if($local_id == $_exchange['id'])
                                {
                                    $allow_add = false;
                                }
                            }
                            if($allow_add)
                            {
                                $this->exchange_class->save($order_id,$_exchange,$session_data);
                                $line_items = array();
                                foreach($_exchange['return_items'] as $refund_item)
                                {
                                    $item_local_id = $refund_item['id'];
                                    $check_order_id = wc_get_order_id_by_order_item_id($item_local_id);
                                    if($check_order_id == $order_id)
                                    {
                                        $item_id = $item_local_id;
                                    }else{
                                        foreach($order_items as $order_item)
                                        {
                                            $order_local_item_id = $order_item->get_meta('_op_local_id');
                                            if($order_local_item_id == $item_local_id)
                                            {
                                                $item_id = $order_item->get_id();
                                            }
                                        }
                                    }
    
    
                                    $line_items[ $item_id ] = array(
                                        'qty'          => 1 * $refund_item['qty'],
                                        'refund_total' => 1 * $refund_item['refund_total'],
                                        'refund_tax'   => array(),
                                    );
                                }
    
                                if($_exchange['fee_amount'] > 0)
                                {
                                    $fee_item = new WC_Order_Item_Fee();
                                    $fee_item->set_name(__('Exchange Fee','openpos'));
                                    $fee_item->set_total($_exchange['fee_amount']);
                                    $fee_item->set_amount($_exchange['fee_amount']);
                                    $order->add_item($fee_item);
                                }
                                $addition_total = $_exchange['addition_total'] - $_exchange['fee_amount'];
                                if( $addition_total  > 0)
                                {
                                    $fee_item = new WC_Order_Item_Fee();
                                    $fee_item->set_name(__('Addition total for exchange items','openpos'));
                                    $fee_item->set_total($addition_total);
                                    $fee_item->set_amount($addition_total);
                                    $order->add_item($fee_item);
                                }
                                $order->calculate_totals(false);
                                $order->save();
                            }
                        }
    
    
                    }
                }
                do_action('op_update_order_after',$order_data,$order_post_data);
                
                $result['response']['data'] = $order_data;
                $result['response']['status'] = 1;
                $result['code'] = 200;
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['message'] = $e->getMessage();
                
            }
            return $this->rest_ensure_response($result);
        }
        public function pending_order($request){
            $result = array(
                'code' =>'unknown_error',
                'response' => array(
                    'status' => 0,
                    'data' => array(),
                    'message' => ''
                ),
                'api_message' => ''
            );
            try{
                $use_hpos = $this->core_class->enable_hpos();
                $session_data = $this->session_data;
                global $_op_warehouse_id;
                $_op_warehouse_id = isset($session_data['login_warehouse_id']) ? $session_data['login_warehouse_id'] : 0;
                $order_data_json = $request->get_param('order');
                $order_data = json_decode($order_data_json,true);

                $payment_parse_data = json_decode($request->get_param('payment'),true);
                $is_clear = false;
            
                $order_source = $request->get_param('source') ? $request->get_param('source') : 'sync';
                $order_result = $this->_add_order($order_data,$order_source,$is_clear);
                if(!$order_result['status'])
                {
                    throw new Exception($order_result['message']);
                }
                $order = wc_get_order($order_result['data']['order_id']);
                $payment_data = apply_filters('op_pending_payment_method_data',$payment_parse_data,$order_result);
                if(!empty($payment_data))
                {
                    if($use_hpos )
                    {
                        $order->update_meta_data('pos_payment',$payment_data);
                    }else{
                        add_post_meta($order_result['data']['order_id'],'pos_payment',$payment_data);
                    }
                    
                }

                do_action('op_pending_payment_order',$order,$payment_data);

                
               
                $checkout_url = $order->get_checkout_payment_url();
                $image_url = $this->core_class->generateQRcode($checkout_url,100,100);
                $guide_html = '<div class="checkout-container">';
                $guide_html .= '<p style="text-align: center" id="payment-qr-image"><img  src="'.$image_url.'" /></p>';
                $guide_html .= '<p  style="text-align: center">Please checkout with scan QrCode or <a target="_blank" href="'.esc_url($checkout_url).'">click here</a> to continue checkout</p>';
                $guide_html .= '</div>';
                $result['response']['data']['checkout_guide']  = apply_filters('op_order_checkout_guide_data',$guide_html,$order,$payment_data);
                $result['response']['data']['order']  = $order_result['data'];
               
                $result['response']['status'] = 1;
                $result['code'] = 200;
                
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['message'] = $e->getMessage();
                
            }
            return $this->rest_ensure_response($result);
        }
        public function _formatApiOrderItem($item)
        {
            //$currency = $session_data['setting']['currency'];
            //$decimal = $currency['decimal'];
            $item = array(
                'id' => null, // Optional, for SQLite row id
                'itemType' => '',
                'itemKey' => '', // Unique key for the item, use check new item or existing item

                'variantId' => null,
                'productId' => null,
                'productName' => '',
                'productSku' => '',
                'productImage' => '',
                'barcode' => '',
                'barcodeDetails' => null,
                'productPrice' => 0,
                'productPriceInclTax' => 0,
                'product' => null, // Product data if available, can be null if not set
                'customPrice' => null, // Custom price if applicable, can be null if not set
                'price' => 0, // product price or variation price
                'priceInclTax' => 0, // Price including tax
                'finalPrice' => 0, // price with option and bundle
                'finalPriceInclTax' => 0, // price with option and bundle incl tax
                'options' => '',
                'bundles' => '',
                'groupItems' => '',
                'variations' => '',
                'optionPrice' => 0, // Total price of options
                'bundlePrice' => 0, // Total price of bundles
                'groupItemPrice' => 0, // Total price of group items
                'note' => '',
                'qty' => 0,

                'discounts' => array(),
                'discount' => 0,
                'discountTax' => 0,
                'discountInclTax' => 0,

                'discountRules' => null,
                'discountRuleTotal' => 0, // Total discount rules amount, can be 0 if no rules applied
                'discountRuleTax' => 0, // Total discount rules tax amount, can be 0 if no rules applied
                'discountRuleInclTax' => 0, // Total discount rules amount including tax, can be 0 if no rules applied

                'shipping' => 0,
                'shippingTax' => 0,
                'shippingInclTax' => 0,

                'subtotal' => 0,
                'subtotalInclTax' => 0,
                'tax' => 0,
                'taxDetails' => array(),

                'total' => 0,
                'totalInclTax' => 0,

                'isShipping' => false,
                'isSync' => false,
                'createdAt' => 0,
                'updatedAt' => 0,
            );
            return $item;
        }
        public function _formatApiOrder($order_id,$currency = null)
        {
            $formatted_order = $this->woo_class->formatWooOrder($order_id);
            return $formatted_order;
            // $currency_pos = wc_get_price_decimals(); 
            // $decimal = isset($currency['decimal']) ? $currency['decimal'] : $currency_pos;
            // $register = null;
            // $outlet = null;
            // $cashier = null;
            // $seller = null;
            // $order = array(
            //     'id' => $formatted_order['id'], // Optional, for SQLite row id
            //     'localId' => $formatted_order['order_id'], // Optional, for SQLite row id
            //     'sessionId' => '',
            //     'cartType' => '',
            //     'cartSource' => '',
            //     'cartSourceDetails' => '',
            
            //     'orderNumber' => $formatted_order['order_number'], // Order number, can be empty if not assigned
            //     'orderId' => $formatted_order['order_id'], // Order ID, can be null if not assigned
            //     'orderNumberFormatted' => $formatted_order['order_number_format'], // Formatted order number, can be empty if not assigned
            
            //     'label' => $formatted_order['title'],
            //     'customerId' => 0,
            //     'customer' => $formatted_order['customer'], // Customer|null
            //     'seller' => $seller,   // User|null
            //     'cashier' => $cashier,  // User|null
            //     'register' => $register, // User|null
            //     'outlet' => $outlet,   // User|null
            
            //     'coupons' => [],
            //     'coupounTotal' => 0, // Total coupon amount, can be 0 if no coupons applied
            //     'couponTax' => 0,    // Total coupon tax amount, can be 0 if no coupons applied
            //     'couponInclTax' => 0, // Total coupon amount including tax, can be 0 if no coupons applied
            
            //     'discountRules' => null,
            //     'discountRuleTotal' => 0, // Total discount rules amount, can be 0 if no rules applied
            //     'discountRuleTax' => 0,   // Total discount rules tax amount, can be 0 if no rules applied
            //     'discountRuleInclTax' => 0, // Total discount rules amount including tax, can be 0 if no rules applied
            
            //     'discounts' => '', // manual discount
            //     'discount' => 0,
            //     'discountTax' => 0,
            //     'discountInclTax' => 0,
            
            //     'pickupDate' => '', // Optional, for pickup date if applicable
            //     'pickupTime' => '', // Optional, for pickup time if applicable
            //     'pickupLocation' => '', // Optional, for pickup location if applicable
            //     'pickupNote' => '', // Optional, for pickup note if applicable
            
            //     'shipping' => '',
            //     'shippingNote' => '',
            //     'shippingCost' => 0,
            //     'shippingTax' => 0,
            //     'shippingTotal' => 0,
            //     'shippingInclTax' => 0, // Total shipping cost including tax
            
            //     'subTotal' => 0,
            //     'itemTax' => 0,
            //     'subtotalInclTax' => 0,
            
            //     'feeTotal' => 0, // Total fee amount excl tax
            //     'feeTax' => 0,   // Total fee tax amount
            //     'feeInclTax' => 0, // Total fee amount including tax
            
            //     'taxDetails' => '',
            //     'tax' => $this->_convertToCent($formatted_order['grand_total'],$decimal), // Total tax amount
            //     'grandTotal' => $this->_convertToCent($formatted_order['tax_amount'],$decimal), // grand total with tax
            
            //     'state' => $formatted_order['state'], // Order state, can be 'pending', 'processing', 'completed', 'cancelled', etc.
            //     'status' => $formatted_order['status'], // Order status, can be 'pending', 'processing', 'completed', 'cancelled', etc.
            //     'totalPaid' => $this->_convertToCent($formatted_order['total_paid'],$decimal), // total paid amount
            //     'paymentTransactions' => null, // Array of payment transactions, can be null if no transactions
            
            //     'isGift' => false,
            //     'isShipping' => false,
            //     'isSync' => true,
            //     'isPaid' => false,
            //     'isPrinted' => false,
            //     'isSyncCart' => false,
            
            //     'feeItems' => array(), // Optional, for fee items if any
            //     'items' => array(),     // You can replace 'any' with your CartItem[] interface if available
            //     'currency' => null,
            //     'note' => '', // Optional, for pickup note if applicable
            
            //     'additionalData' => $formatted_order['addition_information'], // Optional, for any additional data related to the order
            
            //     'createdAt' => 0,
            //     'updatedAt' => 0,
            // );
            // return $order;
        }
        public function search_orders($request){
            $result = array(
                'code' =>'unknown_error',
                'response' => array(
                    'status' => 0,
                    'data' => array(),
                    'message' => '',
                ),
                'api_message' => ''
            );
            try{
                $session_data = $this->session_data;
                $term = $request->get_param('term') ? $request->get_param('term') : '';
                if(strlen($term) > 1)
                {
                    $term = trim($term);
                    $post_type = 'shop_order';
                    $term_id = $this->order_class->get_order_id_from_number($term);
                    $orders = array();
                    $order = wc_get_order($term);
                    if($order )
                    {
                        $orders[] = $order;
                    }
                    $order = wc_get_order($term_id);
                    if($order )
                    {
                        $orders[] = $order;
                    }
                    if(!$order || !$term_id)
                    {
                        $term_id = $this->order_class->get_order_id_from_order_number_format($term);
                    
                        if($term_id)
                        {
                            $order = wc_get_order($term_id);
                            if($order )
                            {
                                $orders[] = $order;
                                
                            }
                        }
                        
                    }
                    
                    $args = array(
                        'post_type' => $post_type,
                        'post_status' => 'any',
                        'meta_query' => array(
                            'relation' => 'OR',
                            array(
                                'key' => '_billing_email',
                                'value' => $term,
                                'compare' => 'like',
                            ),
                            array(
                                'key' => '_billing_phone',
                                'value' => $term,
                                'compare' => 'like',
                            ),
                            array(
                                'key' => '_billing_address_index',
                                'value' => $term,
                                'compare' => 'like',
                            ),


                        ),
                        'posts_per_page' => 10,
                        'orderby'   => array(
                            'date' =>'DESC',

                        )
                    );
                    if($this->core_class->enable_hpos())
                    {
                        $args = array(
                            'type' => $post_type,
                            'limit' => 10,
                            'paginate' => 1,
                            'page' => 1,
                            'post_status' => 'any',
                            'orderby' => 'date',
                            'order' => 'DESC',
                            's' => $term,
                            'search_filter' => 'all'
                        );
                    }
                    $args = apply_filters('op_search_order_args',$args);
                    if($this->core_class->enable_hpos())
                    {
                        $_orders_result = wc_get_orders( $args );
                        $_orders = $_orders_result->orders;
                        foreach($_orders as $_order)
                        {
                            if($_order)
                            {
                                $orders[] = $_order; 
                            }
                        }
                        
                    }else{
                        $query = new WP_Query($args);
                        $_orders = $query->get_posts();
                        $orders = array_merge($orders,$_orders);
                    }
                    if(count($orders) > 0)
                    {
                        $result_orders = array();
                        foreach($orders as $_order)
                        {
                            $order_id = 0;
                            if($_order instanceof WC_Order )
                            {
                                $order_id = $_order->get_id();
                                $formatted_order = $this->woo_class->formatWooOrder($_order->get_id());
                            }else{
                                
                                $order_id = $_order->ID;
                                
                                $formatted_order = $this->woo_class->formatWooOrder($_order->ID);
                            }
                            
                            if(!$formatted_order || empty($formatted_order))
                            {
                                continue;
                            }
                            $result_orders[$order_id] = $formatted_order;
                            
                        }
                        $result['response']['data'] = array_values($result_orders);
                        $result['response']['status'] = 1;
                    }else{
                        throw new Exception(__('Order is not found', 'openpos'));
                    }

                    //$query = new WP_Query($args);


                }else{
                    throw new Exception( __('Order number too short','openpos') );

                }
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['response']['message'] = $e->getMessage();
                
            }
            return $this->rest_ensure_response($result);
        }
        public function add_note($request){
            $result = array(
                'code' =>'unknown_error',
                'response' => array(
                    'status' => 0,
                    'data' => array(),
                    'message' => '',
                ),
                'api_message' => ''
            );
            try{
                $session_data = $this->session_data;
                $order_number_old = $request->get_param('order_number');
                $order_id_old = $request->get_param('order_id');
                $order_local_id_old = $request->get_param('local_order_id');
                $order_status = $request->get_param('order_status');
                $order_note = esc_textarea($request->get_param('note'));
                $order_number = $this->order_class->get_order_id_from_number($order_number_old);
            
                $order = wc_get_order($order_number);
                if(!$order)
                {
                    $order = wc_get_order($order_number_old);
                }
                if(!$order)
                {
                    $order_number = $this->woo_class->get_order_id_from_local_id($order_number_old);
                    $order = wc_get_order($order_number);
                }

                if(!$order)
                {
                    $order_number = $this->order_class->get_order_id_from_local_id($order_local_id_old);
                    $order = wc_get_order($order_number);
                }
                if(!$order)
                {
                    $order_number =  $this->order_class->get_order_id_from_local_id($order_id_old);
                    $order = wc_get_order($order_number);
                }
                if($order)
                {
                    if(!$order_status)
                    {
                        $this->order_class->addOrderNote($order->get_id(),$order_note);
                    }else{
                        $this->order_class->addOrderStatusNote($order->get_id(),$order_note,$order_status);
                    }
                    
                    $result['response']['status'] = 1;
                    do_action('op_save_order_note_after',$order_note,$order_status,$order);
                }else{
                    throw new Exception(__('Order not found.','openpos'));
                }
                $result['code'] = 200;
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['response']['message'] = $e->getMessage();
                
            }
            return $this->rest_ensure_response($result);
        }
        public function send_receipt($request){
            global $is_openpos_email;
            $result = array(
                'code' =>'unknown_error',
                'response' => array(
                    'status' => 0,
                    'data' => array(),
                    'message' => '',
                ),
                'api_message' => ''
            );
            try{
                $is_openpos_email = true;
                $session_data = $this->session_data;
                $order_data = json_decode($request->get_param('order'),true);
                $send_to = $request->get_param('to');
                $register_id = isset($session_data['login_cashdrawer_id']) ? $session_data['login_cashdrawer_id'] : 0;
                
                if(is_email($send_to))
                {
                    $result['response'] = $this->receipt_class->send_receipt($send_to,$order_data,$register_id,'manual');
                }else{
                    $result['response']['message'] = __('Your email address is incorrect. Please check again!','openpos');
                }
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['response']['message'] = $e->getMessage();
            }
            return $this->rest_ensure_response($result);
        }
        public function pickup($request){
            $result = array(
                'code' =>'unknown_error',
                'response' => array(
                    'status' => 0,
                    'data' => array(),
                    'message' => '',
                ),
                'api_message' => ''
            );
            try{
                $order_data = json_decode($request->get_param('order'),true);
                $pickup_note = esc_textarea($request->get_param('pickup_note'));
                $session_data = $this->session_data;

                $use_hpos = $this->core_class->enable_hpos();

                if($order_data['allow_pickup'])
                {
                    $order_id = $order_data['system_order_id'];
                    $order = wc_get_order($order_id);
                    if($order)
                    {
                        if(!$pickup_note)
                        {
                            $pickup_note = 'Pickup ';
                        }
                        $pickup_note.= ' By '.$session_data['username'];
                        $order->update_status('wc-completed',$pickup_note);
                        if($use_hpos)
                        {
                            $order->update_meta_data('_op_order_pickup_by',$session_data['username']);
                        }else{
                            update_post_meta($order->get_id(),'_op_order_pickup_by',$session_data['username']);
                        }
                        
                        $result['response']['status'] = 1;
                        $result['response']['data'] = $this->woo_class->formatWooOrder($order->get_id());
                    }else{
                        throw new Exception(__('Order is not found','openpos'));
                    }
                }else{
                    throw new Exception(__('Order do not allow pickup from store','openpos'));
                }
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['response']['message'] = $e->getMessage();
            }
            return $this->rest_ensure_response($result);
        }
        public function customer_orders($request){
            $result = array(
                'code' =>'unknown_error',
                'response' => array(
                    'status' => 0,
                    'data' => array(),
                    'message' => '',
                ),
                'api_message' => ''
            );
            try{
                $customer_id = (int)$request->get_param('customer_id');
                $current_page = $request->get_param('page') ? (int)$request->get_param('page') : 1;
                $use_hpos = $this->core_class->enable_hpos();
                if(!$customer_id)
                {
                    throw new Exception(__('Customer do not exist','openpos'));
                }
                $customer = new WC_Customer($customer_id);
                if(!$customer)
                {
                    throw new Exception(__('Customer do not exist','openpos'));
                }
                $total_order_count = $customer->get_order_count();
                $per_page = 10;

                $total_page = ceil($total_order_count / $per_page);

                $data = array(
                    'total_page' => $total_page,
                    'orders' => array()
                );
               
                $offset = ($current_page -1) * $per_page;
            

                if($use_hpos)
                {
                    $post_type = 'shop_order';
                
                    $args = array(
                        'numberposts' => $per_page,
                        'post_type' => $post_type,
                        'post_status' => 'any',
                        'customer_id' => $customer_id,
                        'offset'           => $offset,
                        'orderby'          => 'date',
                        'order'            => 'DESC',
                    );
                    $args['_query_src'] = 'op_order_query';
                    $data_store = WC_Data_Store::load( 'order' );
                    $customer_orders = $data_store->query( $args );
                
                }else{
                    $query_params = array(
                        'numberposts' => $per_page,
                        'meta_key'    => '_customer_user',
                        'meta_value'  => $customer_id,
                        'post_type'   => wc_get_order_types( 'view-orders' ),
                        'post_status' => array_keys( wc_get_order_statuses() ),
                        'offset'           => $offset,
                        'customer' => $customer_id,
                        'orderby'          => 'date',
                        'order'            => 'DESC',
                    ) ;
                    $customer_orders = get_posts( $query_params );
                }
                

                foreach($customer_orders as $customer_order)
                {
                    if($customer_order instanceof WC_Order )
                    {
                        $order_id = $customer_order->get_id();
                    }else{
                        $order_id = $customer_order->ID;
                    }
                    
                    $formatted_order = $this->woo_class->formatWooOrder($order_id);
                    if(!empty($formatted_order))
                    {
                        $data['orders'][] =  $formatted_order;
                    }
                }

                $result['response']['data'] = $data;
                $result['response']['status'] = 1;
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['response']['message'] = $e->getMessage();
            }
            return $this->rest_ensure_response($result);
        }

        public function close_order($request){
            
            $result = array(
                'code' =>'unknown_error',
                'response' => array(
                    'status' => 0,
                    'data' => array(),
                    'message' => '',
                ),
                'api_message' => ''
            );
            try{
                global $_op_warehouse_id;
                $session_data = $this->session_data;
                $use_hpos = $this->core_class->enable_hpos();
                $login_warehouse_id = isset($session_data['login_warehouse_id']) ? $session_data['login_warehouse_id'] : 0;
                $_op_warehouse_id = $login_warehouse_id;

                $order_data = json_decode($request->get_param('order'),true);
                $order_number = $order_data['order_number'];
                if($order_number)
                {
                    $order_number = $this->order_class->get_order_id_from_number($order_number);
                }
                
                if((int)$order_number > 0)
                {
                    $order = wc_get_order($order_number);
                    $orders = array();
                    if($order )
                    {
                        $orders[] = $order;
                    }

                    if(count($orders) > 0)
                    {

                        $_order = end($orders);
                        $formatted_order = $this->woo_class->formatWooOrder($_order->get_id());
                        $result['response']['data'] = $formatted_order;
                        $payment_status = $formatted_order['payment_status'];
                        $status = '';
                        if($payment_status != 'paid')
                        {
                            $pos_order =  $order;
                            
                            
                            if($use_hpos)
                            {
                                $_order->update_meta_data('_op_order_close_by',$session_data['username']);
                            }else{
                                update_post_meta($_order->get_id(),'_op_order_close_by',$session_data['username']);
                            }
                            $status = apply_filters('op_woocommerce_cancelled_order_status','cancelled',$pos_order);
                            if($status == 'cancelled')
                            {
                                //$pos_order->close_order();
                            }
                            $pos_order->update_status($status ,__('Closed from POS','openpos'));

                            $result['response']['status'] = 1;
                        }else{
                            $result['response']['message'] = __('You can not close a order has been paid! Please complete order by click Check Payment button.', 'openpos');

                        }
                        do_action( 'op_woocommerce_cancelled_order', $_order->get_id(), $status );
                        
                    }else{
                        throw new Exception( __('Order is not found', 'openpos'));
                    }

                }else{
                    throw new Exception(__('Order is not found', 'openpos'));

                }
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['response']['message'] = $e->getMessage();
            }
            return $this->rest_ensure_response($result);
        }
        public function check_order($request){
            
            $result = array(
                'code' =>'unknown_error',
                'response' => array(
                    'status' => 0,
                    'data' => array(),
                    'message' => '',
                ),
                'api_message' => ''
            );
            try{
                global $op_woo_order;
                $order_number = esc_textarea($request->get_param('order_number'));
                if($order_number)
                {
                    $order_number = $op_woo_order->get_order_id_from_number($order_number);
                }
                if((int)$order_number > 0)
                {
                    $order = wc_get_order($order_number);
                    $orders = array();
                    if($order )
                    {
                        $orders[] = $order;
                    }

                    if(count($orders) > 0)
                    {

                        $_order = end($orders);
                        $formatted_order = $this->woo_class->formatWooOrder($_order->get_id());
                        $result['response']['data'] = $formatted_order;
                        $payment_status = $formatted_order['payment_status'];
                        $result['response']['message'] = __('Payment Status : ','openpos').$payment_status;
                        $result['response']['status'] = 1;
                    }else{
                        throw new Exception( __('Order is not found','openpos') );
                    }

                    //$query = new WP_Query($args);


                }else{
                    throw new Exception(__('Order number too short','openpos'));

                }
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['response']['message'] = $e->getMessage();
            }
            return $this->rest_ensure_response($result);
        }
        public function refund_order($request){
            
            $result = array(
                'code' =>'unknown_error',
                'response' => array(
                    'status' => 0,
                    'data' => array(),
                    'message' => '',
                ),
                'api_message' => ''
            );
            try{
                global $_op_warehouse_id;
                $session_data = $this->session_data;
                $login_warehouse_id = isset($session_data['login_warehouse_id']) ? $session_data['login_warehouse_id'] : 0;
                $_op_warehouse_id = $login_warehouse_id;

                
                $order_data = json_decode($request->get_param('order'),true);
                $order_number = $order_data['order_number'];
                if($order_number)
                {
                    $order_number = $this->order_class->get_order_id_from_number($order_number);
                }
                
                if((int)$order_number > 0)
                {
                    $order = wc_get_order($order_number);
                    $orders = array();
                    if($order )
                    {
                        $orders[] = $order;
                    }

                    if(count($orders) > 0)
                    {

                        $_order = end($orders);
                        $formatted_order = $this->woo_class->formatWooOrder($_order->get_id());
                        $result['response']['data'] = $formatted_order;
                        $payment_status = $formatted_order['payment_status'];
                        $status = '';
                        if($payment_status != 'paid')
                        {
                            $pos_order =  $order;
                            
                            
                            if($this->_enable_hpos)
                            {
                                $_order->update_meta_data('_op_order_close_by',$session_data['username']);
                            }else{
                                update_post_meta($_order->get_id(),'_op_order_close_by',$session_data['username']);
                            }
                            $status = apply_filters('op_woocommerce_cancelled_order_status','cancelled',$pos_order);
                            if($status == 'cancelled')
                            {
                                //$pos_order->close_order();
                            }
                            $pos_order->update_status($status ,__('Closed from POS','openpos'));

                            $result['response']['status'] = 1;
                        }else{
                            $result['response']['message'] = __('You can not close a order has been paid! Please complete order by click Check Payment button.', 'openpos');

                        }
                        do_action( 'op_woocommerce_cancelled_order', $_order->get_id(), $status );
                        
                    }else{
                        throw new Exception( __('Order is not found', 'openpos'));
                    }

                    //$query = new WP_Query($args);


                }else{
                    throw new Exception(__('Order is not found', 'openpos'));

                }
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['response']['message'] = $e->getMessage();
            }
            return $this->rest_ensure_response($result);
        }
        
    }
}