<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Admin;

use Wpistic\Seoistic\Addon\BusinessAutomatorModule;
use Wpistic\Seoistic\BusinessAutomator\RecipeEngine;

final class BusinessAutomatorPage {

	private RecipeEngine $engine;

	public function __construct(?RecipeEngine $engine = null) {
		$this->engine = $engine ?? new RecipeEngine();
	}

	public function register(): void {
		add_action('admin_menu', array($this, 'add_submenu'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
	}

	public function add_submenu(): void {
		add_submenu_page(
			'seoistic',
			__('Business Automator', 'seoistic'),
			__('Business Automator', 'seoistic'),
			'manage_options',
			'seoistic-business-automator',
			array($this, 'render_page')
		);
	}

	public function enqueue_assets(): void {
		if (!isset($_GET['page']) || 'seoistic-business-automator' !== $_GET['page']) {
			return;
		}
		wp_enqueue_style('seoistic-admin');
		wp_enqueue_script('seoistic-business-automator', plugins_url('assets/js/business-automator.js', SEOISTIC_FILE), array('wp-api-fetch'), SEOISTIC_VERSION, true);
		wp_add_inline_script('seoistic-business-automator', sprintf(
			'window.SeoisticAutomator=%s;',
			wp_json_encode(array(
				'nonce' => wp_create_nonce(BusinessAutomatorModule::NONCE_ACTION),
				'recipes' => array_values($this->engine->recipes()),
				'history' => $this->engine->history(),
				'settings' => get_option(RecipeEngine::SETTINGS_OPTION, array('notify_email' => '')),
				'strings' => array(
					'run' => __('Run', 'seoistic'),
					'approve' => __('Approve & apply', 'seoistic'),
					'approved' => __('Approved', 'seoistic'),
					'awaiting' => __('Awaiting approval', 'seoistic'),
					'confirm' => __('Apply this approved automation change?', 'seoistic'),
					'error' => __('Error', 'seoistic'),
				),
			))
		), 'before');
	}

	public function render_page(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Unauthorized', 'seoistic'));
		}

		$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'recipes';
		$tabs = array(
			'recipes' => __('Recipes', 'seoistic'),
			'history' => __('Run history', 'seoistic'),
		);
		?>
		<div class="wrap seoistic-page">
			<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
			<p><?php esc_html_e('Automation writes remain in the approval queue unless a recipe explicitly opts into auto-apply. Every approval and change is recorded.', 'seoistic'); ?></p>
			<nav class="nav-tab-wrapper" aria-label="<?php echo esc_attr__('Business Automator sections', 'seoistic'); ?>">
				<?php foreach ($tabs as $slug => $label) : ?>
					<a href="<?php echo esc_url(admin_url('admin.php?page=seoistic-business-automator&tab=' . $slug)); ?>" class="nav-tab<?php echo $tab === $slug ? ' nav-tab-active' : ''; ?>"><?php echo esc_html($label); ?></a>
				<?php endforeach; ?>
			</nav>
			<div id="seoistic-automator-root" data-tab="<?php echo esc_attr($tab); ?>"></div>
		</div>
		<?php
	}
}
