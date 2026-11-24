<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
   REST ПАНЕЛИ: studyhub/v1/admin/…  (вход WordPress + X-WP-Nonce)
   ============================================================ */

/** Сообщение для панели ('admin') или для студента ('student'). */
function studyhub_message_shape( $m, $for ) {
	$file = $m->file_id ? StudyHub_Files::public_shape( StudyHub_DB::get_file( $m->file_id ) ) : null;
	if ( $file ) {
		$file['url'] = 'admin' === $for
			? add_query_arg( array( 'action' => 'studyhub_file', 'id' => $file['id'], '_wpnonce' => wp_create_nonce( 'studyhub_file' ) ), admin_url( 'admin-post.php' ) )
			: rest_url( 'studyhub/v1/file/' . $file['id'] );
	}
	if ( 'student' === $for ) {
		$from = 'student' === $m->sender ? 'me' : ( 'system' === $m->sender ? 'system' : 'brand' );
	} else {
		$from = $m->sender;
	}
	return array(
		'id'   => (int) $m->id,
		'from' => $from,
		'type' => $m->type,
		'text' => (string) $m->body,
		'file' => $file,
		'meta' => $m->meta ? json_decode( $m->meta, true ) : null,
		'time' => (int) $m->created_at,
	);
}

/* файлы чатов для панели: вход WordPress + одноразовая подпись */
add_action( 'admin_post_studyhub_file', 'studyhub_admin_file' );
function studyhub_admin_file() {
	if ( ! studyhub_panel_user_ok() || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'studyhub_file' ) ) {
		wp_die( 'Forbidden', 403 );
	}
	$file = StudyHub_DB::get_file( isset( $_GET['id'] ) ? (int) $_GET['id'] : 0 );
	if ( ! $file ) {
		wp_die( 'Not found', 404 );
	}
	StudyHub_Files::stream( $file, ! empty( $_GET['download'] ) );
}

add_action( 'rest_api_init', 'studyhub_panel_routes' );
function studyhub_panel_routes() {
	$ns = 'studyhub/v1';
	$ok = 'studyhub_panel_user_ok';
	$r  = function ( $path, $methods, $cb ) use ( $ns, $ok ) {
		register_rest_route( $ns, $path, array( 'methods' => $methods, 'callback' => $cb, 'permission_callback' => $ok ) );
	};
	$r( '/admin/chats', 'GET', 'studyhub_rest_chats' );
	$r( '/admin/chats/(?P<id>\d+)/messages', 'GET', 'studyhub_rest_messages' );
	$r( '/admin/chats/(?P<id>\d+)/messages', 'POST', 'studyhub_rest_send' );
	$r( '/admin/chats/(?P<id>\d+)/review-request', 'POST', 'studyhub_rest_review_request' );
	$r( '/admin/chats/(?P<id>\d+)/note', 'POST', 'studyhub_rest_note' );
	$r( '/admin/status', 'POST', 'studyhub_rest_status' );
	$r( '/admin/students', 'GET', 'studyhub_rest_students' );
	$r( '/admin/students', 'POST', 'studyhub_rest_student_create' );
	$r( '/admin/students/(?P<id>\d+)', 'POST', 'studyhub_rest_student_action' );
	$r( '/admin/reviews', 'GET', 'studyhub_rest_reviews' );
	$r( '/admin/reviews/(?P<id>\d+)', 'POST', 'studyhub_rest_review_action' );
	$r( '/admin/settings', 'GET', 'studyhub_rest_settings' );
	$r( '/admin/settings', 'POST', 'studyhub_rest_settings_save' );
	$r( '/admin/catalog', 'POST', 'studyhub_rest_catalog_save' );
	$r( '/admin/upload-image', 'POST', 'studyhub_rest_upload_image' );
	$r( '/admin/sections/(?P<key>[a-z0-9_-]+)', 'GET', 'studyhub_rest_section' );
	$r( '/admin/sections/(?P<key>[a-z0-9_-]+)', 'POST', 'studyhub_rest_section_save' );
}

function studyhub_heartbeat() {
	update_option( 'studyhub_admin_heartbeat', time(), false );
}

/* ---------- чаты ---------- */

