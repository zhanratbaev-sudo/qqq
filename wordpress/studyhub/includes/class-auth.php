<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Student authentication. Students are NOT WordPress users:
 * the admin issues a login/password, the student gets an HttpOnly session cookie.
 */
class StudyHub_Auth {

	const COOKIE      = 'studyhub_session';
	const SESSION_TTL = 90 * DAY_IN_SECONDS;
	const MAX_FAILS   = 6;

	private static $current = false;

	public static function current_student() {
		if ( false !== self::$current ) {
			return self::$current;
		}
		self::$current = null;

		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return null;
		}
		$token = preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		if ( strlen( $token ) !== 64 ) {
			return null;
		}

		global $wpdb;
		$sessions = StudyHub_DB::table( 'sessions' );
		$session  = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $sessions WHERE token_hash = %s AND expires_at > %d", hash( 'sha256', $token ), time() )
		);
		if ( ! $session ) {
			return null;
		}
		$student = StudyHub_DB::get_student( $session->student_id );
		if ( ! $student || (int) $student->disabled ) {
			return null;
		}
		if ( time() - (int) $student->last_seen > 60 ) {
			StudyHub_DB::update_student( $student->id, array( 'last_seen' => time() ) );
		}
		self::$current = $student;
		return $student;
	}

	private static function ip_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		return 'studyhub_fail_' . md5( $ip );
	}

	/**
	 * @return object|WP_Error student row on success.
	 */
	public static function login( $login, $password ) {
		$key   = self::ip_key();
		$fails = (int) get_transient( $key );
		if ( $fails >= self::MAX_FAILS ) {
			return new WP_Error( 'too_many', 'too_many_attempts', array( 'status' => 429 ) );
		}

		$student = StudyHub_DB::get_student_by_login( $login );
		if ( ! $student || (int) $student->disabled || ! wp_check_password( $password, $student->pass_hash ) ) {
			set_transient( $key, $fails + 1, 15 * MINUTE_IN_SECONDS );
			return new WP_Error( 'bad_login', 'bad_credentials', array( 'status' => 401 ) );
		}
		delete_transient( $key );

		$token = bin2hex( random_bytes( 32 ) );
		global $wpdb;
		$wpdb->insert(
			StudyHub_DB::table( 'sessions' ),
			array(
				'student_id' => $student->id,
				'token_hash' => hash( 'sha256', $token ),
				'created_at' => time(),
				'expires_at' => time() + self::SESSION_TTL,
			)
		);
		// Opportunistic cleanup of expired sessions.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . StudyHub_DB::table( 'sessions' ) . ' WHERE expires_at < %d', time() ) );

		self::set_cookie( $token, time() + self::SESSION_TTL );
		StudyHub_DB::update_student( $student->id, array( 'last_seen' => time() ) );
		self::$current = $student;
		return $student;
	}

	public static function logout() {
		if ( ! empty( $_COOKIE[ self::COOKIE ] ) ) {
			$token = preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_COOKIE[ self::COOKIE ] ) );
			global $wpdb;
			$wpdb->delete( StudyHub_DB::table( 'sessions' ), array( 'token_hash' => hash( 'sha256', $token ) ) );
		}
		self::set_cookie( '', time() - YEAR_IN_SECONDS );
		self::$current = null;
	}

	public static function destroy_sessions( $student_id ) {
		global $wpdb;
		$wpdb->delete( StudyHub_DB::table( 'sessions' ), array( 'student_id' => (int) $student_id ) );
	}

	private static function set_cookie( $value, $expires ) {
		$path = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		setcookie(
			self::COOKIE,
			$value,
			array(
				'expires'  => $expires,
				'path'     => $path,
				'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		if ( '' === $value ) {
			unset( $_COOKIE[ self::COOKIE ] );
		} else {
			$_COOKIE[ self::COOKIE ] = $value;
		}
	}

	/**
	 * Generate a readable random password (no ambiguous chars).
	 */
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
