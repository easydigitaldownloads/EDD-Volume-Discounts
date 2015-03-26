<?php
/**
 * Volume Discounts Admin
 *
 * @package     Volume Discounts
 * @subpackage  admin
 * @copyright   Copyright (c) 2013, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * EDD_Volume_Discounts_Admin Class
 *
 * @since 1.0
 */
class EDD_Volume_Discounts_Admin {


	function __construct() {

		add_action( 'admin_head',       array( $this, 'css' ) );
		add_action( 'add_meta_boxes',   array( $this, 'metaboxes' ) );
		add_action( 'admin_menu',       array( $this, 'metaboxes' ) );
		add_action( 'save_post',        array( $this, 'save_meta_box' ) );
		add_filter( 'enter_title_here', array( $this, 'change_default_title' ) );
		add_filter( 'post_updated_messages', array( $this, 'updated_messages' ) );
		add_filter( 'manage_edit-edd_volume_discount_columns', array( $this, 'columns' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'render_columns' ), 10, 2 );

	}


	/**
	 * Add / remove meta boxes
	 *
	 * @since 1.0
	 * @return void
	 */
	public function metaboxes() {

		add_meta_box( 'edd_voume_discount_metabox_save', __( 'Save Volume Discount', 'edd-volume-discounts' ),  array( __CLASS__, 'render_save_metabox' ), 'edd_volume_discount', 'side', 'core' );
		add_meta_box( 'edd_voume_discount_metabox_options', __( 'Discount Options', 'edd-volume-discounts' ),  array( __CLASS__, 'render_options_metabox' ), 'edd_volume_discount', 'normal', 'high' );

		remove_meta_box( 'submitdiv', 'edd_volume_discount', 'core' );
		remove_meta_box( 'slugdiv', 'edd_volume_discount', 'core' );

		// Plugin compatibility with Restrict Content Pro
		remove_meta_box( 'rcp_meta_box', 'edd_volume_discount', 'normal' );
		// Plugin compatibility with WordPress SEO
		remove_meta_box( 'wpseo_meta', 'edd_volume_discount', 'normal' );
	}


	/**
	 * Add inline CSS to the top of the edit / new screen for Volume Discounts
	 *
	 * This is to hide the Permalink section
	 *
	 * @since 1.0
	 * @return void
	 */
	public function css() {
		global $post, $pagenow;

		if( 'post.php' !== $pagenow && 'post-new.php' !== $pagenow )
			return;
		if( 'edd_volume_discount' !== get_post_type( $post->ID ) )
			return;

		echo '<style>#edit-slug-box{display:none;}</style>';
	}


	/**
	 * Render the Save meta box
	 *
	 * @since 1.0
	 * @return void
	 */
	public static function render_save_metabox() {
		global $post;
?>
		<div><?php _e( 'If the conditions are met, this discount will be automatically applied at checkout.', 'edd-volume-discounts' ); ?></div>
<?php
		$text = 'publish' === get_post_status( $post->ID ) ? __( 'Update', 'edd-volume-discounts' ) : __( 'Create', 'edd-volume-discounts' );
		submit_button( $text );
		do_action( 'edd_volume_discounts_save_metabox', $post->ID );
		wp_nonce_field( basename( __FILE__ ), 'edd_volume_discounts_meta_box_nonce' );
	}


	/**
	 * Render the options meta box
	 *
	 * @since 1.0
	 * @return void
	 */
	public static function render_options_metabox() {
		global $post;

		$number = get_post_meta( $post->ID, '_edd_volume_discount_number', true );
		$amount = get_post_meta( $post->ID, '_edd_volume_discount_amount', true );
?>
		<table>
			<tr>
				<td>
					<label for="edd-volume-discount-number"><?php _e( 'Number of Products Required', 'edd-volume-discounts' ); ?></label>
				</td>
				<td>
					<input type="number" min="1" step="1" id="edd-volume-discount-number" name="_edd_volume_discount_number" value="<?php echo absint( $number ); ?>"/>
				</td>
			</tr>
			<tr>
				<td>
					<label for="edd-volume-discount-amount"><?php _e( 'Discount Amount', 'edd-volume-discounts' ); ?>&nbsp;%&nbsp;</label>
				</td>
				<td>
					<input type="number" min="1" step="1" id="edd-volume-discount-amount" name="_edd_volume_discount_amount" value="<?php echo absint( $amount ); ?>"/>
				</td>
			</tr>
		</table>
<?php
		do_action( 'edd_volume_discounts_options_metabox', $post->ID );
	}


