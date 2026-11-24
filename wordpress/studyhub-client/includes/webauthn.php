<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
   FACE ID / TOUCH ID / ОТПЕЧАТОК — ключи доступа (WebAuthn, passkeys).
   Ключ хранится в телефоне (iCloud Keychain / Google), на сервер — только открытый ключ.
   Проверка подписи — на сервере (ES256), без сторонних библиотек.

   studyhub/v1/passkey/options  {mode: register|login|unlock} → параметры для navigator.credentials
   studyhub/v1/passkey/register {id, clientDataJSON, attestationObject}
   studyhub/v1/passkey/login    {id, clientDataJSON, authenticatorData, signature}  (mode login или unlock)
   studyhub/v1/passkey/delete
   ============================================================ */

function studyhub_b64u( $bin ) {
	return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
}

function studyhub_b64u_dec( $s ) {
	$s = strtr( (string) $s, '-_', '+/' );
	$p = strlen( $s ) % 4;
	if ( $p ) {
		$s .= str_repeat( '=', 4 - $p );
	}
	$out = base64_decode( $s, true );
	return false === $out ? '' : $out;
}

/** Минимальный разбор CBOR (то, что нужно для attestationObject и ключа COSE). */
class StudyHub_CBOR {
	private $b;
	private $p = 0;

	public static function decode( $bin ) {
		$c = new self();
		$c->b = $bin;
		return $c->item();
	}

	/** Декодировать и вернуть, сколько байт занял элемент (для authData с хвостом). */
	public static function decode_prefix( $bin, &$len ) {
		$c    = new self();
		$c->b = $bin;
		$v    = $c->item();
		$len  = $c->p;
		return $v;
	}

	private function byte() {
		if ( $this->p >= strlen( $this->b ) ) {
			throw new Exception( 'cbor eof' );
		}
		return ord( $this->b[ $this->p++ ] );
	}

	private function bytes( $n ) {
		if ( $this->p + $n > strlen( $this->b ) ) {
			throw new Exception( 'cbor eof' );
		}
		$s        = substr( $this->b, $this->p, $n );
		$this->p += $n;
		return $s;
	}

	private function len( $info ) {
		if ( $info < 24 ) {
			return $info;
		}
		if ( 24 === $info ) {
			return $this->byte();
		}
		if ( 25 === $info ) {
			return unpack( 'n', $this->bytes( 2 ) )[1];
		}
		if ( 26 === $info ) {
			return unpack( 'N', $this->bytes( 4 ) )[1];
		}
		if ( 27 === $info ) {
			$v = unpack( 'J', $this->bytes( 8 ) )[1];
			return $v;
		}
		throw new Exception( 'cbor len' );
	}

	private function item() {
		$ib   = $this->byte();
		$type = $ib >> 5;
		$info = $ib & 31;
		switch ( $type ) {
			case 0:
				return $this->len( $info );
			case 1:
				return -1 - $this->len( $info );
			case 2:
				return array( '__bytes' => $this->bytes( $this->len( $info ) ) );
			case 3:
				return $this->bytes( $this->len( $info ) );
			case 4:
				$n   = $this->len( $info );
				$out = array();
				for ( $i = 0; $i < $n; $i++ ) {
					$out[] = $this->item();
				}
				return $out;
			case 5:
				$n   = $this->len( $info );
				$out = array();
				for ( $i = 0; $i < $n; $i++ ) {
					$k = $this->item();
					$out[ is_array( $k ) ? 'k' . $i : $k ] = $this->item();
				}
				return $out;
			case 7:
				if ( 20 === $info ) {
					return false;
				}
				if ( 21 === $info ) {
					return true;
				}
				if ( 22 === $info || 23 === $info ) {
					return null;
				}
				throw new Exception( 'cbor simple' );
		}
		throw new Exception( 'cbor type' );
	}
}

class StudyHub_WebAuthn {

	public static function rp_id() {
		return (string) wp_parse_url( home_url(), PHP_URL_HOST );
	}

