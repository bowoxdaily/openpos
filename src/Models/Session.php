<?php
namespace Op\Models;

use WP_Filesystem_Direct;
if ( ! defined( 'ABSPATH' ) ) exit;
class Session{
    public $_base_path;
    public $_session_path;
    public $_file_system;
    public function __construct($_base_path = '')
    {
        if(!$_base_path)
        {
            $upload_dir   = wp_upload_dir();
            $_base_path =  $upload_dir['basedir'].'/openpos';
        }
        $this->_base_path = $_base_path;
        $this->_file_system = new WP_Filesystem_Direct(false);
        $this->_session_path =  $this->_base_path.'/sessions';
    }
    function generate_session_id($prefix = ''){
        if(session_id() == '') {
            session_start();
        }
        $session_id = 'op-'.time().'-'.session_id();
        if($prefix)
        {
            $session_id = 'op-'.$prefix.'-'.time().'-'.session_id();
        }
        return apply_filters('op_session_id',sanitize_title($session_id));
    }
    public function get_session($session_id)
    {
        $session_file = $this->_session_path.'/'.$session_id;
        
        if($this->_file_system->exists($session_file))
        {
            
            $session = json_decode($this->_file_system->get_contents($session_file), true);
            
            return $session;
        }
        return false;
    }
    public function set_session($session_id, $data)
    {
        $session_file = $this->_session_path.'/'.$session_id;
        if(!$this->_file_system->exists($this->_session_path))
        {
            $this->_file_system->mkdir($this->_session_path);
        }
        $this->_file_system->put_contents($session_file, json_encode($data));
    }
    public function delete_session($session_id)
    {
        $session_file = $this->_session_path.'/'.$session_id;
        if($this->_file_system->exists($session_file))
        {
            $this->_file_system->delete($session_file);
        }
    }
    public function get_session_list()
    {
        $session_files = $this->_file_system->dirlist($this->_session_path);
        $sessions = array();
        foreach($session_files as $session_file)
        {
            if($session_file['type'] == 'file' )
            {
                $sessions[] = $session_file['name'];
            }
        }
        return $sessions;
    }
   
}