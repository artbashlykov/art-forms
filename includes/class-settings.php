<?php
/**
 * Global plugin settings.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Settings
 */
class Art_Forms_Settings {

	const OPTION_KEY = 'art_forms_settings';

	const CRON_HOOK = 'art_forms_cleanup_submissions';

	/**
	 * Default global settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'default_email_to'         => get_option( 'admin_email', '' ),
			'default_email_subject'    => __( 'Новая заявка: {form_title}', 'art-forms' ),
			'default_email_body'       => self::default_email_body(),
			'default_success_message'  => __( 'Спасибо! Мы получили ваши данные.', 'art-forms' ),
			'default_privacy_url'      => '',
			'retention_days'           => 0,
			'store_payload'            => 'full',
			'honeypot_enabled'         => 1,
			'rate_limit_enabled'       => 1,
			'rate_limit_max'           => 5,
			'rate_limit_window'        => 10,
			'delivery_fail_notify'     => 1,
			'delivery_fail_email'      => get_option( 'admin_email', '' ),
			'crm_notify_enabled'       => 1,
			'crm_notify_email'         => '',
			'crm_manager_ids'          => array(),
		);
	}

	/**
	 * Default email body template.
	 *
	 * @return string
	 */
	public static function default_email_body() {
		return implode(
			"\n",
			array(
				__( 'Новая заявка с формы «{form_title}».', 'art-forms' ),
				'',
				__( 'ID ответа: {submission_id}', 'art-forms' ),
				__( 'Email: {email}', 'art-forms' ),
				__( 'Телефон: {phone}', 'art-forms' ),
				__( 'Страница: {page_url}', 'art-forms' ),
				'',
				__( 'Ответы:', 'art-forms' ),
				'{all_fields}',
			)
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$all = wp_parse_args( $stored, self::defaults() );

		$all['honeypot_enabled']     = ! empty( $all['honeypot_enabled'] ) ? 1 : 0;
		$all['rate_limit_enabled']   = ! empty( $all['rate_limit_enabled'] ) ? 1 : 0;
		$all['delivery_fail_notify'] = ! empty( $all['delivery_fail_notify'] ) ? 1 : 0;
		$all['crm_notify_enabled']   = ! empty( $all['crm_notify_enabled'] ) ? 1 : 0;
		$all['retention_days']       = absint( $all['retention_days'] );
		$all['rate_limit_max']       = max( 1, absint( $all['rate_limit_max'] ) );
		$all['rate_limit_window']    = max( 1, absint( $all['rate_limit_window'] ) );
		$all['store_payload']        = in_array( $all['store_payload'], array( 'full', 'contacts' ), true ) ? $all['store_payload'] : 'full';

		$mgr = array();
		if ( ! empty( $all['crm_manager_ids'] ) && is_array( $all['crm_manager_ids'] ) ) {
			foreach ( $all['crm_manager_ids'] as $uid ) {
				$uid = absint( $uid );
				if ( $uid > 0 ) {
					$mgr[] = $uid;
				}
			}
		}
		$all['crm_manager_ids'] = array_values( array_unique( $mgr ) );

		return $all;
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::get_all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}

		return $default;
	}

