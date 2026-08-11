<?php
/**
 * Per-form settings stored in post meta.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Form_Settings
 */
class Art_Forms_Form_Settings {

	const META_KEY = '_art_forms_settings';

	/**
	 * Defaults for a form.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		$global = Art_Forms_Settings::get_all();

		return array(
			'success_message' => (string) $global['default_success_message'],
			'actions'         => array(
				Art_Forms_Form_Actions::default_action( 'email_admin' ),
			),
		);
	}

	/**
	 * Default subject for client auto-reply.
	 *
	 * @return string
	 */
	public static function default_client_email_subject() {
		return __( 'Мы получили вашу заявку: {form_title}', 'art-forms' );
	}

	/**
	 * Default body for client auto-reply.
	 *
	 * @return string
	 */
	public static function default_client_email_body() {
		return __(
			"Здравствуйте!\n\nМы получили вашу заявку «{form_title}» и свяжемся с вами в ближайшее время.\n\nСпасибо!",
			'art-forms'
		);
	}

	/**
	 * Migrate legacy flat settings into actions list.
	 *
	 * @param array<string, mixed> $stored Stored meta.
	 * @return array<string, mixed>
	 */
	private static function migrate_legacy( array $stored ) {
		if ( isset( $stored['actions'] ) && is_array( $stored['actions'] ) ) {
			return $stored;
		}

		$global  = Art_Forms_Settings::get_all();
		$actions = array();

		$actions[] = array(
			'type'          => 'email_admin',
			'email_to'      => isset( $stored['email_to'] ) ? (string) $stored['email_to'] : (string) $global['default_email_to'],
			'email_subject' => isset( $stored['email_subject'] ) ? (string) $stored['email_subject'] : (string) $global['default_email_subject'],
			'email_body'    => isset( $stored['email_body'] ) ? (string) $stored['email_body'] : (string) $global['default_email_body'],
		);

		$redirect_url = isset( $stored['redirect_url'] ) ? (string) $stored['redirect_url'] : '';
		if ( '' !== $redirect_url ) {
			$actions[] = array(
				'type'         => 'redirect',
				'redirect_url' => $redirect_url,
			);
		}

		if ( ! empty( $stored['client_email_enabled'] ) ) {
			$actions[] = array(
				'type'                 => 'email_client',
				'client_email_subject' => isset( $stored['client_email_subject'] ) ? (string) $stored['client_email_subject'] : self::default_client_email_subject(),
				'client_email_body'    => isset( $stored['client_email_body'] ) ? (string) $stored['client_email_body'] : self::default_client_email_body(),
			);
		}

		$stored['actions'] = $actions;

		return $stored;
	}

	/**
	 * Normalize settings.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return array<string, mixed>
	 */
	private static function normalize( array $settings ) {
		$defaults = self::defaults();
		$settings = self::migrate_legacy( $settings );
		$merged   = wp_parse_args( $settings, $defaults );

		$merged['success_message'] = self::repair_stripped_newlines( (string) $merged['success_message'] );
		$merged['actions']         = Art_Forms_Form_Actions::sanitize_list(
			isset( $merged['actions'] ) ? $merged['actions'] : array()
		);

		return array(
			'success_message' => $merged['success_message'],
			'actions'         => $merged['actions'],
		);
	}

	/**
	 * Get settings for form.
	 *
	 * @param int $form_id Form ID.
	 * @return array<string, mixed>
	 */
	public static function get( $form_id ) {
		$form_id = absint( $form_id );
		$raw     = get_post_meta( $form_id, self::META_KEY, true );
		$stored  = array();
		$legacy  = false;

		if ( is_array( $raw ) ) {
			$stored = $raw;
		} elseif ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$stored = $decoded;
				$legacy = true;
			}
		}

		$had_actions = isset( $stored['actions'] ) && is_array( $stored['actions'] );
		$merged      = self::normalize( $stored );

		$needs_migrate = $legacy || ( ! empty( $stored ) && ! $had_actions );

		if ( $needs_migrate && $form_id > 0 && ! empty( $stored ) ) {
			update_post_meta( $form_id, self::META_KEY, $merged );
		}

		return $merged;
	}

	/**
	 * Save form settings.
	 *
	 * @param int                  $form_id  Form ID.
	 * @param array<string, mixed> $settings Settings.
	 * @return array<string, mixed>
	 */
	public static function save( $form_id, array $settings ) {
		$form_id = absint( $form_id );
		$clean   = self::defaults();

		if ( isset( $settings['success_message'] ) ) {
			$clean['success_message'] = self::repair_stripped_newlines(
				sanitize_textarea_field( (string) $settings['success_message'] )
			);
		}

		if ( isset( $settings['actions'] ) ) {
			$clean['actions'] = Art_Forms_Form_Actions::sanitize_list( $settings['actions'] );
		}

		update_post_meta( $form_id, self::META_KEY, $clean );

		return $clean;
	}

	/**
	 * Restore newlines broken by stripslashes on JSON (\r\n → rn).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function repair_stripped_newlines( $text ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return $text;
		}

		if ( false !== strpos( $text, "\n" ) || false !== strpos( $text, "\r" ) ) {
			return $text;
		}

		if ( false === strpos( $text, 'rn' ) ) {
			return $text;
		}

		$repaired = str_replace( 'rnrn', "\n\n", $text );
		$repaired = preg_replace( '/rn(?=\{|[A-ZА-ЯЁ])/u', "\n", $repaired );

		return is_string( $repaired ) ? $repaired : $text;
	}
}
