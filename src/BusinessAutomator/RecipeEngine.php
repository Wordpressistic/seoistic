<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\BusinessAutomator;

use Wpistic\Seoistic\AI\AiService;
use Wpistic\Seoistic\Core\LlmsTxt;
use Wpistic\Seoistic\Core\PostSeo;
use Wpistic\Seoistic\Core\Scorer;
use WP_Error;

/**
 * Internal recipe runner. Mutating actions are represented as previews first and
 * can only be written by approve(), whether approval is manual or auto-apply.
 */
final class RecipeEngine {

	public const RECIPES_OPTION = 'seoistic_automator_recipes';
	public const HISTORY_OPTION = 'seoistic_automator_history';
	public const SETTINGS_OPTION = 'seoistic_automator_settings';
	public const LOCK_TRANSIENT = 'seoistic_automator_lock';
	public const MAX_HISTORY = 50;

	private AiService $ai;

	/** @var callable(array<string,mixed>): string|null */
	private $clock;

	public function __construct(?AiService $ai = null, ?callable $clock = null) {
		$this->ai = $ai ?? new AiService();
		$this->clock = $clock ?? static fn(): string => gmdate('c');
	}

	/** @return array<string, array<string, mixed>> */
	public function starter_recipes(): array {
		return array(
			'weekly_audit_report' => array(
				'id' => 'weekly_audit_report',
				'name' => __('Weekly audit + report', 'seoistic'),
				'description' => __('Audits published content and prepares an emailed score digest.', 'seoistic'),
				'trigger' => 'schedule',
				'schedule' => 'weekly',
				'steps' => array('audit', 'ai_draft', 'approval', 'apply', 'notify'),
				'enabled' => true,
				'auto_apply' => false,
			),
			'new_post_seo_polish' => array(
				'id' => 'new_post_seo_polish',
				'name' => __('New post SEO polish', 'seoistic'),
				'description' => __('Drafts title, description, and focus keyword suggestions when content is saved.', 'seoistic'),
				'trigger' => 'content_saved',
				'steps' => array('audit', 'ai_draft', 'approval', 'apply'),
				'enabled' => true,
				'auto_apply' => false,
			),
			'freshness_monitor' => array(
				'id' => 'freshness_monitor',
				'name' => __('Freshness monitor', 'seoistic'),
				'description' => __('Finds stale pages and drafts an update reminder.', 'seoistic'),
				'trigger' => 'schedule',
				'schedule' => 'weekly',
				'steps' => array('audit', 'ai_draft', 'approval', 'notify'),
				'enabled' => true,
				'auto_apply' => false,
			),
			'schema_reminder' => array(
				'id' => 'schema_reminder',
				'name' => __('Schema reminder', 'seoistic'),
				'description' => __('Checks for missing schema and prepares an implementation reminder.', 'seoistic'),
				'trigger' => 'schedule',
				'schedule' => 'weekly',
				'steps' => array('audit', 'approval', 'notify'),
				'enabled' => true,
				'auto_apply' => false,
			),
			'llms_txt_refresh' => array(
				'id' => 'llms_txt_refresh',
				'name' => __('llms.txt refresh', 'seoistic'),
				'description' => __('Refreshes llms.txt from current published content.', 'seoistic'),
				'trigger' => 'schedule',
				'schedule' => 'weekly',
				'steps' => array('audit', 'approval', 'apply', 'notify'),
				'enabled' => true,
				'auto_apply' => false,
			),
		);
	}

	public function install_starters(): void {
		$saved = $this->recipes();
		foreach ($this->starter_recipes() as $id => $recipe) {
			if (!isset($saved[$id])) {
				$saved[$id] = $recipe;
			}
		}
		self::update($saved);
	}

	/** @return array<string, array<string, mixed>> */
	public function recipes(): array {
		$saved = get_option(self::RECIPES_OPTION, array());
		return is_array($saved) && $saved ? array_merge($this->starter_recipes(), $saved) : $this->starter_recipes();
	}

	public function recipe(string $id): ?array {
		return $this->recipes()[$id] ?? null;
	}

	public function save_recipe(string $id, bool $enabled, bool $autoApply, string $schedule = 'weekly'): WP_Error|array {
		$recipes = $this->recipes();
		if (!isset($recipes[$id])) {
			return new WP_Error('seoistic_recipe_not_found', __('Recipe not found.', 'seoistic'), array('status' => 404));
		}
		if (!in_array($schedule, array('daily', 'weekly'), true)) {
			return new WP_Error('seoistic_invalid_schedule', __('Choose a daily or weekly schedule.', 'seoistic'), array('status' => 400));
		}
		$recipes[$id]['enabled'] = $enabled;
		$recipes[$id]['auto_apply'] = $autoApply;
		$recipes[$id]['schedule'] = $schedule;
		self::update($recipes);
		return $recipes[$id];
	}

