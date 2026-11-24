<?php
/**
 * Uninstall: remove data only if the admin opted in.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
$studyhub_settings = get_option( 'studyhub_settings', array() );
if ( empty( $studyhub_settings['delete_on_uninstall'] ) ) {
	return;
}
global $wpdb;
foreach ( array( 'students', 'sessions', 'messages', 'files', 'reviews' ) as $studyhub_t ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'studyhub_' . $studyhub_t ); // phpcs:ignore
}
delete_option( 'studyhub_settings' );
delete_option( 'studyhub_db_version' );
delete_option( 'studyhub_admin_heartbeat' );

$studyhub_up  = wp_upload_dir( null, false );
$studyhub_dir = trailingslashit( $studyhub_up['basedir'] ) . 'studyhub-private';
if ( is_dir( $studyhub_dir ) ) {
	$studyhub_it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $studyhub_dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $studyhub_it as $studyhub_f ) {
		$studyhub_f->isDir() ? rmdir( $studyhub_f->getPathname() ) : unlink( $studyhub_f->getPathname() ); // phpcs:ignore
	}
	rmdir( $studyhub_dir ); // phpcs:ignore
}
