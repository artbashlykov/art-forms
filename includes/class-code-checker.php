<?php
/**
 * Validate pasted HTML/CSS/JS against form schema.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Code_Checker
 */
class Art_Forms_Code_Checker {

	/**
	 * Check pasted code.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $code    Pasted markup.
	 * @return array{ok: bool, errors: array<int, string>, warnings: array<int, string>}
	 */
	public static function check( $form_id, $code ) {
		$form_id  = absint( $form_id );
		$errors   = array();
		$warnings = array();
		$code     = (string) $code;

		if ( '' === trim( $code ) ) {
			return array(
				'ok'       => false,
				'errors'   => array( __( 'Вставьте код для проверки.', 'art-forms' ) ),
				'warnings' => array(),
			);
		}

		$schema   = Art_Forms_Schema::get( $form_id );
		$settings = Art_Forms_Form_Settings::get( $form_id );
		$honeypot = Art_Forms_Schema::honeypot_name( $form_id );

		// Strip script/style for attribute search (keep markup).
		$html = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $code );
		$html = preg_replace( '#<style\b[^>]*>.*?</style>#is', '', (string) $html );
		$html = is_string( $html ) ? $html : '';
		$plain = self::plain_text( $html );

		if ( ! preg_match( '/<form\b[^>]*>/i', $html ) ) {
			$errors[] = __( 'Не найден тег <form>.', 'art-forms' );
		}

		$form_id_ok = false;
		if ( preg_match_all( '/<form\b[^>]*>/i', $html, $form_tags ) ) {
			foreach ( $form_tags[0] as $tag ) {
				if ( preg_match( '/data-art-form-id\s*=\s*([\'"])\s*' . preg_quote( (string) $form_id, '/' ) . '\s*\1/i', $tag ) ) {
					$form_id_ok = true;
					break;
				}
				if ( preg_match( '/data-art-form-id\s*=\s*' . preg_quote( (string) $form_id, '/' ) . '(?=\s|>)/i', $tag ) ) {
					$form_id_ok = true;
					break;
				}
			}
		}

		if ( ! $form_id_ok ) {
			$errors[] = sprintf(
				/* translators: %d: form ID */
				__( 'У <form> нет data-art-form-id="%d" (или ID не совпадает).', 'art-forms' ),
				$form_id
			);
		}

		$names = self::extract_names( $html );

		if ( Art_Forms_Settings::honeypot_enabled() ) {
			if ( ! in_array( $honeypot, $names, true ) ) {
				$errors[] = sprintf(
					/* translators: %s: honeypot field name */
					__( 'Не найдено honeypot-поле name="%s".', 'art-forms' ),
					$honeypot
				);
			}
		}

		$fields = Art_Forms_Schema::flatten_fields( $schema );
		if ( empty( $fields ) ) {
			$warnings[] = __( 'В схеме формы пока нет полей.', 'art-forms' );
		}

