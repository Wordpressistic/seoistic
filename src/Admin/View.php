<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Admin;

use Wpistic\Seoistic\Module\Entitlement;

/**
 * Shared chrome for every SEOISTIC admin screen — the premium application
 * shell: grouped sidebar navigation, topbar with breadcrumbs and the command
 * palette launcher, metric cards, score rings and badges.
 *
 * The public contract ( header()/footer()/card()/badge()/score_ring()/
 * score_tone()/tabs() ) is stable — every page class and addon module renders
 * through these helpers, so the whole plugin picks up the shell from here.
 */
final class View {

	/** Guards the once-per-page overlays (command palette + toast region). */
	private static bool $overlays_printed = false;

	/**
	 * Grouped navigation registry. Only links whose submenu page is actually
	 * registered (module enabled + entitled) are rendered, so the sidebar and
	 * the command palette can never offer a screen the user can't open.
	 *
	 * @return array<string, array{label:string, items: array<string, array{label:string, icon:string, badge?:string}>}>
	 */
	public static function nav_groups(): array {
		$groups = array(
			'overview' => array(
				'label' => __( 'Overview', 'seoistic' ),
				'items' => array(
					'seoistic'                => array( 'label' => __( 'Dashboard', 'seoistic' ), 'icon' => 'dashboard' ),
					'seoistic-content'        => array( 'label' => __( 'Content', 'seoistic' ), 'icon' => 'admin-page' ),
					'seoistic-content-health' => array( 'label' => __( 'Content Health', 'seoistic' ), 'icon' => 'heart' ),
				),
			),
			'search'   => array(
				'label' => __( 'Search & Indexing', 'seoistic' ),
				'items' => array(
					'seoistic-indexistic' => array( 'label' => __( 'Indexistic', 'seoistic' ), 'icon' => 'search' ),
					'seoistic-gsc'        => array( 'label' => __( 'Search Console', 'seoistic' ), 'icon' => 'chart-area' ),
					'seoistic-redirects'  => array( 'label' => __( 'Redirects', 'seoistic' ), 'icon' => 'randomize' ),
				),
			),
			'ai'       => array(
				'label' => __( 'AI & Automation', 'seoistic' ),
				'items' => array(
					'seoistic-ai-tools'            => array( 'label' => __( 'AI Tools', 'seoistic' ), 'icon' => 'superhero', 'badge' => 'ai' ),
					'seoistic-business-automator'  => array( 'label' => __( 'Business Automator', 'seoistic' ), 'icon' => 'controls-repeat' ),
				),
			),
			'system'   => array(
				'label' => __( 'System', 'seoistic' ),
				'items' => array(
					'seoistic-addons'   => array( 'label' => __( 'Addons', 'seoistic' ), 'icon' => 'admin-plugins' ),
					'seoistic-import'   => array( 'label' => __( 'Import', 'seoistic' ), 'icon' => 'download' ),
					'seoistic-settings' => array( 'label' => __( 'Settings', 'seoistic' ), 'icon' => 'admin-settings' ),
					'seoistic-license'  => array( 'label' => __( 'License', 'seoistic' ), 'icon' => 'admin-network' ),
					'seoistic-pricing'  => array( 'label' => __( 'Upgrade', 'seoistic' ), 'icon' => 'star-filled', 'badge' => 'premium' ),
				),
			),
		);

		/**
		 * Filter the SEOISTIC sidebar navigation groups (addons may add screens).
		 *
		 * @param array $groups
		 */
		return (array) apply_filters( 'seoistic/nav_groups', $groups );
	}

	/**
	 * Flat capability-aware command list for the ⌘K palette, derived from the
	 * same registry as the sidebar.
	 *
	 * @return list<array{label:string, group:string, icon:string, url:string}>
	 */
	public static function palette_commands(): array {
		$commands = array();
		foreach ( self::nav_groups() as $group ) {
			foreach ( $group['items'] as $slug => $item ) {
				if ( ! self::page_exists( $slug ) ) {
					continue;
				}
				$commands[] = array(
					'label' => $item['label'],
					'group' => $group['label'],
					'icon'  => $item['icon'],
					'url'   => admin_url( 'admin.php?page=' . $slug ),
				);
			}
		}
		return $commands;
	}

