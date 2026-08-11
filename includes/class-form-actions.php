<?php
/**
 * After-submit actions registry and helpers.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Form_Actions
 */
class Art_Forms_Form_Actions {

	const REDIRECT_DELAY_SEC = 3;

	/**
	 * Available action type definitions (for UI + validation).
	 *
	 * @return array<string, array{label: string, hint: string}>
	 */
	public static function definitions() {
		return array(
			'redirect'     => array(
				'label' => __( 'Редирект после отправки', 'art-forms' ),
				'hint'  => __( 'Показать сообщение, затем через 3 секунды перейти по URL.', 'art-forms' ),
			),
			'email_admin'  => array(
				'label' => __( 'Письмо на свою почту', 'art-forms' ),
				'hint'  => __( 'Уведомление вам о новой заявке.', 'art-forms' ),
			),
			'email_client' => array(
				'label' => __( 'Письмо на почту клиента', 'art-forms' ),
				'hint'  => __( 'Автоответ на email из заявки. Нужно поле Email в форме.', 'art-forms' ),
			),
		);
	}

	/**
	 * Default action config by type.
	 *
	 * @param string $type Action type.
	 * @return array<string, mixed>|null
	 */
	public static function default_action( $type ) {
		$type   = sanitize_key( $type );
		$global = Art_Forms_Settings::get_all();

		switch ( $type ) {
			case 'redirect':
				return array(
					'type'         => 'redirect',
					'redirect_url' => '',
				);
			case 'email_admin':
				return array(
					'type'          => 'email_admin',
					'email_to'      => (string) $global['default_email_to'],
					'email_subject' => (string) $global['default_email_subject'],
					'email_body'    => (string) $global['default_email_body'],
				);
			case 'email_client':
				return array(
					'type'                 => 'email_client',
					'client_email_subject' => Art_Forms_Form_Settings::default_client_email_subject(),
					'client_email_body'    => Art_Forms_Form_Settings::default_client_email_body(),
				);
			default:
				return null;
		}
	}

	/**
	 * Sanitize list of actions from POST / storage.
	 *
	 * @param mixed $actions Raw actions.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sanitize_list( $actions ) {
		if ( ! is_array( $actions ) ) {
			return array();
		}

		$defs   = self::definitions();
		$clean  = array();
		$seen   = array();

		foreach ( $actions as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$type = isset( $row['type'] ) ? sanitize_key( (string) $row['type'] ) : '';
			if ( '' === $type || ! isset( $defs[ $type ] ) || isset( $seen[ $type ] ) ) {
				continue;
			}

			$base = self::default_action( $type );
			if ( ! is_array( $base ) ) {
				continue;
			}

			$seen[ $type ] = true;
			$item          = $base;

			switch ( $type ) {
				case 'redirect':
					$item['redirect_url'] = isset( $row['redirect_url'] ) ? esc_url_raw( (string) $row['redirect_url'] ) : '';
					break;
				case 'email_admin':
					if ( isset( $row['email_to'] ) ) {
						$emails           = Art_Forms_Settings::sanitize_email_list( (string) $row['email_to'] );
						$item['email_to'] = implode( ', ', $emails );
					}
					if ( isset( $row['email_subject'] ) ) {
						$item['email_subject'] = sanitize_text_field( (string) $row['email_subject'] );
					}
					if ( isset( $row['email_body'] ) ) {
						$item['email_body'] = Art_Forms_Form_Settings::repair_stripped_newlines(
							sanitize_textarea_field( (string) $row['email_body'] )
						);
					}
					break;
				case 'email_client':
					if ( isset( $row['client_email_subject'] ) ) {
						$item['client_email_subject'] = sanitize_text_field( (string) $row['client_email_subject'] );
					}
					if ( isset( $row['client_email_body'] ) ) {
						$item['client_email_body'] = Art_Forms_Form_Settings::repair_stripped_newlines(
							sanitize_textarea_field( (string) $row['client_email_body'] )
						);
					}
					break;
			}

			$clean[] = $item;
		}

		return $clean;
	}

	/**
	 * Find action by type.
	 *
	 * @param array<string, mixed> $settings Form settings.
	 * @param string               $type     Action type.
	 * @return array<string, mixed>|null
	 */
	public static function get_action( array $settings, $type ) {
		$type = sanitize_key( $type );
		if ( empty( $settings['actions'] ) || ! is_array( $settings['actions'] ) ) {
			return null;
		}

		foreach ( $settings['actions'] as $action ) {
			if ( is_array( $action ) && isset( $action['type'] ) && $type === $action['type'] ) {
				return $action;
			}
		}

		return null;
	}