	public static function origin() {
		$u    = wp_parse_url( home_url() );
		$port = isset( $u['port'] ) ? ':' . $u['port'] : '';
		return $u['scheme'] . '://' . $u['host'] . $port;
	}

	public static function count_for( $student_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . StudyHub_DB::table( 'passkeys' ) . ' WHERE student_id = %d', $student_id ) );
	}

	private static function challenge( $mode, $student_id ) {
		$c = random_bytes( 32 );
		set_transient( 'studyhub_wa_' . hash( 'sha256', $c ), array( 'mode' => $mode, 'student' => (int) $student_id, 'dev' => StudyHub_Auth::device_hash() ), 5 * MINUTE_IN_SECONDS );
		return studyhub_b64u( $c );
	}

	/** Проверить и «погасить» вызов из clientDataJSON. */
	private static function take_challenge( $client, $type, $mode ) {
		if ( ! is_array( $client ) || ( $client['type'] ?? '' ) !== $type ) {
			return new WP_Error( 'wa_type', 'bad_type', array( 'status' => 400 ) );
		}
		if ( ( $client['origin'] ?? '' ) !== self::origin() ) {
			return new WP_Error( 'wa_origin', 'bad_origin', array( 'status' => 400 ) );
		}
		$raw = studyhub_b64u_dec( $client['challenge'] ?? '' );
		$key = 'studyhub_wa_' . hash( 'sha256', $raw );
		$c   = '' !== $raw ? get_transient( $key ) : false;
		delete_transient( $key );
		if ( ! $c || $c['mode'] !== $mode || $c['dev'] !== StudyHub_Auth::device_hash() ) {
			return new WP_Error( 'wa_challenge', 'bad_challenge', array( 'status' => 400 ) );
		}
		return $c;
	}

	private static function check_auth_data( $ad ) {
		if ( strlen( $ad ) < 37 || ! hash_equals( hash( 'sha256', self::rp_id(), true ), substr( $ad, 0, 32 ) ) ) {
			return new WP_Error( 'wa_rp', 'bad_rp', array( 'status' => 400 ) );
		}
		$flags = ord( $ad[32] );
		if ( ! ( $flags & 0x01 ) || ! ( $flags & 0x04 ) ) { // нужен и «пользователь рядом», и подтверждение (Face ID / код).
			return new WP_Error( 'wa_uv', 'no_user_verification', array( 'status' => 400 ) );
		}
		return array(
			'flags' => $flags,
			'count' => unpack( 'N', substr( $ad, 33, 4 ) )[1],
		);
	}

	/** Ключ COSE (EC2, P-256) → PEM. */
	private static function cose_to_pem( $cose ) {
		if ( ! is_array( $cose ) || ( $cose[1] ?? null ) !== 2 || ( $cose[3] ?? null ) !== -7 || ( $cose[-1] ?? null ) !== 1 ) {
			return '';
		}
		$x = $cose[-2]['__bytes'] ?? '';
		$y = $cose[-3]['__bytes'] ?? '';
		if ( strlen( $x ) !== 32 || strlen( $y ) !== 32 ) {
			return '';
		}
		$der = hex2bin( '3059301306072a8648ce3d020106082a8648ce3d030107034200' ) . "\x04" . $x . $y;
		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
	}

	/* ---------- REST ---------- */

	public static function routes() {
		$ns = 'studyhub/v1';
		register_rest_route( $ns, '/passkey/options', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'options' ), 'permission_callback' => 'studyhub_req_header' ) );
		register_rest_route( $ns, '/passkey/register', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'register' ), 'permission_callback' => 'studyhub_req_student' ) );
		register_rest_route( $ns, '/passkey/login', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'login' ), 'permission_callback' => 'studyhub_req_header' ) );
		register_rest_route( $ns, '/passkey/delete', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'delete' ), 'permission_callback' => 'studyhub_req_student' ) );
	}

	public static function options( WP_REST_Request $req ) {
		$mode = sanitize_key( (string) $req->get_param( 'mode' ) );
		if ( 'register' === $mode ) {
			$s = StudyHub_Auth::current_student();
			if ( ! $s ) {
				return new WP_Error( 'not_logged_in', 'not_logged_in', array( 'status' => 401 ) );
			}
			$exclude = array();
			global $wpdb;
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT cred_id FROM ' . StudyHub_DB::table( 'passkeys' ) . ' WHERE student_id = %d', $s->id ) ) as $cid ) {
				$exclude[] = array( 'type' => 'public-key', 'id' => $cid );
			}
			return array(
				'publicKey' => array(
					'challenge'              => self::challenge( 'register', $s->id ),
					'rp'                     => array( 'name' => StudyHub_Settings::brand(), 'id' => self::rp_id() ),
					'user'                   => array(
						'id'          => studyhub_b64u( hash( 'sha256', 'studyhub-user|' . $s->id . '|' . wp_salt( 'auth' ), true ) ),
						'name'        => $s->login,
						'displayName' => StudyHub_Settings::brand() . ' ' . $s->alias,
					),
					'pubKeyCredParams'       => array( array( 'type' => 'public-key', 'alg' => -7 ) ),
					'authenticatorSelection' => array( 'authenticatorAttachment' => 'platform', 'userVerification' => 'required', 'residentKey' => 'required', 'requireResidentKey' => true ),
					'attestation'            => 'none',
					'timeout'                => 60000,
					'excludeCredentials'     => $exclude,
				),
			);
		}
		if ( 'login' === $mode || 'unlock' === $mode ) {
			$student = 'unlock' === $mode ? StudyHub_Auth::locked_student() : null;
			if ( 'unlock' === $mode && ! $student ) {
				return new WP_Error( 'not_locked', 'not_locked', array( 'status' => 400 ) );
			}
			$allow = array();
			if ( $student ) {
				global $wpdb;
				foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT cred_id FROM ' . StudyHub_DB::table( 'passkeys' ) . ' WHERE student_id = %d', $student->id ) ) as $cid ) {
					$allow[] = array( 'type' => 'public-key', 'id' => $cid );
				}
			}
			return array(
				'publicKey' => array(
					'challenge'        => self::challenge( $mode, $student ? $student->id : 0 ),
					'rpId'             => self::rp_id(),
					'userVerification' => 'required',
					'timeout'          => 60000,
					'allowCredentials' => $allow,
				),
			);
		}
		return new WP_Error( 'bad', 'bad_mode', array( 'status' => 400 ) );
	}

	public static function register( WP_REST_Request $req ) {
		$s      = StudyHub_Auth::current_student();
		$client = json_decode( studyhub_b64u_dec( $req->get_param( 'clientDataJSON' ) ), true );
		$c      = self::take_challenge( $client, 'webauthn.create', 'register' );
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		if ( (int) $c['student'] !== (int) $s->id ) {
			return new WP_Error( 'wa_student', 'bad_challenge', array( 'status' => 400 ) );
		}
		try {
			$att = StudyHub_CBOR::decode( studyhub_b64u_dec( $req->get_param( 'attestationObject' ) ) );
			$ad  = isset( $att['authData']['__bytes'] ) ? $att['authData']['__bytes'] : '';
			$chk = self::check_auth_data( $ad );
			if ( is_wp_error( $chk ) ) {
				return $chk;
			}
			if ( ! ( $chk['flags'] & 0x40 ) || strlen( $ad ) < 55 ) {
				return new WP_Error( 'wa_nocred', 'no_credential', array( 'status' => 400 ) );
			}
			$idlen = unpack( 'n', substr( $ad, 53, 2 ) )[1];
			$cid   = substr( $ad, 55, $idlen );
			$len   = 0;
			$cose  = StudyHub_CBOR::decode_prefix( substr( $ad, 55 + $idlen ), $len );
		} catch ( Exception $e ) {
			return new WP_Error( 'wa_cbor', 'bad_attestation', array( 'status' => 400 ) );
		}
		$pem = self::cose_to_pem( $cose );
		if ( '' === $pem || ! openssl_pkey_get_public( $pem ) ) {
			return new WP_Error( 'wa_key', 'unsupported_key', array( 'status' => 400 ) );
		}
		$cid_b64 = studyhub_b64u( $cid );
		if ( $cid_b64 !== (string) $req->get_param( 'id' ) ) {
			return new WP_Error( 'wa_id', 'bad_id', array( 'status' => 400 ) );
		}
		global $wpdb;
		$wpdb->replace(
			StudyHub_DB::table( 'passkeys' ),
			array(
				'student_id' => $s->id,
				'cred_id'    => $cid_b64,
				'pubkey'     => $pem,
				'sign_count' => (int) $chk['count'],
				'created_at' => time(),
			)
		);
		return array( 'ok' => true, 'id' => $cid_b64 );
	}

	public static function login( WP_REST_Request $req ) {
		if ( StudyHub_Auth::too_many() ) {
			return new WP_Error( 'too_many', 'too_many_attempts', array( 'status' => 429 ) );
		}
		$client = json_decode( studyhub_b64u_dec( $req->get_param( 'clientDataJSON' ) ), true );
		$mode   = 'unlock' === $req->get_param( 'mode' ) ? 'unlock' : 'login';
		$c      = self::take_challenge( $client, 'webauthn.get', $mode );
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		global $wpdb;
		$t    = StudyHub_DB::table( 'passkeys' );
		$cred = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE cred_id = %s", (string) $req->get_param( 'id' ) ) );
		if ( ! $cred ) {
			StudyHub_Auth::fail();
			return new WP_Error( 'wa_unknown', 'unknown_passkey', array( 'status' => 401 ) );
		}
		$ad  = studyhub_b64u_dec( $req->get_param( 'authenticatorData' ) );
		$chk = self::check_auth_data( $ad );
		if ( is_wp_error( $chk ) ) {
			return $chk;
		}
		$data = $ad . hash( 'sha256', studyhub_b64u_dec( $req->get_param( 'clientDataJSON' ) ), true );
		if ( 1 !== openssl_verify( $data, studyhub_b64u_dec( $req->get_param( 'signature' ) ), $cred->pubkey, OPENSSL_ALGO_SHA256 ) ) {
			StudyHub_Auth::fail();
			return new WP_Error( 'wa_sig', 'bad_signature', array( 'status' => 401 ) );
		}
		if ( $chk['count'] > 0 && (int) $cred->sign_count > 0 && $chk['count'] <= (int) $cred->sign_count ) {
			return new WP_Error( 'wa_clone', 'cloned_key', array( 'status' => 401 ) );
		}
		$wpdb->update( $t, array( 'sign_count' => (int) $chk['count'], 'last_used' => time() ), array( 'id' => $cred->id ) );
		$student = StudyHub_DB::get_student( $cred->student_id );
		if ( ! $student || (int) $student->disabled ) {
			return new WP_Error( 'bad_login', 'bad_credentials', array( 'status' => 401 ) );
		}
		if ( 'unlock' === $mode ) {
			if ( (int) $c['student'] !== (int) $student->id || ! StudyHub_Auth::unlock_current() ) {
				return new WP_Error( 'wa_unlock', 'bad_unlock', array( 'status' => 401 ) );
			}
			return array( 'ok' => true );
		}
		$res = StudyHub_Auth::start_session( $student );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		studyhub_event( 'student_login', array( 'student' => (int) $student->id, 'how' => 'passkey' ) );
		return studyhub_rest_me();
	}

	public static function delete() {
		$s = StudyHub_Auth::current_student();
		global $wpdb;
		$wpdb->delete( StudyHub_DB::table( 'passkeys' ), array( 'student_id' => $s->id ) );
		StudyHub_DB::update_student( $s->id, array( 'face_lock' => 0 ) );
		return array( 'ok' => true );
	}
}

add_action( 'rest_api_init', array( 'StudyHub_WebAuthn', 'routes' ) );
