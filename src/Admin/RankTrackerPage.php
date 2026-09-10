<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Admin;

use Wpistic\Seoistic\RankTracker\RankTrackerReport;
use Wpistic\Seoistic\RankTracker\RankTrackerRepository;
use Wpistic\Seoistic\RankTracker\RankTrackerService;

final class RankTrackerPage {

	private RankTrackerService $service;

	public function __construct( ?RankTrackerService $service = null ) {
		$this->service = $service ?? new RankTrackerService();
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 34 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_seoistic_save_rank_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_seoistic_save_rank_keyword', array( $this, 'save_keyword' ) );
		add_action( 'admin_post_seoistic_delete_rank_keyword', array( $this, 'delete_keyword' ) );
		add_action( 'admin_post_seoistic_run_rank_tracker', array( $this, 'run_now' ) );
		add_action( 'admin_post_seoistic_send_rank_report', array( $this, 'send_report' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'seoistic',
			__( 'Rank Tracker', 'seoistic' ),
			__( 'Rank Tracker', 'seoistic' ),
			'manage_options',
			'seoistic-rank-tracker',
			array( $this, 'render' )
		);
	}

	public function assets( string $hook ): void {
		if ( false === strpos( $hook, 'seoistic-rank-tracker' ) ) {
			return;
		}
		wp_enqueue_script( 'seoistic-rank-tracker', SEOISTIC_URL . 'assets/js/rank-tracker.js', array( 'seoistic-admin' ), SEOISTIC_VERSION, true );
		wp_localize_script(
			'seoistic-rank-tracker',
			'SeoisticRankTracker',
			array(
				'rows' => $this->service->table_rows( $this->current_locale(), $this->current_device() ),
				'i18n' => array(
					'showHistory' => __( 'Show history', 'seoistic' ),
					'hideHistory' => __( 'Hide history', 'seoistic' ),
					'noHistory' => __( 'No position history yet.', 'seoistic' ),
					'printUnavailable' => __( 'Open the report preview in a new tab to print it.', 'seoistic' ),
					'improved' => __( 'Improved', 'seoistic' ),
					'declined' => __( 'Declined', 'seoistic' ),
					'unchanged' => __( 'Unchanged', 'seoistic' ),
				),
			)
		);
	}

	public function save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_rank_settings' ) ) {
			wp_die( esc_html__( 'You are not allowed to change Rank Tracker settings.', 'seoistic' ) );
		}
		RankTrackerService::save_settings( $_POST );
		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-rank-tracker&updated=settings' ) );
		exit;
	}

	public function save_keyword(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_rank_keyword' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage tracked keywords.', 'seoistic' ) );
		}
		$repository = new RankTrackerRepository();
		$repository->save_keyword(
			(string) wp_unslash( (string) ( $_POST['keyword'] ?? '' ) ),
			(string) wp_unslash( (string) ( $_POST['locale'] ?? 'en-US' ) ),
			(string) wp_unslash( (string) ( $_POST['device'] ?? 'desktop' ) ),
			 RankTrackerService::settings()['mode']
		);
		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-rank-tracker&updated=keyword' ) );
		exit;
	}

	public function delete_keyword(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_rank_keyword' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage tracked keywords.', 'seoistic' ) );
		}
		( new RankTrackerRepository() )->delete_keyword( absint( $_POST['id'] ?? 0 ) );
		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-rank-tracker&updated=deleted' ) );
		exit;
	}

	public function run_now(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_run_rank_tracker' ) ) {
			wp_die( esc_html__( 'You are not allowed to run rank checks.', 'seoistic' ) );
		}
		$result = $this->service->run_daily( true );
		$args = array( 'page' => 'seoistic-rank-tracker', 'updated' => is_wp_error( $result ) ? 'run-error' : 'run' );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function send_report(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_send_rank_report' ) ) {
			wp_die( esc_html__( 'You are not allowed to send rank reports.', 'seoistic' ) );
		}
		$sent = $this->service->send_weekly_report( RankTrackerService::settings()['report_email'] );
		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-rank-tracker&updated=' . ( $sent ? 'report-sent' : 'report-error' ) ) );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access Rank Tracker.', 'seoistic' ) );
		}
		$settings = RankTrackerService::settings();
		$rows = $this->service->table_rows( $this->current_locale(), $this->current_device() );
		View::header( 'seoistic-rank-tracker', __( 'Rank Tracker', 'seoistic' ), __( 'Track keyword movement and deliver white-label client reports.', 'seoistic' ) );
		$this->notice();
		$this->render_toolbar( $settings );
		$this->render_table( $rows );
		$this->render_report( $rows, $settings );
		$this->render_settings( $settings );
		View::footer();
	}

	private function notice(): void {
		$message = (string) ( $_GET['updated'] ?? '' );
		$messages = array(
			'settings' => __( 'Rank Tracker settings saved.', 'seoistic' ),
			'keyword' => __( 'Keyword saved.', 'seoistic' ),
			'deleted' => __( 'Keyword deleted.', 'seoistic' ),
			'run' => __( 'Rank batch completed.', 'seoistic' ),
			'run-error' => __( 'The rank batch could not finish. Check the license or connection and try again.', 'seoistic' ),
			'report-sent' => __( 'Report email sent.', 'seoistic' ),
			'report-error' => __( 'Report email could not be sent.', 'seoistic' ),
		);
		if ( isset( $messages[ $message ] ) ) {
			echo '<div class="notice notice-' . ( str_contains( $message, 'error' ) ? 'error' : 'success' ) . ' is-dismissible"><p>' . esc_html( $messages[ $message ] ) . '</p></div>';
		}
	}

	private function render_toolbar( array $settings ): void {
		$action = admin_url( 'admin-post.php' );
		echo '<div class="seoistic-rank-toolbar">';
		echo '<form class="seoistic-rank-filter" method="get"><input type="hidden" name="page" value="seoistic-rank-tracker">';
		echo '<label for="seoistic-rank-locale">' . esc_html__( 'Locale', 'seoistic' ) . '</label><input id="seoistic-rank-locale" name="locale" type="text" value="' . esc_attr( $this->current_locale() ) . '">';
		echo '<label for="seoistic-rank-device">' . esc_html__( 'Device', 'seoistic' ) . '</label><select id="seoistic-rank-device" name="device"><option value="">' . esc_html__( 'All', 'seoistic' ) . '</option><option value="desktop"' . selected( 'desktop', $this->current_device(), false ) . '>' . esc_html__( 'Desktop', 'seoistic' ) . '</option><option value="mobile"' . selected( 'mobile', $this->current_device(), false ) . '>' . esc_html__( 'Mobile', 'seoistic' ) . '</option></select>';
		echo '<button class="seoistic-btn seoistic-btn-sm" type="submit">' . esc_html__( 'Filter', 'seoistic' ) . '</button></form><div class="seoistic-rank-actions">';
		echo '<form method="post" action="' . esc_url( $action ) . '">' . wp_nonce_field( 'seoistic_run_rank_tracker' ) . '<input type="hidden" name="action" value="seoistic_run_rank_tracker"><button class="seoistic-btn seoistic-btn-primary" type="submit">' . esc_html__( 'Run batch now', 'seoistic' ) . '</button></form>';
		echo '<form method="post" action="' . esc_url( $action ) . '">' . wp_nonce_field( 'seoistic_send_rank_report' ) . '<input type="hidden" name="action" value="seoistic_send_rank_report"><button class="seoistic-btn" type="submit">' . esc_html__( 'Email report', 'seoistic' ) . '</button></form>';
		echo '</div></div>';
		echo '<p class="description">' . esc_html( 'gsc' === $settings['mode'] ? __( 'Current mode: Search Console data (delayed).', 'seoistic' ) : __( 'Current mode: WPistic Search API.', 'seoistic' ) ) . '</p>';
	}

	private function render_table( array $rows ): void {
		echo '<div class="seoistic-table-wrap seoistic-rank-table"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Keyword', 'seoistic' ) . '</th><th>' . esc_html__( 'Locale', 'seoistic' ) . '</th><th>' . esc_html__( 'Device', 'seoistic' ) . '</th><th>' . esc_html__( 'Position', 'seoistic' ) . '</th><th>' . esc_html__( 'Movement', 'seoistic' ) . '</th><th>' . esc_html__( '30-day trend', 'seoistic' ) . '</th><th>' . esc_html__( 'Data source', 'seoistic' ) . '</th><th><span class="screen-reader-text">' . esc_html__( 'Actions', 'seoistic' ) . '</span></th></tr></thead><tbody>';
		if ( array() === $rows ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No keywords match this filter yet.', 'seoistic' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$keyword = (array) $row['keyword'];
			$id = (int) $keyword['id'];
			$change = $row['change'];
			$badge = null === $change ? 'neutral' : ( $change > 0 ? 'good' : ( $change < 0 ? 'bad' : 'neutral' ) );
			$label = null === $change ? '—' : ( $change > 0 ? '▲ ' : ( $change < 0 ? '▼ ' : '' ) ) . number_format_i18n( abs( (float) $change ), 1 );
			echo '<tr data-rank-row="' . esc_attr( (string) $id ) . '"><td><strong>' . esc_html( (string) $keyword['keyword'] ) . '</strong></td><td>' . esc_html( (string) $keyword['locale'] ) . '</td><td>' . esc_html( (string) $keyword['device'] ) . '</td><td class="seoistic-rank-current">' . ( null === $row['current'] ? '—' : esc_html( number_format_i18n( (float) $row['current'], 1 ) ) ) . '</td><td><span class="seoistic-badge ' . esc_attr( $badge ) . '">' . esc_html( (string) $label ) . '</span></td><td><svg class="seoistic-sparkline" data-rank-sparkline="' . esc_attr( (string) $id ) . '" viewBox="0 0 96 24" role="img" aria-label="' . esc_attr__( 'Position trend', 'seoistic' ) . '"></svg></td><td>' . esc_html( (string) $row['source_label'] ) . '</td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'seoistic_rank_keyword' ) . '<input type="hidden" name="action" value="seoistic_delete_rank_keyword"><input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '"><button class="seoistic-btn seoistic-btn-danger seoistic-btn-sm" type="submit">' . esc_html__( 'Delete', 'seoistic' ) . '</button></form>';
			echo '<button class="seoistic-btn seoistic-btn-sm" type="button" data-rank-history-toggle="' . esc_attr( (string) $id ) . '" aria-expanded="false">' . esc_html__( 'History', 'seoistic' ) . '</button></td></tr>';
			echo '<tr class="seoistic-rank-history" data-rank-history="' . esc_attr( (string) $id ) . '" hidden><td colspan="8"><figure><figcaption>' . esc_html( (string) $keyword['keyword'] ) . '</figcaption><svg data-rank-chart="' . esc_attr( (string) $id ) . '" viewBox="0 0 640 180" role="img" aria-label="' . esc_attr__( 'Position history', 'seoistic' ) . '"></svg></figure></td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<form class="seoistic-rank-add" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'seoistic_rank_keyword' ) . '<input type="hidden" name="action" value="seoistic_save_rank_keyword">';
		echo '<label for="seoistic-new-keyword">' . esc_html__( 'Keyword', 'seoistic' ) . '</label><input id="seoistic-new-keyword" name="keyword" type="text" required>';
		echo '<label for="seoistic-new-locale">' . esc_html__( 'Locale', 'seoistic' ) . '</label><input id="seoistic-new-locale" name="locale" type="text" value="en-US">';
		echo '<label for="seoistic-new-device">' . esc_html__( 'Device', 'seoistic' ) . '</label><select id="seoistic-new-device" name="device"><option value="desktop">' . esc_html__( 'Desktop', 'seoistic' ) . '</option><option value="mobile">' . esc_html__( 'Mobile', 'seoistic' ) . '</option></select>';
		echo '<button class="seoistic-btn seoistic-btn-primary" type="submit">' . esc_html__( 'Track keyword', 'seoistic' ) . '</button></form>';
	}

	private function render_report( array $rows, array $settings ): void {
		$html = ( new RankTrackerReport( $rows, $settings ) )->html( true );
		echo '<div class="seoistic-section-title">' . esc_html__( 'White-label report', 'seoistic' ) . '</div>';
		echo '<div class="seoistic-report-preview"><div class="seoistic-report-preview-bar"><button class="seoistic-btn seoistic-btn-primary" type="button" data-rank-report-print>' . esc_html__( 'Print to PDF', 'seoistic' ) . '</button><span>' . esc_html__( 'Use your browser’s destination picker to save as PDF.', 'seoistic' ) . '</span></div><iframe title="' . esc_attr__( 'SEO rank report preview', 'seoistic' ) . '" data-rank-report-frame srcdoc="' . esc_attr( $html ) . '"></iframe></div>';
	}

	private function render_settings( array $settings ): void {
		echo '<div class="seoistic-section-title">' . esc_html__( 'Tracking & reporting', 'seoistic' ) . '</div><div class="seoistic-rank-settings">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'seoistic_rank_settings' ) . '<input type="hidden" name="action" value="seoistic_save_rank_settings">';
		echo '<label><input type="checkbox" name="enabled"' . checked( $settings['enabled'], true, false ) . '> ' . esc_html__( 'Enable daily checks', 'seoistic' ) . '</label>';
		echo '<label><input type="radio" name="mode" value="search_api"' . checked( $settings['mode'], 'search_api', false ) . '> ' . esc_html__( 'WPistic Search API', 'seoistic' ) . '</label>';
		echo '<label><input type="radio" name="mode" value="gsc"' . checked( $settings['mode'], 'gsc', false ) . '> ' . esc_html__( 'Search Console data (delayed)', 'seoistic' ) . '</label>';
		echo '<label>' . esc_html__( 'Batch size', 'seoistic' ) . '<input type="number" name="batch_size" min="1" max="50" value="' . esc_attr( (string) $settings['batch_size'] ) . '"></label>';
		echo '<label><input type="checkbox" name="report_enabled"' . checked( $settings['report_enabled'], true, false ) . '> ' . esc_html__( 'Send weekly HTML email', 'seoistic' ) . '</label>';
		echo '<label>' . esc_html__( 'Recipient', 'seoistic' ) . '<input type="email" name="report_email" value="' . esc_attr( $settings['report_email'] ) . '"></label>';
		echo '<label>' . esc_html__( 'Brand name', 'seoistic' ) . '<input type="text" name="brand_name" value="' . esc_attr( $settings['brand_name'] ) . '"></label>';
		echo '<label>' . esc_html__( 'Logo URL', 'seoistic' ) . '<input type="url" name="logo_url" value="' . esc_attr( $settings['logo_url'] ) . '"></label>';
		echo '<label>' . esc_html__( 'Primary color', 'seoistic' ) . '<input type="color" name="primary_color" value="' . esc_attr( $settings['primary_color'] ) . '"></label>';
		echo '<button class="seoistic-btn seoistic-btn-primary" type="submit">' . esc_html__( 'Save settings', 'seoistic' ) . '</button></form></div>';
	}

	private function current_locale(): string {
		$locale = (string) wp_unslash( (string) ( $_GET['locale'] ?? '' ) );
		return ( new RankTrackerRepository() )->sanitize_locale( $locale );
	}

	private function current_device(): string {
		$device = sanitize_key( (string) wp_unslash( (string) ( $_GET['device'] ?? '' ) ) );
		return in_array( $device, array( 'desktop', 'mobile' ), true ) ? $device : '';
	}
}