	/**
	 * Save settings.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	public static function update( array $settings ) {
		$clean = self::defaults();

		if ( isset( $settings['default_email_to'] ) ) {
			$emails = self::sanitize_email_list( (string) $settings['default_email_to'] );
			$clean['default_email_to'] = implode( ', ', $emails );
		}

		if ( isset( $settings['default_email_subject'] ) ) {
			$clean['default_email_subject'] = sanitize_text_field( (string) $settings['default_email_subject'] );
		}

		if ( isset( $settings['default_email_body'] ) ) {
			$clean['default_email_body'] = sanitize_textarea_field( (string) $settings['default_email_body'] );
		}

		if ( isset( $settings['default_success_message'] ) ) {
			$clean['default_success_message'] = sanitize_textarea_field( (string) $settings['default_success_message'] );
		}

		if ( isset( $settings['default_privacy_url'] ) ) {
			$clean['default_privacy_url'] = esc_url_raw( (string) $settings['default_privacy_url'] );
		}

		if ( isset( $settings['retention_days'] ) ) {
			$clean['retention_days'] = absint( $settings['retention_days'] );
		}

		if ( isset( $settings['store_payload'] ) ) {
			$mode = sanitize_key( (string) $settings['store_payload'] );
			$clean['store_payload'] = in_array( $mode, array( 'full', 'contacts' ), true ) ? $mode : 'full';
		}

		$clean['honeypot_enabled']     = ! empty( $settings['honeypot_enabled'] ) ? 1 : 0;
		$clean['rate_limit_enabled']   = ! empty( $settings['rate_limit_enabled'] ) ? 1 : 0;
		$clean['delivery_fail_notify'] = ! empty( $settings['delivery_fail_notify'] ) ? 1 : 0;
		$clean['crm_notify_enabled']   = ! empty( $settings['crm_notify_enabled'] ) ? 1 : 0;

		if ( isset( $settings['rate_limit_max'] ) ) {
			$clean['rate_limit_max'] = max( 1, absint( $settings['rate_limit_max'] ) );
		}

		if ( isset( $settings['rate_limit_window'] ) ) {
			$clean['rate_limit_window'] = max( 1, absint( $settings['rate_limit_window'] ) );
		}

		if ( isset( $settings['delivery_fail_email'] ) ) {
			$email = sanitize_email( (string) $settings['delivery_fail_email'] );
			$clean['delivery_fail_email'] = is_email( $email ) ? $email : '';
		}

		if ( isset( $settings['crm_notify_email'] ) ) {
			$emails = self::sanitize_email_list( (string) $settings['crm_notify_email'] );
			$clean['crm_notify_email'] = implode( ', ', $emails );
		}

		$mgr = array();
		if ( isset( $settings['crm_manager_ids'] ) && is_array( $settings['crm_manager_ids'] ) ) {
			foreach ( $settings['crm_manager_ids'] as $uid ) {
				$uid = absint( $uid );
				if ( $uid > 0 ) {
					$mgr[] = $uid;
				}
			}
		}
		$clean['crm_manager_ids'] = array_values( array_unique( $mgr ) );

		update_option( self::OPTION_KEY, $clean );
		if ( class_exists( 'Art_Forms_Capabilities' ) ) {
			Art_Forms_Capabilities::apply_manager_ids( $clean['crm_manager_ids'] );
		}
		self::schedule_cron();
	}

	/**
	 * Sanitize comma-separated email list.
	 *
	 * @param string $raw Raw string.
	 * @return array<int, string>
	 */
	public static function sanitize_email_list( $raw ) {
		$parts  = preg_split( '/[,;\s]+/', $raw );
		$result = array();

		if ( ! is_array( $parts ) ) {
			return $result;
		}

		foreach ( $parts as $part ) {
			$email = sanitize_email( trim( (string) $part ) );
			if ( $email && is_email( $email ) ) {
				$result[] = $email;
			}
		}

		return array_values( array_unique( $result ) );
	}

	/**
	 * Whether honeypot is enabled globally.
	 *
	 * @return bool
	 */
	public static function honeypot_enabled() {
		return ! empty( self::get( 'honeypot_enabled' ) );
	}

	/**
	 * Schedule daily cleanup cron.
	 */
	public static function schedule_cron() {
		$days = absint( self::get( 'retention_days', 0 ) );
		if ( $days <= 0 ) {
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, self::CRON_HOOK );
			}
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Clear scheduled cleanup.
	 */
	public static function clear_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Delete submissions older than retention_days.
	 *
	 * @return int Deleted rows count.
	 */
	public static function cleanup_old_submissions() {
		$days = absint( self::get( 'retention_days', 0 ) );
		if ( $days <= 0 ) {
			return 0;
		}

		return Art_Forms_Submissions::delete_older_than( $days );
	}
}
