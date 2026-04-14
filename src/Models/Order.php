<?php
namespace Op\Models;

class Order {
    public $id;
    public $session;
    public $order_id;
    public $order_number;
    public $order_number_format;
    public $order_number_details = [];
    public $cart_number;
    public $register = [];
    public $table = null;
    public $title;
    public $items = [];
    public $fee_items = [];
    public $sub_total;
    public $sub_total_incl_tax;
    public $tax_amount;
    public $customer = [];
    public $cart_rule_discount = [];
    public $discount_source;
    public $discount_amount;
    public $discount_final_amount;
    public $discount_type;
    public $final_items_discount_amount;
    public $final_discount_amount;
    public $final_discount_tax;
    public $discount_tax_amount;
    public $discount_excl_tax;
    public $grand_total;
    public $total_paid;
    public $discount_code;
    public $discount_codes = [];
    public $discount_code_amount;
    public $discount_code_tax_amount;
    public $discount_code_excl_tax;
    public $payment_method = [];
    public $shipping_information = [];
    public $shipping_cost;
    public $shipping_cost_excl_tax;
    public $shipping_tax;
    public $shipping_tax_details = [];
    public $sale_person;
    public $sale_person_name;
    public $note;
    public $pickup_time;
    public $created_at;
    public $state;
    public $order_state;
    public $online_payment;
    public $print_invoice;
    public $point_discount = [];
    public $add_discount;
    public $add_shipping;
    public $add_tax;
    public $custom_tax;
    public $custom_tax_rate;
    public $custom_tax_rates = [];
    public $tax_details = [];
    public $discount_tax_details = [];
    public $fee_tax_details = [];
    public $source = [];
    public $source_type;
    public $current_screen;
    public $available_shipping_methods = [];
    public $mode;
    public $is_takeaway;
    public $sync_status;
    public $addition_information = [];
    public $total_items;
    public $total_qty;
    public $total_discount;
    public $total_fee;
    public $total_payment;
    public $update_time;
    public $posVersion;

    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            if(property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}