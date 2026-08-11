<?php
/**
 * Delivery log admin page.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Admin_Delivery_Log
 */
class Art_Forms_Admin_Delivery_Log {

	/**
	 * Register hooks.
	 */
	public static function init() {
		// No extra hooks.
	}

	/**
	 * Render page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$form_id = isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page    = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$result = Art_Forms_Delivery_Log::query(
			array(
				'form_id'  => $form_id,
				'page'     => $page,
				'per_page' => 50,
			)
		);

		$forms = get_posts(
			array(
				'post_type'      => Art_Forms_Post_Types::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		include ART_FORMS_PLUGIN_DIR . 'admin/views/page-delivery-log.php';
	}
}
