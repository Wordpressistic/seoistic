<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Module\AbstractModule;

/**
 * Fills missing image alt text from the attachment title — free (RankMath gates this).
 */
final class ImageSeoModule extends AbstractModule {

	public function id(): string {
		return 'image';
	}

	public function name(): string {
		return __( 'Image SEO', 'seoistic' );
	}

	public function description(): string {
		return __( 'Automatic alt text from titles so no meaningful image ships without it — free.', 'seoistic' );
	}

	public function register(): void {
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'alt' ), 10, 2 );
	}

	/**
	 * @param array<string, string> $attr
	 * @param \WP_Post               $attachment
	 * @return array<string, string>
	 */
	public function alt( $attr, $attachment ) {
		if ( empty( $attr['alt'] ) ) {
			$title = get_the_title( $attachment->ID );
			if ( $title ) {
				$attr['alt'] = $title;
			}
		}
		return $attr;
	}
}
