<?php
/**
 * Created by PhpStorm.
 * User: anhvnit
 * Date: 3/19/19
 * Time: 23:36
 */
defined( 'ABSPATH' ) || exit;
require_once "interface-op-app.php";
abstract class OP_App_Abstract  {
    public $key;
    public $name;
    public $thumb; // 256x256
    public $session;
    public $url = '';
    public function __construct(){
        add_filter('allowed_http_origins', array($this, 'add_allowed_origins'));
        add_filter('rest_pre_serve_request', array($this, 'rest_send_cors_headers'));

        
    }
    public function add_allowed_origins($origins) {
        // Add your specific origins or allow all
        $pos_action = $_REQUEST['pos_action'] ?? '';
        if($pos_action == 'app_view')
        {
            $origins[] = '*'; 
        }
        return $origins;
    }
    public function rest_send_cors_headers($served) {
        $origin = '*';
        // Send headers
        header("Access-Control-Allow-Origin: " . $origin);
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With");
        header("Access-Control-Allow-Credentials: true");
        
        return $served;
    }
    public function get_key(){
        return $this->key;
    }
    public function get_name(){
        return $this->name;
    }
    public function get_thumb(){
        return $this->thumb;
    }
    public function set_key($key){
        $this->key = $key;
    }
    public function set_name($name){
        $this->name = $name;
    }
    public function set_thumb($url){
        $this->thumb = $url;
    }
    public function set_session($session)
    {
        $this->session = $session;
    }
    public function get_session(){
        return $this->session;
    }
    public function get_url(){
        return $this->url;
    }
    public function render(){
    }
}