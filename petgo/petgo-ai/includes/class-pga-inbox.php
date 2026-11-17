<?php
/**
 * Передача чата живому менеджеру.
 * Состояние разговора: ai (отвечает ИИ) → waiting (ждёт менеджера) → manager (менеджер отвечает) → снова ai.
 * Менеджер отвечает в приложении PetGo: раздел «Чаты с сайта» (владелец, админ, менеджер).
 */
defined( 'ABSPATH' ) || exit;

class PGA_Inbox {

	const DB = 1;

	public static function chat_table() {
		global $wpdb;
		return $wpdb->prefix . 'petgo_ai_chat';
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'petgo_ai_state';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$cc = $wpdb->get_charset_collate();
		$c  = self::chat_table();
		$t  = self::table();
		dbDelta( "CREATE TABLE {$c} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session varchar(64) NOT NULL,
			lang varchar(5) NOT NULL DEFAULT '',
			role varchar(10) NOT NULL DEFAULT '',
			message text NOT NULL,
			created datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY session (session),
			KEY created (created)
		) {$cc};" );
		dbDelta( "CREATE TABLE {$t} (
			session varchar(64) NOT NULL,
			lang varchar(5) NOT NULL DEFAULT '',
			status varchar(10) NOT NULL DEFAULT 'ai',
			manager_id bigint(20) unsigned NOT NULL DEFAULT 0,
			unread int(11) NOT NULL DEFAULT 0,
			updated datetime NOT NULL,
			PRIMARY KEY  (session),
			KEY status (status)
		) {$cc};" );
		update_option( 'pga_db', self::DB, false );
	}

	public static function maybe_install() {
		if ( (int) get_option( 'pga_db' ) < self::DB ) {
			self::install();
		}
	}

	/** Передавать ли чат менеджеру (настройка; приложение PetGo должно быть на месте). */
	public static function handoff() {
		$s = PGA_AI::settings();
		return function_exists( 'petgo_can' ) && '0' !== (string) ( isset( $s['handoff'] ) ? $s['handoff'] : '1' );
	}

	private static function row( $session ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE session = %s', $session ), ARRAY_A ); // phpcs:ignore
	}

	public static function status( $session ) {
		$r = self::row( $session );
		return $r ? $r['status'] : 'ai';
	}

	/** Обновить состояние; $from_client — новое сообщение от клиента (растёт счётчик непрочитанных). */
	public static function touch( $session, $lang, $status, $from_client = false ) {
		global $wpdb;
		$r = self::row( $session );
		if ( $r ) {
			$wpdb->update( self::table(), array( // phpcs:ignore
				'status'  => $status,
				'unread'  => $from_client ? (int) $r['unread'] + 1 : (int) $r['unread'],
				'updated' => current_time( 'mysql' ),
			), array( 'session' => $session ) );
		} else {
			$wpdb->insert( self::table(), array( // phpcs:ignore
				'session' => $session,
				'lang'    => $lang,
				'status'  => $status,
				'unread'  => $from_client ? 1 : 0,
				'updated' => current_time( 'mysql' ),
			) );
		}
		if ( $from_client && function_exists( 'petgo_bump' ) ) {
			petgo_bump(); // приложение увидит новое сообщение при ближайшей проверке
		}
	}

	/** Клиент попросил человека: в очередь + уведомление сотрудникам на телефон. */
	public static function escalate( $session, $lang, $text ) {
		$was = self::status( $session );
		self::touch( $session, $lang, 'waiting', true );
		if ( 'waiting' !== $was && function_exists( 'petgo_push_notify' ) && function_exists( 'petgo_push_users' ) ) {
			petgo_push_notify( petgo_push_users( array( 'owner', 'admin', 'manager' ) ), 'Чат с сайта: нужен менеджер', mb_substr( (string) $text, 0, 120 ), petgo_page_url( array( 'pg' => 'aichats', 's' => $session ) ) );
		}
	}

