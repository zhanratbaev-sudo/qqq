<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
   КАБИНЕТ СТУДЕНТА — отдельная страница (по умолчанию /app/), как приложение.
   ============================================================ */

add_filter(
	'studyhub_routes',
	function ( $routes ) {
		$routes['app'] = array(
			'slug'   => StudyHub_Settings::slug( 'app_slug', 'app' ),
			'render' => 'studyhub_render_app',
		);
		return $routes;
	}
);

add_filter(
	'studyhub_pwa_apps',
	function ( $apps ) {
		$apps['app'] = array(
			'name'   => StudyHub_Settings::brand(),
			'start'  => studyhub_url( 'app' ),
			'assets' => array( studyhub_asset_url( 'app.js', STUDYHUB_CLIENT_FILE ) ),
		);
		return $apps;
	}
);

function studyhub_app_config() {
	$s       = StudyHub_Settings::all();
	$student = StudyHub_Auth::current_student();
	$config  = array_merge(
		studyhub_base_config(),
		array(
			'icon'            => studyhub_icon_url( 192 ),
			'welcome'         => array( 'ru' => $s['welcome_ru'], 'kk' => $s['welcome_kk'] ),
			'rest'            => esc_url_raw( rest_url( 'studyhub/v1/' ) ),
			'appUrl'          => studyhub_url( 'app' ),
			'swUrl'           => studyhub_sw_url(),
			'fallbackMinutes' => max( 1, (int) $s['fallback_minutes'] ),
			'maxUploadMb'     => (int) $s['max_upload_mb'],
			'accept'          => '.' . implode( ',.', array_keys( StudyHub_Files::allowed_types() ) ),
			'student'         => $student ? studyhub_student_shape( $student ) : null,
			'unread'          => $student ? StudyHub_DB::unread_for_student( $student->id ) : 0,
			'ai'              => array( 'enabled' => false ), // заполняет «StudyHub ИИ».
		)
	);
	return apply_filters( 'studyhub_app_config', $config, $student );
}

function studyhub_render_app() {
	$config = studyhub_app_config();
	include dirname( __DIR__ ) . '/templates/app.php';
}