	public function run(string $recipeId, string $trigger, ?int $postId = null): array {
		if (!$this->acquire_lock()) {
			return array('run_id' => '', 'status' => 'locked');
		}

		try {
			$recipe = $this->recipe($recipeId);
			if (!$recipe || !$recipe['enabled'] || $recipe['trigger'] !== $trigger) {
				return array('run_id' => '', 'status' => 'skipped');
			}

			$runId = sprintf('%s-%s', $recipeId, substr(wp_generate_uuid4(), 0, 8));
			$run = array(
				'run_id' => $runId,
				'recipe_id' => $recipeId,
				'recipe_name' => $recipe['name'],
				'trigger' => $trigger,
				'post_id' => $postId,
				'status' => 'running',
				'started_at' => ($this->clock)($runId),
				'completed_at' => null,
				'auto_applied' => false,
				'steps' => array(),
			);
			$this->persist($run);

			$context = $this->audit_step($run, $recipeId, $postId);
			$draft = $this->ai_draft_step($run, $recipeId, $context, $postId);
			if (!$draft && 'llms_txt_refresh' === $recipeId) {
				$draft = array('recipe_id' => $recipeId, 'llms_txt' => LlmsTxt::render_blueprint(LlmsTxt::blueprint()));
			}
			$run['draft'] = $draft;
			$this->approval_step($run, $draft);

			if ($draft && $recipe['auto_apply']) {
				$approval = end($run['steps']);
				$approval['status'] = 'auto_approved';
				$approval['audit'] = array(
					'auto_apply' => true,
					'approved_by' => 'auto_apply:' . $recipeId,
					'approved_at' => ($this->clock)($runId),
				);
				$run['steps'][count($run['steps']) - 1] = $approval;
				$run['auto_applied'] = true;
				$this->persist($run);
			}

			if ($draft && $this->was_approved($run)) {
				$this->apply_step($run, $draft);
			}
			$this->notify_step($run, $draft);

			$run['status'] = 'awaiting_approval';
			if (!$draft || $this->was_approved($run)) {
				$run['status'] = 'completed';
			}
			$run['completed_at'] = ($this->clock)($runId);
			$this->persist($run);
			return array('run_id' => $runId, 'status' => $run['status']);
		} finally {
			delete_transient(self::LOCK_TRANSIENT);
		}
	}

	public function approve(string $runId, int $userId): WP_Error|array {
		$runs = $this->history();
		foreach ($runs as $index => $run) {
			if (($run['run_id'] ?? '') !== $runId) {
				continue;
			}
			if ('awaiting_approval' !== ($run['status'] ?? '')) {
				return new WP_Error('seoistic_run_not_approvable', __('This run is no longer awaiting approval.', 'seoistic'), array('status' => 409));
			}
			foreach ($run['steps'] as $stepIndex => $step) {
				if ('approval' === $step['id'] && 'awaiting_approval' === $step['status']) {
					$step['status'] = 'approved';
					$step['audit'] = array(
						'approved_by' => 'user:' . $userId,
						'approved_at' => ($this->clock)($runId),
					);
					$run['steps'][$stepIndex] = $step;
				}
			}
			$draft = $run['draft'] ?? array();
			$this->apply_step($run, $draft);
			$this->notify_step($run, $draft);
			$run['status'] = 'completed';
			$run['completed_at'] = ($this->clock)($runId);
			$runs[$index] = $run;
			self::update($runs, self::HISTORY_OPTION);
			return $run;
		}
		return new WP_Error('seoistic_run_not_found', __('Run not found.', 'seoistic'), array('status' => 404));
	}

	/** @return array<int, array<string, mixed>> */
	public function history(?int $limit = null): array {
		$runs = get_option(self::HISTORY_OPTION, array());
		$runs = is_array($runs) ? array_values($runs) : array();
		return null === $limit ? $runs : array_slice($runs, 0, $limit);
	}

	public function run_by_id(string $runId): ?array {
		foreach ($this->history() as $run) {
			if (($run['run_id'] ?? '') === $runId) {
				return $run;
			}
		}
		return null;
	}

