<?php
/*
Plugin Name: Safe Redirect Short URLs
Plugin URI: https://github.com/pdclark/safe-redirect-short-urls
Description: Generate short urls in Safe Redirect Manager by loading <code>/wp-admin/admin-ajax.php?action=create-short-url&key=YOUR_API_KEY&url=http://longurl.com</code>. Set your API key in <code>wp-config.php</code> with <code>define( 'SHORT_URL_API_KEY', 'your-api-key' );</code>
Version: 1.0
Author: Paul Clark, 10up
Author URI: http://pdclark.com
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

class TenUp_Safe_Redirect_Short_Urls {

	/**
	 * @var TenUp_Safe_Redirect_Short_Urls Instance of this class.
	 */
	private static $instance = false;

	/**
	 * @var array  Admin notices
	 */
	protected $notices = array();

	/**
	 * @var string  Plugin slug for Safe Redirect Manager.
	 */
	protected $safe_redirect_slug = 'safe-redirect-manager/safe-redirect-manager.php';

	/**
	 * @var string  Absolute path to Safe Redirect Manager.
	 */
	protected $safe_redirect_file;

	/**
	 * 
	 * 
	 * @var boolean|string  False or hash to pass as redirect_from for Safe Redirect Manager.
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
	 * 
	 * @return TenUp_Safe_Redirect_Short_Urls Controller instance.
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

		add_action( 'wp_ajax_srm-short-url', array( $this, 'wp_ajax_srm_short_url' ) );
		add_action( 'wp_ajax_nopriv_srm-short-url', array( $this, 'srm_short_url' ) );

		// Remove redirect limitation
		add_filter( 'srm_max_redirects', array( $this, 'max_redirects' ) );
	}

	/**
	 * Output all notices that have been added to the $this->notices array.
	 */
	public function admin_notices() {
		foreach( $this->notices as $key => $message ) {
			echo "<div class='updated fade' id='styles-$key'>$message</div>";
		}
	}

	/**
	 * Verify Safe Redirect Manager is installed and active.
	 * Attempt to activate SRM if it is installed, but not active.
	 */
	protected function check_plugin_requirements() {
		$this->safe_redirect_file = trailingslashit( WP_PLUGIN_DIR ) . $this->safe_redirect_slug;

		if ( isset( $_GET['plugin'] ) && plugin_basename( __FILE__ ) != $_GET['plugin'] ) {
			add_action( 'update_option_active_plugins', array( $this, 'srm_deactivate' ) );
		}

		if ( class_exists( 'SRM_Safe_Redirect_Manager' ) ) {
			return;
		}

		if ( !function_exists( 'is_plugin_inactive') ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_inactive( $this->safe_redirect_slug ) && file_exists( $this->safe_redirect_file ) ) {
			// SRM is installed, but not active. Activate it.
			activate_plugin( $this->safe_redirect_file );
		}else {
			$url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=safe-redirect-manager' ), 'install-plugin_safe-redirect-manager' );
			$this->notices[] = "<p><strong>Safe Redirect Short URLs</strong> requires <strong>Safe Redirect Manager</strong>. Please <a href='$url'>install it</a>.</p>";
		}

	}

	/**
	 * Remove redirect count limitation.
	 * @param  int $max
	 * @return int
	 */
	public function max_redirects( $max ) {
		return PHP_INT_MAX;
	}

	/**
	 * Exit with notice if $_GET['url'] or $_GET['key'] are not valid.
	 */
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

	/**
	 * Load API key from constant or filter.
	 *
	 * @todo  (maybe) Create a hashid API key for each administrator and editor.
	 * @return string API key
	 */
	public function get_api_key() {
		$api_key = false;

		if ( defined( 'SHORT_URL_API_KEY') ) {
			$api_key = SHORT_URL_API_KEY;
		}

		return apply_filters( 'short_url_api_key', $api_key );
	}

	/**
	 * Create a redirect for $_GET['url']
	 * Set the redirect_from path to a short hashid
	 * 
	 * @return string (Output to browser) Full short URL. For example, http://10up.com/abc123
	 */
	public function wp_ajax_srm_short_url() {
		global $safe_redirect_manager;
		
		$this->check_ajax_requirements();

		$this->get_existing_redirect_hash();

		if ( !$this->redirect_hash ) {

			add_filter( 'update_post_metadata', array( $this, 'set_redirect_hash' ), 10, 5 );
			$safe_redirect_manager->create_redirect( md5( rand() ), $_GET['url'], 301 );
		
		}

		exit( site_url( $this->redirect_hash ) );
	}

	/**
	 * Check if a redirect already exists for $_GET['url']
	 * If so, set $this->redirect_hash to existing redirect_from hash.
	 * 
	 * @return string|bool Redirect hash or false
	 */
	public function get_existing_redirect_hash() {
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

	/**
	 * Intercept the update_post_meta('_redirect_rule_from') call in $safe_redirect_manager->create_redirect()
	 * Throw out the randomly generated redirect_from, then replace with hashid based on the new post's ID.
	 * 
	 * @param null   $null       null.
	 * @param int    $object_id  Post ID
	 * @param string $meta_key   meta_key for post_meta. Method does nothing if not == '_redirect_rule_from'
	 * @param string $meta_value Not used.
	 * @param string $prev_value Not used.
	 * 
	 * @return  null|bool  null to continue original update. True to cancel original update.
	 */
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

	/**
	 * If Safe Redirect Manager is deactivated, deactivate this plugin too.
	 */
	public function srm_deactivate() {
		if ( !function_exists( 'deactivate_plugins') ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_inactive( $this->safe_redirect_slug ) ) {
			deactivate_plugins( __FILE__ );
		}

	}

}

TenUp_Safe_Redirect_Short_Urls::get_instance();