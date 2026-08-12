<?php
/**
 * CRM stages per form.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Stages
 */
class Art_Forms_Stages {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'art_forms_stages';
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
	 * Default stage definitions.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function default_definitions() {
		return array(
			array(
				'slug'       => 'new',
				'title'      => __( 'Новые', 'art-forms' ),
				'color'      => '#00a32a',
				'position'   => 0,
				'is_default' => 1,
			),
			array(
				'slug'       => 'in_progress',
				'title'      => __( 'В работе', 'art-forms' ),
				'color'      => '#dba617',
				'position'   => 1,
				'is_default' => 0,
			),
			array(
				'slug'       => 'done',
				'title'      => __( 'Обработано', 'art-forms' ),
				'color'      => '#d63638',
				'position'   => 2,
				'is_default' => 0,
			),
			array(
				'slug'       => 'archive',
				'title'      => __( 'Архив', 'art-forms' ),
				'color'      => '#646970',
				'position'   => 3,
				'is_default' => 0,
			),
		);
	}

	/**
	 * Ensure default stages exist for a form.
	 *
	 * @param int $form_id Form ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function ensure_defaults( $form_id ) {
		$form_id = absint( $form_id );
		if ( $form_id <= 0 ) {
			return array();
		}

		$existing = self::get_for_form( $form_id );
		if ( ! empty( $existing ) ) {
			return $existing;
		}

		foreach ( self::default_definitions() as $def ) {
			self::insert(
				array(
					'form_id'    => $form_id,
					'slug'       => $def['slug'],
					'title'      => $def['title'],
					'color'      => $def['color'],
					'position'   => $def['position'],
					'is_default' => $def['is_default'],
				)
			);
		}

		return self::get_for_form( $form_id );
	}

	/**
	 * Get stages for a form ordered by position.
	 *
	 * @param int $form_id Form ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_for_form( $form_id ) {
		global $wpdb;

		$form_id   = absint( $form_id );
		$table_sql = self::table_sql();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_sql} WHERE form_id = %d ORDER BY position ASC, id ASC",
				$form_id
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
	 * Get one stage.
	 *
	 * @param int $id Stage ID.
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
	 * Default stage for a form.
	 *
	 * @param int $form_id Form ID.
	 * @return array<string, mixed>|null
	 */
	public static function get_default( $form_id ) {
		$stages = self::ensure_defaults( $form_id );
		foreach ( $stages as $stage ) {
			if ( ! empty( $stage['is_default'] ) ) {
				return $stage;
			}
		}

		return ! empty( $stages[0] ) ? $stages[0] : null;
	}

