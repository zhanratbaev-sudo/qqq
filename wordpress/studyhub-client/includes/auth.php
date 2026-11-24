<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Вход студентов. Студенты — не пользователи WordPress: логин и пароль выдаёт администратор,
 * браузер получает HttpOnly-cookie сессии.
 *
 * - Один логин — одно устройство: устройство узнаётся по своей cookie (studyhub_dev).
 *   На другом телефоне вход закрыт, пока студент не нажмёт «Выйти» на старом или админ не сбросит устройство.
 * - Блокировка Face ID: если включена, сессия «засыпает» через 15 минут без действий
 *   и открывается только подтверждением Face ID / отпечатка (ключ доступа, см. webauthn.php).
 */
class StudyHub_Auth {

	const COOKIE      = 'studyhub_session';
	const DEVICE      = 'studyhub_dev';
	const SESSION_TTL = 90 * DAY_IN_SECONDS;
	const LOCK_IDLE   = 15 * MINUTE_IN_SECONDS;
	const MAX_FAILS   = 6;

	private static $current = false;
	private static $session = null;
	private static $locked  = null;
	private static $device  = null;

	/* ---------- устройство ---------- */

	/** Хеш устройства (cookie ставится при первом визите). */
	public static function device_hash() {
		if ( null !== self::$device ) {
			return self::$device;
		}
		$raw = isset( $_COOKIE[ self::DEVICE ] ) ? preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_COOKIE[ self::DEVICE ] ) ) : '';
		if ( strlen( $raw ) !== 64 ) {
			$raw = bin2hex( random_bytes( 32 ) );
			self::set_cookie( self::DEVICE, $raw, time() + 2 * YEAR_IN_SECONDS );
		}
		self::$device = hash( 'sha256', 'dev|' . $raw );
		return self::$device;
	}

	/* ---------- текущая сессия ---------- */

	private static function load() {
		if ( false !== self::$current ) {
			return;
		}
		self::$current = null;
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return;
		}
		$token = preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		if ( strlen( $token ) !== 64 ) {
			return;
		}
		global $wpdb;
		$t       = StudyHub_DB::table( 'sessions' );
		$session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE token_hash = %s AND expires_at > %d", hash( 'sha256', $token ), time() ) );
		if ( ! $session ) {
			return;
		}
		$student = StudyHub_DB::get_student( $session->student_id );
		if ( ! $student || (int) $student->disabled ) {
			return;
		}
		/* логин привязан к другому устройству (админ сбросил и студент вошёл в другом месте) */
		if ( '' !== (string) $student->device_hash && ! hash_equals( (string) $student->device_hash, self::device_hash() ) ) {
			return;
		}
		self::$session = $session;
		self::$locked  = (int) $student->face_lock && (int) $session->unlock_until < time();
		if ( self::$locked ) {
			self::$current = null;
			self::$locked  = $student;
			return;
		}
		self::$locked = null;
		$upd          = array();
		if ( time() - (int) $student->last_seen > 60 ) {
			StudyHub_DB::update_student( $student->id, array( 'last_seen' => time() ) );
		}
		if ( (int) $student->face_lock && (int) $session->unlock_until - time() < self::LOCK_IDLE - 60 ) {
			$wpdb->update( $t, array( 'unlock_until' => time() + self::LOCK_IDLE ), array( 'id' => $session->id ) );
		}
		self::$current = $student;
	}

	public static function current_student() {
		self::load();
		return self::$current;
	}

	/** Студент, чья сессия ждёт Face ID (или null). */
	public static function locked_student() {
		self::load();
		return self::$locked;
	}

	public static function unlock_current() {
		self::load();
		if ( ! self::$session ) {
			return false;
		}
		global $wpdb;
		$wpdb->update( StudyHub_DB::table( 'sessions' ), array( 'unlock_until' => time() + self::LOCK_IDLE ), array( 'id' => self::$session->id ) );
		self::$current = false;
		self::load();
		return (bool) self::$current;
	}

	/* ---------- вход ---------- */

	private static function ip_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		return 'studyhub_fail_' . md5( $ip );
	}

	public static function too_many() {
		return (int) get_transient( self::ip_key() ) >= self::MAX_FAILS;
	}

	public static function fail() {
		$k = self::ip_key();
		set_transient( $k, (int) get_transient( $k ) + 1, 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * @return object|WP_Error строка студента.
	 */
	public static function login( $login, $password ) {
		if ( self::too_many() ) {
			return new WP_Error( 'too_many', 'too_many_attempts', array( 'status' => 429 ) );
		}
		$student = StudyHub_DB::get_student_by_login( $login );
		if ( ! $student || (int) $student->disabled || ! wp_check_password( $password, $student->pass_hash ) ) {
			self::fail();
			return new WP_Error( 'bad_login', 'bad_credentials', array( 'status' => 401 ) );
		}
		delete_transient( self::ip_key() );
		return self::start_session( $student );
	}

	/** Открыть сессию на этом устройстве (после пароля, Face ID или принятой заявки). */
	public static function start_session( $student, $unlocked = false ) {
		$dev = self::device_hash();
		if ( '' !== (string) $student->device_hash && ! hash_equals( (string) $student->device_hash, $dev ) ) {
			return new WP_Error( 'other_device', 'other_device', array( 'status' => 403 ) );
		}
		if ( '' === (string) $student->device_hash ) {
			StudyHub_DB::update_student( $student->id, array( 'device_hash' => $dev ) );
		}
		/* одна активная сессия на студента */
		self::destroy_sessions( $student->id );
		$token = bin2hex( random_bytes( 32 ) );
		global $wpdb;
		$wpdb->insert(
			StudyHub_DB::table( 'sessions' ),
			array(
				'student_id'   => $student->id,
				'token_hash'   => hash( 'sha256', $token ),
				'created_at'   => time(),
				'expires_at'   => time() + self::SESSION_TTL,
				'unlock_until' => time() + self::LOCK_IDLE,
			)
		);
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . StudyHub_DB::table( 'sessions' ) . ' WHERE expires_at < %d', time() ) );
		self::set_cookie( self::COOKIE, $token, time() + self::SESSION_TTL );
		StudyHub_DB::update_student( $student->id, array( 'last_seen' => time() ) );
		self::$current = false;
		self::load();
		return StudyHub_DB::get_student( $student->id );
	}

	/** «Выйти»: закрыть сессию и освободить устройство (можно войти на другом телефоне). */
	public static function logout() {
		self::load();
		$s = self::$current ? self::$current : self::$locked;
		if ( $s ) {
			StudyHub_DB::update_student( $s->id, array( 'device_hash' => '' ) );
			self::destroy_sessions( $s->id );
		}
		self::set_cookie( self::COOKIE, '', time() - YEAR_IN_SECONDS );
		self::$current = null;
		self::$locked  = null;
	}

	public static function destroy_sessions( $student_id ) {
		global $wpdb;
		$wpdb->delete( StudyHub_DB::table( 'sessions' ), array( 'student_id' => (int) $student_id ) );
	}

	public static function set_cookie( $name, $value, $expires ) {
		if ( headers_sent() ) {
			return;
		}
		setcookie(
			$name,
			$value,
			array(
				'expires'  => $expires,
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		if ( '' === $value ) {
			unset( $_COOKIE[ $name ] );
		} else {
			$_COOKIE[ $name ] = $value;
		}
	}

	public static function generate_password( $length = 10 ) {
		$chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$out   = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$out .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
		}
		return $out;
	}

	public static function generate_login() {
		for ( $i = 0; $i < 20; $i++ ) {
			$login = 'st' . random_int( 10000, 99999 );
			if ( ! StudyHub_DB::get_student_by_login( $login ) ) {
				return $login;
			}
		}
		return 'st' . bin2hex( random_bytes( 4 ) );
	}
}