function studyhub_rest_chats() {
	studyhub_heartbeat();
	$out = array();
	foreach ( StudyHub_DB::list_students() as $s ) {
		$last = $s->last_msg_id ? StudyHub_DB::get_message( $s->last_msg_id ) : null;
		$prev = '';
		if ( $last ) {
			$prev = $last->body ? $last->body : ( $last->file_id ? '📎 файл' : '' );
			if ( 'review_request' === $last->type ) {
				$prev = '⭐ Запрос отзыва';
			}
			$prev = ( 'student' === $last->sender ? '' : 'Вы: ' ) . wp_html_excerpt( $prev, 70, '…' );
		}
		$out[] = array(
			'id'         => (int) $s->id,
			'alias'      => $s->alias,
			'login'      => $s->login,
			'university' => StudyHub_Settings::university_label( $s->university ),
			'note'       => (string) $s->note,
			'unread'     => (int) $s->unread,
			'lastAt'     => (int) $s->last_msg_at,
			'lastSeen'   => (int) $s->last_seen,
			'preview'    => $prev,
			'disabled'   => (int) $s->disabled,
		);
	}
	return array(
		'chats'   => $out,
		'status'  => StudyHub_Settings::get( 'admin_status' ),
		'online'  => StudyHub_Settings::admin_online(),
		'pending' => StudyHub_DB::pending_reviews_count(),
	);
}

function studyhub_rest_messages( WP_REST_Request $req ) {
	studyhub_heartbeat();
	$id = (int) $req['id'];
	if ( ! StudyHub_DB::get_student( $id ) ) {
		return new WP_Error( 'not_found', 'not_found', array( 'status' => 404 ) );
	}
	$out = array();
	foreach ( StudyHub_DB::messages( $id, max( 0, (int) $req->get_param( 'after' ) ) ) as $m ) {
		$out[] = studyhub_message_shape( $m, 'admin' );
	}
	StudyHub_DB::mark_read( $id, 'admin' );
	$read = StudyHub_DB::last_read_ids( $id );
	return array(
		'messages' => $out,
		'readUpTo' => $read['student'],
	);
}

function studyhub_rest_send( WP_REST_Request $req ) {
	studyhub_heartbeat();
	$id = (int) $req['id'];
	if ( ! StudyHub_DB::get_student( $id ) ) {
		return new WP_Error( 'not_found', 'not_found', array( 'status' => 404 ) );
	}
	$text    = trim( mb_substr( sanitize_textarea_field( (string) $req->get_param( 'text' ) ), 0, 4000 ) );
	$file_id = 0;
	$files   = $req->get_file_params();
	if ( ! empty( $files['file'] ) ) {
		$file_id = StudyHub_Files::store_upload( $files['file'], $id, 'admin' );
		if ( is_wp_error( $file_id ) ) {
			return $file_id;
		}
	}
	if ( '' === $text && ! $file_id ) {
		return new WP_Error( 'empty', 'empty_message', array( 'status' => 400 ) );
	}
	$mid = StudyHub_DB::add_message( $id, 'admin', $file_id ? 'file' : 'text', $text, $file_id );
	studyhub_event( 'admin_message', array( 'student' => $id, 'message' => $mid ) );
	return array( 'message' => studyhub_message_shape( StudyHub_DB::get_message( $mid ), 'admin' ) );
}

function studyhub_rest_review_request( WP_REST_Request $req ) {
	$id = (int) $req['id'];
	if ( ! StudyHub_DB::get_student( $id ) ) {
		return new WP_Error( 'not_found', 'not_found', array( 'status' => 404 ) );
	}
	$mid = StudyHub_DB::add_message( $id, 'admin', 'review_request', '', 0, array( 'done' => false, 'service' => sanitize_key( (string) $req->get_param( 'service' ) ) ) );
	return array( 'message' => studyhub_message_shape( StudyHub_DB::get_message( $mid ), 'admin' ) );
}

function studyhub_rest_note( WP_REST_Request $req ) {
	StudyHub_DB::update_student( (int) $req['id'], array( 'note' => sanitize_textarea_field( (string) $req->get_param( 'note' ) ) ) );
	return array( 'ok' => true );
}

function studyhub_rest_status( WP_REST_Request $req ) {
	$status = (string) $req->get_param( 'status' );
	if ( ! in_array( $status, array( 'auto', 'online', 'offline' ), true ) ) {
		return new WP_Error( 'bad', 'bad_status', array( 'status' => 400 ) );
	}
	StudyHub_Settings::update( array( 'admin_status' => $status ) );
	studyhub_heartbeat();
	return array(
		'status' => $status,
		'online' => StudyHub_Settings::admin_online(),
	);
}

/* ---------- студенты (логины) ---------- */

