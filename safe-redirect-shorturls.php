<?php
/*
Plugin Name: Safe Redirect Short URLs
Plugin URI: http://10up.com
Description: 
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
		$this->check_requirements();

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Output all notices that have been added to the $this->notices array
	 */
	public function admin_notices() {
		foreach( $this->notices as $key => $message ) {
			echo "<div class='updated fade' id='styles-$key'>$message</div>";
		}
	}

	protected function check_requirements() {
		$this->srm_file = trailingslashit( WP_PLUGIN_DIR ) . $this->srm_slug;

		// Deactivate this plugin if SRM is deactivated.
		add_action( 'update_option_active_plugins', array( $this, 'srm_deactivate' ) );

		if ( class_exists( 'SRM_Safe_Redirect_Manager' ) || isset( $_GET['deactivate'] ) ) {
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