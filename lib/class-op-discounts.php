<?php
/**
 * Discount calculation
 *
 * @package WooCommerce/Classes
 * @since   3.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Discounts class.
 */
if(!class_exists('OP_Discounts'))
{
    class OP_Discounts extends  WC_Discounts {

        /**
         * Reference to cart or order object.
         *
         * @since 3.2.0
         * @var array
         */
        protected $object;

        /**
         * An array of items to discount.
         *
         * @var array
         */
        protected $items = array();

        /**
         * An array of discounts which have been applied to items.
         *
         * @var array[] Code => Item Key => Value
         */
        protected $discounts = array();

        /**
         * Constructor.
         *
         * @param array $object Cart or order object.
         */
        public function __construct( $object = array() ) {
            parent::__construct($object);
        }


        protected function get_items_to_apply_coupon( $coupon ) {
            $items_to_apply = array();

            foreach ( $this->get_items_to_validate() as $item ) {
                $item_to_apply = clone $item; // Clone the item so changes to this item do not affect the originals.

                if ( 0 === $this->get_discounted_price_in_cents( $item_to_apply ) || 0 >= $item_to_apply->quantity ) {
                    continue;
                }

                if ( ! $coupon->is_valid_for_product( $item_to_apply->product, $item_to_apply->object ) && ! $coupon->is_valid_for_cart() ) {
                    continue;
                }

                $items_to_apply[] = $item_to_apply;
            }
            return $items_to_apply;
        }
        /**
         * Apply a discount to all items using a coupon.
         *
         * @since  3.2.0
         * @param  WC_Coupon $coupon Coupon object being applied to the items.
         * @param  bool      $validate Set to false to skip coupon validation.
         * @return bool|WP_Error True if applied or WP_Error instance in failure.
         */
        public function apply_coupon( $coupon, $validate = true ) {
            if ( ! is_a( $coupon, 'WC_Coupon' ) ) {
                return new WP_Error( 'invalid_coupon', __( 'Invalid coupon', 'openpos' ) );
            }

        
            if ( ! isset( $this->discounts[ $coupon->get_code() ] ) ) {
                $this->discounts[ $coupon->get_code() ] = array_fill_keys( array_keys( $this->items ), 0 );
            }

            $items_to_apply = $this->get_items_to_apply_coupon( $coupon );


            // Core discounts are handled here as of 3.2.
            switch ( $coupon->get_discount_type() ) {
                case 'percent':
                    $amount = $this->apply_coupon_percent( $coupon, $items_to_apply );
                    break;
                case 'fixed_product':
                    $amount = $this->apply_coupon_fixed_product( $coupon, $items_to_apply );
                    break;
                case 'fixed_cart':
                    $amount = $this->apply_coupon_fixed_cart( $coupon, $items_to_apply );
                    break;
                default:
                    $amount = $this->apply_coupon_custom( $coupon, $items_to_apply );
                    break;
            }
            
            return  apply_filters('op_check_coupon_apply_coupon',$amount,$coupon,$validate,$this);

        }
        public function is_coupon_emails_allowed( $check_emails, $restrictions ) {

            foreach ( $check_emails as $check_email ) {
                // With a direct match we return true.
                if ( in_array( $check_email, $restrictions, true ) ) {
                    return true;
                }
    
                // Go through the allowed emails and return true if the email matches a wildcard.
                foreach ( $restrictions as $restriction ) {
                    // Convert to PHP-regex syntax.
                    $regex = '/^' . str_replace( '*', '(.+)?', $restriction ) . '$/';
                    preg_match( $regex, $check_email, $match );
                    if ( ! empty( $match ) ) {
                        return true;
                    }
                }
            }
    
            // No matches, this one isn't allowed.
            return false;
        }
        public function is_coupon_valid( $coupon,$request = array() ) {
            global $op_session_data;
            try{
                $validate = true;// parent::is_coupon_valid($coupon);
                $this->validate_coupon_exists( $coupon );
                $this->validate_coupon_usage_limit( $coupon );
                //$this->validate_coupon_user_usage_limit( $coupon );
                $this->validate_coupon_expiry_date( $coupon );
                $this->validate_coupon_minimum_amount( $coupon );
                $this->validate_coupon_maximum_amount( $coupon );
                $this->validate_coupon_product_ids( $coupon );
                $this->validate_coupon_product_categories( $coupon );
                $this->validate_coupon_excluded_items( $coupon );
                $this->validate_coupon_eligible_items( $coupon );
                //$this->validate_coupon_allowed_emails( $coupon );
                
                $customer_email = get_post_meta( $coupon->get_id(), 'customer_email', true );
                $request_params = apply_filters('op_check_coupon_request',$_REQUEST);
                $cart_data = isset($request_params['cart']) ? json_decode(stripslashes($request_params['cart']),true) : array();
                if($customer_email)
                {
                    $customer_emails = (array) maybe_unserialize($customer_email);
                    $restrictions = $coupon->get_email_restrictions();
                    
                    if(!empty($customer_emails))
                    {
    
                        if(!empty($cart_data) && $op_session_data)
                        {
                            if(isset($cart_data['customer']) && $cart_data['customer']['email'])
                            {
                                $check_emails = array( strtolower($cart_data['customer']['email']));
                                if(!$this->is_coupon_emails_allowed($check_emails,$restrictions))
                                {
                                    return new WP_Error(
                                        'invalid_coupon',
                                        __('This coupon not for current customer','openpos'),
                                        array(
                                            'status' => 400,
                                        )
                                    );
                                }

                                
                            }else{
                                return new WP_Error(
                                    'invalid_coupon',
                                    __('Please add customer before valid coupon','openpos'),
                                    array(
                                        'status' => 400,
                                    )
                                );
    
                            }
                        }
    
                    }
                }
               
                $usage_limit_per_user = $coupon->get_usage_limit_per_user();
                if($validate === true && $usage_limit_per_user > 0)
                {
                    if(!isset($cart_data['customer']))
                    {
                        return new WP_Error(
                            'invalid_coupon',
                            __('Please add customer before valid coupon','openpos'),
                            array(
                                'status' => 400,
                            )
                        );
                    }
                    $customer = $cart_data['customer'];
    
                    if(!isset($customer['id']) || !$customer['id'])
                    {
                        return new WP_Error(
                            'invalid_coupon',
                            __('Please add customer before valid coupon','openpos'),
                            array(
                                'status' => 400,
                            )
                        );
                    }
    
                    $validate = $this->validate_coupon_user_usage_limit($coupon,$customer['id']);
    
                }
                return $validate;
            }catch(Exception $e)
            {
                return new WP_Error(
                    'invalid_coupon',
                    $e->getMessage(),
                    array(
                        'status' => 400,
                    )
                );
            }


            return new WP_Error(
                'invalid_coupon',
                'Unknown',
                array(
                    'status' => 400,
                )
            );

           
        }


    }

}