function studyhub_rest_students() {
	$out = array();
	foreach ( StudyHub_DB::list_students() as $s ) {
		$out[] = array(
			'id'         => (int) $s->id,
			'alias'      => $s->alias,
			'login'      => $s->login,
			'university' => StudyHub_Settings::university_label( $s->university ),
			'note'       => (string) $s->note,
			'unread'     => (int) $s->unread,
			'created'    => (int) $s->created_at,
			'lastSeen'   => (int) $s->last_seen,
			'disabled'   => (int) $s->disabled,
		);
	}
	return array( 'students' => $out );
}

/** Готовый текст для студента: ссылка, логин, пароль. */
function studyhub_share_text( array $creds ) {
	$url   = isset( studyhub_routes()['app'] ) ? studyhub_url( 'app' ) : home_url( '/' );
	$brand = StudyHub_Settings::brand();
	$text  = '';
	foreach ( $creds as $c ) {
		$text .= "{$brand}\n{$url}\nЛогин: {$c['login']}\nПароль: {$c['password']}\n\n";
	}
	return trim( $text );
}

function studyhub_rest_student_create( WP_REST_Request $req ) {
	if ( ! class_exists( 'StudyHub_Auth' ) ) {
		return new WP_Error( 'no_client', 'Включите плагин «StudyHub Студент».', array( 'status' => 400 ) );
	}
	$count    = min( 50, max( 1, (int) $req->get_param( 'count' ) ) );
	$login_in = sanitize_user( (string) $req->get_param( 'login' ), true );
	$pass_in  = trim( (string) $req->get_param( 'password' ) );
	$note     = sanitize_text_field( (string) $req->get_param( 'note' ) );
	if ( $login_in ) {
		$count = 1;
		if ( StudyHub_DB::get_student_by_login( $login_in ) ) {
			return new WP_Error( 'taken', 'Логин «' . $login_in . '» уже занят.', array( 'status' => 409 ) );
		}
	}
	if ( '' !== $pass_in && strlen( $pass_in ) < 6 ) {
		return new WP_Error( 'short', 'Пароль — минимум 6 символов.', array( 'status' => 400 ) );
	}
	$creds = array();
	for ( $i = 0; $i < $count; $i++ ) {
		$login = $login_in ? $login_in : StudyHub_Auth::generate_login();
		$pass  = '' !== $pass_in ? $pass_in : StudyHub_Auth::generate_password();
		if ( StudyHub_DB::create_student( $login, $pass, $note ) ) {
			$creds[] = array( 'login' => $login, 'password' => $pass );
		}
	}
	return array(
		'creds' => $creds,
		'text'  => studyhub_share_text( $creds ),
	);
}

function studyhub_rest_student_action( WP_REST_Request $req ) {
	$id = (int) $req['id'];
	$s  = StudyHub_DB::get_student( $id );
	if ( ! $s ) {
		return new WP_Error( 'not_found', 'not_found', array( 'status' => 404 ) );
	}
	$do = sanitize_key( (string) $req->get_param( 'do' ) );
	if ( 'reset' === $do && class_exists( 'StudyHub_Auth' ) ) {
		$pass = StudyHub_Auth::generate_password();
		StudyHub_DB::update_student( $id, array( 'pass_hash' => wp_hash_password( $pass ) ) );
		StudyHub_Auth::destroy_sessions( $id );
		$creds = array( array( 'login' => $s->login, 'password' => $pass ) );
		return array( 'creds' => $creds, 'text' => studyhub_share_text( $creds ) );
	}
	if ( 'disable' === $do || 'enable' === $do ) {
		StudyHub_DB::update_student( $id, array( 'disabled' => 'disable' === $do ? 1 : 0 ) );
		if ( 'disable' === $do && class_exists( 'StudyHub_Auth' ) ) {
			StudyHub_Auth::destroy_sessions( $id );
		}
		return array( 'ok' => true );
	}
	if ( 'delete' === $do ) {
		StudyHub_DB::delete_student( $id );
		return array( 'ok' => true );
	}
	return new WP_Error( 'bad', 'bad_action', array( 'status' => 400 ) );
}

/* ---------- отзывы ---------- */

function studyhub_rest_reviews() {
	$out = array();
	foreach ( StudyHub_DB::reviews( 'all', 300 ) as $r ) {
		$s     = StudyHub_DB::get_student( $r->student_id );
		$row   = studyhub_review_shape( $r );
		$row['status'] = $r->status;
		$row['alias']  = $s ? $s->alias : '—';
		$out[] = $row;
	}
	return array(
		'reviews' => $out,
		'stats'   => StudyHub_DB::review_stats(),
	);
}

