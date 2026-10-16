<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$assert_contains = static function ( string $needle, string $haystack ): void {
	if ( false === strpos( $haystack, $needle ) ) {
		throw new RuntimeException( 'Expected email output to contain: ' . $needle );
	}
};

$html = Wpistic\Seoistic\Core\EmailReportTemplate::render(
	'<Report>',
	'Preview',
	'Intro',
	'<p>Details</p>',
	'Open report',
	'https://example.com/wp-admin/admin.php?page=report'
);
$assert_contains( '&lt;Report&gt;', $html );
$assert_contains( 'bgcolor="#00C857"', $html );
$assert_contains( 'min-height:44px', $html );

$report = new Wpistic\Seoistic\RankTracker\RankTrackerReport(
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
$rank_email = $report->html( false );
foreach ( array( 'Example Tours', 'Tracked keywords', 'Average position', 'Open Rank Tracker' ) as $needle ) {
	$assert_contains( $needle, $rank_email );
}

echo "PASS branded email template and rank report smoke checks\n";
