<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API: studyhub/v1
 */
class StudyHub_REST {

	const NS = 'studyhub/v1';

	public static function register_routes() {
		$student = array( __CLASS__, 'require_student' );
		$admin   = array( __CLASS__, 'require_admin' );
		$public  = '__return_true';

		register_rest_route( self::NS, '/login', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'login' ), 'permission_callback' => array( __CLASS__, 'require_header' ) ) );
		register_rest_route( self::NS, '/logout', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'logout' ), 'permission_callback' => array( __CLASS__, 'require_header' ) ) );
		register_rest_route(
			self::NS,
			'/me',
			array(
				array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'me' ), 'permission_callback' => $student ),
				array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'update_me' ), 'permission_callback' => $student ),
			)
		);
		register_rest_route( self::NS, '/reviews', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'public_reviews' ), 'permission_callback' => $public ) );
		register_rest_route( self::NS, '/reviews', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'submit_review' ), 'permission_callback' => $student ) );
		register_rest_route(
			self::NS,
			'/messages',
			array(
				array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'student_messages' ), 'permission_callback' => $student ),
				array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'student_send' ), 'permission_callback' => $student ),
			)
		);
		register_rest_route(
			self::NS,
			'/file/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'file' ),
				'permission_callback' => $public, // Ownership checked in callback.
			)
		);

		// Admin.
		register_rest_route( self::NS, '/admin/chats', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'admin_chats' ), 'permission_callback' => $admin ) );
		register_rest_route(
			self::NS,
			'/admin/chats/(?P<id>\d+)/messages',
			array(
				array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'admin_messages' ), 'permission_callback' => $admin ),
				array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'admin_send' ), 'permission_callback' => $admin ),
			)
		);
		register_rest_route( self::NS, '/admin/chats/(?P<id>\d+)/review-request', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'admin_review_request' ), 'permission_callback' => $admin ) );
		register_rest_route( self::NS, '/admin/chats/(?P<id>\d+)/note', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'admin_note' ), 'permission_callback' => $admin ) );
		register_rest_route( self::NS, '/admin/status', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'admin_status' ), 'permission_callback' => $admin ) );
	}

	/* ------------------------------------------------------------------
	 * Permissions
	 * ---------------------------------------------------------------- */

	/**
	 * Custom header forces a CORS preflight, so other sites cannot forge requests
	 * with the student's cookie (in addition to SameSite=Lax).
	 */
	public static function require_header( WP_REST_Request $req ) {
		if ( 'GET' === $req->get_method() ) {
			return true;
		}
		return '1' === $req->get_header( 'x_studyhub' );
	}

	public static function require_student( WP_REST_Request $req ) {
		if ( ! self::require_header( $req ) ) {
			return new WP_Error( 'forbidden', 'forbidden', array( 'status' => 403 ) );
		}
		if ( ! StudyHub_Auth::current_student() ) {
			return new WP_Error( 'not_logged_in', 'not_logged_in', array( 'status' => 401 ) );
		}
		return true;
	}

	public static function require_admin() {
		return current_user_can( 'manage_options' );
	}

	/* ------------------------------------------------------------------
	 * Shapes
	 * ---------------------------------------------------------------- */

	public static function student_shape( $s ) {
		return array(
			'alias'      => $s->alias,
			'university' => $s->university,
			'lang'       => $s->lang,
		);
	}

	private static function message_shape( $m, $for ) {
		$file = $m->file_id ? StudyHub_Files::public_shape( StudyHub_DB::get_file( $m->file_id ) ) : null;
		if ( $file ) {
			$file['url'] = 'admin' === $for
				? add_query_arg( array( 'action' => 'studyhub_file', 'id' => $file['id'], '_wpnonce' => wp_create_nonce( 'studyhub_file' ) ), admin_url( 'admin-post.php' ) )
				: rest_url( self::NS . '/file/' . $file['id'] );
		}
		$meta = $m->meta ? json_decode( $m->meta, true ) : null;
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
			'meta' => $meta,
			'time' => (int) $m->created_at,
		);
	}

	public static function review_shape( $r ) {
		return array(
			'id'         => (int) $r->id,
			'rating'     => (int) $r->rating,
			'text'       => $r->body,
			'university' => StudyHub_Settings::university_label( $r->university ),
			'service'    => $r->service,
			'time'       => (int) $r->created_at,
		);
	}

	/* ------------------------------------------------------------------
	 * Student endpoints
	 * ---------------------------------------------------------------- */

	public static function login( WP_REST_Request $req ) {
		$login    = sanitize_user( (string) $req->get_param( 'login' ), true );
		$password = (string) $req->get_param( 'password' );
		if ( '' === $login || '' === $password ) {
			return new WP_Error( 'bad_login', 'bad_credentials', array( 'status' => 401 ) );
		}
		$student = StudyHub_Auth::login( $login, $password );
		if ( is_wp_error( $student ) ) {
			return $student;
		}
		return self::me();
	}

	public static function logout() {
		StudyHub_Auth::logout();
		return array( 'ok' => true );
	}

	public static function me() {
		$s = StudyHub_Auth::current_student();
		return array(
			'student'     => self::student_shape( $s ),
			'unread'      => StudyHub_DB::unread_for_student( $s->id ),
			'adminOnline' => StudyHub_Settings::admin_online(),
			'now'         => time(),
		);
	}

	public static function update_me( WP_REST_Request $req ) {
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
		$fresh = StudyHub_DB::get_student( $s->id );
		return array( 'student' => self::student_shape( $fresh ) );
	}

	public static function student_messages( WP_REST_Request $req ) {
		$s     = StudyHub_Auth::current_student();
		$after = max( 0, (int) $req->get_param( 'after' ) );
		$rows  = StudyHub_DB::messages( $s->id, $after );
		$out   = array();
		foreach ( $rows as $m ) {
			$out[] = self::message_shape( $m, 'student' );
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

	public static function student_send( WP_REST_Request $req ) {
		$s    = StudyHub_Auth::current_student();
		$text = self::clean_text( $req->get_param( 'text' ) );

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

		$meta    = null;
		$service = sanitize_key( (string) $req->get_param( 'service' ) );
		if ( $service ) {
			$meta = array( 'service' => $service );
		}

		$id = StudyHub_DB::add_message( $s->id, 'student', $file_id ? 'file' : 'text', $text, $file_id, $meta );
		self::maybe_notify_admin( $s, $text );
		return array( 'message' => self::message_shape( StudyHub_DB::get_message( $id ), 'student' ) );
	}

	public static function public_reviews() {
		$out = array();
		foreach ( StudyHub_DB::reviews( 'approved', 30 ) as $r ) {
			$out[] = self::review_shape( $r );
		}
		return array(
			'reviews' => $out,
			'stats'   => StudyHub_DB::review_stats(),
		);
	}

	public static function submit_review( WP_REST_Request $req ) {
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
		$text   = self::anonymize_review( (string) $req->get_param( 'text' ) );
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
		// Show the review in the admin's chat as a student message.
		StudyHub_DB::add_message( $s->id, 'student', 'review', $text, 0, array( 'rating' => $rating, 'review' => $review_id ) );
		return array(
			'ok'     => true,
			'status' => $status,
		);
	}

	public static function file( WP_REST_Request $req ) {
		$file = StudyHub_DB::get_file( (int) $req['id'] );
		if ( ! $file ) {
			return new WP_Error( 'not_found', 'not_found', array( 'status' => 404 ) );
		}
		$s = StudyHub_Auth::current_student();
		if ( ! ( $s && (int) $s->id === (int) $file->student_id ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden', 'forbidden', array( 'status' => 403 ) );
		}
		StudyHub_Files::stream( $file, (bool) $req->get_param( 'download' ) );
	}

	/* ------------------------------------------------------------------
	 * Admin endpoints
	 * ---------------------------------------------------------------- */

	private static function heartbeat() {
		update_option( 'studyhub_admin_heartbeat', time(), false );
	}

	public static function admin_chats() {
		self::heartbeat();
		$out = array();
		foreach ( StudyHub_DB::list_students() as $s ) {
			$last = $s->last_msg_id ? StudyHub_DB::get_message( $s->last_msg_id ) : null;
			$prev = '';
			if ( $last ) {
				$prev = $last->body ? $last->body : ( $last->file_id ? '📎' : '' );
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
			'chats'  => $out,
			'status' => StudyHub_Settings::get( 'admin_status' ),
			'online' => StudyHub_Settings::admin_online(),
		);
	}

	public static function admin_messages( WP_REST_Request $req ) {
		self::heartbeat();
		$id = (int) $req['id'];
		if ( ! StudyHub_DB::get_student( $id ) ) {
			return new WP_Error( 'not_found', 'not_found', array( 'status' => 404 ) );
		}
		$after = max( 0, (int) $req->get_param( 'after' ) );
		$out   = array();
		foreach ( StudyHub_DB::messages( $id, $after ) as $m ) {
			$out[] = self::message_shape( $m, 'admin' );
		}
		StudyHub_DB::mark_read( $id, 'admin' );
		$read = StudyHub_DB::last_read_ids( $id );
		return array(
			'messages' => $out,
			'readUpTo' => $read['student'],
		);
	}

	public static function admin_send( WP_REST_Request $req ) {
		self::heartbeat();
		$id = (int) $req['id'];
		if ( ! StudyHub_DB::get_student( $id ) ) {
			return new WP_Error( 'not_found', 'not_found', array( 'status' => 404 ) );
		}
		$text    = self::clean_text( $req->get_param( 'text' ) );
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
		return array( 'message' => self::message_shape( StudyHub_DB::get_message( $mid ), 'admin' ) );
	}

	public static function admin_review_request( WP_REST_Request $req ) {
		$id = (int) $req['id'];
		if ( ! StudyHub_DB::get_student( $id ) ) {
			return new WP_Error( 'not_found', 'not_found', array( 'status' => 404 ) );
		}
		$service = sanitize_key( (string) $req->get_param( 'service' ) );
		$mid     = StudyHub_DB::add_message( $id, 'admin', 'review_request', '', 0, array( 'done' => false, 'service' => $service ) );
		return array( 'message' => self::message_shape( StudyHub_DB::get_message( $mid ), 'admin' ) );
	}

	public static function admin_note( WP_REST_Request $req ) {
		$id = (int) $req['id'];
		StudyHub_DB::update_student( $id, array( 'note' => sanitize_textarea_field( (string) $req->get_param( 'note' ) ) ) );
		return array( 'ok' => true );
	}

	public static function admin_status( WP_REST_Request $req ) {
		$status = (string) $req->get_param( 'status' );
		if ( ! in_array( $status, array( 'auto', 'online', 'offline' ), true ) ) {
			return new WP_Error( 'bad', 'bad_status', array( 'status' => 400 ) );
		}
		StudyHub_Settings::update( array( 'admin_status' => $status ) );
		self::heartbeat();
		return array(
			'status' => $status,
			'online' => StudyHub_Settings::admin_online(),
		);
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------- */

	private static function clean_text( $text ) {
		$text = sanitize_textarea_field( (string) $text );
		return trim( mb_substr( $text, 0, 4000 ) );
	}

	/**
	 * Remove anything that could de-anonymize a reviewer: links, emails, phones, @handles.
	 */
	public static function anonymize_review( $text ) {
		$text = sanitize_textarea_field( $text );
		$text = preg_replace( '~https?://\S+|www\.\S+~iu', '', $text );
		$text = preg_replace( '~[\w.+-]+@[\w-]+\.[\w.]+~u', '', $text );
		$text = preg_replace( '~(?<!\w)@[\w.]{2,}~u', '', $text );
		$text = preg_replace( '~\+?\d[\d\s()\-]{7,}\d~u', '', $text );
		$text = preg_replace( '~[ \t]{2,}~u', ' ', $text );
		return trim( mb_substr( $text, 0, 600 ) );
	}

	private static function maybe_notify_admin( $student, $text ) {
		if ( ! StudyHub_Settings::get( 'notify_email' ) ) {
			return;
		}
		$key = 'studyhub_notified_' . $student->id;
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, 10 * MINUTE_IN_SECONDS );
		$brand = StudyHub_Settings::brand();
		wp_mail(
			get_option( 'admin_email' ),
			sprintf( '[%s] Новое сообщение от %s', $brand, $student->alias ),
			wp_html_excerpt( $text, 300, '…' ) . "\n\n" . admin_url( 'admin.php?page=studyhub&chat=' . $student->id )
		);
	}
}