function studyhub_rest_review_action( WP_REST_Request $req ) {
	$id = (int) $req['id'];
	$do = sanitize_key( (string) $req->get_param( 'do' ) );
	if ( 'approve' === $do ) {
		StudyHub_DB::set_review_status( $id, 'approved' );
	} elseif ( 'hide' === $do ) {
		StudyHub_DB::set_review_status( $id, 'hidden' );
	} elseif ( 'delete' === $do ) {
		StudyHub_DB::delete_review( $id );
	} else {
		return new WP_Error( 'bad', 'bad_action', array( 'status' => 400 ) );
	}
	return array( 'ok' => true );
}

/* ---------- настройки ---------- */

function studyhub_rest_settings() {
	$s = StudyHub_Settings::all();
	unset( $s['services'], $s['universities'] );
	$plugins = array();
	foreach ( studyhub_plugins() as $p ) {
		$plugins[] = array( 'name' => $p['name'], 'ver' => $p['ver'], 'core' => $p['core'] );
	}
	return array(
		'settings'     => $s,
		'services'     => array_values( (array) StudyHub_Settings::get( 'services' ) ),
		'universities' => array_values( (array) StudyHub_Settings::get( 'universities' ) ),
		'plugins'      => $plugins,
		'urls'         => studyhub_base_config()['urls'],
	);
}

