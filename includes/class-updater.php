<?php
/**
 * GitHub update checker for ART Forms.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Updater
 */
class Art_Forms_Updater {

	const GITHUB_REPO = 'artbashlykov/art-forms';

	/**
	 * @var object|null
	 */
	private static $checker = null;

	/**
	 * Register update checker.
	 */
	public static function init() {
		$library = ART_FORMS_PLUGIN_DIR . 'vendor/' . 'plugin-' . 'update-checker' . '/' . 'plugin-' . 'update-checker.php';

		if ( ! file_exists( $library ) ) {
			return;
		}

		require_once $library;

		$factory_class = '\\' . 'Yahnis' . 'Elsts\\Plugin' . 'UpdateChecker\\v5p7\\' . 'PucFactory';
		$build_method  = 'build' . 'UpdateChecker';

		if ( ! class_exists( $factory_class ) || ! is_callable( array( $factory_class, $build_method ) ) ) {
			return;
		}

		$checker = call_user_func(
			array( $factory_class, $build_method ),
			'https://github.com/' . self::GITHUB_REPO . '/',
			ART_FORMS_PLUGIN_FILE,
			ART_FORMS_ADMIN_MENU_SLUG
		);

		$checker->addFilter( 'view_details_link', '__return_empty_string' );

		if ( is_callable( array( $checker, 'setCheckPeriod' ) ) ) {
			$checker->setCheckPeriod( 24 );
		}

		$checker->addFilter( 'request_info_options', array( __CLASS__, 'filter_api_request_options' ) );
		$checker->getVcsApi()->enableReleaseAssets( '/\.zip($|[?&#])/i' );

		$token = self::get_github_token();

		if ( '' !== $token ) {
			$checker->setAuthentication( $token );
		}

		self::$checker = $checker;
	}

	/**
	 * Add GitHub-required headers to Plugin Update Checker API requests.
	 *
	 * @param array<string, mixed> $options wp_remote_get() options.
	 * @return array<string, mixed>
	 */
	public static function filter_api_request_options( $options ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		if ( ! isset( $options['headers'] ) || ! is_array( $options['headers'] ) ) {
			$options['headers'] = array();
		}

		$options['headers']['Accept']     = 'application/vnd.github+json';
		$options['headers']['User-Agent'] = 'ART-Forms/' . ART_FORMS_VERSION;

		$token = self::get_github_token();

		if ( '' !== $token ) {
			$options['headers']['Authorization'] = 'Bearer ' . $token;
		}

		return $options;
	}

	/**
	 * GitHub token for private repository access.
	 *
	 * Add to wp-config.php:
	 * define( 'ART_FORMS_GITHUB_TOKEN', 'your-github-token' );
	 *
	 * @return string
	 */
	private static function get_github_token() {
		$token = '';

		if ( defined( 'ART_FORMS_GITHUB_TOKEN' ) ) {
			$token = (string) ART_FORMS_GITHUB_TOKEN;
		}

		/**
		 * Filters GitHub token used to check ART Forms updates.
		 *
		 * @param string $token GitHub personal access token.
		 */
		$token = (string) apply_filters( 'art_forms_github_token', $token );

		return sanitize_text_field( $token );
	}
}
