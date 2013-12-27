<?php
/*
Plugin Name: Easy Digital Downloads - Volume Discounts
Plugin URI: http://easydigitaldownloads.com/extension/volume-discounts
Description: Provides the ability to create automatically applied discounts based on cart volume
Version: 1.2.4
Author: Pippin Williamson
Author URI:  http://pippinsplugins.com
Contributors: mordauk
*/

class EDD_Volume_Discounts {

	private static $instance;


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
		define( 'EDD_VOLUME_DISCOUNTS_VERSION', '1.2.4' );

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

		if( is_admin() ) {
			$admin = new EDD_Volume_Discounts_Admin;
		}

		// internationalization
		add_action( 'init', array( $this, 'textdomain' ) );

		// Register the Volume Discounts post type
		add_action( 'init', array( $this, 'register_post_type' ), 100 );

		// Apply discounts to the checkout
		add_action( 'init', array( $this, 'apply_discounts' ) );


		// Licenseing and updates
		if( ! class_exists( 'EDD_License' ) ) {
			include( dirname( __FILE__ ) . '/EDD_License_Handler.php' );
		}
		$license = new EDD_License( __FILE__, EDD_VOLUME_DISCOUNTS_PRODUCT_NAME, EDD_VOLUME_DISCOUNTS_VERSION, 'Pippin Williamson' );

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
			'public' 			=> false,
			'show_ui' 			=> true,
			'show_in_menu'      => 'edit.php?post_type=download',
			'query_var' 		=> false,
			'rewrite' 			=> false,
			'capability_type' 	=> 'shop_discount',
			'map_meta_cap'      => true,
			'supports' 			=> array( 'title' ),
			'can_export'		=> false
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

		global $wpdb;

		if( is_admin() )
			return;

		$cart_count  = 0;
		$cart_items  = edd_get_cart_contents();

		if( empty( $cart_items ) )
			return;

		foreach( $cart_items as $item ) {
			$cart_count += $item['quantity'];
		}

		$discount = $wpdb->get_var(
			"SELECT $wpdb->postmeta.post_id FROM $wpdb->posts, $wpdb->postmeta
			 WHERE $wpdb->posts.ID = $wpdb->postmeta.post_id
			 AND $wpdb->posts.post_status = 'publish'
			 AND $wpdb->postmeta.meta_value <= $cart_count
			 AND $wpdb->postmeta.meta_key = '_edd_volume_discount_number'
			 ORDER BY $wpdb->postmeta.meta_value+0
			 DESC LIMIT 1"
		);

		if( $discount ) {

			$number  = get_post_meta( $discount, '_edd_volume_discount_number', true );
			if( $number > $cart_count ) {
				EDD()->fees->remove_fee( 'volume_discount' );
				return;
			}

			$percent = get_post_meta( $discount, '_edd_volume_discount_amount', true );
			$amount  = $this->get_discount_amount( $percent );
			EDD()->fees->add_fee( $amount, get_the_title( $discount ), 'volume_discount' );

		} else {
			EDD()->fees->remove_fee( 'volume_discount' );
		}

	}


	/**
	 * Get the discounted amount
	 *
	 * @since 1.1
	 *
	 * @access public
	 * @return string
	 */
	private function get_discount_amount( $percentage ) {

		$amount = edd_get_cart_subtotal();

		if( edd_taxes_after_discounts() )
			$amount += edd_get_cart_tax();

		$amount  = ( $amount * ( $percentage / 100 ) ) * -1;

		return edd_sanitize_amount( $amount );
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



/**
 * Registers the new license field type
 *
 * @access      private
 * @since       10
 * @return      void
*/

if( ! function_exists( 'edd_license_key_callback' ) ) {
	function edd_license_key_callback( $args ) {
		global $edd_options;

		if( isset( $edd_options[ $args['id'] ] ) ) { $value = $edd_options[ $args['id'] ]; } else { $value = isset( $args['std'] ) ? $args['std'] : ''; }
		$size = isset( $args['size'] ) && !is_null($args['size']) ? $args['size'] : 'regular';
		$html = '<input type="text" class="' . $args['size'] . '-text" id="edd_settings_' . $args['section'] . '[' . $args['id'] . ']" name="edd_settings_' . $args['section'] . '[' . $args['id'] . ']" value="' . esc_attr( $value ) . '"/>';

		if( 'valid' == get_option( $args['options']['is_valid_license_option'] ) ) {
			$html .= wp_nonce_field( $args['id'] . '_nonce', $args['id'] . '_nonce', false );
			$html .= '<input type="submit" class="button-secondary" name="' . $args['id'] . '_deactivate" value="' . __( 'Deactivate License',  'edd-recurring' ) . '"/>';
		}
		$html .= '<label for="edd_settings_' . $args['section'] . '[' . $args['id'] . ']"> '  . $args['desc'] . '</label>';

		echo $html;
	}
}