	public function run_due(string $frequency): void {
		foreach ($this->recipes() as $recipe) {
			if ('schedule' === $recipe['trigger'] && $recipe['enabled'] && $recipe['schedule'] === $frequency) {
				$this->run((string) $recipe['id'], 'schedule');
			}
		}
	}

	private function acquire_lock(): bool {
		if (false !== get_transient(self::LOCK_TRANSIENT)) {
			return false;
		}
		return set_transient(self::LOCK_TRANSIENT, ($this->clock)('lock'), 5 * MINUTE_IN_SECONDS);
	}

	/** @return array<string, mixed> */
	private function audit_step(array &$run, string $recipeId, ?int $postId): array {
		$posts = $this->target_posts($recipeId, $postId);
		$context = array('posts' => array(), 'site' => home_url('/'));
		foreach ($posts as $target) {
			$targetPost = get_post($target);
			$score = $targetPost ? Scorer::score($targetPost)['score'] : 0;
			$context['posts'][] = array(
				'id' => $target,
				'title' => (string) get_the_title($target),
				'url' => (string) get_permalink($target),
				'score' => $score,
				'schema_type' => PostSeo::schema_type($target),
				'modified_gmt' => $targetPost ? (string) $targetPost->post_modified_gmt : '',
			);
		}
		$this->step($run, 'audit', 'completed', $context);
		return $context;
	}

	private function ai_draft_step(array &$run, string $recipeId, array $context, ?int $postId): array {
		if (!in_array('ai_draft', (array) ($this->recipe($recipeId)['steps'] ?? array()), true)) {
			return array();
		}
		$payload = array(
			'task' => $recipeId,
			'title' => get_bloginfo('name') . ' automation',
			'url' => home_url('/'),
			'content' => $postId ? (string) (get_post($postId)->post_content ?? '') : wp_json_encode($context),
			'context' => $context,
		);
		$result = $this->ai->generate('full_page_optimization', $payload);
		if (empty($result['success'])) {
			$this->step($run, 'ai_draft', 'failed', array('error' => (string) ($result['error'] ?? __('AI draft failed.', 'seoistic'))));
			return array();
		}
		$draft = $this->normalize_draft((array) $result['data'], $postId);
		if (!$draft) {
			$this->step($run, 'ai_draft', 'failed', array('error' => __('AI did not return a usable draft.', 'seoistic')));
			return array();
		}
		$this->step($run, 'ai_draft', 'completed', array('summary' => $this->draft_summary($draft)));
		return $draft;
	}

	private function approval_step(array &$run, array $draft): void {
		$this->step($run, 'approval', $draft ? 'awaiting_approval' : 'skipped', array(
			'diff_preview' => $this->diff_preview($draft),
		));
	}

	private function apply_step(array &$run, array $draft): void {
		if (!$draft || !$this->was_approved($run)) {
			$this->step($run, 'apply', 'skipped', array('reason' => __('Approval required.', 'seoistic')));
			return;
		}

		if (!empty($draft['post_id'])) {
			$fields = array_filter(array(
				'_seoistic_title' => $draft['title'] ?? null,
				'_seoistic_description' => $draft['description'] ?? null,
				'_seoistic_focus_keyword' => $draft['focus_keyword'] ?? null,
			), static fn($value): bool => null !== $value && '' !== $value);
			if ($fields) {
				PostSeo::save((int) $draft['post_id'], $fields);
				Scorer::recalculate((int) $draft['post_id']);
			}
		} elseif (!empty($draft['llms_txt'])) {
			update_option('seoistic_llms_txt_content', wp_kses((string) $draft['llms_txt'], array()), false);
		} else {
			$this->step($run, 'apply', 'skipped', array('reason' => __('No content changes were required.', 'seoistic')));
			return;
		}
		$this->step($run, 'apply', 'completed', array(
			'changed' => $this->draft_summary($draft),
			'audit' => array('changed_by' => 'RecipeEngine::apply_step', 'changed_at' => ($this->clock)($run['run_id'])),
		));
	}

	private function notify_step(array &$run, array $draft): void {
		$recipe = $this->recipe((string) $run['recipe_id']);
		if (!in_array('notify', (array) ($recipe['steps'] ?? array()), true)) {
			return;
		}
		if ($draft && !$this->was_approved($run)) {
			$this->step($run, 'notify', 'awaiting_approval', array());
			return;
		}
		$historyUrl = admin_url('admin.php?page=seoistic-business-automator&tab=history');
		$body = sprintf("%s\n\n%s\n", (string) $run['recipe_name'], $historyUrl);
		wp_mail(
			$this->notification_email(),
			sprintf('[%s] %s', get_bloginfo('name'), (string) $run['recipe_name']),
			$body
		);
		$this->step($run, 'notify', 'completed', array('recipient_hash' => self::hash_email($this->notification_email())));
	}

