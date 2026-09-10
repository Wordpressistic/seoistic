<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\AI;

use Wpistic\Seoistic\Core\AI\WpisticAiClient;
use Wpistic\Seoistic\Core\PostSeo;

/**
 * Builds page context, routes every generation through WpisticAiClient, and
 * parses the model's JSON reply without writing to the database.
 */
final class AiService {

	private WpisticAiClient $client;

	public function __construct( ?WpisticAiClient $client = null ) {
		$this->client = $client ?? new WpisticAiClient();
	}

	/**
	 * @param array<string, mixed> $page
	 * @return array{success:bool, data?:array<string,mixed>, error?:string, error_data?:array<string,mixed>, usage?:array<string,mixed>, cached?:bool}
	 */
	public function generate( string $type, array $page ): array {
		$task   = self::task( $type );
		$result = $this->client->generate( $task, $page );

		if ( is_wp_error( $result ) ) {
			return array(
				'success'    => false,
				'error'      => $result->get_error_message(),
				'error_code' => $result->get_error_code(),
				'error_data' => (array) $result->get_error_data(),
			);
		}

		$data = $this->parse_json( (string) $result['data'] );
		if ( null === $data ) {
			return array( 'success' => false, 'error' => __( 'AI returned a response that was not valid JSON.', 'seoistic' ) );
		}

		$result['data'] = $data;
		return $result;
	}

	public static function task( string $type ): string {
		return match ( $type ) {
			'full_page_optimization' => 'full_optimize',
			'aeo' => 'aeo_audit',
			default => $type,
		};
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
