<?php
/**
 * CRM comments on submissions.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Comments
 */
class Art_Forms_Comments {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'art_forms_comments';
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
	 * List comments for a submission.
	 *
	 * @param int $submission_id Submission ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_for_submission( $submission_id ) {
		global $wpdb;

		$submission_id = absint( $submission_id );
		$table_sql     = self::table_sql();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_sql} WHERE submission_id = %d ORDER BY created_at ASC, id ASC",
				$submission_id
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
	 * Add comment.
	 *
	 * @param int    $submission_id Submission ID.
	 * @param string $body          Comment body.
	 * @param int    $user_id       Author user ID.
	 * @return int|false
	 */
	public static function add( $submission_id, $body, $user_id = 0 ) {
		global $wpdb;

		$submission_id = absint( $submission_id );
		$body          = trim( wp_strip_all_tags( (string) $body ) );
		$user_id       = $user_id > 0 ? absint( $user_id ) : get_current_user_id();

		if ( $submission_id <= 0 || '' === $body ) {
			return false;
		}

		if ( strlen( $body ) > 5000 ) {
			$body = substr( $body, 0, 5000 );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert(
			self::table(),
			array(
				'submission_id' => $submission_id,
				'user_id'       => $user_id,
				'body'          => $body,
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s' )
		);

		return false === $ok ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Delete comment.
	 *
	 * @param int $id Comment ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id = absint( $id );
		if ( $id <= 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Get one comment.
	 *
	 * @param int $id Comment ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$id        = absint( $id );
		$table_sql = self::table_sql();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_sql} WHERE id = %d",
				$id
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? self::hydrate( $row ) : null;
	}

	/**
	 * Delete all comments for a submission.
	 *
	 * @param int $submission_id Submission ID.
	 */
	public static function delete_for_submission( $submission_id ) {
		global $wpdb;

		$submission_id = absint( $submission_id );
		if ( $submission_id <= 0 ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( self::table(), array( 'submission_id' => $submission_id ), array( '%d' ) );
	}

	/**
	 * Hydrate row with author display name.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array<string, mixed>
	 */
	private static function hydrate( array $row ) {
		$row['id']            = absint( $row['id'] );
		$row['submission_id'] = absint( $row['submission_id'] );
		$row['user_id']       = absint( $row['user_id'] );
		$row['body']          = (string) $row['body'];
		$row['created_at']    = (string) $row['created_at'];

		$user = $row['user_id'] > 0 ? get_userdata( $row['user_id'] ) : false;
		$row['author_name'] = $user ? $user->display_name : __( 'Система', 'art-forms' );

		return $row;
	}
}
