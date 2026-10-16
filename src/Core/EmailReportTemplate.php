<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core;

/**
 * Shared, email-client-safe presentation for SEOistic reports and notices.
 */
final class EmailReportTemplate {

	/**
	 * Render a branded report email with a single-column layout and a clear CTA.
	 * Content should be built from escaped values and trusted plugin markup.
	 */
	public static function render( string $title, string $preheader, string $intro, string $content, string $button_label, string $button_url, array $branding = array() ): string {
		$brand_label  = sanitize_text_field( (string) ( $branding['brand_label'] ?? __( 'SEOISTIC REPORT', 'seoistic' ) ) );
		$accent_color = sanitize_hex_color( (string) ( $branding['accent_color'] ?? '#00C857' ) ) ?: '#00C857';
		$logo_url     = esc_url( (string) ( $branding['logo_url'] ?? '' ) );
		$logo         = '' !== $logo_url ? '<img src="' . $logo_url . '" alt="' . esc_attr( $brand_label ) . '" style="display:block;max-width:180px;max-height:40px;width:auto;height:auto;margin-bottom:8px">' : '';
		$button = '';
		if ( '' !== $button_label && '' !== $button_url ) {
			$button = '<tr><td style="padding:8px 24px 24px"><table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td bgcolor="' . esc_attr( $accent_color ) . '" style="border-radius:6px"><a role="button" href="' . esc_url( $button_url ) . '" style="display:inline-block;min-height:44px;padding:0 20px;line-height:44px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;border-radius:6px">' . esc_html( $button_label ) . '</a></td></tr></table></td></tr>';
		}

		return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $title ) . '</title></head><body style="margin:0;padding:24px 12px;background:#f2f4f7;color:#172033;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.55"><span style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden">' . esc_html( $preheader ) . '</span><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e2e7ef;border-radius:8px;overflow:hidden"><tr><td style="padding:18px 24px;background:#0F2044;color:#ffffff"><div style="font-size:11px;font-weight:700;letter-spacing:.08em;color:' . esc_attr( $accent_color ) . '">' . esc_html( $brand_label ) . '</div>' . $logo . '<div style="padding-top:3px;font-size:20px;font-weight:700;line-height:1.3">' . esc_html( $title ) . '</div></td></tr><tr><td style="padding:20px 24px 8px;color:#334155">' . esc_html( $intro ) . '</td></tr><tr><td style="padding:8px 24px 16px;color:#172033">' . wp_kses_post( $content ) . '</td></tr>' . $button . '<tr><td style="padding:12px 24px;border-top:1px solid #e2e7ef;color:#64748b;font-size:11px">Sent by SEOistic for ' . esc_html( get_bloginfo( 'name' ) ) . '. Manage SEOistic settings in your WordPress dashboard.</td></tr></table></body></html>';
	}

	/** Standard HTML header used by all SEOistic email notifications. */
	public static function headers(): array {
		return array( 'Content-Type: text/html; charset=UTF-8' );
	}
}
