<?php
/**
 * Delivery log page.
 *
 * @package Art_Forms
 *
 * @var array{items: array, total: int} $result
 * @var WP_Post[] $forms
 * @var int $form_id
 * @var int $page
 */

defined( 'ABSPATH' ) || exit;

$items = $result['items'];
$total = $result['total'];
$pages = (int) ceil( max( 1, $total ) / 50 );
?>
<div class="wrap art-forms-admin">
	<h1><?php echo esc_html__( 'Лог доставок', 'art-forms' ); ?></h1>

	<form method="get" class="art-forms-panel">
		<input type="hidden" name="page" value="art-forms-delivery-log" />
		<label>
			<?php echo esc_html__( 'Форма', 'art-forms' ); ?>
			<select name="form_id">
				<option value="0"><?php echo esc_html__( 'Все', 'art-forms' ); ?></option>
				<?php foreach ( $forms as $form ) : ?>
					<option value="<?php echo esc_attr( (string) $form->ID ); ?>" <?php selected( $form_id, $form->ID ); ?>><?php echo esc_html( get_the_title( $form ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<button type="submit" class="button"><?php echo esc_html__( 'Фильтр', 'art-forms' ); ?></button>
	</form>

	<table class="widefat striped art-forms-panel">
		<thead>
			<tr>
				<th>ID</th>
				<th><?php echo esc_html__( 'Время (UTC)', 'art-forms' ); ?></th>
				<th><?php echo esc_html__( 'Форма', 'art-forms' ); ?></th>
				<th><?php echo esc_html__( 'Ответ', 'art-forms' ); ?></th>
				<th><?php echo esc_html__( 'Канал', 'art-forms' ); ?></th>
				<th><?php echo esc_html__( 'Статус', 'art-forms' ); ?></th>
				<th><?php echo esc_html__( 'Тест', 'art-forms' ); ?></th>
				<th><?php echo esc_html__( 'Сообщение', 'art-forms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $items ) ) : ?>
				<tr><td colspan="8"><?php echo esc_html__( 'Записей нет.', 'art-forms' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $items as $row ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $row['id'] ); ?></td>
						<td><?php echo esc_html( $row['created_at'] ); ?></td>
						<td><?php echo esc_html( get_the_title( (int) $row['form_id'] ) ); ?></td>
						<td>
							<?php if ( ! empty( $row['submission_id'] ) ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=art-forms-submissions&view=' . (int) $row['submission_id'] ) ); ?>">
									#<?php echo esc_html( (string) $row['submission_id'] ); ?>
								</a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $row['channel'] ); ?></td>
						<td><?php echo wp_kses_post( Art_Forms_Delivery_Log::render_status_badge( (string) $row['status'] ) ); ?></td>
						<td><?php echo ! empty( $row['is_test'] ) ? esc_html__( 'да', 'art-forms' ) : esc_html__( 'нет', 'art-forms' ); ?></td>
						<td><?php echo esc_html( $row['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
