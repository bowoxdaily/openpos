<?php
defined( 'ABSPATH' ) || exit;
if(!class_exists('OP_Stock'))
{
    class OP_Stock{
        public function __construct()
        {
            add_action( 'openpos_daily_event', array($this, 'openpos_daily_event') );
        }
        public function registers(){

        }
        public function delete($id){

        }
        public function save(){

        }
        public function openpos_daily_event(){
            global $OPENPOS_CORE;
            global $wpdb;
            $current_date_from =  date('Y-m-d'); 
            $current_time_zone_from = strtotime($current_date_from);
            $current_time_zone_to = strtotime($current_date_from) + 86400;
            
            
            $meta_key = '_sale_price_dates_from';
            $sql = $wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE meta_key = '".$meta_key."' AND (meta_value <> '' AND meta_value >".($current_time_zone_from - 100)." AND meta_value < ".($current_time_zone_to + 86400).") ORDER BY meta_value ASC ");
            $rows = $wpdb->get_results(  $sql, ARRAY_A);
            foreach($rows as $row)
            {
                $product_id = $row['post_id'];
                $warehouse_id = 0;
                $OPENPOS_CORE->addProductChange($product_id,$warehouse_id);
            }
            $meta_key = '_sale_price_dates_to';
            $sql = $wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE meta_key = '".$meta_key."' AND (meta_value <> '' AND meta_value >".($current_time_zone_from - 86400)." AND meta_value < ".($current_time_zone_to + 100).") ORDER BY meta_value ASC ");
            $rows = $wpdb->get_results(  $sql, ARRAY_A);
            foreach($rows as $row)
            {
                $product_id = $row['post_id'];
                $warehouse_id = 0;
                $OPENPOS_CORE->addProductChange($product_id,$warehouse_id);
            }
            
           
            //$date_on_sale_from = date( 'Y-m-d 00:00:00', strtotime( $date_on_sale_from ) ); 
            //_sale_price_dates_from
            //_sale_price_dates_to
            // delete the duplicate order items

            $sql = $wpdb->prepare( "
                SELECT COUNT(*) c,meta_value,MAX(order_item_id) as max_order_item_id,  GROUP_CONCAT(order_item_id SEPARATOR ',') AS item_ids
                FROM {$wpdb->prefix}woocommerce_order_itemmeta
                WHERE meta_key = '_op_local_id' GROUP BY meta_value HAVING c > %d
            ", 1 );
            $rows = $wpdb->get_results(  $sql, ARRAY_A);
            foreach($rows as $row)
            {
                $max_item_id = $row['max_order_item_id'];
                $items_ids = explode(',',$row['item_ids']);
                $_items_ids = array_diff($items_ids, array($max_item_id));

                //delete order item
                foreach($_items_ids as $item_id)
                {
                    wc_delete_order_item( $item_id );
                }
                // delete order item meta
            }

        }
    }
}
?>