<?php
/**
 * Admin forms list and editor.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Admin_Forms
 */
class Art_Forms_Admin_Forms {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_post_art_forms_save_form', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_art_forms_duplicate_form', array( __CLASS__, 'handle_duplicate' ) );
		add_action( 'admin_post_art_forms_delete_form', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_art_forms_export_form', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_art_forms_import_form', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_art_forms_test_email', array( __CLASS__, 'handle_test_email' ) );
		add_action( 'wp_ajax_art_forms_check_code', array( __CLASS__, 'ajax_check_code' ) );
	}

	/**
	 * Forms list page.
	 */
	public static function render_list_page() {
		if ( ! Art_Forms_Capabilities::can_manage() ) {
			return;
		}

		$forms = get_posts(
			array(
				'post_type'      => Art_Forms_Post_Types::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$import_error  = Art_Forms_Form_Pack::consume_error();
		$import_notice = null;
		if ( isset( $_GET['imported'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$import_notice = Art_Forms_Form_Pack::consume_notice();
		}

		include ART_FORMS_PLUGIN_DIR . 'admin/views/page-forms-list.php';
	}

	/**
	 * Edit / new form page.
	 */
	public static function render_edit_page() {
		if ( ! Art_Forms_Capabilities::can_manage() ) {
			return;
		}

		$form_id = 0;
		if ( isset( $_GET['form_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$form_id = absint( wp_unslash( $_GET['form_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'art-forms-new' === $page ) {
			$form_id = 0;
		}

		$form    = $form_id ? get_post( $form_id ) : null;
		$schema  = $form_id ? Art_Forms_Schema::get( $form_id ) : Art_Forms_Schema::empty_schema();
		$settings = $form_id ? Art_Forms_Form_Settings::get( $form_id ) : Art_Forms_Form_Settings::defaults();
		$title   = $form ? $form->post_title : '';
		$code    = $form_id ? Art_Forms_Export::form_code( $form_id ) : '';
		$prompt  = $form_id ? Art_Forms_Export::design_prompt( $form_id ) : '';

		$notice     = '';
		$just_saved = false;
		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$just_saved = true;
		}
		if ( isset( $_GET['duplicated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$notice = __( 'Форма скопирована.', 'art-forms' );
		}
		if ( isset( $_GET['tested'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$notice = __( 'Тестовое письмо отправлено (см. лог доставок).', 'art-forms' );
		}

		$import_notice = null;
		if ( isset( $_GET['imported'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$import_notice = Art_Forms_Form_Pack::consume_notice();
		}

		include ART_FORMS_PLUGIN_DIR . 'admin/views/page-form-edit.php';
	}

	/**
	 * Save form.
	 */
	public static function handle_save() {
		if ( ! Art_Forms_Capabilities::can_manage() ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-forms' ) );
		}

		check_admin_referer( 'art_forms_save_form' );

		$form_id = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		$title   = isset( $_POST['form_title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['form_title'] ) ) : '';
		if ( '' === $title ) {
			$title = __( 'Без названия', 'art-forms' );
		}

		$schema_json = isset( $_POST['schema_json'] ) ? wp_unslash( (string) $_POST['schema_json'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$schema_raw  = json_decode( $schema_json, true );
		if ( ! is_array( $schema_raw ) ) {
			$schema_raw = Art_Forms_Schema::empty_schema();
		}

		$settings_in = array(
			'success_message'   => isset( $_POST['success_message'] ) ? wp_unslash( (string) $_POST['success_message'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'submit_label'      => isset( $_POST['submit_label'] ) ? wp_unslash( (string) $_POST['submit_label'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'intro_title'       => isset( $_POST['intro_title'] ) ? wp_unslash( (string) $_POST['intro_title'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'intro_description' => isset( $_POST['intro_description'] ) ? wp_unslash( (string) $_POST['intro_description'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'style_brief'       => isset( $_POST['style_brief'] ) ? wp_unslash( (string) $_POST['style_brief'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'actions'           => isset( $_POST['actions'] ) && is_array( $_POST['actions'] ) ? wp_unslash( $_POST['actions'] ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		);

		if ( $form_id > 0 ) {
			wp_update_post(
				array(
					'ID'         => $form_id,
					'post_title' => $title,
					'post_status'=> 'publish',
				)
			);
		} else {
			$form_id = wp_insert_post(
				array(
					'post_type'   => Art_Forms_Post_Types::POST_TYPE,
					'post_title'  => $title,
					'post_status' => 'publish',
				),
				true
			);
			if ( is_wp_error( $form_id ) ) {
				wp_die( esc_html( $form_id->get_error_message() ) );
			}
			$form_id = absint( $form_id );
		}

		Art_Forms_Schema::save( $form_id, $schema_raw );
		Art_Forms_Form_Settings::save( $form_id, $settings_in );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'art-forms-edit',
					'form_id' => $form_id,
					'saved'   => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Duplicate form.
	 */
	public static function handle_duplicate() {
		if ( ! Art_Forms_Capabilities::can_manage() ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-forms' ) );
		}

		check_admin_referer( 'art_forms_duplicate_form' );

		$form_id = isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0;
		$source  = get_post( $form_id );
		if ( ! $source || Art_Forms_Post_Types::POST_TYPE !== $source->post_type ) {
			wp_die( esc_html__( 'Форма не найдена.', 'art-forms' ) );
		}

		$new_id = wp_insert_post(
			array(
				'post_type'   => Art_Forms_Post_Types::POST_TYPE,
				'post_title'  => $source->post_title . ' ' . __( '(копия)', 'art-forms' ),
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}

		$new_id = absint( $new_id );
		Art_Forms_Schema::save( $new_id, Art_Forms_Schema::get( $form_id ) );
		Art_Forms_Form_Settings::save( $new_id, Art_Forms_Form_Settings::get( $form_id ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'art-forms-edit',
					'form_id'    => $new_id,
					'duplicated' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Delete form.
	 */
	public static function handle_delete() {
		if ( ! Art_Forms_Capabilities::can_manage() ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-forms' ) );
		}

		check_admin_referer( 'art_forms_delete_form' );

		$form_id = isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0;
		if ( $form_id > 0 ) {
			wp_trash_post( $form_id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=art-forms' ) );
		exit;
	}

	/**
	 * Download form pack JSON.
	 */
	public static function handle_export() {
		if ( ! Art_Forms_Capabilities::can_manage() ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-forms' ) );
		}

		check_admin_referer( 'art_forms_export_form' );

		$form_id = isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0;
		Art_Forms_Form_Pack::send_download( $form_id );
	}

	/**
	 * Import form pack JSON.
	 */
	public static function handle_import() {
		if ( ! Art_Forms_Capabilities::can_manage() ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-forms' ) );
		}

		check_admin_referer( 'art_forms_import_form' );

		$file = isset( $_FILES['art_forms_pack'] ) && is_array( $_FILES['art_forms_pack'] ) ? $_FILES['art_forms_pack'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed below.
		$pack = Art_Forms_Form_Pack::parse_upload( $file );
		if ( is_wp_error( $pack ) ) {
			Art_Forms_Form_Pack::store_error( $pack->get_error_message() );
			wp_safe_redirect( admin_url( 'admin.php?page=art-forms&import_error=1' ) );
			exit;
		}

		$result = Art_Forms_Form_Pack::import_pack( $pack );
		if ( is_wp_error( $result ) ) {
			Art_Forms_Form_Pack::store_error( $result->get_error_message() );
			wp_safe_redirect( admin_url( 'admin.php?page=art-forms&import_error=1' ) );
			exit;
		}

		Art_Forms_Form_Pack::store_notice( $result );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => 'art-forms-edit',
					'form_id'  => (int) $result['form_id'],
					'imported' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Send test email.
	 */
	public static function handle_test_email() {
		if ( ! Art_Forms_Capabilities::can_manage() ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-forms' ) );
		}

		check_admin_referer( 'art_forms_test_email' );

		$form_id = isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0;
		if ( $form_id <= 0 ) {
			wp_die( esc_html__( 'Форма не найдена.', 'art-forms' ) );
		}

		$context = Art_Forms_Delivery_Email::build_test_context( $form_id );
		Art_Forms_Delivery::deliver_all( $context, array( 'is_test' => true ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'art-forms-edit',
					'form_id' => $form_id,
					'tested'  => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * AJAX code checker.
	 */
	public static function ajax_check_code() {
		if ( ! Art_Forms_Capabilities::can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Недостаточно прав.', 'art-forms' ) ), 403 );
		}

		check_ajax_referer( 'art_forms_check_code', 'nonce' );

		$form_id = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		$code    = isset( $_POST['code'] ) ? wp_unslash( (string) $_POST['code'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$result = Art_Forms_Code_Checker::check( $form_id, $code );
		wp_send_json_success( $result );
	}
}
