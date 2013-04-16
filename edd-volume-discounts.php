<?php
/*
Plugin Name: Easy Digital Downloads - Volume Discounts
Plugin URI: http://easydigitaldownloads.com/extension/volume-discounts
Description: Provides the ability to charge simple shipping fees for physical products in EDD
Version: 1.0
Author: Pippin Williamson
Author URI:  http://pippinsplugins.com
Contributors: mordauk
*/

class EDD_Volume_Discounts {

	private static $instance;

	private static $has_discounts = false;

	/**
	 * Get active object instance
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @static
	 * @return object
	 */
	public static function get_instance() {

		if ( ! self::$instance )
			self::$instance = new EDD_Volume_Discounts();

		return self::$instance;
	}

	/**
	 * Class constructor.  Includes constants, includes and init method.
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {

		define( 'EDD_VOLUME_DISCOUNTS_STORE_API_URL', 'https://easydigitaldownloads.com' );
		define( 'EDD_VOLUME_DISCOUNTS_PRODUCT_NAME', 'Volume Discounts' );
		define( 'EDD_VOLUME_DISCOUNTS_VERSION', '1.0' );

		if( !class_exists( 'EDD_SL_Plugin_Updater' ) ) {
			// load our custom updater
			include( dirname( __FILE__ ) . '/EDD_SL_Plugin_Updater.php' );
		}

		$this->includes();
		$this->init();

	}


	/**
	 * Include our extra files
	 *
	 * @since 1.0
	 *
	 * @access private
	 * @return void
	 */
	private function includes() {

		if( is_admin() ) {

			include dirname( __FILE__ ) . '/includes/admin.php';

		} else {

		}

	}


	/**
	 * Run action and filter hooks.
	 *
	 * @since 1.0
	 *
	 * @access private
	 * @return void
	 */
	private function init() {

		if( ! class_exists( 'Easy_Digital_Downloads' ) )
			return; // EDD not present

		global $edd_options;

		if( is_admin() ) {
			$admin = new EDD_Volume_Discounts_Admin;
		}

		// internationalization
		add_action( 'init', array( $this, 'textdomain' ) );

		// Register the Volume Discounts post type
		add_action( 'init', array( $this, 'register_post_type' ), 100 );

		// Apply discounts to the checkout
		add_action( 'init', array( $this, 'apply_discounts' ) );

		// register our license key settings
		add_filter( 'edd_settings_general', array( $this, 'settings' ), 1 );

		// activate license key on settings save
		add_action( 'admin_init', array( $this, 'activate_license' ) );

		// auto updater

		// retrieve our license key from the DB
		$edd_volume_discounts_license_key = isset( $edd_options['edd_volume_discounts_license_key'] ) ? trim( $edd_options['edd_volume_discounts_license_key'] ) : '';

		// setup the updater
		$edd_updater = new EDD_SL_Plugin_Updater( EDD_VOLUME_DISCOUNTS_STORE_API_URL, __FILE__, array(
				'version' 	=> EDD_VOLUME_DISCOUNTS_VERSION, 		// current version number
				'license' 	=> $edd_volume_discounts_license_key, // license key (used get_option above to retrieve from DB)
				'item_name' => EDD_VOLUME_DISCOUNTS_PRODUCT_NAME, // name of this plugin
				'author' 	=> 'Pippin Williamson'  // author of this plugin
			)
		);

	}


	/**
	 * Load plugin text domain
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return void
	 */
	public static function textdomain() {

		// Set filter for plugin's languages directory
		$lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
		$lang_dir = apply_filters( 'edd_volume_discounts_lang_directory', $lang_dir );

		// Load the translations
		load_plugin_textdomain( 'edd-volume-discounts', false, $lang_dir );

	}


