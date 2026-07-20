<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Module;

/**
 * An SEOISTIC addon module. Free modules ship active; premium modules are listed in
 * the Addons screen with a "Premium / Coming Soon" badge until built and licensed.
 */
interface Module {

	public function id(): string;

	public function name(): string;

	public function description(): string;

	/** 'free' | 'premium' */
	public function tier(): string;

	/** 'active' | 'coming_soon' */
	public function status(): string;

	public function defaultEnabled(): bool;

	/** Hook into WordPress. Called only when enabled + entitled + active. */
	public function register(): void;
}