	/**
	 * Insert stage.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return int|false
	 */
	public static function insert( array $data ) {
		global $wpdb;

		$form_id = isset( $data['form_id'] ) ? absint( $data['form_id'] ) : 0;
		if ( $form_id <= 0 ) {
			return false;
		}

		$title = isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : '';
		if ( '' === $title ) {
			$title = __( 'Этап', 'art-forms' );
		}

		$slug = isset( $data['slug'] ) ? sanitize_key( (string) $data['slug'] ) : '';
		if ( '' === $slug ) {
			$slug = self::unique_slug( $form_id, sanitize_title( $title ) );
		}

		$color = isset( $data['color'] ) ? self::sanitize_color( (string) $data['color'] ) : '#2271b1';
		$pos   = isset( $data['position'] ) ? absint( $data['position'] ) : self::next_position( $form_id );
		$def   = ! empty( $data['is_default'] ) ? 1 : 0;

		if ( $def ) {
			self::clear_default( $form_id );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert(
			self::table(),
			array(
				'form_id'    => $form_id,
				'slug'       => $slug,
				'title'      => $title,
				'color'      => $color,
				'position'   => $pos,
				'is_default' => $def,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%s' )
		);

		return false === $ok ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Update stage.
	 *
	 * @param int                  $id   Stage ID.
	 * @param array<string, mixed> $data Data.
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		global $wpdb;

		$id    = absint( $id );
		$stage = self::get( $id );
		if ( ! $stage ) {
			return false;
		}

		$row    = array();
		$format = array();

		if ( isset( $data['title'] ) ) {
			$title = sanitize_text_field( (string) $data['title'] );
			if ( '' !== $title ) {
				$row['title'] = $title;
				$format[]     = '%s';
			}
		}

		if ( isset( $data['color'] ) ) {
			$row['color'] = self::sanitize_color( (string) $data['color'] );
			$format[]     = '%s';
		}

		if ( isset( $data['position'] ) ) {
			$row['position'] = absint( $data['position'] );
			$format[]        = '%d';
		}

		if ( isset( $data['is_default'] ) && $data['is_default'] ) {
			self::clear_default( (int) $stage['form_id'] );
			$row['is_default'] = 1;
			$format[]          = '%d';
		}

		if ( empty( $row ) ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->update(
			self::table(),
			$row,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);
	}

	/**
	 * Reorder stages.
	 *
	 * @param int        $form_id   Form ID.
	 * @param array<int> $stage_ids Ordered IDs.
	 * @return bool
	 */
	public static function reorder( $form_id, array $stage_ids ) {
		$form_id = absint( $form_id );
		$pos     = 0;
		foreach ( $stage_ids as $sid ) {
			$sid = absint( $sid );
			if ( $sid <= 0 ) {
				continue;
			}
			$stage = self::get( $sid );
			if ( ! $stage || (int) $stage['form_id'] !== $form_id ) {
				continue;
			}
			self::update( $sid, array( 'position' => $pos ) );
			++$pos;
		}

		return true;
	}

	/**
	 * Delete stage and move submissions to fallback.
	 *
	 * @param int $id          Stage ID.
	 * @param int $fallback_id Target stage for submissions.
	 * @return bool|WP_Error
	 */
	public static function delete( $id, $fallback_id = 0 ) {
		global $wpdb;

		$id    = absint( $id );
		$stage = self::get( $id );
		if ( ! $stage ) {
			return false;
		}

		$form_id = (int) $stage['form_id'];
		$all     = self::get_for_form( $form_id );
		if ( count( $all ) <= 1 ) {
			return new WP_Error( 'art_forms_last_stage', __( 'Нельзя удалить последний этап.', 'art-forms' ) );
		}

		$fallback_id = absint( $fallback_id );
		if ( $fallback_id <= 0 || $fallback_id === $id ) {
			foreach ( $all as $candidate ) {
				if ( (int) $candidate['id'] !== $id ) {
					$fallback_id = (int) $candidate['id'];
					break;
				}
			}
		}

		$fallback = self::get( $fallback_id );
		if ( ! $fallback || (int) $fallback['form_id'] !== $form_id ) {
			return new WP_Error( 'art_forms_bad_fallback', __( 'Некорректный этап для переноса заявок.', 'art-forms' ) );
		}

		Art_Forms_Submissions::reassign_stage( $id, $fallback_id );

		if ( ! empty( $stage['is_default'] ) ) {
			self::update( $fallback_id, array( 'is_default' => 1 ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );

		return false !== $deleted;
	}

	/**
	 * Whether stage is the archive column (slug archive).
	 *
	 * @param array<string, mixed>|null $stage Stage row.
	 * @return bool
	 */
	public static function is_archive_stage( $stage ) {
		return is_array( $stage ) && isset( $stage['slug'] ) && 'archive' === (string) $stage['slug'];
	}

	/**
	 * Archive stage IDs for a form.
	 *
	 * @param int $form_id Form ID.
	 * @return array<int, int>
	 */
	public static function archive_ids( $form_id ) {
		$ids = array();
		foreach ( self::get_for_form( $form_id ) as $stage ) {
			if ( self::is_archive_stage( $stage ) ) {
				$ids[] = (int) $stage['id'];
			}
		}

		return $ids;
	}

	/**
	 * Counts of submissions per stage (+ total / active without archive).
	 *
	 * @param int $form_id Form ID.
	 * @return array{total: int, active_total: int, by_stage: array<int, int>}
	 */
	public static function counts( $form_id ) {
		global $wpdb;

		$form_id   = absint( $form_id );
		$table_sql = '`' . esc_sql( Art_Forms_Submissions::table() ) . '`';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT stage_id, COUNT(*) AS cnt FROM {$table_sql} WHERE form_id = %d GROUP BY stage_id",
				$form_id
			),
			ARRAY_A
		);
		// phpcs:enable

		$by_stage = array();
		$total    = 0;
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$sid              = absint( $row['stage_id'] );
				$cnt              = absint( $row['cnt'] );
				$by_stage[ $sid ] = $cnt;
				$total           += $cnt;
			}
		}

		$archive_count = 0;
		foreach ( self::archive_ids( $form_id ) as $aid ) {
			$archive_count += isset( $by_stage[ $aid ] ) ? (int) $by_stage[ $aid ] : 0;
		}

		return array(
			'total'        => $total,
			'active_total' => max( 0, $total - $archive_count ),
			'by_stage'     => $by_stage,
		);
	}

	/**
	 * Clear default flag for form.
	 *
	 * @param int $form_id Form ID.
	 */
	private static function clear_default( $form_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			self::table(),
			array( 'is_default' => 0 ),
			array( 'form_id' => absint( $form_id ) ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Next position.
	 *
	 * @param int $form_id Form ID.
	 * @return int
	 */
	private static function next_position( $form_id ) {
		global $wpdb;

		$table_sql = self::table_sql();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$max = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(position) FROM {$table_sql} WHERE form_id = %d",
				absint( $form_id )
			)
		);
		// phpcs:enable

		return $max + 1;
	}

	/**
	 * Unique slug for form.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $base    Base slug.
	 * @return string
	 */
	private static function unique_slug( $form_id, $base ) {
		$base = sanitize_key( $base );
		if ( '' === $base ) {
			$base = 'stage';
		}

		$slug  = $base;
		$n     = 2;
		$taken = array();
		foreach ( self::get_for_form( $form_id ) as $stage ) {
			$taken[ $stage['slug'] ] = true;
		}

		while ( isset( $taken[ $slug ] ) ) {
			$slug = $base . '_' . $n;
			++$n;
		}

		return $slug;
	}

	/**
	 * Sanitize hex color.
	 *
	 * @param string $color Color.
	 * @return string
	 */
	public static function sanitize_color( $color ) {
		$color = trim( (string) $color );
		if ( preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ) {
			return strtolower( $color );
		}
		if ( preg_match( '/^#[0-9a-fA-F]{3}$/', $color ) ) {
			return strtolower( $color );
		}

		return '#2271b1';
	}

	/**
	 * Hydrate row.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array<string, mixed>
	 */
	private static function hydrate( array $row ) {
		$row['id']         = absint( $row['id'] );
		$row['form_id']    = absint( $row['form_id'] );
		$row['position']   = absint( $row['position'] );
		$row['is_default'] = ! empty( $row['is_default'] ) ? 1 : 0;
		$row['slug']       = (string) $row['slug'];
		$row['title']      = (string) $row['title'];
		$row['color']      = self::sanitize_color( (string) $row['color'] );

		return $row;
	}
}
