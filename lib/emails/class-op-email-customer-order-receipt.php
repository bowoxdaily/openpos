<?php
/**
 * Class WC_Email_Customer_Completed_Order file.
 *
 * @package WooCommerce\Emails
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'OP_Email_Customer_Order_Receipt', false ) ) :

	/**
	 * Customer Completed Order Email.
	 *
	 * Order complete emails are sent to the customer when the order is marked complete and usual indicates that the order has been shipped.
	 *
	 * @class       WC_Email_Customer_Completed_Order
	 * @version     2.0.0
	 * @package     WooCommerce\Classes\Emails
	 * @extends     WC_Email
	 */
	class OP_Email_Customer_Order_Receipt extends WC_Email {

		/**
		 * Constructor.
		 */
		public $order_data = array();
		public function __construct() {
			$this->template_base = OPENPOS_DIR . '/templates/';
			$this->id             = 'op_customer_order_receipt';
			$this->customer_email = true;
			$this->title          = __( 'OpenPOS - Customer Order Receipt', 'openpos' );
			$this->description    = __( 'Send email receipt when click send receipt on POS after complete order.', 'openpos' );
			$this->template_html  = 'emails/op-customer-receipt.php';
			$this->template_plain = 'emails/plain/op-customer-receipt.php';
			$this->placeholders   = array(
				'{order_date}'   => '',
				'{order_number}' => '',
			);
			
            

			// Triggers for this email.
			//add_action( 'woocommerce_order_status_completed_notification', array( $this, 'trigger' ), 10, 2 );

			// Call parent constructor.
			parent::__construct();
		}

		/**
		 * Trigger the sending of this email.
		 *
		 * @param int            $order_id The order ID.
		 * @param WC_Order|false $order Order object.
		 */
		public function trigger( $order_id, $order_data = array() ) {
			$this->setup_locale();
			$order = false;
			if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
				$order = wc_get_order( $order_id );
			}
			$this->order_data = $order_data;

			if ( is_a( $order, 'WC_Order' ) ) {
				$this->object                         = $order;
				$this->recipient                      = isset($order_data['send_to']) && $order_data['send_to'] ? $order_data['send_to'] : $this->object->get_billing_email();
				$this->placeholders['{order_date}']   = wc_format_datetime( $this->object->get_date_created() );
				$this->placeholders['{order_number}'] = $this->object->get_order_number();
				$this->placeholders['{receipt_url}'] = $order_data['receipt_url'];
			}
			$sent = false;

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$sent = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();
			return $sent;
		}

		/**
		 * Get email subject.
		 *
		 * @since  3.1.0
		 * @return string
		 */
		public function get_default_subject() {
			return __( 'Your {site_title} order POS receipt', 'woocommerce' );
		}

		/**
		 * Get email heading.
		 *
		 * @since  3.1.0
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Thanks for shopping with us', 'woocommerce' );
		}

		/**
		 * Get content html.
		 *
		 * @return string
		 */
		public function get_content_html() {
			
			return wc_get_template_html(
				$this->template_html,
				array(
					'order_data'              => $this->order_data,
					'order'              => $this->object,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => false,
					'email'              => $this,

				),
				'',
				$this->template_base,
			);
		}

		/**
		 * Get content plain.
		 *
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html(
				$this->template_plain,
				array(
					'order_data'              => $this->order_data,
					'order'              => $this->object,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => true,
					'email'              => $this,
				),
				'',
				$this->template_base
			);
		}

		/**
		 * Default content to show below main email content.
		 *
		 * @since 3.7.0
		 * @return string
		 */
		public function get_default_additional_content() {
			return __( 'Thanks for shopping with us.', 'woocommerce' );
		}
		public function get_template_path() {
			if ( null === $this->template_path ) {
				$this->template_path = OPENPOS_DIR . '/templates';
			}
		}
	}

endif;

return new OP_Email_Customer_Order_Receipt();
