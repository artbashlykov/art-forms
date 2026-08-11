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
		$honeypot = Art_Forms_Schema::honeypot_name( $form_id );

		// Strip script/style for attribute search (keep markup).
		$html = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $code );
		$html = preg_replace( '#<style\b[^>]*>.*?</style>#is', '', (string) $html );

		if ( ! preg_match( '/<form\b[^>]*>/i', (string) $html ) ) {
			$errors[] = __( 'Не найден тег <form>.', 'art-forms' );
		}

		$form_id_ok = false;
		if ( preg_match_all( '/<form\b[^>]*>/i', (string) $html, $form_tags ) ) {
			foreach ( $form_tags[0] as $tag ) {
				if ( preg_match( '/data-art-form-id\s*=\s*([\'"])\s*' . preg_quote( (string) $form_id, '/' ) . '\s*\1/i', $tag ) ) {
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

		$names = self::extract_names( (string) $html );

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
			$key = isset( $field['key'] ) ? $field['key'] : '';
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

		return array_values( array_unique( $names ) );
	}
}
