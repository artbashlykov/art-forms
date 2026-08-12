<?php
/**
 * Settings page.
 *
 * @package Art_Forms
 *
 * @var array<string,mixed> $settings
 * @var bool $saved
 * @var string $tab
 * @var array<string,string> $tabs
 */

defined( 'ABSPATH' ) || exit;

$tab  = isset( $tab ) ? (string) $tab : 'general';
$tabs = isset( $tabs ) && is_array( $tabs ) ? $tabs : array(
	'general'       => __( 'Основные', 'art-forms' ),
	'integrations'  => __( 'Интеграции', 'art-forms' ),
);
?>
<div class="wrap art-forms-admin">
	<h1><?php echo esc_html__( 'Настройки ART Forms', 'art-forms' ); ?></h1>

	<nav class="nav-tab-wrapper art-forms-settings-tabs" aria-label="<?php echo esc_attr__( 'Вкладки настроек', 'art-forms' ); ?>">
		<?php foreach ( $tabs as $tab_id => $label ) : ?>
			<?php
			$url   = add_query_arg(
				array(
					'page' => 'art-forms-settings',
					'tab'  => $tab_id,
				),
				admin_url( 'admin.php' )
			);
			$class = 'nav-tab' . ( $tab === $tab_id ? ' nav-tab-active' : '' );
			?>
			<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Настройки сохранены.', 'art-forms' ); ?></p></div>
	<?php endif; ?>

	<?php if ( 'integrations' === $tab ) : ?>
		<section class="art-forms-panel art-forms-settings-tab-panel">
			<div class="art-forms-panel-head">
				<div>
					<h2><?php echo esc_html__( 'Интеграции', 'art-forms' ); ?></h2>
					<p class="art-forms-hint"><?php echo esc_html__( 'Подключение внешних сервисов к ART Forms.', 'art-forms' ); ?></p>
				</div>
			</div>
			<p class="art-forms-settings-stub">
				<?php echo esc_html__( 'Пока здесь пусто. Скоро появятся интеграции (CRM, мессенджеры и другие сервисы).', 'art-forms' ); ?>
			</p>
		</section>
	<?php else : ?>
		<p class="art-forms-hint art-forms-settings-intro">
			<?php echo esc_html__( 'Эти значения подставляются в новые формы; у каждой формы можно переопределить email, тему, текст письма и сообщение после отправки.', 'art-forms' ); ?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="art-forms-settings-form">
			<?php wp_nonce_field( 'art_forms_save_settings' ); ?>
			<input type="hidden" name="action" value="art_forms_save_settings" />
			<input type="hidden" name="tab" value="general" />

			<section class="art-forms-panel">
				<div class="art-forms-panel-head">
					<div>
						<h2><?php echo esc_html__( 'Письма и ответы клиенту', 'art-forms' ); ?></h2>
						<p class="art-forms-hint"><?php echo esc_html__( 'Шаблоны по умолчанию для новых форм.', 'art-forms' ); ?></p>
					</div>
				</div>

				<div class="art-forms-field-block">
					<label class="art-forms-label" for="default_email_to"><?php echo esc_html__( 'Email по умолчанию', 'art-forms' ); ?></label>
					<input type="text" class="art-forms-input" id="default_email_to" name="default_email_to" value="<?php echo esc_attr( $settings['default_email_to'] ); ?>" />
					<p class="art-forms-hint"><?php echo esc_html__( 'Несколько адресов — через запятую.', 'art-forms' ); ?></p>
				</div>

				<div class="art-forms-field-block">
					<label class="art-forms-label" for="default_email_subject"><?php echo esc_html__( 'Тема письма по умолчанию', 'art-forms' ); ?></label>
					<input type="text" class="art-forms-input" id="default_email_subject" name="default_email_subject" value="<?php echo esc_attr( $settings['default_email_subject'] ); ?>" />
				</div>

				<div class="art-forms-field-block">
					<label class="art-forms-label" for="default_email_body"><?php echo esc_html__( 'Текст письма по умолчанию', 'art-forms' ); ?></label>
					<textarea class="art-forms-input art-forms-textarea" rows="10" id="default_email_body" name="default_email_body"><?php echo esc_textarea( $settings['default_email_body'] ); ?></textarea>
					<p class="art-forms-hint"><?php echo esc_html__( 'Плейсхолдеры: {form_title}, {submission_id}, {email}, {phone}, {all_fields}, {page_url}, {field:key}', 'art-forms' ); ?></p>
				</div>

				<div class="art-forms-field-block">
					<label class="art-forms-label" for="default_success_message"><?php echo esc_html__( 'Сообщение после отправки по умолчанию', 'art-forms' ); ?></label>
					<textarea class="art-forms-input art-forms-textarea" rows="3" id="default_success_message" name="default_success_message"><?php echo esc_textarea( $settings['default_success_message'] ); ?></textarea>
				</div>

				<div class="art-forms-field-block">
					<label class="art-forms-label" for="default_privacy_url"><?php echo esc_html__( 'URL политики конфиденциальности', 'art-forms' ); ?></label>
					<input type="url" class="art-forms-input" id="default_privacy_url" name="default_privacy_url" value="<?php echo esc_attr( $settings['default_privacy_url'] ); ?>" placeholder="https://" />
					<p class="art-forms-hint"><?php echo esc_html__( 'Подставляется в поля «Согласие на ПДн», если у поля не указана своя ссылка.', 'art-forms' ); ?></p>
				</div>
			</section>

			<section class="art-forms-panel art-forms-collapsible is-collapsed" data-collapse-key="settings-retention">
				<button type="button" class="art-forms-collapse-toggle" aria-expanded="false">
					<span class="art-forms-collapse-titles">
						<span class="art-forms-collapse-title"><?php echo esc_html__( 'Хранение ответов', 'art-forms' ); ?></span>
						<span class="art-forms-collapse-hint"><?php echo esc_html__( 'Настройки для персональных данных в базе.', 'art-forms' ); ?></span>
					</span>
					<span class="art-forms-collapse-chevron" aria-hidden="true">▸</span>
				</button>
				<div class="art-forms-collapse-body">
					<div class="art-forms-field-block">
						<label class="art-forms-label" for="retention_days"><?php echo esc_html__( 'Срок хранения ответов', 'art-forms' ); ?></label>
						<select class="art-forms-input" id="retention_days" name="retention_days">
							<option value="0" <?php selected( (int) $settings['retention_days'], 0 ); ?>><?php echo esc_html__( 'Бессрочно', 'art-forms' ); ?></option>
							<option value="30" <?php selected( (int) $settings['retention_days'], 30 ); ?>><?php echo esc_html__( '30 дней', 'art-forms' ); ?></option>
							<option value="90" <?php selected( (int) $settings['retention_days'], 90 ); ?>><?php echo esc_html__( '90 дней', 'art-forms' ); ?></option>
							<option value="180" <?php selected( (int) $settings['retention_days'], 180 ); ?>><?php echo esc_html__( '180 дней', 'art-forms' ); ?></option>
							<option value="365" <?php selected( (int) $settings['retention_days'], 365 ); ?>><?php echo esc_html__( '365 дней', 'art-forms' ); ?></option>
						</select>
						<p class="art-forms-hint"><?php echo esc_html__( 'Старые ответы и связанные записи лога доставок удаляются автоматически раз в сутки.', 'art-forms' ); ?></p>
					</div>

					<div class="art-forms-field-block">
						<label class="art-forms-label" for="store_payload"><?php echo esc_html__( 'Что хранить в ответе', 'art-forms' ); ?></label>
						<select class="art-forms-input" id="store_payload" name="store_payload">
							<option value="full" <?php selected( $settings['store_payload'], 'full' ); ?>><?php echo esc_html__( 'Все ответы полей', 'art-forms' ); ?></option>
							<option value="contacts" <?php selected( $settings['store_payload'], 'contacts' ); ?>><?php echo esc_html__( 'Только контакты и метки (без текста ответов)', 'art-forms' ); ?></option>
						</select>
						<p class="art-forms-hint"><?php echo esc_html__( 'В режиме «только контакты» письмо всё равно уходит с полными ответами, а в базе payload очищается после отправки.', 'art-forms' ); ?></p>
					</div>
				</div>
			</section>

			<section class="art-forms-panel art-forms-collapsible is-collapsed" data-collapse-key="settings-antispam">
				<button type="button" class="art-forms-collapse-toggle" aria-expanded="false">
					<span class="art-forms-collapse-titles">
						<span class="art-forms-collapse-title"><?php echo esc_html__( 'Антиспам', 'art-forms' ); ?></span>
						<span class="art-forms-collapse-hint"><?php echo esc_html__( 'Защита публичного REST-эндпоинта отправки.', 'art-forms' ); ?></span>
					</span>
					<span class="art-forms-collapse-chevron" aria-hidden="true">▸</span>
				</button>
				<div class="art-forms-collapse-body">
					<div class="art-forms-field-block">
						<label class="art-forms-control-required">
							<input type="checkbox" name="honeypot_enabled" value="1" <?php checked( ! empty( $settings['honeypot_enabled'] ) ); ?> />
							<?php echo esc_html__( 'Honeypot-поле в коде формы', 'art-forms' ); ?>
						</label>
						<p class="art-forms-hint"><?php echo esc_html__( 'Скрытое поле против ботов. Попадает в экспорт кода и проверяется при отправке.', 'art-forms' ); ?></p>
					</div>

					<div class="art-forms-field-block">
						<label class="art-forms-control-required">
							<input type="checkbox" name="rate_limit_enabled" value="1" <?php checked( ! empty( $settings['rate_limit_enabled'] ) ); ?> />
							<?php echo esc_html__( 'Лимит частоты отправок', 'art-forms' ); ?>
						</label>
					</div>

					<div class="art-forms-grid-2">
						<div class="art-forms-field-block">
							<label class="art-forms-label" for="rate_limit_max"><?php echo esc_html__( 'Максимум отправок', 'art-forms' ); ?></label>
							<input type="number" class="art-forms-input" id="rate_limit_max" name="rate_limit_max" min="1" max="100" value="<?php echo esc_attr( (string) $settings['rate_limit_max'] ); ?>" />
						</div>
						<div class="art-forms-field-block">
							<label class="art-forms-label" for="rate_limit_window"><?php echo esc_html__( 'Окно, минут', 'art-forms' ); ?></label>
							<input type="number" class="art-forms-input" id="rate_limit_window" name="rate_limit_window" min="1" max="1440" value="<?php echo esc_attr( (string) $settings['rate_limit_window'] ); ?>" />
						</div>
					</div>
					<p class="art-forms-hint"><?php echo esc_html__( 'Считается отдельно для каждой пары «IP + форма».', 'art-forms' ); ?></p>
				</div>
			</section>

			<section class="art-forms-panel art-forms-collapsible is-collapsed" data-collapse-key="settings-crm-notify">
				<button type="button" class="art-forms-collapse-toggle" aria-expanded="false">
					<span class="art-forms-collapse-titles">
						<span class="art-forms-collapse-title"><?php echo esc_html__( 'Уведомления CRM', 'art-forms' ); ?></span>
						<span class="art-forms-collapse-hint"><?php echo esc_html__( 'Бейдж в меню и короткое письмо со ссылкой на заявку.', 'art-forms' ); ?></span>
					</span>
					<span class="art-forms-collapse-chevron" aria-hidden="true">▸</span>
				</button>
				<div class="art-forms-collapse-body">
					<div class="art-forms-field-block">
						<label class="art-forms-control-required">
							<input type="checkbox" name="crm_notify_enabled" value="1" <?php checked( ! empty( $settings['crm_notify_enabled'] ) ); ?> />
							<?php echo esc_html__( 'Письмо менеджеру о новой заявке (со ссылкой в CRM)', 'art-forms' ); ?>
						</label>
						<p class="art-forms-hint"><?php echo esc_html__( 'Бейдж непрочитанных в меню «Ответы» работает всегда. Письмо можно отключить, если хватает уведомлений формы.', 'art-forms' ); ?></p>
					</div>
					<div class="art-forms-field-block">
						<label class="art-forms-label" for="crm_notify_email"><?php echo esc_html__( 'Email для CRM-уведомлений', 'art-forms' ); ?></label>
						<input type="text" class="art-forms-input" id="crm_notify_email" name="crm_notify_email" value="<?php echo esc_attr( isset( $settings['crm_notify_email'] ) ? $settings['crm_notify_email'] : '' ); ?>" />
						<p class="art-forms-hint"><?php echo esc_html__( 'Если пусто — берётся «Email по умолчанию», иначе email администратора. Несколько адресов — через запятую.', 'art-forms' ); ?></p>
					</div>
					<div class="art-forms-field-block">
						<span class="art-forms-label"><?php echo esc_html__( 'Менеджеры CRM', 'art-forms' ); ?></span>
						<p class="art-forms-hint"><?php echo esc_html__( 'Могут открывать «Ответы», смотреть заявки, менять этапы, править поля и оставлять комментарии. Без доступа к формам и настройкам плагина.', 'art-forms' ); ?></p>
						<?php
						$manager_ids = isset( $settings['crm_manager_ids'] ) && is_array( $settings['crm_manager_ids'] ) ? $settings['crm_manager_ids'] : array();
						$users       = get_users(
							array(
								'orderby' => 'display_name',
								'order'   => 'ASC',
								'number'  => 200,
								'fields'  => array( 'ID', 'user_login', 'display_name', 'user_email' ),
							)
						);
						?>
						<div class="art-forms-crm-managers-list">
							<?php foreach ( $users as $u ) : ?>
								<?php
								if ( user_can( $u->ID, 'manage_options' ) || user_can( $u->ID, Art_Forms_Capabilities::CAP_MANAGE ) ) {
									continue; // Admins already have full access.
								}
								?>
								<label class="art-forms-crm-manager-row">
									<input type="checkbox" name="crm_manager_ids[]" value="<?php echo esc_attr( (string) $u->ID ); ?>" <?php checked( in_array( (int) $u->ID, array_map( 'intval', $manager_ids ), true ) ); ?> />
									<?php
									echo esc_html(
										sprintf(
											'%1$s (%2$s)',
											$u->display_name ? $u->display_name : $u->user_login,
											$u->user_email
										)
									);
									?>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</section>

			<section class="art-forms-panel art-forms-collapsible is-collapsed" data-collapse-key="settings-delivery-fail">
				<button type="button" class="art-forms-collapse-toggle" aria-expanded="false">
					<span class="art-forms-collapse-titles">
						<span class="art-forms-collapse-title"><?php echo esc_html__( 'Ошибки доставки', 'art-forms' ); ?></span>
						<span class="art-forms-collapse-hint"><?php echo esc_html__( 'Если wp_mail не отправил письмо по заявке.', 'art-forms' ); ?></span>
					</span>
					<span class="art-forms-collapse-chevron" aria-hidden="true">▸</span>
				</button>
				<div class="art-forms-collapse-body">
					<div class="art-forms-field-block">
						<label class="art-forms-control-required">
							<input type="checkbox" name="delivery_fail_notify" value="1" <?php checked( ! empty( $settings['delivery_fail_notify'] ) ); ?> />
							<?php echo esc_html__( 'Уведомлять об ошибке доставки', 'art-forms' ); ?>
						</label>
					</div>

					<div class="art-forms-field-block">
						<label class="art-forms-label" for="delivery_fail_email"><?php echo esc_html__( 'Email для уведомлений об ошибках', 'art-forms' ); ?></label>
						<input type="email" class="art-forms-input" id="delivery_fail_email" name="delivery_fail_email" value="<?php echo esc_attr( $settings['delivery_fail_email'] ); ?>" />
						<p class="art-forms-hint"><?php echo esc_html__( 'Если пусто — используется email администратора сайта.', 'art-forms' ); ?></p>
					</div>
				</div>
			</section>

			<p>
				<button type="submit" class="button button-primary button-large"><?php echo esc_html__( 'Сохранить настройки', 'art-forms' ); ?></button>
			</p>
		</form>
	<?php endif; ?>
</div>