	/**
	 * Save post meta when the save_post action is called
	 *
	 * @since 1.0
	 * @param int $post_id Download (Post) ID
	 * @global array $post All the data of the the current post
	 * @return void
	 */
	public function save_meta_box( $post_id) {
		global $post;

		if ( ! isset( $_POST['edd_volume_discounts_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['edd_volume_discounts_meta_box_nonce'], basename( __FILE__ ) ) )
			return $post_id;

		if ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || ( defined( 'DOING_AJAX') && DOING_AJAX ) || isset( $_REQUEST['bulk_edit'] ) ) return $post_id;

		if ( isset( $post->post_type ) && $post->post_type == 'revision' )
			return $post_id;

		if ( ! current_user_can( 'manage_shop_discounts' ) ) {
			return $post_id;
		}

		// The default fields that get saved
		$fields = apply_filters( 'edd_volume_discounts_metabox_fields_save', array(
				'_edd_volume_discount_number',
				'_edd_volume_discount_amount',
			)
		);

		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				if ( is_string( $_POST[ $field ] ) ) {
					$new = sanitize_text_field( $_POST[ $field ] );
				} else {
					$new = array_map( 'sanitize_text_field', $_POST[ $field ] );
				}

				$new = apply_filters( 'edd_volume_discounts_metabox_save' . $field, $new );

				update_post_meta( $post_id, $field, $new );
			} else {
				delete_post_meta( $post_id, $field );
			}
		}

		// Force the discount to be marked as published
		if ( 'publish' !== get_post_status( $post->ID ) ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
		}
	}


	/**
	 *  Change the "Enter title here" text
	 *
	 * @since 1.0
	 * @return void
	 */
	public function change_default_title( $title ) {

		$screen = get_current_screen();

		if ( 'edd_volume_discount' == $screen->post_type ) {
			$title = __( 'Enter discount title. This will be shown on checkout.', 'edd-volume-discounts' );
		}

		return $title;
	}


	/**
	 * Updated Messages
	 *
	 * Returns an array of with all updated messages.
	 *
	 * @since 1.0
	 * @param array $messages Post updated message
	 * @return array $messages New post updated messages
	 */
	public function updated_messages( $messages ) {
		global $post, $post_ID;

		$messages['edd_volume_discount'] = array(
			1 => __( 'Volume Discount updated.', 'edd-volume-discount' ),
			4 => __( 'Volume Discount updated.', 'edd-volume-discount' ),
			6 => __( 'Volume Discount published.', 'edd-volume-discount' ),
			7 => __( 'Volume Discount saved.', 'edd-volume-discount' ),
			8 => __( 'Volume Discount submitted.', 'edd-volume-discount' )
		);

		return $messages;
	}


	/**
	 * Register list table columns
	 *
	 * @since 1.0
	 * @param $columns array An array of existing columns
	 * @return array
	 */
	public function columns( $columns ) {
		$columns['number'] = __( 'Number of Products Required', 'edd-volume-discounts' );
		$columns['amount'] = __( 'Discount Amount', 'edd-volume-discounts' );
		unset( $columns['date'] );
		return $columns;
	}


	/**
	 *  render the custom column content
	 *
	 * @since 1.0
	 * @param $column_name string The name of the column being rendered
	 * @param $post_id int The post ID a column is being rendered for
	 * @return void
	 */
	public function render_columns( $column_name, $post_id ) {
		if ( 'edd_volume_discount' !== get_post_type( $post_id ) ) {
			return;
		}

		switch ( $column_name ) {

			case 'number' :
				echo absint( get_post_meta( $post_id, '_edd_volume_discount_number', true ) );
				break;

			case 'amount' :
				echo absint( get_post_meta( $post_id, '_edd_volume_discount_amount', true ) ) . '%';
				break;

		}
	}

}