<?php
/**
 * Admin submissions CRM.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Admin_Submissions
 */
class Art_Forms_Admin_Submissions {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_post_art_forms_export_csv', array( __CLASS__, 'handle_csv' ) );
		add_action( 'admin_post_art_forms_delete_submission', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_crm_assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );

		$ajax = array(
			'art_forms_crm_get_card',
			'art_forms_crm_set_stage',
			'art_forms_crm_toggle_star',
			'art_forms_crm_add_comment',
			'art_forms_crm_delete_comment',
			'art_forms_crm_stage_save',
			'art_forms_crm_stage_delete',
			'art_forms_crm_stage_reorder',
			'art_forms_crm_bulk',
			'art_forms_crm_save_prefs',
			'art_forms_crm_update_fields',
			'art_forms_crm_board_reorder',
		);

		foreach ( $ajax as $action ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, 'ajax_' . str_replace( 'art_forms_crm_', '', $action ) ) );
		}
	}

	/**
	 * Body class on CRM answers screen (for overflow containment CSS).
	 *
	 * @param string $classes Classes.
	 * @return string
	 */
	public static function admin_body_class( $classes ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'art-forms-submissions' === $page ) {
			$classes .= ' art-forms-crm-screen';
		}
		return $classes;
	}

	/**
	 * Enqueue CRM assets on submissions page.
	 *
	 * @param string $hook Hook.
	 */
	public static function enqueue_crm_assets( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'art-forms-submissions' !== $page ) {
			return;
		}

		wp_enqueue_script(
			'art-forms-admin-crm',
			ART_FORMS_PLUGIN_URL . 'assets/js/admin-crm.js',
			array( 'art-forms-admin' ),
			ART_FORMS_VERSION,
			true
		);

		$form_id = isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		wp_localize_script(
			'art-forms-admin-crm',
			'artFormsCrm',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'art_forms_crm' ),
				'formId'  => $form_id,
				'adminUrl'=> admin_url( 'admin.php' ),
				'strings' => array(
					'saved'           => __( 'Сохранено', 'art-forms' ),
					'error'           => __( 'Ошибка', 'art-forms' ),
					'confirmDelete'   => __( 'Удалить выбранные заявки безвозвратно?', 'art-forms' ),
					'confirmStageDel' => __( 'Удалить этап? Заявки будут перенесены.', 'art-forms' ),
					'commentEmpty'    => __( 'Введите комментарий', 'art-forms' ),
					'noSelection'     => __( 'Выберите заявки', 'art-forms' ),
					'stageTitle'      => __( 'Название этапа', 'art-forms' ),
					'close'           => __( 'Закрыть', 'art-forms' ),
					'save'            => __( 'Сохранить', 'art-forms' ),
					'prev'            => __( 'Предыдущая', 'art-forms' ),
					'next'            => __( 'Следующая', 'art-forms' ),
					'comments'        => __( 'Комментарии', 'art-forms' ),
					'addComment'      => __( 'Добавить', 'art-forms' ),
					'related'         => __( 'Заявки контакта', 'art-forms' ),
					'profile'         => __( 'профиль', 'art-forms' ),
					'consentDoc'      => __( 'документ', 'art-forms' ),
					'edit'            => __( 'Редактировать', 'art-forms' ),
					'saveFields'      => __( 'Сохранить', 'art-forms' ),
					'cancel'          => __( 'Отмена', 'art-forms' ),
					'fieldsSaved'     => __( 'Поля сохранены', 'art-forms' ),
					'invalidEmail'    => __( 'Некорректный email', 'art-forms' ),
					'name'            => __( 'Имя', 'art-forms' ),
					'priority'        => __( 'Приоритет', 'art-forms' ),
					'tags'            => __( 'Теги', 'art-forms' ),
					'tagsHint'        => __( 'через запятую', 'art-forms' ),
					'activity'        => __( 'История', 'art-forms' ),
					'noActivity'      => __( 'Пока нет записей', 'art-forms' ),
					'stage'           => __( 'Этап заявки', 'art-forms' ),
					'delete'          => __( 'Удалить', 'art-forms' ),
					'loading'         => __( 'Загрузка…', 'art-forms' ),
				),
			)
		);

		unset( $hook );
	}

	/**
	 * Render submissions page.
	 */
	public static function render_page() {
		if ( ! Art_Forms_Capabilities::can_crm() ) {
			return;
		}

		$form_id   = isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page      = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$view_id   = isset( $_GET['view'] ) ? absint( wp_unslash( $_GET['view'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$deleted   = isset( $_GET['deleted'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$stage_id  = isset( $_GET['stage_id'] ) ? absint( wp_unslash( $_GET['stage_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$layout    = isset( $_GET['layout'] ) ? sanitize_key( wp_unslash( (string) $_GET['layout'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab       = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'leads'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby   = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( (string) $_GET['orderby'] ) ) : 'id'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order     = isset( $_GET['order'] ) ? strtolower( sanitize_text_field( wp_unslash( (string) $_GET['order'] ) ) ) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$starred   = isset( $_GET['starred'] ) ? absint( wp_unslash( $_GET['starred'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 1 !== $starred ) {
			$starred = 0;
		}
		$priority_filter = isset( $_GET['priority'] ) ? (string) wp_unslash( $_GET['priority'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$priority_filter = ( '' === $priority_filter ) ? -1 : absint( $priority_filter );
		if ( $priority_filter < 0 || $priority_filter > 3 ) {
			$priority_filter = -1;
		}
		$tag_filter = isset( $_GET['tag'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['tag'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( strlen( $tag_filter ) > 40 ) {
			$tag_filter = substr( $tag_filter, 0, 40 );
		}

		$allowed_orderby = array( 'id', 'form', 'date', 'email', 'phone', 'name', 'status', 'stage', 'star', 'priority' );
		$is_field_order  = ( 0 === strpos( $orderby, 'field_' ) );
		if ( ! in_array( $orderby, $allowed_orderby, true ) && ! $is_field_order ) {
			$orderby = 'id';
		}
		if ( 'asc' !== $order && 'desc' !== $order ) {
			$order = 'desc';
		}
		if ( ! in_array( $tab, array( 'leads', 'contacts', 'stages' ), true ) ) {
			$tab = 'leads';
		}

		$forms = get_posts(
			array(
				'post_type'      => Art_Forms_Post_Types::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		// Deep-link to a submission without form_id.
		if ( $view_id > 0 && $form_id <= 0 ) {
			$submission = Art_Forms_Submissions::get( $view_id );
			if ( $submission ) {
				$form_id = absint( $submission['form_id'] );
			}
		}

		if ( $form_id <= 0 ) {
			$counts = Art_Forms_Submissions::counts_by_form();
			Art_Forms_Crm_Notifications::mark_seen();
			include ART_FORMS_PLUGIN_DIR . 'admin/views/page-submissions-hub.php';
			return;
		}

		$form = get_post( $form_id );
		if ( ! $form || Art_Forms_Post_Types::POST_TYPE !== $form->post_type ) {
			echo '<div class="wrap art-forms-admin"><div class="notice notice-error"><p>' . esc_html__( 'Форма не найдена.', 'art-forms' ) . '</p></div></div>';
			return;
		}

		$stages  = Art_Forms_Stages::ensure_defaults( $form_id );
		$counts  = Art_Forms_Stages::counts( $form_id );
		$prefs       = self::get_user_prefs( $form_id );
		$layout      = in_array( $layout, array( 'table', 'board' ), true ) ? $layout : $prefs['layout'];
		$hidden      = $prefs['hidden_columns'];
		$col_widths  = $prefs['column_widths'];
		$col_order   = $prefs['column_order'];
		$col_aliases = $prefs['column_labels'];

		$schema     = Art_Forms_Schema::get( $form_id );
		$fields     = Art_Forms_Schema::flatten_fields( $schema );
		$fields_map = Art_Forms_Schema::fields_map( $schema );

		// Visible field columns (exclude hidden/honeypot-like).
		$field_columns = array();
		$field_keys    = array();
		foreach ( $fields as $field ) {
			$type = isset( $field['type'] ) ? $field['type'] : 'text';
			$key  = isset( $field['key'] ) ? $field['key'] : '';
			if ( '' === $key || 'hidden' === $type || 'name' === $type ) {
				continue;
			}
			$field_columns[] = $field;
			$field_keys[]    = (string) $key;
		}

		if ( $is_field_order ) {
			$field_order_key = substr( $orderby, 6 );
			if ( ! in_array( $field_order_key, $field_keys, true ) ) {
				$orderby = 'id';
			}
		}

		$table_col_order = self::resolve_column_order( $col_order, $field_keys );
		$fields_by_key   = array();
		foreach ( $field_columns as $fc ) {
			$fields_by_key[ (string) $fc['key'] ] = $fc;
		}

		$per_page = ( 'board' === $layout ) ? 500 : 30;

		$archive_ids = Art_Forms_Stages::archive_ids( $form_id );

		$query_args = array(
			'form_id'   => $form_id,
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'search'    => $search,
			'page'      => $page,
			'per_page'  => $per_page,
			'orderby'   => $orderby,
			'order'     => $order,
		);
		if ( 'board' === $layout ) {
			$query_args['orderby'] = 'board_order';
			$query_args['order']   = 'asc';
		}
		if ( $stage_id > 0 && 'board' !== $layout ) {
			$query_args['stage_id'] = $stage_id;
		} elseif ( 0 === $stage_id && 'board' !== $layout && ! empty( $archive_ids ) ) {
			// «Все» — без архива.
			$query_args['exclude_stage_ids'] = $archive_ids;
		}
		if ( 1 === $starred ) {
			$query_args['is_starred'] = 1;
			if ( 0 === $stage_id && 'board' !== $layout && ! empty( $archive_ids ) ) {
				$query_args['exclude_stage_ids'] = $archive_ids;
			}
		}
		if ( $priority_filter >= 0 ) {
			$query_args['priority'] = $priority_filter;
		}
		if ( '' !== $tag_filter ) {
			$query_args['tag'] = $tag_filter;
		}

		$board_groups    = array();
		$board_truncated = array();
		$board_more      = array();

		if ( 'board' === $layout ) {
			$nav_ids = array();
			$total   = 0;
			foreach ( $stages as $st ) {
				$sid       = (int) $st['id'];
				$col_args  = array_merge(
					$query_args,
					array(
						'stage_id' => $sid,
						'page'     => 1,
						'per_page' => 500,
						'orderby'  => 'board_order',
						'order'    => 'asc',
					)
				);
				unset( $col_args['exclude_stage_ids'] );
				$col_result               = Art_Forms_Submissions::query( $col_args );
				$board_groups[ $sid ]     = $col_result['items'];
				$col_total                = (int) $col_result['total'];
				$total                   += $col_total;
				$loaded                   = count( $col_result['items'] );
				$board_truncated[ $sid ] = $col_total > $loaded;
				$board_more[ $sid ]       = max( 0, $col_total - $loaded );
				foreach ( $col_result['items'] as $item ) {
					$nav_ids[] = (int) $item['id'];
				}
			}
			$result = array(
				'items' => array(),
				'total' => $total,
			);
			foreach ( $board_groups as $group_items ) {
				foreach ( $group_items as $item ) {
					$result['items'][] = $item;
				}
			}
		} else {
			$result = Art_Forms_Submissions::query( $query_args );
		}

		$starred_total = 0;
		if ( 'leads' === $tab ) {
			$star_args = array(
				'form_id'    => $form_id,
				'is_starred' => 1,
				'per_page'   => 1,
				'page'       => 1,
			);
			if ( ! empty( $archive_ids ) ) {
				$star_args['exclude_stage_ids'] = $archive_ids;
			}
			$starred_q     = Art_Forms_Submissions::query( $star_args );
			$starred_total = isset( $starred_q['total'] ) ? (int) $starred_q['total'] : 0;
		}

		if ( 'board' !== $layout ) {
			$nav_ids = array();
			foreach ( $result['items'] as $item ) {
				$nav_ids[] = (int) $item['id'];
			}
		}

		$contacts = array();
		if ( 'contacts' === $tab ) {
			$contacts = Art_Forms_Submissions::contacts_for_form( $form_id, 200 );
		}

		Art_Forms_Crm_Notifications::mark_seen();

		include ART_FORMS_PLUGIN_DIR . 'admin/views/page-submissions-inbox.php';
	}

	/**
	 * User CRM prefs for a form.
	 *
	 * @param int $form_id Form ID.
	 * @return array{layout: string, hidden_columns: array<int, string>, column_widths: array<string, int>, column_order: array<int, string>, column_labels: array<string, string>}
	 */
	public static function get_user_prefs( $form_id ) {
		$all = get_user_meta( get_current_user_id(), 'art_forms_crm_prefs', true );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		$key = 'f' . absint( $form_id );
		$raw = isset( $all[ $key ] ) && is_array( $all[ $key ] ) ? $all[ $key ] : array();

		$layout = isset( $raw['layout'] ) ? sanitize_key( (string) $raw['layout'] ) : 'table';
		if ( ! in_array( $layout, array( 'table', 'board' ), true ) ) {
			$layout = 'table';
		}

		$hidden = array();
		if ( ! empty( $raw['hidden_columns'] ) && is_array( $raw['hidden_columns'] ) ) {
			foreach ( $raw['hidden_columns'] as $col ) {
				$hidden[] = sanitize_key( (string) $col );
			}
		}

		$widths = array();
		if ( ! empty( $raw['column_widths'] ) && is_array( $raw['column_widths'] ) ) {
			foreach ( $raw['column_widths'] as $col => $w ) {
				$col = sanitize_key( (string) $col );
				$w   = absint( $w );
				if ( '' !== $col && $w >= 28 && $w <= 600 ) {
					$widths[ $col ] = $w;
				}
			}
		}

		$order = array();
		if ( ! empty( $raw['column_order'] ) && is_array( $raw['column_order'] ) ) {
			foreach ( $raw['column_order'] as $col ) {
				$col = sanitize_key( (string) $col );
				if ( '' !== $col && 'check' !== $col ) {
					$order[] = $col;
				}
			}
			$order = array_values( array_unique( $order ) );
		}

		$labels = array();
		if ( ! empty( $raw['column_labels'] ) && is_array( $raw['column_labels'] ) ) {
			foreach ( $raw['column_labels'] as $col => $lab ) {
				$col = sanitize_key( (string) $col );
				$lab = sanitize_text_field( (string) $lab );
				if ( '' !== $col && '' !== $lab ) {
					$labels[ $col ] = self::truncate_column_label( $lab );
				}
			}
		}

		return array(
			'layout'         => $layout,
			'hidden_columns' => $hidden,
			'column_widths'  => $widths,
			'column_order'   => $order,
			'column_labels'  => $labels,
		);
	}

	/**
	 * Limit custom column title length.
	 *
	 * @param string $label Label.
	 * @return string
	 */
	public static function truncate_column_label( $label ) {
		$label = trim( (string) $label );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $label, 0, 80 );
		}
		return substr( $label, 0, 80 );
	}

	/**
	 * Display label for a form field column (custom alias or schema label).
	 *
	 * @param string                $field_key    Field key.
	 * @param string                $schema_label Original label.
	 * @param array<string, string> $aliases      Custom labels map.
	 * @return string
	 */
	public static function field_column_label( $field_key, $schema_label, array $aliases ) {
		$key = sanitize_key( (string) $field_key );
		if ( isset( $aliases[ $key ] ) && '' !== trim( (string) $aliases[ $key ] ) ) {
			return (string) $aliases[ $key ];
		}
		$schema_label = (string) $schema_label;
		return '' !== $schema_label ? $schema_label : $key;
	}

	/**
	 * Resolve display column order (check is always first, not included).
	 *
	 * @param array<int, string> $saved_order Saved order.
	 * @param array<int, string> $field_keys  Form field keys.
	 * @return array<int, string>
	 */
	public static function resolve_column_order( array $saved_order, array $field_keys ) {
		$default = array_merge(
			array( 'star', 'priority', 'tags', 'id', 'name', 'date' ),
			$field_keys,
			array( 'stage' )
		);
		$allowed = array_fill_keys( $default, true );
		$out     = array();

		$had_name = false;
		foreach ( $saved_order as $col ) {
			if ( 'name' === sanitize_key( (string) $col ) ) {
				$had_name = true;
				break;
			}
		}

		foreach ( $saved_order as $col ) {
			$col = sanitize_key( (string) $col );
			// Service email/phone stay in the lead card only — skip table duplicates.
			if ( in_array( $col, array( 'email', 'phone', 'check' ), true ) ) {
				continue;
			}
			if ( isset( $allowed[ $col ] ) && ! in_array( $col, $out, true ) ) {
				$out[] = $col;
			}
		}

		foreach ( $default as $col ) {
			if ( in_array( $col, $out, true ) ) {
				continue;
			}
			if ( 'name' === $col && ! $had_name ) {
				$id_pos = array_search( 'id', $out, true );
				if ( false !== $id_pos ) {
					array_splice( $out, $id_pos + 1, 0, array( 'name' ) );
					continue;
				}
			}
			$out[] = $col;
		}

		return $out;
	}

	/**
	 * Save user prefs.
	 *
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $prefs   Prefs.
	 */
	public static function save_user_prefs( $form_id, array $prefs ) {
		$uid = get_current_user_id();
		$all = get_user_meta( $uid, 'art_forms_crm_prefs', true );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		$key         = 'f' . absint( $form_id );
		$current     = self::get_user_prefs( $form_id );
		$all[ $key ] = array_merge( $current, $prefs );
		update_user_meta( $uid, 'art_forms_crm_prefs', $all );
	}

	/**
	 * Capability + nonce check for AJAX.
	 *
	 * @return bool
	 */
	private static function ajax_guard() {
		if ( ! Art_Forms_Capabilities::can_crm() ) {
			wp_send_json_error( array( 'message' => __( 'Недостаточно прав.', 'art-forms' ) ), 403 );
		}
		check_ajax_referer( 'art_forms_crm', 'nonce' );
		return true;
	}

	/**
	 * AJAX: get lead card payload.
	 */
	public static function ajax_get_card() {
		self::ajax_guard();

		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$submission = Art_Forms_Submissions::get( $id );
		if ( ! $submission ) {
			wp_send_json_error( array( 'message' => __( 'Заявка не найдена.', 'art-forms' ) ), 404 );
		}

		$form_id    = (int) $submission['form_id'];
		$stages     = Art_Forms_Stages::ensure_defaults( $form_id );
		$fields_map = Art_Forms_Schema::fields_map( Art_Forms_Schema::get( $form_id ) );
		$fields_out = array();
		foreach ( (array) $submission['payload'] as $key => $value ) {
			$key   = (string) $key;
			$field = isset( $fields_map[ $key ] ) ? $fields_map[ $key ] : array( 'key' => $key, 'label' => $key, 'type' => 'text' );
			$type  = isset( $field['type'] ) ? (string) $field['type'] : 'text';
			if ( 'hidden' === $type ) {
				continue;
			}
			$label   = Art_Forms_Schema::field_display_label( $field );
			$options = array();
			if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
				foreach ( $field['options'] as $opt ) {
					if ( is_array( $opt ) ) {
						$options[] = isset( $opt['value'] ) ? (string) $opt['value'] : (string) ( $opt['label'] ?? '' );
					} else {
						$options[] = (string) $opt;
					}
				}
			}
			$fields_out[] = array(
				'key'               => $key,
				'label'             => $label,
				'type'              => $type,
				'value'             => Art_Forms_Schema::format_display_value( $field, $value ),
				'raw'               => $value,
				'options'           => $options,
				'privacy_url'       => ( 'consent' === $type ) ? Art_Forms_Schema::resolve_privacy_url( $field ) : '',
				'privacy_link_text' => ( 'consent' === $type && ! empty( $field['privacy_link_text'] ) ) ? (string) $field['privacy_link_text'] : '',
			);
		}

		$related  = Art_Forms_Submissions::related_by_contact( $submission );
		$comments = Art_Forms_Comments::get_for_submission( $id );
		$activity = class_exists( 'Art_Forms_Activity' ) ? Art_Forms_Activity::get_for_submission( $id, 40 ) : array();
		$comments_out = array();
		foreach ( $comments as $c ) {
			$comments_out[] = array(
				'id'          => (int) $c['id'],
				'body'        => $c['body'],
				'author_name' => $c['author_name'],
				'user_id'     => (int) $c['user_id'],
				'created_at'  => Art_Forms_Submissions::format_datetime( $c['created_at'] ),
				'can_delete'  => ( (int) $c['user_id'] === get_current_user_id() ) || Art_Forms_Capabilities::can_manage(),
			);
		}

		$activity_out = array();
		foreach ( $activity as $a ) {
			$activity_out[] = array(
				'id'          => (int) $a['id'],
				'summary'     => (string) $a['summary'],
				'event_type'  => (string) $a['event_type'],
				'author_name' => (string) $a['author_name'],
				'created_at'  => Art_Forms_Submissions::format_datetime( (string) $a['created_at'] ),
			);
		}

		$related_out = array();
		foreach ( $related as $r ) {
			$related_out[] = array(
				'id'         => (int) $r['id'],
				'form_id'    => (int) $r['form_id'],
				'form_title' => get_the_title( (int) $r['form_id'] ),
				'created_at' => Art_Forms_Submissions::format_datetime( (string) $r['created_at'] ),
				'stage_id'   => (int) $r['stage_id'],
			);
		}

		$delete_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=art_forms_delete_submission&id=' . $id ),
			'art_forms_delete_submission'
		);

		$profile_url = '';
		$email       = sanitize_email( (string) $submission['contact_email'] );
		if ( is_email( $email ) ) {
			$user = get_user_by( 'email', $email );
			if ( $user instanceof WP_User ) {
				$link = get_edit_user_link( $user->ID );
				if ( is_string( $link ) && '' !== $link ) {
					$profile_url = $link;
				}
			}
		}

		wp_send_json_success(
			array(
				'id'             => (int) $submission['id'],
				'form_id'        => $form_id,
				'stage_id'       => (int) $submission['stage_id'],
				'is_starred'     => (int) $submission['is_starred'],
				'status'         => (string) $submission['status'],
				'status_badge'   => Art_Forms_Submissions::render_status_badge( (string) $submission['status'] ),
				'contact_email'  => (string) $submission['contact_email'],
				'contact_phone'  => (string) $submission['contact_phone'],
				'contact_name'   => isset( $submission['contact_name'] ) ? (string) $submission['contact_name'] : '',
				'profile_url'    => $profile_url,
				'priority'       => (int) $submission['priority'],
				'priority_label' => Art_Forms_Submissions::priority_labels()[ (int) $submission['priority'] ] ?? '',
				'tags'           => is_array( $submission['tags'] ) ? $submission['tags'] : array(),
				'priorities'     => Art_Forms_Submissions::priority_labels(),
				'created_at'     => Art_Forms_Submissions::format_datetime( (string) $submission['created_at'] ),
				'page_url'       => Art_Forms_Schema::format_display_url( (string) $submission['page_url'] ),
				'referrer'       => Art_Forms_Schema::format_display_url( (string) $submission['referrer'] ),
				'utm'            => array_filter(
					array(
						$submission['utm_source'],
						$submission['utm_medium'],
						$submission['utm_campaign'],
						$submission['utm_content'],
						$submission['utm_term'],
					)
				),
				'fields'         => $fields_out,
				'stages'         => $stages,
				'comments'       => $comments_out,
				'activity'       => $activity_out,
				'related'        => $related_out,
				'related_count'  => count( $related_out ) + 1,
				'delete_url'     => $delete_url,
			)
		);
	}

	/**
	 * AJAX: update contact + payload fields from lead card.
	 */
	public static function ajax_update_fields() {
		self::ajax_guard();

		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		if ( $id <= 0 || ! Art_Forms_Submissions::get( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Заявка не найдена.', 'art-forms' ) ), 404 );
		}

		$data = array();
		if ( isset( $_POST['contact_email'] ) ) {
			$data['contact_email'] = sanitize_text_field( wp_unslash( (string) $_POST['contact_email'] ) );
		}
		if ( isset( $_POST['contact_phone'] ) ) {
			$data['contact_phone'] = sanitize_text_field( wp_unslash( (string) $_POST['contact_phone'] ) );
		}
		if ( isset( $_POST['contact_name'] ) ) {
			$data['contact_name'] = sanitize_text_field( wp_unslash( (string) $_POST['contact_name'] ) );
		}

		$payload_raw = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( is_string( $payload_raw ) ) {
			$decoded     = json_decode( $payload_raw, true );
			$payload_raw = is_array( $decoded ) ? $decoded : null;
		}
		if ( is_array( $payload_raw ) ) {
			$data['payload'] = $payload_raw;
		}

		if ( isset( $data['contact_email'] ) && '' !== $data['contact_email'] && ! is_email( $data['contact_email'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Некорректный email', 'art-forms' ) ), 400 );
		}

		$updated = Art_Forms_Submissions::update_lead_fields( $id, $data );
		if ( ! $updated ) {
			wp_send_json_error( array( 'message' => __( 'Не удалось сохранить.', 'art-forms' ) ), 500 );
		}

		if ( isset( $_POST['priority'] ) || isset( $_POST['tags'] ) ) {
			$priority = isset( $_POST['priority'] ) ? absint( wp_unslash( $_POST['priority'] ) ) : (int) $updated['priority'];
			$tags_raw = isset( $_POST['tags'] ) ? wp_unslash( $_POST['tags'] ) : $updated['tags']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			Art_Forms_Submissions::update_priority_tags( $id, $priority, $tags_raw );
		}

		$_POST['id'] = $id;
		self::ajax_get_card();
	}

	/**
	 * AJAX: set stage.
	 */
	public static function ajax_set_stage() {
		self::ajax_guard();

		$id       = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$stage_id = isset( $_POST['stage_id'] ) ? absint( wp_unslash( $_POST['stage_id'] ) ) : 0;
		$submission = Art_Forms_Submissions::get( $id );
		$stage      = Art_Forms_Stages::get( $stage_id );

		if ( ! $submission || ! $stage || (int) $stage['form_id'] !== (int) $submission['form_id'] ) {
			wp_send_json_error( array( 'message' => __( 'Некорректные данные.', 'art-forms' ) ), 400 );
		}

		Art_Forms_Submissions::update_stage( $id, $stage_id );
		wp_send_json_success(
			array(
				'id'       => $id,
				'stage_id' => $stage_id,
				'stage'    => $stage,
			)
		);
	}

	/**
	 * AJAX: reorder kanban cards (within or into a stage).
	 */
	public static function ajax_board_reorder() {
		self::ajax_guard();

		$form_id  = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		$stage_id = isset( $_POST['stage_id'] ) ? absint( wp_unslash( $_POST['stage_id'] ) ) : 0;
		$ids      = isset( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_array( $ids ) ) {
			$ids = array();
		}
		$ids = array_map( 'absint', $ids );

		$stage = Art_Forms_Stages::get( $stage_id );
		if ( ! $stage || (int) $stage['form_id'] !== $form_id ) {
			wp_send_json_error( array( 'message' => __( 'Некорректные данные.', 'art-forms' ) ), 400 );
		}

		foreach ( $ids as $sid ) {
			$row = Art_Forms_Submissions::get( $sid );
			if ( ! $row || (int) $row['form_id'] !== $form_id ) {
				wp_send_json_error( array( 'message' => __( 'Некорректные данные.', 'art-forms' ) ), 400 );
			}
		}

		$ok = Art_Forms_Submissions::reorder_board( $form_id, $stage_id, $ids );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Не удалось сохранить порядок.', 'art-forms' ) ), 500 );
		}

		wp_send_json_success(
			array(
				'form_id'  => $form_id,
				'stage_id' => $stage_id,
				'ids'      => $ids,
			)
		);
	}

	/**
	 * AJAX: toggle star.
	 */
	public static function ajax_toggle_star() {
		self::ajax_guard();

		$id  = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$val = Art_Forms_Submissions::set_starred( $id );
		if ( false === $val ) {
			wp_send_json_error( array( 'message' => __( 'Не удалось обновить.', 'art-forms' ) ), 400 );
		}

		wp_send_json_success( array( 'id' => $id, 'is_starred' => (int) $val ) );
	}

	/**
	 * AJAX: add comment.
	 */
	public static function ajax_add_comment() {
		self::ajax_guard();

		$id   = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$body = isset( $_POST['body'] ) ? wp_unslash( (string) $_POST['body'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! Art_Forms_Submissions::get( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Заявка не найдена.', 'art-forms' ) ), 404 );
		}

		$cid = Art_Forms_Comments::add( $id, $body );
		if ( ! $cid ) {
			wp_send_json_error( array( 'message' => __( 'Не удалось добавить комментарий.', 'art-forms' ) ), 400 );
		}

		$comment = Art_Forms_Comments::get( $cid );
		wp_send_json_success(
			array(
				'comment' => array(
					'id'          => (int) $comment['id'],
					'body'        => $comment['body'],
					'author_name' => $comment['author_name'],
					'user_id'     => (int) $comment['user_id'],
					'created_at'  => Art_Forms_Submissions::format_datetime( $comment['created_at'] ),
					'can_delete'  => true,
				),
			)
		);
	}

	/**
	 * AJAX: delete comment.
	 */
	public static function ajax_delete_comment() {
		self::ajax_guard();

		$cid     = isset( $_POST['comment_id'] ) ? absint( wp_unslash( $_POST['comment_id'] ) ) : 0;
		$comment = Art_Forms_Comments::get( $cid );
		if ( ! $comment ) {
			wp_send_json_error( array( 'message' => __( 'Комментарий не найден.', 'art-forms' ) ), 404 );
		}

		if ( (int) $comment['user_id'] !== get_current_user_id() && ! Art_Forms_Capabilities::can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Недостаточно прав.', 'art-forms' ) ), 403 );
		}

		Art_Forms_Comments::delete( $cid );
		wp_send_json_success( array( 'comment_id' => $cid ) );
	}

	/**
	 * AJAX: create/update stage.
	 */
	public static function ajax_stage_save() {
		self::ajax_guard();

		$form_id  = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		$stage_id = isset( $_POST['stage_id'] ) ? absint( wp_unslash( $_POST['stage_id'] ) ) : 0;
		$title    = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['title'] ) ) : '';
		$color    = isset( $_POST['color'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['color'] ) ) : '#2271b1';

		if ( $form_id <= 0 || '' === $title ) {
			wp_send_json_error( array( 'message' => __( 'Укажите название этапа.', 'art-forms' ) ), 400 );
		}

		Art_Forms_Stages::ensure_defaults( $form_id );

		if ( $stage_id > 0 ) {
			$stage = Art_Forms_Stages::get( $stage_id );
			if ( ! $stage || (int) $stage['form_id'] !== $form_id ) {
				wp_send_json_error( array( 'message' => __( 'Этап не найден.', 'art-forms' ) ), 404 );
			}
			Art_Forms_Stages::update( $stage_id, array( 'title' => $title, 'color' => $color ) );
		} else {
			$stage_id = Art_Forms_Stages::insert(
				array(
					'form_id' => $form_id,
					'title'   => $title,
					'color'   => $color,
				)
			);
			if ( ! $stage_id ) {
				wp_send_json_error( array( 'message' => __( 'Не удалось создать этап.', 'art-forms' ) ), 500 );
			}
		}

		wp_send_json_success(
			array(
				'stages' => Art_Forms_Stages::get_for_form( $form_id ),
				'counts' => Art_Forms_Stages::counts( $form_id ),
			)
		);
	}

	/**
	 * AJAX: delete stage.
	 */
	public static function ajax_stage_delete() {
		self::ajax_guard();

		$stage_id    = isset( $_POST['stage_id'] ) ? absint( wp_unslash( $_POST['stage_id'] ) ) : 0;
		$fallback_id = isset( $_POST['fallback_id'] ) ? absint( wp_unslash( $_POST['fallback_id'] ) ) : 0;
		$stage       = Art_Forms_Stages::get( $stage_id );
		if ( ! $stage ) {
			wp_send_json_error( array( 'message' => __( 'Этап не найден.', 'art-forms' ) ), 404 );
		}

		$result = Art_Forms_Stages::delete( $stage_id, $fallback_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		$form_id = (int) $stage['form_id'];
		wp_send_json_success(
			array(
				'stages' => Art_Forms_Stages::get_for_form( $form_id ),
				'counts' => Art_Forms_Stages::counts( $form_id ),
			)
		);
	}

	/**
	 * AJAX: reorder stages.
	 */
	public static function ajax_stage_reorder() {
		self::ajax_guard();

		$form_id = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		$ids     = isset( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_array( $ids ) ) {
			$ids = array();
		}
		$ids = array_map( 'absint', $ids );

		Art_Forms_Stages::reorder( $form_id, $ids );
		wp_send_json_success( array( 'stages' => Art_Forms_Stages::get_for_form( $form_id ) ) );
	}

	/**
	 * AJAX: bulk actions.
	 */
	public static function ajax_bulk() {
		self::ajax_guard();

		$bulk = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( (string) $_POST['bulk_action'] ) ) : '';
		$ids  = isset( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_array( $ids ) ) {
			$ids = array();
		}
		$ids = array_filter( array_map( 'absint', $ids ) );

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Выберите заявки', 'art-forms' ) ), 400 );
		}

		if ( 'delete' === $bulk ) {
			$n = Art_Forms_Submissions::bulk_delete( $ids );
			wp_send_json_success( array( 'affected' => $n ) );
		}

		if ( 'stage' === $bulk ) {
			$stage_id = isset( $_POST['stage_id'] ) ? absint( wp_unslash( $_POST['stage_id'] ) ) : 0;
			$stage    = Art_Forms_Stages::get( $stage_id );
			if ( ! $stage ) {
				wp_send_json_error( array( 'message' => __( 'Этап не найден.', 'art-forms' ) ), 404 );
			}
			$n = Art_Forms_Submissions::bulk_update_stage( $ids, $stage_id );
			wp_send_json_success( array( 'affected' => $n, 'stage_id' => $stage_id ) );
		}

		if ( 'star' === $bulk || 'unstar' === $bulk ) {
			$val = ( 'star' === $bulk ) ? 1 : 0;
			$n   = 0;
			foreach ( $ids as $id ) {
				if ( false !== Art_Forms_Submissions::set_starred( $id, $val ) ) {
					++$n;
				}
			}
			wp_send_json_success( array( 'affected' => $n ) );
		}

		wp_send_json_error( array( 'message' => __( 'Неизвестное действие.', 'art-forms' ) ), 400 );
	}

	/**
	 * AJAX: save layout / hidden columns prefs.
	 */
	public static function ajax_save_prefs() {
		self::ajax_guard();

		$form_id = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		$prefs   = array();

		if ( isset( $_POST['layout'] ) ) {
			$layout = sanitize_key( wp_unslash( (string) $_POST['layout'] ) );
			if ( in_array( $layout, array( 'table', 'board' ), true ) ) {
				$prefs['layout'] = $layout;
			}
		}

		if ( isset( $_POST['hidden_columns'] ) ) {
			$cols = wp_unslash( $_POST['hidden_columns'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( is_string( $cols ) ) {
				$decoded = json_decode( $cols, true );
				$cols    = is_array( $decoded ) ? $decoded : array();
			}
			if ( ! is_array( $cols ) ) {
				$cols = array();
			}
			$prefs['hidden_columns'] = array_map( 'sanitize_key', $cols );
		}

		if ( isset( $_POST['column_widths'] ) ) {
			$widths_raw = wp_unslash( $_POST['column_widths'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( is_string( $widths_raw ) ) {
				$decoded    = json_decode( $widths_raw, true );
				$widths_raw = is_array( $decoded ) ? $decoded : array();
			}
			if ( ! is_array( $widths_raw ) ) {
				$widths_raw = array();
			}
			$widths = array();
			foreach ( $widths_raw as $col => $w ) {
				$col = sanitize_key( (string) $col );
				$w   = absint( $w );
				if ( '' !== $col && $w >= 28 && $w <= 600 ) {
					$widths[ $col ] = $w;
				}
			}
			$prefs['column_widths'] = $widths;
		}

		if ( isset( $_POST['column_order'] ) ) {
			$order_raw = wp_unslash( $_POST['column_order'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( is_string( $order_raw ) ) {
				$decoded   = json_decode( $order_raw, true );
				$order_raw = is_array( $decoded ) ? $decoded : array();
			}
			if ( ! is_array( $order_raw ) ) {
				$order_raw = array();
			}
			$order = array();
			foreach ( $order_raw as $col ) {
				$col = sanitize_key( (string) $col );
				if ( '' !== $col && 'check' !== $col && ! in_array( $col, $order, true ) ) {
					$order[] = $col;
				}
			}
			$prefs['column_order'] = $order;
		}

		if ( isset( $_POST['column_labels'] ) ) {
			$labels_raw = wp_unslash( $_POST['column_labels'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( is_string( $labels_raw ) ) {
				$decoded    = json_decode( $labels_raw, true );
				$labels_raw = is_array( $decoded ) ? $decoded : array();
			}
			if ( ! is_array( $labels_raw ) ) {
				$labels_raw = array();
			}
			$labels = array();
			foreach ( $labels_raw as $col => $lab ) {
				$col = sanitize_key( (string) $col );
				$lab = sanitize_text_field( (string) $lab );
				if ( '' !== $col && '' !== $lab ) {
					$labels[ $col ] = self::truncate_column_label( $lab );
				}
			}
			$prefs['column_labels'] = $labels;
		}

		self::save_user_prefs( $form_id, $prefs );
		wp_send_json_success( array( 'prefs' => self::get_user_prefs( $form_id ) ) );
	}

	/**
	 * Build sortable column header HTML.
	 *
	 * @param string               $column   Column key.
	 * @param string               $label    Label.
	 * @param string               $orderby  Current orderby.
	 * @param string               $order    Current order.
	 * @param array<string, mixed> $base_args Query args.
	 * @return string
	 */
	public static function sort_header( $column, $label, $orderby, $order, array $base_args ) {
		$is_current = ( $orderby === $column );
		$next_order = ( $is_current && 'asc' === $order ) ? 'desc' : 'asc';
		$url        = add_query_arg(
			array_merge(
				$base_args,
				array(
					'orderby' => $column,
					'order'   => $next_order,
					'paged'   => 1,
				)
			),
			admin_url( 'admin.php' )
		);

		$classes = array( 'art-forms-crm-sort-link' );
		if ( $is_current ) {
			$classes[] = 'is-sorted';
			$classes[] = 'is-' . $order;
		}

		return sprintf(
			'<a class="%1$s" href="%2$s"><span class="art-forms-crm-sort-label">%3$s</span><span class="art-forms-crm-sort-indicator" aria-hidden="true"></span></a>',
			esc_attr( implode( ' ', $classes ) ),
			esc_url( $url ),
			esc_html( $label )
		);
	}

	/**
	 * Delete submission handler.
	 */
	public static function handle_delete() {
		if ( ! Art_Forms_Capabilities::can_crm() ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-forms' ) );
		}

		check_admin_referer( 'art_forms_delete_submission' );

		$id      = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$form_id = 0;
		if ( $id > 0 ) {
			$sub = Art_Forms_Submissions::get( $id );
			if ( $sub ) {
				$form_id = absint( $sub['form_id'] );
			}
			Art_Forms_Submissions::delete( $id );
		}

		$args = array(
			'page'    => 'art-forms-submissions',
			'deleted' => 1,
		);
		if ( $form_id > 0 ) {
			$args['form_id'] = $form_id;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * CSV download handler.
	 */
	public static function handle_csv() {
		if ( ! Art_Forms_Capabilities::can_crm() ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-forms' ) );
		}

		check_admin_referer( 'art_forms_export_csv' );

		Art_Forms_Csv_Export::download(
			array(
				'form_id'   => isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0,
				'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_from'] ) ) : '',
				'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_to'] ) ) : '',
				'stage_id'  => isset( $_GET['stage_id'] ) ? absint( wp_unslash( $_GET['stage_id'] ) ) : 0,
				'starred'   => isset( $_GET['starred'] ) ? absint( wp_unslash( $_GET['starred'] ) ) : 0,
				'priority'  => isset( $_GET['priority'] ) ? (string) wp_unslash( $_GET['priority'] ) : '',
				'tag'       => isset( $_GET['tag'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['tag'] ) ) : '',
			)
		);
	}

	/**
	 * Truncate text for table cells.
	 *
	 * @param string $text   Text.
	 * @param int    $length Length.
	 * @return string
	 */
	public static function truncate( $text, $length = 80 ) {
		$text = wp_strip_all_tags( (string) $text );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > $length ) {
			return mb_substr( $text, 0, $length ) . '…';
		}
		if ( strlen( $text ) > $length ) {
			return substr( $text, 0, $length ) . '…';
		}
		return $text;
	}
}
