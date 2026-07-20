<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Indexistic;

/**
 * IndexNow — the free, key-based URL submission protocol supported by Bing,
 * Yandex, Seznam, and Naver. No OAuth, no service account: a random key is
 * generated once, served publicly at /{key}.txt as proof of ownership, and
 * URLs are submitted with a single unauthenticated POST.
 */
final class IndexNowClient {

	private const ENDPOINT = 'https://api.indexnow.org/indexnow';

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_serve_key_file' ) );
	}

	/**
	 * Serves the IndexNow key file at the site root — required by the protocol
	 * so search engines can verify the submitter owns the domain.
	 */
	public function maybe_serve_key_file(): void {
		$path = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
		$key  = IndexisticSettings::indexnow_key();
		if ( $path !== $key . '.txt' ) {
			return;
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( $key ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public function key_location(): string {
		return home_url( '/' . IndexisticSettings::indexnow_key() . '.txt' );
	}

	/**
	 * @param list<string> $urls Up to 10,000 URLs per the IndexNow protocol.
	 * @return array{success:bool, code?:int, error?:string}
	 */
	public function submit( array $urls ): array {
		$urls = array_values( array_filter( $urls ) );
		if ( array() === $urls ) {
			return array( 'success' => false, 'error' => __( 'No URLs to submit.', 'seoistic' ) );
		}

		$body = array(
			'host'        => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			'key'         => IndexisticSettings::indexnow_key(),
			'keyLocation' => $this->key_location(),
			'urlList'     => array_slice( $urls, 0, 10000 ),
		);

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'    => wp_json_encode( $body ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'error' => $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 === $code || 202 === $code ) {
			return array( 'success' => true, 'code' => $code );
		}

		return array( 'success' => false, 'code' => $code, 'error' => self::message_for_code( $code ) );
	}

	private static function message_for_code( int $code ): string {
		return match ( $code ) {
			400 => __( 'Bad request — the URL list or key was malformed.', 'seoistic' ),
			403 => __( 'Forbidden — the IndexNow key could not be verified. Check that the key file is publicly accessible.', 'seoistic' ),
			422 => __( "Unprocessable — the URLs don't belong to this host, or the key doesn't match.", 'seoistic' ),
			429 => __( 'Too many requests — you are being rate-limited by IndexNow.', 'seoistic' ),
			default => sprintf(
				/* translators: %d: HTTP status code. */
				__( 'IndexNow request failed (HTTP %d).', 'seoistic' ),
				$code
			),
		};
	}
}
