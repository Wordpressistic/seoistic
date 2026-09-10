<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core;

/**
 * /llms.txt — tells AI engines (ChatGPT, Perplexity, Gemini, AI Overviews) what the
 * site is and which URLs matter. Foundational for the premium AI Search addon.
 */
final class LlmsTxt {

	public const OPTION_ENABLED = 'seoistic_llms_txt';
	public const OPTION_CONTENT = 'seoistic_llms_txt_content';

	public function register(): void {
		if ( ! get_option( self::OPTION_ENABLED, 1 ) ) {
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

	public function generate(): string {
		$custom = trim( (string) get_option( self::OPTION_CONTENT, '' ) );
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

	/**
	 * @return array{title:string, description:string, sections:list<array{id:string,title:string,description:string,links:list<array{text:string,url:string}>}>}
	 */
	public static function blueprint(): array {
		$decoded = json_decode( (string) get_option( 'seoistic_llms_blueprint', '' ), true );
		if ( is_array( $decoded ) ) {
			$blueprint = self::sanitize_blueprint( $decoded );
			if ( array() !== $decoded ) {
				return $blueprint;
			}
		}

		$sections = array(
			array(
				'id'           => wp_generate_uuid4(),
				'title'        => __( 'Key pages', 'seoistic' ),
				'description'  => __( 'Pages that best represent the site and its products.', 'seoistic' ),
				'links'        => self::default_page_links( 6 ),
			),
			array(
				'id'           => wp_generate_uuid4(),
				'title'        => __( 'About & entity facts', 'seoistic' ),
				'description' => get_bloginfo( 'description' ),
				'links'        => array(),
			),
		);

		return array(
			'title'       => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'sections'    => $sections,
		);
	}

	/**
	 * @param array<string, mixed> $blueprint
	 */
	public static function save_blueprint( array $blueprint ): bool {
		return update_option( 'seoistic_llms_blueprint', wp_json_encode( self::sanitize_blueprint( $blueprint ) ), false );
	}

	public static function reset_blueprint(): bool {
		delete_option( 'seoistic_llms_blueprint' );
		return delete_option( self::OPTION_CONTENT );
	}

	/**
	 * @param array<string, mixed> $blueprint
	 */
	public static function render_blueprint( array $blueprint ): string {
		$data       = self::sanitize_blueprint( $blueprint );
		$lines = array( '# ' . $data['title'] );

		foreach ( $data['sections'] as $section ) {
			if ( '' !== $section['description'] ) {
				$lines[] = '> ' . $section['description'];
			}
		}

		$lines[] = '';
		foreach ( $data['sections'] as $section ) {
			if ( '' === trim( $section['title'] ) && array() === $section['links'] ) {
				continue;
			}
			$lines[] = '## ' . $section['title'];
			if ( '' !== $section['description'] ) {
				$lines[] = $section['description'];
			}
			foreach ( $section['links'] as $link ) {
				$lines[] = '- [' . $link['text'] . '](' . $link['url'] . ')';
			}
			$lines[] = '';
		}

		$lines[] = '## Sitemap';
		$lines[] = '- ' . home_url( '/wp-sitemap.xml' );

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * @return list<array{text:string,url:string}>
	 */
	private static function default_page_links( int $limit ): array {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'numberposts'    => $limit,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$links = array();
		foreach ( $pages as $page ) {
			$links[] = array(
				'text' => get_the_title( $page ),
				'url'  => (string) get_permalink( $page ),
			);
		}
		return $links;
	}

	/**
	 * @param array<string, mixed> $value
	 * @return array{title:string, description:string, sections:list<array{id:string,title:string,description:string,links:list<array{text:string,url:string}>}>}
	 */
	private static function sanitize_blueprint( array $value ): array {
		$sections     = array();
		$raw_sections = is_array( $value['sections'] ?? null ) ? $value['sections'] : array();
		foreach ( array_slice( $raw_sections, 0, 50 ) as $raw_section ) {
			if ( ! is_array( $raw_section ) ) {
				continue;
			}
			$links = array();
			$raw_links = is_array( $raw_section['links'] ?? null ) ? $raw_section['links'] : array();
			foreach ( array_slice( $raw_links, 0, 100 ) as $raw_link ) {
				if ( ! is_array( $raw_link ) ) {
					continue;
				}
				$url = esc_url_raw( (string) ( $raw_link['url'] ?? '' ) );
				$text = sanitize_text_field( (string) ( $raw_link['text'] ?? '' ) );
				if ( '' !== $url && '' !== $text ) {
					$links[] = array( 'text' => $text, 'url' => $url );
				}
			}
			$id           = sanitize_text_field( (string) ( $raw_section['id'] ?? '' ) );
			$sections[] = array(
				'id' => '' !== $id ? $id : wp_generate_uuid4(),
				'title' => sanitize_text_field( (string) ( $raw_section['title'] ?? '' ) ),
				'description' => sanitize_textarea_field( (string) ( $raw_section['description'] ?? '' ) ),
				'links' => $links,
			);
		}

		return array(
			'title' => wp_strip_all_tags( sanitize_text_field( (string) ( $value['title'] ?? get_bloginfo( 'name' ) ) ) ),
			'description' => sanitize_textarea_field( (string) ( $value['description'] ?? get_bloginfo( 'description' ) ) ),
			'sections' => $sections,
		);
	}
}
