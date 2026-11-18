<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AAL_Hook_WooCommerce extends AAL_Hook_Base {

	/**
	 * @var array<int, array<string, mixed>>
	 */
	private $pending_product_changes = array();

	/**
	 * @var bool
	 */
	private $order_driven_stock_change = false;

	/**
	 * @var string[]
	 */
	private static $product_log_fields = array(
		'regular_price',
		'sale_price',
		'date_on_sale_from',
		'date_on_sale_to',
		'sku',
		'manage_stock',
		'stock_quantity',
		'stock_status',
		'backorders',
		'catalog_visibility',
		'featured',
	);

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

	protected function should_log_product() {
		return current_user_can( 'edit_products' );
	}

	protected function normalize_product_field_value( $value ) {
		if ( is_object( $value ) && is_a( $value, 'WC_DateTime' ) ) {
			return $value->date( 'Y-m-d H:i:s' );
		}

		if ( $value instanceof DateTimeInterface ) {
			return $value->format( 'Y-m-d H:i:s' );
		}

		return $value;
	}

	protected function product_field_value_to_string( $value ) {
		$value = $this->normalize_product_field_value( $value );

		if ( null === $value || '' === $value ) {
			return '';
		}

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		return (string) $value;
	}

	protected function build_product_changes_diff( $product ) {
		$data    = $product->get_data();
		$changes = $product->get_changes();
		$old     = array();
		$new     = array();

		foreach ( self::$product_log_fields as $field ) {
			if ( ! array_key_exists( $field, $changes ) ) {
				continue;
			}

			$old_value = array_key_exists( $field, $data ) ? $data[ $field ] : null;
			$new_value = $changes[ $field ];

			$old_string = $this->product_field_value_to_string( $old_value );
			$new_string = $this->product_field_value_to_string( $new_value );

			if ( $old_string === $new_string ) {
				continue;
			}

			$old[ $field ] = $old_string;
			$new[ $field ] = $new_string;
		}

		return AAL_Changes::diff( $old, $new );
	}

	protected function get_product_object_subtype( $product, $default_subtype = 'product' ) {
		if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variation' ) ) {
			return 'product_variation';
		}

		return $default_subtype;
	}

	protected function insert_product_log( $product_id, $action, $product, $meta = array(), $object_subtype = null ) {
		if ( ! $this->should_log_product() ) {
			return;
		}

		if ( is_null( $object_subtype ) ) {
			$object_subtype = $this->get_product_object_subtype( $product );
		}

		$args = array(
			'action'         => $action,
			'object_type'    => 'WooCommerce',
			'object_subtype' => $object_subtype,
			'object_id'      => (int) $product_id,
			'object_name'    => $product->get_name(),
		);

		if ( ! empty( $meta ) ) {
			$args['meta'] = $meta;
		}

		aal_insert_log( $args );
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

	public function hooks_woocommerce_before_product_object_save( $product, $data_store = null ) {
		if ( ! $product->get_id() ) {
			return;
		}

		if ( ! $this->should_log_product() ) {
			return;
		}

		if ( $this->order_driven_stock_change ) {
			return;
		}

		$meta = $this->build_product_changes_diff( $product );
		if ( empty( $meta ) ) {
			return;
		}

		$this->pending_product_changes[ (int) $product->get_id() ] = $meta;
	}

	public function hooks_woocommerce_update_product( $product_id, $product ) {
		$product_id = (int) $product_id;
		if ( empty( $this->pending_product_changes[ $product_id ] ) ) {
			return;
		}

		$meta = $this->pending_product_changes[ $product_id ];
		unset( $this->pending_product_changes[ $product_id ] );

		$this->insert_product_log( $product_id, 'updated', $product, $meta, 'product' );
	}

	public function hooks_woocommerce_update_product_variation( $product_id, $product ) {
		$product_id = (int) $product_id;
		if ( empty( $this->pending_product_changes[ $product_id ] ) ) {
			return;
		}

		$meta = $this->pending_product_changes[ $product_id ];
		unset( $this->pending_product_changes[ $product_id ] );

		$this->insert_product_log( $product_id, 'updated', $product, $meta, 'product_variation' );
	}

	public function hooks_woocommerce_product_before_set_stock( $product ) {
		$this->order_driven_stock_change = true;
	}

	public function hooks_woocommerce_variation_before_set_stock( $product ) {
		$this->order_driven_stock_change = true;
	}

	public function hooks_woocommerce_product_set_stock( $product ) {
		$this->order_driven_stock_change = false;
	}

	public function hooks_woocommerce_variation_set_stock( $product ) {
		$this->order_driven_stock_change = false;
	}

	public function __construct() {
		add_action( 'woocommerce_new_order', array( &$this, 'hooks_woocommerce_new_order' ), 10, 2 );
		add_action( 'woocommerce_order_status_changed', array( &$this, 'hooks_woocommerce_order_status_changed' ), 10, 4 );
		add_action( 'woocommerce_trash_order', array( &$this, 'hooks_woocommerce_trash_order' ), 10, 1 );
		add_action( 'woocommerce_untrash_order', array( &$this, 'hooks_woocommerce_untrash_order' ), 10, 2 );
		add_action( 'woocommerce_before_delete_order', array( &$this, 'hooks_woocommerce_before_delete_order' ), 10, 2 );
		add_action( 'woocommerce_order_refunded', array( &$this, 'hooks_woocommerce_order_refunded' ), 10, 2 );
		add_action( 'woocommerce_order_note_added', array( &$this, 'hooks_woocommerce_order_note_added' ), 10, 2 );
		add_action( 'woocommerce_before_product_object_save', array( &$this, 'hooks_woocommerce_before_product_object_save' ), 10, 2 );
		add_action( 'woocommerce_update_product', array( &$this, 'hooks_woocommerce_update_product' ), 10, 2 );
		add_action( 'woocommerce_update_product_variation', array( &$this, 'hooks_woocommerce_update_product_variation' ), 10, 2 );
		add_action( 'woocommerce_product_before_set_stock', array( &$this, 'hooks_woocommerce_product_before_set_stock' ), 10, 1 );
		add_action( 'woocommerce_variation_before_set_stock', array( &$this, 'hooks_woocommerce_variation_before_set_stock' ), 10, 1 );
		add_action( 'woocommerce_product_set_stock', array( &$this, 'hooks_woocommerce_product_set_stock' ), 10, 1 );
		add_action( 'woocommerce_variation_set_stock', array( &$this, 'hooks_woocommerce_variation_set_stock' ), 10, 1 );

		parent::__construct();
	}
}
