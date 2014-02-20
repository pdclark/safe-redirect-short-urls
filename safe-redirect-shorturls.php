<?php
/*
Plugin Name: Safe Redirect Short URLs
Plugin URI: http://10up.com
Description: Generate short urls in Safe Redirect Manager by loading <code>/wp-admin/admin-ajax.php?action=create-short-url&key=YOUR_API_KEY&url=http://longurl.com</code>. Set your API key in <code>wp-config.php</code> with <code>define( 'SHORT_URL_API_KEY', 'your-api-key' );</code>
Version: 1.0
Author: Paul Clark, 10up
Author URI: http://10up.com
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

class TenUp_Safe_Redirect_Shorturls {

	/**
	 * @var TenUp_Safe_Redirect_Shorturls Instance of this class.
	 */
	private static $instance = false;

	/**
	 * Admin notices
	 */
	protected $notices = array();

	/**
	 * Plugin slug for Safe Redirect Manager.
	 * @var string
	 */
	protected $srm_slug = 'safe-redirect-manager/safe-redirect-manager.php';

	/**
	 * Plugin path for Safe Redirect Manager
	 * @var string
	 */
	protected $srm_path;

	/**
	 * Hash to pass to SRM as redirect_from
	 * @var boolean|string
	 */
	protected $redirect_hash = false;

	/**
	 * Don't use this. Use ::get_instance() instead.
	 */
	public function __construct() {
		if ( !self::$instance ) {
			$message = '<code>' . __CLASS__ . '</code> is a singleton.<br/> Please get an instantiate it with <code>' . __CLASS__ . '::get_instance();</code>';
			wp_die( $message );
		}       
	}

	/**
	 * Maybe instantiate, then return instance of this class.
	 * @return TenUp_Safe_Redirect_Shorturls Controller instance.
	 */
	public static function get_instance() {
		if ( !is_a( self::$instance, __CLASS__ ) ) {
			self::$instance = true;
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}
	
	/**
	 * Initial setup. Called by get_instance.
	 */
	protected function init() {
		$this->check_plugin_requirements();

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		add_action( 'wp_ajax_create-short-url', array( $this, 'wp_ajax_create_short_url' ) );
		add_action( 'wp_ajax_nopriv_create-short-url', array( $this, 'create_short_url' ) );
	}

	/**
	 * Output all notices that have been added to the $this->notices array
	 */
	public function admin_notices() {
		foreach( $this->notices as $key => $message ) {
			echo "<div class='updated fade' id='styles-$key'>$message</div>";
		}
	}

	protected function check_plugin_requirements() {
		$this->srm_file = trailingslashit( WP_PLUGIN_DIR ) . $this->srm_slug;

		if ( isset( $_GET['plugin'] ) && plugin_basename( __FILE__ ) != $_GET['plugin'] ) {
			add_action( 'update_option_active_plugins', array( $this, 'srm_deactivate' ) );
		}

		if ( class_exists( 'SRM_Safe_Redirect_Manager' ) ) {
			return;
		}

		if ( !function_exists( 'is_plugin_inactive') ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_inactive( $this->srm_slug ) && file_exists( $this->srm_file ) ) {
			// SRM is installed, but not active. Activate it.
			activate_plugin( $this->srm_file );
		}else {
			$url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=safe-redirect-manager' ), 'install-plugin_safe-redirect-manager' );
			$this->notices[] = "<p><strong>Safe Redirect Short URLs</strong> requires <strong>Safe Redirect Manager</strong>. Please <a href='$url'>install it</a>.</p>";
		}

	}

	public function check_ajax_requirements() {
		if ( !isset( $_GET['url'] ) ) {
			exit( 'No URL set' );
		}

		$_GET['url'] = trim( $_GET['url'] );

		if ( !filter_var( $_GET['url'], FILTER_VALIDATE_URL ) ) {
			$with_http = 'http://' . $_GET['url'];

			if ( filter_var( $with_http, FILTER_VALIDATE_URL ) ) {
				$_GET['url'] = $with_http;
			}else {
				exit( 'Please provide a valid URL. You sent: ' . $_GET['url'] );
			}
			
		}

		if ( $this->get_api_key() ) {
			if ( !isset( $_GET['key'] ) || $_GET['key'] != $this->get_api_key() ) {
				exit( 'Please provide a valid API key.' );
			}
		}
	}

	public function get_api_key() {
		$api_key = false;

		if ( defined( 'SHORT_URL_API_KEY') ) {
			$api_key = SHORT_URL_API_KEY;
		}

		return apply_filters( 'short_url_api_key', $api_key );
	}

	public function wp_ajax_create_short_url() {
		global $safe_redirect_manager;
		
		$this->check_ajax_requirements();

		$this->get_existing_redirect_hash();

		if ( !$this->redirect_hash ) {

			add_filter( 'update_post_metadata', array( $this, 'set_redirect_hash' ), 10, 5 );
			$safe_redirect_manager->create_redirect( md5( rand() ), $_GET['url'], 301 );
		
		}

		exit( site_url( $this->redirect_hash ) );
	}

	protected function get_existing_redirect_hash() {
		global $wpdb, $safe_redirect_manager;

		$sanitized_redirect_to = $safe_redirect_manager->sanitize_redirect_to( $_GET['url'] );

		$sql = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key=%s AND meta_value=%s";
		$post_id = $wpdb->get_var( $wpdb->prepare( $sql, $safe_redirect_manager->meta_key_redirect_to, $sanitized_redirect_to ) );

		if ( $post_id ) {
			$sql = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key=%s AND post_id=%d";
			$this->redirect_hash = $wpdb->get_var( $wpdb->prepare( $sql, $safe_redirect_manager->meta_key_redirect_from, $post_id ) );

			return $this->redirect_hash;
		}

		return false;
	}

	public function set_redirect_hash( $null, $object_id, $meta_key, $meta_value, $prev_value ) {
		global $safe_redirect_manager;

		if ( $meta_key != $safe_redirect_manager->meta_key_redirect_from ) {
			return $null;
		}

		remove_filter( 'update_post_metadata', array( $this, 'set_redirect_hash' ), 10, 5 );

		require_once dirname( __FILE__ ) . '/includes/class-hashids.php';

		$hashids = new Hashids\Hashids( NONCE_SALT );
		$post_hash = '/' . $hashids->encrypt( $object_id );

		$this->redirect_hash = $safe_redirect_manager->sanitize_redirect_from( $post_hash );

		update_post_meta( $object_id, $safe_redirect_manager->meta_key_redirect_from, $this->redirect_hash );

		return true;

	}

	public function sanatize_url( $url ) {
		if ( false === strpos( $url, 'http://') && false === strpos( $url, 'https://' ) ) {
			return 'http://' . $url;
		}else {
			return $url;
		}
	}

	/**
	 * If Safe Redirect Manager is deactivated, deactivate this plugin too.
	 * @return [type] [description]
	 */
	public function srm_deactivate() {
		if ( !function_exists( 'deactivate_plugins') ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_inactive( $this->srm_slug ) ) {
			deactivate_plugins( __FILE__ );
		}

	}

}

TenUp_Safe_Redirect_Shorturls::get_instance();