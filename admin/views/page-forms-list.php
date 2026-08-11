<?php
/**
 * Forms list view.
 *
 * @package Art_Forms
 *
 * @var WP_Post[] $forms Forms.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap art-forms-admin">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Формы', 'art-forms' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=art-forms-new' ) ); ?>" class="page-title-action"><?php echo esc_html__( 'Добавить форму', 'art-forms' ); ?></a>
	<hr class="wp-header-end" />

	<table class="widefat striped art-forms-panel">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Название', 'art-forms' ); ?></th>
				<th><?php echo esc_html__( 'ID', 'art-forms' ); ?></th>
				<th><?php echo esc_html__( 'Поля', 'art-forms' ); ?></th>
				<th><?php echo esc_html__( 'Дата', 'art-forms' ); ?></th>
				<th><?php echo esc_html__( 'Действия', 'art-forms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $forms ) ) : ?>
				<tr>
					<td colspan="5"><?php echo esc_html__( 'Форм пока нет.', 'art-forms' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $forms as $form ) : ?>
					<?php
					$schema = Art_Forms_Schema::get( $form->ID );
					$count  = count( Art_Forms_Schema::flatten_fields( $schema ) );
					$edit  = admin_url( 'admin.php?page=art-forms-edit&form_id=' . $form->ID );
					$stats = admin_url( 'admin.php?page=art-forms-submissions&form_id=' . $form->ID );
					$dup   = wp_nonce_url( admin_url( 'admin-post.php?action=art_forms_duplicate_form&form_id=' . $form->ID ), 'art_forms_duplicate_form' );
					$del   = wp_nonce_url( admin_url( 'admin-post.php?action=art_forms_delete_form&form_id=' . $form->ID ), 'art_forms_delete_form' );
					?>
					<tr>
						<td><strong><a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( get_the_title( $form ) ); ?></a></strong></td>
						<td><?php echo esc_html( (string) $form->ID ); ?></td>
						<td><?php echo esc_html( (string) $count ); ?></td>
						<td><?php echo esc_html( get_the_date( '', $form ) ); ?></td>
						<td>
							<div class="art-forms-row-actions">
								<a class="art-forms-field-icon-btn" href="<?php echo esc_url( $edit ); ?>" title="<?php echo esc_attr__( 'Изменить', 'art-forms' ); ?>" aria-label="<?php echo esc_attr__( 'Изменить', 'art-forms' ); ?>">
									<span class="dashicons dashicons-edit" aria-hidden="true"></span>
								</a>
								<a class="art-forms-field-icon-btn" href="<?php echo esc_url( $stats ); ?>" title="<?php echo esc_attr__( 'Статистика', 'art-forms' ); ?>" aria-label="<?php echo esc_attr__( 'Статистика', 'art-forms' ); ?>">
									<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
								</a>
								<a class="art-forms-field-icon-btn" href="<?php echo esc_url( $dup ); ?>" title="<?php echo esc_attr__( 'Дублировать', 'art-forms' ); ?>" aria-label="<?php echo esc_attr__( 'Дублировать', 'art-forms' ); ?>">
									<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
								</a>
								<a class="art-forms-field-icon-btn art-forms-field-remove" href="<?php echo esc_url( $del ); ?>" title="<?php echo esc_attr__( 'Удалить', 'art-forms' ); ?>" aria-label="<?php echo esc_attr__( 'Удалить', 'art-forms' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Удалить форму в корзину?', 'art-forms' ) ); ?>');">
									<span class="dashicons dashicons-trash" aria-hidden="true"></span>
								</a>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
