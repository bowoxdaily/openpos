<?php
defined( 'ABSPATH' ) || exit;
if(!class_exists('OP_Receipt'))
{
    class OP_Receipt{
        public $_post_type = '_op_receipt';
        
       
        public function __construct()
        {
           
            $this->init();
        }
        function init(){
            // create openpos data directory
            add_action( 'wp_ajax_openpos_update_receipt_template', array($this,'update_receipt_template') );
            add_action( 'wp_ajax_openpos_delete_receipt', array($this,'delete_receipt') );
            add_action( 'wp_ajax_openpos_update_receipt_content', array($this,'update_receipt_content') );
            add_action( 'wp_ajax_openpos_update_receipt_draft', array($this,'update_receipt_draft') );
            add_action( 'wp_ajax_openpos_update_receipt_preview', array($this,'receipt_preview') );
            add_action( 'op_register_form_end', array($this,'op_register_form_end'),10,1 );
            add_action( 'op_register_save_after', array($this,'op_register_save_after'),10,3 );
           

            add_filter('op_get_login_cashdrawer_data',array($this,'op_setting_data'),10,1);
            add_filter('woocommerce_email_classes',array($this,'woocommerce_email_classes'),10,1);
        }
        function _nonce_check(){
            $nonce = $_REQUEST['op_nonce'];
            if ( ! wp_verify_nonce( $nonce, 'op_nonce' ) ) {
                die( __( 'Security check', 'openpos' ) ); 
            } 
    
        }
        public function receipt_types(){
            global $OPENPOS_SETTING;
            $openpos_type = $OPENPOS_SETTING->get_option('openpos_type','openpos_pos');
            $result = array();

            $result['receipt'] = array(
                'code' => 'receipt',
                'label' => __('Receipt','openpos'),
                'sample_data' => 'order_formatted.json',
                'sample_template' => 'receipt_template_sample.txt',
                'sample_template_css' => 'receipt_template_css_sample.txt',
                'default_template' => ''
            );
            $result['zreport'] = array(
                'code' => 'zreport',
                'label' => __('X-Report','openpos'),
                'sample_data' => 'zreport_data.json',
                'sample_template' => 'report_template_sample.txt',
                'sample_template_css' => 'report_template_css.txt',
                'default_template' => ''
            );
            if($openpos_type == 'restaurant')
            {
                $result['kitchen_receipt'] = array(
                    'code' => 'kitchen_receipt',
                    'label' => __('Kitchen Receipt','openpos'),
                    'sample_data' => 'desk_formatted.json',
                    'sample_template' => 'kitchen_receipt_template.txt',
                    'sample_template_css' => 'kitchen_receipt_css.txt',
                    'default_template' => ''
                );
                $result['delivery_receipt'] = array(
                    'code' => 'delivery_receipt',
                    'label' => __('Delivery Receipt','openpos'),
                    'sample_data' => 'delivery_formatted.json',
                    'sample_template' => 'delivery_receipt_template.txt',
                    'sample_template_css' => 'delivery_receipt_css.txt',
                    'default_template' => ''
                );
                $result['product_decal'] = array(
                    'code' => 'product_decal',
                    'label' => __('Product Decal','openpos'),
                    'sample_data' => 'items_formatted.json',
                    'sample_template' => 'product_decal_template.txt',
                    'sample_template_css' => 'product_decal_css.txt',
                    'default_template' => ''
                );
            }
            return $result;
            
            
        }
        public function templates($status = ''){
            $result = array();

            if(!$status)
            {
                $statues = array('publish','draft');
            }else{
                $statues = array($status);
            }

            $posts = get_posts([
                'post_type' => $this->_post_type,
                'post_status' => $statues,
                'numberposts' => -1
            ]);
            foreach($posts as $p)
            {
                $result[] = $this->get($p->ID);
            }
            return $result;
        }
        public function delete($id){
            $post = get_post($id);
           
            if($post->post_type == $this->_post_type)
            {
                wp_trash_post( $id  );
                return true;
            }
            return false;
        }
        public function save($params){
            
            $id  = 0;
            if(isset($params['id']) && $params['id'] > 0)
            {
                $id = $params['id'];
            }
            
            $args = array(
                'ID' => $id,
                'post_title' => $params['name'],
                'post_type' => $this->_post_type,
                'post_status' => $params['status'],
                'post_parent' => 0
            );
            if($id)
            {
                $post_id = wp_update_post($args);
            }else{
                $post_id = wp_insert_post($args);
            }
            
            $type = isset($params['type']) ? $params['type'] : 'receipt';
            
            if(!is_wp_error($post_id)){
                update_post_meta($post_id,'template_type',$type);
                return $post_id;
            }else{
                //there was an error in the post insertion,
                throw new Exception($post_id->get_error_message()) ;
            }
        }
        public function get($id)
        {
            global $OPENPOS_CORE;
            $post = get_post($id);
            if(!$post)
            {
                return array();
            }
            if($post->post_type != $this->_post_type)
            {
                return array();
            }
            $name = $post->post_title;
            $created_by = get_the_author_meta('display_name',$post->post_author);

          
            $created_at_time = $post->post_date;
            $created_at = $OPENPOS_CORE->render_ago_date_by_time_stamp($created_at_time);
            $status = $post->post_status;
            
            $content = $post->post_content;
            $custom_css = get_post_meta($id,'custom_css',true);
            $paper_width = get_post_meta($id,'paper_width',true);
            $padding_top = get_post_meta($id,'padding_top',true);
            $padding_right = get_post_meta($id,'padding_right',true);
            $padding_bottom = get_post_meta($id,'padding_bottom',true);
            $padding_left = get_post_meta($id,'padding_left',true);
            $tmp_type = get_post_meta($id,'template_type',true);
            $type = $tmp_type ? $tmp_type : 'receipt';
            
            $result = array(
                'id' => $id,
                'name' => $name,
                'type' => $type,
                'created_by' => $created_by,
                'created_at' => $created_at,
                'status' => $status,
                'content' => $content,
                'custom_css' => $custom_css,
                'paper_width' => $paper_width,
                'padding_top' => $padding_top,
                'padding_right' => $padding_right,
                'padding_bottom' => $padding_bottom,
                'padding_left' => $padding_left
            );
        
            return apply_filters('op_receipt_template_get_data',$result,$this);
        }
        public function update_receipt_template(){
            $this->_nonce_check();
            $result = array(
                'status' => 0,
                'message' => 'Unknown message',
                'data' => array()
            );
            $name = isset($_REQUEST['name']) ? $_REQUEST['name'] : '';
            $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
            $type = isset($_REQUEST['type']) ? $_REQUEST['type'] : 'receipt';
            $status = isset($_REQUEST['status'])  ? $_REQUEST['status'] : 'draft';
            if($name)
            {
                $params = array(
                    'id' => $id,
                    'name' => $name,
                    'type' => $type,
                    'status' => $status
                );
                $post_id = $this->save($params);
                if($post_id)
                {
                    $result['status'] = 1;
                }
            }else{
                $result['message'] = __('Please enter template name','openpos');
            }
            echo json_encode($result);
            exit;
        }
        public function delete_receipt(){
            $this->_nonce_check();
            $result = array(
                'status' => 0,
                'message' => 'Unknown message',
                'data' => array()
            );
            $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
            if($this->delete($id))
            {
                $result['status'] = 1;
            }
            echo json_encode($result);
            exit;
        }
        public function update_receipt_content(){
            $this->_nonce_check();
            $result = array(
                'status' => 0,
                'message' => 'Unknown message',
                'data' => array()
            );
            $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
            $template = $this->get($id);
            if(!empty($template))
            {
                $params = $_POST;
                foreach($params as $key => $val)
                {
                    if($key == "content")
                    {
                        $my_post = array();
                        $my_post['ID'] = $id;
                        $my_post['post_content'] = $val;
                        wp_update_post( $my_post );
                    }else{
                        if($key != 'id' )
                        {
                            update_post_meta($id,$key,$val);
                        }
                    }
                }
                update_post_meta($id,'_tmp_setting','');
                $result['status'] = 1;
            }else{
                $result['message'] = 'Template not found';
            }
            echo json_encode($result);
            exit;
        }
        public function update_receipt_draft(){
            $this->_nonce_check();
            $result = array(
                'status' => 0,
                'message' => 'Unknown message',
                'data' => array()
            );
            $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
            $template = $this->get($id);
            if(!empty($template))
            {
                $params = $_POST;
                $key = "_tmp_setting";
                update_post_meta($id,$key,$params);
                $result['status'] = 1;
            }else{
                $result['message'] = 'Template not found';
            }
            echo json_encode($result);
            exit;
        }
        public function receipt_preview(){
            $this->_nonce_check();
            $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
            $order_id = isset($_REQUEST['order_id']) ? (int)$_REQUEST['order_id'] : 0;
            $template = $this->get($id);
            if(!empty($template))
            {
                wp_register_script('openpos.admin.receipt.ejs', OPENPOS_URL.'/assets/js/ejs.js',array('jquery'));

                $data = $this->get_template_preview_data($template);

                

                if($order_id)
                {
                    $order = wc_get_order($order_id);
                    if($order)
                    {
                        $order_data = $order->get_meta('_op_order');
                        if($order_data)
                        {
                            $order_json = json_encode($order_data);
                            $data['order_json'] = $order_json;
                        }
                    }
                    
                }
                $is_review = true;
                require(OPENPOS_DIR.'templates/admin/print_receipt.php');
            }else{
               echo 'Template not found';
            }
            
            
            exit;
        }
        public function get_template_preview_data($template){
           
            $setting = $template;
            $html_header = '';

            $receipt_padding_top = $setting['padding_top'];

            $unit = 'in';
            $receipt_padding_right = $setting['padding_right'];
            $receipt_padding_bottom = $setting['padding_bottom'];
            $receipt_padding_left = $setting['padding_left'];
            $receipt_width = $setting['paper_width'];
            $receipt_css = $setting['custom_css'];
           
            $receipt_template =  do_shortcode($setting['content']);

           

            $html_header = '<style type="text/css" media="print,screen">';
            $html_header .= 'body{ ';
            $html_header .= 'background: #FFEB3B;';   
            $html_header .=  '}';
            $html_header .= '#op-page-cut{page-break-after: always; }';
            $html_header .= '#invoice-POS { ';
            $html_header .= 'padding:  '.$receipt_padding_top.$unit. ' ' . $receipt_padding_right.$unit .' '.$receipt_padding_bottom.$unit.' '.$receipt_padding_left.$unit.';';
            $html_header .= 'margin: 0 auto;';
            $html_header .= 'background: #fff;';
            $html_header .= 'width: '.$receipt_width.$unit.' ;';
            $html_header .=  '}';

            $html_header .= $receipt_css;
            $html_header .= '</style>';
           
            $html = '<div id="invoice-POS">';
            $html_body = html_entity_decode(esc_html($receipt_template));
            
            $html_body = trim(preg_replace('/\s\s+/', ' ', $html_body));
            $html .= $html_body;
            $html .= '</div>';
            
            $html = do_shortcode($html);
            $html_page_cut = '<p id="op-page-cut">&nbsp;</p>';
            $html .= $html_page_cut;
            $data = array(
                'setting' => $setting,
                'html_header' => $html_header,
                'html_body' =>   addslashes($html) ,
                'order_json' =>  ''
    
            );
            if(isset($template['type']) )
            {
                $template_type = $template['type'];
                $receipt_types = $this->receipt_types();
                if(isset($receipt_types[$template_type]))
                {
                    $receipt_type = $receipt_types[$template_type];
               
                    if(isset($receipt_type['sample_data']) && $receipt_type['sample_data'])
                    {
                        $data['order_json'] = file_get_contents(OPENPOS_DIR.'default/'.$receipt_type['sample_data']);
                    }
                }
                
            }
           
            return $data;
        }
        public function get_register_template($register_id){
            $template_id = get_post_meta($register_id,'_op_receipt_template',true);
            if(!$template_id)
            {
                $template_id = 0;
            }
            return $this->get($template_id);
        }
        public function get_register_gift_template($register_id){
            $template_id = get_post_meta($register_id,'_op_gift_receipt_template',true);
            if(!$template_id)
            {
                $template_id = 0;
            }
            if($template_id == -1)
            {
                return false;
            }
            return $this->get($template_id);
        }
        public function get_zreport_template($register_id){
            $template_id = get_post_meta($register_id,'_op_zreport_template',true);
            if(!$template_id)
            {
                $template_id = 0;
            }
            return $this->get($template_id);
        }
        public function get_kitchen_template($register_id)
        {
            $template_id = get_post_meta($register_id,'_op_kitchen_template',true);
            if(!$template_id)
            {
                $template_id = 0;
            }
            return $this->get($template_id);
        }
        public function get_delivery_template($register_id)
        {
            $template_id = get_post_meta($register_id,'_op_delivery_template',true);
            if(!$template_id)
            {
                $template_id = 0;
            }
            return $this->get($template_id);
        }
        public function get_decal_template($register_id)
        {
            $template_id = get_post_meta($register_id,'_op_decal_template',true);
            if($template_id == -1 )
            {
                return false;
            }
            if(!$template_id)
            {
                $template_id = 0;
            }
            return $this->get($template_id);
        }
        
        
        public function op_register_form_end($default){
            global $OPENPOS_SETTING;
            $openpos_type = $OPENPOS_SETTING->get_option('openpos_type','openpos_pos');
            
            $templates = $this->templates('publish');
            $current_id = 0;
            $current_gift_id = 0;
            $current_zreport_id = 0;
            $current_kitchen_id = 0;
            $current_delivery_id = 0;
            $current_decal_id = 0;
           
            if(isset($default['id']) && $default['id'])
            {
                $current_receipt = $this->get_register_template($default['id']);
                $current_gift_receipt = $this->get_register_gift_template($default['id']);
                $current_zreport = $this->get_zreport_template($default['id']);
                $current_kitchen = $this->get_kitchen_template($default['id']);
                $current_delivery = $this->get_delivery_template($default['id']);
                $current_decal= $this->get_decal_template($default['id']);
                if(!$current_decal)
                {
                    $current_decal_id = -1;
                }
                
                if($current_receipt && !empty($current_receipt))
                {
                    $current_id = $current_receipt['id'];
                }
                if(!is_array($current_gift_receipt))
                {
                    $current_gift_id = -1;
                }else{
                    if($current_gift_receipt && !empty($current_gift_receipt))
                    {
                        $current_gift_id = $current_gift_receipt['id'];
                    }
                }
                
                if($current_zreport && !empty($current_zreport))
                {
                    $current_zreport_id = $current_zreport['id'];
                }
                if($current_kitchen && !empty($current_kitchen))
                {
                    $current_kitchen_id = $current_kitchen['id'];
                }
                if($current_delivery && !empty($current_delivery))
                {
                    $current_delivery_id = $current_delivery['id'];
                }
                
                if($current_decal && !empty($current_decal))
                {
                    $current_decal_id = $current_decal['id'];
                }
                
                
            }
            
            ?>
            <div class="form-group">
                <label class="col-sm-2 control-label"><?php echo __( 'Receipt', 'openpos' ); ?></label>
                <div class="col-sm-10">
                    <select class="form-control" name="receipt_template">
                            <option value="0" <?php echo $current_id == 0 ? 'selected':''; ?>><?php echo __('Default Template','openpos'); ?></option>
                            <?php foreach($templates as $template): if($template['type'] != 'receipt' ){ continue ; }  ?>
                                    <option value="<?php echo $template['id']; ?>"  <?php echo $current_id == $template['id'] ? 'selected':''; ?> ><?php echo $template['name']; ?></option>
                            <?php endforeach; ?>
                    </select>
                    <small id="emailHelp" class="form-text text-muted"><?php echo __( 'Default template = Use receipt template setting at : admin/pos/setting/receipt template setting', 'openpos' ); ?></small>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label"><?php echo __( 'Gift Receipt', 'openpos' ); ?></label>
                <div class="col-sm-10">
                    <select class="form-control" name="gift_receipt_template">
                            <option value="0" <?php echo $current_gift_id == 0 ? 'selected':''; ?>><?php echo __('Default Template','openpos'); ?></option>
                            <?php foreach($templates as $template):  if($template['type'] != 'receipt' ){ continue ; } ?>
                                    <option value="<?php echo $template['id']; ?>"  <?php echo $current_gift_id == $template['id'] ? 'selected':''; ?> ><?php echo $template['name']; ?></option>
                            <?php endforeach; ?>
                            <option value="-1" <?php echo $current_gift_id == -1 ? 'selected':''; ?>><?php echo __('Disable','openpos'); ?></option>
                    </select>
                    <small id="emailHelp" class="form-text text-muted"><?php echo __( 'Default template = Use receipt template setting at : admin/pos/setting/receipt template setting', 'openpos' ); ?></small>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label"><?php echo __( 'xReport', 'openpos' ); ?></label>
                <div class="col-sm-10">
                    <select class="form-control" name="zreport_template">
                            <option value="0" <?php echo $current_zreport_id == 0 ? 'selected':''; ?>><?php echo __('No','openpos'); ?></option>
                            <?php foreach($templates as $template): if($template['type'] != 'zreport' ){ continue ; } ?>
                                    <option value="<?php echo $template['id']; ?>"  <?php echo $current_zreport_id == $template['id'] ? 'selected':''; ?> ><?php echo $template['name']; ?></option>
                            <?php endforeach; ?>
                    </select>
                    <small id="emailHelp" class="form-text text-muted"><?php echo __( 'Report template print end working session sale ', 'openpos' ); ?></small>
                </div>
            </div>
            <?php if($openpos_type == 'restaurant') : ?>
            <div class="form-group">
                <label class="col-sm-2 control-label"><?php echo __( 'Kitchen Receipt', 'openpos' ); ?></label>
                <div class="col-sm-10">
                    <select class="form-control" name="kitchen_template">
                            <option value="0" <?php echo $current_kitchen_id == 0 ? 'selected':''; ?>><?php echo __('Default Template','openpos'); ?></option>
                            <?php foreach($templates as $template): if($template['type'] != 'kitchen_receipt' ){ continue ; } ?>
                                    <option value="<?php echo $template['id']; ?>"  <?php echo $current_kitchen_id == $template['id'] ? 'selected':''; ?> ><?php echo $template['name']; ?></option>
                            <?php endforeach; ?>
                    </select>
                    <small id="emailHelp" class="form-text text-muted"><?php echo __( 'Receipt print in table / takeaway.', 'openpos' ); ?></small>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label"><?php echo __( 'Delivery Receipt', 'openpos' ); ?></label>
                <div class="col-sm-10">
                    <select class="form-control" name="delivery_receipt">
                            <option value="0" <?php echo $current_delivery_id == 0 ? 'selected':''; ?>><?php echo __('Default Template','openpos'); ?></option>
                            <?php foreach($templates as $template): if($template['type'] != 'delivery_receipt' ){ continue ; } ?>
                                    <option value="<?php echo $template['id']; ?>"  <?php echo $current_delivery_id == $template['id'] ? 'selected':''; ?> ><?php echo $template['name']; ?></option>
                            <?php endforeach; ?>
                    </select>
                    <small id="emailHelp" class="form-text text-muted"><?php echo __( 'Receipt print in delivery takeaway for shipper.', 'openpos' ); ?></small>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label"><?php echo __( 'Product Decal', 'openpos' ); ?></label>
                <div class="col-sm-10">
                    <select class="form-control" name="product_decal">
                            
                            <option value="0" <?php echo $current_decal_id == 0 ? 'selected':''; ?>><?php echo __('Default Template','openpos'); ?></option>
                            <?php foreach($templates as $template): if($template['type'] != 'product_decal' ){ continue ; } ?>
                                    <option value="<?php echo $template['id']; ?>"  <?php echo $current_decal_id == $template['id'] ? 'selected':''; ?> ><?php echo $template['name']; ?></option>
                            <?php endforeach; ?>
                            <!-- <option value="-1" <?php echo $current_decal_id == -1 ? 'selected':''; ?>><?php echo __('Disable','openpos'); ?></option> -->
                    </select>
                    <small id="emailHelp" class="form-text text-muted"><?php echo __( 'Receipt print in delivery takeaway for shipper.', 'openpos' ); ?></small>
                </div>
            </div>
        <?php endif; ?>
        <?php
        }
        public function op_register_save_after($id,$params,$op_register){
               
                if($id && isset($params['receipt_template']))
                {
                    update_post_meta($id,'_op_receipt_template',(int)$params['receipt_template']);
                }
                if($id && isset($params['gift_receipt_template']))
                {
                    update_post_meta($id,'_op_gift_receipt_template',(int)$params['gift_receipt_template']);
                }
                
                if($id && isset($params['zreport_template']))
                {
                    update_post_meta($id,'_op_zreport_template',(int)$params['zreport_template']);
                }
                if($id && isset($params['kitchen_template']))
                {
                    update_post_meta($id,'_op_kitchen_template',(int)$params['kitchen_template']);
                }
                if($id && isset($params['delivery_receipt']))
                {
                    update_post_meta($id,'_op_delivery_template',(int)$params['delivery_receipt']);
                }

                if($id && isset($params['product_decal']))
                {
                    update_post_meta($id,'_op_decal_template',(int)$params['product_decal']);
                }
                
                
                
        }
        public function op_setting_data($session_response_data){
            global $OPENPOS_SETTING;
            $openpos_type = $OPENPOS_SETTING->get_option('openpos_type','openpos_pos');

            //receipt_full_template
            $login_cashdrawer_id = $session_response_data['login_cashdrawer_id'];
            $login_warehouse_id = $session_response_data['login_warehouse_id'];
            if($login_cashdrawer_id)
            {
                global $register_id;
                global $warehouse_id;
                $register_id = $login_cashdrawer_id;
                $warehouse_id = $login_warehouse_id;


                $receipt = $this->get_register_template($login_cashdrawer_id);
                $gift_receipt = $this->get_register_gift_template($login_cashdrawer_id);
                $current_zreport = $this->get_zreport_template($login_cashdrawer_id);
                $current_kitchen = $this->get_kitchen_template($login_cashdrawer_id);
                $current_delivery = $this->get_delivery_template($login_cashdrawer_id);
                $current_decal = $this->get_decal_template($login_cashdrawer_id);
                if($receipt && !empty($receipt))
                {
                    
                    $session_response_data['setting']['receipt_full_template'] = $this->generate_full_receipt_template($receipt);

                    $session_response_data['setting']['receipt_template_header'] = '';
                    $session_response_data['setting']['receipt_template_footer'] = '';
                    $session_response_data['setting']['receipt_template'] = '';
                    $session_response_data['setting']['receipt_css'] = '';
                }
                if(!is_array($gift_receipt))
                {
                    $session_response_data['setting']['receipt_gift_full_template'] = '';
                    $session_response_data['setting']['pos_gift_receipt_enable'] = 'no';
                }else{
                    if($gift_receipt && !empty($gift_receipt))
                    {
                        
                        $session_response_data['setting']['receipt_gift_full_template'] = $this->generate_full_receipt_template($gift_receipt,'gift');
                    }
                }
                
                if($current_zreport && !empty($current_zreport))
                {
                    
                    $session_response_data['pos_report_template']= $this->generate_full_receipt_template($current_zreport,'report');
                }
                if($openpos_type == 'restaurant') 
                {
                    if($current_kitchen && !empty($current_kitchen))
                    {
                        
                        $session_response_data['setting']['pos_kitchen_receipt_template']= $this->generate_full_receipt_template($current_kitchen,'kitchen');
                    }else{
                        $file_name = 'kitchen_receipt_template_full.txt';
                        $kitchen_file_path = rtrim(OPENPOS_DIR,'/').'/default/'.$file_name;
                        $kitchen_receipt_template = file_get_contents($kitchen_file_path);
                        
                        $session_response_data['setting']['pos_kitchen_receipt_template']= $kitchen_receipt_template;
                    }
                    if($current_delivery && !empty($current_delivery))
                    {
                        
                        $session_response_data['setting']['pos_delivery_receipt_template']= $this->generate_full_receipt_template($current_delivery,'delivery');
                        
                    }else{
                        $file_name = 'delivery_receipt_template_full.txt';
                        $kitchen_file_path = rtrim(OPENPOS_DIR,'/').'/default/'.$file_name;
                        $kitchen_receipt_template = file_get_contents($kitchen_file_path);
                        
                        $session_response_data['setting']['pos_delivery_receipt_template']= $kitchen_receipt_template;
                    }
                    if($current_decal && !empty($current_decal))
                    {
                        
                        $session_response_data['setting']['pos_decal_receipt_template']= $this->generate_full_receipt_template($current_decal,'decal');
                        
                    }else{
                        $file_name = 'product_decal_template_full.txt';
                        $kitchen_file_path = rtrim(OPENPOS_DIR,'/').'/default/'.$file_name;
                        $kitchen_receipt_template = file_get_contents($kitchen_file_path);
                        
                        $session_response_data['setting']['pos_decal_receipt_template']= $kitchen_receipt_template;
                    }
                    
                }
                
            }
            return $session_response_data;
        }
        public function generate_full_receipt_template($receipt,$type = ''){
            global $shortcode_tags;
            unset($shortcode_tags['order_barcode']);
            unset($shortcode_tags['order_qrcode']);
            $setting = $receipt;
            $html_header = '';
            $receipt_padding_top = $setting['padding_top'];
            $unit = 'in';
            $receipt_padding_right = $setting['padding_right'];
            $receipt_padding_bottom = $setting['padding_bottom'];
            $receipt_padding_left = $setting['padding_left'];
            $receipt_width = $setting['paper_width'];
            $receipt_css = $setting['custom_css'];
            $receipt_template = do_shortcode($setting['content']);
            $html_header = '<style type="text/css" media="print,screen">';
            $html_header .= '#op-page-cut{page-break-after: always; }';
            $html_header .= '#invoice-POS { ';
            $html_header .= 'padding:  '.$receipt_padding_top.$unit. ' ' . $receipt_padding_right.$unit .' '.$receipt_padding_bottom.$unit.' '.$receipt_padding_left.$unit.';';
            $html_header .= 'margin: 0 auto;';
            $html_header .= 'background: #fff;';
            $html_header .= 'width: '.$receipt_width.$unit.' ;';
            $html_header .=  '}';
            $html_header .= $receipt_css;
            $html_header .= '</style>';
            $html = '<div id="invoice-POS" class="tempalte-type-'.esc_attr($type).'">';
            $html_body = html_entity_decode(esc_html($receipt_template));
            $html_body = trim(preg_replace('/\s\s+/', ' ', $html_body));
            $html .= $html_body;
            $html .= '</div>';

            $html_page_cut = '<p id="op-page-cut">&nbsp;</p>';

            
            return '<html><head>'.$html_header.'</head><body style="margin:0;">'.$html.$html_page_cut.'</body></html>';
        }

        public function send_receipt(string $send_to = null,$order_data = array(),$register_id= 0,$source = '') //formatted order data 
        {
            global $OPENPOS_CORE;
            global $_g_send_to;
            $result = array('status' => 0, 'message' => '','data' => array());
            try{
                $allow_send = true;
                $order_id = isset($order_data['order_id']) ? $order_data['order_id'] : 0;
                $template_id = isset($_REQUEST['template_id']) ? (int)$_REQUEST['template_id'] : 0;
                $order = wc_get_order($order_id);
                if($source != 'manual' && $order_id && $order)
                {
                    if($order->get_meta('_op_email_receipt_sent') == 'yes'){

                        $allow_send = false;
                    }
                }
                if($allow_send && $send_to != null)
                {
                    $use_woo_email =  apply_filters('op_receipt_use_woo_email',false,$order_data);
                    
                   
                    if ( is_wp_error( $order ) ) {
                        $use_woo_email = false;
                    }
                    
                    if($use_woo_email && $order)
                    {
                        $_g_send_to = $send_to;
                        WC()->mailer()->customer_invoice( $order );
                        

                        $result['status'] = 1;
                        $result['message'] = __('Receipt has been sent','openpos');
                    }else{
                        $receipt_url = '';
                        if($order_id && $register_id)
                        {
                            $_op_session_id = $order->get_meta('_op_session_id');
                            $receipt_url = admin_url('admin-ajax.php?action=print_receipt&source=email&id='.(int)$order_id.'&s='.$_op_session_id);
                        }
                        $order_data['send_to'] = $send_to;
                        $order_data['receipt_url'] = $receipt_url;
                        if(!class_exists('OP_Email_Customer_Order_Receipt'))
                        {
                            \WC_Emails::instance();
                        }
                        $email = new OP_Email_Customer_Order_Receipt();
                        ;
                        if($order && $email->trigger( $order->get_id(), $order_data )){
                            $result['status'] = 1;
                            $result['message'] = __('Receipt has been sent','openpos');
                            if($source != 'manual' && $order_id)
                            {
                                $order->update_meta_data('_op_email_receipt_sent','yes');
                            }
                        }else{
                            $result['status'] = 0;
                            $result['message'] = __('Can not send receipt','openpos');;
                        } 

                       
                    }
                    
                }
                
            }catch (Exception $e)
            {
                $result['status'] = 0;
                $result['message'] = $e->getMessage();
            }
            return $result;
        }
        public function woocommerce_email_classes($email_classes){
            $email_classes['OP_Email_Customer_Order_Receipt'] = include OPENPOS_DIR . '/lib/emails/class-op-email-customer-order-receipt.php';
            
    
            return $email_classes;
        }
        

    }
}
?>