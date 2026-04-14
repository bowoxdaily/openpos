<?php
defined( 'ABSPATH' ) || exit;
if(!class_exists('OP_Register'))
{
    class OP_Register{
        public $_post_type = '_op_register';
        public $_cashiers_meta_key = '_op_cashiers';
        public $_warehouse_meta_key = '_op_warehouse';
        public $_payment_guide_meta_key = '_op_payment_guide';
        public $_filesystem;
        public $_bill_data_path;
        public $_base_path;
        public $_cache_group = '_op_registers';
        public function __construct($base_path = '')
        {
            if(!class_exists('WP_Filesystem_Direct'))
            {
                require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php');
                require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php');
            }
            
            $this->_filesystem = new WP_Filesystem_Direct(false);
            if(!$base_path)
            {
                $upload_dir   = wp_upload_dir();
                $base_path =  $upload_dir['basedir'].'/openpos';
            }
            $this->_base_path = $base_path;
            $this->_bill_data_path =  $this->_base_path.'/registers';
            add_action( 'init', array($this, 'wp_init') );
            $this->init();
        }
        function wp_init(){
            register_post_type( '_op_register',
                    array(
                        'labels'              => array(
                            'name'                  => __( 'Register', 'openpos' ),
                            'singular_name'         => __( 'Register', 'openpos' )
                        ),
                        'description'         => __( 'This is where you can add new transaction that customers can use in your store.', 'openpos' ),
                        'public'              => false,
                        'show_ui'             => false,
                        'capability_type'     => 'op_report',
                        'map_meta_cap'        => true,
                        'publicly_queryable'  => false,
                        'exclude_from_search' => true,
                        'show_in_menu'        => false,
                        'hierarchical'        => false,
                        'rewrite'             => false,
                        'query_var'           => false,
                        'supports'            => array( 'title','author','content' ),
                        'show_in_nav_menus'   => false,
                        'show_in_admin_bar'   => false
                    )
            );
        }
        function init(){
            // create openpos data directory
            $chmod_dir = ( 0755 & ~ umask() );
            if (  defined( 'FS_CHMOD_DIR' ) ) {

                $chmod_dir = FS_CHMOD_DIR;
            }

            if(!file_exists($this->_base_path))
            {
                $this->_filesystem->mkdir($this->_base_path,$chmod_dir);
            }

            if(!file_exists($this->_bill_data_path))
            {
                $this->_filesystem->mkdir($this->_bill_data_path,$chmod_dir);
            }
        }
        public function registers($warehouse_id = -1){
            $result = array();
            $params = array(
                'post_type' => $this->_post_type,
                'post_status' => array('publish','draft'),
                'numberposts' => -1
                // 'order'    => 'ASC'
            );
            if($warehouse_id > -1)
            {
                $params['post_parent'] = $warehouse_id;
            }
            $posts = get_posts($params);
            foreach($posts as $p)
            {
                $result[] = $this->get($p->ID);
            }
            return $result;
        }
        public function delete($id){
            $post = get_post($id);
            if($post->post_type == $this->_post_type)
            {
                wp_trash_post( $id  );
            }
            $cache_key = 'op_register_'.$id;
            wp_cache_delete($cache_key, $this->_cache_group );
        }
        public function save($params){

            $id  = 0;
            if(isset($params['id']) && $params['id'] > 0)
            {
                $id = $params['id'];
            }
            $warehouse_id = isset($params['warehouse']) ? $params['warehouse'] : 0;
            $args = array(
                'ID' => $id,
                'post_title' => $params['name'],
                'post_type' => $this->_post_type,
                'post_status' => $params['status'],
                'post_parent' => $warehouse_id
            );
            $post_id = wp_insert_post($args);
            if(!is_wp_error($post_id)){

                $cashiers = array();
                if(isset($params['cashiers']))
                {
                    $cashiers = $params['cashiers'];
                }
                $mode = isset($params['register_mode']) ? esc_attr($params['register_mode']) : 'cashier';
                update_post_meta($post_id,'_op_mode',$mode);
                update_post_meta($post_id,$this->_cashiers_meta_key,$cashiers);
                update_post_meta($post_id,$this->_warehouse_meta_key,$warehouse_id);

                $cache_key = 'op_register_'.$post_id;
                wp_cache_delete($cache_key, $this->_cache_group );

                return $post_id;
            }else{
                //there was an error in the post insertion,
                throw new Exception($post_id->get_error_message()) ;
            }
        }
        public function get($id)
        {
            $cache_key = 'op_register_'.$id;
            
    
            $cached_data = wp_cache_get( $cache_key, $this->_cache_group );
    
            if ( false !== $cached_data )
            {
                $result = $cached_data;
            }else{
                $post = get_post($id);
                if(!$post)
                {
                    return array();
                }
                if($post->post_type != $this->_post_type)
                {
                    return array();
                }
                $name = $post->post_title;
                $warehouse = get_post_meta($id,$this->_warehouse_meta_key,true);

                $cashiers = get_post_meta($id,$this->_cashiers_meta_key,true);
                $register_mode = get_post_meta($id,'_op_mode',true);
                if(!$register_mode)
                {
                    $register_mode = 'cashier';
                }
                $status = $post->post_status;
                $result = array(
                    'id' => $id,
                    'name' => $name,
                    'warehouse' => $warehouse,
                    'cashiers' => $cashiers,
                    'balance' => $this->cash_balance($id),
                    'debit_total' => $this->debit_total($id),
                    'register_mode' => $register_mode,
                    'status' => $status
                );
                wp_cache_set( $cache_key, $result, $this->_cache_group );
            }
            

            return apply_filters('op_register_get_data',$result,$this);

        }
        public function getUserRegisters($user_id){
            $result = array();

            $registers = $this->registers();
        
            foreach($registers as $register)
            {
                
                if($register['status'] == 'publish')
                {
                    $cashiers = $register['cashiers'];
                    if(in_array($user_id,$cashiers))
                    {
                        $result[] = $register;
                    }
                }
            }
            return $result;
        }
        public function cash_balance($register_id = 0){
            $option_key = $this->get_transaction_balance_key($register_id);
            return $this->_get_option($option_key,0);
        }
        public function debit_total($register_id = 0){
            $option_key = $this->get_debit_key($register_id);
            return $this->_get_option($option_key,0);
        }
        public function get_transaction_balance_key($register_id = 0){
            $option_key = '_pos_cash_balance_'.$register_id;
            return $option_key;
        }
        public function get_debit_key($register_id = 0){
            $option_key = '_pos_debit_'.$register_id;
            return $option_key;
        }
        public function get_order_meta_key(){
            $option_key = '_pos_order_cashdrawer';
            return $option_key;
        }
        public function get_transaction_meta_key(){
            $option_key = '_pos_transaction_cashdrawer';
            return $option_key;
        }
        public function addCashBalance($register_id = 0 ,$amount = 0){
            $current_balance = $this->cash_balance($register_id);
            $new_blance = $current_balance + $amount;
            $option_key = $this->get_transaction_balance_key($register_id);
            update_option($option_key,$new_blance);

            
        }
        public function addDebitBalance($register_id = 0 ,$amount = 0){
            $current_balance = $this->debit_total($register_id);
            $new_blance = $current_balance + $amount;

            $option_key = $this->get_debit_key($register_id);

            update_option($option_key,$new_blance);

            
        }
        public function transactions($register_id = 0){

        }
        public function update_bill_screen($session_data,$cart_data){
            $register_id = isset($session_data['login_cashdrawer_id']) ? $session_data['login_cashdrawer_id'] : 0;
           
            if($register_id)
            {
               

                $cart_data['session_user'] = $session_data['name'];
                $cart_data['register'] = $this->get($register_id);
                if(!isset($cart_data['items']))
                {
                    //$cart_data['items'] = array();
                }
                if(!isset($cart_data['grand_total']))
                {
                    $cart_data['grand_total'] = 0;
                }
                $order_id = isset($cart_data['id']) ? $cart_data['id'] : 0;
                $cart_data['checkout_guide'] = '';
                if($order_id)
                {
                    
                    $guide = get_transient('op_checkout_guide_'.$register_id ) ;
                    if($guide !== false)
                    {
                        $cart_data['checkout_guide'] = $guide;
                    }
                    
                }

                $register_file = $this->bill_screen_file_path($register_id);
                $file_mode = apply_filters('op_file_mode',0755) ;
                if(file_exists($register_file))
                {
                    if ( defined( 'FS_CHMOD_FILE' ) ) {
                        $this->_filesystem->put_contents(
                            $register_file,
                            json_encode($cart_data)
                        );
                    }else{
                        $this->_filesystem->put_contents(
                            $register_file,
                            json_encode($cart_data),
                            $file_mode
                        );
                    }
                }else{
                    
                    $this->_filesystem->put_contents(
                        $register_file,
                        json_encode($cart_data),
                        $file_mode
                    );
                }
                
            }
        }
        public function bill_screen_file_path($register_id)
        {
            return $this->_bill_data_path.'/'.$register_id.'.json';
        }
        public function bill_screen_file_url($register_id,$site_id = 0)
        {
            $upload_dir = wp_upload_dir();
            $url = $upload_dir['baseurl'];
            $url = ltrim($url,'/');
            // if(is_multisite()){
            //     if(!$site_id)
            //     {
            //         $prefix = '/sites/'.get_current_blog_id();
            //         $url = str_replace($prefix, '',$url);
            //     }
                
            // }
            return $url.'/openpos/registers/'.$register_id.'.json';
        }
        public function bill_template(){
            $file_name = 'bill.txt';
            $file_path = OPENPOS_DIR.'/default/'.$file_name;
            if($this->_filesystem->is_file($file_path))
            {
                return $this->_filesystem->get_contents($file_path);
            }else{
                return '';
            }
        }
        public function get_modes(){
            $modes = array(
                'cashier' => __('Cashier Mode','openpos'),
                'seller' => __('Seller Mode - No checkout button','openpos'),
                'customer' => __('Customer Mode - Submit Order','openpos'),
                'waiter' => __('Waiter Mode - No checkout button','openpos')
            );

            return apply_filters('op_register_get_modes',$modes,$this);
        }
        private function _get_option($option,$default = '')
        {
            global $wpdb;
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option ) );
            $value = $default;
            if ( is_object( $row ) ) {
                $value = $row->option_value;
            } 
            return $value;
        }
        public function admin_register_menu($register,$openpos_type = ''){
            global $OPENPOS_CORE;
            $result = array();
            $result[] = array(
                'url' => admin_url('admin.php?page=op-registers&id='.esc_attr($register['id'])),
                'label' => __('Edit','openpos')
            );
            $result[] = array(
                'url' => 'javascript:void(0);',
                'label' => __('Delete','openpos'),
                'attributes' => array(
                    'data-id' => esc_attr($register['id']),
                    'class' => 'delete-register-btn'
                )
            );
            $result[] = array(
                'url' => admin_url('admin.php?page=op-transactions&register='.esc_attr($register['id'])),
                'label' => __('Transactions','openpos')
            );
            $result[] = array(
                'url' => admin_url('edit.php?post_type=shop_order&register='.esc_attr($register['id'])),
                'label' => __('Orders','openpos')
            );
            $result[] = array(
                'url' => isset($register['bill_url']) ?  esc_url($register['bill_url']) : esc_url($OPENPOS_CORE->get_bill_url($register['id'])),
                'label' => __('Bill Screen','openpos'),
                'attributes' => array(
                    'target' => '_blank'
                )
            );
            return apply_filters( 'op_register_menu', $result,$register, $openpos_type);
        }
        public function getCashierList($cashdrawer_id = 0){

            $roles =  array(); //array('administrator','shop_manager');
            $final_roles = apply_filters('op_allow_user_roles',$roles);
            if(!$final_roles)
            {
                $final_roles = array();
            }
            $rows = array();
            
            if($cashdrawer_id > 0)
            {
                $sellers = get_post_meta($cashdrawer_id,'_op_cashiers',true);
                
                foreach($sellers as $user_id)
                {
                    $user = get_userdata( $user_id );
                    if($user)
                    {
                        $user_roles = $user->roles;
                        $user_data = (array)$user->data;
    
                        if(isset($user_data['user_pass']))
                        {
                            unset($user_data['user_pass']);
                        }
    
                        $user_data['id'] = (int)$user_data['ID'];
                        $user_data['name'] = $user_data['display_name'] ? $user_data['display_name'] : $user_data['user_nicename'];
    
                        $user_data['avatar'] = get_avatar_url($user_id);
                        $allow = false;
                        $result = array_intersect($final_roles,$user_roles);
                        if(empty($final_roles)  || !empty($result))
                        {
                            $allow = true;
                        }
                        if($allow)
                        {
                            $rows[] = $user_data;
                        }
                        
                      
                    }
                }
            }else{
                $args = array(
                    'count_total' => true,
                    'number'   => -1,
                    'role__in' => $final_roles,
                    'fields' => array('ID', 'display_name','user_email','user_login','user_status'),
                    'meta_query' => array(
                        array(
                            'key'     => '_op_allow_pos',
                            'value'   => 1,
                            'compare' => '='
                        )
                    )
                );
                if(!empty($final_roles))
                {
                    $args['role__in'] = $final_roles;
                }
        
                $users = get_users( $args);
                foreach($users as $user)
                {
                    $tmp = (array)$user;
        
                    $tmp_test['id'] = (int)$tmp['ID'];
                    $tmp_test['name'] = $tmp['display_name'];
        
                    $rows[] = $tmp_test;
                }
            }
            
            return $rows;
        }
    }
}
?>