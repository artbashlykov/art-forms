<?php
/**
 * Custom post types.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Post_Types
 */
class Art_Forms_Post_Types {

	const POST_TYPE = 'art_form';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register form CPT.
	 */
	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Формы', 'art-forms' ),
					'singular_name'      => __( 'Форма', 'art-forms' ),
					'add_new'            => __( 'Добавить форму', 'art-forms' ),
					'add_new_item'       => __( 'Добавить форму', 'art-forms' ),
					'edit_item'          => __( 'Редактировать форму', 'art-forms' ),
					'new_item'           => __( 'Новая форма', 'art-forms' ),
					'view_item'          => __( 'Просмотр формы', 'art-forms' ),
					'search_items'       => __( 'Искать формы', 'art-forms' ),
					'not_found'          => __( 'Формы не найдены', 'art-forms' ),
					'not_found_in_trash' => __( 'В корзине форм нет', 'art-forms' ),
					'menu_name'          => __( 'Формы', 'art-forms' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
			)
		);
	}
}
