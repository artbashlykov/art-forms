<?php
/**
 * Plugin deactivation.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Deactivator
 */
class Art_Forms_Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		require_once ART_FORMS_PLUGIN_DIR . 'includes/class-settings.php';
		Art_Forms_Settings::clear_cron();
		flush_rewrite_rules();
	}
}
