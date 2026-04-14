<?php
    global $op_in_pos_screen;
    $op_in_pos_screen = true;
    $base_dir = dirname(dirname(dirname(dirname(__DIR__))));
    if(!file_exists($base_dir.'/wp-load.php'))
    {
        $sampe_paths = array(
            dirname(__DIR__),
            '/opt/bitnami/apps/wordpress/htdocs',
            '/opt/bitnami/wordpress',
        );
        foreach($sampe_paths as $s)
        {
            if(file_exists($s.'/wp-load.php')){
                $base_dir = $s;
            }
        }
    } 
    
    /** UPDATE YOUR CUSTOM WORDPRESS DIR AT HERE */

    # $base_dir = 'ENTER_YOUR_WORDPRESS_BASE_PATH'; // enter your custom wordpress base dir and uncomment    

    /** END */

    $wordpress_load = $base_dir.'/wp-load.php'; 
    if(!file_exists($wordpress_load))
    {
        ?>
        <h2>No wordpress base dir found. </h2>
        <p>Please goto <b><?php echo __FILE__ ; ?></b> , find the line</p>
        <pre>
        # $base_dir = 'ENTER_YOUR_WORDPRESS_BASE_PATH'; // enter your custom wordpress base dir and uncomment  
        </pre>
        and replace with your new wordpress patch + uncomment (remove "#"). And try again!
        <pre>
        $base_dir = 'ENTER_YOUR_WORDPRESS_BASE_PATH'; // enter your custom wordpress base dir and uncomment  
        </pre>
        <?php
        
        exit;
    }   

    require_once ($wordpress_load);
    global $OPENPOS_SETTING;
    global $OPENPOS_CORE;

    $lang = $OPENPOS_SETTING->get_option('pos_language','openpos_pos');
    if(!$lang || $lang == '_auto')
    {
        $lang = false;
    }
    $pos_url =  rtrim($OPENPOS_CORE->get_pos_url(),'/');
    $plugin_info = $OPENPOS_CORE->getPluginInfo();
?>
<!doctype html>
<html lang="<?php echo $lang ? $lang : 'en'?>" style="height: calc(100% - 0px);">
<head>
    <meta charset="utf-8">
    <title><?php echo apply_filters('openpos_pos_title','POS'); ?></title>
    <meta NAME="ROBOTS" CONTENT="NOINDEX, NOFOLLOW">
    <!-- <base href="<?php echo $pos_url ?>/"> -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1,user-scalable=0"/>
    <link rel="icon" type="image/x-icon" href="<?php echo apply_filters('openpos_pos_favicon','favicon.ico'); ?>">
    <meta name="generator" content="OpenPOS - <?php echo  esc_attr($plugin_info['Version']); ?>" />
    <link rel="manifest" href="<?php echo apply_filters('openpos_pos_manifest',OPENPOS_URL.'/pos/manifest.json');?>" />
    <link  href="<?php echo OPENPOS_URL; ?>/pos/assets/i18n/en.json" as="fetch" />
    <?php
        $handes = array(
            'openpos.material.icon',
            'openpos.styles',
            'openpos.front'
        );
        wp_print_styles(apply_filters('openpos_pos_header_style',$handes));
    ?>
    <script type="text/javascript">
        var Buffer = Buffer || [];
        var process = process || {
            env: { DEBUG: undefined },
            version: []
        };
        
    </script>
    <script type="text/javascript">
        window.addEventListener("beforeunload", function (e) {
            var confirmationMessage = '<?php echo __('It looks like you have been editing something.If you leave before saving, your changes will be lost.','openpos');?> ';

            (e || window.event).returnValue = confirmationMessage; 
            return confirmationMessage; 
        });
        window.addEventListener("focus", function(event) { document.body.className = 'focused'; }, false);
        window.addEventListener("click", function(event) { document.body.className = 'focused'; }, false);
        window.addEventListener("blur", function(event) { document.body.className = 'non-focused'; }, false);
        

    </script>
</head>
<?php
$handes = array();
wp_print_scripts(apply_filters('openpos_pos_header_js',$handes));
?>
<body style="width: 100%; height: 100%; overflow: hidden;">

<app-root>
    <style>
        .bg {
        animation:slide 3s ease-in-out infinite alternate;
        background-image: linear-gradient(-60deg, #6c3 50%, #09f 50%);
        bottom:0;
        left:-50%;
        opacity:.5;
        position:fixed;
        right:-50%;
        top:0;
        z-index:-1;
        }

        .bg2 {
        animation-direction:alternate-reverse;
        animation-duration:4s;
        }

        .bg3 {
        animation-duration:5s;
        }
        .content {
            background-color:rgba(255,255,255,.8);
            border-radius:.25em;
            box-shadow:0 0 .25em rgba(0,0,0,.25);
            box-sizing:border-box;
            left:50%;
            padding:10vmin;
            position:fixed;
            text-align:center;
            top:50%;
            transform:translate(-50%, -50%);
        }
    </style>
    <div class="bg"></div>
    <div class="bg bg2"></div>
    <div class="bg bg3"></div>
    <div class="content">
        <h1><?php echo __('Loading. Please wait.....','openpos');?></h1>
    </div>
</app-root>
<script type='text/javascript' src='<?php echo OPENPOS_URL; ?>/pos/runtime.js?ver=<?php echo  esc_attr($plugin_info['Version']); ?>'></script>
<script type='text/javascript' src='<?php echo OPENPOS_URL; ?>/pos/polyfills.js?ver=<?php echo  esc_attr($plugin_info['Version']); ?>'></script>
<script type='text/javascript' src='<?php echo OPENPOS_URL; ?>/pos/main.js?ver=<?php echo  esc_attr($plugin_info['Version']); ?>'></script>
<script type='text/javascript'>
        
</script>

<?php
    $handes = array(
        'openpos.pos.main',
        'openpos.pos.ga'
    );
    wp_print_scripts(apply_filters('openpos_pos_footer_js',$handes));
    do_action('op_pos_page_after');
?>


</body>
</html>
