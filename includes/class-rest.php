<?php
/**
 * REST API for form submit.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Rest
 */
class Art_Forms_Rest {

	const REST_NAMESPACE = 'art-forms/v1';

	/**
	 * Register routes.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_submit' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Max length for single-line field values.
	 */
	const MAX_TEXT_LENGTH = 500;

	/**
	 * Max length for textarea values.
	 */
	const MAX_TEXTAREA_LENGTH = 5000;

	/**
	 * Max items in a checkbox multi-value.
	 */
	const MAX_CHECKBOX_ITEMS = 50;

	/**
	 * Soft cap for encoded payload size (bytes).
	 */
	const MAX_PAYLOAD_BYTES = 100000;

	/**
	 * Handle form submit.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_submit( WP_REST_Request $request ) {
		$form_id = absint( $request->get_param( 'form_id' ) );
		if ( $form_id <= 0 ) {
			return new WP_Error( 'art_forms_missing_form', __( 'Не указан form_id.', 'art-forms' ), array( 'status' => 400 ) );
		}

		$post = get_post( $form_id );
		if ( ! $post || Art_Forms_Post_Types::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error( 'art_forms_invalid_form', __( 'Форма не найдена.', 'art-forms' ), array( 'status' => 404 ) );
		}

		if ( self::is_rate_limited( $form_id ) ) {
			return new WP_Error( 'art_forms_rate_limited', __( 'Слишком много отправок. Попробуйте позже.', 'art-forms' ), array( 'status' => 429 ) );
		}

		$schema  = Art_Forms_Schema::get( $form_id );
		$fields  = $request->get_param( 'fields' );
		$meta_in = $request->get_param( 'meta' );

		if ( ! is_array( $fields ) ) {
			$fields = array();
		}
		if ( ! is_array( $meta_in ) ) {
			$meta_in = array();
		}

		// Reject nested objects / unexpected structures early.
		if ( self::contains_disallowed_structure( $fields ) ) {
			return new WP_Error( 'art_forms_invalid_payload', __( 'Некорректные данные формы.', 'art-forms' ), array( 'status' => 400 ) );
		}

		if ( Art_Forms_Settings::honeypot_enabled() ) {
			$honeypot = Art_Forms_Schema::honeypot_name( $form_id );
			$hp_value = '';
			if ( isset( $fields[ $honeypot ] ) ) {
				$hp_value = is_scalar( $fields[ $honeypot ] ) ? (string) $fields[ $honeypot ] : '';
				unset( $fields[ $honeypot ] );
			} elseif ( null !== $request->get_param( $honeypot ) ) {
				$hp_value = (string) $request->get_param( $honeypot );
			}

			if ( '' !== trim( $hp_value ) ) {
				// Silent success for bots.
				$settings = Art_Forms_Form_Settings::get( $form_id );
				return rest_ensure_response(
					array(
						'success'         => true,
						'submission_id'   => 0,
						'success_message' => $settings['success_message'],
						'redirect_url'    => Art_Forms_Form_Actions::redirect_url( $settings ),
						'redirect_delay'  => Art_Forms_Form_Actions::redirect_delay( $settings ),
					)
				);
			}
		} else {
			$honeypot = Art_Forms_Schema::honeypot_name( $form_id );
			unset( $fields[ $honeypot ] );
		}

		$validated = self::validate_fields( $schema, $fields );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$encoded = wp_json_encode( $validated );
		if ( is_string( $encoded ) && strlen( $encoded ) > self::MAX_PAYLOAD_BYTES ) {
			return new WP_Error( 'art_forms_payload_too_large', __( 'Слишком большой объём данных.', 'art-forms' ), array( 'status' => 413 ) );
		}

		$payload = $validated;
		$email   = '';
		$phone   = '';

		foreach ( Art_Forms_Schema::flatten_fields( $schema ) as $field ) {
			$key = $field['key'];
			if ( ! isset( $payload[ $key ] ) ) {
				continue;
			}
			if ( 'email' === $field['type'] && '' === $email ) {
				$email = sanitize_email( is_scalar( $payload[ $key ] ) ? (string) $payload[ $key ] : '' );
			}
			if ( 'tel' === $field['type'] && '' === $phone ) {
				$phone = sanitize_text_field( is_scalar( $payload[ $key ] ) ? (string) $payload[ $key ] : '' );
			}
		}

		$utm_keys = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' );
		$utm      = array();
		foreach ( $utm_keys as $utm_key ) {
			$utm[ $utm_key ] = isset( $meta_in[ $utm_key ] )
				? self::sanitize_limited_text( (string) $meta_in[ $utm_key ], 200 )
				: '';
		}

		$meta = array(
			'user_agent' => isset( $meta_in['user_agent'] )
				? self::sanitize_limited_text( (string) $meta_in['user_agent'], 500 )
				: '',
		);

		$page_url = isset( $meta_in['page_url'] ) ? esc_url_raw( (string) $meta_in['page_url'] ) : '';
		$referrer = isset( $meta_in['referrer'] ) ? esc_url_raw( (string) $meta_in['referrer'] ) : '';
		if ( strlen( $page_url ) > 2000 ) {
			$page_url = substr( $page_url, 0, 2000 );
		}
		if ( strlen( $referrer ) > 2000 ) {
			$referrer = substr( $referrer, 0, 2000 );
		}

		$submission_id = Art_Forms_Submissions::insert(
			array(
				'form_id'       => $form_id,
				'created_at'    => current_time( 'mysql', true ),
				'status'        => 'new',
				'user_id'       => get_current_user_id(),
				'ip'            => self::client_ip(),
				'contact_email' => $email,
				'contact_phone' => $phone,
				'page_url'      => $page_url,
				'referrer'      => $referrer,
				'utm_source'    => $utm['utm_source'],
				'utm_medium'    => $utm['utm_medium'],
				'utm_campaign'  => $utm['utm_campaign'],
				'utm_content'   => $utm['utm_content'],
				'utm_term'      => $utm['utm_term'],
				'payload'       => $payload,
				'meta'          => $meta,
			)
		);

		if ( ! $submission_id ) {
			return new WP_Error( 'art_forms_save_failed', __( 'Не удалось сохранить ответ.', 'art-forms' ), array( 'status' => 500 ) );
		}

		$context = Art_Forms_Submissions::build_context( $submission_id );
		if ( is_array( $context ) ) {
			/**
			 * Fires after a submission is stored.
			 *
			 * @param int                  $submission_id Submission ID.
			 * @param array<string, mixed> $context       Delivery context.
			 */
			do_action( 'art_forms_submission_created', $submission_id, $context );

