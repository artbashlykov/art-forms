<?php
/**
 * Export form code and ChatGPT prompt.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Export
 */
class Art_Forms_Export {

	/**
	 * HTML skeleton for ChatGPT / page paste.
	 *
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function form_code( $form_id ) {
		$form_id   = absint( $form_id );
		$schema    = Art_Forms_Schema::get( $form_id );
		$honeypot  = Art_Forms_Schema::honeypot_name( $form_id );
		$title     = get_the_title( $form_id );
		$lines     = array();

		$lines[] = '<!-- ART Forms: do not change data-art-form-id or field name attributes -->';
		$lines[] = '<!-- Form: ' . $title . ' (ID ' . $form_id . ') -->';
		$lines[] = '<form data-art-form-id="' . esc_attr( (string) $form_id ) . '" method="post" action="#">';

		foreach ( $schema['steps'] as $step_index => $step ) {
			$step_id = isset( $step['id'] ) ? $step['id'] : ( 'step_' . ( $step_index + 1 ) );
			$lines[] = '  <div data-art-form-step="' . esc_attr( $step_id ) . '">';
			if ( ! empty( $step['title'] ) ) {
				$lines[] = '    <h3>' . esc_html( $step['title'] ) . '</h3>';
			}

			$fields = isset( $step['fields'] ) && is_array( $step['fields'] ) ? $step['fields'] : array();
			foreach ( $fields as $field ) {
				$lines = array_merge( $lines, self::field_html_lines( $field ) );
			}

			$lines[] = '  </div>';
		}

		if ( Art_Forms_Settings::honeypot_enabled() ) {
			$lines[] = '  <div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">';
			$lines[] = '    <label>' . esc_html__( 'Оставьте пустым', 'art-forms' );
			$lines[] = '      <input type="text" name="' . esc_attr( $honeypot ) . '" value="" tabindex="-1" autocomplete="off" />';
			$lines[] = '    </label>';
			$lines[] = '  </div>';
		}
		$lines[] = '  <div data-art-form-success></div>';
		$lines[] = '  <button type="submit">' . esc_html__( 'Отправить', 'art-forms' ) . '</button>';
		$lines[] = '</form>';

		return implode( "\n", $lines );
	}

	/**
	 * ChatGPT prompt text.
	 *
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function chatgpt_prompt( $form_id ) {
		$form_id = absint( $form_id );
		$schema  = Art_Forms_Schema::get( $form_id );
		$title   = get_the_title( $form_id );
		$code    = self::form_code( $form_id );

		$field_lines = array();
		foreach ( Art_Forms_Schema::flatten_fields( $schema ) as $field ) {
			$req           = ! empty( $field['required'] ) ? __( 'обязательное', 'art-forms' ) : __( 'необязательное', 'art-forms' );
			$field_lines[] = '- ' . $field['key'] . ' (' . $field['type'] . ', ' . $req . '): ' . $field['label'];
		}

		$parts   = array();
		$parts[] = 'Сделай красивый дизайн для формы WordPress-плагина ART Forms.';
		$parts[] = 'Верни ОДИН блок: HTML + CSS + JS (можно в одном фрагменте).';
		$parts[] = 'Нельзя менять: атрибут data-art-form-id у <form>, атрибуты name у полей' . ( Art_Forms_Settings::honeypot_enabled() ? ', honeypot-поле' : '' ) . ', логику отправки ART Forms (её обеспечит сайт).';
		$parts[] = 'Можно менять внешний вид, вёрстку, анимации, многошаговый UI (разбивку на экраны делает дизайн).';
		$parts[] = 'Форма: «' . $title . '» (ID ' . $form_id . ').';
		$parts[] = 'Поля:';
		$parts[] = implode( "\n", $field_lines );
		$parts[] = '';
		$parts[] = 'Исходный каркас:';
		$parts[] = $code;

		return implode( "\n", $parts );
	}

	/**
	 * HTML lines for one field.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return array<int, string>
	 */
	private static function field_html_lines( array $field ) {
		$key      = isset( $field['key'] ) ? (string) $field['key'] : '';
		$type     = isset( $field['type'] ) ? (string) $field['type'] : 'text';
		$label    = isset( $field['label'] ) ? (string) $field['label'] : $key;
		$required = ! empty( $field['required'] ) ? ' required' : '';
		$req_attr = ! empty( $field['required'] ) ? ' required' : '';
		$lines    = array();

		if ( 'hidden' === $type ) {
			$default = isset( $field['default'] ) ? (string) $field['default'] : '';
			$lines[] = '    <input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $default ) . '" />';
			return $lines;
		}

		$lines[] = '    <div class="art-forms-field" data-field-key="' . esc_attr( $key ) . '">';
		$lines[] = '      <label for="art-forms-' . esc_attr( $key ) . '">' . esc_html( $label ) . ( $required ? ' *' : '' ) . '</label>';

		switch ( $type ) {
			case 'textarea':
				$lines[] = '      <textarea id="art-forms-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '"' . $req_attr . '></textarea>';
				break;
			case 'select':
				$lines[] = '      <select id="art-forms-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '"' . $req_attr . '>';
				$options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
				$default = isset( $field['default'] ) ? (string) $field['default'] : '';
				foreach ( $options as $option ) {
					$selected = ( $default === (string) $option ) ? ' selected' : '';
					$lines[]  = '        <option value="' . esc_attr( $option ) . '"' . $selected . '>' . esc_html( $option ) . '</option>';
				}
				$lines[] = '      </select>';
				break;
			case 'radio':
				$options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
				$default = isset( $field['default'] ) ? (string) $field['default'] : '';
				foreach ( $options as $i => $option ) {
					$id       = 'art-forms-' . $key . '-' . $i;
					$checked  = ( $default === (string) $option ) ? ' checked' : '';
					$lines[]  = '      <label><input type="radio" id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $option ) . '"' . $req_attr . $checked . ' /> ' . esc_html( $option ) . '</label>';
				}
				break;
			case 'checkbox':
				$options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
				if ( empty( $options ) ) {
					$lines[] = '      <label><input type="checkbox" id="art-forms-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="1"' . $req_attr . ' /> ' . esc_html( $label ) . '</label>';
				} else {
					foreach ( $options as $i => $option ) {
						$id = 'art-forms-' . $key . '-' . $i;
						$lines[] = '      <label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '[]" value="' . esc_attr( $option ) . '" /> ' . esc_html( $option ) . '</label>';
					}
				}
				break;
			case 'consent':
				$privacy_url = Art_Forms_Schema::resolve_privacy_url( $field );
				$link_text   = isset( $field['privacy_link_text'] ) ? (string) $field['privacy_link_text'] : '';
				if ( '' !== $privacy_url ) {
					if ( '' === $link_text ) {
						$link_text = __( 'политикой конфиденциальности', 'art-forms' );
					}
					$consent_label = sprintf(
						'%1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a>',
						esc_html( $label ),
						esc_url( $privacy_url ),
						esc_html( $link_text )
					);
				} else {
					$consent_label = esc_html( $label );
				}
				$lines[] = '      <label><input type="checkbox" id="art-forms-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="1"' . $req_attr . ' /> ' . $consent_label . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
				break;
			case 'email':
			case 'tel':
			case 'text':
			default:
				$input_type = in_array( $type, array( 'email', 'tel', 'text' ), true ) ? $type : 'text';
				$lines[]    = '      <input type="' . esc_attr( $input_type ) . '" id="art-forms-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '"' . $req_attr . ' />';
				break;
		}

		$lines[] = '    </div>';

		return $lines;
	}
}
