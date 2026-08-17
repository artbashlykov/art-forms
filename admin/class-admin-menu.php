<?php
/**
 * Admin menu.
 *
 * @package Art_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Forms_Admin_Menu
 */
class Art_Forms_Admin_Menu {

	const MENU_SLUG = ART_FORMS_ADMIN_MENU_SLUG;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_head', array( __CLASS__, 'print_hidden_submenu_css' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'parent_file', array( __CLASS__, 'filter_parent_file' ), 999 );
		add_filter( 'submenu_file', array( __CLASS__, 'filter_submenu_file' ), 999 );
		add_filter( 'plugin_action_links_' . ART_FORMS_PLUGIN_BASENAME, array( __CLASS__, 'plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta_forge' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta_strip_details' ), 100, 2 );
	}

	/**
	 * Register top-level menu.
	 */
	public static function register_menu() {
		$cap_crm    = Art_Forms_Capabilities::CAP_CRM;
		$cap_manage = Art_Forms_Capabilities::CAP_MANAGE;

		add_menu_page(
			__( 'ART Forms', 'art-forms' ),
			__( 'ART Forms', 'art-forms' ),
			$cap_crm,
			self::MENU_SLUG,
			array( __CLASS__, 'render_top_page' ),
			'dashicons-feedback',
			58
		);

		// Same slug as parent = rename first item to «Формы».
		// Must use the same callback as the parent (not render_list_page),
		// otherwise WP can fire both hooks and the list renders twice.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Формы', 'art-forms' ),
			__( 'Формы', 'art-forms' ),
			$cap_manage,
			self::MENU_SLUG,
			array( __CLASS__, 'render_top_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Добавить форму', 'art-forms' ),
			__( 'Добавить форму', 'art-forms' ),
			$cap_manage,
			'art-forms-new',
			array( 'Art_Forms_Admin_Forms', 'render_edit_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Ответы', 'art-forms' ),
			__( 'Ответы', 'art-forms' ),
			$cap_crm,
			'art-forms-submissions',
			array( 'Art_Forms_Admin_Submissions', 'render_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Лог доставок', 'art-forms' ),
			__( 'Лог доставок', 'art-forms' ),
			$cap_manage,
			'art-forms-delivery-log',
			array( 'Art_Forms_Admin_Delivery_Log', 'render_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Настройки', 'art-forms' ),
			__( 'Настройки', 'art-forms' ),
			$cap_manage,
			'art-forms-settings',
			array( 'Art_Forms_Admin_Settings', 'render_page' )
		);

		// Edit screen under ART Forms (keeps menu open). Hidden from the list via CSS class.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Редактировать форму', 'art-forms' ),
			__( 'Редактировать форму', 'art-forms' ),
			$cap_manage,
			'art-forms-edit',
			array( 'Art_Forms_Admin_Forms', 'render_edit_page' )
		);

