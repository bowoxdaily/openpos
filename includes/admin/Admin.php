<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * Created by PhpStorm.
 * User: anhvnit
 * Date: 7/26/16
 * Time: 23:32
 */
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;



class Openpos_Admin{
    private $settings_api;
    public $core;
    public $_filesystem;
    private $_session;
    private $_enable_hpos;
    public function __construct($OPENPOS_SETTING,$OPENPOS_CORE)
    {
       
        global $op_session;
        $this->_session = $op_session;
        $this->settings_api = $OPENPOS_SETTING;
        $this->core = $OPENPOS_CORE;
        
        if(!class_exists('WP_Filesystem_Direct'))
        {
            require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php');
            require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php');
        }
        $this->_filesystem = new WP_Filesystem_Direct(false);

    }

    public function init()
    {
        add_action('plugins_loaded', array($this,'plugins_loaded'));
        add_action( 'admin_notices',array($this, 'admin_notice') );
        add_action( 'admin_init', array($this, 'admin_init') );
        add_action( 'init', array($this, '_short_code') );
        add_action('admin_enqueue_scripts', array($this,'admin_global_style'));
        //add_action( 'init', array($this,'create_store_taxonomies'), 0 );

        add_filter( "manage_edit-store_columns", array($this,'store_setting_column_header'), 10);
        add_action( "manage_store_custom_column",array($this,'store_setting_column_content'), 10, 3);
        add_action( 'admin_menu', array($this,'pos_admin_menu'),1 );

        add_action( 'woocommerce_product_options_inventory_product_data', array($this,'woocommerce_product_options_stock_fields'));
        add_action( 'woocommerce_product_after_variable_attributes', array($this,'woocommerce_variation_options_inventory'),10,3);

        add_action( 'woocommerce_product_options_pricing', array($this,'woocommerce_product_options_pricing'));
        add_action( 'woocommerce_variation_options_pricing', array($this,'woocommerce_variation_options_pricing'),10,3);
        

        //ajax

        add_action( 'wp_ajax_op_products', array($this,'products') );
        add_action( 'wp_ajax_op_stock_products', array($this,'stock_products') );
        add_action( 'wp_ajax_op_stock_products_update', array($this,'stock_products_update') );

        add_action( 'wp_ajax_op_transactions', array($this,'transactions') );
        add_action( 'wp_ajax_op_orders', array($this,'orders') );
        add_action( 'wp_ajax_op_dashboard', array($this,'dashboard_ajax') );

        // Admin bar menus
        if ( apply_filters( 'woocommerce_show_admin_bar_visit_store', true ) ) {
            add_action( 'admin_bar_menu', array( $this, 'admin_bar_menus' ), 31 );
        }

        add_action( 'wp_ajax_op_cashier', array($this,'getUsers') );
        add_action( 'wp_ajax_save_cashier', array($this,'save_cashier') );

        add_action( 'wp_ajax_save_bacode_setting', array($this,'save_bacode_setting') );

        add_action( 'wp_ajax_print_barcode', array($this,'print_bacode') );
        add_action( 'wp_ajax_print_receipt', array($this,'print_receipt') );
        add_action( 'wp_ajax_nopriv_print_receipt', array($this,'print_receipt') );
       
        add_action( 'wp_ajax_admin_openpos_reset_balance', array($this,'reset_balance') );
        add_action( 'wp_ajax_admin_openpos_reset_debit_balance', array($this,'reset_debit') );

        add_action( 'wp_ajax_admin_openpos_update_product_grid', array($this,'update_product_grid') );
        add_action( 'wp_ajax_admin_openpos_update_transaction_grid', array($this,'update_transaction_grid') );
        add_action( 'wp_ajax_admin_openpos_update_inventory_grid', array($this,'update_inventory_grid') );

        add_action( 'wp_ajax_admin_openpos_session_unlink', array($this,'session_unlink') );

        //register
        add_action( 'wp_ajax_openpos_update_register', array($this,'update_register') );
        add_action( 'wp_ajax_openpos_delete_register', array($this,'delete_register') );
        //table
        add_action( 'wp_ajax_openpos_update_table', array($this,'update_table') );
        add_action( 'wp_ajax_openpos_delete_table', array($this,'delete_table') );
        add_action( 'wp_ajax_openpos_qrcode_table', array($this,'qrcode_table') );
        add_action( 'wp_ajax_openpos_qrcode_takeaway', array($this,'qrcode_takeaway') );
        add_action( 'wp_ajax_openpos_geneate_qrcode_table', array($this,'geneate_qrcode_table') );
        add_action( 'wp_ajax_openpos_geneate_qrcode_takeaway', array($this,'geneate_qrcode_takeaway') );
        //warehouse

        add_action( 'wp_ajax_openpos_delete_warehouse', array($this,'delete_warehouse') );
        add_action( 'wp_ajax_openpos_update_warehouse', array($this,'update_warehouse') );

        add_action( 'wp_ajax_op_inventory', array($this,'get_inventories') );
        add_action( 'wp_ajax_openpos_stock_overview', array($this,'stock_overview') );
        add_action( 'wp_ajax_op_export_inventory', array($this,'export_inventory') );
        add_action( 'wp_ajax_op_upload_inventory_csv', array($this,'upload_inventory_csv') );

        add_action( 'wp_ajax_op_stock_products_export', array($this,'op_stock_products_export') );
        add_action( 'wp_ajax_openpos_adjust_stock_finder', array($this,'adjust_stock_finder') );
        add_action( 'wp_ajax_op_adjust_stock', array($this,'op_adjust_stock') );
        add_action( 'wp_ajax_op_ajax_category', array($this,'op_ajax_category') );
        add_action( 'wp_ajax_op_ajax_order_status', array($this,'op_ajax_order_statuses') );
        add_action( 'wp_ajax_op_ajax_report', array($this,'report_ajax') );
        add_action( 'wp_ajax_op_upload_product_image', array($this,'upload_product_image') );
//        add_action( 'wp_ajax_openpos_report_data_table', array($this,'report_data_table') );

        add_filter('pre_update_option_openpos_general',array($this,'pre_update_option_openpos_general'),10,3);


        add_filter( 'woocommerce_product_data_tabs', array( $this, 'woocommerce_product_data_tabs' ) , 90 , 1 );
        add_action( 'woocommerce_product_data_panels', array( $this,'woocommerce_product_data_panels') );

        // PIN code
        add_action('admin_enqueue_scripts', array($this,'admin_enqueue_scripts'));
        add_action('edit_user_profile',array($this,'edit_user_profile'),90,1);
        add_action('show_user_profile',array($this,'edit_user_profile'),90,1);
        add_action( 'wp_ajax_op_generate_pin', array($this,'op_generate_pin') );
        
        
        //end PIN code
        add_action( 'wp_ajax_op_force_download', array($this,'op_force_download') );

        //woo js

        add_filter('woocommerce_screen_ids',function($screen_ids){
            $screen_ids[] = 'pos_page_op-tables';
            return $screen_ids;
        },110,1);
    }
    function plugins_loaded()
    {
        $this->_enable_hpos = $this->core->enable_hpos();
    }

    function pre_update_option_openpos_general($value, $old_value, $option){
        $se_number = isset($value['pos_sequential_number']) ? (int)$value['pos_sequential_number'] : 0;
        $current_order_number = get_option('_op_wc_custom_order_number',0);
        if($se_number && $current_order_number)
        {
            if($se_number > $current_order_number)
            {
                update_option('_op_wc_custom_order_number',$se_number,true);
            }else{
                //$value['pos_sequential_number'] = $current_order_number;
            }
        }
        return $value;
    }


    function admin_init() {
        $openpos_type = $this->settings_api->get_option('openpos_type','openpos_pos');
        add_filter('plugin_row_meta',array($this,'plugin_row_meta'),100,3);

        $current_page = isset( $_REQUEST['page'])  ?  esc_attr($_REQUEST['page']): false;
        $option_page = isset( $_REQUEST['action'])  ?  esc_attr($_REQUEST['action']): false;
        $allow = false;
        
        if($current_page =='op-setting' ||  $option_page == 'update'  ||  $option_page == 'openpos' || $option_page == 'print_receipt' ||  $option_page == 'op_customer_table_order')
        {
            $allow = true;
            
        }
        
        if( $allow )
        {
             //set the settings
            //$this->settings_api->set_sections( $this->get_settings_sections() );
            $this->settings_api->set_fields( $this->get_settings_fields() );
            //initialize settings
            $this->settings_api->admin_init();
        }
        $this->settings_save();
        add_settings_section(
            'openpos_permalink_section',
            __('OpenPOS Permalink', 'openpos'),
            function () {
                echo '<p>Path for POS panel.</p>';
            },
            'permalink'
        );
    
        add_settings_field(
            'openpos_base',
            __('POS base', 'openpos'),
            array($this,'openpos_base_field_callback'),
            'permalink',
            'openpos_permalink_section'
        );
        if($openpos_type == 'restaurant')
        {
            add_settings_field(
                'openpos_kitchen_base',
                __('Kitchen base', 'openpos'),
                array($this,'openpos_kitchen_base_field_callback'),
                'permalink',
                'openpos_permalink_section'
            );
            add_settings_field(
                'openpos_queue_base',
                __('Customer Queue base', 'openpos'),
                array($this,'openpos_queue_base_field_callback'),
                'permalink',
                'openpos_permalink_section'
            );
        }
        add_settings_field(
            'openpos_bill_base',
            __('Bill base', 'openpos'),
            array($this,'openpos_bill_base_field_callback'),
            'permalink',
            'openpos_permalink_section'
        );

        $this->admin_notice_init();
    }
    function openpos_kitchen_base_field_callback() { 
        $value = get_option('openpos_kitchen_base', '');
        echo '<input type="text" name="openpos_kitchen_base" value="' . esc_attr($value) . '" class="regular-text code" />';
        echo '<p class="description">'.__('Example: <code>/kitchen</code>.Leave empty to use plain url','openpos').'</p>';
    }
    function openpos_queue_base_field_callback() { 
        $value = get_option('openpos_queue_base', '');
        echo '<input type="text" name="openpos_queue_base" value="' . esc_attr($value) . '" class="regular-text code" />';
        echo '<p class="description">'.__('Example: <code>/queue</code>.Leave empty to use plain url','openpos').'</p>';
    }
    
    function openpos_bill_base_field_callback() { 
        $value = get_option('openpos_bill_base', '');
        echo '<input type="text" name="openpos_bill_base" value="' . esc_attr($value) . '" class="regular-text code" />';
        echo '<p class="description">'.__('Example: <code>/bill</code>.Leave empty to use plain url','openpos').'</p>';
    }
    function openpos_base_field_callback() {
        $value = get_option('openpos_base', '');
        echo '<input type="text" name="openpos_base" value="' . esc_attr($value) . '" class="regular-text code" />';
        echo '<p class="description">'.__('Example: <code>/openpos</code>.Leave empty to use plain url','openpos').'</p>';
    }
    function plugin_row_meta($plugin_meta, $plugin_file, $plugin_data){
        $plugin = isset($plugin_data['TextDomain']) ? $plugin_data['TextDomain']:'';

        if($plugin == 'openpos')
        {
           $plugin_meta[] = '<a target="_blank" href="'.esc_url('https://wpos.app/knowledge-base/').'">'.__('Knowledgebase','openpos').'</a>';
           $plugin_meta[] = '<a target="_blank" href="'.esc_url('https://support.openswatch.com/open.php').'">'.__('Support','openpos').'</a>';
           $plugin_meta[] = '<a target="_blank" href="'.esc_url('https://wpos.app/openpos-addons/').'">'.__('Addons','openpos').'</a>';
        }
        return $plugin_meta;
    }

    function get_default_value($key)
    {
        $file_name = $key.'.txt';
        $file_path = rtrim(OPENPOS_DIR,'/').'/default/'.$file_name;
        if($this->_filesystem->is_file($file_path))
        {
            return $this->_filesystem->get_contents($file_path);
        }else{
            return '';
        }
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
        $settings_fields = $this->get_settings_fields();
        if(isset($settings_fields['openpos_addon']) && !empty($settings_fields['openpos_addon']))
        {
            $sections[] = array(
                'id'    => 'openpos_addon',
                'title' => __( 'Add-on', 'openpos' )
            );
        }
        return $sections;
    }

