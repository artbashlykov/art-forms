<?php
/**
 * Delivery attempts log.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Delivery_Log
 */
class Art_Forms_Delivery_Log {

	/**
	 * Table name (unescaped).
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'art_forms_delivery_log';
	}

	/**
	 * Escaped table identifier for SQL.
	 *
	 * @return string
	 */
	private static function table_sql() {
		return '`' . esc_sql( self::table() ) . '`';
	}

	/**
	 * Insert log row.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return int|false
	 */
	public static function insert( array $data ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table; no WP API equivalent.
		$ok = $wpdb->insert(
			self::table(),
			array(
				'submission_id' => isset( $data['submission_id'] ) ? absint( $data['submission_id'] ) : 0,
				'form_id'       => isset( $data['form_id'] ) ? absint( $data['form_id'] ) : 0,
				'channel'       => isset( $data['channel'] ) ? sanitize_key( (string) $data['channel'] ) : 'email',
				'status'        => isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : 'failed',
				'message'       => isset( $data['message'] ) ? sanitize_textarea_field( (string) $data['message'] ) : '',
				'is_test'       => ! empty( $data['is_test'] ) ? 1 : 0,
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( false === $ok ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Query log entries.
	 *
	 * @param array<string, mixed> $args Args.
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$form_id       = isset( $args['form_id'] ) ? absint( $args['form_id'] ) : 0;
		$submission_id = isset( $args['submission_id'] ) ? absint( $args['submission_id'] ) : 0;
		$per_page      = isset( $args['per_page'] ) ? max( 1, absint( $args['per_page'] ) ) : 50;
		$page          = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$offset        = ( $page - 1 ) * $per_page;

		$table_sql = self::table_sql();
		$where     = array( '1=1' );
		$params    = array();

		if ( $form_id > 0 ) {
			$where[]  = 'form_id = %d';
			$params[] = $form_id;
		}

		if ( $submission_id > 0 ) {
			$where[]  = 'submission_id = %d';
			$params[] = $submission_id;
		}

		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; identifier via esc_sql(); WHERE placeholders built dynamically, values unpacked into prepare().
		if ( ! empty( $params ) ) {
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_sql} WHERE {$where_sql}",
					...$params
				)
			);
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_sql} WHERE {$where_sql}" );
		}

		$list_params = array_merge( $params, array( $per_page, $offset ) );
		$rows        = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_sql} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d",
				...$list_params
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		return array(
			'items' => $rows,
			'total' => $total,
		);
	}

	/**
	 * Human-readable delivery status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( $status ) {
		$status = sanitize_key( (string) $status );
		$labels = array(
			'sent'   => __( 'Отправлено', 'art-forms' ),
			'failed' => __( 'Ошибка', 'art-forms' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Render delivery status as a colored badge (HTML).
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function render_status_badge( $status ) {
		$status = sanitize_key( (string) $status );
		$class  = 'art-forms-status-badge art-forms-status--' . ( '' !== $status ? $status : 'unknown' );

		return sprintf(
			'<span class="%1$s">%2$s</span>',
			esc_attr( $class ),
			esc_html( self::status_label( $status ) )
		);
	}
}
