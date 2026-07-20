<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core;

/**
 * /llms.txt — tells AI engines (ChatGPT, Perplexity, Gemini, AI Overviews) what the
 * site is and which URLs matter. Foundational for the premium AI Search addon.
 */
final class LlmsTxt {

	public function register(): void {
		if ( ! get_option( 'seoistic_llms_txt', 1 ) ) {
			return;
		}
		add_action( 'template_redirect', array( $this, 'maybe_output' ) );
	}

	public function maybe_output(): void {
		$path = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
		if ( 'llms.txt' !== $path ) {
			return;
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo $this->generate(); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	private function generate(): string {
		$custom = trim( (string) get_option( 'seoistic_llms_txt_content', '' ) );
		if ( '' !== $custom ) {
			return $custom . "\n";
		}

		$name = get_bloginfo( 'name' );
		$desc = get_bloginfo( 'description' );
		$out  = "# {$name}\n\n";
		if ( $desc ) {
			$out .= "> {$desc}\n\n";
		}
		$out .= "## Key pages\n";

		$pages = get_posts( array( 'post_type' => 'page', 'numberposts' => 25, 'orderby' => 'menu_order', 'order' => 'ASC' ) );
		foreach ( $pages as $page ) {
			$out .= '- [' . get_the_title( $page ) . '](' . get_permalink( $page ) . ")\n";
		}

		$out .= "\n## Sitemap\n- " . home_url( '/wp-sitemap.xml' ) . "\n";

		$extra = (string) get_option( 'seoistic_llms_extra', '' );
		if ( '' !== $extra ) {
			$out .= "\n" . $extra . "\n";
		}

		return $out;
	}
}
