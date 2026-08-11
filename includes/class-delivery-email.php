<?php
/**
 * Email delivery channel.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Delivery_Email
 */
class Art_Forms_Delivery_Email {

	/**
	 * Deliver email for context.
	 *
	 * @param array<string, mixed> $context Context.
	 * @param array<string, mixed> $args    Args.
	 * @return array{status: string, message: string}
	 */
	public static function deliver( array $context, array $args = array() ) {
		$form_id  = isset( $context['form_id'] ) ? absint( $context['form_id'] ) : 0;
		$settings = Art_Forms_Form_Settings::get( $form_id );
		$action   = Art_Forms_Form_Actions::get_action( $settings, 'email_admin' );

		if ( ! $action ) {
			return null;
		}

		$to_list = Art_Forms_Settings::sanitize_email_list( isset( $action['email_to'] ) ? (string) $action['email_to'] : '' );

		if ( empty( $to_list ) ) {
			$global  = Art_Forms_Settings::get_all();
			$to_list = Art_Forms_Settings::sanitize_email_list( (string) $global['default_email_to'] );
		}

		if ( empty( $to_list ) ) {
			return array(
				'status'  => 'failed',
				'message' => __( 'Не указан email получателя.', 'art-forms' ),
			);
		}

		$subject = self::sanitize_email_subject(
			self::render_template( isset( $action['email_subject'] ) ? (string) $action['email_subject'] : '', $context )
		);
		$body    = self::render_template( isset( $action['email_body'] ) ? (string) $action['email_body'] : '', $context );

		if ( ! empty( $args['is_test'] ) ) {
			$subject = '[TEST] ' . $subject;
			$body    = __( 'Это тестовое письмо ART Forms.', 'art-forms' ) . "\n\n" . $body;
		}

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$sent = wp_mail( $to_list, $subject, $body, $headers );

		if ( $sent ) {
			return array(
				'status'  => 'sent',
				'message' => sprintf(
					/* translators: %s: comma-separated emails */
					__( 'Отправлено на: %s', 'art-forms' ),
					implode( ', ', $to_list )
				),
			);
		}

		return array(
			'status'  => 'failed',
			'message' => __( 'wp_mail вернул ошибку (проверьте SMTP).', 'art-forms' ),
		);
	}

	/**
	 * Deliver auto-reply email to the client.
	 *
	 * @param array<string, mixed> $context Context.
	 * @param array<string, mixed> $args    Args.
	 * @return array{status: string, message: string}|null Null when disabled (skip log).
	 */
	public static function deliver_client( array $context, array $args = array() ) {
		$form_id  = isset( $context['form_id'] ) ? absint( $context['form_id'] ) : 0;
		$settings = Art_Forms_Form_Settings::get( $form_id );
		$action   = Art_Forms_Form_Actions::get_action( $settings, 'email_client' );

		if ( ! $action ) {
			return null;
		}

		$to = isset( $context['contact_email'] ) ? sanitize_email( (string) $context['contact_email'] ) : '';
		if ( ! is_email( $to ) ) {
			return array(
				'status'  => 'failed',
				'message' => __( 'В заявке нет корректного email клиента.', 'art-forms' ),
			);
		}

		$subject = self::sanitize_email_subject(
			self::render_template( isset( $action['client_email_subject'] ) ? (string) $action['client_email_subject'] : '', $context )
		);
		$body    = self::render_template( isset( $action['client_email_body'] ) ? (string) $action['client_email_body'] : '', $context );

		if ( ! empty( $args['is_test'] ) ) {
			$subject = '[TEST] ' . $subject;
			$body    = __( 'Это тестовое письмо клиенту ART Forms.', 'art-forms' ) . "\n\n" . $body;
		}

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$sent    = wp_mail( $to, $subject, $body, $headers );

		if ( $sent ) {
			return array(
				'status'  => 'sent',
				'message' => sprintf(
					/* translators: %s: client email */
					__( 'Письмо клиенту отправлено на: %s', 'art-forms' ),
					$to
				),
			);
		}

		return array(
			'status'  => 'failed',
			'message' => __( 'Не удалось отправить письмо клиенту (проверьте SMTP).', 'art-forms' ),
		);
	}

