<?php
defined( 'ABSPATH' ) || exit;
class OP_Error extends WP_Error{
    public function __construct( $code = '', $message = '', $reponse = '' ) {
		if ( empty( $code ) ) {
			return;
		}

		$this->add( $code, $message, $reponse );
	}
}