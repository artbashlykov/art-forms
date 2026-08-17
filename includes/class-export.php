<?php
/**
 * Export form code and Нейронки prompt.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Export
 */
class Art_Forms_Export {

	/**
	 * HTML skeleton for Нейронки / page paste.
	 *
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function form_code( $form_id ) {
		$form_id   = absint( $form_id );
		$schema    = Art_Forms_Schema::get( $form_id );
		$settings  = Art_Forms_Form_Settings::get( $form_id );
		$honeypot  = Art_Forms_Schema::honeypot_name( $form_id );
		$title     = get_the_title( $form_id );
		$submit    = Art_Forms_Form_Settings::submit_label( $form_id );
		$intro_h   = isset( $settings['intro_title'] ) ? trim( (string) $settings['intro_title'] ) : '';
		$intro_p   = isset( $settings['intro_description'] ) ? trim( (string) $settings['intro_description'] ) : '';
		$lines     = array();

		$lines[] = '<!-- ART Forms: do not change data-art-form-id or field name attributes -->';
		$lines[] = '<!-- Form: ' . $title . ' (ID ' . $form_id . ') -->';
		$lines[] = '<form data-art-form-id="' . esc_attr( (string) $form_id ) . '" method="post" action="#">';

		if ( '' !== $intro_h || '' !== $intro_p ) {
			$lines[] = '  <div data-art-form-intro>';
			if ( '' !== $intro_h ) {
				$lines[] = '    <h2>' . esc_html( $intro_h ) . '</h2>';
			}
			if ( '' !== $intro_p ) {
				$lines[] = '    <p>' . nl2br( esc_html( $intro_p ), false ) . '</p>';
			}
			$lines[] = '  </div>';
		}

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
		$lines[] = '  <button type="submit">' . esc_html( $submit ) . '</button>';
		$lines[] = '</form>';

		return implode( "\n", $lines );
	}

	/**
	 * Нейронки prompt text.
	 *
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function design_prompt( $form_id ) {
		$form_id       = absint( $form_id );
		$schema        = Art_Forms_Schema::get( $form_id );
		$title         = get_the_title( $form_id );
		$code          = self::form_code( $form_id );
		$settings      = Art_Forms_Form_Settings::get( $form_id );
		$submit_label  = Art_Forms_Form_Settings::submit_label( $form_id );
		$intro_h       = isset( $settings['intro_title'] ) ? trim( (string) $settings['intro_title'] ) : '';
		$intro_p       = isset( $settings['intro_description'] ) ? trim( (string) $settings['intro_description'] ) : '';
		$success       = isset( $settings['success_message'] ) ? trim( (string) $settings['success_message'] ) : '';
		$style_brief   = isset( $settings['style_brief'] ) ? trim( (string) $settings['style_brief'] ) : '';
		$steps         = isset( $schema['steps'] ) && is_array( $schema['steps'] ) ? $schema['steps'] : array();
		$step_count    = count( $steps );

		$parts   = array();
		$parts[] = 'Сделай готовый дизайн формы WordPress-плагина ART Forms.';
		$parts[] = 'Верни ОДИН фрагмент: HTML + CSS + JS. Его вставляют на страницу как есть.';
		$parts[] = 'Форма должна выглядеть законченной: заголовок и описание (если есть), все поля, кнопка отправки, сообщение после успеха.';
		$parts[] = '';
		$parts[] = 'Нельзя менять:';
		$parts[] = '- атрибут data-art-form-id у <form>';
		$parts[] = '- атрибуты name у полей (и data-field-key)';
		if ( Art_Forms_Settings::honeypot_enabled() ) {
			$parts[] = '- honeypot-поле ' . Art_Forms_Schema::honeypot_name( $form_id ) . ' (оставь скрытым)';
		}
		$parts[] = '- тексты: заголовок, описание, подписи полей, варианты ответов, текст кнопки, сообщение после отправки';
		$parts[] = '- логику отправки: её обеспечивает сайт (не пиши свой fetch/ajax на другой URL)';
		$parts[] = '';
		$parts[] = 'Можно менять внешний вид, вёрстку, анимации. Если блоков несколько — сделай пошаговый квиз: один экран = один data-art-form-step, на последнем шаге кнопка отправки.';
		$parts[] = 'Сообщение об успехе показывай в элементе data-art-form-success (плагин подставит текст после отправки).';
		$parts[] = '';
		$parts[] = 'Форма в админке: «' . $title . '» (ID ' . $form_id . ').';
		$parts[] = '';

		$parts[] = '=== Заголовок и описание (начало формы, data-art-form-intro) ===';
		if ( '' === $intro_h && '' === $intro_p ) {
			$parts[] = 'Не заданы — блок в начале формы не обязателен.';
		} else {
			$parts[] = 'Покажи этот блок в начале формы. Текст не меняй.';
			if ( '' !== $intro_h ) {
				$parts[] = 'Заголовок: «' . $intro_h . '»';
			}
			if ( '' !== $intro_p ) {
				$parts[] = 'Описание: «' . $intro_p . '»';
			}
		}
		$parts[] = '';

		$parts[] = '=== Поля и шаги (' . $step_count . ') ===';
		if ( 0 === $step_count ) {
			$parts[] = 'Шагов пока нет.';
		} else {
			foreach ( $steps as $index => $step ) {
				$step_title = isset( $step['title'] ) ? trim( (string) $step['title'] ) : '';
				$step_id    = isset( $step['id'] ) ? (string) $step['id'] : ( 'step_' . ( $index + 1 ) );
				$parts[]    = 'Шаг ' . ( $index + 1 ) . ' (data-art-form-step="' . $step_id . '")' . ( '' !== $step_title ? ': «' . $step_title . '»' : '' );
				$fields     = isset( $step['fields'] ) && is_array( $step['fields'] ) ? $step['fields'] : array();
				if ( empty( $fields ) ) {
					$parts[] = '- полей нет';
					continue;
				}
				foreach ( $fields as $field ) {
					$parts[] = '- ' . self::prompt_field_line( $field );
				}
			}
		}
		$parts[] = '';

		$parts[] = '=== Кнопка отправки ===';
		$parts[] = 'Текст кнопки: «' . $submit_label . '». type="submit". Текст не меняй.';
		$parts[] = '';

		$parts[] = '=== Сообщение после отправки ===';
		if ( '' !== $success ) {
			$parts[] = 'Текст: «' . $success . '». Показывать в data-art-form-success после успешной отправки.';
		} else {
			$parts[] = 'Текст не задан — оставь пустой контейнер data-art-form-success.';
		}
		$parts[] = '';

		$parts[] = '=== Цвета, шрифты и стили ===';
		if ( '' !== $style_brief ) {
			$parts[] = $style_brief;
		} else {
			$parts[] = 'Отдельных указаний нет — сделай аккуратный современный дизайн, удобный на телефоне.';
		}
		$parts[] = '';
		$parts[] = '=== Исходный HTML-каркас ===';
		$parts[] = 'От него отталкивайся. Не удаляй обязательные атрибуты.';
		$parts[] = $code;

		return implode( "\n", $parts );
	}

	/**
	 * One field line for the design prompt.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return string
	 */
	private static function prompt_field_line( array $field ) {
		$key      = isset( $field['key'] ) ? (string) $field['key'] : '';
		$type     = isset( $field['type'] ) ? (string) $field['type'] : 'text';
		$label    = Art_Forms_Schema::field_display_label( $field );
		$required = ! empty( $field['required'] ) ? __( 'обязательное', 'art-forms' ) : __( 'необязательное', 'art-forms' );
		$line     = $key . ' — ' . self::prompt_type_label( $type ) . ', ' . $required . ': «' . $label . '»';

		if ( in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) && ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
			$opts = array();
			foreach ( $field['options'] as $option ) {
				$option = trim( (string) $option );
				if ( '' !== $option ) {
					$opts[] = $option;
				}
			}
			if ( ! empty( $opts ) ) {
				$line .= '; варианты: ' . implode( ', ', $opts );
			}
		}

