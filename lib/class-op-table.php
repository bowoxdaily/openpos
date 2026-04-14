<?php
defined( 'ABSPATH' ) || exit;
if(!class_exists('OP_Table'))
{
    class OP_Table{
        public $_post_type = '_op_table';
        public $_warehouse_meta_key = '_op_warehouse';
        public $_position_meta_key = '_op_table_position';
        public $_type_meta_key = '_op_table_type';
        public $_cost_meta_key = '_op_table_cost';
        public $_cost_type_meta_key = '_op_table_cost_type';
        public $_seat_meta_key = '_op_table_seat';
        public $_filesystem;
        public $_bill_data_path;
        public $_kitchen_data_path;
        public $_bill_data_path_ready;
        public $_bill_data_path_completed;
        public $_bill_data_path_accepted;
        public $_bill_data_path_deleted;
        public $_base_path;
        public $_cache_group = '_op_tables';
        public $_core;
        public $_session;
        public function __construct($base_path = '')
        {
            if(!class_exists('WP_Filesystem_Direct'))
            {
                require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php');
                require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php');
            }
            $this->_core = new Openpos_Core();
            $this->_session =  new OP_Session();
            $this->_filesystem = new WP_Filesystem_Direct(false);
            if(!$base_path)
            {
                $upload_dir   = wp_upload_dir();
                $base_path =  $upload_dir['basedir'].'/openpos';
            }
            $this->_base_path =  $base_path;
            $this->_bill_data_path =  $this->_base_path.'/tables';
            $this->_kitchen_data_path =  $this->_base_path.'/kitchen';

            $this->_bill_data_path_ready =  $this->_base_path.'/ready';
            $this->_bill_data_path_completed =  $this->_base_path.'/completed';
            $this->_bill_data_path_accepted =  $this->_base_path.'/accepted';
            $this->_bill_data_path_deleted =  $this->_base_path.'/deleted';
            add_action( 'init', array($this, 'wp_init') );
            $this->init();
        }
        function wp_init(){
            register_post_type( '_op_table',
                    array(
                        'labels'              => array(
                            'name'                  => __( 'Table', 'openpos' ),
                            'singular_name'         => __( 'Table', 'openpos' )
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
            $chmod_dir = ( 0755 & ~ umask() );
            if (  defined( 'FS_CHMOD_DIR' ) ) {

                $chmod_dir = FS_CHMOD_DIR;
            }

            // create openpos data directory
            if(!file_exists($this->_base_path))
            {
                $this->_filesystem->mkdir($this->_base_path,$chmod_dir);
            }

            if(!file_exists($this->_bill_data_path))
            {
                $this->_filesystem->mkdir($this->_bill_data_path,$chmod_dir);
            }

            if(!file_exists($this->_bill_data_path_ready))
            {
                $this->_filesystem->mkdir($this->_bill_data_path_ready,$chmod_dir);
            }
            if(!file_exists($this->_bill_data_path_completed))
            {
                $this->_filesystem->mkdir($this->_bill_data_path_completed,$chmod_dir);
            }
            if(!file_exists($this->_bill_data_path_accepted))
            {
                $this->_filesystem->mkdir($this->_bill_data_path_accepted,$chmod_dir);
            }
            if(!file_exists($this->_kitchen_data_path))
            {
                $this->_filesystem->mkdir($this->_kitchen_data_path,$chmod_dir);
            }

            if(!file_exists($this->_bill_data_path_deleted))
            {
                $this->_filesystem->mkdir($this->_bill_data_path_deleted,$chmod_dir);
            }
            
            add_action('openpos_logout',array($this,'openpos_logout'),20,2);
            add_action('op_add_order_after',array($this,'op_add_order_after'),20,2);
            add_action('op_upload_desk_after',array($this,'op_upload_desk_after'),20,5);   
            add_action('op_product_data',array($this,'op_product_data'),20,3);

        }
        function openpos_logout($session_id, $session_data ){
            $warehouse_id = isset($session_data['login_warehouse_id']) ? $session_data['login_warehouse_id'] : -1;
            if($warehouse_id >= 0)
            {
               
                $this->removed_deleted_markup($warehouse_id);
                
            }
        }
        public function get_file_mode(){
            $file_mode = apply_filters('op_file_mode',0755) ;
            return $file_mode;
        }
        public function _updateTableNoPos(){
            //upload all table with no position
            $posts = get_posts([
                'post_type' => $this->_post_type,
                'numberposts' => -1,
                'meta_query' => array(
                    array(
                        'key' => $this->_position_meta_key,
                        'compare' => 'NOT EXISTS' // this should work...
                    ),
                )
            ]);
            foreach ($posts as $post)
            {
                $post_id = $post->ID;
                update_post_meta($post_id,$this->_position_meta_key,0);
            }
        }
        public function tables($warehouse_id = -1,$is_front = false ){
            $result = array();

            if($warehouse_id >= 0)
            {
                $cache_key = 'op_tables_'.$warehouse_id;
                $cache_group = $this->_cache_group;
                $cached_data = wp_cache_get( $cache_key, $cache_group );
               
                if ( false !== $cached_data )
                {
                    $result =  $cached_data;
                }else{
                   
                    $posts = get_posts([
                        'post_type' => $this->_post_type,
                        'post_status' => array('publish'),
                        'numberposts' => -1,
                        'order'     => 'ASC',
                        'meta_key' => $this->_position_meta_key,
                        'orderby'   => 'meta_value_num'
                    ]);
                    foreach($posts as $p)
                    {
                        $tmp = $this->get($p->ID);
                        if($tmp['warehouse'] == $warehouse_id)
                        {
                            $result[] = $tmp;
                        }
                    }
                    
                    wp_cache_set( $cache_key, $result, $cache_group );
                }
            }else{
                $posts = get_posts([
                    'post_type' => $this->_post_type,
                    'post_status' => array('publish','draft'),
                    'numberposts' => -1,
                    'order'     => 'ASC',
                    'meta_key' => $this->_position_meta_key,
                    'orderby'   => 'meta_value_num',
                ]);

                foreach($posts as $p)
                {
                    $result[] = $this->get($p->ID,$is_front);
                }
            }
            
           
            return $result;
        }
        public function takeawayJsonTables($warehouse_id = -1 ,$takeaway_keys = [] ){
            $result = array();
            $takeaways = $this->takeaways($warehouse_id);
            $guest_takeaways = $this->guest_takeaways($warehouse_id);
            
            $all_takeaways = array(
                'takeaway' => $takeaways,
                'guest_takeaway' => $guest_takeaways
            );
            
            foreach($all_takeaways as $type => $tables)
            {
                foreach($tables as $table_id)
                {
                    //$table_id = $table['id'];
                    $key = implode('-',array($type,$table_id));
                    if(!empty($takeaway_keys) && (!in_array($table_id,$takeaway_keys) && !in_array($key,$takeaway_keys)))
                    {
                        continue;
                    }
                    $table_data = $this->get_data($table_id,$type,$warehouse_id);
                    
                    if($table_data)
                    {
                        $result_table = $table_data;
                        if(isset($result_table['online_ver']) )
                        {

                            $result_table['online_ver'] = max($result_table['ver'],$result_table['online_ver']);
                        }
                        if(!isset($result_table['desk']))
                        {
                            continue;
                        }
                        $desk = $result_table['desk'];

                        if(isset($desk['warehouse_id']))
                        {
                            if(  $warehouse_id >= 0 && $desk['warehouse_id'] != $warehouse_id)
                            {
                                continue;
                            }
                            $result[] =  $result_table;
                        }

                    }
                }
            }

            return $result;
        }
        public function takeawayTables($warehouse_id = -1 ){

            $result = array();
            $takeaways = $this->takeaways($warehouse_id);
            $guest_takeaways = $this->guest_takeaways($warehouse_id);

            $all_takeaways = array(
                'takeaway' => $takeaways,
                'guest_takeaway' => $guest_takeaways
            );

           

            foreach($all_takeaways as $type => $tables)
            {
                foreach($tables as $table)
                {
                    $table_id = isset($table['id']) ? $table['id'] : 0;
                    $table_data = array();
                    if($table_id)
                    {
                        $table_data = $this->get_data($table_id,$type,$warehouse_id);
                    }
                    
                    if(!empty($table_data))
                    {
                        
                        $result_table = $table_data;
                               
                        if(!isset($result_table['desk']))
                        {
                            continue;
                        }
                        $desk = $result_table['desk'];
                        

                        if(isset($desk['warehouse_id']))
                        {
                            if(  $warehouse_id >= 0 && $desk['warehouse_id'] != $warehouse_id)
                            {
                                continue;
                            }
                            $table_name = $desk['name'];
                            if(isset($result_table['label']) && $result_table['label'] != null )
                            {
                                $table_name = $result_table['label'];
                            }
                            $result[] = array(
                                'id' => $desk['id'],
                                'name' => $table_name,
                                'warehouse' => $desk['warehouse_id'],
                                'position' => 0,
                                'status' => 'publish',
                                'dine_type' => 'takeaway',
                            );
                        }
                    }
                }
            }   

            return $result;
          

        }
        public function delete($id){
            $table = $this->get($id);
            if($table && !empty($table))
            {
                wp_trash_post( $id  );
            }

            $cache_key = 'op_table_'.$id;
            wp_cache_delete($cache_key, $this->_cache_group );

            $warehouse_id = $table['warehouse'];
            $cache_key = 'op_tables_'.$warehouse_id;
            wp_cache_delete($cache_key, $this->_cache_group );
        }
        public function save($params){

            $id  = 0;
            if(isset($params['id']) && $params['id'] > 0)
            {
                $id = $params['id'];
            }
            $warehouse_id = isset($params['warehouse']) ? $params['warehouse'] : 0;
            $position = isset($params['position']) ? (int)$params['position'] : 0;
            $seat = isset($params['seat']) ? (int)$params['seat'] : 0;

            $type = isset($params['type']) ? $params['type'] : 'default';
            $cost = isset($params['cost']) ? $params['cost'] : 0;
            $cost_type = isset($params['cost_type']) ? $params['cost_type'] : 'hour';
            $args = array(
                'ID' => $id,
                'post_title' => $params['name'],
                'post_type' => $this->_post_type,
                'post_status' => $params['status'],
                'post_parent' => $warehouse_id
            );
            $post_id = wp_insert_post($args);

            $cache_key = 'op_tables_'.$warehouse_id;
            wp_cache_delete($cache_key, $this->_cache_group );

            if(!is_wp_error($post_id)){


                update_post_meta($post_id,$this->_warehouse_meta_key,$warehouse_id);
                update_post_meta($post_id,$this->_position_meta_key,$position);
                update_post_meta($post_id,$this->_seat_meta_key,$seat);

                update_post_meta($post_id,$this->_type_meta_key,$type);
                update_post_meta($post_id,$this->_cost_meta_key,$cost);
                update_post_meta($post_id,$this->_cost_type_meta_key,$cost_type);

                $cache_key = 'op_table_'.$post_id;
                wp_cache_delete($cache_key, $this->_cache_group );

                return $post_id;
            }else{
                //there was an error in the post insertion,
                throw new Exception($post_id->get_error_message()) ;
            }
        }
        public function get($id,$is_front = false)
        {
            $cache_key = 'op_table_'.$id;
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
                $position = get_post_meta($id,$this->_position_meta_key,true);
                $seat = get_post_meta($id,$this->_seat_meta_key,true);
                
                $type = get_post_meta($id,$this->_type_meta_key,true);
                $cost_type = get_post_meta($id,$this->_cost_type_meta_key,true);
                $cost = get_post_meta($id,$this->_cost_meta_key,true);

                if(!$cost)
                {
                    $cost = 0;
                }
                if(!$cost_type)
                {
                    $cost_type = 'hour';
                }
                if(!$type)
                {
                    $type = 'default';
                }
                if($type != 'hire')
                {
                    $cost = 0;
                    $cost_type = 'hour';
                }

                $status = $post->post_status;
                $result = array(
                    'id' => $id,
                    'name' => $name,
                    'warehouse' => $warehouse,
                    'position' => (int)$position,
                    'seat' => (int)$seat,
                    'type' => $type,
                    'cost' => $cost,
                    'cost_type' => $cost_type,
                    //'cost_base_on' => 'total',
                    'status' => $status
                );
                
                wp_cache_set( $cache_key, $result, $this->_cache_group );
            }

            if($is_front)
            {
                $min_cost = $result['cost'] ;
                switch($result['cost_type'])
                {
                    case 'day':
                        $min_cost = $min_cost / ( 60 * 24 );
                        break;
                    case 'hour':
                        $min_cost = $min_cost / ( 60  );
                        break;
                }
                $result['cost_type'] = 'minute';
                $result['cost'] = 1 * $min_cost;
                
            }
            
            return  apply_filters('op_table_details',$result,$is_front);;
        }

        public function update_bill_screen($tables_data,$is_purge = false,$source = ''){
            $result = array();
            if(!empty($tables_data))
            {
                $allow_update_kitchen = false;
                $outlet_id = -1;
                $server_time = time()*1000;// in miliseconds
                if($source == 'background')
                {
                    $is_purge = true;
                }
                

                foreach($tables_data as $table_key => $table_data)
                {
                    $table_type = 'dine_in';
                    $table_id = str_replace('desk-','',$table_key);
                    $_table_id = $table_data['id'] ? $table_data['id']: $table_id;
                    if(strpos($table_id,'takeaway') !== false )
                    {
                        $table_type = 'takeaway';
                        if(!isset($table_data['id']) || !$table_data['id'])
                        {
                            
                            $_table_id = $table_id;
                            $table_data['id'] = $table_id;
                            $table_data['order_number'] = $table_id;
                            $table_data['source'] = 'desk_takeaway';
                            $table_data['source_type'] = 'desk_takeaway';
                            $table_data['type'] = 'takeaway';
                        }
                        $table_id = 'takeaway-'.$table_id;
                        if(strpos($table_id,'guest_takeaway') !== false )
                        {
                                $table_type = 'guest_takeaway';
                        }
                        if($table_type == 'guest_takeaway')
                        {
                        	$table_data['order_number'] = $table_data['id'];
                        }
                    }
                    
                    $current_data = $this->get_data($_table_id,$table_type);
                   
                    
                    $desk = isset($table_data['desk']) ? $table_data['desk'] : array();
                    
                    if(!empty($desk))
                    {
                        $outlet_id = isset($desk['warehouse']) ? $desk['warehouse'] : -1;
                        if($outlet_id == -1){
                            $outlet_id = isset($desk['warehouse_id']) ? $desk['warehouse_id'] : 0;    
                         }
                    }else{
                        $is_purge = true;
                    }
                    
                    
                    $allow_update = true;
                    $table_sys_version = isset($table_data['system_ver']) ? $table_data['system_ver'] : 0;
                    $current_sys_version = isset($current_data['system_ver']) ? $current_data['system_ver'] : 0;
                    

                    if($is_purge)
                    {
                        $allow_update = true;
                    }else{
                        if($table_sys_version < $current_sys_version)
                        {
                            $allow_update = false;
                            throw new Exception(__('There are an other update of this table. Please refresh this table and try again. new:'.$table_sys_version .'- old:'. $current_sys_version,'openpos'));
                        }
                        
                        
                    }
                    
                   
                    $_table_data = apply_filters('op_update_table_data',$table_data,$current_data);

                  
                    
                    $_allow_update = apply_filters('op_get_allow_update_table_data',$allow_update,$table_data,$current_data);
                    $result[$table_key] = $current_data;
                    
                    if($_allow_update)
                    {
                        if($source != 'background')
                        {
                            $_table_data['system_ver'] =  $server_time;
                        }
                        $allow_update_kitchen = true;
                       
                        $this->update_data($_table_data,$_table_id,$table_type,$outlet_id);
                        
                        $result[$table_key] = $_table_data;
                    }
                   
                }
                if($outlet_id  >= 0 && $allow_update_kitchen )
                {
                    
                    $this->update_kitchen_data($outlet_id);
                }

            }
            return $result;
        }
        public function update_table_bill_screen($table_id,$table_data,$table_type = 'dine_in'){ //use for update message guest update from kitchen only
           
            $this->update_data($table_data,$table_id,$table_type);
            $desk = isset($table_data['desk']) ? $table_data['desk'] : array();
                    
            if(!empty($desk))
            {
                $outlet_id = isset($desk['warehouse']) ? $desk['warehouse'] : -1;

                if($outlet_id == -1){
                    $outlet_id = isset($desk['warehouse_id']) ? $desk['warehouse_id'] : 0;    
                }
                $this->update_kitchen_data($outlet_id);
            }
            
        }
        public function bill_screen_file_path($table_id)
        {
            return $this->_bill_data_path.'/'.$table_id.'.json';
        }
        public function bill_screen_file_url($table_id)
        {
            $upload_dir = wp_upload_dir();
            $url = $upload_dir['baseurl'];
            $url = ltrim($url,'/');
            return $url.'/openpos/tables/'.$table_id.'.json';
        }
        private function _bill_screen_data($table_id,$type='dine_in')
        {
            if($type != 'dine_in')
            {
                $table_id = $type.'-'.$table_id;
            }
            $file_path = $this->bill_screen_file_path($table_id);
            
            if(!file_exists($file_path))
            {
                $table_id = 'takeaway-'.$table_id;
                $guest_path = $this->bill_screen_file_path($table_id);
                $data = $this->_filesystem->get_contents($guest_path);
                $result = array();
                if($data)
                {
                    $guest_data = json_decode($data,true);
                    $desk = isset($guest_data['desk']) ? $guest_data['desk'] : array();
                    $desk_type = isset($desk['type']) ? $desk['type'] : '';
                    if($desk_type == 'guest_takeaway')
                    {
                        $result = $guest_data;
                    }
                }

                return $result;

            }else{
                $data = $this->_filesystem->get_contents($file_path);

                $result = array();
                if($data)
                {
                    $result = json_decode($data,true);
                }

                return $result;
            }
        }
        
       
        function generate_removing_file_path($file_name,$outlet_id = 0){
            $process_name = 'removing_';
            $process_name .= $file_name;
            if($outlet_id < 0)
            {
                $outlet_id = 0;
            }
            $chmod_dir = $this->get_file_mode();
            if(!file_exists($this->_bill_data_path_deleted.'/'.$outlet_id))
            {
                $this->_filesystem->mkdir($this->_bill_data_path_deleted.'/'.$outlet_id,$chmod_dir);
            }
            return $this->_bill_data_path_deleted.'/'.$outlet_id.'/'.$process_name;
        }
        public function removeJsonTable($table_id,$force = false,$outlet_id = -1){
            $table_type = 'dine_in';
            $_table_id = str_replace('desk-','',$table_id);
            $table_key = $table_id;
            if(strpos($table_id,'takeaway') !== false )
            {
                $_table_id = str_replace('takeaway-','',$table_id);
                $table_type = 'takeaway';
                $table_key = 'takeaway-'.$_table_id;
            }
            $table_data = $this->get_data($table_id,$table_type);
            
            
            $desk = isset($table_data['desk']) ? $table_data['desk'] : array();
           
            do_action('op_remove_json_table_before',$table_data,$outlet_id);

            $this->remove_data($_table_id,$table_type);
            if($table_type == 'takeaway')
            {
             
                $this->remove_takeaway($_table_id,$outlet_id);
            }
            if($outlet_id >= 0)
            {
                
                $this->update_kitchen_data($outlet_id);
            }
        }
        public function clear_all_data($warehouse_id = -1 ){
            $result = array();
            
           
            if($warehouse_id >= 0)
            {
                $tables = $this->tables($warehouse_id);
                $takeaways = $this->takeaways($warehouse_id);
                $guest_takeaways = $this->guest_takeaways($warehouse_id);
                
                //$exist_tables = array();
                foreach($tables as $t)
                {
                    //$exist_tables[] = $t['id'];
                    $this->remove_data($t['id'],'dine_in');
                }
                foreach($takeaways as $t)
                {
                    $this->remove_data($t,'takeaway');
                    
                }
                
                foreach($guest_takeaways as $t)
                {
                    $this->remove_data($t,'guest_takeaway');
                }
                $this->remove_all_takeaway($warehouse_id);
                $this->remove_all_takeaway($warehouse_id,true);
                
                
                $this->update_kitchen_data($warehouse_id);
            }
            $this->removed_deleted_markup($warehouse_id);
            return $result;
        }
        public function getTableByKey($key){
            $key_meta = '_op_barcode_key';
            $register_meta = '_op_barcode_register';
            $args = array(
                'meta_query'        => array(
                    array(
                        'key'       => $key_meta,
                        'value'     => $key
                    )
                ),
                'post_type'         => $this->_post_type,
                'posts_per_page'    => '1',
                'post_status' => 'any'
            );
           
            // run query ##
            $posts = get_posts( $args );
            
            
            $result = null;
            foreach($posts as $p)
            {
                $result = $this->get($p->ID);

            }
            if($result != null)
            {
                $result['register_id'] = get_post_meta($result['id'],$register_meta,true);
            }
            
            return $result;
            
        }
        
        public function verify_template(){
            $file_name = 'table.txt';
            $file_path = OPENPOS_DIR.'/default/'.$file_name;
            if($this->_filesystem->is_file($file_path))
            {
                return $this->_filesystem->get_contents($file_path);
            }else{
                return '';
            }
        }
        public function kitchen_view_template($display = 'items')
        {
            $file_name = 'kitchen_view_';
            $file_name.= $display;
            $file_name.= '.txt';
            $file_path = apply_filters('kitchen_view_template_path',OPENPOS_DIR.'/default/'.$file_name,$display);
            
            if($this->_filesystem->is_file($file_path))
            {
                return $this->_filesystem->get_contents($file_path);
            }else{
                return '';
            }
        }
        public function addMessage($table_id,$messages = array()){
            $table_data = $this->get_data($table_id);
            $table_data['messages'] = $messages;
            try{
                $this->update_table_bill_screen($table_id,$table_data);
                return true;
            }catch(Exception $e)
            {
                return false;
            }
        }
        public function getMessages($table_id){
            $table_data = $this->get_data($table_id);
            
            $result = array();
            if(isset($table_data['messages']) &&  !empty($table_data['messages']))
            {
                foreach($table_data['messages'] as $key => $m)
                {
                    $result[$key] = array(
                        'id' => $key,
                        'content' => $m,
                        'desk_id' => $table_id ,
                        'desk' => $table_data['desk']['name'],
                        'time_stamp' => $key //UTC time stamp
                    );
                }
            }
            return $result;
        }
        public function clearMessages($table_id){
            $table_data = $this->get_data($table_id);
            $table_data['messages'] = array();
            try{
                $this->update_table_bill_screen($table_id,$table_data);
                return true;
            }catch(Exception $e)
            {
                return false;
            }
        }

        public function getNotifications($last_check){
            $message = false;//sprintf( __('You have new message from table %s','openpos'),'ngoai troi');
            return $message;
        }
        public function get_all_update_data($request_takeaway = array(),$warehouse_id=0,$time_stamp = 0,$time_stamp_utc = 0)
        {
            
            $tables = $this->tables($warehouse_id);
            $takeaways = $this->takeaways($warehouse_id);
            $guest_takeaways = $this->guest_takeaways($warehouse_id);

            $table_ids = array();
            foreach($tables as $t)
            {
                $table_ids[] = $t['id'];
            }

           
            

            $tables_version = array();
            $ready_dish = array();
            $desk_message = array();
            $deleted_takeaway = array();
            
            $all_tables = array(
                'dine_in' => $table_ids,
                'takeaway' => $takeaways,
                'guest_takeaway' => $guest_takeaways,
            );

            foreach($all_tables as $table_type => $table_list)
            {
                foreach($table_list as $t)
                {
                    $data =  $this->get_data($t,$table_type,$warehouse_id);
                    if($data)
                    {
                        $result_table = $data;
                        if($warehouse_id >= 0)
                        {
                            if( isset($result_table['desk']['warehouse_id']) && $result_table['desk']['warehouse_id'] != $warehouse_id ){
                                continue;
                            }
                        }
                        //table version
                        $version = isset($result_table['system_ver']) ? $result_table['system_ver'] : 0;

                        $table_key = $t;
                        if($table_type != 'dine_in')
                        {
                            $table_key = implode('-',array($table_type,$t));
                        }
                       
                        $tables_version[$table_key] = 1*$version;


                        //ready item
                        $items = isset($result_table['items']) ? $result_table['items'] : array();
                        $table = isset($result_table['desk']) ? $result_table['desk'] : [];
                        $table_name = isset($table['name']) ? $table['name'] : '';
                        $table_id = isset($table['id']) ? $table['id'] : 0;
                        if(!empty($items))
                        {
                            foreach ($items as $_item)
                            {
                                if(isset($_item['done']) && $_item['done'] == 'ready')
                                {
                                    $item_dinning = isset($_item['dining']) ? $_item['dining'] : $table_type;
                                    if(!$item_dinning)
                                    {
                                        $item_dinning = 'dine_in';
                                    }
                                    $ready_dish[] = array(
                                        'id' => $_item['id'],
                                        'table_id' => $table_id,
                                        'table_name' => $table_name,
                                        'table_type' => $table_type,
                                        'item_dinning' => $item_dinning,
                                        'item_name' => $_item['qty'].' x '.$_item['name']
                                    );
                                }
                            }

                        }
                        //desk messages
                        $messages = isset($result_table['messages']) ? $result_table['messages'] : array();
                        $new_messages = array();
                        if(!empty($messages))
                        {
                            foreach($messages as $time_utc => $content)
                            {
                                if($time_stamp_utc < $time_utc)
                                {
                                    $new_messages[] = $content;
                                }
                            }
                        }
                        if(!empty($new_messages))
                        {
                            $desk_message[] = $table_name;
                        }

                    }
                }
            }             
            foreach($request_takeaway as $takeaway_id)
            {
                if($this->is_deleted($takeaway_id,'takeaway',$warehouse_id))
                {
                    $deleted_takeaway[] = $takeaway_id;
                }
            }
            $result = array(
                'tables_version' => $tables_version,
                'ready_dish' => $ready_dish,
                'desk_message' => $desk_message,
                'deleted_takeaway' => array_unique($deleted_takeaway),
            );
           
            return $result;
        }
        public function kitchen_custom_action($custom_action){
            return apply_filters('op_kitchen_custom_action',array(),$custom_action,$this);
        }
        public function kitchen_data_url($outlet_id = 0){
            $upload_dir = wp_upload_dir();
            $url = $upload_dir['baseurl'];
            $url = ltrim($url,'/');
            return get_rest_url(null,'/op/v1/kitchen/'.$outlet_id);
            //return $url.'/openpos/kitchen/'.$outlet_id.'.json';
        }
        public function kitchen_data_path($outlet_id = 0){
            return $this->_kitchen_data_path.'/'.$outlet_id.'.json';;
        }
       
        public function update_kitchen_data($outlet_id = 0)
        {
            global $op_woo;
            $warehouse_id = $outlet_id;
            $cache_group = 'openpos';
            $cache_key = 'op_kitchen_'.$outlet_id;

            wp_cache_delete($cache_key, $cache_group );

            $result = array();
            $result_formated = array();

            $result_orders = array();
            $result_items = array();
            $items_formated = array();
            $orders_formated = array();

            $all_area = $op_woo->getListRestaurantArea();
            $result_orders['all'] = array();
            $result_items['all'] = array();
            foreach($all_area as $a => $area)
            {
                $result_orders[$a] = array();
                $result_items[$a] = array();
            }

            $total = 0;
            if($warehouse_id >= 0)
            {
                $off_tables = $this->tables((int)$warehouse_id);
                $takeaway_tables =  $this->takeaways((int)$warehouse_id);
                $guest_takeaways =  $this->guest_takeaways((int)$warehouse_id);
                $tables = array(
                     'dine_in' => $off_tables,
                     'takeaway' => $takeaway_tables,
                     'guest_takeaway' => $guest_takeaways
                );

           
               
                foreach($tables as $table_type => $_tables)
                {
                    
                    foreach($_tables as $table)
                    {

                        
                        if($table_type == 'dine_in')
                        {
                            
                            $table_id = $table['id'];
                            $table_data = $this->get_data( $table_id,$table_type,$warehouse_id);
                        }else{
                            
                            $table_id = $table;
                            $table_data = $this->get_data( $table_id,$table_type,$warehouse_id);
                        }
                        
                       
                        if(isset($table_data['parent']) && $table_data['parent'] == 0 && isset($table_data['items'])  && count($table_data['items']) > 0)
                        {
                            $items = $table_data['items'];
                            $formatted_items = array();
                            $is_full_serverd = true;
                            $last_order_timestamp = 0;
                            
                            foreach($items as $key => $item)
                            {
                                $id = 1 * $item['id'];

                                if($id > $last_order_timestamp)
                                {
                                    $last_order_timestamp = $id;
                                }
                                $product_id = isset($item['product_id']) ? $item['product_id'] : 0;

                                

                                if(isset($item['done']) && ($item['done'] == 'done' || $item['done'] == 'done_all'))
                                {
                                    continue;
                                }else{
                                    $is_full_serverd = false;
                                    $timestamp = (int)($item['id'] / 1000);
                                    if(isset($item['order_time']) && $item['order_time'] > 100)
                                    {
                                    if(is_numeric($item['order_time']))
                                    {
                                            $timestamp = (1*($item['order_time']) / 1000);
                                    }
                                    
                                    }
                                    if(isset($item['order_timestamp']) && $item['order_timestamp'] > 100)
                                    {
                                    if(is_numeric($item['order_timestamp']))
                                    {
                                            $timestamp = (1*($item['order_timestamp']) / 1000);
                                    }
                                    
                                    }
                                    $order_timestamp = $timestamp  * 1000;

                                    $timestamp += wc_timezone_offset();

                                    $order_time = '--:--';
                                    if($timestamp)
                                    {
                                        $order_time = date('d-m-y  h:i',$timestamp);
                                    }
                                    
                                    $dish_id = $id.'-'. $table_id;
                                    if($table_type && $table_type != 'dine_in')
                                    {
                                        $dish_id.= '-'.$table_type;
                                    }
                                    $item_note = $item['sub_name'];
                                    $order_note = '';
                                    if(isset($table_data['note']) && $table_data['note'])
                                    {
                                        $order_note = $table_data['note'];
                                    }
                                    $item_kitchen_area = array();

                                    foreach($all_area as $a => $area)
                                    {
                                        if($product_id)
                                        {
                                            if($op_woo->check_product_kitchen_op_type($a,$product_id,$item)){
                                                $item_kitchen_area[] = $a;
                                            }
                                        }
                                    }

                                    
                                    
                                    $tmp = array(
                                        'id' => $dish_id,
                                        'local_id' => $id ,
                                        'product_id' => $product_id ,
                                        'priority' => 1,
                                        'item' => $item['name'],
                                        'seller_name' => $item['seller_name'] ? $item['seller_name'] : '',
                                        'qty' => $item['qty'],
                                        'table' => isset($table_data['label']) ? $table_data['label'] : (isset($table_data['desk']['name']) ? $table_data['desk']['name'] : ''),
                                        'order_time' => $order_time,
                                        'order_timestamp' => $order_timestamp,
                                        'note' => $item_note,
                                        'order_note' => $order_note,
                                        'dining' => isset($item['dining']) ? $item['dining'] : '',
                                        'done' => isset($item['done']) ? $item['done'] : '',
                                        'allow_action' => array(),
                                        'kitchen_area' => $item_kitchen_area,
                                    );
                                    $item_key = md5(json_encode(array('product_id'=> $tmp['product_id'],'name'=> $tmp['item'],'note'=> $tmp['note'],'order_note'=> $tmp['order_note'],'dining'=> $tmp['dining'])));

                                    $tmp['item_key'] = $item_key;
                                    
                                    $dish_data = apply_filters('op_kitchen_dish_item_data',$tmp,$table_data,$item);
                                    if($dish_data && !empty($dish_data) )
                                    {
                                        $result_items['all'][$id] =  $dish_data;
                                        $formatted_items['all'][] = $dish_data;
                                        $kitchen_area = isset($dish_data['kitchen_area']) ? $dish_data['kitchen_area'] : array();
                                        foreach($kitchen_area as $a)
                                        {
                                            $result_items[$a][$id] =  $dish_data;
                                            $formatted_items[$a][] = $dish_data;
                                        }
                                        $total++;
                                    }
                                }
                            }

                            if( !empty($formatted_items['all']) && !$is_full_serverd)
                            {
                                $table_data['items'] = $formatted_items['all'];
                                $table_data['allow_action'] = array();
                                $table_data['dining'] = isset($table_data['dining']) ? $table_data['dining'] : '';
                                $table_data['order_timestamp'] = isset($table_data['created_at_time']) && $table_data['created_at_time'] > 100 ? $table_data['created_at_time'] : $last_order_timestamp;
                                if($last_order_timestamp)
                                {
                                    if(isset($result_items['all'][$last_order_timestamp]))
                                    {
                                        $last_order_timestamp = $last_order_timestamp + rand(1,10);
                                    }
                                    $result_orders['all'][$last_order_timestamp] = apply_filters('op_kitchen_dish_table_data',$table_data);
                                }else{
                                    $result_orders['all'][] = apply_filters('op_kitchen_dish_table_data',$table_data);
                                }
                                foreach($formatted_items as $a => $area_items)
                                {
                                    if($a != 'all')
                                    {
                                        $table_data['items'] = $formatted_items[$a];
                                        if($last_order_timestamp)
                                        {
                                            $result_orders[$a][$last_order_timestamp] = apply_filters('op_kitchen_dish_table_data_area',$table_data,$a);
                                        }else{
                                            $result_orders[$a][] = apply_filters('op_kitchen_dish_table_data_area',$table_data,$a);
                                        }
                                    }
                                }
                            }
                            
                            
                        }
                    }
                }
            }

            
           
            
            foreach($result_orders as $a => $_result_orders)
            {
                if(!empty($_result_orders))
                {
                    $keys = array_keys($_result_orders);
                    sort($keys);
                    
                    foreach($keys as  $r)
                    {
                        $orders_formated[$a][] = $_result_orders[$r];
                    }
                }else{
                    $orders_formated[$a] = array();
                }
            }
            if(!empty($result_items))
            {
                foreach($result_items as $a => $_result_items)
                {
                    if(empty($_result_items))
                    {
                        $items_formated[$a] = array();
                    }else{
                        $i = 1;
                        $keys = array_keys($_result_items);
                        $min_key = min($keys);
                        foreach($_result_items as  $r)
                        {
                            $key = 1*$r['order_timestamp'] - $min_key;
                            if(isset($r['local_id']) && $r['local_id'])
                            {
                                $key = 1*$r['local_id'] - $min_key;
                            }
                            
                            $r['priority'] = round($i / $total,2) * 100;
                            $items_formated[$a][$key] = $r;
    
                            $i++;
                        }
                    }
                    

                }
            }
            $result_formated['orders'] = $orders_formated;
            $result_formated['items'] = $items_formated;
            $kitchen_data = $result_formated;
            $data_file = $this->kitchen_data_path($outlet_id);
           
            $final_kitchen_data = apply_filters('op_kitchen_tables_data',$kitchen_data,$outlet_id,$this);
            
           

            $file_mode = $this->get_file_mode();
            
            
            $this->_filesystem->put_contents(
                $data_file,
                json_encode($final_kitchen_data),
                $file_mode // predefined mode settings for WP files
            );
            
        }
        public function removed_deleted_markup($warehouse_id = 0,$table_id = ''){
            // $deleted_files = array();
            // $takeaway_files = array();
            // $diff_files = array();
            // if($table_id)
            // {
                
            //     $file = $table_id.'.json';
            //     $path = $this->generate_removing_file_path($file,$warehouse_id);
                
            //     if(file_exists($path))
            //     {
            //         $diff_files[] = $file; // format takeaway_1234.json
            //     }
                
            // }else{
            //     if (file_exists($this->_bill_data_path_deleted.'/'.$warehouse_id) && $handle = opendir( $this->_bill_data_path_deleted.'/'.$warehouse_id)) {
    
            //         while (false !== ($entry = readdir($handle))) {
    
            //             if ($entry != "." && $entry != "..") {
    
            //                 if(strpos($entry,'.json') > 0)
            //                 {
                               
            //                     $deleted_files[] = str_replace('removing_','',$entry);
                                
            //                 }
            //             }
            //         }
            //         closedir($handle);
            //     }
            //     if ($handle = opendir( $this->_bill_data_path)) {
        
            //         while (false !== ($entry = readdir($handle))) {
    
            //             if ($entry != "." && $entry != "..") {
    
            //                 if(strpos($entry,'.json') > 0)
            //                 {
                               
            //                     $takeaway_files[] = $entry;
                                
            //                 }
            //             }
            //         }
            //         closedir($handle);
            //     }
                
            //     $diff_files = array_diff($deleted_files,$takeaway_files);
            // }
            
            
            // foreach($diff_files as $file)
            // {
            //     $path = $this->generate_removing_file_path($file,$warehouse_id);
                
            //     unlink($path);
            // }
        }

        public function getLastTakeawayNumber($register_id,$warehouse_id){
            $result_number = 0;
            $takeaways = $this->takeaways($warehouse_id);
            if($takeaways && !empty($takeaways))
            {
                return max($takeaways);
            }
            return $result_number;
        }
        public function getTakeawayNumber($register_id,$warehouse_id = 0){
            $takeaway_number = 1 * ($register_id . '0000');
            $last_number = $this->getLastTakeawayNumber($register_id,$warehouse_id);
            if($last_number > $register_id)
            {
                $takeaway_number = $last_number;
            }
            return ($takeaway_number + 1);
        }
        public function getDeskType($table_data)
        {
            $desk = isset($table_data['desk']) ? $table_data['desk'] : array();
            $desk_type = isset($desk['type']) ? $desk['type'] : '';
            return $desk_type;
        }
        public function isTakeaway($table_data)
        {
            $result = false;
            
            if($desk_type = $this->getDeskType($table_data))
            {
                if(strpos($desk_type,'takeaway') !== false )
                {
                    $result = true;
                }
            }
            return $result;
        }
        
        
        public function op_add_order_after($order,$order_parse_data){
            $source_type = isset($order_parse_data['source_type']) ? $order_parse_data['source_type'] : '';
            if($source_type == 'desk'){
                $desk = isset($order_parse_data['source']) ? $order_parse_data['source'] : array();
                if(!empty($desk))
                {
                    $clear_desk = isset($desk['clear_desk']) && $desk['clear_desk'] == 'no' ? false : true;
                    
                    $deks_id = isset($desk['id']) ? $desk['id'] : 0;
                    if($deks_id )
                    {
                        
                        $current_desk = $this->get_data($deks_id);
                       
                        $desk_items = isset($current_desk['items']) ? $current_desk['items'] : array();
                       
                        if(!empty($desk_items))
                        {
                            $desk_state = isset($desk['desk_state']) ? $desk['desk_state'] : array();
                            $items = isset($desk_state['items']) ? $desk_state['items'] : array();
                            if( !empty($items )){
                                
                                $item_ids = array();
                                $paid_items = array();
                                $paid_total = 0;
                                $ignore_items = isset($desk['ignore_items']) ? $desk['ignore_items'] : array();
                                foreach($desk_items as $item)
                                {
                                    $item_ids[] = $item['id'];
                                    $product_id = isset($item['product_id']) ? $item['product_id'] : 0;
                                    if(!in_array($item['id'],$ignore_items))
                                    {
                                        $item['item_type'] = 'pos_item_order';
                                        $paid_total += $item['total_incl_tax'];
                                    }
                                    $paid_items[] = $item;
                                    $transient_key = 'table_stock_'.$deks_id.'_'.$product_id;
                                    $this->_session->delete_transient($transient_key);
                                }
                                if(empty($ignore_items))
                                {
                                    if($clear_desk)
                                    {
                                        //reset table data
                                        $current_desk['child_desks'] = array();
                                        $current_desk['items'] = array();
                                        $current_desk['seller'] = array();
                                        $current_desk['customer'] = array();
                                        $current_desk['messages'] = array();
                                        $current_desk['note'] = '';
                                        $current_desk['source'] = '';
                                        $current_desk['source_type'] = '';
                                        $current_desk['source_details'] = '';
                                        $current_desk['serverd_qty'] = 0;
                                        $current_desk['total_qty'] = 0;
                                        $current_desk['paid_total'] = 0;
                                        $current_desk['start_time'] = 0;
                                        $current_desk['seat'] = 0;
                                    }else{
                                        $current_desk['items'] = $paid_items;
                                        $current_desk['paid_total'] = $paid_total;
                                    }
                                    
                                    $current_desk['ver'] += 10;
                                    $current_desk['system_ver'] += 10;
                                    $this->update_table_bill_screen($deks_id,$current_desk);
                                    
                                    
                                }
                            }
                        }
                        
                    }
                }
            }

        }
        public function op_upload_desk_after($_tables,$op_table,$tables,$_old_tables,$session_data){
            $old_items = array();
            $old_item_ids = array();
            $has_update = [];
            $warehouse_id = isset($session_data['login_warehouse_id']) ? $session_data['login_warehouse_id'] : 0;
            foreach($_old_tables as $table){
                if(!isset($table['id']))
                {
                    continue;
                }
                $table_id = $table['id'];
                $items = isset($table['items']) ? $table['items'] : array();
                if(!empty($items))
                {
                    foreach($items as $item)
                    {
                        $product_id = isset($item['product_id']) ? $item['product_id'] : 0;
                        $item_type = $item['item_type'];
                        if($product_id && $item_type != 'pos_item_order')
                        {
                            $old_item_ids[] = $item['id'];
                            $old_items[$item['id']] = array('table_id' => $table_id,'product_id' => $product_id, 'qty' => $item['qty'] );
                        }
                    }
                }
            }
            $new_item_ids = array();
            foreach($_tables as $table)
            {
                $table_id = $table['id'];
                $items = $table['items'];
                foreach($items as $item)
                {
                    $product_id = $item['product_id'];
                    $item_type = $item['item_type'];
                    $item_id = $item['id'];
                    $qty = $item['qty'];
                    $item_update = false;
                    if($item_type != 'pos_item_order')
                    {
                        $new_item_ids[] = $item_id;
                        $transient_key = 'table_stock_'.$table_id.'_'.$product_id;
                        $current_qty = $this->_session->get_transient($transient_key);
                        if($current_qty === false)
                        {
                            $current_qty = 0;
                            $item_update = true;
                        }else{
                            if(isset($old_items[$item_id]))
                            {
                                if($old_items[$item_id]['qty'] != $qty)
                                {
                                    $item_update = true;
                                    $current_qty -= $old_items[$item_id]['qty'];
                                }
                            }else{
                                $item_update = true;
                               
                            }
                        }
                        if($item_update)
                        {
                            $current_qty += $qty;
                            $this->_session->set_transient ($transient_key, $current_qty, HOUR_IN_SECONDS);
                            $has_update[] = $product_id;
                        }
                    }
    
                }
            }
            //get different old item id and new item id ;
            $diff_item_ids = array_diff($old_item_ids,$new_item_ids);
            if(!empty($diff_item_ids))
            {
               foreach($diff_item_ids as $item_id)
               {
                    if(isset($old_items[$item_id]))
                    {
                        $table_id = $old_items[$item_id]['table_id'];
                        $product_id = $old_items[$item_id]['product_id'];
                        $qty = $old_items[$item_id]['qty'];
                        $transient_key = 'table_stock_'.$table_id.'_'.$product_id;
                        $current_qty = $this->_session->get_transient($transient_key);
                        if($current_qty === false)
                        {
                            $current_qty = 0;
                        }
                        $current_qty -= $qty;
                        if($current_qty > 0)
                        {
                            $this->_session->set_transient ($transient_key, $current_qty, HOUR_IN_SECONDS);
                        }else{
                            $this->_session->delete_transient( $transient_key );
                        }
                        $has_update[] = $product_id;
                    }
               }
            }
            if(!empty($has_update))
            {
                $has_update = array_unique($has_update);
                foreach($has_update as $product_id)
                {
                    //increase version of product
                    $this->_core->addProductChange($product_id,$warehouse_id);
                }
            }
        }

        public function op_product_data($result,$_product,$warehouse_id){
            $tables = $this->tables($warehouse_id);
            $takeaway_tables = $this->takeawayTables($warehouse_id);
            //get pending / processing order from tables , takeaways
            $product_id = $result['id'];
            foreach($tables as $table)
            {
                $table_id = $table['id'];
                $transient_key = 'table_stock_'.$table_id.'_'.$product_id;
                $current_qty = $this->_session->get_transient($transient_key);
                if($current_qty === false)
                {
                    $current_qty = 0;
                    
                }
                $result['qty'] -= $current_qty;
            }
            foreach($takeaway_tables as $table)
            {
                $table_id = $table['id'];
                $table_id = $table['id'];
                $transient_key = 'table_stock_'.$table_id.'_'.$product_id;
                $current_qty = $this->_session->get_transient($transient_key);
                if($current_qty === false)
                {
                    $current_qty = 0;
                    
                }
                $result['qty'] -= $current_qty;
            }
            return $result;
        }
        public function get_data($table_id,$type = 'dine_in',$outlet_id = 0){
            $table_key = $table_id;
            if($type != 'dine_in')
            {
                $table_key = $type.'-'.$table_id;
            }
            
            $cache_group = $this->_cache_group;
            $cache_key = 'op_table_'.$outlet_id.'_'.$table_key;
            $cached_data = wp_cache_get( $cache_key, $cache_group );
            if ( false !== $cached_data  )
            {
                $result = $cached_data;
            }else{
                $data = $this->_bill_screen_data($table_id,$type);
                wp_cache_set( $cache_key, $data, $this->_cache_group );
                $result = $data;
            }
            return apply_filters('op_table_data',$result,$table_id,$type,$outlet_id,$this);
            
        }
        public function update_data($data = array(),$table_id = 0,$type = 'dine_in',$outlet_id = 0){
            $table_key = $table_id;
            if($type != 'dine_in')
            {
                $table_key = $type.'-'.$table_id;
            }
           
            $cache_group = $this->_cache_group;
            $cache_key = 'op_table_'.$outlet_id.'_'.$table_key;

            $file_path = $this->bill_screen_file_path($table_key);
            $file_mode = $this->get_file_mode();
            
            if(file_exists($file_path))
            {
                if ( defined( 'FS_CHMOD_FILE' ) ) {
                    $this->_filesystem->put_contents(
                        $file_path,
                        json_encode($data)
                    );
                }else{
                    $this->_filesystem->put_contents(
                        $file_path,
                        json_encode($data),
                        $file_mode
                    );
                }
                
            }else{
                
                $this->_filesystem->put_contents(
                    $file_path,
                    json_encode($data),
                    $file_mode // predefined mode settings for WP files
                );
            }
            if($type == 'takeaway')
            {
                $this->add_takeaway($table_id,$outlet_id);
            }
            if($type == 'guest_takeaway')
            {
                $this->add_takeaway($table_id,$outlet_id,true);
            }  
            wp_cache_delete($cache_key, $cache_group );
            do_action('op_update_table_data_after',$data,$table_id,$type,$outlet_id,$this);
        }
        
        public function remove_data($table_id,$type = 'dine_in',$outlet_id = 0){
            $table_key = $table_id;
            if($type != 'dine_in')
            {
                $table_key = $type.'-'.$table_id;
            }
            $cache_group = $this->_cache_group;
            $cache_key = 'op_table_'.$outlet_id.'_'.$table_key;

            $table_data = $this->get_data($table_id,$type);

            $transient_key = 'op_table_deleted_'.$outlet_id.'_'.$type.'_'.$table_id;
            $this->_session->set_transient ($transient_key, $table_data, WEEK_IN_SECONDS);

            $file_path = $this->bill_screen_file_path($table_key);
            if(file_exists($file_path))
            {
                unlink($file_path);
            }
            wp_cache_delete($cache_key, $cache_group );
            do_action('op_remove_table_data_after',$table_id,$type,$outlet_id,$this);
        }
        public function is_deleted($table_id,$type='dine_in',$outlet_id=0){
            $transient_key = 'op_table_deleted_'.$outlet_id.'_'.$type.'_'.$table_id;
            $transient_value = $this->_session->get_transient($transient_key);
            return ($transient_value !== false);
        }
        //save takeaway and guest takeaway list to transient
        public function add_takeaway($takeaway_id,$outlet_id,$is_guest = false){
            $result = array();
            $transient_key = 'op_all_takeaway_'.$outlet_id;
            if($is_guest)
            {
                $transient_key = 'op_all_guesttakeaway_'.$outlet_id;
            }
            $transient_value = $this->_session->get_transient($transient_key);
            if($transient_value !== false)
            {
                $result = $transient_value;
            }
            if(!in_array($takeaway_id,$result))
            {
                $result[] = $takeaway_id;
            }
            $this->_session->set_transient ($transient_key, $result, WEEK_IN_SECONDS);
            return $result;
        }
        public function remove_all_takeaway($outlet_id,$is_guest = false){
            $transient_key = 'op_all_takeaway_'.$outlet_id;
            if($is_guest)
            {
                $transient_key = 'op_all_guesttakeaway_'.$outlet_id;
            }
            $this->_session->delete_transient( $transient_key );
        }
        public function remove_takeaway($takeaway_id,$outlet_id,$is_guest = false){
            $result = array();
            $transient_key = 'op_all_takeaway_'.$outlet_id;
            if($is_guest)
            {
                $transient_key = 'op_all_guesttakeaway_'.$outlet_id;
            }
            $transient_value = $this->_session->get_transient($transient_key);
            if($transient_value !== false)
            {
                $result = $transient_value;
            }
            if(in_array($takeaway_id,$result))
            {
                $key = array_search($takeaway_id,$result);
                unset($result[$key]);
                $result = array_values($result);
            }
            $this->_session->set_transient ($transient_key, $result, WEEK_IN_SECONDS);
            return $result;
        }
        public function takeaways($outlet_id){
            $result = array();
            $transient_key = 'op_all_takeaway_'.$outlet_id;
            $transient_value = $this->_session->get_transient($transient_key);
            if($transient_value !== false)
            {
                $result = $transient_value;
            }
            return $result;
        }
        public function guest_takeaways($outlet_id){
            $result = array();
            $transient_key = 'op_all_guesttakeaway_'.$outlet_id;
            $transient_value = $this->_session->get_transient($transient_key);
            if($transient_value !== false)
            {
                $result = $transient_value;
            }
            return $result;
        }
    }
}
?>