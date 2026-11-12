<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AAL_Hook_WooCommerce extends AAL_Hook_Base {

	public static function is_hpos() {
		return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	protected function get_order( $id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return false;
		}

		return wc_get_order( $id );
	}

	protected function should_log() {
		return static::is_hpos() && current_user_can( 'edit_shop_orders' );
	}

	protected function insert_order_log( $order_id, $action, $order = null, $meta = array() ) {
		if ( ! $this->should_log() ) {
			return;
		}

		if ( is_null( $order ) ) {
			$order = $this->get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		$args = array(
			'action'         => $action,
			'object_type'    => 'WooCommerce',
			'object_subtype' => 'shop_order',
			'object_id'      => (int) $order_id,
			'object_name'    => '#' . $order->get_order_number(),
		);

		if ( ! empty( $meta ) ) {
			$args['meta'] = $meta;
		}

		aal_insert_log( $args );
	}

	public function hooks_woocommerce_new_order( $order_id, $order ) {
		if ( ! $this->should_log() ) {
			return;
		}

		$this->insert_order_log( $order_id, 'created', $order );
	}

	public function hooks_woocommerce_order_status_changed( $order_id, $from, $to, $order ) {
		if ( ! $this->should_log() ) {
			return;
		}

		$meta = AAL_Changes::diff(
			array( 'status' => $from ),
			array( 'status' => $to )
		);

		$this->insert_order_log( $order_id, 'status_changed', $order, $meta );
	}

	public function hooks_woocommerce_trash_order( $order_id ) {
		if ( ! $this->should_log() ) {
			return;
		}

		$this->insert_order_log( $order_id, 'trashed' );
	}

	public function hooks_woocommerce_untrash_order( $order_id, $previous_status ) {
		if ( ! $this->should_log() ) {
			return;
		}

		$this->insert_order_log( $order_id, 'restored' );
	}

	public function hooks_woocommerce_before_delete_order( $order_id, $order ) {
		if ( ! $this->should_log() ) {
			return;
		}

		$this->insert_order_log( $order_id, 'deleted', $order );
	}

	public function hooks_woocommerce_order_refunded( $order_id, $refund_id ) {
		if ( ! $this->should_log() ) {
			return;
		}

		$order = $this->get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$refund = $this->get_order( $refund_id );
		if ( ! $refund ) {
			return;
		}

		$meta = array();
		if ( method_exists( $refund, 'get_amount' ) && method_exists( $refund, 'get_currency' ) ) {
			$meta = AAL_Changes::diff(
				array(),
				array(
					'amount'   => $refund->get_amount(),
					'currency' => $refund->get_currency(),
				)
			);
		}

		$this->insert_order_log( $order_id, 'refunded', $order, $meta );
	}

	public function hooks_woocommerce_order_note_added( $note_id, $order ) {
		if ( ! $this->should_log() ) {
			return;
		}

		// Skip notes WooCommerce adds itself (status changes, refunds, emails).
		$note = wc_get_order_note( $note_id );
		if ( ! $note || 'system' === $note->added_by ) {
			return;
		}

		// Skip automatic notes attributed to a user (they carry a note group).
		if ( get_comment_meta( $note_id, 'note_group', true ) ) {
			return;
		}

		$this->insert_order_log( $order->get_id(), 'note_added', $order );
	}

	public function __construct() {
		add_action( 'woocommerce_new_order', array( &$this, 'hooks_woocommerce_new_order' ), 10, 2 );
		add_action( 'woocommerce_order_status_changed', array( &$this, 'hooks_woocommerce_order_status_changed' ), 10, 4 );
		add_action( 'woocommerce_trash_order', array( &$this, 'hooks_woocommerce_trash_order' ), 10, 1 );
		add_action( 'woocommerce_untrash_order', array( &$this, 'hooks_woocommerce_untrash_order' ), 10, 2 );
		add_action( 'woocommerce_before_delete_order', array( &$this, 'hooks_woocommerce_before_delete_order' ), 10, 2 );
		add_action( 'woocommerce_order_refunded', array( &$this, 'hooks_woocommerce_order_refunded' ), 10, 2 );
		add_action( 'woocommerce_order_note_added', array( &$this, 'hooks_woocommerce_order_note_added' ), 10, 2 );

		parent::__construct();
	}
}
