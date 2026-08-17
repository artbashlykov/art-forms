<?php
/**
 * Submissions list.
 *
 * @package Art_Forms
 *
 * @var array{items: array, total: int} $result
 * @var WP_Post[] $forms
 * @var int $form_id
 * @var string $date_from
 * @var string $date_to
 * @var string $search
 * @var int $page
 * @var string $orderby
 * @var string $order
 * @var bool $deleted
 */

defined( 'ABSPATH' ) || exit;

$items = $result['items'];
$total = $result['total'];
$pages = (int) ceil( $total / 20 );

$list_args = array(
	'page'      => 'art-forms-submissions',
	'form_id'   => $form_id,
	'date_from' => $date_from,
	'date_to'   => $date_to,
	's'         => $search,
	'orderby'   => $orderby,
	'order'     => $order,
);

$csv_url = Art_Forms_Admin_Menu::nonce_admin_post_url(
	'art_forms_export_csv',
	array(
		'form_id'   => $form_id,
		'date_from' => $date_from,
		'date_to'   => $date_to,
	)
);

/**
 * Build sortable column header link.
 *
 * @param string               $column Column key.
 * @param string               $label  Column label.
 * @param string               $current_orderby Current orderby.
 * @param string               $current_order Current order.
 * @param array<string, mixed> $base_args Base query args.
 * @return string
 */
$art_forms_sort_th = static function ( $column, $label, $current_orderby, $current_order, array $base_args ) {
	$is_current = ( $current_orderby === $column );
	$next_order = ( $is_current && 'asc' === $current_order ) ? 'desc' : 'asc';
	$url        = add_query_arg(
		array_merge(
			$base_args,
			array(
				'orderby' => $column,
				'order'   => $next_order,
				'paged'   => 1,
			)
		),
		admin_url( 'admin.php' )
	);

	$classes = array( 'art-forms-sort-link' );
	if ( $is_current ) {
		$classes[] = 'is-sorted';
		$classes[] = 'is-' . $current_order;
	}

	return sprintf(
		'<a class="%1$s" href="%2$s"><span>%3$s</span><span class="art-forms-sort-indicator" aria-hidden="true"></span></a>',
		esc_attr( implode( ' ', $classes ) ),
		esc_url( $url ),
		esc_html( $label )
	);
};
?>
<div class="wrap art-forms-admin">
	<h1><?php echo esc_html__( 'Ответы', 'art-forms' ); ?></h1>

	<?php if ( ! empty( $deleted ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Ответ удалён.', 'art-forms' ); ?></p></div>
	<?php endif; ?>

	<form method="get" class="art-forms-panel">
		<input type="hidden" name="page" value="art-forms-submissions" />
		<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>" />
		<input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>" />
		<p>
			<label>
				<?php echo esc_html__( 'Форма', 'art-forms' ); ?>
				<select name="form_id">
					<option value="0"><?php echo esc_html__( 'Все', 'art-forms' ); ?></option>
					<?php foreach ( $forms as $form ) : ?>
						<option value="<?php echo esc_attr( (string) $form->ID ); ?>" <?php selected( $form_id, $form->ID ); ?>><?php echo esc_html( get_the_title( $form ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<?php echo esc_html__( 'С', 'art-forms' ); ?>
				<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" />
			</label>
			<label>
				<?php echo esc_html__( 'По', 'art-forms' ); ?>
				<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" />
			</label>
			<label>
				<?php echo esc_html__( 'Поиск', 'art-forms' ); ?>
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" />
			</label>
			<button type="submit" class="button"><?php echo esc_html__( 'Фильтр', 'art-forms' ); ?></button>
			<a class="button" href="<?php echo esc_url( $csv_url ); ?>"><?php echo esc_html__( 'Экспорт CSV', 'art-forms' ); ?></a>
		</p>
	</form>

	<table class="widefat striped art-forms-panel art-forms-submissions-table">
		<thead>
			<tr>
				<th scope="col"><?php echo $art_forms_sort_th( 'id', 'ID', $orderby, $order, $list_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
				<th scope="col"><?php echo $art_forms_sort_th( 'form', __( 'Форма', 'art-forms' ), $orderby, $order, $list_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
				<th scope="col"><?php echo $art_forms_sort_th( 'date', __( 'Дата (UTC)', 'art-forms' ), $orderby, $order, $list_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
				<th scope="col"><?php echo $art_forms_sort_th( 'email', 'Email', $orderby, $order, $list_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
				<th scope="col"><?php echo $art_forms_sort_th( 'phone', __( 'Телефон', 'art-forms' ), $orderby, $order, $list_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
				<th scope="col"><?php echo $art_forms_sort_th( 'status', __( 'Статус', 'art-forms' ), $orderby, $order, $list_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
				<th><?php echo esc_html__( 'Действия', 'art-forms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $items ) ) : ?>
				<tr><td colspan="7"><?php echo esc_html__( 'Ответов нет.', 'art-forms' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $items as $item ) : ?>
					<?php
					$delete_url = Art_Forms_Admin_Menu::nonce_admin_post_url(
						'art_forms_delete_submission',
						array( 'id' => (int) $item['id'] ),
						'art_forms_delete_submission_' . (int) $item['id']
					);
					if ( ! empty( $item['form_title'] ) ) {
						$form_title = (string) $item['form_title'];
					} else {
						$form_title = $item['form_id'] ? (string) get_the_title( $item['form_id'] ) : '—';
					}
					?>
					<tr>
						<td><?php echo esc_html( (string) $item['id'] ); ?></td>
						<td><?php echo esc_html( $form_title ? $form_title : '—' ); ?></td>
						<td><?php echo esc_html( Art_Forms_Submissions::format_datetime( (string) $item['created_at'] ) ); ?></td>
						<td><?php echo esc_html( $item['contact_email'] ); ?></td>
						<td><?php echo esc_html( $item['contact_phone'] ); ?></td>
						<td><?php echo wp_kses_post( Art_Forms_Submissions::render_status_badge( (string) $item['status'] ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=art-forms-submissions&view=' . $item['id'] ) ); ?>">
								<?php echo esc_html__( 'Открыть', 'art-forms' ); ?>
							</a>
							|
							<a class="art-forms-delete-link" href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Удалить этот ответ безвозвратно?', 'art-forms' ) ); ?>');">
								<?php echo esc_html__( 'Удалить', 'art-forms' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg(
								array_merge(
									$list_args,
									array( 'paged' => '%#%' )
								),
								admin_url( 'admin.php' )
							),
							'format'    => '',
							'current'   => $page,
							'total'     => $pages,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