	/**
	 * Legacy tab registry — kept for backward compatibility with third-party
	 * callers. Feeds from the grouped navigation.
	 *
	 * @return array<string, array{label: string, icon: string}>
	 */
	public static function tabs(): array {
		$tabs = array();
		foreach ( self::nav_groups() as $group ) {
			foreach ( $group['items'] as $slug => $item ) {
				$tabs[ $slug ] = array( 'label' => $item['label'], 'icon' => $item['icon'] );
			}
		}
		return $tabs;
	}

	public static function header( string $active_page, string $title = 'SEOISTIC', string $subtitle = '' ): void {
		$site_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$plan      = Entitlement::plan();

		echo '<div class="wrap seoistic-app">';
		echo '<div class="seoistic-shell">';

		echo '<div class="seoistic-sidebar-overlay" data-seoistic-sidebar-close aria-hidden="true"></div>';

		/* ---------- Sidebar ---------- */
		echo '<aside class="seoistic-sidebar" id="seoistic-sidebar" aria-label="' . esc_attr__( 'SEOISTIC navigation', 'seoistic' ) . '">';
		echo '<a class="seoistic-sidebar-brand" href="' . esc_url( admin_url( 'admin.php?page=seoistic' ) ) . '">';
		echo '<span class="seoistic-sidebar-logo" aria-hidden="true">S</span>';
		echo '<span><span class="seoistic-sidebar-brand-name">SEOistic</span><span class="seoistic-sidebar-brand-site">' . esc_html( $site_host ) . '</span></span>';
		echo '</a>';

		echo '<nav class="seoistic-nav">';
		foreach ( self::nav_groups() as $group ) {
			$links = '';
			foreach ( $group['items'] as $slug => $item ) {
				if ( ! self::page_exists( $slug ) ) {
					continue;
				}
				$is_active = $slug === $active_page;
				$links    .= '<a class="seoistic-nav-link' . ( $is_active ? ' is-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '"' . ( $is_active ? ' aria-current="page"' : '' ) . '>';
				$links    .= '<span class="dashicons dashicons-' . esc_attr( $item['icon'] ) . '" aria-hidden="true"></span>' . esc_html( $item['label'] );
				if ( ! empty( $item['badge'] ) ) {
					$links .= '<span class="seoistic-badge ' . esc_attr( $item['badge'] ) . '">' . ( 'ai' === $item['badge'] ? esc_html__( 'AI', 'seoistic' ) : esc_html__( 'Pro', 'seoistic' ) ) . '</span>';
				}
				$links .= '</a>';
			}
			if ( '' === $links ) {
				continue;
			}
			echo '<div class="seoistic-nav-group">';
			echo '<div class="seoistic-nav-group-label">' . esc_html( $group['label'] ) . '</div>';
			echo $links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
			echo '</div>';
		}
		echo '</nav>';
		echo '</aside>';

		/* ---------- Main column ---------- */
		echo '<div class="seoistic-main">';
		echo '<header class="seoistic-topbar">';
		echo '<button type="button" class="seoistic-menu-toggle" data-seoistic-sidebar-toggle aria-controls="seoistic-sidebar" aria-expanded="false"><span class="dashicons dashicons-menu-alt" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Toggle navigation', 'seoistic' ) . '</span></button>';

		echo '<nav class="seoistic-crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'seoistic' ) . '">';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=seoistic' ) ) . '">SEOistic</a>';
		if ( 'seoistic' !== $active_page && '' !== $title ) {
			echo '<span class="seoistic-crumb-sep" aria-hidden="true">/</span><span class="seoistic-crumb-current">' . esc_html( $title ) . '</span>';
		}
		echo '</nav>';

		echo '<div class="seoistic-topbar-spacer"></div>';

		echo '<button type="button" class="seoistic-cmdk-launcher" data-seoistic-cmdk-open>';
		echo '<span class="dashicons dashicons-search" aria-hidden="true"></span>' . esc_html__( 'Search…', 'seoistic' ) . ' <kbd>' . esc_html__( 'Ctrl K', 'seoistic' ) . '</kbd>';
		echo '</button>';

		echo '<span class="seoistic-topbar-plan">' . self::badge( ucfirst( $plan ), 'business' === $plan ? 'business' : ( 'free' === $plan ? 'neutral' : 'pro' ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
		if ( 'free' === $plan ) {
			echo '<a class="seoistic-btn seoistic-btn-gold seoistic-btn-sm" href="' . esc_url( admin_url( 'admin.php?page=seoistic-pricing' ) ) . '">' . esc_html__( 'Upgrade', 'seoistic' ) . '</a>';
		}
		echo '</header>';

		echo '<div class="seoistic-content">';
		if ( '' !== $title ) {
			// Keep the SR-only h1 first: WordPress core relocates admin notices
			// after the first .wrap heading, which lands them inside the content
			// column instead of breaking the shell layout.
			echo '<h1 class="screen-reader-text">' . esc_html( $title ) . '</h1>';
		}
		echo '<div class="seoistic-page-head">';
		if ( '' !== $title ) {
			echo '<p class="seoistic-page-title" aria-hidden="true">' . esc_html( $title ) . '</p>';
		}
		if ( '' !== $subtitle ) {
			echo '<p class="seoistic-lede">' . esc_html( $subtitle ) . '</p>';
		}
		echo '</div>';
	}

	public static function footer(): void {
		echo '</div>'; // .seoistic-content
		echo '</div>'; // .seoistic-main
		echo '</div>'; // .seoistic-shell
		self::overlays();
		echo '</div>'; // .wrap.seoistic-app
	}

	/**
	 * Command palette dialog + toast live region — printed once per screen.
	 */
	private static function overlays(): void {
		if ( self::$overlays_printed ) {
			return;
		}
		self::$overlays_printed = true;
		?>
		<div class="seoistic-cmdk" id="seoistic-cmdk" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'SEOISTIC command palette', 'seoistic' ); ?>" hidden>
			<div class="seoistic-cmdk-backdrop" data-seoistic-cmdk-close></div>
			<div class="seoistic-cmdk-dialog">
				<div class="seoistic-cmdk-input-row">
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<input type="text" class="seoistic-cmdk-input" id="seoistic-cmdk-input" role="combobox" aria-expanded="true" aria-controls="seoistic-cmdk-list" aria-autocomplete="list" autocomplete="off" spellcheck="false" placeholder="<?php esc_attr_e( 'Search screens, content and actions…', 'seoistic' ); ?>">
				</div>
				<ul class="seoistic-cmdk-list" id="seoistic-cmdk-list" role="listbox" aria-label="<?php esc_attr_e( 'Results', 'seoistic' ); ?>"></ul>
				<div class="seoistic-cmdk-foot">
					<span><kbd>↑</kbd><kbd>↓</kbd> <?php esc_html_e( 'Navigate', 'seoistic' ); ?></span>
					<span><kbd>↵</kbd> <?php esc_html_e( 'Open', 'seoistic' ); ?></span>
					<span><kbd>Esc</kbd> <?php esc_html_e( 'Close', 'seoistic' ); ?></span>
				</div>
			</div>
		</div>
		<div class="seoistic-toasts" id="seoistic-toasts" role="status" aria-live="polite"></div>
		<?php
	}

	private static function page_exists( string $slug ): bool {
		global $submenu;
		if ( ! isset( $submenu['seoistic'] ) ) {
			return true;
		}
		foreach ( $submenu['seoistic'] as $item ) {
			if ( isset( $item[2] ) && $item[2] === $slug ) {
				return true;
			}
		}
		return 'seoistic' === $slug;
	}

	/**
	 * A single animated metric card.
	 */
	public static function card( string $icon, string $number, string $label, string $tone = '', string $badge = '', string $badge_type = 'neutral', string $suffix = '' ): void {
		$tone_class = '' !== $tone ? ' is-' . $tone : '';
		echo '<div class="seoistic-card' . esc_attr( $tone_class ) . '">';
		echo '<div class="seoistic-card-icon"><span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span></div>';
		echo '<div class="seoistic-card-num"><span class="seoistic-counter" data-seoistic-count="' . esc_attr( $number ) . '">' . esc_html( $number ) . '</span>';
		if ( '' !== $suffix ) {
			echo '<small>' . esc_html( $suffix ) . '</small>';
		}
		echo '</div>';
		echo '<div class="seoistic-card-label">' . esc_html( $label ) . '</div>';
		if ( '' !== $badge ) {
			echo '<div class="seoistic-card-foot"><span class="seoistic-badge ' . esc_attr( $badge_type ) . '">' . esc_html( $badge ) . '</span></div>';
		}
		echo '</div>';
	}

	/**
	 * Score band: 0-49 critical, 50-69 warning, 70-89 good, 90-100 excellent.
	 * "bad"/"warn"/"good" keep their historic names (markup contract); 90+ adds
	 * the excellent tone used by the ring.
	 */
	public static function score_tone( int $score ): string {
		if ( $score >= 80 ) {
			return 'good';
		}
		if ( $score >= 50 ) {
			return 'warn';
		}
		return 'bad';
	}

	/**
	 * Four-band tone used by the ring fill (adds "excellent" at 90+).
	 */
	public static function ring_tone( int $score ): string {
		if ( $score >= 90 ) {
			return 'excellent';
		}
		return self::score_tone( $score );
	}

	/**
	 * Pure CSS/SVG animated score ring. $size: sm|md|lg.
	 */
	public static function score_ring( int $score, string $size = 'md', bool $show_label = true ): string {
		$score         = max( 0, min( 100, $score ) );
		$tone          = self::ring_tone( $score );
		$radius        = 'sm' === $size ? 14 : ( 'lg' === $size ? 56 : 22 );
		$stroke        = 'sm' === $size ? 3 : ( 'lg' === $size ? 10 : 6 );
		$box           = ( $radius + $stroke ) * 2;
		$circumference = 2 * M_PI * $radius;
		$offset        = $circumference * ( 1 - $score / 100 );

		$label = $show_label ? '<span class="seoistic-ring-label">' . (int) $score . ( 'lg' === $size ? '<small>' . esc_html__( '/ 100', 'seoistic' ) . '</small>' : '' ) . '</span>' : '';

		return sprintf(
			'<span class="seoistic-ring seoistic-ring-%1$s is-%2$s" role="img" aria-label="%3$s">'
			. '<svg width="%4$d" height="%4$d" viewBox="0 0 %4$d %4$d" aria-hidden="true">'
			. '<circle class="seoistic-ring-track" cx="%5$d" cy="%5$d" r="%6$d" style="stroke-width:%7$d"></circle>'
			. '<circle class="seoistic-ring-fill" cx="%5$d" cy="%5$d" r="%6$d" style="stroke-width:%7$d;stroke-dasharray:%8$F;stroke-dashoffset:%9$F"></circle>'
			. '</svg>%10$s</span>',
			esc_attr( $size ),
			esc_attr( $tone ),
			esc_attr(
				/* translators: %d: SEO score out of 100. */
				sprintf( __( 'SEO score %d out of 100', 'seoistic' ), $score )
			),
			$box,
			$box / 2,
			$radius,
			$stroke,
			$circumference,
			$offset,
			$label
		);
	}

	public static function badge( string $text, string $type = 'neutral' ): string {
		return '<span class="seoistic-badge ' . esc_attr( $type ) . '">' . esc_html( $text ) . '</span>';
	}

	/**
	 * Consistent empty state: icon, title, explanation, optional action button.
	 */
	public static function empty_state( string $icon, string $title, string $message, string $action_label = '', string $action_url = '' ): void {
		echo '<div class="seoistic-empty">';
		echo '<span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
		echo '<p class="seoistic-empty-title">' . esc_html( $title ) . '</p>';
		echo '<p>' . esc_html( $message ) . '</p>';
		if ( '' !== $action_label && '' !== $action_url ) {
			echo '<a class="seoistic-btn seoistic-btn-primary" href="' . esc_url( $action_url ) . '">' . esc_html( $action_label ) . '</a>';
		}
		echo '</div>';
	}
}
