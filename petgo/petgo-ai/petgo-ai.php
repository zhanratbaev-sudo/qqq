<?php
/**
 * Plugin Name:       PetGo ИИ
 * Plugin URI:        https://petgo.kz/
 * Description:       ИИ‑помощник для сайта PetGo (Google Gemini): чат в центре нижнего меню, ответ «печатается» по мере генерации, голосовые сообщения, знания с сайта, передача чата менеджеру в приложение. Работает вместе с плагином «PetGo Кабинет».
 * Version:           34.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            web.for made
 * Author URI:        https://web.for.petgo.kz
 * License:           GPL-2.0-or-later
 * Text Domain:       petgo-ai
 */

defined( 'ABSPATH' ) || exit;

define( 'PGA_VERSION', '34.2.0' );
define( 'PGA_FILE', __FILE__ );
define( 'PGA_DIR', plugin_dir_path( __FILE__ ) );
define( 'PGA_URL', plugin_dir_url( __FILE__ ) );

require_once PGA_DIR . 'includes/class-pga-ai.php';
require_once PGA_DIR . 'includes/class-pga-inbox.php';
require_once PGA_DIR . 'includes/class-pga-rest.php';
require_once PGA_DIR . 'includes/class-pga-admin.php';

/* Настройки в WordPress работают всегда — даже если сайт PetGo выключен (чтобы можно было вставить ключ). */
PGA_Admin::init();

/** Чат на сайте — только вместе с основным плагином (данные, дизайн, кнопка в меню). */
add_action( 'plugins_loaded', function () {
	PGA_Inbox::maybe_install();
	pga_upgrade();
	if ( ! function_exists( 'petgo_site_cfg' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><b>PetGo ИИ</b> работает вместе с плагином <b>PetGo Кабинет</b>. Включите его.</p></div>';
		} );
		return;
	}
	PGA_Rest::init();
	PGA_Inbox::app_init();

	/* сообщаем сайту, что ИИ есть: в центре нижнего меню появляется кнопка «Помощник» */
	add_filter( 'petgo_ai_active', function () {
		return PGA_AI::on();
	} );
	add_action( 'petgo_site_head', array( 'PGA_Front', 'head' ) );
	add_action( 'petgo_site_foot', array( 'PGA_Front', 'foot' ) );
	/* окно чата выводится внутри сайта (.pg-layer), поэтому выглядит как его часть */
	add_action( 'petgo_layer_end', array( 'PGA_Front', 'render' ) );
}, 20 );

register_activation_hook( __FILE__, function () {
	PGA_Inbox::install();
	pga_upgrade();
} );

/**
 * 33.10: переход на устройство как у Oscar Clinic AI (Gemini, пул ключей).
 * Настройки 33.0 (несколько ИИ на выбор) переводятся в новые; копия видна в настройках.
 */
