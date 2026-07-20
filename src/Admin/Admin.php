<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Admin;

use Wpistic\Seoistic\Core\DashboardMetrics;
use Wpistic\Seoistic\Core\Links;
use Wpistic\Seoistic\Core\Scorer;
use Wpistic\Seoistic\License\LicenseClient;
use Wpistic\Seoistic\License\Plans;
use Wpistic\Seoistic\Module\Entitlement;
use Wpistic\Seoistic\Module\ModuleRegistry;

/**
 * SEOISTIC admin: dashboard, the Addons screen, the pricing/upgrade screen, and
 * settings. Addon submenus (Redirects, Import, AI Tools) attach to this menu at
 * higher priority. Rendering goes through Admin\View for the shared WPistic
 * CRM-style chrome (top bar, tabs, cards, score rings, badges).
 */
final class Admin {

	/**
	 * Category tags per addon id, used by the Addons screen filter pills. Purely a
	 * display concern — entitlement/tier still comes from the Module itself.
	 *
	 * @var array<string, list<string>>
	 */
	private const CATEGORIES = array(
		'schema'         => array( 'technical' ),
		'local'          => array( 'local' ),
		'woocommerce'    => array( 'woocommerce' ),
		'image'          => array( 'technical' ),
		'redirects'      => array( 'technical' ),
		'links'          => array( 'technical' ),
		'sitemap_extras' => array( 'technical' ),
		'social'         => array( 'technical' ),
		'migration'      => array( 'technical' ),
		'audit'          => array( 'technical' ),
		'indexistic'     => array( 'indexing', 'technical' ),
		'ai'             => array( 'ai' ),
		'ai_search'      => array( 'ai', 'technical' ),
		'rank_tracker'   => array(),
		'schema_pro'     => array( 'technical' ),
		'performance'    => array( 'technical' ),
		'gsc'            => array( 'technical' ),
	);

