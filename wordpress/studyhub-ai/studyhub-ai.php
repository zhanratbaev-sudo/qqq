<?php
/**
 * Plugin Name: StudyHub ИИ
 * Description: ИИ-помощник для студентов (Google Gemini): вкладка «ИИ» в кабинете, подсказка в чате, когда администратор не в сети или долго не отвечает. Несколько ключей по кругу, запасные модели, база знаний. Настройки — в панели StudyHub. Нужны «StudyHub Ядро» и «StudyHub Студент».
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: studyhub-core
 * Text Domain: studyhub
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'STUDYHUB_AI_FILE', __FILE__ );
define( 'STUDYHUB_AI_VER', '2.0.0' );

require_once __DIR__ . '/includes/class-ai.php';
require_once __DIR__ . '/includes/class-rest.php';
require_once __DIR__ . '/includes/class-panel.php';

add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'STUDYHUB_VER' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p><b>StudyHub ИИ</b> работает вместе с плагином <b>StudyHub Ядро</b>. Включите его.</p></div>';
				}
			);
			return;
		}
		studyhub_register_plugin( STUDYHUB_AI_FILE, array( 'name' => 'StudyHub ИИ', 'version' => STUDYHUB_AI_VER ) );
		StudyHub_AI_Rest::init();
		StudyHub_AI_Panel::init();

		/* сообщаем сайту и кабинету, что помощник есть */
		add_filter( 'studyhub_ai_active', array( 'StudyHub_AI', 'on' ) );
		add_filter(
			'studyhub_app_config',
			function ( $config ) {
				if ( StudyHub_AI::on() ) {
					$config['ai'] = array(
						'enabled'  => true,
						'endpoint' => esc_url_raw( rest_url( 'studyhub-ai/v1/chat' ) ),
					);
				}
				return $config;
			}
		);
	},
	20
);