function pga_upgrade() {
	if ( version_compare( (string) get_option( 'pga_version', '0' ), '33.10.0', '>=' ) ) {
		return;
	}
	$o = get_option( 'petgo_ai', null );
	$n = PGA_AI::defaults();
	if ( is_array( $o ) && isset( $o['provider'] ) ) {
		/* версия 33.0 */
		update_option( 'pga_backup_330', wp_json_encode( array_diff_key( $o, array_flip( array( 'key_claude', 'key_gemini', 'key_openai' ) ) ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ), false );
		$n['enabled'] = isset( $o['on'] ) ? (string) $o['on'] : '1';
		$n['name']    = ! empty( $o['name'] ) ? $o['name'] : $n['name'];
		$n['know']    = isset( $o['know'] ) ? (string) $o['know'] : '';
		$n['key']     = isset( $o['key_gemini'] ) ? (string) $o['key_gemini'] : '';
		if ( '' === $n['key'] && ! empty( $o['key_claude'] ) ) {
			update_option( 'pga_old_claude', 1, false );
		}
		update_option( 'petgo_ai', $n, false );
	} elseif ( ! is_array( $o ) ) {
		/* ещё раньше: настройки помощника жили в «PetGo Кабинет» */
		$site = get_option( 'petgo_site', array() );
		if ( is_array( $site ) ) {
			if ( isset( $site['ai_on'] ) ) {
				$n['enabled'] = (string) $site['ai_on'];
			}
			if ( ! empty( $site['ai_name'] ) ) {
				$n['name'] = (string) $site['ai_name'];
			}
			if ( ! empty( $site['ai_know'] ) ) {
				$n['know'] = (string) $site['ai_know'];
			}
			if ( ! empty( $site['ai_key'] ) ) {
				update_option( 'pga_old_claude', 1, false );
			}
		}
		update_option( 'petgo_ai', $n, false );
	}
	PGA_AI::settings( true );
	update_option( 'pga_version', '33.10.0', false );
}

/** Язык сайта (ru, kz, en) и перевод строк — словарь сайта PetGo. */
function pga_lang() {
	return function_exists( 'petgo_site_lang' ) ? petgo_site_lang() : 'ru';
}

function pga_l( $s ) {
	return function_exists( 'petgo_site_l' ) ? petgo_site_l( $s ) : $s;
}

/** Имя помощника: своё имя из настроек не переводим, стандартное — переводим. */
function pga_name() {
	$s = PGA_AI::settings();
	$n = trim( (string) $s['name'] );
	return ( '' === $n || 'Помощник PetGo' === $n ) ? pga_l( 'Помощник PetGo' ) : pga_l( $n );
}

/**
 * Перевод своих текстов сайта (услуги, слайды, вопросы, «О себе» нянь) на казахский и английский.
 * Сайт складывает непереведённые строки в очередь и в фоне просит перевести их здесь, через Gemini.
 */
add_filter( 'petgo_site_translate', 'pga_translate', 10, 3 );
function pga_translate( $res, $texts, $lang ) {
	if ( ! PGA_AI::has_key() || ! $texts || ! in_array( $lang, array( 'kz', 'en' ), true ) ) {
		return $res;
	}
	$to   = 'kz' === $lang ? 'казахский (кириллица, литературный язык Казахстана)' : 'английский';
	$body = array(
		'contents'         => array(
			array(
				'role'  => 'user',
				'parts' => array(
					array(
						'text' => "Переведи значения JSON с русского на {$to}. Это тексты сайта PetGo — сервис нянь для собак и кошек в Алматы (выгул собак, визиты к питомцам на дом).\n"
							. "Правила: переводи естественно, как носитель языка, коротко и дружелюбно; «няня» — pet sitter / күтуші; сохраняй без изменений {off}, {walk}, {walk_old}, {week}, {week_old}, числа, ₸, %, эмодзи, названия PetGo и WhatsApp.\n"
							. "Верни ТОЛЬКО JSON-объект с теми же ключами.\n\n" . wp_json_encode( $texts, JSON_UNESCAPED_UNICODE ),
					),
				),
			),
		),
		'generationConfig' => array( 'temperature' => 0.2, 'maxOutputTokens' => 8192, 'responseMimeType' => 'application/json' ),
	);
	$r = PGA_AI::request( $body );
	if ( is_wp_error( $r ) ) {
		return $res;
	}
	$txt = trim( (string) $r['text'] );
	$txt = preg_replace( '/^```(?:json)?\s*|\s*```$/', '', $txt );
	$out = json_decode( $txt, true );
	return is_array( $out ) ? $out : $res;
}

class PGA_Front {

	private static function ver() {
		return PGA_VERSION . '.' . @filemtime( PGA_DIR . 'assets/ai.js' ) . @filemtime( PGA_DIR . 'assets/ai.css' ); // phpcs:ignore
	}

	/** путь без домена: запросы не блокируются, если сайт за Cloudflare (http/https) */
	public static function rel( $u ) {
		$p = wp_parse_url( $u );
		return ( $p && ! empty( $p['path'] ) ) ? $p['path'] . ( isset( $p['query'] ) ? '?' . $p['query'] : '' ) : $u;
	}

	public static function head() {
		if ( ! PGA_AI::on() ) {
			return;
		}
		echo '<link rel="stylesheet" href="' . esc_url( PGA_URL . 'assets/ai.css?ver=' . self::ver() ) . '">' . "\n";
	}

	public static function foot() {
		if ( ! PGA_AI::on() ) {
			return;
		}
		$s     = PGA_AI::settings();
		$chips = array();
		if ( function_exists( 'petgo_site_faq' ) ) {
			foreach ( array_slice( petgo_site_faq(), 0, 4 ) as $q ) {
				$chips[] = str_replace( "\u{00A0}", ' ', $q[0] );
			}
		}
		if ( ! $chips ) {
			$chips = array( pga_l( 'Сколько стоит выгул?' ), pga_l( 'Вы ухаживаете за кошками?' ), pga_l( 'Как заказать няню?' ) );
		}
		$cfg = array(
			'rest'     => self::rel( rest_url( 'petgo/v1/' ) ),
			'ajax'     => self::rel( admin_url( 'admin-ajax.php' ) ),
			'lang'     => pga_lang(),
			'name'     => pga_name(),
			/* 34.0: в кабинете клиента помощник здоровается по имени и знает его питомцев */
			'greeting' => (string) apply_filters( 'pga_greeting', pga_l( 'Здравствуйте! Я помощник PetGo 🐾 Расскажу про выгул, визиты к питомцу, цены и помогу оставить заявку. Что вас интересует?' ) ),
			'chips'    => (array) apply_filters( 'pga_chips', $chips ),
			'ctx'      => (string) apply_filters( 'pga_client_token', '' ),
			'wa'       => function_exists( 'petgo_site_wa' ) ? petgo_site_wa() : '',
			'waText'   => pga_l( 'Здравствуйте! Пишу с сайта PetGo.' ),
			'phone'    => function_exists( 'petgo_site_phone_fmt' ) ? petgo_site_phone_fmt() : '',
			'i18n'     => array(
				'book' => pga_l( 'Оставить заявку' ),
				'send' => pga_l( 'Отправить' ),
				'err'  => pga_l( 'Не получилось ответить — похоже, пропала связь. Напишите нам в WhatsApp или позвоните' ) . ( function_exists( 'petgo_site_phone_fmt' ) ? ': ' . petgo_site_phone_fmt() : '' ) . '.',
			),
		);
		echo '<script>window.PGA=' . wp_json_encode( $cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ';</script>' . "\n"; // phpcs:ignore
		echo '<script src="' . esc_url( PGA_URL . 'assets/ai.js?ver=' . self::ver() ) . '" defer></script>' . "\n"; // phpcs:ignore
	}

	public static function render() {
		if ( ! PGA_AI::on() ) {
			return;
		}
		$s = PGA_AI::settings();
		include PGA_DIR . 'templates/chat.php';
	}
}
