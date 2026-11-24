<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Раздел «ИИ-помощник» в панели StudyHub (а в wp-admin — только ссылка туда). */
class StudyHub_AI_Panel {

	public static function init() {
		add_filter( 'studyhub_panel_sections', array( __CLASS__, 'section' ) );
	}

	private static function mask( $k ) {
		return '…' . substr( $k, -4 );
	}

	public static function section( $sections ) {
		$sections['ai'] = array(
			'title'   => 'ИИ-помощник',
			'icon'    => 'sparkle',
			'intro'   => 'Google Gemini. Студенты видят вкладку «ИИ», а в чате — подсказку спросить ИИ, когда вы не в сети или долго не отвечаете.',
			'fields'  => array(
				array( 'name' => 'enabled', 'label' => 'Показывать ИИ-помощника студентам', 'type' => 'checkbox' ),
				array( 'name' => 'keys_info', 'label' => 'Сохранённые ключи', 'type' => 'info' ),
				array( 'name' => 'keys_new', 'label' => 'Добавить ключи Gemini', 'type' => 'textarea', 'rows' => 3, 'placeholder' => "AIza…\nAIza…", 'help' => 'По одному в строке. aistudio.google.com → Get API key (бесплатно). Несколько ключей с разных аккаунтов Google делят нагрузку по кругу.' ),
				array( 'name' => 'keys_clear', 'label' => 'Удалить все сохранённые ключи', 'type' => 'checkbox' ),
				array( 'name' => 'models', 'label' => 'Модели по порядку', 'type' => 'text', 'help' => 'Через запятую. Если первая недоступна или исчерпан лимит — берётся следующая.' ),
				array( 'name' => 'daily_limit', 'label' => 'Вопросов на студента в день', 'type' => 'number' ),
				array( 'name' => 'temperature', 'label' => 'Креативность (0–1)', 'type' => 'number' ),
				array( 'name' => 'knowledge', 'label' => 'База знаний', 'type' => 'textarea', 'rows' => 6, 'placeholder' => 'Например: требования КазГАСА к оформлению, сроки сессии, ответы на частые вопросы…', 'help' => 'Услуги, цены и вузы ИИ знает сам из настроек.' ),
				array( 'name' => 'extra', 'label' => 'Дополнительные инструкции', 'type' => 'textarea', 'rows' => 3 ),
				array( 'name' => 'last_error', 'label' => 'Последняя ошибка Google', 'type' => 'info' ),
			),
			'actions' => array( array( 'id' => 'test', 'label' => 'Сохранить и проверить' ) ),
			'get'     => array( __CLASS__, 'values' ),
			'save'    => array( __CLASS__, 'save' ),
		);
		return $sections;
	}

	public static function values() {
		$s    = StudyHub_AI::settings();
		$keys = StudyHub_AI::keys();
		return array(
			'enabled'     => (int) $s['enabled'],
			'keys_info'   => $keys ? count( $keys ) . ' шт.: ' . implode( ', ', array_map( array( __CLASS__, 'mask' ), $keys ) ) : 'Ключей нет — добавьте хотя бы один.',
			'keys_new'    => '',
			'keys_clear'  => 0,
			'models'      => $s['models'],
			'daily_limit' => (int) $s['daily_limit'],
			'temperature' => (float) $s['temperature'],
			'knowledge'   => $s['knowledge'],
			'extra'       => $s['extra'],
			'last_error'  => StudyHub_AI::last_error() ? StudyHub_AI::last_error() : 'нет',
		);
	}

	public static function save( array $v, $action ) {
		$models = implode( ', ', array_filter( array_map( 'trim', explode( ',', preg_replace( '/[^a-z0-9.,\-\s]/i', '', (string) ( isset( $v['models'] ) ? $v['models'] : '' ) ) ) ) ) );
		StudyHub_AI::save(
			array(
				'enabled'     => empty( $v['enabled'] ) ? 0 : 1,
				'models'      => $models ? $models : StudyHub_AI::defaults()['models'],
				'daily_limit' => min( 1000, max( 1, (int) ( isset( $v['daily_limit'] ) ? $v['daily_limit'] : 40 ) ) ),
				'temperature' => min( 1, max( 0, (float) ( isset( $v['temperature'] ) ? $v['temperature'] : 0.6 ) ) ),
				'knowledge'   => sanitize_textarea_field( isset( $v['knowledge'] ) ? $v['knowledge'] : '' ),
				'extra'       => sanitize_textarea_field( isset( $v['extra'] ) ? $v['extra'] : '' ),
			)
		);
		$keys = ! empty( $v['keys_clear'] ) ? array() : StudyHub_AI::keys();
		foreach ( preg_split( '/[\s,]+/', (string) ( isset( $v['keys_new'] ) ? $v['keys_new'] : '' ) ) as $k ) {
			$k = preg_replace( '/[^A-Za-z0-9_\-]/', '', $k );
			if ( strlen( $k ) >= 20 ) {
				$keys[] = $k;
			}
		}
		StudyHub_AI::set_keys( $keys );

		$notice = 'Сохранено.';
		$ok     = true;
		if ( 'test' === $action ) {
			$all = StudyHub_AI::keys();
			if ( ! $all ) {
				$notice = 'Добавьте хотя бы один ключ.';
				$ok     = false;
			} else {
				$lines = array();
				foreach ( $all as $k ) {
					$r       = StudyHub_AI::request( array( array( 'role' => 'user', 'parts' => array( array( 'text' => 'Ответь одним словом: работает?' ) ) ) ), 'Тестовый запрос.', $k );
					$lines[] = self::mask( $k ) . ': ' . ( is_wp_error( $r ) ? '✗ ' . $r->get_error_message() : '✓ ' . wp_html_excerpt( $r, 40, '…' ) );
					$ok      = $ok && ! is_wp_error( $r );
				}
				$notice = implode( "\n", $lines );
			}
		}
		return array(
			'values' => self::values(),
			'notice' => $notice,
			'ok'     => $ok,
		);
	}
}
