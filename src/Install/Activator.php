<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Install;

final class Activator {

	public static function activate(): void {
		Tables::create();

		$defaults = array(
			'seoistic_title_sep'    => '–',
			'seoistic_sitemaps'     => 1,
			'seoistic_breadcrumbs'  => 1,
			'seoistic_llms_txt'     => 1,
		);
		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key, false ) ) {
				add_option( $key, $value );
			}
		}

		update_option( 'seoistic_flush', 1 );
		update_option( 'seoistic_db_version', SEOISTIC_DB_VERSION );
	}
}
