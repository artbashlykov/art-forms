<?php
/**
 * Plugin activation.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Activator
 */
class Art_Forms_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		require_once ART_FORMS_PLUGIN_DIR . 'includes/class-post-types.php';
		Art_Forms_Post_Types::register();

		self::create_tables();
		self::set_default_options();

		require_once ART_FORMS_PLUGIN_DIR . 'includes/class-settings.php';
		Art_Forms_Settings::schedule_cron();

		flush_rewrite_rules();
	}

	/**
	 * Ensure DB tables exist (safe to call repeatedly).
	 */
	public static function ensure_tables() {
		$installed = get_option( 'art_forms_db_version', '' );
		if ( ART_FORMS_DB_VERSION === $installed ) {
			return;
		}
		self::create_tables();
	}

	/**
	 * Create custom database tables.
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$submissions     = $wpdb->prefix . 'art_forms_submissions';
		$delivery_log    = $wpdb->prefix . 'art_forms_delivery_log';

		$sql_submissions = "CREATE TABLE {$submissions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			status varchar(20) NOT NULL DEFAULT 'new',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			ip varchar(45) NOT NULL DEFAULT '',
			contact_email varchar(190) NOT NULL DEFAULT '',
			contact_phone varchar(50) NOT NULL DEFAULT '',
			page_url text NULL,
			referrer text NULL,
			utm_source varchar(191) NOT NULL DEFAULT '',
			utm_medium varchar(191) NOT NULL DEFAULT '',
			utm_campaign varchar(191) NOT NULL DEFAULT '',
			utm_content varchar(191) NOT NULL DEFAULT '',
			utm_term varchar(191) NOT NULL DEFAULT '',
			payload longtext NULL,
			meta longtext NULL,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY created_at (created_at),
			KEY status (status),
			KEY contact_email (contact_email),
			KEY utm_campaign (utm_campaign)
		) {$charset_collate};";

		$sql_delivery = "CREATE TABLE {$delivery_log} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			submission_id bigint(20) unsigned NOT NULL DEFAULT 0,
			form_id bigint(20) unsigned NOT NULL DEFAULT 0,
			channel varchar(32) NOT NULL DEFAULT 'email',
			status varchar(20) NOT NULL DEFAULT 'failed',
			message text NULL,
			is_test tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY submission_id (submission_id),
			KEY form_id (form_id),
			KEY channel (channel),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_submissions );
		dbDelta( $sql_delivery );

		update_option( 'art_forms_db_version', ART_FORMS_DB_VERSION );
	}

	/**
	 * Seed default plugin options.
	 */
	private static function set_default_options() {
		if ( false === get_option( 'art_forms_settings', false ) ) {
			require_once ART_FORMS_PLUGIN_DIR . 'includes/class-settings.php';
			update_option( 'art_forms_settings', Art_Forms_Settings::defaults() );
		}
	}
}
