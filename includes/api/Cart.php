<?php
if(!class_exists('OP_REST_API_Cart'))
{
    class OP_REST_API_Cart extends OP_REST_API{
        public function register_routes() {
            register_rest_route( $this->namespace, '/cart/carts', array(
                'methods' =>  WP_REST_Server::CREATABLE,
                'callback' => array($this,'carts'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/cart/generate-cart-number', array(
                'methods' =>  WP_REST_Server::CREATABLE,
                'callback' => array($this,'generate_cart_number'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/cart/load-cart', array(
                'methods' =>  WP_REST_Server::CREATABLE,
                'callback' => array($this,'get_cart'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/cart/shipping-method', array(
                'methods' =>  WP_REST_Server::CREATABLE,
                'callback' => array($this,'get_shipping'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/cart/shipping-cost', array(
                'methods' =>  WP_REST_Server::CREATABLE,
                'callback' => array($this,'get_shipping_cost'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/cart/discount', array(
                'methods' =>  WP_REST_Server::CREATABLE,
                'callback' => array($this,'get_discount'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/cart/check-coupon', array(
                'methods' =>  WP_REST_Server::CREATABLE,
                'callback' => array($this,'check_coupon'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/cart/current-cart', array(
                'methods' =>  WP_REST_Server::CREATABLE,
                'callback' => array($this,'current_cart'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/cart/save', array(
                'methods' =>  WP_REST_Server::CREATABLE,
                'callback' => array($this,'save'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/cart/delete', array(
                'methods' =>  WP_REST_Server::CREATABLE,
                'callback' => array($this,'delete_cart'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
        }
        public function carts(WP_REST_Request $request = null){
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
                $register_id = isset($this->session_data['login_cashdrawer_id']) ? $this->session_data['login_cashdrawer_id'] : [];
                $warehouse_id = isset($this->session_data['login_warehouse_id']) ? $this->session_data['login_warehouse_id'] : [];
                
                if(!$register_id )
                {
                    throw new Exception(__('Register not found','openpos'));
                }
                $warehouse_meta_key = $this->warehouse_class->get_order_meta_key();
                $post_type = 'shop_order';

                $today = getdate();
                
                $args = array(
                    'date_query' => array(
                        array(
                            'year'  => $today['year'],
                            'month' => $today['mon'],
                            'day'   => $today['mday'],
                        ),
                    ),
                    'post_type' => $post_type,
                    'post_status' => 'auto-draft',
                    'meta_query' => array(
                        array(
                            'key' => $warehouse_meta_key,
                            'value' => $warehouse_id,
                            'compare' => '=',
                        )
                    ),
                    'posts_per_page' => -1
                );
                $args = apply_filters('op_draft_orders_query_args',$args);

                
                $query = new WP_Query($args);
                $orders = $query->get_posts();
                
            
                
                $carts = array();
                if(count($orders) > 0)
                {
                    foreach($orders as $_order)
                    {
                        $order_number = $_order->ID;
                        $cart_data = get_post_meta($order_number,'_op_cart_data');
                        if($cart_data && is_array($cart_data) && !empty($cart_data))
                        {
                            $cart= end($cart_data);
                            $cart['allow_delete'] = 'yes';
                            $carts[] = $cart;
                        }else{
                            continue;
                        }
                    }

                    
                    
                }else{
                    throw new Exception(__('No cart found','openpos'));
                }
                $result['response']['data'] = $carts;
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
        public function get_cart(WP_REST_Request $request = null){
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
                $order_number = $request->get_param('order_number');
                if(!$order_number)
                {
                    throw new Exception( __('Cart Not found','openpos') );
                }else{
                    if(!$this->core_class->enable_hpos())
                    {
                        $order_number = $this->order_class->get_order_id_from_number($order_number);
                    }
                }
            
                if(is_numeric($order_number))
                {
                    //cashier cart
                    $order = get_post((int)$order_number);
                    if(!$order)
                    {
                        throw new Exception( __('Cart Not found','openpos') );
                    }
                    $cart_data = get_post_meta($order->ID,'_op_cart_data');
                    if($cart_data && is_array($cart_data) && !empty($cart_data))
                    {
                        $result['response']['data'] = $cart_data[0];
                        $result['response']['status'] = 1;
                    }
                }else{
                    // online cart
                    $cart_type = 'website';
                    $result['cart_type'] = $cart_type;
                    $cart_data = $this->cart_class->getCartBySessionId($order_number);
                    if(!$cart_data || !is_array($cart_data) || empty($cart_data))
                    {
                        throw new Exception( __('Cart Not found','openpos')   );
                    }else{
                        $result['response']['data'] = $cart_data;
                        $result['response']['status'] = 1;
                    }
                }
                
                
                $result['code'] = 200;
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['message'] = $e->getMessage();
                
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
                $order_data =  json_decode($request->get_param( 'order' ),true);
                $login_warehouse_id = isset($session_data['login_warehouse_id']) ? $session_data['login_warehouse_id'] : 0;
                
                $order_parse_data = apply_filters('op_new_order_data',$order_data,$session_data);

                


                $order_number = isset($order_parse_data['order_number']) ? $order_parse_data['order_number'] : 0;
                $order_id = isset($order_parse_data['order_id']) ? $order_parse_data['order_id'] : 0;
                if(!$order_id && !$this->_enable_hpos)
                {
                    $order_id = $this->order_class->get_order_id_from_number($order_number);
                }

                do_action('op_add_draft_order_data_before',$order_parse_data,$session_data);

                $items = isset($order_parse_data['items']) ? $order_parse_data['items'] : array();
                if(empty($items))
                {
                    throw new Exception('Item not found.');
                }

                
                $order = get_post($order_id);
                if($order)
                {
                
                    do_action('op_add_draft_order_before',$order,$order_data,$session_data);
                    $warehouse_meta_key = $this->warehouse_class->get_order_meta_key();
                    
                    update_post_meta($order->ID,'_op_cart_data',$order_data);
                    update_post_meta($order->ID,$warehouse_meta_key,$login_warehouse_id);
                    
                    

                    do_action('op_add_draft_order_after',$order,$order_data);
                    $result['response']['data'] = array('id' => $order->ID);
                    $result['response']['status'] = 1;
                }else{

                    throw new  Exception(__('Cart Not Found', 'openpos'));
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
        public function current_cart(WP_REST_Request $request = null){
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
                $register_id = $request->get_param('register_id') ;
                if(!$register_id)
                {
                    $register = isset($this->session_data['register']) ? $this->session_data['register'] : [];
                    if(!isset($register['id']))
                    {
                        throw new Exception(__('Register not found','openpos'));
                    }
                    $register_id = $register['id'];
                }
                $cart_data = $this->cart_class->getPosCart($register_id);
                $result['response']['data'] = $cart_data;
                $result['response']['status'] = 1;
                $result['code'] = 200;
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['response']['message'] = $e->getMessage();
                
            }
            return $this->rest_ensure_response($result);
        }

        public function generate_cart_number(WP_REST_Request $request = null){
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
                $cart_data = $request->get_param('cart');
                $cart_number = isset($cart_data['cart_number']) ? $cart_data['cart_number'] : array();


                $session_data = $this->session_data;

                // lock order number
                $post_type = 'shop_order';
                $arg = array(
                    'post_type' => $post_type,
                    'post_status'   => 'auto-draft'
                );

                if(isset($cart_number['order_id']) && $cart_number['order_id'] )
                {
                    $post = get_post($cart_number['order_id']);
                    if($post && $post->post_status == 'auto-draft')
                    {
                        $arg['ID'] = $post->ID;
                    }
                }
            
                $next_order_id = wp_insert_post( $arg );
                update_post_meta($next_order_id,'_op_pos_session',$session_data['session']);
                $next_order_number = $next_order_id;

                if(!$next_order_number)
                {
                    $next_order_number = $next_order_id;
                }
                $order_number_info = array(
                    'order_id' => $next_order_id,
                    'order_number' => $next_order_number,
                    'order_number_formatted' => '#'.$next_order_number
                );
                $result['response']['data'] = apply_filters('op_get_next_cart_number_info',$order_number_info);
                $result['response']['status'] = 1;
                $result['code'] = 200;
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['response']['message'] = $e->getMessage();
                
            }
            return $this->rest_ensure_response($result);
        }
        public function delete_cart(WP_REST_Request $request = null){
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
                $cart_data = json_decode($request->get_param('cart'),true);
                
                $order_number = isset($cart_data['order_number']) ? trim($cart_data['order_number'],'#') : 0;
                $order_id = isset($cart_data['order_id']) ? $cart_data['order_id'] : 0;
                if(!$order_number)
                {
                    $order_number = $order_id;
                }
                
                if(!$order_number)
                {

                    throw new Exception('Cart Not found');
                }else{
                    if(!$this->_enable_hpos)
                    {
                        //$order_number = $this->order_class->get_order_id_from_number($order_number);
                    }
                }
                if(is_numeric($order_number) && get_post_status($order_number) == 'auto-draft')
                {
                    do_action('delete_cart_before',$order_number,$cart_data);
                    wp_trash_post( $order_number );
                }
                $result['code'] = 200;
                $result['response']['status'] = 1;
                $result['response']['data'] = $order_number;
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['response']['message'] = $e->getMessage();
                
            }
            return $this->rest_ensure_response($result);
        }
        public function get_shipping($request){
            $result = array(
                'code' => 200,
                'response' => array(
                    'status' => 0,
                    'data' => array(),
                    'message' => ''
                ),
                'api_message' => ''
            );
            try{
                $session_data = $this->session_data;
                $by_data = json_decode($request->get_param('by_data'),true);
    
                $cart = json_decode($request->get_param('cart'),true);
    
    
                $result['response']['status'] = 1;
                $result['response']['data'] = $this->cart_class->getShippingMethod($by_data,$cart,true);
    
                do_action('op_get_online_shipping_method',$result,$session_data);
                $result['response'] = apply_filters('op_get_online_shipping_method_response',$result['response'],$session_data);
                $result['code'] = 200;
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['response']['message'] = $e->getMessage();
                
            }
            return $this->rest_ensure_response($result);
        }
        public function get_shipping_cost($request){
            $result = array(
                'code' => 200,
                'response' => array(
                    'status' => 0,
                    'data' => array(),
                    'message' => ''
                ),
                'api_message' => ''
            );
            try{
                $session_data = $this->session_data;
                $by_data = json_decode($request->get_param('by_data'),true);
    
                $cart = json_decode($request->get_param('cart'),true);

                $method = $by_data['shipping_method'] ? $by_data['shipping_method'] :'';
                $calc_shipping_cost = 0;
                $result['response']['data']['calc_shipping_cost'] = $calc_shipping_cost;
                $result['response']['data']['calc_shipping_tax'] = 0;
                $result['response']['data']['calc_shipping_rate_id'] = '';
                if($method)
                {
                    $result['response']['status'] = 1;
                    $cost = $this->cart_class->getShippingCost($by_data,$cart,true);
                    if(!empty($cost))
                    {
                        $result['response']['data']['calc_shipping_cost'] = $cost['cost'];
                        $result['response']['data']['calc_shipping_tax'] = $cost['tax'];
                        $result['response']['data']['calc_shipping_rate_id'] = $cost['rate_id'];
                    }
                }
                do_action('op_get_online_shipping_cost',$result['response'],$session_data);
                $result['response'] = apply_filters('op_get_online_shipping_cost_response',$result['response'],$session_data);
                $result['code'] = 200;
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['response']['message'] = $e->getMessage();
                
            }
            return $this->rest_ensure_response($result);
        }
        public function get_discount($request){
            $result = array(
                'code' => 200,
                'response' => array(
                    'status' => 0,
                    'data' => array(),
                    'message' => ''
                ),
                'api_message' => ''
            );
            try{
                $session_data = $this->session_data;
                $cart = json_decode($request->get_param('cart'),true);

                $calc_shipping_cost = 0;
                $result['response']['data']['discount_amount'] = $calc_shipping_cost;
                $result['response']['data']['discount_type'] = 'fixed'; // fixed , percent
    
                $result['response']['status'] = 1;
                $cost = $this->cart_class->getCartDiscount($cart,true);
                if(!empty($cost))
                {
                    $result['response']['data']['discount_amount'] = $cost['discount_amount'];
                    $result['response']['data']['discount_type'] = $cost['discount_type'];
                }
    
                do_action('op_get_online_discount',$result['response'],$session_data);
                $result['response'] = apply_filters('op_get_online_shipping_cost_response',$result['response'],$session_data);
                $result['code'] = 200;
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['response']['message'] = $e->getMessage();
                
            }
            return $this->rest_ensure_response($result);
        }
        public function check_coupon($request){
            $result = array(
                'code' => 200,
                'response' => array(
                    'status' => 0,
                    'data' => array(),
                    'message' => ''
                ),
                'api_message' => ''
            );
            try{
                $session_data = $this->session_data;
               
                if(class_exists('OP_Discounts'))
                {
                    $wc_discount = new OP_Discounts();
                    $code = trim($request->get_param('code'));
                    $cart_data = json_decode($request->get_param('cart'),true);
                    $coupon = new WC_Coupon($code);
                    
                    $applied_coupons = isset($request['applied_codes']) ? json_decode($request['applied_codes'],true) : array();

                    if(!empty($applied_coupons)){
                        
                        foreach($applied_coupons as $_a)
                        {
                            $a_coupon = new WC_Coupon($_a['code']);
                            if($a_coupon->get_individual_use())
                            {
                                throw new Exception(sprintf(__('Coupon %s is invidual use','openpos'),$_a['code']));
                            }
                        }
                        
                        
                    }

                    $items = array();
                    $_pf = new WC_Product_Factory();

                    

                    $grand_total = 0;
                    if(!$grand_total)
                    {
                        $grand_total = $cart_data['tax_amount'] + $cart_data['sub_total'] - $cart_data['discount_amount'] + $cart_data['shipping_cost'];
                    }


                    $item_discount_ids = array(); 
                    $is_after_tax = false;
                    if($this->setting_class->get_option('pos_cart_discount','openpos_general') == 'after_tax')
                    {
                        $is_after_tax = true;
                    }
                    
                    $discount_is_after_tax = apply_filters('op_check_coupon_request',$is_after_tax);
                    foreach ( $cart_data['items'] as $key => $cart_item ) {
                        $item                = new stdClass();
                        $item->key           = $key;
                        $item->object        = $cart_item;
                        $item->product       = $_pf->get_product($cart_item['product']['id']);
                        $item->quantity      = $cart_item['qty'];
                        if($discount_is_after_tax)
                        {
                            $item->price         = wc_add_number_precision_deep( $cart_item['total_incl_tax']  );
                        }else{
                            $item->price         = wc_add_number_precision_deep( $cart_item['total']  );
                        }
                        
                        $items[ $key ] = $item;
                    }

                    $wc_discount->set_items($items);
                    $valid = $wc_discount->is_coupon_valid($coupon,$cart_data);
                    
                    $after_items = $wc_discount->get_items();
                    foreach($after_items as $_i)
                    {
                        $item_obj = $_i->object;
                        $item_discount_ids[$_i->key] =  $item_obj['id'];
                    }
                    
                    if($valid === true)
                    {
                        
                        $result['valid'] = $valid;

                        
                        
                        $discount_type = $coupon->get_discount_type();

                        $amount = $wc_discount->apply_coupon($coupon);
                        $amount = wc_round_discount($amount/pow(10 , wc_get_price_decimals()),wc_get_price_decimals());



                        if($amount > $grand_total)
                        {
                            $amount = $grand_total;
                        }

                        if($amount < 0)
                        {
                            $msg = __('Coupon code has been expired','openpos');
                            throw new Exception($msg );
                        }
                        $code = $coupon->get_code();
                        $discount_amount_type = 'fixed';
                        if($discount_type == 'percent'){
                            $discount_amount_type = 'percent';
                        }
                        if($discount_type == 'fixed_product'){
                            $discount_amount_type = 'fixed_product';
                        }
                        $result['response']['amount'] = $amount;
                        $result['response']['data']['code'] = $code;
                        $result['response']['data']['base_amount'] = $coupon->get_amount();
                        $result['response']['data']['amount'] = $amount; // amount calculate base on cart
                        $result['response']['data']['tax_amount'] = 0;
                        $result['response']['data']['applied_items'] = array();
                        $result['response']['data']['discount_type'] = $discount_type;
                        $result['response']['data']['discount_amount'] = $coupon->get_amount();
                        $result['response']['data']['description'] = $coupon->get_description();
                        $result['response']['data']['discount_amount_type'] = $discount_amount_type;

                        $applied_items = $wc_discount->get_discounts();
                        $has_zero = false;
                        foreach($applied_items[$code] as $k => $_amount)
                        {
                            $id = $item_discount_ids[$k];
                            if($id)
                            {
                                $result['response']['data']['applied_items'][$id] = 1 * $_amount;
                                if($_amount == 0)
                                {
                                    $has_zero = true;
                                }
                            }
                            
                        }
                        if($has_zero)
                        {
                            $result['response']['data']['discount_type'] = 'fixed';
                            $result['response']['data']['discount_amount_type'] = 'fixed';
                            $result['response']['data']['discount_amount'] = $amount;
                            $string_amnt = wc_price($amount);
                        }else{
                            $string_amnt = wc_price($coupon->get_amount());

                            if($discount_type == 'percent')
                            {
                                $string_amnt = number_format($coupon->get_amount()).'%';
                            }
                        }
                    
                        $result['response']['message'] = sprintf(__("<b>%s</b> discount value: <b>%s</b>", 'openpos'), $code,$string_amnt);
                        $result['response']['status'] = 1;

                    }else{
                        $msg = $valid->get_error_message();

                        throw new Exception($msg );

                    }
                    do_action('op_check_coupon_after',$result);
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