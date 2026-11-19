<?php

if ( ! function_exists( 'wc_get_order_note' ) ) {
	function wc_get_order_note( $note_id ) {
		$comment = get_comment( $note_id );
		if ( ! $comment ) {
			return null;
		}

		$added_by = 'user';
		if ( __( 'WooCommerce', 'woocommerce' ) === $comment->comment_author ) {
			$added_by = 'system';
		}

		return (object) array(
			'added_by' => $added_by,
		);
	}
}

class AAL_Stub_WC_Order {

	private $id;

	private $order_number;

	public function __construct( $id, $order_number ) {
		$this->id           = (int) $id;
		$this->order_number = $order_number;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_order_number() {
		return $this->order_number;
	}
}

if ( ! class_exists( 'WC_Coupon' ) ) {
	class WC_Coupon {}
}

if ( ! class_exists( 'WC_DateTime' ) ) {
	class WC_DateTime extends DateTime {}
}

class AAL_Stub_WC_Coupon extends WC_Coupon {

	private $id;

	private $code;

	private $data;

	private $changes;

	public $get_data_calls = 0;

	public function __construct( $id, $code, $data = array(), $changes = array() ) {
		$this->id      = (int) $id;
		$this->code    = $code;
		$this->data    = $data;
		$this->changes = $changes;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_code( $context = 'view' ) {
		return $this->code;
	}

	public function get_data() {
		$this->get_data_calls++;
		return $this->data;
	}

	public function get_changes() {
		return $this->changes;
	}
}

class AAL_Stub_WC_Product {

	private $id;

	private $name;

	private $type;

	private $data;

	private $changes;

	public function __construct( $id, $name, $type, $data = array(), $changes = array() ) {
		$this->id      = (int) $id;
		$this->name    = $name;
		$this->type    = $type;
		$this->data    = $data;
		$this->changes = $changes;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_name() {
		return $this->name;
	}

	public function is_type( $type ) {
		if ( 'variation' === $type ) {
			return 'variation' === $this->type;
		}

		return $type === $this->type;
	}

	public function get_data() {
		return $this->data;
	}

	public function get_changes() {
		return $this->changes;
	}
}

class AAL_Stub_WC_Refund {

	private $amount;

	private $currency;

	private $reason;

	public function __construct( $amount, $currency, $reason = 'Customer asked for refund' ) {
		$this->amount   = $amount;
		$this->currency = $currency;
		$this->reason   = $reason;
	}

	public function get_amount() {
		return $this->amount;
	}

	public function get_currency() {
		return $this->currency;
	}

	public function get_reason() {
		return $this->reason;
	}
}

class AAL_Hook_WooCommerce_Testable extends AAL_Hook_WooCommerce {

	public static $force_hpos = true;

	public $log_enabled = true;

	public $product_log_enabled = true;

	public $coupon_log_enabled = true;

	public $orders = array();

	public static function is_hpos() {
		return self::$force_hpos;
	}

	protected function should_log() {
		return $this->log_enabled && static::is_hpos();
	}

	protected function should_log_product() {
		return $this->product_log_enabled;
	}

	protected function should_log_coupon() {
		return $this->coupon_log_enabled;
	}

	protected function get_order( $id ) {
		return isset( $this->orders[ $id ] ) ? $this->orders[ $id ] : false;
	}
}

class AAL_Test_Hook_WooCommerce extends WP_UnitTestCase {

	private function get_latest_order_log( $order_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'WooCommerce' AND `object_id` = %d
					ORDER BY histid DESC LIMIT 1",
				$order_id
			)
		);
	}

	private function make_hook() {
		$hook                = new AAL_Hook_WooCommerce_Testable();
		$hook->orders[ 100 ] = new AAL_Stub_WC_Order( 100, '1042' );
		return $hook;
	}

	private function insert_order_note_comment( $author, $note_text = 'Hand-typed order note body', $note_group = null ) {
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'  => 100,
				'comment_author'   => $author,
				'comment_content'  => $note_text,
				'comment_type'     => 'order_note',
				'comment_approved' => 1,
			)
		);

		if ( $note_group ) {
			add_comment_meta( $comment_id, 'note_group', $note_group, true );
		}

