<?php
/**
 * Form edit view.
 *
 * @package Art_Forms
 *
 * @var int                  $form_id
 * @var string               $title
 * @var array<string,mixed>  $schema
 * @var array<string,mixed>  $settings
 * @var string               $code
 * @var string               $prompt
 * @var string               $notice
 * @var bool                 $just_saved
 * @var array<string,mixed>|null $import_notice
 */

defined( 'ABSPATH' ) || exit;

$field_types    = Art_Forms_Schema::FIELD_TYPES;
$just_saved     = ! empty( $just_saved );
$import_notice  = isset( $import_notice ) && is_array( $import_notice ) ? $import_notice : null;
$export_url     = $form_id ? wp_nonce_url( admin_url( 'admin-post.php?action=art_forms_export_form&form_id=' . $form_id ), 'art_forms_export_form' ) : '';
?>
<div class="wrap art-forms-admin art-forms-edit">
	<div class="art-forms-page-head">
		<div class="art-forms-page-head-main">
			<a class="art-forms-back" href="<?php echo esc_url( admin_url( 'admin.php?page=art-forms' ) ); ?>">&larr; <?php echo esc_html__( 'К списку форм', 'art-forms' ); ?></a>
			<h1><?php echo $form_id ? esc_html__( 'Редактировать форму', 'art-forms' ) : esc_html__( 'Новая форма', 'art-forms' ); ?></h1>
		</div>
		<?php if ( $form_id ) : ?>
			<div class="art-forms-page-head-actions">
				<a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php echo esc_html__( 'Экспортировать', 'art-forms' ); ?></a>
				<span class="art-forms-id-badge"><?php echo esc_html( sprintf( /* translators: %d: form id */ __( 'ID %d', 'art-forms' ), $form_id ) ); ?></span>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( $import_notice ) : ?>
		<div class="notice notice-warning is-dismissible art-forms-import-notice">
			<p><strong><?php echo esc_html__( 'Форма импортирована. Проверьте пункты ниже — они часто ломаются при переносе между сайтами.', 'art-forms' ); ?></strong></p>
			<?php if ( ! empty( $import_notice['warnings'] ) && is_array( $import_notice['warnings'] ) ) : ?>
				<ul>
					<?php foreach ( $import_notice['warnings'] as $warning ) : ?>
						<li><?php echo esc_html( (string) $warning ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="art-forms-edit-form" class="art-forms-edit-form">
		<?php wp_nonce_field( 'art_forms_save_form' ); ?>
		<input type="hidden" name="action" value="art_forms_save_form" />
		<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $form_id ); ?>" />
		<input type="hidden" name="schema_json" id="art-forms-schema-json" value="<?php echo esc_attr( Art_Forms_Schema::encode_json( $schema ) ); ?>" />

		<section class="art-forms-panel art-forms-panel-title">
			<label class="art-forms-label" for="form_title"><?php echo esc_html__( 'Название формы', 'art-forms' ); ?></label>
			<input type="text" class="art-forms-input art-forms-input-lg" id="form_title" name="form_title" value="<?php echo esc_attr( $title ); ?>" required placeholder="<?php echo esc_attr__( 'Например: Квиз на главной', 'art-forms' ); ?>" />
		</section>

		<section class="art-forms-panel art-forms-builder-main">
			<div class="art-forms-panel-head">
				<div>
					<div class="art-forms-builder-title-row">
						<h2><?php echo esc_html__( 'Конструктор', 'art-forms' ); ?></h2>
						<span class="art-forms-schema-stats" id="art-forms-schema-stats" aria-live="polite"></span>
					</div>
					<p class="art-forms-hint"><?php echo esc_html__( 'Поля свёрнуты в список — кликните, чтобы открыть настройки. Вкладки блоков и поля можно перетаскивать.', 'art-forms' ); ?></p>
				</div>
				<button type="button" class="button" id="art-forms-add-step"><?php echo esc_html__( 'Добавить блок', 'art-forms' ); ?></button>
			</div>
			<div id="art-forms-schema-editor" class="art-forms-schema-editor"></div>
		</section>

		<section class="art-forms-panel">
			<div class="art-forms-panel-head">
				<div>
					<h2><?php echo esc_html__( 'Сообщение после отправки', 'art-forms' ); ?></h2>
					<p class="art-forms-hint"><?php echo esc_html__( 'Текст, который пользователь видит на сайте сразу после успешной отправки.', 'art-forms' ); ?></p>
				</div>
			</div>
			<div class="art-forms-field-block">
				<label class="art-forms-label screen-reader-text" for="success_message"><?php echo esc_html__( 'Сообщение после отправки', 'art-forms' ); ?></label>
				<textarea class="art-forms-input art-forms-textarea" rows="3" id="success_message" name="success_message"><?php echo esc_textarea( $settings['success_message'] ); ?></textarea>
			</div>
		</section>

		<section class="art-forms-panel art-forms-actions-panel">
			<div class="art-forms-panel-head">
				<div>
					<h2><?php echo esc_html__( 'Действия после отправки', 'art-forms' ); ?></h2>
					<p class="art-forms-hint"><?php echo esc_html__( 'Добавьте, что должно произойти после отправки: письма, редирект и другие интеграции.', 'art-forms' ); ?></p>
				</div>
			</div>

			<div id="art-forms-actions-list" class="art-forms-actions-list">
				<?php
				$actions = isset( $settings['actions'] ) && is_array( $settings['actions'] ) ? $settings['actions'] : array();
				foreach ( $actions as $i => $action ) {
					if ( is_array( $action ) ) {
						Art_Forms_Form_Actions::render_action_card( $action, (int) $i );
					}
				}
				?>
			</div>

			<div class="art-forms-actions-add">
				<label class="screen-reader-text" for="art-forms-action-type"><?php echo esc_html__( 'Тип действия', 'art-forms' ); ?></label>
				<select id="art-forms-action-type" class="art-forms-input art-forms-actions-select">
					<option value=""><?php echo esc_html__( 'Выберите действие…', 'art-forms' ); ?></option>
					<?php foreach ( Art_Forms_Form_Actions::definitions() as $type => $def ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $def['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button" id="art-forms-add-action"><?php echo esc_html__( 'Добавить действие', 'art-forms' ); ?></button>
			</div>

			<?php foreach ( Art_Forms_Form_Actions::definitions() as $type => $def ) : ?>
				<?php
				$tpl = Art_Forms_Form_Actions::default_action( $type );
				if ( ! is_array( $tpl ) ) {
					continue;
				}
				?>
				<template id="art-forms-action-tpl-<?php echo esc_attr( $type ); ?>">
					<?php Art_Forms_Form_Actions::render_action_card( $tpl, '__i__' ); ?>
				</template>
			<?php endforeach; ?>
		</section>

		<div id="art-forms-save-warning" class="notice notice-warning inline art-forms-save-warning" hidden></div>

		<div class="art-forms-savebar">
			<button type="submit" class="button button-primary button-large"><?php echo esc_html__( 'Сохранить форму', 'art-forms' ); ?></button>
			<span
				class="art-forms-savebar-status"
				id="art-forms-save-status"
				aria-live="polite"
				<?php echo $just_saved ? ' data-just-saved="1"' : ''; ?>
			></span>
			<span class="art-forms-savebar-note"><?php echo esc_html__( 'Несохранённые изменения будут потеряны при уходе со страницы.', 'art-forms' ); ?></span>
		</div>
	</form>

	<?php if ( $form_id ) : ?>
		<section class="art-forms-panel art-forms-collapsible is-collapsed" data-collapse-key="export">
			<button type="button" class="art-forms-collapse-toggle" aria-expanded="false">
				<span class="art-forms-collapse-titles">
					<span class="art-forms-collapse-title"><?php echo esc_html__( 'Экспорт для Нейронки', 'art-forms' ); ?></span>
					<span class="art-forms-collapse-hint"><?php echo esc_html__( 'Скопируйте код или готовый промпт, отдайте в Нейронки, затем проверьте результат ниже.', 'art-forms' ); ?></span>
				</span>
				<span class="art-forms-collapse-chevron" aria-hidden="true">▸</span>
			</button>
			<div class="art-forms-collapse-body">
				<div class="art-forms-actions-row art-forms-collapse-actions">
					<button type="button" class="button button-primary art-forms-copy" data-copy-target="art-forms-code"><?php echo esc_html__( 'Скопировать код', 'art-forms' ); ?></button>
					<button type="button" class="button art-forms-copy" data-copy-target="art-forms-prompt"><?php echo esc_html__( 'Скопировать промпт', 'art-forms' ); ?></button>
					<span class="art-forms-copy-status" aria-live="polite"></span>
				</div>
				<div class="art-forms-grid-2 art-forms-export-grid">
					<div class="art-forms-field-block">
						<label class="art-forms-label" for="art-forms-code"><?php echo esc_html__( 'Код формы', 'art-forms' ); ?></label>
						<textarea id="art-forms-code" class="art-forms-input art-forms-textarea code" rows="12" readonly><?php echo esc_textarea( $code ); ?></textarea>
					</div>
					<div class="art-forms-field-block">
						<label class="art-forms-label" for="art-forms-prompt"><?php echo esc_html__( 'Промпт для Нейронки', 'art-forms' ); ?></label>
						<textarea id="art-forms-prompt" class="art-forms-input art-forms-textarea code" rows="12" readonly><?php echo esc_textarea( $prompt ); ?></textarea>
					</div>
				</div>
			</div>
		</section>

		<section class="art-forms-panel art-forms-collapsible is-collapsed" data-collapse-key="checker">
			<button type="button" class="art-forms-collapse-toggle" aria-expanded="false">
				<span class="art-forms-collapse-titles">
					<span class="art-forms-collapse-title"><?php echo esc_html__( 'Проверщик кода', 'art-forms' ); ?></span>
					<span class="art-forms-collapse-hint"><?php echo esc_html__( 'Вставьте HTML+CSS+JS от Нейронки. Код никуда не сохраняется — только проверка.', 'art-forms' ); ?></span>
				</span>
				<span class="art-forms-collapse-chevron" aria-hidden="true">▸</span>
			</button>
			<div class="art-forms-collapse-body">
				<textarea id="art-forms-checker-code" class="art-forms-input art-forms-textarea code" rows="10" placeholder="<?php echo esc_attr__( 'Вставьте код сюда…', 'art-forms' ); ?>"></textarea>
				<div class="art-forms-actions-row art-forms-checker-actions">
					<button type="button" class="button button-primary" id="art-forms-run-checker" data-form-id="<?php echo esc_attr( (string) $form_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'art_forms_check_code' ) ); ?>">
						<?php echo esc_html__( 'Проверить код', 'art-forms' ); ?>
					</button>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=art_forms_test_email&form_id=' . $form_id ), 'art_forms_test_email' ) ); ?>">
						<?php echo esc_html__( 'Отправить тест на email', 'art-forms' ); ?>
					</a>
				</div>
				<div id="art-forms-checker-result" class="art-forms-checker-result" hidden></div>
			</div>
		</section>
	<?php else : ?>
		<section class="art-forms-panel art-forms-panel-muted">
			<p class="art-forms-hint art-forms-hint-alone"><?php echo esc_html__( 'Сохраните форму — появятся экспорт кода, промпт, проверщик и тест email.', 'art-forms' ); ?></p>
		</section>
	<?php endif; ?>
</div>

<script type="application/json" id="art-forms-field-types"><?php echo Art_Forms_Schema::encode_json( array_values( $field_types ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON in script type=application/json ?></script>
<script type="application/json" id="art-forms-schema-data"><?php echo Art_Forms_Schema::encode_json( $schema ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON in script type=application/json ?></script>
