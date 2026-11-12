<?php

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

	public $orders = array();

	public static function is_hpos() {
		return self::$force_hpos;
	}

	protected function should_log() {
		return $this->log_enabled && static::is_hpos();
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
		$this->assertStringNotContainsString( $note_text, $row->meta );
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