	public static function waiting_count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COALESCE(SUM(unread),0) FROM ' . self::table() . " WHERE status IN ('waiting','manager')" ); // phpcs:ignore
	}

	/** Клиент: новые сообщения менеджера после id. */
	public static function poll( WP_REST_Request $r ) {
		global $wpdb;
		$session = substr( preg_replace( '/[^a-z0-9]/i', '', (string) $r['session'] ), 0, 40 );
		$after   = (int) $r['after'];
		if ( ! $session ) {
			return array( 'status' => 'ai', 'messages' => array() );
		}
		$chat = self::chat_table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, role, message FROM {$chat} WHERE session = %s AND id > %d AND role IN ('manager','system') ORDER BY id ASC LIMIT 50", $session, $after ), ARRAY_A ); // phpcs:ignore
		$st   = self::row( $session );
		$name = '';
		if ( $st && $st['manager_id'] ) {
			$u    = get_user_by( 'id', (int) $st['manager_id'] );
			$name = $u ? $u->display_name : '';
		}
		/* служебная строка «ИИ не ответил…» клиенту не показывается */
		$rows = array_values( array_filter( (array) $rows, function ( $x ) { return 'system' !== $x['role'] || false === strpos( $x['message'], 'нужен ответ менеджера' ); } ) );
		return array( 'status' => $st ? $st['status'] : 'ai', 'manager' => $name, 'messages' => $rows );
	}

	/* ================================================================== *
	 * Приложение PetGo: раздел «Чаты с сайта»
	 * ================================================================== */
	public static function app_init() {
		add_filter( 'petgo_nav_items', function ( $items, $adm ) {
			if ( $adm && function_exists( 'petgo_can' ) && petgo_can( 'office' ) ) {
				$items['aichats'] = array( 'Чаты с сайта', petgo_page_url( array( 'pg' => 'aichats' ) ), 'chat', 'd' );
			}
			return $items;
		}, 10, 2 );
		add_filter( 'petgo_nav_badge', function ( $n, $k ) {
			return 'aichats' === $k ? self::waiting_count() : $n;
		}, 10, 2 );
		add_filter( 'petgo_app_page', function ( $done, $pg ) {
			if ( 'aichats' !== $pg || ! petgo_can( 'office' ) ) {
				return $done;
			}
			self::page();
			return true;
		}, 10, 2 );
		add_action( 'admin_post_petgo_aic_reply', array( __CLASS__, 'h_reply' ) );
		add_action( 'admin_post_petgo_aic_close', array( __CLASS__, 'h_close' ) );
	}

	private static function sess_param() {
		return isset( $_REQUEST['s'] ) ? substr( preg_replace( '/[^a-z0-9]/i', '', (string) wp_unslash( $_REQUEST['s'] ) ), 0, 40 ) : ''; // phpcs:ignore
	}

	private static function css() {
		return '<style>.pga-th{display:flex;flex-direction:column;gap:6px;padding:14px}.pga-m{max-width:84%;padding:9px 13px;border-radius:18px;font-size:16px;line-height:1.38;white-space:pre-wrap;overflow-wrap:anywhere}.pga-m.u{align-self:flex-start;background:var(--so);border-bottom-left-radius:6px}.pga-m.b{align-self:flex-start;background:var(--acs);border-bottom-left-radius:6px}.pga-m.mg{align-self:flex-end;background:var(--acb);color:#fff;border-bottom-right-radius:6px}.pga-m.sy{align-self:center;max-width:92%;padding:4px 12px;border-radius:999px;font-size:13px;color:var(--mu);background:var(--so)}.pga-m small{display:block;margin-top:3px;font-size:11px;opacity:.6}.pga-who{font-size:12px;font-weight:600;color:var(--mu);margin:6px 4px 0}.pga-rep textarea{min-height:88px}.pga-acts{display:flex;gap:8px;margin-top:10px}.pga-acts>*{flex:1}.pga-acts form{display:contents}</style>';
	}

	public static function page() {
		global $wpdb;
		echo self::css(); // phpcs:ignore
		$sess = self::sess_param();
		$chat = self::chat_table();
		if ( $sess ) {
			$st = self::row( $sess );
			$wpdb->update( self::table(), array( 'unread' => 0 ), array( 'session' => $sess ) ); // phpcs:ignore
			$msgs = $wpdb->get_results( $wpdb->prepare( "SELECT id, role, message, created FROM {$chat} WHERE session = %s ORDER BY id ASC LIMIT 300", $sess ), ARRAY_A ); // phpcs:ignore
			echo '<div class="pgc-row pgc-full"><a class="pgc-btn sec sm" href="' . esc_url( petgo_page_url( array( 'pg' => 'aichats' ) ) ) . '">← Все чаты</a><span class="mu">' . esc_html( $st ? ( 'waiting' === $st['status'] ? 'Ждёт ответа' : ( 'manager' === $st['status'] ? 'Вы отвечаете' : 'Отвечает ИИ' ) ) : '' ) . '</span></div>';
			echo '<section class="pgc-panel pgc-full"><div class="pga-th">';
			if ( ! $msgs ) {
				echo '<p class="mu">Сообщений нет.</p>';
			}
			foreach ( (array) $msgs as $m ) {
				$cls  = 'user' === $m['role'] ? 'u' : ( 'manager' === $m['role'] ? 'mg' : ( 'system' === $m['role'] ? 'sy' : 'b' ) );
				$text = trim( str_replace( array( '[[BOOK]]', '[[MANAGER]]', '[[EMERGENCY]]' ), '', (string) $m['message'] ) );
				$time = mysql2date( 'd.m H:i', $m['created'] );
				echo '<div class="pga-m ' . esc_attr( $cls ) . '">' . ( 'b' === $cls ? '<b style="display:block;font-size:12px;opacity:.7">ИИ</b>' : '' ) . esc_html( $text ) . ( 'sy' !== $cls ? '<small>' . esc_html( $time ) . '</small>' : '' ) . '</div>';
			}
			echo '</div></section>';
			if ( $st ) {
				echo '<section class="pgc-panel pgc-full pga-rep">' . petgo_form_open( 'aic_reply' ) . '<input type="hidden" name="s" value="' . esc_attr( $sess ) . '">'; // phpcs:ignore
				echo '<label class="pgc-f"><span>Ответ клиенту</span><textarea name="text" maxlength="2000" required placeholder="Напишите ответ — клиент увидит его в чате на сайте"></textarea></label>';
				echo '<div class="pga-acts"><button type="submit" class="pgc-btn">Отправить</button></div></form>';
				if ( 'ai' !== $st['status'] ) {
					echo '<div class="pga-acts">' . petgo_form_open( 'aic_close' ) . '<input type="hidden" name="s" value="' . esc_attr( $sess ) . '"><button type="submit" class="pgc-btn sec">Завершить — дальше отвечает ИИ</button></form></div>'; // phpcs:ignore
				}
				echo '</section>';
			}
			return;
		}
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table() . " WHERE status IN ('waiting','manager') ORDER BY unread > 0 DESC, updated DESC LIMIT 100", ARRAY_A ); // phpcs:ignore
		echo '<p class="mu pgc-full">Здесь появляются чаты с сайта, где клиент попросил живого человека или ИИ не смог ответить. Ответ увидит клиент прямо в чате на сайте.</p>';
		if ( ! $rows ) {
			echo function_exists( 'petgo_empty' ) ? petgo_empty( 'chat', 'Сейчас никто не ждёт ответа' ) : '<p class="mu">Сейчас никто не ждёт ответа.</p>'; // phpcs:ignore
		} else {
			echo '<section class="pgc-panel pgc-full">';
			foreach ( $rows as $r ) {
				$last = (string) $wpdb->get_var( $wpdb->prepare( "SELECT message FROM {$chat} WHERE session = %s AND role = 'user' ORDER BY id DESC LIMIT 1", $r['session'] ) ); // phpcs:ignore
				$name = 'Клиент ' . strtoupper( substr( $r['session'], 0, 4 ) );
				echo '<a class="pgc-mini" href="' . esc_url( petgo_page_url( array( 'pg' => 'aichats', 's' => $r['session'] ) ) ) . '">' . petgo_av( '', $name, 'md' ) . '<span class="pgc-sm"><b>' . esc_html( $name ) . ( (int) $r['unread'] ? ' <i class="pgc-bd" style="position:static;display:inline-grid">' . (int) $r['unread'] . '</i>' : '' ) . '</b><span class="mu">' . esc_html( mb_substr( $last, 0, 90 ) ) . '</span></span><span class="mu pgc-ago">' . esc_html( 'waiting' === $r['status'] ? 'ждёт' : 'в работе' ) . '</span></a>'; // phpcs:ignore
			}
			echo '</section>';
		}
		$recent = $wpdb->get_results( "SELECT session, MAX(created) last, COUNT(*) n FROM {$chat} GROUP BY session ORDER BY last DESC LIMIT 20", ARRAY_A ); // phpcs:ignore
		if ( $recent ) {
			echo '<div class="pgc-ph2 pgc-full"><h3>Последние разговоры с ИИ</h3></div><section class="pgc-panel pgc-full">';
			foreach ( $recent as $r ) {
				$name = 'Клиент ' . strtoupper( substr( $r['session'], 0, 4 ) );
				echo '<a class="pgc-mini" href="' . esc_url( petgo_page_url( array( 'pg' => 'aichats', 's' => $r['session'] ) ) ) . '">' . petgo_av( '', $name, 'md' ) . '<span class="pgc-sm"><b>' . esc_html( $name ) . '</b><span class="mu">' . (int) $r['n'] . ' сообщ.</span></span><span class="mu pgc-ago">' . esc_html( mysql2date( 'd.m H:i', $r['last'] ) ) . '</span></a>'; // phpcs:ignore
			}
			echo '</section>';
		}
	}

	public static function h_reply() {
		petgo_guard( 'office' );
		global $wpdb;
		$sess = self::sess_param();
		$st   = self::row( $sess );
		$text = trim( sanitize_textarea_field( mb_substr( (string) petgo_p( 'text' ), 0, 2000 ) ) );
		if ( $st && '' !== $text ) {
			$kz = 'kz' === $st['lang'];
			if ( 'manager' !== $st['status'] ) {
				$me = wp_get_current_user();
				PGA_Rest::log( $sess, $st['lang'], 'system', ( $kz ? 'Менеджер қосылды: ' : 'Менеджер подключился: ' ) . $me->display_name );
			}
			PGA_Rest::log( $sess, $st['lang'], 'manager', $text );
			$wpdb->update( self::table(), array( 'status' => 'manager', 'manager_id' => get_current_user_id(), 'unread' => 0, 'updated' => current_time( 'mysql' ) ), array( 'session' => $sess ) ); // phpcs:ignore
			petgo_flash( 'Отправлено — клиент увидит ответ в чате.' );
		}
		petgo_back( 'aichats', array( 's' => $sess ) );
	}

	public static function h_close() {
		petgo_guard( 'office' );
		global $wpdb;
		$sess = self::sess_param();
		$st   = self::row( $sess );
		if ( $st ) {
			$kz = 'kz' === $st['lang'];
			PGA_Rest::log( $sess, $st['lang'], 'system', $kz ? 'Менеджер диалогты аяқтады. Енді ЖИ‑көмекші жауап береді.' : 'Менеджер завершил разговор. Дальше снова отвечает ИИ‑помощник.' );
			$wpdb->update( self::table(), array( 'status' => 'ai', 'unread' => 0, 'updated' => current_time( 'mysql' ) ), array( 'session' => $sess ) ); // phpcs:ignore
			petgo_flash( 'Разговор завершён.' );
		}
		petgo_back( 'aichats' );
	}
}
