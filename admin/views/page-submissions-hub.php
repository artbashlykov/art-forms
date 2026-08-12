<?php
/**
 * Submissions hub — choose a form.
 *
 * @package Art_Forms
 *
 * @var WP_Post[] $forms
 * @var array<int, int> $counts
 * @var bool $deleted
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap art-forms-admin art-forms-crm-hub">
	<h1><?php echo esc_html__( 'Ответы', 'art-forms' ); ?></h1>
	<p class="art-forms-crm-hub-hint"><?php echo esc_html__( 'Выберите форму, чтобы открыть заявки только по ней.', 'art-forms' ); ?></p>

	<?php if ( ! empty( $deleted ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Ответ удалён.', 'art-forms' ); ?></p></div>
	<?php endif; ?>

	<?php if ( empty( $forms ) ) : ?>
		<div class="art-forms-panel">
			<p><?php echo esc_html__( 'Форм пока нет. Создайте форму, чтобы собирать ответы.', 'art-forms' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=art-forms-new' ) ); ?>"><?php echo esc_html__( 'Добавить форму', 'art-forms' ); ?></a></p>
		</div>
	<?php else : ?>
		<div class="art-forms-crm-form-grid">
			<?php foreach ( $forms as $form ) : ?>
				<?php
				$fid   = (int) $form->ID;
				$count = isset( $counts[ $fid ] ) ? (int) $counts[ $fid ] : 0;
				$url   = admin_url( 'admin.php?page=art-forms-submissions&form_id=' . $fid );
				$title = get_the_title( $form );
				if ( '' === $title ) {
					$title = __( '(без названия)', 'art-forms' );
				}
				?>
				<a class="art-forms-crm-form-card" href="<?php echo esc_url( $url ); ?>">
					<span class="art-forms-crm-form-card-title"><?php echo esc_html( $title ); ?></span>
					<span class="art-forms-crm-form-card-meta">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: submissions count */
								_n( '%d заявка', '%d заявок', $count, 'art-forms' ),
								$count
							)
						);
						?>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
