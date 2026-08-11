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
	 * @param array<string, mixed> $args Query args (form_id, date_from, date_to).
	 */
	public static function download( array $args ) {
		$form_id = isset( $args['form_id'] ) ? absint( $args['form_id'] ) : 0;

		$result = Art_Forms_Submissions::query(
			array(
				'form_id'   => $form_id,
				'date_from' => isset( $args['date_from'] ) ? $args['date_from'] : '',
				'date_to'   => isset( $args['date_to'] ) ? $args['date_to'] : '',
				'per_page'  => 10000,
				'page'      => 1,
			)
		);

		$items      = $result['items'];
		$field_keys = array();

		if ( $form_id > 0 ) {
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

		$headers = array_merge(
			array(
				'id',
				'form_id',
				'created_at',
				'status',
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
			$row = array(
				$item['id'],
				$item['form_id'],
				$item['created_at'],
				$item['status'],
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
