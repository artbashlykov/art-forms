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
		require_once ART_FORMS_PLUGIN_DIR . 'includes/class-capabilities.php';
		Art_Forms_Capabilities::register();
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
		$stages          = $wpdb->prefix . 'art_forms_stages';
		$comments        = $wpdb->prefix . 'art_forms_comments';
		$activity        = $wpdb->prefix . 'art_forms_activity';

		$sql_submissions = "CREATE TABLE {$submissions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			status varchar(20) NOT NULL DEFAULT 'new',
			stage_id bigint(20) unsigned NOT NULL DEFAULT 0,
			is_starred tinyint(1) NOT NULL DEFAULT 0,
			priority tinyint(1) unsigned NOT NULL DEFAULT 0,
			tags longtext NULL,
			board_order int(11) NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			ip varchar(45) NOT NULL DEFAULT '',
			contact_email varchar(190) NOT NULL DEFAULT '',
			contact_phone varchar(50) NOT NULL DEFAULT '',
			contact_name varchar(190) NOT NULL DEFAULT '',
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
			KEY stage_id (stage_id),
			KEY is_starred (is_starred),
			KEY priority (priority),
			KEY board_order (form_id, stage_id, board_order),
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
		$sql_stages = "CREATE TABLE {$stages} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL DEFAULT 0,
			slug varchar(64) NOT NULL DEFAULT '',
			title varchar(191) NOT NULL DEFAULT '',
			color varchar(20) NOT NULL DEFAULT '#2271b1',
			position int(11) NOT NULL DEFAULT 0,
			is_default tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY form_position (form_id, position)
		) {$charset_collate};";

		$sql_comments = "CREATE TABLE {$comments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			submission_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			body text NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY submission_id (submission_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_activity = "CREATE TABLE {$activity} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			submission_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			event_type varchar(32) NOT NULL DEFAULT '',
			summary text NULL,
			meta longtext NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY submission_id (submission_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_submissions );
		dbDelta( $sql_delivery );
		dbDelta( $sql_stages );
		dbDelta( $sql_comments );
		dbDelta( $sql_activity );

		self::backfill_stages();
		self::backfill_board_order();

		update_option( 'art_forms_db_version', ART_FORMS_DB_VERSION );
	}

	/**
	 * Assign board_order for legacy rows still at 0.
	 */
	private static function backfill_board_order() {
		global $wpdb;

		$submissions = $wpdb->prefix . 'art_forms_submissions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col = $wpdb->get_results( "SHOW COLUMNS FROM `{$submissions}` LIKE 'board_order'" );
		if ( empty( $col ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$groups = $wpdb->get_results(
			"SELECT form_id, stage_id FROM `{$submissions}` GROUP BY form_id, stage_id HAVING MAX(board_order) = 0 AND COUNT(*) > 0",
			ARRAY_A
		);
		if ( ! is_array( $groups ) ) {
			return;
		}

		foreach ( $groups as $group ) {
			$form_id  = absint( $group['form_id'] );
			$stage_id = absint( $group['stage_id'] );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM `{$submissions}` WHERE form_id = %d AND stage_id = %d ORDER BY id ASC",
					$form_id,
					$stage_id
				)
			);
			if ( ! is_array( $ids ) ) {
				continue;
			}
			$pos = 10;
			foreach ( $ids as $sid ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$submissions,
					array( 'board_order' => $pos ),
					array( 'id' => absint( $sid ) ),
					array( '%d' ),
					array( '%d' )
				);
				$pos += 10;
			}
		}
	}

	/**
	 * Ensure stages exist and assign stage_id to legacy rows.
	 */
	private static function backfill_stages() {
		global $wpdb;

		if ( ! class_exists( 'Art_Forms_Stages' ) ) {
			require_once ART_FORMS_PLUGIN_DIR . 'includes/class-stages.php';
		}
		if ( ! class_exists( 'Art_Forms_Submissions' ) ) {
			require_once ART_FORMS_PLUGIN_DIR . 'includes/class-schema.php';
			require_once ART_FORMS_PLUGIN_DIR . 'includes/class-submissions.php';
		}

		$submissions     = $wpdb->prefix . 'art_forms_submissions';
		$submissions_sql = '`' . esc_sql( $submissions ) . '`';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$form_ids = $wpdb->get_col( "SELECT DISTINCT form_id FROM {$submissions_sql} WHERE form_id > 0" );
		if ( ! is_array( $form_ids ) ) {
			$form_ids = array();
		}

		if ( ! class_exists( 'Art_Forms_Post_Types' ) ) {
			require_once ART_FORMS_PLUGIN_DIR . 'includes/class-post-types.php';
		}

		$posts = get_posts(
			array(
				'post_type'      => Art_Forms_Post_Types::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'trash' ),
				'posts_per_page' => 500,
				'fields'         => 'ids',
			)
		);
		foreach ( $posts as $pid ) {
			$form_ids[] = (int) $pid;
		}
		$form_ids = array_unique( array_map( 'absint', $form_ids ) );

		foreach ( $form_ids as $form_id ) {
			if ( $form_id <= 0 ) {
				continue;
			}
			$default = Art_Forms_Stages::get_default( $form_id );
			if ( ! $default ) {
				continue;
			}
			$default_id = (int) $default['id'];

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$submissions_sql} SET stage_id = %d WHERE form_id = %d AND (stage_id = 0 OR stage_id IS NULL)",
					$default_id,
					$form_id
				)
			);
		}
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
