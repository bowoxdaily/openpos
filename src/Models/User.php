<?php
namespace Op\Models;

use WP_Error;

class User{
    public function __construct()
    {
    }
    public static function login($username,$password){
        try{
            $creds = array(
                'user_login'    => $username,
                'user_password' => $password,
                'remember'      => false
            );
            $user = wp_authenticate($creds['user_login'], $creds['user_password']);
            
            return $user;
        }catch(Exception $e){
            return new WP_Error( 'authentication_failed',$e->getMessage(),$creds);
        }
        
    }
    public static function login_pin($pin,$unique_session = false)
    {
        try{
            global $wpdb;
            $pin = trim($pin);
            $sql = $wpdb->prepare("SELECT * FROM `{$wpdb->usermeta}`  WHERE meta_key= '_op_pin' AND meta_value='%s'",$pin);
            $rows = $wpdb->get_results( $sql );
            
            if(!empty($rows))
            {
                if(count($rows) == 1)
                {
                    $row = end($rows);
                    $user_id = $row->user_id;
                    $user = get_user_by('id',$user_id);
                    if(!$user)
                    {
                        $user = new WP_Error( 'authentication_failed', __( '<strong>Error:</strong> Invalid PIN.','openpos' ) );
                    }
                }else{
                    $user = new WP_Error( 'authentication_failed', __( '<strong>Error:</strong> Have multi user has same PIN.','openpos' ) );
                }
            
            }else{
                $user = new WP_Error( 'authentication_failed', __( '<strong>Error:</strong> Invalid PIN.','openpos' ) );
            }
            return $user;
            
        }catch(Exception $e){
            return new WP_Error( 'authentication_failed',$e->getMessage(),$pin);
        }
       
    }
    public static function get_data($user)
    {
        $id = $user->ID;
        $user_data = $user->data;
        $cashier_name = $user_data->display_name;
        $avatar = rtrim(OPENPOS_URL,'/').'/assets/images/default_avatar.png';
        $avatar_args = get_avatar_data( $id);
        if($avatar_args && isset($avatar_args['url']))
        {
            $avatar = $avatar_args['url'];
        }
        $user_login_data = array(
            'user_id' => $id ,
            'username' =>  $user_data->user_login ,
            'name' =>  $cashier_name,
            'email' =>  $user_data->user_email ,
            'role' =>  $user->roles ,
            'phone' => '',
            'avatar' => $avatar
        );
        return $user_login_data;
    }
}