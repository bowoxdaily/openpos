<?php
if(!class_exists('OP_REST_API_Extension'))
{
    class OP_REST_API_Extension extends OP_REST_API{
        public function register_routes() {
            
            register_rest_route( $this->namespace, '/extension/extensions', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'extensions'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
            register_rest_route( $this->namespace, '/extension/view', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this,'extension_view'),
                'permission_callback' => array($this,'permission_callback'),
                ) 
            );
        }
        public function extensions($request){
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
                $session = $this->_getSessionData();
                $classes = get_declared_classes();
                $extensions = array();
                foreach($classes as $klass) {
                    $reflect = new ReflectionClass($klass);
        
                    if($reflect->implementsInterface('OP_App'))
                    {
                       $tmp_class =  new $klass();
                       $app_key = $tmp_class->get_key();
                       if($app_key)
                       {
                           $url = $tmp_class->get_url();
                           if(!$url)
                           {
                             $path = sprintf('admin-ajax.php?action=openpos&pos_action=app_view&app=%s',$app_key);
                             $url = admin_url($path);
                           }
                           $tmp = array(
                               'key' => $app_key,
                               'name' => $tmp_class->get_name(),
                               'thumb' => $tmp_class->get_thumb(),
                               'object'   => $klass,
                               'app_url' => $url,
                           );
                           $extensions[] = $tmp;
                       }
        
                    }
                }
                $result['response']['data']= apply_filters('op_api_extensions',$extensions,$session);
                $result['response']['status'] = 1;
            }catch(Exception $e)
            {
                $result['code'] = 400;
                $result['response']['status'] = 0;
                $result['response']['message'] = $e->getMessage();
                
            }
            return $this->rest_ensure_response($result);
        }
        public function extension_view(){
            $app_key = isset($_REQUEST['app']) ?  esc_attr($_REQUEST['app']) : '';
            $session = $this->_getSessionData();
            $apps = $this->get_app_list();
    
            foreach($apps['data'] as $app)
            {
                if($app['key'] == $app_key)
                {
                    $obj = $app['object'];
                    $app_obj = new $obj;
                    $app_obj->set_session($session);
                    $app_obj->render();
                    exit;
                }
    
            }
        }
        private function _getSessionData(){
            return $this->session_data;
        }
    }
}