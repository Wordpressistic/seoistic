<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core;

/**
 * Checks a built JSON-LD node against schema.org's required/recommended
 * properties for the handful of types SEOISTIC's Schema tab lets a post pick
 * (roughly matching Google's Rich Results requirements). This is local and
 * structural only — it does not call Google's Rich Results Test API, and it
 * can only flag what's missing from data SEOISTIC itself has access to
 * (title, description, featured image, dates, FAQ meta). Types like Product
 * or Event need properties (price, availability, start date...) this plugin
 * has no field for yet; the validator says so rather than pretending support.
 */
final class SchemaValidator {

	/**
	 * @var array<string, array{required:list<string>, recommended:list<string>}>
	 */
	private const REQUIREMENTS = array(
		'Article'       => array( 'required' => array( 'headline', 'image', 'datePublished', 'author' ), 'recommended' => array( 'dateModified', 'publisher' ) ),
		'BlogPosting'   => array( 'required' => array( 'headline', 'image', 'datePublished', 'author' ), 'recommended' => array( 'dateModified', 'publisher' ) ),
		'WebPage'       => array( 'required' => array(), 'recommended' => array( 'name', 'description' ) ),
		'Product'       => array( 'required' => array( 'name', 'image' ), 'recommended' => array( 'description', 'offers', 'review', 'aggregateRating', 'brand', 'sku' ) ),
		'Event'         => array( 'required' => array( 'name', 'startDate', 'location' ), 'recommended' => array( 'endDate', 'image', 'offers' ) ),
		'LocalBusiness' => array( 'required' => array( 'name', 'address' ), 'recommended' => array( 'telephone', 'openingHoursSpecification', 'geo', 'image' ) ),
		'Recipe'        => array( 'required' => array( 'name', 'image', 'recipeIngredient', 'recipeInstructions' ), 'recommended' => array( 'author', 'datePublished', 'description' ) ),
		'Review'        => array( 'required' => array( 'itemReviewed', 'reviewRating', 'author' ), 'recommended' => array( 'datePublished' ) ),
		'VideoObject'   => array( 'required' => array( 'name', 'description', 'thumbnailUrl', 'uploadDate' ), 'recommended' => array( 'duration' ) ),
	);

	/**
	 * A short note shown alongside a type's warnings when SEOISTIC has no field
	 * at all for one of its required/recommended properties (as opposed to the
	 * property just being blank on this particular post).
	 *
	 * @var array<string, string>
	 */
	private const NO_FIELD_YET = array(
		'offers'                    => 'price/availability',
		'review'                    => 'reviews',
		'aggregateRating'           => 'ratings',
		'brand'                     => 'brand',
		'sku'                       => 'SKU',
		'startDate'                 => 'event date',
		'endDate'                   => 'event date',
		'location'                  => 'venue/address',
		'address'                   => 'address',
		'telephone'                 => 'phone number',
		'openingHoursSpecification' => 'opening hours',
		'geo'                       => 'coordinates',
		'recipeIngredient'          => 'ingredients',
		'recipeInstructions'        => 'instructions',
		'itemReviewed'              => 'the reviewed item',
		'reviewRating'              => 'a rating',
		'thumbnailUrl'              => 'a video thumbnail',
		'uploadDate'                => 'an upload date',
		'duration'                  => 'a duration',
	);

	public static function has_rules( string $type ): bool {
		return isset( self::REQUIREMENTS[ $type ] );
	}

	/**
	 * @param array<string, mixed> $node
	 * @return array{missing_required:list<array{property:string,note:string}>, missing_recommended:list<array{property:string,note:string}>}
	 */
	public static function validate( string $type, array $node ): array {
		$rules = self::REQUIREMENTS[ $type ] ?? null;
		if ( null === $rules ) {
			return array( 'missing_required' => array(), 'missing_recommended' => array() );
		}

		return array(
			'missing_required'    => self::missing( $rules['required'], $node ),
			'missing_recommended' => self::missing( $rules['recommended'], $node ),
		);
	}

	/**
	 * @param list<string>          $props
	 * @param array<string, mixed>  $node
	 * @return list<array{property:string,note:string}>
	 */
	private static function missing( array $props, array $node ): array {
		$missing = array();
		foreach ( $props as $prop ) {
			if ( ! empty( $node[ $prop ] ) ) {
				continue;
			}
			$missing[] = array(
				'property' => $prop,
				'note'     => self::NO_FIELD_YET[ $prop ] ?? '',
			);
		}
		return $missing;
	}

	/**
	 * Validates the schema SEOISTIC will actually emit for a post: the built
	 * primary node for any picked type, or — for FAQPage, which in this plugin's
	 * architecture is always a separate node sourced from FAQ meta rather than
	 * the primary node's own properties (Addon\SchemaModule::faq()) — whether
	 * any FAQ items exist at all.
	 *
	 * @return array{type:string, missing_required:list<array{property:string,note:string}>, missing_recommended:list<array{property:string,note:string}>}|null Null if validation doesn't apply (type is "none"/auto/unrecognized, or a CPT supplies its own node).
	 */
	public static function validate_for_post( int $post_id ): ?array {
		$override = PostSeo::schema_type( $post_id );
		if ( 'none' === $override ) {
			return null;
		}

		if ( 'FAQPage' === $override ) {
			$items = apply_filters( 'seoistic/faq_items', get_post_meta( $post_id, 'wpistic_faq', true ), $post_id );
			$has_items = is_array( $items ) && array() !== array_filter(
				$items,
				static fn( $row ): bool => is_array( $row ) && '' !== trim( (string) ( $row['q'] ?? '' ) ) && '' !== trim( (string) ( $row['a'] ?? '' ) )
			);
			return array(
				'type'                => 'FAQPage',
				'missing_required'    => $has_items ? array() : array( array( 'property' => 'mainEntity', 'note' => __( 'no FAQ question/answer pairs found — add them via the wpistic_faq meta or the seoistic/faq_items filter', 'seoistic' ) ) ),
				'missing_recommended' => array(),
			);
		}

		$node = Schema::build_node_for_post( $post_id );
		if ( null === $node || ! self::has_rules( (string) ( $node['@type'] ?? '' ) ) ) {
			return null;
		}

		$type   = (string) $node['@type'];
		$result = self::validate( $type, $node );
		return array_merge( array( 'type' => $type ), $result );
	}
}