	private function step(array &$run, string $id, string $status, array $data): void {
		$run['steps'][] = array(
			'id' => $id,
			'status' => $status,
			'data' => $data,
			'completed_at' => 'skipped' === $status || 'failed' === $status || 'awaiting_approval' === $status ? ($this->clock)($id) : ($this->clock)($id),
		);
		$this->persist($run);
	}

	private function was_approved(array $run): bool {
		foreach ((array) ($run['steps'] ?? array()) as $step) {
			if ('approval' === ($step['id'] ?? '') && in_array(($step['status'] ?? ''), array('approved', 'auto_approved'), true)) {
				return true;
			}
		}
		return false;
	}

	private function normalize_draft(array $data, ?int $postId): array {
		if ('llms_txt_refresh' === ($data['recipe_id'] ?? '') && !empty($data['llms_txt'])) {
			return array('recipe_id' => 'llms_txt_refresh', 'llms_txt' => sanitize_textarea_field((string) $data['llms_txt']));
		}
		$title = sanitize_text_field((string) ($data['title'] ?? ''));
		$description = sanitize_textarea_field((string) ($data['meta_description'] ?? $data['description'] ?? ''));
		$keyword = sanitize_text_field((string) ($data['focus_keyword'] ?? ($data['focus_keywords'][0] ?? '')));
		if ('' === $title && '' === $description && '' === $keyword) {
			return array();
		}
		return array_filter(array(
			'post_id' => $postId,
			'title' => $title,
			'description' => $description,
			'focus_keyword' => $keyword,
		), static fn($value): bool => null !== $value && '' !== $value);
	}

	/** @return array<string, string> */
	private function diff_preview(array $draft): array {
		if (!empty($draft['post_id'])) {
			$postId = (int) $draft['post_id'];
			return array(
				'_seoistic_title' => self::diff(PostSeo::title($postId), (string) ($draft['title'] ?? '')),
				'_seoistic_description' => self::diff(PostSeo::description($postId), (string) ($draft['description'] ?? '')),
				'_seoistic_focus_keyword' => self::diff(PostSeo::focus_keyword($postId), (string) ($draft['focus_keyword'] ?? '')),
			);
		}
		if (!empty($draft['llms_txt'])) {
			return array('llms_txt' => self::diff((string) get_option('seoistic_llms_txt_content', ''), (string) $draft['llms_txt']));
		}
		return array();
	}

	private static function diff(string $old, string $new): string {
		if ($old === $new) {
			return __('No change', 'seoistic');
		}
		return sprintf("%s\n→ %s", $old ?: __('(empty)', 'seoistic'), $new ?: __('(empty)', 'seoistic'));
	}

	/** @return array<int, int> */
	private function target_posts(string $recipeId, ?int $postId): array {
		if ($postId) {
			return array($postId);
		}
		$limit = 'weekly_audit_report' === $recipeId ? 100 : 20;
		return array_map('intval', get_posts(array(
			'post_type' => array_values(get_post_types(array('public' => true))),
			'post_status' => 'publish',
			'fields' => 'ids',
			'posts_per_page' => $limit,
			'orderby' => 'modified',
			'order' => 'DESC',
		)));
	}

	private function notification_email(): string {
		$settings = get_option(self::SETTINGS_OPTION, array());
		$email = is_array($settings) ? sanitize_email((string) ($settings['notify_email'] ?? '')) : '';
		return $email ?: (string) get_option('admin_email', '');
	}

	private function persist(array $run): void {
		$runs = $this->history();
		$index = false;
		foreach ($runs as $candidateIndex => $candidate) {
			if (($candidate['run_id'] ?? '') === $run['run_id']) {
				$index = $candidateIndex;
				break;
			}
		}
		if (false === $index) {
			array_unshift($runs, $run);
			$runs = array_slice($runs, 0, self::MAX_HISTORY);
		} else {
			$runs[$index] = $run;
		}
		self::update($runs, self::HISTORY_OPTION);
	}

	private static function update(array $value, string $option = self::RECIPES_OPTION): void {
		update_option($option, $value, false);
	}

	private static function hash_email(string $email): string {
		return substr(hash('sha256', $email . wp_salt('auth')), 0, 12);
	}

	private function draft_summary(array $draft): array {
		unset($draft['llms_txt']);
		return $draft;
	}
}