	/**
	 * Strip CR/LF from email subject to prevent header injection.
	 *
	 * @param string $subject Subject.
	 * @return string
	 */
	private static function sanitize_email_subject( $subject ) {
		$subject = (string) $subject;
		$subject = str_replace( array( "\r", "\n", '%0a', '%0d', '%0A', '%0D' ), ' ', $subject );
		$subject = preg_replace( '/\s+/u', ' ', $subject );
		$subject = is_string( $subject ) ? trim( $subject ) : '';

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $subject, 0, 200 );
		}

		return substr( $subject, 0, 200 );
	}

	/**
	 * Replace placeholders in template.
	 *
	 * @param string               $template Template.
	 * @param array<string, mixed> $context  Context.
	 * @return string
	 */
	public static function render_template( $template, array $context ) {
		$payload    = isset( $context['payload'] ) && is_array( $context['payload'] ) ? $context['payload'] : array();
		$labeled    = isset( $context['labeled_fields'] ) && is_array( $context['labeled_fields'] ) ? $context['labeled_fields'] : array();
		$schema     = isset( $context['schema'] ) && is_array( $context['schema'] ) ? $context['schema'] : array();
		$fields_map = Art_Forms_Schema::fields_map( $schema );

		$all_fields_lines = array();
		foreach ( $labeled as $label => $value ) {
			$all_fields_lines[] = $label . ': ' . self::stringify_value( $value );
		}
		$all_fields = implode( "\n", $all_fields_lines );

		$page_url = isset( $context['page_url'] ) ? Art_Forms_Schema::format_display_url( (string) $context['page_url'] ) : '';
		$referrer = isset( $context['referrer'] ) ? Art_Forms_Schema::format_display_url( (string) $context['referrer'] ) : '';

		$replacements = array(
			'{form_title}'     => isset( $context['form_title'] ) ? (string) $context['form_title'] : '',
			'{submission_id}'  => isset( $context['submission_id'] ) ? (string) $context['submission_id'] : '',
			'{email}'          => isset( $context['contact_email'] ) ? (string) $context['contact_email'] : '',
			'{phone}'          => isset( $context['contact_phone'] ) ? (string) $context['contact_phone'] : '',
			'{page_url}'       => $page_url,
			'{referrer}'       => $referrer,
			'{all_fields}'     => $all_fields,
			'{utm_source}'     => isset( $context['utm_source'] ) ? (string) $context['utm_source'] : '',
			'{utm_medium}'     => isset( $context['utm_medium'] ) ? (string) $context['utm_medium'] : '',
			'{utm_campaign}'   => isset( $context['utm_campaign'] ) ? (string) $context['utm_campaign'] : '',
		);

		foreach ( $payload as $key => $value ) {
			$field = isset( $fields_map[ $key ] ) ? $fields_map[ $key ] : array( 'key' => $key, 'type' => 'text' );
			$replacements[ '{field:' . $key . '}' ] = Art_Forms_Schema::format_display_value( $field, $value );
		}

		return strtr( $template, $replacements );
	}

	/**
	 * Stringify field value.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function stringify_value( $value ) {
		if ( is_array( $value ) ) {
			return implode( ', ', array_map( 'strval', $value ) );
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		return (string) $value;
	}

	/**
	 * Build fake context for test email.
	 *
	 * @param int $form_id Form ID.
	 * @return array<string, mixed>
	 */
	public static function build_test_context( $form_id ) {
		$form_id = absint( $form_id );
		$schema  = Art_Forms_Schema::get( $form_id );
		$payload = array();
		$labeled = array();

		foreach ( Art_Forms_Schema::flatten_fields( $schema ) as $field ) {
			$key   = isset( $field['key'] ) ? $field['key'] : '';
			$label = isset( $field['label'] ) ? $field['label'] : $key;
			$type  = isset( $field['type'] ) ? $field['type'] : 'text';
			if ( '' === $key ) {
				continue;
			}

			switch ( $type ) {
				case 'email':
					$value = 'test@example.com';
					break;
				case 'tel':
					$value = '+79990001122';
					break;
				case 'consent':
					$value = '1';
					break;
				case 'checkbox':
					$value = isset( $field['options'][0] ) ? array( $field['options'][0] ) : array( 'yes' );
					break;
				case 'select':
				case 'radio':
					$value = isset( $field['options'][0] ) ? $field['options'][0] : 'test';
					break;
				case 'hidden':
					$value = isset( $field['default'] ) ? $field['default'] : 'hidden-test';
					break;
				default:
					$value = 'Тест';
			}

			$payload[ $key ]   = $value;
			$labeled[ $label ] = Art_Forms_Schema::format_display_value( $field, $value );
		}

		$email = '';
		$phone = '';
		foreach ( Art_Forms_Schema::flatten_fields( $schema ) as $field ) {
			if ( 'email' === $field['type'] && isset( $payload[ $field['key'] ] ) ) {
				$email = (string) $payload[ $field['key'] ];
			}
			if ( 'tel' === $field['type'] && isset( $payload[ $field['key'] ] ) ) {
				$phone = (string) $payload[ $field['key'] ];
			}
		}

		return array(
			'submission_id'  => 0,
			'form_id'        => $form_id,
			'form_title'     => get_the_title( $form_id ),
			'created_at'     => current_time( 'mysql', true ),
			'status'         => 'new',
			'user_id'        => get_current_user_id(),
			'ip'             => '',
			'contact_email'  => $email,
			'contact_phone'  => $phone,
			'page_url'       => home_url( '/' ),
			'referrer'       => '',
			'utm_source'     => 'test',
			'utm_medium'     => 'admin',
			'utm_campaign'   => 'art-forms-test',
			'utm_content'    => '',
			'utm_term'       => '',
			'payload'        => $payload,
			'labeled_fields' => $labeled,
			'schema'         => $schema,
			'meta'           => array( 'test' => true ),
		);
	}
}
