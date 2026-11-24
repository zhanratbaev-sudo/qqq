<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin settings stored in a single option.
 */
class StudyHub_Settings {

	const OPTION = 'studyhub_settings';

	public static function defaults() {
		return array(
			'brand_name'         => 'StudyHub',
			'brand_tagline_ru'   => 'Студенческое пространство',
			'brand_tagline_kk'   => 'Студенттік кеңістік',
			'brand_logo'         => '',
			'accent_color'       => '#F26B1D',
			'app_slug'           => 'app',
			'panel_slug'         => 'panel',
			'site_on_front'      => 0,
			'app_on_front'       => 1,
			'erase_contacts'     => 1,
			'contact_url'        => '',
			'contact_label'      => 'Написать в Telegram',
			'hero_title_ru'      => 'Твой путь в архитектуре',
			'hero_title_kk'      => 'Сәулеттегі сенің жолың',
			'hero_text_ru'       => 'Домашка, макеты, рисунки и чертежи — анонимно и в срок.',
			'hero_text_kk'       => 'Үй тапсырмасы, макеттер, суреттер мен сызбалар — анонимді әрі уақытында.',
			'hero_image'         => '',
			'admin_status'       => 'auto', // auto | online | offline.
			'fallback_minutes'   => 5,
			'welcome_ru'         => 'Здравствуйте! Напишите, что нужно сделать: предмет, сроки и требования. Можно прикрепить файлы.',
			'welcome_kk'         => 'Сәлеметсіз бе! Не істеу керегін жазыңыз: пән, мерзім және талаптар. Файл тіркеуге болады.',
			'max_upload_mb'      => 20,
			'notify_email'       => 0,
			'reviews_automod'    => 0,
			'delete_on_uninstall' => 0,
			'universities'       => array(
				array(
					'id'      => 'alt',
					'short'   => 'ALT',
					'name_ru' => 'Университет ALT им. М. Тынышпаева',
					'name_kk' => 'М. Тынышпаев атындағы ALT университеті',
					'logo'    => '',
				),
				array(
					'id'      => 'kazgasa',
					'short'   => 'КазГАСА',
					'name_ru' => 'КазГАСА',
					'name_kk' => 'ҚазБСҚА',
					'logo'    => '',
				),
			),
			'services'           => array(
				array(
					'id'      => 'homework',
					'icon'    => 'book',
					'color'   => 'peach',
					'title_ru' => 'Домашние задания',
					'title_kk' => 'Үй тапсырмалары',
					'desc_ru'  => 'Задачи, расчёты, рефераты и практические работы по любым предметам.',
					'desc_kk'  => 'Кез келген пән бойынша есептер, рефераттар және практикалық жұмыстар.',
					'price'    => '',
				),
				array(
					'id'      => 'models',
					'icon'    => 'cube',
					'color'   => 'lilac',
					'title_ru' => 'Макеты',
					'title_kk' => 'Макеттер',
					'desc_ru'  => 'Архитектурные макеты: от концепции до готовой модели.',
					'desc_kk'  => 'Сәулет макеттері: идеядан дайын модельге дейін.',
					'price'    => '',
				),
				array(
					'id'      => 'drawings',
					'icon'    => 'pencil',
					'color'   => 'mint',
					'title_ru' => 'Рисунки',
					'title_kk' => 'Суреттер',
					'desc_ru'  => 'Академический рисунок, скетчи, композиция, графика.',
					'desc_kk'  => 'Академиялық сурет, скетчтер, композиция, графика.',
					'price'    => '',
				),
				array(
					'id'      => 'blueprints',
					'icon'    => 'ruler',
					'color'   => 'sky',
					'title_ru' => 'Чертежи',
					'title_kk' => 'Сызбалар',
					'desc_ru'  => 'Чертежи вручную и в AutoCAD / ArchiCAD / Revit.',
					'desc_kk'  => 'Қолмен және AutoCAD / ArchiCAD / Revit-те сызбалар.',
					'price'    => '',
				),
			),
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::defaults(), $saved );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function update( array $values ) {
		$merged = array_merge( self::all(), $values );
		update_option( self::OPTION, $merged );
		return $merged;
	}

	public static function brand() {
		$name = trim( (string) self::get( 'brand_name' ) );
		return '' !== $name ? $name : 'StudyHub';
	}

	public static function accent() {
		$c = sanitize_hex_color( (string) self::get( 'accent_color' ) );
		return $c ? $c : '#F26B1D';
	}

	public static function slug( $key, $default ) {
		$slug = sanitize_title( (string) self::get( $key ) );
		return '' !== $slug ? $slug : $default;
	}

	public static function university_ids() {
		$ids = array();
		foreach ( (array) self::get( 'universities' ) as $u ) {
			if ( ! empty( $u['id'] ) ) {
				$ids[] = $u['id'];
			}
		}
		return $ids;
	}

	public static function university_label( $id ) {
		foreach ( (array) self::get( 'universities' ) as $u ) {
			if ( isset( $u['id'] ) && $u['id'] === $id ) {
				return $u['short'] ? $u['short'] : $u['name_ru'];
			}
		}
		return '';
	}

	/**
	 * Is the admin currently considered online (for the student UI)?
	 */
	public static function admin_online() {
		$status = self::get( 'admin_status' );
		if ( 'online' === $status ) {
			return true;
		}
		if ( 'offline' === $status ) {
			return false;
		}
		$last = (int) get_option( 'studyhub_admin_heartbeat', 0 );
		return ( time() - $last ) < 5 * MINUTE_IN_SECONDS;
	}
}
