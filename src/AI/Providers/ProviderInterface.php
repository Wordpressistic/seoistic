<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\AI\Providers;

/**
 * A single AI backend SEOISTIC can talk to. Every provider takes the same
 * already-resolved settings (model/temperature/max_tokens) and returns the
 * same shape — AiGateway doesn't need to know which one it's calling.
 */
interface ProviderInterface {

	/**
	 * @param array<int, array{role:string, content:string}> $messages
	 * @return array{success:bool, content:string, error:string}
	 */
	public function chat( array $messages, string $model, float $temperature, int $max_tokens ): array;
}
