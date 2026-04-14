<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if(!class_exists('Openpos_Setting'))
{
    class Openpos_Setting
    {
        public $OPENPOS_SETTING;
        public function __construct($OPENPOS_SETTING)
        {
            $this->OPENPOS_SETTING = $OPENPOS_SETTING;
            add_action('init', array($this, 'init'), 120);
        }
        function get_settings_sections() {
            $sections = array(
                array(
                    'id'    => 'openpos_general',
                    'title' => __( 'General', 'openpos' )
                ),
                array(
                    'id'    => 'openpos_payment',
                    'title' => __( 'Payment', 'openpos' )
                ),
                array(
                    'id'    => 'openpos_shipment',
                    'title' => __( 'Shipping', 'openpos' )
                ),
                array(
                    'id'    => 'openpos_label',
                    'title' => __( 'Barcode Label', 'openpos' )
                ),
                array(
                    'id'    => 'openpos_receipt',
                    'title' => __( 'Receipt', 'openpos' )
                ),
                array(
                    'id'    => 'openpos_pos',
                    'title' => __( 'POS Layout', 'openpos' )
                )
            );
            
            $sections[] = array(
                'id'    => 'openpos_addon',
                'title' => __( 'Add-on', 'openpos' )
            );
        
            return $sections;
        }
    
        function init()
        {
            $this->OPENPOS_SETTING->set_sections( $this->get_settings_sections() );
        }
    }
    
}
