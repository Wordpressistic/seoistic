<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Core\SchemaValidator;

final class SchemaBlockRepository {

	private const TEMPLATE_KEYS = array( 'front_page', 'page', 'search', 'author_archive', 'date_archive', 'other' );

	private const JSON_KEYS_ALLOWLIST = array(
		'@context', '@type', '@id', '@graph', 'name', 'alternateName', 'description', 'url', 'image', 'logo',
		'headline', 'author', 'publisher', 'datePublished', 'dateModified', 'mainEntity', 'mainEntityOfPage',
		'itemReviewed', 'reviewRating', 'reviewBody', 'ratingValue', 'bestRating', 'worstRating',
		'address', 'streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry',
		'telephone', 'email', 'priceRange', 'geo', 'latitude', 'longitude', 'openingHoursSpecification',
		'dayOfWeek', 'opens', 'closes', 'startDate', 'endDate', 'location', 'eventStatus', 'eventAttendanceMode',
		'offers', 'price', 'priceCurrency', 'availability', 'validFrom', 'aggregateRating', 'ratingCount',
		'sku', 'brand', 'review', 'step', 'text', 'supply', 'tool', 'totalTime', 'yield',
		'recipeIngredient', 'recipeInstructions', 'recipeCuisine', 'recipeCategory', 'cookTime', 'prepTime',
		'provider', 'hasCourseInstance', 'courseMode', 'courseWorkload', 'applicationCategory', 'operatingSystem',
		'question', 'acceptedAnswer', 'answer', 'itemListElement', 'position', 'item',
	);

	private const STRUCTURED_FIELDS = array(
		'address', 'openingHoursSpecification', 'geo', 'location', 'offers', 'aggregateRating',
		'mainEntity', 'step', 'recipeIngredient', 'recipeInstructions', 'hasCourseInstance',
	);

	public const TYPES = array(
		'Article'             => array( 'headline', 'description', 'image', 'author', 'datePublished', 'dateModified' ),
		'FAQPage'             => array( 'mainEntity' ),
		'HowTo'               => array( 'name', 'description', 'step' ),
		'Product'             => array( 'name', 'description', 'image', 'sku', 'brand', 'offers', 'aggregateRating' ),
		'LocalBusiness'       => array( 'name', 'description', 'image', 'address', 'telephone', 'openingHoursSpecification', 'geo' ),
		'Event'               => array( 'name', 'description', 'image', 'startDate', 'endDate', 'location', 'offers' ),
		'Review'              => array( 'itemReviewed', 'reviewRating', 'author', 'datePublished' ),
		'Course'              => array( 'name', 'description', 'provider', 'hasCourseInstance' ),
		'Recipe'              => array( 'name', 'description', 'image', 'author', 'datePublished', 'recipeIngredient', 'recipeInstructions' ),
		'TravelAgency'        => array( 'name', 'description', 'image', 'address', 'telephone', 'priceRange' ),
		'SoftwareApplication' => array( 'name', 'description', 'image', 'applicationCategory', 'operatingSystem', 'offers', 'aggregateRating' ),
		'custom'              => array(),
	);

	private const VARIABLE_KEYS = array(
		'post_title',
		'post_excerpt',
		'post_content',
		'post_date',
		'post_modified',
		'permalink',
		'featured_image',
		'seo_title',
		'seo_description',
		'focus_keyword',
		'term_name',
		'term_description',
		'archive_title',
		'site_name',
		'site_description',
		'site_url',
		'author_name',
		'author_url',
	);

	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seoistic_schema_blocks';
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		global $wpdb;

		$rows = $wpdb->get_results( "SELECT * FROM {$this->table()} ORDER BY title ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array_map( array( $this, 'hydrate' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function find( int $id ): array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return is_array( $row ) ? $this->hydrate( $row ) : array();
	}