	public function __construct( private ModuleRegistry $registry ) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 9 );
		add_action( 'admin_post_seoistic_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_seoistic_toggle_modules', array( $this, 'toggle' ) );
		add_action( 'wp_ajax_seoistic_audit_batch', array( $this, 'ajax_audit_batch' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function assets( string $hook ): void {
		$is_seoistic_page = false !== strpos( $hook, 'seoistic' );
		$is_edit_screen   = in_array( $hook, array( 'post.php', 'post-new.php' ), true );
		$is_list_screen   = 'edit.php' === $hook; // Score-column rings on list tables.

		if ( ! $is_seoistic_page && ! $is_edit_screen && ! $is_list_screen ) {
			return;
		}

		wp_enqueue_style( 'seoistic-admin', SEOISTIC_URL . 'assets/css/admin.css', array(), SEOISTIC_VERSION );

		if ( $is_list_screen ) {
			return; // List tables only need the styles.
		}

		wp_enqueue_script( 'seoistic-admin', SEOISTIC_URL . 'assets/js/admin.js', array(), SEOISTIC_VERSION, true );
		wp_localize_script(
			'seoistic-admin',
			'SeoisticAdmin',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'restUrl'     => esc_url_raw( rest_url( 'seoistic/v1' ) ),
				'restNonce'   => wp_create_nonce( 'wp_rest' ),
				'importNonce' => wp_create_nonce( 'seoistic_import_batch' ),
				'auditNonce'  => wp_create_nonce( 'seoistic_audit_batch' ),
				'commands'    => $is_seoistic_page ? View::palette_commands() : array(),
				'i18n'        => array(
					'copied'        => __( 'Copied!', 'seoistic' ),
					'importFailed'  => __( 'Import failed.', 'seoistic' ),
					'noResults'     => __( 'No results found.', 'seoistic' ),
					'searching'     => __( 'Searching…', 'seoistic' ),
					'screens'       => __( 'Pages & actions', 'seoistic' ),
					'content'       => __( 'Content', 'seoistic' ),
					'notScored'     => __( 'Not scored', 'seoistic' ),
					'searchFailed'  => __( 'Search failed — check your connection.', 'seoistic' ),
				),
			)
		);

		if ( $is_edit_screen ) {
			wp_enqueue_script( 'seoistic-ai', SEOISTIC_URL . 'assets/js/ai.js', array( 'seoistic-admin' ), SEOISTIC_VERSION, true );
			wp_localize_script(
				'seoistic-ai',
				'SeoisticEditor',
				array(
					'i18n' => array(
						'live'          => __( 'Live analysis', 'seoistic' ),
						'analyzing'     => __( 'Analyzing…', 'seoistic' ),
						'upToDate'      => __( 'Live analysis · up to date', 'seoistic' ),
						'analysisError' => __( 'Analysis failed — will retry on next change.', 'seoistic' ),
						'applied'       => __( 'Suggestion applied.', 'seoistic' ),
						'undone'        => __( 'Change undone.', 'seoistic' ),
						'dismissed'     => __( 'Suggestion dismissed.', 'seoistic' ),
						'before'        => __( 'Before', 'seoistic' ),
						'after'         => __( 'After', 'seoistic' ),
						'apply'         => __( 'Apply', 'seoistic' ),
						'dismiss'       => __( 'Dismiss', 'seoistic' ),
						'undo'          => __( 'Undo', 'seoistic' ),
						'aiSuggestion'  => __( 'AI suggestion', 'seoistic' ),
						'aiFailed'      => __( 'AI request failed.', 'seoistic' ),
						'empty'         => __( '(empty)', 'seoistic' ),
						'priorityFixes' => __( 'Priority fixes', 'seoistic' ),
						'passedChecks'  => __( 'Passed checks', 'seoistic' ),
						'allPassing'    => __( 'Nothing to fix — all checks pass.', 'seoistic' ),
						'noChange'      => __( 'AI returned no new suggestion for this field.', 'seoistic' ),
						'bandExcellent' => __( 'Excellent', 'seoistic' ),
						'bandGood'      => __( 'Good', 'seoistic' ),
						'bandNeedsWork' => __( 'Needs work', 'seoistic' ),
						'bandCritical'  => __( 'Critical', 'seoistic' ),
					),
				)
			);
		}
	}

	public function menu(): void {
		add_menu_page( 'SEOISTIC', 'SEOISTIC', 'manage_options', 'seoistic', array( $this, 'dashboard' ), 'dashicons-chart-line', 58 );
		add_submenu_page( 'seoistic', __( 'Dashboard', 'seoistic' ), __( 'Dashboard', 'seoistic' ), 'manage_options', 'seoistic', array( $this, 'dashboard' ) );
		add_submenu_page( 'seoistic', __( 'Addons', 'seoistic' ), __( 'Addons', 'seoistic' ), 'manage_options', 'seoistic-addons', array( $this, 'addons' ) );
		add_submenu_page( 'seoistic', __( 'Pricing', 'seoistic' ), __( 'Upgrade', 'seoistic' ), 'manage_options', 'seoistic-pricing', array( $this, 'pricing' ) );
		add_submenu_page( 'seoistic', __( 'Settings', 'seoistic' ), __( 'Settings', 'seoistic' ), 'manage_options', 'seoistic-settings', array( $this, 'settings' ) );
	}

	/* -------------------------------------------------------------- */
	/* Dashboard                                                        */
	/* -------------------------------------------------------------- */

	public function dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$metrics = DashboardMetrics::snapshot( $this->registry );
		$history = DashboardMetrics::history();

		$health       = null === $metrics['seo_health'] ? null : (int) $metrics['seo_health'];
		$scored_count = (int) ( $metrics['scored_count'] ?? 0 );
		$last_scan    = array() !== $history ? (string) $history[ count( $history ) - 1 ]['time'] : '';
		$prev_score   = count( $history ) >= 2 ? (int) $history[ count( $history ) - 2 ]['score'] : null;

		View::header( 'seoistic', __( 'Dashboard', 'seoistic' ) );
		?>
		<section class="seoistic-hero" aria-label="<?php esc_attr_e( 'SEO health overview', 'seoistic' ); ?>">
			<div class="seoistic-hero-score">
				<?php echo View::score_ring( $health ?? 0, 'lg', null !== $health ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<div class="seoistic-hero-meta">
					<p class="seoistic-hero-label"><?php esc_html_e( 'SEO health score', 'seoistic' ); ?></p>
					<p class="seoistic-hero-status">
						<?php
						if ( null === $health ) {
							esc_html_e( 'Not yet analyzed', 'seoistic' );
						} else {
							$tone = View::score_tone( $health );
							echo esc_html( $health >= 90 ? __( 'Excellent', 'seoistic' ) : ( 'good' === $tone ? __( 'Good', 'seoistic' ) : ( 'warn' === $tone ? __( 'Needs work', 'seoistic' ) : __( 'Critical', 'seoistic' ) ) ) );
						}
						?>
						<?php if ( null !== $health && null !== $prev_score ) : ?>
							<?php $delta = $health - $prev_score; ?>
							<span class="seoistic-hero-delta <?php echo esc_attr( $delta > 0 ? 'is-up' : ( $delta < 0 ? 'is-down' : 'is-flat' ) ); ?>">
								<?php echo esc_html( ( $delta > 0 ? '+' : '' ) . $delta ); ?>
								<span class="screen-reader-text"><?php esc_html_e( 'since previous scan', 'seoistic' ); ?></span>
							</span>
						<?php endif; ?>
					</p>
					<p class="seoistic-hero-sub">
						<?php
						if ( null === $health ) {
							esc_html_e( 'Run a site audit to score your published content — the score is calculated from real on-page checks, never estimated.', 'seoistic' );
						} else {
							/* translators: %d: number of scored posts. */
							echo esc_html( sprintf( _n( 'Based on %d analyzed page.', 'Based on %d analyzed pages.', $scored_count, 'seoistic' ), $scored_count ) );
							if ( '' !== $last_scan ) {
								echo '<br>' . esc_html(
									sprintf(
										/* translators: %s: human-readable time difference. */
										__( 'Last full scan %s ago.', 'seoistic' ),
										human_time_diff( (int) mysql2date( 'U', $last_scan ), (int) current_time( 'timestamp' ) )
									)
								);
							}
						}
						?>
					</p>
				</div>
			</div>
			<div class="seoistic-hero-actions">
				<button type="button" id="seoistic-run-audit" class="seoistic-btn seoistic-btn-primary"><span class="dashicons dashicons-shield" aria-hidden="true"></span> <?php esc_html_e( 'Run Site Audit', 'seoistic' ); ?></button>
				<a class="seoistic-btn seoistic-btn-gold" href="<?php echo esc_url( admin_url( 'admin.php?page=seoistic-ai-tools' ) ); ?>"><span class="dashicons dashicons-superhero" aria-hidden="true"></span> <?php esc_html_e( 'Optimize with AI', 'seoistic' ); ?></a>
				<a class="seoistic-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=seoistic-content' ) ); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span> <?php esc_html_e( 'Review Content', 'seoistic' ); ?></a>
			</div>
		</section>
		<div id="seoistic-audit-progress" class="seoistic-tool-progress"><div class="seoistic-tool-progress-bar"></div></div>
		<div id="seoistic-audit-result" class="seoistic-tool-result" role="status"></div>

		<div class="seoistic-quick-actions">
			<a class="seoistic-btn seoistic-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=seoistic-ai-tools&tool=llms' ) ); ?>"><span class="dashicons dashicons-media-text" aria-hidden="true"></span> <?php esc_html_e( 'Generate llms.txt', 'seoistic' ); ?></a>
			<a class="seoistic-btn seoistic-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=seoistic-content-health' ) ); ?>"><span class="dashicons dashicons-editor-unlink" aria-hidden="true"></span> <?php esc_html_e( 'Scan Internal Links', 'seoistic' ); ?></a>
			<?php if ( $this->page_registered( 'seoistic-indexistic' ) ) : ?>
				<a class="seoistic-btn seoistic-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=seoistic-indexistic' ) ); ?>"><span class="dashicons dashicons-search" aria-hidden="true"></span> <?php esc_html_e( 'Review Index Status', 'seoistic' ); ?></a>
			<?php endif; ?>
			<a class="seoistic-btn seoistic-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=seoistic-settings' ) ); ?>"><span class="dashicons dashicons-admin-settings" aria-hidden="true"></span> <?php esc_html_e( 'Open Settings', 'seoistic' ); ?></a>
		</div>

		<div class="seoistic-section-title"><?php esc_html_e( 'Optimization roadmap', 'seoistic' ); ?></div>
		<?php $this->render_roadmap( $metrics ); ?>

		<div class="seoistic-section-title"><?php esc_html_e( 'Site metrics', 'seoistic' ); ?></div>
		<div class="seoistic-cards">
			<?php
			View::card( 'yes', (string) $metrics['indexed_pages'], __( 'Indexable Pages', 'seoistic' ), 'good' );
			View::card( 'warning', (string) $metrics['missing_meta'], __( 'Missing Meta Descriptions', 'seoistic' ), $metrics['missing_meta'] > 0 ? 'warn' : 'good' );
			View::card( 'admin-network', (string) $metrics['missing_keyword'], __( 'Without Focus Keyword', 'seoistic' ), $metrics['missing_keyword'] > 0 ? 'warn' : 'good' );
			View::card( 'editor-unlink', null === $metrics['errors_404'] ? '—' : (string) $metrics['errors_404'], __( '404 Errors', 'seoistic' ), null !== $metrics['errors_404'] && $metrics['errors_404'] > 0 ? 'warn' : 'good', null === $metrics['errors_404'] ? __( 'Enable Redirects addon', 'seoistic' ) : '' );
			View::card( 'randomize', (string) $metrics['redirects_count'], __( 'Active Redirects', 'seoistic' ) );
			?>
		</div>

		<div class="seoistic-section-title"><?php esc_html_e( 'Technical SEO', 'seoistic' ); ?></div>
		<div class="seoistic-cards">
			<?php
			View::card( 'welcome-widgets-menus', $metrics['schema_enabled'] ? __( 'Enabled', 'seoistic' ) : __( 'Disabled', 'seoistic' ), __( 'Schema Markup', 'seoistic' ), $metrics['schema_enabled'] ? 'good' : 'bad' );
			View::card( 'networking', $metrics['sitemap_enabled'] ? __( 'Live', 'seoistic' ) : __( 'Disabled', 'seoistic' ), __( 'XML Sitemap', 'seoistic' ), $metrics['sitemap_enabled'] ? 'good' : 'bad' );
			View::card( 'superhero', $metrics['ai_configured'] ? __( 'Configured', 'seoistic' ) : ( $metrics['ai_enabled'] ? __( 'Key missing', 'seoistic' ) : __( 'Off', 'seoistic' ) ), __( 'AI Optimization', 'seoistic' ), $metrics['ai_configured'] ? 'good' : 'warn' );
			View::card( 'performance', __( 'Premium', 'seoistic' ), __( 'Core Web Vitals', 'seoistic' ), 'neutral', __( 'Upgrade to unlock', 'seoistic' ), 'premium' );
			?>
		</div>
		<?php
		View::footer();
	}

	private function page_registered( string $slug ): bool {
		global $submenu;
		foreach ( (array) ( $submenu['seoistic'] ?? array() ) as $item ) {
			if ( isset( $item[2] ) && $item[2] === $slug ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The optimization roadmap: real per-issue counts from DashboardMetrics
	 * grouped by severity, each with a drill-down into the filtered content
	 * inventory (or the owning tool). Impact levels are qualitative — no
	 * invented traffic predictions.
	 *
	 * @param array<string, mixed> $metrics
	 */
	private function render_roadmap( array $metrics ): void {
		$content_url = admin_url( 'admin.php?page=seoistic-content' );
		$ai_url      = admin_url( 'admin.php?page=seoistic-ai-tools' );

		$definitions = array(
			array(
				'severity' => 'critical',
				'count'    => (int) ( $metrics['missing_meta'] ?? 0 ),
				'title'    => __( 'Meta descriptions missing', 'seoistic' ),
				'desc'     => __( 'Search engines improvise snippets for pages without a meta description. Write one per page, or bulk-generate with AI.', 'seoistic' ),
				'passed'   => __( 'Every published page has a meta description.', 'seoistic' ),
				'impact'   => __( 'High impact', 'seoistic' ),
				'review'   => add_query_arg( 'seo_filter', 'missing_meta', $content_url ),
				'ai'       => $ai_url,
			),
			array(
				'severity' => 'critical',
				'count'    => (int) ( $metrics['low_score_count'] ?? 0 ),
				'title'    => __( 'Pages scoring below 50', 'seoistic' ),
				'desc'     => __( 'These pages fail more than half of the on-page checks — start here for the biggest score gains.', 'seoistic' ),
				'passed'   => __( 'No analyzed page scores below 50.', 'seoistic' ),
				'impact'   => __( 'High impact', 'seoistic' ),
				'review'   => add_query_arg( 'seo_filter', 'critical', $content_url ),
			),
			array(
				'severity' => 'high',
				'count'    => (int) ( $metrics['missing_keyword'] ?? 0 ),
				'title'    => __( 'Focus keywords missing', 'seoistic' ),
				'desc'     => __( 'Without a focus keyword, keyword placement checks can’t run for these pages.', 'seoistic' ),
				'passed'   => __( 'Every published page has a focus keyword.', 'seoistic' ),
				'impact'   => __( 'Medium impact', 'seoistic' ),
				'review'   => add_query_arg( 'seo_filter', 'missing_keyword', $content_url ),
			),
			array(
				'severity' => 'high',
				'count'    => null === ( $metrics['errors_404'] ?? null ) ? null : (int) $metrics['errors_404'],
				'title'    => __( '404 errors logged', 'seoistic' ),
				'desc'     => __( 'Visitors and crawlers are hitting dead URLs — add redirects for the ones that matter.', 'seoistic' ),
				'passed'   => __( 'No 404 errors logged.', 'seoistic' ),
				'impact'   => __( 'Medium impact', 'seoistic' ),
				'review'   => admin_url( 'admin.php?page=seoistic-redirects' ),
			),
			array(
				'severity' => 'medium',
				'count'    => (int) ( $metrics['missing_title'] ?? 0 ),
				'title'    => __( 'Custom SEO titles missing', 'seoistic' ),
				'desc'     => __( 'These pages fall back to the post title. A tuned SEO title usually lifts click-through.', 'seoistic' ),
				'passed'   => __( 'Every published page has a custom SEO title.', 'seoistic' ),
				'impact'   => __( 'Medium impact', 'seoistic' ),
				'review'   => add_query_arg( 'seo_filter', 'missing_title', $content_url ),
			),
			array(
				'severity' => 'medium',
				'count'    => (int) ( $metrics['unscored_count'] ?? 0 ),
				'title'    => __( 'Content never analyzed', 'seoistic' ),
				'desc'     => __( 'These pages have no SEO score yet — run a site audit to include them.', 'seoistic' ),
				'passed'   => __( 'All published content has been analyzed.', 'seoistic' ),
				'impact'   => __( 'Low impact', 'seoistic' ),
				'run_audit' => true,
			),
		);

		$groups = array(
			'critical' => array( 'label' => __( 'Critical', 'seoistic' ), 'icon' => 'warning', 'items' => array() ),
			'high'     => array( 'label' => __( 'High', 'seoistic' ), 'icon' => 'flag', 'items' => array() ),
			'medium'   => array( 'label' => __( 'Medium', 'seoistic' ), 'icon' => 'info-outline', 'items' => array() ),
			'passed'   => array( 'label' => __( 'Passed', 'seoistic' ), 'icon' => 'yes-alt', 'items' => array() ),
		);

		foreach ( $definitions as $item ) {
			if ( null === $item['count'] ) {
				continue; // Data source unavailable (addon disabled) — never fake a zero.
			}
			if ( $item['count'] > 0 ) {
				$groups[ $item['severity'] ]['items'][] = $item;
			} else {
				$groups['passed']['items'][] = $item;
			}
		}

		echo '<div class="seoistic-roadmap">';
		$first_open_done = false;
		foreach ( $groups as $severity => $group ) {
			if ( array() === $group['items'] ) {
				continue;
			}
			$is_open = ! $first_open_done && 'passed' !== $severity;
			if ( $is_open ) {
				$first_open_done = true;
			}
			$body_id = 'seoistic-roadmap-' . $severity;
			echo '<div class="seoistic-roadmap-group is-' . esc_attr( $severity ) . ( $is_open ? ' is-open' : '' ) . '">';
			echo '<button type="button" class="seoistic-roadmap-head" data-seoistic-roadmap-toggle aria-expanded="' . ( $is_open ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $body_id ) . '">';
			echo '<span class="dashicons dashicons-' . esc_attr( $group['icon'] ) . '" aria-hidden="true"></span>';
			echo esc_html( $group['label'] );
			echo '<span class="seoistic-roadmap-count">' . count( $group['items'] ) . '</span>';
			echo '<span class="dashicons dashicons-arrow-down-alt2 seoistic-roadmap-chevron" aria-hidden="true"></span>';
			echo '</button>';
			echo '<div class="seoistic-roadmap-body" id="' . esc_attr( $body_id ) . '">';
			foreach ( $group['items'] as $item ) {
				$this->render_roadmap_item( $item, 'passed' === $severity );
			}
			echo '</div></div>';
		}
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private function render_roadmap_item( array $item, bool $passed ): void {
		echo '<div class="seoistic-roadmap-item">';
		echo '<div class="seoistic-roadmap-item-text">';
		echo '<p class="seoistic-roadmap-item-title">' . esc_html( (string) $item['title'] ) . '</p>';
		echo '<p class="seoistic-roadmap-item-desc">' . esc_html( $passed ? (string) $item['passed'] : (string) $item['desc'] ) . '</p>';
		echo '</div>';
		echo '<div class="seoistic-roadmap-item-meta">';
		if ( $passed ) {
			echo View::badge( __( 'Passed', 'seoistic' ), 'good' ); // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			echo View::badge(
				sprintf(
					/* translators: %d: number of affected pages. */
					_n( '%d page', '%d pages', (int) $item['count'], 'seoistic' ),
					(int) $item['count']
				),
				'critical' === ( $item['severity'] ?? '' ) ? 'bad' : 'warn'
			); // phpcs:ignore WordPress.Security.EscapeOutput
			echo View::badge( (string) $item['impact'], 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput
			if ( ! empty( $item['run_audit'] ) ) {
				echo '<button type="button" class="seoistic-btn seoistic-btn-sm" data-seoistic-run-audit>' . esc_html__( 'Run Site Audit', 'seoistic' ) . '</button>';
			}
			if ( ! empty( $item['review'] ) ) {
				echo '<a class="seoistic-btn seoistic-btn-sm" href="' . esc_url( (string) $item['review'] ) . '">' . esc_html__( 'Review', 'seoistic' ) . '</a>';
			}
			if ( ! empty( $item['ai'] ) ) {
				echo '<a class="seoistic-btn seoistic-btn-sm seoistic-btn-gold" href="' . esc_url( (string) $item['ai'] ) . '">' . esc_html__( 'Fix with AI', 'seoistic' ) . '</a>';
			}
		}
		echo '</div></div>';
	}

	/**
	 * Batched, capability-checked score recalculation for the whole site. Runs in
	 * small chunks over admin-ajax so a large content library never times out a
	 * single request — this is the only place a site-wide audit executes.
	 */
	public function ajax_audit_batch(): void {
		check_ajax_referer( 'seoistic_audit_batch', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'seoistic' ) ), 403 );
		}

		$offset     = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$batch_size = 20;

		$ids = get_posts(
			array(
				'post_type'      => get_post_types( array( 'public' => true ) ),
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'offset'         => $offset,
				'posts_per_page' => $batch_size,
			)
		);

		foreach ( $ids as $post_id ) {
			Scorer::recalculate( (int) $post_id );
		}

		$total_public = 0;
		foreach ( get_post_types( array( 'public' => true ) ) as $post_type ) {
			$total_public += (int) ( wp_count_posts( $post_type )->publish ?? 0 );
		}
		$next_offset = $offset + count( $ids );
		$done        = count( $ids ) < $batch_size;

		if ( $done ) {
			DashboardMetrics::flush();
			DashboardMetrics::record_history( $this->registry );
		}

		wp_send_json_success(
			array(
				'imported'    => count( $ids ),
				'next_offset' => $next_offset,
				'percent'     => $total_public > 0 ? min( 100, ( $next_offset / $total_public ) * 100 ) : 100,
				'done'        => $done,
				/* translators: %d: number of posts scored. */
				'message'     => sprintf( __( 'Audit complete — %d posts scored.', 'seoistic' ), $next_offset ),
			)
		);
	}

	/* -------------------------------------------------------------- */
	/* Addons                                                           */
	/* -------------------------------------------------------------- */

	public function addons(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		View::header( 'seoistic-addons', __( 'Addons', 'seoistic' ), __( 'Turn free addons on or off, and see what unlocks with a SEOISTIC plan.', 'seoistic' ) );
		?>
		<div class="seoistic-toolbar">
			<div class="seoistic-filters">
				<button type="button" class="seoistic-filter is-active" data-filter="all"><?php esc_html_e( 'All', 'seoistic' ); ?></button>
				<button type="button" class="seoistic-filter" data-filter="free"><?php esc_html_e( 'Free', 'seoistic' ); ?></button>
				<button type="button" class="seoistic-filter" data-filter="premium"><?php esc_html_e( 'Premium', 'seoistic' ); ?></button>
				<button type="button" class="seoistic-filter" data-filter="ai"><?php esc_html_e( 'AI', 'seoistic' ); ?></button>
				<button type="button" class="seoistic-filter" data-filter="indexing"><?php esc_html_e( 'Indexing', 'seoistic' ); ?></button>
				<button type="button" class="seoistic-filter" data-filter="technical"><?php esc_html_e( 'Technical SEO', 'seoistic' ); ?></button>
				<button type="button" class="seoistic-filter" data-filter="woocommerce"><?php esc_html_e( 'WooCommerce', 'seoistic' ); ?></button>
				<button type="button" class="seoistic-filter" data-filter="local"><?php esc_html_e( 'Local SEO', 'seoistic' ); ?></button>
			</div>
			<div class="seoistic-search">
				<input type="search" id="seoistic-addon-search" placeholder="<?php esc_attr_e( 'Search addons…', 'seoistic' ); ?>">
			</div>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="seoistic_toggle_modules">
			<?php wp_nonce_field( 'seoistic_modules' ); ?>
			<div class="seoistic-addons-grid">
				<?php foreach ( $this->registry->all() as $module ) : ?>
					<?php $this->render_addon_card( $module ); ?>
				<?php endforeach; ?>
			</div>
			<p><button type="submit" class="seoistic-btn seoistic-btn-primary"><?php esc_html_e( 'Save addons', 'seoistic' ); ?></button></p>
		</form>
		<?php
		View::footer();
	}

	private function render_addon_card( \Wpistic\Seoistic\Module\Module $module ): void {
		$is_premium = 'premium' === $module->tier();
		$is_locked  = $is_premium && ! Entitlement::can( $module->id(), $module->tier() );
		$categories = array_merge( array( $is_premium ? 'premium' : 'free' ), self::CATEGORIES[ $module->id() ] ?? array() );
		$enabled    = $this->registry->is_enabled( $module ) && ! ( 'coming_soon' === $module->status() );

		echo '<div class="seoistic-addon' . ( $is_locked ? ' is-locked' : '' ) . '" data-categories="' . esc_attr( implode( ',', $categories ) ) . '" data-search="' . esc_attr( strtolower( $module->name() . ' ' . $module->description() ) ) . '">';
		echo '<div class="seoistic-addon-head"><strong>' . esc_html( $module->name() ) . '</strong>';
		echo View::badge( $is_premium ? ucfirst( Plans::addon_plan( $module->id() ) ) : __( 'Free', 'seoistic' ), $is_premium ? Plans::addon_plan( $module->id() ) : 'free' );
		echo '</div>';
		echo '<p class="seoistic-addon-desc">' . esc_html( $module->description() ) . '</p>';

		echo '<div class="seoistic-addon-foot">';
		if ( $is_locked ) {
			echo '<span class="seoistic-badge neutral">' . ( 'coming_soon' === $module->status() ? esc_html__( 'Coming soon', 'seoistic' ) : esc_html__( 'Locked', 'seoistic' ) ) . '</span>';
			echo '<a class="seoistic-btn seoistic-btn-gold seoistic-btn-sm" href="' . esc_url( admin_url( 'admin.php?page=seoistic-pricing' ) ) . '">' . esc_html__( 'Upgrade', 'seoistic' ) . '</a>';
		} elseif ( $is_premium ) {
			echo '<span class="seoistic-badge good">' . esc_html__( 'Unlocked', 'seoistic' ) . '</span>';
			echo '<label class="seoistic-switch"><input type="checkbox" name="modules[]" value="' . esc_attr( $module->id() ) . '" data-module="' . esc_attr( $module->id() ) . '" ' . checked( $enabled, true, false ) . '><span class="seoistic-switch-track"></span></label>';
		} else {
			echo '<span class="seoistic-badge ' . ( $enabled ? 'good' : 'neutral' ) . '">' . ( $enabled ? esc_html__( 'Enabled', 'seoistic' ) : esc_html__( 'Disabled', 'seoistic' ) ) . '</span>';
			echo '<label class="seoistic-switch"><input type="checkbox" name="modules[]" value="' . esc_attr( $module->id() ) . '" data-module="' . esc_attr( $module->id() ) . '" ' . checked( $enabled, true, false ) . '><span class="seoistic-switch-track"></span></label>';
		}
		echo '</div>';
		echo '</div>';
	}

	public function toggle(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_modules' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}
		$enabled = isset( $_POST['modules'] ) && is_array( $_POST['modules'] )
			? array_map( 'sanitize_key', wp_unslash( $_POST['modules'] ) )
			: array();

		foreach ( $this->registry->all() as $module ) {
			if ( 'premium' === $module->tier() && ! Entitlement::can( $module->id(), $module->tier() ) ) {
				continue;
			}
			$this->registry->set_enabled( $module->id(), in_array( $module->id(), $enabled, true ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-addons&updated=1' ) );
		exit;
	}

	/* -------------------------------------------------------------- */
	/* Pricing / upgrade                                                */
	/* -------------------------------------------------------------- */

	public function pricing(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$current = Entitlement::plan();
		$client  = new LicenseClient();

		View::header( 'seoistic-pricing', __( 'Upgrade', 'seoistic' ), __( 'See the full plan comparison and manage your subscription on the SEOistic website.', 'seoistic' ) );

		$this->render_plan_summary( $current, $client );
		?>
		<div class="seoistic-section-title"><?php esc_html_e( 'Plan comparison', 'seoistic' ); ?></div>
		<p class="description" style="margin:-8px 0 16px;"><?php esc_html_e( 'For reference only — pricing and features are kept current on the SEOistic website; use "View plans and pricing" above for the latest.', 'seoistic' ); ?></p>
		<div class="seoistic-pricing-grid">
			<?php foreach ( Plans::tiers() as $id => $tier ) : ?>
				<div class="seoistic-plan<?php echo ! empty( $tier['featured'] ) ? ' featured' : ''; ?><?php echo $id === $current ? ' current' : ''; ?>">
					<?php if ( ! empty( $tier['featured'] ) ) : ?><span class="seoistic-plan-flag"><?php esc_html_e( 'Main plan', 'seoistic' ); ?></span><?php endif; ?>
					<h2><?php echo esc_html( $tier['name'] ); ?></h2>
					<p class="seoistic-plan-price"><?php echo 0 === $tier['price'] ? esc_html__( 'Free', 'seoistic' ) : '$' . (int) $tier['price']; ?> <span>/<?php esc_html_e( 'year', 'seoistic' ); ?></span></p>
					<p class="seoistic-plan-sites"><?php echo esc_html( is_int( $tier['sites'] ) ? sprintf( _n( '%d site', '%d sites', $tier['sites'], 'seoistic' ), $tier['sites'] ) : (string) $tier['sites'] ); ?></p>
					<ul>
						<?php foreach ( (array) $tier['features'] as $feature ) : ?><li><?php echo esc_html( $feature ); ?></li><?php endforeach; ?>
					</ul>
					<?php if ( $id === $current ) : ?>
						<span class="seoistic-btn is-disabled"><?php esc_html_e( 'Current plan', 'seoistic' ); ?></span>
					<?php elseif ( 'free' !== $id ) : ?>
						<a class="seoistic-btn <?php echo ! empty( $tier['featured'] ) ? 'seoistic-btn-gold' : 'seoistic-btn-primary'; ?>" href="<?php echo esc_url( (string) apply_filters( 'seoistic_upgrade_url', Links::pricing_url(), $id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade', 'seoistic' ); ?></a>
					<?php else : ?>
						<span class="seoistic-btn is-disabled"><?php esc_html_e( 'Current plan', 'seoistic' ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $tier['note'] ) ) : ?><p class="seoistic-plan-note"><?php echo esc_html( $tier['note'] ); ?></p><?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="seoistic-section-title"><?php esc_html_e( 'Lifetime deals — limited launch offer', 'seoistic' ); ?></div>
		<div class="seoistic-ltd-grid">
			<?php foreach ( Plans::lifetime() as $ltd_slug => $ltd ) : ?>
				<?php $ltd_slug = is_string( $ltd_slug ) ? $ltd_slug : sanitize_title( $ltd['name'] ); ?>
				<div class="seoistic-ltd-card">
					<h3><?php echo esc_html( $ltd['name'] ); ?></h3>
					<p class="seoistic-ltd-price">$<?php echo (int) $ltd['price']; ?> <span style="font-size:12px;font-weight:500;color:var(--seoistic-muted);"><?php esc_html_e( 'one-time', 'seoistic' ); ?></span></p>
					<p class="seoistic-ltd-meta"><?php echo esc_html( sprintf( _n( '%d site', '%d sites', $ltd['sites'], 'seoistic' ), $ltd['sites'] ) ); ?> · <?php echo esc_html( $ltd['limit'] ); ?></p>
					<p style="margin-top:14px;"><a class="seoistic-btn seoistic-btn-primary" href="<?php echo esc_url( (string) apply_filters( 'seoistic_upgrade_url', Links::pricing_url(), $ltd_slug ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get the deal', 'seoistic' ); ?></a></p>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		View::footer();
	}

	/**
	 * The lightweight upgrade summary: current plan, license status, and one
	 * strong CTA to the canonical marketing pricing page — sits above the
	 * detailed comparison grid so the common case (go upgrade) never requires
	 * scanning card copy that can drift from what's actually offered.
	 */
	private function render_plan_summary( string $current, LicenseClient $client ): void {
		$status_label = $client->is_valid() ? __( 'Active', 'seoistic' ) : ( 'inactive' === $client->status() ? __( 'No license', 'seoistic' ) : __( 'Inactive', 'seoistic' ) );
		$status_tone  = $client->is_valid() ? 'good' : 'neutral';
		?>
		<div class="seoistic-plan-summary">
			<div class="seoistic-plan-summary-info">
				<div class="seoistic-plan-summary-item">
					<span class="seoistic-plan-summary-label"><?php esc_html_e( 'Current plan', 'seoistic' ); ?></span>
					<span class="seoistic-plan-summary-value"><?php echo esc_html( ucfirst( $current ) ); ?></span>
				</div>
				<div class="seoistic-plan-summary-item">
					<span class="seoistic-plan-summary-label"><?php esc_html_e( 'License status', 'seoistic' ); ?></span>
					<span class="seoistic-plan-summary-value"><?php echo View::badge( $status_label, $status_tone ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</div>
			</div>
			<div class="seoistic-plan-summary-actions">
				<a class="seoistic-btn seoistic-btn-gold" href="<?php echo esc_url( (string) apply_filters( 'seoistic_upgrade_url', Links::pricing_url(), 'summary' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Plans and Pricing', 'seoistic' ); ?></a>
				<a class="seoistic-btn seoistic-btn-ghost" href="<?php echo esc_url( Links::account_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Manage Account', 'seoistic' ); ?></a>
				<a class="seoistic-btn seoistic-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=seoistic-license' ) ); ?>"><?php esc_html_e( 'Enter a License Key', 'seoistic' ); ?></a>
			</div>
		</div>
		<?php
	}

	/* -------------------------------------------------------------- */
	/* Settings                                                         */
	/* -------------------------------------------------------------- */

	public function settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$local = get_option( 'seoistic_local', array() );
		$local = is_array( $local ) ? $local : array();

		View::header( 'seoistic-settings', __( 'SEOISTIC Settings', 'seoistic' ) );
		?>
		<div class="seoistic-preview-toggle" style="margin-bottom:18px;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=seoistic-settings&tab=general' ) ); ?>" class="seoistic-btn<?php echo 'general' === $tab ? ' seoistic-btn-primary' : ''; ?>"><?php esc_html_e( 'General', 'seoistic' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=seoistic-settings&tab=ai' ) ); ?>" class="seoistic-btn<?php echo 'ai' === $tab ? ' seoistic-btn-primary' : ''; ?>"><?php esc_html_e( 'AI', 'seoistic' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=seoistic-settings&tab=automation' ) ); ?>" class="seoistic-btn<?php echo 'automation' === $tab ? ' seoistic-btn-primary' : ''; ?>"><?php esc_html_e( 'Automation', 'seoistic' ); ?></a>
		</div>

		<?php if ( 'ai' === $tab && class_exists( '\\Wpistic\\Seoistic\\Admin\\AiSettingsPage' ) ) : ?>
			<?php ( new AiSettingsPage() )->render_fields(); ?>
		<?php elseif ( 'automation' === $tab && class_exists( '\\Wpistic\\Seoistic\\Admin\\AutomationSettingsPage' ) ) : ?>
			<?php ( new AutomationSettingsPage() )->render_fields(); ?>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="seoistic_save_settings">
				<?php wp_nonce_field( 'seoistic_settings' ); ?>
				<div class="seoistic-table-wrap">
				<table class="form-table">
					<tr><th><?php esc_html_e( 'Title separator', 'seoistic' ); ?></th><td><input type="text" name="seoistic_title_sep" value="<?php echo esc_attr( get_option( 'seoistic_title_sep', '–' ) ); ?>" class="small-text"></td></tr>
					<tr><th><?php esc_html_e( 'XML sitemaps', 'seoistic' ); ?></th><td><label><input type="checkbox" name="seoistic_sitemaps" value="1" <?php checked( (int) get_option( 'seoistic_sitemaps', 1 ), 1 ); ?>> <?php esc_html_e( 'Enable', 'seoistic' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'llms.txt', 'seoistic' ); ?></th><td><label><input type="checkbox" name="seoistic_llms_txt" value="1" <?php checked( (int) get_option( 'seoistic_llms_txt', 1 ), 1 ); ?>> <?php esc_html_e( 'Serve /llms.txt for AI search engines', 'seoistic' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'Default share image', 'seoistic' ); ?></th><td><input type="url" name="seoistic_default_share_image" value="<?php echo esc_attr( get_option( 'seoistic_default_share_image', '' ) ); ?>" class="regular-text"></td></tr>
					<tr><th><?php esc_html_e( 'Banned words (audit gate)', 'seoistic' ); ?></th><td><textarea name="seoistic_banned_words" rows="3" class="large-text" placeholder="authentic, curated, world-class"><?php echo esc_textarea( get_option( 'seoistic_banned_words', '' ) ); ?></textarea><p class="description"><?php esc_html_e( 'Comma-separated. The audit gate fails any page using these.', 'seoistic' ); ?></p></td></tr>
					<tr><th><?php esc_html_e( 'llms.txt extra', 'seoistic' ); ?></th><td><textarea name="seoistic_llms_extra" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'seoistic_llms_extra', '' ) ); ?></textarea></td></tr>
				</table>
				</div>

				<h2><?php esc_html_e( 'Local business', 'seoistic' ); ?></h2>
				<div class="seoistic-table-wrap">
				<table class="form-table">
					<tr><th><?php esc_html_e( 'Name', 'seoistic' ); ?></th><td><input type="text" name="seoistic_local[name]" value="<?php echo esc_attr( $local['name'] ?? '' ); ?>" class="regular-text"></td></tr>
					<tr><th><?php esc_html_e( 'Type', 'seoistic' ); ?></th><td><input type="text" name="seoistic_local[type]" value="<?php echo esc_attr( $local['type'] ?? 'LocalBusiness' ); ?>" class="regular-text"></td></tr>
					<tr><th><?php esc_html_e( 'Phone', 'seoistic' ); ?></th><td><input type="text" name="seoistic_local[phone]" value="<?php echo esc_attr( $local['phone'] ?? '' ); ?>" class="regular-text"></td></tr>
					<tr><th><?php esc_html_e( 'Address', 'seoistic' ); ?></th><td><input type="text" name="seoistic_local[address]" value="<?php echo esc_attr( $local['address'] ?? '' ); ?>" class="regular-text"></td></tr>
					<tr><th><?php esc_html_e( 'City / Country', 'seoistic' ); ?></th><td><input type="text" name="seoistic_local[city]" value="<?php echo esc_attr( $local['city'] ?? '' ); ?>" class="regular-text"> <input type="text" name="seoistic_local[country]" value="<?php echo esc_attr( $local['country'] ?? '' ); ?>" class="small-text"></td></tr>
				</table>
				</div>
				<?php submit_button(); ?>
			</form>
		<?php endif; ?>
		<?php
		View::footer();
	}

	public function save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_settings' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}
		update_option( 'seoistic_title_sep', sanitize_text_field( wp_unslash( $_POST['seoistic_title_sep'] ?? '–' ) ) );
		update_option( 'seoistic_sitemaps', isset( $_POST['seoistic_sitemaps'] ) ? 1 : 0 );
		update_option( 'seoistic_llms_txt', isset( $_POST['seoistic_llms_txt'] ) ? 1 : 0 );
		update_option( 'seoistic_default_share_image', esc_url_raw( wp_unslash( $_POST['seoistic_default_share_image'] ?? '' ) ) );
		update_option( 'seoistic_banned_words', sanitize_textarea_field( wp_unslash( $_POST['seoistic_banned_words'] ?? '' ) ) );
		update_option( 'seoistic_llms_extra', sanitize_textarea_field( wp_unslash( $_POST['seoistic_llms_extra'] ?? '' ) ) );

		$local_raw = isset( $_POST['seoistic_local'] ) && is_array( $_POST['seoistic_local'] ) ? wp_unslash( $_POST['seoistic_local'] ) : array(); // phpcs:ignore WordPress.Security.ValidationSanitization
		$local     = array();
		foreach ( $local_raw as $key => $value ) {
			$local[ sanitize_key( $key ) ] = sanitize_text_field( (string) $value );
		}
		update_option( 'seoistic_local', $local );

		DashboardMetrics::flush();

		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=seoistic-settings' ) ) );
		exit;
	}
}
