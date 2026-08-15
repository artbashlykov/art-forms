<?php
/**
 * Export / import a form pack between sites (schema, settings, CRM stages).
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Form_Pack
 */
class Art_Forms_Form_Pack {

	const FORMAT         = 'art-forms-pack';
	const FORMAT_VERSION = 1;
	const MAX_BYTES      = 524288;
	const NOTICE_TTL     = 180;

	/**
	 * Build a portable pack for a form.
	 *
	 * @param int $form_id Form post ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function export_pack( $form_id ) {
		$form_id = absint( $form_id );
		$post    = get_post( $form_id );
		if ( ! $post || Art_Forms_Post_Types::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'art_forms_pack_missing', __( 'Форма не найдена.', 'art-forms' ) );
		}

		$stages_out = array();
		foreach ( Art_Forms_Stages::get_for_form( $form_id ) as $stage ) {
			$stages_out[] = array(
				'slug'       => (string) $stage['slug'],
				'title'      => (string) $stage['title'],
				'color'      => (string) $stage['color'],
				'position'   => (int) $stage['position'],
				'is_default' => ! empty( $stage['is_default'] ) ? 1 : 0,
			);
		}

		return array(
			'format'         => self::FORMAT,
			'format_version' => self::FORMAT_VERSION,
			'exported_at'    => gmdate( 'c' ),
			'plugin_version' => ART_FORMS_VERSION,
			'source'         => array(
				'form_id'  => $form_id,
				'site_url' => untrailingslashit( (string) site_url() ),
				'home_url' => untrailingslashit( (string) home_url() ),
			),
			'form'           => array(
				'title'    => (string) $post->post_title,
				'schema'   => Art_Forms_Schema::get( $form_id ),
				'settings' => Art_Forms_Form_Settings::get( $form_id ),
				'stages'   => $stages_out,
			),
		);
	}

	/**
	 * Download JSON pack.
	 *
	 * @param int $form_id Form post ID.
	 */
	public static function send_download( $form_id ) {
		$pack = self::export_pack( $form_id );
		if ( is_wp_error( $pack ) ) {
			wp_die( esc_html( $pack->get_error_message() ) );
		}

		$filename = sprintf( 'art-form-%d-%s.json', absint( $form_id ), gmdate( 'Ymd' ) );
		$json     = wp_json_encode(
			$pack,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		if ( ! is_string( $json ) ) {
			wp_die( esc_html__( 'Не удалось сформировать файл экспорта.', 'art-forms' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . (string) strlen( $json ) );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download body.
		exit;
	}

	/**
	 * Import pack into a new form (never reuses source post ID).
	 *
	 * @param array<string, mixed> $pack Decoded pack.
	 * @return array<string, mixed>|WP_Error { form_id, title, source_form_id, warnings }
	 */
	public static function import_pack( array $pack ) {
		$validated = self::validate_pack( $pack );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$form_in = $validated['form'];
		$source  = $validated['source'];
		$warnings = array();

		$schema   = isset( $form_in['schema'] ) && is_array( $form_in['schema'] ) ? $form_in['schema'] : array();
		$settings = isset( $form_in['settings'] ) && is_array( $form_in['settings'] ) ? $form_in['settings'] : array();
		$stages   = isset( $form_in['stages'] ) && is_array( $form_in['stages'] ) ? $form_in['stages'] : array();

		$rewritten = false;
		$schema    = self::rewrite_site_urls( $schema, $source, $rewritten );
		$settings  = self::rewrite_site_urls( $settings, $source, $rewritten );

		$title = isset( $form_in['title'] ) ? sanitize_text_field( (string) $form_in['title'] ) : '';
		if ( '' === $title ) {
			$title = __( 'Импортированная форма', 'art-forms' );
		}

		$new_id = wp_insert_post(
			array(
				'post_type'   => Art_Forms_Post_Types::POST_TYPE,
				'post_title'  => $title,
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}
		$new_id = absint( $new_id );
		if ( $new_id <= 0 ) {
			return new WP_Error( 'art_forms_pack_insert', __( 'Не удалось создать форму.', 'art-forms' ) );
		}

		Art_Forms_Schema::save( $new_id, $schema );
		Art_Forms_Form_Settings::save( $new_id, $settings );
		self::import_stages( $new_id, $stages );

		$saved_schema   = Art_Forms_Schema::get( $new_id );
		$saved_settings = Art_Forms_Form_Settings::get( $new_id );

		$source_id = isset( $source['form_id'] ) ? absint( $source['form_id'] ) : 0;

		$warnings[] = sprintf(
			/* translators: 1: new form id, 2: old form id */
			__( 'Новый ID формы: %1$d (на исходном сайте был %2$d). В вёрстке замените data-art-form-id на новый ID, иначе заявки уйдут не туда.', 'art-forms' ),
			$new_id,
			$source_id
		);
		$warnings[] = sprintf(
			/* translators: 1: old honeypot name, 2: new honeypot name */
			__( 'Имя honeypot-поля зависит от ID: было %1$s, стало %2$s. Обновите скрытое поле в HTML или заново скопируйте код формы.', 'art-forms' ),
			Art_Forms_Schema::honeypot_name( $source_id ),
			Art_Forms_Schema::honeypot_name( $new_id )
		);
		$warnings[] = __( 'Ключи полей (name / {field:ключ} в письмах) сохранены. Заявки, комментарии и история CRM не переносятся.', 'art-forms' );

		if ( $rewritten ) {
			$warnings[] = __( 'Ссылки исходного сайта (редирект, политика, тексты писем) заменены на адрес этого сайта, если совпадал домен.', 'art-forms' );
		}

		$leftover = self::leftover_source_urls( $saved_schema, $saved_settings, $source );
		if ( ! empty( $leftover ) ) {
			$warnings[] = __( 'Остались ссылки на исходный сайт — проверьте политику конфиденциальности, редирект и тексты писем.', 'art-forms' );
		}

		if ( self::consent_missing_privacy_url( $saved_schema ) ) {
			$warnings[] = __( 'У поля согласия нет своей ссылки на политику — на этом сайте будет использован адрес из настроек ART Forms.', 'art-forms' );
		}

		if ( self::settings_have_email_to( $saved_settings ) ) {
			$warnings[] = __( 'Адреса получателей писем перенесены как есть. На новом сайте проверьте «Письмо себе».', 'art-forms' );
		}

		$warnings[] = __( 'Этапы CRM созданы заново (новые числовые ID). Слаги этапов сохранены.', 'art-forms' );

		return array(
			'form_id'        => $new_id,
			'title'          => $title,
			'source_form_id' => $source_id,
			'warnings'       => $warnings,
		);
	}

	/**
	 * Parse uploaded JSON file into a pack array.
	 *
	 * @param array<string, mixed> $file One $_FILES entry.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function parse_upload( array $file ) {
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'art_forms_pack_file', __( 'Выберите JSON-файл экспорта формы.', 'art-forms' ) );
		}

		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $error ) {
			return new WP_Error( 'art_forms_pack_upload', __( 'Не удалось загрузить файл.', 'art-forms' ) );
		}

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		if ( $size <= 0 || $size > self::MAX_BYTES ) {
			return new WP_Error( 'art_forms_pack_size', __( 'Файл слишком большой или пустой (максимум 512 КБ).', 'art-forms' ) );
		}

		$raw = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local upload tmp.
		if ( ! is_string( $raw ) || '' === $raw ) {
			return new WP_Error( 'art_forms_pack_empty', __( 'Файл пустой или не читается.', 'art-forms' ) );
		}

		if ( 0 === strpos( $raw, "\xEF\xBB\xBF" ) ) {
			$raw = substr( $raw, 3 );
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'art_forms_pack_json', __( 'Это не JSON-пакет ART Forms.', 'art-forms' ) );
		}

		return $decoded;
	}

	/**
	 * Store import result for admin notice.
	 *
	 * @param array<string, mixed> $result Result.
	 */
	public static function store_notice( array $result ) {
		set_transient( self::notice_key(), $result, self::NOTICE_TTL );
	}

	/**
	 * Consume import notice once.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function consume_notice() {
		$key = self::notice_key();
		$row = get_transient( $key );
		if ( ! is_array( $row ) ) {
			return null;
		}
		delete_transient( $key );

		return $row;
	}

	/**
	 * Store error message for list page.
	 *
	 * @param string $message Message.
	 */
	public static function store_error( $message ) {
		set_transient( self::error_key(), (string) $message, self::NOTICE_TTL );
	}

	/**
	 * Consume import error.
	 *
	 * @return string
	 */
	public static function consume_error() {
		$key = self::error_key();
		$msg = get_transient( $key );
		delete_transient( $key );

		return is_string( $msg ) ? $msg : '';
	}

	/**
	 * @param array<string, mixed> $pack Pack.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function validate_pack( array $pack ) {
		$format = isset( $pack['format'] ) ? (string) $pack['format'] : '';
		if ( self::FORMAT !== $format ) {
			return new WP_Error( 'art_forms_pack_format', __( 'Файл не является пакетом формы ART Forms.', 'art-forms' ) );
		}

		$version = isset( $pack['format_version'] ) ? (int) $pack['format_version'] : 0;
		if ( $version < 1 || $version > self::FORMAT_VERSION ) {
			return new WP_Error( 'art_forms_pack_version', __( 'Эта версия пакета не поддерживается. Обновите плагин ART Forms.', 'art-forms' ) );
		}

		$form = isset( $pack['form'] ) && is_array( $pack['form'] ) ? $pack['form'] : array();
		if ( empty( $form ) ) {
			return new WP_Error( 'art_forms_pack_form', __( 'В файле нет данных формы.', 'art-forms' ) );
		}

		$schema = isset( $form['schema'] ) && is_array( $form['schema'] ) ? $form['schema'] : array();
		$steps  = isset( $schema['steps'] ) && is_array( $schema['steps'] ) ? $schema['steps'] : array();
		if ( empty( $steps ) ) {
			return new WP_Error( 'art_forms_pack_schema', __( 'В пакете нет схемы полей.', 'art-forms' ) );
		}

		$source = isset( $pack['source'] ) && is_array( $pack['source'] ) ? $pack['source'] : array();

		return array(
			'form'   => $form,
			'source' => $source,
		);
	}

	/**
	 * Recreate CRM stages from pack (no numeric IDs).
	 *
	 * @param int                  $form_id Form ID.
	 * @param array<int, mixed>    $stages  Stages.
	 */
	private static function import_stages( $form_id, array $stages ) {
		$form_id = absint( $form_id );
		if ( $form_id <= 0 ) {
			return;
		}

		$clean = array();
		foreach ( $stages as $stage ) {
			if ( ! is_array( $stage ) ) {
				continue;
			}
			$clean[] = $stage;
		}

		if ( empty( $clean ) ) {
			Art_Forms_Stages::ensure_defaults( $form_id );
			return;
		}

		usort(
			$clean,
			static function ( $a, $b ) {
				$pa = isset( $a['position'] ) ? (int) $a['position'] : 0;
				$pb = isset( $b['position'] ) ? (int) $b['position'] : 0;
				return $pa <=> $pb;
			}
		);

		$have_default = false;
		$first_id     = 0;
		$position     = 0;

		foreach ( $clean as $stage ) {
			$is_default = ! empty( $stage['is_default'] ) && ! $have_default;
			if ( $is_default ) {
				$have_default = true;
			}

			$id = Art_Forms_Stages::insert(
				array(
					'form_id'    => $form_id,
					'slug'       => isset( $stage['slug'] ) ? (string) $stage['slug'] : '',
					'title'      => isset( $stage['title'] ) ? (string) $stage['title'] : '',
					'color'      => isset( $stage['color'] ) ? (string) $stage['color'] : '#2271b1',
					'position'   => $position,
					'is_default' => $is_default ? 1 : 0,
				)
			);
			++$position;

			if ( $id && $first_id <= 0 ) {
				$first_id = (int) $id;
			}
		}

		if ( ! $have_default && $first_id > 0 ) {
			Art_Forms_Stages::update( $first_id, array( 'is_default' => 1 ) );
		}
	}

	/**
	 * Replace source site/home URLs with current home_url.
	 *
	 * @param mixed                $value      Value.
	 * @param array<string, mixed> $source     Source meta.
	 * @param bool                 $rewritten  Whether any replacement happened.
	 * @return mixed
	 */
	private static function rewrite_site_urls( $value, array $source, &$rewritten ) {
		$to = untrailingslashit( (string) home_url() );
		$map = self::source_url_bases( $source );
		if ( empty( $map ) || '' === $to ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::rewrite_site_urls( $item, $source, $rewritten );
			}
			return $value;
		}

		if ( ! is_string( $value ) || '' === $value ) {
			return $value;
		}

		$out = $value;
		foreach ( $map as $from ) {
			if ( $from === $to ) {
				continue;
			}
			if ( false !== strpos( $out, $from ) ) {
				$out        = str_replace( $from, $to, $out );
				$rewritten  = true;
			}
		}

		return $out;
	}

	/**
	 * Source site bases, longest first.
	 *
	 * @param array<string, mixed> $source Source.
	 * @return array<int, string>
	 */
	private static function source_url_bases( array $source ) {
		$bases = array();
		foreach ( array( 'site_url', 'home_url' ) as $key ) {
			if ( empty( $source[ $key ] ) ) {
				continue;
			}
			$base = untrailingslashit( esc_url_raw( (string) $source[ $key ] ) );
			if ( '' !== $base ) {
				$bases[] = $base;
			}
		}
		$bases = array_values( array_unique( $bases ) );
		usort(
			$bases,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);

		return $bases;
	}

	/**
	 * Hosts from source URLs still present after rewrite.
	 *
	 * @param array<string, mixed> $schema   Schema.
	 * @param array<string, mixed> $settings Settings.
	 * @param array<string, mixed> $source   Source.
	 * @return array<int, string>
	 */
	private static function leftover_source_urls( array $schema, array $settings, array $source ) {
		$blob  = wp_json_encode( array( $schema, $settings ) );
		$found = array();
		if ( ! is_string( $blob ) ) {
			return $found;
		}

		$current = untrailingslashit( (string) home_url() );
		foreach ( self::source_url_bases( $source ) as $from ) {
			if ( $from === $current ) {
				continue;
			}
			if ( false !== strpos( $blob, $from ) ) {
				$found[] = $from;
			}
		}

		return $found;
	}

	/**
	 * @param array<string, mixed> $settings Settings.
	 * @return bool
	 */
	private static function settings_have_email_to( array $settings ) {
		$actions = isset( $settings['actions'] ) && is_array( $settings['actions'] ) ? $settings['actions'] : array();
		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			if ( ! empty( $action['email_to'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $schema Schema.
	 * @return bool
	 */
	private static function consent_missing_privacy_url( array $schema ) {
		foreach ( Art_Forms_Schema::flatten_fields( $schema ) as $field ) {
			if ( isset( $field['type'] ) && 'consent' === $field['type'] && empty( $field['privacy_url'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return string
	 */
	private static function notice_key() {
		return 'art_forms_import_notice_' . get_current_user_id();
	}

	/**
	 * @return string
	 */
	private static function error_key() {
		return 'art_forms_import_error_' . get_current_user_id();
	}
}