		return $comment_id;
	}

	private function assert_common_row( $row, $action ) {
		$this->assertNotEmpty( $row );
		$this->assertSame( $action, $row->action );
		$this->assertSame( 'WooCommerce', $row->object_type );
		$this->assertSame( 'shop_order', $row->object_subtype );
		$this->assertSame( '#1042', $row->object_name );
	}

	public function set_up() {
		parent::set_up();

		AAL_Hook_WooCommerce_Testable::$force_hpos = true;
		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_type` = 'WooCommerce'" );
	}

	/**
	 * @dataProvider order_action_provider
	 */
	public function test_handlers_insert_expected_row( $method, $args, $expected_action ) {
		$hook = $this->make_hook();

		if ( 'hooks_woocommerce_order_refunded' === $method ) {
			$hook->orders[ 200 ] = new AAL_Stub_WC_Refund( '12.50', 'USD' );
		}

		call_user_func_array( array( $hook, $method ), $args );

		$row = $this->get_latest_order_log( 100 );
		$this->assert_common_row( $row, $expected_action );
	}

	public function order_action_provider() {
		return array(
			'created'        => array( 'hooks_woocommerce_new_order', array( 100, new AAL_Stub_WC_Order( 100, '1042' ) ), 'created' ),
			'status_changed'   => array( 'hooks_woocommerce_order_status_changed', array( 100, 'pending', 'processing', new AAL_Stub_WC_Order( 100, '1042' ) ), 'status_changed' ),
			'trashed'          => array( 'hooks_woocommerce_trash_order', array( 100 ), 'trashed' ),
			'restored'         => array( 'hooks_woocommerce_untrash_order', array( 100, 'pending' ), 'restored' ),
			'deleted'          => array( 'hooks_woocommerce_before_delete_order', array( 100, new AAL_Stub_WC_Order( 100, '1042' ) ), 'deleted' ),
			'refunded'         => array( 'hooks_woocommerce_order_refunded', array( 100, 200 ), 'refunded' ),
		);
	}

	public function test_status_changed_meta_status_only() {
		$hook = $this->make_hook();
		$hook->hooks_woocommerce_order_status_changed( 100, 'pending', 'processing', $hook->orders[ 100 ] );

		$row  = $this->get_latest_order_log( 100 );
		$meta = json_decode( $row->meta, true );
		$this->assertSame( array( 'pending', 'processing' ), $meta['changes']['status'] );
	}

	public function test_note_added_logs_staff_hand_typed_note() {
		$note_text  = 'Staff-only secret note text';
		$comment_id = $this->insert_order_note_comment( 'Shop Manager', $note_text );
		$hook       = $this->make_hook();
		$order      = new AAL_Stub_WC_Order( 100, '1042' );

		$hook->hooks_woocommerce_order_note_added( $comment_id, $order );

		global $wpdb;
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'WooCommerce' AND `object_id` = %d AND `action` = 'note_added'",
				100
			)
		);
		$this->assertSame( 1, $count );

		$row = $this->get_latest_order_log( 100 );
		$this->assert_common_row( $row, 'note_added' );
		$this->assertEmpty( $row->meta );
		$this->assertStringNotContainsString( $note_text, $row->object_name );
	}

	public function test_note_added_skips_system_author() {
		$comment_id = $this->insert_order_note_comment( __( 'WooCommerce', 'woocommerce' ) );
		$hook       = $this->make_hook();
		$order      = new AAL_Stub_WC_Order( 100, '1042' );

		$hook->hooks_woocommerce_order_note_added( $comment_id, $order );

		global $wpdb;
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$wpdb->activity_log}` WHERE `object_type` = %s",
				'WooCommerce'
			)
		);
		$this->assertSame( 0, $count );
	}

	public function test_note_added_skips_note_group_meta() {
		$comment_id = $this->insert_order_note_comment( 'Shop Manager', 'Automatic user-attributed note', 'order_update' );
		$hook       = $this->make_hook();
		$order      = new AAL_Stub_WC_Order( 100, '1042' );

		$hook->hooks_woocommerce_order_note_added( $comment_id, $order );

		global $wpdb;
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$wpdb->activity_log}` WHERE `object_type` = %s",
				'WooCommerce'
			)
		);
		$this->assertSame( 0, $count );
	}

	public function test_note_added_skips_missing_comment() {
		$hook  = $this->make_hook();
		$order = new AAL_Stub_WC_Order( 100, '1042' );

		$hook->hooks_woocommerce_order_note_added( 999999, $order );

		global $wpdb;
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$wpdb->activity_log}` WHERE `object_type` = %s",
				'WooCommerce'
			)
		);
		$this->assertSame( 0, $count );
	}

	public function test_refunded_meta_amount_and_currency_only() {
		$hook                = $this->make_hook();
		$hook->orders[ 200 ] = new AAL_Stub_WC_Refund( '12.50', 'USD' );
		$hook->hooks_woocommerce_order_refunded( 100, 200 );

		$row  = $this->get_latest_order_log( 100 );
		$meta = json_decode( $row->meta, true );
		$this->assertSame( '12.50', $meta['changes']['amount'][1] );
		$this->assertSame( 'USD', $meta['changes']['currency'][1] );
		$this->assertStringNotContainsString( 'reason', $row->meta );
	}

	/**
	 * @dataProvider order_action_provider
	 */
	public function test_handlers_skip_when_should_log_is_false( $method, $args ) {
		$hook              = $this->make_hook();
		$hook->log_enabled = false;

		if ( 'hooks_woocommerce_order_refunded' === $method ) {
			$hook->orders[ 200 ] = new AAL_Stub_WC_Refund( '12.50', 'USD' );
		}

		call_user_func_array( array( $hook, $method ), $args );

		global $wpdb;
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$wpdb->activity_log}` WHERE `object_type` = %s",
				'WooCommerce'
			)
		);
		$this->assertSame( 0, $count );
	}

	public function test_refund_meta_contains_no_pii() {
		$hook                = $this->make_hook();
		$hook->orders[ 200 ] = new AAL_Stub_WC_Refund( '9.99', 'EUR', 'john@example.com leaked note' );

		$hook->hooks_woocommerce_order_refunded( 100, 200 );

		$row = $this->get_latest_order_log( 100 );
		$this->assertNotEmpty( $row );
		$this->assertStringNotContainsString( 'john@example.com', $row->meta );
		$this->assertStringNotContainsString( 'leaked', $row->meta );
	}

	private function get_latest_product_log( $product_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'WooCommerce' AND `object_id` = %d AND `action` = 'updated'
					ORDER BY histid DESC LIMIT 1",
				$product_id
			)
		);
	}

	private function count_woocommerce_logs() {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$wpdb->activity_log}` WHERE `object_type` = %s",
				'WooCommerce'
			)
		);
	}

	private function make_product( $id, $name, $type, $data, $changes ) {
		return new AAL_Stub_WC_Product( $id, $name, $type, $data, $changes );
	}

	private function run_product_save_flow( $hook, $product, $update_method ) {
		$hook->hooks_woocommerce_before_product_object_save( $product, null );
		$hook->$update_method( $product->get_id(), $product );
	}

	public function test_product_price_change_logs_updated_row() {
		$hook    = $this->make_hook();
		$product = $this->make_product(
			501,
			'Test Product',
			'simple',
			array( 'regular_price' => '10.00' ),
			array( 'regular_price' => '15.00' )
		);

		$this->run_product_save_flow( $hook, $product, 'hooks_woocommerce_update_product' );

		$row = $this->get_latest_product_log( 501 );
		$this->assertNotEmpty( $row );
		$this->assertSame( 'updated', $row->action );
		$this->assertSame( 'product', $row->object_subtype );
		$this->assertSame( 'Test Product', $row->object_name );

		$meta = json_decode( $row->meta, true );
		$this->assertSame( array( '10.00', '15.00' ), $meta['changes']['regular_price'] );
	}

	public function test_product_unchanged_whitelisted_values_log_nothing() {
		$hook    = $this->make_hook();
		$product = $this->make_product(
			502,
			'Stable Product',
			'simple',
			array( 'regular_price' => '10.00' ),
			array( 'regular_price' => '10.00' )
		);

		$this->run_product_save_flow( $hook, $product, 'hooks_woocommerce_update_product' );

		$this->assertSame( 0, $this->count_woocommerce_logs() );
	}

	public function test_product_non_whitelisted_change_logs_nothing() {
		$hook    = $this->make_hook();
		$product = $this->make_product(
			503,
			'Described Product',
			'simple',
			array( 'description' => 'Old' ),
			array( 'description' => 'New' )
		);

		$this->run_product_save_flow( $hook, $product, 'hooks_woocommerce_update_product' );

		$this->assertSame( 0, $this->count_woocommerce_logs() );
	}

	public function test_new_product_logs_nothing() {
		$hook    = $this->make_hook();
		$product = $this->make_product(
			0,
			'New Product',
			'simple',
			array(),
			array( 'regular_price' => '9.99' )
		);

		$this->run_product_save_flow( $hook, $product, 'hooks_woocommerce_update_product' );

		$this->assertSame( 0, $this->count_woocommerce_logs() );
	}

	public function test_product_update_without_edit_products_logs_nothing() {
		$hook                       = $this->make_hook();
		$hook->product_log_enabled = false;
		$product = $this->make_product(
			504,
			'Protected Product',
			'simple',
			array( 'regular_price' => '10.00' ),
			array( 'regular_price' => '12.00' )
		);

		$this->run_product_save_flow( $hook, $product, 'hooks_woocommerce_update_product' );

		$this->assertSame( 0, $this->count_woocommerce_logs() );
	}

	public function test_product_stock_change_during_order_stock_window_logs_nothing() {
		$hook    = $this->make_hook();
		$product = $this->make_product(
			505,
			'Stock Product',
			'simple',
			array( 'stock_quantity' => 10 ),
			array( 'stock_quantity' => 8 )
		);

		$hook->hooks_woocommerce_product_before_set_stock( $product );
		$this->run_product_save_flow( $hook, $product, 'hooks_woocommerce_update_product' );
		$hook->hooks_woocommerce_product_set_stock( $product );

		$this->assertSame( 0, $this->count_woocommerce_logs() );
	}

	public function test_variation_update_logs_product_variation_subtype() {
		$hook    = $this->make_hook();
		$product = $this->make_product(
			506,
			'Blue / Large',
			'variation',
			array( 'sku' => 'OLD-SKU' ),
			array( 'sku' => 'NEW-SKU' )
		);

		$this->run_product_save_flow( $hook, $product, 'hooks_woocommerce_update_product_variation' );

		$row = $this->get_latest_product_log( 506 );
		$this->assertNotEmpty( $row );
		$this->assertSame( 'product_variation', $row->object_subtype );

		$meta = json_decode( $row->meta, true );
		$this->assertSame( array( 'OLD-SKU', 'NEW-SKU' ), $meta['changes']['sku'] );
	}

	private function get_latest_coupon_log( $coupon_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'WooCommerce' AND `object_id` = %d AND `action` = 'updated'
					ORDER BY histid DESC LIMIT 1",
				$coupon_id
			)
		);
	}

	private function make_coupon( $id, $code, $data, $changes ) {
		return new AAL_Stub_WC_Coupon( $id, $code, $data, $changes );
	}

	private function run_coupon_save_flow( $hook, $coupon ) {
		$hook->hooks_woocommerce_before_data_object_save( $coupon, null );
		$hook->hooks_woocommerce_update_coupon( $coupon->get_id(), $coupon );
	}

	public function test_coupon_amount_change_logs_updated_row() {
		$hook   = $this->make_hook();
		$coupon = $this->make_coupon(
			601,
			'save10',
			array( 'amount' => '10' ),
			array( 'amount' => '20' )
		);

		$this->run_coupon_save_flow( $hook, $coupon );

		$row = $this->get_latest_coupon_log( 601 );
		$this->assertNotEmpty( $row );
		$this->assertSame( 'updated', $row->action );
		$this->assertSame( 'shop_coupon', $row->object_subtype );
		$this->assertSame( 'save10', $row->object_name );

		$meta = json_decode( $row->meta, true );
		$this->assertSame( array( '10', '20' ), $meta['changes']['amount'] );
	}

	public function test_coupon_unchanged_whitelisted_values_log_nothing() {
		$hook   = $this->make_hook();
		$coupon = $this->make_coupon(
			602,
			'stable',
			array( 'amount' => '15' ),
			array( 'amount' => '15' )
		);

		$this->run_coupon_save_flow( $hook, $coupon );

		$this->assertSame( 0, $this->count_woocommerce_logs() );
	}

	public function test_coupon_limit_usage_to_x_items_null_to_zero_logs_nothing() {
		$hook   = $this->make_hook();
		$coupon = $this->make_coupon(
			603,
			'nolimit',
			array( 'limit_usage_to_x_items' => null ),
			array( 'limit_usage_to_x_items' => 0 )
		);

		$this->run_coupon_save_flow( $hook, $coupon );

		$this->assertSame( 0, $this->count_woocommerce_logs() );
	}

	public function test_coupon_same_date_expires_different_instance_logs_nothing() {
		$hook = $this->make_hook();
		$old  = new WC_DateTime( '2026-12-31 23:59:59', new DateTimeZone( 'UTC' ) );
		$new  = new WC_DateTime( '2026-12-31 23:59:59', new DateTimeZone( 'UTC' ) );
		$coupon = $this->make_coupon(
			604,
			'dated',
			array( 'date_expires' => $old ),
			array( 'date_expires' => $new )
		);

		$this->run_coupon_save_flow( $hook, $coupon );

		$this->assertSame( 0, $this->count_woocommerce_logs() );
	}

	public function test_coupon_non_whitelisted_change_logs_nothing_without_get_data() {
		$hook   = $this->make_hook();
		$coupon = $this->make_coupon(
			605,
			'pii',
			array(),
			array(
				'usage_count'         => 5,
				'email_restrictions'  => array( 'a@example.com' ),
				'used_by'             => array( 'user@example.com' ),
			)
		);

		$hook->hooks_woocommerce_before_data_object_save( $coupon, null );
		$hook->hooks_woocommerce_update_coupon( $coupon->get_id(), $coupon );

		$this->assertSame( 0, $this->count_woocommerce_logs() );
		$this->assertSame( 0, $coupon->get_data_calls );
	}

	public function test_new_coupon_logs_nothing() {
		$hook   = $this->make_hook();
		$coupon = $this->make_coupon(
			0,
			'newcode',
			array(),
			array( 'amount' => '5' )
		);

		$this->run_coupon_save_flow( $hook, $coupon );

		$this->assertSame( 0, $this->count_woocommerce_logs() );
	}

	public function test_coupon_update_without_edit_shop_coupons_logs_nothing() {
		$hook                      = $this->make_hook();
		$hook->coupon_log_enabled = false;
		$coupon = $this->make_coupon(
			606,
			'locked',
			array( 'amount' => '10' ),
			array( 'amount' => '12' )
		);

		$this->run_coupon_save_flow( $hook, $coupon );

		$this->assertSame( 0, $this->count_woocommerce_logs() );
	}

	public function test_before_data_object_save_ignores_non_coupon_object() {
		$hook    = $this->make_hook();
		$product = $this->make_product(
			607,
			'Not A Coupon',
			'simple',
			array( 'regular_price' => '10.00' ),
			array( 'regular_price' => '12.00' )
		);

		$hook->hooks_woocommerce_before_data_object_save( $product, null );

		$this->assertSame( 0, $this->count_woocommerce_logs() );
	}

	public function test_legacy_shop_order_post_logs_via_posts_hook() {
		AAL_Hook_WooCommerce_Testable::$force_hpos = false;

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'shop_order',
				'post_title'  => 'Legacy Order',
				'post_status' => 'auto-draft',
			)
		);

		global $wpdb;
		$wpdb->query( "DELETE FROM `{$wpdb->activity_log}` WHERE `object_id` = {$post_id}" );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
				'post_title'  => 'Legacy Order',
			)
		);

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->activity_log}`
					WHERE `object_type` = 'Posts' AND `object_id` = %d
					ORDER BY histid DESC LIMIT 1",
				$post_id
			)
		);

		$this->assertNotEmpty( $row );
		$this->assertSame( 'shop_order', $row->object_subtype );
	}
}
