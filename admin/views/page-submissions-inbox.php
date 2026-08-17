<?php
/**
 * Submissions CRM inbox for one form.
 *
 * @package Art_Forms
 *
 * @var WP_Post $form
 * @var WP_Post[] $forms
 * @var int $form_id
 * @var array $stages
 * @var array $counts
 * @var array $result
 * @var array $field_columns
 * @var array $fields_map
 * @var array $hidden
 * @var array $col_widths
 * @var array $col_aliases
 * @var array $table_col_order
 * @var array $fields_by_key
 * @var string $layout
 * @var string $tab
 * @var int $stage_id
 * @var int $starred
 * @var int $starred_total
 * @var string $date_from
 * @var string $date_to
 * @var string $search
 * @var int $page
 * @var string $orderby
 * @var string $order
 * @var bool $deleted
 * @var int $view_id
 * @var array $nav_ids
 * @var array $contacts
 */

defined( 'ABSPATH' ) || exit;

$items = $result['items'];
$total = $result['total'];
$pages = (int) ceil( $total / ( 'board' === $layout ? 200 : 30 ) );
$form_title = get_the_title( $form );
if ( '' === $form_title ) {
	$form_title = __( '(без названия)', 'art-forms' );
}

$base_args = array(
	'page'      => 'art-forms-submissions',
	'form_id'   => $form_id,
	'date_from' => $date_from,
	'date_to'   => $date_to,
	's'         => $search,
	'orderby'   => $orderby,
	'order'     => $order,
	'layout'    => $layout,
	'tab'       => $tab,
);
if ( ! empty( $starred ) ) {
	$base_args['starred'] = 1;
}
if ( isset( $priority_filter ) && $priority_filter >= 0 ) {
	$base_args['priority'] = $priority_filter;
}
if ( ! empty( $tag_filter ) ) {
	$base_args['tag'] = $tag_filter;
}

$csv_args = array(
	'form_id'   => $form_id,
	'date_from' => $date_from,
	'date_to'   => $date_to,
);
if ( 'board' !== $layout && $stage_id > 0 ) {
	$csv_args['stage_id'] = $stage_id;
}
if ( ! empty( $starred ) ) {
	$csv_args['starred'] = 1;
}
if ( isset( $priority_filter ) && $priority_filter >= 0 ) {
	$csv_args['priority'] = $priority_filter;
}
if ( ! empty( $tag_filter ) ) {
	$csv_args['tag'] = $tag_filter;
}

$csv_url = Art_Forms_Admin_Menu::nonce_admin_post_url( 'art_forms_export_csv', $csv_args );

$stages_by_id = array();
foreach ( $stages as $st ) {
	$stages_by_id[ (int) $st['id'] ] = $st;
}

if ( ! isset( $board_groups ) || ! is_array( $board_groups ) ) {
	$board_groups = array();
}
if ( ! isset( $board_more ) || ! is_array( $board_more ) ) {
	$board_more = array();
}
if ( 'board' === $layout && empty( $board_groups ) ) {
	foreach ( $stages as $st ) {
		$board_groups[ (int) $st['id'] ] = array();
	}
	foreach ( $items as $item ) {
		$sid = (int) $item['stage_id'];
		if ( ! isset( $board_groups[ $sid ] ) ) {
			$board_groups[ $sid ] = array();
		}
		$board_groups[ $sid ][] = $item;
	}
}
if ( ! isset( $priority_filter ) ) {
	$priority_filter = -1;
}
if ( ! isset( $tag_filter ) ) {
	$tag_filter = '';
}
?>
<div
	class="wrap art-forms-admin art-forms-crm"
	id="art-forms-crm"
	data-form-id="<?php echo esc_attr( (string) $form_id ); ?>"
	data-layout="<?php echo esc_attr( $layout ); ?>"
	data-view-id="<?php echo esc_attr( (string) $view_id ); ?>"
	data-nav-ids="<?php echo esc_attr( wp_json_encode( $nav_ids ) ); ?>"
	data-hidden-columns="<?php echo esc_attr( wp_json_encode( array_values( $hidden ) ) ); ?>"
