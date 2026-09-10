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

	private const MARKER_START = '# BEGIN SEOISTIC';
	private const MARKER_END   = '# END SEOISTIC';

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
	 * @param string $marker Optional feature-specific marker. Independent markers
	 *                       prevent one SEOistic tool from replacing another's rules.
	 */
	public function apply( string $new_rules, ?string $marker = null ): array {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		global $wp_filesystem;
		if ( ! WP_Filesystem() || ! $wp_filesystem ) {
			return array( 'success' => false, 'error' => __( 'Could not initialize the filesystem — check file permissions.', 'seoistic' ) );
		}

		$start         = $this->marker( self::MARKER_START, $marker );
		$end           = $this->marker( self::MARKER_END, $marker );
		$path          = $this->path();
		$backup        = '';

		if ( $wp_filesystem->exists( $path ) ) {
			$backup = $path . '.seoistic-backup-' . gmdate( 'Ymd-His' );
			if ( ! $wp_filesystem->copy( $path, $backup, true ) ) {
				return array( 'success' => false, 'error' => __( 'Could not create a backup — aborting to avoid data loss.', 'seoistic' ) );
			}
		}

		$existing = $this->current();
		$pattern  = '/\n?' . preg_quote( $start, '/' ) . '\r?\n.*?\r?\n' . preg_quote( $end, '/' ) . '\n?/s';
		$stripped = (string) preg_replace( $pattern, '', $existing );
		$final    = rtrim( $stripped ) . "\n\n" . $start . "\n" . rtrim( $new_rules ) . "\n" . $end . "\n";

		if ( ! $wp_filesystem->put_contents( $path, ltrim( $final ), defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644 ) ) {
			return array( 'success' => false, 'error' => __( 'Could not write .htaccess — check file permissions.', 'seoistic' ) );
		}

		return array( 'success' => true, 'backup' => '' !== $backup ? basename( $backup ) : '' );
	}

	private function marker( string $base, ?string $suffix ): string {
		return null === $suffix || '' === $suffix ? $base : $base . ' ' . $suffix;
	}
}
