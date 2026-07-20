<?php
/**
 * PSR-4 autoloader for SEOISTIC + the bundled (or monorepo) seo-core package.
 * No composer install required.
 *
 * @package Seoistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( $class ) {
		$prefixes = array(
			'Wpistic\\Seoistic\\' => array( SEOISTIC_DIR . 'src/' ),
			'Wpistic\\SeoCore\\'  => array(
				SEOISTIC_DIR . 'lib/seo-core/src/',
				dirname( SEOISTIC_DIR, 2 ) . '/packages/seo-core/src/',
			),
		);

		foreach ( $prefixes as $prefix => $bases ) {
			$len = strlen( $prefix );
			if ( 0 !== strncmp( $class, $prefix, $len ) ) {
				continue;
			}
			$relative = str_replace( '\\', '/', substr( $class, $len ) ) . '.php';
			foreach ( $bases as $base ) {
				$file = $base . $relative;
				if ( is_file( $file ) ) {
					require $file;
					return;
				}
			}
		}
	}
);
