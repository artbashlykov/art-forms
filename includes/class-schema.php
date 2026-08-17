<?php
/**
 * Form schema helpers.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Schema
 */
class Art_Forms_Schema {

	const META_KEY = '_art_forms_schema';

	const FIELD_TYPES = array(
		'text',
		'name',
		'email',
		'tel',
		'textarea',
		'select',
		'radio',
		'checkbox',
		'hidden',
		'consent',
	);

	/**
	 * JSON flags for storing/outputting plugin data (keep Cyrillic as UTF-8).
	 * Avoids \uXXXX escapes that WordPress stripslashes breaks in post meta / POST.
	 */
	const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG;

	/**
	 * Encode data as JSON for storage or HTML output.
	 *
	 * @param mixed $data Data.
	 * @return string
	 */
	public static function encode_json( $data ) {
		$json = wp_json_encode( $data, self::JSON_FLAGS );
		return is_string( $json ) ? $json : '{}';
	}

	/**
	 * Empty schema.
	 *
	 * @return array<string, mixed>
	 */
	public static function empty_schema() {
		return array(
			'version' => 1,
			'steps'   => array(
				array(
					'id'     => 'step_1',
					'title'  => __( 'Блок 1', 'art-forms' ),
					'fields' => array(),
				),
			),
		);
	}

