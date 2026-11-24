<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
   REST СТУДЕНТА: studyhub/v1/login, me, messages, reviews (POST), file/{id}
   ============================================================ */

function studyhub_student_shape( $s ) {
	return array(
		'alias'      => $s->alias,
		'university' => $s->university,
		'lang'       => $s->lang,
	);
}

/* заголовок X-StudyHub заставляет браузер делать предзапрос — чужой сайт не отправит запрос с cookie студента */
function studyhub_req_header( WP_REST_Request $req ) {
	return 'GET' === $req->get_method() || '1' === $req->get_header( 'x_studyhub' );
}

function studyhub_req_student( WP_REST_Request $req ) {
	if ( ! studyhub_req_header( $req ) ) {
		return new WP_Error( 'forbidden', 'forbidden', array( 'status' => 403 ) );
	}
	if ( ! StudyHub_Auth::current_student() ) {
		return new WP_Error( 'not_logged_in', 'not_logged_in', array( 'status' => 401 ) );
	}
	return true;
}

add_action(
	'rest_api_init',
	function () {
		$ns = 'studyhub/v1';
		$st = 'studyhub_req_student';
		register_rest_route( $ns, '/login', array( 'methods' => 'POST', 'callback' => 'studyhub_rest_login', 'permission_callback' => 'studyhub_req_header' ) );
		register_rest_route(
			$ns,
			'/logout',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'studyhub_req_header',
				'callback'            => function () {
					StudyHub_Auth::logout();
					return array( 'ok' => true );
				},
			)
		);
		register_rest_route( $ns, '/me', array( 'methods' => 'GET', 'callback' => 'studyhub_rest_me', 'permission_callback' => $st ) );
		register_rest_route( $ns, '/me', array( 'methods' => 'POST', 'callback' => 'studyhub_rest_me_update', 'permission_callback' => $st ) );
		register_rest_route( $ns, '/messages', array( 'methods' => 'GET', 'callback' => 'studyhub_rest_student_messages', 'permission_callback' => $st ) );
		register_rest_route( $ns, '/messages', array( 'methods' => 'POST', 'callback' => 'studyhub_rest_student_send', 'permission_callback' => $st ) );
		register_rest_route( $ns, '/review', array( 'methods' => 'POST', 'callback' => 'studyhub_rest_review_submit', 'permission_callback' => $st ) );
		register_rest_route( $ns, '/file/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => 'studyhub_rest_file', 'permission_callback' => '__return_true' ) );
	}
);

function studyhub_rest_login( WP_REST_Request $req ) {
	$login = sanitize_user( (string) $req->get_param( 'login' ), true );
	$pass  = (string) $req->get_param( 'password' );
	if ( '' === $login || '' === $pass ) {
		return new WP_Error( 'bad_login', 'bad_credentials', array( 'status' => 401 ) );
	}
	$s = StudyHub_Auth::login( $login, $pass );
	if ( is_wp_error( $s ) ) {
		return $s;
	}
	studyhub_event( 'student_login', array( 'student' => (int) $s->id ) );
	return studyhub_rest_me();
}

function studyhub_rest_me() {
	$s = StudyHub_Auth::current_student();
	return array(
		'student'     => studyhub_student_shape( $s ),
		'unread'      => StudyHub_DB::unread_for_student( $s->id ),
		'adminOnline' => StudyHub_Settings::admin_online(),
		'now'         => time(),
	);
}

function studyhub_rest_me_update( WP_REST_Request $req ) {
	$s    = StudyHub_Auth::current_student();
	$data = array();
	$uni  = $req->get_param( 'university' );
	if ( null !== $uni ) {
		$uni = sanitize_key( $uni );
		if ( '' === $uni || in_array( $uni, StudyHub_Settings::university_ids(), true ) ) {
			$data['university'] = $uni;
		}
	}
	$lang = $req->get_param( 'lang' );
	if ( in_array( $lang, array( 'ru', 'kk' ), true ) ) {
		$data['lang'] = $lang;
	}
	if ( $data ) {
		StudyHub_DB::update_student( $s->id, $data );
	}
	return array( 'student' => studyhub_student_shape( StudyHub_DB::get_student( $s->id ) ) );
}

function studyhub_rest_student_messages( WP_REST_Request $req ) {
	$s   = StudyHub_Auth::current_student();
	$out = array();
	foreach ( StudyHub_DB::messages( $s->id, max( 0, (int) $req->get_param( 'after' ) ) ) as $m ) {
		$out[] = studyhub_message_shape( $m, 'student' );
	}
	if ( ! $req->get_param( 'peek' ) ) {
		StudyHub_DB::mark_read( $s->id, 'student' );
	}
	$read = StudyHub_DB::last_read_ids( $s->id );
	return array(
		'messages'    => $out,
		'readUpTo'    => $read['admin'],
		'adminOnline' => StudyHub_Settings::admin_online(),
		'now'         => time(),
	);
}

