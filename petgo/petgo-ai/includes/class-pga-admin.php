<?php
/**
 * Консоль WordPress: Настройки → PetGo ИИ (ключи, модель, база знаний, проверка, последняя ошибка).
 */
defined( 'ABSPATH' ) || exit;

class PGA_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_post_pga_settings', array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', function ( $hook ) {
			if ( 'settings_page_petgo-ai' === $hook ) {
				wp_enqueue_style( 'pga-admin', PGA_URL . 'assets/admin.css', array(), PGA_VERSION );
			}
		} );
		add_filter( 'plugin_action_links_' . plugin_basename( PGA_FILE ), function ( $l ) {
			array_unshift( $l, '<a href="' . esc_url( admin_url( 'options-general.php?page=petgo-ai' ) ) . '">Настройки</a>' );
			return $l;
		} );
	}

	public static function menu() {
		add_options_page( 'PetGo ИИ', 'PetGo ИИ', 'manage_options', 'petgo-ai', array( __CLASS__, 'page' ) );
	}

	private static function mask( $k ) {
		return $k ? substr( $k, 0, 4 ) . str_repeat( '•', 8 ) . substr( $k, -4 ) : '';
	}

	private static function sw( $name, $on, $title, $sub ) {
		return '<label class="pgs-sw"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( $on, true, false ) . '><i></i><span><b>' . esc_html( $title ) . '</b><small>' . esc_html( $sub ) . '</small></span></label>';
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s   = PGA_AI::settings( true );
		$key = (string) $s['key'];
		$oc  = isset( $_GET['pga'] ) ? sanitize_key( wp_unslash( $_GET['pga'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		echo '<div class="wrap pgs"><div class="pgs-bg" aria-hidden="true"></div>';
		echo '<header class="pgs-head"><div class="pgs-logo">' . ( function_exists( 'petgo_site_logo' ) ? petgo_site_logo() : '' ) . '</div><div><h1>PetGo ИИ ' . esc_html( PGA_VERSION ) . '</h1><p>ИИ‑помощник на сайте: кнопка в центре нижнего меню. Знания берутся с сайта сами (услуги, цены, скидка, частые вопросы, отзывы, контакты). Ниже — ключи Google Gemini и база знаний.</p></div></header>';

		if ( 'saved' === $oc ) {
			echo '<div class="pgs-note ok">Сохранено.</div>';
		}
		if ( 'tested' === $oc ) {
			$t = get_transient( 'pga_test_' . get_current_user_id() );
			if ( $t ) {
				echo '<div class="pgs-note ' . ( $t['ok'] ? 'ok' : 'err' ) . '"><b>' . ( $t['ok'] ? 'ИИ работает ✓ Ответ:' : 'ИИ не отвечает. Ошибка:' ) . '</b> ' . esc_html( $t['text'] ) . '</div>';
			}
		}
		if ( 'probed' === $oc ) {
			$t = get_transient( 'pga_probe_' . get_current_user_id() );
			if ( $t ) {
				echo '<div class="pgs-card"><h2>Проверка ключей</h2><ul style="margin:0;padding-left:18px;list-style:disc">';
				foreach ( $t as $row ) {
					echo '<li>Ключ ' . (int) $row['n'] . ' <code>' . esc_html( $row['mask'] ) . '</code>: <b style="color:' . ( $row['ok'] ? '#1b7f3a' : '#c62828' ) . '">' . ( $row['ok'] ? '✓ ' : '✗ ' ) . esc_html( $row['text'] ) . '</b></li>';
				}
				echo '</ul></div>';
			}
		}
		if ( get_option( 'pga_old_claude' ) ) {
			echo '<div class="pgs-note err">Раньше помощник был настроен на Claude. Теперь он работает на Google Gemini, как на oscarclinic.kz: вставьте ключ Gemini ниже (aistudio.google.com → Get API key — бесплатно).</div>';
		}
		if ( PGA_AI::busy() ) {
			echo '<div class="pgs-note err"><b>Сейчас высокая нагрузка:</b> большинство ключей упёрлись в лимит — голосовые на сайте временно отключены. Добавьте ещё ключи.</div>';
		}
		$last = get_option( 'pga_last_error' );
		if ( $last ) {
			echo '<div class="pgs-note err"><b>Последняя ошибка ИИ на сайте:</b> ' . esc_html( $last ) . '</div>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'pga_settings' );
		echo '<input type="hidden" name="action" value="pga_settings">';

		/* --- помощник --- */
		echo '<section class="pgs-card"><h2>Помощник</h2>';
		echo self::sw( 'enabled', '1' === (string) $s['enabled'], 'Показывать ИИ‑помощника на сайте', 'Кнопка «Помощник» в центре нижнего меню. Выключите — кнопка исчезнет, сайт работает как обычно.' ); // phpcs:ignore
		echo self::sw( 'handoff', '0' !== (string) ( isset( $s['handoff'] ) ? $s['handoff'] : '1' ), 'Передавать чат менеджеру', 'Если клиент просит человека или ИИ не знает ответа — чат появляется в приложении PetGo → «Чаты с сайта», сотрудникам приходит уведомление.' ); // phpcs:ignore
		echo self::sw( 'log', '1' === (string) $s['log'], 'Сохранять разговоры', 'Последние разговоры видны в приложении → «Чаты с сайта». Хранятся 90 дней.' ); // phpcs:ignore
		echo '<div class="pgs-row"><label class="pgs-f"><span class="pgs-l">Имя в чате</span><input type="text" name="name" value="' . esc_attr( $s['name'] ) . '"></label>';
		echo '<label class="pgs-f narrow"><span class="pgs-l">Сообщений в сутки</span><input type="number" min="0" name="daily_limit" value="' . esc_attr( $s['daily_limit'] ) . '"><small>0 — без ограничения.</small></label></div></section>';

		/* --- ключи --- */
		echo '<section class="pgs-card"><h2>Google Gemini</h2><p class="pgs-sub">Ключ — на aistudio.google.com → Get API key (бесплатный тоже подходит). Ключи хранятся только на сервере и не попадают на страницы сайта.</p>';
		echo '<label class="pgs-f"><span class="pgs-l">Основной ключ</span><input type="password" name="key" autocomplete="off" placeholder="' . esc_attr( $key ? self::mask( $key ) . ' (сохранён — оставьте пустым)' : 'Вставьте ключ Gemini' ) . '">';
		echo $key ? '<small><label><input type="checkbox" name="key_remove" value="1"> удалить сохранённый ключ</label></small>' : '<small>Ключ ещё не добавлен — помощник отвечает только контактами.</small>';
		echo '</label>';
		$pool  = (array) get_option( 'pga_keys', array() );
		$state = (array) get_option( 'pga_key_state', array() );
		echo '<span class="pgs-l" style="margin-top:6px">Запасные ключи</span>';
		if ( $pool ) {
			echo '<ul class="pgs-keys">';
			foreach ( $pool as $i => $pk ) {
				$kid  = PGA_AI::kid( $pk );
				$cool = PGA_AI::cooling( $pk ) && isset( $state[ $kid ] ) ? '<span style="color:#b26200">отдыхает: ' . esc_html( $state[ $kid ]['why'] ) . '</span>' : '<span style="color:#1b7f3a">в работе</span>';
				echo '<li><b>' . ( $i + 1 ) . '</b> <code>' . esc_html( self::mask( $pk ) ) . '</code> ' . $cool . ' <label style="margin-left:auto"><input type="checkbox" name="pool_del[]" value="' . esc_attr( $kid ) . '"> удалить</label></li>'; // phpcs:ignore
			}
			echo '</ul>';
		}
		echo '<label class="pgs-f"><textarea name="pool" rows="3" class="code" placeholder="Вставьте ключи — по одному в строке" autocomplete="off" spellcheck="false"></textarea><small>Ключи с разных аккаунтов Google. Сообщения делятся между всеми ключами по кругу; если ключ упёрся в лимит — сразу берётся следующий, а этот отдыхает минуту. Если не отвечает ни один — посетитель видит WhatsApp и телефон, а вопрос уходит менеджеру.</small></label>';
		echo '<label class="pgs-f"><span class="pgs-l">Модель</span><input type="text" name="model" value="' . esc_attr( $s['model'] ) . '"><small>По умолчанию gemini-3.1-flash-lite (есть бесплатный лимит). Если модель недоступна — плагин сам переключится на рабочую' . ( get_option( 'pga_model_ok' ) ? ' (сейчас работает: ' . esc_html( get_option( 'pga_model_ok' ) ) . ')' : '' ) . '.</small></label></section>';

		/* --- база знаний --- */
		echo '<section class="pgs-card"><h2>База знаний — обучение помощника</h2><p class="pgs-sub">Всё, что помощник должен знать, кроме того, что он и так берёт с сайта. Пишите обычным текстом: часы работы, районы, оплата, правила, акции, ответы на вопросы клиентов. Эти сведения важнее остальных.</p>';
		echo '<p class="description">Цены и скидку сюда не пишите: помощник берёт их из приложения → «Настройки сайта» при каждом сообщении, сразу после сохранения.</p>';
		echo '<label class="pgs-f"><textarea name="know" rows="16" placeholder="Работаем каждый день с 8:00 до 21:00.&#10;Районы: весь город, кроме пригородов.&#10;Оплата: Kaspi перевод после заказа.&#10;Если няня заболела — заранее предупредим и найдём замену.">' . esc_textarea( $s['know'] ) . '</textarea></label>';
		echo '<details class="pgs-know"><summary>Что помощник уже знает с сайта (обновляется само)</summary><pre>' . esc_html( PGA_AI::site_knowledge() ) . '</pre></details>';
		$bk = get_option( 'pga_backup_330' );
		if ( $bk ) {
			echo '<details class="pgs-know"><summary>Старые настройки помощника из версии 33.0 (копия)</summary><pre>' . esc_html( $bk ) . '</pre></details>';
		}
		echo '</section>';

		echo '<div class="pgs-save"><span>Проверка задаёт ИИ вопрос «Сколько стоит выгул?»</span><button type="submit" name="probe" value="1" class="pgs-btn">Проверить каждый ключ</button><button type="submit" name="test" value="1" class="pgs-btn primary">Сохранить и проверить</button></div>';
		echo '</form></div>';
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Недостаточно прав.' );
		}
		check_admin_referer( 'pga_settings' );
		$p = function ( $k ) {
			return isset( $_POST[ $k ] ) ? (string) wp_unslash( $_POST[ $k ] ) : ''; // phpcs:ignore
		};
		$s = PGA_AI::settings( true );
		$k = trim( sanitize_text_field( $p( 'key' ) ) );
		if ( '' !== $k ) {
			$s['key'] = $k;
			delete_option( 'pga_old_claude' );
		}
		if ( '' !== $p( 'key_remove' ) ) {
			$s['key'] = '';
		}
		$pool = (array) get_option( 'pga_keys', array() );
		if ( ! empty( $_POST['pool_del'] ) ) {
			$del  = array_map( 'sanitize_key', (array) wp_unslash( $_POST['pool_del'] ) ); // phpcs:ignore
			$pool = array_values( array_filter( $pool, function ( $x ) use ( $del ) { return ! in_array( PGA_AI::kid( $x ), $del, true ); } ) );
		}
		foreach ( preg_split( '/[\s,;]+/', $p( 'pool' ) ) as $nk ) {
			$nk = trim( sanitize_text_field( $nk ) );
			if ( strlen( $nk ) >= 20 && ! in_array( $nk, $pool, true ) && $nk !== $s['key'] ) {
				$pool[] = $nk;
				delete_option( 'pga_old_claude' );
			}
		}
		update_option( 'pga_keys', array_values( $pool ), false );
		$model = preg_replace( '/[^a-z0-9.\-_]/i', '', $p( 'model' ) );
		if ( $model ) {
			$s['model'] = $model;
		}
		$s['enabled']     = '' !== $p( 'enabled' ) ? '1' : '0';
		$s['handoff']     = '' !== $p( 'handoff' ) ? '1' : '0';
		$s['log']         = '' !== $p( 'log' ) ? '1' : '0';
		$s['name']        = mb_substr( sanitize_text_field( $p( 'name' ) ), 0, 60 );
		$s['daily_limit'] = (string) max( 0, (int) $p( 'daily_limit' ) );
		$s['know']        = mb_substr( sanitize_textarea_field( $p( 'know' ) ), 0, 40000 );
		PGA_AI::save( $s );

		$msg = 'saved';
		if ( '' !== $p( 'probe' ) ) {
			set_transient( 'pga_probe_' . get_current_user_id(), PGA_AI::probe_keys(), 300 );
			$msg = 'probed';
		} elseif ( '' !== $p( 'test' ) && PGA_AI::has_key() ) {
			$res = PGA_AI::ask( array( array( 'role' => 'user', 'text' => 'Сколько стоит выгул? Ответь одной фразой.' ) ), 'ru' );
			set_transient( 'pga_test_' . get_current_user_id(), array( 'ok' => ! is_wp_error( $res ), 'text' => is_wp_error( $res ) ? $res->get_error_message() : $res['reply'] ), 300 );
			$msg = 'tested';
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=petgo-ai&pga=' . $msg ) );
		exit;
	}
}