	public function save_from_request( array $raw ): array {
		$id = absint( $raw['id'] ?? 0 );
		$data = $this->sanitize(
			array(
				'title'   => (string) ( $raw['title'] ?? '' ),
				'type'    => (string) ( $raw['type'] ?? '' ),
				'rules'   => json_decode( wp_unslash( (string) ( $raw['rules'] ?? '' ) ), true ),
				'mapping' => json_decode( wp_unslash( (string) ( $raw['mapping'] ?? '' ) ), true ),
			)
		);
		$issues = $this->validate( $data );
		if ( array() !== $issues ) {
			return array( 'error', $issues, $data );
		}

		$now  = current_time( 'mysql', true );
		$row  = array(
			'title'     => $data['title'],
			'type'      => $data['type'],
			'rules'     => wp_json_encode( $data['rules'] ),
			'mapping'   => wp_json_encode( $data['mapping'] ),
			'active'    => empty( $raw['active'] ) ? 0 : 1,
			'updated_at' => $now,
		);
		global $wpdb;
		if ( $id > 0 ) {
			$wpdb->update( $this->table(), $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$row['created_at'] = $now;
			$wpdb->insert( $this->table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		return array( $wpdb->last_error === '' ? 'success' : 'error', $wpdb->last_error === '' ? array() : array( __( 'The block could not be saved.', 'seoistic' ) ), $data );
	}

	public function delete( int $id ): void {
		global $wpdb;
		$wpdb->delete( $this->table(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public function set_active( int $id, bool $active ): void {
		global $wpdb;
		$wpdb->update( $this->table(), array( 'active' => $active ? 1 : 0, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public function toggle( int $id ): void {
		$block = $this->find( $id );
		if ( array() !== $block ) {
			$this->set_active( $id, empty( $block['active'] ) );
		}
	}

	/**
	 * @param array<string, mixed> $block
	 * @return array<string, mixed>
	 */
	public function sanitize( array $block ): array {
		$type = sanitize_key( (string) ( $block['type'] ?? '' ) );
		$type = isset( self::TYPES[ $type ] ) && 'custom' !== $type ? $type : 'custom';

		$allowed_fields = self::TYPES[ $type ];
		$mapping = array();
		if ( is_array( $block['mapping'] ?? null ) ) {
			foreach ( $block['mapping'] as $field => $value ) {
				if ( in_array( $field, $allowed_fields, true ) ) {
					$mapping[ $field ] = $this->sanitize_mapping_value( $value );
				}
			}
		}
		if ( 'custom' === $type ) {
			$mapping = $this->sanitize_custom_json( $block['mapping'] ?? '' );
		}

		return array(
			'title'   => sanitize_text_field( (string) ( $block['title'] ?? '' ) ),
			'type'    => $type,
			'rules'   => $this->sanitize_rules( $block['rules'] ?? array() ),
			'mapping' => $mapping,
		);
	}

	/**
	 * @param array<string, mixed> $block
	 * @return list<string>
	 */
	public function validate( array $block ): array {
		$issues = array();
		if ( '' === (string) ( $block['title'] ?? '' ) ) {
			$issues[] = __( 'A block title is required.', 'seoistic' );
		}
		if ( 'custom' === (string) ( $block['type'] ?? '' ) && array() === ( $block['mapping'] ?? array() ) ) {
			$issues[] = __( 'Custom JSON-LD must be a non-empty JSON object.', 'seoistic' );
		}
		foreach ( (array) ( $block['mapping'] ?? array() ) as $field => $value ) {
			if ( in_array( (string) $field, self::STRUCTURED_FIELDS, true ) && ! is_array( $value ) ) {
				/* translators: %s: schema property. */
				$issues[] = sprintf( __( '%s must contain valid JSON.', 'seoistic' ), (string) $field );
			}
		}

		$node = $this->build_node( $block );
		$type = (string) ( $node['@type'] ?? '' );
		if ( '' === $type ) {
			$issues[] = __( 'The generated JSON-LD must include an @type.', 'seoistic' );
		} elseif ( SchemaValidator::has_rules( $type ) ) {
			$result = SchemaValidator::validate( $type, $node );
			foreach ( $result['missing_required'] as $missing ) {
				/* translators: %s: schema property. */
				$issues[] = sprintf( __( 'Required property %s is empty.', 'seoistic' ), $missing['property'] );
			}
		}

		return array_values( array_unique( $issues ) );
	}

	/**
	 * @param array<string, mixed> $block
	 * @return array<string, mixed>
	 */
	public function build_node( array $block ): array {
		$mapping = is_array( $block['mapping'] ?? null ) ? $block['mapping'] : array();
		if ( 'custom' === (string) ( $block['type'] ?? '' ) ) {
			$node = $mapping;
			if ( isset( $node['@id'] ) && is_string( $node['@id'] ) ) {
				$node['@id'] = $this->expand_variables( $node['@id'], 0 );
			} elseif ( ! isset( $node['@id'] ) ) {
				$node['@id'] = home_url( '/' ) . '#schema-block-' . absint( $block['id'] ?? 0 );
			}
			return $node;
		}

		$node = array( '@type' => (string) $block['type'] );
		foreach ( self::TYPES[ $node['@type'] ] as $field ) {
			$value = $this->resolve_value( $mapping[ $field ] ?? '' );
			if ( '' !== $value && null !== $value && array() !== $value ) {
				$node[ $field ] = $value;
			}
		}
		$node['@id'] = home_url( '/' ) . '#schema-block-' . absint( $block['id'] ?? 0 );
		return $node;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function nodes_for_current_request(): array {
		$nodes = array();
		foreach ( $this->all() as $block ) {
			if ( empty( $block['active'] ) || ! $this->rules_match( $block['rules'] ) ) {
				continue;
			}
			$nodes[] = $this->build_node( $block );
		}
		return $nodes;
	}

	public function rules_match( array $rules ): bool {
		$post_types = $this->string_list( $rules['post_types'] ?? array() );
		if ( array() !== $post_types && ! ( is_singular() && in_array( get_post_type(), $post_types, true ) ) ) {
			return false;
		}

		$taxonomies = $this->string_list( $rules['taxonomies'] ?? array() );
		if ( array() !== $taxonomies && ! ( ( is_tax() || is_category() || is_tag() ) && in_array( get_queried_object()->taxonomy ?? '', $taxonomies, true ) ) ) {
			return false;
		}

		$templates = $this->string_list( $rules['templates'] ?? array() );
		if ( array() !== $templates && ! in_array( $this->current_template(), $templates, true ) ) {
			return false;
		}

		$request = (string) ( $GLOBALS['wp']->request ?? '' );
		$url = home_url( '' === $request ? '/' : '/' . $request );
		foreach ( $this->string_list( $rules['url_patterns'] ?? array() ) as $pattern ) {
			if ( ! $this->url_matches( $pattern, $url ) ) {
				return false;
			}
		}
		return true;
	}

	public function current_template(): string {
		if ( is_singular() ) {
			$post_type = get_post_type();
			if ( is_page() ) {
				return is_front_page() ? 'front_page' : 'page';
			}
			return $post_type . '_single';
		}
		if ( is_post_type_archive() ) {
			return get_post_type() . '_archive';
		}
		if ( is_tax() || is_category() || is_tag() ) {
			return ( get_queried_object()->taxonomy ?? 'term' ) . '_archive';
		}
		if ( is_search() ) {
			return 'search';
		}
		if ( is_author() ) {
			return 'author_archive';
		}
		if ( is_date() ) {
			return 'date_archive';
		}
		return 'other';
	}

	private function url_matches( string $pattern, string $url ): bool {
		if ( '' === $pattern ) {
			return true;
		}
		if ( '*' === $pattern ) {
			return true;
		}
		if ( str_contains( $pattern, '*' ) ) {
			$regex = '#^' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '$#u';
			return (bool) preg_match( $regex, $url );
		}
		return str_contains( $url, $pattern ) || str_contains( '/**' . $url . '**/', $pattern );
	}

	private function resolve_value( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			$result = array();
			foreach ( $value as $key => $item ) {
				$resolved = $this->resolve_value( $item );
				if ( '' !== $resolved && null !== $resolved && array() !== $resolved ) {
					$result[ (string) $key ] = $resolved;
				}
			}
			return $result;
		}
		return $this->expand_variables( (string) $value, 0 );
	}

	private function expand_variables( string $value, int $post_id ): string {
		$post_id = $post_id > 0 ? $post_id : (int) get_the_ID();
		$term    = is_tax() || is_category() || is_tag() ? get_queried_object() : null;
		$values  = array(
			'{post_title}'       => $post_id > 0 ? get_the_title( $post_id ) : '',
			'{post_excerpt}'     => $post_id > 0 ? get_the_excerpt( $post_id ) : '',
			'{post_content}'     => $post_id > 0 ? wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) : '',
			'{post_date}'        => $post_id > 0 ? get_the_date( 'c', $post_id ) : '',
			'{post_modified}'    => $post_id > 0 ? get_the_modified_date( 'c', $post_id ) : '',
			'{permalink}'        => $post_id > 0 ? (string) get_permalink( $post_id ) : '',
			'{featured_image}'   => $this->featured_image( $post_id ),
			'{seo_title}'        => $post_id > 0 ? \Wpistic\Seoistic\Core\PostSeo::title( $post_id ) : '',
			'{seo_description}'  => $post_id > 0 ? \Wpistic\Seoistic\Core\PostSeo::description( $post_id ) : '',
			'{focus_keyword}'    => $post_id > 0 ? (string) get_post_meta( $post_id, '_seoistic_focus_keyword', true ) : '',
			'{term_name}'        => $term ? (string) $term->name : '',
			'{term_description}' => $term ? (string) $term->description : '',
			'{archive_title}'    => is_post_type_archive() ? (string) post_type_archive_title( '', false ) : '',
			'{site_name}'        => (string) get_bloginfo( 'name' ),
			'{site_description}' => (string) get_bloginfo( 'description' ),
			'{site_url}'         => (string) home_url( '/' ),
			'{author_name}'      => $post_id > 0 ? (string) get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) ) : '',
			'{author_url}'       => $post_id > 0 ? (string) get_author_posts_url( (int) get_post_field( 'post_author', $post_id ) ) : '',
		);
		return trim( strtr( $value, $values ) );
	}

	private function featured_image( int $post_id ): string {
		if ( $post_id < 1 || ! has_post_thumbnail( $post_id ) ) {
			return '';
		}
		$source = wp_get_attachment_image_src( (int) get_post_thumbnail_id( $post_id ), 'large' );
		return is_array( $source ) ? (string) $source[0] : '';
	}

	private function sanitize_rules( mixed $rules ): array {
		if ( ! is_array( $rules ) ) {
			return array();
		}
		$templates = $this->string_list( $rules['templates'] ?? array(), 'sanitize_key' );
		$templates = array_values( array_filter( $templates, array( $this, 'template_exists' ) ) );
		return array(
			'post_types'   => array_values( array_filter( $this->string_list( $rules['post_types'] ?? array(), 'sanitize_key' ), 'post_type_exists' ) ),
			'taxonomies'   => array_values( array_filter( $this->string_list( $rules['taxonomies'] ?? array(), 'sanitize_key' ), 'taxonomy_exists' ) ),
			'templates'    => $templates,
			'url_patterns' => $this->string_list( $rules['url_patterns'] ?? array(), 'sanitize_text_field' ),
		);
	}

	private function template_exists( string $template ): bool {
		if ( in_array( $template, self::TEMPLATE_KEYS, true ) ) {
			return true;
		}
		if ( str_ends_with( $template, '_single' ) ) {
			return post_type_exists( substr( $template, 0, -strlen( '_single' ) ) );
		}
		if ( str_ends_with( $template, '_archive' ) ) {
			$root = substr( $template, 0, -strlen( '_archive' ) );
			return post_type_exists( $root ) || taxonomy_exists( $root );
		}
		return false;
	}

	public function types(): array {
		return self::TYPES;
	}

	private function string_list( mixed $value, ?string $callback = null ): array {
		if ( ! is_array( $value ) ) {
			$value = array( $value );
		}
		$result = array();
		foreach ( $value as $item ) {
			$item = is_string( $item ) ? trim( $item ) : '';
			if ( '' !== $item ) {
				$result[] = null === $callback ? $item : call_user_func( $callback, $item );
			}
		}
		return array_values( array_unique( $result ) );
	}

	private function sanitize_mapping_value( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			$result = array();
			foreach ( $value as $key => $item ) {
				if ( '' !== (string) $key ) {
					$result[ (string) $key ] = $this->sanitize_mapping_value( $item );
				}
			}
			return $result;
		}
		return sanitize_text_field( (string) $value );
	}

	private function sanitize_custom_json( mixed $value ): array {
		if ( is_array( $value ) ) {
			return $this->sanitize_schema_data( $value );
		}
		$decoded = json_decode( wp_unslash( (string) $value ), true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		return $this->sanitize_schema_data( $decoded );
	}

	private function sanitize_schema_data( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			$result = array();
			foreach ( $value as $key => $item ) {
				if ( is_int( $key ) || in_array( (string) $key, self::JSON_KEYS_ALLOWLIST, true ) ) {
					$result[ is_int( $key ) ? $key : (string) $key ] = $this->sanitize_schema_data( $item );
				}
			}
			return $result;
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function hydrate( array $row ): array {
		return array(
			'id'       => (int) $row['id'],
			'title'    => (string) $row['title'],
			'type'     => (string) $row['type'],
			'rules'    => is_array( $decoded = json_decode( (string) $row['rules'], true ) ) ? $decoded : array(),
			'mapping'  => is_array( $decoded = json_decode( (string) $row['mapping'], true ) ) ? $decoded : array(),
			'active'   => (int) $row['active'],
		);
	}

	/**
	 * @return array<int, string>
	 */
	public function variables(): array {
		return self::VARIABLE_KEYS;
	}
}
