<?php
/**
 * Single submission view.
 *
 * @package Art_Forms
 *
 * @var array<string,mixed>|null $submission
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap art-forms-admin">
	<h1><?php echo esc_html__( 'Ответ', 'art-forms' ); ?></h1>
	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=art-forms-submissions' ) ); ?>">&larr; <?php echo esc_html__( 'К списку', 'art-forms' ); ?></a></p>

	<?php if ( ! $submission ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html__( 'Ответ не найден.', 'art-forms' ); ?></p></div>
	<?php else : ?>
		<?php
		$delete_url = Art_Forms_Admin_Menu::nonce_admin_post_url(
			'art_forms_delete_submission',
			array( 'id' => (int) $submission['id'] ),
			'art_forms_delete_submission_' . (int) $submission['id']
		);
		?>
		<p>
			<a class="button button-link-delete" href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Удалить этот ответ безвозвратно?', 'art-forms' ) ); ?>');">
				<?php echo esc_html__( 'Удалить ответ', 'art-forms' ); ?>
			</a>
		</p>
		<div class="art-forms-panel">
			<p><strong>ID:</strong> <?php echo esc_html( (string) $submission['id'] ); ?></p>
			<p><strong><?php echo esc_html__( 'Форма', 'art-forms' ); ?>:</strong> <?php echo esc_html( $submission['form_id'] ? (string) get_the_title( $submission['form_id'] ) : '—' ); ?> (<?php echo esc_html( (string) $submission['form_id'] ); ?>)</p>
			<p><strong><?php echo esc_html__( 'Дата (UTC)', 'art-forms' ); ?>:</strong> <?php echo esc_html( Art_Forms_Submissions::format_datetime( (string) $submission['created_at'] ) ); ?></p>
			<p><strong><?php echo esc_html__( 'Статус', 'art-forms' ); ?>:</strong> <?php echo wp_kses_post( Art_Forms_Submissions::render_status_badge( (string) $submission['status'] ) ); ?></p>
			<p><strong><?php echo esc_html__( 'Имя', 'art-forms' ); ?>:</strong> <?php echo esc_html( isset( $submission['contact_name'] ) ? (string) $submission['contact_name'] : '' ); ?></p>
			<p><strong>Email:</strong> <?php echo esc_html( $submission['contact_email'] ); ?></p>
			<p><strong><?php echo esc_html__( 'Телефон', 'art-forms' ); ?>:</strong> <?php echo esc_html( $submission['contact_phone'] ); ?></p>
			<p><strong>URL:</strong> <?php echo esc_html( Art_Forms_Schema::format_display_url( (string) $submission['page_url'] ) ); ?></p>
			<p><strong>Referrer:</strong> <?php echo esc_html( Art_Forms_Schema::format_display_url( (string) $submission['referrer'] ) ); ?></p>
			<p><strong>UTM:</strong>
				<?php
				echo esc_html(
					implode(
						' | ',
						array_filter(
							array(
								$submission['utm_source'],
								$submission['utm_medium'],
								$submission['utm_campaign'],
								$submission['utm_content'],
								$submission['utm_term'],
							)
						)
					)
				);
				?>
			</p>
		</div>

		<div class="art-forms-panel">
			<h2><?php echo esc_html__( 'Ответы полей', 'art-forms' ); ?></h2>
			<?php
			$fields_map = array();
			if ( ! empty( $submission['form_id'] ) ) {
				$fields_map = Art_Forms_Schema::fields_map( Art_Forms_Schema::get( (int) $submission['form_id'] ) );
			}
			?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Вопрос', 'art-forms' ); ?></th>
						<th><?php echo esc_html__( 'Ответ', 'art-forms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( (array) $submission['payload'] as $key => $value ) : ?>
						<?php
						$key   = (string) $key;
						$field = isset( $fields_map[ $key ] ) ? $fields_map[ $key ] : array( 'key' => $key, 'label' => $key, 'type' => 'text' );
						$label = Art_Forms_Schema::field_display_label( $field );
						?>
						<tr>
							<th>
								<span class="art-forms-answer-label"><?php echo esc_html( $label ); ?></span>
								<?php if ( $label !== $key ) : ?>
									<span class="art-forms-answer-key"><?php echo esc_html( $key ); ?></span>
								<?php endif; ?>
							</th>
							<td><?php echo esc_html( Art_Forms_Schema::format_display_value( $field, $value ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php
		$log = Art_Forms_Delivery_Log::query(
			array(
				'submission_id' => $submission['id'],
				'per_page'      => 20,
			)
		);
		?>
		<div class="art-forms-panel">
			<h2><?php echo esc_html__( 'Доставки', 'art-forms' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Время', 'art-forms' ); ?></th>
						<th><?php echo esc_html__( 'Канал', 'art-forms' ); ?></th>
						<th><?php echo esc_html__( 'Статус', 'art-forms' ); ?></th>
						<th><?php echo esc_html__( 'Сообщение', 'art-forms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $log['items'] ) ) : ?>
						<tr><td colspan="4"><?php echo esc_html__( 'Записей нет.', 'art-forms' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $log['items'] as $row ) : ?>
							<tr>
								<td><?php echo esc_html( Art_Forms_Submissions::format_datetime( (string) $row['created_at'] ) ); ?></td>
								<td><?php echo esc_html( $row['channel'] ); ?></td>
								<td><?php echo wp_kses_post( Art_Forms_Delivery_Log::render_status_badge( (string) $row['status'] ) ); ?></td>
								<td><?php echo esc_html( $row['message'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
