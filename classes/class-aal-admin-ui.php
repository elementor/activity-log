<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AAL_Admin_Ui {

	/**
	 * @var AAL_Activity_Log_List_Table
	 */
	protected $_list_table = null;

	protected $_screens = array();

	public function create_admin_menu() {
		$menu_capability = current_user_can( 'view_all_aryo_activity_log' ) ? 'view_all_aryo_activity_log' : apply_filters( 'aal_menu_page_capability', 'edit_pages' );

		$this->_screens['main'] = add_menu_page( _x( 'Activity Log', 'Page and Menu Title', 'aryo-activity-log' ), _x( 'Activity Log', 'Page and Menu Title', 'aryo-activity-log' ), $menu_capability, 'activity-log-page', array( &$this, 'activity_log_page_func' ), '', '2.1' );

		// Just make sure we are create instance.
		add_action( 'load-' . $this->_screens['main'], array( &$this, 'get_list_table' ) );
	}

	public function activity_log_page_func() {
		$this->get_list_table()->prepare_items();
		$page_slug = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : 'activity-log-page';
		?>
		<div class="wrap">
			<h1 class="aal-page-title"><?php echo esc_html_x( 'Activity Log', 'Page and Menu Title', 'aryo-activity-log' ); ?></h1>

			<form id="activity-filter" method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( $page_slug ); ?>" />
				<?php $this->get_list_table()->display(); ?>
			</form>
		</div>

		<script>
			jQuery( document ).ready( ( $ ) => {
				const aalPromotionWrapSelector = 'tr.aal-table-promotion-row';
				$( '.aal-promotion-dismiss', aalPromotionWrapSelector ).on( 'click', function( event ) {
					event.preventDefault();

					const $promotionWrap = $( this ).closest( aalPromotionWrapSelector );

					$promotionWrap.hide();

					$.post( ajaxurl, {
						action: 'aal_promotion_dismiss',
						promotion_id: $promotionWrap.data( 'promotion-id' ),
						nonce: $promotionWrap.data( 'nonce' ),
					} );
				} );

				$( '.aal-promotion-cta', aalPromotionWrapSelector ).on( 'click', function( event ) {
					const $promotionWrap = $( this ).closest( aalPromotionWrapSelector );

					$.post( ajaxurl, {
						action: 'aal_promotion_campaign',
						promotion_id: $promotionWrap.data( 'promotion-id' ),
						nonce: $promotionWrap.data( 'nonce' ),
					} );
				} );
			} );
		</script>
		<?php
	}

	public function enqueue_admin_styles() {
		wp_enqueue_style(
			'aal-activity-log-table',
			plugins_url( 'assets/css/activity-log-table.css', ACTIVITY_LOG__FILE__ ),
			array(),
			'2.12.0'
		);
	}

	public function __construct() {
		add_action( 'admin_menu', array( &$this, 'create_admin_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_admin_styles' ) );

		add_action( 'wp_ajax_aal_promotion_dismiss', [ $this, 'ajax_aal_promotion_dismiss' ] );
		add_action( 'wp_ajax_aal_promotion_campaign', [ $this, 'ajax_aal_promotion_campaign' ] );
	}

	/**
	 * @return AAL_Activity_Log_List_Table
	 */
	public function get_list_table() {
		if ( is_null( $this->_list_table ) ) {
			$this->_list_table = new AAL_Activity_Log_List_Table( array( 'screen' => $this->_screens['main'] ) );
			do_action( 'aal_admin_page_load', $this->_list_table );
		}

		return $this->_list_table;
	}

	public function ajax_aal_promotion_dismiss() {
		if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'aal_promotion' ) ) {
			wp_send_json_error();
		}

		if ( empty( $_POST['promotion_id'] ) ) {
			wp_send_json_error();
		}

		$promotion_id = sanitize_key( $_POST['promotion_id'] );

		update_user_meta( get_current_user_id(), "_aal_promotion_{$promotion_id}_notice_viewed", 'true'  );

		wp_send_json_success();
	}

	public function ajax_aal_promotion_campaign() {
		if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'aal_promotion' ) ) {
			wp_send_json_error();
		}

		if ( empty( $_POST['promotion_id'] ) ) {
			wp_send_json_error();
		}

		if ( 'emails' === $_POST['promotion_id'] ) {
			$campaign_data = [
				'source' => 'sm-aal-install',
				'campaign' => 'sm-plg',
				'medium' => 'wp-dash',
			];

			set_transient( 'elementor_site_mailer_campaign', $campaign_data, 30 * DAY_IN_SECONDS );
		}

		if ( 'media' === $_POST['promotion_id'] ) {
			$campaign_data = [
				'source' => 'io-aal-install',
				'campaign' => 'io-plg',
				'medium' => 'wp-dash',
			];

			set_transient( 'elementor_image_optimization_campaign', $campaign_data, 30 * DAY_IN_SECONDS );
		}

		wp_send_json_success();
	}
}
