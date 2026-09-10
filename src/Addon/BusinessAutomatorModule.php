<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\BusinessAutomator\RecipeEngine;
use Wpistic\Seoistic\Module\AbstractModule;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class BusinessAutomatorModule extends AbstractModule {

	public const CRON_DAILY_HOOK = 'seoistic_business_automator_daily';
	public const CRON_WEEKLY_HOOK = 'seoistic_business_automator_weekly';
	public const CONTENT_HOOK = 'save_post';
	public const NONCE_ACTION = 'seoistic_business_automator';

	private RecipeEngine $engine;

	public function __construct(?RecipeEngine $engine = null) {
		$this->engine = $engine ?? new RecipeEngine();
	}

	public function id(): string {
		return 'business_automator';
	}

	public function name(): string {
		return __('Business Automator', 'seoistic');
	}

	public function description(): string {
		return __('Run approval-gated SEO recipes with scheduled triggers, AI drafts, audits, and notifications.', 'seoistic');
	}

	public function tier(): string {
		return 'business';
	}

	public function status(): string {
		return 'active';
	}

	public function defaultEnabled(): bool {
		return false;
	}

	public function register(): void {
		add_action('init', function (): void {
			if (!get_option(RecipeEngine::RECIPES_OPTION, false)) {
				$this->engine->install_starters();
			}
			$this->sync_cron();
		});
		add_action(self::CRON_DAILY_HOOK, fn() => $this->engine->run_due('daily'));
		add_action(self::CRON_WEEKLY_HOOK, fn() => $this->engine->run_due('weekly'));
		add_action(self::CONTENT_HOOK, array($this, 'content_saved'), 10, 3);
		add_action('seoistic/license_event', array($this, 'license_event'));
		add_action('rest_api_init', array($this, 'register_rest_routes'));
	}

	public function content_saved(int $postId, $post, bool $update): void {
		static $running = false;
		if ($running || wp_is_post_revision($postId) || wp_is_post_autosave($postId) || !current_user_can('edit_post', $postId)) {
			return;
		}
		$publicTypes = get_post_types(array('public' => true), 'names');
		if (!in_array($post->post_type, $publicTypes, true) || !in_array($post->post_status, array('publish', 'draft', 'pending', 'future', 'private'), true)) {
			return;
		}
		$running = true;
		try {
			$this->engine->run('new_post_seo_polish', 'content_saved', $postId);
		} finally {
			$running = false;
		}
	}

	public function license_event(string $event = ''): void {
		if ('activated' !== $event) {
			return;
		}
		$this->engine->run('llms_txt_refresh', 'license_event');
	}

	public function register_rest_routes(): void {
		register_rest_route('seoistic/v1', '/business-automator/recipes', array(
			'methods' => 'GET',
			'callback' => fn(WP_REST_Request $request) => new WP_REST_Response(array(
				'success' => true,
				'data' => array(
					'recipes' => $this->engine->recipes(),
					'history' => $this->engine->history(),
					'settings' => get_option(RecipeEngine::SETTINGS_OPTION, array('notify_email' => '')),
				),
			)),
			'permission_callback' => array($this, 'can_manage'),
		));

		register_rest_route('seoistic/v1', '/business-automator/recipes/(?P<id>[a-z0-9_-]+)', array(
			'methods' => 'POST',
			'callback' => array($this, 'save_recipe'),
			'permission_callback' => array($this, 'can_manage'),
			'args' => array(
				'enabled' => array('type' => 'boolean', 'default' => false),
				'auto_apply' => array('type' => 'boolean', 'default' => false),
				'schedule' => array('type' => 'string', 'enum' => array('daily', 'weekly'), 'default' => 'weekly'),
				'notify_email' => array('type' => 'string', 'sanitize_callback' => 'sanitize_email'),
				'nonce' => array('type' => 'string', 'required' => true),
			),
		));

		register_rest_route('seoistic/v1', '/business-automator/runs/(?P<run_id>[a-z0-9_-]+)/approve', array(
			'methods' => 'POST',
			'callback' => array($this, 'approve_run'),
			'permission_callback' => array($this, 'can_manage'),
			'args' => array('nonce' => array('type' => 'string', 'required' => true)),
		));

		register_rest_route('seoistic/v1', '/business-automator/run', array(
			'methods' => 'POST',
			'callback' => array($this, 'run_recipe'),
			'permission_callback' => array($this, 'can_manage'),
			'args' => array(
				'recipe_id' => array('type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_key'),
				'nonce' => array('type' => 'string', 'required' => true),
			),
		));
	}

	public function save_recipe(WP_REST_Request $request) {
		if (!$this->verify_nonce((string) $request->get_param('nonce'))) {
			return new WP_Error('seoistic_invalid_nonce', __('Invalid request token.', 'seoistic'), array('status' => 403));
		}
		$saved = $this->engine->save_recipe(
			sanitize_key((string) $request->get_param('id')),
			(bool) $request->get_param('enabled'),
			(bool) $request->get_param('auto_apply'),
			sanitize_key((string) $request->get_param('schedule'))
		);
		if (is_wp_error($saved)) {
			return $saved;
		}
		$email = sanitize_email((string) $request->get_param('notify_email'));
		update_option(RecipeEngine::SETTINGS_OPTION, array('notify_email' => $email), false);
		$this->sync_cron();
		return new WP_REST_Response(array('success' => true, 'data' => $saved));
	}

	public function approve_run(WP_REST_Request $request) {
		if (!$this->verify_nonce((string) $request->get_param('nonce'))) {
			return new WP_Error('seoistic_invalid_nonce', __('Invalid request token.', 'seoistic'), array('status' => 403));
		}
		$result = $this->engine->approve(sanitize_text_field((string) $request->get_param('run_id')), get_current_user_id());
		return is_wp_error($result) ? $result : new WP_REST_Response(array('success' => true, 'data' => $result));
	}

	public function run_recipe(WP_REST_Request $request) {
		if (!$this->verify_nonce((string) $request->get_param('nonce'))) {
			return new WP_Error('seoistic_invalid_nonce', __('Invalid request token.', 'seoistic'), array('status' => 403));
		}
		$recipe = $this->engine->recipe(sanitize_key((string) $request->get_param('recipe_id')));
		if (!$recipe) {
			return new WP_Error('seoistic_recipe_not_found', __('Recipe not found.', 'seoistic'), array('status' => 404));
		}
		$result = $this->engine->run((string) $recipe['id'], (string) $recipe['trigger']);
		return new WP_REST_Response(array('success' => true, 'data' => $result));
	}

	public function sync_cron(): void {
		$recipes = $this->engine->recipes();
		self::sync_hook(self::CRON_DAILY_HOOK, 'daily', (bool) array_filter($recipes, fn($recipe) => $recipe['enabled'] && 'schedule' === $recipe['trigger'] && 'daily' === $recipe['schedule']));
		self::sync_hook(self::CRON_WEEKLY_HOOK, 'weekly', (bool) array_filter($recipes, fn($recipe) => $recipe['enabled'] && 'schedule' === $recipe['trigger'] && 'weekly' === $recipe['schedule']));
	}

	private static function sync_hook(string $hook, string $recurrence, bool $needed): void {
		$next = wp_next_scheduled($hook);
		if (!$next && $needed) {
			wp_schedule_event(time() + HOUR_IN_SECONDS, $recurrence, $hook);
			return;
		}
		if ($next && !$needed) {
			wp_clear_scheduled_hook($hook);
		}
	}

	public function can_manage(): bool {
		return current_user_can('manage_options');
	}

	private function verify_nonce(string $nonce): bool {
		return (bool) wp_verify_nonce($nonce, self::NONCE_ACTION);
	}
}