    function get_settings_fields() {
        global $op_woo;
        $payment_gateways = WC()->payment_gateways->payment_gateways();
        $payment_options = array();

        $openpos_type = $this->settings_api->get_option('openpos_type','openpos_pos');

        foreach ($payment_gateways as $code => $p)
        {
            if($code == 'pos_multi' || $code == 'cash')
            {
                continue;
            }
            $payment_options[$code] = $p->title ? $p->title : $code;
        }
        $addition_payments = $this->core->additionPaymentMethods();
        $payment_options = array_merge($payment_options, $addition_payments);
        
        $shipping_options = array();
        $shipping_methods = $op_woo->get_setting_shipping_methods();
        
        foreach ($shipping_methods as $shipping_method)
        {
            $code = $shipping_method->id;
            $title = $shipping_method->title;
            if(!$title)
            {
                $title = $shipping_method->method_title;
            }
            if(!$title)
            {
                $title = $code;
            }
            $instance_id = $shipping_method->instance_id ? $shipping_method->instance_id : 0;

            if($instance_id > 0)
            {
                $zone = WC_Shipping_Zones::get_zone_by('instance_id',$instance_id);
                if($zone->get_id() > 0)
                {
                    $title .= ' ('.$zone->get_zone_name().')';
                }
            }

            $shipping_options[$code.':'.$instance_id] = $title;
        }


        $refund_duration = array();
        $allow_refund_duration = $this->settings_api->get_option('pos_allow_refund','openpos_general');


        $setting_pos_discount_tax_class = $this->settings_api->get_option('pos_discount_tax_class','openpos_general');

        $addition_general_setting = array();
        $wc_order_status = wc_get_order_statuses();
        
        if($allow_refund_duration == 'yes_day')
        {
            $refund_duration = array(
                'name'              => 'pos_refund_duration',
                'label'             => __( 'Refund Duration', 'openpos' ),
                'type'              => 'number',
                'default'           => '1',
                'desc'    => __( 'refund duration in day', 'openpos' ),
                'sanitize_callback' => 'sanitize_text_field'
            );
        }
        $tax_included_discount = array();
        $tax_included_discount_rate = array();
        $allow_exchange_partial_refund = array();
        $allow_exchange = $this->settings_api->get_option('allow_exchange','openpos_general');
        $pos_tax_class = $this->settings_api->get_option('pos_tax_class','openpos_general');

        $pos_custom_item = $this->settings_api->get_option('pos_allow_custom_item','openpos_pos');
        $pos_enable_weight_barcode = $this->settings_api->get_option('pos_enable_weight_barcode','openpos_pos');
        $pos_default_checkout_mode = $this->settings_api->get_option('pos_default_checkout_mode','openpos_pos');

       
        $pos_custom_item_tax_rate_setting = array();

        $pos_discount_tax_class_setting = array();
        $pos_discount_tax_rate_setting = array();
        $pos_laybuy = array();
        $pos_table_default_view = array();


        $pos_weight_barcode_format = array();
        $pos_tipping = array();
        $pos_tip_amount = array();

        $all_tax_classes = wc_get_product_tax_class_options();
        $all_product_tax_classes = wc_get_product_tax_class_options();
        
        if(!is_array($all_tax_classes))
        {
            $all_tax_classes = array();
        }
        if(!is_array($all_product_tax_classes))
        {
            $all_product_tax_classes = array();
        }
        $all_tax_classes['op_notax'] = __( 'No Tax', 'openpos' );
        $all_product_tax_classes['op_notax'] = __( 'No Tax', 'openpos' );
        $all_product_tax_classes['op_productax'] = __( 'Use Product Tax Class', 'openpos' );

        if($pos_enable_weight_barcode == 'yes' )
        {
            $pos_weight_barcode_format = array(
                'name'    => 'pos_weight_barcode_format',
                'label'   => __( 'Scale Barcode Format', 'openpos' ),
                'desc'    => __('Work with barcode scanner device only. I : item code , P : price , W : weight  , Q : quantity, E : expired . Example: "DDIIIIIDPPPPC" -   "2081981002076" With "20": string to detect barcode generate by scale, "81981" : product barcode , "0207" : price = 2.07$  ', 'openpos' ),
                'type'    => 'text',
                'default' => 'DDIIIIIDPPPPC'
            );
        }
        if($pos_default_checkout_mode =='single' || $pos_default_checkout_mode =='single_mutli_times' )
        {
            $pos_laybuy = array(
                'name'              => 'pos_laybuy',
                'label'             => __( 'Allow LayBuy', 'openpos' ),
                'type'              => 'select',
                'default'           => 'no',
                'desc'    => __( 'Allow Order with Pay Later', 'openpos' ),
                'options' =>  array(
                    'yes' => __( 'Yes', 'openpos' ),
                    'no'  => __( 'No', 'openpos' ),
                )
            );
            $pos_tipping = array(
                'name'              => 'pos_allow_tip',
                'label'             => __( 'Allow Tipping', 'openpos' ),
                'type'              => 'select',
                'default'           => 'no',
                'desc'    => __( 'Allow Order with Tipping', 'openpos' ),
                'options' =>  array(
                    'yes' => __( 'Yes', 'openpos' ),
                    'no'  => __( 'No', 'openpos' ),
                )
            );
            $pos_tip_amount = array(
                'name'              => 'pos_tip_amount',
                'label'             => __( 'Quick Tipping Amount', 'openpos' ),
                'desc'              => __( 'List of quick tip values in your pos, 4 value only. Separate by "|" character. Example: 5|10|5%|10% ', 'openpos' ),
                'default'           => '5|10|5%|10%',
                'type'              => 'text'
            );
        }
        if($allow_exchange == 'yes')
        {
            $allow_exchange_partial_refund = array(
                'name'              => 'pos_exchange_partial_refund',
                'label'             => __( 'Refund exchange cash', 'openpos' ),
                'type'              => 'select',
                'default'           => 'no',
                'desc'    => __( 'Allow return cash with remain money amount after exchange', 'openpos' ),
                'options' =>  array(
                    'yes' => __( 'Yes', 'openpos' ),
                    'no'  => __( 'No', 'openpos' ),
                )
            );
        }
        $tax_included_discount = array();

        

        if($pos_tax_class != 'op_productax')
        {


            if($pos_tax_class != 'op_notax')
            {
                $rates = $op_woo->getTaxRates($pos_tax_class);

                $rate_options = array();
                $default_rate_option = 0;
                foreach($rates as $rate_id => $rate)
                {
                    $rate_options[$rate_id] = $rate['label'].' ('.$rate['rate'].'%)';
                }
                if(!empty($rate_options))
                {
                    $default_rate_option = max(array_keys($rate_options));

                    $tax_included_discount_rate = array(
                        'name'              => 'pos_tax_rate_id',
                        'label'             => __( 'Tax Rate', 'openpos' ),
                        'type'              => 'select',
                        'default'           => $default_rate_option,
                        'desc'    => __( 'Choose Tax Rate', 'openpos' ),
                        'options' =>  $rate_options
                    );
                }


            }

        }else{



            $tax_rate_options = array();
            $tax_rate_options['op_notax'] =  __( 'No Tax', 'openpos' );
            foreach($all_tax_classes as $k => $v)
            {
                $tax_rate_options[$k] = $v; 
            }
            $tax_rate_options['op_productax'] =  __( 'Use cart items Tax', 'openpos' );

            $pos_discount_tax_class_setting = array(
                'name'              => 'pos_discount_tax_class',
                'label'             => __( 'Discount Tax Class', 'openpos' ),
                'desc'              => __('Discount Tax Class, both cart discount and coupon. It for POS only','openpos'),
                'default'           => 'op_notax',
                'type'              => 'select',
                'options' => $tax_rate_options
            );

            if($setting_pos_discount_tax_class != 'op_notax' && $setting_pos_discount_tax_class != 'op_productax')
            {
                $rates = $op_woo->getTaxRates($setting_pos_discount_tax_class);

                $rate_options = array();
                foreach($rates as $rate_id => $rate)
                {
                    $rate_options[''.$rate_id] = $rate['label'].' ('.$rate['rate'].'%)';
                }

                if( !empty($rate_options))
                {
                    $rate_options[0] = __( 'Choose tax rate', 'openpos' );
                    $pos_discount_tax_rate_setting = array(
                        'name'              => 'pos_discount_tax_rate',
                        'label'             => __( 'Discount Tax Rate', 'openpos' ),
                        'desc'              => __('Add discount tax rate, this rate for cart discount and coupon discount on POS only','openpos'),
                        'default'           => '0',
                        'type'              => 'select',
                        'options' => $rate_options
                    );
                    
                }
            }
            if('no' === get_option( 'woocommerce_prices_include_tax' ))
            {
                $tax_included_discount = array(
                        'name'              => 'pos_tax_included_discount',
                        'label'             => __( 'Item Discount Calculation', 'openpos' ), // Tax Included Discount 
                        'type'              => 'select',
                        'default'           => 'no',
                        'desc'    => __( 'Include discount amount when get final tax amount', 'openpos' ),
                        'options' =>  array(
                            'yes' => __( 'After Tax', 'openpos' ),
                            'no'  => __( 'Before Tax', 'openpos' ),
                        )
                );
            }

        }
        //fee tax class
        $fee_tax_options = array();
        $fee_tax_options['op_notax'] =  __( 'No Tax', 'openpos' );
        foreach($all_tax_classes as $k => $v)
        {
            $fee_tax_options[$k] = $v; 
        }
        $pos_fee_tax_class_setting = array(
            'name'              => 'pos_fee_tax_class',
            'label'             => __( 'Fee Tax Class', 'openpos' ),
            'desc'              => __('Fee Tax Class. It for POS only','openpos'),
            'default'           => 'op_notax',
            'type'              => 'select',
            'options' => $fee_tax_options
        );
        //end

        $dashboard_display_options = array(
            'board' => __('New DashBoard','openpos'),
            'tiles' => __('Tiles DashBoard','openpos'),
            'product' => __('Products','openpos'),
            'category' => __('Categories','openpos'),
        );

        if($openpos_type =='restaurant')
        {
            $dashboard_display_options['table'] = __('Tables','openpos');
            $pos_table_default_view = array(
                'name'              => 'pos_table_default_view',
                'label'             => __( 'Default Table View', 'openpos' ),
                'desc'              => __( 'Default display for table / takeaway', 'openpos' ),
                'default'           => 'product',
                'type'              => 'select',
                'options' => array(
                    'product' => __('Products','openpos'),
                    'category' => __('Categories','openpos'),
                )
            );
        }

        $dashboard_display = array(
            'name'              => 'dashboard_display',
            'label'             => __( 'Default Dashboard Display', 'openpos' ),
            'desc'              => __( 'Default display for POS , in case category please set category item on category setting', 'openpos' ),
            'default'           => 'board',
            'type'              => 'select',
            'options' => $dashboard_display_options
        );


        $barcode_key_options  = apply_filters('op_barcode_key_setting',array(
            '_op_barcode' => __('OpenPOS Barcode','openpos'),
            'post_id' => __('Product Id','openpos'),
            '_sku' => __('Product Sku','openpos'),
            '_global_unique_id' => __('GTIN, UPC, EAN or ISBN','openpos'),
        ));

        $pos_sequential_number_enable = $this->settings_api->get_option('pos_sequential_number_enable','openpos_general');

        $pos_sequential_number = array();
        $pos_sequential_number_prefix = array();
        
        if($pos_sequential_number_enable == 'yes')
        {

            $current_order_number = get_option('_op_wc_custom_order_number',0);
            if(!$current_order_number)
            {
                $current_order_number = 1;
            }

            $pos_sequential_number = array(
                'name'              => 'pos_sequential_number',
                'label'             => __( 'Sequential: Start order number', 'openpos' ),
                'type'              => 'number',
                'default'           => $current_order_number,
                'desc'    => __( 'Next order number', 'openpos' ).': '.$current_order_number,
                'sanitize_callback' => 'sanitize_text_field'
            );
            $pos_sequential_number_prefix = array(
                'name'              => 'pos_sequential_number_prefix',
                'label'             => __( 'Order number prefix', 'openpos' ),
                'type'              => 'text',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
                'desc'    => __( '[year] : current year, [month] : current month 01 -> 12, [day] : current day 01 -> 31 , [register_id] : register id, [outlet_id] : outlet id, [cashier_id] : cashier id  ', 'openpos' )
            );
        }

        $pos_shipping_enable = $this->settings_api->get_option('shipping_methods','openpos_shipment');
        $openpos_shipment =  array(
            array(
                'name'    => 'shipping_methods',
                'label'   => __( 'Shipping Methods', 'openpos' ),
                'desc'    => __( 'Default Shipping methods for POS beside Store pickup', 'openpos' ),
                'type'    => 'multicheck_sortable',
                'default' => '',
                'options' => $shipping_options
            ),
            
            array(
                'name'        => 'html',
                'desc'        => __( 'Set Shipping class for active shipping method. Please choose shipping and click save change to see set shipping tax', 'openpos' ),
                'type'        => 'html'
            ),
            
        );
        if(is_array($pos_shipping_enable) && !empty($pos_shipping_enable))
        {
            foreach($pos_shipping_enable as $pos_shipping)
            {
                $shipping_label = isset($shipping_options[$pos_shipping]) ? $shipping_options[$pos_shipping] : '';
                if($shipping_label)
                {
                    $openpos_shipment[] = array(
                        'name'    => 'shipping_tax_class_'.esc_attr($pos_shipping),
                        'label'   => __( 'Tax for', 'openpos' ).' "'.$shipping_label.'"',
                        'desc'    => __( 'Tax class for current shipping method. Tax rate auto generate base on store/outlet address', 'openpos' ),
                        'type'    => 'select',
                        'default' => 'op_notax',
                        'options' => $all_tax_classes
                    );
                }
            }
        }
        
        

        $settings_fields = array(
            'openpos_general' => array(
                array(
                    'name'    => 'pos_pwa_enable',
                    'label'   => __( 'Progressive Web Apps Cached', 'openpos' ),
                    'desc'    => __( 'To use this feature, your POS url should under HTTPS. OpenPOS Progressive Web Apps on Desktop can be ‘installed’ on the user’s device much like native apps. It’s fast. Feel integrated because they launched in the same way as other apps, and run in an app window, without an address bar or tabs. It is reliable because service workers can cache all of the assets they need to run. And it create an engaging experience for users.', 'openpos' ),
                    'type'    => 'select',
                    'default' => 'yes',
                    'options' =>  array(
                        'yes' => __( 'Yes', 'openpos' ),
                        'no'  => __( 'No', 'openpos' ),
                    )
                ),
                array(
                    'name'    => 'pos_sequential_number_enable',
                    'label'   => __( 'Custom Order Number', 'openpos' ),
                    'desc'    => __( 'Custom Sequential Order Numbers for Order create via POS', 'openpos' ),
                    'type'    => 'select',
                    'default' => 'no',
                    'options' =>  array(
                        'yes' => __( 'Yes', 'openpos' ),
                        'no'  => __( 'No', 'openpos' ),
                    )
                ),
                $pos_sequential_number,
                $pos_sequential_number_prefix,
                array(
                    'name'    => 'pos_stock_manage',
                    'label'   => __( 'POS Stock Manager', 'openpos' ),
                    'desc'    => __( 'Don\'t allow checkout out of stock product in POS', 'openpos' ),
                    'type'    => 'select',
                    'default' => 'no',
                    'options' =>  array(
                        'yes' => __( 'Yes', 'openpos' ),
                        'no'  => __( 'No', 'openpos' ),
                    )
                ),
                
                array(
                    'name'    => 'pos_order_status',
                    'label'   => __( 'POS Order Status', 'openpos' ),
                    'desc'    => __( 'status for those order created by POS', 'openpos' ),
                    'type'    => 'select',
                    'default' => 'wc-completed',
                    'options' =>  $wc_order_status
                ),
                array(
                    'name'              => 'pos_continue_checkout_order_status',
                    'label'             => __( 'Continue Checkout Order Status', 'openpos' ),
                    'desc'              => __( 'Status of online order allow continue checkout on POS. Enter status name to search', 'openpos' ),
                    'default'           => '',
                    'type'              => 'list_tags',
                    'options' =>  array(
                        'yes' => __( 'Yes', 'openpos' ),
                        'no'  => __( 'No', 'openpos' ),
                    )
                ),
                array(
                    'name'    => 'pos_allow_refund',
                    'label'   => __( 'Allow Refund', 'openpos' ),
                    'desc'    => __( 'Refund offline via pos panel', 'openpos' ),
                    'type'    => 'select',
                    'default' => 'yes',
                    'options' =>  array(
                        'yes' => __( 'Always allow', 'openpos' ),
                        'yes_day' => __( 'Allow with durations', 'openpos' ),
                        'no'  => __( 'No Refund', 'openpos' ),
                    )
                ),
                $refund_duration,
                array(
                    'name'    => 'allow_exchange',
                    'label'   => __( 'Allow Exchange', 'openpos' ),
                    'desc'    => __( 'Allow exchange for order made by current session', 'openpos' ),
                    'type'    => 'select',
                    'default' => 'no',
                    'options' =>  array(
                        'yes' => __( 'Allow for current session', 'openpos' ),
                        'alway' => __( 'Alway Allow', 'openpos' ),
                        'no'  => __( 'No exchange', 'openpos' ),
                    )
                ),
                $allow_exchange_partial_refund,
                array(
                    'name'    => 'pos_tax_class',
                    'label'   => __( 'Pos Tax Class', 'openpos' ),
                    'desc'    => __( 'Tax Class assign for POS system. Require refresh product list to take effect.', 'openpos' ),
                    'type'    => 'select',
                    'default' => 'op_notax',
                    'options' => $all_product_tax_classes
                ),
                $pos_fee_tax_class_setting,
                array(
                    'name'    => 'pos_cart_discount',
                    'label'   => __( 'Cart Discount Calculation', 'openpos' ),
                    'desc'    => __( 'Cart discount calculation base on', 'openpos' ),
                    'type'    => 'select',
                    'default' => 'after_tax',
                    'options' =>  array(
                        'after_tax' => __( 'After Tax', 'openpos' ),
                        'before_tax'  => __( 'Before Tax', 'openpos' ),
                    )
                ),
                $tax_included_discount_rate,
                $tax_included_discount,
                $pos_discount_tax_class_setting,
                $pos_discount_tax_rate_setting
            ),
            'openpos_payment' => array(
                array(
                    'name'    => 'payment_methods',
                    'label'   => __( 'POS Addition Payment Methods', 'openpos' ),
                    'desc'    => __( 'Payment methods for POS beside cash(default)', 'openpos' ),
                    'type'    => 'multicheck_sortable',
                    'default' => '',
                    'options' => $payment_options
                ),
            ),
            'openpos_shipment' => $openpos_shipment,
            'openpos_label' => array(
                array(
                    'name'              => 'barcode_meta_key',
                    'label'             => __( 'Barcode Meta Key', 'openpos' ),
                    'desc'    => __( 'Barcode field . Make sure the data is unique on meta key you are selected', 'openpos' ),
                    'type'              => 'select',
                    'default' => '_op_barcode',
                    'options' => $barcode_key_options
                ),
                array(
                    'name'              => 'unit',
                    'label'             => __( 'Unit', 'openpos' ),
                    'type'              => 'select',
                    'default' => 'in',
                    'options' => array(
                        'in' => 'Inch',
                        'mm' => 'Millimeter'
                    )
                ),
                array(
                    'name'              => 'heading-s',
                    'desc'              => __( '<h2>Sheet Setting</h2>', 'openpos' ),
                    'type'              => 'html'
                ),

                array(
                    'name'              => 'sheet_width',
                    'label'             => __( 'Sheet Width', 'openpos' ),
                    'type'              => 'number',
                    'default'           => '8.5',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'sheet_height',
                    'label'             => __( 'Sheet Height', 'openpos' ),
                    'type'              => 'number',
                    'default'           => '11',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'sheet_vertical_space',
                    'label'             => __( 'Vertical Space', 'openpos' ),
                    'type'              => 'number',
                    'default'           => '0',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'sheet_horizontal_space',
                    'label'             => __( 'Horizontal Space', 'openpos' ),
                    'type'              => 'number',
                    'default'           => '0.125',
                    'sanitize_callback' => 'sanitize_text_field'
                ),

                array(
                    'name'              => 'sheet_margin_top',
                    'label'             => __( 'Margin Top', 'openpos' ),
                    'type'              => 'number',
                    'default'           => '0.5',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'sheet_margin_right',
                    'label'             => __( 'Margin Right', 'openpos' ),
                    'type'              => 'number',
                    'default'           => '0.188',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'sheet_margin_bottom',
                    'label'             => __( 'Margin Bottom', 'openpos' ),
                    'type'              => 'number',
                    'default'           => '0.5',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'sheet_margin_left',
                    'label'             => __( 'Margin Left', 'openpos' ),
                    'type'              => 'number',
                    'default'           => '0.188',
                    'sanitize_callback' => 'sanitize_text_field'
                ),


                array(
                    'name'              => 'barcode_label_width',
                    'label'             => __( 'Label Width', 'openpos' ),
                    'type'              => 'number',
                    'default'           => '2.625',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'barcode_label_height',
                    'label'             => __( 'Label Height', 'openpos' ),
                    'type'              => 'number',
                    'default'           => '1',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'barcode_label_padding_top',
                    'label'             => __( 'Padding Top', 'openpos' ),
                    'type'              => 'number',
                    'default'           => '0.1',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'barcode_label_padding_right',
                    'label'             => __( 'Padding Right', 'openpos' ),
                    'type'              => 'number',
                    'default'           => '0.1',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'barcode_label_padding_bottom',
                    'label'             => __( 'Padding Bottom', 'openpos' ),
                    'type'              => 'number',
                    'default'           => '0.1',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'barcode_label_padding_left',
                    'label'             => __( 'Padding Left', 'openpos' ),
                    'type'              => 'number',
                    'default'           => '0.1',
                    'sanitize_callback' => 'sanitize_text_field'
                ),

                array(
                    'name'              => 'barcode_label_template',
                    'label'             => __( 'Label Template', 'openpos' ),
                    'desc'              => __( 'use [barcode with="" height=""] to adjust barcode image, [op_product attribute="attribute_name"] with attribute name: <b>name, price ,regular_price, sale_price, width, height,length,weight</b> and accept html,inline style css string', 'openpos' ),
                    'default'           => '[op_product attribute="name"][barcode][op_product attribute="barcode"]',
                    'type'              => 'wysiwyg'
                ),

                array(
                    'name'              => 'heading',
                    'desc'              => __( '<h2>Barcode Setting</h2>', 'openpos' ),
                    'type'              => 'html'
                ),


                array(
                    'name'              => 'barcode_mode',
                    'label'             => __( 'Mode', 'openpos' ),
                    'type'              => 'select',
                    'default' => 'code_128',
                    'options' => array(
                        'code_128' => 'Code 128',
                        'ean_13' => 'EAN-13',
                        'code_39' => 'Code-39',
                        'qrcode' => __( 'QRCode', 'openpos' ),
                    )
                ),
                array(
                    'name'              => 'barcode_width',
                    'label'             => __( 'Width', 'openpos' ),
                    'type'              => 'number',
                    'default'           => '2.625',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'barcode_height',
                    'label'             => __( 'Height', 'openpos' ),
                    'type'              => 'number',
                    'default'           => '1',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
            ),

            'openpos_receipt' => array(
                array(
                    'name'        => 'html',
                    'desc'        => __( 'Those setting for receipt <strong>Default template</strong>.', 'openpos' ),
                    'type'        => 'html'
                ),
                array(
                    'name'              => 'receipt_width',
                    'label'             => __( 'Receipt Width', 'openpos' ),
                    'desc'              => __( 'inch', 'openpos' ),
                    'type'              => 'text',
                    'default'           => '2.28',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'receipt_padding_top',
                    'label'             => __( 'Padding Top', 'openpos' ),
                    'desc'              => __( 'inch', 'openpos' ),
                    'type'              => 'number',
                    'default'           => '0',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'receipt_padding_right',
                    'label'             => __( 'Padding Right', 'openpos' ),
                    'desc'              => __( 'inch', 'openpos' ),
                    'type'              => 'number',
                    'default'           => '0',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'receipt_padding_bottom',
                    'label'             => __( 'Padding Bottom', 'openpos' ),
                    'desc'              => __( 'inch', 'openpos' ),
                    'type'              => 'number',
                    'default'           => '0',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'receipt_padding_left',
                    'label'             => __( 'Padding Left', 'openpos' ),
                    'desc'              => __( 'inch', 'openpos' ),
                    'type'              => 'number',
                    'default'           => '0',
                    'sanitize_callback' => 'sanitize_text_field'
                ),

                array(
                    'name'              => 'receipt_template_header',
                    'label'             => __( 'Receipt Template Header', 'openpos' ),
                    'desc'              => __( 'use [payment_method], [customer_name], [customer_phone],[sale_person], [created_at], [order_number],[order_number_format],[order_note],[order_qrcode width="_number_" height="_number_"],[order_barcode  width="_number_" height="_number_"], [customer_email],[op_warehouse field="_fiel_name"] - (_fiel_name : name, address, city, postal_code,country,phone,email), [op_register field="name"] shortcode to adjust receipt information, accept html string', 'openpos' ),
                    'type'              => 'wysiwyg',
                    'default'           => $this->get_default_value('receipt_template_header')
                ),
                array(
                    'name'              => 'receipt_template_footer',
                    'label'             => __( 'Receipt Template Footer', 'openpos' ),
                    'desc'              => __( 'use [payment_method],[customer_name], [customer_phone], [sale_person], [created_at], [order_number],[order_number_format],[order_qrcode width="_number_" height="_number_"],[order_barcode  width="_number_" height="_number_"],[order_note], [customer_email], [op_warehouse field="_fiel_name"] - (_fiel_name : name, address, city, postal_code,country,phone,email), [op_register field="name"] shortcode to adjust receipt information, accept html string', 'openpos' ),
                    'type'              => 'wysiwyg',
                    'default'           => $this->get_default_value('receipt_template_footer')
                ),
                array(
                    'name'              => 'receipt_css',
                    'label'             => __( 'Receipt Style', 'openpos' ),
                    'desc'              => sprintf('<a  target="_blank" href="'.admin_url('admin-ajax.php?action=print_receipt').'">%s</a>',__( 'click here to preview receipt', 'openpos' )),
                    'type'              => 'textarea_code',
                    'default'           => $this->get_default_value('receipt_css'),
                ),
            ),
            'openpos_pos' => array(
                array(
                    'name'              => 'openpos_logo',
                    'label'             => __( 'POS Logo', 'openpos' ),
                    'desc'              => __( 'Default Logo for POS Panel (ex: 100x50)', 'openpos' ),
                    'default'           => '',
                    'type'              => 'file',
                    'options' => array()
                ),
                array(
                    'name'              => 'openpos_type',
                    'label'             => __( 'POS Type', 'openpos' ),
                    'desc'              => __( 'Default display for POS , their are table management in cafe/restaurant type', 'openpos' ),
                    'default'           => 'grocery',
                    'type'              => 'select',
                    'options' => array(
                        'grocery' => __('Grocery','openpos'),
                        'restaurant' => __('Cafe / Restaurant','openpos'),
                    )
                ),
                $dashboard_display,
                $pos_table_default_view ,
                array(
                    'name'              => 'openpos_login_mode',
                    'label'             => __( 'Login Mode', 'openpos' ),
                    'desc'              => __( 'Default login mode', 'openpos' ),
                    'default'           => 'default',
                    'type'              => 'select',
                    'options' => array(
                        'default' => __('Username + Password','openpos'),
                        'pin' => __('PIN login','openpos'),
                    )
                ),
                array(
                    'name'              => 'pos_product_grid',
                    'label'             => __( 'Product Grid Size', 'openpos' ),
                    'desc'              => __( 'Grid Size for Products (column x row)  on POS Panel', 'openpos' ),
                    'default'           => array('col' => 4,'row' => 4),
                    'type'              => 'pos_grid',
                    'options' =>  array()
                ),

                array(
                    'name'              => 'pos_category_grid',
                    'label'             => __( 'Category Grid Size', 'openpos' ),
                    'desc'              => __( 'Grid Size for Categories (column x row)   on POS Panel', 'openpos' ),
                    'default'           => array('col' => 2,'row' => 4),
                    'type'              => 'pos_grid',
                    'options' =>  array()
                ),

                array(
                    'name'              => 'pos_language',
                    'label'             => __( 'Default POS Language', 'openpos' ),
                    'desc'              => __( 'Default language on POS. To translate goto pos/assets/i18n/_you_lang.json and update this file', 'openpos' ),
                    'default'           => '_auto',
                    'type'              => 'select',
                    'options' => $this->core->getAllLanguage()
                ),
                array(
                    'name'              => 'pos_allow_custom_item',
                    'label'             => __( 'Allow Add Custom Item', 'openpos' ),
                    'desc'              => __( 'Add custom item , the item do not exist in your system from POS', 'openpos' ),
                    'default'           => 'yes',
                    'type'              => 'select',
                    'options' => array(
                        'yes' => __('Yes','openpos'),
                        'no' => __('No','openpos'),
                    )
                ),
                $pos_custom_item_tax_rate_setting,

                array(
                    'name'              => 'pos_allow_custom_note',
                    'label'             => __( 'Allow Add Order Note', 'openpos' ),
                    'desc'              =>  __( 'Add order note from POS','openpos'),
                    'default'           => 'no',
                    'type'              => 'select',
                    'options' => array(
                        'yes' => __('Yes','openpos'),
                        'no' => __('No','openpos'),
                    )
                ),


                array(
                    'name'              => 'time_frequency',
                    'label'             => __( 'Time Frequency', 'openpos' ),
                    'desc'              => __( 'Time duration POS state checking (in mini seconds)','openpos'),
                    'default'           => '5000',
                    'type'              => 'number'
                ),
                array(
                    'name'              => 'pos_auto_sync',
                    'label'             => __( 'Product Auto Sync', 'openpos' ),
                    'desc'              => __( 'Auto sync product qty by running process in background','openpos'),
                    'default'           => 'yes',
                    'type'              => 'select',
                    'options' => array(
                        'yes' => __('Yes','openpos'),
                        'no' => __('No','openpos'),
                    )
                ),
                array(
                    'name'              => 'pos_clear_product',
                    'label'             => __( 'Clear Product List ', 'openpos' ),
                    'desc'              => __( 'Auto clear product list on your local data after logout. Recommend set to "No" if you have > 500 products.','openpos'),
                    'default'           => 'yes',
                    'type'              => 'select',
                    'options' => array(
                        'yes' => __('Yes','openpos'),
                        'no' => __('No','openpos'),
                    )
                ),
                array(
                    'name'              => 'pos_display_outofstock',
                    'label'             => __( 'Display Out of stock', 'openpos' ),
                    'desc'              => __( 'Display out of stock product in POS panel','openpos'),
                    'default'           => 'no',
                    'type'              => 'select',
                    'options' => array(
                        'yes' => __('Yes','openpos'),
                        'no' => __('No','openpos'),
                    )
                ),
                array(
                    'name'              => 'accept_negative_checkout',
                    'label'             => __( 'Allow Negative Qty', 'openpos' ),
                    'desc'              => __( 'Allow negative qty , grand total  use as refund','openpos'),
                    'default'           => 'no',
                    'type'              => 'select',
                    'options' => array(
                        'yes' => __('Yes','openpos'),
                        'no' => __('No','openpos'),
                    )
                ),
                array(
                    'name'              => 'pos_change_price',
                    'label'             => __( 'Allow Update Price', 'openpos' ),
                    'desc'              => __( 'Allow change product price on POS panel. Require refresh product list to take effect.','openpos'),
                    'default'           => 'no',
                    'type'              => 'select',
                    'options' => array(
                        'yes' => __('Yes','openpos'),
                        'no' => __('No','openpos'),
                    )
                ),
                array(
                    'name'              => 'pos_image_width',
                    'label'             => __( 'POS Image Width', 'openpos' ),
                    'desc'              => __( 'Width of image in pos in px','openpos'),
                    'default'           => '208',
                    'type'              => 'number'
                ),
                array(
                    'name'              => 'pos_image_height',
                    'label'             => __( 'POS Image Height', 'openpos' ),
                    'desc'              => __( 'Height of image in pos in px','openpos'),
                    'default'           => '195',
                    'type'              => 'number'
                ),
                array(
                    'name'              => 'pos_custom_css',
                    'label'             => __( 'POS Custom CSS', 'openpos' ),
                    'desc'              => __( 'Custom style for POS with CSS', 'openpos' ),
                    'default'           => '',
                    'type'              => 'textarea_code'
                ),
                array(
                    'name'              => 'pos_categories',
                    'label'             => __( 'POS Category', 'openpos' ),
                    'desc'              => __( 'List of Categories display on POS panel. Enter keyword to search, this field is autocomplete', 'openpos' ),
                    'default'           => '',
                    'type'              => 'category_tags'
                ),
                array(
                    'name'              => 'pos_money',
                    'label'             => __( 'POS Money List', 'openpos' ),
                    'desc'              => __( 'List of money values in your pos, This field use for cash rounding in Single / Single multi time Payment mode . Separate by "|" character. Example: 10|20|30. If you don\'t want rounding, just leave this setting to empty!' , 'openpos' ),
                    'default'           => '',
                    'type'              => 'text'
                ),
                array(
                    'name'              => 'pos_custom_item_discount_amount',
                    'label'             => __( 'Quick Item Discount Amount', 'openpos' ),
                    'desc'              => __( 'List of quick discount values in your pos. Separate by "|" character. Example: 5|5%|10%', 'openpos' ),
                    'default'           => '',
                    'type'              => 'text'
                ),
                array(
                    'name'              => 'pos_custom_cart_discount_amount',
                    'label'             => __( 'Quick Cart Discount Amount', 'openpos' ),
                    'desc'              => __( 'List of quick discount values in your pos. Separate by "|" character. Example: 5|5%|10%', 'openpos' ),
                    'default'           => '',
                    'type'              => 'text'
                ),
                array(
                    'name'              => 'pos_require_customer_mode',
                    'label'             => __( 'Require customer', 'openpos' ),
                    'desc'              => __( 'Require checkout with customer added only in POS','openpos'),
                    'default'           => 'no',
                    'type'              => 'select',
                    'options' => array(
                        'yes' => __('Yes','openpos'),
                        'no' => __('No','openpos'),
                    )
                ),
                array(
                    'name'              => 'pos_customer_autocomplete',
                    'label'             => __( 'Customer Autocomplete', 'openpos' ),
                    'desc'              => __( 'Auto suggestion customer search when type word in customer search. Once disable, cashier should click "Enter" to start search','openpos'),
                    'default'           => 'yes',
                    'type'              => 'select',
                    'options' => array(
                        'yes' => __('Yes','openpos'),
                        'no' => __('No','openpos'),
                    )
                ),
                array(
                    'name'              => 'pos_search_product_auto',
                    'label'             => __( 'Product Auto complete', 'openpos' ),
                    'desc'              => __( 'Auto suggestion when type word in product search. Once disable, cashier should click "Enter" to start search','openpos'),
                    'default'           => 'yes',
                    'type'              => 'select',
                    'options' => array(
                        'yes' => __('Yes','openpos'),
                        'no' => __('No','openpos'),
                    )
                ),
                array(
                    'name'              => 'pos_default_open_cash',
                    'label'             => __( 'Open Cash When Login', 'openpos' ),
                    'desc'              => __( 'Open Cash Adjustment Popup when login to POS','openpos'),
                    'default'           => 'no',
                    'type'              => 'select',
                    'options' => array(
                        'yes' => __('Yes','openpos'),
                        'no' => __('No','openpos'),
                    )
                ),
                
                array(
                    'name'              => 'pos_search_product_online',
                    'label'             => __( 'Search Mode', 'openpos' ),
                    'desc'              => __( 'The way of search when type keyword on search box on POS','openpos'),
                    'default'           => 'suggestion',
                    'type'              => 'select',
                    'options' => array(
                        'no' => __('Offline - Local browser data search','openpos'),
                        'yes' => __('Online - Seach by your website','openpos'),
                    )
                ),
                array(
                    'name'              => 'search_type',
                    'label'             => __( 'Search Display Type', 'openpos' ),
                    'desc'              => __( 'Layout of result return by search product input ','openpos'),
                    'default'           => 'suggestion',
                    'type'              => 'select',
                    'options' => array(
                        'suggestion' => __('Auto Suggestion','openpos'),
                        'grid' => __('Product Grid Display','openpos'),
                    )
                ),
                array(
                    'name'              => 'search_result_total',
                    'label'             => __( 'Total Search Result', 'openpos' ),
                    'desc'              => __( 'Number of products search result', 'openpos' ),
                    'default'           => '10',
                    'type'              => 'number'
                ),
                
                array(
                    'name'              => 'pos_default_checkout_mode',
                    'label'             => __( 'Payment Type', 'openpos' ),
                    'desc'              => __( 'Logic for Payment method type use in POS checkout','openpos'),
                    'default'           => 'single',
                    'type'              => 'select',
                    'options' => array(
                        'multi' => __('Split Multi Payment - Deprecated','openpos'),
                        'single' => __('Single Payment','openpos'),
                        'single_mutli_times' => __('Single Payment with Multi Times','openpos'),
                    )
                ),
                array(
                    'name'              => 'pos_enable_weight_barcode',
                    'label'             => __( 'Allow Digital Scale', 'openpos' ),
                    'desc'              => __( 'Allow scan barcode with label generate by digital scale','openpos'),
                    'default'           => 'no',
                    'type'              => 'select',
                    'options' => array(
                        'yes' => __('Yes','openpos'),
                        'no' => __('No','openpos'),
                    )
                ),
                $pos_weight_barcode_format,
                $pos_laybuy,
                $pos_tipping,
                $pos_tip_amount
               
                



            ),
            /*
            'openpos_basics' => array(


                array(
                    'name'              => 'number_input',
                    'label'             => __( 'Number Input', 'openpos' ),
                    'desc'              => __( 'Number field with validation callback `floatval`', 'openpos' ),
                    'placeholder'       => __( '1.99', 'openpos' ),
                    'min'               => 0,
                    'max'               => 100,
                    'step'              => '0.01',
                    'type'              => 'number',
                    'default'           => 'Title',
                    'sanitize_callback' => 'floatval'
                ),
                array(
                    'name'        => 'textarea',
                    'label'       => __( 'Textarea Input', 'openpos' ),
                    'desc'        => __( 'Textarea description', 'openpos' ),
                    'placeholder' => __( 'Textarea placeholder', 'openpos' ),
                    'type'        => 'textarea'
                ),
                array(
                    'name'        => 'html',
                    'desc'        => __( 'HTML area description. You can use any <strong>bold</strong> or other HTML elements.', 'openpos' ),
                    'type'        => 'html'
                ),
                array(
                    'name'  => 'checkbox',
                    'label' => __( 'Checkbox', 'openpos' ),
                    'desc'  => __( 'Checkbox Label', 'openpos' ),
                    'type'  => 'checkbox'
                ),
                array(
                    'name'    => 'radio',
                    'label'   => __( 'Radio Button', 'openpos' ),
                    'desc'    => __( 'A radio button', 'openpos' ),
                    'type'    => 'radio',
                    'options' => array(
                        'yes' => 'Yes',
                        'no'  => 'No'
                    )
                ),
                array(
                    'name'    => 'selectbox',
                    'label'   => __( 'A Dropdown', 'openpos' ),
                    'desc'    => __( 'Dropdown description', 'openpos' ),
                    'type'    => 'select',
                    'default' => 'no',
                    'options' => array(
                        'yes' => 'Yes',
                        'no'  => 'No'
                    )
                ),
                array(
                    'name'    => 'password',
                    'label'   => __( 'Password', 'openpos' ),
                    'desc'    => __( 'Password description', 'openpos' ),
                    'type'    => 'password',
                    'default' => ''
                ),
                array(
                    'name'    => 'file',
                    'label'   => __( 'File', 'openpos' ),
                    'desc'    => __( 'File description', 'openpos' ),
                    'type'    => 'file',
                    'default' => '',
                    'options' => array(
                        'button_label' => 'Choose Image'
                    )
                )
            ),
            'wedevs_advanced' => array(
                array(
                    'name'    => 'color',
                    'label'   => __( 'Color', 'wedevs' ),
                    'desc'    => __( 'Color description', 'openpos' ),
                    'type'    => 'color',
                    'default' => ''
                ),
                array(
                    'name'    => 'password',
                    'label'   => __( 'Password', 'openpos' ),
                    'desc'    => __( 'Password description', 'openpos' ),
                    'type'    => 'password',
                    'default' => ''
                ),
                array(
                    'name'    => 'wysiwyg',
                    'label'   => __( 'Advanced Editor', 'wedevs' ),
                    'desc'    => __( 'WP_Editor description', 'wedevs' ),
                    'type'    => 'wysiwyg',
                    'default' => ''
                ),
                array(
                    'name'    => 'multicheck',
                    'label'   => __( 'Multile checkbox', 'openpos' ),
                    'desc'    => __( 'Multi checkbox description', 'openpos' ),
                    'type'    => 'multicheck',
                    'default' => array('one' => 'one', 'four' => 'four'),
                    'options' => array(
                        'one'   => 'One',
                        'two'   => 'Two',
                        'three' => 'Three',
                        'four'  => 'Four'
                    )
                ),
            ) */
        );

        $addon_setting = apply_filters('op_addon_setting',array(),$this);
        if(!empty($addon_setting)){
            $settings_fields['openpos_addon'] = $addon_setting;
        }
        $addition_general_setting = apply_filters('op_addition_general_setting',$addition_general_setting);
        $settings_fields['openpos_payment'] = array_merge($settings_fields['openpos_payment'],$addition_general_setting);
        return  apply_filters('op_setting_fields',$settings_fields);
    }

    function _nonce_check(){
        $nonce = isset($_REQUEST['op_nonce']) ? $_REQUEST['op_nonce'] : '';
        if ( !$nonce || ! wp_verify_nonce( $nonce, 'op_nonce' ) ) {
            die( __( 'Security check', 'openpos' ) ); 
        } 

    }
    function _role_check($role_name){
            die( __( 'No role access', 'openpos' ) ); 

    }

    public function products()
    {
        $this->_nonce_check();
        $rows = array();
        $allow_types = $this->core->getPosProductTypes();
        $current = isset($_REQUEST['current']) ? intval($_REQUEST['current']) : 1;
        $sort  = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : false;
        $searchPhrase  = $_REQUEST['searchPhrase'] ? sanitize_text_field($_REQUEST['searchPhrase']) : false;
        $sortBy = 'date';
        $order = 'DESC';
        if($sort && is_array($sort))
        {
            $key = array_keys($sort);

            $sortBy = end($key);
            if($sortBy == 'id')
            {
                $sortBy = 'ID';
            }
            $order = end($sort);
        }

        $rowCount = $_REQUEST['rowCount'] ? intval($_REQUEST['rowCount']) : get_option( 'posts_per_page' );
        $offet = ($current -1) * $rowCount;
        $ignores = apply_filters('admin_op_products_ignores',array(),$this);
    
        $args = array(
            'posts_per_page'   => $rowCount,
            'offset'           => $offet,
            'current_page'           => $current,
            'category'         => '',
            'category_name'    => '',
            'orderby'          => $sortBy,
            'order'            => $order,
            'exclude'          => $ignores,
            'post_type'        => $this->core->getPosPostType(),
            'post_status'      => $this->core->getDefaultProductPostStatus(),
            'suppress_filters' => false
        );
        $total = 0;
        $posts_array = array();
        if($searchPhrase)
        {
            $args['s'] = $searchPhrase;
            $product_id = $this->core->getProductIdByBarcode($searchPhrase);
        
            if($product_id){
                $_tmp_product = wc_get_product($product_id);
                $tmp_type = $_tmp_product->get_type();
                if($tmp_type == 'variable')
                {
                    
                    if(in_array('variation',$allow_types))
                    {
                        $childs = $_tmp_product->get_children();
                        foreach($childs as $child_id)
                        {
                            $post = get_post($child_id);
                            $posts_array[] = $post;
                        }
                    }
                    
                }else{
                    // if(in_array($tmp_type,$allow_types))
                    // {
                    //     $total = 1;
                    //     $_product = $_tmp_product;
                    //     $type = $_product->get_type();
                    //     $product_id = $_product->get_id();
                    //     $post = get_post($product_id);
                    //     $posts_array[] = $post;
                    // }
                }
            }else{
                
                if($_tmp_product = wc_get_product($searchPhrase))
                {
                    
                    $fields = array('post_title');
                    $tmp_type = $_tmp_product->get_type();
                    if(in_array($tmp_type,$allow_types))
                    {
                        $total = 1;
                        $_product = $_tmp_product;
                        $type = $_product->get_type();
                        $product_id = $_product->get_id();
                        $post = get_post($product_id);
                        $posts_array[] = $post;
                    }
                }else{
                    $product_id = wc_get_product_id_by_sku( $searchPhrase );
                    if($product_id)
                    {
                        $_tmp_product = wc_get_product($product_id);
                        $tmp_type = $_tmp_product->get_type();
                        if($tmp_type == 'variable')
                        {
                           
                            if(in_array('variation',$allow_types))
                            {
                                $childs = $_tmp_product->get_children();
                                foreach($childs as $child_id)
                                {
                                    $post = get_post($child_id);
                                    $posts_array[] = $post;
                                }
                            }
                           
                        }
                        
    
                    }
                }
            }
            

        }
        
        if(empty($posts_array))
        {
           
            $posts = $this->core->getProducts($args);
            $posts_array = $posts['posts'];
            $total = $posts['total'];
        }else{
            $total = count($posts_array);
        }
        
        $fields = array('post_title');
        foreach($posts_array as $post)
        {
            if(is_a($post, 'WP_Post'))
            {
                $product_id = $post->ID;
            }else{
                $product_id = $post->get_id();
                $post = get_post($product_id);
            }
            $_product = wc_get_product($product_id);
            if(!$_product)
            {
                continue;
            }
            $type = $_product->get_type();
            
            if(in_array($type,$allow_types))
            {
                $tmp = array();
                $thumb = '';
                $thumb_id = 0;
                if( wc_placeholder_img_src() ) {
                    $thumb = wc_placeholder_img();
                }
                $parent_product = false;
                foreach($fields as $field)
                {
                    $tmp[$field] = $post->$field;
                }
                if($tid = get_post_thumbnail_id($post->ID))
                {
                    $thumb_id = get_post_thumbnail_id($product_id);
                    $props = wc_get_product_attachment_props( $thumb_id, $post );
                    $thumb = get_the_post_thumbnail( $post->ID, 'shop_thumbnail', array(
                        'title'  => $props['title'],
                        'alt'    => $props['alt'],
                    ) );
                }
                $tmp['action'] = '<a href="'.get_edit_post_link($product_id).'">'.__('edit','openpos').'</a>';

                if($type == 'variation')
                {
                   $parent_id = $post->post_parent;
                    $parent_product = wc_get_product($parent_id);
                    if($tid = get_post_thumbnail_id($parent_id) && !$thumb_id)
                    {
                        $props = wc_get_product_attachment_props( get_post_thumbnail_id($parent_id), $parent_product );
                        $thumb = get_the_post_thumbnail( $parent_id, 'shop_thumbnail', array(
                            'title'  => $props['title'],
                            'alt'    => $props['alt'],
                        ) );
                    }
                    $tmp['action'] = '<a href="'.get_edit_post_link($parent_id).'">'.__('edit','openpos').'</a>';

                }
                $tmp['action'] .= '<a href="'.admin_url( 'admin.php?page=op-products&action=print-barcode&id='.$product_id ).'"  class="print-barcode-product-btn">'.__('Print Barcode','openpos').'</a>';
                //$tmp['action'] .= '<a href="'.admin_url( 'admin-ajax.php?action=print_barcode&id='.$product_id ).'" target="_blank" class="print-barcode-product-btn">Print Barcode</a>';
                $tmp['action'] = '<div class="action-row">'.$tmp['action'].'</div>';
                $tmp['regular_price'] = $_product->get_regular_price();
                $tmp['sale_price'] = $_product->get_sale_price();
                $price = $_product->get_price();
                $tmp['price'] = $price;
                $barcode = $this->core->getBarcode($product_id);
                $tmp['barcode'] = '<input type="text" name="barcode['.$product_id.']" class="form-control" disabled value="'.$barcode.'">';


                $sub_title = '';
                if($_product->get_type() == 'variation')
                {
                    $variation_attributes = $_product->get_attributes();
                    $variation_label_attributes = array();
                    foreach($variation_attributes as $vk =>$v)
                    {
                        $variation_label_attributes[] = $_product->get_attribute($vk);
                        
                    }
                    
                    $sub_title = '<p>'.implode(',',$variation_label_attributes).'</p>';
                }
                $tmp['post_title'] .= $sub_title;

                if(!$price)
                {
                    $price = 0;
                }

                $tmp['formatted_price'] = wc_price($price);
                $qty = $_product->get_stock_quantity();
                $manage_stock = $_product->get_manage_stock();
                if($manage_stock)
                {
                    $tmp['qty'] = '<div class="col-xs-6 pull-left"><input class="form-control"  disabled name="qty['.$product_id.']" type="number" value="'.$qty.'" /></div>';

                }else{
                    $tmp['qty'] = 'Unlimited';
                }
                $tmp['id'] = $product_id;

                $tmp['product_thumb'] = $thumb;
                $_tmp_product = apply_filters('op_admin_products_data',$tmp,$this);
                if($_tmp_product && !empty($_tmp_product))
                {
                    $rows[] = $_tmp_product;
                }
                

            }
        }
        
        $result = array(
            'current' => $current,
            'rowCount' => $rowCount,
            'rows' => $rows,
            'total' => $total

        );
        echo json_encode($result);
        exit;
    }

    public function stock_products_update(){
        global $op_warehouse;
        $params = $_REQUEST;
        $result = array('status' => 0, 'message' => '');
        $this->_nonce_check();
        $select_warehouse_id = isset($params['select_warehouse_id']) ? (int)$params['select_warehouse_id'] : -1;
        if(isset($params['field']) && $params['field'] && isset($params['id']))
        {
                $id = $params['id'];
                $product = wc_get_product($id);
                if($product)
                {
                    if($params['field'] == 'price' && $params['field_value'])
                    {
                        $product->set_price((float)$params['field_value']);
                        $product->set_regular_price((float)$params['field_value']);
                        $product->save();
                        $result['status'] = 1;
                    }

                }else{
                    $result['message'] = __('Product not found','openpos');
                }
        }else{
            $request_data = isset($_REQUEST['qty']) ? $_REQUEST['qty'] : array();
            $request_in_store = isset($_REQUEST['allow_pos']) ? $_REQUEST['allow_pos'] : array();
            $id = isset($params['id']) ? $params['id'] : 0;
            $all_store_ids = array();
            $in_store_ids = array();
            if($id )
            {
                $warehouses = $op_warehouse->warehouses();
                
                foreach($warehouses as $w)
                {
                    
                    $all_store_ids[] = $w['id'];
                    
                }
            }
            foreach($request_in_store as $product_id => $instore_data ){
               
                foreach($instore_data as $warehouse_id => $v)
                {
                    if($v == 'yes')
                    {
                        $in_store_ids[] = $warehouse_id;
                        $op_warehouse->add_instore($warehouse_id,$product_id);

                    }
                }
            }
            foreach($request_data as $product_id => $qty_data)
            {
                foreach($qty_data as $warehouse_id => $qty)
                {
                    if($warehouse_id > 0)
                    {
                        
                        if(isset($request_in_store[$product_id][$warehouse_id]) && $request_in_store[$product_id][$warehouse_id] == 'yes')
                        {
                            $op_warehouse->set_qty($warehouse_id,$product_id,(int)$qty);
                        }
                    }else{
                        $_product = wc_get_product($product_id);
                        $_product->set_stock_quantity($qty);
                        $_product->save();
                        $this->core->addProductChange($product_id,$warehouse_id);
                    }
                }
            }
            if($select_warehouse_id < 0)
            {
                $remove_instore = array_diff($all_store_ids,$in_store_ids);
                foreach($remove_instore as $warehouse_id)
                {
                    $op_warehouse->remove_instore($warehouse_id,$id);
                }
            }
            
            $result['status'] = 1;
        }
        do_action('stock_products_update_after',$params);
        echo json_encode($result);
        exit;
    }

    public function stock_products(){
        global $op_warehouse;
        $this->_nonce_check();
        $rows = array();
        $current = isset($_REQUEST['current']) ? intval($_REQUEST['current']) : 1;
        $warehouse_id = isset($_REQUEST['warehouse_id']) ? intval($_REQUEST['warehouse_id']) : -1;
        $sort  = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : false;
        $searchPhrase  = $_REQUEST['searchPhrase'] ? sanitize_text_field($_REQUEST['searchPhrase']) : false;
        $sortBy = 'date';
        $order = 'DESC';
        if($sort && is_array($sort))
        {
            $key = array_keys($sort);

            $sortBy = end($key);
            if($sortBy == 'id')
            {
                $sortBy = 'ID';
            }
            $order = end($sort);
        }


        $rowCount = $_REQUEST['rowCount'] ? intval($_REQUEST['rowCount']) : get_option( 'posts_per_page' );
        $offet = ($current -1) * $rowCount;

        
        $ignores = array();
       

        $args = array(
            'posts_per_page'   => $rowCount,
            'offset'           => $offet,
            'current_page'           => $current,
            'category'         => '',
            'category_name'    => '',
            'orderby'          => $sortBy,
            'order'            => $order,
            'exclude'          => $ignores,
            'post_type'        => $this->core->getPosPostType(),
            'post_status'      => $this->core->getDefaultProductPostStatus(),
            'suppress_filters' => false
        );
        $search_product_ids = array();
        if($searchPhrase)
        {

            $args['s'] = $searchPhrase;
            $tmp = (int)$searchPhrase;
            if($tmp)
            {
               $tmp_product = wc_get_product($tmp);
               if($tmp_product)
               {
                   $tmp_post = $tmp_product->get_type();
                   if($tmp_post != 'variable')
                   {
                       $search_product_ids[] = $tmp_product->get_id();
                   }

               }
            }
            if(empty($search_product_ids))
            {
                $product_id = wc_get_product_id_by_sku( $searchPhrase );
                if($product_id)
                {
                    $tmp_product = wc_get_product($product_id);
                    $tmp_post = $tmp_product->get_type();
                    if($tmp_post == 'variable')
                    {
                        $search_product_ids = $tmp_product->get_children();
                        
                    }
                }
            }

        }
        $posts_array = array();
        if(!empty($search_product_ids))
        {
            
            foreach($search_product_ids as $search_product_id){
                $posts_array[] = get_post($search_product_id);
            }
            $total = count($posts_array);

        }else{
            $args = apply_filters('op_load_stock_product_args',$args);
            
            $posts = $this->core->getProducts($args);
            $posts_array = $posts['posts'];

            $total = $posts['total'];
        }
       


        $fields = array('post_title');

        $warehouses = $op_warehouse->warehouses();
        
        foreach($posts_array as $post)
        {
            if(is_a($post, 'WP_Post'))
            {
                $product_id = $post->ID;
            }else{
                $product_id = $post->get_id();
                $post = get_post($product_id);
            }
            $_product = wc_get_product($product_id);
            if(!$_product)
            {
                continue;
            }
            $type = $_product->get_type();
            $allow_types = $this->core->getPosProductTypes();
            if(in_array($type,$allow_types))
            {
                $tmp = array();
                $thumb = '';
                if( wc_placeholder_img_src() ) {
                    $thumb = wc_placeholder_img();
                }

                foreach($fields as $field)
                {
                    $tmp[$field] = $post->$field;
                }

                $tid = get_post_thumbnail_id($post->ID);
                if($tid)
                {
                    $props = wc_get_product_attachment_props( get_post_thumbnail_id($product_id), $post );
                    $thumb = get_the_post_thumbnail( $post->ID, 'shop_thumbnail', array(
                        'title'  => $props['title'],
                        'alt'    => $props['alt'],
                    ) );
                }
                $tmp['action'] = '<a href="javascript:void(0)" class="update-row"  data-select_warehouse_id="'.$warehouse_id.'" data-id="'.$product_id.'">'.__('Update','openpos').'</a>';

                if($type == 'variation')
                {
                    $parent_id = $post->post_parent;
                    $parent_product = wc_get_product($parent_id);
                    if(!$tid)
                    {
                        if($tid = get_post_thumbnail_id($parent_id))
                        {
                            $props = wc_get_product_attachment_props( get_post_thumbnail_id($parent_id), $parent_product );
                            $thumb = get_the_post_thumbnail( $parent_id, 'shop_thumbnail', array(
                                'title'  => $props['title'],
                                'alt'    => $props['alt'],
                            ) );
                        }
                    }
                }

                $tmp['action'] = '<div class="action-row">'.$tmp['action'].'</div>';
                $tmp['regular_price'] = $_product->get_regular_price();
                $tmp['sale_price'] = $_product->get_sale_price();
                $price = $_product->get_price();
                $tmp['price'] = $price;
                $barcode = $this->core->getBarcode($product_id);
                $tmp['barcode'] = $barcode;
                $sub_title = '';
                if($_product->get_type() == 'variation')
                {
                    $variation_attributes = $_product->get_attributes();
                    $sub_title = '<p>'.implode(',',$variation_attributes).'</p>';
                }
                $tmp['post_title'] .= $sub_title;
                $tmp['post_title'] = '<div class="product-name">'. $tmp['post_title']. '</div>';
                if(!$price)
                {
                    $price = 0;
                }

                $tmp['formatted_price'] = '<div class="vna-row-price row"><div class="col-md-8 text-right"><input type="text" value="'.$price.'" class="row-price-input form-control" /><div class="row-price-span">'.wc_price($price).'</div> </div><div class="col-md-4 text-left"> <a href="javascript:void(0)" data-select_warehouse_id="'.$warehouse_id.'" data-id="'.$product_id.'" class="click-edit-price-a"><span class="glyphicon glyphicon-pencil"></span><span class="glyphicon glyphicon-saved"></span></a></div></div>';
                $qty = $_product->get_stock_quantity();

                $stock_manager = $_product->get_manage_stock() ? 'yes' : 'no';
                
                

                $tmp['qty_html'] = '<form id="product-row-'.$product_id.'">';
                $tmp['total_qty'] = 0;

                $no_stock_text = __('No manage','openpos');

                foreach($warehouses as $w)
                {
                    $tmp_qty_html = '';
                    $read_only = '';
                    $checked = 'checked';
                    if(!$op_warehouse->is_instore($w['id'],$product_id))
                    {
                        $checked = '';
                        $read_only = 'readonly';
                    }
                    if($warehouse_id == -1)
                    {

                        $qty = $op_warehouse->get_qty($w['id'],$product_id);
                        if(!$qty){
                            $qty = 0;
                        }

                        $tmp['total_qty']  += 1 * $qty;


                        if($w['id'] > 0)
                        {
                            if($qty === false)
                            {
                                $read_only = 'readonly';
                            }
                            if($stock_manager == 'yes'){
                                $tmp_qty_html .= '<div class="warehouse-product-qty"> <p>'.$w['name'].': </p><p><input '.$read_only.'  class="form-text-input input-control product-qty-warehouse" name="qty['.$product_id.']['.$w['id'].']" type="number" value="'.$qty.'"/><input class="input-control product-allow-warehouse" type="checkbox" name="allow_pos['.$product_id.']['.$w['id'].']" value="yes" '.$checked.' /></p></div>';
                            }else{
                                $tmp_qty_html .= '<div class="warehouse-product-qty"> <p>'.$w['name'].': </p><p><input '.$read_only.'  class="form-text-input input-control product-qty-warehouse" name="qty['.$product_id.']['.$w['id'].']" type="number"  disabled placeholder="'.$no_stock_text .'" /><input class="input-control product-allow-warehouse" type="checkbox" name="allow_pos['.$product_id.']['.$w['id'].']" value="yes" '.$checked.' /></p></div>';
                            }
                            
                        }else{
                            if($stock_manager == 'yes')
                            {
                                $tmp_qty_html .= '<div class="warehouse-product-qty"> <p>'.$w['name'].': </p><p><input class="form-text-input input-control product-qty-warehouse" name="qty['.$product_id.']['.$w['id'].']" type="number" value="'.$qty.'"/><input class="input-control product-allow-warehouse" type="checkbox" name="allow_pos['.$product_id.']['.$w['id'].']" value="yes" '.$checked.' /></p></div>';
                            }else{
                                $tmp_qty_html .= '<div class="warehouse-product-qty"> <p>'.$w['name'].': </p><p><input class="form-text-input input-control product-qty-warehouse" type="number" disabled placeholder="'.$no_stock_text .'"/><input class="input-control product-allow-warehouse" type="checkbox" name="allow_pos['.$product_id.']['.$w['id'].']" value="yes" '.$checked.' /></p></div>';
                            
                            }
                            
                        }

                    }else{
                        if($w['id'] == $warehouse_id)
                        {
                            $qty = $op_warehouse->get_qty($w['id'],$product_id);

                            $tmp['total_qty']  += $qty;

                            if($w['id'] > 0)
                            {
                                if($qty === false)
                                {
                                    $read_only = 'readonly';
                                }
                                if($stock_manager == 'yes')
                                {
                                    $tmp_qty_html .= '<div class="warehouse-product-qty"> <p>'.$w['name'].': </p><p><input '.$read_only.' class="form-text-input input-control product-qty-warehouse" name="qty['.$product_id.']['.$w['id'].']" type="number" value="'.$qty.'"/><input  class="input-control product-allow-warehouse" type="checkbox" name="allow_pos['.$product_id.']['.$w['id'].']" value="yes" '.$checked.' /></p></div>';
                                }else{
                                    $tmp_qty_html .= '<div class="warehouse-product-qty"> <p>'.$w['name'].': </p><p><input '.$read_only.' class="form-text-input input-control product-qty-warehouse" name="qty['.$product_id.']['.$w['id'].']" type="number" disabled placeholder="'.$no_stock_text .'" /><input  class="input-control product-allow-warehouse" type="checkbox" name="allow_pos['.$product_id.']['.$w['id'].']" value="yes" '.$checked.' /></p></div>';
                                }
                                
                            }else{
                            if($stock_manager == 'yes')
                            {
                                $tmp_qty_html .= '<div class="warehouse-product-qty"> <p>'.$w['name'].': </p><p><input class="form-text-input input-control product-qty-warehouse" name="qty['.$product_id.']['.$w['id'].']" type="number" value="'.$qty.'"/><input  class="input-control product-allow-warehouse" type="checkbox" name="allow_pos['.$product_id.']['.$w['id'].']" value="yes" '.$checked.' /></p></div>';
                            }else{
                                $tmp_qty_html .= '<div class="warehouse-product-qty"> <p>'.$w['name'].': </p><p><input class="form-text-input input-control product-qty-warehouse" disabled placeholder="'.$no_stock_text .'" type="number" /><input  class="input-control product-allow-warehouse" type="checkbox" name="allow_pos['.$product_id.']['.$w['id'].']" value="yes" '.$checked.' /></p></div>';
                            }
                                
                            }
                        }
                    }
                    $tmp['qty_html'] .= apply_filters('op_warehouse_stock_qty_html_input',$tmp_qty_html,$tmp,$w);


                }
                $tmp['qty_html'] .= '</form>';
                $tmp['id'] = $product_id;

                $tmp['product_thumb'] = '<div class="vna-cell-image"><a href="javascript:void(0);" class="upload-a" data-id="'.$product_id.'"><span class="glyphicon glyphicon-camera"></span></a>'.$thumb.'</div>';

                $_tmp_product =  apply_filters('op_warehouse_stock_row_data',$tmp,$post,$warehouse_id);
                if($_tmp_product && !empty($_tmp_product))
                {
                        $rows[] = $_tmp_product;
                }

                


            }


        }


        $result = array(
            'current' => $current,
            'rowCount' => $rowCount,
            'rows' => $rows,
            'total' => $total

        );
        echo json_encode($result);
        exit;
    }

    public function get_inventories(){
        global $op_warehouse;
        $this->_nonce_check();
        $warehouse_id = isset($_REQUEST['warehouse_id']) ? intval($_REQUEST['warehouse_id']) : 0;
        $rows = array();
        $current = isset($_REQUEST['current']) ? intval($_REQUEST['current']) : 1;
        $sort  = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : false;
        $searchPhrase  = $_REQUEST['searchPhrase'] ? sanitize_text_field($_REQUEST['searchPhrase']) : false;
        $sortBy = 'date';
        $order = 'DESC';
        if($sort && is_array($sort))
        {
            $key = array_keys($sort);

            $sortBy = end($key);
            if($sortBy == 'id')
            {
                $sortBy = 'ID';
            }
            $order = end($sort);
        }


        $rowCount = $_REQUEST['rowCount'] ? intval($_REQUEST['rowCount']) : get_option( 'posts_per_page' );
        $offet = ($current -1) * $rowCount;

        
       
        $ignores = array();
        

        $args = array(
            'posts_per_page'   => $rowCount,
            'offset'           => $offet,
            'current_page'           => $current,
            'category'         => '',
            'category_name'    => '',
            'orderby'          => $sortBy,
            'order'            => $order,
            'exclude'          => $ignores,
            'post_type'        => $this->core->getPosPostType(),
            'post_status'      => $this->core->getDefaultProductPostStatus(),
            'suppress_filters' => false
        );
        if($searchPhrase)
        {
            $args['s'] = $searchPhrase;
        }
        $final_args = apply_filters('admin_get_inventories_args',$args );

        $posts = $this->core->getProducts($final_args);
        $posts_array = $posts['posts'];
        $total = $posts['total'];
        $fields = array('post_title');
       
        foreach($posts_array as $post)
        {
            if(is_a($post, 'WP_Post'))
            {
                $product_id = $post->ID;
            }else{
                $product_id = $post->get_id();
                $post = get_post($product_id);
            }
            $_product = wc_get_product($product_id);
            if(!$_product)
            {
                continue;
            }    
            $type = $_product->get_type();
            $allow_types = $this->core->getPosProductTypes();
            if(in_array($type,$allow_types))
            {
                $tmp = array();
                $thumb = '';
                if( wc_placeholder_img_src() ) {
                    $thumb = wc_placeholder_img();
                }
                $parent_product = false;
                foreach($fields as $field)
                {
                    $tmp[$field] = $post->$field;
                }


                if($tid = get_post_thumbnail_id($post->ID))
                {
                    $props = wc_get_product_attachment_props( get_post_thumbnail_id($product_id), $post );
                    $thumb = get_the_post_thumbnail( $post->ID, 'shop_thumbnail', array(
                        'title'  => $props['title'],
                        'alt'    => $props['alt'],
                    ) );
                }
                $tmp['action'] = '<a href="'.get_edit_post_link($product_id).'">'.__('edit','openpos').'</a>';

                if($type == 'variation')
                {
                    $parent_id = $post->post_parent;
                    $parent_product = wc_get_product($parent_id);
                    if($tid = get_post_thumbnail_id($parent_id))
                    {
                        $props = wc_get_product_attachment_props( get_post_thumbnail_id($parent_id), $parent_product );
                        $thumb = get_the_post_thumbnail( $parent_id, 'shop_thumbnail', array(
                            'title'  => $props['title'],
                            'alt'    => $props['alt'],
                        ) );
                    }
                    $tmp['action'] = '<a href="'.get_edit_post_link($parent_id).'">'.__('edit','openpos').'</a>';

                }

                $tmp['regular_price'] = $_product->get_regular_price();
                $tmp['sale_price'] = $_product->get_sale_price();
                $price = $_product->get_price();
                $tmp['price'] = $price;
                $barcode = $this->core->getBarcode($product_id);
                $tmp['barcode'] = $barcode;

                if(!$price)
                {
                    $price = 0;
                }
                $tmp['formatted_price'] = wc_price($price);

                if($warehouse_id > 0)
                {

                    $qty = $op_warehouse->get_qty($warehouse_id,$product_id);
                    $manage_stock = true;

                }else{
                    $manage_stock = $_product->get_manage_stock();
                    $qty = $_product->get_stock_quantity();
                }
                if($manage_stock)
                {
                    $tmp['qty'] = '<div class="col-xs-6 pull-left"><input class="form-control"  disabled name="qty['.$product_id.']" type="text" value="'.$qty.'" /></div>';

                }else{
                    $tmp['qty'] = '<div class="col-xs-6 pull-left"><input class="form-control"  disabled name="qty['.$product_id.']" type="text" value="" /></div>';
                }
                $tmp['id'] = $product_id;

                $tmp['product_thumb'] = $thumb;


                $rows[] =  apply_filters('op_warehouse_inventory_row',$tmp,$warehouse_id,$post);


            }


        }


       
        $result = apply_filters('op_warehouse_inventory_result', array(
            'current' => $current,
            'rowCount' => $rowCount,
            'rows' => $rows,
            'total' => $total

        ));
        echo json_encode($result);
        exit;
    }
    public function transactions(){
        global $op_register;
        global $op_warehouse;
        $this->_nonce_check();
        $rows = array();

        $current = isset($_REQUEST['current']) ? intval($_REQUEST['current']) : 1;

        $sort  = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : false;
        $source_type  = isset($_REQUEST['source_type']) ? esc_attr($_REQUEST['source_type']) : '';

        $warehouse_id  = isset($_REQUEST['warehouse']) ? $_REQUEST['warehouse'] : 0;
        $register_id  = isset($_REQUEST['register']) ? $_REQUEST['register'] : 0;

        $searchPhrase  = $_REQUEST['searchPhrase'] ? sanitize_text_field($_REQUEST['searchPhrase']) : false;
        $sortBy = 'date';
        $order = 'DESC';
        if($sort && is_array($sort))
        {
            $key = array_keys($sort);

            $sortBy = end($key);
            if($sortBy == 'id')
            {
                $sortBy = 'ID';
            }
            $order = end($sort);
        }


        $rowCount = $_REQUEST['rowCount'] ? intval($_REQUEST['rowCount']) : get_option( 'posts_per_page' );

        $offet = ($current -1) * $rowCount;


        $args = array(
            'posts_per_page'   => $rowCount,
            'offset'           => $offet,
            'category'         => '',
            'category_name'    => '',
            'orderby'          => $sortBy,
            'order'            => $order,
            'post_type'        => array('op_transaction'),
            'post_status'      => 'any',
            'suppress_filters' => false
        );
        $meta_query = array();

        if($register_id)
        {
            $register_meta_key = $op_register->get_transaction_meta_key();
            $meta_query[] = array(
                    'key'     => $register_meta_key,
                    'value'   => $register_id,
                    'compare' => '=',
                );
        }
        if($warehouse_id)
        {
            $warehouse_meta_key = $op_warehouse->get_transaction_meta_key();
            $meta_query[] = array(
                'key'     => $warehouse_meta_key,
                'value'   => $warehouse_id,
                'compare' => '=',
            );
        }
        if($source_type)
        {
            $meta_query[] = array(
                'key'     => '_source_type',
                'value'   => $source_type,
                'compare' => '=',
            );
        }


        if(!empty($meta_query))
        {
            $args['meta_query'] = $meta_query;
        }

        if($searchPhrase)
        {
            $args['s'] = $searchPhrase;
        }
        $final_args = apply_filters('admin_transactions_args',$args );
        $get_posts = new WP_Query($final_args);
        $posts = array('total'=>$get_posts->found_posts,'posts' => $get_posts->get_posts());

        $posts_array = $posts['posts'];
        $total = $posts['total'];

        $cashdrawer_key = $op_register->get_transaction_meta_key();
        foreach($posts_array as $post)
        {
            $id = $post->ID;
            $user_id = get_post_meta($id,'_user_id',true);
            $register = 'Unknown';
            $name = 'Unknown';
            if($register_id = get_post_meta($id,$cashdrawer_key,true))
            {
                $register_details = $op_register->get($register_id);
                if($register_details && isset($register_details['name']))
                {
                    $register = $register_details['name'];
                }

            }
            if($user_id)
            {
                $user = get_user_by('ID',$user_id);
                $name = $user->display_name;
            }
            $method_code = get_post_meta($id,'_payment_code',true);
            $method_name = get_post_meta($id,'_payment_name',true);
            $currency = get_post_meta($id,'_currency',true);
            if(!$name)
            {
                $method_name = $method_code;
            }
            if(!$method_name)
            {
                $method_name = __('Cash','openpos');
            }
            $created_at_time =  get_post_meta($id,'_created_at',true);
            
            $created_at =  $this->core->render_ago_date_by_time_stamp($post->post_date);
            $created_at_html =  "<p>".$created_at."</p><p class='pos-local-time'>".$created_at_time."</p>";
            $in_amount = get_post_meta($id,'_in_amount',true);
            $out_amount = get_post_meta($id,'_out_amount',true);
            $in_amount_format = wc_price($in_amount);
            $out_amount_format = wc_price($out_amount);
            if($currency && isset($currency['code']))
            {
               
                $in_amount_format = wc_price($in_amount,array(
                    'currency' => $currency['code'],
                    'decimal_separator' => $currency['decimal_separator'],
                    'thousand_separator' => $currency['thousand_separator'],
                    'decimals' => $currency['decimal'],
                ));
                $out_amount_format = wc_price($out_amount,array(
                    'currency' => $currency['code'],
                    'decimal_separator' => $currency['decimal_separator'],
                    'thousand_separator' => $currency['thousand_separator'],
                    'decimals' => $currency['decimal'],
                ));
            }
            $tmp = array(
                'id' => $id,
                'title' => $post->post_title,
                'in_amount' => $in_amount_format,
                'out_amount'=> $out_amount_format,
                'payment_name'=> $method_name,
                'created_at'=> $created_at_html,
                'register' => $register,
                'created_by' => $name
            );
            $rows[] = $tmp;


        }


        $result = array(
            'current' => $current,
            'rowCount' => $rowCount,
            'rows' => $rows,
            'total' => $total

        );
        echo json_encode($result);
        exit;
    }

    public function orders(){
        global $op_register;
        global $op_warehouse;
        global $op_woo_order;
        $this->_nonce_check();
        $rows = array();

        $current = isset($_REQUEST['current']) ? intval($_REQUEST['current']) : 1;

        $sort  = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : false;
        $warehouse_id  = isset($_REQUEST['warehouse']) ? $_REQUEST['warehouse'] : 0;
        $register_id  = isset($_REQUEST['register']) ? $_REQUEST['register'] : 0;

        $searchPhrase  = $_REQUEST['searchPhrase'] ? sanitize_text_field($_REQUEST['searchPhrase']) : false;
        $sortBy = 'date';
        $order = 'DESC';
        if($sort && is_array($sort))
        {
            $key = array_keys($sort);

            $sortBy = end($key);
            if($sortBy == 'id')
            {
                $sortBy = 'ID';
            }
            $order = end($sort);
        }


        $rowCount = $_REQUEST['rowCount'] ? intval($_REQUEST['rowCount']) : get_option( 'posts_per_page' );

        $offet = ($current -1) * $rowCount;
        
        $post_statuses = apply_filters('op_orders_statuses', array(
            'wc-processing',
            'wc-pending',
            'wc-completed',
            'wc-refunded',
            'wc-on-hold',
        ));

        $args = array(
            'posts_per_page'   => $rowCount,
            'offset'           => $offet,
            'category'         => '',
            'category_name'    => '',
            'orderby'          => $sortBy,
            'order'            => $order,
            'post_type'        => array('shop_order'),
            'post_status'      => $post_statuses,
            'suppress_filters' => false
        );
        $meta_query = array();

        if($register_id)
        {
            $register_meta_key = $op_register->get_transaction_meta_key();
            $meta_query[] = array(
                'key'     => $register_meta_key,
                'value'   => $register_id,
                'compare' => '=',
            );
        }
        if($warehouse_id)
        {
            $warehouse_meta_key = $op_warehouse->get_transaction_meta_key();
            $meta_query[] = array(
                'key'     => $warehouse_meta_key,
                'value'   => $warehouse_id,
                'compare' => '=',
            );
        }

        $meta_query[] =  array(
            'key' => '_op_order_source',
            'value' => 'openpos',
            'compare' => '=',
        );
        

        
        


        if($searchPhrase)
        {
            $meta_query[] =  array(
                'key' => '_op_order_number_format',
                'value' => ltrim($searchPhrase, '#'),
                'compare' => 'LIKE',
            );
           // $args['post__in'] = [$searchPhrase];
        }
        if(!empty($meta_query))
        {
            $args['meta_query'] = $meta_query;
        }
        $final_args = apply_filters('admin_orders_args',$args );
        
        if($this->_enable_hpos)
        {
            $args['_query_src'] = 'op_order_query';
            $data_store = WC_Data_Store::load( 'order' );
            $orders = $data_store->query( $args );
            $total_orders = 0;
            foreach($post_statuses as $status)
            {
                $total_orders += $data_store->get_order_count($status);
            }
            $posts = array('total'=>$total_orders,'posts' => $orders);
        }else{
            $get_posts = new WP_Query($final_args);
            $posts = array('total'=>$get_posts->found_posts,'posts' => $get_posts->get_posts());
        }

        

        $posts_array = $posts['posts'];
        $total = $posts['total'];
        
        if($total === 0)
        {

            $tmp_order_id = $op_woo_order->get_order_id_from_order_number_format($searchPhrase);
            if($tmp_order_id)
            {
                $_tmp_post = get_post($tmp_order_id);
                
                $posts_array[] = $_tmp_post;
                $total = 1;
            }
        }
        if($total === 0)
        {

            $tmp_order_id = $op_woo_order->get_order_id_from_number($searchPhrase);
            if($tmp_order_id)
            {
                $_tmp_post = get_post($tmp_order_id);
                
                $posts_array[] = $_tmp_post;
                $total = 1;
            }
        }
        
        foreach($posts_array as $post)
        {
            if ( $post instanceof WC_Order )
            {
                $id = $post->get_id();
                $order = $post;
                $register_id = $order->get_meta('_pos_order_cashdrawer');
                $cashier_id = $order->get_meta('_op_sale_by_cashier_id');
                $_op_sale_by_person_id = $order->get_meta('_op_sale_by_person_id');
                $_op_order = $order->get_meta('_op_order');
                $view_url = $order->get_edit_order_url();
            }else{
                $id = $post->ID;
                $order = wc_get_order($id);
                if(!$order)
                {
                    continue;
                }
                $register_id = get_post_meta($id,'_pos_order_cashdrawer',true);
                $cashier_id = get_post_field( 'post_author', $id);
                $_op_sale_by_person_id = get_post_meta($id,'_op_sale_by_person_id',true);
                $_op_order =  get_post_meta($id,'_op_order',true);
                $view_url = $order->get_edit_order_url();
            }
            if(!$order)
            {
                continue;
            }
            
            $register_name = __('Unknown' , 'openpos');
            $register = $op_register->get($register_id);
            if(!empty($register))
            {
                $register_name = $register['name'];
            }

            
            $cashier = get_user_by('ID',$cashier_id);
            $cashier_name = 'unknown';

            if($cashier)
            {
                $cashier_name = $cashier->display_name;
            }
            $seller_name = $cashier_name;
            
            if($_op_sale_by_person_id)
            {
                $seller = get_user_by('ID',$_op_sale_by_person_id);
                if($seller)
                {
                    $seller_name = $seller->display_name;
                }
            }


            $by_html = '<p><b>C:</b> '.$cashier_name.'</p>';
            $by_html .= '<p><b>S:</b> '.$seller_name.'</p>';
            $created_at = $this->core->render_order_date_column($order);
            
            $created_at_local = '';
            if($_op_order && isset($_op_order['created_at']))
            {
                $created_at_local = $_op_order['created_at'];
            }
           
            $created_at_html = '<p>'.$created_at.'</p>';
            if($created_at_local)
            {
                $created_at_html .= '<p class="pos-local-time">'.$created_at_local.'</p>';
            }

            $status_html = '<span class="order_status '.esc_attr($order->get_status()).'">'.$order->get_status().'</span>';
            $order_number_str = '<p class="op-order-id">'. $id.'</p>';
            $order_number_str .= '<a class="op-order-number" href="'.esc_url($view_url).'">#'.$order->get_order_number().'</a>';
            
            $customer_name = $order->get_formatted_billing_address();
            if( !$customer_name)
            {
                $customer_name = __('Guest','openpos');
            }
            
            $tmp = array(
                'id' => $order_number_str,
                'order_number' => $order_number_str,
                'customer' => $customer_name,
                'source' => $register_name,
                'created_at' => $created_at_html,
                'total'=> $order->get_formatted_order_total(),
                'view_url'=> '<a href="'.esc_url($view_url).'" class="order-preview" data-order-id="666" title="Preview">Preview</a>',
                'created_by' => $by_html,
                'status' => $status_html

            );
            $rows[] = $tmp;


        }


        $result = array(
            'current' => $current,
            'rowCount' => $rowCount,
            'rows' => $rows,
            'total' => $total

        );
        echo json_encode($result);
        exit;
    }

    public function admin_style() {
        $info = $this->core->getPluginInfo();
        $allow_bootstrap = array('openpos-dasboard','op-products','op-cashiers','op-transactions','op-orders','op-reports','op-sessions','op-registers','op-warehouses','op-stock','op-setting','op-tables','op-receipt-template');
        $all_pos_page = array('op-products','op-cashiers','op-transactions','op-orders','op-reports','op-sessions','op-registers','op-warehouses','op-stock','op-setting','openpos-dasboard','op-tables','op-receipt-template');

        $current_page = isset( $_REQUEST['page'])  ?  esc_attr($_REQUEST['page']): false;


        if(in_array($current_page,$all_pos_page))
        {

            if(in_array($current_page,$allow_bootstrap))
            {
                wp_enqueue_style('openpos.bootstrap', OPENPOS_URL.'/assets/css/bootstrap.css','',$info['Version']);
                wp_enqueue_script('openpos.bootstrap', OPENPOS_URL.'/assets/js/bootstrap.min.js','jquery',$info['Version']);

            }
            if($current_page == 'openpos-dasboard' )
            {
                wp_enqueue_script('chart.js', OPENPOS_URL.'/assets/js/Chart.min.js',$info['Version']);
              
            }
            if($current_page == 'op-reports')
            {
                wp_enqueue_script('chart.js', OPENPOS_URL.'/assets/js/Chart.min.js',$info['Version']);
            }


            if($current_page == 'op-reports' || $current_page == 'op-sessions')
            {
                wp_enqueue_style('openpos.admin-jquery.datatable', OPENPOS_URL.'/assets/css/datatable.css','',$info['Version']);
                wp_enqueue_style('openpos.admin-jquery.datatable.bootstrap', OPENPOS_URL.'/assets/css/datatable-bootstrap.css','',$info['Version']);
                wp_enqueue_style( 'jquery-ui-style', WC()->plugin_url() . '/assets/css/jquery-ui/jquery-ui.min.css', array(), $info['Version'] );

                wp_enqueue_script('openpos.admin-jquery.datatable', OPENPOS_URL.'/assets/js/datatable.js',['jquery','jquery-ui-core','jquery-ui-datepicker'],$info['Version']);
                wp_enqueue_script('openpos.admin-jquery.datatable.jquery', OPENPOS_URL.'/assets/js/datatable.jquery.js','jquery',$info['Version']);
                wp_enqueue_script('openpos.admin-jquery.datatable.jquery.csv', OPENPOS_URL.'/assets/js/jquery.tabletoCSV.js','jquery',$info['Version']);
                wp_enqueue_script('openpos.kitchen.timeago', OPENPOS_URL.'/assets/js/jquery.timeago.js','',$info['Version']);

            }else{

                wp_enqueue_style('openpos.admin-jquery.bootgrid', OPENPOS_URL.'/assets/css/jquery.bootgrid.min.css','',$info['Version']);
                wp_enqueue_script('openpos.admin-jquery.bootgrid', OPENPOS_URL.'/assets/js/jquery.bootgrid.min.js','jquery',$info['Version']);
            }


            wp_enqueue_script('openpos.admin.js', OPENPOS_URL.'/assets/js/admin.js','jquery',$info['Version']);
            $vars['ajax_url'] = admin_url('admin-ajax.php');
            wp_localize_script('openpos.admin.js', 'openpos_admin', $vars);
        }

        
        
        wp_enqueue_style('openpos.admin', OPENPOS_URL.'/assets/css/admin.css','',$info['Version']);
        
        if($current_page == 'op-setting' )
        {
            // wp_dequeue_style( 'select2' );
            // wp_deregister_style( 'select2' );
    
            // wp_dequeue_script( 'select2');
            // wp_deregister_script('select2');
    
            // wp_dequeue_script( 'selectWoo');
            // wp_deregister_script('selectWoo');
        }
        if($current_page == 'op-tables')
        {
            wp_enqueue_style('openpos.admin.table.css', OPENPOS_URL.'/assets/css/admin-table.css',array('wp-jquery-ui-dialog'),$info['Version']);
            wp_enqueue_script('openpos.admin.table.js', OPENPOS_URL.'/assets/js/admin-table.js',array('jquery','jquery-ui-core','jquery-ui-dialog'),$info['Version']);
        
        }
        if($current_page == 'op-warehouses')
        {
            wp_enqueue_style('openpos.admin.warehouse.css', OPENPOS_URL.'/assets/css/admin-warehouse.css',array('wp-jquery-ui-dialog'),$info['Version']);
            wp_enqueue_script('openpos.admin.warehouse.js', OPENPOS_URL.'/assets/js/admin-warehouse.js',array('jquery','jquery-ui-core','jquery-ui-dialog'),$info['Version']);
        }

       
        
        

    }

    function add_store_setting_column($content,$column_name,$term_id){

        return $content;
    }

    function store_setting_column_header( $columns ){
        $columns['header_name'] = __( 'Action','openpos' );
        return $columns;
    }

    function store_setting_column_content( $value, $column_name, $tax_id ){

        $href = '';
        return '<a href="'.esc_url($href).'">'.__('Setting','openpos').'</a>';

    }

    public function get_pos_url($sub = ''){
       
        return  $this->core->get_pos_url($sub);
    }

    public function admin_bar_menus( $wp_admin_bar ) {
        if ( ! is_admin() || ! is_user_logged_in() ) {
            return;
        }
        // Show only when the user is a member of this site, or they're a super admin.
        if ( ! is_user_member_of_blog() && ! is_super_admin() ) {
            return;
        }
        $pos_url = $this->get_pos_url();
        // Add an option to visit the store.
        $wp_admin_bar->add_node( array(
            'parent' => 'site-name',
            'id'     => 'view-pos',
            'target'     => '_blank',
            'title'  => __( 'Visit POS', 'openpos' ),
            'href'   => $pos_url,
        ) );
    }

    function pos_admin_menu() {
        $openpos_type = $this->settings_api->get_option('openpos_type','openpos_pos');
        $page = add_menu_page( __( 'Open POS', 'openpos' ), __( 'POS', 'openpos' ),'manage_woocommerce','openpos-dasboard',array($this,'dashboard'),plugins_url('woocommerce-openpos/assets/images/pos.png'),59 );
        add_action( 'admin_print_styles-'. $page, array( &$this, 'admin_enqueue' ) );

        $page = add_submenu_page( 'openpos-dasboard', __( 'POS - Orders', 'openpos' ),  __( 'Orders', 'openpos' ) , 'manage_woocommerce', 'op-orders', array( $this, 'orders_page' ) );
        add_action( 'admin_print_styles-'. $page, array( &$this, 'admin_enqueue' ) );

        $page = add_submenu_page( 'openpos-dasboard', __( 'POS - Transactions', 'openpos' ),  __( 'Transactions', 'openpos' ) , 'manage_woocommerce', 'op-transactions', array( $this, 'transactions_page' ) );
        add_action( 'admin_print_styles-'. $page, array( &$this, 'admin_enqueue' ) );

        $page = add_submenu_page( 'openpos-dasboard', __( 'POS - Products', 'openpos' ),  __( 'Products Barcode', 'openpos' ) , 'manage_woocommerce', 'op-products', array( $this, 'products_page' ) );
        add_action( 'admin_print_styles-'. $page, array( &$this, 'admin_enqueue' ) );

        $page = add_submenu_page( 'openpos-dasboard', __( 'POS - Staffs', 'openpos' ),  __( 'Store Staff', 'openpos' ) , 'manage_options', 'op-cashiers', array( $this, 'cashier_page' ) );
        add_action( 'admin_print_styles-'. $page, array( &$this, 'admin_enqueue' ) );



        $page = add_submenu_page( 'openpos-dasboard', __( 'POS - Registers', 'openpos' ),  __( 'Registers', 'openpos' ) , 'manage_options', 'op-registers', array( $this, 'register_page' ) );
        add_action( 'admin_print_styles-'. $page, array( &$this, 'admin_enqueue' ) );

        $page = add_submenu_page( 'openpos-dasboard', __( 'POS - Outlets', 'openpos' ),  __( 'Outlets', 'openpos' ) , 'manage_options', 'op-warehouses', array( $this, 'warehouse_page' ) );
        add_action( 'admin_print_styles-'. $page, array( &$this, 'admin_enqueue' ) );

        if($openpos_type == 'restaurant')
        {
            $page = add_submenu_page( 'openpos-dasboard', __( 'POS - Tables', 'openpos' ),  __( 'Tables', 'openpos' ) , 'manage_options', 'op-tables', array( $this, 'table_page' ) );
            add_action( 'admin_print_styles-'. $page, array( &$this, 'admin_enqueue' ) );
            add_action( 'admin_print_scripts-'. $page, array( &$this, 'admin_tables_enqueue_scripts' ) );
        }

        $page = add_submenu_page( 'openpos-dasboard', __( 'POS - Stock Manager', 'openpos' ),  __( 'Stock Overview', 'openpos' ) , 'manage_woocommerce', 'op-stock', array( $this, 'stock_page' ) );
        add_action( 'admin_print_styles-'. $page, array( &$this, 'admin_enqueue' ) );

        $page = add_submenu_page( 'openpos-dasboard', __( 'POS - Reports', 'openpos' ),  __( 'Reports', 'openpos' ) , 'manage_woocommerce', 'op-reports', array( $this, 'report_page' ) );
        add_action( 'admin_print_styles-'. $page, array( &$this, 'admin_enqueue' ) );



        $page = add_submenu_page( 'openpos-dasboard', __( 'Receipt Templates', 'openpos' ),  __( 'Receipt Templates', 'openpos' ) , 'manage_options', 'op-receipt-template', array( $this, 'receipts_page' ) );
        add_action( 'admin_print_styles-'. $page, array( &$this, 'admin_receipt_enqueue' ) );

        $setting_page = add_submenu_page( 'openpos-dasboard', __( 'POS - Setting', 'openpos' ),  __( 'Setting', 'openpos' ) , 'manage_options', 'op-setting', array( $this, 'setting_page' ) );
        add_action( 'admin_print_styles-'. $setting_page, array( $this, 'admin_enqueue_setting' ) );

        $page = add_submenu_page( 'openpos-dasboard', __( 'POS - Sessions', 'openpos' ),  __( 'Login Sessions', 'openpos' ) , 'manage_options', 'op-sessions', array( $this, 'sessions_page' ) );
        add_action( 'admin_print_styles-'. $page, array( &$this, 'admin_enqueue' ) );

       
        

    }
    function sessions_page() {
        global $op_register;
        $sessions = $this->_session->getActiveSessions();
        $session_data = array();

       
        foreach($sessions as $key => $s)
        {
            $session_key = isset($s['session'] )  ? $s['session'] : '';
            $session_key = isset($s['session_id'] )  ? $s['session_id'] : $session_key;
            if(!$session_key)
            {
                continue;
            }
            if(strpos($session_key,'op-api-') === 0)
            {
                $tmp_location = ( isset($s['user']['location']) && $s['user']['location'] ) ?  json_decode(stripslashes($s['user']['location']),true): array();
    
                $location = __( 'Unknown', 'openpos' );
                if(!empty($tmp_location))
                {
                    $coord = implode('%2C',array($tmp_location['latitude'],$tmp_location['longitude']));
                    $url = 'https://maps.google.com/maps?q='.$coord.'&t=&z=13&ie=UTF8&iwloc=&output=embed';
                    $location = '<a href="javascript:void(0);" data-url="'.$url.'" class="session-location">'.__( 'View', 'openpos' ).'</a>';
                }

                $created_at = $s['user']['login_time'];
                $ip = $s['user']['login_ip'];
                $created_at = $this->core->render_ago_date_by_time_stamp($created_at);
                $_register_location = '';
                $tmp = array(
                    $session_key,
                    $s['user']['username'],
                    $created_at,
                    $ip,
                    $_register_location,
                    $location,
                    '<a href="javascript:void(0);" class="unlink-session" data-id="'.$session_key.'">'.__( 'Unlink', 'openpos' ).'</a>',
                );
    
                //$session_data[] = $tmp;
            }else{
                $login_cashdrawer_id = isset($s['login_cashdrawer_id']) ? $s['login_cashdrawer_id'] : 0;
                $tmp_location = ( isset($s['location']) && $s['location'] ) ?  json_decode(stripslashes($s['location']),true): array();
    
                $location = __( 'Unknown', 'openpos' );
                if(!empty($tmp_location))
                {
                    $coord = implode('%2C',array($tmp_location['latitude'],$tmp_location['longitude']));
                    $url = 'https://maps.google.com/maps?q='.$coord.'&t=&z=13&ie=UTF8&iwloc=&output=embed';
                    $location = '<a href="javascript:void(0);" data-url="'.$url.'" class="session-location">'.__( 'View', 'openpos' ).'</a>';
                }
    
                $ip = isset($s['ip']) ? $s['ip'] : '0.0.0.0';
                $_register_location = '';
    
                $register = $op_register->get($login_cashdrawer_id);
                if(!empty($register))
                {
                    $_register_location = $register['name'];
                }
                $created_at = $s['logged_time'];
                if(isset($s['logged_time_stamp']))
                {
                    $logged_time_stamp = $s['logged_time_stamp'];
                    $created_at = date('Y-m-d H:i:s',$logged_time_stamp);
                }
                
                $created_at = $this->core->render_ago_date_by_time_stamp($created_at);
                $tmp = array(
                    $session_key,
                    $s['username'],
                    $created_at,
                    $ip,
                    $_register_location,
                    $location,
                    '<a href="javascript:void(0);" class="unlink-session" data-id="'.$session_key.'">'.__( 'Unlink', 'openpos' ).'</a>',
                );
    
                $session_data[] = $tmp;
            }
            
        }

        require(OPENPOS_DIR.'templates/admin/sessions.php');
    }
    function session_unlink(){
        $ids = $_REQUEST['id'];
        $this->_nonce_check();
        foreach($ids as $id)
        {
            if($id)
            {
                $this->_session->clean($id);
            }
        }

        echo json_encode(array());
        exit;
    }

    function receipts_page(){
        global $op_receipt;
        $action = isset($_GET['op-action']) ? $_GET['op-action'] : '';
        $template_file = 'receipt_templates.php';
        $receipt_types = $op_receipt->receipt_types();
        switch($action)
        {
            case 'composer':
                $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
                $order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
                $default_paper_width = $this->settings_api->get_option('receipt_width','openpos_receipt');
                $default_paper_padding_top = $this->settings_api->get_option('receipt_padding_top','openpos_receipt');
                $default_paper_padding_right = $this->settings_api->get_option('receipt_padding_right','openpos_receipt');
                $default_paper_padding_bottom = $this->settings_api->get_option('receipt_padding_bottom','openpos_receipt');
                $default_paper_padding_left = $this->settings_api->get_option('receipt_padding_left','openpos_receipt');
                $template_details = $op_receipt->get($id);
                
                $paper_width = ($template_details['paper_width'])  ? $template_details['paper_width'] : $default_paper_width;
                $paper_padding_top =  ($template_details['padding_top'] || $template_details['padding_top'] === 0 )  ? $template_details['padding_top'] : $default_paper_padding_top;
                $paper_padding_right = ($template_details['padding_right'] || $template_details['padding_right'] === 0 )  ? $template_details['padding_right'] : $default_paper_padding_right;
                $paper_padding_bottom = ($template_details['padding_bottom'] || $template_details['padding_bottom'] === 0 )  ? $template_details['padding_bottom'] : $default_paper_padding_bottom;
                $paper_padding_left = ($template_details['padding_left'] || $template_details['padding_left'] === 0 )  ? $template_details['padding_left'] : $default_paper_padding_left;
                $template_content = $template_details['content'];
                $template_type = isset($template_details['type']) ? $template_details['type'] : 'receipt';
                $template_css = $template_details['custom_css'];
                $default = array(
                    'id' => $id,
                    'name' => get_the_title($id),
                    'type' => $template_type,
                    'order_id' => $order_id,
                    'paper_width' => $paper_width,
                    'padding_top' => $paper_padding_top,
                    'padding_right' => $paper_padding_right,
                    'padding_bottom' => $paper_padding_bottom,
                    'padding_left' => $paper_padding_left,
                    'content' => $template_content,
                    'custom_css' => $template_css,
                );
                $sample_template_url = OPENPOS_URL.'/default/receipt_template_sample.txt';
                $sample_css_url = OPENPOS_URL.'/default/receipt_template_css_sample.txt'; 
                
                if(isset($receipt_types[$template_type]))
                {
                    $receipt_type = $receipt_types[$template_type];
                    if(isset($receipt_type['sample_template']) && $receipt_type['sample_template']){
                        $sample_template_url = OPENPOS_URL.'/default/'.$receipt_type['sample_template'];
                    }
                    if(isset($receipt_type['sample_template_css']) && $receipt_type['sample_template_css']){
                        $sample_css_url = OPENPOS_URL.'/default/'.$receipt_type['sample_template_css']; 
                    }
                    
                }
                
                $template_file = 'receipt_template_composer.php';
                break;
          

        }
        require(OPENPOS_DIR.'templates/admin/'.$template_file);
    }

    function products_page() {
        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
        $template_path = OPENPOS_DIR.'templates/admin/products.php';
        if($action == 'print-barcode')
        {
            $template_path = OPENPOS_DIR.'templates/admin/print_barcode.php';
        }
        require( apply_filters('op_admin_template_products_page',$template_path,$action,$this));
        
    }
    function register_page() {
        require(OPENPOS_DIR.'templates/admin/registers.php');
    }
    function table_page(){
        require(OPENPOS_DIR.'templates/admin/tables.php');
    }
    function warehouse_page() {

        $action = isset($_GET['op-action']) ? $_GET['op-action'] : '';
        
        switch ($action)
        {
            case 'adjust_stock':
                require(OPENPOS_DIR.'templates/admin/warehouse/adjust_stock.php');
                break;
            case 'inventory':
                require(OPENPOS_DIR.'templates/admin/warehouse/inventory.php');
                break;
            case 'edit':
            case 'new':
                require(OPENPOS_DIR.'templates/admin/warehouse/new.php');
                break;
            default:
                require(OPENPOS_DIR.'templates/admin/warehouses.php');
                break;
        }

    }
    function stock_page() {
        $template_file =  apply_filters('op_admin_template_stock',OPENPOS_DIR.'templates/admin/stock.php');
        require($template_file);
    }

    public function dashboard()
    {
        global $op_woo;
        global $op_register;
        global $op_warehouse;
        global $op_report;
        $op_nonce = wp_create_nonce( 'op_nonce' );
        $duration = isset($_GET['duration']) ? $_GET['duration'] : 'today';
        if(!in_array($duration,['today','this_week','this_month','last_30_days','last_7_days','yesterday']) )
        {
            $duration = 'today';
            
        }
        
        $chart_data = array();
        $pos_url = $this->get_pos_url();
        
        $register_sales = array();
        $outlet_sales = array();
        $payment_sales = array();
        $seller_sales = array();

        $warehouse_meta_key = $op_warehouse->get_order_meta_key();
        $cashdrawer_meta_key = $op_register->get_order_meta_key();
       
        $orders = array();
        $total_transaction = 0;
        $transactions = array();
        array_unshift( $chart_data, array(
            __('Date','openpos'),
            __('Sales','openpos'),
            __('Transactions','openpos'),
            __('Profit','openpos'),
        ) );
        $pie_data = array();
        $dashboard_data = $this->dashboard_data(true);
        
        require(OPENPOS_DIR.'templates/admin/dashboard.php');
    }
    public function dashboard_ajax()
    {
        global $op_woo;
        global $op_register;
        global $op_warehouse;
        global $op_report;
        
        $duration = isset($_REQUEST['duration']) ? $_REQUEST['duration'] : 'today';
        if(!in_array($duration,['today','this_week','this_month','last_30_days','last_7_days','yesterday']) )
        {
            $duration = 'today';
            
        }
        

        $ranges = $this->core->getReportRanges($duration);
        $chart_data = array();
        
        
        $register_sales = array();
        $outlet_sales = array();
        $payment_sales = array();
        $seller_sales = array();

        $warehouse_meta_key = $op_warehouse->get_order_meta_key();
        $cashdrawer_meta_key = $op_register->get_order_meta_key();
       
        $orders = $this->core->getPosOrderByDate($ranges['start'],$ranges['end']);
        $total_transaction = 0;
        $transactions = $this->core->getPosTransactionsByDate($ranges['start'],$ranges['end']);
        foreach($orders as $_order_id)
        {
            $_order = new WC_Order($_order_id);
            if ( $_order instanceof WC_Order )
            {
                $order = $_order;
                $post_author_id = $order->get_meta('_op_sale_by_cashier_id');
                $warehouse_id = $order->get_meta($warehouse_meta_key);
                $register_id = $order->get_meta($cashdrawer_meta_key);
            }else{
                $order = new WC_Order($_order->ID);
                $post_author_id = get_post_field( 'post_author', $_order->ID );
                $warehouse_id = get_post_meta($order->get_id(),$warehouse_meta_key,true);
                $register_id = get_post_meta($order->get_id(),$cashdrawer_meta_key,true);
            }
            $created_at = $order->get_date_created();
            $customer_id = $order->get_customer_id();
            $author =   get_userdata($post_author_id);
            $author_name = 'Unknown';
            if($author)
            {
                $author_name = $author->display_name;
            }

            $created_at_time_mili = $order->get_meta('pos_created_at_time');
            $created_at = $order->get_date_created();
            
            if($created_at_time_mili)
            {
                $created_at_time = round($created_at_time_mili / 1000);

            }else{
                $created_at_time = strtotime($created_at);
            }
            $created_at_time_timestamp = strtotime(get_date_from_gmt(date( 'Y-m-d H:i:s', $created_at_time ),'Y-m-d H:i:s'));

            $grand_total = $order->get_total() - $order->get_total_refunded();
            $commision_total = $op_report->getSaleCommision($order);
            $tip_total = $op_report->getSaleTip($order);

            if(isset($register_sales[$register_id]))
            {
                $register_sales[$register_id] += $grand_total;
            }else{
                $register_sales[$register_id] = $grand_total;
            }

            if(isset($outlet_sales[$warehouse_id]))
            {
                $outlet_sales[$warehouse_id] += $grand_total;
            }else{
                $outlet_sales[$warehouse_id] = $grand_total;
            }

            $tmp_seller_sales = $op_report->getSellerSaleByOrder( $order);
            foreach($tmp_seller_sales as $user_id => $total)
            {
                if(isset($seller_sales[$user_id]))
                {
                    $seller_sales[$user_id]['total'] += $total;
                }else{
                    $_user = get_user_by('ID',$user_id);
                    $name = 'Unknown';
                    if($_user)
                    {
                        $name = $_user->display_name;
                        
                    }
                    $seller_sales[$user_id] = array(
                        'user_id' => $user_id,
                        'name' => $name,
                        'total' => $total
                    );
                }
            }

            foreach($ranges['ranges'] as $rk => $r)
            {

                $from_date_str = $r['from'];
                $to_date_str = $r['to'];
                $from_time = strtotime($from_date_str);
                $to_time = strtotime($to_date_str);
                if(!isset($chart_data[$rk]))
                {
                    $chart_data[$rk] = array(
                        $r['label'],
                        0,
                        0,
                        0,
                    );
                }
                if($created_at_time_timestamp > $from_time && $created_at_time_timestamp <= $to_time ){
                    $chart_data[$rk][1] += 1*$grand_total;
                    $chart_data[$rk][3] += 1*$commision_total;
                }
                
            }

            
        }

        
        array_unshift( $chart_data, array(
            __('Date','openpos'),
            __('Sales','openpos'),
            __('Transactions','openpos'),
            __('Profit','openpos'),
        ) );

        foreach($transactions as $s)
        {
            $in = 0;
            $out = 0;
            $in_value = get_post_meta($s->ID,'_in_amount',true);
            $out_value = get_post_meta($s->ID,'_out_amount',true);
            if($in_value !== false)
            {
                $in = 1 * (float)$in_value;
            }
            if($out_value !== false)
            {
                $out = 1 * (float)$out_value;
            }
            $_transaction_details = get_post_meta($s->ID,'_transaction_details',true);
            $_total = ($in - $out);
            if(isset($_transaction_details['source_type']) && $_transaction_details['source_type'] == 'order')
            {
                $payment_code = get_post_meta($s->ID,'_payment_code',true);
                $payment_name = get_post_meta($s->ID,'_payment_name',true);
                if(isset($payment_sales[$payment_code]))
                {
                    $payment_sales[$payment_code]['total'] += $_total;
                }else{
                    $payment_sales[$payment_code] = array(
                        'total' => $_total,
                        'code' => $payment_code,
                        'name' => $payment_name
                    );
                }
            }
            
            $total_transaction += $_total;
        }
        $pie_data = array();
        $pie_type = 'register';
        if(count($register_sales) > 0)
        {
            foreach($register_sales as $register_id => $register_sale)
            {
                $register = $op_register->get($register_id);
                $label = __('Unknown','openpos');
                if(isset($register['name']))
                {
                    $label = $register['name'];
                }
                
                $pie_data[] = array(
                    'label' => $register_id.' - '.$label.' ('.$op_woo->stripePriceTag($register_sale).')',
                    'sale' => $register_sale,
                    'type' => 'register'
                );
            }
        }
        if(count($outlet_sales) > 1)
        {
            $pie_type = 'outlet';
            $pie_data = array();
            foreach($outlet_sales as $outlet_id => $outlet_sale)
            {
                $outlet = $op_warehouse->get($outlet_id);
                $label = __('Unknown','openpos');
                if(isset($outlet['name']))
                {
                    $label = $outlet['name'];
                }
                
                

                $pie_data[] = array(
                    'label' => $outlet_id.' - '.$label.' ('.$op_woo->stripePriceTag($outlet_sale).')',
                    'sale' => $outlet_sale,
                    'type' => 'outlet'
                );
            }
        }

        $label = array();
        $sale_data = array();
        $transaction_data = array();
        $commision_data = array();
        foreach($chart_data as $index =>  $c)
        {
            if($index == 0)
            {
                continue;
            }
            $label[] = $c[0];
            $sale_data[] = round($c[1],wc_get_price_decimals());;
            $transaction_data[] = $c[2];
            $commision_data[] = round($c[3],wc_get_price_decimals());
        }
        
        $registe_data = array(
            'datasets' => array(
                [
                    'data' => array(),
                    'backgroundColor' => array(),
                ]
            ),
            'labels' => array(),
           
        );
       
        foreach($pie_data as $p){

            $registe_data['labels'][] = esc_html($p['label']);
            $registe_data['datasets'][0]['data'][] =  round($p['sale'],2);
            $registe_data['datasets'][0]['backgroundColor'][] =  '#'.$this->core->random_color();
        }
        $payment_data = array(
            'datasets' => array(
                [
                    'data' => array(),
                    'backgroundColor' => array(),
                ]
            ),
            'labels' => array(),
        );
        
        foreach($payment_sales as $p)
        {
            $payment_data['labels'][] = html_entity_decode(esc_html($p['name']));
            $payment_data['datasets'][0]['data'][] =  round($p['total'],2);
            $payment_data['datasets'][0]['backgroundColor'][] =  '#'.$this->core->random_color();
        }
        $seller_data = array(
            'labels' => array(),
            'datasets' => array(
                [
                    'label' => __('Sales','openpos'),
                    'data' => array(),
                    'backgroundColor' => array(),
                ]
            )
        );
        foreach($seller_sales as $p)
        {
            $seller_data['datasets'][0]['data'][] = round($p['total'],2); 
            $seller_data['datasets'][0]['backgroundColor'][] = '#'.$this->core->random_color();
            $seller_data['labels'][] = $p['name'];
        }

        
        $result = array(
            'sale_data' => array(
                'data' => $sale_data,
                'label' => $label,
                'commission_data' => $commision_data
            ),
            'register_data' => array(
                'data' => $registe_data,
                'label' =>  $pie_type == 'outlet' ? __('Sale by Outlet','openpos') : __('Sale by Register','openpos')
            ),
            'seller_data' => $seller_data,
            'payment_data' => $payment_data,
        );
        echo wp_json_encode($result);
        exit;
        
    }
    public function cashier_page()
    {
        require(OPENPOS_DIR.'templates/admin/cashier.php');
    }
    public function transactions_page()
    {
        require(OPENPOS_DIR.'templates/admin/transactions.php');
    }
    public function orders_page(){
        require(OPENPOS_DIR.'templates/admin/orders.php');
    }

    public function setting_page()
    {
        echo '<div class="op-wrap wrap op-admin-wrap">';
        $this->settings_api->show_navigation();
        $this->settings_api->show_forms();
        $this->settings_api->category_widget();
        echo '</div>';
    }

    public function report_ajax(){

        global  $op_warehouse;
        global $op_register;
        global $op_woo;
        global $op_report;
        global $op_transaction;
        global $op_woo_order;
        $this->_nonce_check();

        $report_type = isset($_REQUEST['report_type']) ? $_REQUEST['report_type'] : 'sales';
        $is_export = isset($_REQUEST['export']) ? $_REQUEST['export'] : 0;
        $report_duration =  isset($_REQUEST['report_duration']) ? $_REQUEST['report_duration'] : 'today';
        $report_action =  isset($_REQUEST['report_action']) ? $_REQUEST['report_action'] : '';
        $report_seller_id =  isset($_REQUEST['report_seller']) ? $_REQUEST['report_seller'] : 0;

        $time_offset_mins = 0;

        $report_outlet_id =  isset($_REQUEST['report_outlet']) ? $_REQUEST['report_outlet'] : -1;
        $report_register_id =  isset($_REQUEST['report_register']) ? $_REQUEST['report_register'] : 0;


        $custom_from =  isset($_REQUEST['custom_from']) ? $_REQUEST['custom_from'] : date('Y-m-d');
        $custom_to =  isset($_REQUEST['custom_to']) ? $_REQUEST['custom_to'] : date('Y-m-d');
        $registers = $op_register->registers();

        
        $decimals =  wc_get_price_decimals();
        if($report_action)
        {
            if($report_action == 'load_form')
            {
                $warehouse_id = isset($_REQUEST['report_outlet']) ? $_REQUEST['report_outlet'] : 0;
                $result_registers = array();
                foreach($registers as $register)
                {
                    if($register['status'] == 'publish' && $register['warehouse'] == $warehouse_id)
                    {
                        $result_registers[] = $register;
                    }
                }
                $cashiers = $op_woo->get_cashiers();
                $_form_result = array(
                    //'outlets' => array(),
                    'registers' => $result_registers,
                    'sellers' => $cashiers,
                );
                $form_result = apply_filters('report_ajax_load_form',$_form_result,$warehouse_id);
                echo json_encode($form_result);
            }else{
                $result = array();
                $ranges = array();
                try{
                    $total_page = 1;
                    $current_report_page = isset($_REQUEST['report_page']) ? $_REQUEST['report_page'] : 1;
                    if($report_duration == 'custom')
                    {
                        $gameFrom = $this->core->dateToUTC($custom_from);
                        $gameTo =   $this->core->dateToUTC($custom_to); 
                        $limit_duration = true;
                        if($report_type == 'debt_report')
                        {
                            $limit_duration = false;
                        }
                        $limit_duration = false;//test
                        
                        $ranges = $this->core->getReportRanges($report_duration,$gameFrom->toFormattedDateString('Y-m-d'),$gameTo->toFormattedDateString('Y-m-d'),0,$limit_duration);
                    }else{
                        $ranges = $this->core->getReportRanges($report_duration,false,false);
                    }
                    
                    if($time_offset_mins)
                    {
                        $ranges = $this->core->convertToUtc($ranges,$time_offset_mins);
                    }
                    
                    $total_page = count($ranges['ranges']);
                    $orders_table_data = array();
                    $orders_export_data = array();
                    
                    $chart_data = array();
                    $summary_html = '';
                    $summary_data = array(
                        'total_order' => 0,
                        'total_sales' => 0,
                        'total_transaction' => 0,
                        'total_transaction_cash' => 0
                    );
                    $table_label = array(
                        __('ID','openpos'),
                        __('Order','openpos'),
                        __('Grand Total','openpos'),
                        __('Profit Total','openpos'),
                        __('Tip Total','openpos'),
                        __('Cashier','openpos'),
                        __('Created At','openpos'),
                        __('View','openpos'),
                    );
                    $chart_data_js = null;
                    

                    switch ($report_type)
                    {
                        case 'transactions':
                            $table_label = array(
                                __('#','openpos'),
                                __('Ref','openpos'),
                                __('IN','openpos'),
                                __('OUT','openpos'),
                                __('Method','openpos'),
                                __('Created At','openpos'),
                                __('Created By','openpos')
                            );
                            $table_label_export = array(
                                __('#','openpos'),
                                __('Ref','openpos'),
                                __('IN','openpos'),
                                __('OUT','openpos'),
                                __('Method','openpos'),
                                __('Currency','openpos'),
                                __('Created At','openpos'),
                                __('Created By','openpos')
                            );
                            $orders_export_data[] = $table_label_export;
                            $chart_data = array();
                            if($report_register_id > 0)
                            {
                                $transactions = $this->core->getPosRegisterTransactionsByDate($report_register_id,$ranges['start'],$ranges['end']);
                            }elseif($report_outlet_id >= 0)
                            {
                                $transactions = $this->core->getPosWarehouseTransactionsByDate($report_outlet_id,$ranges['start'],$ranges['end']);
                            }else{
                                $transactions = $this->core->getPosTransactionsByDate($ranges['start'],$ranges['end']);
                            }
                            $total_in = 0;
                            $total_out = 0;
                            
                            foreach($transactions as $_transaction)
                            {
                                $id = $_transaction->ID;

                                $created_at_str = get_the_date('Y-m-d H:i:s',$id);
                                
                                $created_at_time = get_post_meta($_transaction->ID,'_created_at_time',true);
                                if($created_at_time)
                                {
                                    $created_at_time = round($created_at_time / 1000);
                                }else{
                                    $created_at_time = strtotime($created_at_str);
                                }

                                $user_id = get_post_meta($id,'_user_id',true);
    
                                $name = 'Unknown';
                                if($user_id)
                                {
                                    $user = get_user_by('ID',$user_id);
                                    $name = $user->display_name;
                                }
                                $in_amount = get_post_meta($id,'_in_amount',true);
                                $out_amount = get_post_meta($id,'_out_amount',true);
                                $method = get_post_meta($id,'_payment_name',true);
                                $currency = get_post_meta($id,'_currency',true);
                                if(!$method)
                                {
                                    $method = __('Cash','openpos');
                                }
                                $chart_type = 'pie';
                                if(!isset($chart_data[$method]))
                                {
                                    $chart_data[$method]= 0;
                                }
                                $chart_data[$method] += ((1*$in_amount - 1*$out_amount));
                                
                                
                                $in_amount_format = wc_price($in_amount);
                                $out_amount_format = wc_price($out_amount);
                                $currency_code = get_woocommerce_currency();
                                if($currency && isset($currency['code']))
                                {
                                
                                    $in_amount_format = wc_price($in_amount,array(
                                        'currency' => $currency['code'],
                                        'decimal_separator' => $currency['decimal_separator'],
                                        'thousand_separator' => $currency['thousand_separator'],
                                        'decimals' => $currency['decimal'],
                                    ));
                                    $out_amount_format = wc_price($out_amount,array(
                                        'currency' => $currency['code'],
                                        'decimal_separator' => $currency['decimal_separator'],
                                        'thousand_separator' => $currency['thousand_separator'],
                                        'decimals' => $currency['decimal'],
                                    ));
                                    $currency_code = $currency['code'];
                                    $currency_rate = $currency['rate'] ? 1*$currency['rate'] : 1;

                                    $total_in += $in_amount / $currency_rate;
                                    $total_out += $out_amount/ $currency_rate;
                                }else{
                                    $total_in += $in_amount;
                                    $total_out += $out_amount;
                                }

                                $tmp = array(
                                    $id,
                                    $_transaction->post_title,
                                    $in_amount_format ,
                                    $out_amount_format,
                                    $method,
                                    get_post_meta($id,'_created_at',true),
                                    $name
                                );
                                $orders_table_data[] = $tmp;
                                $tmp_export = array(
                                    $id,
                                    $_transaction->post_title,
                                    $in_amount,
                                    $out_amount,
                                    $method,
                                    $currency_code,
                                    get_post_meta($id,'_created_at',true),
                                    $name
                                );
                                $orders_export_data[] = $tmp_export;
    
                            }
                            if(!empty($chart_data) )
                            {
                               
                                $chart_data_js = array(
                                    'type' => 'bar',
                                    'data'=> array(
                                        'labels' => array(),
                                        'datasets' => array()
                                    )
                                );
                                
                                $chart_data_js['data']['datasets'][0]['label'] = 'Transaction by payment method';
                                $chart_data_js['data']['datasets'][0]['borderColor'] = 'rgba(75, 192, 192, 1)';
                                $chart_data_js['data']['datasets'][0]['backgroundColor'] = 'rgba(75, 192, 192, 1)';

                                $new_chart_data = array();
                               
                                $new_chart_label = array();

                                foreach($chart_data as $m => $c)
                                {
                                    $new_chart_label[] = $m;

                                   
                                    $chart_data_js['data']['datasets'][0]['data'][] = $c;
                                }

                                
                                $chart_data_js['data']['labels'] = $new_chart_label;
                                $chart_data_js['data']['borderColor'] = $new_chart_label;
                               
                                
                                
                            }
                            $summary_html = '';
                            $title =  __('Total IN','openpos');
                            $value = wc_price($total_in);
                            $summary_html .= '<div class="col-md-3 col-log-3 col-sm-3 col-xs-3">';
                            $summary_html .= '<div class="summary-block">';
                            $summary_html .= '<dl>';
                            $summary_html .= '<dt>'.$title.'</dt>';
                            $summary_html .= '<dd>'.$value.'</dd>';
                            $summary_html .= '</dl>';
                            $summary_html .= '</div>';
                            $summary_html .= '</div>';
    
                            $title =  __('Total OUT','openpos');
                            $value = wc_price($total_out);
                            $summary_html .= '<div class="col-md-3 col-log-3 col-sm-3 col-xs-3">';
                            $summary_html .= '<div class="summary-block">';
                            $summary_html .= '<dl>';
                            $summary_html .= '<dt>'.$title.'</dt>';
                            $summary_html .= '<dd>'.$value.'</dd>';
                            $summary_html .= '</dl>';
                            $summary_html .= '</div>';
                            $summary_html .= '</div>';
                            break;
                        case 'sale_by_agent':
                            $table_label = array(
                                __('Order','openpos'),
                                __('Grand Total','openpos'),
                                __('Seller Amount','openpos'),
                                __('Tip Amount','openpos'),
                                __('Cashier','openpos'),
                                __('Created At','openpos'),
                                __('View','openpos'),
                            );
                            
                            $is_all = false;
                            $orders_export_data = array();
                            if($report_seller_id == '_all')
                            {
                                $is_all = true;
                                $table_label = array(
                                    __('User','openpos'),
                                    __('Email','openpos'),
                                    __('Sold Total','openpos'),
                                    __('Sold QTY','openpos'),
                                    __('Total Order','openpos'),
                                    __('Total Tip','openpos'),
                                );
                               
                                $orders_export_data[] = array(
                                    __('User','openpos'),
                                    __('Email','openpos'),
                                    __('Sold Total','openpos'),
                                    __('Sold QTY','openpos'),
                                    __('Total Order','openpos'),
                                    __('Total Tip','openpos'),
                                );
                            }else{
                                
                                if($report_seller_id)
                                {
                                    $seller_name = 'Unknown';
                                    $seller_user = get_user_by('id',$report_seller_id);
                                    
                                    
                                    if($seller_user)
                                    {
                                        $seller_user_data = $seller_user->data;
                                        $seller_name = $seller_user_data->user_nicename ? $seller_user_data->user_nicename : $seller_user_data->user_login;
                                    }
                                    
                                    $orders_export_data[] = array(
                                        __('User','openpos'),
                                        $seller_name,
                                       '',
                                       '',
                                       '',
                                       '',
                                    );
                                }
                                $orders_export_data[] = array(
                                    __('Order','openpos'),
                                    __('Grand Total','openpos'),
                                    __('Seller Amount','openpos'),
                                    __('Tip Amount','openpos'),
                                    __('Cashier','openpos'),
                                    __('Created At','openpos')
                                );
                               
                                
                            }
    
                            if($report_seller_id && !$is_all)
                            {
                                $chart_data[] = array(
                                    __('Date','openpos'),
                                    __('Sales','openpos')
                                );
                                
                                $orders = $this->core->getPosOrderByDate($ranges['start'],$ranges['end']);
                                $summary_data['total_order'] = count($orders);
                                $summary_data['total_qty']  = 0;
                                $summary_data['total_tip']  = 0;

                                $chart_data_js = array(
                                    'type' => 'line',
                                    'data' => array(
                                        'labels' => array(),
                                        'datasets' => array()
                                    ),
                                    
                                );
                                foreach($ranges['ranges'] as $rk => $r){
                                    $chart_data_js['data']['labels'][] = $r['label'];
                                    $chart_data_js['data']['datasets'][0]['label'] =  __('Sales','openpos');
                                    $chart_data_js['data']['datasets'][0]['data'][] = 0;
                                }
                                foreach($orders as $_order_id)
                                {
                                    $order = new WC_Order($_order_id);
                                    // if ( $_order instanceof WC_Order )
                                    // {
                                    //     $order = $_order;
                                    // }else{
                                    //     $order = new WC_Order($_order->ID);
                                    // }

                                   
                                    $items = $order->get_items();
                                    $is_sale_by_seller = false;
                                    $seller_order_sale = 0;
                                    $_agent_sale_id = $order->get_meta('_op_sale_by_cashier_id');
    
                                    if($_agent_sale_id && $_agent_sale_id == $report_seller_id)
                                    {
                                        $is_sale_by_seller = true;
                                        $summary_data['total_sales'] += $order->get_total() - $order->get_total_refunded();
                                        $seller_order_sale += $order->get_total();
    
                                        $tip_total =$op_report->getSaleTip($order);
                                        $summary_data['total_tip']  +=  $tip_total;
    
                                        foreach($items as $item)
                                        {
                                           $summary_data['total_qty'] += $item->get_quantity();
                                        }
    
    
                                       
    
                                        $post_author_id = $_agent_sale_id;
                                        $author =   get_userdata($post_author_id);
                                        $author_name = 'Unknown';
                                        if($author)
                                        {
                                            $author_name = $author->display_name;
                                        }
                                        $grand_total = $order->get_total() - $order->get_total_refunded();


                                        $created_at = $order->get_date_created();
                                        $created_at_str = $created_at->format('Y-m-d H:i:s');
                                        $created_at_time = strtotime($created_at_str);
                                        $counter = 0;
                                        foreach($ranges['ranges'] as $rk => $r){
                                            $from_date_str = $r['from'];
                                            $to_date_str = $r['to'];
                                            $from_time = strtotime($from_date_str);
                                            $to_time = strtotime($to_date_str);
                                           
                                            if($created_at_time > $from_time && $created_at_time < $to_time ){
                                                 
                                                 $chart_data_js['data']['datasets'][0]['data'][$counter] +=  1 * $grand_total;
                                            }
                                            $counter++;
                                        }
                                        
    
                                        $orders_table_data[] = array(
                                            $order->get_order_number(),
                                            strip_tags(wc_price($grand_total)),
                                            strip_tags(wc_price($seller_order_sale)),
                                            strip_tags(wc_price($tip_total )),
                                            $author_name,
                                            wc_format_datetime( $created_at ).' '.wc_format_datetime( $created_at, get_option( 'time_format' ) ) ,
                                            '<a target="_blank" href="'.esc_url($order->get_edit_order_url()).'">'.__( 'View', 'openpos' ) .'</a>'
                                        );
    
                                        $tmp_export = array(
                                            $order->get_order_number(),
                                            $grand_total,
                                            $seller_order_sale,
                                            $tip_total,
                                            $author_name,
                                            wc_format_datetime( $created_at ).' '.wc_format_datetime( $created_at, get_option( 'time_format' ) )
                                        );
                                        $orders_export_data[] = $tmp_export;
                                    }
    
    
                                }
                                $summary_html = '';
                                foreach($summary_data as $k => $v)
                                {
                                    if(in_array($k,array('total_sales','total_qty','total_tip')))
                                    {
                                        $title =  __('Total Qty','openpos');
                                        $value = $v;
                                        if($k == 'total_sales')
                                        {
                                            $title =  __('Total Sales','openpos');
                                            $value = wc_price($v);
                                        }
                                        if($k == 'total_tip')
                                        {
                                            $title =  __('Total Tip','openpos');
                                            $value = wc_price($v);
                                        }
    
                                        $summary_html .= '<div class="col-md-3 col-log-3 col-sm-3 col-xs-3">';
                                        $summary_html .= '<div class="summary-block">';
                                        $summary_html .= '<dl>';
                                        $summary_html .= '<dt>'.$title.'</dt>';
                                        $summary_html .= '<dd>'.$value.'</dd>';
                                        $summary_html .= '</dl>';
                                        $summary_html .= '</div>';
                                        $summary_html .= '</div>';
                                    }
                                }
                            }
                            if($is_all)
                            {
    
                                $all_sellers_id = array();
    
                                $all_seller_report = $op_report->getSaleByCashierReport($all_sellers_id,$ranges['start'],$ranges['end']);
                                $summary_data = array(
                                    'total_sales' => 0,
                                    'total_qty' => 0,
                                    'total_order' => 0,
                                    'total_tip' => 0,
                                );
                                $chart_data_js = array(
                                    'type' => 'horizontalBar',
                                    'data' => array(
                                        'labels' => array(),
                                        'datasets' => array()
                                    ),
                                    
                                );
                                foreach($all_seller_report as $user_id => $report_data)
                                {
                                    $summary_data['total_sales'] += $report_data['total_sale'];
                                    $summary_data['total_qty'] += $report_data['total_qty'];
                                    $summary_data['total_order'] += $report_data['total_order'];
                                    $summary_data['total_tip'] += $report_data['total_tip'];
                                    $user_name = __('Unknown');
                                    $user_email = __('Unknown');
                                    if($user_id)
                                    {
                                        $author_obj = get_user_by('id', $user_id);
                                        if($author_obj)
                                        {
                                            $user_name = $author_obj->user_nicename ? $author_obj->user_nicename : $author_obj->user_login;
                                            $user_email = $author_obj->user_email;
                                        }
    
                                    }
                                    $orders_table_data[] = array(
                                        $user_name,
                                        $user_email,
                                        wc_price($report_data['total_sale']),
                                        $report_data['total_qty'],
                                        $report_data['total_order'],
                                        $report_data['total_tip'],
                                    );
                                    $tmp_export = array(
                                        $user_name,
                                        $user_email,
                                        $report_data['total_sale'],
                                        $report_data['total_qty'],
                                        $report_data['total_order'],
                                        $report_data['total_tip'],
                                    );
                                    $orders_export_data[] = $tmp_export;

                                    $chart_data_js['data']['labels'][] = $user_name;
                                    $chart_data_js['data']['datasets'][0]['label'] = __('Cashier sales','openpos');
                                    $chart_data_js['data']['datasets'][0]['backgroundColor'][] = '#0064d1';
                                    $chart_data_js['data']['datasets'][0]['data'][] = $report_data['total_sale'];
                                }
                                
                                $summary_html = '';
    
                                foreach($summary_data as $k => $v)
                                {
                                    if(in_array($k,array('total_sales','total_qty','total_order','total_tip')))
                                    {
                                        $title =  __('Total Qty','openpos');
                                        $value = $v;
                                        if($k == 'total_sales')
                                        {
                                            $title =  __('Total Sales','openpos');
                                            $value = wc_price($v);
                                        }
                                        if($k == 'total_tip')
                                        {
                                            $title =  __('Total Tip','openpos');
                                            $value = wc_price($v);
                                        }
                                        if($k == 'total_order')
                                        {
                                            $title =  __('Total Orders','openpos');
                                           
                                        }
                                        $summary_html .= '<div class="col-md-3 col-log-3 col-sm-3 col-xs-3" style="margin-bottom: 15px;">';
                                        $summary_html .= '<div class="summary-block">';
                                        $summary_html .= '<dl>';
                                        $summary_html .= '<dt>'.$title.'</dt>';
                                        $summary_html .= '<dd>'.$value.'</dd>';
                                        $summary_html .= '</dl>';
                                        $summary_html .= '</div>';
                                        $summary_html .= '</div>';
                                    }
                                }
                            }
    
                            break;
                        case 'sale_by_seller':
                            $table_label = array(
                                __('Order','openpos'),
                                __('Grand Total','openpos'),
                                __('Seller Amount','openpos'),
                                __('Cashier','openpos'),
                                __('Created At','openpos'),
                                __('View','openpos'),
                            );
                           
                            $is_all = false;
                            $orders_export_data = array();
                            if($report_seller_id == '_all')
                            {
                                $is_all = true;
                                $table_label = array(
                                    __('User','openpos'),
                                    __('Email','openpos'),
                                    __('Sold Total','openpos'),
                                    __('Sold QTY','openpos'),
                                    __('Total Order','openpos'),
                                );
                                $orders_export_data[] = array(
                                    __('User','openpos'),
                                    __('Email','openpos'),
                                    __('Sold Total','openpos'),
                                    __('Sold QTY','openpos'),
                                    __('Total Order','openpos'),
                                );
                            }else{
                               
                                if($report_seller_id)
                                {
                                    $seller_name = 'Unknown';
                                    $seller_user = get_user_by('id',$report_seller_id);
                                    
                                    
                                    if($seller_user)
                                    {
                                        $seller_user_data = $seller_user->data;
                                        $seller_name = $seller_user_data->user_nicename ? $seller_user_data->user_nicename : $seller_user_data->user_login;
                                    }
                                    
                                    $orders_export_data[] = array(
                                        __('User','openpos'),
                                        $seller_name,
                                       '',
                                       '',
                                       '',
                                    );
                                }
                               
                                $orders_export_data[] = array(
                                    __('Order','openpos'),
                                    __('Grand Total','openpos'),
                                    __('Seller Amount','openpos'),
                                    __('Cashier','openpos'),
                                    __('Created At','openpos')
                                );
                            }
                            
                            if($report_seller_id && !$is_all)
                            {
                                
                                $chart_data[] = array(
                                    __('Date','openpos'),
                                    __('Sales','openpos')
                                );
                               
                                $orders = $this->core->getPosOrderByDate($ranges['start'],$ranges['end']);
                                $summary_data['total_order'] = count($orders);
                                $summary_data['total_qty'] = 0;
                                $summary_data['total_order'] = 0;
                                $effect_orders = array();

                                $chart_data_js = array(
                                    'type' => 'line',
                                    'data' => array(
                                        'labels' => array(),
                                        'datasets' => array()
                                    ),
                                    
                                );
                                foreach($ranges['ranges'] as $rk => $r){
                                    $chart_data_js['data']['labels'][] = $r['label'];
                                    $chart_data_js['data']['datasets'][0]['label'] =  __('Sales','openpos');
                                    $chart_data_js['data']['datasets'][0]['data'][] = 0;
                                }

                                foreach($orders as $_order_id)
                                {
                                    $order = new WC_Order($_order_id);
                                    // if ( $_order instanceof WC_Order )
                                    // {
                                    //     $order = $_order;
                                    // }else{
                                    //     $order = new WC_Order($_order->ID);
                                    // }
                                    $order_id = $order->get_id();
                                    $items = $order->get_items();
                                    $is_sale_by_seller = false;
                                    $seller_order_sale = 0;
                                    $_op_sale_by_person_id = $order->get_meta('_op_sale_by_person_id');
    
                                    
                                    
                                    if($report_seller_id == $_op_sale_by_person_id)
                                    {
    
                                        $partial_sale = 0;
                                        $partial_all_qty = 0;
                                        $partial_qty = 0;
                                        $has_parital = false;
                                        foreach($items as $item)
                                        {
                                            $_item_sale_id = $item->get_meta('_op_seller_id');
                                            $item_qty = $item->get_quantity();
                                            $partial_all_qty += $item_qty;
    
    
                                            if($_item_sale_id > 0 && $_item_sale_id != $_op_sale_by_person_id)
                                            {
                                                $has_parital = true;
                                            }
                                            if( $_item_sale_id == $report_seller_id )
                                            {
                                                $item_data = $item->get_data();
                                                $partial_sale += $item_data['subtotal'];
                                                $partial_qty += $item_qty;
                                            }
                                        }
    
    
                                        if(!$has_parital)
                                        {
                                            $is_sale_by_seller = true;
                                            $seller_order_sale += $order->get_total() - $order->get_total_refunded();
                                            $summary_data['total_sales'] += $order->get_total() - $order->get_total_refunded();
                                            $summary_data['total_qty'] += $partial_all_qty;
                                            
                                        }else{
                                            if($partial_sale > 0)
                                            {
                                                $is_sale_by_seller = true;
                                                $summary_data['total_sales'] += $partial_sale;
                                                $summary_data['total_qty'] += $partial_qty;
                                                $seller_order_sale += $partial_sale;
                                                
                                            }
    
                                        }
    
                                    }else{
                                        foreach($items as $item)
                                        {
                                            $_item_sale_id = $item->get_meta('_op_seller_id');
                                            if($_item_sale_id && $_item_sale_id == $report_seller_id)
                                            {
                                                $is_sale_by_seller = true;
                                                $item_data = $item->get_data();
                                                $summary_data['total_sales'] += $item_data['subtotal'];
                                                $seller_order_sale += $item_data['subtotal'];
                                                $summary_data['total_qty'] += $item->get_quantity();
                                                
                                            }
                                        }
                                    }
    
                                    if($is_sale_by_seller)
                                    {
                                        $created_at = $order->get_date_created();

                                        $effect_orders[] = $order_id;

                                        $customer_id = $order->get_customer_id();
                                        
                                        $post_author_id = $order->get_meta('_op_sale_by_cashier_id');
                                        $author =   get_userdata($post_author_id);
                                        $author_name = 'Unknown';
                                        if($author)
                                        {
                                            $author_name = $author->display_name;
                                        }
                                        $grand_total = $order->get_total() - $order->get_total_refunded();

                                        $created_at_str = $created_at->format('Y-m-d H:i:s');
                                        $created_at_time = strtotime($created_at_str);
                                        $counter = 0;
                                        foreach($ranges['ranges'] as $rk => $r){
                                            $from_date_str = $r['from'];
                                            $to_date_str = $r['to'];
                                            $from_time = strtotime($from_date_str);
                                            $to_time = strtotime($to_date_str);
                                            
                                            if($created_at_time > $from_time && $created_at_time < $to_time ){
                                                 
                                                 $chart_data_js['data']['datasets'][0]['data'][$counter] +=  $seller_order_sale;
                                            }
                                            $counter++;
                                        }
                                        $orders_table_data[] = array(
                                            $order->get_order_number(),
                                            strip_tags(wc_price($grand_total)),
                                            strip_tags(wc_price($seller_order_sale)),
                                            $author_name,
                                            wc_format_datetime( $created_at ).' '.wc_format_datetime( $created_at, get_option( 'time_format' ) ),
                                            '<a target="_blank" href="'.esc_url($order->get_edit_order_url()).'">'.__( 'View', 'openpos' ) .'</a>'
                                        );
    
                                        $tmp_export = array(
                                            $order->get_order_number(),
                                            $grand_total,
                                            $seller_order_sale,
                                            $author_name,
                                            wc_format_datetime( $created_at ).' '.wc_format_datetime( $created_at, get_option( 'time_format' ) )
                                        );
                                        $orders_export_data[] = $tmp_export;
                                    }
    
                                }
                                $summary_data['total_order'] = count(array_unique($effect_orders));
                                $summary_html = '';
                                foreach($summary_data as $k => $v)
                                {
                                    if(in_array($k,array('total_sales','total_qty','total_order')))
                                    {
                                        $title =  __('Total Qty','openpos');
                                        $value = $v;
                                        if($k == 'total_sales')
                                        {
                                            $title =  __('Total Sales','openpos');
                                            $value = wc_price($v);
                                        }
                                        if($k == 'total_order')
                                        {
                                            $title =  __('Total Orders','openpos');
                                           
                                        }
    
                                        $summary_html .= '<div class="col-md-3 col-log-3 col-sm-3 col-xs-3">';
                                        $summary_html .= '<div class="summary-block">';
                                        $summary_html .= '<dl>';
                                        $summary_html .= '<dt>'.$title.'</dt>';
                                        $summary_html .= '<dd>'.$value.'</dd>';
                                        $summary_html .= '</dl>';
                                        $summary_html .= '</div>';
                                        $summary_html .= '</div>';
                                    }
                                }
                            }
                            if($is_all)
                            {
                                $all_sellers_id = array();
    
                                $all_seller_report = $op_report->getSaleBySellerReport($all_sellers_id,$ranges['start'],$ranges['end']);
                                $summary_data = array(
                                    'total_order' => 0,
                                    'total_sales' => 0,
                                    'total_qty' => 0,
                                );
                                $effect_orders = array();
                                $chart_data_js = array(
                                    'type' => 'horizontalBar',
                                    'data' => array(
                                        'labels' => array(),
                                        'datasets' => array()
                                    ),
                                    
                                );
                                foreach($all_seller_report as $user_id => $report_data)
                                {
                                    $summary_data['total_sales'] += $report_data['total_sale'];
                                    $summary_data['total_qty'] += $report_data['total_qty'];
                                    $order_ids = array_unique($report_data['order_ids']);
                                    $effect_orders += $order_ids;
                                    $user_name = __('Unknown');
                                    $user_email = __('Unknown');
                                    if($user_id)
                                    {
                                        $author_obj = get_user_by('id', $user_id);
                                        if($author_obj)
                                        {
                                            $user_name = $author_obj->user_nicename ? $author_obj->user_nicename : $author_obj->user_login;
                                            $user_email = $author_obj->user_email;
                                        }
                                    }
                                    $orders_table_data[] = array(
                                        $user_name,
                                        $user_email,
                                        wc_price($report_data['total_sale']),
                                        $report_data['total_qty'],
                                        count($order_ids),
                                    );
                                    $tmp_export = array(
                                        $user_name,
                                        $user_email,
                                        $report_data['total_sale'],
                                        $report_data['total_qty'],
                                        count($order_ids),
                                    );
                                    $orders_export_data[] = $tmp_export;
                                    

                                    $chart_data_js['data']['labels'][] = $user_name;
                                    $chart_data_js['data']['datasets'][0]['label'] = __('Seller sales','openpos');
                                    $chart_data_js['data']['datasets'][0]['backgroundColor'][] = '#0064d1';
                                    $chart_data_js['data']['datasets'][0]['data'][] = $report_data['total_sale'];
                                }
                                $summary_data['total_order'] = count(array_unique($effect_orders));
                                $summary_html = '';
    
                                foreach($summary_data as $k => $v)
                                {
                                    if(in_array($k,array('total_sales','total_qty','total_order')))
                                    {
                                        $title =  __('Total Qty','openpos');
                                        $value = $v;
                                        if($k == 'total_sales')
                                        {
                                            $title =  __('Total Sales','openpos');
                                            $value = wc_price($v);
                                        }
                                        if($k == 'total_order')
                                        {
                                            $title =  __('Total Orders','openpos');
                                           
                                        }
    
                                        $summary_html .= '<div class="col-md-3 col-log-3 col-sm-3 col-xs-3" style="margin-bottom: 15px;">';
                                        $summary_html .= '<div class="summary-block">';
                                        $summary_html .= '<dl>';
                                        $summary_html .= '<dt>'.$title.'</dt>';
                                        $summary_html .= '<dd>'.$value.'</dd>';
                                        $summary_html .= '</dl>';
                                        $summary_html .= '</div>';
                                        $summary_html .= '</div>';
                                    }
                                }
                                
                            }
    
                            break;
                        case 'sale_by_payment':
                            $report_payment = isset($_REQUEST['report_payment']) ? $_REQUEST['report_payment'] : '';
                            $table_label = array(
                                __('Order','openpos'),
                                __('Grand Total','openpos'),
                                __('Method Amount','openpos').'('.$report_payment.')',
                                __('Cashier','openpos'),
                                __('Created At','openpos'),
                                __('View','openpos'),
                            );
                            
    
    
                            $chart_data[] = array(
                                __('Date','openpos'),
                                __('Sales','openpos')
                            );
                            if($report_payment)
                            {
                                $orders_export_data[] = array(
                                    __('Order','openpos'),
                                    __('Grand Total','openpos'),
                                    __('Method Amount','openpos').'('.$report_payment.')',
                                    __('Cashier','openpos'),
                                    __('Created At','openpos')
                                );
                                $transactions = array();
                                if($report_register_id > 0)
                                {
                                    $transactions = $this->core->getPosRegisterTransactionsByDate($report_register_id,$ranges['start'],$ranges['end'],array( 'order'),$report_payment);
                                }elseif($report_outlet_id >= 0)
                                {
                                    $transactions = $this->core->getPosWarehouseTransactionsByDate($report_outlet_id,$ranges['start'],$ranges['end'],array( 'order'),$report_payment);
                                }else{
                                    $transactions = $this->core->getPosTransactionsByDate($ranges['start'],$ranges['end'],array( 'order'),$report_payment);
                                }
                                $summary_data['total_order'] = 0;

                                $chart_data_js = array(
                                    'type' => 'line',
                                    'data' => array(
                                        'labels' => array(),
                                        'datasets' => array(
                                            
                                        )
                                    ),
                                    
                                );
                                foreach($ranges['ranges'] as $rk => $r)
                                {
                                    $chart_data_js['data']['labels'][] = $r['label'];
                                }
                                $chart_data_js['data']['datasets'][0]['label'] =  __('Paid Amount','openpos');
                                $chart_data_js['data']['datasets'][0]['data'] =  array();

                                

                                $order_payments = array();
                                foreach($transactions as $_transaction)
                                {
                                    $_source_data_order_id = get_post_meta($_transaction->ID,'_source_data_order_id',true);
                                    $_source_data_order_number = get_post_meta($_transaction->ID,'_source_data_order_number',true);
                                    $user_id = get_post_meta($_transaction->ID,'_user_id',true);
                                    
                                    if( $_source_data_order_id &&  !$order = wc_get_order($_source_data_order_id))
                                    {
	                                     $_source_data_order_id = $op_woo_order->get_order_id_from_local_id($_source_data_order_id);
                                    }
                                    if(!$_source_data_order_id && $_source_data_order_number )
                                    {
                                    	$_source_data_order_id = $_source_data_order_number;
                                    	if($tmp_id = $op_woo_order->get_order_id_from_number( $_source_data_order_number)){
                                    		$_source_data_order_id = $tmp_id;
                                    	}
                                    }
                                    $payment_method = array(
                                        'payment_code' => get_post_meta($_transaction->ID,'_payment_code',true),
                                        'in_amount' => get_post_meta($_transaction->ID,'_in_amount',true),
                                        'out_amount' => get_post_meta($_transaction->ID,'_out_amount',true),
                                    );//
                                    $created_at_time = get_post_meta($_transaction->ID,'_created_at_time',true);
                                    if($created_at_time)
                                    {
                                        $created_at_time = round($created_at_time / 1000);
                                    }
                                    
                                    $payment_code = $payment_method['payment_code'];
                                    $paid = isset($payment_method['in_amount']) ? (float)$payment_method['in_amount'] : 0;
                                    $return_paid = isset($payment_method['out_amount']) ? (float)$payment_method['out_amount'] : 0;

                                    if($payment_code == $report_payment)
                                    {
                                        $counter = 0;
                                        foreach($ranges['ranges'] as $rk => $r)
                                        {
                                            $from_date_str = $r['from'];
                                            $to_date_str = $r['to'];
                                            $from_time = strtotime($from_date_str);
                                            $to_time = strtotime($to_date_str);
                                            $created_at_time_timestamp = strtotime(get_date_from_gmt(date( 'Y-m-d H:i:s', $created_at_time ),'Y-m-d H:i:s'));
                                            
                                            if(!isset($chart_data_js['data']['datasets'][0]['data'][$counter]))
                                            {
                                                $chart_data_js['data']['datasets'][0]['data'][$counter] = 0;
                                            }
                                            if($created_at_time_timestamp > $from_time && $created_at_time_timestamp < $to_time )
                                            {
                                                $chart_data_js['data']['datasets'][0]['data'][$counter] +=   ($paid - $return_paid);
                                               
                                            }
                                            $counter++;
                                        }
                                        
                                    }
                                    
                                    // if(!isset($order_payments[$_source_data_order_id][$payment_code])){
                                    //     $order_payments[$_source_data_order_id][$payment_code] = array(
                                    //         'in_amount' => $paid,
                                    //         'out_amount' => $return_paid,
                                            
                                    //     );
                                    // }else{
                                    //     $order_payments[$_source_data_order_id][$payment_code]['in_amount'] += $paid;
                                    //     $order_payments[$_source_data_order_id][$payment_code]['out_amount'] += $return_paid;
                                    // }

                                    if(!isset($order_payments[$_source_data_order_id]['payments'][$payment_code])){
                                    	$order_payments[$_source_data_order_id]['create_time'] = $created_at_time;
                                        $order_payments[$_source_data_order_id]['user_id'] = $user_id;
                                        $order_payments[$_source_data_order_id]['payments'][$payment_code] = array(
                                            'in_amount' => $paid,
                                            'out_amount' => $return_paid,
                                            
                                        );
                                    }else{
                                        $order_payments[$_source_data_order_id]['payments'][$payment_code]['in_amount'] += $paid;
                                        $order_payments[$_source_data_order_id]['payments'][$payment_code]['out_amount'] += $return_paid;
                                    }

                                }


                                foreach($order_payments as $order_id => $_order_payment)
                                {
                                	$order_payment = $_order_payment['payments'];
                                    $order = wc_get_order($order_id);
                                    $summary_data['total_order']++;
                                    $author_name = 'Unknown';
                                    $grand_total = 0;
                                    $_order_number = 0;
                                    $edit_url = '';
                                    $date_str = '';
                                    if(!$order)
                                    {
                                    	continue;
                                    }else{
                                    		$created_at = $order->get_date_created();
                                     		$_order_number = $order->get_order_number();
                                   		 	$post_author_id = $order->get_meta('_op_sale_by_cashier_id');
                                            $author =   get_userdata($post_author_id);
                                            
                                            if($author)
                                            {
                                                $author_name = $author->display_name;
                                            }
                                            $grand_total = $order->get_total() - $order->get_total_refunded();
                                            $edit_url = $order->get_edit_order_url();
                                            $date_str = wc_format_datetime( $created_at ).' '.wc_format_datetime( $created_at, get_option( 'time_format' ) ) ;
                                    }
                                    foreach($order_payment as $payment_code => $payment_method)
                                    {
                                        //$payment_code = $payment_method['payment_code'];
                                        if($payment_code == $report_payment)
                                        {
                                           
                                            $paid = isset($payment_method['in_amount']) ? (float)$payment_method['in_amount'] : 0;
                                            $return_paid = isset($payment_method['out_amount']) ? (float)$payment_method['out_amount'] : 0;
                                            $paid -=  $return_paid;
                                            $summary_data['total_sales'] += $paid;
                                            $orders_table_data[] = array(
                                                $_order_number,
                                                strip_tags(wc_price($grand_total)),
                                                strip_tags(wc_price($paid)),
                                                $author_name,
                                                $date_str ,
                                                '<a target="_blank" href="'.esc_url($edit_url).'">'.__( 'View', 'openpos' ) .'</a>'
                                            );
    
                                            $tmp_export = array(
                                                $_order_number,
                                                $grand_total,
                                                $paid,
                                                $author_name,
                                                $date_str
                                            );
                                            $orders_export_data[] = $tmp_export;
                                        }
                                    }
                                }
                                


                                
                                $summary_html = '';
                                foreach($summary_data as $k => $v)
                                {
                                    if(in_array($k,array('total_sales','total_order')))
                                    {
                                        $title =  __('Total Orders','openpos');
                                        $value = $v;
                                        if($k == 'total_sales')
                                        {
                                            $title =  __('Total Sales','openpos');
                                            $value = wc_price($v);
                                        }
    
                                        $summary_html .= '<div class="col-md-3 col-log-3 col-sm-3 col-xs-3">';
                                        $summary_html .= '<div class="summary-block">';
                                        $summary_html .= '<dl>';
                                        $summary_html .= '<dt>'.$title.'</dt>';
                                        $summary_html .= '<dd>'.$value.'</dd>';
                                        $summary_html .= '</dl>';
                                        $summary_html .= '</div>';
                                        $summary_html .= '</div>';
                                    }
                                }
                            }else{
                                //all payment

                                $orders_export_data[] = array(
                                    __('Method','openpos'),
                                    __('Orders','openpos'),
                                    __('Paid Amount','openpos').'('.$report_payment.')'
                                );

                                $payment_gateways = WC()->payment_gateways->payment_gateways();
                                $payment_options = array();
                                foreach ($payment_gateways as $code => $p)
                                {
                                    if($code == 'pos_multi' || $code == 'cash')
                                    {
                                        continue;
                                    }
                                    $payment_options[$code] = $p->title;
                                }
                                $addition_payments = $this->core->additionPaymentMethods();
                                $payment_options = array_merge($payment_options, $addition_payments);

                                $setting_payment_methods =  apply_filters('report_form_setting_payment_methods',$this->core->formatPaymentMethods($payment_options));
                                $payment_methods = array();
                                $payment_methods[] = array(
                                        'code' => 'cash',
                                        'title' => __('Cash','openpos')
                                );
                                foreach($setting_payment_methods as  $gateway)
                                {
                                    $code = $gateway['code'];
                                    $gtitle = $gateway['name'];
                                    if($code == 'pos_multi')
                                    {
                                        continue;
                                    }
                                    $payment_methods[] = array(
                                            'code' => $code,
                                            'title' =>  strip_tags($gtitle) ? strip_tags($gtitle) : $code
                                    );
                                }
                                $order_payments = array();
                                $total_orders = array();
                                $total_amount = 0;
                                $table_label = array(
                                    __('Method','openpos'),
                                    __('Orders','openpos'),
                                    __('Paid Amount','openpos'),
                                );
                                $chart_data_js = array(
                                    'type' => 'horizontalBar',
                                    'data' => array(
                                        'labels' => array(),
                                        'datasets' => array()
                                    ),
                                    
                                );
                                foreach($payment_methods as $payment_method)
                                {
                                    $report_payment = $payment_method['code'];
                                    $transactions = array();
                                    if($report_register_id > 0)
                                    {
                                        $transactions = $this->core->getPosRegisterTransactionsByDate($report_register_id,$ranges['start'],$ranges['end'],array( 'order'),$report_payment);
                                        
                                    }elseif($report_outlet_id >= 0)
                                    {
                                        
                                        $transactions = $this->core->getPosWarehouseTransactionsByDate($report_outlet_id,$ranges['start'],$ranges['end'],array( 'order'),$report_payment);
                                    }else{
                                        $transactions = $this->core->getPosTransactionsByDate($ranges['start'],$ranges['end'],array( 'order'),$report_payment);
                                       
                                    }
                                    foreach($transactions as $_transaction)
                                    {
                                        $_source_data_order_id = get_post_meta($_transaction->ID,'_source_data_order_id',true);
                                        $_source_data_order_number = get_post_meta($_transaction->ID,'_source_data_order_number',true);
                                        if(!$_source_data_order_id )
                                        {
                                            $_source_data_order_id = $_source_data_order_number;
                                           // continue;
                                        }
                                        $payment_method = array(
                                            'payment_code' => get_post_meta($_transaction->ID,'_payment_code',true),
                                            'in_amount' => get_post_meta($_transaction->ID,'_in_amount',true),
                                            'out_amount' => get_post_meta($_transaction->ID,'_out_amount',true),
                                        );//
                                        $created_at_time = get_post_meta($_transaction->ID,'_created_at_time',true);
                                        if($created_at_time)
                                        {
                                            $created_at_time = round($created_at_time / 1000);
                                        }
                                        
                                        $payment_code = $payment_method['payment_code'];
                                        $paid = isset($payment_method['in_amount']) ? (float)$payment_method['in_amount'] : 0;
                                        $return_paid = isset($payment_method['out_amount']) ? (float)$payment_method['out_amount'] : 0;

                                       
                                        $total_amount+= 1*($paid - $return_paid);
                                        if(!in_array($_source_data_order_id,$total_orders))
                                        {
                                            $total_orders[] = $_source_data_order_id;
                                        }

                                        if(!isset($order_payments[$payment_code])){
                                            $order_payments[$payment_code] = array(
                                                'sale' => 1*($paid - $return_paid),
                                                'order_ids' => array($_source_data_order_id)
                                            );
                                        }else{
                                            $order_payments[$payment_code]['sale'] += 1*($paid - $return_paid);
                                            if(!in_array($_source_data_order_id,$order_payments[$payment_code]['order_ids']))
                                            {
                                                $order_payments[$payment_code]['order_ids'][] = $_source_data_order_id;
                                            }
                                            
                                        }
                                    }
                                }
                                $orders_table_data = array();
                                foreach($payment_methods as $payment_method)
                                {
                                    $p_paid = 0;
                                    $p_order = 0;
                                    $payment_code = $payment_method['code'];
                                    if(isset($order_payments[$payment_code]))
                                    {
                                        $p_order = count($order_payments[$payment_code]['order_ids']);
                                        $p_paid = $order_payments[$payment_code]['sale'];
                                    }



                                    $orders_table_data[] = array(
                                        $payment_method['title'],
                                        $p_order ,
                                        wc_price($p_paid)
                                    ); 
                                    $orders_export_data[] = array(
                                        $payment_method['title'],
                                        $p_order ,
                                        $p_paid
                                    ); 

                                    $chart_data_js['data']['labels'][] = $payment_method['title'];
                                    $chart_data_js['data']['datasets'][0]['label'] = __('Paid amount','openpos');
                                    $chart_data_js['data']['datasets'][0]['backgroundColor'][] = '#0064d1';
                                    $chart_data_js['data']['datasets'][0]['data'][] = $p_paid;

                                    $chart_data_js['data']['datasets'][1]['label'] = __('Order count','openpos');
                                    $chart_data_js['data']['datasets'][1]['backgroundColor'][] = '#e0761a';
                                    $chart_data_js['data']['datasets'][1]['data'][] = $p_order;
                                }
                                

                                $summary_data['total_sales'] =  $total_amount;
                                $summary_data['total_order'] =  count($total_orders);

                                $summary_html = '';
                                foreach($summary_data as $k => $v)
                                {
                                    if(in_array($k,array('total_sales','total_order')))
                                    {
                                        $title =  __('Total Orders','openpos');
                                        $value = $v;
                                        if($k == 'total_sales')
                                        {
                                            $title =  __('Total Paid','openpos');
                                            $value = wc_price($v);
                                        }
    
                                        $summary_html .= '<div class="col-md-3 col-log-3 col-sm-3 col-xs-3">';
                                        $summary_html .= '<div class="summary-block">';
                                        $summary_html .= '<dl>';
                                        $summary_html .= '<dt>'.$title.'</dt>';
                                        $summary_html .= '<dd>'.$value.'</dd>';
                                        $summary_html .= '</dl>';
                                        $summary_html .= '</div>';
                                        $summary_html .= '</div>';
                                    }
                                }
                            }
                            break;
                        case 'sales':
                            $table_label = array(
                                __('Order','openpos'),
                                __('Customer','openpos'),
                                __('Grand Total','openpos'),
                                __('Profit Total','openpos'),
                                __('Tip Total','openpos'),
                                __('Cashier','openpos'),
                                __('Created At','openpos'),
                                
                            );
                            $orders_export_data[] = array(
                                __('#','openpos'),
                                __('Grand Total','openpos'),
                                __('Profit Total','openpos'),
                                __('Tip Total','openpos'),
                                __('Cashier','openpos'),
                                __('Created At','openpos')
                            );
                            $chart_data = array();
                            if($report_register_id > 0)
                            {
                                $orders = $this->core->getPosRegisterOrderByDate($report_register_id,$ranges['start'],$ranges['end']);
                            }elseif($report_outlet_id >= 0){
                                $orders = $this->core->getPosWarehouseOrderByDate($report_outlet_id,$ranges['start'],$ranges['end']);
                            }else{
                                $orders = $this->core->getPosOrderByDate($ranges['start'],$ranges['end']);
                            }
    
    
                            $summary_data['total_order'] = count($orders);
                            $summary_data['total_commision'] = 0;
                            $summary_data['total_tip'] = 0;

                            foreach($ranges['ranges'] as $rk => $r){
                                $chart_data[$rk] = array(
                                    $r['label'],
                                    0,
                                    0,
                                );
                                
                            }

                            foreach($orders as $_order_id)
                            {
                                $post_author_id = 0;
                                $order = new WC_Order($_order_id);
                                $post_author_id = $order->get_meta('_op_sale_by_cashier_id');
                                // if ( $_order instanceof WC_Order )
                                // {
                                //     $order = $_order;
                                //     $post_author_id = $order->get_meta('_op_sale_by_cashier_id');
                                    
                                // }else{
                                //     $order = new WC_Order($_order->ID);
                                //     $post_author_id = get_post_field( 'post_author', $_order->ID );
                                // }
                                $created_at_time_mili = $order->get_meta('pos_created_at_time');
                                $created_at = $order->get_date_created();
                                if($created_at != null)
                                {
                                    $created_at_str = $created_at->format('Y-m-d H:i:s');
                                }
                                
                                if($created_at_time_mili)
                                {
                                    $created_at_time = round($created_at_time_mili / 1000);

                                }else{
                                    $created_at_time = strtotime($created_at);
                                }
                                
                                
                                $customer_id = $order->get_customer_id();
                                
                                $author =   get_userdata($post_author_id);
                                $author_name = 'Unknown';
                                if($author)
                                {
                                    $author_name = $author->display_name;
                                }
                               
                                $grand_total = $order->get_total() - $order->get_total_refunded();
                                $commision_total = $op_report->getSaleCommision($order);
                                $tip_total =$op_report->getSaleTip($order);
                               
                                foreach($ranges['ranges'] as $rk => $r){
                                    $from_date_str = $r['from'];
                                    $to_date_str = $r['to'];
                                    $from_time = strtotime($from_date_str);
                                    $to_time = strtotime($to_date_str);

                                    
                                     $created_at_time_timestamp = strtotime(get_date_from_gmt(date( 'Y-m-d H:i:s', $created_at_time ),'Y-m-d H:i:s'));
                                    
                                    
                                    if(!isset($chart_data[$rk]))
                                    {
                                        $chart_data[$rk] = array(
                                            $r['label'],
                                            0,
                                            0,
                                        );
                                    }
                                    if($created_at_time_timestamp > $from_time && $created_at_time_timestamp < $to_time ){
                                        $chart_data[$rk][1] = (float)$chart_data[$rk][1] + 1 * $grand_total;
                                        $chart_data[$rk][2] = (float)$chart_data[$rk][2] + 1 * $commision_total;

                                        
                                    }
                                }
                                

                                $summary_data['total_sales'] += $grand_total;
                                $summary_data['total_commision'] += $commision_total;
                                $summary_data['total_tip'] += $tip_total;
                               
                                $order_id_str =  '<p><a target="_blank" href="'.esc_url($order->get_edit_order_url()).'">#'.$order->get_order_number().'</a></p>';
                                $order_id_str .=  '<p><small>'.$order->get_id().'</small></p>';

                                $customer_name = $order->get_formatted_billing_address();
                                if( !$customer_name)
                                {
                                    $customer_name = __('Guest','openpos');
                                }

                                $orders_table_data[] = array(
                                    $order_id_str,
                                    $customer_name,
                                    strip_tags(wc_price($grand_total)),
                                    strip_tags(wc_price($commision_total)),
                                    strip_tags(wc_price($tip_total)),
                                    $author_name,
                                    get_date_from_gmt(date( 'Y-m-d H:i:s', $created_at_time ),'d-m-Y').' '.get_date_from_gmt(date( 'Y-m-d H:i:s', $created_at_time ), get_option( 'time_format' ) ) ,
                                   
                                );
    
                                $tmp_export = array(
                                    $order->get_order_number(),
                                    $grand_total,
                                    $commision_total,
                                    $tip_total,
                                    $author_name,
                                    wc_format_datetime( $created_at ).' '.wc_format_datetime( $created_at, get_option( 'time_format' ) )
                                );
                                $orders_export_data[] = $tmp_export;
                            }
                            
                            $summary_html = '';
                           
                            foreach($summary_data as $k => $v)
                            {
                                if(in_array($k,array('total_sales','total_order','total_commision','total_tip')))
                                {
                                    $title =  __('Total Orders','openpos');
                                    $value = $v;
                                    if($k == 'total_sales')
                                    {
                                        $title =  __('Total Sales','openpos');
                                        $value = wc_price($v);
                                    }
                                    if($k == 'total_commision')
                                    {
                                        $title =  __('Total Profit','openpos');
                                        $value = wc_price($v);
                                    }
                                    if($k == 'total_tip')
                                    {
                                        $title =  __('Total TIP','openpos');
                                        $value = wc_price($v);
                                    }
                                    
    
                                    $summary_html .= '<div class="col-md-3 col-log-3 col-sm-3 col-xs-3">';
                                    $summary_html .= '<div class="summary-block">';
                                    $summary_html .= '<dl>';
                                    $summary_html .= '<dt>'.$title.'</dt>';
                                    $summary_html .= '<dd>'.$value.'</dd>';
                                    $summary_html .= '</dl>';
                                    $summary_html .= '</div>';
                                    $summary_html .= '</div>';
                                }
                            }
                            if(count($chart_data) > 0)
                            {
                                $chart_data_js = array(
                                    'type' => 'line',
                                    'data' => array(
                                        'labels' => array(),
                                        'datasets' => array()
                                    )
                                );
                                $new_chart_label = array();
                                $new_chart_data = array();
                                $new_commsion_data = array();

                                for($i = 0; $i < count($chart_data);$i++)
                                {
                                    $new_chart_label[] = $chart_data[$i][0];
                                    $new_chart_data[] = $chart_data[$i][1];
                                    if(isset($chart_data[$i][2]))
                                    {
                                        $new_commsion_data[] = $chart_data[$i][2];
                                    }
                                }
                                $chart_data_js['data']['labels'] = $new_chart_label;
                                $chart_data_js['data']['datasets'][] = array(
                                    'label' =>  __('Sales','openpos'),
                                    'data' => $new_chart_data
                                );
                                if(!empty($new_commsion_data))
                                {
                                
                                $chart_data_js['data']['datasets'][] = array(
                                        'label' =>  __('Profit','openpos'),
                                        'data' => $new_commsion_data,
                                        'borderColor' => 'rgba(75, 192, 192, 1)',
                                        'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                                    );
                                }
                            }
                            break;
                        case 'sale_by_product':
                            $orders_table_data = array();
                            $orders_export_data = array();
                            $summary_html = '';
                            $is_all = false;
                            if($report_register_id > 0)
                            {
                                $orders = $this->core->getPosRegisterOrderByDate($report_register_id,$ranges['start'],$ranges['end']);
                            }elseif($report_outlet_id >= 0){
                                $orders = $this->core->getPosWarehouseOrderByDate($report_outlet_id,$ranges['start'],$ranges['end']);
                            }else{
                                $orders = $this->core->getPosOrderByDate($ranges['start'],$ranges['end']);
                                $is_all = true;
                            }
                            $table_label = array(
                                __('Product','openpos'),
                                __('SKU','openpos'),
                                __('QTY','openpos'),
                                __('Sale','openpos'),
                                __('Profit','openpos'),
                            );
                            $orders_export_data[] = array(
                                __('Product','openpos'),
                                __('SKU','openpos'),
                                __('QTY','openpos'),
                                __('Sale','openpos'),
                                __('Profit','openpos'),
                            );
    
                            
    
                            $item_data = array();
                            $custom_item_data = array();
    
                            foreach($orders as $_order_id) {

                                $order = new WC_Order($_order_id);
                                // if ( $_order instanceof WC_Order )
                                // {
                                //     $order = $_order;
                                // }else{
                                //     $order = new WC_Order($_order->ID);
                                    
                                // }

                                
                                $items = $order->get_items();
                                foreach($items as $item)
                                {
                                    $product = $item->get_product();
                                    $order_item_data = $item->get_data();
                                    $name =  $item->get_name();
                                    if($product)
                                    {
                                        $id = $product->get_id();
                                        $sku = $product->get_sku();
                                        $item_qty = $item->get_quantity();
                                        
                                       
                                        $product_type = $product->get_type();
                                        if($product_type == 'variation')
                                        {
                                            $parent_id = $product->get_parent_id();
                                            $parent = wc_get_product($parent_id);
                                            
                                            if($parent)
                                            {
                                                $variable_attributes = $parent->get_variation_attributes();
                                                $meta_tiles = array();
                                                foreach($variable_attributes as $ke => $v)
                                                {
                                                    if($tmp_label = $item->get_meta( $ke))
                                                    {
                                                        $meta_tiles[] = $tmp_label;
                                                    }
                                                    
                                                }
                                                if(!empty($meta_tiles))
                                                {
                                                    $name = $parent->get_name();
                                                    $name .= ' - '.implode(', ',$meta_tiles);
                                                }
                                            }
                                        }
                                        
                                        
                                        $item_sales =  $order_item_data['total'];
                                        $metas = $item->get_meta_data();
                                        //$sub_total = $item_data['subtotal'];
                                        
                                        $cost_price = 0;
                                        
                                        foreach($metas as $meta)
                                        {
                                            if($meta->key == '_op_cost_price' && $meta->value)
                                            {
                                                $cost_price = $meta->value; 
                                                if(is_array($cost_price) || !is_numeric($cost_price))
                                                {
                                                    $cost_price = 0;
                                                }
                                            }
                                        }
                                        $item_profit = $item_sales - ($cost_price * $item_qty);
    
                                        if(isset($item_data[$id]))
                                        {
                                            $item_data[$id]['qty'] += $item_qty;
                                            $item_data[$id]['sale'] += $item_sales;
                                            $item_data[$id]['profit'] += $item_profit;
                                        }else{
                                            $item_data[$id] = apply_filters('op_report_sale_by_product_item',array(
                                                'name' => $item->get_name(),
                                                'sku' => $sku,
                                                'qty' => $item_qty,
                                                'sale' => $item_sales,
                                                'profit' => $item_profit,
                                            ),$item,$order);
                                        }
                                    }else{
    
                                        $item_qty = $item->get_quantity();
                                        $item_sales =  $order_item_data['subtotal'];
                                        $item_profit = $item_sales;
                                        $custom_item_data[] = array(
                                            'name' => $name,
                                            'sku' => '',
                                            'qty' => $item_qty,
                                            'sale' => $item_sales,
                                            'profit' => $item_profit
                                        );
                                    }
    
                                }
                            }
                            $total_sales = 0;
                            $total_qty = 0;
                            $total_profit = 0;
                            $chart_data_js = array(
                                'type' => 'horizontalBar',
                                'data' => array(
                                    'labels' => array(),
                                    'datasets' => array()
                                ),
                                'options' => array(
                                    'indexAxis' => 'y',
                                    'responsive' => true,
                                    'maintainAspectRatio' => false,
                                    
                                )
                                
                            );
                            foreach($item_data as $_item)
                            {
                                if($_item['qty'] <= 0)
                                {
                                    continue;
                                }  
                                $total_sales += $_item['sale'];
                                $total_qty += $_item['qty'];
                                $total_profit += $_item['profit'];

                                $orders_export_data[] = array(
                                    $_item['name'],
                                    $_item['sku'],
                                    $_item['qty'],
                                    $_item['sale'],
                                    $_item['profit'],
                                );
                                $orders_table_data[] = array(
                                    $_item['name'],
                                    $_item['sku'],
                                    $_item['qty'],
                                    wc_price($_item['sale']),
                                    wc_price($_item['profit']),
                                );
                                $chart_data_js['data']['labels'][] = $_item['name'];
                                $chart_data_js['data']['datasets'][0]['label'] = __('QTY Sold','openpos');
                                $chart_data_js['data']['datasets'][0]['backgroundColor'][] = '#0064d1';
                                $chart_data_js['data']['datasets'][0]['data'][] = $_item['qty'];

                                $chart_data_js['data']['datasets'][1]['label'] = __('Sales','openpos');
                                $chart_data_js['data']['datasets'][1]['backgroundColor'][] = '#e0761a';
                                $chart_data_js['data']['datasets'][1]['data'][] = $_item['sale'];
                            }
                            foreach($custom_item_data as $_item)
                            {
                                if($_item['qty'] <= 0)
                                {
                                    continue;
                                }
                                
                                $total_sales += $_item['sale'];
                                $total_qty += $_item['qty'];
                                $total_profit += $_item['profit'];
    
                                $orders_export_data[] = array(
                                    $_item['name'],
                                    $_item['sku'],
                                    $_item['qty'],
                                    $_item['sale'],
                                    $_item['profit'],
                                );
                                $orders_table_data[] = array(
                                    $_item['name'],
                                    $_item['sku'],
                                    $_item['qty'],
                                    wc_price($_item['sale']),
                                    wc_price($_item['profit']),
                                );
                            }
                            $summary_html = '';
                            $summary_data['total_sales'] = $total_sales;
                            $summary_data['total_commision'] = $total_profit;
                            $summary_data['total_qty'] = $total_qty;
                            
                            foreach($summary_data as $k => $v)
                            {
                                if(in_array($k,array('total_qty','total_sales','total_commision')))
                                {
                                    
                                    $value = $v;
                                    if($k == 'total_sales')
                                    {
                                        $title =  __('Total Sales','openpos');
                                        $value = wc_price($v);
                                    }
                                    if($k == 'total_commision')
                                    {
                                        $title =  __('Total Profit','openpos');
                                        $value = wc_price($v);
                                    }
                                    if($k == 'total_qty')
                                    {
                                        $title =  __('Total QTY','openpos');
                                        $value = $v;
                                    }
                                    
    
                                    $summary_html .= '<div class="col-md-3 col-log-3 col-sm-3 col-xs-3">';
                                    $summary_html .= '<div class="summary-block">';
                                    $summary_html .= '<dl>';
                                    $summary_html .= '<dt>'.$title.'</dt>';
                                    $summary_html .= '<dd>'.$value.'</dd>';
                                    $summary_html .= '</dl>';
                                    $summary_html .= '</div>';
                                    $summary_html .= '</div>';
                                }
                            }
                            break;
                        
    
                    }
                    $result['table_data'] = array('data' => $orders_table_data,'label' => $table_label);
                    
                    
                    $result['chart_data'] = $chart_data_js;
                    $result['orders_export_data'] = $orders_export_data;
                    $result['summary_html'] = $summary_html;
                    $result['summary_data'] = $summary_data;
                    $result['export_file'] = '';
                    $result['total_page'] = $total_page;
                    $result['current_page'] = $current_report_page;
                    
                    $result = apply_filters('op_report_result',$result,$ranges,$report_type,$this);
                    
                    if($is_export)
                    {
                        $op_nonce = wp_create_nonce( 'op_nonce' );
                        $upload_dir = wp_upload_dir();
                        $url = $upload_dir['baseurl'];
                        $url = rtrim($url,'/');
                        
                        $upload_dir_path = $upload_dir['basedir'];
                        $upload_dir_path = rtrim($upload_dir_path,'/');

                        $is_csv =  apply_filters('op_report_export_is_csv',true);
                        
                        if($is_csv)
                        {
                            $file_name = 'openpos-report-'.time().'.csv';
                        }else{
                            $file_name = 'openpos-report-'.time().'.xls';
                        }
                        $file_path = $upload_dir_path.'/openpos/'.$file_name;
                        //$file_url = $url.'/openpos/'.$file_name;
                        $file_url = admin_url('admin-ajax.php?op_nonce='.$op_nonce.'&action=op_force_download&file=openpos/'.$file_name);
                        if($is_csv){
                            $fh = @fopen($file_path, 'a+' );
                            fprintf($fh, chr(0xEF).chr(0xBB).chr(0xBF));
                            foreach($result['orders_export_data'] as $_tmp){
                                fputcsv( $fh, $_tmp);
                            }
                            
                        }else{
                            $spreadsheet = new Spreadsheet();
                            $sheet = $spreadsheet->getActiveSheet()->fromArray( $result['orders_export_data'], null, 'A1');
                            $writer = new Xlsx($spreadsheet);
                            $writer->save($file_path); 
                        }
                        $result['export_file'] = $file_url;
                    }
                    
                }catch(Exception $e)
                {
                    $message = $e->getMessage();
                    $result['summary_html'] = $message;
                    $result['chart_data'] = null;
                    $result['table_data'] = array();
                }
                echo json_encode($result);
                

            }

            exit;
        }
    }
    public function report_page(){
        global  $op_warehouse;
        global $op_register;
        global $op_report;
        global $op_woo;
        $report_type = 'sales';
        $report_duration =  'today';
        $report_action =  '';

        $custom_from =  date('Y-m-d');
        $custom_to =  date('Y-m-d');

        if($report_action)
        {
            $form_result = array();
            echo json_encode($form_result);
            exit;
        }

        if($report_duration == 'custom')
        {
            $gameFrom = $this->core->dateToUTC($custom_from);
            $gameTo =   $this->core->dateToUTC($custom_to); 
            

            $ranges = $this->core->getReportRanges($report_duration,$gameFrom->toFormattedDateString('Y-m-d'),$gameTo->toFormattedDateString('Y-m-d'));
        }else{
            $ranges = $this->core->getReportRanges($report_duration);
        }

        
        $orders =  array();//$this->core->getPosOrderByDate($ranges['start'],$ranges['end']);
        $orders_table_data = array();
        $chart_data = array();
        $summaries  = array(
            'total_order' => count($orders),
            'total_sale' => 0,
            'total_commision' => 0,
            'total_tip' => 0
        );


        foreach($orders as $_order)
        {
            if ( $_order instanceof WC_Order )
            {
                $order = $_order;
                $post_author_id = $order->get_meta('_op_sale_by_cashier_id');
                
            }else{
                $order = new WC_Order($_order->ID);
                $post_author_id = get_post_field( 'post_author', $_order->ID );
            }
            $created_at = $order->get_date_created();
            $customer_id = $order->get_customer_id();
            $author =   get_userdata($post_author_id);
            $author_name = 'Unknown';
            if($author)
            {
                $author_name = $author->display_name;
            }

            $created_at_time_mili = $order->get_meta('pos_created_at_time');
            $created_at = $order->get_date_created();
            
            if($created_at_time_mili)
            {
                $created_at_time = round($created_at_time_mili / 1000);

            }else{
                $created_at_time = strtotime($created_at);
            }
            $created_at_time_timestamp = strtotime(get_date_from_gmt(date( 'Y-m-d H:i:s', $created_at_time ),'Y-m-d H:i:s'));

            $grand_total = $order->get_total() - $order->get_total_refunded();
            $commision_total = $op_report->getSaleCommision($order);
            $tip_total = $op_report->getSaleTip($order);


            foreach($ranges['ranges'] as $rk => $r)
            {

                $from_date_str = $r['from'];
                $to_date_str = $r['to'];
                $from_time = strtotime($from_date_str);
                $to_time = strtotime($to_date_str);
                if(!isset($chart_data[$rk]))
                {
                    $chart_data[$rk] = array(
                        $r['label'],
                        0,
                        0,
                    );
                }
                if($created_at_time_timestamp > $from_time && $created_at_time_timestamp <= $to_time ){
                    $chart_data[$rk][1] += 1*$grand_total;
                    $chart_data[$rk][2] += 1*$commision_total;
                }
                
            }

            $summaries['total_sale'] += $grand_total;
            $summaries['total_commision'] += $commision_total;
            $summaries['total_tip'] += $tip_total;
            $orders_table_data[] = array(
                $order->get_order_number(),
                wc_price($grand_total),
                wc_price($commision_total),
                wc_price($tip_total),
                $author_name,
                wc_format_datetime( $created_at ).' '.wc_format_datetime( $created_at, get_option( 'time_format' ) ) ,
                '<a target="_blank" href="'.esc_url($order->get_edit_order_url()).'">'.__( 'View', 'openpos' ) .'</a>'
            );
        }

        array_unshift( $chart_data, array(
            __('Date','openpos'),
            __('Sales','openpos'),
            __('Profit','openpos'),
        ) );

        $allow_laybuy = $this->settings_api->get_option('pos_laybuy','openpos_pos') == 'yes' ? true : false;

        
        $payment_gateways = WC()->payment_gateways->payment_gateways();
        $payment_options = array();
        foreach ($payment_gateways as $code => $p)
        {
            if($code == 'pos_multi' || $code == 'cash')
            {
                continue;
            }
            $payment_options[$code] = $p->title;
        }
        $addition_payments = $this->core->additionPaymentMethods();
        $payment_options = array_merge($payment_options, $addition_payments);

        
        

        $setting_payment_methods =  apply_filters('report_form_setting_payment_methods',$this->core->formatPaymentMethods($payment_options));
        $warehouses =  apply_filters('report_form_warehouses',$op_warehouse->warehouses() );
        $cashiers =  apply_filters('report_form_cashiers',$op_woo->get_cashiers() );
        
        echo '<div class="wrap op-admin-wrap">';
        require(OPENPOS_DIR.'templates/admin/report/report_form.php');
        require(OPENPOS_DIR.'templates/admin/report/report_'.esc_attr($report_type).'_chart.php');
        require(OPENPOS_DIR.'templates/admin/report/report_'.esc_attr($report_type).'_table.php');
        echo '</div>';
    }



    public function admin_receipt_enqueue(){
        wp_enqueue_script('op.jquery.codemirror',OPENPOS_URL.'/assets/js/codemirror.js',array('jquery'));
        wp_enqueue_style( 'op.codemirror',OPENPOS_URL.'/assets/css/codemirror.css' );
        $this->admin_style();


    }

    public function admin_enqueue_setting() {
        global $OPENPOS_SETTING;
        $OPENPOS_SETTING->admin_enqueue_scripts();
        wp_enqueue_script('op.jquery.codemirror',OPENPOS_URL.'/assets/js/codemirror.js',array('jquery'));
        wp_enqueue_style( 'op.codemirror',OPENPOS_URL.'/assets/css/codemirror.css' );
        $this->admin_style();

    }
    public function admin_enqueue() {
        $this->admin_style();
    }
    public function admin_tables_enqueue_scripts(){
        wp_enqueue_script( 'woocommerce_admin' );
    }

    public function getUsers(){
        $this->_nonce_check();
        $rows = array();
        $current = isset($_REQUEST['current']) ? intval($_REQUEST['current']) : 1;
        $display = isset($_REQUEST['display']) ? esc_attr($_REQUEST['display']) : 'user';
        $sort  = isset($_REQUEST['sort']) ? sanitize_text_field($_REQUEST['sort']) : false;
        $searchPhrase  = $_REQUEST['searchPhrase'] ? sanitize_text_field($_REQUEST['searchPhrase']) : false;
        $sortBy = 'date';
        $order = 'DESC';
        if($sort)
        {
            if(is_array($sort))
            {
                $sortBy = end(array_keys($sort));
            }
            if($sortBy == 'id')
            {
                $sortBy = 'ID';
            }
            $order = end($sort);
        }


        $rowCount = $_REQUEST['rowCount'] ? intval($_REQUEST['rowCount']) : get_option( 'posts_per_page' );
        $offet = ($current -1) * $rowCount;

        $roles = array();//  array('administrator','shop_manager');
        $final_roles = apply_filters('op_allow_user_roles',$roles);
        
        $args = array(
            'count_total' => true,
            'number'   => $rowCount,
            'offset'           => $offet,
            'orderby'          => $sortBy,
            'order'            => $order,
            'fields' => array('ID', 'display_name','user_email','user_login','user_status')
        );
        if($display == 'staff')
        {
            $args['meta_key'] = '_op_allow_pos';
            $args['meta_value'] = true;
        }
        if(!empty($final_roles))
        {
            $args['role__in'] = $final_roles;
        }
        if($searchPhrase)
        {
            $args['search'] = $searchPhrase;
        }

        $user_query = new WP_User_Query( $args );


        $users = get_users( $args);
        $total = $user_query->total_users;

        foreach($users as $user)
        {
            $tmp = (array)$user;
            
            $allow_pos = get_user_meta($tmp['ID'],'_op_allow_pos',true);
            $user_meta = get_userdata($tmp['ID']);

            $user_role = $user_meta->roles;
            if(!empty($user_role))
            {
                $tmp['user_login'] .= '<p class="op-user-roles">'.implode(',',$user_role).'</p>';
            }

            if(!$allow_pos)
            {
                $allow_pos = 0;
            }else{
                $allow_pos = 1;
            }
            
            $tmp['id'] = (int)$tmp['ID'];
            unset($tmp['ID']);
            if($allow_pos)
            {
                $tmp['allow_pos'] = '<select type="text" name="_op_allow_pos['.$tmp['id'].']" class="form-control _op_allow_pos" disabled><option value="0">No</option><option value="1" selected>Yes</option></select>';
            }else{
                $tmp['allow_pos'] = '<select  type="text" name="_op_allow_pos['.$tmp['id'].']" class="form-control _op_allow_pos" disabled><option value="0" selected>No</option><option value="1">Yes</option></select>';
            }
            $rows[] = $tmp;
        }
        $result = array(
            'current' => $current,
            'rowCount' => $rowCount,
            'rows' => $rows,
            'total' => $total
        );
        echo json_encode($result);
        exit;
    }

    public function dashboard_data($return = false){
        global $op_register;
        $result = array('order' => array());
        
        $args = array(
            "post_type" => "shop_order",
            'posts_per_page' => 10,
            'orderby' => 'publish_date',
            'order' => 'DESC',
            'post_status'      => 'any',
            'meta_query' => array(
                array(
                    'key' => '_op_order_source',
                    'value' => 'openpos',
                    'compare' => '=',
                )
            )
            
        );
        if($this->_enable_hpos)
        {
            $args['orderby'] = 'date_created';
            $args['_query_src'] = 'op_order_query';
            $data_store = WC_Data_Store::load( 'order' );
            $orders = $data_store->query( $args );
        }else{
            $query = new WP_Query($args);
            $orders = $query->get_posts();
        }
        
        $debt_balance = 0;
        
        $allow_laybuy = $this->settings_api->get_option('pos_laybuy','openpos_pos') == 'yes' ? true : false;
        foreach($orders as $order)
        {
            if ( $order instanceof WC_Order )
            {
                $id = $order->get_id();
                $_order = $order;
            }else{
                $id = $order->ID;
                $_order = wc_get_order($id);
            }
            if(!$_order)
            {
                continue;
            }
           
            $customer_name = __('Guest','openpos');
            if( $_order->get_billing_first_name() || $_order->get_billing_last_name())
            {
                $customer_name = $_order->get_billing_first_name().' '.$_order->get_billing_last_name();
            }
            //$cashier_id = get_post_field( 'post_author', $id);
            $cashier_id = $_order->get_meta('_op_sale_by_cashier_id');
            $cashier = get_user_by('ID',$cashier_id);
            $cashier_name = 'unknown';
            $order_status = $_order->get_status();
            if($cashier)
            {
                $cashier_name = $cashier->display_name;
            }
            $tmp = array(
                'order_id' => "#".$_order->get_order_number(),
                'customer_name' => $customer_name,
                'total' => $_order->get_formatted_order_total(),
                'cashier' => $cashier_name,
                'created_at' =>  $this->core->render_order_date_column($_order),
                'view' => '<a target="_blank" href="'.get_edit_post_link($id).'">'."#".$_order->get_order_number().'</a>',
                'status' => '<span class="'.esc_attr($order_status).'">'.wc_get_order_status_name( $order_status ).'</span>'
            );
            if($order_status == $this->core->getPosLayBuyOrderStatus())
            {
                global $op_woo;   
                $is_oder_laybuy = get_post_meta($id,'_op_allow_laybuy',true);
                if($is_oder_laybuy == 'yes')
                {
                    $order_formatted = $op_woo->formatWooOrder($id);
                    $customer_total_paid = $order_formatted['customer_total_paid'];
                    $total_paid = $order_formatted['total_paid'];
                    if($customer_total_paid < $total_paid)
                    {
                        $debt_balance += ($total_paid - $customer_total_paid);
                    }
                }
                
                
            }

            $result['order'][] = $tmp;
        }
        
        $balance = 0;
        $debit = 0;

        $registers = $op_register->registers();
        foreach($registers as $register)
        {
            if($register['status'] == 'publish')
            {
                $balance +=  $op_register->cash_balance($register['id']);
                $debit +=  $op_register->debit_total($register['id']);
            }

        }
        $result['cash_balance'] = wc_price($balance);
        if($allow_laybuy)
        {
           // $result['debt_balance'] = wc_price($debt_balance);
            $result['debt_balance'] = wc_price($debit);
        }
        if($return)
        {
            return $result;
        }else{
            echo json_encode($result);
            exit;
        }
       
    }

    public function save_cashier(){
        $data = $_REQUEST['_op_allow_pos'];
        $this->_nonce_check();
        foreach($data as $user_id => $value)
        {
            update_user_meta($user_id,'_op_allow_pos',intval($value));
        }
        $result = array();
        echo json_encode($result);
        exit;
    }

    public function update_product_grid(){
        $barcode_field = $this->settings_api->get_option('barcode_meta_key','openpos_label');
        $this->_nonce_check();
        if(!$barcode_field)
        {
            $barcode_field = '_op_barcode';
        }
        if(isset($_REQUEST['data']))
        {
            parse_str($_REQUEST['data'], $data);
            
            if(isset($data['barcode']))
            {

                foreach($data['barcode'] as $product_id => $val)
                {
                    if($val)
                    {
                        if($barcode_field != 'post_id')
                        {
                            update_post_meta($product_id,$barcode_field,$val);
                        }

                    }
                }
            }
        }
        echo json_encode(array('status'=> 1));
        exit;
    }

    public function update_transaction_grid(){
        $this->_nonce_check();
        if(isset($_REQUEST['data']))
        {
            $data = $_REQUEST['data'];
            if(is_array($data))
            {
                foreach($data as $post_id)
                {
                    $post_type = get_post_type($post_id);
                    if($post_type == 'op_transaction')
                    {
                        $in = get_post_meta($post_id,'_in_amount',true);
                        $out = get_post_meta($post_id,'_out_amount',true);
                        $total_transaction = ($in - $out);
                        $balance = get_option('_pos_cash_balance',0);
                        $balance += $total_transaction;
                        update_option('_pos_cash_balance',$balance);
                        wp_delete_post($post_id);
                    }

                }
            }
        }
        echo json_encode(array('status'=> 1));
        exit;
    }

    public function update_inventory_grid(){
        global $op_warehouse;
        $data = array();
        $this->_nonce_check();
        if(isset($_REQUEST['data']))
        {
            parse_str($_REQUEST['data'], $data);
            $warehouse_id = $data['warehouse_id'];
            if(isset($data['barcode']))
            {
                foreach($data['qty'] as $product_id => $val)
                {
                    if($warehouse_id > 0)
                    {
                        if(!$val)
                        {
                            $val = 0;
                        }
                        $op_warehouse->set_qty($warehouse_id,$product_id,$val);
                    }else{
                        $product = wc_get_product($product_id);
                        if($val === '')
                        {
                            $product->set_manage_stock(false);

                        }else{
                            $qty = (float)$val;
                            $product->set_manage_stock(true);
                            $product->set_stock_quantity($qty);
                        }
                        $product->save();
                    }

                }
            }
        }
        echo json_encode($data);
        exit;
    }

    public function _short_code()
    {
        $is_pos = false ;
        if(isset($_REQUEST['action']) && esc_attr($_REQUEST['action']) == 'openpos')
        {
            $is_pos = true;
            
        }
        add_shortcode( 'barcode', array($this,'_barcode_img_func'));
        add_shortcode( 'op_product', array($this,'_product_barcode_func'));
        
        add_shortcode( 'order_barcode', array($this,'_order_barcode_func'));
        add_shortcode( 'order_qrcode', array($this,'_order_qrcode_func'));
       
        add_shortcode( 'op_warehouse', array($this,'_warehouse_func'));

        add_shortcode( 'op_register', array($this,'_register_func'));

        
    }
    public function _order_qrcode_func($atts){
        global $op_current_order;
        $atts = shortcode_atts( array(
            'width' => 1,
            'height' => 1
        ), $atts, 'barcode' );
        $barcode = '100';
        $unit = 'px';
        if($op_current_order)
        {
            if(isset($op_current_order['order_number'])){
                $barcode = $op_current_order['order_number'];
            }
        }
        //$img_src = esc_url('https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl='.$barcode.'&choe=UTF-8');
        $img_src = $this->core->generateQRcode($barcode,$atts['width'],$atts['height']);
        return '<img src="'.$img_src.'" style="width: '.esc_attr($atts['width']).$unit.' ;max-width:'.esc_attr($atts['width']).$unit.';max-height:'.esc_attr($atts['height']).$unit.';height:'.esc_attr($atts['height']).$unit.'">';
        
    }
    public function _order_barcode_func($atts)
    {
        global $op_current_order;
        
        global $OPENPOS_SETTING;
        $barcode_mode = $OPENPOS_SETTING->get_option('barcode_mode','openpos_label');

        $atts = shortcode_atts( array(
            'width' => 100,
            'height' => 50
        ), $atts, 'barcode' );
        $barcode = '100';
        if($op_current_order)
        {
            if(isset($op_current_order['order_number'])){
                $barcode = $op_current_order['order_number'];
            }
        }
        $unit = 'px';
        
        $barcode_width = isset($atts['width']) ? $atts['width'] : null ;
        $barcode_height = isset($atts['height']) ? $atts['height'] : null ;
        
        $img_data = $this->core->generateBarcode($barcode, $barcode_mode,$barcode_width,$barcode_height);
        return '<img src="'.$img_data.'" style="width: '.esc_attr($atts['width']).$unit.' ;max-width:'.esc_attr($atts['width']).$unit.';max-height:'.esc_attr($atts['height']).$unit.';height:'.esc_attr($atts['height']).$unit.'">';
    }
    public function _barcode_img_func($atts)
    {
        global $product;
        global $_op_product;
       
        global $unit;
        global $OPENPOS_SETTING;
        global $_barcode_width;
        global $_barcode_height;
        $barcode_mode = $OPENPOS_SETTING->get_option('barcode_mode','openpos_label','code_128');

        if(!$_op_product && $product)
        {
            $_op_product = $product;
        }
        if($_op_product)
        {
            

            $atts = shortcode_atts( array(
                'width' => 2.7,
                'height' => 0.5
            ), $atts, 'barcode' );

            if($_barcode_width)
            {
                $atts['width'] = $_barcode_width;
            }
            if($_barcode_height)
            {
                $atts['height'] = $_barcode_height;
            }

            $default_barcode = $this->core->getBarcode($_op_product->get_id());
            $barcode =  apply_filters('barcode_img_data',$default_barcode,$product);
            
            $unit = isset($_REQUEST['unit']) ? sanitize_text_field($_REQUEST['unit']) :  $OPENPOS_SETTING->get_option('unit','openpos_label','in');
            if($barcode_mode != 'qrcode')
            {
               
                
                $barcode_w =  null ;
                $barcode_h =  null ;
                if($unit == 'in')
                {
                    $barcode_w = isset($atts['width']) ? round($atts['width'] * 96) : null;
                    $barcode_h = isset($atts['height']) ? round($atts['height'] * 96) : null;
                    
                }
                if($unit == 'mm')
                {
                    $barcode_w = isset($atts['width']) ? round($atts['width'] * 3.7795275591) : null;
                    $barcode_h = isset($atts['height']) ? round($atts['height'] * 3.7795275591) : null;
                   
                }
                
                $img_data = $this->core->generateBarcode($barcode, $barcode_mode,$barcode_w,$barcode_h);
                return '<img src="'.$img_data.'" style="width: '.esc_attr($atts['width']).$unit.' ;max-width:'.esc_attr($atts['width']).$unit.';max-height:'.esc_attr($atts['height']).$unit.';height:'.esc_attr($atts['height']).$unit.'">';

            }else{
                $chs = '100x100';
                if($unit == 'in')
                {
                    $barcode_w = round($atts['width'] * 96);
                    $barcode_h = round($atts['height'] * 96);
                    $chs = implode('x',array($barcode_w,$barcode_h));
                }
                if($unit == 'mm')
                {
                    $barcode_w = round($atts['width'] * 3.7795275591);
                    $barcode_h = round($atts['height'] * 3.7795275591);
                    $chs = implode('x',array($barcode_w,$barcode_h));
                }
               

                $img_url = $this->core->generateQRcode($barcode,$barcode_w,$barcode_h);


                return '<img src="'.$img_url.'" style="width: '.esc_attr($atts['width']).$unit.' ;max-width:'.esc_attr($atts['width']).$unit.';max-height:'.esc_attr($atts['height']).$unit.';height:'.esc_attr($atts['height']).$unit.'">';

            }
        }
        return '';

    }
    public function _product_barcode_func($atts)
    {
        global $_op_product;
        $atts = shortcode_atts( array(
            'attribute' => 'name',
            'format' => false
        ), $atts, 'op_product' );
        $result = '';
        if(!$_op_product || $_op_product == null)
        {
            return '';
        }
        switch ($atts['attribute'])
        {
            case 'barcode':
                $result = $this->core->getBarcode($_op_product->get_id());
                break;
            case 'price':
                $result = $this->core->getProductPrice($_op_product,$atts['format']);
                break;
            case 'name':
                $result = $_op_product->get_name();
                break;
            default:
                $methods = get_class_methods($_op_product);
                if(in_array('get_'.esc_attr($atts['attribute']),$methods))
                {
                    $result = $_op_product->{'get_'.esc_attr($atts['attribute'])}();
                }
                break;
        }
        if(strpos($atts['attribute'],'price') !== false)
        {
            if($result !== '')
            {
                $result = wc_price($result);
            }
            
        }else{
            if(!$result)
            {
                
                $attribute = '';
                if(isset($atts['attribute']) && $atts['attribute'])
                {
                    $attribute = $atts['attribute'];
                    $result = $_op_product->get_attribute( 'pa_'.$attribute );
                    if(!$result)
					{
						$result = $_op_product->get_attribute( $attribute );
					}
                }
                
            }
        }
        $result = apply_filters('op_product_info_label',$result,$_op_product,$atts );
        return $result;
    }

    public function _register_func($atts){
        global $op_register;
        global $register_id;
        
        $atts = shortcode_atts( array(
            'field' => '',
            'id' => 0
        ), $atts, 'op_register' );
        $result = $atts['field'];
        if(!$register_id)
        {
            $register_id = isset($_REQUEST['cashdrawer_id']) ? intval($_REQUEST['cashdrawer_id']) : $atts['id'];
        }
        
        if($register_id)
        {
            $field = esc_attr($result);
            $result = $register_id;
            $register = $op_register->get($register_id);
            if(isset($register[$field]))
            {
                $result = $register[$field];
            }
        }
        return apply_filters('op_register_field_info',$result,$atts);

    }
    public function _warehouse_func($atts){
        global $op_register;
        global $op_warehouse;
        global $warehouse_id;
        $atts = shortcode_atts( array(
            'field' => '',
            'id' => 0
        ), $atts, 'op_warehouse' );
        $result = $atts['field'];
        $field = esc_attr($result);
        if($warehouse_id !== false)
        {
            $warehouse = $op_warehouse->get($warehouse_id);
            if(isset($warehouse[$field]))
            {
                $result = $warehouse[$field];
            }
        }else{
            $register_id = isset($_REQUEST['cashdrawer_id']) ? intval($_REQUEST['cashdrawer_id']) : $atts['id'];
            if($register_id)
            {
                
                $result = $register_id;
                $register = $op_register->get($register_id);
                $warehouse_id = $register['warehouse'];
                $warehouse = $op_warehouse->get($warehouse_id);
                if(isset($warehouse[$field]))
                {
                    $result = $warehouse[$field];
                }
            }
        }
        
        
        return apply_filters('op_warehouse_field_info',$result,$atts);
    }


    public function save_bacode_setting(){
        $result = array();
        $setting = get_option('openpos_label',false);
        $this->_nonce_check();
        $_request = $_REQUEST;
        $fields = $this->get_settings_fields();
        
        if(!$setting)
        {
            foreach($fields['openpos_label'] as $f)
            {
                $key = $f['name'];
                $default = isset($f['default']) ? $f['default'] : '';
                $setting[$key] = $default;
            }
           
        }
        foreach($_request as $key => $value)
        {
            if( $key == 'barcode_label_template')
            {
                $value = stripslashes($value);
            }
            if(isset($setting[$key]))
            {
                $setting[$key] = $value;
            }
        }
        $result['setting'] = $setting;
        
        update_option('openpos_label',$setting);
        
        echo json_encode($result);
        exit;
    }
    public function print_bacode(){
        $this->_nonce_check();
        $is_preview = isset($_REQUEST['is_preview']) && $_REQUEST['is_preview'] == 1 ? true : false;
        $is_print = isset($_REQUEST['is_print']) && $_REQUEST['is_print'] == 1 ? true : false;

        $product_id_str = isset($_REQUEST['product_id']) ? $_REQUEST['product_id'] : '';
        $_product_ids = explode(',',$product_id_str);
        $total = isset($_REQUEST['total']) ? intval($_REQUEST['total']) : -1;
        $_tmp_product_ids = array();
        if($total == -1){
            foreach($_product_ids as $id)
            {
                $_op_product = wc_get_product(intval($id));
               
                $_tmp_product_ids[$id] = apply_filters('op_product_label_qty',1,$_op_product);
            }
        }else{
            foreach($_product_ids as $id)
            {
                $_tmp_product_ids[$id] = 1;
            }
        }
        $product_ids = apply_filters('admin_print_bacode_product_ids',$_tmp_product_ids);
        
        if($is_preview)
        {
            global $_op_product;
            require(OPENPOS_DIR.'templates/admin/print_barcode_paper.php');
        }else{
            if(!isset($_POST['product_id']) && !$is_print)
            {
                require(OPENPOS_DIR.'templates/admin/print_barcode.php');
            }else{
                global $_op_product;
                
                
                require(OPENPOS_DIR.'templates/admin/print_barcode_paper.php');
            }
        }
        
        
        exit;
    }
    public function reset_balance(){
        global $op_register;
        $this->_nonce_check();
        $registers = $op_register->registers();
        foreach($registers as $register)
        {
            $balance_key = $op_register->get_transaction_balance_key($register['id']);
            update_option($balance_key,0);
        }

    }
    public function reset_debit(){
        global $op_register;
        $this->_nonce_check();
        $registers = $op_register->registers();
        foreach($registers as $register)
        {
            $balance_key = $op_register->get_debit_key($register['id']);
            update_option($balance_key,0);
        }
    }
    public function print_receipt(){

        $allow_access = false;
        
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
           
            if ( in_array( 'administrator', (array) $user->roles ) ) {
                $allow_access = true;
            }
        } 
       
        $order_id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        $order_token = isset($_REQUEST['s']) ? esc_attr($_REQUEST['s']) : '';
        $order = wc_get_order($order_id);
        if(!$allow_access && $order_token && $order)
        {
            $_op_session_id = $order->get_meta('_op_session_id');// get_post_meta($order_id,'_op_session_id',true);
            
            if($order_token == $_op_session_id)
            {
                $allow_access = true;
            }
        }
        if(! apply_filters('op_print_receipt_allow_access',$allow_access,$order))
        {
            die(__('Oh! Restricted access','openpos'));
        }

        global $op_receipt;   
        global $op_register;   
        global $op_warehouse;   
        global $op_current_order;   
        global $op_woo;   
        global $register_id;   
        global $warehouse_id;   
        wp_register_script('openpos.admin.receipt.ejs', OPENPOS_URL.'/assets/js/ejs.js',array('jquery'));
        $sections = $this->settings_api->get_fields();

        $setting = array();
        foreach($sections as $section => $fields)
        {
            foreach($fields as $field)
            {
                if(isset($field['name']))
                {
                    $option = $field['name'];

                    $setting[$option] = $this->settings_api->get_option($option,$section);
                    if($option == 'receipt_template_header' || $option == 'receipt_template_footer')
                    {
                        $setting[$option] = balanceTags($setting[$option],true);
                    }
                }

            }
        }
        $setting = $this->core->formatReceiptSetting($setting);

        $order_json = file_get_contents(OPENPOS_DIR.'default/order_formatted.json');
        $register_id = 0;
        
        $cashdrawer_meta_key = $op_register->get_order_meta_key();
        $warehouse_meta_key = $op_warehouse->get_order_meta_key();
        if($order_id)
        {
            $order = wc_get_order($order_id);
            $order_data = $order->get_meta('_op_order',true);
            $register_id = $order->get_meta($cashdrawer_meta_key,true);
            $warehouse_id = $order->get_meta($warehouse_meta_key,true);
            if($order_data)
            {
                $op_current_order = $op_woo->formatCurrencyOrder($order_data);

                $order_json = json_encode($op_current_order);
            }else{
               
                if($order)
                {
                    $tmp_order = $op_woo->formatWooOrder($order_id);
                    $order_json = json_encode($tmp_order);
                }
            }

        }
       

        $receipt_padding_top = isset($setting['receipt_padding_top']) ? $setting['receipt_padding_top'] : 0;
        $unit = 'in';
        $receipt_padding_right = isset($setting['receipt_padding_right']) ? $setting['receipt_padding_right'] : 0;
        $receipt_padding_bottom = isset($setting['receipt_padding_bottom']) ? $setting['receipt_padding_bottom'] : 0;
        $receipt_padding_left = isset($setting['receipt_padding_left']) ? $setting['receipt_padding_left'] : 0;;
        $receipt_width = isset($setting['receipt_width']) ? $setting['receipt_width'] : 0;
        $receipt_css = isset($setting['receipt_css']) ? $setting['receipt_css'] : '';
        $receipt_template_header = $setting['receipt_template_header'];
        $receipt_template = $setting['receipt_template'];
        $receipt_template_footer = $setting['receipt_template_footer'];

        $receipt_template_footer = do_shortcode($receipt_template_footer);

        $html_header = '<style type="text/css" media="print,screen">';
        $html_header .= '#op-page-cut{page-break-after: always; }';
        $html_header .= '#invoice-POS { ';
        $html_header .= 'padding:  '.$receipt_padding_top.$unit. ' ' . $receipt_padding_right.$unit .' '.$receipt_padding_bottom.$unit.' '.$receipt_padding_left.$unit.';';
        $html_header .= 'margin: 0 auto;';
        $html_header .= 'width: '.$receipt_width.$unit.' ;';
        $html_header .=  '}';

        $html_header .= $receipt_css;
        $html_header .= '</style>';
        $html_header .= '<body>';


        $html = '<div id="invoice-POS">';
        $html .= '<div id="invoce-header">';
        $html .= $receipt_template_header;


        $html .= '</div>';
        $html .= '<div id="bot">';

        $html .= '<div id="table">';
        $html .= $receipt_template;

        $html .= '</div><!--End Table-->';

        $html .= '<div id="invoce-footer">';
        $html .= $receipt_template_footer;
        $html .= '</div>';

        $html .= '</div><!--End InvoiceBot-->';
        $html .= '</div><!--End Invoice-->';
        $html_page_cut = '<p id="op-page-cut">&nbsp;</p>';
        $html .= $html_page_cut;

        $html = trim(preg_replace('/\s+/', ' ', $html));

        
        
        $tmp_template_id = isset($_REQUEST['template_id']) ? (int)$_REQUEST['template_id'] : 0;
        $template_id = apply_filters('op_print_receipt_template_id',$tmp_template_id);
        
        $order_json = apply_filters('op_print_receipt_order_json',$order_json,$order_id);

        $data = array(
            'setting' => $setting,
            'html_header' =>$html_header,
            'html_body' =>  addslashes(html_entity_decode($html)),
            'order_json' =>  $order_json
        );
    
        if($register_id)
        {
            $receipt_template = $op_receipt->get_register_template($register_id);
            if($receipt_template && !empty($receipt_template))
            {
                $data = $op_receipt->get_template_preview_data($receipt_template);
                $data['order_json'] =  $order_json;
            }
        }else{
            if($template_id)
            {
                $receipt_template = $op_receipt->get($template_id);
                if($receipt_template && !empty($receipt_template))
                {
                    $data = $op_receipt->get_template_preview_data($receipt_template);
                    
                }
            }
        }
        $data['template_id'] = $template_id;
        $data['order_id'] = $order_id;
        
        
        require(OPENPOS_DIR.'templates/admin/print_receipt.php');
        exit;
    }

    //register
    public function update_register(){
        global $op_register;
        $params = $_POST;
        $result = array(
            'status' => 0,
            'message' => 'Unknown'
        );
        $this->_nonce_check();
        try{

            if(!$params['name'])
            {
                throw new Exception(__('Please enter register name','openpos'));
            }
            do_action('op_register_save_before',$params,$op_register);
            $id = $op_register->save($params);
            do_action('op_register_save_after',$id,$params,$op_register);
            $result['status'] = 1;
        }catch (Exception $e)
        {
            $result['status'] = 0;
            $result['message'] = $e->getMessage();
        }
        echo json_encode($result);
        exit;
    }
    public function delete_register(){
        global $op_register;
        $params = $_POST;
        $result = array(
            'status' => 0,
            'message' => 'Unknown'
        );
        $this->_nonce_check();
        try{
            $id = isset($params['id']) ? $params['id'] : 0;
            if(!$id)
            {
                throw new Exception(__('Please choose register to delete','openpos'));
            }
            do_action('op_register_delete_before',$params,$op_register);
            $op_register->delete($id);
            do_action('op_register_delete_after',$params,$op_register);
            $result['status'] = 1;
        }catch (Exception $e)
        {
            $result['status'] = 0;
            $result['message'] = $e->getMessage();
        }
        echo json_encode($result);
        exit;
    }
    //table
    public function update_table(){
        global $op_table;
        $params = $_POST;
        $result = array(
            'status' => 0,
            'message' => 'Unknown'
        );
        $this->_nonce_check();
        try{

            if(!$params['name'])
            {
                throw new Exception(__('Please enter register name','openpos'));
            }
            if($params['cost'])
            {
                $params['cost'] = $this->core->sanitize_cost(wc_clean( wp_unslash( $params['cost'] ) ));
                
            }
            do_action('op_table_save_before',$params,$op_table);
            $id = $op_table->save($params);
            do_action('op_table_save_after',$id,$params,$op_table);
            $result['status'] = 1;
        }catch (Exception $e)
        {
            $result['status'] = 0;
            $result['message'] = $e->getMessage();
        }
        echo json_encode($result);
        exit;
    }
    public function delete_table(){
        global $op_table;
        $params = $_POST;
        $result = array(
            'status' => 0,
            'message' => 'Unknown'
        );
        $this->_nonce_check();
        try{
            $id = isset($params['id']) ? $params['id'] : 0;
            if(!$id)
            {
                throw new Exception(__('Please choose table to delete','openpos'));
            }
            do_action('op_table_delete_before',$params,$op_table);
            $op_table->delete($id);
            do_action('op_table_delete_after',$params,$op_table);
            $result['status'] = 1;
        }catch (Exception $e)
        {
            $result['status'] = 0;
            $result['message'] = $e->getMessage();
        }
        echo json_encode($result);
        exit;
    }
    public function qrcode_table(){
        global $op_table;
        global $op_register;
        $params = $_POST;
        $result = array(
            'status' => 0,
            'data' => array(),
            'message' => 'Unknown'
        );
        try{
            $id = isset($params['id']) ? $params['id'] : 0;
            if(!$id)
            {
                throw new Exception(__('Please choose table ','openpos'));
            }
            $this->_nonce_check();
            $table = $op_table->get($id);
            if(!$table || empty($table))
            {
                throw new Exception(__('Table do not exist','openpos'));
            }
            $image_url = 'https://via.placeholder.com/150x150';
            $key = get_post_meta($id,'_op_barcode_key',true);
            $register = get_post_meta($id,'_op_barcode_register',true);
            $login_url = '';
            if($key && $register)
            {
                $login_url = $this->core->get_customer_url(array('key' => esc_attr($key) )); 
                //$image_url = 'https://chart.googleapis.com/chart?chs=250x250&cht=qr&choe=UTF-8&chl='.urlencode($login_url);
                $image_url = $this->core->generateQRcode($login_url,100,100);
            }
            $result['data']['table'] = $table;
            $result['data']['register'] = $register;
            $result['data']['qrcode'] = $image_url;
            $result['data']['url'] = $login_url;
            $result['data']['key'] = $key;
            $result['data']['registers'] = $op_register->registers($table['warehouse']);
            $result['status'] = 1;
        }catch (Exception $e)
        {
            $result['status'] = 0;
            $result['message'] = $e->getMessage();
        }
        echo json_encode($result);
        exit;
    }
    public function qrcode_takeaway(){
        global $op_warehouse;
        global $op_register;
        $params = $_POST;
        $result = array(
            'status' => 0,
            'data' => array(),
            'message' => 'Unknown'
        );
        try{
            $id = isset($params['id']) ? $params['id'] : -1;
            if($id < 0)
            {
                throw new Exception(__('Please choose Outlet ','openpos'));
            }
            $this->_nonce_check();
            $warehouse = $op_warehouse->get($id);
            if(!$warehouse || empty($warehouse))
            {
                throw new Exception(__('Outlet do not exist','openpos'));
            }
            $image_url = 'https://via.placeholder.com/150x150';
            if($id == 0)
            {
                $key = get_option('_op_barcode_takeaway_key',true);
                $register = get_option('_op_barcode_takeaway_register',true);
               
            }else{
                $key = get_post_meta($id,'_op_barcode_takeaway_key',true);
                $register = get_post_meta($id,'_op_barcode_takeaway_register',true);
            }
            

            $login_url = '';
            
            if($key && $register)
            {
                $login_url = $this->core->get_customer_url(array('key' => esc_attr($key) )); 
                //$image_url = 'https://chart.googleapis.com/chart?chs=250x250&cht=qr&choe=UTF-8&chl='.urlencode($login_url);
                $image_url = $this->core->generateQRcode($login_url,100,100);
            }
            $result['data']['warehouse'] = $warehouse;
            $result['data']['register'] = $register;
            $result['data']['qrcode'] = $image_url;
            $result['data']['url'] = $login_url;
            $result['data']['key'] = $key;
            $result['data']['registers'] = $op_register->registers($id);
            $result['status'] = 1;
        }catch (Exception $e)
        {
            $result['status'] = 0;
            $result['message'] = $e->getMessage();
        }
        echo json_encode($result);
        exit;
    }
    public function geneate_qrcode_table(){
        global $op_table;
        global $op_register;
        $params = $_POST;
        $result = array(
            'status' => 0,
            'message' => 'Unknown'
        );
        try{
            $this->_nonce_check();
            $id = isset($params['id']) ? $params['id'] : 0;
            $register_id = isset($params['register']) ? $params['register'] : 0;
            if(!$id)
            {
                throw new Exception(__('Please choose table','openpos'));
            }

            if(!$register_id)
            {
                throw new Exception(__('Please choose register','openpos'));
            }

            $table = $op_table->get($id);
            $register = $op_register->get($register_id);
            if(!$table || empty($table))
            {
                throw new Exception(__('Table do not exist','openpos'));
            }
            if(!$register || empty($register))
            {
                throw new Exception(__('Register do not exist','openpos'));
            }
            if($register['warehouse'] != $table['warehouse'])
            {
                throw new Exception(__('Register and Table do not same location','openpos'));
            }
           
            $key = time();
            $key .= '-';

            $key .= strtolower(wp_generate_password(20,false));
            update_post_meta($id,'_op_barcode_key',$key );
            update_post_meta($id,'_op_barcode_register',$register_id );
            $image_url = 'https://via.placeholder.com/250x250';
            $login_url = '';
            if($key && $register)
            {
                $login_url = $this->core->get_customer_url(array('key' => esc_attr($key) )); 
                //$image_url = 'https://chart.googleapis.com/chart?chs=250x250&cht=qr&choe=UTF-8&chl='.urlencode($login_url);
                $image_url = $this->core->generateQRcode($login_url,100,100);
            }
            $result['data']['table'] = $table;
            $result['data']['register'] = $register_id;
            $result['data']['qrcode'] = $image_url;
            $result['data']['key'] = $key;
            $result['data']['url'] = $login_url;

            $result['status'] = 1;
        }catch (Exception $e)
        {
            $result['status'] = 0;
            $result['message'] = $e->getMessage();
        }
        echo json_encode($result);
        exit;
    }
    public function geneate_qrcode_takeaway(){
        global $op_warehouse;
        global $op_register;
        $params = $_POST;
        $result = array(
            'status' => 0,
            'message' => 'Unknown'
        );
        try{
            $this->_nonce_check();
            $id = isset($params['id']) ? $params['id'] : -1;
            $register_id = isset($params['register']) ? $params['register'] : 0;
            if($id < 0)
            {
                throw new Exception(__('Please choose outlet','openpos'));
            }

            if(!$register_id)
            {
                throw new Exception(__('Please choose register','openpos'));
            }

            $warehouse = $op_warehouse->get($id);
            $register = $op_register->get($register_id);
            if(!$warehouse || empty($warehouse))
            {
                throw new Exception(__('Outlet do not exist','openpos'));
            }
            if(!$register || empty($register))
            {
                throw new Exception(__('Register do not exist','openpos'));
            }
            if($register['warehouse'] != $id)
            {
                throw new Exception(__('Register and Warehouse do not same location','openpos'));
            }
           
            $key = time();
            $key .= '-';

            $key .= strtolower(wp_generate_password(20,false));
            if($id == 0)
            {
                update_option('_op_barcode_takeaway_key',$key );
                update_option('_op_barcode_takeaway_register',$register_id );
            }else{
                update_post_meta($id,'_op_barcode_takeaway_key',$key );
                update_post_meta($id,'_op_barcode_takeaway_register',$register_id );
            }
            
            $image_url = 'https://via.placeholder.com/250x250';
            $login_url = '';
            if($key && $register)
            {
                $login_url = $this->core->get_customer_url(array('key' => esc_attr($key) )); 
                //$image_url = 'https://chart.googleapis.com/chart?chs=250x250&cht=qr&choe=UTF-8&chl='.urlencode($login_url);
                $image_url = $this->core->generateQRcode($login_url,100,100);
            }
            $result['data']['warehouse'] = $warehouse;
            $result['data']['register'] = $register_id;
            $result['data']['qrcode'] = $image_url;
            $result['data']['key'] = $key;
            $result['data']['url'] = $login_url;

            $result['status'] = 1;
        }catch (Exception $e)
        {
            $result['status'] = 0;
            $result['message'] = $e->getMessage();
        }
        echo json_encode($result);
        exit;
    }
    //warehouse
    public function update_warehouse(){
        global $op_warehouse;
        $params = $_POST;
        $result = array(
            'status' => 0,
            'message' => 'Unknown',
            'data' => array()
        );
        try{
            $this->_nonce_check();
            if(!$params['name'])
            {
                throw new Exception(__('Please enter outlet name','openpos'));
            }
            do_action('op_warehouse_save_before',$params,$op_warehouse);
            $id = $op_warehouse->save($params);
            do_action('op_warehouse_save_after',$id,$params,$op_warehouse);
            $result['data'] = array(
                'id' => $id
            );
            $result['status'] = 1;
        }catch (Exception $e)
        {
            $result['status'] = 0;
            $result['message'] = $e->getMessage();
        }
        echo json_encode($result);
        exit;
    }
    public function delete_warehouse(){
        global $op_warehouse;
        $params = $_POST;
        $result = array(
            'status' => 0,
            'message' => 'Unknown'
        );
        try{
            $this->_nonce_check();
            $id = isset($params['id']) ? $params['id'] : 0;
            if(!$id)
            {
                throw new Exception(__('Please choose warehouse to delete','openpos'));
            }
            do_action('op_warehouse_delete_before',$params,$op_warehouse);
            $op_warehouse->delete($id);
            do_action('op_warehouse_delete_after',$params,$op_warehouse);
            $result['status'] = 1;
        }catch (Exception $e)
        {
            $result['status'] = 0;
            $result['message'] = $e->getMessage();
        }
        echo json_encode($result);
        exit;
    }
    public function stock_overview(){
        global $op_warehouse;
        $params = $_POST;
        $result = array(
            'status' => 0,
            'message' => 'Unknown'
        );
        try{
            $this->_nonce_check();
            $barcode = isset($params['barcode']) ? $params['barcode'] : 0;
            if(!$barcode)
            {

                throw new Exception(__('Please enter barcode to search','openpos'));
            }
            $product_id = $this->core->getProductIdByBarcode($barcode);
            if($product_id)
            {
                $warehouses = $op_warehouse->warehouses();
                $result['html'] = '<div class="table-responsive"><table class="table"><tr><th>'.__('Outlet','openpos').'</th><th>'. __('Qty','openpos').'</th></tr>';
                $total_with_online = 0;
                $total_no_online = 0;

                foreach($warehouses as $w)
                {
                    if($w['status'] == 'draft')
                    {
                        continue;
                    }
                    $qty = $op_warehouse->get_qty($w['id'],$product_id);
                    $total_with_online += $qty;
                    if($w['id'])
                    {
                        $total_no_online += $qty;
                    }
                    $result['html'] .= '<tr ><td>'.$w['name'].'</td><td>'.$qty.'</td></tr>';
                }


                $result['html'] .= '<tr ><th>'. __('Total','openpos').'</th><td>'. $total_with_online .'</td></tr>';
                //$result['html'] .= '<tr ><th>'. __('Total Qty Without Online','openpos').'</th><td>'. $total_no_online .'</td></tr>';

                $result['html'] .= '<tr><td colspan="2"></td></tr></table></div>';
                $result['status'] = 1;
            }else{
                $result['message'] = __('No product found. Please check your barcode !','openpos');
            }

        }catch (Exception $e)
        {
            $result['status'] = 0;
            $result['message'] = $e->getMessage();
        }
        echo json_encode($result);
        exit;
    }
    public function export_inventory(){
        global $op_warehouse;
        $warehouse_id = isset($_REQUEST['warehouse_id']) ? (int)$_REQUEST['warehouse_id'] : -1;
        $page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 1;
        $warehouse = $op_warehouse->get($warehouse_id);
        if(empty($warehouse))
        {
            die(__('Your warehouse do not exist.','openpos'));
        }
        $this->_nonce_check();

        $upload_dir   = wp_upload_dir();
        $filename = 'inventory_'.$warehouse_id.'.csv';
        $file_path = $upload_dir['basedir'].'/openpos/'.$filename;
        $file_url = $upload_dir['baseurl'].'/openpos/'.$filename;

        if($page == 1 && file_exists($file_path))
        {
            unlink($file_path);
        }

        $args = array(
            'numberposts' => 50,
            'current_page' => $page,
           
        );
        $final_args = apply_filters('admin_export_inventory_args',$args);

        $query = $this->core->getProducts($final_args);

        
       
        $products = $query['posts'];

        $fh = @fopen($file_path, 'a+' );
        
        if($page == 1)
        {
            fprintf($fh, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv( $fh, array('BARCODE','QTY',__('Name','openpos')));
        }
        //start product data
        foreach($products as $p)
        {
           
            if(is_a($p, 'WP_Post'))
            {
                $product_id = $p->ID;
            }else{
                $product_id = $p->get_id();
                
            }
            $product = wc_get_product($product_id);
            $name = $product->get_name();
            $barcode = $this->core->getBarcode($product_id);
            if($warehouse_id > 0)
            {
                $qty = $op_warehouse->get_qty($warehouse_id,$product_id);
            }else{
                
                if(!$product->get_manage_stock())
                {
                    $qty = '';
                }else{
                    $qty = $product->get_stock_quantity();
                }
            }

            fputcsv( $fh, array($barcode,$qty,$name));
        }
        //end product data
        fclose( $fh );

        $op_nonce = wp_create_nonce( 'op_nonce' );
        $result = array(
            'total_page' => $query['total_page'],
            'page'  => $page,
            'file_url' => $file_url,
            'file_name' => $filename
        );
        $result['export_file'] = admin_url('admin-ajax.php?op_nonce='.$op_nonce.'&action=op_force_download&file=openpos/'.$filename);
        echo json_encode($result);
        //ob_end_flush();
        exit;
    }
    public function op_stock_products_export(){
        global $op_warehouse;
        $warehouse_id = isset($_REQUEST['warehouse_id']) ? (int)$_REQUEST['warehouse_id'] : -1;
        $page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 1;
        $result = array();
        $export_data = array();
        $is_all = false;
        $this->_nonce_check();
        if($warehouse_id == -1)
        {
            $is_all = true;
        }
        $upload_dir = wp_upload_dir();
        $url = $upload_dir['baseurl'];
        $url = rtrim($url,'/');
        $file_name = 'openpos-stock-'.$warehouse_id.'.csv';
        if($is_all)
        {
            $file_name = 'openpos-stock-all.csv';
        }
        $upload_dir_path = $upload_dir['basedir'];
        $upload_dir_path = rtrim($upload_dir_path,'/');
        $full_path = $upload_dir_path.'/openpos/'.$file_name;

        if(file_exists($full_path) && $page == 1)
        {
            unlink($full_path);
        }

        $query = $this->core->getProducts(array(
            'posts_per_page' => 50,
            'current_page' => $page,
        ));
        
        $products = $query['posts'];

        $fh = @fopen($full_path, 'a+' );

        $all_warehouses = array();
        if($warehouse_id == -1)
        {
            $all_warehouses = $op_warehouse->warehouses();
        }

        $tmp_label = array(
            __('ID','openpos'),
            __('BARCODE','openpos'),
            __('PRODUCT','openpos'),
            __('PRICE','openpos'),
            __('QTY','openpos')
        );
        
        if(!empty($all_warehouses ))
        {
            $tmp_label = array(
                __('ID','openpos'),
                __('BARCODE','openpos'),
                __('PRODUCT','openpos'),
                __('PRICE','openpos'),
                
            );
            foreach($all_warehouses as $w)
            {
                $w_name = $w['name'];
                $tmp_label[] = $w_name.'-'.__('QTY','openpos');

            }
            $tmp_label[] = __('TOTAL QTY','openpos');
            
        }
        $export_label = apply_filters('op_stock_products_export_label',$tmp_label);
        if(!is_array($export_label))
        {
            $export_label = array();
        }

        if($page == 1)
        {
            fprintf($fh, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv( $fh, $export_label );
        }
        $export_data[] = $export_label;
        foreach($products as $post)
        {
            $product_id = $post->ID;
            $product = wc_get_product($product_id);
            if(!$product)
            {
                continue;
            }
           
            $name = $product->get_name();
            if( $product->get_type() == 'variation')
            {
                $parent_id = $product->get_parent_id();
                $parent = wc_get_product($parent_id);
                if($parent)
                {
                    $name = $parent->get_name();
                    $attribute_label = wc_get_formatted_variation( $product, true, false );
                    if($attribute_label)
                    {
                        $name.= ' ';
                        $name.= $attribute_label;
                    }
                }
            }
            $barcode = $this->core->getBarcode($product_id);
            $price = $product->get_price();

            $qty = 0;
            $all_qty = array();
            if($warehouse_id >= 0)
            {
                
                $w_qty =  $op_warehouse->get_qty($warehouse_id,$product_id);
                $qty += $w_qty;
            }else{
                foreach($all_warehouses as $w)
                {
                    $w_id = $w['id'];
                    $w_qty = $op_warehouse->get_qty($w_id,$product_id);
                    $all_qty[$w_id] = $w_qty;
                    $qty += $w_qty;
                }
            }
           
            if(!empty($all_warehouses ))
            {
                $tmp = array(
                    $product_id,
                    ''.$barcode,
                    $name,
                    $price,
                    
                );
                foreach($all_warehouses as $w)
                {
                    $w_id = $w['id'];
                    $w_name = $w['name'];
                    $tmp[] = $all_qty[$w_id];
    
                }
                $tmp[] = $qty;
            }else{
                $tmp = array(
                    $product_id,
                    ''.$barcode,
                    $name,
                    $price,
                    $qty
                );
            }
            $_tmp = apply_filters('op_stock_products_export_row',$tmp,$product,$post);
            if(is_array($_tmp))
            {
                fputcsv( $fh, $_tmp);
            }
            
        }
        
        
        fclose( $fh );
        $op_nonce = wp_create_nonce( 'op_nonce' );
        $result['page'] = $page;
        $result['total_page'] = $query['total_page'];
        $result['file_name'] = $file_name;
        $result['export_file'] = admin_url('admin-ajax.php?op_nonce='.$op_nonce.'&action=op_force_download&file=openpos/'.$file_name);
        $result['file_url'] = $url.'/openpos/'.$file_name;
        echo json_encode($result);exit;
    }
    public function upload_inventory_csv(){
        $result = array('status' => 0,'data' => array(),'message' => '');
        try{
            $data = array();
            $this->_nonce_check();
            if( isset($_FILES['file']))
            {
                $file = $_FILES['file'];
                if($file['type'])
                {
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
                    $csv = $spreadsheet->getActiveSheet()->toArray();
                    $labels = $csv[0];
                    array_shift($csv);
                    $barcode_index = -1;
                    $qty_index = -1;
                    $id_index = -1;
                    foreach($labels as $key => $label)
                    {
                        if(strtoupper($label) == strtoupper('barcode'))
                        {
                            $barcode_index = $key;
                        }
                        if(strtoupper($label) == strtoupper('qty'))
                        {
                            $qty_index = $key;
                        }
                        if(strtoupper($label) == strtoupper('id'))
                        {
                            $id_index = $key;
                        }
                        
                    }
                   

                    foreach($csv as $row)
                    {
                        $barcode = $row[$barcode_index];
                        $qty = $row[$qty_index];
                        if($id_index == -1)
                        {
                            $product_id = $this->core->getProductIdByBarcode($barcode);
                        }else{
                            $product_id = $row[$id_index];
                        }
                        
                        $post = get_post($product_id);
                        if($post)
                        {
                            $_barcode = $this->core->getBarcode($product_id);
                            $name = $post->post_title;
                            $data[] = array(
                                'id' => $product_id,
                                'barcode' => $_barcode,
                                'name' => $name,
                                'qty' => $qty
                            );
                        }


                    }
                }
            }
            $result['data'] = $data;
            $result['status'] = 1;
        }catch (Exception $e)
        {
            $result['status'] = 0;
            $result['message'] = $e->getMessage();
        }
        echo json_encode($result);
        exit;
    }
    public function adjust_stock_finder(){
        $params = $_POST;
        $result = array(
            'status' => 0,
            'message' => 'Unknown',
            'data' => array()
        );
        try{
            $barcode = isset($params['barcode']) ? $params['barcode'] : 0;
            $this->_nonce_check();
            if(!$barcode)
            {

                throw new Exception(__('Please enter barcode to search','openpos'));
            }
            $product_id = $this->core->getProductIdByBarcode($barcode);
            if($product_id)
            {
                $post = get_post($product_id);
                if($post)
                {
                    $_barcode = $this->core->getBarcode($product_id);
                    $name = $post->post_title;
                    $result['data'][]  = array(
                        'id' => $product_id,
                        'barcode' => $_barcode,
                        'name' => $name,
                        'qty' => 1
                    );
                }

                $result['status'] = 1;
            }else{
                $result['message'] = __('No product found. Please check your barcode !','openpos');
            }

        }catch (Exception $e)
        {
            $result['status'] = 0;
            $result['message'] = $e->getMessage();
        }
        echo json_encode($result);
        exit;
    }

    public function op_adjust_stock(){
        global $op_warehouse;
        $result = array(
            'status' => 0,
            'message' => 'Unknown',
            'data' => array()
        );
        try{
            $this->_nonce_check();
            $params = $_POST;

            $result['data'] = $params['product'];
            $adjust_action = isset($params['adjust_action']) ? $params['adjust_action'] : 'append';
            

            if(!isset($params['warehouse_id']) )
            {
                throw new Exception(__('Outlet do not found'));
            }
            $warehouse_id = (int)$params['warehouse_id'];

            if(!isset($params['product']) || empty($params['product']))
            {
                throw new Exception(__('Product do not found'));
            }
            $products = $params['product'];
            foreach($products as $product_id => $qty)
            {
                if($warehouse_id > 0)
                {
                    if($qty > 0)
                    {
                        $op_warehouse->add_instore($warehouse_id,$product_id);
                    }
                    switch($adjust_action)
                    {
                        case 'replace':
                            $op_warehouse->set_qty($warehouse_id,$product_id,$qty);
                            break;
                        default:
                            $op_warehouse->set_qty($warehouse_id,$product_id,$qty,true);
                            break;
                    }
                    
                }else{
                    $product = wc_get_product($product_id);
                    if($qty === '')
                    {
                        $product->set_manage_stock(false);
                    }else{
                        $product->set_manage_stock(true);
                        $qty = (float)$qty;
                        switch($adjust_action)
                        {
                            case 'replace':
                                $op_warehouse->set_qty($warehouse_id,$product_id,$qty);
                                break;
                            default:
                                $op_warehouse->set_qty($warehouse_id,$product_id,$qty,true);
                                break;
                        }
                    }
                    $product->save();
                }

            }
            $result['status'] = 1;

        }catch (Exception $e)
        {
            $result['status'] = 0;
            $result['message'] = $e->getMessage();
        }
        echo json_encode($result);
        exit;
    }
    function op_ajax_category(){
        $query = $_REQUEST['search'];
        $this->_nonce_check();
        $args = array(
            'taxonomy'   => "product_cat",
            'hide_empty' => false,
            'name__like' => $query
        );
        $product_categories = get_terms($args);

        $result = array();
        foreach($product_categories as $cat)
        {

            $id = $cat->term_id;
            $text = $cat->name;
            $result[] = array(
                'value' => $id,
                'text' => $text
            );
        }

        echo json_encode($result);
        exit;
    }
    function op_ajax_order_statuses(){
        $result = array();
        $this->_nonce_check();
        $wc_order_status = wc_get_order_statuses();
        
        foreach($wc_order_status as $key =>$status)
        {
            $result[] = array(
                'value' => $key,
                'text' => $status
            );
        }
        
        echo json_encode($result);
        exit;
    }
    function upload_product_image(){
        $product_id = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;
        $this->_nonce_check();
        if($product_id && current_user_can('upload_files'))
        {
            if ( ! function_exists( 'wp_handle_upload' ) ) {
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
            }
            $uploadedfile = $_FILES['field_value'];

            $upload_overrides = array( 'test_form' => false );

            $movefile = wp_handle_upload( $uploadedfile, $upload_overrides );

            if ( $movefile && ! isset( $movefile['error'] ) ) {
                $type = $movefile['type'];
                if(strpos($type,'image') >= 0)
                {
                    $filename = $movefile['file'];
                    $attachment = array(
                        'guid'           => $movefile['url'],
                        'post_mime_type' => $type,
                        'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
                        'post_content'   => '',
                        'post_status'    => 'inherit'
                    );
                    $attach_id = wp_insert_attachment( $attachment, $filename, $product_id );
                    require_once( ABSPATH . 'wp-admin/includes/image.php' );

                    $attach_data = wp_generate_attachment_metadata( $attach_id, $filename );

                    wp_update_attachment_metadata( $attach_id, $attach_data );
                    set_post_thumbnail( $product_id, $attach_id );
                }
            } else {
                /**
                 * Error generated by _wp_handle_upload()
                 * @see _wp_handle_upload() in wp-admin/includes/file.php
                 */
                echo $movefile['error'];
            }
        }

    }
    function admin_global_style(){
        wp_enqueue_style('openpos.admin.global', OPENPOS_URL.'/assets/css/admin_global.css');
        wp_enqueue_script('openpos.global.admin.js', OPENPOS_URL.'/assets/js/admin_global.js','jquery');
    }

    function admin_notice_init(){
        $option_page = isset($_REQUEST['option_page']) ? esc_attr($_REQUEST['option_page']) : '';
        $action = isset($_REQUEST['action']) ? esc_attr($_REQUEST['action']) : '';
        if(strpos($option_page,'openpos_') !== false && $action == 'update')
        {
            update_option('_admin_op_setting_msg',__( 'Your setting has been update successfull. Don\'t forget Logout and Login POS again to take effect on POS panel !', 'openpos' ));
        }
    }

    function admin_notice() {

        $msg = get_option('_admin_op_setting_msg',false);

        if($msg)
        {
            ?>
            <div class="notice">
                <p style="color: green;"><?php echo $msg; ?></p>
            </div>
            <?php
            update_option('_admin_op_setting_msg','');
        }
    }
    function woocommerce_product_options_stock_fields(){
        global $post;
        global $op_warehouse;
        $warehouses = $op_warehouse->warehouses();
        if($post && count($warehouses) > 1)
        {
            $product = wc_get_product($post->ID);
            $product_type = $product->get_type();
            if($product_type != 'variable')
            {
                ?>
                <div class="op-product-outlet-stock hide_if_variable">
                    <p class="op-stock-label"><?php echo __('Other Outlet Stock quantity','openpos'); ?></p>
                    <?php foreach($warehouses as $warehouse): ?>
                        <?php if($warehouse['id'] > 0): $warehouse_id  = $warehouse['id']; ?>
                        <fieldset class="form-field">
                            <label><?php echo $warehouse['name']; ?></label>
                            <div class="product-warehouse-form-container">
                            
                        
                                    <?php
                                        $product_id = $post->ID;
                                        $qty = '';
                                        if($op_warehouse->is_instore($warehouse_id,$product_id))
                                        {
                                            $qty = $op_warehouse->get_qty($warehouse_id,$product_id);
                                            
                                        }
                                        $wrapper_class = 'op-no-display';
                                        if ( 'yes' === get_option( 'woocommerce_manage_stock' ) ) {
                                            $wrapper_class = $product->get_manage_stock( 'edit' ) ? '' : 'op-no-display';
                                            woocommerce_wp_text_input(
                                                array(
                                                    'id'                => '_op_stock',
                                                    'name'                => '_op_stock['.$warehouse_id.']',
                                                    'value'             => $qty,
                                                    'label'             => '',
                                                    'type'              => 'text',
                                                    'class' => 'short wc_op_input_stock',
                                                    'wrapper_class' => $wrapper_class 
                                                )
                                            );
                                        }

                                       
                                        $checked_value = $op_warehouse->is_instore($warehouse_id,$product_id) ? 'yes' : 'no';
                                        woocommerce_wp_checkbox(
                                            array(
                                                'id'                => '_op_instore',
                                                'name'                => '_op_instore['.$warehouse_id.']',
                                                'checked_value'             => 'yes',
                                                'value'     => $checked_value,
                                                'label'             => '',
                                                'type'              => 'text',
                                                'class' => 'checkbox wc_op_input_instore',
                                                'description' => __('In Store','openpos')
                                    
                                            )
                                        );
                                    ?>
                            
                           
                            </div>
                        </fieldset>
                        <?php endif; ?>
                        <hr/>
                    <?php endforeach; ?>
                    
                </div>
                <?php
            }

        }

    }
    function woocommerce_variation_options_inventory($loop, $variation_data, $variation){

        global $op_warehouse;
        $warehouses = $op_warehouse->warehouses();
        if(count($warehouses) > 1)
        {

            ?>
            <div class="op-product-outlet-stock-variation woocommerce_options_panel ">
                <p class="op-stock-label"><?php echo __('Other Outlet Stock quantity','openpos'); ?></p>

               
                    <?php foreach($warehouses as $warehouse): ?>
                        <?php if($warehouse['id'] > 0): $warehouse_id  = $warehouse['id']; ?>
                        <fieldset class="form-field">
                                    <label><?php echo $warehouse['name']; ?></label>
                                    <div class="product-warehouse-form-container">
                                    <?php

                                    $qty = 0;
                                    if($variation && isset($variation->ID))
                                    {
                                        $variation_id = $variation->ID;

                                        $qty = 1 * $op_warehouse->get_qty($warehouse_id,$variation_id);

                                        if(!$op_warehouse->is_instore($warehouse_id,$variation_id))
                                        {
                                                $qty = '';
                                        }
                                    }
                                    woocommerce_wp_text_input(
                                        array(
                                            'id'                => "_op_stock_{$warehouse_id}_{$loop}",
                                            'name'              => "_op_stock[{$warehouse_id}][{$loop}]",
                                            'label'             => $warehouse['name'],
                                            'value' => $qty,
                                            'type'         => 'text',
                                            'wrapper_class' => 'form-row show_if_variation_manage_stock',
                                            'class' => 'short wc_op_input_stock'
                                        )
                                    );
                                    $checked_value = $op_warehouse->is_instore($warehouse_id,$variation_id) ? 'yes' : 'no';
                                    woocommerce_wp_checkbox(
                                        array(
                                            'id'                => "_op_instore_{$warehouse_id}_{$loop}",
                                            'name'              => "_op_instore[{$warehouse_id}][{$loop}]",
                                            'checked_value'             => 'yes',
                                            'value'     => $checked_value,
                                            'label'             => '',
                                            'type'              => 'text',
                                            'class' => 'checkbox wc_op_input_instore',
                                            'description' => __('In Store','openpos')
                                
                                        )
                                    );
                                    ?>
                                </div>
                        </fieldset>
                        <?php endif; ?>
                    <?php endforeach; ?>

            </div>
            <?php
        }

    }

    function woocommerce_product_options_pricing(){
        global $post;
        global $op_woo;
        $product = wc_get_product($post->ID);
        $product_type = $product->get_type();
        $price = '';
        if($product && $product_type != 'variable')
        {
            $tmp_price = $op_woo->get_cost_price($post->ID,true);
            if($tmp_price !== '')
            {
                $price = $tmp_price;
            }
        }

        woocommerce_wp_text_input(
            array(
                'id'        => '_op_cost_price',
                'value'     => $price,
                'label'     => __( 'OP Cost price', 'openpos' ) . ' (' . get_woocommerce_currency_symbol() . ')',
                'data_type' => 'price',
                'desc_tip'   => true,
                'description'   => __( 'Cost price - Use to get commision report', 'openpos' ),
            )
        );

    }
    function woocommerce_variation_options_pricing($loop, $variation_data, $variation){
        global $op_woo;
        $price = '';
        if($variation && isset($variation->ID))
        {
            $variation_id = $variation->ID;

            $tmp_price =  $op_woo->get_cost_price($variation_id,true); 
            if($tmp_price !== '')
            {
                $price = $tmp_price;
            }
        }
        woocommerce_wp_text_input(
            array(
                'id'            => "_op_cost_price{$loop}",
                'name'          => "_op_cost_price[{$loop}]",
                'value'         => $price,
                'label'         => __( 'OP Cost price', 'openpos' ) . ' (' . get_woocommerce_currency_symbol() . ')',
                'data_type'     => 'price',
                'wrapper_class' => 'form-row form-row-first',
                'placeholder'   => __( 'Cost price', 'openpos' ),
                'desc_tip'   => true,
                'description'   => __( 'Cost price - Use to get commision report', 'openpos' ),
            )
        );

    }
    

    public function woocommerce_product_data_tabs($product_data_tabs ){
        $product_data_tabs['op-product-tab'] = array(
            'label' => __( 'OpenPOS', 'openpos' ),
            'target' => 'openpos_product_data',
        );
        return $product_data_tabs;
    }
    public function woocommerce_product_data_panels(){
        global $woocommerce, $post;
        $openpos_type = $this->settings_api->get_option('openpos_type','openpos_pos');
        $tmp_price = get_post_meta($post->ID,'_op_weight_base_pricing',true);
        if($tmp_price == 'yes' )
        {
            $tmp_price = 'weight_base';
        }
        if($tmp_price != 'weight_base' && $tmp_price != 'length_base' && $tmp_price != 'price_base')
        {
            $tmp_price = 'no';
        }
        $pricing_option = apply_filters(
            'op_admin_weight_base_pricing_option',array(
                'id'      => '_op_weight_base_pricing',
                'value'   => $tmp_price,
                'label'   => __( 'POS Base Pricing', 'openpos' ),
                'cbvalue' => 'yes',
                'options' => array(
                    'weight_base' => __( 'Weight-Base Pricing', 'openpos' ),
                    'length_base' => __( 'Length-Base Pricing', 'openpos' ),
                    'price_base' => __( 'Price-Base Pricing', 'openpos' ),
                    'no' => __( 'No', 'openpos' ),
                )
                )
            );
        $tmp_qty_option = get_post_meta($post->ID,'_op_qty_decimal',true);
        
        $qty_option =  apply_filters(
            'op_admin_qty_decimal_option',array(
                'id'      => '_op_qty_decimal',
                'value'   => $tmp_qty_option,
                'label'   => __( 'Allow Decimal Qty', 'openpos' ),
                'cbvalue' => 'yes',
                )
            );
        $tmp_decal_option = get_post_meta($post->ID,'_op_allow_decal',true);
        $decal_option =  apply_filters(
            'op_admin_decal_option',array(
                'id'      => '_op_allow_decal',
                'value'   => $tmp_decal_option,
                'label'   => __( 'Allow print decal', 'openpos' ),
                'cbvalue' => 'yes',
                )
            );
       
        
        ?>
        <!-- id below must match target registered in above add_my_custom_product_data_tab function -->
        <div id="openpos_product_data" class="panel woocommerce_options_panel">
            
            <div class="options_group"><?php woocommerce_wp_select( $pricing_option); ?> </div>
            <div class="options_group"><?php woocommerce_wp_checkbox( $qty_option); ?> </div>
            <?php
           
            if($openpos_type == 'restaurant')
            {
                echo '<div class="options_group">';
                woocommerce_wp_checkbox( $decal_option);
                echo '</div>';
            }

            ?>
            
        </div>
        <?php
    }
    public function op_generate_pin(){
        $result = array(
            'status' => 0,
            'data' => array(),
            'message' => ''
        );
        $user_id = isset($_REQUEST['user_id']) ? $_REQUEST['user_id'] : 0;
        $this->_nonce_check();
        if($user_id)
        {
            $user = get_user_by('id', $user_id);
            if($user)
            {
                global $wpdb;
                $row_count = 1;
                $random_number = 0;
                $pin_lengh = apply_filters('op_generate_pin_length',6);
                $from = pow(($pin_lengh - 1),4);
                $to = pow(10,$pin_lengh) - 1;

                while ($row_count > 0) {
                    $random_number = mt_rand($from, $to); 
                    $sql = $wpdb->prepare("SELECT * FROM `{$wpdb->usermeta}`  WHERE meta_key= '_op_pin' AND meta_value='%d'",$random_number);
                    $users_found = $wpdb->get_results( $sql );
                    $row_count = count($users_found);
                }
                if($random_number)
                {
                    update_user_meta($user_id,'_op_pin',$random_number);
                    $result['status'] = 1;
                    $result['data'] = $random_number;
                    $result['message'] = sprintf(__( 'Current PIN %s','openpos'  ),$random_number);
                }
            }
        }
        echo json_encode($result);
        exit;
    }
    public function admin_enqueue_scripts($hook){
        $allow = array('user-edit.php','profile.php');
        
        if(!in_array($hook,$allow))
        {
            return;
        }
        wp_enqueue_script('op_user_pin', OPENPOS_URL . '/assets/js/pin.js',array('jquery'),time());
    }
    public function edit_user_profile($user){
        $user_id = $user->ID;
        $pin = get_user_meta($user_id,'_op_pin',true);
        $allow_pos = get_user_meta($user_id,'_op_allow_pos',true);
        if(!$allow_pos)
        {
            $allow_pos = 0;
        }else{
            $allow_pos = 1;
        }
        if($allow_pos)
        {
            $op_nonce = wp_create_nonce( 'op_nonce' );
            ?>
            <h2><?php _e( 'POS Account Management','openpos' ); ?></h2>
            <table class="form-table" role="presentation">
                <tr id="op_pin" class="user-op-pin-wrap">
                    <th><label for="op_pin"><?php _e( 'Login PIN','openpos' ); ?></label></th>
                    <td>
                            <div class="generate-pin-link">
                                <button type="button" class="button button-secondary" data-op_nonce="<?php echo $op_nonce; ?>" id="generate-pin-link" data-id="<?php echo $user_id; ?>"><?php _e( 'Generate PIN','openpos' ); ?></button>
                            </div>
                            <p class="description" id="user-pint-description"><?php echo $pin ?  sprintf(__( 'Current PIN %s','openpos'  ),$pin) : ''; ?></p>
                    </td>
                </tr>
            </table>
            <?php
        }
        
    }
    public function op_force_download(){
        $this->_nonce_check();
        $file = isset($_REQUEST['file']) ? $_REQUEST['file'] : '';
        $upload_dir = wp_upload_dir();
        $upload_dir_path = $upload_dir['basedir'];
        $upload_dir_path = rtrim($upload_dir_path,'/');
        if($file)
        {
            $file_path = $upload_dir_path.'/'.ltrim($file);
            if(file_exists($file_path))
            {
                //header('Content-type: '.mime_content_type($file_path));
                header('Content-type: text/csv');
                header('Content-Disposition: attachment; filename="' . $file . '"');
                readfile($file_path);
            }
        }

        
        exit;
    }
    public function settings_save() {
		if ( ! is_admin() ) {
			return;
		}
        $flush = false;
        if (isset($_POST['openpos_base'])) {
            
            $value = wc_sanitize_permalink( wp_unslash( $_POST['openpos_base'] ) ); 
            update_option('openpos_base', $value ?: '');
            $flush = true;
        }
        if (isset($_POST['openpos_kitchen_base'])) {
            
            $value = wc_sanitize_permalink( wp_unslash( $_POST['openpos_kitchen_base'] ) ); 
            update_option('openpos_kitchen_base', $value ?: '');
            $flush = true;
        }
        if (isset($_POST['openpos_queue_base'])) {
            
            $value = wc_sanitize_permalink( wp_unslash( $_POST['openpos_queue_base'] ) ); 
            update_option('openpos_queue_base', $value ?: '');
            $flush = true;
        }
        if (isset($_POST['openpos_bill_base'])) {
            $value = wc_sanitize_permalink( wp_unslash( $_POST['openpos_bill_base'] ) ); 
            update_option('openpos_bill_base', $value ?: '');
            $flush = true;
        }
        if( $flush )
        {
            flush_rewrite_rules(); 
        }
    }
    

}