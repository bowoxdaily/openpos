<?php
if(!class_exists('OP_REST_API_Auth'))
{
    class OP_REST_API_Auth extends OP_REST_API{
       
        public function register_routes() {
            
                // staff  route         
                register_rest_route( $this->namespace, '/auth/login', array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this,'login'),
                    'permission_callback' => '__return_true',
                    ) 
                );
                register_rest_route( $this->namespace, '/auth/login-register', array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this,'login_register'),
                    'permission_callback' => '__return_true',
                    ) 
                );
                
                register_rest_route( $this->namespace, '/auth/login-session', array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this,'login_session'),
                    'permission_callback' => '__return_true',
                    ) 
                );
                register_rest_route( $this->namespace, '/auth/logout', array(
                    'methods' =>  WP_REST_Server::CREATABLE,
                    'callback' => array($this,'logout'),
                    'permission_callback' => array($this,'permission_callback'),
                    ) 
                );
                
                register_rest_route( $this->namespace, '/auth/logoff', array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this,'logoff'),
                    'permission_callback' => array($this,'permission_callback'),
                    ) 
                );
                register_rest_route( $this->namespace, '/auth/logon', array(
                    'methods' =>  WP_REST_Server::CREATABLE,
                    'callback' => array($this,'logon'),
                    'permission_callback' => array($this,'permission_callback'),
                    ) 
                );
                register_rest_route( $this->namespace, '/auth/verify-pin', array(
                    'methods' =>  WP_REST_Server::CREATABLE,
                    'callback' => array($this,'verify_pin'),
                    'permission_callback' => array($this,'permission_callback'),
                    ) 
                );
                register_rest_route( $this->namespace, '/pos-state/(?P<local_timestamp>\d+)', array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this,'pos_state'),
                    'permission_callback' => array($this,'permission_callback'),
                    ) 
                );
                register_rest_route( $this->namespace, '/pos-info', array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this,'pos_info'),
                    'permission_callback' => '__return_true',
                    ) 
                );
        }
        private function _getAllSetting($warehouse_id,$cashdrawer_id = 0,$allow_keys = array()){
            $setting_sections = array(
                array(
                    'id'    => 'openpos_general',
                    'title' => __( 'General', 'openpos' )
                ),
                array(
                    'id'    => 'openpos_payment',
                    'title' => __( 'Payment', 'openpos' )
                ),
                array(
                    'id'    => 'openpos_shipment',
                    'title' => __( 'Shipping', 'openpos' )
                ),
                array(
                    'id'    => 'openpos_label',
                    'title' => __( 'Barcode Label', 'openpos' )
                ),
                array(
                    'id'    => 'openpos_receipt',
                    'title' => __( 'Receipt', 'openpos' )
                ),
                array(
                    'id'    => 'openpos_pos',
                    'title' => __( 'POS Layout', 'openpos' )
                )
            );
            $ignore =  array('stripe_public_key','stripe_secret_key');
            $setting = array();
            foreach($setting_sections as $section)
            {
                $options = $this->setting_class->get_options($section['id']);
                foreach($options as $field => $value)
                {
                    $option = $field;
                    if(in_array($option,$ignore))
                    {
                        continue;
                    }
                    if(!empty($allow_keys))
                    {
                        if(!in_array($option,$allow_keys))
                        {
                            continue;
                        }
                    }
                    switch ($option)
                    {
                        case 'shipping_methods':
                            $setting_methods = $value;
                            $shipping_methods =   $this->woo_class->get_setting_shipping_methods();// WC()->shipping()->get_shipping_methods();
                            $shipping_result = array();
                            if(!is_array($setting_methods))
                            {
                                $setting_methods = array();
                            }
                            foreach ($setting_methods as $shipping_method_code)
                            {
                                foreach($shipping_methods as $shipping_method)
                                {
                                    $instance_id = $shipping_method->instance_id ? $shipping_method->instance_id : 0;

                                    $code = $shipping_method->id.':'.$instance_id;
                                
                                    if($code == $shipping_method_code)
                                    {
                                        $title = $shipping_method->title;
                                        if(!$title)
                                        {
                                            $title = $shipping_method->method_title;
                                        }
                                        if(!$title)
                                        {
                                            $title = $code;
                                        }
                                        $taxes = array();
                                        $cost = isset($shipping_method->cost) ? $shipping_method->cost : 0;
                                        
                                        $tmp = array(
                                            'code' => $code,
                                            'title' => $title,
                                            'cost' => $cost,
                                            'cost_online' => 'yes',
                                            'inclusive_tax' => 'yes',
                                            'tax_details' => $taxes
                                        );
                                        $shipping_result[] = apply_filters('op_setting_shipping_method_data',$tmp);
                                    }
                                }
                            }
                            $shipping_methods =  apply_filters('op_shipping_methods',$shipping_result);
                            $setting[$option] = $shipping_methods;
                            break;
                        case 'payment_methods':
                            $payment_gateways = WC()->payment_gateways->payment_gateways();
                            $addition_payment_gateways = $this->core_class->additionPaymentMethods();
                            $payment_gateways = array_merge($payment_gateways,$addition_payment_gateways);
                            $payment_options = $value;
                            foreach ($payment_gateways as $code => $p)
                            {
                                if($p)
                                {
                                    if(isset( $payment_options[$code]))
                                    {
                                        if(!is_object($p))
                                        {
                                            $title = $p;
                                            $payment_options[$code] = $title;
                                        }else{
                                            $title = $p->title;
                                            $payment_options[$code] = $title;
                                        }

                                    }
                                }
                            }
                            $setting[$option] = $payment_options;
                            break;
                        default:
                            $setting[$option] = $value;
                            if($option == 'receipt_template_header' || $option == 'receipt_template_footer')
                            {
                                $setting[$option] = balanceTags($setting[$option],true);
                            }
                            break;
                    }
                }
            
            }
            $setting['pos_allow_online_payment'] = $this->core_class->allow_online_payment(); // yes or no

            
            $setting['openpos_tables'] = array();
            $setting['payment_methods'] = $this->core_class->formatPaymentMethods($setting['payment_methods']);

            $setting['shipping_methods'] = $this->woo_class->getStoreShippingMethods($warehouse_id,$setting);
            if(isset($setting['pos_enable_weight_barcode']) && $setting['pos_enable_weight_barcode'] == 'yes')
            {
                $setting['pos_weight_barcode_prefix'] = '20';
            }

            $incl_tax_mode = $this->woo_class->inclTaxMode() == 'yes' ? true : false;
            $setting = $this->woo_class->_formatSetting($setting);
            $setting = $this->core_class->formatReceiptSetting($setting,$incl_tax_mode);

           
            
            return $setting;
        }
        public function login(WP_REST_Request $request){
            $result = array(
                'code' =>'unknown_error',
                'response' => array(
                    'status' => 0,
                    'database_version' => 0,
                    'data' => array(),
                    'message' => ''
                ),
                'api_message' => ''
            );
            
            try{
                if(!$this->rest_api_limit())
                {
                    throw new Exception( __('Your have reach to maximum api request limit. Please try again later.','openpos' ));
                }
                $username = $request->get_param( 'username' );
                $password = $request->get_param( 'password' );
                $login_mode = $request->get_param( 'login_mode' );
                $location = $request->get_param( 'location' );
                $lang = $request->get_param('lang');
                $time_stamp = $request->get_param('time_stamp');
                
                // Initialize LoginSecurity helper
                require_once(dirname(__FILE__) . '/LoginSecurity.php');
                $login_security = new OP_LoginSecurity();
                
                $ip = $this->core_class->getClientIp();
                
                // Check for account lockout
                $lockout_check = $login_security->check_lockout($username, $ip);
                if ($lockout_check['locked']) {
                    throw new Exception($lockout_check['message']);
                }
                
                $creds = array(
                    'user_login'    => $username,
                    'user_password' => $password,
                    'remember'      => false
                );
                
                if($login_mode == 'pin')
                {
                    $unique_session = apply_filters('op_login_pin_unique_session',false);
                    $user = \Op\Models\User::login_pin($password,$unique_session);
                }else{
                    $user = \Op\Models\User::login($username,$password);
                }
                do_action( 'openpos_before_login',$creds );
                if(is_wp_error($user))
                {
                   $message = strip_tags($user->get_error_message());
                   $login_security->record_failed_attempt($username, $ip);
                   throw new Exception($message);
                }
                $id = $user->ID;
                $drawers = array();
                $registers = $this->register_class->getUserRegisters($id);
                foreach($registers as $register)
                {
                    $warehouse_id = $register['warehouse'];
                    $warehouse = $this->warehouse_class->get($warehouse_id);
                    if(!empty($warehouse))
                    {
                        $drawers[] = array(
                            'id' => $register['id'],
                            'name' => $register['name'],
                            'outlet_id' => $warehouse['id'],
                            'outlet_name' => $warehouse['name'],
                            'address' => $warehouse['formatted_address']
                        );
                    }
                }
                $allow_pos = get_user_meta($id,'_op_allow_pos',true);
                if(!$allow_pos)
                {
                    $login_security->record_failed_attempt($username, $ip);
                    throw new Exception(__('You have no permission to access POS. Please contact with admin to resolve it.','openpos' ));
                }

                if(!$drawers || empty($drawers))
                {
                    $login_security->record_failed_attempt($username, $ip);
                    throw new Exception(__('You have no grant access to any Register POS. Please contact with admin to assign your account to POS Register.','openpos' ));
                }
                
                $user_data = $user->data;
                $cashier_name = $user_data->display_name;
                if(!$cashier_name)
                {
                    $cashier_name = $user_data->user_nicename;
                }
                
                $avatar = rtrim(OPENPOS_URL,'/').'/assets/images/default_avatar.png';

                $avatar_args = get_avatar_data( $id);
                if($avatar_args && isset($avatar_args['url']))
                {
                    $avatar = $avatar_args['url'];
                }
                $prefix = sanitize_title($user_data->user_login);
                $session_id = $this->session_class->generate_session_id($prefix);
                
                // Check for suspicious activity
                $suspicious = $login_security->check_suspicious_activity($id, $ip);
                
                $sale_person = array();
                $payment_methods = array();
                $cash = array();
                $pos_balance = 0;
                $price_included_tax = true;
                $user_login_data = array(
                    'user_id' => $id ,
                    'ip' => $ip,
                    'session' => $session_id ,
                    'username' =>  $user_data->user_login ,
                    'name' =>  $cashier_name,
                    'email' =>  $user_data->user_email ,
                    'role' =>  $user->roles ,
                    'phone' => '',
                    'logged_time' => current_time('Y-m-d H:i:s',true), // gmt date
                    'setting' => array(),
                    'session' => $session_id,
                    'sale_persons' => $sale_person,
                    'payment_methods' => $payment_methods,
                    'cash_drawer_balance' => $pos_balance,
                    'balance' => $pos_balance,
                    'cashes' => $cash,
                    'cash_drawers' => $drawers,
                    'price_included_tax' => $price_included_tax,
                    'avatar' => $avatar,
                    'location' => $location,
                    'suspicious_activity' => $suspicious['suspicious'],
                    'suspicious_flags' => isset($suspicious['flags']) ? $suspicious['flags'] : []
                );

                $login_data = apply_filters('op_login_data',$user_login_data,$user);
                $this->session_class->save($session_id,$login_data);
                
                // Record successful login
                $login_security->record_successful_login($id, $user_data->user_login, $ip, $session_id);
                
                $result['response']['data'] = $login_data;
                $result['response']['status'] = 1;
                $result['code'] = 200;
                do_action( 'openpos_after_login',$creds,$result['response'] );
            }catch(Exception $e){
                $result['response']['message'] = $e->getMessage();
                $result['code'] = 400;
                $result['response']['status'] = 0;
            }
            return $this->rest_ensure_response( $result );
        }
        public function login_register(WP_REST_Request $request){
            
            $response_code = 200;
            $result = array(
                'code' =>'unknown_error',
                'response' => array(
                    'database_version' => 0,
                    'status' => 0,
                    'data' => array(),
                    'message' => '',
                ),
                'api_message' => ''
            );
            try{
                $session_id = $request->get_param( 'session' );
                
                $cashdrawer_id = $request->get_param( 'cashdrawer_id' );
                $lang = $request->get_param( 'lang' );
                $app_ver  = $request->get_param( 'app_ver' );
                $client_time_offset = $request->get_param( 'client_time_offset' );

                if(!$session_id)
                {
                    throw new Exception('Session not found');
                }
                $session_data =  $this->session_class->data($session_id);
                
                if(!$session_data)
                {
                    throw new Exception('Session data not found');
                }
                $user_id = isset($session_data['user_id']) ? $session_data['user_id'] : 0;
                if(!$user_id)
                {
                    throw new Exception('Session not found');
                }
                $cash_drawers = isset($session_data['cash_drawers']) ? $session_data['cash_drawers'] : array();
                if(empty($cash_drawers))
                {
                    throw new Exception('You not assign to any register');
                }
                $register = false;
                foreach($cash_drawers as $re)
                {
                    if($re['id'] == $cashdrawer_id)
                    {
                        $register = $re;
                        break;
                    }
                }
                if(!$register)
                {
                    throw new Exception('Register not found');
                }
                $register = $this->register_class->get($cashdrawer_id);
                $warehouse_id = isset($register['warehouse']) ? $register['warehouse'] : 0;
                $pos_balance = $this->register_class->cash_balance($cashdrawer_id);

                $session_data['client_time_offset'] = $client_time_offset;
                $session_data['cash_drawer_balance'] = $pos_balance;
                $session_data['balance'] = $pos_balance;
                $session_data['login_cashdrawer_id'] = $cashdrawer_id;
                $session_data['login_cashdrawer_mode'] = isset($register['register_mode']) ? $register['register_mode'] : 'cashier' ;
                $session_data['login_warehouse_id'] = $warehouse_id;
                $session_data['default_display'] =  ( $this->setting_class->get_option('openpos_type','openpos_pos') == 'grocery'  && $this->setting_class->get_option('dashboard_display','openpos_pos') =='table' ) ? 'product': $this->setting_class->get_option('dashboard_display','openpos_pos');
                $session_data['categories'] = $this->woo_class->get_pos_categories($cashdrawer_id);
                
                $session_data['currency_decimal'] = wc_get_price_decimals() ;

                $currency_pos = get_option( 'woocommerce_currency_pos' );
                $default_currency  = array(
                    'decimal' => wc_get_price_decimals(),
                    'decimal_separator' => wc_get_price_decimal_separator(),
                    'thousand_separator' => wc_get_price_thousand_separator(),
                    'currency_pos' => $currency_pos,
                    'code' => get_woocommerce_currency(), 
                    'symbol' => html_entity_decode(get_woocommerce_currency_symbol()), 
                    'rate' => 1
                );
                $session_data['currency'] = $default_currency;
                $availble_currencies[$default_currency['code']] = $default_currency;

                $session_data['time_frequency'] = $this->setting_class->get_option('time_frequency','openpos_pos') ? (int)$this->setting_class->get_option('time_frequency','openpos_pos') : 3000 ;
                $session_data['product_sync'] = true;
                $session_data['date_format'] = $this->core_class->convert_to_js_date_format(get_option( 'date_format' ));
                $session_data['time_format'] = $this->core_class->convert_to_js_date_format(get_option( 'time_format' ));

                if($this->setting_class->get_option('pos_auto_sync','openpos_pos') == 'no')
                {
                    $session_data['product_sync'] =false;
                }

                
                $setting = $this->_getAllSetting($warehouse_id,$cashdrawer_id);// code here 
                $user_id = $session_data['user_id'];
                $incl_tax_mode = $this->woo_class->inclTaxMode() == 'yes' ? true : false;
                
                if(isset($setting['pos_sequential_number_prefix']) && $setting['pos_sequential_number_prefix'] )
                {
                    $pos_sequential_number_prefix = $setting['pos_sequential_number_prefix'];
                    $pos_sequential_number_prefix = str_replace('[outlet_id]',$warehouse_id,$pos_sequential_number_prefix);
                    $pos_sequential_number_prefix = str_replace('[register_id]',$cashdrawer_id,$pos_sequential_number_prefix);
                    $pos_sequential_number_prefix = str_replace('[cashier_id]',$user_id,$pos_sequential_number_prefix);
                    $pos_sequential_number_prefix = str_replace('[year]',date('Y'),$pos_sequential_number_prefix);
                    $pos_sequential_number_prefix = str_replace('[month]',date('m'),$pos_sequential_number_prefix);
                    $pos_sequential_number_prefix = str_replace('[day]',date('d'),$pos_sequential_number_prefix);
                   
                    $setting['pos_sequential_number_prefix'] = $pos_sequential_number_prefix;
                }
                
                if($setting['openpos_type'] == 'restaurant')
                {
                    
                    $setting['openpos_tables'] = $this->table_class->tables($warehouse_id,true);
                    
                     // desk multi pay
                     $setting['pos_desk_multi_pay'] = 'yes';
                     $setting['pos_auto_dish_send'] = 'no';
                     $session_data['takeaway_number'] = $this->table_class->getTakeawayNumber($cashdrawer_id,$warehouse_id);
                     $kitchen_area = $this->woo_class->getListRestaurantArea($warehouse_id);
                     $session_data['kitchen_area'] = array_values($kitchen_area);
                }
                if($setting['pos_default_checkout_mode'] == 'single_mutli_times'){
                    $setting['pos_default_checkout_mode'] = 'single';
                    $setting['pos_single_multi_payment'] = 'yes';
                }

                if($setting['pos_default_checkout_mode'] == 'single_mutli_times' ||  $setting['pos_default_checkout_mode'] == 'single'){
                    $session_data['allow_receipt'] = 'no';
                }
                $pos_available_taxes =  $this->woo_class->getAvailableTaxes($warehouse_id);
                $setting['pos_available_taxes'] =  $pos_available_taxes;

                // fee tax
                if(isset($setting['pos_fee_tax_class']) && $setting['pos_fee_tax_class'] != 'op_notax')
                {
                    $fee_tax_class = $setting['pos_fee_tax_class'];
                    $setting['pos_fee_tax'] = array();
                    if($setting['pos_fee_tax_class'] === ''){
                        $fee_tax_class = 'standard';
                    }
                    foreach($pos_available_taxes as $tax_class)
                    {
                        $tax_class_code = $tax_class['tax_class'] ? $tax_class['tax_class'] : '';
                        if($fee_tax_class == $tax_class_code)
                        {
                            $setting['pos_fee_tax'] = $tax_class;
                        }
                    }
                    
                }

                //start role
                $roles = array();
                
                switch($session_data['login_cashdrawer_mode'])
                {
                    case 'seller':
                    case 'waiter':
                        $roles = array(
                            'do_checkout' => 'no',
                            'transactions' => 'no',
                        );
                        break;
                    case 'customer':
                        $roles = array(
                            'orders' => 'no',
                            'customers' => 'no',
                            'transactions' => 'no',
                            'report' => 'no',
                            'tables' => 'no',
                            'takeaway' => 'no',
                            'switch_seller' => 'no',
                        );
                        $setting['pos_default_checkout_mode'] = 'multi';
                        $setting['pos_disable_item_discount'] = 'yes';
                        $setting['pos_disable_cart_discount'] = 'yes';
                        $setting['pos_cart_tab'] = 'no'; // disable multi tab login for customer mode and waiter mode
                        $setting['pos_cart_buttons'] = array(
                            'cart-note',
                            // 'shipping',
                            // 'pickup',
                            // 'cart-discount',
                            // 'custom-item',
                            // 'custom-tax',
                            // 'seller'
                        );
                        break;
                }
                // do_remove_ready_dish : allow remove dish once it ready
                // do_clear_desk : clear desk
                // do_remove_dish : remove dish out of desk
                // do_mark_item_done : mark item done on desk
                $session_data['role'] = $roles;
                //end role
                

                $setting['currencies'] = $availble_currencies;
                //cart button
                $pos_cart_buttons = ['cart-note','shipping','pickup','cart-discount','coupon','custom-item','custom-tax','seller','fee'];
                if($setting['pos_tax_class'] == 'op_productax')
                {
                    $pos_cart_buttons = array_diff( $pos_cart_buttons, ['custom-tax'] ) ;
                }
                if(isset($setting['pos_allow_custom_item'] ) && $setting['pos_allow_custom_item'] == 'no')
                {
                    $pos_cart_buttons = array_diff( $pos_cart_buttons, ['custom-item'] ) ;
                }
                if(!isset($setting['pos_allow_custom_note']) || $setting['pos_allow_custom_note'] != 'yes')
                {
                    $pos_cart_buttons = array_diff( $pos_cart_buttons, ['cart-note'] ) ;
                }
                
            
                $setting['pos_cart_buttons'] = array_values($pos_cart_buttons);
                //end
                
                $setting['shipping_methods'] = $this->woo_class->getStoreShippingMethods($warehouse_id,$setting);
                $payment_methods = $setting['payment_methods'];
                $setting['pos_allow_online_payment'] = 'no';
                foreach ($payment_methods as $_payment_method)
                {
                    if($_payment_method['type'] != 'offline')
                    {
                        $setting['pos_allow_online_payment'] = 'yes';
                    }
                }
                $session_data['payment_methods'] = $payment_methods;

                if(isset($setting['pos_enable_weight_barcode']) && $setting['pos_enable_weight_barcode'] == 'yes')
                {
                    $setting['pos_weight_barcode_prefix'] = '20';
                }
                $ignore_use_settings = array(
                    'op_credit_expired',
                    'op_stripe_payment_publishable_key',
                    'op_stripe_payment_secret_key',
                    // 'receipt_padding_top',
                    // 'receipt_padding_left',
                    // 'receipt_padding_bottom',
                    // 'receipt_padding_right',
                    // 'receipt_template',
                    // 'receipt_template_footer',
                    // 'receipt_template_header',
                    // 'receipt_width',
                    // 'receipt_css',
                    'sheet_width',
                    'sheet_height',
                    'sheet_horizontal_space',
                    'sheet_vertical_space',
                    'sheet_margin_top',
                    'sheet_margin_right',
                    'sheet_margin_left',
                    'sheet_margin_bottom',
                );
                foreach($ignore_use_settings as $ignore_key)
                {
                    if(isset($setting[$ignore_key]))
                    {
                        unset($setting[$ignore_key]);
                    }
                }
                $session_data['setting'] = $setting;

                if(!isset($session_data['setting']['pos_categories']) || !$session_data['setting']['pos_categories'])
                {
                    $session_data['setting']['pos_categories'] = array();
                }

                $sale_persons = $this->register_class->getCashierList($cashdrawer_id);
                
                $session_data['sale_persons'] = $sale_persons;
                $session_data['logged_time'] = $this->core_class->convertToShopTime($session_data['logged_time']);
                $session_data['logged_time_stamp'] = time();

                $session_data = apply_filters('op_cashdrawer_login_session_data',$session_data);
                $this->session_class->clean($session_id);
                $this->session_class->save($session_id,$session_data);
                $result['response']['data'] = apply_filters('op_get_login_cashdrawer_data',$session_data);
                $result['response']['status'] = 1;
                $result['code'] = 200;
               
            }catch(Exception $e){
                $result['response']['message'] = $e->getMessage();
                $result['code'] = 400;
                $result['response']['status'] = 0;
            }

            return $this->rest_ensure_response( $result );
            

        }
        public function logon(WP_REST_Request $request){
            
            $result = array(
                'code' => 'unknown_error',
                'response' => array(
                    'status' => 0,
                    'data' => array(),
                    'message' => ''
                ),
                'api_message' => ''
            );
            try{
                $session_id = $request->get_param( 'session_id' );
                $logon_mode =  $request->get_param( 'logon_mode' ) ? stripslashes($request->get_param( 'logon_mode' )) : 'default';
                $session_data = $this->session_data;
                $username = $session_data['username'];
                $user_id = $session_data['user_id'];
                if($logon_mode == 'pin')
                {
                    $password =  $request->get_param('password') ? trim($request->get_param('password')) : '';
                
                    
                    if( !$password)
                    {
                        throw new Exception(__('PIN can not empty.','openpos' ));
                    }
                    $user = $this->woo_class->pin_authenticate($password,false);
                    if ( is_wp_error($user) ) {
                        
                        throw new Exception($user->get_error_message());
                    }
                    $sale_persons = isset($session_data['sale_persons']) ? $session_data['sale_persons'] : array();
                    $allow_user = array($user_id);
                    foreach($sale_persons as $staff)
                    {
                        $allow_user[] = $staff['ID'];
                    }
                    
                    if(in_array($user->ID , $allow_user))
                    {
                        $result['response']['data'] = array('logon_user_id' => $user->ID,'session_data' =>$session_data);
                        $result['response']['status'] = 1;
                    }else{
                        throw new Exception(__('Your PIN is incorrect. Please try again.','openpos' ));
                    }

                    //start verify pin
                }else{
                    $password =  $request->get_param('password') ? sanitize_text_field($request->get_param('password')) : '';
                    if(!$password)
                    {
                        throw new Exception(__('Please enter password','openpos' ));
                    }
                    
                    $user = wp_authenticate($username, $password);
                    if ( is_wp_error($user) ) {
                        throw new Exception(__('Your password is incorrect. Please try again.','openpos' ));
                    }
                    $result['response']['data'] = array('logon_user_id' => $user->ID,'session_data' =>$session_data);
                    $result['response']['status'] = 1;
                }
                
                $result['code'] = 200;
            }catch(Exception $e){
                $result['response']['message'] = $e->getMessage();
                $result['response']['status'] = 0;
                $result['code'] = 400;
            }
            return $this->rest_ensure_response($result);
        }
        public function verify_pin(WP_REST_Request $request){
            $result = array(
                'code' => 'unknown_error',
                'response' => array(
                    'status' => 0,
                    'data' => array(),
                    'message' => __('Unknow message','openpos')
                ),
                'api_message' => ''
            );
            try{

                $session_id = $request->get_param( 'session_id' );
                $pin = $request->get_param( 'pin' );
                if( !$pin)
                {
                    throw new Exception(__('PIN can not empty.','openpos' ));
                }
                $user = $this->woo_class->pin_authenticate($pin,false);
                if ( is_wp_error($user) ) {
                    
                    throw new Exception($user->get_error_message());
                }
                $result['response']['data'] = apply_filters('op_verify_pin_data',array(
                    'user_id' => $user->ID,
                    'role' => array()
                ));
                $result['response']['status'] = 1;
                $result['code'] = 200;
            }catch(Exception $e){
                $result['response']['message'] = strip_tags($e->getMessage());
                $result['response']['status'] = 0;
                $result['code'] = 400;
            }
            return $this->rest_ensure_response($result);
        }
        public function logoff(WP_REST_Request $request){
            $response_code = 200;
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

                $session_id = $request->get_param( 'session_id' );
                $result['response']['status'] = 1;
                $result['code'] = 200;
            }catch(Exception $e){
                $result['response']['message'] = $e->getMessage();
                $result['response']['status'] = 0;
                $result['code'] = 400;
            }
            return $this->rest_ensure_response( $result );
        }
        public function login_session(WP_REST_Request $request){
            
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
                require_once(dirname(__FILE__) . '/LoginSecurity.php');
                $login_security = new OP_LoginSecurity();
                
                $session_id = $request->get_param( 'session_id' );

                $session_data = $this->session_class->data($session_id);
                if(empty($session_data))
                {
                    throw  new Exception(__('Your login session has been clean. Please try login again','openpos'));
                }
                
                // Validate session timeout
                $timeout_check = $login_security->validate_session_timeout($session_data, $session_id);
                if (!$timeout_check['valid']) {
                    $this->session_class->clean($session_id);
                    throw new Exception($timeout_check['message']);
                }
                
                $check = true;
                if($check)
                {
                    $session_response_data = $session_data;
                    // Add remaining session time
                    $session_response_data['session_remaining_seconds'] = $timeout_check['remaining_seconds'] ?? 0;
                    $session_response_data['session_expires_at'] = $timeout_check['expired_at'] ?? 0;
                    
                    $result['response']['data'] = apply_filters('op_get_login_with_session_cashdrawer_data',$session_response_data);
                    $result['response']['status'] = 1;
                }else{
                    $result['response']['status'] = 0;
                    $this->session_class->clean($session_id);
                    $result['response']['message'] = __('Your have no grant to any register','openpos');
                }
                $result['code'] = 200;
            }catch(Exception $e){
                $result['response']['message'] = $e->getMessage();
                $result['response']['status'] = 0;
                $result['code'] = 400;
            }
            return rest_ensure_response( $result );
        }
        public function logout(WP_REST_Request $request){
            
            $result = array(
                'code' =>'unknown_error',
                'response' => array(
                    'status' => 0,
                    'message' => '',
                    'data' => array(),
                ),
                'api_message' => ''
            );
            try{
                $session_id = $request->get_param( 'session' );
                $current_order_number = $request->get_param( 'current_order_number' );

                $this->order_class->reset_order_number($current_order_number);
                $z_report_str =  $request->get_param( 'z_report' );
                $z_report_data = $z_report_str ? json_decode($z_report_str,true): array();
                $session_data =  $this->session_data;
                $result['response']['status']  = 1;
                
                if(!empty($z_report_data))
                {
                    
                    if(!empty($session_data))
                    {
                        unset($session_data['setting']);
                        unset($session_data['categories']);
                        unset($session_data['cashes']);
                        unset($session_data['payment_methods']);
                        unset($session_data['sale_persons']);
                        $z_report_data['session_data'] = $session_data;
                        $id = $this->report_class->add_z_report($z_report_data,$z_report_str);
                        if(!$id)
                        {
                            $result['response']['status'] = 0;
                        }else{
                            $result['response']['data']['zid'] = $id;
                        }
                        
                    }

                    
                }
                if($result['response']['status']  == 1)
                {
                    do_action( 'openpos_logout',$session_id, $session_data  );
                    $this->session_class->clean($session_id);
                }
                
                
                $result['response']['data'] =  apply_filters('op_logout_data', $result['response'],$session_data);
                $result['response']['status'] = 1;
                $result['code'] = 200;
            }catch(Exception $e){
                $result['response']['message'] = $e->getMessage();
                $result['response']['status'] = 0;
                $result['code'] = 400;
            }

            return $this->rest_ensure_response( $result );
        }
        public function pos_state(WP_REST_Request $request = null){
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
                $last_check = $request->get_param('last_check') ? $request->get_param('last_check') : 0; // in miliseconds
                //$client_time_offset = isset($session_data['client_time_offset']) ? $session_data['client_time_offset'] : 0;
                $last_check_utc = $last_check ;//+ $client_time_offset * 60 * 1000;
                
                if($last_check == 0 && isset($session_data['logged_time']))
                {
                    $last_check = strtotime($session_data['logged_time']) * 1000;
                }
                $cart = $request->get_param('cart') ?  json_decode($request->get_param('cart'),true) : array();
                
                if( !empty($cart) ){
                    $this->register_class->update_bill_screen($session_data,$cart);
                }
                $tables_version = array();
                $ready_dish = array();
                $deleted_takeaway = array();
                $desk_message = '';
                $openpos_type = isset($session_data['setting']['openpos_type']) ? $session_data['setting']['openpos_type'] : $this->setting_class->get_option('openpos_type','openpos_pos');    
                
                if($openpos_type == 'restaurant' )
                {
                    $tables = $request->get_param('tables') ? json_decode($request->get_param('tables'),true) : array();
                    
                    if(!empty($tables))
                    {
                        

                        foreach($tables as $table_id => $table)
                        {
                            $source_type = isset($table['source_type']) ? $table['source_type'] : '';
                            if($source_type == 'order_takeaway')
                            {
                                //$tables[$table_id] = $table;
                            }
                        }
                        $this->table_class->update_bill_screen($tables,true,'background');
                    }

                    $warehouse_id = isset($session_data['login_warehouse_id']) ? $session_data['login_warehouse_id'] : 0;

                    $request_takeaway = $request->get_param('takeaway') ?  json_decode($request->get_param('takeaway'),true) : array();
                    
                    $update_data = $this->table_class->get_all_update_data($request_takeaway,$warehouse_id,$last_check,$last_check_utc);

                    $tables_version = isset($update_data['tables_version']) ? $update_data['tables_version'] : array();

                    $ready_dish = isset($update_data['ready_dish']) ? $update_data['ready_dish'] : array();

                    $tables_desk_messages = $update_data['desk_message'];
                    $deleted_takeaway = isset($update_data['deleted_takeaway']) ? $update_data['deleted_takeaway'] : array();

                    if(!empty($tables_desk_messages))
                    {
                        $desk_message = sprintf(__( 'There are new message from tables: %s', 'openpos' ),implode(',',$tables_desk_messages));
                        
                    }
                    
                }
                $notifications = $this->woo_class->getNotifications($last_check,$session_data);

                $result['response']['data'] = array(
                    'deleted_takeaway' => $deleted_takeaway,
                    'tables' => $tables_version,
                    'ready_dish' => $ready_dish,
                    'desk_message' => $desk_message,
                    'notifications' => $notifications,
                );
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
        public function pos_info(WP_REST_Request $request = null){
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
                $result['response']['data'] = array(
                    'version' => $this->core_class->_op_version_number(),
                    'type' => 'woocommerce-openpos',
                    'woo_version' => WC()->version,
                    'wp_version' => get_bloginfo( 'version' ),
                );
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
        
    }
}