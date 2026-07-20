<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Module\AbstractModule;

/**
 * Adds FAQ rich results. Pulls FAQ pairs from the Tour Manager's wpistic_faq meta (or
 * any source via the seoistic/faq_items filter) and emits FAQPage into the graph.
 */
final class SchemaModule extends AbstractModule {

	public function id(): string {
		return 'schema';
	}

	public function name(): string {
		return __( 'Schema / Structured Data', 'seoistic' );
	}

	public function description(): string {
		return __( 'FAQ and HowTo rich results on top of the built-in article, page and breadcrumb schema.', 'seoistic' );
	}

	public function register(): void {
		add_filter( 'seoistic/schema_nodes', array( $this, 'faq' ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $nodes
	 * @return array<int, array<string, mixed>>
	 */
	public function faq( $nodes ) {
		if ( ! is_singular() ) {
			return $nodes;
		}
		$id  = get_queried_object_id();
		$faq = apply_filters( 'seoistic/faq_items', get_post_meta( $id, 'wpistic_faq', true ), $id );
		if ( ! is_array( $faq ) || array() === $faq ) {
			return $nodes;
		}

		$items = array();
		foreach ( $faq as $row ) {
			$question = trim( (string) ( $row['q'] ?? '' ) );
			$answer   = trim( (string) ( $row['a'] ?? '' ) );
			if ( '' !== $question && '' !== $answer ) {
				$items[] = array(
					'@type'          => 'Question',
					'name'           => $question,
					'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $answer ),
				);
			}
		}
		if ( array() !== $items ) {
			$nodes[] = array( '@type' => 'FAQPage', 'mainEntity' => $items );
		}
		return $nodes;
	}
}