		if ( 'consent' === $type ) {
			$url = Art_Forms_Schema::resolve_privacy_url( $field );
			if ( '' !== $url ) {
				$line .= '; ссылка на политику: ' . $url;
			}
		}

		if ( 'hidden' === $type && isset( $field['default'] ) && '' !== (string) $field['default'] ) {
			$line .= '; значение: ' . (string) $field['default'];
		}

		return $line;
	}

	/**
	 * Human type name for the prompt.
	 *
	 * @param string $type Field type.
	 * @return string
	 */
	private static function prompt_type_label( $type ) {
		$labels = array(
			'text'     => __( 'текст', 'art-forms' ),
			'name'     => __( 'имя', 'art-forms' ),
			'email'    => __( 'email', 'art-forms' ),
			'tel'      => __( 'телефон', 'art-forms' ),
			'textarea' => __( 'многострочный текст', 'art-forms' ),
			'select'   => __( 'выпадающий список', 'art-forms' ),
			'radio'    => __( 'один вариант', 'art-forms' ),
			'checkbox' => __( 'несколько вариантов', 'art-forms' ),
			'hidden'   => __( 'скрытое поле', 'art-forms' ),
			'consent'  => __( 'согласие на ПДн', 'art-forms' ),
		);

		return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
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
			case 'name':
				$lines[] = '      <input type="text" id="art-forms-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" autocomplete="name"' . $req_attr . ' />';
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
