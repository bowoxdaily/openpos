<?php
if(!class_exists('OP_REST_API_Table'))
{
    class OP_REST_API_Table extends OP_REST_API{
        public function register_routes() {
            register_rest_route( $this->namespace, '/table/tables', array(
                'methods' =>  WP_REST_Server::CREATABLE,
                'callback' => array($this,'tables'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/table/takeaways', array(
                'methods' =>  WP_REST_Server::CREATABLE,
                'callback' => array($this,'takeaways'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/table/upload-table', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'upload_table'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/table/get', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'pull_table'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/table/clean-table', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'clean_table'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/table/remove-takeaway', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'remove_takeaway'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );

            register_rest_route( $this->namespace, '/table/messages', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'pull_messages'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/table/delete-message', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'delete_message'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/table/send-message', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'send_message'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/kitchen/(?P<store_id>\d+)/(?P<time_id>\d+)', array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this,'kitchen_data'),
                'permission_callback' => '__return_true',
                ) 
            );
            
        }
        public function tables(WP_REST_Request $request = null){
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
                $desk_ids =  $request->get_param('desk_ids') ?  json_decode($request->get_param('desk_ids'),true) : array();
                if(empty($desk_ids))
                {
                    throw new Exception('Desk Not found');
                }
                foreach($desk_ids as $desk_id)
                {
                    $desk_data = $this->table_class->get_data($desk_id);
                    $items = isset($desk_data['items']) ? $desk_data['items'] : array();
                    $version = isset($desk_data['ver']) ? $desk_data['ver'] : 0;
                    $sys_version = isset($desk_data['system_ver']) ? $desk_data['system_ver'] : 0;
                    $start_time = isset($desk_data['start_time']) ? $desk_data['start_time'] : 0;
                    $parent = isset($desk_data['parent']) ? $desk_data['parent'] : 0;
                    $seat = isset($desk_data['seat']) ? $desk_data['seat'] : -1;
                    $child_desks = isset($desk_data['child_desks']) ? $desk_data['child_desks'] : [];
                    $seller = isset($desk_data['seller']) ? $desk_data['seller'] : null;
                    $fee_item = isset($desk_data['fee_item']) ? $desk_data['fee_item'] : null;
                    if(!isset($seller['id']))
                    {
                        $seller = null;
                    }
                    $note = isset($desk_data['note']) ? $desk_data['note'] : '';
                    $customer =  isset($desk_data['customer']) ? $desk_data['customer'] : null;
                    
                    $result_data = array(
                        'items' => $items,
                        'version'  => $version,
                        'system_ver'  => $sys_version,
                        'start_time'  => $start_time,
                        'parent'  => $parent,
                        'seat'  => $seat,
                        'child_desks'  => $child_desks,
                        'seller' => $seller,
                        'note' => $note,
                        'customer' => $customer,
                        'fee_item' => $fee_item,
                    );
                    $result['response']['data'][$desk_id] = apply_filters('op_pull_desk_data',$result_data,$desk_data);
                }
            
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
        public function takeaways(WP_REST_Request $request = null){
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
                $desk_ids = $request->get_param('desk_ids') ? json_decode($request->get_param('desk_ids'),true) : array();
                if(!is_array($desk_ids))
                {
                    $desk_ids = array();
                }
                $warehouse_id = $this->session_data['login_warehouse_id'];
                $list = $this->table_class->takeawayJsonTables($warehouse_id,$desk_ids);
               
                $result['response']['data'] =  $list;
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
        public function upload_table(WP_REST_Request $request = null){
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
                $tables = json_decode($request->get_param('tables'),true);
                
                $is_force = $request->get_param('fore_update')  == 'yes' ? true : false;
                
                $session_data = $this->session_data;
                do_action('op_upload_desk_before',$tables,$session_data);
                $_tables = array();
                $_old_tables = array();
                if($session_data)
                {
                    $warehouse_id = $session_data['login_warehouse_id'];
                    
                    //save to table data
                    
                    foreach($tables as $table_id => $table)
                    {
                        $desk_type = $this->table_class->getDeskType($table);

                        
                        

                        //old data
                        $table_type = 'dine_in';
                        if(strpos($table_id,'desk') !== false )
                        {
                            $_table_id = str_replace('desk-','',$table_id);
                            
                        }
                        if(strpos($table_id,'takeaway') !== false )
                        {
                            $_table_id = str_replace('takeaway-','',$table_id);
                            $table_type = 'takeaway';
                            
                        }
                        

                        if($desk_type == 'guest_takeaway')
                        {
                            $table_id = 'takeaway-'.$table['desk']['id'];
                            $_table_id = $table['desk']['id'];
                            if($this->table_class->is_deleted($table_id,$desk_type,$warehouse_id)){
                                
                                throw new Exception(__('Your order has been deleted. Please scan order QRcode and try again.','openpos'));
                            }
                        }
                        $_old_tables[$table_id] = $this->table_class->get_data($_table_id,$desk_type,$warehouse_id);

                        
                        
                        if(strpos($table_id,'takeaway') !== false )
                        {
                            $this->table_class->removed_deleted_markup($warehouse_id,$table_id);
                        }

                        $_tables[$table_id] = $table;

                        //delete cache
                        $table_key = $_table_id;
                        if($desk_type != 'dine_in')
                        {
                            $table_key = $desk_type.'-'.$_table_id;
                        }
                        
                    }
                }
                
                do_action('op_upload_desk_after',$_tables,$this->table_class,$tables,$_old_tables,$session_data);
              
                $result['response']['data'] = $this->table_class->update_bill_screen($_tables,$is_force);
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
        public function upload_takeaway(WP_REST_Request $request = null){
            $result = array(
                'code' =>'unknown_error',
                'data' => array(
                    'status' => 0,
                    'result' => array(),
                ),
                'message' => ''
            );
            try{
                $takeaway_id = $request->get_param('takeaway_id');
                if(!$takeaway_id)
                {
                    throw new Exception(__('Takeaway not found','openpos'));
                }
                $register = isset($this->session_data['register']) ? $this->session_data['register'] : [];
                if(!isset($register['id']))
                {
                    throw new Exception(__('Register not found','openpos'));
                }
                $warehouse_id = $register['outlet_id'];


                $raw_json = $request->get_body();
                $by_data = json_decode($raw_json, true); 
                if(!$by_data)
                {
    
                    throw new Exception(__('Please enter table data','openpos'));
                }

                $_tables = array(
                    'takeaway-'.$takeaway_id = $by_data
                );
                $result['data']['result'] = $this->table_class->update_bill_screen($_tables,true);

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
        public function clean_table(WP_REST_Request $request = null){
            $result = array(
                'code' =>'unknown_error',
                'data' => array(
                    'status' => 0,
                    'result' => array(),
                ),
                'message' => ''
            );
            try{
                $table_id = $request->get_param('table_id');
                if(!$table_id)
                {
                    throw new Exception(__('Table not found','openpos'));
                }
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
        public function remove_takeaway(WP_REST_Request $request = null){
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

                $takeaway_key = $request->get_param('desk_number') ? trim($request->get_param('desk_number'),'#') : 0;
                $force = $request->get_param('force_remove')  == 'yes' ? true : false;
                if(!$takeaway_key)
                {
                    throw new Exception( __('Takeaway Not found','openpos')  );
                }
                $allow = apply_filters('op_allow_remove_takeaway',true,$takeaway_key);
                if($allow)
                {
                    $session_data = $this->session_data;
                    $warehouse_id = $session_data['login_warehouse_id'];
                    $this->table_class->removeJsonTable($takeaway_key,$force,$warehouse_id);
                    $result['response']['status'] = 1;
                }else{
                    throw new Exception( __('You do not allow remove this','openpos')  );
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
        public function pull_table($request){
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

                $desk_id = $request->get_param('desk_id') ? trim($request->get_param('desk_id'),'#') : 0;
                if(!$desk_id)
                {
                    throw new Exception('Desk Not found');
                }
                if(strpos($desk_id,'takeaway-') === 0)
                {
                    $_table_id = str_replace('takeaway-','',$desk_id);
                    $desk_data = $this->table_class->get_data($_table_id,'takeaway');
                    $result_data = $desk_data;
                }else{
                    $_table_id = str_replace('desk-','',$desk_id);
                    $desk_data = $this->table_class->get_data($_table_id);
                    $items = isset($desk_data['items']) ? $desk_data['items'] : array();
                    $version = isset($desk_data['ver']) ? $desk_data['ver'] : 0;
                    $system_ver = isset($desk_data['system_ver']) ? $desk_data['system_ver'] : 0;
                    $start_time = isset($desk_data['start_time']) ? $desk_data['start_time'] : 0;
                    $seller = isset($desk_data['seller']) ? $desk_data['seller'] : null;
                    $fee_item = isset($desk_data['fee_item']) ? $desk_data['fee_item'] : null;
                    $parent = isset($desk_data['parent']) ? $desk_data['parent'] : 0;
                    $seat = isset($desk_data['seat']) ? $desk_data['seat'] : 0;
                    $child_desks = isset($desk_data['child_desks']) ? $desk_data['child_desks'] : [];
                    if(!isset($seller['id']))
                    {
                        $seller = null;
                    }
                    $note = isset($desk_data['note']) ? $desk_data['note'] : '';
                    $customer =  isset($desk_data['customer']) ? $desk_data['customer'] : null;
                    
                    $result_data = array(
                        'items' => $items,
                        'version'  => $version,
                        'system_ver'  => $system_ver,
                        'start_time'  => $start_time,
                        'parent'  => $parent,
                        'seat'  => $seat,
                        'child_desks'  => $child_desks,
                        'seller' => $seller,
                        'note' => $note,
                        'customer' => $customer,
                        'fee_item' => $fee_item,
                    );
                    
                }
                
                $result['response']['data'] = apply_filters('op_pull_desk_data',$result_data,$desk_data);
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
        public function pull_messages($request){
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
                $time_stamp = $request->get_param('time_stamp') ? $request->get_param('time_stamp') : 0;
                $client_time_offset = $request->get_param('client_time_offset');
                $session_data = $this->session_data;
                if(!empty($session_data))
                {
                    
                    $warehouse_id = isset($session_data['login_warehouse_id']) ? $session_data['login_warehouse_id'] : -1;
                    if($warehouse_id >= 0)
                    {
                        $tables = $this->table_class->tables($warehouse_id);
                        $messages = array();
                        foreach($tables as $table)
                        {
                            $table_id = $table['id'];
                            $_messages = $this->table_class->getMessages($table_id);
                            foreach($_messages as $k => $v)
                            {
                                $messages[$k] = $v;
                            }
                        }
                        krsort($messages);
                        
                        if(!empty($messages))
                        {
                            $result['status'] = 1;
                            $result['data'] = $messages;
                        }
                    }
                
                }
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
        public function send_message($request){
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
                $time_stamp = $request->get_param('time_stamp') ? $request->get_param('time_stamp') : 0;
                $client_time_offset = $request->get_param('client_time_offset');
                $session_data = $this->session_data;
                
                if(!empty($session_data))
                {
                    $data_request = $request->get_param('data_request') ?   json_decode($request->get_param('data_request'),true) : array();
                    if(isset($data_request['messages']) && !empty($data_request['messages']))
                    {
                        $desk = $data_request['desk'];
                        if($desk && isset($desk['id']))
                        {
                            $messages = array();
                            foreach($data_request['messages'] as $time => $m)
                            {
                                $utc_time = $time_stamp ;//+ ($client_time_offset * 60 * 1000);
                                $messages[$utc_time] = $m;
                            }
                            if($this->table_class->addMessage($desk['id'],$data_request['messages']))
                            {
                                $result['response']['status'] = 1;
                                $result['response']['message'] = __('Your request has been sent to our waiter. Please wait.','openpos');
                            }else{
                                $result['response']['message'] = __('All by waiter is busy. Pls try later','openpos');
                            }
                        }
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
        public function delete_message($request){
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
                if(!empty($session_data))
                {
                    $time_stamp = $request->get_param('time_stamp') ? $request->get_param('time_stamp') : 0;
                    $client_time_offset = $request->get_param('client_time_offset');
                    $warehouse_id = isset($session_data['login_warehouse_id']) ? $session_data['login_warehouse_id'] : -1;
                    if($warehouse_id >= 0)
                    {
                        $tables = $this->table_class->tables($warehouse_id);
                        foreach($tables as $table)
                        {
                            $table_id = $table['id'];
                            if($this->table_class->clearMessages($table_id))
                            {
                                $result['response']['status'] = 1;
                                $result['response']['message'] = __('Deleted.','openpos');
                            }
                        }
                        
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

        public function kitchen_data($request){
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
                $store_id = $request->get_param('store_id');
                $last_check = $request->get_param('time_id');
                $cache_group = 'openpos';
                $cache_key = 'op_kitchen_'.$store_id;
                $start_time = time();
                $file =  $this->table_class->kitchen_data_path($store_id);
                
                $max_time = ini_get('max_execution_time');
                $timeout = min($max_time - 5, 20);
                while ((time() - $start_time) < $timeout) {
                    clearstatcache(true, $file); 
                    $modified_time = file_exists($file) ? @filemtime($file) : 0;
                   
                    if ($modified_time > $last_check) {

                        $cached_data = wp_cache_get( $cache_key, $cache_group );
                        if ( false !== $cached_data )
                        {
                            $data =  $cached_data;
                        }else{
                            $data = file_get_contents($file);
                            wp_cache_set( $cache_key, $data, $cache_group, 300 );
                        }
                        //print_r($modified_time.'|'.$last_check);
                        echo $data;
                        exit();
                    }
                    sleep(1);
                }
                throw new Exception(__('No change','openpos'),200);
                
            }catch(Exception $e)
            {
                
                $result['code'] = $e->getCode();
                $result['response']['status'] = 0;
                $result['response']['message'] = $e->getMessage();
            }
            return $this->rest_ensure_response($result);
        }
        
    }
}