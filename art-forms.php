<?php
/**
 * Plugin Name:       ART Forms
 * Description:       Создание логики форм (квизы, опросы), экспорт кода для дизайна, приём и сохранение ответов, отправка на email.
 * Version:           1.1.30
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Арт Башлыков
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       art-forms
 * Domain Path:       /languages
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

define( 'ART_FORMS_VERSION', '1.1.30' );
define( 'ART_FORMS_ADMIN_MENU_SLUG', 'art-forms' );
define( 'ART_FORMS_AUTHOR_URL', 'https://forge.artbashlykov.ru' );
define( 'ART_FORMS_PLUGIN_FILE', __FILE__ );
define( 'ART_FORMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ART_FORMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ART_FORMS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'ART_FORMS_DB_VERSION', '1.3.0' );
require_once ART_FORMS_PLUGIN_DIR . 'includes/class-activator.php';
require_once ART_FORMS_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once ART_FORMS_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( ART_FORMS_PLUGIN_FILE, array( 'Art_Forms_Activator', 'activate' ) );
register_deactivation_hook( ART_FORMS_PLUGIN_FILE, array( 'Art_Forms_Deactivator', 'deactivate' ) );

/**
 * Returns the main plugin instance.
 *
 * @return Art_Forms_Plugin
 */
function art_forms() {
	return Art_Forms_Plugin::instance();
}

art_forms()->run();
