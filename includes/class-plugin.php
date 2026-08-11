<?php
/**
 * Main plugin bootstrap.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Plugin
 */
class Art_Forms_Plugin {

	/**
	 * Singleton.
	 *
	 * @var Art_Forms_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether admin was initialized.
	 *
	 * @var bool
	 */
	private static $admin_initialized = false;

	/**
	 * @return Art_Forms_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
	}

	/**
	 * Load class files.
	 */
	private function load_dependencies() {
		require_once ART_FORMS_PLUGIN_DIR . 'includes/class-post-types.php';
		require_once ART_FORMS_PLUGIN_DIR . 'includes/class-settings.php';
		require_once ART_FORMS_PLUGIN_DIR . 'includes/class-form-settings.php';
		require_once ART_FORMS_PLUGIN_DIR . 'includes/class-form-actions.php';
		require_once ART_FORMS_PLUGIN_DIR . 'includes/class-schema.php';
		require_once ART_FORMS_PLUGIN_DIR . 'includes/class-submissions.php';
		require_once ART_FORMS_PLUGIN_DIR . 'includes/class-delivery-log.php';
		require_once ART_FORMS_PLUGIN_DIR . 'includes/class-delivery.php';
		require_once ART_FORMS_PLUGIN_DIR . 'includes/class-delivery-email.php';
		require_once ART_FORMS_PLUGIN_DIR . 'includes/class-export.php';
		require_once ART_FORMS_PLUGIN_DIR . 'includes/class-code-checker.php';
		require_once ART_FORMS_PLUGIN_DIR . 'includes/class-csv-export.php';
		require_once ART_FORMS_PLUGIN_DIR . 'includes/class-rest.php';
		require_once ART_FORMS_PLUGIN_DIR . 'public/class-public.php';

		if ( is_admin() ) {
			require_once ART_FORMS_PLUGIN_DIR . 'admin/class-admin-menu.php';
			require_once ART_FORMS_PLUGIN_DIR . 'admin/class-admin-forms.php';
			require_once ART_FORMS_PLUGIN_DIR . 'admin/class-admin-submissions.php';
			require_once ART_FORMS_PLUGIN_DIR . 'admin/class-admin-settings.php';
			require_once ART_FORMS_PLUGIN_DIR . 'admin/class-admin-delivery-log.php';
		}
	}

	/**
	 * Boot plugin.
	 */
	public function run() {
		add_action( 'init', array( $this, 'init' ), 5 );
		$this->init_admin();
	}

	/**
	 * Front/init modules.
	 */
	public function init() {
		Art_Forms_Activator::ensure_tables();
		Art_Forms_Post_Types::register();
		Art_Forms_Rest::init();
		Art_Forms_Public::init();
		Art_Forms_Settings::schedule_cron();
		add_action( Art_Forms_Settings::CRON_HOOK, array( 'Art_Forms_Settings', 'cleanup_old_submissions' ) );
	}

	/**
	 * Admin modules.
	 */
	public function init_admin() {
		if ( self::$admin_initialized || ! is_admin() ) {
			return;
		}

		self::$admin_initialized = true;

		Art_Forms_Admin_Forms::init();
		Art_Forms_Admin_Submissions::init();
		Art_Forms_Admin_Settings::init();
		Art_Forms_Admin_Delivery_Log::init();
		Art_Forms_Admin_Menu::init();
	}
}
