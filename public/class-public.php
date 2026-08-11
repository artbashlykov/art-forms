<?php
/**
 * Public-facing bootstrap.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Public
 */
class Art_Forms_Public {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Enqueue frontend runtime.
	 */
	public static function enqueue() {
		wp_enqueue_script(
			'art-forms',
			ART_FORMS_PLUGIN_URL . 'assets/js/art-forms.js',
			array(),
			ART_FORMS_VERSION,
			true
		);

		wp_localize_script(
			'art-forms',
			'artForms',
			array(
				'restUrl' => esc_url_raw( rest_url( 'art-forms/v1/submit' ) ),
				'strings' => array(
					'sending' => __( 'Отправка…', 'art-forms' ),
					'error'   => __( 'Не удалось отправить форму. Попробуйте ещё раз.', 'art-forms' ),
				),
			)
		);
	}
}
