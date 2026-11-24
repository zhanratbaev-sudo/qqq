<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
   ЗАЯВКА С САЙТА (без логина): задача, срок, вуз, Telegram и телефон.
   Телефон, с которого её отправили, узнаётся по своей cookie: на нём видно ответ,
   а когда администратор примет заявку — откроется чат (логин и пароль сохраняются в «Пароли»).
   ============================================================ */

const STUDYHUB_REQ_COOKIE = 'studyhub_req';

function studyhub_req_token_hash() {
	$raw = isset( $_COOKIE[ STUDYHUB_REQ_COOKIE ] ) ? preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_COOKIE[ STUDYHUB_REQ_COOKIE ] ) ) : '';
	return strlen( $raw ) === 64 ? hash( 'sha256', 'req|' . $raw ) : '';
}

/** Заявка этого телефона (последняя) или null. */
function studyhub_my_request() {
	$h = studyhub_req_token_hash();
	return $h ? StudyHub_DB::request_by_token( $h ) : null;
}

function studyhub_request_public( $r ) {
	if ( ! $r ) {
		return null;
	}
	return array(
		'id'       => (int) $r->id,
		'status'   => $r->status,
		'service'  => $r->service,
		'task'     => $r->task,
		'deadline' => $r->deadline,
		'reply'    => (string) $r->reply,
		'created'  => (int) $r->created_at,
		'updated'  => (int) $r->updated_at,
		'canOpen'  => 'accepted' === $r->status && (int) $r->student_id > 0 && ! empty( $r->creds ),
	);
}

add_action(
	'rest_api_init',
	function () {
		$ns = 'studyhub/v1';
		register_rest_route( $ns, '/request', array( 'methods' => 'POST', 'callback' => 'studyhub_rest_request_create', 'permission_callback' => 'studyhub_req_header' ) );
		register_rest_route( $ns, '/request', array( 'methods' => 'GET', 'callback' => 'studyhub_rest_request_status', 'permission_callback' => '__return_true' ) );
		register_rest_route( $ns, '/request/claim', array( 'methods' => 'POST', 'callback' => 'studyhub_rest_request_claim', 'permission_callback' => 'studyhub_req_header' ) );
		register_rest_route( $ns, '/request/forget', array( 'methods' => 'POST', 'callback' => 'studyhub_rest_request_forget', 'permission_callback' => 'studyhub_req_header' ) );
	}
);

function studyhub_rest_request_create( WP_REST_Request $req ) {
	$dev = StudyHub_Auth::device_hash();
	if ( StudyHub_DB::requests_today_by_device( $dev ) >= 3 ) {
		return new WP_Error( 'limit', 'too_many_requests', array( 'status' => 429 ) );
	}
	$task = trim( mb_substr( sanitize_textarea_field( (string) $req->get_param( 'task' ) ), 0, 3000 ) );
	if ( mb_strlen( $task ) < 10 ) {
		return new WP_Error( 'task', 'task_short', array( 'status' => 400 ) );
	}
	$tg = preg_replace( '~^(https?://)?(www\.)?(t\.me|telegram\.me)/~i', '', trim( (string) $req->get_param( 'telegram' ) ) );
	$tg = preg_replace( '/[^A-Za-z0-9_]/', '', ltrim( $tg, '@' ) );
	$ph = preg_replace( '/[^\d+]/', '', (string) $req->get_param( 'phone' ) );
	if ( strlen( $tg ) < 4 && strlen( preg_replace( '/\D/', '', $ph ) ) < 10 ) {
		return new WP_Error( 'contact', 'need_contact', array( 'status' => 400 ) );
	}
	$service = sanitize_key( (string) $req->get_param( 'service' ) );
	$valid   = wp_list_pluck( (array) StudyHub_Settings::get( 'services' ), 'id' );
	$uni     = sanitize_key( (string) $req->get_param( 'university' ) );
	$raw     = bin2hex( random_bytes( 32 ) );
	$id      = StudyHub_DB::add_request(
		array(
			'token_hash'  => hash( 'sha256', 'req|' . $raw ),
			'device_hash' => $dev,
			'service'     => in_array( $service, $valid, true ) ? $service : '',
			'task'        => $task,
			'deadline'    => sanitize_text_field( mb_substr( (string) $req->get_param( 'deadline' ), 0, 60 ) ),
			'university'  => in_array( $uni, StudyHub_Settings::university_ids(), true ) ? $uni : '',
			'telegram'    => strlen( $tg ) >= 4 ? '@' . mb_substr( $tg, 0, 40 ) : '',
			'phone'       => mb_substr( $ph, 0, 20 ),
			'lang'        => 'kk' === $req->get_param( 'lang' ) ? 'kk' : 'ru',
			'status'      => 'new',
		)
	);
	StudyHub_Auth::set_cookie( STUDYHUB_REQ_COOKIE, $raw, time() + 60 * DAY_IN_SECONDS );
	studyhub_event( 'request', array( 'request' => $id, 'service' => $service ) );
	if ( StudyHub_Settings::get( 'notify_email' ) ) {
		wp_mail(
			get_option( 'admin_email' ),
			sprintf( '[%s] Новая заявка #%d', StudyHub_Settings::brand(), $id ),
			$task . "\n\n" . studyhub_url( 'panel' ) . '#requests'
		);
	}
	return array( 'request' => studyhub_request_public( StudyHub_DB::get_request( $id ) ) );
}

function studyhub_rest_request_status() {
	return array( 'request' => studyhub_request_public( studyhub_my_request() ) );
}

/** Заявку приняли: открыть чат на этом телефоне и один раз показать логин и пароль (для «Пароли»). */
function studyhub_rest_request_claim() {
	$r = studyhub_my_request();
	if ( ! $r || 'accepted' !== $r->status || ! $r->student_id || empty( $r->creds ) ) {
		return new WP_Error( 'not_ready', 'not_ready', array( 'status' => 400 ) );
	}
	if ( ! hash_equals( (string) $r->device_hash, StudyHub_Auth::device_hash() ) ) {
		return new WP_Error( 'other_device', 'other_device', array( 'status' => 403 ) );
	}
	$student = StudyHub_DB::get_student( $r->student_id );
	if ( ! $student ) {
		return new WP_Error( 'not_found', 'not_found', array( 'status' => 404 ) );
	}
	$res = StudyHub_Auth::start_session( $student );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$creds = json_decode( $r->creds, true );
	StudyHub_DB::update_request( $r->id, array( 'creds' => null ) );
	StudyHub_Auth::set_cookie( STUDYHUB_REQ_COOKIE, '', time() - YEAR_IN_SECONDS );
	return array_merge( studyhub_rest_me(), array( 'creds' => $creds ) );
}

function studyhub_rest_request_forget() {
	StudyHub_Auth::set_cookie( STUDYHUB_REQ_COOKIE, '', time() - YEAR_IN_SECONDS );
	return array( 'ok' => true );
}