		foreach ( $fields as $field ) {
			$key  = isset( $field['key'] ) ? (string) $field['key'] : '';
			$type = isset( $field['type'] ) ? (string) $field['type'] : 'text';
			if ( '' === $key ) {
				continue;
			}

			$found = in_array( $key, $names, true ) || in_array( $key . '[]', $names, true );
			if ( ! $found ) {
				$errors[] = sprintf(
					/* translators: 1: field key, 2: field label */
					__( 'Не найдено поле name="%1$s" (%2$s).', 'art-forms' ),
					$key,
					isset( $field['label'] ) ? $field['label'] : $key
				);
				continue;
			}

			if ( in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) && ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
				foreach ( $field['options'] as $option ) {
					$option = trim( (string) $option );
					if ( '' === $option ) {
						continue;
					}
					if ( ! self::contains_text( $plain, $option ) ) {
						$warnings[] = sprintf(
							/* translators: 1: option, 2: field label */
							__( 'В коде нет варианта «%1$s» (поле «%2$s»).', 'art-forms' ),
							$option,
							isset( $field['label'] ) ? $field['label'] : $key
						);
					}
				}
			}
		}

		$intro_h = isset( $settings['intro_title'] ) ? trim( (string) $settings['intro_title'] ) : '';
		$intro_p = isset( $settings['intro_description'] ) ? trim( (string) $settings['intro_description'] ) : '';
		if ( '' !== $intro_h || '' !== $intro_p ) {
			if ( ! preg_match( '/data-art-form-intro/i', $html ) ) {
				$warnings[] = __( 'Нет блока data-art-form-intro для заголовка и описания формы.', 'art-forms' );
			}
			if ( '' !== $intro_h && ! self::contains_text( $plain, $intro_h ) ) {
				$errors[] = sprintf(
					/* translators: %s: intro title */
					__( 'В коде нет заголовка формы «%s».', 'art-forms' ),
					$intro_h
				);
			}
			if ( '' !== $intro_p && ! self::contains_text( $plain, $intro_p ) ) {
				$errors[] = sprintf(
					/* translators: %s: intro description */
					__( 'В коде нет описания формы «%s».', 'art-forms' ),
					$intro_p
				);
			}
		}

		$submit_label = Art_Forms_Form_Settings::submit_label( $form_id );
		if ( ! self::has_submit_control( $html ) ) {
			$errors[] = __( 'Не найдена кнопка отправки (button/input type="submit").', 'art-forms' );
		} elseif ( '' !== $submit_label && ! self::submit_text_matches( $html, $submit_label ) ) {
			$warnings[] = sprintf(
				/* translators: %s: submit label */
				__( 'Текст кнопки отправки отличается от «%s».', 'art-forms' ),
				$submit_label
			);
		}

		if ( ! preg_match( '/data-art-form-success/i', $html ) ) {
			$warnings[] = __( 'Нет контейнера data-art-form-success для сообщения после отправки.', 'art-forms' );
		}

		$steps = isset( $schema['steps'] ) && is_array( $schema['steps'] ) ? $schema['steps'] : array();
		if ( count( $steps ) > 1 ) {
			foreach ( $steps as $index => $step ) {
				$step_id = isset( $step['id'] ) ? (string) $step['id'] : ( 'step_' . ( $index + 1 ) );
				if ( ! preg_match( '/data-art-form-step\s*=\s*([\'"])\s*' . preg_quote( $step_id, '/' ) . '\s*\1/i', $html ) ) {
					$warnings[] = sprintf(
						/* translators: %s: step id */
						__( 'Нет экрана data-art-form-step="%s".', 'art-forms' ),
						$step_id
					);
				}
			}
		}

		return array(
			'ok'       => empty( $errors ),
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Extract name attributes from HTML.
	 *
	 * @param string $html HTML.
	 * @return array<int, string>
	 */
	private static function extract_names( $html ) {
		$names = array();
		if ( preg_match_all( '/\bname\s*=\s*([\'"])(.*?)\1/i', $html, $matches ) ) {
			foreach ( $matches[2] as $name ) {
				$name = html_entity_decode( $name, ENT_QUOTES, 'UTF-8' );
				$name = trim( $name );
				if ( '' !== $name ) {
					$names[] = $name;
				}
			}
		}
		if ( preg_match_all( '/\bname\s*=\s*([^\s>\'"]+)/i', $html, $bare ) ) {
			foreach ( $bare[1] as $name ) {
				$name = html_entity_decode( $name, ENT_QUOTES, 'UTF-8' );
				$name = trim( $name );
				if ( '' !== $name ) {
					$names[] = $name;
				}
			}
		}

		return array_values( array_unique( $names ) );
	}

	/**
	 * Visible text of markup.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private static function plain_text( $html ) {
		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );

		return is_string( $text ) ? trim( $text ) : '';
	}

	/**
	 * Whether haystack contains needle, ignoring extra whitespace and case.
	 *
	 * @param string $haystack Plain text.
	 * @param string $needle   Needle.
	 * @return bool
	 */
	private static function contains_text( $haystack, $needle ) {
		$needle = preg_replace( '/\s+/u', ' ', trim( (string) $needle ) );
		if ( ! is_string( $needle ) || '' === $needle ) {
			return true;
		}

		$hay = function_exists( 'mb_strtolower' ) ? mb_strtolower( $haystack, 'UTF-8' ) : strtolower( $haystack );
		$nee = function_exists( 'mb_strtolower' ) ? mb_strtolower( $needle, 'UTF-8' ) : strtolower( $needle );

		if ( function_exists( 'mb_strpos' ) ) {
			return false !== mb_strpos( $hay, $nee, 0, 'UTF-8' );
		}

		return false !== strpos( $hay, $nee );
	}

	/**
	 * Whether markup has a submit control.
	 *
	 * @param string $html HTML.
	 * @return bool
	 */
	private static function has_submit_control( $html ) {
		if ( preg_match( '/<input\b[^>]*type\s*=\s*([\'"]?)submit\1/i', $html ) ) {
			return true;
		}
		if ( preg_match( '/<button\b[^>]*type\s*=\s*([\'"]?)submit\1/i', $html ) ) {
			return true;
		}
		if ( preg_match_all( '/<button\b([^>]*)>/i', $html, $matches ) ) {
			foreach ( $matches[1] as $attrs ) {
				if ( ! preg_match( '/\btype\s*=/i', $attrs ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Whether a submit control uses the expected label.
	 *
	 * @param string $html  HTML.
	 * @param string $label Expected label.
	 * @return bool
	 */
	private static function submit_text_matches( $html, $label ) {
		$label = trim( $label );
		if ( '' === $label ) {
			return true;
		}

		$found = array();
		if ( preg_match_all( '/<button\b([^>]*)>(.*?)<\/button>/is', $html, $buttons, PREG_SET_ORDER ) ) {
			foreach ( $buttons as $button ) {
				$attrs = $button[1];
				$type  = 'submit';
				if ( preg_match( '/\btype\s*=\s*([\'"]?)([^\'"\s>]+)\1/i', $attrs, $tm ) ) {
					$type = strtolower( $tm[2] );
				}
				if ( 'submit' !== $type ) {
					continue;
				}
				$found[] = self::plain_text( $button[2] );
			}
		}
		if ( preg_match_all( '/<input\b[^>]*type\s*=\s*([\'"]?)submit\1[^>]*>/i', $html, $inputs ) ) {
			foreach ( $inputs[0] as $tag ) {
				$value = '';
				if ( preg_match( '/\bvalue\s*=\s*([\'"])(.*?)\1/i', $tag, $vm ) ) {
					$value = html_entity_decode( $vm[2], ENT_QUOTES, 'UTF-8' );
				}
				$found[] = trim( $value );
			}
		}

		foreach ( $found as $text ) {
			if ( self::contains_text( $text, $label ) || self::contains_text( $label, $text ) ) {
				return true;
			}
		}

		return false;
	}
}
