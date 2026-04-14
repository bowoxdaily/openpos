<?php
namespace Op\Models;

class Customer {
    public $id;
    public $avatar;
    public $name;
    public $firstname;
    public $lastname;
    public $address;
    public $address_2;
    public $state;
    public $city;
    public $country;
    public $postcode;
    public $phone;
    public $email;
    public $addition_fields = [];
    public $billing_address = [];
    public $shipping_address = [];
    public $group_id;
    public $point;
    public $discount;
    public $badge;
    public $summary_html;
    public $auto_add;
    public $company;
    public $result_html;

    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            if(property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
    public function get_data() {
        $data = [];
        foreach ($this as $key => $value) {
            if(property_exists($this, $key)) {
                $data[$key] = $value;
            }
        }
        return $data;
    }
    public function get_id() {
        return $this->id;
    }
    public function get_name() {
        return $this->name;
    }
    public function get_avatar() {
        return $this->avatar;
    }
    public function get_firstname() {
        return $this->firstname;
    }
    public function get_lastname() {
        return $this->lastname;
    }
    public function get_address() {
        return $this->address;
    }
    public function get_address_2() {
        return $this->address_2;
    }
    public function get_state() {
        return $this->state;
    }
    public function get_city() {
        return $this->city;
    }
    public function get_country() {
        return $this->country;
    }
    public function get_postcode() {
        return $this->postcode;
    }
    public function get_phone() {
        return $this->phone;
    }
    public function get_email() {
        return $this->email;
    }
    public function get_addition_fields() {
        return $this->addition_fields;
    }
    public function get_billing_address() {
        return $this->billing_address;
    }
    public function get_shipping_address() {
        return $this->shipping_address;
    }
    public function get_group_id() {
        return $this->group_id;
    }
    public function get_point() {
        return $this->point;
    }
    public function get_discount() {
        return $this->discount;
    }
    public function get_badge() {
        return $this->badge;
    }
    public function get_summary_html() {
        return $this->summary_html;
    }
    public function get_auto_add() {
        return $this->auto_add;
    }
    public function get_company() {
        return $this->company;
    }
    public function get_result_html() {
        return $this->result_html;
    }
    public function set_id($id) {
        $this->id = $id;
    }
    public function set_name($name) {
        $this->name = $name;
    }
    public function set_avatar($avatar) {
        $this->avatar = $avatar;
    }
    public function set_firstname($firstname) {
        $this->firstname = $firstname;
    }
    public function set_lastname($lastname) {
        $this->lastname = $lastname;
    }
    public function set_address($address) {
        $this->address = $address;
    }
    public function set_address_2($address_2) {
        $this->address_2 = $address_2;
    }
    public function set_state($state) {
        $this->state = $state;
    }
    public function set_city($city) {
        $this->city = $city;
    }
    public function set_country($country) {
        $this->country = $country;
    }
    public function set_postcode($postcode) {
        $this->postcode = $postcode;
    }
    public function set_phone($phone) {
        $this->phone = $phone;
    }
    public function set_email($email) {
        $this->email = $email;
    }
    public function set_addition_fields($addition_fields) {
        $this->addition_fields = $addition_fields;
    }
    public function set_billing_address($billing_address) {
        $this->billing_address = $billing_address;
    }
    public function set_shipping_address($shipping_address) {
        $this->shipping_address = $shipping_address;
    }
    public function set_group_id($group_id) {
        $this->group_id = $group_id;
    }
    public function set_point($point) {
        $this->point = $point;
    }
    public function set_discount($discount) {
        $this->discount = $discount;
    }
    public function set_badge($badge) {
        $this->badge = $badge;
    }
    public function set_summary_html($summary_html) {
        $this->summary_html = $summary_html;
    }
    public function set_auto_add($auto_add) {
        $this->auto_add = $auto_add;
    }
    public function set_company($company) {
        $this->company = $company;
    }
    public function set_result_html($result_html) {
        $this->result_html = $result_html;
    }
    public function to_array() {
        return $this->get_data();
    }
    public function to_json() {
        return json_encode($this->get_data());
    }
    public function __toString() {
        return $this->to_json();
    }
    public function __get($name) {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        return null;
    }
    public function __set($name, $value) {
        if (property_exists($this, $name)) {
            $this->$name = $value;
        }
    }
   
}