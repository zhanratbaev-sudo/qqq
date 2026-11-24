<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** REST: studyhub-ai/v1/chat — только для вошедшего студента, с дневным лимитом. */
class StudyHub_AI_Rest {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			'studyhub-ai/v1',
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'chat' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
			)
		);
	}

	public static function permission( WP_REST_Request $req ) {
		if ( '1' !== $req->get_header( 'x_studyhub' ) ) {
			return new WP_Error( 'forbidden', 'forbidden', array( 'status' => 403 ) );
		}
		if ( ! function_exists( 'studyhub_current_student' ) || ! studyhub_current_student() ) {
			return new WP_Error( 'not_logged_in', 'not_logged_in', array( 'status' => 401 ) );
		}
		return StudyHub_AI::on() ? true : new WP_Error( 'off', 'ai_off', array( 'status' => 503 ) );
	}

	public static function chat( WP_REST_Request $req ) {
		$s       = StudyHub_AI::settings();
		$student = studyhub_current_student();
		$key     = 'studyhub_ai_' . (int) $student->id . '_' . gmdate( 'Ymd' );
		$count   = (int) get_transient( $key );
		if ( $count >= max( 1, (int) $s['daily_limit'] ) ) {
			return new WP_Error( 'limit', 'daily_limit', array( 'status' => 429 ) );
		}
		$contents = StudyHub_AI::contents( (array) $req->get_param( 'messages' ) );
		if ( ! $contents || 'user' !== $contents[ count( $contents ) - 1 ]['role'] ) {
			return new WP_Error( 'empty', 'empty', array( 'status' => 400 ) );
		}
		$lang  = 'kk' === $req->get_param( 'lang' ) ? 'kk' : 'ru';
		$reply = StudyHub_AI::request( $contents, StudyHub_AI::system_prompt( $lang ) );
		if ( is_wp_error( $reply ) ) {
			return new WP_Error( 'ai_unavailable', 'ai_unavailable', array( 'status' => 502 ) );
		}
		set_transient( $key, $count + 1, DAY_IN_SECONDS );
		studyhub_event( 'ai_answer', array( 'student' => (int) $student->id ) );
		return array( 'reply' => $reply );
	}
}
