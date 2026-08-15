<?php
/**
 * CRM notifications: admin badge + email on new submissions.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Crm_Notifications
 */
class Art_Forms_Crm_Notifications {

	const USER_META_SEEN_ID = 'art_forms_crm_seen_id';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'art_forms_submission_created', array( __CLASS__, 'on_submission_created' ), 20, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'apply_menu_badge' ), 999 );
	}

	/**
	 * After a submission is stored — optional short CRM email.
	 *
	 * @param int                  $submission_id Submission ID.
	 * @param array<string, mixed> $context       Context.
	 */
	public static function on_submission_created( $submission_id, $context ) {
		unset( $context );
		$submission_id = absint( $submission_id );
		if ( $submission_id <= 0 ) {
			return;
		}
		if ( empty( Art_Forms_Settings::get( 'crm_notify_enabled', 1 ) ) ) {
			return;
		}

		self::send_crm_email( $submission_id );
	}

	/**
	 * Short email with link to open the lead in CRM.
	 *
	 * @param int $submission_id Submission ID.
	 * @return bool
	 */
	public static function send_crm_email( $submission_id ) {
		$submission = Art_Forms_Submissions::get( $submission_id );
		if ( ! $submission ) {
			return false;
		}

		$raw_to = (string) Art_Forms_Settings::get( 'crm_notify_email', '' );
		if ( '' === trim( $raw_to ) ) {
			$raw_to = (string) Art_Forms_Settings::get( 'default_email_to', get_option( 'admin_email', '' ) );
		}
		$to_list = Art_Forms_Settings::sanitize_email_list( $raw_to );
		if ( empty( $to_list ) ) {
			$admin = sanitize_email( (string) get_option( 'admin_email', '' ) );
			if ( is_email( $admin ) ) {
				$to_list = array( $admin );
			}
		}
		if ( empty( $to_list ) ) {
			return false;
		}

		$normalized_crm = array();
		foreach ( $to_list as $email ) {
			$normalized_crm[] = strtolower( $email );
		}

		$form_id = (int) $submission['form_id'];
		$admin_to = class_exists( 'Art_Forms_Form_Actions' )
			? Art_Forms_Form_Actions::admin_email_recipients( $form_id )
			: array();
		if ( ! empty( array_intersect( $normalized_crm, $admin_to ) ) ) {
			// Form already sends a full notification to the same inbox.
			return false;
		}

		$form_title = get_the_title( $form_id );
		if ( '' === $form_title ) {
			$form_title = __( '(без названия)', 'art-forms' );
		}

		$crm_url = add_query_arg(
			array(
				'page'    => 'art-forms-submissions',
				'form_id' => $form_id,
				'view'    => $submission_id,
			),
			admin_url( 'admin.php' )
		);

		$subject = sprintf(
			/* translators: 1: form title, 2: submission id */
			__( '[ART Forms] Новая заявка #%2$d — %1$s', 'art-forms' ),
			$form_title,
			$submission_id
		);

		$lines = array(
			sprintf(
				/* translators: %s: form title */
				__( 'Новая заявка с формы «%s».', 'art-forms' ),
				$form_title
			),
			'',
			sprintf(
				/* translators: %d: submission id */
				__( 'ID: %d', 'art-forms' ),
				$submission_id
			),
		);
		if ( ! empty( $submission['contact_name'] ) ) {
			$lines[] = __( 'Имя:', 'art-forms' ) . ' ' . $submission['contact_name'];
		}
		if ( ! empty( $submission['contact_email'] ) ) {
			$lines[] = 'Email: ' . $submission['contact_email'];
		}
		if ( ! empty( $submission['contact_phone'] ) ) {
			$lines[] = __( 'Телефон:', 'art-forms' ) . ' ' . $submission['contact_phone'];
		}
		$lines[] = '';
		$lines[] = __( 'Открыть в CRM:', 'art-forms' );
		$lines[] = $crm_url;

		$body    = implode( "\n", $lines );
		$headers = class_exists( 'Art_Forms_Delivery_Email' )
			? Art_Forms_Delivery_Email::mail_headers( 'text/plain; charset=UTF-8' )
			: array( 'Content-Type: text/plain; charset=UTF-8' );

		return (bool) wp_mail( $to_list, $subject, $body, $headers );
	}

	/**
	 * Last seen submission id for current user.
	 *
	 * @return int
	 */
	public static function get_seen_id() {
		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			return 0;
		}
		return absint( get_user_meta( $uid, self::USER_META_SEEN_ID, true ) );
	}

	/**
	 * Mark submissions up to max id as seen.
	 *
	 * @param int $max_id Max submission id.
	 */
	public static function mark_seen( $max_id = 0 ) {
		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			return;
		}
		$max_id = absint( $max_id );
		if ( $max_id <= 0 ) {
			$max_id = self::max_submission_id();
		}
		$current = self::get_seen_id();
		if ( $max_id > $current ) {
			update_user_meta( $uid, self::USER_META_SEEN_ID, $max_id );
		}
	}

	/**
	 * Highest submission id.
	 *
	 * @return int
	 */
	public static function max_submission_id() {
		global $wpdb;
		$table = Art_Forms_Submissions::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$max = $wpdb->get_var( 'SELECT MAX(id) FROM `' . esc_sql( $table ) . '`' );
		return absint( $max );
	}

	/**
	 * Unseen submissions count for menu badge.
	 *
	 * @return int
	 */
	public static function unseen_count() {
		$seen = self::get_seen_id();
		global $wpdb;
		$table = Art_Forms_Submissions::table();
		if ( $seen > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '` WHERE id > %d',
					$seen
				)
			);
		} else {
			// First visit: baseline to current max so history is not a flood of badges.
			self::mark_seen( self::max_submission_id() );
			return 0;
		}
		return absint( $count );
	}

	/**
	 * Append awaiting-mod badge to «Ответы» submenu.
	 */
	public static function apply_menu_badge() {
		if ( ! Art_Forms_Capabilities::can_crm() ) {
			return;
		}

		$count = self::unseen_count();
		if ( $count <= 0 ) {
			return;
		}

		global $submenu;
		$parent = ART_FORMS_ADMIN_MENU_SLUG;
		if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
			return;
		}

		$label = sprintf(
			/* translators: %s: unread count HTML bubble */
			__( 'Ответы %s', 'art-forms' ),
			'<span class="awaiting-mod">' . esc_html( (string) $count ) . '</span>'
		);

		foreach ( $submenu[ $parent ] as $i => $item ) {
			if ( isset( $item[2] ) && 'art-forms-submissions' === $item[2] ) {
				$submenu[ $parent ][ $i ][0] = $label;
				break;
			}
		}

		// Also badge on top-level menu if count > 0.
		global $menu;
		if ( ! empty( $menu ) && is_array( $menu ) ) {
			foreach ( $menu as $i => $item ) {
				if ( isset( $item[2] ) && ART_FORMS_ADMIN_MENU_SLUG === $item[2] ) {
					$menu[ $i ][0] = 'ART Forms <span class="awaiting-mod">' . esc_html( (string) $count ) . '</span>';
					break;
				}
			}
		}
	}
}