	/**
	 * Whether settings include action type.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $type     Type.
	 * @return bool
	 */
	public static function has_action( array $settings, $type ) {
		return null !== self::get_action( $settings, $type );
	}

	/**
	 * Redirect URL from actions (empty if none).
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return string
	 */
	public static function redirect_url( array $settings ) {
		$action = self::get_action( $settings, 'redirect' );
		if ( ! $action || empty( $action['redirect_url'] ) ) {
			return '';
		}

		return (string) $action['redirect_url'];
	}

	/**
	 * Redirect delay in seconds (0 if no redirect action).
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return int
	 */
	public static function redirect_delay( array $settings ) {
		if ( '' === self::redirect_url( $settings ) ) {
			return 0;
		}

		return self::REDIRECT_DELAY_SEC;
	}

	/**
	 * Render one action card (admin).
	 *
	 * @param array<string, mixed> $action Action.
	 * @param int|string           $index  Index in list, or "__i__" for JS templates.
	 */
	public static function render_action_card( array $action, $index ) {
		$type = isset( $action['type'] ) ? sanitize_key( (string) $action['type'] ) : '';
		$defs = self::definitions();
		if ( ! isset( $defs[ $type ] ) ) {
			return;
		}

		$label = $defs[ $type ]['label'];
		$hint  = $defs[ $type ]['hint'];
		$idx   = ( '__i__' === $index ) ? '__i__' : (string) absint( $index );
		$ph    = __( 'Плейсхолдеры: {form_title}, {submission_id}, {email}, {phone}, {all_fields}, {page_url}, {field:key}', 'art-forms' );
		?>
		<div class="art-forms-action-card art-forms-collapsible" data-action-type="<?php echo esc_attr( $type ); ?>">
			<input type="hidden" name="actions[<?php echo esc_attr( $idx ); ?>][type]" value="<?php echo esc_attr( $type ); ?>" />
			<div class="art-forms-action-card-head">
				<button type="button" class="art-forms-collapse-toggle" aria-expanded="true">
					<span class="art-forms-collapse-titles">
						<span class="art-forms-collapse-title"><?php echo esc_html( $label ); ?></span>
						<span class="art-forms-collapse-hint"><?php echo esc_html( $hint ); ?></span>
					</span>
					<span class="art-forms-collapse-chevron" aria-hidden="true">▾</span>
				</button>
				<button type="button" class="button-link art-forms-action-remove">
					<?php echo esc_html__( 'Удалить', 'art-forms' ); ?>
				</button>
			</div>
			<div class="art-forms-collapse-body">
				<?php if ( 'redirect' === $type ) : ?>
					<div class="art-forms-field-block">
						<label class="art-forms-label" for="art-forms-action-redirect-<?php echo esc_attr( $idx ); ?>"><?php echo esc_html__( 'URL редиректа', 'art-forms' ); ?></label>
						<input
							type="url"
							class="art-forms-input"
							id="art-forms-action-redirect-<?php echo esc_attr( $idx ); ?>"
							name="actions[<?php echo esc_attr( $idx ); ?>][redirect_url]"
							value="<?php echo esc_attr( isset( $action['redirect_url'] ) ? (string) $action['redirect_url'] : '' ); ?>"
							placeholder="https://"
						/>
						<p class="art-forms-hint"><?php echo esc_html__( 'После отправки сначала показывается сообщение, затем через 3 секунды — переход.', 'art-forms' ); ?></p>
					</div>
				<?php elseif ( 'email_admin' === $type ) : ?>
					<div class="art-forms-grid-2">
						<div class="art-forms-field-block">
							<label class="art-forms-label" for="art-forms-action-email-to-<?php echo esc_attr( $idx ); ?>"><?php echo esc_html__( 'Получатели email', 'art-forms' ); ?></label>
							<input
								type="text"
								class="art-forms-input"
								id="art-forms-action-email-to-<?php echo esc_attr( $idx ); ?>"
								name="actions[<?php echo esc_attr( $idx ); ?>][email_to]"
								value="<?php echo esc_attr( isset( $action['email_to'] ) ? (string) $action['email_to'] : '' ); ?>"
								placeholder="mail@example.com"
							/>
							<p class="art-forms-hint"><?php echo esc_html__( 'Несколько адресов — через запятую.', 'art-forms' ); ?></p>
						</div>
						<div class="art-forms-field-block">
							<label class="art-forms-label" for="art-forms-action-email-subject-<?php echo esc_attr( $idx ); ?>"><?php echo esc_html__( 'Тема письма', 'art-forms' ); ?></label>
							<input
								type="text"
								class="art-forms-input"
								id="art-forms-action-email-subject-<?php echo esc_attr( $idx ); ?>"
								name="actions[<?php echo esc_attr( $idx ); ?>][email_subject]"
								value="<?php echo esc_attr( isset( $action['email_subject'] ) ? (string) $action['email_subject'] : '' ); ?>"
							/>
						</div>
					</div>
					<div class="art-forms-field-block">
						<label class="art-forms-label" for="art-forms-action-email-body-<?php echo esc_attr( $idx ); ?>"><?php echo esc_html__( 'Текст письма', 'art-forms' ); ?></label>
						<textarea
							class="art-forms-input art-forms-textarea"
							rows="7"
							id="art-forms-action-email-body-<?php echo esc_attr( $idx ); ?>"
							name="actions[<?php echo esc_attr( $idx ); ?>][email_body]"
						><?php echo esc_textarea( isset( $action['email_body'] ) ? (string) $action['email_body'] : '' ); ?></textarea>
						<p class="art-forms-hint"><?php echo esc_html( $ph ); ?></p>
					</div>
				<?php elseif ( 'email_client' === $type ) : ?>
					<div class="art-forms-field-block">
						<label class="art-forms-label" for="art-forms-action-client-subject-<?php echo esc_attr( $idx ); ?>"><?php echo esc_html__( 'Тема письма клиенту', 'art-forms' ); ?></label>
						<input
							type="text"
							class="art-forms-input"
							id="art-forms-action-client-subject-<?php echo esc_attr( $idx ); ?>"
							name="actions[<?php echo esc_attr( $idx ); ?>][client_email_subject]"
							value="<?php echo esc_attr( isset( $action['client_email_subject'] ) ? (string) $action['client_email_subject'] : '' ); ?>"
						/>
					</div>
					<div class="art-forms-field-block">
						<label class="art-forms-label" for="art-forms-action-client-body-<?php echo esc_attr( $idx ); ?>"><?php echo esc_html__( 'Текст письма клиенту', 'art-forms' ); ?></label>
						<textarea
							class="art-forms-input art-forms-textarea"
							rows="7"
							id="art-forms-action-client-body-<?php echo esc_attr( $idx ); ?>"
							name="actions[<?php echo esc_attr( $idx ); ?>][client_email_body]"
						><?php echo esc_textarea( isset( $action['client_email_body'] ) ? (string) $action['client_email_body'] : '' ); ?></textarea>
						<p class="art-forms-hint"><?php echo esc_html( $ph ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
