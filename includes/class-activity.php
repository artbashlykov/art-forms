<?php
/**
 * CRM activity log for submissions.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Activity
 */
class Art_Forms_Activity {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'art_forms_activity';
	}

	/**
	 * Escaped table identifier.
	 *
	 * @return string
	 */
	private static function table_sql() {
		return '`' . esc_sql( self::table() ) . '`';
	}

	/**
	 * Log an activity event.
	 *
	 * @param int                  $submission_id Submission ID.
	 * @param string               $event_type    Event key.
	 * @param string               $summary       Human-readable summary.
	 * @param array<string, mixed> $meta          Optional meta.
	 * @param int                  $user_id       Author (0 = current).
	 * @return int|false
	 */
	public static function log( $submission_id, $event_type, $summary, array $meta = array(), $user_id = 0 ) {
		global $wpdb;

		$submission_id = absint( $submission_id );
		$event_type    = sanitize_key( (string) $event_type );
		$summary       = trim( wp_strip_all_tags( (string) $summary ) );
		$user_id       = $user_id > 0 ? absint( $user_id ) : get_current_user_id();

		if ( $submission_id <= 0 || '' === $event_type || '' === $summary ) {
			return false;
		}

		if ( strlen( $summary ) > 500 ) {
			$summary = substr( $summary, 0, 500 );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert(
			self::table(),
			array(
				'submission_id' => $submission_id,
				'user_id'       => $user_id,
				'event_type'    => $event_type,
				'summary'       => $summary,
				'meta'          => Art_Forms_Schema::encode_json( $meta ),
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return false === $ok ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Log stage change.
	 *
	 * @param int         $submission_id Submission ID.
	 * @param array|null  $from          Old stage row.
	 * @param array|null  $to            New stage row.
	 * @return int|false
	 */
	public static function log_stage_change( $submission_id, $from, $to ) {
		$from_title = is_array( $from ) && isset( $from['title'] ) ? (string) $from['title'] : '—';
		$to_title   = is_array( $to ) && isset( $to['title'] ) ? (string) $to['title'] : '—';
		$summary    = sprintf(
			/* translators: 1: old stage, 2: new stage */
			__( 'Этап: %1$s → %2$s', 'art-forms' ),
			$from_title,
			$to_title
		);

		return self::log(
			$submission_id,
			'stage_change',
			$summary,
			array(
				'from_id' => is_array( $from ) ? absint( $from['id'] ?? 0 ) : 0,
				'to_id'   => is_array( $to ) ? absint( $to['id'] ?? 0 ) : 0,
			)
		);
	}

	/**
	 * List activity for a submission (newest first).
	 *
	 * @param int $submission_id Submission ID.
	 * @param int $limit         Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_for_submission( $submission_id, $limit = 50 ) {
		global $wpdb;

		$submission_id = absint( $submission_id );
		$limit         = max( 1, min( 100, absint( $limit ) ) );
		$table_sql     = self::table_sql();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_sql} WHERE submission_id = %d ORDER BY created_at DESC, id DESC LIMIT %d",
				$submission_id,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = self::hydrate( $row );
		}

		return $items;
	}

	/**
	 * Delete activity for a submission.
	 *
	 * @param int $submission_id Submission ID.
	 * @return bool
	 */
	public static function delete_for_submission( $submission_id ) {
		global $wpdb;

		$submission_id = absint( $submission_id );
		if ( $submission_id <= 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->delete(
			self::table(),
			array( 'submission_id' => $submission_id ),
			array( '%d' )
		);
	}

	/**
	 * Hydrate row.
	 *
	 * @param array<string, mixed> $row DB row.
	 * @return array<string, mixed>
	 */
	private static function hydrate( array $row ) {
		$user_id = absint( $row['user_id'] ?? 0 );
		$name    = '';
		if ( $user_id > 0 ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$name = $user->display_name;
			}
		}
		if ( '' === $name ) {
			$name = __( 'Система', 'art-forms' );
		}

		$meta = array();
		if ( ! empty( $row['meta'] ) ) {
			$decoded = json_decode( (string) $row['meta'], true );
			if ( is_array( $decoded ) ) {
				$meta = $decoded;
			}
		}

		return array(
			'id'          => absint( $row['id'] ?? 0 ),
			'submission_id' => absint( $row['submission_id'] ?? 0 ),
			'user_id'     => $user_id,
			'author_name' => $name,
			'event_type'  => (string) ( $row['event_type'] ?? '' ),
			'summary'     => (string) ( $row['summary'] ?? '' ),
			'meta'        => $meta,
			'created_at'  => (string) ( $row['created_at'] ?? '' ),
		);
	}
}
