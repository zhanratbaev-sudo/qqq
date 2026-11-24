<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database schema and data access helpers.
 * All timestamps are UNIX seconds (UTC).
 */
class StudyHub_DB {

	const DB_VERSION = '1.1.0';

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'studyhub_' . $name;
	}

	public static function maybe_upgrade() {
		if ( get_option( 'studyhub_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$students = self::table( 'students' );
		$sessions = self::table( 'sessions' );
		$messages = self::table( 'messages' );
		$files    = self::table( 'files' );
		$reviews  = self::table( 'reviews' );

		dbDelta(
			"CREATE TABLE $students (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			login varchar(60) NOT NULL,
			pass_hash varchar(255) NOT NULL,
			alias varchar(40) NOT NULL,
			university varchar(40) NOT NULL DEFAULT '',
			lang varchar(5) NOT NULL DEFAULT 'ru',
			note text NULL,
			disabled tinyint(1) NOT NULL DEFAULT 0,
			created_at bigint(20) unsigned NOT NULL DEFAULT 0,
			last_seen bigint(20) unsigned NOT NULL DEFAULT 0,
			device_hash char(64) NOT NULL DEFAULT '',
			face_lock tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY login (login)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $sessions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			student_id bigint(20) unsigned NOT NULL,
			token_hash char(64) NOT NULL,
			created_at bigint(20) unsigned NOT NULL DEFAULT 0,
			expires_at bigint(20) unsigned NOT NULL DEFAULT 0,
			unlock_until bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY student_id (student_id)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $messages (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			student_id bigint(20) unsigned NOT NULL,
			sender varchar(10) NOT NULL,
			type varchar(20) NOT NULL DEFAULT 'text',
			body text NULL,
			file_id bigint(20) unsigned NOT NULL DEFAULT 0,
			meta text NULL,
			read_student tinyint(1) NOT NULL DEFAULT 0,
			read_admin tinyint(1) NOT NULL DEFAULT 0,
			created_at bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY student_id (student_id),
			KEY unread_admin (read_admin, sender)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $files (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			student_id bigint(20) unsigned NOT NULL,
			uploader varchar(10) NOT NULL,
			path varchar(255) NOT NULL,
			orig_name varchar(190) NOT NULL,
			mime varchar(100) NOT NULL,
			size bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY student_id (student_id)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $reviews (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			student_id bigint(20) unsigned NOT NULL,
			invite_id bigint(20) unsigned NOT NULL DEFAULT 0,
			university varchar(40) NOT NULL DEFAULT '',
			service varchar(40) NOT NULL DEFAULT '',
			rating tinyint(1) NOT NULL DEFAULT 5,
			body text NOT NULL,
			status varchar(10) NOT NULL DEFAULT 'pending',
			created_at bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY student_id (student_id)
			) $charset;"
		);

		$requests = self::table( 'requests' );
		$passkeys = self::table( 'passkeys' );
		dbDelta(
			"CREATE TABLE $requests (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token_hash char(64) NOT NULL,
			device_hash char(64) NOT NULL DEFAULT '',
			service varchar(40) NOT NULL DEFAULT '',
			task text NOT NULL,
			deadline varchar(60) NOT NULL DEFAULT '',
			university varchar(40) NOT NULL DEFAULT '',
			telegram varchar(64) NOT NULL DEFAULT '',
			phone varchar(32) NOT NULL DEFAULT '',
			lang varchar(5) NOT NULL DEFAULT 'ru',
			status varchar(12) NOT NULL DEFAULT 'new',
			reply text NULL,
			student_id bigint(20) unsigned NOT NULL DEFAULT 0,
			creds text NULL,
			seen_admin tinyint(1) NOT NULL DEFAULT 0,
			created_at bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY token_hash (token_hash),
			KEY status (status)
			) $charset;"
		);
		dbDelta(
			"CREATE TABLE $passkeys (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			student_id bigint(20) unsigned NOT NULL,
			cred_id varchar(255) NOT NULL,
			pubkey text NOT NULL,
			sign_count bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at bigint(20) unsigned NOT NULL DEFAULT 0,
			last_used bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY cred_id (cred_id),
			KEY student_id (student_id)
			) $charset;"
		);

		update_option( 'studyhub_db_version', self::DB_VERSION );
	}

	/* ---------------------------------------------------------------------
	 * Students
	 * ------------------------------------------------------------------ */

	public static function get_student( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'students' ) . ' WHERE id = %d', $id ) );
	}

	public static function get_student_by_login( $login ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'students' ) . ' WHERE login = %s', $login ) );
	}

	/**
	 * Anonymous display alias, e.g. "#4F2A".
	 */
	public static function generate_alias() {
		global $wpdb;
		$t = self::table( 'students' );
		for ( $i = 0; $i < 20; $i++ ) {
			$alias = '#' . strtoupper( substr( bin2hex( random_bytes( 3 ) ), 0, 4 ) );
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE alias = %s", $alias ) ) ) {
				return $alias;
			}
		}
		return '#' . strtoupper( bin2hex( random_bytes( 4 ) ) );
	}

	public static function create_student( $login, $password, $note = '' ) {
		global $wpdb;
		$ok = $wpdb->insert(
			self::table( 'students' ),
			array(
				'login'      => $login,
				'pass_hash'  => wp_hash_password( $password ),
				'alias'      => self::generate_alias(),
				'note'       => $note,
				'created_at' => time(),
			)
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public static function update_student( $id, array $data ) {
		global $wpdb;
		return $wpdb->update( self::table( 'students' ), $data, array( 'id' => (int) $id ) );
	}

	public static function delete_student( $id ) {
		global $wpdb;
		$id = (int) $id;
		foreach ( self::files_for_student( $id ) as $f ) {
			StudyHub_Files::delete_physical( $f );
		}
		$wpdb->delete( self::table( 'files' ), array( 'student_id' => $id ) );
		$wpdb->delete( self::table( 'messages' ), array( 'student_id' => $id ) );
		$wpdb->delete( self::table( 'sessions' ), array( 'student_id' => $id ) );
		$wpdb->delete( self::table( 'reviews' ), array( 'student_id' => $id ) );
		$wpdb->delete( self::table( 'passkeys' ), array( 'student_id' => $id ) );
		$wpdb->update( self::table( 'requests' ), array( 'student_id' => 0, 'creds' => null ), array( 'student_id' => $id ) );
		$wpdb->delete( self::table( 'students' ), array( 'id' => $id ) );
	}

	public static function list_students() {
		global $wpdb;
		$s = self::table( 'students' );
		$m = self::table( 'messages' );
		return $wpdb->get_results(
			"SELECT s.*,
				(SELECT COUNT(*) FROM $m WHERE student_id = s.id AND sender = 'student' AND read_admin = 0) AS unread,
				(SELECT MAX(id) FROM $m WHERE student_id = s.id) AS last_msg_id,
				(SELECT MAX(created_at) FROM $m WHERE student_id = s.id) AS last_msg_at
			FROM $s s
			ORDER BY (last_msg_id IS NULL), last_msg_id DESC, s.created_at DESC"
		);
	}

	/* ---------------------------------------------------------------------
	 * Messages
	 * ------------------------------------------------------------------ */

	public static function add_message( $student_id, $sender, $type, $body, $file_id = 0, $meta = null ) {
		global $wpdb;
		$wpdb->insert(
			self::table( 'messages' ),
			array(
				'student_id'   => (int) $student_id,
				'sender'       => $sender,
				'type'         => $type,
				'body'         => $body,
				'file_id'      => (int) $file_id,
				'meta'         => null === $meta ? null : wp_json_encode( $meta ),
				'read_student' => 'student' === $sender ? 1 : 0,
				'read_admin'   => 'student' === $sender ? 0 : 1,
				'created_at'   => time(),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function get_message( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'messages' ) . ' WHERE id = %d', $id ) );
	}

	public static function update_message_meta( $id, array $meta ) {
		global $wpdb;
		return $wpdb->update( self::table( 'messages' ), array( 'meta' => wp_json_encode( $meta ) ), array( 'id' => (int) $id ) );
	}

	public static function messages( $student_id, $after_id = 0, $limit = 200 ) {
		global $wpdb;
		$t = self::table( 'messages' );
		if ( $after_id > 0 ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE student_id = %d AND id > %d ORDER BY id ASC LIMIT %d", $student_id, $after_id, $limit ) );
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE student_id = %d ORDER BY id DESC LIMIT %d", $student_id, $limit ) );
		return array_reverse( (array) $rows );
	}

	public static function mark_read( $student_id, $by ) {
		global $wpdb;
		$t = self::table( 'messages' );
		if ( 'admin' === $by ) {
			$wpdb->query( $wpdb->prepare( "UPDATE $t SET read_admin = 1 WHERE student_id = %d AND read_admin = 0", $student_id ) );
		} else {
			$wpdb->query( $wpdb->prepare( "UPDATE $t SET read_student = 1 WHERE student_id = %d AND read_student = 0", $student_id ) );
		}
	}

	public static function unread_for_student( $student_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'messages' ) . " WHERE student_id = %d AND read_student = 0 AND sender <> 'student'", $student_id ) );
	}

	public static function unread_for_admin() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table( 'messages' ) . " WHERE read_admin = 0 AND sender = 'student'" );
	}

	/**
	 * Highest message id the admin side has seen as read for a student (for read receipts).
	 */
	public static function last_read_ids( $student_id ) {
		global $wpdb;
		$t = self::table( 'messages' );
		return array(
			'admin'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(id) FROM $t WHERE student_id = %d AND sender = 'student' AND read_admin = 1", $student_id ) ),
			'student' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(id) FROM $t WHERE student_id = %d AND sender <> 'student' AND read_student = 1", $student_id ) ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Files
	 * ------------------------------------------------------------------ */

	public static function add_file( $student_id, $uploader, $path, $orig_name, $mime, $size ) {
		global $wpdb;
		$wpdb->insert(
			self::table( 'files' ),
			array(
				'student_id' => (int) $student_id,
				'uploader'   => $uploader,
				'path'       => $path,
				'orig_name'  => $orig_name,
				'mime'       => $mime,
				'size'       => (int) $size,
				'created_at' => time(),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function get_file( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'files' ) . ' WHERE id = %d', $id ) );
	}

	public static function files_for_student( $student_id ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'files' ) . ' WHERE student_id = %d', $student_id ) );
	}

	/* ---------------------------------------------------------------------
	 * Reviews
	 * ------------------------------------------------------------------ */

	public static function add_review( array $data ) {
		global $wpdb;
		$data['created_at'] = time();
		$wpdb->insert( self::table( 'reviews' ), $data );
		return (int) $wpdb->insert_id;
	}

	public static function reviews( $status = 'approved', $limit = 50 ) {
		global $wpdb;
		$t = self::table( 'reviews' );
		if ( 'all' === $status ) {
			return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t ORDER BY id DESC LIMIT %d", $limit ) );
		}
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE status = %s ORDER BY id DESC LIMIT %d", $status, $limit ) );
	}

	public static function review_stats() {
		global $wpdb;
		$t   = self::table( 'reviews' );
		$row = $wpdb->get_row( "SELECT COUNT(*) AS cnt, AVG(rating) AS avg FROM $t WHERE status = 'approved'" );
		return array(
			'count'   => $row ? (int) $row->cnt : 0,
			'average' => $row && $row->avg ? round( (float) $row->avg, 1 ) : 0,
		);
	}

	public static function set_review_status( $id, $status ) {
		global $wpdb;
		return $wpdb->update( self::table( 'reviews' ), array( 'status' => $status ), array( 'id' => (int) $id ) );
	}

	public static function delete_review( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table( 'reviews' ), array( 'id' => (int) $id ) );
	}

	public static function pending_reviews_count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table( 'reviews' ) . " WHERE status = 'pending'" );
	}

	/* ---------------------------------------------------------------------
	 * Requests (заявки с сайта)
	 * ------------------------------------------------------------------ */

	public static function add_request( array $data ) {
		global $wpdb;
		$data['created_at'] = time();
		$data['updated_at'] = time();
		$wpdb->insert( self::table( 'requests' ), $data );
		return (int) $wpdb->insert_id;
	}

	public static function get_request( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'requests' ) . ' WHERE id = %d', $id ) );
	}

	public static function request_by_token( $token_hash ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'requests' ) . ' WHERE token_hash = %s ORDER BY id DESC LIMIT 1', $token_hash ) );
	}

	public static function update_request( $id, array $data ) {
		global $wpdb;
		$data['updated_at'] = time();
		return $wpdb->update( self::table( 'requests' ), $data, array( 'id' => (int) $id ) );
	}

	public static function delete_request( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table( 'requests' ), array( 'id' => (int) $id ) );
	}

	public static function requests( $limit = 200 ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'requests' ) . ' ORDER BY id DESC LIMIT %d', $limit ) );
	}

	public static function new_requests_count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table( 'requests' ) . " WHERE status = 'new'" );
	}

	public static function requests_today_by_device( $device_hash ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'requests' ) . ' WHERE device_hash = %s AND created_at > %d', $device_hash, time() - DAY_IN_SECONDS ) );
	}
}

