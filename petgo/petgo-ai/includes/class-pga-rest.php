<?php
/**
 * REST/AJAX ИИ‑консультанта (пространство petgo/v1).
 * Если REST закрыт плагином безопасности — тот же чат работает через admin-ajax.
 */
defined( 'ABSPATH' ) || exit;

class PGA_Rest {

	const NS = 'petgo/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		foreach ( array( 'pga_chat' => 'ajax_chat', 'pga_chat_stream' => 'ajax_stream', 'pga_chat_voice' => 'ajax_voice', 'pga_chat_poll' => 'ajax_poll' ) as $a => $m ) {
			add_action( 'wp_ajax_' . $a, array( __CLASS__, $m ) );
			add_action( 'wp_ajax_nopriv_' . $a, array( __CLASS__, $m ) );
		}
	}

	public static function routes() {
		register_rest_route( self::NS, '/chat', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'chat' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NS, '/chat/stream', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'chat_stream' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NS, '/chat/voice', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'voice' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NS, '/chat/poll', array( 'methods' => 'GET', 'callback' => array( 'PGA_Inbox', 'poll' ), 'permission_callback' => '__return_true' ) );
	}

	public static function ajax_poll() {
		$req = new WP_REST_Request( 'GET', '/petgo/v1/chat/poll' );
		$req->set_param( 'session', isset( $_GET['session'] ) ? sanitize_text_field( wp_unslash( $_GET['session'] ) ) : '' ); // phpcs:ignore
		$req->set_param( 'after', isset( $_GET['after'] ) ? (int) $_GET['after'] : 0 ); // phpcs:ignore
		wp_send_json( PGA_Inbox::poll( $req ) );
	}

	private static function req_from_body( $route ) {
		$body = json_decode( (string) file_get_contents( 'php://input' ), true );
		$req  = new WP_REST_Request( 'POST', '/petgo/v1/' . $route );
		foreach ( (array) $body as $k => $v ) {
			$req->set_param( $k, $v );
		}
		return $req;
	}

	public static function ajax_chat() {
		$res = self::chat( self::req_from_body( 'chat' ) );
		if ( is_wp_error( $res ) ) {
			$d = $res->get_error_data();
			wp_send_json( array( 'message' => $res->get_error_message() ), isset( $d['status'] ) ? (int) $d['status'] : 500 );
		}
		wp_send_json( $res instanceof WP_REST_Response ? $res->get_data() : $res );
	}

	public static function ajax_stream() {
		self::chat_stream( self::req_from_body( 'chat/stream' ) );
	}

	public static function ajax_voice() {
		$res = self::voice( self::req_from_body( 'chat/voice' ) );
		if ( is_wp_error( $res ) ) {
			$d = $res->get_error_data();
			wp_send_json( array( 'message' => $res->get_error_message(), 'voice' => isset( $d['voice'] ) ? $d['voice'] : true ), isset( $d['status'] ) ? (int) $d['status'] : 500 );
		}
		wp_send_json( $res instanceof WP_REST_Response ? $res->get_data() : $res );
	}

	private static function ip() {
		if ( function_exists( 'petgo_ip' ) ) {
			return petgo_ip();
		}
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	private static function rate_msg( $lang ) {
		if ( 'en' === $lang ) {
			return 'Too many messages. Please try again in a few minutes or give us a call.';
		}
		return 'kz' === $lang ? 'Тым көп сұрау. Бірнеше минуттан кейін қайталаңыз немесе бізге қоңырау шалыңыз.' : 'Слишком много сообщений. Попробуйте через несколько минут или позвоните нам.';
	}

	/**
	 * Лимиты: 30 сообщений за 10 минут на один чат; 200 за 10 минут с одного IP
	 * (у мобильных операторов сотни абонентов выходят с одного адреса); общий дневной лимит — если задан.
	 */
	private static function limit( $lang, $sess = '' ) {
		$s = PGA_AI::settings();
		if ( $sess ) {
			$skey = 'pga_s_' . md5( $sess );
			$sh   = (int) get_transient( $skey );
			if ( $sh >= 30 ) {
				return new WP_Error( 'pga_rate', self::rate_msg( $lang ), array( 'status' => 429 ) );
			}
			set_transient( $skey, $sh + 1, 10 * MINUTE_IN_SECONDS );
		}
		$ipkey = 'pga_ip_' . md5( self::ip() );
		$hits  = (int) get_transient( $ipkey );
		if ( $hits >= 200 ) {
			return new WP_Error( 'pga_rate', self::rate_msg( $lang ), array( 'status' => 429 ) );
		}
		set_transient( $ipkey, $hits + 1, 10 * MINUTE_IN_SECONDS );
		$daykey = 'pga_count_' . current_time( 'Ymd' );
		$day    = (int) get_transient( $daykey );
		if ( (int) $s['daily_limit'] && $day >= (int) $s['daily_limit'] ) {
			return new WP_Error( 'pga_rate', self::rate_msg( $lang ), array( 'status' => 429 ) );
		}
		set_transient( $daykey, $day + 1, DAY_IN_SECONDS + HOUR_IN_SECONDS );
		return true;
	}

	private static function off_text( $lang ) {
		$ph = function_exists( 'petgo_site_phone_fmt' ) ? petgo_site_phone_fmt() : '';
		$wa = function_exists( 'petgo_site_wa' ) ? petgo_site_wa() : '';
		if ( 'en' === $lang ) {
			return "The AI assistant can't answer right now. Message or call us: **{$ph}**\n[Message us on WhatsApp](https://wa.me/{$wa})";
		}
		return 'kz' === $lang
			? "Қазір ИИ‑көмекші жауап бере алмайды. Бізге жазыңыз немесе қоңырау шалыңыз: **{$ph}**\n[WhatsApp-қа жазу](https://wa.me/{$wa})"
			: "Сейчас ИИ‑помощник не может ответить. Напишите или позвоните нам: **{$ph}**\n[Написать в WhatsApp](https://wa.me/{$wa})";
	}

	/** Общая подготовка: {ctx} или {response} (готовый ответ) или WP_Error */
	private static function prepare( WP_REST_Request $r ) {
		$lang = in_array( $r['lang'], array( 'kz', 'en' ), true ) ? $r['lang'] : 'ru';
		if ( ! PGA_AI::on() || ! PGA_AI::has_key() ) {
			return array( 'response' => array( 'reply' => self::off_text( $lang ), 'book' => true ) );
		}
		$sess = substr( preg_replace( '/[^a-z0-9]/i', '', (string) $r['session'] ), 0, 40 );
		$msgs = $r->get_param( 'messages' );
		if ( ! is_array( $msgs ) || ! $msgs ) {
			return new WP_Error( 'pga_bad', 'Пустой запрос', array( 'status' => 400 ) );
		}
		$msgs = array_slice( array_values( array_filter( $msgs, 'is_array' ) ), -14 );
		$msgs = array_map( function ( $m ) {
			return array(
				'role' => ( isset( $m['role'] ) && 'user' === $m['role'] ) ? 'user' : 'model',
				'text' => isset( $m['text'] ) ? sanitize_textarea_field( mb_substr( (string) $m['text'], 0, 1500 ) ) : '',
			);
		}, $msgs );
		$last = end( $msgs );
		if ( 'user' !== $last['role'] || '' === trim( $last['text'] ) ) {
			return new WP_Error( 'pga_bad', 'Пустой вопрос', array( 'status' => 400 ) );
		}
		/* цены поменялись после прошлых ответов — старые ответы ИИ (со старыми ценами) не отдаём модели */
		if ( (string) $r['pv'] !== PGA_AI::price_sig() ) {
			$msgs = array_values( array_filter( $msgs, function ( $m ) { return 'user' === $m['role']; } ) );
		}
		/* после экстренной ситуации чат больше не консультирует — повторяет одно и то же */
		if ( $sess && get_transient( 'pga_em_' . md5( $sess ) ) ) {
			return array( 'response' => array( 'reply' => self::emergency_text( $lang ), 'emergency' => true ) );
		}
		/* разговор у менеджера — ИИ молчит, сообщение уходит менеджеру */
		$status = $sess ? PGA_Inbox::status( $sess ) : 'ai';
		if ( in_array( $status, array( 'waiting', 'manager' ), true ) ) {
			self::log( $sess, $lang, 'user', $last['text'] );
			PGA_Inbox::touch( $sess, $lang, $status, true );
			return array( 'response' => array( 'reply' => '', 'queued' => true, 'status' => $status ) );
		}
		$ok = self::limit( $lang, $sess );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		/* 34.0: клиент в своём кабинете — помощник знает его и его питомцев (метка подписана плагином «PetGo Кабинет») */
		$ctok = substr( preg_replace( '/[^a-f0-9.]/i', '', (string) $r['ctx'] ), 0, 80 );
		if ( '' !== $ctok ) {
			PGA_AI::$client = mb_substr( (string) apply_filters( 'pga_client_context', '', $ctok, $lang ), 0, 4000 );
		}
		return array( 'ctx' => compact( 'lang', 'sess', 'msgs', 'last' ) );
	}

	public static function emergency_text( $lang ) {
		if ( 'en' === $lang ) {
			return 'This is an emergency. Act now: for people — call **112** (single emergency number), **102** — police, **103** — ambulance, **101** — fire service. For your pet — go straight to a 24-hour veterinary clinic. This chat cannot help in an emergency.';
		}
		return 'kz' === $lang
			? "Бұл — төтенше жағдай. Дереу әрекет етіңіз: адамдар үшін — **112** (бірыңғай нөмір), **102** — полиция, **103** — жедел жәрдем, **101** — өрт сөндіру қызметі. Үй жануары үшін — бірден тәулік бойы жұмыс істейтін ветеринарлық клиникаға барыңыз. Чат төтенше жағдайда көмектесе алмайды."
			: "Это экстренная ситуация. Действуйте сразу: для людей — **112** (единый номер), **102** — полиция, **103** — скорая помощь, **101** — пожарная служба. Для питомца — немедленно в круглосуточную ветеринарную клинику. Чат не может помочь в экстренной ситуации.";
	}

	/** Все ключи заняты/не ответили: менеджер + WhatsApp и телефон. */
	private static function fail( $ctx, $err ) {
		error_log( '[PetGo AI] ' . $err->get_error_message() ); // phpcs:ignore
		$lang = $ctx['lang'];
		$sess = $ctx['sess'];
		$ph   = function_exists( 'petgo_site_phone_fmt' ) ? petgo_site_phone_fmt() : '';
		$wa   = function_exists( 'petgo_site_wa' ) ? petgo_site_wa() : '';
		$mgr  = $sess && PGA_Inbox::handoff();
		$load = 'pga_api' === $err->get_error_code();
		if ( 'en' === $lang ) {
			$msg = ( $load ? 'The server is very busy right now, so I can’t answer.' : 'I can’t answer this question right now.' ) . ( $mgr ? ' I’ve passed your question to a manager — they will reply right here.' : '' )
				. "\nMessage us on WhatsApp or call: **{$ph}**\n[Message us on WhatsApp](https://wa.me/{$wa})";
		} elseif ( 'kz' === $lang ) {
			$msg = ( $load ? 'Қазір серверде жүктеме жоғары, сондықтан жауап бере алмай тұрмын.' : 'Бұл сұраққа қазір жауап бере алмаймын.' ) . ( $mgr ? ' Сұрағыңызды менеджерге жібердім — ол осы чатта жауап береді.' : '' )
				. "\nWhatsApp-қа жазыңыз немесе қоңырау шалыңыз: **{$ph}**\n[WhatsApp-қа жазу](https://wa.me/{$wa})";
		} else {
			$msg = ( $load ? 'Сейчас высокая нагрузка на сервер, и я не могу ответить.' : 'Сейчас не получается ответить на этот вопрос.' ) . ( $mgr ? ' Передал ваш вопрос менеджеру — он ответит прямо здесь.' : '' )
				. "\nНапишите нам в WhatsApp или позвоните: **{$ph}**\n[Написать в WhatsApp](https://wa.me/{$wa})";
		}
		if ( $mgr ) {
			self::log( $sess, $lang, 'user', $ctx['last']['text'] );
			self::log( $sess, $lang, 'system', 'ИИ не ответил — нужен ответ менеджера' );
			PGA_Inbox::escalate( $sess, $lang, $ctx['last']['text'] );
		}
		return array( 'reply' => $msg, 'escalated' => $mgr, 'status' => $mgr ? 'waiting' : 'ai', 'busy' => $load, 'voice' => ! PGA_AI::busy() );
	}

	/** Ответ получен: журнал, передача менеджеру. */
	private static function done( $ctx, $res ) {
		$s    = PGA_AI::settings();
		$lang = $ctx['lang'];
		$sess = $ctx['sess'];
		if ( ! empty( $res['emergency'] ) ) {
			$res['reply'] = self::emergency_text( $lang );
			if ( $sess ) {
				set_transient( 'pga_em_' . md5( $sess ), 1, 12 * HOUR_IN_SECONDS );
			}
		}
		$res['voice'] = ! PGA_AI::busy();
		$escalate     = ! empty( $res['manager'] ) && $sess && PGA_Inbox::handoff();
		if ( $sess && ( '1' === (string) $s['log'] || $escalate ) ) {
			self::log( $sess, $lang, 'user', $ctx['last']['text'] );
			self::log( $sess, $lang, 'model', $res['reply'] . ( $res['book'] ? ' [[BOOK]]' : '' ) );
		}
		if ( $escalate ) {
			PGA_Inbox::escalate( $sess, $lang, $ctx['last']['text'] );
			$res['escalated'] = true;
			$res['status']    = 'waiting';
		}
		unset( $res['manager'] );
		$res['pv'] = PGA_AI::price_sig();
		return $res;
	}

	public static function chat( WP_REST_Request $r ) {
		$pre = self::prepare( $r );
		if ( is_wp_error( $pre ) ) {
			return $pre;
		}
		if ( isset( $pre['response'] ) ) {
			return rest_ensure_response( $pre['response'] );
		}
		$ctx = $pre['ctx'];
		$res = PGA_AI::ask( $ctx['msgs'], $ctx['lang'] );
		return rest_ensure_response( is_wp_error( $res ) ? self::fail( $ctx, $res ) : self::done( $ctx, $res ) );
	}

	/* ---------- Потоковый ответ (текст появляется по мере генерации) ---------- */
	private static function sse_start() {
		ignore_user_abort( true );
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', '1' ); // phpcs:ignore
		}
		@ini_set( 'zlib.output_compression', '0' ); // phpcs:ignore
		@ini_set( 'output_buffering', '0' ); // phpcs:ignore
		while ( ob_get_level() > 0 ) {
			@ob_end_clean(); // phpcs:ignore
		}
		status_header( 200 );
		header( 'Content-Type: text/event-stream; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-store, no-transform' );
		header( 'X-Accel-Buffering: no' );
		header( 'Content-Encoding: none' );
		echo ':' . str_repeat( ' ', 2048 ) . "\n\n"; // «проталкиваем» буферы прокси
		flush();
	}

	private static function sse( $data ) {
		echo 'data: ' . wp_json_encode( $data ) . "\n\n"; // phpcs:ignore
		if ( ob_get_level() > 0 ) {
			@ob_flush(); // phpcs:ignore
		}
		flush();
	}

	public static function chat_stream( WP_REST_Request $r ) {
		$pre = self::prepare( $r );
		self::sse_start();
		if ( is_wp_error( $pre ) ) {
			self::sse( array( 'error' => $pre->get_error_message() ) );
			exit;
		}
		if ( isset( $pre['response'] ) ) {
			self::sse( array_merge( $pre['response'], array( 'done' => 1 ) ) );
			exit;
		}
		$ctx = $pre['ctx'];
		$res = PGA_AI::ask_stream( $ctx['msgs'], $ctx['lang'], function ( $t ) {
			PGA_Rest::sse( array( 't' => $t ) );
		} );
		self::sse( array_merge( is_wp_error( $res ) ? self::fail( $ctx, $res ) : self::done( $ctx, $res ), array( 'done' => 1 ) ) );
		exit;
	}

	/* ---------- Голосовые: расшифровка в текст ---------- */
	public static function voice( WP_REST_Request $r ) {
		$lang = in_array( $r['lang'], array( 'kz', 'en' ), true ) ? $r['lang'] : 'ru';
		if ( ! PGA_AI::on() || ! PGA_AI::has_key() ) {
			return new WP_Error( 'pga_off', 'kz' === $lang ? 'Дауыстық хабарлама қазір қолжетімсіз.' : 'Голосовые сейчас недоступны — напишите текстом.', array( 'status' => 503 ) );
		}
		if ( PGA_AI::busy() ) {
			return new WP_Error( 'pga_busy', 'kz' === $lang ? 'Қазір жүктеме жоғары — дауыстық хабарлама уақытша өшірулі. Мәтінмен жазыңыз.' : 'Сейчас высокая нагрузка — голосовые временно отключены. Напишите, пожалуйста, текстом.', array( 'status' => 503, 'voice' => false ) );
		}
		$audio = preg_replace( '/\s+/', '', (string) $r['audio'] );
		$mime  = in_array( $r['mime'], array( 'audio/wav', 'audio/webm', 'audio/ogg', 'audio/mp4', 'audio/aac' ), true ) ? $r['mime'] : 'audio/wav';
		if ( '' === $audio || strlen( $audio ) > 4 * 1024 * 1024 || false === base64_decode( $audio, true ) ) {
			return new WP_Error( 'pga_bad', 'kz' === $lang ? 'Хабарлама тым ұзын.' : 'Слишком длинное сообщение — до 1 минуты.', array( 'status' => 400 ) );
		}
		$ok = self::limit( $lang, substr( preg_replace( '/[^a-z0-9]/i', '', (string) $r['session'] ), 0, 40 ) );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$text = PGA_AI::transcribe( $audio, $mime, $lang );
		if ( is_wp_error( $text ) ) {
			error_log( '[PetGo AI] voice: ' . $text->get_error_message() ); // phpcs:ignore
			return new WP_Error( 'pga_ai', 'kz' === $lang ? 'Дауысты тану мүмкін болмады. Мәтінмен жазыңыз.' : 'Не удалось распознать голос. Напишите, пожалуйста, текстом.', array( 'status' => 502 ) );
		}
		return rest_ensure_response( array( 'text' => sanitize_textarea_field( mb_substr( $text, 0, 1500 ) ) ) );
	}

	public static function log( $session, $lang, $role, $text ) {
		global $wpdb;
		$table = PGA_Inbox::chat_table();
		$wpdb->insert( $table, array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'session' => $session,
			'lang'    => $lang,
			'role'    => $role,
			'message' => $text,
			'created' => current_time( 'mysql' ),
		) );
		/* храним 90 дней */
		if ( 1 === wp_rand( 1, 50 ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created < %s", gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ) ); // phpcs:ignore
		}
	}
}