	/**
	 * register the post type
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return void
	 */
	public function register_post_type() {

		/** Payment Post Type */
		$labels = array(
			'name' 				=> _x('Volume Discounts', 'post type general name', 'edd' ),
			'singular_name' 	=> _x('Volume Discount', 'post type singular name', 'edd' ),
			'add_new' 			=> __( 'Add New', 'edd' ),
			'add_new_item' 		=> __( 'Add New Volume Discount', 'edd' ),
			'edit_item' 		=> __( 'Edit Volume Discount', 'edd' ),
			'new_item' 			=> __( 'New Volume Discount', 'edd' ),
			'all_items' 		=> __( 'Volume Discounts', 'edd' ),
			'view_item' 		=> __( 'View Volume Discount', 'edd' ),
			'search_items' 		=> __( 'Search Volume Discounts', 'edd' ),
			'not_found' 		=> __( 'No Volume Discounts found', 'edd' ),
			'not_found_in_trash'=> __( 'No Volume Discounts found in Trash', 'edd' ),
			'parent_item_colon' => '',
			'menu_name' 		=> __( 'Volume Discounts', 'edd' )
		);

		$args = array(
			'labels' 			=> apply_filters( 'edd_volume_discounts_labels', $labels ),
			'public' 			=> true,
			'show_ui' 			=> true,
			'show_in_menu'      => 'edit.php?post_type=download',
			'query_var' 		=> false,
			'rewrite' 			=> false,
			'capability_type' 	=> 'shop_discount',
			'map_meta_cap'      => true,
			'supports' 			=> array( 'title' ),
			'can_export'		=> true
		);

		register_post_type( 'edd_volume_discount', $args );
	}


	/**
	 * Apply the discounts to the cart
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return void
	 */
	public function apply_discounts() {

		$cart_count  = count( edd_get_cart_contents() );
		$cart_amount = edd_get_cart_subtotal();

		$discounts   = get_posts( array(
			'post_type'      => 'edd_volume_discount',
			'posts_per_page' => '1',
			'meta_key'       => '_edd_volume_discount_number',
			'meta_compare'   => '>=',
			'meta_value'     => $cart_count,
			'orderby'        => 'meta_value_num',
			'order'          => 'ASC',
			'fields'         => 'ids'
		) );

		foreach( $discounts as $discount ) {

			$number  = get_post_meta( $discount, '_edd_volume_discount_number', true );
			if( $number > $cart_count ) {
				EDD()->fees->remove_fee( 'volume_discount' );
				return;
			}

			$percent = get_post_meta( $discount, '_edd_volume_discount_amount', true );
			$amount  = ( $cart_amount * ( $percent / 100 ) ) * -1;
			EDD()->fees->add_fee( $amount, get_the_title( $discount ), 'volume_discount' );
		}

	}


	/**
	 * Add our extension settings
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return array
	 */
	public function settings( $settings ) {
		$license_settings = array(
			array(
				'id' => 'edd_volume_discounts_license_header',
				'name' => '<strong>' . __( 'Volume Discounts', 'edd-volume-discounts' ) . '</strong>',
				'desc' => '',
				'type' => 'header',
				'size' => 'regular'
			),
			array(
				'id' => 'edd_volume_discounts_license_key',
				'name' => __( 'License Key', 'edd-volume-discounts' ),
				'desc' => __( 'Enter your license for Volume Discounts to receive automatic upgrades', 'edd-volume-discounts' ),
				'type'  => 'license_key',
				'size'  => 'regular',
				'options' => array( 'is_valid_license_option' => 'edd_volume_discounts_license_active' )
			)
		);

		return array_merge( $settings, $license_settings );
	}


	/**
	 * Activate a license key
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return void
	 */
	public function activate_license() {

		global $edd_options;

		if( ! isset( $_POST['edd_settings_general'] ) )
			return;
		if( ! isset( $_POST['edd_settings_general']['edd_volume_discounts_license_key'] ) )
			return;

		if( get_option( 'edd_volume_discounts_license_active' ) == 'valid' )
			return;

		$license = sanitize_text_field( $_POST['edd_settings_general']['edd_volume_discounts_license_key'] );

		// data to send in our API request
		$api_params = array(
			'edd_action'=> 'activate_license',
			'license' 	=> $license,
			'item_name' => urlencode( EDD_VOLUME_DISCOUNTS_PRODUCT_NAME ) // the name of our product in EDD
		);

		// Call the custom API.
		$response = wp_remote_get( add_query_arg( $api_params, EDD_VOLUME_DISCOUNTS_STORE_API_URL ), array( 'timeout' => 15, 'body' => $api_params, 'sslverify' => false ) );

		// make sure the response came back okay
		if ( is_wp_error( $response ) )
			return false;

		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		update_option( 'edd_volume_discounts_license_active', $license_data->license );

	}

}


/**
 * Get everything running
 *
 * @since 1.0
 *
 * @access private
 * @return void
 */

function edd_volume_discounts_load() {
	$discounts = new EDD_Volume_Discounts();
}
add_action( 'plugins_loaded', 'edd_volume_discounts_load' );