<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    TeleBridge_Pro
 * @subpackage TeleBridge_Pro/admin
 * @author     TeleBridge Team
 */
class TeleBridge_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * Load CSS only on plugin settings page to avoid conflicts.
		 */
		$screen = get_current_screen();
		if ( strpos( $screen->id, 'telebridge' ) === false ) {
			return;
		}

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/telebridge-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		$screen = get_current_screen();
		if ( strpos( $screen->id, 'telebridge' ) === false ) {
			return;
		}

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/telebridge-admin.js', array( 'jquery', 'wp-util' ), $this->version, false );

		// Localize script to pass server-side data to JS
		wp_localize_script( $this->plugin_name, 'telebridgeData', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'telebridge_admin_nonce' ),
			'strings'  => array(
				'testing' => __( 'Testing connection...', 'telebridge-pro' ),
				'success' => __( 'Connection Successful!', 'telebridge-pro' ),
				'fail'    => __( 'Connection Failed.', 'telebridge-pro' ),
			)
		) );

	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {

		add_menu_page(
			__( 'TeleBridge Pro', 'telebridge-pro' ),
			__( 'TeleBridge AI', 'telebridge-pro' ),
			'manage_options',
			'telebridge-pro',
			array( $this, 'display_plugin_setup_page' ),
			'dashicons-randomize', // A generic icon, can be replaced with custom SVG
			65
		);

		// Add Settings Submenu
		add_submenu_page(
			'telebridge-pro',
			__( 'General Settings', 'telebridge-pro' ),
			__( 'Settings', 'telebridge-pro' ),
			'manage_options',
			'telebridge-pro',
			array( $this, 'display_plugin_setup_page' )
		);
		
		// Add Mapping Submenu (Placeholder for now)
		add_submenu_page(
			'telebridge-pro',
			__( 'Field Mapping', 'telebridge-pro' ),
			__( 'Field Mapping', 'telebridge-pro' ),
			'manage_options',
			'telebridge-mapping',
			array( $this, 'display_mapping_page' )
		);

	}

	/**
	 * Render the Settings Page (React Root or Classic Form).
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_setup_page() {
		// Include the partial file which contains the HTML/Form
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/telebridge-admin-display.php';
	}
	
	/**
	 * Render the Mapping Page.
	 */
	public function display_mapping_page() {
		echo '<div class="wrap"><h1>' . __( 'JetEngine Field Mapping', 'telebridge-pro' ) . '</h1><p>' . __( 'Drag and drop interface coming here.', 'telebridge-pro' ) . '</p></div>';
	}

	/**
	 * AJAX Handler: Test API Connection.
	 * * Allows users to click "Test" next to their API Key to verify it works.
	 *
	 * @since    1.0.0
	 */
	public function ajax_test_api_connection() {
		
		check_ajax_referer( 'telebridge_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'telebridge-pro' ) ) );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';
		$api_key  = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '';

		if ( empty( $provider ) || empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing Provider or API Key.', 'telebridge-pro' ) ) );
		}

		// Instantiate the AI Manager specifically for testing (not saving)
		$ai_instance = TeleBridge_AI_Manager::get_instance( $provider, $api_key );

		if ( $ai_instance->validate_connection() ) {
			// Save the working key if test passes (Optional UX improvement)
			update_option( "telebridge_api_key_{$provider}", $api_key );
			wp_send_json_success( array( 'message' => __( 'Connection verified successfully!', 'telebridge-pro' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Connection failed. Please check your API Key.', 'telebridge-pro' ) ) );
		}

	}

	/**
	 * AJAX Handler: Manual Fetch from Telegram.
	 * * Allows manual triggering of the fetch process.
	 */
	public function ajax_fetch_latest_posts() {
		check_ajax_referer( 'telebridge_admin_nonce', 'security' );
		
		// In a real scenario, this would call TeleBridge_Telegram_Bot->fetch_updates()
		// Since we use Webhooks mainly, this is a fallback method using getUpdates
		
		wp_send_json_success( array( 'message' => __( 'Manual fetch triggered.', 'telebridge-pro' ) ) );
	}

}