>
	<div class="art-forms-crm-top">
		<div class="art-forms-crm-top-left">
			<a class="art-forms-crm-back" href="<?php echo esc_url( admin_url( 'admin.php?page=art-forms-submissions' ) ); ?>">&larr; <?php echo esc_html__( 'Все формы', 'art-forms' ); ?></a>
			<h1 class="screen-reader-text"><?php echo esc_html( $form_title ); ?></h1>
			<div class="art-forms-crm-form-switch">
				<form method="get">
					<input type="hidden" name="page" value="art-forms-submissions" />
					<input type="hidden" name="layout" value="<?php echo esc_attr( $layout ); ?>" />
					<label class="screen-reader-text" for="art-forms-crm-form-select"><?php echo esc_html__( 'Форма', 'art-forms' ); ?></label>
					<select name="form_id" id="art-forms-crm-form-select" class="art-forms-crm-form-select" onchange="this.form.submit()" aria-label="<?php echo esc_attr__( 'Форма', 'art-forms' ); ?>">
						<?php foreach ( $forms as $f ) : ?>
							<option value="<?php echo esc_attr( (string) $f->ID ); ?>" <?php selected( $form_id, $f->ID ); ?>><?php echo esc_html( get_the_title( $f ) ? get_the_title( $f ) : __( '(без названия)', 'art-forms' ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</form>
			</div>
		</div>
		<div class="art-forms-crm-top-right">
			<?php if ( 'leads' === $tab ) : ?>
				<a class="button" href="<?php echo esc_url( $csv_url ); ?>"><?php echo esc_html__( 'Экспорт CSV', 'art-forms' ); ?></a>
				<?php if ( 'table' === $layout ) : ?>
					<?php
					$popover_col_labels = array(
						'star'     => '★',
						'priority' => __( 'Приоритет', 'art-forms' ),
						'tags'     => __( 'Теги', 'art-forms' ),
						'id'       => 'ID',
						'name'     => __( 'Имя', 'art-forms' ),
						'date'     => __( 'Дата', 'art-forms' ),
						'stage'    => __( 'Этап', 'art-forms' ),
					);
					?>
					<span class="art-forms-crm-columns-wrap">
						<button type="button" class="button" id="art-forms-crm-columns-btn"><?php echo esc_html__( 'Скрыть поля', 'art-forms' ); ?></button>
						<div class="art-forms-crm-columns-popover" id="art-forms-crm-columns-popover" hidden>
							<p><strong><?php echo esc_html__( 'Колонки', 'art-forms' ); ?></strong></p>
							<p class="art-forms-crm-columns-hint"><?php echo esc_html__( 'Снимите галочку, чтобы скрыть колонку. Короткое название — только в таблице.', 'art-forms' ); ?></p>
							<?php foreach ( $table_col_order as $pkey ) : ?>
								<?php
								$is_field_col = isset( $fields_by_key[ $pkey ] );
								if ( $is_field_col ) {
									$fc       = $fields_by_key[ $pkey ];
									$flab_src = Art_Forms_Schema::field_display_label( $fc );
								} else {
									$flab_src = isset( $popover_col_labels[ $pkey ] ) ? $popover_col_labels[ $pkey ] : $pkey;
								}
								$flab = Art_Forms_Admin_Submissions::field_column_label( $pkey, $flab_src, $col_aliases );
								$hid  = in_array( $pkey, $hidden, true );
								?>
								<div class="art-forms-crm-col-row">
									<label class="art-forms-crm-col-vis">
										<input type="checkbox" class="art-forms-crm-col-toggle" value="<?php echo esc_attr( $pkey ); ?>" <?php checked( ! $hid ); ?> />
										<span class="screen-reader-text"><?php echo esc_html__( 'Показать колонку', 'art-forms' ); ?></span>
									</label>
									<input
										type="text"
										class="art-forms-crm-col-alias"
										value="<?php echo esc_attr( $flab ); ?>"
										data-col="<?php echo esc_attr( $pkey ); ?>"
										data-original="<?php echo esc_attr( $flab_src ); ?>"
										placeholder="<?php echo esc_attr( $flab_src ); ?>"
										title="<?php echo esc_attr( sprintf( /* translators: %s: original field label */ __( 'Исходное название: %s', 'art-forms' ), $flab_src ) ); ?>"
									/>
								</div>
							<?php endforeach; ?>
						</div>
					</span>
				<?php endif; ?>
			<?php endif; ?>
			<div class="art-forms-crm-layout-toggle" role="group" aria-label="<?php echo esc_attr__( 'Вид', 'art-forms' ); ?>">
				<?php
				$table_url = add_query_arg( array_merge( $base_args, array( 'layout' => 'table', 'stage_id' => $stage_id ) ), admin_url( 'admin.php' ) );
				$board_url = add_query_arg( array_merge( $base_args, array( 'layout' => 'board', 'stage_id' => 0, 'paged' => 1 ) ), admin_url( 'admin.php' ) );
				?>
				<a class="button <?php echo 'table' === $layout ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $table_url ); ?>" data-crm-layout="table" title="<?php echo esc_attr__( 'Таблица', 'art-forms' ); ?>">
					<span class="dashicons dashicons-list-view"></span>
				</a>
				<a class="button <?php echo 'board' === $layout ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $board_url ); ?>" data-crm-layout="board" title="<?php echo esc_attr__( 'Канбан', 'art-forms' ); ?>">
					<span class="dashicons dashicons-columns"></span>
				</a>
			</div>
		</div>
	</div>

	<?php if ( ! empty( $deleted ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Ответ удалён.', 'art-forms' ); ?></p></div>
	<?php endif; ?>

	<nav class="art-forms-crm-tabs-main">
		<?php
		$tabs = array(
			'leads'    => __( 'Заявки', 'art-forms' ),
			'contacts' => __( 'Контакты', 'art-forms' ),
			'stages'   => __( 'Этапы', 'art-forms' ),
		);
		foreach ( $tabs as $tkey => $tlabel ) :
			$turl = add_query_arg( array_merge( $base_args, array( 'tab' => $tkey, 'paged' => 1 ) ), admin_url( 'admin.php' ) );
			?>
			<a class="<?php echo $tab === $tkey ? 'is-active' : ''; ?>" href="<?php echo esc_url( $turl ); ?>"><?php echo esc_html( $tlabel ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php if ( 'stages' === $tab ) : ?>
		<div class="art-forms-panel art-forms-crm-stages-panel">
			<p><?php echo esc_html__( 'Настройте этапы (вкладки) для заявок этой формы. Цвет отображается на вкладках и в канбане.', 'art-forms' ); ?></p>
			<ul class="art-forms-crm-stages-editor" id="art-forms-crm-stages-editor">
				<?php foreach ( $stages as $st ) : ?>
					<li data-stage-id="<?php echo esc_attr( (string) $st['id'] ); ?>">
						<span class="art-forms-crm-stage-drag dashicons dashicons-menu" title="<?php echo esc_attr__( 'Порядок', 'art-forms' ); ?>"></span>
						<input type="color" class="art-forms-crm-stage-color" value="<?php echo esc_attr( $st['color'] ); ?>" />
						<input type="text" class="art-forms-crm-stage-title" value="<?php echo esc_attr( $st['title'] ); ?>" />
						<?php if ( ! empty( $st['is_default'] ) ) : ?>
							<span class="art-forms-crm-stage-default"><?php echo esc_html__( 'по умолчанию', 'art-forms' ); ?></span>
						<?php endif; ?>
						<button type="button" class="button-link art-forms-crm-stage-save"><?php echo esc_html__( 'Сохранить', 'art-forms' ); ?></button>
						<button type="button" class="button-link-delete art-forms-crm-stage-delete"><?php echo esc_html__( 'Удалить', 'art-forms' ); ?></button>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="art-forms-crm-stage-add-row">
				<input type="text" id="art-forms-crm-new-stage-title" placeholder="<?php echo esc_attr__( 'Новый этап', 'art-forms' ); ?>" />
				<input type="color" id="art-forms-crm-new-stage-color" value="#2271b1" />
				<button type="button" class="button" id="art-forms-crm-add-stage"><?php echo esc_html__( 'Добавить этап', 'art-forms' ); ?></button>
			</p>
		</div>

	<?php elseif ( 'contacts' === $tab ) : ?>
		<div class="art-forms-panel">
			<table class="widefat striped art-forms-crm-contacts-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Имя', 'art-forms' ); ?></th>
						<th><?php echo esc_html__( 'Email', 'art-forms' ); ?></th>
						<th><?php echo esc_html__( 'Телефон', 'art-forms' ); ?></th>
						<th><?php echo esc_html__( 'Заявок', 'art-forms' ); ?></th>
						<th><?php echo esc_html__( 'Последняя', 'art-forms' ); ?></th>
						<th><?php echo esc_html__( 'Действия', 'art-forms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $contacts ) ) : ?>
						<tr><td colspan="6"><?php echo esc_html__( 'Контактов пока нет.', 'art-forms' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $contacts as $c ) : ?>
							<tr>
								<td><?php echo esc_html( ! empty( $c['contact_name'] ) ? $c['contact_name'] : '—' ); ?></td>
								<td><?php echo esc_html( $c['contact_email'] ? $c['contact_email'] : '—' ); ?></td>
								<td><?php echo esc_html( $c['contact_phone'] ? $c['contact_phone'] : '—' ); ?></td>
								<td><?php echo esc_html( (string) $c['submissions_count'] ); ?></td>
								<td><?php echo esc_html( Art_Forms_Submissions::format_datetime( $c['last_at'] ) ); ?></td>
								<td>
									<button type="button" class="button-link art-forms-crm-open-card" data-id="<?php echo esc_attr( (string) $c['last_id'] ); ?>">
										<?php echo esc_html__( 'Открыть последнюю заявку', 'art-forms' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

	<?php else : ?>

		<form method="get" class="art-forms-panel art-forms-crm-filters">
			<input type="hidden" name="page" value="art-forms-submissions" />
			<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $form_id ); ?>" />
			<input type="hidden" name="layout" value="<?php echo esc_attr( $layout ); ?>" />
			<input type="hidden" name="tab" value="leads" />
			<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>" />
			<input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>" />
			<?php if ( 'board' !== $layout ) : ?>
				<input type="hidden" name="stage_id" value="<?php echo esc_attr( (string) $stage_id ); ?>" />
			<?php endif; ?>
			<?php if ( ! empty( $starred ) ) : ?>
				<input type="hidden" name="starred" value="1" />
			<?php endif; ?>
			<p class="art-forms-crm-filters-row">
				<label>
					<?php echo esc_html__( 'С', 'art-forms' ); ?>
					<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" />
				</label>
				<label>
					<?php echo esc_html__( 'По', 'art-forms' ); ?>
					<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" />
				</label>
				<label>
					<?php echo esc_html__( 'Приоритет', 'art-forms' ); ?>
					<select name="priority">
						<option value=""><?php echo esc_html__( 'Любой', 'art-forms' ); ?></option>
						<?php foreach ( Art_Forms_Submissions::priority_labels() as $pkey => $plabel ) : ?>
							<option value="<?php echo esc_attr( (string) $pkey ); ?>" <?php selected( $priority_filter, (int) $pkey ); ?>>
								<?php echo esc_html( $plabel ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<?php echo esc_html__( 'Тег', 'art-forms' ); ?>
					<input type="text" name="tag" value="<?php echo esc_attr( $tag_filter ); ?>" placeholder="<?php echo esc_attr__( 'например vip', 'art-forms' ); ?>" autocomplete="off" />
				</label>
				<label>
					<?php echo esc_html__( 'Поиск', 'art-forms' ); ?>
					<input type="search" name="s" id="art-forms-crm-live-search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'По таблице…', 'art-forms' ); ?>" autocomplete="off" />
				</label>
				<span class="art-forms-crm-filters-actions">
					<button type="submit" class="button"><?php echo esc_html__( 'Фильтр', 'art-forms' ); ?></button>
					<?php
					$filters_active = ( '' !== $date_from || '' !== $date_to || '' !== $search || ! empty( $starred ) || $priority_filter >= 0 || '' !== $tag_filter );
					if ( $filters_active ) :
						$reset_args = array(
							'page'    => 'art-forms-submissions',
							'form_id' => $form_id,
							'layout'  => $layout,
							'tab'     => 'leads',
							'orderby' => $orderby,
							'order'   => $order,
							'paged'   => 1,
						);
						if ( 'board' !== $layout && $stage_id > 0 ) {
							$reset_args['stage_id'] = $stage_id;
						}
						$reset_url = add_query_arg( $reset_args, admin_url( 'admin.php' ) );
						?>
						<a class="button" href="<?php echo esc_url( $reset_url ); ?>"><?php echo esc_html__( 'Сбросить', 'art-forms' ); ?></a>
					<?php endif; ?>
					<?php
					$qf_all_args = $base_args;
					unset( $qf_all_args['starred'] );
					$qf_all_args['paged'] = 1;
					$qf_star_args         = array_merge( $base_args, array( 'starred' => 1, 'paged' => 1 ) );
					$qf_all_url           = add_query_arg( $qf_all_args, admin_url( 'admin.php' ) );
					$qf_star_url          = add_query_arg( $qf_star_args, admin_url( 'admin.php' ) );
					$star_cnt             = isset( $starred_total ) ? (int) $starred_total : 0;
					?>
					<span class="art-forms-crm-quick-filters" role="group" aria-label="<?php echo esc_attr__( 'Быстрые фильтры', 'art-forms' ); ?>">
						<a class="art-forms-crm-quick-filter <?php echo empty( $starred ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( $qf_all_url ); ?>">
							<?php echo esc_html__( 'Все заявки', 'art-forms' ); ?>
						</a>
						<a class="art-forms-crm-quick-filter art-forms-crm-quick-filter-star <?php echo ! empty( $starred ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( $qf_star_url ); ?>">
							★ <?php echo esc_html__( 'Избранное', 'art-forms' ); ?>
							<span class="art-forms-crm-stage-count"><?php echo esc_html( (string) $star_cnt ); ?></span>
						</a>
					</span>
				</span>
			</p>
		</form>

		<?php if ( 'table' === $layout ) : ?>
			<div class="art-forms-crm-stage-tabs" role="tablist">
				<?php
				$all_url = add_query_arg( array_merge( $base_args, array( 'stage_id' => 0, 'paged' => 1 ) ), admin_url( 'admin.php' ) );
				$all_cnt = isset( $counts['active_total'] ) ? (int) $counts['active_total'] : (int) ( $counts['total'] ?? 0 );
				?>
				<a class="art-forms-crm-stage-tab <?php echo 0 === $stage_id ? 'is-active' : ''; ?>" href="<?php echo esc_url( $all_url ); ?>" style="--stage-color:#1d2327">
					<?php echo esc_html__( 'Все', 'art-forms' ); ?>
					<span class="art-forms-crm-stage-count"><?php echo esc_html( (string) $all_cnt ); ?></span>
				</a>
				<?php foreach ( $stages as $st ) : ?>
					<?php
					$sid   = (int) $st['id'];
					$scnt  = isset( $counts['by_stage'][ $sid ] ) ? (int) $counts['by_stage'][ $sid ] : 0;
					$surl  = add_query_arg( array_merge( $base_args, array( 'stage_id' => $sid, 'paged' => 1 ) ), admin_url( 'admin.php' ) );
					?>
					<a class="art-forms-crm-stage-tab <?php echo $stage_id === $sid ? 'is-active' : ''; ?>" href="<?php echo esc_url( $surl ); ?>" style="--stage-color:<?php echo esc_attr( $st['color'] ); ?>">
						<?php echo esc_html( $st['title'] ); ?>
						<span class="art-forms-crm-stage-count"><?php echo esc_html( (string) $scnt ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>

			<div class="art-forms-crm-bulk-bar" id="art-forms-crm-bulk-bar" hidden>
				<select id="art-forms-crm-bulk-action">
					<option value=""><?php echo esc_html__( 'Действия', 'art-forms' ); ?></option>
					<option value="stage"><?php echo esc_html__( 'Сменить этап', 'art-forms' ); ?></option>
					<option value="star"><?php echo esc_html__( 'В избранное', 'art-forms' ); ?></option>
					<option value="unstar"><?php echo esc_html__( 'Убрать из избранного', 'art-forms' ); ?></option>
					<option value="delete"><?php echo esc_html__( 'Удалить', 'art-forms' ); ?></option>
				</select>
				<select id="art-forms-crm-bulk-stage" hidden>
					<?php foreach ( $stages as $st ) : ?>
						<option value="<?php echo esc_attr( (string) $st['id'] ); ?>"><?php echo esc_html( $st['title'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button" id="art-forms-crm-bulk-apply"><?php echo esc_html__( 'Применить', 'art-forms' ); ?></button>
			</div>

			<div class="art-forms-crm-table-wrap">
				<?php
				$sort_args = array_merge(
					$base_args,
					array(
						'stage_id' => $stage_id,
						'tab'      => 'leads',
					)
				);
				$width_style = '';
				$default_w   = array(
					'check'    => 36,
					'star'     => 36,
					'priority' => 90,
					'tags'     => 140,
					'id'       => 70,
					'name'     => 160,
					'date'     => 130,
					'stage'    => 120,
				);
				$width_style .= sprintf(
					'--crm-col-check:%dpx;',
					isset( $col_widths['check'] ) ? (int) $col_widths['check'] : $default_w['check']
				);
				foreach ( $table_col_order as $ck ) {
					$def = isset( $default_w[ $ck ] ) ? $default_w[ $ck ] : 160;
					$w   = isset( $col_widths[ $ck ] ) ? (int) $col_widths[ $ck ] : $def;
					$width_style .= '--crm-col-' . $ck . ':' . $w . 'px;';
				}

				$col_labels = array(
					'star'     => '★',
					'priority' => __( 'Приоритет', 'art-forms' ),
					'tags'     => __( 'Теги', 'art-forms' ),
					'id'       => 'ID',
					'name'     => __( 'Имя', 'art-forms' ),
					'date'     => __( 'Дата', 'art-forms' ),
					'stage'    => __( 'Этап', 'art-forms' ),
				);
				?>
				<table
					class="widefat striped art-forms-crm-table"
					id="art-forms-crm-table"
					style="<?php echo esc_attr( $width_style ); ?>"
					data-column-widths="<?php echo esc_attr( wp_json_encode( $col_widths ) ); ?>"
					data-column-order="<?php echo esc_attr( wp_json_encode( array_values( $table_col_order ) ) ); ?>"
				>
					<thead>
						<tr>
							<th class="art-forms-crm-col-check art-forms-crm-th" data-col="check" style="width:var(--crm-col-check)">
								<input type="checkbox" id="art-forms-crm-check-all" />
								<span class="art-forms-crm-col-resizer" data-col="check"></span>
							</th>
							<?php foreach ( $table_col_order as $ck ) : ?>
								<?php
								$is_field = isset( $fields_by_key[ $ck ] );
								$hid      = in_array( $ck, $hidden, true );
								if ( $is_field ) {
									$fc       = $fields_by_key[ $ck ];
									$flab_src = Art_Forms_Schema::field_display_label( $fc );
									$flab     = Art_Forms_Admin_Submissions::field_column_label( $ck, $flab_src, $col_aliases );
									$sort     = 'field_' . $ck;
									$th_class = 'art-forms-crm-dyn-col art-forms-crm-th art-forms-crm-th-draggable';
								} else {
									$flab_src = isset( $col_labels[ $ck ] ) ? $col_labels[ $ck ] : $ck;
									$flab     = Art_Forms_Admin_Submissions::field_column_label( $ck, $flab_src, $col_aliases );
									$sort     = $ck;
									$th_class = 'art-forms-crm-th art-forms-crm-th-draggable';
									if ( 'star' === $ck ) {
										$th_class .= ' art-forms-crm-col-star';
									}
								}
								?>
								<th
									class="<?php echo esc_attr( $th_class ); ?>"
									data-col="<?php echo esc_attr( $ck ); ?>"
									data-original-label="<?php echo esc_attr( $flab_src ); ?>"
									title="<?php echo esc_attr( $flab_src ); ?>"
									draggable="true"
									style="width:var(--crm-col-<?php echo esc_attr( $ck ); ?>)"
									<?php echo $hid ? 'hidden' : ''; ?>
								>
									<span class="art-forms-crm-col-drag" title="<?php echo esc_attr__( 'Перетащить колонку', 'art-forms' ); ?>" aria-hidden="true">⋮⋮</span>
									<?php echo Art_Forms_Admin_Submissions::sort_header( $sort, $flab, $orderby, $order, $sort_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<span class="art-forms-crm-col-resizer" data-col="<?php echo esc_attr( $ck ); ?>"></span>
								</th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $items ) ) : ?>
							<tr><td colspan="<?php echo esc_attr( (string) ( 1 + count( $table_col_order ) ) ); ?>"><?php echo esc_html__( 'Заявок нет.', 'art-forms' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $items as $item ) : ?>
								<?php
								$st = isset( $stages_by_id[ (int) $item['stage_id'] ] ) ? $stages_by_id[ (int) $item['stage_id'] ] : null;
								?>
								<tr class="art-forms-crm-row" data-id="<?php echo esc_attr( (string) $item['id'] ); ?>" data-stage-id="<?php echo esc_attr( (string) $item['stage_id'] ); ?>">
									<td class="art-forms-crm-col-check" data-col="check">
										<input type="checkbox" class="art-forms-crm-row-check" value="<?php echo esc_attr( (string) $item['id'] ); ?>" />
									</td>
									<?php foreach ( $table_col_order as $ck ) : ?>
										<?php $cell_hid = in_array( $ck, $hidden, true ); ?>
										<?php if ( 'star' === $ck ) : ?>
											<td class="art-forms-crm-col-star" data-col="star" <?php echo $cell_hid ? 'hidden' : ''; ?>>
												<button type="button" class="art-forms-crm-star <?php echo ! empty( $item['is_starred'] ) ? 'is-on' : ''; ?>" data-id="<?php echo esc_attr( (string) $item['id'] ); ?>" aria-label="<?php echo esc_attr__( 'Избранное', 'art-forms' ); ?>">★</button>
											</td>
										<?php elseif ( 'priority' === $ck ) : ?>
											<?php
											$prio        = isset( $item['priority'] ) ? (int) $item['priority'] : 0;
											$prio_labels = Art_Forms_Submissions::priority_labels();
											$prio_label  = isset( $prio_labels[ $prio ] ) ? $prio_labels[ $prio ] : $prio_labels[0];
											?>
											<td class="art-forms-crm-cell" data-col="priority" data-full="<?php echo esc_attr( $prio_label ); ?>" <?php echo $cell_hid ? 'hidden' : ''; ?>>
												<span class="art-forms-crm-priority art-forms-crm-priority-<?php echo esc_attr( (string) $prio ); ?> art-forms-crm-cell-text"><?php echo esc_html( $prio_label ); ?></span>
											</td>
										<?php elseif ( 'tags' === $ck ) : ?>
											<?php
											$item_tags = isset( $item['tags'] ) && is_array( $item['tags'] ) ? $item['tags'] : array();
											$tags_str  = ! empty( $item_tags ) ? implode( ', ', $item_tags ) : '—';
											?>
											<td class="art-forms-crm-cell" data-col="tags" data-full="<?php echo esc_attr( $tags_str ); ?>" <?php echo $cell_hid ? 'hidden' : ''; ?>>
												<span class="art-forms-crm-cell-text"><?php echo esc_html( $tags_str ); ?></span>
											</td>
										<?php elseif ( 'id' === $ck ) : ?>
											<td class="art-forms-crm-cell" data-col="id" data-full="<?php echo esc_attr( (string) $item['id'] ); ?>" <?php echo $cell_hid ? 'hidden' : ''; ?>>
												<button type="button" class="button-link art-forms-crm-open-card" data-id="<?php echo esc_attr( (string) $item['id'] ); ?>"><?php echo esc_html( (string) $item['id'] ); ?></button>
											</td>
										<?php elseif ( 'name' === $ck ) : ?>
											<?php
											$cname = isset( $item['contact_name'] ) ? trim( (string) $item['contact_name'] ) : '';
											$cname_disp = '' !== $cname ? $cname : '—';
											?>
											<td class="art-forms-crm-cell" data-col="name" data-full="<?php echo esc_attr( $cname ); ?>" <?php echo $cell_hid ? 'hidden' : ''; ?>>
												<span class="art-forms-crm-cell-text"><?php echo esc_html( $cname_disp ); ?></span>
											</td>
										<?php elseif ( 'date' === $ck ) : ?>
											<td class="art-forms-crm-cell" data-col="date" data-full="<?php echo esc_attr( Art_Forms_Submissions::format_datetime( (string) $item['created_at'] ) ); ?>" <?php echo $cell_hid ? 'hidden' : ''; ?>>
												<span class="art-forms-crm-cell-text"><?php echo esc_html( Art_Forms_Submissions::format_datetime( (string) $item['created_at'] ) ); ?></span>
											</td>
										<?php elseif ( 'stage' === $ck ) : ?>
											<td class="art-forms-crm-cell" data-col="stage" data-full="<?php echo esc_attr( $st ? (string) $st['title'] : '—' ); ?>" <?php echo $cell_hid ? 'hidden' : ''; ?>>
												<?php if ( $st ) : ?>
													<span class="art-forms-crm-stage-pill art-forms-crm-cell-text" style="--stage-color:<?php echo esc_attr( $st['color'] ); ?>"><?php echo esc_html( $st['title'] ); ?></span>
												<?php else : ?>
													<span class="art-forms-crm-cell-text">—</span>
												<?php endif; ?>
											</td>
										<?php elseif ( isset( $fields_by_key[ $ck ] ) ) : ?>
											<?php
											$fc   = $fields_by_key[ $ck ];
											$raw  = isset( $item['payload'][ $ck ] ) ? $item['payload'][ $ck ] : '';
											$disp = Art_Forms_Schema::format_display_value( $fc, $raw );
											?>
											<td class="art-forms-crm-dyn-col art-forms-crm-cell" data-col="<?php echo esc_attr( $ck ); ?>" data-full="<?php echo esc_attr( $disp ); ?>" <?php echo $cell_hid ? 'hidden' : ''; ?>>
												<span class="art-forms-crm-cell-text"><?php echo esc_html( $disp ); ?></span>
											</td>
										<?php endif; ?>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'base'      => add_query_arg(
										array_merge(
											$base_args,
											array(
												'stage_id' => $stage_id,
												'paged'    => '%#%',
											)
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

		<?php else : /* board */ ?>
			<div class="art-forms-crm-board" id="art-forms-crm-board">
				<?php foreach ( $stages as $st ) : ?>
					<?php
					$sid   = (int) $st['id'];
					$cards = isset( $board_groups[ $sid ] ) ? $board_groups[ $sid ] : array();
					$scnt  = isset( $counts['by_stage'][ $sid ] ) ? (int) $counts['by_stage'][ $sid ] : count( $cards );
					?>
					<div class="art-forms-crm-board-col" data-stage-id="<?php echo esc_attr( (string) $sid ); ?>" style="--stage-color:<?php echo esc_attr( $st['color'] ); ?>">
						<div class="art-forms-crm-board-col-head">
							<span class="art-forms-crm-board-col-title"><?php echo esc_html( $st['title'] ); ?></span>
							<span class="art-forms-crm-stage-count"><?php echo esc_html( (string) $scnt ); ?></span>
						</div>
						<div class="art-forms-crm-board-cards" data-stage-id="<?php echo esc_attr( (string) $sid ); ?>">
							<?php foreach ( $cards as $item ) : ?>
								<?php
								$preview = ! empty( $item['contact_name'] )
									? $item['contact_name']
									: ( $item['contact_email'] ? $item['contact_email'] : $item['contact_phone'] );
								if ( ! $preview && ! empty( $field_columns[0] ) ) {
									$fk = $field_columns[0]['key'];
									$preview = isset( $item['payload'][ $fk ] ) ? Art_Forms_Schema::format_display_value( $field_columns[0], $item['payload'][ $fk ] ) : '';
								}
								$card_prio = isset( $item['priority'] ) ? (int) $item['priority'] : 0;
								$prio_labels = Art_Forms_Submissions::priority_labels();
								$card_tags = isset( $item['tags'] ) && is_array( $item['tags'] ) ? $item['tags'] : array();
								?>
								<div
									class="art-forms-crm-board-card"
									draggable="true"
									data-id="<?php echo esc_attr( (string) $item['id'] ); ?>"
									data-stage-id="<?php echo esc_attr( (string) $item['stage_id'] ); ?>"
									data-full="<?php echo esc_attr( trim( ( isset( $item['contact_name'] ) ? (string) $item['contact_name'] : '' ) . ' ' . (string) $item['contact_email'] . ' ' . (string) $item['contact_phone'] ) ); ?>"
								>
									<button type="button" class="art-forms-crm-star <?php echo ! empty( $item['is_starred'] ) ? 'is-on' : ''; ?>" data-id="<?php echo esc_attr( (string) $item['id'] ); ?>">★</button>
									<button type="button" class="art-forms-crm-board-card-open art-forms-crm-open-card" data-id="<?php echo esc_attr( (string) $item['id'] ); ?>">
										<span class="art-forms-crm-board-card-id">#<?php echo esc_html( (string) $item['id'] ); ?></span>
										<span class="art-forms-crm-board-card-preview"><?php echo esc_html( Art_Forms_Admin_Submissions::truncate( (string) $preview, 80 ) ); ?></span>
										<?php if ( $card_prio > 0 ) : ?>
											<span class="art-forms-crm-board-card-priority art-forms-crm-priority art-forms-crm-priority-<?php echo esc_attr( (string) $card_prio ); ?>"><?php echo esc_html( $prio_labels[ $card_prio ] ?? '' ); ?></span>
										<?php endif; ?>
										<?php if ( ! empty( $card_tags ) ) : ?>
											<span class="art-forms-crm-board-card-tags"><?php echo esc_html( implode( ', ', $card_tags ) ); ?></span>
										<?php endif; ?>
										<span class="art-forms-crm-board-card-date"><?php echo esc_html( Art_Forms_Submissions::format_datetime( (string) $item['created_at'] ) ); ?></span>
									</button>
								</div>
							<?php endforeach; ?>
							<?php if ( ! empty( $board_more[ $sid ] ) ) : ?>
								<p class="art-forms-crm-board-more">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: number of hidden cards */
											__( 'Ещё %d — уточните фильтр или откройте таблицу', 'art-forms' ),
											(int) $board_more[ $sid ]
										)
									);
									?>
								</p>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<div class="art-forms-crm-modal" id="art-forms-crm-modal" hidden>
		<div class="art-forms-crm-modal-backdrop" data-crm-close></div>
		<div class="art-forms-crm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="art-forms-crm-modal-title">
			<div class="art-forms-crm-modal-toolbar">
				<div class="art-forms-crm-modal-nav">
					<button type="button" class="button" id="art-forms-crm-prev" title="<?php echo esc_attr__( 'Предыдущая', 'art-forms' ); ?>">&lsaquo;</button>
					<button type="button" class="button" id="art-forms-crm-next" title="<?php echo esc_attr__( 'Следующая', 'art-forms' ); ?>">&rsaquo;</button>
				</div>
				<strong id="art-forms-crm-modal-title"><?php echo esc_html__( 'Заявка', 'art-forms' ); ?></strong>
				<div class="art-forms-crm-modal-actions">
					<button type="button" class="button" id="art-forms-crm-edit-fields"><?php echo esc_html__( 'Редактировать', 'art-forms' ); ?></button>
					<button type="button" class="button button-primary" id="art-forms-crm-save-fields" hidden><?php echo esc_html__( 'Сохранить', 'art-forms' ); ?></button>
					<button type="button" class="button" id="art-forms-crm-cancel-fields" hidden><?php echo esc_html__( 'Отмена', 'art-forms' ); ?></button>
					<button type="button" class="art-forms-crm-star" id="art-forms-crm-modal-star">★</button>
					<a class="button-link-delete" id="art-forms-crm-modal-delete" href="#"><?php echo esc_html__( 'Удалить', 'art-forms' ); ?></a>
					<button type="button" class="button" data-crm-close><?php echo esc_html__( 'Закрыть', 'art-forms' ); ?></button>
				</div>
			</div>
			<div class="art-forms-crm-modal-body">
				<div class="art-forms-crm-modal-main">
					<label class="art-forms-crm-modal-stage-label">
						<span><?php echo esc_html__( 'Этап заявки', 'art-forms' ); ?></span>
						<select id="art-forms-crm-modal-stage"></select>
					</label>
					<div class="art-forms-crm-modal-meta" id="art-forms-crm-modal-meta"></div>
					<div class="art-forms-crm-modal-fields" id="art-forms-crm-modal-fields"></div>
					<div class="art-forms-crm-modal-related" id="art-forms-crm-modal-related"></div>
				</div>
				<div class="art-forms-crm-modal-side">
					<h3><?php echo esc_html__( 'Комментарии', 'art-forms' ); ?></h3>
					<div id="art-forms-crm-modal-comments" class="art-forms-crm-comments"></div>
					<textarea id="art-forms-crm-comment-body" rows="3" placeholder="<?php echo esc_attr__( 'Новый комментарий…', 'art-forms' ); ?>"></textarea>
					<button type="button" class="button button-primary" id="art-forms-crm-comment-add"><?php echo esc_html__( 'Добавить', 'art-forms' ); ?></button>
					<h3 class="art-forms-crm-activity-title"><?php echo esc_html__( 'История', 'art-forms' ); ?></h3>
					<div id="art-forms-crm-modal-activity" class="art-forms-crm-activity"></div>
				</div>
			</div>
		</div>
	</div>
</div>
