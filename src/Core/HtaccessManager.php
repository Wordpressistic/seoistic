<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core;

/**
 * Safe .htaccess writer: always backs up the existing file first, and only ever
 * replaces SEOISTIC's own marked block (like WP core's rewrite rules do) — every
 * other line in the file is left untouched. Never called without manage_options +
 * a valid nonce; the REST route enforces both before this class is invoked.
 */
final class HtaccessManager {

	private const MARKER_START = "# BEGIN SEOISTIC\n";
	private const MARKER_END   = "# END SEOISTIC\n";

	private function path(): string {
		return rtrim( ABSPATH, '/' ) . '/.htaccess';
	}

	public function current(): string {
		$path = $this->path();
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return '';
		}
		return (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * @return array{success:bool, backup?:string, error?:string}
	 */
	public function apply( string $new_rules ): array {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		global $wp_filesystem;
		if ( ! WP_Filesystem() || ! $wp_filesystem ) {
			return array( 'success' => false, 'error' => __( 'Could not initialize the filesystem — check file permissions.', 'seoistic' ) );
		}

		$path   = $this->path();
		$backup = '';

		if ( $wp_filesystem->exists( $path ) ) {
			$backup = $path . '.seoistic-backup-' . gmdate( 'Ymd-His' );
			if ( ! $wp_filesystem->copy( $path, $backup, true ) ) {
				return array( 'success' => false, 'error' => __( 'Could not create a backup — aborting to avoid data loss.', 'seoistic' ) );
			}
		}

		$existing = $this->current();
		$stripped = (string) preg_replace( '/' . preg_quote( self::MARKER_START, '/' ) . '.*?' . preg_quote( self::MARKER_END, '/' ) . '/s', '', $existing );
		$final    = rtrim( $stripped ) . "\n\n" . self::MARKER_START . rtrim( $new_rules ) . "\n" . self::MARKER_END;

		if ( ! $wp_filesystem->put_contents( $path, ltrim( $final ), defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644 ) ) {
			return array( 'success' => false, 'error' => __( 'Could not write .htaccess — check file permissions.', 'seoistic' ) );
		}

		return array( 'success' => true, 'backup' => '' !== $backup ? basename( $backup ) : '' );
	}
}
