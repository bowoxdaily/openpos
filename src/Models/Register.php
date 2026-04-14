<?php
namespace Op\Models;
class Register{
    public $_base_path;
    public $_post_type = '_op_register';
    public $_cashiers_meta_key = '_op_cashiers';
    public $_warehouse_meta_key = '_op_warehouse';
    public $_payment_guide_meta_key = '_op_payment_guide';
    public function __construct($base_path = '')
    {
        if(!$base_path)
        {
            $upload_dir   = wp_upload_dir();
            $base_path =  $upload_dir['basedir'].'/openpos';
        }
        $this->_base_path = $base_path;
    }
    public function get_register($register_id)
    {
        $register = get_post($register_id);
        if($register)
        {
            $register->cashiers = get_post_meta($register_id, $this->_cashiers_meta_key, true);
            $register->warehouse = get_post_meta($register_id, $this->_warehouse_meta_key, true);
            $register->payment_guide = get_post_meta($register_id, $this->_payment_guide_meta_key, true);
            return $register;
        }
        return false;
    }
    public function get_registers($args = array())
    {
        $args = wp_parse_args($args, array(
            'post_type' => $this->_post_type,
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ));
        $registers = get_posts($args);
        if($registers)
        {
            foreach ($registers as $key => $register) {
                $registers[$key]->cashiers = get_post_meta($register->ID, $this->_cashiers_meta_key, true);
                $registers[$key]->warehouse = get_post_meta($register->ID, $this->_warehouse_meta_key, true);
                $registers[$key]->payment_guide = get_post_meta($register->ID, $this->_payment_guide_meta_key, true);
            }
        }
        return $registers;
    }
    public function create_register($data)
    {
        $register = array(
            'post_title' => $data['register_name'],
            'post_type' => $this->_post_type,
            'post_status' => 'publish',
        );
        $register_id = wp_insert_post($register);
        if($register_id)
        {
            update_post_meta($register_id, $this->_cashiers_meta_key, $data['cashiers']);
            update_post_meta($register_id, $this->_warehouse_meta_key, $data['warehouse']);
            update_post_meta($register_id, $this->_payment_guide_meta_key, $data['payment_guide']);
            return $register_id;
        }
        return false;
    }
    public function update_register($register_id, $data)
    {
        $register = array(
            'ID' => $register_id,
            'post_title' => $data['register_name'],
        );
        wp_update_post($register);
        update_post_meta($register_id, $this->_cashiers_meta_key, $data['cashiers']);
        update_post_meta($register_id, $this->_warehouse_meta_key, $data['warehouse']);
        update_post_meta($register_id, $this->_payment_guide_meta_key, $data['payment_guide']);
        return true;
    }
    public function delete_register($register_id)
    {
        wp_delete_post($register_id, true);
        return true;
    }
    public function get_register_by_warehouse($warehouse_id)
    {
        $args = array(
            'post_type' => $this->_post_type,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => $this->_warehouse_meta_key,
                    'value' => $warehouse_id,
                    'compare' => '='
                )
            )
        );
        $registers = get_posts($args);
        return $registers;
    }
    public function get_register_by_cashier($cashier_id)
    {
        $args = array(
            'post_type' => $this->_post_type,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => $this->_cashiers_meta_key,
                    'value' => $cashier_id,
                    'compare' => 'LIKE'
                )
            )
        );
        $registers = get_posts($args);
        return $registers;
    }
    
    
}