		self::mark_edit_submenu_hidden();
	}

	/**
	 * Top-level callback: forms for admins, CRM for managers-only.
	 *
	 * Guard: parent + first submenu share slug `art-forms` and both hooks
	 * may run in one request — render only once.
	 */
	public static function render_top_page() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		if ( Art_Forms_Capabilities::can_manage() ) {
			Art_Forms_Admin_Forms::render_list_page();
			return;
		}
		Art_Forms_Admin_Submissions::render_page();
	}

	/**
	 * Hide the edit submenu item on every admin screen (menu is global).
	 */
	public static function print_hidden_submenu_css() {
		echo '<style id="art-forms-hidden-submenu">#adminmenu li.art-forms-admin-hidden-submenu{display:none!important}</style>' . "\n";
	}

	/**
	 * Mark edit submenu row as hidden (still registered under ART Forms for menu highlight).
	 */
	private static function mark_edit_submenu_hidden() {
		global $submenu;

		if ( empty( $submenu[ self::MENU_SLUG ] ) || ! is_array( $submenu[ self::MENU_SLUG ] ) ) {
			return;
		}

		foreach ( $submenu[ self::MENU_SLUG ] as $index => $item ) {
			if ( ! isset( $item[2] ) || 'art-forms-edit' !== $item[2] ) {
				continue;
			}

			$submenu[ self::MENU_SLUG ][ $index ][4] = trim(
				( isset( $item[4] ) ? (string) $item[4] : '' ) . ' art-forms-admin-hidden-submenu'
			);
		}
	}

	/**
	 * Enqueue admin assets on plugin pages.
	 *
	 * @param string $hook Hook.
	 */
	public static function enqueue_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$our_pages = array(
			'art-forms',
			'art-forms-new',
			'art-forms-edit',
			'art-forms-submissions',
			'art-forms-delivery-log',
			'art-forms-settings',
		);

		if ( ! in_array( $page, $our_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'art-forms-admin',
			ART_FORMS_PLUGIN_URL . 'assets/css/admin.css',
			array( 'dashicons' ),
			ART_FORMS_VERSION
		);

		wp_enqueue_script(
			'art-forms-admin',
			ART_FORMS_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			ART_FORMS_VERSION,
			true
		);

		wp_localize_script(
			'art-forms-admin',
			'artFormsAdmin',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'defaultPrivacyUrl' => (string) Art_Forms_Settings::get( 'default_privacy_url', '' ),
				'strings'           => array(
					'copied'         => __( 'Скопировано', 'art-forms' ),
					'copyFailed'     => __( 'Не удалось скопировать', 'art-forms' ),
					'confirmUnlock'  => __( 'Переименование ключа может сломать код на сайте и интеграции. Продолжить?', 'art-forms' ),
					'unsavedLeave'   => __( 'У вас есть несохранённые изменения. Уйти со страницы?', 'art-forms' ),
					'stepTitle'      => __( 'Название блока', 'art-forms' ),
					'removeStep'     => __( 'Удалить блок', 'art-forms' ),
					'duplicateStep'  => __( 'Дублировать блок', 'art-forms' ),
					'confirmRemoveStep' => __( 'Удалить блок вместе с полями? Это нельзя отменить.', 'art-forms' ),
					'stepDrag'       => __( 'Перетащите, чтобы поменять порядок блоков', 'art-forms' ),
					'copySuffix'     => __( 'копия', 'art-forms' ),
					'statsFieldOne'  => __( 'поле', 'art-forms' ),
					'statsFieldFew'  => __( 'поля', 'art-forms' ),
					'statsFieldMany' => __( 'полей', 'art-forms' ),
					'statsBlockOne'  => __( 'блок', 'art-forms' ),
					'statsBlockFew'  => __( 'блока', 'art-forms' ),
					'statsBlockMany' => __( 'блоков', 'art-forms' ),
					'warnNoEmail'    => __( 'нет поля Email', 'art-forms' ),
					'warnNoTel'      => __( 'нет поля Телефон', 'art-forms' ),
					'warnNoConsent'  => __( 'нет поля согласия', 'art-forms' ),
					'saveWarnTitle'  => __( 'Перед сохранением', 'art-forms' ),
					'saveWarnHint'   => __( 'Форму всё равно можно сохранить — это только напоминание.', 'art-forms' ),
					'saveAnyway'     => __( 'Сохранить всё равно', 'art-forms' ),
					'savedStatus'    => __( 'Сохранено', 'art-forms' ),
					'addStep'        => __( 'Добавить блок', 'art-forms' ),
					'addField'       => __( 'Добавить поле', 'art-forms' ),
					'fieldLabel'     => __( 'Подпись', 'art-forms' ),
					'fieldKey'       => __( 'Ключ', 'art-forms' ),
					'fieldKeyShort'  => __( 'Ключ', 'art-forms' ),
					'fieldKeyHint'   => __( 'Техническое имя поля (name). Обычно достаточно автоключа f1, f2…', 'art-forms' ),
					'required'       => __( 'Обязательное', 'art-forms' ),
					'unlockKey'      => __( 'Изменить', 'art-forms' ),
					'removeField'    => __( 'Удалить', 'art-forms' ),
					'duplicateField' => __( 'Дублировать', 'art-forms' ),
					'fieldDrag'      => __( 'Перетащить', 'art-forms' ),
					'optionsHint'    => __( 'Каждый вариант с новой строки', 'art-forms' ),
					'optionsLabel'   => __( 'Варианты ответа', 'art-forms' ),
					'optionsHelp'    => __( 'Пишите по одному варианту в строке.', 'art-forms' ),
					'defaultEmpty'   => __( '— не выбрано —', 'art-forms' ),
					'hiddenValue'    => __( 'Значение скрытого поля', 'art-forms' ),
					'privacyUrl'     => __( 'Ссылка (необязательно)', 'art-forms' ),
					'privacyLinkText'=> __( 'Текст ссылки', 'art-forms' ),
					'privacyLinkPlaceholder' => __( 'политикой конфиденциальности', 'art-forms' ),
					'fieldType'      => __( 'Тип', 'art-forms' ),
					'fieldNumber'    => __( 'Поле', 'art-forms' ),
					'stepBadge'      => __( 'Блок', 'art-forms' ),
					'noLabel'        => __( 'Без подписи', 'art-forms' ),
					'emptyFields'    => __( 'Пока нет полей. Добавьте первое поле ниже.', 'art-forms' ),
					'defaultValue'   => __( 'Значение по умолчанию', 'art-forms' ),
					'typeText'       => __( 'Текст', 'art-forms' ),
					'typeName'       => __( 'Имя', 'art-forms' ),
					'typeEmail'      => __( 'Email', 'art-forms' ),
					'typeTel'        => __( 'Телефон', 'art-forms' ),
					'typeTextarea'   => __( 'Многострочный текст', 'art-forms' ),
					'typeSelect'     => __( 'Выпадающий список', 'art-forms' ),
					'typeRadio'      => __( 'Варианты (один)', 'art-forms' ),
					'typeCheckbox'   => __( 'Варианты (несколько)', 'art-forms' ),
					'typeHidden'     => __( 'Скрытое поле', 'art-forms' ),
					'typeConsent'    => __( 'Согласие', 'art-forms' ),
					'hintText'       => __( 'Одна строка текста.', 'art-forms' ),
					'hintName'       => __( 'Имя человека. Показывается в карточке CRM перед email и телефоном.', 'art-forms' ),
					'hintEmail'      => __( 'Проверка формата email.', 'art-forms' ),
					'hintTel'        => __( 'Поле для телефона.', 'art-forms' ),
					'hintTextarea'   => __( 'Длинный текст в несколько строк.', 'art-forms' ),
					'hintSelect'     => __( 'Меню на странице: клиент выбирает один пункт.', 'art-forms' ),
					'hintRadio'      => __( 'Варианты на экране: можно выбрать только один.', 'art-forms' ),
					'hintCheckbox'   => __( 'Несколько галочек: можно отметить сразу несколько.', 'art-forms' ),
					'hintHidden'     => __( 'Не видно клиенту. Для оффера, тарифа и других служебных данных.', 'art-forms' ),
					'hintConsent'    => __( 'Обязательная галочка. Подпись — любой текст согласия: обработка данных, политика, рассылка. Ссылку можно добавить отдельно.', 'art-forms' ),
					'checkError'     => __( 'Ошибка проверки', 'art-forms' ),
					'checkOk'        => __( 'код соответствует схеме.', 'art-forms' ),
					'checkErrors'    => __( 'Ошибки', 'art-forms' ),
					'checkWarnings'  => __( 'Предупреждения', 'art-forms' ),
					'networkError'   => __( 'Ошибка сети', 'art-forms' ),
				),
			)
		);

		unset( $screen, $hook );
	}

	/**
	 * Keep ART Forms top-level menu open on edit screen.
	 *
	 * @param string $parent_file Parent file.
	 * @return string
	 */
	public static function filter_parent_file( $parent_file ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( in_array( $page, array( 'art-forms-edit', 'art-forms-new' ), true ) ) {
			return self::MENU_SLUG;
		}

		return $parent_file;
	}

	/**
	 * Highlight «Формы» while editing; «Добавить форму» stays on its own item.
	 *
	 * @param string $submenu_file Submenu file.
	 * @return string
	 */
	public static function filter_submenu_file( $submenu_file ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'art-forms-edit' === $page ) {
			return self::MENU_SLUG;
		}

		return $submenu_file;
	}

	/**
	 * Plugin action links.
	 *
	 * @param array<int, string> $links Links.
	 * @return array<int, string>
	 */
	public static function plugin_action_links( $links ) {
		$url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'Формы', 'art-forms' )
			)
		);

		return $links;
	}

	/**
	 * Forge link in plugin row meta.
	 *
	 * @param array<int, string> $links Links.
	 * @param string             $file  Plugin file.
	 * @return array<int, string>
	 */
	public static function plugin_row_meta_forge( $links, $file ) {
		if ( ART_FORMS_PLUGIN_BASENAME !== $file ) {
			return $links;
		}

		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( ART_FORMS_AUTHOR_URL ),
			esc_html__( 'Больше материалов автора', 'art-forms' )
		);

		return $links;
	}

	/**
	 * Remove PUC «View details» link from plugin row meta.
	 *
	 * @param array<int, string> $links Plugin row meta links.
	 * @param string             $file  Plugin basename.
	 * @return array<int, string>
	 */
	public static function plugin_row_meta_strip_details( $links, $file ) {
		if ( ART_FORMS_PLUGIN_BASENAME !== $file ) {
			return $links;
		}

		return array_values(
			array_filter(
				$links,
				static function ( $link ) {
					return false === strpos( $link, 'open-plugin-details-modal' )
						&& false === strpos( $link, 'plugin-install.php?tab=plugin-information' );
				}
			)
		);
	}
}
