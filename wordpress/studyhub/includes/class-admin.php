<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress admin screens: chats, students (credentials), reviews, services, settings.
 * The admin logs in with their normal WordPress login/password.
 */
class StudyHub_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_studyhub_student', array( __CLASS__, 'handle_student' ) );
		add_action( 'admin_post_studyhub_review', array( __CLASS__, 'handle_review' ) );
		add_action( 'admin_post_studyhub_settings', array( __CLASS__, 'handle_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( STUDYHUB_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=studyhub-settings' ) ) . '">Настройки</a>' );
		return $links;
	}

	public static function menu() {
		$unread  = StudyHub_DB::unread_for_admin();
		$pending = StudyHub_DB::pending_reviews_count();
		$badge   = $unread ? ' <span class="awaiting-mod"><span class="pending-count">' . (int) $unread . '</span></span>' : '';
		$rbadge  = $pending ? ' <span class="awaiting-mod"><span class="pending-count">' . (int) $pending . '</span></span>' : '';

		add_menu_page( 'StudyHub', 'StudyHub' . $badge, self::CAP, 'studyhub', array( __CLASS__, 'page_chats' ), 'dashicons-format-chat', 3 );
		add_submenu_page( 'studyhub', 'Чаты', 'Чаты' . $badge, self::CAP, 'studyhub', array( __CLASS__, 'page_chats' ) );
		add_submenu_page( 'studyhub', 'Студенты', 'Студенты (логины)', self::CAP, 'studyhub-students', array( __CLASS__, 'page_students' ) );
		add_submenu_page( 'studyhub', 'Отзывы', 'Отзывы' . $rbadge, self::CAP, 'studyhub-reviews', array( __CLASS__, 'page_reviews' ) );
		add_submenu_page( 'studyhub', 'Услуги и вузы', 'Услуги и вузы', self::CAP, 'studyhub-services', array( __CLASS__, 'page_services' ) );
		add_submenu_page( 'studyhub', 'Настройки', 'Настройки', self::CAP, 'studyhub-settings', array( __CLASS__, 'page_settings' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'studyhub' ) ) {
			return;
		}
		wp_enqueue_style( 'studyhub-admin', STUDYHUB_URL . 'assets/admin.css', array(), STUDYHUB_VERSION );

		if ( 'toplevel_page_studyhub' === $hook ) {
			wp_enqueue_script( 'studyhub-admin-chat', STUDYHUB_URL . 'assets/admin-chat.js', array(), STUDYHUB_VERSION, true );
			$services = array();
			foreach ( (array) StudyHub_Settings::get( 'services' ) as $svc ) {
				$services[] = array( 'id' => $svc['id'], 'title' => $svc['title_ru'] );
			}
			wp_localize_script(
				'studyhub-admin-chat',
				'STUDYHUB_ADMIN',
				array(
					'rest'     => esc_url_raw( rest_url( StudyHub_REST::NS . '/' ) ),
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'brand'    => StudyHub_Settings::brand(),
					'services' => $services,
					'open'     => isset( $_GET['chat'] ) ? (int) $_GET['chat'] : 0, // phpcs:ignore
					'students' => admin_url( 'admin.php?page=studyhub-students' ),
					'appUrl'   => StudyHub_App::app_url(),
				)
			);
		}
		if ( false !== strpos( (string) $hook, 'studyhub-settings' ) || false !== strpos( (string) $hook, 'studyhub-services' ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'studyhub-admin-settings', STUDYHUB_URL . 'assets/admin-settings.js', array( 'jquery' ), STUDYHUB_VERSION, true );
		}
	}

	private static function check( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden', 403 );
		}
		check_admin_referer( $action );
	}

	private static function notice() {
		$key = 'studyhub_notice_' . get_current_user_id();
		$msg = get_transient( $key );
		if ( $msg ) {
			delete_transient( $key );
			echo wp_kses_post( $msg );
		}
	}

	private static function set_notice( $html ) {
		set_transient( 'studyhub_notice_' . get_current_user_id(), $html, 120 );
	}

	private static function header( $title, $subtitle = '' ) {
		echo '<div class="wrap studyhub-wrap"><div class="sh-head"><div class="sh-logo">' . esc_html( mb_substr( StudyHub_Settings::brand(), 0, 1 ) ) . '</div><div><h1>' . esc_html( $title ) . '</h1>';
		if ( $subtitle ) {
			echo '<p class="sh-sub">' . esc_html( $subtitle ) . '</p>';
		}
		echo '</div><a class="sh-app-link" target="_blank" rel="noopener" href="' . esc_url( StudyHub_App::app_url() ) . '">Открыть приложение ↗</a></div>';
		self::notice();
	}

	/* ------------------------------------------------------------------
	 * Chats
	 * ---------------------------------------------------------------- */

	public static function page_chats() {
		self::header( 'Чаты', 'Студенты видят только название «' . StudyHub_Settings::brand() . '», ваше имя скрыто.' );
		echo '<div id="studyhub-chat" class="sh-chat"><div class="sh-loading">Загрузка…</div></div></div>';
	}

	/* ------------------------------------------------------------------
	 * Students
	 * ---------------------------------------------------------------- */

	public static function page_students() {
		self::header( 'Студенты', 'Регистрации нет — вы выдаёте логин и пароль сами. Студенты не видят ничьих имён.' );
		$action = esc_url( admin_url( 'admin-post.php' ) );
		?>
		<div class="sh-card">
			<h2>Выдать доступ</h2>
			<form method="post" action="<?php echo $action; // phpcs:ignore ?>" class="sh-form-row">
				<?php wp_nonce_field( 'studyhub_student' ); ?>
				<input type="hidden" name="action" value="studyhub_student">
				<input type="hidden" name="do" value="create">
				<label>Логин<input type="text" name="login" placeholder="авто (st12345)" pattern="[A-Za-z0-9_.\-]{3,40}"></label>
				<label>Пароль<input type="text" name="password" placeholder="авто" minlength="6"></label>
				<label class="grow">Заметка (видите только вы)<input type="text" name="note" placeholder="например: макет, 2 курс"></label>
				<label class="sh-count">Кол-во<input type="number" name="count" value="1" min="1" max="50"></label>
				<button class="button button-primary sh-btn">Создать</button>
			</form>
		</div>
		<?php
		$students = StudyHub_DB::list_students();
		echo '<div class="sh-card"><h2>Все студенты <span class="sh-muted">(' . count( $students ) . ')</span></h2>';
		if ( ! $students ) {
			echo '<p class="sh-muted">Пока никого. Создайте первый логин выше.</p></div></div>';
			return;
		}
		echo '<table class="widefat striped sh-table"><thead><tr><th>Аноним</th><th>Логин</th><th>Вуз</th><th>Заметка</th><th>Был(а)</th><th>Сообщений не прочитано</th><th></th></tr></thead><tbody>';
		foreach ( $students as $s ) {
			$seen = $s->last_seen ? human_time_diff( $s->last_seen ) . ' назад' : '—';
			echo '<tr' . ( $s->disabled ? ' class="sh-disabled"' : '' ) . '>';
			echo '<td><strong>' . esc_html( $s->alias ) . '</strong></td>';
			echo '<td><code>' . esc_html( $s->login ) . '</code></td>';
			echo '<td>' . esc_html( StudyHub_Settings::university_label( $s->university ) ?: '—' ) . '</td>';
			echo '<td>' . esc_html( (string) $s->note ) . '</td>';
			echo '<td>' . esc_html( $seen ) . '</td>';
			echo '<td>' . ( $s->unread ? '<span class="sh-badge">' . (int) $s->unread . '</span>' : '0' ) . '</td>';
			echo '<td class="sh-actions">';
			echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=studyhub&chat=' . $s->id ) ) . '">Чат</a> ';
			self::student_button( $s->id, 'reset', 'Новый пароль', 'Сгенерировать новый пароль? Старый перестанет работать.' );
			self::student_button( $s->id, $s->disabled ? 'enable' : 'disable', $s->disabled ? 'Включить' : 'Заблокировать' );
			self::student_button( $s->id, 'delete', 'Удалить', 'Удалить студента вместе с перепиской и файлами? Это необратимо.', 'sh-danger' );
			echo '</td></tr>';
		}
		echo '</tbody></table></div></div>';
	}

	private static function student_button( $id, $do, $label, $confirm = '', $class = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="sh-inline"' . ( $confirm ? ' onsubmit="return confirm(\'' . esc_js( $confirm ) . '\')"' : '' ) . '>';
		wp_nonce_field( 'studyhub_student' );
		echo '<input type="hidden" name="action" value="studyhub_student"><input type="hidden" name="do" value="' . esc_attr( $do ) . '"><input type="hidden" name="id" value="' . (int) $id . '">';
		echo '<button class="button ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form> ';
	}

	private static function credentials_box( array $creds ) {
		$url   = StudyHub_App::app_url();
		$brand = StudyHub_Settings::brand();
		$text  = '';
		foreach ( $creds as $c ) {
			$text .= "{$brand}\n{$url}\nЛогин: {$c[0]}\nПароль: {$c[1]}\n\n";
		}
		$html  = '<div class="notice notice-success sh-creds"><p><strong>Готово! Скопируйте и отправьте студенту.</strong> Пароль показывается только один раз.</p>';
		$html .= '<textarea readonly rows="' . min( 16, 5 * count( $creds ) ) . '" onclick="this.select()">' . esc_textarea( trim( $text ) ) . '</textarea>';
		$html .= '<p><button type="button" class="button button-primary" onclick="navigator.clipboard.writeText(this.closest(\'.sh-creds\').querySelector(\'textarea\').value);this.textContent=\'Скопировано ✓\'">Копировать</button></p></div>';
		return $html;
	}

	public static function handle_student() {
		self::check( 'studyhub_student' );
		$do       = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
		$id       = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$redirect = admin_url( 'admin.php?page=studyhub-students' );

		if ( 'create' === $do ) {
			$count    = isset( $_POST['count'] ) ? min( 50, max( 1, (int) $_POST['count'] ) ) : 1;
			$login_in = isset( $_POST['login'] ) ? sanitize_user( wp_unslash( $_POST['login'] ), true ) : '';
			$pass_in  = isset( $_POST['password'] ) ? trim( (string) wp_unslash( $_POST['password'] ) ) : ''; // phpcs:ignore
			$note     = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
			if ( $login_in ) {
				$count = 1;
			}
			$creds = array();
			for ( $i = 0; $i < $count; $i++ ) {
				$login = $login_in ? $login_in : StudyHub_Auth::generate_login();
				if ( StudyHub_DB::get_student_by_login( $login ) ) {
					self::set_notice( '<div class="notice notice-error"><p>Логин «' . esc_html( $login ) . '» уже занят.</p></div>' );
					wp_safe_redirect( $redirect );
					exit;
				}
				$pass = ( $pass_in && strlen( $pass_in ) >= 6 ) ? $pass_in : StudyHub_Auth::generate_password();
				if ( StudyHub_DB::create_student( $login, $pass, $note ) ) {
					$creds[] = array( $login, $pass );
				}
			}
			self::set_notice( self::credentials_box( $creds ) );
		} elseif ( $id && ( $s = StudyHub_DB::get_student( $id ) ) ) { // phpcs:ignore
			if ( 'reset' === $do ) {
				$pass = StudyHub_Auth::generate_password();
				StudyHub_DB::update_student( $id, array( 'pass_hash' => wp_hash_password( $pass ) ) );
				StudyHub_Auth::destroy_sessions( $id );
				self::set_notice( self::credentials_box( array( array( $s->login, $pass ) ) ) );
			} elseif ( 'disable' === $do || 'enable' === $do ) {
				StudyHub_DB::update_student( $id, array( 'disabled' => 'disable' === $do ? 1 : 0 ) );
				if ( 'disable' === $do ) {
					StudyHub_Auth::destroy_sessions( $id );
				}
			} elseif ( 'delete' === $do ) {
				StudyHub_DB::delete_student( $id );
				self::set_notice( '<div class="notice notice-success"><p>Студент удалён.</p></div>' );
			}
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Reviews
	 * ---------------------------------------------------------------- */

	public static function page_reviews() {
		self::header( 'Отзывы', 'Отзыв может оставить только студент, которому вы отправили запрос в чате. Имена, фото и контакты не публикуются.' );
		$reviews = StudyHub_DB::reviews( 'all', 200 );
		$stats   = StudyHub_DB::review_stats();
		echo '<div class="sh-card"><p>Опубликовано: <strong>' . (int) $stats['count'] . '</strong> · Средняя оценка: <strong>' . esc_html( $stats['average'] ) . '</strong> ★</p>';
		echo '<p class="sh-muted">Как получить отзыв: откройте чат со студентом → кнопка «⭐ Попросить отзыв».</p></div>';
		if ( ! $reviews ) {
			echo '<div class="sh-card"><p class="sh-muted">Отзывов пока нет.</p></div></div>';
			return;
		}
		$labels = array(
			'pending'  => 'На модерации',
			'approved' => 'Опубликован',
			'hidden'   => 'Скрыт',
		);
		echo '<div class="sh-reviews">';
		foreach ( $reviews as $r ) {
			$student = StudyHub_DB::get_student( $r->student_id );
			echo '<div class="sh-card sh-review sh-status-' . esc_attr( $r->status ) . '">';
			echo '<div class="sh-review-top"><span class="sh-stars">' . str_repeat( '★', (int) $r->rating ) . '<i>' . str_repeat( '★', 5 - (int) $r->rating ) . '</i></span><span class="sh-pill">' . esc_html( $labels[ $r->status ] ?? $r->status ) . '</span></div>';
			echo '<p class="sh-review-text">' . esc_html( $r->body ) . '</p>';
			echo '<p class="sh-muted">' . esc_html( ( $student ? $student->alias : 'удалён' ) . ' · ' . ( StudyHub_Settings::university_label( $r->university ) ?: 'вуз не указан' ) . ' · ' . wp_date( 'd.m.Y', $r->created_at ) ) . '</p>';
			echo '<div class="sh-actions">';
			if ( 'approved' !== $r->status ) {
				self::review_button( $r->id, 'approve', 'Опубликовать', 'button-primary' );
			}
			if ( 'hidden' !== $r->status ) {
				self::review_button( $r->id, 'hide', 'Скрыть' );
			}
			self::review_button( $r->id, 'delete', 'Удалить', 'sh-danger' );
			echo '</div></div>';
		}
		echo '</div></div>';
	}

	private static function review_button( $id, $do, $label, $class = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="sh-inline">';
		wp_nonce_field( 'studyhub_review' );
		echo '<input type="hidden" name="action" value="studyhub_review"><input type="hidden" name="do" value="' . esc_attr( $do ) . '"><input type="hidden" name="id" value="' . (int) $id . '">';
		echo '<button class="button ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form> ';
	}

	public static function handle_review() {
		self::check( 'studyhub_review' );
		$do = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( 'approve' === $do ) {
			StudyHub_DB::set_review_status( $id, 'approved' );
		} elseif ( 'hide' === $do ) {
			StudyHub_DB::set_review_status( $id, 'hidden' );
		} elseif ( 'delete' === $do ) {
			StudyHub_DB::delete_review( $id );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=studyhub-reviews' ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Services & universities
	 * ---------------------------------------------------------------- */

	public static function page_services() {
		self::header( 'Услуги и университеты', 'Тексты на русском и казахском. Пустая цена — цена не показывается, обсуждается в чате.' );
		$s = StudyHub_Settings::all();
		$icons  = array( 'book' => 'Книга', 'cube' => 'Макет (куб)', 'pencil' => 'Карандаш', 'ruler' => 'Линейка/чертёж', 'building' => 'Здание', 'laptop' => 'Ноутбук', 'palette' => 'Палитра', 'doc' => 'Документ' );
		$colors = array( 'peach' => 'Персиковый', 'lilac' => 'Сиреневый', 'mint' => 'Мятный', 'sky' => 'Голубой', 'sand' => 'Песочный', 'rose' => 'Розовый' );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'studyhub_settings' ); ?>
			<input type="hidden" name="action" value="studyhub_settings">
			<input type="hidden" name="section" value="services">

			<div class="sh-card">
				<h2>Услуги</h2>
				<div class="sh-repeat" data-repeat="services">
					<?php foreach ( array_values( (array) $s['services'] ) as $i => $svc ) : ?>
						<?php self::service_row( $i, $svc, $icons, $colors ); ?>
					<?php endforeach; ?>
				</div>
				<template id="sh-tpl-services"><?php self::service_row( '__i__', array(), $icons, $colors ); ?></template>
				<button type="button" class="button sh-add" data-target="services">+ Добавить услугу</button>
			</div>

			<div class="sh-card">
				<h2>Университеты</h2>
				<div class="sh-repeat" data-repeat="universities">
					<?php foreach ( array_values( (array) $s['universities'] ) as $i => $u ) : ?>
						<?php self::uni_row( $i, $u ); ?>
					<?php endforeach; ?>
				</div>
				<template id="sh-tpl-universities"><?php self::uni_row( '__i__', array() ); ?></template>
				<button type="button" class="button sh-add" data-target="universities">+ Добавить университет</button>
			</div>
			<p><button class="button button-primary button-hero">Сохранить</button></p>
		</form></div>
		<?php
	}

	private static function service_row( $i, $svc, $icons, $colors ) {
		$v = function ( $k ) use ( $svc ) {
			return isset( $svc[ $k ] ) ? $svc[ $k ] : '';
		};
		$n = 'services[' . $i . ']';
		?>
		<div class="sh-row">
			<div class="sh-grid">
				<label>Название (рус)<input type="text" name="<?php echo esc_attr( $n ); ?>[title_ru]" value="<?php echo esc_attr( $v( 'title_ru' ) ); ?>" required></label>
				<label>Атауы (қаз)<input type="text" name="<?php echo esc_attr( $n ); ?>[title_kk]" value="<?php echo esc_attr( $v( 'title_kk' ) ); ?>"></label>
				<label>Описание (рус)<textarea name="<?php echo esc_attr( $n ); ?>[desc_ru]" rows="2"><?php echo esc_textarea( $v( 'desc_ru' ) ); ?></textarea></label>
				<label>Сипаттамасы (қаз)<textarea name="<?php echo esc_attr( $n ); ?>[desc_kk]" rows="2"><?php echo esc_textarea( $v( 'desc_kk' ) ); ?></textarea></label>
				<label>Цена (необязательно)<input type="text" name="<?php echo esc_attr( $n ); ?>[price]" value="<?php echo esc_attr( $v( 'price' ) ); ?>" placeholder="напр. от 5 000 ₸"></label>
				<label>Иконка<select name="<?php echo esc_attr( $n ); ?>[icon]">
					<?php foreach ( $icons as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $v( 'icon' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select></label>
				<label>Цвет карточки<select name="<?php echo esc_attr( $n ); ?>[color]">
					<?php foreach ( $colors as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $v( 'color' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select></label>
				<input type="hidden" name="<?php echo esc_attr( $n ); ?>[id]" value="<?php echo esc_attr( $v( 'id' ) ); ?>">
			</div>
			<button type="button" class="button-link sh-remove">Удалить</button>
		</div>
		<?php
	}

	private static function uni_row( $i, $u ) {
		$v = function ( $k ) use ( $u ) {
			return isset( $u[ $k ] ) ? $u[ $k ] : '';
		};
		$n = 'universities[' . $i . ']';
		?>
		<div class="sh-row">
			<div class="sh-grid">
				<label>Коротко (на значке)<input type="text" name="<?php echo esc_attr( $n ); ?>[short]" value="<?php echo esc_attr( $v( 'short' ) ); ?>" required maxlength="10"></label>
				<label>Полное название (рус)<input type="text" name="<?php echo esc_attr( $n ); ?>[name_ru]" value="<?php echo esc_attr( $v( 'name_ru' ) ); ?>"></label>
				<label>Толық атауы (қаз)<input type="text" name="<?php echo esc_attr( $n ); ?>[name_kk]" value="<?php echo esc_attr( $v( 'name_kk' ) ); ?>"></label>
				<label>Логотип (URL)<span class="sh-media"><input type="url" name="<?php echo esc_attr( $n ); ?>[logo]" value="<?php echo esc_attr( $v( 'logo' ) ); ?>"><button type="button" class="button sh-pick">Выбрать</button></span></label>
				<input type="hidden" name="<?php echo esc_attr( $n ); ?>[id]" value="<?php echo esc_attr( $v( 'id' ) ); ?>">
			</div>
			<button type="button" class="button-link sh-remove">Удалить</button>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ---------------------------------------------------------------- */

	public static function page_settings() {
		self::header( 'Настройки' );
		$s = StudyHub_Settings::all();
		$f = function ( $key, $label, $type = 'text', $extra = '' ) use ( $s ) {
			echo '<label>' . esc_html( $label );
			if ( 'textarea' === $type ) {
				echo '<textarea name="s[' . esc_attr( $key ) . ']" rows="3">' . esc_textarea( (string) $s[ $key ] ) . '</textarea>';
			} elseif ( 'checkbox' === $type ) {
				echo '<span class="sh-check"><input type="checkbox" name="s[' . esc_attr( $key ) . ']" value="1" ' . checked( ! empty( $s[ $key ] ), true, false ) . '> ' . wp_kses_post( $extra ) . '</span>';
			} elseif ( 'media' === $type ) {
				echo '<span class="sh-media"><input type="url" name="s[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $s[ $key ] ) . '"><button type="button" class="button sh-pick">Выбрать</button></span>';
			} else {
				echo '<input type="' . esc_attr( $type ) . '" name="s[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $s[ $key ] ) . '" ' . $extra . '>'; // phpcs:ignore
			}
			echo '</label>';
		};
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'studyhub_settings' ); ?>
			<input type="hidden" name="action" value="studyhub_settings">
			<input type="hidden" name="section" value="general">

			<div class="sh-card"><h2>Бренд (вместо вашего имени)</h2><div class="sh-grid">
				<?php
				$f( 'brand_name', 'Название сайта/приложения' );
				$f( 'accent_color', 'Акцентный цвет', 'color' );
				$f( 'brand_tagline_ru', 'Подзаголовок (рус)' );
				$f( 'brand_tagline_kk', 'Подзаголовок (қаз)' );
				$f( 'brand_logo', 'Логотип / иконка приложения (квадрат PNG 512×512)', 'media' );
				?>
			</div></div>

			<div class="sh-card"><h2>Главный баннер</h2><div class="sh-grid">
				<?php
				$f( 'hero_title_ru', 'Заголовок (рус)' );
				$f( 'hero_title_kk', 'Тақырып (қаз)' );
				$f( 'hero_text_ru', 'Текст (рус)', 'textarea' );
				$f( 'hero_text_kk', 'Мәтін (қаз)', 'textarea' );
				$f( 'hero_image', 'Фото для баннера (необязательно)', 'media' );
				?>
			</div></div>

			<div class="sh-card"><h2>Чат и ИИ-помощник</h2><div class="sh-grid">
				<label>Мой статус
					<select name="s[admin_status]">
						<option value="auto" <?php selected( $s['admin_status'], 'auto' ); ?>>Авто (в сети, пока открыта страница «Чаты»)</option>
						<option value="online" <?php selected( $s['admin_status'], 'online' ); ?>>Всегда в сети</option>
						<option value="offline" <?php selected( $s['admin_status'], 'offline' ); ?>>Не в сети — сразу предлагать ИИ</option>
					</select>
				</label>
				<?php
				$f( 'fallback_minutes', 'Предлагать ИИ, если я не ответил за N минут', 'number', 'min="1" max="240"' );
				$f( 'welcome_ru', 'Приветствие в чате (рус)', 'textarea' );
				$f( 'welcome_kk', 'Чаттағы сәлемдесу (қаз)', 'textarea' );
				$f( 'max_upload_mb', 'Макс. размер файла, МБ', 'number', 'min="1" max="200"' );
				$f( 'notify_email', 'Уведомления', 'checkbox', 'Письмо на e-mail администратора о новых сообщениях (не чаще раза в 10 минут на чат)' );
				$f( 'reviews_automod', 'Отзывы', 'checkbox', 'Публиковать отзывы сразу, без модерации' );
				?>
			</div>
			<?php if ( ! has_filter( 'studyhub_app_config', 'StudyHub_AI::config' ) ) : ?>
				<p class="sh-muted">ИИ-помощник подключается отдельным плагином <strong>StudyHub AI</strong>.</p>
			<?php endif; ?>
			</div>

			<div class="sh-card"><h2>Приложение</h2><div class="sh-grid">
				<?php
				$f( 'app_slug', 'Адрес приложения: ' . home_url( '/' ) . '…', 'text', 'pattern="[a-z0-9\-]+"' );
				$f( 'app_on_front', 'Главная страница сайта', 'checkbox', 'Показывать приложение на главной странице сайта' );
				$f( 'delete_on_uninstall', 'Удаление', 'checkbox', 'Удалить все данные (чаты, студенты, файлы) при удалении плагина' );
				?>
			</div>
			<p>Ссылка для студентов: <a href="<?php echo esc_url( StudyHub_App::app_url() ); ?>" target="_blank"><?php echo esc_html( StudyHub_App::app_url() ); ?></a> — на телефоне: «Поделиться → На экран „Домой“» (iPhone) или «Установить приложение» (Android).</p>
			</div>
			<p><button class="button button-primary button-hero">Сохранить</button></p>
		</form></div>
		<?php
	}

	public static function handle_settings() {
		self::check( 'studyhub_settings' );
		$section = isset( $_POST['section'] ) ? sanitize_key( $_POST['section'] ) : '';

		if ( 'services' === $section ) {
			$services = array();
			$raw      = isset( $_POST['services'] ) && is_array( $_POST['services'] ) ? wp_unslash( $_POST['services'] ) : array(); // phpcs:ignore
			foreach ( $raw as $row ) {
				$title = sanitize_text_field( $row['title_ru'] ?? '' );
				if ( '' === $title ) {
					continue;
				}
				$id         = sanitize_key( $row['id'] ?? '' );
				$services[] = array(
					'id'       => $id ? $id : 's' . substr( md5( $title . microtime() ), 0, 6 ),
					'icon'     => sanitize_key( $row['icon'] ?? 'book' ),
					'color'    => sanitize_key( $row['color'] ?? 'peach' ),
					'title_ru' => $title,
					'title_kk' => sanitize_text_field( $row['title_kk'] ?? '' ),
					'desc_ru'  => sanitize_textarea_field( $row['desc_ru'] ?? '' ),
					'desc_kk'  => sanitize_textarea_field( $row['desc_kk'] ?? '' ),
					'price'    => sanitize_text_field( $row['price'] ?? '' ),
				);
			}
			$unis = array();
			$raw  = isset( $_POST['universities'] ) && is_array( $_POST['universities'] ) ? wp_unslash( $_POST['universities'] ) : array(); // phpcs:ignore
			foreach ( $raw as $row ) {
				$short = sanitize_text_field( $row['short'] ?? '' );
				if ( '' === $short ) {
					continue;
				}
				$id     = sanitize_key( $row['id'] ?? '' );
				$unis[] = array(
					'id'      => $id ? $id : 'u' . substr( md5( $short . microtime() ), 0, 6 ),
					'short'   => $short,
					'name_ru' => sanitize_text_field( $row['name_ru'] ?? '' ),
					'name_kk' => sanitize_text_field( $row['name_kk'] ?? '' ),
					'logo'    => esc_url_raw( $row['logo'] ?? '' ),
				);
			}
			StudyHub_Settings::update(
				array(
					'services'     => $services,
					'universities' => $unis,
				)
			);
			self::set_notice( '<div class="notice notice-success"><p>Сохранено.</p></div>' );
			wp_safe_redirect( admin_url( 'admin.php?page=studyhub-services' ) );
			exit;
		}

		$in       = isset( $_POST['s'] ) && is_array( $_POST['s'] ) ? wp_unslash( $_POST['s'] ) : array(); // phpcs:ignore
		$old_slug = StudyHub_Settings::app_slug();
		$text     = array( 'brand_name', 'brand_tagline_ru', 'brand_tagline_kk', 'hero_title_ru', 'hero_title_kk' );
		$area     = array( 'hero_text_ru', 'hero_text_kk', 'welcome_ru', 'welcome_kk' );
		$urls     = array( 'brand_logo', 'hero_image' );
		$bools    = array( 'notify_email', 'reviews_automod', 'app_on_front', 'delete_on_uninstall' );
		$out      = array();
		foreach ( $text as $k ) {
			$out[ $k ] = sanitize_text_field( $in[ $k ] ?? '' );
		}
		foreach ( $area as $k ) {
			$out[ $k ] = sanitize_textarea_field( $in[ $k ] ?? '' );
		}
		foreach ( $urls as $k ) {
			$out[ $k ] = esc_url_raw( $in[ $k ] ?? '' );
		}
		foreach ( $bools as $k ) {
			$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		$out['accent_color']     = sanitize_hex_color( $in['accent_color'] ?? '' ) ?: '#F26B1D';
		$out['admin_status']     = in_array( $in['admin_status'] ?? '', array( 'auto', 'online', 'offline' ), true ) ? $in['admin_status'] : 'auto';
		$out['fallback_minutes'] = min( 240, max( 1, (int) ( $in['fallback_minutes'] ?? 5 ) ) );
		$out['max_upload_mb']    = min( 200, max( 1, (int) ( $in['max_upload_mb'] ?? 20 ) ) );
		$out['app_slug']         = sanitize_title( $in['app_slug'] ?? 'app' ) ?: 'app';

		StudyHub_Settings::update( $out );
		if ( $old_slug !== $out['app_slug'] ) {
			StudyHub_App::add_rewrite_rules();
			flush_rewrite_rules();
		}
		self::set_notice( '<div class="notice notice-success"><p>Настройки сохранены.</p></div>' );
		wp_safe_redirect( admin_url( 'admin.php?page=studyhub-settings' ) );
		exit;
	}
}
