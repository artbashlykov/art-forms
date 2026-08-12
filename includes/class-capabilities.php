<?php
/**
 * Capabilities for ART Forms CRM managers.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Capabilities
 */
class Art_Forms_Capabilities {

	/** Full plugin (forms, settings). */
	const CAP_MANAGE = 'art_forms_manage';

	/** CRM: answers + comments (no form builder / settings). */
	const CAP_CRM = 'art_forms_crm';

	/**
	 * Register caps on roles (idempotent).
	 */
	public static function register() {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::CAP_MANAGE );
			$admin->add_cap( self::CAP_CRM );
		}
		self::apply_manager_ids( Art_Forms_Settings::get( 'crm_manager_ids', array() ) );
	}

	/**
	 * Can manage full plugin.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' ) || current_user_can( self::CAP_MANAGE );
	}

	/**
	 * Can use CRM (Ответы).
	 *
	 * @return bool
	 */
	public static function can_crm() {
		return self::can_manage() || current_user_can( self::CAP_CRM );
	}

	/**
	 * Apply CRM manager user IDs (grant/revoke cap).
	 *
	 * @param array<int, int|string> $ids User IDs.
	 */
	public static function apply_manager_ids( $ids ) {
		if ( ! is_array( $ids ) ) {
			$ids = array();
		}
		$new_ids = array();
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 ) {
				$new_ids[] = $id;
			}
		}
		$new_ids = array_values( array_unique( $new_ids ) );

		$prev = get_option( 'art_forms_crm_manager_ids_prev', array() );
		if ( ! is_array( $prev ) ) {
			$prev = array();
		}
		$prev = array_map( 'absint', $prev );

		foreach ( $prev as $uid ) {
			if ( in_array( $uid, $new_ids, true ) ) {
				continue;
			}
			if ( user_can( $uid, 'manage_options' ) || user_can( $uid, self::CAP_MANAGE ) ) {
				continue;
			}
			$user = get_userdata( $uid );
			if ( $user ) {
				$user->remove_cap( self::CAP_CRM );
			}
		}

		foreach ( $new_ids as $uid ) {
			$user = get_userdata( $uid );
			if ( $user ) {
				$user->add_cap( self::CAP_CRM );
			}
		}

		update_option( 'art_forms_crm_manager_ids_prev', $new_ids, false );
	}
}
