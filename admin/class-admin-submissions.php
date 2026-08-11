<?php
/**
 * Admin submissions list.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Admin_Submissions
 */
class Art_Forms_Admin_Submissions {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_post_art_forms_export_csv', array( __CLASS__, 'handle_csv' ) );
		add_action( 'admin_post_art_forms_delete_submission', array( __CLASS__, 'handle_delete' ) );
	}

	/**
	 * Render submissions page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$form_id   = isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page      = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$view_id   = isset( $_GET['view'] ) ? absint( wp_unslash( $_GET['view'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$deleted   = isset( $_GET['deleted'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby   = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( (string) $_GET['orderby'] ) ) : 'id'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order     = isset( $_GET['order'] ) ? strtolower( sanitize_text_field( wp_unslash( (string) $_GET['order'] ) ) ) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$allowed_orderby = array( 'id', 'form', 'date', 'email', 'phone', 'status' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'id';
		}
		if ( 'asc' !== $order && 'desc' !== $order ) {
			$order = 'desc';
		}

		if ( $view_id > 0 ) {
			$submission = Art_Forms_Submissions::get( $view_id );
			include ART_FORMS_PLUGIN_DIR . 'admin/views/page-submission-view.php';
			return;
		}

		$result = Art_Forms_Submissions::query(
			array(
				'form_id'   => $form_id,
				'date_from' => $date_from,
				'date_to'   => $date_to,
				'search'    => $search,
				'page'      => $page,
				'per_page'  => 20,
				'orderby'   => $orderby,
				'order'     => $order,
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

		include ART_FORMS_PLUGIN_DIR . 'admin/views/page-submissions.php';
	}

	/**
	 * Delete submission handler.
	 */
	public static function handle_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-forms' ) );
		}

		check_admin_referer( 'art_forms_delete_submission' );

		$id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		if ( $id > 0 ) {
			Art_Forms_Submissions::delete( $id );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'art-forms-submissions',
					'deleted' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * CSV download handler.
	 */
	public static function handle_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-forms' ) );
		}

		check_admin_referer( 'art_forms_export_csv' );

		Art_Forms_Csv_Export::download(
			array(
				'form_id'   => isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0,
				'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_from'] ) ) : '',
				'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_to'] ) ) : '',
			)
		);
	}
}
