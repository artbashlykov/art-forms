<?php
/**
 * Submissions storage.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Submissions
 */
class Art_Forms_Submissions {

	/**
	 * Table name (unescaped).
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'art_forms_submissions';
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
	 * Insert submission.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return int|false Insert ID or false.
	 */
	public static function insert( array $data ) {
		global $wpdb;

		$row = array(
			'form_id'        => isset( $data['form_id'] ) ? absint( $data['form_id'] ) : 0,
			'created_at'     => isset( $data['created_at'] ) ? (string) $data['created_at'] : current_time( 'mysql', true ),
			'status'         => isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : 'new',
			'user_id'        => isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0,
			'ip'             => isset( $data['ip'] ) ? sanitize_text_field( (string) $data['ip'] ) : '',
			'contact_email'  => isset( $data['contact_email'] ) ? sanitize_email( (string) $data['contact_email'] ) : '',
			'contact_phone'  => isset( $data['contact_phone'] ) ? sanitize_text_field( (string) $data['contact_phone'] ) : '',
			'page_url'       => isset( $data['page_url'] ) ? esc_url_raw( (string) $data['page_url'] ) : '',
			'referrer'       => isset( $data['referrer'] ) ? esc_url_raw( (string) $data['referrer'] ) : '',
			'utm_source'     => isset( $data['utm_source'] ) ? sanitize_text_field( (string) $data['utm_source'] ) : '',
			'utm_medium'     => isset( $data['utm_medium'] ) ? sanitize_text_field( (string) $data['utm_medium'] ) : '',
			'utm_campaign'   => isset( $data['utm_campaign'] ) ? sanitize_text_field( (string) $data['utm_campaign'] ) : '',
			'utm_content'    => isset( $data['utm_content'] ) ? sanitize_text_field( (string) $data['utm_content'] ) : '',
			'utm_term'       => isset( $data['utm_term'] ) ? sanitize_text_field( (string) $data['utm_term'] ) : '',
			'payload'        => isset( $data['payload'] ) ? Art_Forms_Schema::encode_json( $data['payload'] ) : '{}',
			'meta'           => isset( $data['meta'] ) ? Art_Forms_Schema::encode_json( $data['meta'] ) : '{}',
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table; no WP API equivalent.
		$ok = $wpdb->insert(
			self::table(),
			$row,
			array(
				'%d',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		if ( false === $ok ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get one submission.
	 *
	 * @param int $id Submission ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$id        = absint( $id );
		$table_sql = self::table_sql();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; identifier via esc_sql(); id via prepare().
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_sql} WHERE id = %d",
				$id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( ! is_array( $row ) ) {
			return null;
		}

		return self::hydrate( $row );
	}

	/**
	 * Query submissions.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$form_id   = isset( $args['form_id'] ) ? absint( $args['form_id'] ) : 0;
		$status    = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
		$search    = isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '';
		$date_from = isset( $args['date_from'] ) ? sanitize_text_field( (string) $args['date_from'] ) : '';
		$date_to   = isset( $args['date_to'] ) ? sanitize_text_field( (string) $args['date_to'] ) : '';
		$per_page  = isset( $args['per_page'] ) ? max( 1, absint( $args['per_page'] ) ) : 20;
		$page      = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$offset    = ( $page - 1 ) * $per_page;

		$orderby_key = isset( $args['orderby'] ) ? sanitize_key( (string) $args['orderby'] ) : 'id';
		$order       = isset( $args['order'] ) ? strtoupper( sanitize_text_field( (string) $args['order'] ) ) : 'DESC';
		if ( 'ASC' !== $order && 'DESC' !== $order ) {
			$order = 'DESC';
		}

		$orderby_map = array(
			'id'     => 's.id',
			'form'   => 'form_title',
			'date'   => 's.created_at',
			'email'  => 's.contact_email',
			'phone'  => 's.contact_phone',
			'status' => 's.status',
		);
		if ( ! isset( $orderby_map[ $orderby_key ] ) ) {
			$orderby_key = 'id';
		}
		$orderby_sql = $orderby_map[ $orderby_key ];

		$where  = array( '1=1' );
		$params = array();

		if ( $form_id > 0 ) {
			$where[]  = 's.form_id = %d';
			$params[] = $form_id;
		}

		if ( '' !== $status ) {
			$where[]  = 's.status = %s';
			$params[] = $status;
		}

		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(s.contact_email LIKE %s OR s.contact_phone LIKE %s OR s.payload LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( '' !== $date_from ) {
			$where[]  = 's.created_at >= %s';
			$params[] = $date_from . ' 00:00:00';
		}

		if ( '' !== $date_to ) {
			$where[]  = 's.created_at <= %s';
			$params[] = $date_to . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$table_sql = self::table_sql();
		$posts     = $wpdb->posts;
		$from_sql  = "{$table_sql} s LEFT JOIN {$posts} p ON p.ID = s.form_id";

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; identifier via esc_sql(); filters/order via whitelist; WHERE placeholders built dynamically, values unpacked into prepare().
		if ( ! empty( $params ) ) {
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$from_sql} WHERE {$where_sql}",
					...$params
				)
			);
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$from_sql} WHERE {$where_sql}" );
		}

		$list_params = array_merge( $params, array( $per_page, $offset ) );
		$rows        = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, p.post_title AS form_title FROM {$from_sql} WHERE {$where_sql} ORDER BY {$orderby_sql} {$order}, s.id DESC LIMIT %d OFFSET %d",
				...$list_params
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = self::hydrate( $row );
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Format MySQL datetime for admin display: dd.mm.yyyy HH:MM.
	 *
	 * @param string $mysql_datetime Datetime string.
	 * @return string
	 */
	public static function format_datetime( $mysql_datetime ) {
		$mysql_datetime = trim( (string) $mysql_datetime );
		if ( '' === $mysql_datetime ) {
			return '—';
		}

		$dt = date_create( $mysql_datetime );
		if ( ! $dt ) {
			return $mysql_datetime;
		}

		return $dt->format( 'd.m.Y H:i' );
	}

	/**
	 * Update status.
	 *
	 * @param int    $id     Submission ID.
	 * @param string $status Status.
	 * @return bool
	 */
	public static function update_status( $id, $status ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no WP API equivalent.
		return false !== $wpdb->update(
			self::table(),
			array( 'status' => sanitize_key( $status ) ),
			array( 'id' => absint( $id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Human-readable submission status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( $status ) {
		$status = sanitize_key( (string) $status );
		$labels = array(
			'new'       => __( 'Новый', 'art-forms' ),
			'processed' => __( 'Обработан', 'art-forms' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Render submission status as a colored badge (HTML).
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

	/**
	 * Delete submission and related delivery log rows.
	 *
	 * @param int $id Submission ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id = absint( $id );
		if ( $id <= 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no WP API equivalent.
		$deleted = $wpdb->delete(
			self::table(),
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( false === $deleted ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no WP API equivalent.
		$wpdb->delete(
			Art_Forms_Delivery_Log::table(),
			array( 'submission_id' => $id ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Replace stored payload JSON.
	 *
	 * @param int                  $id      Submission ID.
	 * @param array<string, mixed> $payload Payload.
	 * @return bool
	 */
	public static function update_payload( $id, array $payload ) {
		global $wpdb;

		$id = absint( $id );
		if ( $id <= 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no WP API equivalent.
		$updated = $wpdb->update(
			self::table(),
			array( 'payload' => Art_Forms_Schema::encode_json( $payload ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Delete submissions older than N days (and their delivery logs).
	 *
	 * @param int $days Days.
	 * @return int Number of deleted submissions.
	 */
	public static function delete_older_than( $days ) {
		global $wpdb;

		$days = absint( $days );
		if ( $days <= 0 ) {
			return 0;
		}

		$cutoff    = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$table_sql = self::table_sql();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; identifier via esc_sql(); cutoff via prepare().
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table_sql} WHERE created_at < %s LIMIT 500",
				$cutoff
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return 0;
		}

		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( self::delete( (int) $id ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * Hydrate DB row.
	 *
	 * @param array<string, mixed> $row DB row.
	 * @return array<string, mixed>
	 */
	private static function hydrate( array $row ) {
		$payload = array();
		if ( ! empty( $row['payload'] ) ) {
			$decoded = json_decode( (string) $row['payload'], true );
			if ( is_array( $decoded ) ) {
				$payload = $decoded;
			}
		}

		$meta = array();
		if ( ! empty( $row['meta'] ) ) {
			$decoded = json_decode( (string) $row['meta'], true );
			if ( is_array( $decoded ) ) {
				$meta = $decoded;
			}
		}

		$row['id']             = absint( $row['id'] );
		$row['form_id']        = absint( $row['form_id'] );
		$row['user_id']        = absint( $row['user_id'] );
		$row['payload']        = $payload;
		$row['meta']           = $meta;
		$row['payload_raw']    = isset( $row['payload'] ) ? $row['payload'] : array();

		return $row;
	}

	/**
	 * Build delivery context for channels/hooks.
	 *
	 * @param int                       $submission_id Submission ID.
	 * @param array<string, mixed>|null $submission    Optional preloaded row.
	 * @return array<string, mixed>|null
	 */
	public static function build_context( $submission_id, $submission = null ) {
		if ( null === $submission ) {
			$submission = self::get( $submission_id );
		}

		if ( ! is_array( $submission ) ) {
			return null;
		}

		$form_id    = absint( $submission['form_id'] );
		$schema     = Art_Forms_Schema::get( $form_id );
		$fields_map = Art_Forms_Schema::fields_map( $schema );

		$payload = is_array( $submission['payload'] ) ? $submission['payload'] : array();
		$labeled = array();
		foreach ( $payload as $key => $value ) {
			$field             = isset( $fields_map[ $key ] ) ? $fields_map[ $key ] : array( 'key' => $key, 'label' => $key, 'type' => 'text' );
			$label             = isset( $field['label'] ) && '' !== (string) $field['label'] ? (string) $field['label'] : (string) $key;
			$labeled[ $label ] = Art_Forms_Schema::format_display_value( $field, $value );
		}

		return array(
			'submission_id'  => absint( $submission['id'] ),
			'form_id'        => $form_id,
			'form_title'     => get_the_title( $form_id ),
			'created_at'     => $submission['created_at'],
			'status'         => $submission['status'],
			'user_id'        => absint( $submission['user_id'] ),
			'ip'             => $submission['ip'],
			'contact_email'  => $submission['contact_email'],
			'contact_phone'  => $submission['contact_phone'],
			'page_url'       => Art_Forms_Schema::format_display_url( (string) $submission['page_url'] ),
			'referrer'       => Art_Forms_Schema::format_display_url( (string) $submission['referrer'] ),
			'utm_source'     => $submission['utm_source'],
			'utm_medium'     => $submission['utm_medium'],
			'utm_campaign'   => $submission['utm_campaign'],
			'utm_content'    => $submission['utm_content'],
			'utm_term'       => $submission['utm_term'],
			'payload'        => $payload,
			'labeled_fields' => $labeled,
			'schema'         => $schema,
			'meta'           => is_array( $submission['meta'] ) ? $submission['meta'] : array(),
		);
	}
}
