<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core;

use Wpistic\SeoCore\Schema\SchemaGraph;

/**
 * Emits ALL JSON-LD as a single @graph. Page-type schema for CPTs is sourced from the
 * `seoistic/{type}_data` filters (Tour Manager supplies the node) — SEOISTIC owns the
 * output, the data owner just provides the array.
 */
final class Schema {

	public function register(): void {
		add_action( 'wp_head', array( $this, 'output' ), 2 );
	}

	public function output(): void {
		if ( is_admin() || is_feed() ) {
			return;
		}

		$graph = new SchemaGraph();
		$this->base( $graph );
		$this->page( $graph );
		$this->breadcrumb( $graph );

		/**
		 * Filter the full JSON-LD node list before output.
		 *
		 * @param array<int, array<string, mixed>> $nodes
		 */
		$nodes = apply_filters( 'seoistic/schema_nodes', $graph->toArray()['@graph'] );

		$final = new SchemaGraph();
		foreach ( (array) $nodes as $node ) {
			$final->add( (array) $node );
		}
		if ( $final->isEmpty() ) {
			return;
		}

		echo "\n" . '<script type="application/ld+json">' . $final->toJson() . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private function base( SchemaGraph $graph ): void {
		$home = home_url( '/' );
		$graph->add(
			array(
				'@type' => 'Organization',
				'@id'   => $home . '#organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => $home,
			)
		);
		$graph->add(
			array(
				'@type'     => 'WebSite',
				'@id'       => $home . '#website',
				'url'       => $home,
				'name'      => get_bloginfo( 'name' ),
				'publisher' => array( '@id' => $home . '#organization' ),
			)
		);
	}

	private function page( SchemaGraph $graph ): void {
		if ( ! is_singular() ) {
			return;
		}
		$node = self::build_node_for_post( get_queried_object_id() );
		if ( null !== $node ) {
			$graph->add( $node );
		}
	}

	/**
	 * Builds the primary JSON-LD node for a post — the same node the front end
	 * emits into wp_head, extracted so the Schema tab's admin-side validator
	 * (Core\SchemaValidator) can check it without a live page load. Honors the
	 * per-post schema-type override (Core\PostSeo::schema_type()): "none" emits
	 * nothing, a specific type overrides the auto-picked Article/WebPage default.
	 * CPT-supplied nodes (Tour Manager's tour/destination/experience) always win —
	 * those are already a complete, purpose-built node, not something the generic
	 * type override make sense to reshape.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function build_node_for_post( int $id ): ?array {
		$post_type = get_post_type( $id );
		$filters   = array(
			'wpistic_tour'        => 'seoistic/tour_data',
			'wpistic_destination' => 'seoistic/destination_data',
			'wpistic_experience'  => 'seoistic/experience_data',
		);

		if ( isset( $filters[ $post_type ] ) ) {
			$node = apply_filters( $filters[ $post_type ], array(), $id );
			if ( is_array( $node ) && array() !== $node ) {
				$node['@id'] = get_permalink( $id ) . '#primary';
				return $node;
			}
		}

		$override = PostSeo::schema_type( $id );
		if ( 'none' === $override ) {
			return null;
		}
		// FAQPage is always emitted as its own additional node from FAQ meta
		// (Addon\SchemaModule::faq()), never as the primary node's @type.
		$type = ( '' !== $override && 'FAQPage' !== $override ) ? $override : ( ( 'post' === $post_type ) ? 'BlogPosting' : 'WebPage' );

		$node = array(
			'@type'         => $type,
			'@id'           => get_permalink( $id ) . '#primary',
			'headline'      => get_the_title( $id ),
			'name'          => get_the_title( $id ),
			'url'           => get_permalink( $id ),
			'datePublished' => get_the_date( 'c', $id ),
			'dateModified'  => get_the_modified_date( 'c', $id ),
		);

		$description = PostSeo::description( $id );
		if ( '' !== $description ) {
			$node['description'] = $description;
		}
		if ( has_post_thumbnail( $id ) ) {
			$src = wp_get_attachment_image_src( (int) get_post_thumbnail_id( $id ), 'large' );
			if ( $src ) {
				$node['image'] = $src[0];
			}
		}

		return $node;
	}

	private function breadcrumb( SchemaGraph $graph ): void {
		$trail = Breadcrumbs::trail();
		if ( count( $trail ) < 2 ) {
			return;
		}
		$items    = array();
		$position = 1;
		foreach ( $trail as $crumb ) {
			$item = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => $crumb['label'],
			);
			if ( '' !== $crumb['url'] ) {
				$item['item'] = $crumb['url'];
			}
			$items[] = $item;
		}
		$graph->add( array( '@type' => 'BreadcrumbList', 'itemListElement' => $items ) );
	}
}
