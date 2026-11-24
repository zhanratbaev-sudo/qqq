<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Private file storage for chat attachments.
 * Files live outside the media library, under random names, and are served only
 * to the owning student or to an administrator.
 */
class StudyHub_Files {

	const DIR = 'studyhub-private';

	public static function allowed_types() {
		return apply_filters(
			'studyhub_allowed_file_types',
			array(
				'jpg'  => 'image/jpeg',
				'jpeg' => 'image/jpeg',
				'png'  => 'image/png',
				'webp' => 'image/webp',
				'heic' => 'image/heic',
				'gif'  => 'image/gif',
				'pdf'  => 'application/pdf',
				'doc'  => 'application/msword',
				'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'xls'  => 'application/vnd.ms-excel',
				'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'ppt'  => 'application/vnd.ms-powerpoint',
				'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
				'txt'  => 'text/plain',
				'zip'  => 'application/zip',
				'rar'  => 'application/vnd.rar',
				'7z'   => 'application/x-7z-compressed',
				'dwg'  => 'application/acad',
				'dxf'  => 'application/dxf',
				'skp'  => 'application/vnd.sketchup.skp',
				'pln'  => 'application/octet-stream',
				'rvt'  => 'application/octet-stream',
				'3dm'  => 'application/octet-stream',
				'psd'  => 'image/vnd.adobe.photoshop',
				'ai'   => 'application/postscript',
			)
		);
	}

	public static function base_dir() {
		$up = wp_upload_dir( null, false );
		return trailingslashit( $up['basedir'] ) . self::DIR;
	}

	public static function ensure_private_dir() {
		$dir = self::base_dir();
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			// Apache: deny direct access. (On nginx files are protected by random names; see README.)
			file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php // Silence.\n" ); // phpcs:ignore
		}
		return $dir;
	}

	/**
	 * Store an uploaded file array ($_FILES entry).
	 *
	 * @return int|WP_Error file id.
	 */
	public static function store_upload( array $file, $student_id, $uploader ) {
		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'upload_failed', 'upload_failed', array( 'status' => 400 ) );
		}
		$max = max( 1, (int) StudyHub_Settings::get( 'max_upload_mb' ) ) * MB_IN_BYTES;
		if ( (int) $file['size'] > $max ) {
			return new WP_Error( 'too_large', 'file_too_large', array( 'status' => 413 ) );
		}

		$orig = sanitize_file_name( wp_basename( (string) $file['name'] ) );
		$ext  = strtolower( pathinfo( $orig, PATHINFO_EXTENSION ) );
		$map  = self::allowed_types();
		if ( ! isset( $map[ $ext ] ) ) {
			return new WP_Error( 'bad_type', 'file_type_not_allowed', array( 'status' => 415 ) );
		}
		$mime = $map[ $ext ];

		// For images verify the real content type.
		if ( 0 === strpos( $mime, 'image/' ) && in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp', 'gif' ), true ) ) {
			$info = @getimagesize( $file['tmp_name'] ); // phpcs:ignore
			if ( ! $info ) {
				return new WP_Error( 'bad_image', 'file_type_not_allowed', array( 'status' => 415 ) );
			}
		}

		$dir      = self::ensure_private_dir();
		$sub      = gmdate( 'Y/m' );
		wp_mkdir_p( $dir . '/' . $sub );
		$name     = bin2hex( random_bytes( 16 ) ) . '.' . $ext;
		$relative = $sub . '/' . $name;
		$target   = $dir . '/' . $relative;

		if ( ! @move_uploaded_file( $file['tmp_name'], $target ) ) { // phpcs:ignore
			return new WP_Error( 'upload_failed', 'upload_failed', array( 'status' => 500 ) );
		}
		@chmod( $target, 0640 ); // phpcs:ignore

		// Strip EXIF/GPS metadata from photos for anonymity (re-encode).
		if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ) {
			self::strip_metadata( $target );
		}

		$size = filesize( $target );
		if ( '' === $orig || '.' . $ext === $orig ) {
			$orig = 'file.' . $ext;
		}
		return StudyHub_DB::add_file( $student_id, $uploader, $relative, $orig, $mime, $size );
	}

	private static function strip_metadata( $path ) {
		if ( ! function_exists( 'wp_get_image_editor' ) ) {
			require_once ABSPATH . 'wp-includes/media.php';
		}
		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			return;
		}
		// Keep reasonable size; GD/Imagick re-encode drops EXIF (incl. GPS & device info).
		$size = $editor->get_size();
		if ( $size && ( $size['width'] > 3000 || $size['height'] > 3000 ) ) {
			$editor->resize( 3000, 3000, false );
		}
		if ( method_exists( $editor, 'set_quality' ) ) {
			$editor->set_quality( 90 );
		}
		$editor->save( $path );
	}

	public static function delete_physical( $file ) {
		$path = self::base_dir() . '/' . ltrim( $file->path, '/' );
		if ( false === strpos( $file->path, '..' ) && file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	public static function public_shape( $file ) {
		if ( ! $file ) {
			return null;
		}
		return array(
			'id'      => (int) $file->id,
			'name'    => $file->orig_name,
			'mime'    => $file->mime,
			'size'    => (int) $file->size,
			'isImage' => in_array( $file->mime, array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ), true ),
		);
	}

	/**
	 * Stream a file to the browser and exit.
	 */
	public static function stream( $file, $download = false ) {
		$path = self::base_dir() . '/' . ltrim( $file->path, '/' );
		if ( false !== strpos( $file->path, '..' ) || ! file_exists( $path ) ) {
			status_header( 404 );
			exit;
		}
		nocache_headers();
		header( 'Content-Type: ' . $file->mime );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox" );
		$disp = ( $download || 0 !== strpos( $file->mime, 'image/' ) ) ? 'attachment' : 'inline';
		header( 'Content-Disposition: ' . $disp . "; filename*=UTF-8''" . rawurlencode( $file->orig_name ) );
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		readfile( $path ); // phpcs:ignore
		exit;
	}
}