function studyhub_rest_student_send( WP_REST_Request $req ) {
	$s       = StudyHub_Auth::current_student();
	$text    = trim( mb_substr( sanitize_textarea_field( (string) $req->get_param( 'text' ) ), 0, 4000 ) );
	$file_id = 0;
	$files   = $req->get_file_params();
	if ( ! empty( $files['file'] ) ) {
		$file_id = StudyHub_Files::store_upload( $files['file'], $s->id, 'student' );
		if ( is_wp_error( $file_id ) ) {
			return $file_id;
		}
	}
	if ( '' === $text && ! $file_id ) {
		return new WP_Error( 'empty', 'empty_message', array( 'status' => 400 ) );
	}
	$service = sanitize_key( (string) $req->get_param( 'service' ) );
	$id      = StudyHub_DB::add_message( $s->id, 'student', $file_id ? 'file' : 'text', $text, $file_id, $service ? array( 'service' => $service ) : null );
	studyhub_event( 'student_message', array( 'student' => (int) $s->id, 'message' => $id, 'service' => $service ) );
	studyhub_notify_admin( $s, $text );
	return array( 'message' => studyhub_message_shape( StudyHub_DB::get_message( $id ), 'student' ) );
}

/** Убрать всё, что может раскрыть автора отзыва: ссылки, e-mail, телефоны, @ники. */
function studyhub_anonymize_review( $text ) {
	$text = sanitize_textarea_field( $text );
	$text = preg_replace( '~https?://\S+|www\.\S+~iu', '', $text );
	$text = preg_replace( '~[\w.+-]+@[\w-]+\.[\w.]+~u', '', $text );
	$text = preg_replace( '~(?<!\w)@[\w.]{2,}~u', '', $text );
	$text = preg_replace( '~\+?\d[\d\s()\-]{7,}\d~u', '', $text );
	$text = preg_replace( '~[ \t]{2,}~u', ' ', $text );
	return trim( mb_substr( $text, 0, 600 ) );
}

function studyhub_rest_review_submit( WP_REST_Request $req ) {
	$s         = StudyHub_Auth::current_student();
	$invite_id = (int) $req->get_param( 'invite' );
	$invite    = StudyHub_DB::get_message( $invite_id );
	if ( ! $invite || (int) $invite->student_id !== (int) $s->id || 'review_request' !== $invite->type ) {
		return new WP_Error( 'no_invite', 'no_invite', array( 'status' => 403 ) );
	}
	$meta = $invite->meta ? json_decode( $invite->meta, true ) : array();
	if ( ! empty( $meta['done'] ) ) {
		return new WP_Error( 'already', 'already_reviewed', array( 'status' => 409 ) );
	}
	$rating = min( 5, max( 1, (int) $req->get_param( 'rating' ) ) );
	$text   = studyhub_anonymize_review( (string) $req->get_param( 'text' ) );
	if ( mb_strlen( $text ) < 3 ) {
		return new WP_Error( 'short', 'review_too_short', array( 'status' => 400 ) );
	}
	$status    = StudyHub_Settings::get( 'reviews_automod' ) ? 'approved' : 'pending';
	$review_id = StudyHub_DB::add_review(
		array(
			'student_id' => $s->id,
			'invite_id'  => $invite_id,
			'university' => $s->university,
			'service'    => isset( $meta['service'] ) ? sanitize_key( $meta['service'] ) : '',
			'rating'     => $rating,
			'body'       => $text,
			'status'     => $status,
		)
	);
	$meta['done']   = true;
	$meta['rating'] = $rating;
	$meta['review'] = $review_id;
	StudyHub_DB::update_message_meta( $invite_id, $meta );
	StudyHub_DB::add_message( $s->id, 'student', 'review', $text, 0, array( 'rating' => $rating, 'review' => $review_id ) );
	studyhub_event( 'review', array( 'review' => $review_id, 'rating' => $rating ) );
	return array( 'ok' => true, 'status' => $status );
}

function studyhub_rest_file( WP_REST_Request $req ) {
	$file = StudyHub_DB::get_file( (int) $req['id'] );
	if ( ! $file ) {
		return new WP_Error( 'not_found', 'not_found', array( 'status' => 404 ) );
	}
	$s = StudyHub_Auth::current_student();
	if ( ! ( $s && (int) $s->id === (int) $file->student_id ) ) {
		return new WP_Error( 'forbidden', 'forbidden', array( 'status' => 403 ) );
	}
	StudyHub_Files::stream( $file, (bool) $req->get_param( 'download' ) );
}

function studyhub_notify_admin( $student, $text ) {
	if ( ! StudyHub_Settings::get( 'notify_email' ) ) {
		return;
	}
	$key = 'studyhub_notified_' . $student->id;
	if ( get_transient( $key ) ) {
		return;
	}
	set_transient( $key, 1, 10 * MINUTE_IN_SECONDS );
	wp_mail(
		get_option( 'admin_email' ),
		sprintf( '[%s] Новое сообщение от %s', StudyHub_Settings::brand(), $student->alias ),
		wp_html_excerpt( $text, 300, '…' ) . "\n\n" . studyhub_url( 'panel' ) . '#chats'
	);
}
