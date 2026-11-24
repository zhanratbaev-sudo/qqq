<?php
/**
 * Plugin Name: StudyHub AI — ИИ-помощник (Google Gemini)
 * Description: Отдельное дополнение к StudyHub: вкладка «ИИ-помощник» в приложении студентов на базе Google Gemini. Предлагается автоматически, если администратор не в сети или долго не отвечает.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: studyhub
 * Text Domain: studyhub-ai
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class StudyHub_AI {

	const OPTION = 'studyhub_ai_settings';
	const NS     = 'studyhub-ai/v1';

	public static function init() {
		add_filter( 'studyhub_app_config', array( __CLASS__, 'config' ), 10, 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_post_studyhub_ai_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_notices', array( __CLASS__, 'dependency_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'action_links' ) );
	}

	public static function defaults() {
		return array(
			'enabled'     => 1,
			'api_key'     => '',
			'model'       => 'gemini-2.5-flash',
			'daily_limit' => 40,
			'temperature' => 0.6,
			'extra'       => '',
		);
	}

	public static function settings() {
		$s = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $s ) ? $s : array() );
	}

	public static function ready() {
		$s = self::settings();
		return ! empty( $s['enabled'] ) && '' !== trim( (string) $s['api_key'] ) && function_exists( 'studyhub_current_student' );
	}

	/* ------------------------------------------------------------------ */

	public static function config( $config ) {
		if ( self::ready() ) {
			$config['ai'] = array(
				'enabled'  => true,
				'endpoint' => esc_url_raw( rest_url( self::NS . '/chat' ) ),
			);
		}
		return $config;
	}

	public static function routes() {
		register_rest_route(
			self::NS,
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'chat' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
			)
		);
	}

	public static function permission( WP_REST_Request $req ) {
		if ( '1' !== $req->get_header( 'x_studyhub' ) ) {
			return new WP_Error( 'forbidden', 'forbidden', array( 'status' => 403 ) );
		}
		if ( ! function_exists( 'studyhub_current_student' ) || ! studyhub_current_student() ) {
			return new WP_Error( 'not_logged_in', 'not_logged_in', array( 'status' => 401 ) );
		}
		return true;
	}

	private static function system_prompt( $lang ) {
		$brand = class_exists( 'StudyHub_Settings' ) ? StudyHub_Settings::brand() : get_bloginfo( 'name' );
		$unis  = array();
		if ( class_exists( 'StudyHub_Settings' ) ) {
			foreach ( (array) StudyHub_Settings::get( 'universities' ) as $u ) {
				$unis[] = $u['short'] . ( $u['name_ru'] ? ' (' . $u['name_ru'] . ')' : '' );
			}
		}
		$services = array();
		if ( class_exists( 'StudyHub_Settings' ) ) {
			foreach ( (array) StudyHub_Settings::get( 'services' ) as $svc ) {
				$services[] = $svc['title_ru'] . ( $svc['price'] ? ' — ' . $svc['price'] : '' );
			}
		}
		$s         = self::settings();
		$lang_rule = 'kk' === $lang
			? 'Отвечай на казахском языке (если пользователь пишет по-русски — отвечай по-русски).'
			: 'Отвечай на русском языке (если пользователь пишет по-казахски — отвечай по-казахски).';

		$prompt = "Ты — ИИ-помощник сервиса «{$brand}» для студентов архитектурных и строительных специальностей в Казахстане"
			. ( $unis ? ' (университеты: ' . implode( ', ', $unis ) . ')' : '' ) . ".\n"
			. "Помогай с учёбой: архитектура, композиция, рисунок, черчение, ГОСТ/СНиП/СП РК, макетирование, AutoCAD, ArchiCAD, Revit, SketchUp, 3ds Max, Photoshop, история архитектуры, оформление работ, тайм-менеджмент.\n"
			. ( $services ? 'Услуги сервиса: ' . implode( '; ', $services ) . ".\n" : '' )
			. "Правила:\n"
			. "- {$lang_rule}\n"
			. "- Пиши кратко и по делу, используй короткие списки. Без лишних вступлений.\n"
			. "- Ты ИИ, не человек. Не придумывай имена сотрудников и контакты. Никогда не спрашивай и не проси у студента имя, телефон, фото, ID или другие личные данные — сервис полностью анонимный.\n"
			. "- Вопросы о заказе, цене, сроках, оплате или готовой работе — вежливо предложи написать в чат «{$brand}» (вкладка «Чат»), там ответят лично.\n"
			. "- Не обещай сроки и цены от имени сервиса.\n";
		if ( ! empty( $s['extra'] ) ) {
			$prompt .= "Дополнительно:\n" . $s['extra'] . "\n";
		}
		return $prompt;
	}

	public static function chat( WP_REST_Request $req ) {
		$s       = self::settings();
		$student = studyhub_current_student();

		// Daily limit per student.
		$key   = 'studyhub_ai_' . (int) $student->id . '_' . gmdate( 'Ymd' );
		$count = (int) get_transient( $key );
		if ( $count >= max( 1, (int) $s['daily_limit'] ) ) {
			return new WP_Error( 'limit', 'daily_limit', array( 'status' => 429 ) );
		}

		$lang     = 'kk' === $req->get_param( 'lang' ) ? 'kk' : 'ru';
		$messages = (array) $req->get_param( 'messages' );
		$contents = array();
		foreach ( array_slice( $messages, -12 ) as $m ) {
			if ( ! is_array( $m ) || empty( $m['content'] ) ) {
				continue;
			}
			$role = ( isset( $m['role'] ) && 'assistant' === $m['role'] ) ? 'model' : 'user';
			$text = mb_substr( sanitize_textarea_field( (string) $m['content'] ), 0, 4000 );
			if ( '' === $text ) {
				continue;
			}
			// Gemini requires alternating roles; merge consecutive ones.
			$last = count( $contents ) - 1;
			if ( $last >= 0 && $contents[ $last ]['role'] === $role ) {
				$contents[ $last ]['parts'][0]['text'] .= "\n\n" . $text;
			} else {
				$contents[] = array(
					'role'  => $role,
					'parts' => array( array( 'text' => $text ) ),
				);
			}
		}
		while ( $contents && 'user' !== $contents[0]['role'] ) {
			array_shift( $contents );
		}
		if ( ! $contents || 'user' !== $contents[ count( $contents ) - 1 ]['role'] ) {
			return new WP_Error( 'empty', 'empty', array( 'status' => 400 ) );
		}

		$reply = self::call_gemini( $contents, self::system_prompt( $lang ), $s );
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		set_transient( $key, $count + 1, DAY_IN_SECONDS );
		return array( 'reply' => $reply );
	}

	/**
	 * @return string|WP_Error
	 */
	public static function call_gemini( array $contents, $system, array $s ) {
		$model = preg_replace( '/[^a-z0-9.\-]/i', '', (string) $s['model'] );
		if ( '' === $model ) {
			$model = 'gemini-2.5-flash';
		}
		$url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';
		$body = array(
			'systemInstruction' => array( 'parts' => array( array( 'text' => $system ) ) ),
			'contents'          => $contents,
			'generationConfig'  => array(
				'temperature'     => (float) $s['temperature'],
				'maxOutputTokens' => 2048,
			),
		);
		$res  = wp_remote_post(
			$url,
			array(
				'timeout' => 45,
				'headers' => array(
					'Content-Type'   => 'application/json',
					'x-goog-api-key' => trim( (string) $s['api_key'] ),
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'ai_unavailable', 'ai_unavailable', array( 'status' => 502 ) );
		}
		$code = wp_remote_retrieve_response_code( $res );
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'HTTP ' . $code;
			return new WP_Error( 'ai_unavailable', $msg, array( 'status' => 502 ) );
		}
		$text = '';
		if ( ! empty( $data['candidates'][0]['content']['parts'] ) ) {
			foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
				if ( isset( $part['text'] ) && empty( $part['thought'] ) ) {
					$text .= $part['text'];
				}
			}
		}
		$text = trim( $text );
		if ( '' === $text ) {
			return new WP_Error( 'ai_empty', 'ai_empty', array( 'status' => 502 ) );
		}
		return $text;
	}

	/* ------------------------------------------------------------------ admin */

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::settings_url() ) . '">Настройки</a>' );
		return $links;
	}

	private static function settings_url() {
		return admin_url( function_exists( 'studyhub_current_student' ) ? 'admin.php?page=studyhub-ai' : 'options-general.php?page=studyhub-ai' );
	}

	public static function menu() {
		if ( function_exists( 'studyhub_current_student' ) ) {
			add_submenu_page( 'studyhub', 'ИИ-помощник', 'ИИ-помощник', 'manage_options', 'studyhub-ai', array( __CLASS__, 'page' ) );
		} else {
			add_options_page( 'StudyHub AI', 'StudyHub AI', 'manage_options', 'studyhub-ai', array( __CLASS__, 'page' ) );
		}
	}

	public static function dependency_notice() {
		if ( function_exists( 'studyhub_current_student' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>StudyHub AI</strong> работает только вместе с основным плагином <strong>StudyHub</strong>. Установите и активируйте его.</p></div>';
	}

	public static function page() {
		$s      = self::settings();
		$notice = get_transient( 'studyhub_ai_notice' );
		if ( $notice ) {
			delete_transient( 'studyhub_ai_notice' );
		}
		$masked = $s['api_key'] ? str_repeat( '•', 12 ) . substr( $s['api_key'], -4 ) : '';
		?>
		<div class="wrap studyhub-wrap">
			<div class="sh-head"><div class="sh-logo">✦</div><div><h1>ИИ-помощник</h1><p class="sh-sub">Google Gemini. Студенты видят вкладку «ИИ», а в чате — предложение спросить ИИ, когда вы не в сети или долго не отвечаете.</p></div></div>
			<?php
			if ( $notice ) {
				echo wp_kses_post( $notice );
			}
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'studyhub_ai_save' ); ?>
				<input type="hidden" name="action" value="studyhub_ai_save">
				<div class="sh-card"><div class="sh-grid">
					<label>Включён
						<span class="sh-check"><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?>> Показывать ИИ-помощника студентам</span>
					</label>
					<label>API-ключ Gemini <?php echo $masked ? '<span class="sh-muted">(сохранён: ' . esc_html( $masked ) . ')</span>' : ''; ?>
						<input type="password" name="api_key" autocomplete="off" placeholder="<?php echo $masked ? 'оставьте пустым, чтобы не менять' : 'AIza…'; ?>">
						<span class="sh-muted" style="font-weight:400">Получить: aistudio.google.com → Get API key</span>
					</label>
					<label>Модель
						<input type="text" name="model" value="<?php echo esc_attr( $s['model'] ); ?>" placeholder="gemini-2.5-flash">
						<span class="sh-muted" style="font-weight:400">Например gemini-2.5-flash (быстро и дёшево) или gemini-2.5-pro</span>
					</label>
					<label>Лимит вопросов на студента в день
						<input type="number" name="daily_limit" min="1" max="1000" value="<?php echo (int) $s['daily_limit']; ?>">
					</label>
					<label>Креативность (0–1)
						<input type="number" name="temperature" min="0" max="1" step="0.1" value="<?php echo esc_attr( $s['temperature'] ); ?>">
					</label>
					<label>Дополнительные инструкции для ИИ
						<textarea name="extra" rows="4" placeholder="Например: часы работы, особенности оформления в КазГАСА…"><?php echo esc_textarea( $s['extra'] ); ?></textarea>
					</label>
				</div></div>
				<p>
					<button class="button button-primary button-hero" name="do" value="save">Сохранить</button>
					<button class="button button-hero" name="do" value="test">Сохранить и проверить</button>
				</p>
			</form>
			<div class="sh-card"><p class="sh-muted" style="margin:0">Приватность: переписка с ИИ хранится только на телефоне студента (до закрытия приложения) и не сохраняется на сервере. В Gemini передаётся только текст вопросов — без логина и ID.</p></div>
		</div>
		<?php
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		check_admin_referer( 'studyhub_ai_save' );
		$s                = self::settings();
		$s['enabled']     = empty( $_POST['enabled'] ) ? 0 : 1;
		$key              = isset( $_POST['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : '';
		if ( '' !== $key ) {
			$s['api_key'] = $key;
		}
		$s['model']       = isset( $_POST['model'] ) ? preg_replace( '/[^a-z0-9.\-]/i', '', wp_unslash( $_POST['model'] ) ) : 'gemini-2.5-flash'; // phpcs:ignore
		$s['daily_limit'] = isset( $_POST['daily_limit'] ) ? min( 1000, max( 1, (int) $_POST['daily_limit'] ) ) : 40;
		$s['temperature'] = isset( $_POST['temperature'] ) ? min( 1, max( 0, (float) $_POST['temperature'] ) ) : 0.6;
		$s['extra']       = isset( $_POST['extra'] ) ? sanitize_textarea_field( wp_unslash( $_POST['extra'] ) ) : '';
		update_option( self::OPTION, $s, false );

		$notice = '<div class="notice notice-success"><p>Сохранено.</p></div>';
		if ( isset( $_POST['do'] ) && 'test' === $_POST['do'] ) {
			if ( '' === $s['api_key'] ) {
				$notice = '<div class="notice notice-error"><p>Укажите API-ключ.</p></div>';
			} else {
				$r = self::call_gemini( array( array( 'role' => 'user', 'parts' => array( array( 'text' => 'Ответь одним словом: работает?' ) ) ) ), 'Ты тестовый ассистент.', $s );
				$notice = is_wp_error( $r )
					? '<div class="notice notice-error"><p>Ошибка Gemini: ' . esc_html( $r->get_error_message() ) . '</p></div>'
					: '<div class="notice notice-success"><p>Gemini отвечает ✓ — «' . esc_html( wp_html_excerpt( $r, 80, '…' ) ) . '»</p></div>';
			}
		}
		set_transient( 'studyhub_ai_notice', $notice, 60 );
		wp_safe_redirect( self::settings_url() );
		exit;
	}
}

add_action( 'plugins_loaded', array( 'StudyHub_AI', 'init' ), 20 );
