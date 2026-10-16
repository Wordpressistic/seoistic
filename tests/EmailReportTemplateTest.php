<?php

use Wpistic\Seoistic\Core\EmailReportTemplate;
use Wpistic\Seoistic\RankTracker\RankTrackerReport;

final class EmailReportTemplateTest extends PHPUnit\Framework\TestCase {

	public function testTemplateEscapesCopyAndUsesBrandedButton(): void {
		$html = EmailReportTemplate::render(
			'<Weekly report>',
			'Preview copy',
			'Intro copy',
			'<p>Report details</p>',
			'Open report',
			'https://example.com/wp-admin/admin.php?page=seoistic-report'
		);

		$this->assertStringContainsString( '&lt;Weekly report&gt;', $html );
		$this->assertStringContainsString( 'bgcolor="#00C857"', $html );
		$this->assertStringContainsString( 'min-height:44px', $html );
		$this->assertStringContainsString( 'Open report', $html );
		$this->assertStringContainsString( 'https://example.com/wp-admin/admin.php?page=seoistic-report', $html );
		$this->assertSame( array( 'Content-Type: text/html; charset=UTF-8' ), EmailReportTemplate::headers() );
	}

	public function testRankReportEmailIncludesSummaryAndButtonRatherThanBareDashboardUrl(): void {
		$report = new RankTrackerReport(
			array(
				array(
					'keyword' => array( 'keyword' => 'laos tours', 'locale' => 'en-US', 'device' => 'desktop' ),
					'current' => 3.0,
					'change' => 2.0,
					'source_label' => 'WPistic Search API',
				),
			),
			array( 'brand_name' => 'Example Tours' )
		);
		$html = $report->html( false );

		$this->assertStringContainsString( 'Example Tours', $html );
		$this->assertStringContainsString( 'Tracked keywords', $html );
		$this->assertStringContainsString( 'Average position', $html );
		$this->assertStringContainsString( 'Open Rank Tracker', $html );
		$this->assertStringContainsString( 'href="https://example.com/wp-admin/admin.php?page=seoistic-rank-tracker"', $html );
		$this->assertStringNotContainsString( '>https://example.com/wp-admin/admin.php?page=seoistic-rank-tracker<', $html );
	}
}