			Art_Forms_Delivery::deliver_all( $context );
			Art_Forms_Submissions::update_status( $submission_id, 'processed' );

			if ( 'contacts' === Art_Forms_Settings::get( 'store_payload', 'full' ) ) {
				Art_Forms_Submissions::update_payload( $submission_id, array() );
			}
		}

		$settings = Art_Forms_Form_Settings::get( $form_id );

		return rest_ensure_response(
			array(
				'success'         => true,
				'submission_id'   => $submission_id,
				'success_message' => $settings['success_message'],
				'redirect_url'    => Art_Forms_Form_Actions::redirect_url( $settings ),
				'redirect_delay'  => Art_Forms_Form_Actions::redirect_delay( $settings ),
			)
		);
	}

	/**
	 * Detect nested objects / deep structures that should not be accepted.
	 *
	 * @param mixed $value Value.
	 * @param int   $depth Depth.
	 * @return bool
	 */
	private static function contains_disallowed_structure( $value, $depth = 0 ) {
		if ( $depth > 2 ) {
			return true;
		}

		if ( ! is_array( $value ) ) {
			return false;
		}

		foreach ( $value as $item ) {
			if ( is_array( $item ) ) {
				// One level of list (checkbox) is OK; nested arrays/objects are not.
				foreach ( $item as $nested ) {
					if ( is_array( $nested ) || is_object( $nested ) ) {
						return true;
					}
				}
			} elseif ( is_object( $item ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sanitize a short text value with hard length cap.
	 *
	 * @param string $text   Raw text.
	 * @param int    $max    Max length.
	 * @param bool   $multiline Allow newlines.
	 * @return string
	 */
	private static function sanitize_limited_text( $text, $max, $multiline = false ) {
		$text = (string) $text;
		$text = wp_check_invalid_utf8( $text ) ? $text : '';

		if ( $multiline ) {
			$text = sanitize_textarea_field( $text );
			$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text );
		} else {
			$text = sanitize_text_field( $text );
			$text = preg_replace( '/[\x00-\x1F\x7F]/u', '', $text );
		}

		$text = is_string( $text ) ? $text : '';
		$max  = max( 1, absint( $max ) );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, $max );
		}

		return substr( $text, 0, $max );
	}

	/**
	 * Allowed option values for choice fields.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return array<int, string>
	 */
	private static function allowed_options( array $field ) {
		$options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
		$out     = array();
		foreach ( $options as $option ) {
			if ( is_scalar( $option ) ) {
				$out[] = (string) $option;
			}
		}
		return $out;
	}

	/**
	 * Validate and sanitize fields against schema.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @param array<string, mixed> $fields Incoming fields.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function validate_fields( array $schema, array $fields ) {
		$clean  = array();
		$errors = array();

		foreach ( Art_Forms_Schema::flatten_fields( $schema ) as $field ) {
			$key  = $field['key'];
			$type = $field['type'];
			$raw  = isset( $fields[ $key ] ) ? $fields[ $key ] : null;

			if ( 'checkbox' === $type && null === $raw && isset( $fields[ $key . '[]' ] ) ) {
				$raw = $fields[ $key . '[]' ];
			}

			$is_empty = ( null === $raw || '' === $raw || array() === $raw );

			if ( ! empty( $field['required'] ) && $is_empty ) {
				$errors[] = sprintf(
					/* translators: %s: field label */
					__( 'Заполните поле «%s».', 'art-forms' ),
					$field['label']
				);
				continue;
			}

			if ( $is_empty ) {
				continue;
			}

			switch ( $type ) {
				case 'email':
					if ( ! is_scalar( $raw ) ) {
						$errors[] = sprintf(
							/* translators: %s: field label */
							__( 'Некорректный email в поле «%s».', 'art-forms' ),
							$field['label']
						);
						break;
					}
					$email = sanitize_email( self::sanitize_limited_text( (string) $raw, self::MAX_TEXT_LENGTH ) );
					if ( ! is_email( $email ) ) {
						$errors[] = sprintf(
							/* translators: %s: field label */
							__( 'Некорректный email в поле «%s».', 'art-forms' ),
							$field['label']
						);
					} else {
						$clean[ $key ] = $email;
					}
					break;

				case 'consent':
					if ( ! is_scalar( $raw ) ) {
						$errors[] = sprintf(
							/* translators: %s: field label */
							__( 'Необходимо согласие: «%s».', 'art-forms' ),
							$field['label']
						);
						break;
					}
					$val = strtolower( trim( (string) $raw ) );
					if ( empty( $field['required'] ) || in_array( $val, array( '1', 'on', 'true', 'yes' ), true ) ) {
						$clean[ $key ] = '1';
					} else {
						$errors[] = sprintf(
							/* translators: %s: field label */
							__( 'Необходимо согласие: «%s».', 'art-forms' ),
							$field['label']
						);
					}
					break;

				case 'checkbox':
					$allowed = self::allowed_options( $field );
					$items   = is_array( $raw ) ? $raw : array( $raw );
					$picked  = array();
					foreach ( $items as $item ) {
						if ( ! is_scalar( $item ) ) {
							continue;
						}
						$item = self::sanitize_limited_text( (string) $item, self::MAX_TEXT_LENGTH );
						if ( '' === $item ) {
							continue;
						}
						if ( ! empty( $allowed ) && ! in_array( $item, $allowed, true ) ) {
							continue;
						}
						$picked[] = $item;
						if ( count( $picked ) >= self::MAX_CHECKBOX_ITEMS ) {
							break;
						}
					}
					if ( ! empty( $field['required'] ) && empty( $picked ) ) {
						$errors[] = sprintf(
							/* translators: %s: field label */
							__( 'Заполните поле «%s».', 'art-forms' ),
							$field['label']
						);
					} elseif ( ! empty( $picked ) ) {
						$clean[ $key ] = array_values( array_unique( $picked ) );
					}
					break;

				case 'select':
				case 'radio':
					if ( ! is_scalar( $raw ) ) {
						$errors[] = sprintf(
							/* translators: %s: field label */
							__( 'Некорректное значение в поле «%s».', 'art-forms' ),
							$field['label']
						);
						break;
					}
					$val     = self::sanitize_limited_text( (string) $raw, self::MAX_TEXT_LENGTH );
					$allowed = self::allowed_options( $field );
					if ( ! empty( $allowed ) && ! in_array( $val, $allowed, true ) ) {
						$errors[] = sprintf(
							/* translators: %s: field label */
							__( 'Некорректное значение в поле «%s».', 'art-forms' ),
							$field['label']
						);
					} else {
						$clean[ $key ] = $val;
					}
					break;

				case 'textarea':
					if ( ! is_scalar( $raw ) ) {
						$errors[] = sprintf(
							/* translators: %s: field label */
							__( 'Некорректное значение в поле «%s».', 'art-forms' ),
							$field['label']
						);
						break;
					}
					$clean[ $key ] = self::sanitize_limited_text( (string) $raw, self::MAX_TEXTAREA_LENGTH, true );
					break;

				case 'hidden':
				case 'tel':
				case 'text':
				default:
					if ( ! is_scalar( $raw ) ) {
						$errors[] = sprintf(
							/* translators: %s: field label */
							__( 'Некорректное значение в поле «%s».', 'art-forms' ),
							$field['label']
						);
						break;
					}
					$clean[ $key ] = self::sanitize_limited_text( (string) $raw, self::MAX_TEXT_LENGTH );
					break;
			}
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'art_forms_validation', implode( ' ', $errors ), array( 'status' => 400, 'errors' => $errors ) );
		}

		return $clean;
	}

	/**
	 * Rate limit by IP + form (configurable).
	 *
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	private static function is_rate_limited( $form_id ) {
		if ( empty( Art_Forms_Settings::get( 'rate_limit_enabled' ) ) ) {
			return false;
		}

		$max    = max( 1, absint( Art_Forms_Settings::get( 'rate_limit_max', 5 ) ) );
		$window = max( 1, absint( Art_Forms_Settings::get( 'rate_limit_window', 10 ) ) );
		$ip     = self::client_ip();
		$key    = 'art_forms_rl_' . md5( $form_id . '|' . $ip );
		$count  = (int) get_transient( $key );

		if ( $count >= $max ) {
			return true;
		}

		set_transient( $key, $count + 1, $window * MINUTE_IN_SECONDS );

		return false;
	}

	/**
	 * Client IP.
	 *
	 * @return string
	 */
	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		return $ip;
	}
}