	/**
	 * Get schema for a form.
	 *
	 * @param int $form_id Form post ID.
	 * @return array<string, mixed>
	 */
	public static function get( $form_id ) {
		$form_id = absint( $form_id );
		$raw     = get_post_meta( $form_id, self::META_KEY, true );

		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$normalized = self::normalize( $decoded );
				if ( self::schema_has_broken_unicode( $decoded ) ) {
					update_post_meta( $form_id, self::META_KEY, self::encode_json( $normalized ) );
				}
				return $normalized;
			}
		}

		if ( is_array( $raw ) ) {
			return self::normalize( $raw );
		}

		return self::empty_schema();
	}

	/**
	 * Save schema.
	 *
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $schema  Schema.
	 * @return array<string, mixed> Normalized schema.
	 */
	public static function save( $form_id, array $schema ) {
		$form_id    = absint( $form_id );
		$previous   = self::get( $form_id );
		$normalized = self::normalize( $schema, $previous );
		update_post_meta( $form_id, self::META_KEY, self::encode_json( $normalized ) );

		return $normalized;
	}

	/**
	 * Normalize and sanitize schema. Locks existing field keys unless unlock requested.
	 *
	 * @param array<string, mixed>      $schema   Incoming schema.
	 * @param array<string, mixed>|null $previous Previous schema for key locking.
	 * @return array<string, mixed>
	 */
	public static function normalize( array $schema, $previous = null ) {
		$prev_keys = array();
		if ( is_array( $previous ) ) {
			foreach ( self::flatten_fields( $previous ) as $field ) {
				if ( ! empty( $field['key'] ) ) {
					$prev_keys[ $field['key'] ] = true;
				}
			}
		}

		$steps_in = isset( $schema['steps'] ) && is_array( $schema['steps'] ) ? $schema['steps'] : array();
		$steps    = array();
		$used     = array();
		$index    = 0;

		foreach ( $steps_in as $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}

			++$index;
			$step_id = isset( $step['id'] ) ? sanitize_key( (string) $step['id'] ) : '';
			if ( '' === $step_id ) {
				$step_id = 'step_' . $index;
			}

			$title = isset( $step['title'] ) ? sanitize_text_field( self::repair_broken_unicode( (string) $step['title'] ) ) : '';
			if ( '' === $title ) {
				/* translators: %d: block number */
				$title = sprintf( __( 'Блок %d', 'art-forms' ), $index );
			}

			$fields_in = isset( $step['fields'] ) && is_array( $step['fields'] ) ? $step['fields'] : array();
			$fields    = array();

			foreach ( $fields_in as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				$normalized_field = self::normalize_field( $field, $used, $prev_keys );
				if ( null === $normalized_field ) {
					continue;
				}

				$used[ $normalized_field['key'] ] = true;
				$fields[]                         = $normalized_field;
			}

			$steps[] = array(
				'id'     => $step_id,
				'title'  => $title,
				'fields' => $fields,
			);
		}

		if ( empty( $steps ) ) {
			return self::empty_schema();
		}

		return array(
			'version' => 1,
			'steps'   => $steps,
		);
	}

	/**
	 * Normalize one field.
	 *
	 * @param array<string, mixed>  $field     Field data.
	 * @param array<string, bool>   $used_keys Already used keys in this schema.
	 * @param array<string, bool>   $prev_keys Previously saved keys (locked).
	 * @return array<string, mixed>|null
	 */
	private static function normalize_field( array $field, array $used_keys, array $prev_keys ) {
		$label = isset( $field['label'] ) ? sanitize_text_field( self::repair_broken_unicode( (string) $field['label'] ) ) : '';
		$type  = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : 'text';
		if ( 'button' === $type ) {
			return null;
		}
		if ( ! in_array( $type, self::FIELD_TYPES, true ) ) {
			$type = 'text';
		}

		$key_raw = isset( $field['key'] ) ? (string) $field['key'] : '';
		$key     = sanitize_key( $key_raw );
		$unlock  = ! empty( $field['unlock_key'] );
		$locked  = ( '' !== $key && isset( $prev_keys[ $key ] ) && ! $unlock );

		if ( '' === $key ) {
			$key = self::next_auto_key( $used_keys );
		}

		// Avoid collisions within the same save.
		if ( isset( $used_keys[ $key ] ) ) {
			if ( preg_match( '/^f\d+$/', $key ) ) {
				$key = self::next_auto_key( $used_keys );
			} else {
				$base = $key;
				$n    = 2;
				while ( isset( $used_keys[ $key ] ) ) {
					$key = $base . '_' . $n;
					++$n;
				}
			}
		}

		if ( '' === $label ) {
			$label = $key;
		}

		$required = ! empty( $field['required'] );
		if ( 'consent' === $type ) {
			$required = true;
		}
		if ( 'hidden' === $type ) {
			$required = false;
		}

		$options = array();
		if ( in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) && isset( $field['options'] ) && is_array( $field['options'] ) ) {
			foreach ( $field['options'] as $option ) {
				$option = sanitize_text_field( self::repair_broken_unicode( (string) $option ) );
				if ( '' !== $option ) {
					$options[] = $option;
				}
			}
		}

		$default = '';
		if ( isset( $field['default'] ) ) {
			$default = sanitize_text_field( self::repair_broken_unicode( (string) $field['default'] ) );
		}
		if ( in_array( $type, array( 'select', 'radio' ), true ) && '' !== $default && ! in_array( $default, $options, true ) ) {
			$default = '';
		}

		$privacy_url = '';
		$privacy_link_text = '';
		if ( 'consent' === $type ) {
			if ( isset( $field['privacy_url'] ) ) {
				$privacy_url = esc_url_raw( (string) $field['privacy_url'] );
			}
			if ( isset( $field['privacy_link_text'] ) ) {
				$privacy_link_text = sanitize_text_field( self::repair_broken_unicode( (string) $field['privacy_link_text'] ) );
			}
		}

		$result = array(
			'key'      => $key,
			'type'     => $type,
			'label'    => $label,
			'required' => (bool) $required,
			'locked'   => $locked || ( isset( $prev_keys[ $key ] ) && ! $unlock ),
		);

		if ( ! empty( $options ) ) {
			$result['options'] = $options;
		}

		if ( 'hidden' === $type || in_array( $type, array( 'select', 'radio' ), true ) ) {
			if ( '' !== $default ) {
				$result['default'] = $default;
			}
		}

		if ( 'consent' === $type ) {
			if ( '' !== $privacy_url ) {
				$result['privacy_url'] = $privacy_url;
			}
			if ( '' !== $privacy_link_text ) {
				$result['privacy_link_text'] = $privacy_link_text;
			}
		}

		return $result;
	}

	/**
	 * Whether schema strings look like stripslashes-broken \uXXXX sequences.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @return bool
	 */
	private static function schema_has_broken_unicode( array $schema ) {
		$steps = isset( $schema['steps'] ) && is_array( $schema['steps'] ) ? $schema['steps'] : array();
		foreach ( $steps as $step ) {
			if ( ! empty( $step['title'] ) && self::repair_broken_unicode( (string) $step['title'] ) !== (string) $step['title'] ) {
				return true;
			}
			$fields = isset( $step['fields'] ) && is_array( $step['fields'] ) ? $step['fields'] : array();
			foreach ( $fields as $field ) {
				if ( ! empty( $field['label'] ) && self::repair_broken_unicode( (string) $field['label'] ) !== (string) $field['label'] ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Restore labels corrupted by stripslashes on \uXXXX JSON escapes
	 * (e.g. "u0412u0430u0448u0435" → "Ваше").
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function repair_broken_unicode( $text ) {
		$text = (string) $text;
		if ( '' === $text || ! preg_match( '/u[0-9a-fA-F]{4}/', $text ) ) {
			return $text;
		}

		// Likely broken unicode if most of the alphanumeric content is uXXXX chunks.
		$without_spaces = preg_replace( '/\s+/', '', $text );
		if ( ! is_string( $without_spaces ) || ! preg_match( '/^(u[0-9a-fA-F]{4})+$/', $without_spaces ) ) {
			return $text;
		}

		$escaped = preg_replace( '/u([0-9a-fA-F]{4})/', '\\u$1', $text );
		if ( ! is_string( $escaped ) ) {
			return $text;
		}

		$decoded = json_decode( '"' . $escaped . '"' );
		if ( is_string( $decoded ) && '' !== $decoded ) {
			return $decoded;
		}

		return $text;
	}

	/**
	 * Next short auto key: f1, f2, f3…
	 *
	 * @param array<string, bool> $used_keys Already used keys.
	 * @return string
	 */
	public static function next_auto_key( array $used_keys ) {
		$n = 1;
		while ( isset( $used_keys[ 'f' . $n ] ) ) {
			++$n;
		}

		return 'f' . $n;
	}

	/**
	 * Generate key from label (legacy helper; prefer next_auto_key for new fields).
	 *
	 * @param string $label Label.
	 * @return string
	 */
	public static function key_from_label( $label ) {
		$map = array(
			'а' => 'a',
			'б' => 'b',
			'в' => 'v',
			'г' => 'g',
			'д' => 'd',
			'е' => 'e',
			'ё' => 'e',
			'ж' => 'zh',
			'з' => 'z',
			'и' => 'i',
			'й' => 'y',
			'к' => 'k',
			'л' => 'l',
			'м' => 'm',
			'н' => 'n',
			'о' => 'o',
			'п' => 'p',
			'р' => 'r',
			'с' => 's',
			'т' => 't',
			'у' => 'u',
			'ф' => 'f',
			'х' => 'h',
			'ц' => 'ts',
			'ч' => 'ch',
			'ш' => 'sh',
			'щ' => 'sch',
			'ъ' => '',
			'ы' => 'y',
			'ь' => '',
			'э' => 'e',
			'ю' => 'yu',
			'я' => 'ya',
		);

		$label = mb_strtolower( (string) $label, 'UTF-8' );
		$out   = '';
		$len   = mb_strlen( $label, 'UTF-8' );

		for ( $i = 0; $i < $len; $i++ ) {
			$ch = mb_substr( $label, $i, 1, 'UTF-8' );
			if ( isset( $map[ $ch ] ) ) {
				$out .= $map[ $ch ];
			} elseif ( preg_match( '/[a-z0-9]/', $ch ) ) {
				$out .= $ch;
			} elseif ( preg_match( '/[\s\-_]/u', $ch ) ) {
				$out .= '_';
			}
		}

		$out = preg_replace( '/_+/', '_', $out );
		$out = trim( (string) $out, '_' );
		$out = sanitize_key( $out );

		if ( strlen( $out ) > 40 ) {
			$out = substr( $out, 0, 40 );
			$out = rtrim( $out, '_' );
		}

		return $out;
	}

	/**
	 * Flatten all fields from schema.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @return array<int, array<string, mixed>>
	 */
	public static function flatten_fields( array $schema ) {
		$fields = array();
		$steps  = isset( $schema['steps'] ) && is_array( $schema['steps'] ) ? $schema['steps'] : array();

		foreach ( $steps as $step ) {
			if ( empty( $step['fields'] ) || ! is_array( $step['fields'] ) ) {
				continue;
			}
			foreach ( $step['fields'] as $field ) {
				if ( is_array( $field ) ) {
					$fields[] = $field;
				}
			}
		}

		return $fields;
	}

	/**
	 * Submit label stored as a constructor field (legacy, before settings panel).
	 *
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function legacy_button_label( $form_id ) {
		$form_id = absint( $form_id );
		if ( $form_id < 1 ) {
			return '';
		}

		$raw = get_post_meta( $form_id, self::META_KEY, true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $raw ) ) {
			return '';
		}

		$label = '';
		foreach ( self::flatten_fields( $raw ) as $field ) {
			if ( empty( $field['type'] ) || 'button' !== $field['type'] ) {
				continue;
			}
			$text = isset( $field['label'] ) ? trim( (string) $field['label'] ) : '';
			if ( '' !== $text ) {
				$label = $text;
			}
		}

		return sanitize_text_field( $label );
	}

	/**
	 * Map of field key => field definition.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields_map( array $schema ) {
		$map = array();
		foreach ( self::flatten_fields( $schema ) as $field ) {
			if ( ! empty( $field['key'] ) ) {
				$map[ $field['key'] ] = $field;
			}
		}

		return $map;
	}

	/**
	 * Honeypot field name (stable per form).
	 *
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function honeypot_name( $form_id ) {
		return 'art_forms_hp_' . absint( $form_id );
	}

	/**
	 * Full human-readable label (consent includes link text).
	 *
	 * @param array<string, mixed> $field Field.
	 * @return string
	 */
	public static function field_display_label( array $field ) {
		$key   = isset( $field['key'] ) ? (string) $field['key'] : '';
		$label = isset( $field['label'] ) ? trim( (string) $field['label'] ) : '';
		if ( '' === $label ) {
			$label = $key;
		}

		$type = isset( $field['type'] ) ? (string) $field['type'] : 'text';
		if ( 'consent' !== $type ) {
			return $label;
		}

		$link_text = isset( $field['privacy_link_text'] ) ? trim( (string) $field['privacy_link_text'] ) : '';
		$url       = self::resolve_privacy_url( $field );
		if ( '' === $link_text && '' !== $url ) {
			$link_text = __( 'политикой конфиденциальности', 'art-forms' );
		}
		if ( '' === $link_text ) {
			return $label;
		}

		if ( function_exists( 'mb_stripos' ) ) {
			$already = false !== mb_stripos( $label, $link_text, 0, 'UTF-8' );
		} else {
			$already = false !== stripos( $label, $link_text );
		}
		if ( $already ) {
			return $label;
		}

		return trim( $label . ' ' . $link_text );
	}

	/**
	 * Privacy URL for consent field (field override or global default).
	 *
	 * @param array<string, mixed> $field Field.
	 * @return string
	 */
	public static function resolve_privacy_url( array $field ) {
		$url = '';
		if ( ! empty( $field['privacy_url'] ) ) {
			$url = esc_url_raw( (string) $field['privacy_url'] );
		}
		if ( '' === $url ) {
			$url = esc_url_raw( (string) Art_Forms_Settings::get( 'default_privacy_url', '' ) );
		}

		return $url;
	}

	/**
	 * Human-readable field value for emails / admin.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Raw value.
	 * @return string
	 */
	public static function format_display_value( array $field, $value ) {
		$type = isset( $field['type'] ) ? (string) $field['type'] : 'text';

		if ( 'consent' === $type ) {
			$val = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
			if ( in_array( $val, array( '1', 'on', 'true', 'yes' ), true ) ) {
				return __( 'Да', 'art-forms' );
			}
			return __( 'Нет', 'art-forms' );
		}

		if ( is_array( $value ) ) {
			return implode( ', ', array_map( 'strval', $value ) );
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		return (string) $value;
	}

	/**
	 * Decode percent-encoded URL for readable display (Cyrillic slugs etc).
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function format_display_url( $url ) {
		$url = (string) $url;
		if ( '' === $url ) {
			return '';
		}

		$decoded = rawurldecode( $url );

		// Keep original if decode produced control characters / broken UTF-8.
		if ( ! wp_check_invalid_utf8( $decoded ) || preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $decoded ) ) {
			return $url;
		}

		return $decoded;
	}
}
