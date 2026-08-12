<?php
/**
 * CSV export of submissions.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Csv_Export
 */
class Art_Forms_Csv_Export {

	/**
	 * Stream CSV download and exit.
	 *
	 * @param array<string, mixed> $args Query args (form_id, date_from, date_to, …).
	 */
	public static function download( array $args ) {
		$form_id = isset( $args['form_id'] ) ? absint( $args['form_id'] ) : 0;

		$query = array(
			'form_id'   => $form_id,
			'date_from' => isset( $args['date_from'] ) ? $args['date_from'] : '',
			'date_to'   => isset( $args['date_to'] ) ? $args['date_to'] : '',
			'per_page'  => 10000,
			'page'      => 1,
			'orderby'   => 'id',
			'order'     => 'desc',
		);

		$stage_id = isset( $args['stage_id'] ) ? absint( $args['stage_id'] ) : 0;
		if ( $stage_id > 0 ) {
			$query['stage_id'] = $stage_id;
		} elseif ( $form_id > 0 ) {
			$archive_ids = Art_Forms_Stages::archive_ids( $form_id );
			if ( ! empty( $archive_ids ) ) {
				$query['exclude_stage_ids'] = $archive_ids;
			}
		}

		if ( ! empty( $args['starred'] ) ) {
			$query['is_starred'] = 1;
		}

		if ( isset( $args['priority'] ) && '' !== (string) $args['priority'] && is_numeric( $args['priority'] ) ) {
			$prio = absint( $args['priority'] );
			if ( $prio <= 3 ) {
				$query['priority'] = $prio;
			}
		}

		if ( ! empty( $args['tag'] ) ) {
			$query['tag'] = sanitize_text_field( (string) $args['tag'] );
		}

		$result = Art_Forms_Submissions::query( $query );

		$items      = $result['items'];
		$field_keys = array();

		$stages_map = array();
		if ( $form_id > 0 ) {
			foreach ( Art_Forms_Stages::get_for_form( $form_id ) as $st ) {
				$stages_map[ (int) $st['id'] ] = (string) $st['title'];
			}
			foreach ( Art_Forms_Schema::flatten_fields( Art_Forms_Schema::get( $form_id ) ) as $field ) {
				if ( ! empty( $field['key'] ) ) {
					$field_keys[] = $field['key'];
				}
			}
		} else {
			foreach ( $items as $item ) {
				if ( ! empty( $item['payload'] ) && is_array( $item['payload'] ) ) {
					foreach ( array_keys( $item['payload'] ) as $key ) {
						$field_keys[ $key ] = true;
					}
				}
			}
			$field_keys = array_keys( $field_keys );
		}

		$prio_labels = Art_Forms_Submissions::priority_labels();

		$headers = array_merge(
			array(
				'id',
				'form_id',
				'created_at',
				'status',
				'stage',
				'is_starred',
				'priority',
				'priority_label',
				'tags',
				'user_id',
				'contact_email',
				'contact_phone',
				'page_url',
				'referrer',
				'utm_source',
				'utm_medium',
				'utm_campaign',
				'utm_content',
				'utm_term',
				'ip',
			),
			$field_keys
		);

		$filename = 'art-forms-submissions-' . gmdate( 'Ymd-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'Не удалось открыть поток для CSV.', 'art-forms' ) );
		}

		fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		fputcsv( $out, $headers, ';' );

		foreach ( $items as $item ) {
			$sid   = isset( $item['stage_id'] ) ? (int) $item['stage_id'] : 0;
			$stage = isset( $stages_map[ $sid ] ) ? $stages_map[ $sid ] : '';
			if ( '' === $stage && $sid > 0 ) {
				$st = Art_Forms_Stages::get( $sid );
				$stage = $st ? (string) $st['title'] : '';
			}

			$prio = isset( $item['priority'] ) ? (int) $item['priority'] : 0;
			$tags = isset( $item['tags'] ) && is_array( $item['tags'] ) ? implode( ', ', $item['tags'] ) : '';

			$row = array(
				$item['id'],
				$item['form_id'],
				$item['created_at'],
				$item['status'],
				$stage,
				! empty( $item['is_starred'] ) ? 1 : 0,
				$prio,
				isset( $prio_labels[ $prio ] ) ? $prio_labels[ $prio ] : '',
				$tags,
				$item['user_id'],
				$item['contact_email'],
				$item['contact_phone'],
				$item['page_url'],
				$item['referrer'],
				$item['utm_source'],
				$item['utm_medium'],
				$item['utm_campaign'],
				$item['utm_content'],
				$item['utm_term'],
				$item['ip'],
			);

			$payload = is_array( $item['payload'] ) ? $item['payload'] : array();
			foreach ( $field_keys as $key ) {
				$value = isset( $payload[ $key ] ) ? $payload[ $key ] : '';
				if ( is_array( $value ) ) {
					$value = implode( ', ', $value );
				}
				$row[] = $value;
			}

			fputcsv( $out, $row, ';' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $out );
		exit;
	}
}
