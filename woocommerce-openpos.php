<?php
/*
Plugin Name: Woocommerce OpenPos
Plugin URI: http://wpos.app
Description: Quick POS system for woocommerce.
Author: anhvnit@gmail.com
Author URI: http://wpos.app/
Version: 8.3.0
Requires Plugins: woocommerce
WC requires at least: 3.0
WC tested up to: 10.2.2
Text Domain: openpos
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/
if ( ! defined( 'ABSPATH' ) ) exit;
define('OPENPOS_DIR',plugin_dir_path(__FILE__));
define('OPENPOS_URL',plugins_url('woocommerce-openpos'));

global $OPENPOS_SETTING;
global $OPENPOS_CORE;

if(!function_exists('is_plugin_active'))
{
    include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}

require(OPENPOS_DIR.'vendor/autoload.php');


require_once( OPENPOS_DIR.'lib/class-op-update.php' );
require_once( OPENPOS_DIR.'lib/class-op-setting.php' );
require_once( OPENPOS_DIR.'lib/abtract-op-app.php' );
require_once( OPENPOS_DIR.'lib/op-payment.php' );

require_once( OPENPOS_DIR.'lib/class-op-woo.php' );
require_once( OPENPOS_DIR.'lib/class-op-woo-cart.php' );
require_once( OPENPOS_DIR.'lib/class-op-woo-order.php' );
require_once( OPENPOS_DIR.'lib/class-op-session.php' );
require_once( OPENPOS_DIR.'lib/class-op-receipt.php' );
require_once( OPENPOS_DIR.'includes/api/LoginSecurity.php' );
require_once( OPENPOS_DIR.'includes/api/SalonReceiptHelper.php' );
require_once( OPENPOS_DIR.'lib/class-op-register.php' );
require_once( OPENPOS_DIR.'lib/class-op-table.php' );
require_once( OPENPOS_DIR.'lib/class-op-warehouse.php' );
require_once( OPENPOS_DIR.'lib/class-op-transaction.php' );
require_once( OPENPOS_DIR.'lib/class-op-stock.php' );
require_once( OPENPOS_DIR.'lib/class-op-exchange.php' ); 
require_once( OPENPOS_DIR.'lib/class-op-report.php' ); 
require_once( OPENPOS_DIR.'lib/class-op-addon.php' ); 


//app
require_once( OPENPOS_DIR.'lib/app/class-op-help.php' ); 



require_once( OPENPOS_DIR.'includes/Core.php' );
require_once( OPENPOS_DIR.'includes/Setting.php' );



require_once( OPENPOS_DIR.'includes/admin/Admin.php' );


global $op_session;
global $op_warehouse;
global $op_register;
global $op_table;
global $op_stock;
global $op_woo;
global $op_transaction;
global $op_woo_cart;
global $op_woo_order;
global $op_exchange;
global $op_report;
global $op_receipt;
global $op_admin;
global $op_front;

global $op_addon;


//check woocommerce active
if(is_plugin_active( 'woocommerce/woocommerce.php' ))
{

    
    $op_session = new OP_Session();
    $op_woo = new OP_Woo();
    $op_woo->init();
    $op_receipt = new OP_Receipt();
    $op_woo_cart = new OP_Woo_Cart();
    $op_woo_order = new OP_Woo_Order();
    $op_warehouse = new OP_Warehouse();
    $op_register = new OP_Register();
    $op_table = new OP_Table();
    $op_stock = new OP_Stock();
    $op_exchange = new OP_Exchange();
    $op_report = new OP_Report();
    $op_transaction = new OP_Transaction();
    $OPENPOS_SETTING = new OP_Settings();

    


    $OPENPOS_CORE = new Openpos_Core();
    $OPENPOS_CORE->init();

    $openpos_setting = new Openpos_Setting($OPENPOS_SETTING);
       
   
    

    $op_admin = new Openpos_Admin($OPENPOS_SETTING,$OPENPOS_CORE);
    $op_admin->init();

    $op_addon = new OP_Addon();

    if(!class_exists('Openpos_Front'))
    {
        $path_wc_discount = dirname(OPENPOS_DIR).'/woocommerce/includes/class-wc-discounts.php';
        if(!class_exists('WC_Discounts') && file_exists($path_wc_discount))
        {
            require( dirname(OPENPOS_DIR).'/woocommerce/includes/class-wc-discounts.php' );
        }
        if(class_exists('WC_Discounts') )
        {
            require( OPENPOS_DIR.'lib/class-op-discounts.php' );
        }
        

        require_once( OPENPOS_DIR.'includes/front/Front.php' );
    }
   
    $op_front = new Openpos_Front($OPENPOS_SETTING,$OPENPOS_CORE);
    $op_front->setSession($op_session);

    $op_front->initScripts();

    
    
    //register action on active plugin
    if(!function_exists('openpos_activate'))
    {
        function openpos_activate() {
            if ( is_plugin_active( plugin_basename( 'openpos/openpos.php' ) ) ) {
                wp_die( __( 'Seem you are using OpenPOS Lite Version - Free. Please delete it before intsall this Paid version.', 'openpos' ) );
            }
            if ( !is_plugin_active( plugin_basename( 'woocommerce/woocommerce.php' ) ) ) {
                wp_die( __( 'Seem you are forgot install woocommerce plugin. Please install woocommerce plugin before install OpenPOS', 'openpos' ) );
            }
            update_option('_openpos_product_version_0',time());
            // Activation code here...
            flush_rewrite_rules();
        }
    }
    if(!function_exists('openpos_deactivation'))
    {
        function openpos_deactivation() {
            // unregister schedule
            wp_clear_scheduled_hook( 'openpos_daily_event' );
            flush_rewrite_rules();
        }
    }

    register_activation_hook( __FILE__, 'openpos_activate' );
    register_deactivation_hook( __FILE__, 'openpos_deactivation' );

    
    
    add_action(
        'before_woocommerce_init',
        function() {
            if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', 'woocommerce-openpos/woocommerce-openpos.php' );
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'analytics', 'woocommerce-openpos/woocommerce-openpos.php' );
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'new_navigation', 'woocommerce-openpos/woocommerce-openpos.php' );
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_block_editor', 'woocommerce-openpos/woocommerce-openpos.php' );
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'woocommerce_custom_orders_table_enabled', 'woocommerce-openpos/woocommerce-openpos.php' );
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'woocommerce_custom_orders_table_data_sync_enabled', 'woocommerce-openpos/woocommerce-openpos.php' );
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', 'woocommerce-openpos/woocommerce-openpos.php' );
            }
        }
    );
    
    

    require_once( OPENPOS_DIR.'lib/class-op-integration.php' );

    OP_Update::init(__FILE__,'22613341');
}
