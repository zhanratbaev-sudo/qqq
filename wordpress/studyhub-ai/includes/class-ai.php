<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Gemini: пул ключей (по кругу, «уставший» ключ отдыхает), запасные модели, знания из настроек StudyHub.
 */
class StudyHub_AI {

	const OPTION   = 'studyhub_ai';
	const KEYS     = 'studyhub_ai_keys';
	const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

	public static function defaults() {
		return array(
			'enabled'     => 1,
			'models'      => 'gemini-2.5-flash, gemini-2.5-flash-lite',
			'daily_limit' => 40,
			'guest_limit' => 10,
			'temperature' => 0.6,
			'knowledge'   => '',
			'extra'       => '',
		);
	}

	public static function settings() {
		$s = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $s ) ? $s : array() );
	}

	public static function save( array $s ) {
		update_option( self::OPTION, array_merge( self::settings(), $s ), false );
	}

	public static function keys() {
		$out = array();
		foreach ( (array) get_option( self::KEYS, array() ) as $k ) {
			$k = trim( (string) $k );
			if ( '' !== $k ) {
				$out[] = $k;
			}
		}
		return array_values( array_unique( $out ) );
	}

	public static function set_keys( array $keys ) {
		update_option( self::KEYS, array_values( array_unique( array_filter( array_map( 'trim', $keys ) ) ) ), false );
	}

	public static function on() {
		$s = self::settings();
		return ! empty( $s['enabled'] ) && (bool) self::keys() && function_exists( 'studyhub_current_student' );
	}

	public static function models() {
		$list = array_filter( array_map( 'trim', explode( ',', (string) self::settings()['models'] ) ) );
		$list = array_values( array_filter( $list, function ( $m ) {
			return preg_match( '/^[a-z0-9.\-]+$/i', $m ) && ! get_transient( 'studyhub_ai_nomodel_' . md5( $m ) );
		} ) );
		return $list ? $list : array( 'gemini-2.5-flash' );
	}

	/* ---------- знания: услуги, цены, вузы, правила + «База знаний» ---------- */
	public static function system_prompt( $lang ) {
		$brand    = StudyHub_Settings::brand();
		$s        = self::settings();
		$unis     = array();
		$services = array();
		foreach ( (array) StudyHub_Settings::get( 'universities' ) as $u ) {
			$unis[] = $u['short'] . ( $u['name_ru'] ? ' (' . $u['name_ru'] . ')' : '' );
		}
		foreach ( (array) StudyHub_Settings::get( 'services' ) as $svc ) {
			$services[] = '- ' . $svc['title_ru'] . ( $svc['price'] ? ' — ' . $svc['price'] : ' — цена обсуждается в чате' ) . ( $svc['desc_ru'] ? ': ' . $svc['desc_ru'] : '' );
		}
		$lang_rule = 'kk' === $lang
			? 'Отвечай на казахском языке (если студент пишет по-русски — по-русски).'
			: 'Отвечай на русском языке (если студент пишет по-казахски — по-казахски).';
		$p  = "Ты — ИИ-помощник сервиса «{$brand}» для студентов архитектурных и строительных специальностей в Казахстане";
		$p .= $unis ? ' (университеты: ' . implode( ', ', $unis ) . ").\n" : ".\n";
		$p .= "Помогаешь с учёбой: архитектура, композиция, рисунок, черчение, ГОСТ/СНиП/СП РК, макеты, AutoCAD, ArchiCAD, Revit, SketchUp, 3ds Max, Photoshop, история архитектуры, оформление работ.\n";
		$p .= $services ? "Услуги сервиса:\n" . implode( "\n", $services ) . "\n" : '';
		if ( '' !== trim( $s['knowledge'] ) ) {
			$p .= "База знаний сервиса (опирайся на неё):\n" . $s['knowledge'] . "\n";
		}
		$p .= "Правила:\n- {$lang_rule}\n";
		$p .= "- Кратко и по делу, короткие списки, без лишних вступлений.\n";
		$p .= "- Ты ИИ, не человек. Не придумывай имена сотрудников и контакты.\n";
		$p .= "- Сервис полностью анонимный: никогда не спрашивай имя, телефон, фото, номер группы или другие личные данные.\n";
		$p .= "- Заказ, точная цена, сроки, оплата, готовая работа — если студент ещё без логина, предложи нажать «Оставить заявку» (опишет задачу, оставит Telegram или телефон); если уже вошёл — написать в чат «{$brand}». Не обещай цены и сроки сам.\n";
		$p .= "- Все работы делаются вручную. Сервис конфиденциальный: о нём не стоит рассказывать преподавателям.\n";
		if ( '' !== trim( $s['extra'] ) ) {
			$p .= "Дополнительно:\n" . $s['extra'] . "\n";
		}
		return $p;
	}

	/** Сообщения [{role, content}] → contents для Gemini (роли чередуются). */
	public static function contents( array $messages ) {
		$out = array();
		foreach ( array_slice( $messages, -12 ) as $m ) {
			if ( ! is_array( $m ) || empty( $m['content'] ) ) {
				continue;
			}
			$role = ( isset( $m['role'] ) && 'assistant' === $m['role'] ) ? 'model' : 'user';
			$text = mb_substr( sanitize_textarea_field( (string) $m['content'] ), 0, 4000 );
			if ( '' === $text ) {
				continue;
			}
			$last = count( $out ) - 1;
			if ( $last >= 0 && $out[ $last ]['role'] === $role ) {
				$out[ $last ]['parts'][0]['text'] .= "\n\n" . $text;
			} else {
				$out[] = array( 'role' => $role, 'parts' => array( array( 'text' => $text ) ) );
			}
		}
		while ( $out && 'user' !== $out[0]['role'] ) {
			array_shift( $out );
		}
		return $out;
	}

	public static function cooling( $key ) {
		return (bool) get_transient( 'studyhub_ai_cool_' . md5( $key ) );
	}

	private static function cool( $key, $seconds ) {
		set_transient( 'studyhub_ai_cool_' . md5( $key ), 1, $seconds );
	}

	public static function last_error( $msg = null ) {
		if ( null === $msg ) {
			return (string) get_option( 'studyhub_ai_last_error', '' );
		}
		update_option( 'studyhub_ai_last_error', gmdate( 'd.m H:i' ) . ' UTC — ' . $msg, false );
		return $msg;
	}

	/**
	 * Запрос к Gemini: ключи по кругу, «уставший» (429) отдыхает минуту, недоступная модель пропускается.
	 *
	 * @return string|WP_Error
	 */
	public static function request( array $contents, $system, $only_key = null ) {
		$keys = $only_key ? array( $only_key ) : self::keys();
		if ( ! $keys ) {
			return new WP_Error( 'no_key', 'Нет ключа Gemini.', array( 'status' => 503 ) );
		}
		$n     = count( $keys );
		$start = (int) get_option( 'studyhub_ai_rr', 0 ) % $n;
		update_option( 'studyhub_ai_rr', $start + 1, false );
		$order = array_merge( array_slice( $keys, $start ), array_slice( $keys, 0, $start ) );
		$tired = array_values( array_filter( $order, array( __CLASS__, 'cooling' ) ) );
		$order = array_values( array_merge( array_diff( $order, $tired ), $tired ) ); // отдыхающие — в конец.
		$s     = self::settings();
		$body  = array(
			'systemInstruction' => array( 'parts' => array( array( 'text' => $system ) ) ),
			'contents'          => $contents,
			'generationConfig'  => array( 'temperature' => (float) $s['temperature'], 'maxOutputTokens' => 2048 ),
		);
		$err = 'unknown';
		foreach ( $order as $key ) {
			foreach ( self::models() as $model ) {
				$res = wp_remote_post(
					sprintf( self::ENDPOINT, rawurlencode( $model ) ),
					array(
						'timeout' => 40,
						'headers' => array( 'Content-Type' => 'application/json', 'x-goog-api-key' => $key ),
						'body'    => wp_json_encode( $body ),
					)
				);
				if ( is_wp_error( $res ) ) {
					$err = $res->get_error_message();
					continue;
				}
				$code = (int) wp_remote_retrieve_response_code( $res );
				$data = json_decode( wp_remote_retrieve_body( $res ), true );
				if ( 200 === $code ) {
					$text = '';
					foreach ( (array) ( isset( $data['candidates'][0]['content']['parts'] ) ? $data['candidates'][0]['content']['parts'] : array() ) as $part ) {
						if ( isset( $part['text'] ) && empty( $part['thought'] ) ) {
							$text .= $part['text'];
						}
					}
					if ( '' !== trim( $text ) ) {
						return trim( $text );
					}
					$err = 'пустой ответ (' . $model . ')';
					continue;
				}
				$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'HTTP ' . $code;
				$err = $model . ': ' . $msg;
				if ( 404 === $code ) {
					set_transient( 'studyhub_ai_nomodel_' . md5( $model ), 1, DAY_IN_SECONDS );
					continue;
				}
				if ( 429 === $code ) {
					self::cool( $key, MINUTE_IN_SECONDS );
					break; // следующий ключ.
				}
				if ( 400 === $code || 401 === $code || 403 === $code ) {
					self::cool( $key, 10 * MINUTE_IN_SECONDS );
					break; // ключ не работает — следующий.
				}
			}
		}
		self::last_error( $err );
		return new WP_Error( 'ai_unavailable', $err, array( 'status' => 502 ) );
	}
}
