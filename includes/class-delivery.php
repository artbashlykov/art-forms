<?php
/**
 * Delivery orchestrator.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Delivery
 */
class Art_Forms_Delivery {

	/**
	 * Registered channel callbacks.
	 *
	 * @return array<string, callable>
	 */
	public static function channels() {
		$channels = array(
			'email'        => array( 'Art_Forms_Delivery_Email', 'deliver' ),
			'email_client' => array( 'Art_Forms_Delivery_Email', 'deliver_client' ),
		);

		/**
		 * Filter delivery channels.
		 *
		 * @param array<string, callable> $channels Channel id => callable( $context, $args ).
		 */
		return apply_filters( 'art_forms_delivery_channels', $channels );
	}

	/**
	 * Run all channels for a submission context.
	 *
	 * @param array<string, mixed> $context Delivery context.
	 * @param array<string, mixed> $args    Extra args (is_test etc).
	 */
	public static function deliver_all( array $context, array $args = array() ) {
		foreach ( self::channels() as $channel => $callback ) {
			if ( ! is_callable( $callback ) ) {
				continue;
			}

			$result = call_user_func( $callback, $context, $args );
			if ( null === $result ) {
				continue;
			}
			if ( ! is_array( $result ) ) {
				$result = array(
					'status'  => 'failed',
					'message' => __( 'Канал вернул некорректный ответ.', 'art-forms' ),
				);
			}

			Art_Forms_Delivery_Log::insert(
				array(
					'submission_id' => isset( $context['submission_id'] ) ? absint( $context['submission_id'] ) : 0,
					'form_id'       => isset( $context['form_id'] ) ? absint( $context['form_id'] ) : 0,
					'channel'       => sanitize_key( (string) $channel ),
					'status'        => isset( $result['status'] ) ? $result['status'] : 'failed',
					'message'       => isset( $result['message'] ) ? $result['message'] : '',
					'is_test'       => ! empty( $args['is_test'] ),
				)
			);

			if (
				empty( $args['is_test'] )
				&& isset( $result['status'] )
				&& 'failed' === $result['status']
			) {
				self::notify_failure( $context, (string) $channel, isset( $result['message'] ) ? (string) $result['message'] : '' );
			}
		}
	}

	/**
	 * Email admin about failed delivery.
	 *
	 * @param array<string, mixed> $context Context.
	 * @param string               $channel Channel.
	 * @param string               $message Error message.
	 */
	private static function notify_failure( array $context, $channel, $message ) {
		if ( empty( Art_Forms_Settings::get( 'delivery_fail_notify' ) ) ) {
			return;
		}

		$to = sanitize_email( (string) Art_Forms_Settings::get( 'delivery_fail_email', '' ) );
		if ( ! is_email( $to ) ) {
			$to = sanitize_email( (string) get_option( 'admin_email', '' ) );
		}
		if ( ! is_email( $to ) ) {
			return;
		}

		$form_title = isset( $context['form_title'] ) ? (string) $context['form_title'] : '';
		$sub_id     = isset( $context['submission_id'] ) ? (string) absint( $context['submission_id'] ) : '0';

		$subject = sprintf(
			/* translators: %s: form title */
			__( '[ART Forms] Ошибка доставки: %s', 'art-forms' ),
			$form_title ? $form_title : __( 'форма', 'art-forms' )
		);

		$body = implode(
			"\n",
			array(
				__( 'Не удалось отправить уведомление о заявке.', 'art-forms' ),
				'',
				sprintf(
					/* translators: %s: form title */
					__( 'Форма: %s', 'art-forms' ),
					$form_title
				),
				sprintf(
					/* translators: %s: submission id */
					__( 'ID ответа: %s', 'art-forms' ),
					$sub_id
				),
				sprintf(
					/* translators: %s: channel */
					__( 'Канал: %s', 'art-forms' ),
					$channel
				),
				sprintf(
					/* translators: %s: error message */
					__( 'Сообщение: %s', 'art-forms' ),
					$message
				),
			)
		);

		wp_mail( $to, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
	}
}
