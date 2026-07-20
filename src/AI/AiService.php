<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\AI;

use Wpistic\Seoistic\Core\PostSeo;

/**
 * Orchestrates a single AI generation: builds the page context, asks the
 * configured provider via AiGateway, and parses the model's JSON reply.
 * Callers (RestController) own capability/nonce checks and deciding what to
 * do with the result — this class never writes to the database.
 */
final class AiService {

	private AiGateway $client;

	public function __construct( ?AiGateway $client = null ) {
		$this->client = $client ?? new AiGateway();
	}

	/**
	 * @param array<string, mixed> $page
	 * @return array{success:bool, data?:array<string,mixed>, error?:string}
	 */
	public function generate( string $type, array $page ): array {
		$messages = PromptBuilder::build( $type, $page );
		$result   = $this->client->chat( $messages );

		if ( ! $result['success'] ) {
			return array( 'success' => false, 'error' => $result['error'] );
		}

		$data = $this->parse_json( $result['content'] );
		if ( null === $data ) {
			return array( 'success' => false, 'error' => __( 'AI returned a response that was not valid JSON.', 'seoistic' ) );
		}

		return array( 'success' => true, 'data' => $data );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function page_context_from_post( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}
		return array(
			'title'         => PostSeo::title( $post_id ) ?: $post->post_title,
			'content'       => (string) $post->post_content,
			'focus_keyword' => PostSeo::focus_keyword( $post_id ),
			'page_type'     => $post->post_type,
			'url'           => (string) get_permalink( $post_id ),
		);
	}

	/**
	 * Models occasionally wrap JSON in a code fence or add stray prose — recover the
	 * JSON object defensively rather than failing outright.
	 *
	 * @return array<string, mixed>|null
	 */
	private function parse_json( string $raw ): ?array {
		$raw = trim( $raw );
		$raw = (string) preg_replace( '/^```(?:json)?/i', '', $raw );
		$raw = (string) preg_replace( '/```\s*$/', '', $raw );
		$raw = trim( $raw );

		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		if ( preg_match( '/\{.*\}/s', $raw, $matches ) ) {
			$decoded = json_decode( $matches[0], true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return null;
	}
}
