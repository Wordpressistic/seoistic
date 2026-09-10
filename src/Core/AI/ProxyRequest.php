<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core\AI;

/**
 * Small value object for license-authenticated WPistic proxy requests.
 * Feature services build payloads without knowing transport credentials.
 */
final class ProxyRequest {

	private string $path;
	/** @var array<string, mixed> */
	private array $payload;
	private int $timeout;

	/**
	 * @param array<string, mixed> $payload
	 */
	public function __construct( string $path, array $payload = array(), int $timeout = 45 ) {
		$this->path    = $path;
		$this->payload = $payload;
		$this->timeout = $timeout;
	}

	public function path(): string {
		return $this->path;
	}

	/** @return array<string, mixed> */
	public function payload(): array {
		return $this->payload;
	}

	public function timeout(): int {
		return $this->timeout;
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function send( ?ProxyClient $client = null ) {
		return ( $client ?? new ProxyClient() )->post( $this->path, $this->payload, $this->timeout );
	}
}
