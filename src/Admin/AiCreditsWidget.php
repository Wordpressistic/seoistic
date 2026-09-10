<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Admin;

use Wpistic\Seoistic\AI\AiSettings;
use Wpistic\Seoistic\Core\AI\WpisticAiClient;
use Wpistic\Seoistic\License\Plans;
use Wpistic\Seoistic\Module\Entitlement;

/**
 * Compact, shared AI credit summary. The server snapshot is authoritative when
 * available; the last 20 records let users see cache hits immediately.
 */
final class AiCreditsWidget {

	public static function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$snapshot = WpisticAiClient::server_snapshot();
		$usage    = WpisticAiClient::usage_snapshot();
		$recent   = is_array( $snapshot['recent'] ?? null ) ? (array) $snapshot['recent'] : (array) ( $usage['recent'] ?? array() );
		$left     = isset( $snapshot['left'] ) ? (int) $snapshot['left'] : null;
		$plan     = ucfirst( Plans::normalize_plan( (string) ( $snapshot['plan'] ?? Entitlement::plan() ) ) );
		?>
		<section class="seoistic-ai-credits" aria-label="<?php esc_attr_e( 'AI credits', 'seoistic' ); ?>">
			<div class="seoistic-ai-credits-head">
				<span class="dashicons dashicons-superhero" aria-hidden="true"></span>
				<strong><?php esc_html_e( 'AI Credits', 'seoistic' ); ?></strong>
			</div>
			<p class="seoistic-ai-credits-left">
				<?php if ( null === $left ) : ?>
					<?php esc_html_e( 'Credits left this month: available after your first AI request', 'seoistic' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Credits left this month:', 'seoistic' ); ?>
					<strong><?php echo esc_html( number_format_i18n( $left ) ); ?></strong>
				<?php endif; ?>
				<span><?php esc_html_e( '· resets on the 1st', 'seoistic' ); ?></span>
			</p>
			<?php if ( AiSettings::is_custom_configured() ) : ?>
				<p class="seoistic-ai-credits-mode"><?php esc_html_e( 'Custom model active · unmetered', 'seoistic' ); ?></p>
			<?php else : ?>
				<p class="seoistic-ai-credits-mode">
					<?php
					/* translators: %s: plan name. */
					echo esc_html( sprintf( __( 'Plan: %s', 'seoistic' ), $plan ) );
					?>
				</p>
			<?php endif; ?>
			<div class="seoistic-ai-usage" id="seoistic-ai-usage">
				<h3><?php esc_html_e( 'Last 20 AI uses', 'seoistic' ); ?></h3>
				<?php if ( empty( $usage['recent'] ) ) : ?>
					<p><?php esc_html_e( 'No AI requests yet.', 'seoistic' ); ?></p>
				<?php else : ?>
					<ul>
						<?php foreach ( array_reverse( array_slice( $recent, -20 ) ) as $record ) : ?>
							<li>
								<span><?php echo esc_html( (string) ( $record['task'] ?? '' ) ); ?></span>
								<span>
									<?php if ( ! empty( $record['unmetered'] ) ) : ?>
										<?php esc_html_e( '0 credits · custom', 'seoistic' ); ?>
									<?php else : ?>
										<?php
										/* translators: %d: credit count. */
										echo esc_html( sprintf( _n( '%d credit', '%d credits', (int) ( $record['credits'] ?? 0 ), 'seoistic' ), (int) ( $record['credits'] ?? 0 ) ) );
										?>
									<?php endif; ?>
								</span>
								<time><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) ( $record['created_at'] ?? time() ) ) ); ?></time>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
}

