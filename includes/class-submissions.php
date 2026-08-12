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
	 * Priority levels: 0 normal … 3 high.
	 *
	 * @return array<int, string>
	 */
	public static function priority_labels() {
		return array(
			0 => __( 'Обычный', 'art-forms' ),
			1 => __( 'Низкий', 'art-forms' ),
			2 => __( 'Средний', 'art-forms' ),
			3 => __( 'Высокий', 'art-forms' ),
		);
	}

	/**
	 * @param mixed $value Raw.
	 * @return int
	 */
	public static function sanitize_priority( $value ) {
		$p = absint( $value );
		return ( $p > 3 ) ? 0 : $p;
	}

	/**
	 * @param mixed $tags Tags list or CSV string.
	 * @return array<int, string>
	 */
	public static function sanitize_tags( $tags ) {
		if ( is_string( $tags ) ) {
			$tags = preg_split( '/[,;]+/', $tags );
		}
		if ( ! is_array( $tags ) ) {
			return array();
		}
		$out = array();
		foreach ( $tags as $t ) {
			$t = sanitize_text_field( (string) $t );
			$t = trim( $t );
			if ( '' === $t ) {
				continue;
			}
			if ( function_exists( 'mb_substr' ) ) {
				$t = mb_substr( $t, 0, 40 );
			} else {
				$t = substr( $t, 0, 40 );
			}
			$out[] = $t;
			if ( count( $out ) >= 12 ) {
				break;
			}
		}
		return array_values( array_unique( $out ) );
	}

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

		$form_id  = isset( $data['form_id'] ) ? absint( $data['form_id'] ) : 0;
		$stage_id = isset( $data['stage_id'] ) ? absint( $data['stage_id'] ) : 0;
		if ( $stage_id <= 0 && $form_id > 0 && class_exists( 'Art_Forms_Stages' ) ) {
			$default = Art_Forms_Stages::get_default( $form_id );
			if ( $default ) {
				$stage_id = (int) $default['id'];
			}
		}

		$board_order = isset( $data['board_order'] ) ? (int) $data['board_order'] : 0;
		if ( ! isset( $data['board_order'] ) && $form_id > 0 && $stage_id > 0 ) {
			$board_order = self::next_board_order( $form_id, $stage_id );
		}

		$row = array(
			'form_id'        => $form_id,
			'created_at'     => isset( $data['created_at'] ) ? (string) $data['created_at'] : current_time( 'mysql', true ),
			'status'         => isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : 'new',
			'stage_id'       => $stage_id,
			'is_starred'     => ! empty( $data['is_starred'] ) ? 1 : 0,
			'priority'       => self::sanitize_priority( isset( $data['priority'] ) ? $data['priority'] : 0 ),
			'tags'           => Art_Forms_Schema::encode_json( self::sanitize_tags( isset( $data['tags'] ) ? $data['tags'] : array() ) ),
			'board_order'    => $board_order,
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
				'%d', // form_id
				'%s', // created_at
				'%s', // status
				'%d', // stage_id
				'%d', // is_starred
				'%d', // priority
				'%s', // tags
				'%d', // board_order
				'%d', // user_id
				'%s', // ip
				'%s', // contact_email
				'%s', // contact_phone
				'%s', // page_url
				'%s', // referrer
				'%s', // utm_source
				'%s', // utm_medium
				'%s', // utm_campaign
				'%s', // utm_content
				'%s', // utm_term
				'%s', // payload
				'%s', // meta
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
		$stage_id  = isset( $args['stage_id'] ) ? absint( $args['stage_id'] ) : 0;
		$starred   = isset( $args['is_starred'] ) ? (int) $args['is_starred'] : -1;
		$priority  = isset( $args['priority'] ) ? (int) $args['priority'] : -1;
		$tag       = isset( $args['tag'] ) ? sanitize_text_field( (string) $args['tag'] ) : '';
		$search    = isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '';
		$date_from = isset( $args['date_from'] ) ? sanitize_text_field( (string) $args['date_from'] ) : '';
		$date_to   = isset( $args['date_to'] ) ? sanitize_text_field( (string) $args['date_to'] ) : '';
		$per_page  = isset( $args['per_page'] ) ? max( 1, absint( $args['per_page'] ) ) : 20;
		$page      = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$offset    = ( $page - 1 ) * $per_page;
		$ids_only  = ! empty( $args['ids_only'] );

		$exclude_stage_ids = array();
		if ( ! empty( $args['exclude_stage_ids'] ) && is_array( $args['exclude_stage_ids'] ) ) {
			foreach ( $args['exclude_stage_ids'] as $ex_id ) {
				$ex_id = absint( $ex_id );
				if ( $ex_id > 0 ) {
					$exclude_stage_ids[] = $ex_id;
				}
			}
			$exclude_stage_ids = array_values( array_unique( $exclude_stage_ids ) );
		}

		$orderby_key = isset( $args['orderby'] ) ? (string) $args['orderby'] : 'id';
		$orderby_key = sanitize_key( $orderby_key );
		// Allow field_* for payload JSON keys (sanitize_key keeps underscores).
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
			'stage'  => 's.stage_id',
			'star'        => 's.is_starred',
			'priority'    => 's.priority',
			'board_order' => 's.board_order',
		);

		$orderby_sql = null;
		if ( isset( $orderby_map[ $orderby_key ] ) ) {
			$orderby_sql = $orderby_map[ $orderby_key ];
		} elseif ( 0 === strpos( $orderby_key, 'field_' ) ) {
			$field_key = substr( $orderby_key, 6 );
			$field_key = sanitize_key( $field_key );
			if ( '' !== $field_key && preg_match( '/^[a-z0-9_]+$/', $field_key ) ) {
				// Sort by payload JSON value (MySQL 5.7+ / MariaDB 10.2+).
				$json_path   = '$.' . $field_key;
				$orderby_sql = "JSON_UNQUOTE(JSON_EXTRACT(s.payload, '" . esc_sql( $json_path ) . "'))";
			}
		}

		if ( null === $orderby_sql ) {
			$orderby_key = 'id';
			$orderby_sql = $orderby_map['id'];
		}

		$tiebreak = ( 'board_order' === $orderby_key ) ? 's.id ASC' : 's.id DESC';

		$where  = array( '1=1' );
		$params = array();

		if ( $form_id > 0 ) {
			$where[]  = 's.form_id = %d';
			$params[] = $form_id;
		}

		if ( $stage_id > 0 ) {
			$where[]  = 's.stage_id = %d';
			$params[] = $stage_id;
		} elseif ( ! empty( $exclude_stage_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $exclude_stage_ids ), '%d' ) );
			$where[]      = "s.stage_id NOT IN ({$placeholders})";
			foreach ( $exclude_stage_ids as $ex_id ) {
				$params[] = $ex_id;
			}
		}

		if ( 0 === $starred || 1 === $starred ) {
			$where[]  = 's.is_starred = %d';
			$params[] = $starred;
		}

		if ( $priority >= 0 && $priority <= 3 ) {
			$where[]  = 's.priority = %d';
			$params[] = $priority;
		}

		if ( '' !== $tag ) {
			$where[]  = 's.tags LIKE %s';
			$params[] = '%' . $wpdb->esc_like( '"' . $tag . '"' ) . '%';
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

		if ( $ids_only ) {
			$list_params = array_merge( $params, array( $per_page, $offset ) );
			$ids         = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT s.id FROM {$from_sql} WHERE {$where_sql} ORDER BY {$orderby_sql} {$order}, {$tiebreak} LIMIT %d OFFSET %d",
					...$list_params
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

			return array(
				'items' => is_array( $ids ) ? array_map( 'absint', $ids ) : array(),
				'total' => $total,
			);
		}

		$list_params = array_merge( $params, array( $per_page, $offset ) );
		$rows        = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, p.post_title AS form_title FROM {$from_sql} WHERE {$where_sql} ORDER BY {$orderby_sql} {$order}, {$tiebreak} LIMIT %d OFFSET %d",
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
	 * Count submissions per form.
	 *
	 * @return array<int, int> form_id => count
	 */
	public static function counts_by_form() {
		global $wpdb;

		$table_sql = self::table_sql();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			"SELECT form_id, COUNT(*) AS cnt FROM {$table_sql} GROUP BY form_id",
			ARRAY_A
		);
		// phpcs:enable

		$out = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[ absint( $row['form_id'] ) ] = absint( $row['cnt'] );
			}
		}

		return $out;
	}

	/**
	 * Update CRM stage.
	 *
	 * @param int $id       Submission ID.
	 * @param int $stage_id Stage ID.
	 * @return bool
	 */
	public static function update_stage( $id, $stage_id ) {
		global $wpdb;

		$id       = absint( $id );
		$stage_id = absint( $stage_id );
		if ( $id <= 0 || $stage_id <= 0 ) {
			return false;
		}

		$submission = self::get( $id );
		if ( ! $submission ) {
			return false;
		}

		$old_stage_id = (int) $submission['stage_id'];
		$data         = array( 'stage_id' => $stage_id );
		$format       = array( '%d' );

		if ( $old_stage_id !== $stage_id ) {
			$data['board_order'] = self::next_board_order( (int) $submission['form_id'], $stage_id );
			$format[]            = '%d';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = false !== $wpdb->update(
			self::table(),
			$data,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);

		if ( $ok && $old_stage_id !== $stage_id && class_exists( 'Art_Forms_Activity' ) && class_exists( 'Art_Forms_Stages' ) ) {
			Art_Forms_Activity::log_stage_change(
				$id,
				Art_Forms_Stages::get( $old_stage_id ),
				Art_Forms_Stages::get( $stage_id )
			);
		}

		return $ok;
	}

	/**
	 * Persist kanban card order inside a stage (and optionally move to that stage).
	 *
	 * @param int   $form_id  Form ID.
	 * @param int   $stage_id Stage ID.
	 * @param int[] $ids      Ordered submission IDs (top → bottom).
	 * @return bool
	 */
	public static function reorder_board( $form_id, $stage_id, $ids ) {
		global $wpdb;

		$form_id  = absint( $form_id );
		$stage_id = absint( $stage_id );
		$ids      = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );

		if ( $form_id <= 0 || $stage_id <= 0 || empty( $ids ) ) {
			return false;
		}

		$table     = self::table();
		$ok        = true;
		$pos       = 10;
		$new_stage = class_exists( 'Art_Forms_Stages' ) ? Art_Forms_Stages::get( $stage_id ) : null;

		foreach ( $ids as $id ) {
			$before = self::get( $id );
			$old_id = $before ? (int) $before['stage_id'] : 0;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$updated = $wpdb->update(
				$table,
				array(
					'stage_id'    => $stage_id,
					'board_order' => $pos,
				),
				array(
					'id'      => $id,
					'form_id' => $form_id,
				),
				array( '%d', '%d' ),
				array( '%d', '%d' )
			);
			if ( false === $updated ) {
				$ok = false;
			} elseif ( $old_id !== $stage_id && class_exists( 'Art_Forms_Activity' ) ) {
				Art_Forms_Activity::log_stage_change(
					$id,
					$old_id > 0 ? Art_Forms_Stages::get( $old_id ) : null,
					$new_stage
				);
			}
			$pos += 10;
		}

		return $ok;
	}

	/**
	 * Next board_order value for a stage (append to bottom).
	 *
	 * @param int $form_id  Form ID.
	 * @param int $stage_id Stage ID.
	 * @return int
	 */
	public static function next_board_order( $form_id, $stage_id ) {
		global $wpdb;

		$form_id  = absint( $form_id );
		$stage_id = absint( $stage_id );
		$table    = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$max = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(board_order) FROM {$table} WHERE form_id = %d AND stage_id = %d",
				$form_id,
				$stage_id
			)
		);

		return $max + 10;
	}

	/**
	 * Toggle or set starred flag.
	 *
	 * @param int      $id    Submission ID.
	 * @param int|null $value 1/0 or null to toggle.
	 * @return bool|int New value or false.
	 */
	public static function set_starred( $id, $value = null ) {
		global $wpdb;

		$id = absint( $id );
		if ( $id <= 0 ) {
			return false;
		}

		if ( null === $value ) {
			$current = self::get( $id );
			if ( ! $current ) {
				return false;
			}
			$value = empty( $current['is_starred'] ) ? 1 : 0;
		} else {
			$value = $value ? 1 : 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = $wpdb->update(
			self::table(),
			array( 'is_starred' => $value ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( false === $ok ) {
			return false;
		}

		if ( class_exists( 'Art_Forms_Activity' ) ) {
			Art_Forms_Activity::log(
				$id,
				'star',
				$value
					? __( 'Добавлено в избранное', 'art-forms' )
					: __( 'Убрано из избранного', 'art-forms' ),
				array( 'is_starred' => $value )
			);
		}

		return $value;
	}

	/**
	 * Reassign all submissions from one stage to another.
	 *
	 * @param int $from_stage From stage.
	 * @param int $to_stage   To stage.
	 * @return int Rows affected.
	 */
	public static function reassign_stage( $from_stage, $to_stage ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			self::table(),
			array( 'stage_id' => absint( $to_stage ) ),
			array( 'stage_id' => absint( $from_stage ) ),
			array( '%d' ),
			array( '%d' )
		);

		return false === $updated ? 0 : (int) $updated;
	}

	/**
	 * Bulk update stage.
	 *
	 * @param array<int> $ids      IDs.
	 * @param int        $stage_id Stage ID.
	 * @return int
	 */
	public static function bulk_update_stage( array $ids, $stage_id ) {
		$stage_id = absint( $stage_id );
		$count    = 0;
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 && self::update_stage( $id, $stage_id ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Bulk delete.
	 *
	 * @param array<int> $ids IDs.
	 * @return int
	 */
	public static function bulk_delete( array $ids ) {
		$count = 0;
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 && self::delete( $id ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Related submissions for same contact (email or phone).
	 *
	 * @param array<string, mixed> $submission Current submission.
	 * @param int                  $limit      Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public static function related_by_contact( array $submission, $limit = 20 ) {
		global $wpdb;

		$email = isset( $submission['contact_email'] ) ? sanitize_email( (string) $submission['contact_email'] ) : '';
		$phone = isset( $submission['contact_phone'] ) ? sanitize_text_field( (string) $submission['contact_phone'] ) : '';
		$id    = absint( $submission['id'] );
		$limit = max( 1, absint( $limit ) );

		if ( '' === $email && '' === $phone ) {
			return array();
		}

		$table_sql = self::table_sql();
		$where     = array( 'id != %d' );
		$params    = array( $id );

		$or = array();
		if ( '' !== $email ) {
			$or[]     = 'contact_email = %s';
			$params[] = $email;
		}
		if ( '' !== $phone ) {
			$or[]     = 'contact_phone = %s';
			$params[] = $phone;
		}
		$where[] = '(' . implode( ' OR ', $or ) . ')';
		$params[] = $limit;

		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_sql} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d",
				...$params
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( __CLASS__, 'hydrate' ), $rows );
	}

	/**
	 * Aggregate contacts for a form (by email, fallback phone).
	 *
	 * @param int $form_id Form ID.
	 * @param int $limit   Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public static function contacts_for_form( $form_id, $limit = 100 ) {
		global $wpdb;

		$form_id   = absint( $form_id );
		$limit     = max( 1, absint( $limit ) );
		$table_sql = self::table_sql();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					CASE
						WHEN contact_email != '' THEN contact_email
						ELSE contact_phone
					END AS contact_key,
					MAX(contact_email) AS contact_email,
					MAX(contact_phone) AS contact_phone,
					COUNT(*) AS submissions_count,
					MAX(created_at) AS last_at,
					MAX(id) AS last_id
				FROM {$table_sql}
				WHERE form_id = %d
					AND (contact_email != '' OR contact_phone != '')
				GROUP BY contact_key
				ORDER BY last_at DESC
				LIMIT %d",
				$form_id,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'contact_key'       => (string) $row['contact_key'],
				'contact_email'     => (string) $row['contact_email'],
				'contact_phone'     => (string) $row['contact_phone'],
				'submissions_count' => absint( $row['submissions_count'] ),
				'last_at'           => (string) $row['last_at'],
				'last_id'           => absint( $row['last_id'] ),
			);
		}

		return $out;
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

		if ( class_exists( 'Art_Forms_Comments' ) ) {
			Art_Forms_Comments::delete_for_submission( $id );
		}
		if ( class_exists( 'Art_Forms_Activity' ) ) {
			Art_Forms_Activity::delete_for_submission( $id );
		}

		return true;
	}

	/**
	 * Update contact fields and/or payload for a lead.
	 *
	 * @param int                  $id   Submission ID.
	 * @param array<string, mixed> $data Keys: contact_email?, contact_phone?, payload? (map).
	 * @return array<string, mixed>|false Updated submission or false.
	 */
	public static function update_lead_fields( $id, array $data ) {
		global $wpdb;

		$id  = absint( $id );
		$sub = self::get( $id );
		if ( ! $sub ) {
			return false;
		}

		$form_id    = (int) $sub['form_id'];
		$fields_map = Art_Forms_Schema::fields_map( Art_Forms_Schema::get( $form_id ) );
		$payload    = is_array( $sub['payload'] ) ? $sub['payload'] : array();

		$email = (string) $sub['contact_email'];
		$phone = (string) $sub['contact_phone'];

		if ( array_key_exists( 'contact_email', $data ) ) {
			$raw_email = sanitize_text_field( (string) $data['contact_email'] );
			$email     = '' === $raw_email ? '' : sanitize_email( $raw_email );
			if ( '' !== $raw_email && ! is_email( $email ) ) {
				return false;
			}
		}

		if ( array_key_exists( 'contact_phone', $data ) ) {
			$phone = sanitize_text_field( (string) $data['contact_phone'] );
			if ( strlen( $phone ) > 50 ) {
				$phone = substr( $phone, 0, 50 );
			}
		}

		if ( isset( $data['payload'] ) && is_array( $data['payload'] ) ) {
			foreach ( $data['payload'] as $key => $value ) {
				$key = sanitize_key( (string) $key );
				if ( '' === $key || ! isset( $fields_map[ $key ] ) ) {
					continue;
				}
				$field = $fields_map[ $key ];
				$type  = isset( $field['type'] ) ? (string) $field['type'] : 'text';
				if ( 'hidden' === $type ) {
					continue;
				}
				$payload[ $key ] = self::sanitize_field_value( $type, $value );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			self::table(),
			array(
				'contact_email' => $email,
				'contact_phone' => $phone,
				'payload'       => Art_Forms_Schema::encode_json( $payload ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return false;
		}

		if ( class_exists( 'Art_Forms_Activity' ) ) {
			Art_Forms_Activity::log(
				$id,
				'fields_update',
				__( 'Изменены поля заявки', 'art-forms' )
			);
		}

		return self::get( $id );
	}

	/**
	 * Sanitize a single field value by type for CRM edits.
	 *
	 * @param string $type  Field type.
	 * @param mixed  $value Raw value.
	 * @return mixed
	 */
	public static function sanitize_field_value( $type, $value ) {
		$type = sanitize_key( (string) $type );

		if ( 'checkbox' === $type ) {
			if ( ! is_array( $value ) ) {
				$value = '' === $value || null === $value ? array() : array( $value );
			}
			$out = array();
			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) {
					$out[] = sanitize_text_field( (string) $item );
				}
			}
			return array_values( array_unique( $out ) );
		}

		if ( 'consent' === $type ) {
			return ! empty( $value ) ? '1' : '';
		}

		if ( is_array( $value ) ) {
			$value = isset( $value[0] ) ? $value[0] : '';
		}

		$text = is_scalar( $value ) ? (string) $value : '';

		if ( 'email' === $type ) {
			$text = sanitize_email( $text );
			return is_email( $text ) ? $text : '';
		}

		if ( 'textarea' === $type ) {
			return sanitize_textarea_field( $text );
		}

		return sanitize_text_field( $text );
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
	 * Update priority and tags.
	 *
	 * @param int   $id       Submission ID.
	 * @param int   $priority Priority 0–3.
	 * @param mixed $tags     Tags.
	 * @return bool
	 */
	public static function update_priority_tags( $id, $priority, $tags ) {
		global $wpdb;

		$id = absint( $id );
		if ( $id <= 0 ) {
			return false;
		}

		$priority = self::sanitize_priority( $priority );
		$tags     = self::sanitize_tags( $tags );
		$labels   = self::priority_labels();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			self::table(),
			array(
				'priority' => $priority,
				'tags'     => Art_Forms_Schema::encode_json( $tags ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return false;
		}

		if ( class_exists( 'Art_Forms_Activity' ) ) {
			$prio_label = isset( $labels[ $priority ] ) ? $labels[ $priority ] : (string) $priority;
			$tags_str   = ! empty( $tags ) ? implode( ', ', $tags ) : '—';
			$summary    = sprintf(
				/* translators: 1: priority label, 2: tags list */
				__( 'Приоритет: %1$s; теги: %2$s', 'art-forms' ),
				$prio_label,
				$tags_str
			);
			Art_Forms_Activity::log(
				$id,
				'priority_tags',
				$summary,
				array(
					'priority' => $priority,
					'tags'     => $tags,
				)
			);
		}

		return true;
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

		$row['id']         = absint( $row['id'] );
		$row['form_id']    = absint( $row['form_id'] );
		$row['user_id']    = absint( $row['user_id'] );
		$row['stage_id']   = isset( $row['stage_id'] ) ? absint( $row['stage_id'] ) : 0;
		$row['is_starred'] = ! empty( $row['is_starred'] ) ? 1 : 0;
		$row['priority']    = self::sanitize_priority( isset( $row['priority'] ) ? $row['priority'] : 0 );
		$row['board_order'] = isset( $row['board_order'] ) ? (int) $row['board_order'] : 0;
		$tags              = array();
		if ( ! empty( $row['tags'] ) ) {
			if ( is_string( $row['tags'] ) ) {
				$decoded = json_decode( (string) $row['tags'], true );
				$tags    = is_array( $decoded ) ? $decoded : self::sanitize_tags( $row['tags'] );
			} elseif ( is_array( $row['tags'] ) ) {
				$tags = $row['tags'];
			}
		}
		$row['tags']        = self::sanitize_tags( $tags );
		$row['payload']     = $payload;
		$row['meta']        = $meta;
		$row['payload_raw'] = $payload;

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
