<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\AI\Providers;

/**
 * Shared HTTP logic for any OpenAI chat-completions-compatible endpoint.
 * OpenRouter, Groq, and a self-hosted Ollama instance (via its /v1 endpoint)
 * all speak this exact request/response shape, so every provider funnels
 * through here — only the endpoint and headers differ between them.
 */
final class OpenAiCompatibleClient {

	/**
	 * @param array<string, string>                           $headers  Provider-specific headers (auth, referer, etc).
	 * @param array<int, array{role:string, content:string}>  $messages
	 * @return array{success:bool, content:string, error:string}
	 */
	public static function chat( string $endpoint, array $headers, string $model, array $messages, float $temperature, int $max_tokens ): array {
		$body = array(
			'model'       => $model,
			'messages'    => array_map(
				static fn( array $m ): array => array(
					'role'    => in_array( $m['role'], array( 'system', 'user', 'assistant' ), true ) ? $m['role'] : 'user',
					'content' => (string) $m['content'],
				),
				$messages
			),
			'temperature' => max( 0, min( 2, $temperature ) ),
			'max_tokens'  => max( 50, min( 4000, $max_tokens ) ),
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 40,
				'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'content' => '', 'error' => $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
			$message = is_array( $data ) && isset( $data['error']['message'] ) ? (string) $data['error']['message'] : sprintf(
				/* translators: %d: HTTP status code. */
				__( 'AI provider request failed (HTTP %d).', 'seoistic' ),
				$status
			);
			return array( 'success' => false, 'content' => '', 'error' => $message );
		}

		$content = (string) ( $data['choices'][0]['message']['content'] ?? '' );
		if ( '' === $content ) {
			return array( 'success' => false, 'content' => '', 'error' => __( 'The AI provider returned an empty response.', 'seoistic' ) );
		}

		return array( 'success' => true, 'content' => $content, 'error' => '' );
	}
}
