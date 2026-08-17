<?php
/**
 * Global settings admin page.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Admin_Settings
 */
class Art_Forms_Admin_Settings {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_post_art_forms_save_settings', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * Available settings tabs.
	 *
	 * @return array<string, string>
	 */
	public static function get_tabs() {
		return array(
			'general' => __( 'Основные', 'art-forms' ),
		);
	}

	/**
	 * Resolve current settings tab.
	 *
	 * @return string
	 */
	public static function get_current_tab() {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = self::get_tabs();

		if ( ! isset( $tabs[ $tab ] ) ) {
			return 'general';
		}

		return $tab;
	}

	/**
	 * Render settings page.
	 */
	public static function render_page() {
		if ( ! Art_Forms_Capabilities::can_manage() ) {
			return;
		}

		$settings = Art_Forms_Settings::get_all();
		$saved    = isset( $_GET['saved'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab      = self::get_current_tab();
		$tabs     = self::get_tabs();

		include ART_FORMS_PLUGIN_DIR . 'admin/views/page-settings.php';
	}

	/**
	 * Save settings.
	 */
	public static function handle_save() {
		if ( ! Art_Forms_Capabilities::can_manage() ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-forms' ) );
		}

		check_admin_referer( 'art_forms_save_settings' );

		Art_Forms_Settings::update(
			array(
				'default_email_to'         => isset( $_POST['default_email_to'] ) ? wp_unslash( (string) $_POST['default_email_to'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'default_email_subject'    => isset( $_POST['default_email_subject'] ) ? wp_unslash( (string) $_POST['default_email_subject'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'default_email_body'       => isset( $_POST['default_email_body'] ) ? wp_unslash( (string) $_POST['default_email_body'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'default_success_message'  => isset( $_POST['default_success_message'] ) ? wp_unslash( (string) $_POST['default_success_message'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'default_privacy_url'      => isset( $_POST['default_privacy_url'] ) ? wp_unslash( (string) $_POST['default_privacy_url'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'retention_days'           => isset( $_POST['retention_days'] ) ? absint( wp_unslash( $_POST['retention_days'] ) ) : 0,
				'store_payload'            => isset( $_POST['store_payload'] ) ? sanitize_key( wp_unslash( (string) $_POST['store_payload'] ) ) : 'full',
				'honeypot_enabled'         => ! empty( $_POST['honeypot_enabled'] ) ? 1 : 0,
				'rate_limit_enabled'       => ! empty( $_POST['rate_limit_enabled'] ) ? 1 : 0,
				'rate_limit_max'           => isset( $_POST['rate_limit_max'] ) ? absint( wp_unslash( $_POST['rate_limit_max'] ) ) : 5,
				'rate_limit_window'        => isset( $_POST['rate_limit_window'] ) ? absint( wp_unslash( $_POST['rate_limit_window'] ) ) : 10,
				'delivery_fail_notify'     => ! empty( $_POST['delivery_fail_notify'] ) ? 1 : 0,
				'delivery_fail_email'      => isset( $_POST['delivery_fail_email'] ) ? wp_unslash( (string) $_POST['delivery_fail_email'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'crm_notify_enabled'       => ! empty( $_POST['crm_notify_enabled'] ) ? 1 : 0,
				'crm_notify_email'         => isset( $_POST['crm_notify_email'] ) ? wp_unslash( (string) $_POST['crm_notify_email'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'crm_manager_ids'          => isset( $_POST['crm_manager_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['crm_manager_ids'] ) ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			)
		);

		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( (string) $_POST['tab'] ) ) : 'general';
		if ( ! isset( self::get_tabs()[ $tab ] ) ) {
			$tab = 'general';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => 'art-forms-settings',
					'tab'   => $tab,
					'saved' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
