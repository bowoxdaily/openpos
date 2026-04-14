<?php
/**
 * Create default salon receipt templates
 * Call this when switching to salon mode or when viewing receipt templates
 */

function openpos_create_default_salon_receipt_templates() {
    global $op_receipt;
    
    $openpos_type = get_option('openpos_type', 'openpos_pos');
    
    // Only create salon templates if in salon mode
    if ($openpos_type !== 'salon') {
        return false;
    }
    
    // Check if default salon receipt already exists
    $existing = get_posts([
        'post_type' => '_op_receipt',
        'meta_query' => [
            [
                'key' => 'salon_default_template',
                'value' => 'yes'
            ]
        ],
        'numberposts' => 1
    ]);
    
    if (!empty($existing)) {
        return false; // Already created
    }
    
    // Read salon template files
    $template_dir = rtrim(OPENPOS_DIR, '/') . '/default/';
    
    $header = file_exists($template_dir . 'receipt_template_header_salon.txt') 
        ? file_get_contents($template_dir . 'receipt_template_header_salon.txt') 
        : '';
    
    $body = file_exists($template_dir . 'receipt_template_body_salon.txt') 
        ? file_get_contents($template_dir . 'receipt_template_body_salon.txt') 
        : '';
    
    $footer = file_exists($template_dir . 'receipt_template_footer_salon.txt') 
        ? file_get_contents($template_dir . 'receipt_template_footer_salon.txt') 
        : '';
    
    $css = file_exists($template_dir . 'receipt_css.txt') 
        ? file_get_contents($template_dir . 'receipt_css.txt') 
        : '';
    
    // Create receipt post
    $post_data = [
        'post_title' => __('Salon Receipt (Default)', 'openpos'),
        'post_content' => '',
        'post_type' => '_op_receipt',
        'post_status' => 'publish'
    ];
    
    $post_id = wp_insert_post($post_data);
    
    if ($post_id && !is_wp_error($post_id)) {
        // Save template parts
        update_post_meta($post_id, 'template_type', 'receipt');
        update_post_meta($post_id, 'template_header', $header);
        update_post_meta($post_id, 'template_body', $body);
        update_post_meta($post_id, 'template_footer', $footer);
        update_post_meta($post_id, 'custom_css', $css);
        update_post_meta($post_id, 'paper_width', 80);
        update_post_meta($post_id, 'padding_top', 2);
        update_post_meta($post_id, 'padding_right', 5);
        update_post_meta($post_id, 'padding_bottom', 2);
        update_post_meta($post_id, 'padding_left', 5);
        update_post_meta($post_id, 'salon_default_template', 'yes');
        
        return $post_id;
    }
    
    return false;
}

// Hook to auto-create salon templates when needed
add_action('admin_init', function() {
    $openpos_type = get_option('openpos_type', 'openpos_pos');
    $last_salon_check = get_transient('openpos_salon_template_check');
    
    if ($openpos_type === 'salon' && !$last_salon_check) {
        openpos_create_default_salon_receipt_templates();
        set_transient('openpos_salon_template_check', 1, WEEK_IN_SECONDS);
    }
});
