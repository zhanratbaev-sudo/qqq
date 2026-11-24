<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
   ВХОД В ПАНЕЛЬ: логин и пароль WordPress, но форма — на самой странице /panel/
   (без wp-login.php и без админки WordPress). Войти может только администратор сайта.
   ============================================================ */

const STUDYHUB_PANEL_DAYS = 60;

function studyhub_panel_cap() {
	return apply_filters( 'studyhub_panel_cap', 'manage_options' );
}

function studyhub_panel_user_ok( $user = null ) {
	$user = $user instanceof WP_User ? $user : wp_get_current_user(); // из REST сюда приходит запрос, а не пользователь.
	return $user && $user->exists() && user_can( $user, studyhub_panel_cap() );
}

function studyhub_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'studyhub/v1',
			'/panel/login',
			array(
				'methods'             => 'POST',
				'callback'            => 'studyhub_panel_login',
				'permission_callback' => function ( WP_REST_Request $req ) {
					return '1' === $req->get_header( 'x_studyhub' );
				},
			)
		);
		register_rest_route(
			'studyhub/v1',
			'/panel/logout',
			array(
				'methods'             => 'POST',
				'callback'            => function () {
					wp_logout();
					return array( 'ok' => true );
				},
				'permission_callback' => 'is_user_logged_in',
			)
		);
	}
);

function studyhub_panel_login( WP_REST_Request $req ) {
	$key   = 'studyhub_pfail_' . md5( studyhub_ip() );
	$fails = (int) get_transient( $key );
	if ( $fails >= 5 ) {
		return new WP_Error( 'too_many', 'too_many_attempts', array( 'status' => 429 ) );
	}
	$login = sanitize_user( (string) $req->get_param( 'login' ) );
	$pass  = (string) $req->get_param( 'password' );
	if ( '' === $login || '' === $pass ) {
		return new WP_Error( 'bad_login', 'bad_credentials', array( 'status' => 401 ) );
	}
	$user = wp_signon(
		array(
			'user_login'    => $login,
			'user_password' => $pass,
			'remember'      => true,
		),
		is_ssl()
	);
	if ( is_wp_error( $user ) || ! studyhub_panel_user_ok( $user ) ) {
		if ( ! is_wp_error( $user ) ) {
			wp_clear_auth_cookie();
		}
		set_transient( $key, $fails + 1, 15 * MINUTE_IN_SECONDS );
		return new WP_Error( 'bad_login', 'bad_credentials', array( 'status' => 401 ) );
	}
	delete_transient( $key );
	return array( 'ok' => true );
}

/* вход в панель помнится надолго */
add_filter(
	'auth_cookie_expiration',
	function ( $len, $uid, $remember ) {
		return ( $remember && user_can( (int) $uid, studyhub_panel_cap() ) ) ? STUDYHUB_PANEL_DAYS * DAY_IN_SECONDS : $len;
	},
	10,
	3
);
