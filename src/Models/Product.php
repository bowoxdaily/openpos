<?php
namespace Op\Models;

class Product {
    public $id;
    public $item_parent_id;
    public $request_id;
    public $name;
    public $barcode;
    public $barcode_details = [];
    public $sub_name;
    public $dining;
    public $price;
    public $price_incl_tax;
    public $product_id;
    public $custom_price;
    public $final_price;
    public $final_price_incl_tax;
    public $final_price_source;
    public $batches;
    public $options = [];
    public $bundles = [];
    public $variations;
    public $rule_discount = [];
    public $discounts = [];
    public $discount_source;
    public $discount_amount;
    public $discount_type;
    public $final_discount_amount;
    public $final_discount_amount_incl_tax;
    public $qty;
    public $cart_qty;
    public $refund_qty;
    public $exchange_qty;
    public $refund_total;
    public $tax_amount;
    public $total_tax;
    public $total;
    public $total_incl_tax;
    public $product = [];
    public $parent_product;
    public $option_pass;
    public $option_total;
    public $option_total_tax;
    public $option_total_excl_tax;
    public $bundle_total;
    public $ship_cost;
    public $ship_tax;
    public $ship_total;
    public $ship_total_incl_tax;
    public $note;
    public $parent_id;
    public $seller_id;
    public $seller_name;
    public $item_type;
    public $has_custom_discount;
    public $has_price_change;
    public $has_custom_price_change;
    public $disable_qty_change;
    public $read_only;
    public $promotion_added;
    public $tax_details = [];
    public $custom_fields = [];
    public $is_exchange;
    public $update_time;
    public $order_time;
    public $source;
    public $state;

    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            if(property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}