<?php
if(!class_exists('OP_REST_API_Transaction'))
{
    class OP_REST_API_Transaction extends OP_REST_API{
        public function register_routes() {
            register_rest_route( $this->namespace, '/transaction/transactions', array(
                'methods' =>  WP_REST_Server::CREATABLE,
                'callback' => array($this,'transactions'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/transaction/create', array(
                'methods' =>  WP_REST_Server::CREATABLE,
                'callback' => array($this,'save'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/transaction/add-order-transaction', array(
                'methods' =>  WP_REST_Server::CREATABLE,
                'callback' => array($this,'add_order_transaction'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );

            
            register_rest_route( $this->namespace, '/transaction/get/(?P<transaction_id>\d+)', array(
                'methods' => 'GET',
                'callback' => array($this,'get_transaction'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
        }
        public function transactions(WP_REST_Request $request = null){
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
                $register_id = isset($this->session_data['login_cashdrawer_id']) ? $this->session_data['login_cashdrawer_id'] : 0;
                if(!$register_id)
                {
                    throw new Exception(__('Register not found','openpos'));
                }
                $order_id_old = $request->get_param('order_id');
                $order_number_old = $request->get_param('order_number');
                $order_local_id_old = $request->get_param('local_order_id');
                $register_id = $request->get_param('register_id');
                if($order_id_old || $order_number_old || $order_local_id_old )
                {
                    $order = false;
                    if($order_id_old)
                    {
                        $order = wc_get_order($order_id_old);
                        $order_number = $this->order_class->get_order_id_from_local_id($order_id_old);
                        $order = wc_get_order($order_number);
                    }
                    if(!$order)
                    {
                        $order_id = $this->order_class->get_order_id_from_number($order_number_old);
                        $order = wc_get_order($order_id);
                    }
                    
                    if(!$order)
                    {
                        $order = wc_get_order($order_number_old);
                    }
                    if(!$order)
                    {
                        $order_number = $this->order_class->get_order_id_from_local_id($order_number_old);
                        $order = wc_get_order($order_number);
                    }

                    if(!$order)
                    {
                        $order_number = $this->order_class->get_order_id_from_local_id($order_local_id_old);
                        $order = wc_get_order($order_number);
                    }
                
                    if($order)
                    {
                        
                        $order_formatted = $this->woo_class->formatWooOrder($order);
                    
                        $_order_total_paid = isset($order_formatted['total_paid']) ? $order_formatted['total_paid'] : $order->get_total();

                    
                        $transactions = array();
                        if($order_formatted['status'] == 'refunded')
                        {
                            $transactions = $this->woo_class->getOrderTransactions($order->get_id(),array( 'order','refund_order'));
                        }else{
                            $transactions = $this->woo_class->getOrderTransactions($order->get_id(),array( 'order','refund_order'));
                        }
                        
                        $total_paid = 0;
                        foreach($transactions as $transaction)
                        {
                            $result['response']['data']['transactions'][] = array(
                                'in_amount' => $transaction['in_amount'],
                                'out_amount' => $transaction['out_amount'],
                                'created_at' => $transaction['created_at'],
                                'ref' => $transaction['ref'],
                                'payment_name' => $transaction['payment_name'],
                                'payment_code' => $transaction['payment_code'],
                                'payment_ref' => $transaction['payment_ref'],
                                'created_by' => $transaction['created_by'],
                            );
                            $total_paid += ( 1* $transaction['in_amount'] - 1 * $transaction['out_amount']);
                        }

                        $_order_customer_paid_amount = isset($order_formatted['customer_total_paid']) ? 1 * $order_formatted['customer_total_paid'] : $total_paid;

                        if($order_formatted['status'] == 'refunded')
                        {
                            $result['response']['data']['customer_total_paid'] = 0;
                            $result['response']['data']['total_paid'] = 0;
                            $result['response']['data']['total_remain'] = 0;
                        }else{
                            $result['response']['data']['total_paid'] = $total_paid;
                            $result['response']['data']['total_remain'] =  ($_order_total_paid - $_order_customer_paid_amount);//$total - $total_paid;
                        }
                        $result['response']['status'] = 1;
                    }else{
                        throw new Exception(__('Order not found.','openpos'));
                    }
                   
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
        public function save(WP_REST_Request $request = null){
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
                $transaction = json_decode($request->get_param('transaction'),true);
                
                $transaction_id =  isset($transaction['id']) ? $transaction['id'] : 0;
                $transient_key = 'adding_transaction_'.$transaction_id;
                $done_transient_key = 'done_transaction_'.$transaction_id;
                if($transaction_id)
                {
                    
                    $done_transaction_data = $this->session_class->get_transient($done_transient_key);
                    $transaction_data = $this->session_class->get_transient($transient_key);
                    if ( false !== $done_transaction_data ) {
                        $result['response']['status'] = 1;
                        $result['response']['data'] = $done_transaction_data;
                    }else{
                        if ( false !== $transaction_data ) {
                            throw new Exception(__('Transaction is being processed. Please wait a moment.','openpos' ));
                        }
    
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
                            $result['response']['status'] = 1;
                            $result['response']['data'] = $id;
                            if($is_new)
                            {
                                do_action('op_add_transaction_after',$id,$session_data,$transaction_data);
                            }
                        }
                        $this->session_class->delete_transient( $transient_key );
                    }
                    

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
        public function get_transaction(WP_REST_Request $request = null){
            $result = array(
                'code' =>'unknown_error',
                'data' => array(
                    'status' => 0,
                    'result' => array(),
                ),
                'message' => ''
            );
            try{
                $transaction_id = $request->get_param('transaction_id');
                if(!$transaction_id)
                {
                    throw new Exception(__('Transaction ID is required','openpos'));
                }
                $register = isset($this->session_data['register']) ? $this->session_data['register'] : [];
                if(!isset($register['id']))
                {
                    throw new Exception(__('Register not found','openpos'));
                }
                $warehouse_id = $register['outlet_id'];
                $transaction = $this->transaction_class->get($transaction_id);
                if(!$transaction)
                {
                    throw new Exception(__('Transaction not found','openpos'));
                }
                $result['data']['result'] = $transaction;
                $result['data']['status'] = 1;
                $result['code'] = 200;
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['data']['status'] = 0;
                $result['message'] = $e->getMessage();
                
            }
            return $this->rest_ensure_response($result);
        }
        public function add_order_transaction($request){
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
                
                $transaction = json_decode($request->get_param('transaction'),true);
            
                $order_number_old = intval($request->get_param('order_number'));
                $order_id_old = intval($request->get_param('order_id'));
                $order_local_id_old = intval($request->get_param('local_order_id'));
                $in_amount = isset($transaction['in_amount']) ? 1*$transaction['in_amount'] : 0;
                $order_id = 0;
				if($order_id_old == $order_local_id_old)
				{
					$order_id = $order_id_old;
				}else{
                    $order_id = $this->order_class->get_order_id_from_number($order_number_old);
                }
                if(!$order_id)
                {
                    $order_id = $this->order_class->get_order_id_from_local_id($order_id_old);
                }
                if(!$order_id)
                {
                    $order_id = $this->order_class->get_order_id_from_local_id($order_local_id_old);
                }
                
                if(!$order_id)
                {
                    
                    throw new Exception(__('Order not found','openpos'));
                }
                $transaction_id =  isset($transaction['id']) ? $transaction['id'] : 0;
                if($in_amount && $transaction_id)
                {
                    $order = $this->woo_class->formatWooOrder($order_id);
                    
                
                    $total_paid = $order['total_paid'];
                    $customer_paid_amount = 1 * $order['customer_total_paid'];
                    
                    $remain_amount = ($total_paid - $customer_paid_amount);
                    if($remain_amount >= $in_amount  )
                    {
                        //take look
                       

                        $transaction_data = $this->transaction_class->formatDataFromJson($transaction,$session_data);
                        $exist_transaction = $this->transaction_class->get_by_local_id($transaction_id);
                        if(!$exist_transaction)
                        {
                            
                            $id = $this->transaction_class->add($transaction_data);
        
                        }else{
                            $transaction = $exist_transaction;
                            $id = $transaction['id'];
                           
                        }
                        $customer_total_paid_amount = $customer_paid_amount + 1*$in_amount;
                        
                        
                        $_order = wc_get_order( $order_id );
                        //update debit
                        $cashdrawer_meta_key = $this->register_class->get_order_meta_key();

                        $register_id =  $_order->get_meta($cashdrawer_meta_key,true);
                        if($register_id !== false)
                        {
                            $this->register_class->addDebitBalance($register_id,(0 - (1*$in_amount) ));
                            $op_remain_amount = $order['total_paid'] - $customer_total_paid_amount;
                            if($op_remain_amount < 0)
                            {
                                $op_remain_amount = 0;
                            }
                            $_order->update_meta_data('_op_remain_paid',$op_remain_amount);
                            
                        }
                
                        
                        //check full payment 
                        if($customer_total_paid_amount >= $order['total_paid'])
                        {
                            $tmp_setting_order_status = $this->setting_class->get_option('pos_order_status','openpos_general');
                            $setting_order_status =  apply_filters('op_order_status_full_pay',$tmp_setting_order_status,$order);
                            
                            $_order->payment_complete();
                            $_order->set_status($setting_order_status, __('Full Payment via OpenPos', 'openpos'));
                            
                        }else{
                            $note = wp_sprintf(__('Paid amount %s  via %s','openpos'),wc_price($in_amount),$transaction['payment_name']); 
                            $this->order_class->addOrderNote($order_id,$note);
                        }
                        $_order->save();
                        $result['response']['status'] = 1;
                        $result['response']['data'] = array(
                            'transaction_id' => $id,
                            'customer_total_paid' => $customer_total_paid_amount
                        );
                    }else{
                        throw new Exception(wp_sprintf(__('Amount not match with remain amount: %s','openpos'),$remain_amount));
                    }
                
                }else{
                    throw new Exception(__('Amount value is incorrect','openpos'));
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
    }
}