function studyhub_rest_settings_save( WP_REST_Request $req ) {
	$in   = (array) $req->get_param( 'settings' );
	$out  = array();
	$text = array( 'brand_name', 'brand_tagline_ru', 'brand_tagline_kk', 'hero_title_ru', 'hero_title_kk', 'contact_label' );
	$area = array( 'hero_text_ru', 'hero_text_kk', 'welcome_ru', 'welcome_kk' );
	$urls = array( 'brand_logo', 'hero_image', 'contact_url' );
	$bool = array( 'notify_email', 'reviews_automod', 'site_on_front', 'delete_on_uninstall' );
	foreach ( $text as $k ) {
		if ( array_key_exists( $k, $in ) ) {
			$out[ $k ] = sanitize_text_field( (string) $in[ $k ] );
		}
	}
	foreach ( $area as $k ) {
		if ( array_key_exists( $k, $in ) ) {
			$out[ $k ] = sanitize_textarea_field( (string) $in[ $k ] );
		}
	}
	foreach ( $urls as $k ) {
		if ( array_key_exists( $k, $in ) ) {
			$out[ $k ] = esc_url_raw( (string) $in[ $k ] );
		}
	}
	foreach ( $bool as $k ) {
		if ( array_key_exists( $k, $in ) ) {
			$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
	}
	if ( isset( $in['accent_color'] ) ) {
		$out['accent_color'] = sanitize_hex_color( (string) $in['accent_color'] ) ? sanitize_hex_color( (string) $in['accent_color'] ) : '#F26B1D';
	}
	if ( isset( $in['admin_status'] ) && in_array( $in['admin_status'], array( 'auto', 'online', 'offline' ), true ) ) {
		$out['admin_status'] = $in['admin_status'];
	}
	if ( isset( $in['fallback_minutes'] ) ) {
		$out['fallback_minutes'] = min( 240, max( 1, (int) $in['fallback_minutes'] ) );
	}
	if ( isset( $in['max_upload_mb'] ) ) {
		$out['max_upload_mb'] = min( 200, max( 1, (int) $in['max_upload_mb'] ) );
	}
	foreach ( array( 'app_slug' => 'app', 'panel_slug' => 'panel' ) as $k => $def ) {
		if ( isset( $in[ $k ] ) ) {
			$out[ $k ] = sanitize_title( (string) $in[ $k ] ) ? sanitize_title( (string) $in[ $k ] ) : $def;
		}
	}
	if ( isset( $out['app_slug'], $out['panel_slug'] ) && $out['app_slug'] === $out['panel_slug'] ) {
		return new WP_Error( 'slug', 'Адреса приложения и панели должны отличаться.', array( 'status' => 400 ) );
	}
	StudyHub_Settings::update( $out );
	return studyhub_rest_settings();
}

function studyhub_rest_catalog_save( WP_REST_Request $req ) {
	$services = array();
	foreach ( (array) $req->get_param( 'services' ) as $row ) {
		$row   = (array) $row;
		$title = sanitize_text_field( isset( $row['title_ru'] ) ? $row['title_ru'] : '' );
		if ( '' === $title ) {
			continue;
		}
		$id         = sanitize_key( isset( $row['id'] ) ? $row['id'] : '' );
		$services[] = array(
			'id'       => $id ? $id : 's' . substr( md5( $title . microtime() ), 0, 6 ),
			'icon'     => sanitize_key( isset( $row['icon'] ) ? $row['icon'] : 'book' ),
			'color'    => sanitize_key( isset( $row['color'] ) ? $row['color'] : 'peach' ),
			'title_ru' => $title,
			'title_kk' => sanitize_text_field( isset( $row['title_kk'] ) ? $row['title_kk'] : '' ),
			'desc_ru'  => sanitize_textarea_field( isset( $row['desc_ru'] ) ? $row['desc_ru'] : '' ),
			'desc_kk'  => sanitize_textarea_field( isset( $row['desc_kk'] ) ? $row['desc_kk'] : '' ),
			'price'    => sanitize_text_field( isset( $row['price'] ) ? $row['price'] : '' ),
		);
	}
	$unis = array();
	foreach ( (array) $req->get_param( 'universities' ) as $row ) {
		$row   = (array) $row;
		$short = sanitize_text_field( isset( $row['short'] ) ? $row['short'] : '' );
		if ( '' === $short ) {
			continue;
		}
		$id     = sanitize_key( isset( $row['id'] ) ? $row['id'] : '' );
		$unis[] = array(
			'id'      => $id ? $id : 'u' . substr( md5( $short . microtime() ), 0, 6 ),
			'short'   => $short,
			'name_ru' => sanitize_text_field( isset( $row['name_ru'] ) ? $row['name_ru'] : '' ),
			'name_kk' => sanitize_text_field( isset( $row['name_kk'] ) ? $row['name_kk'] : '' ),
			'logo'    => esc_url_raw( isset( $row['logo'] ) ? $row['logo'] : '' ),
		);
	}
	StudyHub_Settings::update( array( 'services' => $services, 'universities' => $unis ) );
	return studyhub_rest_settings();
}

/* ---------- разделы плагинов (например «ИИ-помощник») ---------- */

/**
 * Раздел панели от плагина:
 * 'ключ' => ['title' => …, 'icon' => 'sparkle', 'intro' => …, 'fields' => [[name, label, type, help, options]],
 *            'get' => callable(): array, 'save' => callable(array): array|WP_Error, 'actions' => [['id','label']]]
 */
function studyhub_panel_sections() {
	return (array) apply_filters( 'studyhub_panel_sections', array() );
}

function studyhub_rest_section( WP_REST_Request $req ) {
	$all = studyhub_panel_sections();
	$key = $req['key'];
	if ( empty( $all[ $key ]['get'] ) ) {
		return new WP_Error( 'not_found', 'not_found', array( 'status' => 404 ) );
	}
	return array( 'values' => call_user_func( $all[ $key ]['get'] ) );
}

function studyhub_rest_section_save( WP_REST_Request $req ) {
	$all = studyhub_panel_sections();
	$key = $req['key'];
	if ( empty( $all[ $key ]['save'] ) ) {
		return new WP_Error( 'not_found', 'not_found', array( 'status' => 404 ) );
	}
	$res = call_user_func( $all[ $key ]['save'], (array) $req->get_param( 'values' ), sanitize_key( (string) $req->get_param( 'action' ) ) );
	return is_wp_error( $res ) ? $res : $res;
}

/* ---------- картинки (логотип, баннер, значки вузов) — в медиатеку WordPress ---------- */
function studyhub_rest_upload_image( WP_REST_Request $req ) {
	$files = $req->get_file_params();
	if ( empty( $files['file'] ) ) {
		return new WP_Error( 'empty', 'Файл не выбран.', array( 'status' => 400 ) );
	}
	$type = wp_check_filetype( $files['file']['name'] );
	if ( empty( $type['type'] ) || 0 !== strpos( $type['type'], 'image/' ) ) {
		return new WP_Error( 'bad_type', 'Нужна картинка (PNG, JPG, WEBP, SVG нельзя).', array( 'status' => 415 ) );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$_FILES['studyhub_img'] = $files['file'];
	$id = media_handle_upload( 'studyhub_img', 0 );
	if ( is_wp_error( $id ) ) {
		return new WP_Error( 'upload', $id->get_error_message(), array( 'status' => 400 ) );
	}
	return array( 'url' => wp_get_attachment_url( $id ) );
}
